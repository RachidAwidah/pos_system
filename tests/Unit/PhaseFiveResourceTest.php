<?php

namespace Tests\Unit;

use App\Http\Resources\CustomerLedgerEntryResource;
use App\Http\Resources\SupplierProductResource;
use App\Http\Resources\SupplierResource;
use App\Models\CustomerLedgerEntry;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;

class PhaseFiveResourceTest extends TestCase
{
    public function test_supplier_purchase_total_is_separate_from_outstanding_balance(): void
    {
        foreach ([[null, '0.00'], ['125.50', '125.50']] as [$total, $expected]) {
            $supplier = new Supplier;
            $supplier->setRawAttributes(['balance' => '0.00', 'purchases_total' => $total]);
            $values = (new SupplierResource($supplier))->resolve(Request::create('/'));
            $this->assertSame($expected, $values['purchases_total']);
            $this->assertSame('0.00', $values['balance']);
        }

        $values = (new SupplierResource(new Supplier))->resolve(Request::create('/'));
        $this->assertArrayNotHasKey('purchases_total', $values);
    }

    /**
     * A basic unit test example.
     */
    public function test_ledger_debit_credit_and_stored_balance_without_database_access(): void
    {
        foreach ([['12.50', '12.50', '0.00'], ['-3.25', '0.00', '3.25'], ['0.00', '0.00', '0.00']] as [$delta, $debit, $credit]) {
            $entry = new CustomerLedgerEntry;
            $entry->setRawAttributes(['id' => 'test-entry', 'customer_id' => 'test-customer', 'entry_type' => 'adjustment', 'amount_delta' => $delta, 'balance_before' => '20.00', 'balance_after' => '16.75']);
            $entry->setRelation('order', null);
            $values = (new CustomerLedgerEntryResource($entry))->toArray(Request::create('/'));
            $this->assertSame($debit, $values['debit']);
            $this->assertSame($credit, $values['credit']);
            $this->assertSame('16.75', $values['balance_after']);
        }
    }

    public function test_supplier_cost_is_omitted_without_financial_permission(): void
    {
        $link = new SupplierProduct;
        $link->setRawAttributes(['id' => 'test-link', 'supplier_id' => 'supplier-a', 'product_id' => 'product-a', 'last_cost' => '42.0000']);
        $link->setRelation('product', null);
        $request = Request::create('/');
        $request->setUserResolver(fn () => null);
        $values = (new SupplierProductResource($link))->resolve($request);
        $this->assertArrayNotHasKey('last_cost', $values);
        $this->assertSame('supplier-a', $values['supplier_id']);
    }
}
