<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Table(name: 'filter_conditions', key: 'id', keyType: 'string', incrementing: false, timestamps: false)]
#[Fillable([
        'filter_id',
        'column_name',
        'operator',
        'filter_value',
        'logical_operator',
    ])]
class FilterCondition extends Model
{
    use HasFactory, HasUuids;

    public function filter(): BelongsTo
    {
        return $this->belongsTo(SavedFilter::class, 'filter_id');
    }
}
