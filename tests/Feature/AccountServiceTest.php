<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Register;
use App\Models\User;
use App\Services\CashSessionService;
use App\Services\CheckoutService;
use App\Services\CustomerAccountService;
use DomainException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class AccountServiceTest extends TestCase
{
    use DatabaseTransactions;

    public function test_customer_debt_payment_updates_order_balance_and_append_only_ledger(): void
    {
        $user = User::query()->firstOrFail();
        $register = Register::query()->where('code', 'MAIN-REG-01')->firstOrFail();
        $shift = app(CashSessionService::class)->open($register, $user, '0');
        $product = Product::query()->where('sku', 'SKU-001')->firstOrFail();
        $customer = Customer::factory()->create(['credit_limit' => '10.00', 'balance' => '0.00']);
        $order = app(CheckoutService::class)->checkout(
            $user,
            $shift,
            [['product_id' => $product->id, 'quantity' => '5']],
            [],
            $customer,
        );

        $payment = app(CustomerAccountService::class)->collectPayment(
            $customer,
            $order,
            $user,
            PaymentMethod::query()->where('code', 'CASH')->firstOrFail(),
            '2.00',
        );

        $this->assertSame('3.00', $customer->refresh()->balance);
        $this->assertSame('3.00', $order->refresh()->due_amount);
        $this->assertDatabaseHas('customer_ledger_entries', ['customer_payment_id' => $payment->id, 'amount_delta' => '-2.00']);
        $this->assertGreaterThan(0, $customer->loyalty_points);
    }

    public function test_customer_payment_cannot_exceed_order_due(): void
    {
        $user = User::query()->firstOrFail();
        $register = Register::query()->where('code', 'MAIN-REG-01')->firstOrFail();
        $shift = app(CashSessionService::class)->open($register, $user, '0');
        $product = Product::query()->where('sku', 'SKU-001')->firstOrFail();
        $customer = Customer::factory()->create(['credit_limit' => '10.00', 'balance' => '0.00']);
        $order = app(CheckoutService::class)->checkout(
            $user,
            $shift,
            [['product_id' => $product->id, 'quantity' => '1']],
            [],
            $customer,
        );

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('The payment exceeds the amount due on the order.');
        app(CustomerAccountService::class)->collectPayment(
            $customer,
            $order,
            $user,
            PaymentMethod::query()->where('code', 'CASH')->firstOrFail(),
            '2.00',
        );
    }
}
