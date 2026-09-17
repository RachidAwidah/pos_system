<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use DatabaseTransactions;

    public function test_admin_can_create_update_and_delete_a_user(): void
    {
        $admin = User::query()->where('email', config('pos.admin_email'))->firstOrFail();
        $admin->update(['must_change_password' => false]);
        Sanctum::actingAs($admin);
        $cashierRole = Role::query()->where('role_name', 'Cashier')->firstOrFail();

        $createResponse = $this->postJson('/v1/users', [
            'name' => 'POS Cashier',
            'email' => 'cashier.test@example.com',
            'phone' => '555-0100',
            'password' => 'Pass!123',
            'password_confirmation' => 'Pass!123',
            'role_ids' => [$cashierRole->id],
        ])->assertCreated()->assertJsonPath('data.email', 'cashier.test@example.com');

        $userId = $createResponse->json('data.id');
        $createdUser = User::query()->findOrFail($userId);

        $this->assertFalse($createdUser->must_change_password);
        $this->assertTrue($createdUser->hasRole($cashierRole));

        $this->getJson("/v1/users/{$userId}")->assertOk();
        $viewAudit = AuditLog::query()
            ->where('action', 'view')
            ->where('entity_type', User::class)
            ->where('entity_id', $userId)
            ->firstOrFail();
        $this->assertSame(['viewed_id' => $userId], $viewAudit->new_values);

        $this->putJson("/v1/users/{$userId}", [
            'name' => 'Updated Cashier',
            'password' => 'UpdatedPassword!123',
            'password_confirmation' => 'UpdatedPassword!123',
        ])->assertOk()->assertJsonPath('data.name', 'Updated Cashier');

        $this->assertFalse($createdUser->refresh()->must_change_password);

        $updateAudit = AuditLog::query()
            ->where('action', 'update')
            ->where('entity_type', User::class)
            ->where('entity_id', $userId)
            ->firstOrFail();
        $this->assertSame('POS Cashier', $updateAudit->old_values['full_name']);
        $this->assertSame('Updated Cashier', $updateAudit->new_values['full_name']);
        $this->assertFalse($updateAudit->old_values['credentials_changed']);
        $this->assertTrue($updateAudit->new_values['credentials_changed']);
        $this->assertArrayNotHasKey('password_hash', $updateAudit->old_values);
        $this->assertArrayNotHasKey('password_hash', $updateAudit->new_values);

        $this->deleteJson("/v1/users/{$userId}")->assertNoContent();
        $this->assertNull(User::query()->find($userId));
    }

    public function test_admin_can_optionally_require_a_new_user_to_change_password(): void
    {
        $admin = User::query()->where('email', config('pos.admin_email'))->firstOrFail();
        $admin->update(['must_change_password' => false]);
        Sanctum::actingAs($admin);
        $cashierRole = Role::query()->where('role_name', 'Cashier')->firstOrFail();

        $response = $this->postJson('/v1/users', [
            'name' => 'Temporary Cashier',
            'email' => 'temporary.cashier@example.com',
            'password' => 'Password!123',
            'password_confirmation' => 'Password!123',
            'role_ids' => [$cashierRole->id],
            'must_change_password' => true,
        ])->assertCreated()->assertJsonPath('data.must_change_password', true);

        $user = User::query()->findOrFail($response->json('data.id'));
        $this->assertTrue($user->must_change_password);
    }

    public function test_new_user_without_password_change_requirement_can_login_normally(): void
    {
        $admin = User::query()->where('email', config('pos.admin_email'))->firstOrFail();
        $admin->update(['must_change_password' => false]);
        Sanctum::actingAs($admin);
        $cashierRole = Role::query()->where('role_name', 'Cashier')->firstOrFail();

        $this->postJson('/v1/users', [
            'name' => 'Login Cashier',
            'email' => 'login.cashier@example.com',
            'password' => 'Password!123',
            'password_confirmation' => 'Password!123',
            'role_ids' => [$cashierRole->id],
        ])->assertCreated();

        $this->postJson('/v1/login', [
            'email' => 'login.cashier@example.com',
            'password' => 'Password!123',
            'device_name' => 'test-terminal',
        ])->assertOk()
            ->assertJsonPath('user.must_change_password', false)
            ->assertJsonPath('user.roles.0.name', 'Cashier')
            ->assertJsonStructure(['token']);
    }

    public function test_cashier_cannot_access_user_management(): void
    {
        $cashier = User::factory()->create(['must_change_password' => false]);
        $cashier->assignRole('Cashier');
        Sanctum::actingAs($cashier);

        $this->getJson('/v1/users')->assertForbidden();
    }

    public function test_role_is_required_when_creating_a_user(): void
    {
        $admin = User::query()->where('email', config('pos.admin_email'))->firstOrFail();
        $admin->update(['must_change_password' => false]);
        Sanctum::actingAs($admin);

        $this->postJson('/v1/users', [
            'name' => 'No Role User',
            'email' => 'no.role@example.com',
            'password' => 'Password!123',
            'password_confirmation' => 'Password!123',
        ])->assertUnprocessable()->assertJsonValidationErrors('role_ids');
    }

    public function test_only_one_role_can_be_assigned_when_creating_a_user(): void
    {
        $admin = User::query()->where('email', config('pos.admin_email'))->firstOrFail();
        $admin->update(['must_change_password' => false]);
        Sanctum::actingAs($admin);
        $roleIds = Role::query()->whereIn('role_name', ['Manager', 'Cashier'])->pluck('id')->all();

        $this->postJson('/v1/users', [
            'name' => 'Multiple Roles User',
            'email' => 'multiple.roles@example.com',
            'password' => 'Password!123',
            'password_confirmation' => 'Password!123',
            'role_ids' => $roleIds,
        ])->assertUnprocessable()->assertJsonValidationErrors('role_ids');
    }

    public function test_weak_password_is_rejected_when_creating_a_user(): void
    {
        $admin = User::query()->where('email', config('pos.admin_email'))->firstOrFail();
        $admin->update(['must_change_password' => false]);
        Sanctum::actingAs($admin);
        $cashierRole = Role::query()->where('role_name', 'Cashier')->firstOrFail();

        $this->postJson('/v1/users', [
            'name' => 'Weak Password User',
            'email' => 'weak.password@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role_ids' => [$cashierRole->id],
        ])->assertUnprocessable()->assertJsonValidationErrors('password');
    }
}
