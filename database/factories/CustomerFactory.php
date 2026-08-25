<?php

namespace Database\Factories;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'company_name' => fake()->optional()->company(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->optional()->phoneNumber(),
            'address' => fake()->optional()->address(),
            'tax_number' => fake()->optional()->numerify('TAX-########'),
            'credit_limit' => fake()->randomFloat(2, 0, 5000),
            'balance' => fake()->randomFloat(2, 0, 1000),
            'loyalty_points' => fake()->numberBetween(0, 5000),
        ];
    }
}
