<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Table(name: 'settings', key: 'id', keyType: 'string', incrementing: false)]
#[Fillable([
    'group',
    'key',
    'value',
    'type',
    'is_public',
])]
class Setting extends Model
{
    use HasFactory, HasUuids;

    protected function casts(): array
    {
        return ['is_public' => 'boolean'];
    }

    public static function valueFor(string $key, mixed $default = null): mixed
    {
        $setting = static::query()->where('key', $key)->first();

        if (! $setting) {
            return $default;
        }

        return $setting->typedValue();
    }

    public function typedValue(): mixed
    {
        return match ($this->type) {
            'boolean' => filter_var($this->value, FILTER_VALIDATE_BOOL),
            'integer' => (int) $this->value,
            'float' => (float) $this->value,
            'json' => json_decode((string) $this->value, true),
            default => $this->value,
        };
    }
}
