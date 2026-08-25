<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\InventoryBalance;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Register;
use App\Models\User;
use App\Services\CashSessionService;
use App\Services\CheckoutService;
use App\Services\SalesReturnService;
use DomainException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class SalesReturnServiceTest extends TestCase
{
    use DatabaseTransactions;

    public function test_partial_return_refunds_payment_and_restocks_inventory(): void
    {
        $user = User::query()->firstOrFail();
        $register = Register::query()->where('code', 'MAIN-REG-01')->firstOrFail();
        $shift = app(CashSessionService::class)->open($register, $user, '20.00');
        $product = Product::query()->where('sku', 'SKU-001')->firstOrFail();
        $cash = PaymentMethod::query()->where('code', 'CASH')->firstOrFail();
        $order = app(CheckoutService::class)->checkout(
            $user,
            $shift,
            [['product_id' => $product->id, 'quantity' => '5']],
            [['payment_method_id' => $cash->id, 'amount' => '5.00']],
        );

        $salesReturn = app(SalesReturnService::class)->complete(
            $order,
            $shift,
            $user,
            [['order_item_id' => $order->items->first()->id, 'quantity' => '2', 'restock' => true]],
            [['payment_method_id' => $cash->id, 'amount' => '2.00']],
            'Customer changed their mind',
        );

        $this->assertSame('2.00', $salesReturn->refund_amount);
        $this->assertSame(OrderStatus::PartiallyRefunded, $order->refresh()->status);
        $this->assertSame('2.00', $order->refunded_amount);
        $this->assertSame('17.000', InventoryBalance::query()->where('product_id', $product->id)->where('warehouse_id', $register->warehouse_id)->value('quantity_on_hand'));
        $this->assertDatabaseHas('payments', ['sales_return_id' => $salesReturn->id, 'amount' => '2.00', 'type' => 'refund']);
    }

    public function test_return_cannot_exceed_remaining_sold_quantity(): void
    {
        $user = User::query()->firstOrFail();
        $register = Register::query()->where('code', 'MAIN-REG-01')->firstOrFail();
        $shift = app(CashSessionService::class)->open($register, $user, '20.00');
        $product = Product::query()->where('sku', 'SKU-001')->firstOrFail();
        $cash = PaymentMethod::query()->where('code', 'CASH')->firstOrFail();
        $order = app(CheckoutService::class)->checkout(
            $user,
            $shift,
            [['product_id' => $product->id, 'quantity' => '1']],
            [['payment_method_id' => $cash->id, 'amount' => '1.00']],
        );

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('A returned quantity cannot exceed the remaining sold quantity.');
        app(SalesReturnService::class)->complete(
            $order,
            $shift,
            $user,
            [['order_item_id' => $order->items->first()->id, 'quantity' => '2']],
            [['payment_method_id' => $cash->id, 'amount' => '2.00']],
            'Invalid quantity',
        );
    }
}
