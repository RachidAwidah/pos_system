<?php

namespace Database\Factories;

use App\Enums\AccountEntryType;
use App\Models\Customer;
use App\Models\CustomerLedgerEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomerLedgerEntry>
 */
class CustomerLedgerEntryFactory extends Factory
{
    protected $model = CustomerLedgerEntry::class;

    public function definition(): array
    {
        $amount = fake()->randomFloat(2, 1, 500);

        return [
            'customer_id' => Customer::factory(),
            'entry_type' => AccountEntryType::Sale,
            'amount_delta' => $amount,
            'balance_before' => 0,
            'balance_after' => $amount,
            'occurred_at' => now(),
        ];
    }
}
