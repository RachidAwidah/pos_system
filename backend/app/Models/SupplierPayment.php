<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use Database\Factories\SupplierPaymentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Table(name: 'supplier_payments', key: 'id', keyType: 'string', incrementing: false)]
#[Fillable(['supplier_id', 'user_id', 'payment_method_id', 'purchase_order_id', 'status', 'amount', 'reference_number', 'notes', 'paid_at'])]
class SupplierPayment extends Model
{
    /** @use HasFactory<SupplierPaymentFactory> */
    use HasFactory, HasUuids;

    protected $attributes = ['status' => PaymentStatus::Completed->value];

    protected function casts(): array
    {
        return ['status' => PaymentStatus::class, 'amount' => 'decimal:2', 'paid_at' => 'datetime'];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function ledgerEntry(): HasOne
    {
        return $this->hasOne(SupplierLedgerEntry::class);
    }
}
