<?php

namespace App\Models;

use App\Enums\InventoryCountStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Table(name: 'inventory_counts', key: 'id', keyType: 'string', incrementing: false)]
#[Fillable(['warehouse_id', 'started_by_user_id', 'approved_by_user_id', 'status', 'counted_at', 'applied_at', 'notes'])]
class InventoryCount extends Model
{
    use HasFactory, HasUuids;

    protected $attributes = [
        'status' => InventoryCountStatus::Draft->value,
    ];

    protected $casts = [
        'status' => InventoryCountStatus::class,
        'counted_at' => 'datetime',
        'applied_at' => 'datetime',
    ];

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function startedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'started_by_user_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(InventoryCountItem::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }
}
