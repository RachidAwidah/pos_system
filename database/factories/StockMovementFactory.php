<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockMovement>
 */
class StockMovementFactory extends Factory
{
    protected $model = StockMovement::class;

    public function definition(): array
    {
        $quantity = fake()->numberBetween(-40, 40);
        $baseQuantity = fake()->numberBetween(40, 250);
        $quantityAfter = $baseQuantity + $quantity;

        return [
            'product_id' => Product::factory(),
            'user_id' => User::factory(),
            'movement_type' => 'adjustment',
            'quantity' => $quantity,
            'before_quantity' => $baseQuantity,
            'after_quantity' => $quantityAfter,
            'order_id' => null,
            'purchase_order_id' => null,
            'notes' => 'Factory inventory adjustment',
        ];
    }
}
