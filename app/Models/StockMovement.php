<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Table(name: 'stock_movements', key: 'id', keyType: 'string', incrementing: false)]
#[Fillable([
        'product_id',
        'user_id',
        'movement_type',
        'quantity',
        'before_quantity',
        'after_quantity',
        'order_id',
        'purchase_order_id',
        'notes',
    ])]
class StockMovement extends Model
{
    use HasFactory, HasUuids;

    protected $casts = [
        'quantity' => 'decimal:3',
        'before_quantity' => 'decimal:3',
        'after_quantity' => 'decimal:3',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }
}
