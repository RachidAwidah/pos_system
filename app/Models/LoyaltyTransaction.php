<?php

namespace App\Models;

use App\Enums\LoyaltyTransactionType;
use Database\Factories\LoyaltyTransactionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

#[Table(name: 'loyalty_transactions', key: 'id', keyType: 'string', incrementing: false)]
#[Fillable(['customer_id', 'user_id', 'order_id', 'sales_return_id', 'transaction_type', 'points_delta', 'balance_before', 'balance_after', 'reason', 'occurred_at'])]
class LoyaltyTransaction extends Model
{
    /** @use HasFactory<LoyaltyTransactionFactory> */
    use HasFactory, HasUuids;

    protected function casts(): array
    {
        return [
            'transaction_type' => LoyaltyTransactionType::class,
            'points_delta' => 'integer',
            'balance_before' => 'integer',
            'balance_after' => 'integer',
            'occurred_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new LogicException('Loyalty transactions are append-only and cannot be updated.');
        });
        static::deleting(function (): never {
            throw new LogicException('Loyalty transactions are append-only and cannot be deleted.');
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
}
