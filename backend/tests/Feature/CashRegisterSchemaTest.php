<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CashRegisterSchemaTest extends TestCase
{
    public function test_cash_register_schema_separates_register_sessions_and_cash_movements(): void
    {
        $this->assertTrue(Schema::hasColumns('registers', [
            'warehouse_id',
            'name',
            'code',
            'is_active',
        ]));
        $this->assertTrue(Schema::hasColumns('shifts', [
            'register_id',
            'opened_by_user_id',
            'closed_by_user_id',
            'status',
            'opened_at',
            'closed_at',
            'opening_cash',
            'closing_cash',
            'expected_cash',
            'difference_amount',
        ]));
        $this->assertTrue(Schema::hasColumns('cash_movements', [
            'shift_id',
            'user_id',
            'type',
            'amount',
            'reason',
            'occurred_at',
        ]));
        $this->assertFalse(Schema::hasColumn('shifts', 'user_id'));
        $this->assertFalse(Schema::hasColumn('shifts', 'starting_cash'));
    }
}
