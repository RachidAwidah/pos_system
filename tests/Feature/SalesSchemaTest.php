<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SalesSchemaTest extends TestCase
{
    public function test_sales_schema_keeps_orders_payments_and_returns_separate(): void
    {
        $this->assertTrue(Schema::hasTable('order_items'));
        $this->assertFalse(Schema::hasTable('orders_details'));
        $this->assertTrue(Schema::hasTable('payment_methods'));
        $this->assertTrue(Schema::hasTable('sales_returns'));
        $this->assertTrue(Schema::hasTable('sales_return_items'));
        $this->assertTrue(Schema::hasColumns('orders', [
            'customer_id',
            'shift_id',
            'warehouse_id',
            'subtotal_amount',
            'paid_amount',
            'due_amount',
            'refunded_amount',
            'payment_status',
        ]));
        $this->assertTrue(Schema::hasColumns('order_items', [
            'product_name',
            'sku',
            'cost_price_at_sale',
            'tax_rate',
            'discount_amount',
            'total_amount',
        ]));
        $this->assertTrue(Schema::hasColumns('payments', [
            'sales_return_id',
            'shift_id',
            'user_id',
            'payment_method_id',
            'type',
            'status',
            'amount',
            'amount_tendered',
            'change_amount',
        ]));
    }
}
