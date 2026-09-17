<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Permission;
use App\Models\PurchaseOrder;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AccessManagementTest extends TestCase
{
    use DatabaseTransactions;

    public function test_roles_and_all_seeded_permissions_have_arabic_metadata_without_changing_keys(): void
    {
        $this->loginAs('Admin');
        $response = $this->getJson('/v1/roles')->assertOk();
        $roles = collect($response->json('data'))->keyBy('name');
        $this->assertSame('مدير النظام', $roles['Admin']['name_ar']);
        $this->assertSame('أمين الصندوق', $roles['Cashier']['name_ar']);
        $permissions = collect($this->getJson('/v1/permissions')->assertOk()->json('data'))->flatten(1);
        $this->assertCount(Permission::query()->count(), $permissions);
        foreach ($permissions as $permission) {
            $this->assertMatchesRegularExpression('/[\x{0600}-\x{06FF}]/u', $permission['name_ar']);
            $this->assertNotEmpty($permission['description_ar']);
            $this->assertDatabaseHas('permissions', ['id' => $permission['id'], 'permission_key' => $permission['key']]);
        }
    }

    public function test_manager_cannot_assign_admin_or_reset_an_admin_password(): void
    {
        $manager = $this->loginAs('Manager');
        $admin = User::query()->where('email', config('pos.admin_email'))->firstOrFail();
        $adminRole = Role::query()->where('role_name', 'Admin')->firstOrFail();
        $cashier = $this->createTestUser();
        $cashier->assignRole('Cashier');
        $count = AuditLog::query()->count();
        foreach ([$manager, $cashier] as $target) {
            $this->patchJson('/v1/users/'.$target->id, ['role_ids' => [$adminRole->id]])->assertForbidden();
        }
        $this->postJson('/v1/users', $this->userPayload($adminRole->id))->assertForbidden();
        $this->patchJson('/v1/users/'.$admin->id, ['password' => 'Password!123', 'password_confirmation' => 'Password!123'])->assertForbidden();
        $this->assertSame($count, AuditLog::query()->count());
        $this->assertFalse($manager->refresh()->hasRole('Admin'));
    }

    public function test_manager_cannot_grant_unowned_permissions_or_modify_system_roles(): void
    {
        $this->loginAs('Manager');
        $forbidden = Permission::query()->where('permission_key', 'settings.edit')->firstOrFail();
        $this->postJson('/v1/roles', ['name' => 'Escalation', 'permission_ids' => [$forbidden->id]])->assertForbidden();
        foreach (['Admin', 'Manager', 'Cashier'] as $name) {
            $role = Role::query()->where('role_name', $name)->firstOrFail();
            $this->patchJson('/v1/roles/'.$role->id, ['permission_ids' => [$forbidden->id]])->assertForbidden();
        }
        $custom = Role::factory()->create();
        $this->patchJson('/v1/roles/'.$custom->id, ['permission_ids' => [$forbidden->id]])->assertForbidden();
    }

    public function test_manager_can_still_manage_users_and_custom_roles_within_their_permissions(): void
    {
        $this->loginAs('Manager');
        $cashierRole = Role::query()->where('role_name', 'Cashier')->firstOrFail();
        $userId = $this->postJson('/v1/users', $this->userPayload($cashierRole->id))->assertCreated()->json('data.id');
        $this->patchJson('/v1/users/'.$userId, ['name' => 'Updated user'])->assertOk();
        $permission = Permission::query()->where('permission_key', 'products.view')->firstOrFail();
        $roleId = $this->postJson('/v1/roles', ['name' => 'Warehouse Reader', 'permission_ids' => [$permission->id]])
            ->assertCreated()->assertJsonPath('data.name_ar', 'Warehouse Reader')->json('data.id');
        $this->patchJson('/v1/roles/'.$roleId, ['name' => 'Warehouse Viewer'])->assertOk();
    }

    public function test_unowned_custom_role_cannot_be_assigned_or_its_users_taken_over(): void
    {
        $this->loginAs('Manager');
        $role = Role::factory()->create();
        $role->permissions()->attach(Permission::query()->where('permission_key', 'settings.edit')->firstOrFail());
        $target = $this->createTestUser();
        $target->assignRole($role);
        $this->postJson('/v1/users', $this->userPayload($role->id))->assertForbidden();
        $this->patchJson('/v1/users/'.$target->id, ['email' => 'takeover@example.com'])->assertForbidden();
        $this->patchJson('/v1/roles/'.$role->id, ['name' => 'Takeover'])->assertForbidden();
    }

    public function test_last_admin_cannot_be_demoted_or_delete_their_current_account(): void
    {
        $admin = User::query()->where('email', config('pos.admin_email'))->firstOrFail();
        $admin->update(['must_change_password' => false]);
        Sanctum::actingAs($admin);
        $cashierRole = Role::query()->where('role_name', 'Cashier')->firstOrFail();
        $this->patchJson('/v1/users/'.$admin->id, ['role_ids' => [$cashierRole->id]])
            ->assertUnprocessable()->assertJsonValidationErrors('role_ids');
        $this->deleteJson('/v1/users/'.$admin->id)->assertUnprocessable();
        $this->assertTrue($admin->refresh()->hasRole('Admin'));
    }

    public function test_admin_can_change_roles_and_audit_records_the_actual_new_role(): void
    {
        $this->loginAs('Admin');
        $target = $this->createTestUser();
        $target->assignRole('Cashier');
        $oldRole = Role::query()->where('role_name', 'Cashier')->firstOrFail();
        $newRole = Role::query()->where('role_name', 'Admin')->firstOrFail();
        $this->patchJson('/v1/users/'.$target->id, ['role_ids' => [$newRole->id]])->assertOk();
        $audit = AuditLog::query()->where('action', 'update')->where('entity_id', $target->id)->firstOrFail();
        $this->assertSame([$oldRole->id], $audit->old_values['role_ids']);
        $this->assertSame([$newRole->id], $audit->new_values['role_ids']);
        $this->assertTrue($target->refresh()->hasRole('Admin'));
    }

    public function test_another_admin_can_be_demoted_but_the_remaining_admin_is_protected(): void
    {
        $actor = $this->loginAs('Admin');
        $target = User::query()->where('email', config('pos.admin_email'))->firstOrFail();
        $cashierRole = Role::query()->where('role_name', 'Cashier')->firstOrFail();
        $this->patchJson('/v1/users/'.$target->id, ['role_ids' => [$cashierRole->id]])->assertOk();
        $this->patchJson('/v1/users/'.$actor->id, ['role_ids' => [$cashierRole->id]])->assertUnprocessable();
    }

    public function test_system_role_names_and_deletion_are_protected_even_for_admin(): void
    {
        $this->loginAs('Admin');
        $role = Role::query()->where('role_name', 'Admin')->firstOrFail();
        $this->patchJson('/v1/roles/'.$role->id, ['name' => 'Renamed Admin'])->assertUnprocessable();
        $this->deleteJson('/v1/roles/'.$role->id)->assertUnprocessable();
        $this->patchJson('/v1/roles/'.$role->id, ['permission_ids' => []])->assertOk();
        auth()->user()->unsetRelation('roles');
        $this->getJson('/v1/audit-logs')->assertOk();
    }

    public function test_empty_custom_roles_are_valid_and_missing_permission_lists_are_rejected(): void
    {
        $this->loginAs('Admin');
        $id = $this->postJson('/v1/roles', ['name' => 'No access', 'permission_ids' => []])
            ->assertCreated()->assertJsonCount(0, 'data.permissions')->json('data.id');
        $this->patchJson('/v1/roles/'.$id, ['permission_ids' => []])->assertOk();
        $this->postJson('/v1/roles', ['name' => 'Missing permissions'])->assertUnprocessable();
        $this->patchJson('/v1/roles/'.$id, ['permission_ids' => null])->assertUnprocessable();
    }

    public function test_admin_can_delete_another_admin_and_revoke_the_deleted_users_tokens(): void
    {
        $this->loginAs('Admin');
        $target = $this->createTestUser();
        $target->assignRole('Admin');
        $token = $target->createToken('phase4')->accessToken;
        $this->deleteJson('/v1/users/'.$target->id)->assertNoContent();
        $this->assertModelMissing($target);
        $this->assertModelMissing($token);
        $this->assertDatabaseHas('audit_logs', ['entity_id' => $target->id, 'action' => 'delete']);
    }

    public function test_user_deletion_permission_does_not_allow_deleting_admin_accounts(): void
    {
        $role = Role::factory()->create();
        $role->permissions()->attach(Permission::query()->where('permission_key', 'users.delete')->firstOrFail());
        $actor = $this->createTestUser();
        $actor->assignRole($role);
        Sanctum::actingAs($actor);
        $admin = User::query()->where('email', config('pos.admin_email'))->firstOrFail();
        $this->deleteJson('/v1/users/'.$admin->id)->assertForbidden();
        $this->assertModelExists($admin);
    }

    public function test_purchase_deletion_requires_delete_permission_even_when_edit_is_granted(): void
    {
        $actor = $this->createTestUser();
        $role = Role::factory()->create();
        $role->permissions()->attach(Permission::query()->where('permission_key', 'purchases.edit')->firstOrFail());
        $actor->assignRole($role);
        Sanctum::actingAs($actor);
        $order = PurchaseOrder::factory()->create();
        $this->deleteJson('/v1/purchase-orders/'.$order->id)->assertForbidden();
        $this->assertModelExists($order);
    }

    public function test_all_operational_v1_routes_require_authentication_password_change_check_and_known_permissions(): void
    {
        $sessionRoutes = ['v1/me', 'v1/logout', 'v1/logout-all', 'v1/password/change'];
        $publicRoutes = ['v1/login', 'v1/settings/public'];
        $known = Permission::query()->pluck('permission_key')->all();
        foreach (Route::getRoutes() as $route) {
            if (! str_starts_with($route->uri(), 'v1/') || in_array($route->uri(), $publicRoutes, true)) {
                continue;
            }
            $middleware = $route->gatherMiddleware();
            $this->assertContains('auth:sanctum', $middleware, $route->uri());
            if (in_array($route->uri(), $sessionRoutes, true)) {
                continue;
            }
            $this->assertContains('must_change_password', $middleware, $route->uri());
            $permissions = array_filter($middleware, fn (string $value): bool => str_starts_with($value, 'permission:'));
            $this->assertNotEmpty($permissions, $route->uri());
            foreach ($permissions as $permission) {
                $this->assertContains(substr($permission, strlen('permission:')), $known, $route->uri());
            }
        }
    }

    private function loginAs(string $role): User
    {
        $user = $this->createTestUser();
        $user->assignRole($role);
        Sanctum::actingAs($user);

        return $user;
    }

    /** @return array<string, mixed> */
    private function userPayload(string $roleId): array
    {
        return ['name' => 'New user', 'email' => 'phase4-user@example.com', 'password' => 'Password!123', 'password_confirmation' => 'Password!123', 'role_ids' => [$roleId]];
    }
}
