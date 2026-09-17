<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\Customer;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class PartyManagementService
{
    /** @param array<string, mixed> $data */
    public function createCustomer(array $data): Customer
    {
        return $this->create(Customer::class, $data);
    }

    /** @param array<string, mixed> $data */
    public function updateCustomer(Customer $customer, array $data): Customer
    {
        return $this->update($customer, $data);
    }

    public function deleteCustomer(Customer $customer): void
    {
        $this->delete($customer);
    }

    /** @param array<string, mixed> $data */
    public function createSupplier(array $data): Supplier
    {
        return $this->create(Supplier::class, $data);
    }

    /** @param array<string, mixed> $data */
    public function updateSupplier(Supplier $supplier, array $data): Supplier
    {
        return $this->update($supplier, $data);
    }

    public function deleteSupplier(Supplier $supplier): void
    {
        $this->delete($supplier);
    }

    /** @param class-string<Customer|Supplier> $modelClass @param array<string, mixed> $data */
    private function create(string $modelClass, array $data): Customer|Supplier
    {
        return DB::transaction(function () use ($modelClass, $data): Customer|Supplier {
            $model = $modelClass::query()->create($data);
            AuditLogService::created($modelClass, $model->id, $this->values($model));

            return $model;
        });
    }

    /** @param array<string, mixed> $data */
    private function update(Customer|Supplier $model, array $data): Customer|Supplier
    {
        return DB::transaction(function () use ($model, $data): Customer|Supplier {
            $oldValues = $this->values($model);
            $model->update($data);
            $model->refresh();
            AuditLogService::updated($model::class, $model->id, $oldValues, $this->values($model));

            return $model;
        });
    }

    private function delete(Customer|Supplier $model): void
    {
        DB::transaction(function () use ($model): void {
            $model = $model::query()->lockForUpdate()->findOrFail($model->id);
            if (bccomp((string) $model->balance, '0.00', 2) !== 0) {
                throw new BusinessRuleException('لا يمكن حذف حساب له رصيد غير صفري.');
            }
            $hasHistory = $model->ledgerEntries()->exists() || $model->accountPayments()->exists();
            if ($model instanceof Customer) {
                $hasHistory = $hasHistory || $model->orders()->exists() || $model->loyaltyTransactions()->exists();
            } else {
                $hasHistory = $hasHistory || $model->purchaseOrders()->exists() || $model->supplierProducts()->exists();
            }
            if ($hasHistory) {
                throw new BusinessRuleException('لا يمكن حذف هذا الحساب لوجود فواتير أو حركات أو روابط تشغيلية محفوظة.');
            }
            AuditLogService::deleted($model::class, $model->id, $this->values($model));
            $model->delete();
        });
    }

    /** @return array<string, mixed> */
    private function values(Model $model): array
    {
        $fields = $model instanceof Customer
            ? ['name', 'company_name', 'email', 'phone', 'address', 'tax_number', 'credit_limit', 'balance', 'loyalty_points']
            : ['name', 'company_name', 'email', 'phone', 'address', 'tax_number', 'payable_limit', 'balance'];

        return $model->only($fields);
    }
}
