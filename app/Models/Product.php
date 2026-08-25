<?php

namespace App\Models;

use App\Enums\ProductType;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Table(name: 'products', key: 'id', keyType: 'string', incrementing: false)]
#[Fillable([
        'product_name',
        'sku',
        'barcode',
        'unit_id',
        'type',
        'cost_price',
        'price',
        'quantity',
        'reorder_level',
        'description',
        'image',
        'tax_id',
        'category_id',
    ])]
class Product extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $casts = [
        'type' => ProductType::class,
        'cost_price' => 'decimal:2',
        'price' => 'decimal:2',
        'quantity' => 'decimal:3',
        'reorder_level' => 'decimal:3',
    ];

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function tax(): BelongsTo
    {
        return $this->belongsTo(Tax::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function orderDetails(): HasMany
    {
        return $this->hasMany(OrderDetail::class);
    }

    public function purchaseOrderDetails(): HasMany
    {
        return $this->hasMany(PurchaseOrderDetail::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }
}
