<?php

namespace App\Models;

use App\Enums\StockMovementType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

#[Table(name: 'stock_movements', key: 'id', keyType: 'string', incrementing: false)]
#[Fillable([
    'product_id',
    'warehouse_id',
    'user_id',
    'movement_type',
    'quantity_delta',
    'balance_before',
    'balance_after',
    'unit_cost',
    'order_id',
    'purchase_order_id',
    'goods_receipt_id',
    'inventory_count_id',
    'transfer_batch_id',
    'notes',
    'occurred_at',
])]
class StockMovement extends Model
{
    use HasFactory, HasUuids;

    protected $casts = [
        'movement_type' => StockMovementType::class,
        'quantity_delta' => 'decimal:3',
        'balance_before' => 'decimal:3',
        'balance_after' => 'decimal:3',
        'unit_cost' => 'decimal:4',
        'occurred_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new LogicException('Stock movements are append-only and cannot be updated.');
        });

        static::deleting(function (): never {
            throw new LogicException('Stock movements are append-only and cannot be deleted.');
        });
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function goodsReceipt(): BelongsTo
    {
        return $this->belongsTo(GoodsReceipt::class);
    }

    public function inventoryCount(): BelongsTo
    {
        return $this->belongsTo(InventoryCount::class);
    }
}
