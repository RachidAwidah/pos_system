<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PurchaseOrderItem>
 */
class PurchaseOrderItemFactory extends Factory
{
    protected $model = PurchaseOrderItem::class;

    public function definition(): array
    {
        $quantity = fake()->randomFloat(3, 1, 100);
        $unitCost = fake()->randomFloat(4, 0.1, 100);
        $subtotalAmount = round($quantity * $unitCost, 2);
        $taxAmount = round($subtotalAmount * 0.1, 2);

        return [
            'purchase_order_id' => PurchaseOrder::factory(),
            'product_id' => Product::factory(),
            'product_name' => fake()->words(3, true),
            'sku' => fake()->unique()->bothify('SKU-####??'),
            'ordered_quantity' => $quantity,
            'received_quantity' => 0,
            'unit_cost' => $unitCost,
            'tax_rate' => 10,
            'subtotal_amount' => $subtotalAmount,
            'discount_amount' => 0,
            'tax_amount' => $taxAmount,
            'total_amount' => round($subtotalAmount + $taxAmount, 2),
        ];
    }
}
