<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\Warehouse;
use App\Services\InventoryService;
use Illuminate\Database\Seeder;

class InventoryBalanceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $warehouse = Warehouse::query()->where('code', 'MAIN')->firstOrFail();
        $inventory = app(InventoryService::class);
        $openingBalances = [
            'SKU-001' => ['quantity' => '20.000', 'reorder_level' => '5.000'],
            'SKU-002' => ['quantity' => '15.000', 'reorder_level' => '4.000'],
        ];

        foreach ($openingBalances as $sku => $openingBalance) {
            $product = Product::query()->where('sku', $sku)->firstOrFail();
            $inventory->setOpeningBalance(
                $product,
                $warehouse,
                $openingBalance['quantity'],
                $openingBalance['reorder_level'],
                (string) $product->cost_price,
            );
        }
    }
}
