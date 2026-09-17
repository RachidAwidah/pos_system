<?php

namespace App\Models;

use App\Enums\SalesReturnStatus;
use Database\Factories\SalesReturnFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Table(name: 'sales_returns', key: 'id', keyType: 'string', incrementing: false)]
#[Fillable(['return_number', 'order_id', 'user_id', 'shift_id', 'warehouse_id', 'status', 'subtotal_amount', 'tax_amount', 'refund_amount', 'reason', 'returned_at'])]
class SalesReturn extends Model
{
    /** @use HasFactory<SalesReturnFactory> */
    use HasFactory, HasUuids;

    protected $attributes = [
        'status' => SalesReturnStatus::Draft->value,
        'subtotal_amount' => 0,
        'tax_amount' => 0,
        'refund_amount' => 0,
    ];

    protected function casts(): array
    {
        return [
            'status' => SalesReturnStatus::class,
            'subtotal_amount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'refund_amount' => 'decimal:2',
            'returned_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(SalesReturnItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}
