<?php

namespace App\Models;

use App\Enums\AccountEntryType;
use Database\Factories\CustomerLedgerEntryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

#[Table(name: 'customer_ledger_entries', key: 'id', keyType: 'string', incrementing: false)]
#[Fillable(['customer_id', 'user_id', 'order_id', 'sales_return_id', 'customer_payment_id', 'entry_type', 'amount_delta', 'balance_before', 'balance_after', 'description', 'occurred_at'])]
class CustomerLedgerEntry extends Model
{
    /** @use HasFactory<CustomerLedgerEntryFactory> */
    use HasFactory, HasUuids;

    protected function casts(): array
    {
        return [
            'entry_type' => AccountEntryType::class,
            'amount_delta' => 'decimal:2',
            'balance_before' => 'decimal:2',
            'balance_after' => 'decimal:2',
            'occurred_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new LogicException('Customer ledger entries are append-only and cannot be updated.');
        });
        static::deleting(function (): never {
            throw new LogicException('Customer ledger entries are append-only and cannot be deleted.');
        });
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function salesReturn(): BelongsTo
    {
        return $this->belongsTo(SalesReturn::class);
    }

    public function customerPayment(): BelongsTo
    {
        return $this->belongsTo(CustomerPayment::class);
    }
}
