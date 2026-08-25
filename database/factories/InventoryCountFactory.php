<?php

namespace Database\Factories;

use App\Enums\InventoryCountStatus;
use App\Models\InventoryCount;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InventoryCount>
 */
class InventoryCountFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'warehouse_id' => Warehouse::factory(),
            'started_by_user_id' => User::factory(),
            'approved_by_user_id' => null,
            'status' => InventoryCountStatus::Draft,
            'counted_at' => null,
            'applied_at' => null,
            'notes' => fake()->sentence(),
        ];
    }
}
