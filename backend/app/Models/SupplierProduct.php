<?php

namespace App\Models;

use Database\Factories\SupplierProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Table(name: 'supplier_products', key: 'id', keyType: 'string', incrementing: false)]
#[Fillable(['supplier_id', 'product_id', 'supplier_sku', 'last_cost', 'minimum_order_quantity', 'lead_time_days', 'is_preferred'])]
class SupplierProduct extends Model
{
    /** @use HasFactory<SupplierProductFactory> */
    use HasFactory, HasUuids;

    protected $attributes = ['minimum_order_quantity' => 1, 'is_preferred' => false];

    protected function casts(): array
    {
        return [
            'last_cost' => 'decimal:4',
            'minimum_order_quantity' => 'decimal:3',
            'lead_time_days' => 'integer',
            'is_preferred' => 'boolean',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
