<?php

namespace Database\Factories;

use App\Models\OrderItem;
use App\Models\Product;
use App\Models\SalesReturn;
use App\Models\SalesReturnItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SalesReturnItem>
 */
class SalesReturnItemFactory extends Factory
{
    protected $model = SalesReturnItem::class;

    public function definition(): array
    {
        $subtotalAmount = fake()->randomFloat(2, 1, 100);
        $taxAmount = round($subtotalAmount * 0.1, 2);

        return [
            'sales_return_id' => SalesReturn::factory(),
            'order_item_id' => OrderItem::factory(),
            'product_id' => Product::factory(),
            'quantity' => fake()->randomFloat(3, 0.001, 10),
            'subtotal_amount' => $subtotalAmount,
            'tax_amount' => $taxAmount,
            'refund_amount' => round($subtotalAmount + $taxAmount, 2),
            'restock' => true,
            'reason' => fake()->optional()->sentence(),
        ];
    }
}
