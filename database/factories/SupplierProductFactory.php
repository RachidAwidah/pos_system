<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SupplierProduct>
 */
class SupplierProductFactory extends Factory
{
    protected $model = SupplierProduct::class;

    public function definition(): array
    {
        return [
            'supplier_id' => Supplier::factory(),
            'product_id' => Product::factory(),
            'supplier_sku' => fake()->optional()->bothify('SUP-SKU-####'),
            'last_cost' => fake()->randomFloat(4, 0.1, 100),
            'minimum_order_quantity' => fake()->randomFloat(3, 1, 20),
            'lead_time_days' => fake()->numberBetween(1, 30),
            'is_preferred' => false,
        ];
    }
}
