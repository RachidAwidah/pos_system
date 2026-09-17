<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Services\CashSessionService;
use App\Services\CheckoutService;
use App\Services\ReportService;
use App\Services\SalesReturnService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ReportServiceTest extends TestCase
{
    use DatabaseTransactions;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_reports_calculate_net_sales_profit_and_restocked_returns(): void
    {
        CarbonImmutable::setTestNow('2030-04-15 09:00:00');
        $user = $this->createTestUser();
        $register = $this->createTestRegister();
        $cash = PaymentMethod::query()->where('code', 'CASH')->firstOrFail();
        $product = Product::query()->where('sku', 'SKU-001')->firstOrFail();
        $customer = Customer::factory()->create(['name' => 'Report Customer', 'credit_limit' => '100.00', 'balance' => '0.00']);
        $shift = app(CashSessionService::class)->open($register, $user, '20.00');
        $order = app(CheckoutService::class)->checkout(
            $user,
            $shift,
            [['product_id' => $product->id, 'quantity' => '2']],
            [['payment_method_id' => $cash->id, 'amount' => '2.00', 'amount_tendered' => '2.00']],
            $customer,
        );

        CarbonImmutable::setTestNow('2030-04-15 10:00:00');
        app(SalesReturnService::class)->complete(
            $order,
            $shift,
            $user,
            [['order_item_id' => $order->items->first()->id, 'quantity' => '1', 'restock' => true]],
            [['payment_method_id' => $cash->id, 'amount' => '1.00']],
            'Customer changed their mind.',
        );

        $filters = ['from' => '2030-04-01', 'to' => '2030-04-30'];
        $summary = app(ReportService::class)->summary($filters);
        $products = app(ReportService::class)->products($filters, 10);
        $customers = app(ReportService::class)->customers($filters, 10);

        $this->assertSame(1, $summary['orders_count']);
        $this->assertSame(1, $summary['returns_count']);
        $this->assertSame('1.00', $summary['net_sales']);
        $this->assertSame(bcadd((string) $order->items->first()->cost_price_at_sale, '0.005', 2), $summary['cost_of_goods_sold']);
        $this->assertSame(bcsub($summary['net_sales'], $summary['cost_of_goods_sold'], 2), $summary['gross_profit']);
        $this->assertSame('1.000', $summary['net_quantity']);
        $this->assertSame($product->id, $products[0]['product_id']);
        $this->assertSame('1.000', $products[0]['net_quantity']);
        $this->assertSame($customer->id, $customers[0]['customer_id']);
        $this->assertSame('1.00', $customers[0]['net_sales']);
    }
}
