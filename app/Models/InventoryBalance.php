<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Table(name: 'inventory_balances', key: 'id', keyType: 'string', incrementing: false)]
#[Fillable(['product_id', 'warehouse_id', 'quantity_on_hand', 'quantity_reserved', 'reorder_level', 'average_cost'])]
class InventoryBalance extends Model
{
    use HasFactory, HasUuids;

    protected $attributes = [
        'quantity_on_hand' => 0,
        'quantity_reserved' => 0,
        'reorder_level' => 0,
        'average_cost' => 0,
    ];

    protected $casts = [
        'quantity_on_hand' => 'decimal:3',
        'quantity_reserved' => 'decimal:3',
        'reorder_level' => 'decimal:3',
        'average_cost' => 'decimal:4',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }
}
