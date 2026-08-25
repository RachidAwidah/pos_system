<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Table(name: 'orders_details', key: 'id', keyType: 'string', incrementing: false, timestamps: false)]
#[Fillable([
        'order_id',
        'product_id',
        'quantity',
        'unit_price',
        'cost_price_at_sale',
        'tax_rate_applicable',
        'discount_amount',
        'tax_amount',
        'total_price',
    ])]
class OrderDetail extends Model
{
    use HasFactory, HasUuids;

    protected $casts = [
        'quantity' => 'decimal:3',
        'unit_price' => 'decimal:2',
        'cost_price_at_sale' => 'decimal:2',
        'tax_rate_applicable' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total_price' => 'decimal:2',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

}
