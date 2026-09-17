<?php

namespace Database\Factories;

use App\Enums\ProductType;
use App\Models\InventoryBalance;
use App\Models\Product;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InventoryBalance>
 */
class InventoryBalanceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $quantityOnHand = fake()->numberBetween(10, 250);

        return [
            'product_id' => Product::factory()->state(['type' => ProductType::Stock]),
            'warehouse_id' => Warehouse::factory(),
            'quantity_on_hand' => $quantityOnHand,
            'quantity_reserved' => fake()->numberBetween(0, $quantityOnHand),
            'reorder_level' => fake()->numberBetween(0, 20),
            'average_cost' => fake()->randomFloat(4, 1, 200),
        ];
    }
}
