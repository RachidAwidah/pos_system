<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'users.view', 'users.create', 'users.edit', 'users.delete',
            'roles.view', 'roles.create', 'roles.edit', 'roles.delete',
            'products.view', 'products.create', 'products.edit', 'products.delete', 'products.edit_price',
            'categories.view', 'categories.create', 'categories.edit', 'categories.delete',
            'sales.view', 'sales.create', 'sales.void', 'sales.return',
            'purchases.view', 'purchases.create', 'purchases.edit', 'purchases.delete',
            'inventory.view', 'inventory.adjust', 'inventory.count',
            'customers.view', 'customers.create', 'customers.edit', 'customers.delete',
            'suppliers.view', 'suppliers.create', 'suppliers.edit', 'suppliers.delete',
            'reports.view_sales', 'reports.view_inventory', 'reports.view_financial',
            'settings.view', 'settings.edit',
            'shifts.open', 'shifts.close',
        ];

        foreach ($permissions as $permissionKey) {
            Permission::query()->firstOrCreate(['permission_key' => $permissionKey]);
        }
    }
}
