<?php

namespace Database\Factories;

use App\Models\Contact;
use App\Models\Order;
use App\Models\Payment;
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
            'contact_id' => Contact::factory()->customer(),
            'payment_method' => fake()->randomElement(['cash', 'card', 'credit']),
            'amount_paid' => fake()->randomFloat(2, 10, 1000),
            'reference_number' => fake()->optional()->bothify('REF-####??'),
            'payment_date' => now()->subDays(fake()->numberBetween(0, 30)),
        ];
    }
}
