<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $roles = Role::query()->whereIn('role_name', ['Admin', 'Manager', 'Cashier'])->get()->keyBy('role_name');
        $cashierPermissions = [
            'sales.create', 'sales.view', 'customers.view', 'customers.create',
            'products.view', 'shifts.open', 'shifts.close',
        ];
        $managerExcludedPermissions = ['users.delete', 'roles.delete', 'settings.edit', 'audit_logs.view'];

        $roles['Admin']->permissions()->sync(Permission::query()->pluck('id'));
        $roles['Manager']->permissions()->sync(
            Permission::query()->whereNotIn('permission_key', $managerExcludedPermissions)->pluck('id'),
        );
        $roles['Cashier']->permissions()->sync(
            Permission::query()->whereIn('permission_key', $cashierPermissions)->pluck('id'),
        );
    }
}
