<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AccountLedgerSchemaTest extends TestCase
{
    public function test_account_and_loyalty_history_is_stored_separately_from_cached_balances(): void
    {
        $this->assertTrue(Schema::hasTable('customer_payments'));
        $this->assertTrue(Schema::hasTable('supplier_payments'));
        $this->assertTrue(Schema::hasTable('customer_ledger_entries'));
        $this->assertTrue(Schema::hasTable('supplier_ledger_entries'));
        $this->assertTrue(Schema::hasTable('loyalty_transactions'));
        $this->assertTrue(Schema::hasTable('supplier_products'));
        $this->assertTrue(Schema::hasColumns('customer_ledger_entries', [
            'entry_type',
            'amount_delta',
            'balance_before',
            'balance_after',
        ]));
        $this->assertTrue(Schema::hasColumns('supplier_products', [
            'supplier_sku',
            'last_cost',
            'minimum_order_quantity',
            'lead_time_days',
            'is_preferred',
        ]));
    }
}
