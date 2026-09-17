<?php

namespace App\Models;

use App\HasRolesAndPermissions;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
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

    public function openedShifts(): HasMany
    {
        return $this->hasMany(Shift::class, 'opened_by_user_id');
    }

    public function closedShifts(): HasMany
    {
        return $this->hasMany(Shift::class, 'closed_by_user_id');
    }

    public function cashMovements(): HasMany
    {
        return $this->hasMany(CashMovement::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function salesReturns(): HasMany
    {
        return $this->hasMany(SalesReturn::class);
    }

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    public function receivedGoodsReceipts(): HasMany
    {
        return $this->hasMany(GoodsReceipt::class, 'received_by_user_id');
    }

    public function customerPayments(): HasMany
    {
        return $this->hasMany(CustomerPayment::class);
    }

    public function supplierPayments(): HasMany
    {
        return $this->hasMany(SupplierPayment::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function startedInventoryCounts(): HasMany
    {
        return $this->hasMany(InventoryCount::class, 'started_by_user_id');
    }

    public function approvedInventoryCounts(): HasMany
    {
        return $this->hasMany(InventoryCount::class, 'approved_by_user_id');
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'must_change_password' => 'boolean',
        ];
    }
}
