<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Table(name: 'ai_importers', key: 'id', keyType: 'string', incrementing: false, timestamps: false)]
#[Fillable([
        'file_path',
        'status',
        'raw_ai_response',
        'processed_by_user_id',
    ])]
class AiImporter extends Model
{
    use HasFactory, HasUuids;

    public function processedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by_user_id');
    }
}
