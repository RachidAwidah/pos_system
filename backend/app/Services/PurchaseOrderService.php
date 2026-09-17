<?php

namespace App\Services;

use App\Enums\PurchaseOrderStatus;
use App\Exceptions\BusinessInputException as InvalidArgumentException;
use App\Exceptions\BusinessRuleException as DomainException;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;

class PurchaseOrderService
{
    /**
     * @param  array<int, array{product_id: string, quantity: string, unit_cost: string, discount_amount?: string}>  $items
     */
    public function createDraft(
        User $user,
        Supplier $supplier,
        Warehouse $warehouse,
        array $items,
        ?string $expectedAt = null,
        ?string $notes = null,
    ): PurchaseOrder {
        if (! $warehouse->is_active) {
            throw new DomainException('A purchase order cannot target an inactive warehouse.');
        }

        if ($items === []) {
            throw new InvalidArgumentException('A purchase order must contain at least one item.');
        }

        return DB::transaction(function () use ($user, $supplier, $warehouse, $items, $expectedAt, $notes): PurchaseOrder {
            [$totals, $itemValues] = $this->prepareItems($items);

            $purchaseOrder = PurchaseOrder::query()->create([
                'user_id' => $user->id,
                'supplier_id' => $supplier->id,
                'warehouse_id' => $warehouse->id,
                'status' => PurchaseOrderStatus::Draft,
                ...$totals,
                'expected_at' => $expectedAt,
                'notes' => $notes,
            ]);
            AuditLogService::created(PurchaseOrder::class, $purchaseOrder->id, $this->purchaseOrderValues($purchaseOrder));

            foreach ($itemValues as $values) {
                $purchaseOrderItem = $purchaseOrder->items()->create($values);
                AuditLogService::created(PurchaseOrderItem::class, $purchaseOrderItem->id, $this->itemValues($purchaseOrderItem));
            }

            return $purchaseOrder->load('items');
        }, attempts: 5);
    }

    /**
     * @param  array<int, array{product_id: string, quantity: string, unit_cost: string, discount_amount?: string}>  $items
     */
    public function updateDraft(
        PurchaseOrder $purchaseOrder,
        Supplier $supplier,
        Warehouse $warehouse,
        array $items,
        ?string $expectedAt = null,
        ?string $notes = null,
    ): PurchaseOrder {
        if (! $warehouse->is_active) {
            throw new DomainException('A purchase order cannot target an inactive warehouse.');
        }

        if ($items === []) {
            throw new InvalidArgumentException('A purchase order must contain at least one item.');
        }

        return DB::transaction(function () use ($purchaseOrder, $supplier, $warehouse, $items, $expectedAt, $notes): PurchaseOrder {
            $lockedPurchaseOrder = PurchaseOrder::query()->lockForUpdate()->findOrFail($purchaseOrder->id);
            if ($lockedPurchaseOrder->status !== PurchaseOrderStatus::Draft) {
                throw new DomainException('Only draft purchase orders can be updated.');
            }

            [$totals, $itemValues] = $this->prepareItems($items);
            $oldValues = $this->purchaseOrderValues($lockedPurchaseOrder);
            $lockedPurchaseOrder->update([
                'supplier_id' => $supplier->id,
                'warehouse_id' => $warehouse->id,
                ...$totals,
                'expected_at' => $expectedAt,
                'notes' => $notes,
            ]);
            $lockedPurchaseOrder->refresh();
            AuditLogService::updated(PurchaseOrder::class, $lockedPurchaseOrder->id, $oldValues, $this->purchaseOrderValues($lockedPurchaseOrder));

            foreach ($lockedPurchaseOrder->items()->lockForUpdate()->get() as $existingItem) {
                AuditLogService::deleted(PurchaseOrderItem::class, $existingItem->id, $this->itemValues($existingItem));
                $existingItem->delete();
            }

            foreach ($itemValues as $values) {
                $purchaseOrderItem = $lockedPurchaseOrder->items()->create($values);
                AuditLogService::created(PurchaseOrderItem::class, $purchaseOrderItem->id, $this->itemValues($purchaseOrderItem));
            }

            return $lockedPurchaseOrder->load('items');
        }, attempts: 5);
    }

