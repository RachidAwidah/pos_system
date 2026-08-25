<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class SupplierProductService
{
    public function save(
        Supplier $supplier,
        Product $product,
        ?string $supplierSku = null,
        ?string $lastCost = null,
        string $minimumOrderQuantity = '1',
        ?int $leadTimeDays = null,
        bool $isPreferred = false,
    ): SupplierProduct {
        $normalizedLastCost = $lastCost === null ? null : $this->normalizeDecimal($lastCost, 4);
        $normalizedMinimumQuantity = $this->normalizePositiveDecimal($minimumOrderQuantity, 3);
        if ($leadTimeDays !== null && ($leadTimeDays < 0 || $leadTimeDays > 65535)) {
            throw new InvalidArgumentException('Lead time days must be between 0 and 65535.');
        }

        return DB::transaction(function () use ($supplier, $product, $supplierSku, $normalizedLastCost, $normalizedMinimumQuantity, $leadTimeDays, $isPreferred): SupplierProduct {
            $supplierProduct = SupplierProduct::query()
                ->whereBelongsTo($supplier)
                ->whereBelongsTo($product)
                ->lockForUpdate()
                ->first();

            if ($isPreferred) {
                $this->clearPreferredSupplier($product, $supplierProduct?->id);
            }

            $values = [
                'supplier_id' => $supplier->id,
                'product_id' => $product->id,
                'supplier_sku' => $supplierSku,
                'last_cost' => $normalizedLastCost,
                'minimum_order_quantity' => $normalizedMinimumQuantity,
                'lead_time_days' => $leadTimeDays,
                'is_preferred' => $isPreferred,
            ];

            if ($supplierProduct === null) {
                $supplierProduct = SupplierProduct::query()->create($values);
                AuditLogService::created(SupplierProduct::class, $supplierProduct->id, $this->values($supplierProduct));

                return $supplierProduct;
            }

            $oldValues = $this->values($supplierProduct);
            $supplierProduct->update($values);
            $supplierProduct->refresh();
            AuditLogService::updated(SupplierProduct::class, $supplierProduct->id, $oldValues, $this->values($supplierProduct));

            return $supplierProduct;
        }, attempts: 5);
    }

    private function clearPreferredSupplier(Product $product, ?string $exceptId): void
    {
        $preferredLinks = SupplierProduct::query()
            ->whereBelongsTo($product)
            ->where('is_preferred', true)
            ->when($exceptId !== null, fn ($query) => $query->where('id', '<>', $exceptId))
            ->lockForUpdate()
            ->get();

        foreach ($preferredLinks as $preferredLink) {
            $oldValues = $this->values($preferredLink);
            $preferredLink->update(['is_preferred' => false]);
            $preferredLink->refresh();
            AuditLogService::updated(SupplierProduct::class, $preferredLink->id, $oldValues, $this->values($preferredLink));
        }
    }

    private function normalizePositiveDecimal(string $value, int $scale): string
    {
        $normalizedValue = $this->normalizeDecimal($value, $scale);
        if (bccomp($normalizedValue, '0', $scale) !== 1) {
            throw new InvalidArgumentException('Minimum order quantity must be greater than zero.');
        }

        return $normalizedValue;
    }

    private function normalizeDecimal(string $value, int $scale): string
    {
        $value = trim($value);
        if (! preg_match('/^\d+(?:\.\d{1,'.$scale.'})?$/', $value)) {
            throw new InvalidArgumentException("Value must be a non-negative decimal with at most {$scale} decimal places.");
        }

        return bcadd($value, '0', $scale);
    }

    /** @return array<string, mixed> */
    private function values(SupplierProduct $supplierProduct): array
    {
        return [
            'supplier_id' => $supplierProduct->supplier_id,
            'product_id' => $supplierProduct->product_id,
            'supplier_sku' => $supplierProduct->supplier_sku,
            'last_cost' => $supplierProduct->last_cost === null ? null : (string) $supplierProduct->last_cost,
            'minimum_order_quantity' => (string) $supplierProduct->minimum_order_quantity,
            'lead_time_days' => $supplierProduct->lead_time_days,
            'is_preferred' => $supplierProduct->is_preferred,
        ];
    }
}
