<?php

namespace Database\Factories;

use App\Enums\LoyaltyTransactionType;
use App\Models\Customer;
use App\Models\LoyaltyTransaction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LoyaltyTransaction>
 */
class LoyaltyTransactionFactory extends Factory
{
    protected $model = LoyaltyTransaction::class;

    public function definition(): array
    {
        $points = fake()->numberBetween(1, 100);

        return [
            'customer_id' => Customer::factory(),
            'transaction_type' => LoyaltyTransactionType::Earned,
            'points_delta' => $points,
            'balance_before' => 0,
            'balance_after' => $points,
            'occurred_at' => now(),
        ];
    }
}