    public function deleteDraft(PurchaseOrder $purchaseOrder): void
    {
        DB::transaction(function () use ($purchaseOrder): void {
            $lockedPurchaseOrder = PurchaseOrder::query()->lockForUpdate()->findOrFail($purchaseOrder->id);
            if ($lockedPurchaseOrder->status !== PurchaseOrderStatus::Draft) {
                throw new DomainException('Only draft purchase orders can be deleted.');
            }

            foreach ($lockedPurchaseOrder->items()->lockForUpdate()->get() as $item) {
                AuditLogService::deleted(PurchaseOrderItem::class, $item->id, $this->itemValues($item));
                $item->delete();
            }

            AuditLogService::deleted(PurchaseOrder::class, $lockedPurchaseOrder->id, $this->purchaseOrderValues($lockedPurchaseOrder));
            $lockedPurchaseOrder->delete();
        }, attempts: 5);
    }

    public function send(PurchaseOrder $purchaseOrder): PurchaseOrder
    {
        return DB::transaction(function () use ($purchaseOrder): PurchaseOrder {
            $lockedPurchaseOrder = PurchaseOrder::query()->lockForUpdate()->findOrFail($purchaseOrder->id);
            if ($lockedPurchaseOrder->status !== PurchaseOrderStatus::Draft) {
                throw new DomainException('Only draft purchase orders can be sent.');
            }

            $oldValues = $this->purchaseOrderValues($lockedPurchaseOrder);
            $lockedPurchaseOrder->update([
                'status' => PurchaseOrderStatus::Sent,
                'ordered_at' => now(),
            ]);
            $lockedPurchaseOrder->refresh();
            AuditLogService::updated(PurchaseOrder::class, $lockedPurchaseOrder->id, $oldValues, $this->purchaseOrderValues($lockedPurchaseOrder));

            return $lockedPurchaseOrder;
        }, attempts: 5);
    }

