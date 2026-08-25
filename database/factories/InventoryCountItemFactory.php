<?php

namespace Database\Factories;

use App\Enums\ProductType;
use App\Models\InventoryCount;
use App\Models\InventoryCountItem;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InventoryCountItem>
 */
class InventoryCountItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $expectedQuantity = fake()->numberBetween(10, 250);
        $countedQuantity = fake()->numberBetween(0, 250);

        return [
            'inventory_count_id' => InventoryCount::factory(),
            'product_id' => Product::factory()->state(['type' => ProductType::Stock]),
            'expected_quantity' => $expectedQuantity,
            'counted_quantity' => $countedQuantity,
            'difference_quantity' => $countedQuantity - $expectedQuantity,
        ];
    }
}
