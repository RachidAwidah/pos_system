<?php

namespace Tests\Feature;

use App\Enums\OrderPaymentStatus;
use App\Enums\OrderStatus;
use App\Models\Customer;
use App\Models\InventoryBalance;
use App\Models\PaymentMethod;
use App\Models\Product;
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
        $user = $this->createTestUser();
        $register = $this->createTestRegister();
        $shift = app(CashSessionService::class)->open($register, $user, '100.00');
        $product = Product::query()->where('sku', 'SKU-001')->firstOrFail();
        $startingQuantity = (string) InventoryBalance::query()->where('product_id', $product->id)->where('warehouse_id', $register->warehouse_id)->value('quantity_on_hand');
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
        $this->assertSame(bcsub($startingQuantity, '2.000', 3), InventoryBalance::query()->where('product_id', $product->id)->where('warehouse_id', $register->warehouse_id)->value('quantity_on_hand'));
        $this->assertDatabaseHas('stock_movements', ['order_id' => $order->id, 'quantity_delta' => '-2.000']);
        $this->assertDatabaseHas('audit_logs', ['entity_type' => $order::class, 'entity_id' => $order->id, 'action' => 'create']);
    }

    public function test_checkout_snapshots_the_warehouse_average_cost(): void
    {
        $user = $this->createTestUser();
        $register = $this->createTestRegister();
        $shift = app(CashSessionService::class)->open($register, $user, '10.00');
        $product = Product::query()->where('sku', 'SKU-001')->firstOrFail();
        $balance = InventoryBalance::query()
            ->whereBelongsTo($product)
            ->where('warehouse_id', $register->warehouse_id)
            ->firstOrFail();
        $balance->update(['average_cost' => '0.4321']);
        $cash = PaymentMethod::query()->where('code', 'CASH')->firstOrFail();

        $order = app(CheckoutService::class)->checkout(
            $user,
            $shift,
            [['product_id' => $product->id, 'quantity' => '1']],
            [['payment_method_id' => $cash->id, 'amount' => '1.00']],
        );

        $this->assertSame('0.4321', $order->items->first()->cost_price_at_sale);
        $this->assertDatabaseHas('stock_movements', [
            'order_id' => $order->id,
            'unit_cost' => '0.4321',
        ]);
    }

    public function test_credit_checkout_requires_a_customer_with_available_credit(): void
    {
        $user = $this->createTestUser();
        $register = $this->createTestRegister();
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
        $user = $this->createTestUser();
        $register = $this->createTestRegister();
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
