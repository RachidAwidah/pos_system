<?php

namespace Database\Factories;

use App\Models\Contact;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Contact>
 */
class ContactFactory extends Factory
{
    protected $model = Contact::class;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'company_name' => fake()->company(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->numerify('09########'),
            'address' => fake()->address(),
            'tax_number' => fake()->numerify('TN########'),
            'type' => fake()->randomElement(['customer', 'supplier']),
            'credit_limit' => 0,
            'payable_limit' => 0,
            'balance' => 0,
        ];
    }

    public function customer(): static
    {
        return $this->state(fn () => ['type' => 'customer']);
    }

    public function supplier(): static
    {
        return $this->state(fn () => ['type' => 'supplier']);
    }
}
