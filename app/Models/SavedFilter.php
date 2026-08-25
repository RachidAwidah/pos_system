<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Table(name: 'saved_filters', key: 'id', keyType: 'string', incrementing: false, timestamps: false)]
#[Fillable([
        'user_id',
        'target_screen',
        'filter_name',
        'is_public',
    ])]
class SavedFilter extends Model
{
    use HasFactory, HasUuids;

    protected $casts = [
        'is_public' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function conditions(): HasMany
    {
        return $this->hasMany(FilterCondition::class, 'filter_id');
    }
}
