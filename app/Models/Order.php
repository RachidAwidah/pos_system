<?php

namespace App\Models;

use App\Enums\OrderPaymentStatus;
use App\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Table(name: 'orders', key: 'id', keyType: 'string', incrementing: false)]
#[Fillable([
    'invoice_number',
    'user_id',
    'customer_id',
    'shift_id',
    'warehouse_id',
    'status',
    'subtotal_amount',
    'discount_amount',
    'tax_amount',
    'final_amount',
    'paid_amount',
    'due_amount',
    'refunded_amount',
    'payment_status',
    'notes',
    'order_date',
    'completed_at',
    'cancelled_at',
    'cancellation_reason',
])]
class Order extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $attributes = [
        'status' => OrderStatus::Draft->value,
        'subtotal_amount' => 0,
        'discount_amount' => 0,
        'tax_amount' => 0,
        'final_amount' => 0,
        'paid_amount' => 0,
        'due_amount' => 0,
        'refunded_amount' => 0,
        'payment_status' => OrderPaymentStatus::Unpaid->value,
    ];

    protected $casts = [
        'status' => OrderStatus::class,
        'subtotal_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'final_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'due_amount' => 'decimal:2',
        'refunded_amount' => 'decimal:2',
        'payment_status' => OrderPaymentStatus::class,
        'order_date' => 'datetime',
        'completed_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function customerPayments(): HasMany
    {
        return $this->hasMany(CustomerPayment::class);
    }

    public function loyaltyTransactions(): HasMany
    {
        return $this->hasMany(LoyaltyTransaction::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function returns(): HasMany
    {
        return $this->hasMany(SalesReturn::class);
    }
}
