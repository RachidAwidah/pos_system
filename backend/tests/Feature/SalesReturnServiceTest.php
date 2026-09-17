<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Customer;
use App\Models\InventoryBalance;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Register;
use App\Models\User;
use App\Models\Warehouse;
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
        $user = $this->createTestUser();
        $register = $this->createTestRegister();
        $shift = app(CashSessionService::class)->open($register, $user, '20.00');
        $product = Product::query()->where('sku', 'SKU-001')->firstOrFail();
        $product->update(['price' => '1.00']);
        $startingQuantity = (string) InventoryBalance::query()->where('product_id', $product->id)->where('warehouse_id', $register->warehouse_id)->value('quantity_on_hand');
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
        $this->assertSame(bcsub($startingQuantity, '3.000', 3), InventoryBalance::query()->where('product_id', $product->id)->where('warehouse_id', $register->warehouse_id)->value('quantity_on_hand'));
        $this->assertDatabaseHas('payments', ['sales_return_id' => $salesReturn->id, 'amount' => '2.00', 'type' => 'refund']);
    }

    public function test_return_cannot_exceed_remaining_sold_quantity(): void
    {
        $user = $this->createTestUser();
        $register = $this->createTestRegister();
        $shift = app(CashSessionService::class)->open($register, $user, '20.00');
        $product = Product::query()->where('sku', 'SKU-001')->firstOrFail();
        $product->update(['price' => '1.00']);
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

    public function test_credit_return_reduces_the_order_due_amount(): void
    {
        $user = $this->createTestUser();
        $register = $this->createTestRegister();
        $shift = app(CashSessionService::class)->open($register, $user, '0');
        $product = Product::query()->where('sku', 'SKU-001')->firstOrFail();
        $product->update(['price' => '1.00']);
        $customer = Customer::factory()->create([
            'credit_limit' => '1000.00',
            'balance' => '0.00',
        ]);
        $order = app(CheckoutService::class)->checkout(
            $user,
            $shift,
            [['product_id' => $product->id, 'quantity' => '5']],
            [],
            $customer,
        );

        $dueBeforeReturn = (string) $order->due_amount;
        $salesReturn = app(SalesReturnService::class)->complete(
            $order,
            $shift,
            $user,
            [['order_item_id' => $order->items->first()->id, 'quantity' => '2', 'restock' => true]],
            [],
            'Credit sale return',
        );

        $expectedDue = bcsub($dueBeforeReturn, (string) $salesReturn->refund_amount, 2);
        $this->assertSame($expectedDue, $order->refresh()->due_amount);
        $this->assertSame($expectedDue, $customer->refresh()->balance);
    }

    public function test_return_must_use_the_original_sale_warehouse(): void
    {
        $seller = $this->createTestUser();
        $returner = User::factory()->create();
        $originalRegister = $this->createTestRegister();
        $originalShift = app(CashSessionService::class)->open($originalRegister, $seller, '10.00');
        $product = Product::query()->where('sku', 'SKU-001')->firstOrFail();
        $product->update(['price' => '1.00']);
        $cash = PaymentMethod::query()->where('code', 'CASH')->firstOrFail();
        $order = app(CheckoutService::class)->checkout(
            $seller,
            $originalShift,
            [['product_id' => $product->id, 'quantity' => '1']],
            [['payment_method_id' => $cash->id, 'amount' => '1.00']],
        );
        $otherWarehouse = Warehouse::factory()->create(['is_active' => true]);
        $otherRegister = Register::factory()->create([
            'warehouse_id' => $otherWarehouse->id,
            'is_active' => true,
        ]);
        $otherShift = app(CashSessionService::class)->open($otherRegister, $returner, '10.00');

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Returns must be processed in the warehouse that completed the original sale.');
        app(SalesReturnService::class)->complete(
            $order,
            $otherShift,
            $returner,
            [['order_item_id' => $order->items->first()->id, 'quantity' => '1']],
            [['payment_method_id' => $cash->id, 'amount' => '1.00']],
            'Wrong warehouse',
        );
    }

    public function test_final_partial_return_absorbs_refund_and_tax_rounding_residuals(): void
    {
        $user = $this->createTestUser();
        $register = $this->createTestRegister();
        $shift = app(CashSessionService::class)->open($register, $user, '20.00');
        $product = Product::query()->where('sku', 'SKU-002')->firstOrFail();
        $product->update(['price' => '1.00']);
        $cash = PaymentMethod::query()->where('code', 'CASH')->firstOrFail();
        $order = app(CheckoutService::class)->checkout(
            $user,
            $shift,
            [['product_id' => $product->id, 'quantity' => '3']],
            [['payment_method_id' => $cash->id, 'amount' => '2.20']],
            discountType: 'fixed',
            discountValue: '1.00',
        );
        $orderItem = $order->items->first();
        $returns = collect();

        foreach (['0.73', '0.73', '0.74'] as $refundAmount) {
            $returns->push(app(SalesReturnService::class)->complete(
                $order,
                $shift,
                $user,
                [['order_item_id' => $orderItem->id, 'quantity' => '1']],
                [['payment_method_id' => $cash->id, 'amount' => $refundAmount]],
                'Return rounding test',
            ));
        }

        $refundedAmount = $returns->reduce(fn (string $total, $return): string => bcadd($total, (string) $return->refund_amount, 2), '0.00');
        $returnedTax = $returns->reduce(fn (string $total, $return): string => bcadd($total, (string) $return->tax_amount, 2), '0.00');
        $this->assertSame((string) $orderItem->total_amount, $refundedAmount);
        $this->assertSame((string) $orderItem->tax_amount, $returnedTax);
        $this->assertSame(OrderStatus::Refunded, $order->refresh()->status);
    }

    public function test_less_cash_refund_rebalances_to_store_credit(): void
    {
        $user = $this->createTestUser();
        $register = $this->createTestRegister();
        $shift = app(CashSessionService::class)->open($register, $user, '20.00');
        $product = Product::query()->where('sku', 'SKU-001')->firstOrFail();
        $product->update(['price' => '1.00']);
        $cash = PaymentMethod::query()->where('code', 'CASH')->firstOrFail();
        $customer = Customer::factory()->create([
            'credit_limit' => '1000.00',
            'balance' => '0.00',
        ]);
        $order = app(CheckoutService::class)->checkout(
            $user,
            $shift,
            [['product_id' => $product->id, 'quantity' => '5']],
            [['payment_method_id' => $cash->id, 'amount' => '3.00']],
            $customer,
        );

        $salesReturn = app(SalesReturnService::class)->complete(
            $order,
            $shift,
            $user,
            [['order_item_id' => $order->items->first()->id, 'quantity' => '2', 'restock' => false]],
            [['payment_method_id' => $cash->id, 'amount' => '0.50']],
            'Partial cash refund, rest as credit',
        );

        $this->assertSame('2.00', $salesReturn->refund_amount);
        $order->refresh();
        $this->assertSame('2.00', $order->refunded_amount);
        $this->assertSame('2.50', $order->paid_amount);
        $this->assertSame('0.50', $order->due_amount);
        $this->assertDatabaseHas('payments', ['sales_return_id' => $salesReturn->id, 'amount' => '0.50', 'type' => 'refund']);
    }

    public function test_refund_exceeding_paid_portion_is_rejected(): void
    {
        $user = $this->createTestUser();
        $register = $this->createTestRegister();
        $shift = app(CashSessionService::class)->open($register, $user, '20.00');
        $product = Product::query()->where('sku', 'SKU-001')->firstOrFail();
        $product->update(['price' => '1.00']);
        $cash = PaymentMethod::query()->where('code', 'CASH')->firstOrFail();
        $order = app(CheckoutService::class)->checkout(
            $user,
            $shift,
            [['product_id' => $product->id, 'quantity' => '5']],
            [['payment_method_id' => $cash->id, 'amount' => '5.00']],
        );

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Refund payments cannot exceed the return total.');
        app(SalesReturnService::class)->complete(
            $order,
            $shift,
            $user,
            [['order_item_id' => $order->items->first()->id, 'quantity' => '2']],
            [['payment_method_id' => $cash->id, 'amount' => '3.00']],
            'Over refund',
        );
    }

    public function test_refund_exceeding_per_method_limit_is_rejected(): void
    {
        $user = $this->createTestUser();
        $register = $this->createTestRegister();
        $shift = app(CashSessionService::class)->open($register, $user, '20.00');
        $product = Product::query()->where('sku', 'SKU-001')->firstOrFail();
        $product->update(['price' => '1.00']);
        $cash = PaymentMethod::query()->where('code', 'CASH')->firstOrFail();
        $card = PaymentMethod::query()->where('code', 'CARD')->firstOrFail();
        $order = app(CheckoutService::class)->checkout(
            $user,
            $shift,
            [['product_id' => $product->id, 'quantity' => '5']],
            [
                ['payment_method_id' => $cash->id, 'amount' => '2.00'],
                ['payment_method_id' => $card->id, 'amount' => '3.00', 'reference_number' => 'TXN-001'],
            ],
        );

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('A refund cannot exceed the amount paid with the same method.');
        app(SalesReturnService::class)->complete(
            $order,
            $shift,
            $user,
            [['order_item_id' => $order->items->first()->id, 'quantity' => '2']],
            [['payment_method_id' => $cash->id, 'amount' => '3.00']],
            'Exceed cash method limit',
        );
    }

    public function test_full_cash_refund_on_partially_paid_order(): void
    {
        $user = $this->createTestUser();
        $register = $this->createTestRegister();
        $shift = app(CashSessionService::class)->open($register, $user, '20.00');
        $product = Product::query()->where('sku', 'SKU-001')->firstOrFail();
        $product->update(['price' => '5.00']);
        $cash = PaymentMethod::query()->where('code', 'CASH')->firstOrFail();
        $customer = Customer::factory()->create([
            'credit_limit' => '1000.00',
            'balance' => '0.00',
        ]);

        $order = app(CheckoutService::class)->checkout(
            $user,
            $shift,
            [['product_id' => $product->id, 'quantity' => '2']],
            [['payment_method_id' => $cash->id, 'amount' => '7.80']],
            $customer,
        );

        $this->assertSame('10.00', $order->final_amount);
        $this->assertSame('7.80', $order->paid_amount);
        $this->assertSame('2.20', $order->due_amount);

        $salesReturn = app(SalesReturnService::class)->complete(
            $order,
            $shift,
            $user,
            [['order_item_id' => $order->items->first()->id, 'quantity' => '1', 'restock' => true]],
            [['payment_method_id' => $cash->id, 'amount' => '5.00']],
            'Full cash refund on partial order',
        );

        $this->assertSame('5.00', $salesReturn->refund_amount);
        $order->refresh();
        $this->assertSame('5.00', $order->refunded_amount);
        $this->assertSame('2.80', $order->paid_amount);
        $this->assertSame('2.20', $order->due_amount);
        $this->assertDatabaseHas('payments', ['sales_return_id' => $salesReturn->id, 'amount' => '5.00', 'type' => 'refund']);
        $this->assertSame(1, $salesReturn->payments->count());
    }
}
