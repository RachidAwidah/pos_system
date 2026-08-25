<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use App\HasRolesAndPermissions;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Table(name: 'users', key: 'id', keyType: 'string', incrementing: false)]
#[Fillable([
        'full_name',
        'email',
        'phone',
        'password_hash',
        'must_change_password',
    ])]
class User extends Authenticatable
{
    use HasApiTokens, HasFactory, HasRolesAndPermissions, HasUuids, Notifiable;

    protected $hidden = [
        'password_hash',
        'remember_token',
    ];

    public function getAuthPasswordName(): string
    {
        return 'password_hash';
    }

    public function savedFilters(): HasMany
    {
        return $this->hasMany(SavedFilter::class);
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    public function aiImports(): HasMany
    {
        return $this->hasMany(AiImporter::class, 'processed_by_user_id');
    }

    public function shifts(): HasMany
    {
        return $this->hasMany(Shift::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'must_change_password' => 'boolean',
        ];
    }
}
