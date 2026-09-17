<?php

namespace Database\Factories;

use App\Enums\ProductType;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<OrderItem> */
class OrderItemFactory extends Factory
{
    public function definition(): array
    {
        $quantity = fake()->numberBetween(1, 5);
        $unitPrice = fake()->randomFloat(2, 1, 100);
        $subtotal = round($quantity * $unitPrice, 2);

        return [
            'order_id' => Order::factory(),
            'product_id' => Product::factory()->state(['type' => ProductType::Stock]),
            'product_name' => fake()->words(2, true),
            'sku' => fake()->unique()->bothify('SKU-######'),
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'cost_price_at_sale' => fake()->randomFloat(4, 0.1, $unitPrice),
            'tax_rate' => 0,
            'subtotal_amount' => $subtotal,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'total_amount' => $subtotal,
        ];
    }
}
