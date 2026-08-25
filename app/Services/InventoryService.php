<?php

namespace App\Services;

use App\Enums\InventoryCountStatus;
use App\Enums\StockMovementType;
use App\Models\GoodsReceipt;
use App\Models\InventoryBalance;
use App\Models\InventoryCount;
use App\Models\InventoryCountItem;
use App\Models\Order;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class InventoryService
{
    public function setOpeningBalance(
        Product $product,
        Warehouse $warehouse,
        string $quantity,
        string $reorderLevel = '0',
        ?string $unitCost = null,
        ?User $user = null,
    ): InventoryBalance {
        $normalizedQuantity = $this->normalizeDecimal($quantity);
        $normalizedReorderLevel = $this->normalizeDecimal($reorderLevel);
        $normalizedUnitCost = $unitCost === null ? null : $this->normalizeDecimal($unitCost, 4);

        return DB::transaction(function () use ($product, $warehouse, $normalizedQuantity, $normalizedReorderLevel, $normalizedUnitCost, $user): InventoryBalance {
            $balance = $this->balanceForUpdate($product, $warehouse);
            $existingOpeningMovement = StockMovement::query()
                ->whereBelongsTo($product)
                ->whereBelongsTo($warehouse)
                ->where('movement_type', StockMovementType::Opening->value)
                ->first();

            if ($existingOpeningMovement !== null) {
                return $balance;
            }

            if (bccomp((string) $balance->quantity_on_hand, '0.000', 3) !== 0) {
                throw new DomainException('An opening balance cannot replace existing inventory.');
            }

            $oldValues = $this->balanceValues($balance);
            $balance->update([
                'quantity_on_hand' => $normalizedQuantity,
                'reorder_level' => $normalizedReorderLevel,
                'average_cost' => $normalizedUnitCost ?? '0.0000',
            ]);
            $balance->refresh();
            AuditLogService::updated(InventoryBalance::class, $balance->id, $oldValues, $this->balanceValues($balance));

            if (bccomp($normalizedQuantity, '0.000', 3) === 1) {
                $this->createMovement(
                    $product,
                    $warehouse,
                    StockMovementType::Opening,
                    $normalizedQuantity,
                    '0.000',
                    $normalizedQuantity,
                    $user,
                    unitCost: $normalizedUnitCost,
                    notes: 'Opening inventory balance',
                );
            }

            return $balance;
        }, attempts: 5);
    }

    public function receive(
        Product $product,
        Warehouse $warehouse,
        string $quantity,
        ?User $user = null,
        ?PurchaseOrder $purchaseOrder = null,
        ?GoodsReceipt $goodsReceipt = null,
        ?string $unitCost = null,
        ?string $notes = null,
    ): StockMovement {
        return $this->changeStock(
            $product,
            $warehouse,
            StockMovementType::Purchase,
            $this->normalizePositiveQuantity($quantity),
            $user,
            purchaseOrder: $purchaseOrder,
            goodsReceipt: $goodsReceipt,
            unitCost: $unitCost,
            notes: $notes,
            updateAverageCost: true,
        );
    }

    public function sell(
        Product $product,
        Warehouse $warehouse,
        string $quantity,
        ?User $user = null,
        ?Order $order = null,
        ?string $notes = null,
    ): StockMovement {
        return $this->changeStock(
            $product,
            $warehouse,
            StockMovementType::Sale,
            bcmul($this->normalizePositiveQuantity($quantity), '-1', 3),
            $user,
            order: $order,
            notes: $notes,
        );
    }

    public function customerReturn(
        Product $product,
        Warehouse $warehouse,
        string $quantity,
        ?User $user = null,
        ?Order $order = null,
        ?string $notes = null,
    ): StockMovement {
        return $this->changeStock(
            $product,
            $warehouse,
            StockMovementType::CustomerReturn,
            $this->normalizePositiveQuantity($quantity),
            $user,
            order: $order,
            notes: $notes,
        );
    }

    public function supplierReturn(
        Product $product,
        Warehouse $warehouse,
        string $quantity,
        ?User $user = null,
        ?PurchaseOrder $purchaseOrder = null,
        ?string $notes = null,
    ): StockMovement {
        return $this->changeStock(
            $product,
            $warehouse,
            StockMovementType::SupplierReturn,
            bcmul($this->normalizePositiveQuantity($quantity), '-1', 3),
            $user,
            purchaseOrder: $purchaseOrder,
            notes: $notes,
        );
    }

    public function recordDamage(
        Product $product,
        Warehouse $warehouse,
        string $quantity,
        ?User $user = null,
        ?string $notes = null,
    ): StockMovement {
        return $this->changeStock(
            $product,
            $warehouse,
            StockMovementType::Damage,
            bcmul($this->normalizePositiveQuantity($quantity), '-1', 3),
            $user,
            notes: $notes,
        );
    }

    public function adjustTo(
        Product $product,
        Warehouse $warehouse,
        string $countedQuantity,
        ?User $user = null,
        ?InventoryCount $inventoryCount = null,
        ?string $notes = null,
    ): StockMovement {
        $normalizedCountedQuantity = $this->normalizeDecimal($countedQuantity);

        return DB::transaction(function () use ($product, $warehouse, $normalizedCountedQuantity, $user, $inventoryCount, $notes): StockMovement {
            $balance = $this->balanceForUpdate($product, $warehouse);
            $quantityDelta = bcsub($normalizedCountedQuantity, (string) $balance->quantity_on_hand, 3);

            if (bccomp($quantityDelta, '0.000', 3) === 0) {
                throw new DomainException('The counted quantity matches the current inventory balance.');
            }

            return $this->applyLockedMovement(
                $balance,
                StockMovementType::Adjustment,
                $quantityDelta,
                $user,
                inventoryCount: $inventoryCount,
                notes: $notes,
            );
        }, attempts: 5);
    }

    /** @return array{out: StockMovement, in: StockMovement} */
    public function transfer(
        Product $product,
        Warehouse $sourceWarehouse,
        Warehouse $destinationWarehouse,
        string $quantity,
        ?User $user = null,
        ?string $notes = null,
    ): array {
        if ($sourceWarehouse->is($destinationWarehouse)) {
            throw new InvalidArgumentException('Source and destination warehouses must be different.');
        }

        $normalizedQuantity = $this->normalizePositiveQuantity($quantity);

        return DB::transaction(function () use ($product, $sourceWarehouse, $destinationWarehouse, $normalizedQuantity, $user, $notes): array {
            $warehouseIds = [$sourceWarehouse->id, $destinationWarehouse->id];
            sort($warehouseIds);

            $lockedBalances = [];
            foreach ($warehouseIds as $warehouseId) {
                $warehouse = $warehouseId === $sourceWarehouse->id ? $sourceWarehouse : $destinationWarehouse;
                $lockedBalances[$warehouseId] = $this->balanceForUpdate($product, $warehouse);
            }

            $sourceBalance = $lockedBalances[$sourceWarehouse->id];
            $destinationBalance = $lockedBalances[$destinationWarehouse->id];
            $transferBatchId = (string) Str::uuid();
            $unitCost = (string) $sourceBalance->average_cost;

            $out = $this->applyLockedMovement(
                $sourceBalance,
                StockMovementType::TransferOut,
                bcmul($normalizedQuantity, '-1', 3),
                $user,
                transferBatchId: $transferBatchId,
                unitCost: $unitCost,
                notes: $notes,
            );
            $in = $this->applyLockedMovement(
                $destinationBalance,
                StockMovementType::TransferIn,
                $normalizedQuantity,
                $user,
                transferBatchId: $transferBatchId,
                unitCost: $unitCost,
                notes: $notes,
                updateAverageCost: true,
            );

            return ['out' => $out, 'in' => $in];
        }, attempts: 5);
    }

    public function reserve(Product $product, Warehouse $warehouse, string $quantity): InventoryBalance
    {
        $normalizedQuantity = $this->normalizePositiveQuantity($quantity);

        return DB::transaction(function () use ($product, $warehouse, $normalizedQuantity): InventoryBalance {
            $balance = $this->balanceForUpdate($product, $warehouse);
            $newReservedQuantity = bcadd((string) $balance->quantity_reserved, $normalizedQuantity, 3);

            if (bccomp($newReservedQuantity, (string) $balance->quantity_on_hand, 3) === 1) {
                throw new DomainException('The requested reservation exceeds available inventory.');
            }

            return $this->updateReservedQuantity($balance, $newReservedQuantity);
        }, attempts: 5);
    }

    public function release(Product $product, Warehouse $warehouse, string $quantity): InventoryBalance
    {
        $normalizedQuantity = $this->normalizePositiveQuantity($quantity);

        return DB::transaction(function () use ($product, $warehouse, $normalizedQuantity): InventoryBalance {
            $balance = $this->balanceForUpdate($product, $warehouse);
            $newReservedQuantity = bcsub((string) $balance->quantity_reserved, $normalizedQuantity, 3);

            if (bccomp($newReservedQuantity, '0.000', 3) === -1) {
                throw new DomainException('Cannot release more inventory than is reserved.');
            }

            return $this->updateReservedQuantity($balance, $newReservedQuantity);
        }, attempts: 5);
    }

    public function applyInventoryCount(InventoryCount $inventoryCount, User $approvedBy): InventoryCount
    {
        return DB::transaction(function () use ($inventoryCount, $approvedBy): InventoryCount {
            $lockedCount = InventoryCount::query()
                ->with('warehouse')
                ->lockForUpdate()
                ->findOrFail($inventoryCount->id);

            if ($lockedCount->status !== InventoryCountStatus::Reviewed) {
                throw new DomainException('Only a reviewed inventory count can be applied.');
            }

            $items = $lockedCount->items()->with('product')->orderBy('product_id')->get();
            if ($items->isEmpty()) {
                throw new DomainException('An inventory count must contain at least one item.');
            }

            foreach ($items as $item) {
                if ($item->counted_quantity === null) {
                    throw new DomainException('Every inventory count item must have a counted quantity.');
                }

                $balance = $this->balanceForUpdate($item->product, $lockedCount->warehouse);
                if (bccomp((string) $balance->quantity_on_hand, (string) $item->expected_quantity, 3) !== 0) {
                    throw new DomainException('Inventory changed after the physical count and must be reviewed again.');
                }

                $difference = bcsub((string) $item->counted_quantity, (string) $item->expected_quantity, 3);
                $oldItemValues = $this->countItemValues($item);
                $item->update(['difference_quantity' => $difference]);
                $item->refresh();
                AuditLogService::updated(
                    InventoryCountItem::class,
                    $item->id,
                    $oldItemValues,
                    $this->countItemValues($item),
                );

                if (bccomp($difference, '0.000', 3) !== 0) {
                    $this->applyLockedMovement(
                        $balance,
                        StockMovementType::Adjustment,
                        $difference,
                        $approvedBy,
                        inventoryCount: $lockedCount,
                        notes: $lockedCount->notes,
                    );
                }
            }

            $oldValues = $this->countValues($lockedCount);
            $lockedCount->update([
                'status' => InventoryCountStatus::Applied,
                'approved_by_user_id' => $approvedBy->id,
                'applied_at' => now(),
            ]);
            $lockedCount->refresh();
            AuditLogService::updated(InventoryCount::class, $lockedCount->id, $oldValues, $this->countValues($lockedCount));

            return $lockedCount;
        }, attempts: 5);
    }

    private function changeStock(
        Product $product,
        Warehouse $warehouse,
        StockMovementType $movementType,
        string $quantityDelta,
        ?User $user = null,
        ?Order $order = null,
        ?PurchaseOrder $purchaseOrder = null,
        ?GoodsReceipt $goodsReceipt = null,
        ?InventoryCount $inventoryCount = null,
        ?string $transferBatchId = null,
        ?string $unitCost = null,
        ?string $notes = null,
        bool $updateAverageCost = false,
    ): StockMovement {
        $normalizedUnitCost = $unitCost === null ? null : $this->normalizeDecimal($unitCost, 4);

        return DB::transaction(function () use ($product, $warehouse, $movementType, $quantityDelta, $user, $order, $purchaseOrder, $goodsReceipt, $inventoryCount, $transferBatchId, $normalizedUnitCost, $notes, $updateAverageCost): StockMovement {
            $balance = $this->balanceForUpdate($product, $warehouse);

            return $this->applyLockedMovement(
                $balance,
                $movementType,
                $quantityDelta,
                $user,
                $order,
                $purchaseOrder,
                $goodsReceipt,
                $inventoryCount,
                $transferBatchId,
                $normalizedUnitCost,
                $notes,
                $updateAverageCost,
            );
        }, attempts: 5);
    }

    private function applyLockedMovement(
        InventoryBalance $balance,
        StockMovementType $movementType,
        string $quantityDelta,
        ?User $user = null,
        ?Order $order = null,
        ?PurchaseOrder $purchaseOrder = null,
        ?GoodsReceipt $goodsReceipt = null,
        ?InventoryCount $inventoryCount = null,
        ?string $transferBatchId = null,
        ?string $unitCost = null,
        ?string $notes = null,
        bool $updateAverageCost = false,
    ): StockMovement {
        $balanceBefore = (string) $balance->quantity_on_hand;
        $balanceAfter = bcadd($balanceBefore, $quantityDelta, 3);

        if (bccomp($balanceAfter, '0.000', 3) === -1) {
            throw new DomainException('Insufficient inventory for this operation.');
        }

        if (bccomp((string) $balance->quantity_reserved, $balanceAfter, 3) === 1) {
            throw new DomainException('This operation would consume reserved inventory.');
        }

        $oldValues = $this->balanceValues($balance);
        $newAverageCost = (string) $balance->average_cost;
        if ($updateAverageCost && $unitCost !== null && bccomp($quantityDelta, '0.000', 3) === 1) {
            $newAverageCost = $this->weightedAverageCost($balanceBefore, $newAverageCost, $quantityDelta, $unitCost, $balanceAfter);
        }

        $balance->update([
            'quantity_on_hand' => $balanceAfter,
            'average_cost' => $newAverageCost,
        ]);
        $balance->refresh();
        AuditLogService::updated(InventoryBalance::class, $balance->id, $oldValues, $this->balanceValues($balance));

        return $this->createMovement(
            $balance->product,
            $balance->warehouse,
            $movementType,
            $quantityDelta,
            $balanceBefore,
            $balanceAfter,
            $user,
            $order,
            $purchaseOrder,
            $goodsReceipt,
            $inventoryCount,
            $transferBatchId,
            $unitCost,
            $notes,
        );
    }

    private function createMovement(
        Product $product,
        Warehouse $warehouse,
        StockMovementType $movementType,
        string $quantityDelta,
        string $balanceBefore,
        string $balanceAfter,
        ?User $user = null,
        ?Order $order = null,
        ?PurchaseOrder $purchaseOrder = null,
        ?GoodsReceipt $goodsReceipt = null,
        ?InventoryCount $inventoryCount = null,
        ?string $transferBatchId = null,
        ?string $unitCost = null,
        ?string $notes = null,
    ): StockMovement {
        $movement = StockMovement::query()->create([
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'user_id' => $user?->id,
            'movement_type' => $movementType,
            'quantity_delta' => $quantityDelta,
            'balance_before' => $balanceBefore,
            'balance_after' => $balanceAfter,
            'unit_cost' => $unitCost,
            'order_id' => $order?->id,
            'purchase_order_id' => $purchaseOrder?->id,
            'goods_receipt_id' => $goodsReceipt?->id,
            'inventory_count_id' => $inventoryCount?->id,
            'transfer_batch_id' => $transferBatchId,
            'notes' => $notes,
            'occurred_at' => now(),
        ]);
        AuditLogService::created(StockMovement::class, $movement->id, $this->movementValues($movement));

        return $movement;
    }

    private function balanceForUpdate(Product $product, Warehouse $warehouse): InventoryBalance
    {
        if (! $product->type->tracksInventory()) {
            throw new DomainException('Inventory can only be changed for stock products.');
        }

        if (! $warehouse->is_active) {
            throw new DomainException('Inventory cannot be changed in an inactive warehouse.');
        }

        $balance = InventoryBalance::query()->createOrFirst([
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
        ]);

        if ($balance->wasRecentlyCreated) {
            AuditLogService::created(InventoryBalance::class, $balance->id, $this->balanceValues($balance));
        }

        return InventoryBalance::query()
            ->with(['product', 'warehouse'])
            ->whereBelongsTo($product)
            ->whereBelongsTo($warehouse)
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function updateReservedQuantity(InventoryBalance $balance, string $newReservedQuantity): InventoryBalance
    {
        $oldValues = $this->balanceValues($balance);
        $balance->update(['quantity_reserved' => $newReservedQuantity]);
        $balance->refresh();
        AuditLogService::updated(InventoryBalance::class, $balance->id, $oldValues, $this->balanceValues($balance));

        return $balance;
    }

    private function normalizePositiveQuantity(string $quantity): string
    {
        $normalizedQuantity = $this->normalizeDecimal($quantity);
        if (bccomp($normalizedQuantity, '0.000', 3) !== 1) {
            throw new InvalidArgumentException('Quantity must be greater than zero.');
        }

        return $normalizedQuantity;
    }

    private function normalizeDecimal(string $value, int $scale = 3): string
    {
        $value = trim($value);
        if (! preg_match('/^\d+(?:\.\d{1,'.$scale.'})?$/', $value)) {
            throw new InvalidArgumentException("Value must be a non-negative decimal with at most {$scale} decimal places.");
        }

        return bcadd($value, '0', $scale);
    }

    private function weightedAverageCost(
        string $oldQuantity,
        string $oldAverageCost,
        string $receivedQuantity,
        string $receivedUnitCost,
        string $newQuantity,
    ): string {
        if (bccomp($newQuantity, '0.000', 3) === 0) {
            return '0.0000';
        }

        $oldValue = bcmul($oldQuantity, $oldAverageCost, 7);
        $receivedValue = bcmul($receivedQuantity, $receivedUnitCost, 7);

        return bcdiv(bcadd($oldValue, $receivedValue, 7), $newQuantity, 4);
    }

    /** @return array<string, string> */
    private function balanceValues(InventoryBalance $balance): array
    {
        return [
            'product_id' => $balance->product_id,
            'warehouse_id' => $balance->warehouse_id,
            'quantity_on_hand' => (string) $balance->quantity_on_hand,
            'quantity_reserved' => (string) $balance->quantity_reserved,
            'reorder_level' => (string) $balance->reorder_level,
            'average_cost' => (string) $balance->average_cost,
        ];
    }

    /** @return array<string, mixed> */
    private function movementValues(StockMovement $movement): array
    {
        return [
            'product_id' => $movement->product_id,
            'warehouse_id' => $movement->warehouse_id,
            'user_id' => $movement->user_id,
            'movement_type' => $movement->movement_type->value,
            'quantity_delta' => (string) $movement->quantity_delta,
            'balance_before' => (string) $movement->balance_before,
            'balance_after' => (string) $movement->balance_after,
            'unit_cost' => $movement->unit_cost === null ? null : (string) $movement->unit_cost,
            'order_id' => $movement->order_id,
            'purchase_order_id' => $movement->purchase_order_id,
            'goods_receipt_id' => $movement->goods_receipt_id,
            'inventory_count_id' => $movement->inventory_count_id,
            'transfer_batch_id' => $movement->transfer_batch_id,
            'notes' => $movement->notes,
            'occurred_at' => $movement->occurred_at?->toISOString(),
        ];
    }

    /** @return array<string, string|null> */
    private function countItemValues(InventoryCountItem $item): array
    {
        return [
            'inventory_count_id' => $item->inventory_count_id,
            'product_id' => $item->product_id,
            'expected_quantity' => (string) $item->expected_quantity,
            'counted_quantity' => $item->counted_quantity === null ? null : (string) $item->counted_quantity,
            'difference_quantity' => $item->difference_quantity === null ? null : (string) $item->difference_quantity,
        ];
    }

    /** @return array<string, mixed> */
    private function countValues(InventoryCount $inventoryCount): array
    {
        return [
            'warehouse_id' => $inventoryCount->warehouse_id,
            'started_by_user_id' => $inventoryCount->started_by_user_id,
            'approved_by_user_id' => $inventoryCount->approved_by_user_id,
            'status' => $inventoryCount->status->value,
            'counted_at' => $inventoryCount->counted_at?->toISOString(),
            'applied_at' => $inventoryCount->applied_at?->toISOString(),
            'notes' => $inventoryCount->notes,
        ];
    }
}
