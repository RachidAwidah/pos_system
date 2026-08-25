<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Table(name: 'purchase_order_details', key: 'id', keyType: 'string', incrementing: false, timestamps: false)]
#[Fillable([
        'purchase_order_id',
        'product_id',
        'quantity',
        'cost_price',
    ])]
class PurchaseOrderDetail extends Model
{
    use HasFactory, HasUuids;

    protected $casts = [
        'quantity' => 'decimal:3',
        'cost_price' => 'decimal:2',
    ];

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
