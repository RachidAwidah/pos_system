<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Table(name: 'shifts', key: 'id', keyType: 'string', incrementing: false)]
#[Fillable([
        'user_id',
        'status',
        'start_time',
        'end_time',
        'starting_cash',
        'ending_cash',
        'expected_cash',
        'difference_amount',
    ])]
class Shift extends Model
{
    use HasFactory, HasUuids;

    protected $casts = [
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'starting_cash' => 'decimal:2',
        'ending_cash' => 'decimal:2',
        'expected_cash' => 'decimal:2',
        'difference_amount' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
}
