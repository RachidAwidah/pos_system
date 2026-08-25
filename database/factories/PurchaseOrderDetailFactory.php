<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderDetail;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PurchaseOrderDetail>
 */
class PurchaseOrderDetailFactory extends Factory
{
    protected $model = PurchaseOrderDetail::class;

    public function definition(): array
    {
        return [
            'purchase_order_id' => PurchaseOrder::factory(),
            'product_id' => Product::factory(),
            'quantity' => fake()->numberBetween(5, 80),
            'cost_price' => fake()->randomFloat(2, 3, 180),
        ];
    }
}
