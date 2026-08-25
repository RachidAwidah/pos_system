<?php

namespace Database\Factories;

use App\Models\Contact;
use App\Models\Order;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition(): array
    {
        $totalAmount = fake()->randomFloat(2, 50, 900);
        $discountAmount = fake()->randomFloat(2, 0, 40);
        $taxAmount = round(($totalAmount - $discountAmount) * 0.1, 2);
        $finalAmount = round($totalAmount - $discountAmount + $taxAmount, 2);
        $paidAmount = round(fake()->randomFloat(2, 0, $finalAmount), 2);

        return [
            'invoice_number' => fake()->unique()->bothify('INV-########'),
            'user_id' => User::factory(),
            'contact_id' => Contact::factory()->customer(),
            'shift_id' => Shift::factory(),
            'status' => 'completed',
            'total_amount' => $totalAmount,
            'discount_amount' => $discountAmount,
            'tax_amount' => $taxAmount,
            'final_amount' => $finalAmount,
            'paid_amount' => $paidAmount,
            'due_amount' => round($finalAmount - $paidAmount, 2),
            'payment_status' => $paidAmount >= $finalAmount ? 'paid' : 'partial',
            'order_date' => now()->subDays(fake()->numberBetween(0, 30)),
        ];
    }
}
