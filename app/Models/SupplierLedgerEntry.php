<?php

namespace App\Models;

use App\Enums\AccountEntryType;
use Database\Factories\SupplierLedgerEntryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

#[Table(name: 'supplier_ledger_entries', key: 'id', keyType: 'string', incrementing: false)]
#[Fillable(['supplier_id', 'user_id', 'purchase_order_id', 'goods_receipt_id', 'supplier_payment_id', 'entry_type', 'amount_delta', 'balance_before', 'balance_after', 'description', 'occurred_at'])]
class SupplierLedgerEntry extends Model
{
    /** @use HasFactory<SupplierLedgerEntryFactory> */
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
            throw new LogicException('Supplier ledger entries are append-only and cannot be updated.');
        });
        static::deleting(function (): never {
            throw new LogicException('Supplier ledger entries are append-only and cannot be deleted.');
        });
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function goodsReceipt(): BelongsTo
    {
        return $this->belongsTo(GoodsReceipt::class);
    }

    public function supplierPayment(): BelongsTo
    {
        return $this->belongsTo(SupplierPayment::class);
    }
}
