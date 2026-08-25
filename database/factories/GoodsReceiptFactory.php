<?php

namespace Database\Factories;

use App\Enums\GoodsReceiptStatus;
use App\Models\GoodsReceipt;
use App\Models\PurchaseOrder;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GoodsReceipt>
 */
class GoodsReceiptFactory extends Factory
{
    protected $model = GoodsReceipt::class;

    public function definition(): array
    {
        $subtotalAmount = fake()->randomFloat(2, 10, 1000);
        $taxAmount = round($subtotalAmount * 0.1, 2);

        return [
            'receipt_number' => fake()->unique()->bothify('GRN-########'),
            'purchase_order_id' => PurchaseOrder::factory(),
            'warehouse_id' => Warehouse::factory(),
            'received_by_user_id' => User::factory(),
            'status' => GoodsReceiptStatus::Completed,
            'subtotal_amount' => $subtotalAmount,
            'discount_amount' => 0,
            'tax_amount' => $taxAmount,
            'total_amount' => round($subtotalAmount + $taxAmount, 2),
            'supplier_reference' => fake()->optional()->bothify('SUP-####??'),
            'notes' => fake()->optional()->sentence(),
            'received_at' => now(),
        ];
    }
}
