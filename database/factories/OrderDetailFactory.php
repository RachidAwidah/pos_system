<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderDetail>
 */
class OrderDetailFactory extends Factory
{
    protected $model = OrderDetail::class;

    public function definition(): array
    {
        $quantity = fake()->numberBetween(1, 5);
        $unitPrice = fake()->randomFloat(2, 5, 250);
        $discount = fake()->randomFloat(2, 0, 15);
        $taxRate = fake()->randomElement([0, 5, 10, 15]);
        $subtotalBeforeTax = max(($quantity * $unitPrice) - $discount, 0);
        $taxAmount = round(($subtotalBeforeTax * $taxRate) / 100, 2);
        $totalPrice = round($subtotalBeforeTax + $taxAmount, 2);

        return [
            'order_id' => Order::factory(),
            'product_id' => Product::factory(),
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'cost_price_at_sale' => fake()->randomFloat(2, 3, $unitPrice),
            'tax_rate_applicable' => $taxRate,
            'discount_amount' => $discount,
            'tax_amount' => $taxAmount,
            'total_price' => $totalPrice,
        ];
    }
}
