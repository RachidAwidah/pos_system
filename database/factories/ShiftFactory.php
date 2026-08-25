<?php

namespace Database\Factories;

use App\Models\Shift;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Shift>
 */
class ShiftFactory extends Factory
{
    protected $model = Shift::class;

    public function definition(): array
    {
        $startingCash = fake()->randomFloat(2, 50, 500);
        $expectedCash = round($startingCash + fake()->randomFloat(2, 200, 1200), 2);
        $endingCash = round($expectedCash + fake()->randomFloat(2, -15, 15), 2);

        return [
            'user_id' => User::factory(),
            'status' => fake()->randomElement(['open', 'closed']),
            'start_time' => now()->subHours(fake()->numberBetween(2, 18)),
            'end_time' => now(),
            'starting_cash' => $startingCash,
            'ending_cash' => $endingCash,
            'expected_cash' => $expectedCash,
            'difference_amount' => round($endingCash - $expectedCash, 2),
        ];
    }
}
