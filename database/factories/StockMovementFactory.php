<?php

namespace Database\Factories;

use App\Enums\ProductType;
use App\Enums\StockMovementType;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockMovement>
 */
class StockMovementFactory extends Factory
{
    protected $model = StockMovement::class;

    public function definition(): array
    {
        $quantity = fake()->numberBetween(1, 40);
        $baseQuantity = fake()->numberBetween(40, 250);
        $quantityAfter = $baseQuantity + $quantity;

        return [
            'product_id' => Product::factory()->state(['type' => ProductType::Stock]),
            'warehouse_id' => Warehouse::factory(),
            'user_id' => User::factory(),
            'movement_type' => StockMovementType::Adjustment,
            'quantity_delta' => $quantity,
            'balance_before' => $baseQuantity,
            'balance_after' => $quantityAfter,
            'unit_cost' => fake()->randomFloat(4, 1, 200),
            'order_id' => null,
            'purchase_order_id' => null,
            'inventory_count_id' => null,
            'transfer_batch_id' => null,
            'notes' => 'Factory inventory adjustment',
            'occurred_at' => now(),
        ];
    }
}
