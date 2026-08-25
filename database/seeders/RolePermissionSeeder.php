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
        $permissions = Permission::query()->get()->keyBy('permission_key');

        $roles['Admin']->permissions()->sync($permissions->pluck('id'));
        $roles['Manager']->permissions()->sync(
            $permissions->except(['users.delete', 'roles.delete', 'settings.edit'])->pluck('id'),
        );
        $roles['Cashier']->permissions()->sync(
            $permissions->only([
                'sales.create', 'sales.view', 'customers.view', 'customers.create',
                'products.view', 'shifts.open', 'shifts.close',
            ])->pluck('id'),
        );
    }
}
