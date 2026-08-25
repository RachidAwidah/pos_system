<?php

namespace Database\Seeders;

use App\Enums\ProductType;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\Tax;
use App\Models\Unit;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            PermissionSeeder::class,
            RolePermissionSeeder::class,
            SettingSeeder::class,
            AdminSeeder::class,
            UnitSeeder::class,
            PaymentMethodSeeder::class,
            WarehouseSeeder::class,
            RegisterSeeder::class,
        ]);

        $generalCategory = Category::query()->firstOrCreate(['category_name' => 'General']);
        $foodCategory = Category::query()->firstOrCreate(['category_name' => 'Food']);
        $zeroTax = Tax::query()->firstOrCreate(['tax_name' => 'Tax Exempt'], ['tax_percentage' => 0]);
        $standardTax = Tax::query()->firstOrCreate(['tax_name' => 'Standard Tax'], ['tax_percentage' => 10]);
        $pieceUnit = Unit::query()->where('symbol', 'pc')->firstOrFail();

        Customer::query()->firstOrCreate(['name' => 'Walk-in Customer']);
        Supplier::query()->firstOrCreate(
            ['name' => 'Main Supplier'],
            ['company_name' => 'Local Supplies'],
        );

        Product::query()->firstOrCreate(['sku' => 'SKU-001'], [
            'product_name' => 'Bottled Water',
            'barcode' => '100000000001',
            'unit_id' => $pieceUnit->id,
            'type' => ProductType::Stock,
            'cost_price' => 0.50,
            'price' => 1.00,
            'tax_id' => $zeroTax->id,
            'category_id' => $generalCategory->id,
        ]);

        Product::query()->firstOrCreate(['sku' => 'SKU-002'], [
            'product_name' => 'Snack',
            'barcode' => '100000000002',
            'unit_id' => $pieceUnit->id,
            'type' => ProductType::Stock,
            'cost_price' => 1.25,
            'price' => 2.00,
            'tax_id' => $standardTax->id,
            'category_id' => $foodCategory->id,
        ]);

        $this->call(InventoryBalanceSeeder::class);

        if (config('pos.seed_demo_data')) {
            $this->call(DemoPosSeeder::class);
        }
    }
}
