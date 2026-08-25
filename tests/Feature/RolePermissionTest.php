<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RolePermissionTest extends TestCase
{
    use DatabaseTransactions;

    public function test_admin_can_manage_a_custom_role_and_permissions(): void
    {
        $admin = User::query()->where('email', config('pos.admin_email'))->firstOrFail();
        $admin->update(['must_change_password' => false]);
        Sanctum::actingAs($admin);
        $permission = Permission::query()->where('permission_key', 'products.view')->firstOrFail();

        $createResponse = $this->postJson('/v1/roles', [
            'name' => 'Stock Viewer',
            'permission_ids' => [$permission->id],
        ])->assertCreated()->assertJsonPath('data.name', 'Stock Viewer');

        $role = Role::query()->findOrFail($createResponse->json('data.id'));
        $this->assertTrue($role->permissions()->whereKey($permission->id)->exists());
        $createAudit = AuditLog::query()
            ->where('action', 'create')
            ->where('entity_type', Role::class)
            ->where('entity_id', $role->id)
            ->firstOrFail();

        $this->assertNull($createAudit->old_values);
        $this->assertSame('Stock Viewer', $createAudit->new_values['role_name']);

        $this->getJson("/v1/roles/{$role->id}")->assertOk();
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'view',
            'entity_type' => Role::class,
            'entity_id' => $role->id,
        ]);

        $this->patchJson("/v1/roles/{$role->id}", [
            'name' => 'Stock Auditor',
            'permission_ids' => [$permission->id],
        ])->assertOk()->assertJsonPath('data.name', 'Stock Auditor');

        $updateAudit = AuditLog::query()
            ->where('action', 'update')
            ->where('entity_type', Role::class)
            ->where('entity_id', $role->id)
            ->firstOrFail();

        $this->assertSame(['role_name' => 'Stock Viewer'], $updateAudit->old_values);
        $this->assertSame(['role_name' => 'Stock Auditor'], $updateAudit->new_values);

        $this->deleteJson("/v1/roles/{$role->id}")->assertNoContent();
        $this->assertTrue(AuditLog::query()
            ->where('action', 'delete')
            ->where('entity_type', Role::class)
            ->where('entity_id', $role->id)
            ->exists());
    }

    public function test_system_role_cannot_be_deleted(): void
    {
        $admin = User::query()->where('email', config('pos.admin_email'))->firstOrFail();
        $admin->update(['must_change_password' => false]);
        Sanctum::actingAs($admin);
        $role = Role::query()->where('role_name', 'Cashier')->firstOrFail();

        $this->deleteJson("/v1/roles/{$role->id}")->assertUnprocessable();
    }
}
