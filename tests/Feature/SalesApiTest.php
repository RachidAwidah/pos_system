<?php

namespace Tests\Feature;

use App\Models\PaymentMethod;
use App\Models\Product;
use App\Services\CashSessionService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SalesApiTest extends TestCase
{
    use DatabaseTransactions;

    public function test_versioned_sales_api_exposes_multi_payment_return_history_inside_the_invoice(): void
    {
        $admin = $this->createTestUser();
        $admin->assignRole('Admin');
        Sanctum::actingAs($admin);
        $shift = app(CashSessionService::class)->open(
            $this->createTestRegister(),
            $admin,
            '20.00',
        );
        $product = Product::query()->where('sku', 'SKU-001')->firstOrFail();
        $cash = PaymentMethod::query()->where('code', 'CASH')->firstOrFail();
        $card = PaymentMethod::query()->where('code', 'CARD')->firstOrFail();

        $response = $this->postJson('/v1/orders', [
            'shift_id' => $shift->id,
            'customer_id' => null,
            'items' => [['product_id' => $product->id, 'quantity' => '1']],
            'payments' => [
                ['payment_method_id' => $cash->id, 'amount' => '0.40', 'amount_tendered' => '0.40'],
                ['payment_method_id' => $card->id, 'amount' => '0.60', 'reference_number' => 'CARD-SALE-001'],
            ],
            'discount_type' => 'none',
            'discount_value' => '0',
        ]);

        $response->assertCreated()
            ->assertHeader('content-type', 'application/json')
            ->assertJsonPath('data.final_amount', '1.00')
            ->assertJsonPath('data.status', 'completed');
        $orderId = $response->json('data.id');
        $orderItemId = $response->json('data.items.0.id');

        $this->postJson("/v1/orders/{$orderId}/returns", [
            'shift_id' => $shift->id,
            'reason' => 'Full API return',
            'items' => [['order_item_id' => $orderItemId, 'quantity' => '1', 'restock' => true]],
            'refunds' => [
                ['payment_method_id' => $cash->id, 'amount' => '0.40'],
                ['payment_method_id' => $card->id, 'amount' => '0.60', 'reference_number' => 'CARD-REFUND-001'],
            ],
        ])->assertCreated()
            ->assertJsonPath('data.refund_amount', '1.00');

        $this->getJson("/v1/orders/{$orderId}")
            ->assertOk()
            ->assertJsonPath('data.status', 'refunded')
            ->assertJsonPath('data.payment_status', 'refunded')
            ->assertJsonPath('data.returns.0.items.0.order_item_id', $orderItemId)
            ->assertJsonCount(2, 'data.returns.0.payments')
            ->assertJsonPath('data.shift.id', $shift->id);

        $this->getJson('/v1/orders')
            ->assertOk()
            ->assertJsonStructure(['data', 'links', 'meta']);
    }
}
