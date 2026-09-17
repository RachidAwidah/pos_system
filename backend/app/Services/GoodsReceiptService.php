<?php

namespace App\Services;

use App\Enums\GoodsReceiptStatus;
use App\Enums\PurchaseOrderStatus;
use App\Exceptions\BusinessInputException as InvalidArgumentException;
use App\Exceptions\BusinessRuleException as DomainException;
use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptItem;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class GoodsReceiptService
{
    public function __construct(
        public InventoryService $inventoryService,
        public SupplierAccountService $supplierAccountService,
    ) {}

    /**
     * @param  array<int, array{purchase_order_item_id: string, quantity: string, batch_number?: string|null, expires_at?: string|null}>  $items
     */
    public function receive(
        PurchaseOrder $purchaseOrder,
        User $receivedBy,
        array $items,
        ?string $supplierReference = null,
        ?string $notes = null,
    ): GoodsReceipt {
        if ($items === []) {
            throw new InvalidArgumentException('A goods receipt must contain at least one item.');
        }

        return DB::transaction(function () use ($purchaseOrder, $receivedBy, $items, $supplierReference, $notes): GoodsReceipt {
            $lockedPurchaseOrder = PurchaseOrder::query()
                ->with('warehouse')
                ->lockForUpdate()
                ->findOrFail($purchaseOrder->id);

            if (! in_array($lockedPurchaseOrder->status, [PurchaseOrderStatus::Sent, PurchaseOrderStatus::PartiallyReceived], true)) {
                throw new DomainException('Only sent or partially received purchase orders can receive stock.');
            }

            if (! $lockedPurchaseOrder->warehouse->is_active) {
                throw new DomainException('Goods cannot be received into an inactive warehouse.');
            }

            $normalizedItems = $this->normalizeItems($lockedPurchaseOrder, $items);
            $subtotalAmount = '0.00';
            $discountAmount = '0.00';
            $taxAmount = '0.00';
            foreach ($normalizedItems as $item) {
                $subtotalAmount = bcadd($subtotalAmount, $item['subtotal_amount'], 2);
                $discountAmount = bcadd($discountAmount, $item['discount_amount'], 2);
                $taxAmount = bcadd($taxAmount, $item['tax_amount'], 2);
            }

            $goodsReceipt = GoodsReceipt::query()->create([
                'receipt_number' => $this->nextNumber(),
                'purchase_order_id' => $lockedPurchaseOrder->id,
                'warehouse_id' => $lockedPurchaseOrder->warehouse_id,
                'received_by_user_id' => $receivedBy->id,
                'status' => GoodsReceiptStatus::Completed,
                'subtotal_amount' => $subtotalAmount,
                'discount_amount' => $discountAmount,
                'tax_amount' => $taxAmount,
                'total_amount' => bcadd(bcsub($subtotalAmount, $discountAmount, 2), $taxAmount, 2),
                'supplier_reference' => $supplierReference,
                'notes' => $notes,
                'received_at' => now(),
            ]);
            AuditLogService::created(GoodsReceipt::class, $goodsReceipt->id, $this->receiptValues($goodsReceipt));

            foreach ($normalizedItems as $item) {
                $purchaseOrderItem = $item['purchase_order_item'];
                $oldValues = $this->purchaseOrderItemValues($purchaseOrderItem);
                $goodsReceiptItem = $goodsReceipt->items()->create([
                    'purchase_order_item_id' => $purchaseOrderItem->id,
                    'product_id' => $purchaseOrderItem->product_id,
                    'quantity' => $item['quantity'],
                    'unit_cost' => $purchaseOrderItem->unit_cost,
                    'subtotal_amount' => $item['subtotal_amount'],
                    'discount_amount' => $item['discount_amount'],
                    'tax_amount' => $item['tax_amount'],
                    'total_amount' => $item['total_amount'],
                    'batch_number' => $item['batch_number'],
                    'expires_at' => $item['expires_at'],
                ]);
                AuditLogService::created(GoodsReceiptItem::class, $goodsReceiptItem->id, $this->receiptItemValues($goodsReceiptItem));

                $purchaseOrderItem->update([
                    'received_quantity' => bcadd((string) $purchaseOrderItem->received_quantity, $item['quantity'], 3),
                ]);
                $purchaseOrderItem->refresh();
                AuditLogService::updated(
                    PurchaseOrderItem::class,
                    $purchaseOrderItem->id,
                    $oldValues,
                    $this->purchaseOrderItemValues($purchaseOrderItem),
                );

                $this->inventoryService->receive(
                    $purchaseOrderItem->product,
                    $lockedPurchaseOrder->warehouse,
                    $item['quantity'],
                    $receivedBy,
                    $lockedPurchaseOrder,
                    $goodsReceipt,
                    (string) $purchaseOrderItem->unit_cost,
                    'Goods receipt '.$goodsReceipt->receipt_number,
                );
            }

            $oldPurchaseOrderValues = $this->purchaseOrderValues($lockedPurchaseOrder);
            $hasOutstandingItems = $lockedPurchaseOrder->items()
                ->whereColumn('received_quantity', '<', 'ordered_quantity')
                ->exists();
            $lockedPurchaseOrder->update([
                'status' => $hasOutstandingItems
                    ? PurchaseOrderStatus::PartiallyReceived
                    : PurchaseOrderStatus::Received,
            ]);
            $lockedPurchaseOrder->refresh();
            AuditLogService::updated(
                PurchaseOrder::class,
                $lockedPurchaseOrder->id,
                $oldPurchaseOrderValues,
                $this->purchaseOrderValues($lockedPurchaseOrder),
            );
            $this->supplierAccountService->recordPurchase(
                $lockedPurchaseOrder->supplier,
                $goodsReceipt,
                $receivedBy,
            );

            return $goodsReceipt->load(['items', 'purchaseOrder', 'warehouse', 'receivedBy']);
        }, attempts: 5);
    }

    /**
     * @param  array<int, array{purchase_order_item_id: string, quantity: string, batch_number?: string|null, expires_at?: string|null}>  $items
     * @return array<int, array{purchase_order_item: PurchaseOrderItem, quantity: string, subtotal_amount: string, discount_amount: string, tax_amount: string, total_amount: string, batch_number: string|null, expires_at: string|null}>
     */
    private function normalizeItems(PurchaseOrder $purchaseOrder, array $items): array
    {
        $itemsById = [];
        foreach ($items as $item) {
            $itemId = $item['purchase_order_item_id'] ?? '';
            if ($itemId === '' || isset($itemsById[$itemId])) {
                throw new InvalidArgumentException('Every receipt line must reference a unique purchase order item.');
            }

            $itemsById[$itemId] = $item;
        }

        ksort($itemsById);
        $purchaseOrderItems = PurchaseOrderItem::query()
            ->with('product')
            ->whereBelongsTo($purchaseOrder)
            ->whereIn('id', array_keys($itemsById))
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        if ($purchaseOrderItems->count() !== count($itemsById)) {
            throw new InvalidArgumentException('One or more receipt lines do not belong to this purchase order.');
        }

        $normalizedItems = [];
        foreach ($itemsById as $itemId => $item) {
            $purchaseOrderItem = $purchaseOrderItems->get($itemId);
            $quantity = $this->normalizePositiveQuantity((string) ($item['quantity'] ?? ''));
            $remainingQuantity = bcsub(
                (string) $purchaseOrderItem->ordered_quantity,
                (string) $purchaseOrderItem->received_quantity,
                3,
            );
            if (bccomp($quantity, $remainingQuantity, 3) === 1) {
                throw new DomainException('A receipt quantity cannot exceed the outstanding ordered quantity.');
            }

            if (bccomp($quantity, $remainingQuantity, 3) === 0) {
                $receiptAmounts = $this->remainingReceiptAmounts($purchaseOrderItem);
                $subtotalAmount = $receiptAmounts['subtotal_amount'];
                $discountAmount = $receiptAmounts['discount_amount'];
                $taxAmount = $receiptAmounts['tax_amount'];
                $totalAmount = $receiptAmounts['total_amount'];
            } else {
                $subtotalAmount = $this->roundMoney(bcmul($quantity, (string) $purchaseOrderItem->unit_cost, 6));
                $discountAmount = $this->roundMoney(bcdiv(
                    bcmul((string) $purchaseOrderItem->discount_amount, $quantity, 6),
                    (string) $purchaseOrderItem->ordered_quantity,
                    6,
                ));
                $taxableAmount = bcsub($subtotalAmount, $discountAmount, 2);
                $taxAmount = $this->roundMoney(bcdiv(
                    bcmul($taxableAmount, (string) $purchaseOrderItem->tax_rate, 6),
                    '100',
                    6,
                ));
                $totalAmount = bcadd($taxableAmount, $taxAmount, 2);
            }

            $normalizedItems[] = [
                'purchase_order_item' => $purchaseOrderItem,
                'quantity' => $quantity,
                'subtotal_amount' => $subtotalAmount,
                'discount_amount' => $discountAmount,
                'tax_amount' => $taxAmount,
                'total_amount' => $totalAmount,
                'batch_number' => $this->nullableTrimmedString($item['batch_number'] ?? null),
                'expires_at' => $this->nullableTrimmedString($item['expires_at'] ?? null),
            ];
        }

        return $normalizedItems;
    }

    /** @return array{subtotal_amount: string, discount_amount: string, tax_amount: string, total_amount: string} */
    private function remainingReceiptAmounts(PurchaseOrderItem $purchaseOrderItem): array
    {
        $received = GoodsReceiptItem::query()
            ->whereBelongsTo($purchaseOrderItem)
            ->selectRaw('COALESCE(SUM(subtotal_amount), 0) AS subtotal_amount')
            ->selectRaw('COALESCE(SUM(discount_amount), 0) AS discount_amount')
            ->selectRaw('COALESCE(SUM(tax_amount), 0) AS tax_amount')
            ->selectRaw('COALESCE(SUM(total_amount), 0) AS total_amount')
            ->firstOrFail();

        return [
            'subtotal_amount' => bcsub((string) $purchaseOrderItem->subtotal_amount, (string) $received->subtotal_amount, 2),
            'discount_amount' => bcsub((string) $purchaseOrderItem->discount_amount, (string) $received->discount_amount, 2),
            'tax_amount' => bcsub((string) $purchaseOrderItem->tax_amount, (string) $received->tax_amount, 2),
            'total_amount' => bcsub((string) $purchaseOrderItem->total_amount, (string) $received->total_amount, 2),
        ];
    }

    private function normalizePositiveQuantity(string $quantity): string
    {
        $quantity = trim($quantity);
        if (! preg_match('/^\d+(?:\.\d{1,3})?$/', $quantity)) {
            throw new InvalidArgumentException('Quantity must be a non-negative decimal with at most three decimal places.');
        }

        $normalizedQuantity = bcadd($quantity, '0', 3);
        if (bccomp($normalizedQuantity, '0.000', 3) !== 1) {
            throw new InvalidArgumentException('Quantity must be greater than zero.');
        }

        return $normalizedQuantity;
    }

    private function nullableTrimmedString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function roundMoney(string $value): string
    {
        return bcadd(bcadd($value, '0.005', 3), '0', 2);
    }

    private function nextNumber(): string
    {
        return 'GRN-'.now()->format('Ymd').'-'.Str::upper(Str::random(10));
    }

    /** @return array<string, mixed> */
    private function receiptValues(GoodsReceipt $goodsReceipt): array
    {
        return [
            'receipt_number' => $goodsReceipt->receipt_number,
            'purchase_order_id' => $goodsReceipt->purchase_order_id,
            'warehouse_id' => $goodsReceipt->warehouse_id,
            'received_by_user_id' => $goodsReceipt->received_by_user_id,
            'status' => $goodsReceipt->status->value,
            'subtotal_amount' => (string) $goodsReceipt->subtotal_amount,
            'discount_amount' => (string) $goodsReceipt->discount_amount,
            'tax_amount' => (string) $goodsReceipt->tax_amount,
            'total_amount' => (string) $goodsReceipt->total_amount,
            'supplier_reference' => $goodsReceipt->supplier_reference,
            'notes' => $goodsReceipt->notes,
            'received_at' => $goodsReceipt->received_at?->toISOString(),
        ];
    }

    /** @return array<string, mixed> */
    private function receiptItemValues(GoodsReceiptItem $item): array
    {
        return [
            'goods_receipt_id' => $item->goods_receipt_id,
            'purchase_order_item_id' => $item->purchase_order_item_id,
            'product_id' => $item->product_id,
            'quantity' => (string) $item->quantity,
            'unit_cost' => (string) $item->unit_cost,
            'subtotal_amount' => (string) $item->subtotal_amount,
            'discount_amount' => (string) $item->discount_amount,
            'tax_amount' => (string) $item->tax_amount,
            'total_amount' => (string) $item->total_amount,
            'batch_number' => $item->batch_number,
            'expires_at' => $item->expires_at?->toDateString(),
        ];
    }

    /** @return array<string, string> */
    private function purchaseOrderItemValues(PurchaseOrderItem $item): array
    {
        return [
            'purchase_order_id' => $item->purchase_order_id,
            'product_id' => $item->product_id,
            'ordered_quantity' => (string) $item->ordered_quantity,
            'received_quantity' => (string) $item->received_quantity,
        ];
    }

    /** @return array<string, string> */
    private function purchaseOrderValues(PurchaseOrder $purchaseOrder): array
    {
        return [
            'status' => $purchaseOrder->status->value,
            'warehouse_id' => $purchaseOrder->warehouse_id,
            'supplier_id' => $purchaseOrder->supplier_id,
            'total_amount' => (string) $purchaseOrder->total_amount,
        ];
    }
}
