<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Laravel\Sanctum\PersonalAccessToken as SanctumPersonalAccessToken;

#[Table(name: 'personal_access_tokens', key: 'id', keyType: 'string', incrementing: false)]
class PersonalAccessToken extends SanctumPersonalAccessToken
{
    use HasUuids;
}
