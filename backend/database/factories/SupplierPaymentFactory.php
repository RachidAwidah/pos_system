<?php

namespace Database\Factories;

use App\Enums\PaymentStatus;
use App\Models\PaymentMethod;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SupplierPayment>
 */
class SupplierPaymentFactory extends Factory
{
    protected $model = SupplierPayment::class;

    public function definition(): array
    {
        return [
            'supplier_id' => Supplier::factory(),
            'user_id' => User::factory(),
            'payment_method_id' => PaymentMethod::factory(),
            'purchase_order_id' => null,
            'status' => PaymentStatus::Completed,
            'amount' => fake()->randomFloat(2, 1, 2500),
            'reference_number' => fake()->optional()->bothify('SP-####??'),
            'paid_at' => now(),
        ];
    }
}
