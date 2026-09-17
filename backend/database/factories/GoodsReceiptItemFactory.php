<?php

namespace Database\Factories;

use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptItem;
use App\Models\Product;
use App\Models\PurchaseOrderItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GoodsReceiptItem>
 */
class GoodsReceiptItemFactory extends Factory
{
    protected $model = GoodsReceiptItem::class;

    public function definition(): array
    {
        $quantity = fake()->randomFloat(3, 0.001, 50);
        $unitCost = fake()->randomFloat(4, 0.1, 100);
        $subtotalAmount = round($quantity * $unitCost, 2);

        return [
            'goods_receipt_id' => GoodsReceipt::factory(),
            'purchase_order_item_id' => PurchaseOrderItem::factory(),
            'product_id' => Product::factory(),
            'quantity' => $quantity,
            'unit_cost' => $unitCost,
            'subtotal_amount' => $subtotalAmount,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'total_amount' => $subtotalAmount,
            'batch_number' => fake()->optional()->bothify('LOT-####??'),
            'expires_at' => fake()->optional()->dateTimeBetween('+1 month', '+2 years')?->format('Y-m-d'),
        ];
    }
}
