<?php

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    protected $model = Category::class;

    public function definition(): array
    {
        return [
            'category_name' => fake()->unique()->randomElement([
                'Beverages',
                'Snacks',
                'Dairy',
                'Cleaning',
                'Electronics',
            ]).' '.fake()->unique()->numberBetween(1, 99),
        ];
    }
}
