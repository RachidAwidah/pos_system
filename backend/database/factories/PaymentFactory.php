<?php

namespace Database\Factories;

use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'sales_return_id' => null,
            'shift_id' => Shift::factory(),
            'user_id' => User::factory(),
            'payment_method_id' => PaymentMethod::factory(),
            'type' => PaymentType::Payment,
            'status' => PaymentStatus::Completed,
            'amount' => fake()->randomFloat(2, 10, 1000),
            'amount_tendered' => null,
            'change_amount' => 0,
            'reference_number' => fake()->optional()->bothify('REF-####??'),
            'paid_at' => now()->subDays(fake()->numberBetween(0, 30)),
        ];
    }
}
