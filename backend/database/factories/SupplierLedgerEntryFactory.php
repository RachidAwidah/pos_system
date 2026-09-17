<?php

namespace Database\Factories;

use App\Enums\AccountEntryType;
use App\Models\Supplier;
use App\Models\SupplierLedgerEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SupplierLedgerEntry>
 */
class SupplierLedgerEntryFactory extends Factory
{
    protected $model = SupplierLedgerEntry::class;

    public function definition(): array
    {
        $amount = fake()->randomFloat(2, 1, 1000);

        return [
            'supplier_id' => Supplier::factory(),
            'entry_type' => AccountEntryType::Purchase,
            'amount_delta' => $amount,
            'balance_before' => 0,
            'balance_after' => $amount,
            'occurred_at' => now(),
        ];
    }
}
