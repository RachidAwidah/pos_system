<?php

namespace Tests\Feature;

use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Register;
use App\Models\User;
use App\Services\CashSessionService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SalesApiTest extends TestCase
{
    use DatabaseTransactions;

    public function test_versioned_sales_api_returns_json_without_api_prefix(): void
    {
        $admin = User::query()->where('email', config('pos.admin_email'))->firstOrFail();
        $admin->update(['must_change_password' => false]);
        Sanctum::actingAs($admin);
        $shift = app(CashSessionService::class)->open(
            Register::query()->where('code', 'MAIN-REG-01')->firstOrFail(),
            $admin,
            '20.00',
        );
        $product = Product::query()->where('sku', 'SKU-001')->firstOrFail();
        $cash = PaymentMethod::query()->where('code', 'CASH')->firstOrFail();

        $response = $this->postJson('/v1/orders', [
            'shift_id' => $shift->id,
            'customer_id' => null,
            'items' => [['product_id' => $product->id, 'quantity' => '1']],
            'payments' => [['payment_method_id' => $cash->id, 'amount' => '1.00', 'amount_tendered' => '1.00']],
            'discount_type' => 'none',
            'discount_value' => '0',
        ]);

        $response->assertCreated()
            ->assertHeader('content-type', 'application/json')
            ->assertJsonPath('data.final_amount', '1.00')
            ->assertJsonPath('data.status', 'completed');

        $this->getJson('/v1/orders')
            ->assertOk()
            ->assertJsonStructure(['data', 'links', 'meta']);
    }
}
