<?php

namespace Database\Factories;

use App\Models\Contact;
use App\Models\PurchaseOrder;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PurchaseOrder>
 */
class PurchaseOrderFactory extends Factory
{
    protected $model = PurchaseOrder::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'supplier_id' => Contact::factory()->supplier(),
            'total_cost' => fake()->randomFloat(2, 100, 2500),
            'status' => fake()->randomElement(['pending', 'received', 'cancelled']),
        ];
    }
}
