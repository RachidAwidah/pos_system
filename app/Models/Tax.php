<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Table(name: 'taxes', key: 'id', keyType: 'string', incrementing: false, timestamps: false)]
#[Fillable([
        'tax_name',
        'tax_percentage',
    ])]
class Tax extends Model
{
    use HasFactory, HasUuids;

    protected $casts = [
        'tax_percentage' => 'decimal:2',
    ];

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

}
