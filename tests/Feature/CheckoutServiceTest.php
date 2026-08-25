<?php

namespace Tests\Feature;

use App\Enums\OrderPaymentStatus;
use App\Enums\OrderStatus;
use App\Models\Customer;
use App\Models\InventoryBalance;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Register;
use App\Models\User;
use App\Services\CashSessionService;
use App\Services\CheckoutService;
use DomainException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class CheckoutServiceTest extends TestCase
{
    use DatabaseTransactions;

    public function test_cash_checkout_creates_an_immutable_sale_and_reduces_inventory(): void
    {
        $user = User::query()->firstOrFail();
        $register = Register::query()->where('code', 'MAIN-REG-01')->firstOrFail();
        $shift = app(CashSessionService::class)->open($register, $user, '100.00');
        $product = Product::query()->where('sku', 'SKU-001')->firstOrFail();
        $cash = PaymentMethod::query()->where('code', 'CASH')->firstOrFail();

        $order = app(CheckoutService::class)->checkout(
            $user,
            $shift,
            [['product_id' => $product->id, 'quantity' => '2']],
            [['payment_method_id' => $cash->id, 'amount' => '2.00', 'amount_tendered' => '5.00']],
        );

        $this->assertSame(OrderStatus::Completed, $order->status);
        $this->assertSame(OrderPaymentStatus::Paid, $order->payment_status);
        $this->assertSame('2.00', $order->final_amount);
        $this->assertSame('3.00', $order->payments->first()->change_amount);
        $this->assertSame('18.000', InventoryBalance::query()->where('product_id', $product->id)->where('warehouse_id', $register->warehouse_id)->value('quantity_on_hand'));
        $this->assertDatabaseHas('stock_movements', ['order_id' => $order->id, 'quantity_delta' => '-2.000']);
        $this->assertDatabaseHas('audit_logs', ['entity_type' => $order::class, 'entity_id' => $order->id, 'action' => 'create']);
    }

    public function test_credit_checkout_requires_a_customer_with_available_credit(): void
    {
        $user = User::query()->firstOrFail();
        $register = Register::query()->where('code', 'MAIN-REG-01')->firstOrFail();
        $shift = app(CashSessionService::class)->open($register, $user, '0');
        $product = Product::query()->where('sku', 'SKU-001')->firstOrFail();
        $customer = Customer::factory()->create(['credit_limit' => '10.00', 'balance' => '0.00']);

        $order = app(CheckoutService::class)->checkout(
            $user,
            $shift,
            [['product_id' => $product->id, 'quantity' => '3']],
            [],
            $customer,
        );

        $this->assertSame('3.00', $order->due_amount);
        $this->assertSame('3.00', $customer->refresh()->balance);
        $this->assertDatabaseHas('customer_ledger_entries', ['order_id' => $order->id, 'amount_delta' => '3.00']);
    }

    public function test_failed_checkout_rolls_back_order_and_stock_changes(): void
    {
        $user = User::query()->firstOrFail();
        $register = Register::query()->where('code', 'MAIN-REG-01')->firstOrFail();
        $shift = app(CashSessionService::class)->open($register, $user, '0');
        $product = Product::query()->where('sku', 'SKU-001')->firstOrFail();
        $ordersBefore = $product->orderItems()->count();

        try {
            app(CheckoutService::class)->checkout(
                $user,
                $shift,
                [['product_id' => $product->id, 'quantity' => '999']],
                [],
                Customer::factory()->create(['credit_limit' => '5000.00', 'balance' => '0.00']),
            );
            $this->fail('Checkout should reject insufficient inventory.');
        } catch (DomainException $exception) {
            $this->assertSame('Insufficient inventory for this operation.', $exception->getMessage());
        }

        $this->assertSame($ordersBefore, $product->orderItems()->count());
    }
}
