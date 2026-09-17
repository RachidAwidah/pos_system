<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PurchaseSchemaTest extends TestCase
{
    public function test_purchase_orders_are_separated_from_inventory_receipts(): void
    {
        $this->assertTrue(Schema::hasTable('purchase_orders'));
        $this->assertTrue(Schema::hasTable('purchase_order_items'));
        $this->assertTrue(Schema::hasTable('goods_receipts'));
        $this->assertTrue(Schema::hasTable('goods_receipt_items'));
        $this->assertFalse(Schema::hasTable('purchase_order_details'));
        $this->assertTrue(Schema::hasColumns('purchase_orders', [
            'purchase_order_number',
            'supplier_id',
            'warehouse_id',
            'status',
            'subtotal_amount',
            'total_amount',
            'ordered_at',
            'expected_at',
        ]));
        $this->assertTrue(Schema::hasColumns('purchase_order_items', [
            'ordered_quantity',
            'received_quantity',
            'unit_cost',
            'total_amount',
        ]));
    }
}
