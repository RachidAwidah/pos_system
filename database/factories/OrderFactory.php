<?php

namespace Database\Factories;

use App\Enums\OrderPaymentStatus;
use App\Enums\OrderStatus;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Shift;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition(): array
    {
        $totalAmount = fake()->randomFloat(2, 50, 900);
        $discountAmount = fake()->randomFloat(2, 0, 40);
        $taxAmount = round(($totalAmount - $discountAmount) * 0.1, 2);
        $finalAmount = round($totalAmount - $discountAmount + $taxAmount, 2);
        $paidAmount = round(fake()->randomFloat(2, 0, $finalAmount), 2);

        return [
            'invoice_number' => fake()->unique()->bothify('INV-########'),
            'user_id' => User::factory(),
            'customer_id' => Customer::factory(),
            'shift_id' => Shift::factory(),
            'warehouse_id' => Warehouse::factory(),
            'status' => OrderStatus::Completed,
            'subtotal_amount' => $totalAmount,
            'discount_amount' => $discountAmount,
            'tax_amount' => $taxAmount,
            'final_amount' => $finalAmount,
            'paid_amount' => $paidAmount,
            'due_amount' => round($finalAmount - $paidAmount, 2),
            'refunded_amount' => 0,
            'payment_status' => $paidAmount >= $finalAmount ? OrderPaymentStatus::Paid : OrderPaymentStatus::Partial,
            'order_date' => now()->subDays(fake()->numberBetween(0, 30)),
            'completed_at' => now(),
        ];
    }
}
