<?php

namespace Database\Factories;

use App\Enums\PurchaseOrderStatus;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PurchaseOrder>
 */
class PurchaseOrderFactory extends Factory
{
    protected $model = PurchaseOrder::class;

    public function definition(): array
    {
        $subtotalAmount = fake()->randomFloat(2, 100, 2500);
        $discountAmount = fake()->randomFloat(2, 0, min(100, $subtotalAmount));
        $taxAmount = round(($subtotalAmount - $discountAmount) * 0.1, 2);

        return [
            'purchase_order_number' => fake()->unique()->bothify('PO-########'),
            'user_id' => User::factory(),
            'supplier_id' => Supplier::factory(),
            'warehouse_id' => Warehouse::factory(),
            'status' => PurchaseOrderStatus::Sent,
            'subtotal_amount' => $subtotalAmount,
            'discount_amount' => $discountAmount,
            'tax_amount' => $taxAmount,
            'total_amount' => round($subtotalAmount - $discountAmount + $taxAmount, 2),
            'ordered_at' => now(),
            'expected_at' => now()->addDays(fake()->numberBetween(1, 14))->toDateString(),
        ];
    }
}
