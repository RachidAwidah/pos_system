<?php

namespace Database\Factories;

use App\Enums\SalesReturnStatus;
use App\Models\Order;
use App\Models\SalesReturn;
use App\Models\Shift;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SalesReturn>
 */
class SalesReturnFactory extends Factory
{
    protected $model = SalesReturn::class;

    public function definition(): array
    {
        $subtotalAmount = fake()->randomFloat(2, 1, 500);
        $taxAmount = round($subtotalAmount * 0.1, 2);

        return [
            'return_number' => fake()->unique()->bothify('RET-########'),
            'order_id' => Order::factory(),
            'user_id' => User::factory(),
            'shift_id' => Shift::factory(),
            'warehouse_id' => Warehouse::factory(),
            'status' => SalesReturnStatus::Completed,
            'subtotal_amount' => $subtotalAmount,
            'tax_amount' => $taxAmount,
            'refund_amount' => round($subtotalAmount + $taxAmount, 2),
            'reason' => fake()->sentence(),
            'returned_at' => now(),
        ];
    }
}
