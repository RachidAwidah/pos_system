<?php

namespace Database\Factories;

use App\Models\Tax;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Tax>
 */
class TaxFactory extends Factory
{
    protected $model = Tax::class;

    public function definition(): array
    {
        return [
            'tax_name' => fake()->unique()->randomElement(['VAT 0%', 'VAT 5%', 'VAT 10%', 'VAT 15%']),
            'tax_percentage' => fake()->randomElement([0, 5, 10, 15]),
        ];
    }
}