    public function cancel(PurchaseOrder $purchaseOrder, string $reason): PurchaseOrder
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw new InvalidArgumentException('A cancellation reason is required.');
        }

        return DB::transaction(function () use ($purchaseOrder, $reason): PurchaseOrder {
            $lockedPurchaseOrder = PurchaseOrder::query()->lockForUpdate()->findOrFail($purchaseOrder->id);
            if (! in_array($lockedPurchaseOrder->status, [PurchaseOrderStatus::Draft, PurchaseOrderStatus::Sent], true)) {
                throw new DomainException('This purchase order can no longer be cancelled.');
            }

            if ($lockedPurchaseOrder->items()->where('received_quantity', '>', 0)->exists()) {
                throw new DomainException('A purchase order with received stock cannot be cancelled.');
            }

            $oldValues = $this->purchaseOrderValues($lockedPurchaseOrder);
            $lockedPurchaseOrder->update([
                'status' => PurchaseOrderStatus::Cancelled,
                'cancelled_at' => now(),
                'cancellation_reason' => $reason,
            ]);
            $lockedPurchaseOrder->refresh();
            AuditLogService::updated(PurchaseOrder::class, $lockedPurchaseOrder->id, $oldValues, $this->purchaseOrderValues($lockedPurchaseOrder));

            return $lockedPurchaseOrder;
        }, attempts: 5);
    }

    /**
     * @param  array<int, array{product_id: string, quantity: string, unit_cost: string, discount_amount?: string}>  $items
     * @return array<int, array{product: Product, quantity: string, unit_cost: string, discount_amount: string}>
     */
    private function normalizeItems(array $items): array
    {
        $itemsByProductId = [];
        foreach ($items as $item) {
            $productId = $item['product_id'] ?? '';
            if ($productId === '' || isset($itemsByProductId[$productId])) {
                throw new InvalidArgumentException('Every purchase order item must reference a unique product.');
            }

            $itemsByProductId[$productId] = $item;
        }

        ksort($itemsByProductId);
        $products = Product::query()
            ->with('tax')
            ->whereIn('id', array_keys($itemsByProductId))
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        if ($products->count() !== count($itemsByProductId)) {
            throw new InvalidArgumentException('One or more purchase order products do not exist.');
        }

        $normalizedItems = [];
        foreach ($itemsByProductId as $productId => $item) {
            $normalizedItems[] = [
                'product' => $products->get($productId),
                'quantity' => $this->normalizePositiveDecimal((string) ($item['quantity'] ?? ''), 3),
                'unit_cost' => $this->normalizeDecimal((string) ($item['unit_cost'] ?? ''), 4),
                'discount_amount' => $this->normalizeDecimal((string) ($item['discount_amount'] ?? '0'), 2),
            ];
        }

        return $normalizedItems;
    }

    /**
     * @param  array<int, array{product_id: string, quantity: string, unit_cost: string, discount_amount?: string}>  $items
     * @return array{array{subtotal_amount: string, discount_amount: string, tax_amount: string, total_amount: string}, array<int, array<string, string>>}
     */
    private function prepareItems(array $items): array
    {
        $subtotalAmount = '0.00';
        $discountAmount = '0.00';
        $taxAmount = '0.00';
        $itemValues = [];

        foreach ($this->normalizeItems($items) as $item) {
            $product = $item['product'];
            $lineSubtotal = $this->roundMoney(bcmul($item['quantity'], $item['unit_cost'], 6));
            if (bccomp($item['discount_amount'], $lineSubtotal, 2) === 1) {
                throw new InvalidArgumentException('An item discount cannot exceed its subtotal.');
            }

            $taxableAmount = bcsub($lineSubtotal, $item['discount_amount'], 2);
            $taxRate = $this->normalizeDecimal((string) ($product->tax?->tax_percentage ?? 0), 4);
            $lineTax = $this->roundMoney(bcdiv(bcmul($taxableAmount, $taxRate, 6), '100', 6));
            $lineTotal = bcadd($taxableAmount, $lineTax, 2);
            $subtotalAmount = bcadd($subtotalAmount, $lineSubtotal, 2);
            $discountAmount = bcadd($discountAmount, $item['discount_amount'], 2);
            $taxAmount = bcadd($taxAmount, $lineTax, 2);
            $itemValues[] = [
                'product_id' => $product->id,
                'product_name' => $product->product_name,
                'sku' => $product->sku,
                'ordered_quantity' => $item['quantity'],
                'received_quantity' => '0.000',
                'unit_cost' => $item['unit_cost'],
                'tax_rate' => $taxRate,
                'subtotal_amount' => $lineSubtotal,
                'discount_amount' => $item['discount_amount'],
                'tax_amount' => $lineTax,
                'total_amount' => $lineTotal,
            ];
        }

        return [[
            'subtotal_amount' => $subtotalAmount,
            'discount_amount' => $discountAmount,
            'tax_amount' => $taxAmount,
            'total_amount' => bcadd(bcsub($subtotalAmount, $discountAmount, 2), $taxAmount, 2),
        ], $itemValues];
    }

    private function normalizePositiveDecimal(string $value, int $scale): string
    {
        $normalizedValue = $this->normalizeDecimal($value, $scale);
        if (bccomp($normalizedValue, '0', $scale) !== 1) {
            throw new InvalidArgumentException('Quantity must be greater than zero.');
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

    private function roundMoney(string $value): string
    {
        return bcadd(bcadd($value, '0.005', 3), '0', 2);
    }

    /** @return array<string, mixed> */
    private function purchaseOrderValues(PurchaseOrder $purchaseOrder): array
    {
        return [
            'purchase_order_number' => $purchaseOrder->purchase_order_number,
            'user_id' => $purchaseOrder->user_id,
            'supplier_id' => $purchaseOrder->supplier_id,
            'warehouse_id' => $purchaseOrder->warehouse_id,
            'status' => $purchaseOrder->status->value,
            'subtotal_amount' => (string) $purchaseOrder->subtotal_amount,
            'discount_amount' => (string) $purchaseOrder->discount_amount,
            'tax_amount' => (string) $purchaseOrder->tax_amount,
            'total_amount' => (string) $purchaseOrder->total_amount,
            'ordered_at' => $purchaseOrder->ordered_at?->toISOString(),
            'expected_at' => $purchaseOrder->expected_at?->toDateString(),
            'notes' => $purchaseOrder->notes,
            'cancelled_at' => $purchaseOrder->cancelled_at?->toISOString(),
            'cancellation_reason' => $purchaseOrder->cancellation_reason,
        ];
    }

    /** @return array<string, string> */
    private function itemValues(PurchaseOrderItem $item): array
    {
        return [
            'purchase_order_id' => $item->purchase_order_id,
            'product_id' => $item->product_id,
            'ordered_quantity' => (string) $item->ordered_quantity,
            'received_quantity' => (string) $item->received_quantity,
            'unit_cost' => (string) $item->unit_cost,
            'total_amount' => (string) $item->total_amount,
        ];
    }
}
