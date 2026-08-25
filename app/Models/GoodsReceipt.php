<?php

namespace App\Models;

use App\Enums\GoodsReceiptStatus;
use Database\Factories\GoodsReceiptFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Table(name: 'goods_receipts', key: 'id', keyType: 'string', incrementing: false)]
#[Fillable(['receipt_number', 'purchase_order_id', 'warehouse_id', 'received_by_user_id', 'status', 'subtotal_amount', 'discount_amount', 'tax_amount', 'total_amount', 'supplier_reference', 'notes', 'received_at'])]
class GoodsReceipt extends Model
{
    /** @use HasFactory<GoodsReceiptFactory> */
    use HasFactory, HasUuids;

    protected $attributes = [
        'status' => GoodsReceiptStatus::Completed->value,
        'subtotal_amount' => 0,
        'discount_amount' => 0,
        'tax_amount' => 0,
        'total_amount' => 0,
    ];

    protected function casts(): array
    {
        return [
            'status' => GoodsReceiptStatus::class,
            'subtotal_amount' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'received_at' => 'datetime',
        ];
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by_user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(GoodsReceiptItem::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function supplierLedgerEntries(): HasMany
    {
        return $this->hasMany(SupplierLedgerEntry::class);
    }
}
