<?php

namespace App\Services;

use App\Enums\LoyaltyTransactionType;
use App\Enums\OrderPaymentStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Enums\SalesReturnStatus;
use App\Enums\ShiftStatus;
use App\Models\LoyaltyTransaction;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\SalesReturn;
use App\Models\SalesReturnItem;
use App\Models\Shift;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class SalesReturnService
{
    public function __construct(
        public InventoryService $inventoryService,
        public CustomerAccountService $customerAccountService,
        public LoyaltyService $loyaltyService,
    ) {}

    /**
     * @param  array<int, array{order_item_id: string, quantity: string, restock?: bool, reason?: string|null}>  $items
     * @param  array<int, array{payment_method_id: string, amount: string, reference_number?: string|null}>  $refunds
     */
    public function complete(
        Order $order,
        Shift $shift,
        User $user,
        array $items,
        array $refunds,
        string $reason,
    ): SalesReturn {
        if ($items === []) {
            throw new InvalidArgumentException('A sales return must contain at least one item.');
        }
        $reason = trim($reason);
        if ($reason === '') {
            throw new InvalidArgumentException('A return reason is required.');
        }

        return DB::transaction(function () use ($order, $shift, $user, $items, $refunds, $reason): SalesReturn {
            $lockedShift = Shift::query()->with('register.warehouse')->lockForUpdate()->findOrFail($shift->id);
            if ($lockedShift->status !== ShiftStatus::Open || $lockedShift->opened_by_user_id !== $user->id) {
                throw new DomainException('Returns require the user\'s open cash session.');
            }

            $lockedOrder = Order::query()->with('customer')->lockForUpdate()->findOrFail($order->id);
            if (! in_array($lockedOrder->status, [OrderStatus::Completed, OrderStatus::PartiallyRefunded], true)) {
                throw new DomainException('Only completed or partially refunded orders can be returned.');
            }

            $normalizedItems = $this->normalizeItems($lockedOrder, $items);
            $subtotalAmount = '0.00';
            $taxAmount = '0.00';
            $returnAmount = '0.00';
            foreach ($normalizedItems as $item) {
                $subtotalAmount = bcadd($subtotalAmount, $item['subtotal_amount'], 2);
                $taxAmount = bcadd($taxAmount, $item['tax_amount'], 2);
                $returnAmount = bcadd($returnAmount, $item['refund_amount'], 2);
            }

            $newRefundedAmount = bcadd((string) $lockedOrder->refunded_amount, $returnAmount, 2);
            if (bccomp($newRefundedAmount, (string) $lockedOrder->final_amount, 2) === 1) {
                throw new DomainException('The return would exceed the order total.');
            }

            $creditAmount = $this->creditAmountForReturn($lockedOrder, $returnAmount);
            $cashRefundAmount = bcsub($returnAmount, $creditAmount, 2);
            $normalizedRefunds = $this->normalizeRefunds($lockedOrder, $refunds);
            $providedRefundAmount = '0.00';
            foreach ($normalizedRefunds as $refund) {
                $providedRefundAmount = bcadd($providedRefundAmount, $refund['amount'], 2);
            }
            if (bccomp($providedRefundAmount, $cashRefundAmount, 2) !== 0) {
                throw new DomainException('Refund payments must equal the paid portion of the return.');
            }

            $salesReturn = SalesReturn::query()->create([
                'return_number' => $this->nextReturnNumber(),
                'order_id' => $lockedOrder->id,
                'user_id' => $user->id,
                'shift_id' => $lockedShift->id,
                'warehouse_id' => $lockedShift->register->warehouse_id,
                'status' => SalesReturnStatus::Completed,
                'subtotal_amount' => $subtotalAmount,
                'tax_amount' => $taxAmount,
                'refund_amount' => $returnAmount,
                'reason' => $reason,
                'returned_at' => now(),
            ]);
            AuditLogService::created(SalesReturn::class, $salesReturn->id, $this->returnValues($salesReturn));

            foreach ($normalizedItems as $item) {
                $orderItem = $item['order_item'];
                $returnItem = $salesReturn->items()->create([
                    'order_item_id' => $orderItem->id,
                    'product_id' => $orderItem->product_id,
                    'quantity' => $item['quantity'],
                    'subtotal_amount' => $item['subtotal_amount'],
                    'tax_amount' => $item['tax_amount'],
                    'refund_amount' => $item['refund_amount'],
                    'restock' => $item['restock'],
                    'reason' => $item['reason'],
                ]);
                AuditLogService::created(SalesReturnItem::class, $returnItem->id, $this->returnItemValues($returnItem));

                if ($item['restock'] && $orderItem->product->type->tracksInventory()) {
                    $this->inventoryService->customerReturn(
                        $orderItem->product,
                        $lockedShift->register->warehouse,
                        $item['quantity'],
                        $user,
                        $lockedOrder,
                        'Sales return '.$salesReturn->return_number,
                    );
                }
            }

            foreach ($normalizedRefunds as $values) {
                $payment = $lockedOrder->payments()->create([
                    'sales_return_id' => $salesReturn->id,
                    'shift_id' => $lockedShift->id,
                    'user_id' => $user->id,
                    'payment_method_id' => $values['payment_method']->id,
                    'type' => PaymentType::Refund,
                    'status' => PaymentStatus::Completed,
                    'amount' => $values['amount'],
                    'amount_tendered' => null,
                    'change_amount' => 0,
                    'reference_number' => $values['reference_number'],
                    'paid_at' => now(),
                ]);
                AuditLogService::created(Payment::class, $payment->id, $this->paymentValues($payment));
            }

            $oldOrderValues = $this->orderValues($lockedOrder);
            $fullyRefunded = bccomp($newRefundedAmount, (string) $lockedOrder->final_amount, 2) === 0;
            $lockedOrder->update([
                'refunded_amount' => $newRefundedAmount,
                'status' => $fullyRefunded ? OrderStatus::Refunded : OrderStatus::PartiallyRefunded,
                'payment_status' => $fullyRefunded ? OrderPaymentStatus::Refunded : OrderPaymentStatus::PartiallyRefunded,
            ]);
            $lockedOrder->refresh();
            AuditLogService::updated(Order::class, $lockedOrder->id, $oldOrderValues, $this->orderValues($lockedOrder));

            if ($lockedOrder->customer !== null) {
                $this->customerAccountService->recordReturn(
                    $lockedOrder->customer,
                    $salesReturn,
                    $creditAmount,
                    $user,
                );
                $pointsToReverse = $this->pointsToReverse($lockedOrder, $returnAmount, $fullyRefunded);
                $this->loyaltyService->reverseForReturn(
                    $lockedOrder->customer,
                    $salesReturn,
                    $user,
                    $pointsToReverse,
                );
            }

            return $salesReturn->load(['items.product', 'payments.paymentMethod', 'order', 'warehouse', 'shift']);
        }, attempts: 5);
    }

    /**
     * @param  array<int, array{order_item_id: string, quantity: string, restock?: bool, reason?: string|null}>  $items
     * @return array<int, array{order_item: OrderItem, quantity: string, subtotal_amount: string, tax_amount: string, refund_amount: string, restock: bool, reason: string|null}>
     */
    private function normalizeItems(Order $order, array $items): array
    {
        $itemsById = [];
        foreach ($items as $item) {
            $itemId = $item['order_item_id'] ?? '';
            if ($itemId === '' || isset($itemsById[$itemId])) {
                throw new InvalidArgumentException('Every return line must reference a unique order item.');
            }
            $itemsById[$itemId] = $item;
        }

        ksort($itemsById);
        $orderItems = OrderItem::query()
            ->with('product.unit')
            ->whereBelongsTo($order)
            ->whereIn('id', array_keys($itemsById))
            ->lockForUpdate()
            ->get()
            ->keyBy('id');
        if ($orderItems->count() !== count($itemsById)) {
            throw new InvalidArgumentException('One or more return items do not belong to this order.');
        }

        $normalizedItems = [];
        foreach ($itemsById as $itemId => $item) {
            $orderItem = $orderItems->get($itemId);
            $quantity = $this->normalizeQuantity((string) ($item['quantity'] ?? ''), $orderItem->product->unit->decimal_places);
            $returnedQuantity = (string) $orderItem->returnItems()
                ->whereHas('salesReturn', fn ($query) => $query->where('status', SalesReturnStatus::Completed->value))
                ->sum('quantity');
            $remainingQuantity = bcsub((string) $orderItem->quantity, $returnedQuantity, 3);
            if (bccomp($quantity, $remainingQuantity, 3) === 1) {
                throw new DomainException('A returned quantity cannot exceed the remaining sold quantity.');
            }

            $previousRefund = (string) $orderItem->returnItems()
                ->whereHas('salesReturn', fn ($query) => $query->where('status', SalesReturnStatus::Completed->value))
                ->sum('refund_amount');
            $refundAmount = bccomp($quantity, $remainingQuantity, 3) === 0
                ? bcsub((string) $orderItem->total_amount, $previousRefund, 2)
                : $this->roundMoney(bcdiv(bcmul((string) $orderItem->total_amount, $quantity, 6), (string) $orderItem->quantity, 6));
            $taxAmount = $this->roundMoney(bcdiv(bcmul((string) $orderItem->tax_amount, $quantity, 6), (string) $orderItem->quantity, 6));
            if (bccomp($taxAmount, $refundAmount, 2) === 1) {
                $taxAmount = $refundAmount;
            }

            $normalizedItems[] = [
                'order_item' => $orderItem,
                'quantity' => $quantity,
                'subtotal_amount' => bcsub($refundAmount, $taxAmount, 2),
                'tax_amount' => $taxAmount,
                'refund_amount' => $refundAmount,
                'restock' => (bool) ($item['restock'] ?? true),
                'reason' => $this->nullableString($item['reason'] ?? null),
            ];
        }

        return $normalizedItems;
    }

    /**
     * @param  array<int, array{payment_method_id: string, amount: string, reference_number?: string|null}>  $refunds
     * @return array<int, array{payment_method: PaymentMethod, amount: string, reference_number: string|null}>
     */
    private function normalizeRefunds(Order $order, array $refunds): array
    {
        if ($refunds === []) {
            return [];
        }

        $methodIds = array_values(array_unique(array_map(fn (array $refund): string => $refund['payment_method_id'] ?? '', $refunds)));
        sort($methodIds);
        if (in_array('', $methodIds, true)) {
            throw new InvalidArgumentException('Every refund must reference a payment method.');
        }
        $methods = PaymentMethod::query()->whereIn('id', $methodIds)->lockForUpdate()->get()->keyBy('id');
        if ($methods->count() !== count($methodIds)) {
            throw new InvalidArgumentException('One or more refund methods do not exist.');
        }

        $refundsByMethod = [];
        $normalizedRefunds = [];
        foreach ($refunds as $refund) {
            $method = $methods->get($refund['payment_method_id']);
            if (! $method->is_active) {
                throw new DomainException('An inactive payment method cannot be used for a refund.');
            }
            $referenceNumber = $this->nullableString($refund['reference_number'] ?? null);
            if ($method->requires_reference && $referenceNumber === null) {
                throw new InvalidArgumentException('A reference number is required for this refund method.');
            }
            $amount = $this->normalizePositiveMoney((string) ($refund['amount'] ?? ''));
            $refundsByMethod[$method->id] = bcadd($refundsByMethod[$method->id] ?? '0.00', $amount, 2);
            $normalizedRefunds[] = ['payment_method' => $method, 'amount' => $amount, 'reference_number' => $referenceNumber];
        }

        foreach ($refundsByMethod as $methodId => $amount) {
            $paid = bcadd(
                (string) $order->payments()->where('type', PaymentType::Payment->value)->where('status', PaymentStatus::Completed->value)->where('payment_method_id', $methodId)->sum('amount'),
                (string) $order->customerPayments()->where('status', PaymentStatus::Completed->value)->where('payment_method_id', $methodId)->sum('amount'),
                2,
            );
            $alreadyRefunded = (string) $order->payments()->where('type', PaymentType::Refund->value)->where('status', PaymentStatus::Completed->value)->where('payment_method_id', $methodId)->sum('amount');
            if (bccomp($amount, bcsub($paid, $alreadyRefunded, 2), 2) === 1) {
                throw new DomainException('A refund cannot exceed the amount paid with the same method.');
            }
        }

        return $normalizedRefunds;
    }

    private function creditAmountForReturn(Order $order, string $returnAmount): string
    {
        if ($order->customer === null || bccomp((string) $order->due_amount, '0.00', 2) !== 1) {
            return '0.00';
        }

        $previousCredits = (string) $order->customer->ledgerEntries()
            ->where('entry_type', 'sales_return')
            ->where('order_id', $order->id)
            ->sum(DB::raw('ABS(amount_delta)'));
        $availableCredit = bcsub((string) $order->due_amount, $previousCredits, 2);
        if (bccomp($availableCredit, '0.00', 2) !== 1) {
            return '0.00';
        }

        return bccomp($returnAmount, $availableCredit, 2) === 1 ? $availableCredit : $returnAmount;
    }

    private function pointsToReverse(Order $order, string $returnAmount, bool $fullyRefunded): int
    {
        $earnedPoints = (int) LoyaltyTransaction::query()
            ->whereBelongsTo($order)
            ->where('transaction_type', LoyaltyTransactionType::Earned->value)
            ->sum('points_delta');
        $reversedPoints = abs((int) LoyaltyTransaction::query()
            ->whereBelongsTo($order)
            ->where('transaction_type', LoyaltyTransactionType::Reversed->value)
            ->sum('points_delta'));
        $remainingPoints = max(0, $earnedPoints - $reversedPoints);
        if ($fullyRefunded) {
            return $remainingPoints;
        }

        return min($remainingPoints, (int) floor($earnedPoints * ((float) $returnAmount / (float) $order->final_amount)));
    }

    private function normalizeQuantity(string $quantity, int $decimalPlaces): string
    {
        $quantity = trim($quantity);
        $pattern = $decimalPlaces === 0 ? '/^\d+$/' : '/^\d+(?:\.\d{1,'.$decimalPlaces.'})?$/';
        if (! preg_match($pattern, $quantity)) {
            throw new InvalidArgumentException("Quantity must use at most {$decimalPlaces} decimal places for this unit.");
        }
        $quantity = bcadd($quantity, '0', 3);
        if (bccomp($quantity, '0.000', 3) !== 1) {
            throw new InvalidArgumentException('Quantity must be greater than zero.');
        }

        return $quantity;
    }

    private function normalizePositiveMoney(string $amount): string
    {
        $amount = trim($amount);
        if (! preg_match('/^\d+(?:\.\d{1,2})?$/', $amount)) {
            throw new InvalidArgumentException('Amount must be a non-negative decimal with at most two decimal places.');
        }
        $amount = bcadd($amount, '0', 2);
        if (bccomp($amount, '0.00', 2) !== 1) {
            throw new InvalidArgumentException('Amount must be greater than zero.');
        }

        return $amount;
    }

    private function roundMoney(string $value): string
    {
        return bcadd(bcadd($value, '0.005', 3), '0', 2);
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : $value;
    }

    private function nextReturnNumber(): string
    {
        return 'RET-'.now()->format('Ymd').'-'.Str::upper(Str::random(10));
    }

    /** @return array<string, mixed> */
    private function returnValues(SalesReturn $return): array
    {
        return [
            'return_number' => $return->return_number,
            'order_id' => $return->order_id,
            'user_id' => $return->user_id,
            'shift_id' => $return->shift_id,
            'warehouse_id' => $return->warehouse_id,
            'status' => $return->status->value,
            'subtotal_amount' => (string) $return->subtotal_amount,
            'tax_amount' => (string) $return->tax_amount,
            'refund_amount' => (string) $return->refund_amount,
            'reason' => $return->reason,
        ];
    }

    /** @return array<string, mixed> */
    private function returnItemValues(SalesReturnItem $item): array
    {
        return [
            'sales_return_id' => $item->sales_return_id,
            'order_item_id' => $item->order_item_id,
            'product_id' => $item->product_id,
            'quantity' => (string) $item->quantity,
            'refund_amount' => (string) $item->refund_amount,
            'restock' => $item->restock,
            'reason' => $item->reason,
        ];
    }

    /** @return array<string, mixed> */
    private function paymentValues(Payment $payment): array
    {
        return [
            'order_id' => $payment->order_id,
            'sales_return_id' => $payment->sales_return_id,
            'shift_id' => $payment->shift_id,
            'payment_method_id' => $payment->payment_method_id,
            'type' => $payment->type->value,
            'status' => $payment->status->value,
            'amount' => (string) $payment->amount,
            'reference_number' => $payment->reference_number,
        ];
    }

    /** @return array<string, mixed> */
    private function orderValues(Order $order): array
    {
        return [
            'status' => $order->status->value,
            'payment_status' => $order->payment_status->value,
            'refunded_amount' => (string) $order->refunded_amount,
        ];
    }
}
