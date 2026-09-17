<?php

namespace Database\Factories;

use App\Enums\ShiftStatus;
use App\Models\Register;
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
        return [
            'register_id' => Register::factory(),
            'opened_by_user_id' => User::factory(),
            'closed_by_user_id' => null,
            'status' => ShiftStatus::Open,
            'opened_at' => now()->subHours(fake()->numberBetween(1, 8)),
            'closed_at' => null,
            'opening_cash' => fake()->randomFloat(2, 0, 500),
            'closing_cash' => null,
            'expected_cash' => null,
            'difference_amount' => null,
            'notes' => fake()->optional()->sentence(),
        ];
    }

    public function closed(): static
    {
        return $this->state(function (array $attributes): array {
            $expectedCash = fake()->randomFloat(2, 100, 1500);
            $closingCash = max(0, $expectedCash + fake()->randomFloat(2, -20, 20));

            return [
                'closed_by_user_id' => User::factory(),
                'status' => ShiftStatus::Closed,
                'closed_at' => now(),
                'closing_cash' => $closingCash,
                'expected_cash' => $expectedCash,
                'difference_amount' => round($closingCash - $expectedCash, 2),
            ];
        });
    }
}
