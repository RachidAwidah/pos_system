<?php

namespace Tests\Feature;

use App\Models\InventoryBalance;
use App\Models\Product;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class InventorySchemaTest extends TestCase
{
    use DatabaseTransactions;

    public function test_inventory_is_separated_from_the_product_catalog(): void
    {
        $this->assertTrue(Schema::hasTable('warehouses'));
        $this->assertTrue(Schema::hasTable('inventory_balances'));
        $this->assertTrue(Schema::hasTable('inventory_counts'));
        $this->assertTrue(Schema::hasTable('inventory_count_items'));
        $this->assertTrue(Schema::hasColumns('inventory_balances', [
            'product_id',
            'warehouse_id',
            'quantity_on_hand',
            'quantity_reserved',
            'reorder_level',
            'average_cost',
        ]));
        $this->assertFalse(Schema::hasColumn('products', 'quantity'));
        $this->assertFalse(Schema::hasColumn('products', 'reorder_level'));
    }

    public function test_seeded_products_have_opening_balances_in_the_default_warehouse(): void
    {
        $product = Product::query()->where('sku', 'SKU-001')->firstOrFail();
        $warehouse = Warehouse::query()->where('code', 'MAIN')->firstOrFail();
        $balance = InventoryBalance::query()
            ->whereBelongsTo($product)
            ->whereBelongsTo($warehouse)
            ->firstOrFail();

        $this->assertTrue($warehouse->is_default);
        $this->assertSame('20.000', $balance->quantity_on_hand);
        $this->assertSame('0.000', $balance->quantity_reserved);
        $this->assertSame('5.000', $balance->reorder_level);
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'movement_type' => 'opening',
            'quantity_delta' => '20.000',
        ]);
    }
}
