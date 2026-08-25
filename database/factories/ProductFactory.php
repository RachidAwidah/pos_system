<?php

namespace Database\Factories;

use App\Enums\ProductType;
use App\Models\Category;
use App\Models\Product;
use App\Models\Tax;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        $costPrice = fake()->randomFloat(2, 5, 200);
        $price = round($costPrice * fake()->randomFloat(2, 1.1, 1.8), 2);

        return [
            'product_name' => ucfirst(fake()->unique()->words(2, true)),
            'sku' => fake()->unique()->bothify('SKU-####-????'),
            'barcode' => fake()->unique()->numerify('62############'),
            'unit_id' => Unit::factory(),
            'type' => ProductType::Stock,
            'cost_price' => $costPrice,
            'price' => $price,
            'description' => fake()->sentence(),
            'tax_id' => Tax::factory(),
            'category_id' => Category::factory(),
        ];
    }
}
