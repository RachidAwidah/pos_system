<?php

namespace App\Services;

use App\Enums\AccountEntryType;
use App\Enums\PaymentStatus;
use App\Models\GoodsReceipt;
use App\Models\PaymentMethod;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\SupplierLedgerEntry;
use App\Models\SupplierPayment;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class SupplierAccountService
{
    public function recordPurchase(Supplier $supplier, GoodsReceipt $goodsReceipt, ?User $user = null): SupplierLedgerEntry
    {
        return DB::transaction(function () use ($supplier, $goodsReceipt, $user): SupplierLedgerEntry {
            $lockedSupplier = Supplier::query()->lockForUpdate()->findOrFail($supplier->id);
            $goodsReceipt->loadMissing('purchaseOrder');
            if ($goodsReceipt->purchaseOrder->supplier_id !== $lockedSupplier->id) {
                throw new InvalidArgumentException('The goods receipt does not belong to this supplier.');
            }

            $existingEntry = SupplierLedgerEntry::query()
                ->whereBelongsTo($goodsReceipt)
                ->where('entry_type', AccountEntryType::Purchase->value)
                ->first();
            if ($existingEntry !== null) {
                return $existingEntry;
            }

            return $this->applyEntry(
                $lockedSupplier,
                AccountEntryType::Purchase,
                $this->normalizePositiveAmount((string) $goodsReceipt->total_amount),
                $user,
                purchaseOrder: $goodsReceipt->purchaseOrder,
                goodsReceipt: $goodsReceipt,
                description: 'Goods receipt '.$goodsReceipt->receipt_number,
            );
        }, attempts: 5);
    }

    public function pay(
        Supplier $supplier,
        User $user,
        PaymentMethod $paymentMethod,
        string $amount,
        ?PurchaseOrder $purchaseOrder = null,
        ?string $referenceNumber = null,
        ?string $notes = null,
    ): SupplierPayment {
        $normalizedAmount = $this->normalizePositiveAmount($amount);

        return DB::transaction(function () use ($supplier, $user, $paymentMethod, $normalizedAmount, $purchaseOrder, $referenceNumber, $notes): SupplierPayment {
            $lockedSupplier = Supplier::query()->lockForUpdate()->findOrFail($supplier->id);
            $lockedPaymentMethod = PaymentMethod::query()->lockForUpdate()->findOrFail($paymentMethod->id);
            if (! $lockedPaymentMethod->is_active) {
                throw new DomainException('The selected payment method is inactive.');
            }
            if ($lockedPaymentMethod->requires_reference && trim((string) $referenceNumber) === '') {
                throw new InvalidArgumentException('A reference number is required for this payment method.');
            }
            if ($purchaseOrder !== null && $purchaseOrder->supplier_id !== $lockedSupplier->id) {
                throw new InvalidArgumentException('The purchase order does not belong to this supplier.');
            }
            if (bccomp($normalizedAmount, (string) $lockedSupplier->balance, 2) === 1) {
                throw new DomainException('The payment exceeds the supplier balance.');
            }

            $payment = SupplierPayment::query()->create([
                'supplier_id' => $lockedSupplier->id,
                'user_id' => $user->id,
                'payment_method_id' => $lockedPaymentMethod->id,
                'purchase_order_id' => $purchaseOrder?->id,
                'status' => PaymentStatus::Completed,
                'amount' => $normalizedAmount,
                'reference_number' => $referenceNumber,
                'notes' => $notes,
                'paid_at' => now(),
            ]);
            AuditLogService::created(SupplierPayment::class, $payment->id, $this->paymentValues($payment));

            $this->applyEntry(
                $lockedSupplier,
                AccountEntryType::SupplierPayment,
                bcmul($normalizedAmount, '-1', 2),
                $user,
                purchaseOrder: $purchaseOrder,
                supplierPayment: $payment,
                description: $notes,
            );

            return $payment;
        }, attempts: 5);
    }

    private function applyEntry(
        Supplier $supplier,
        AccountEntryType $entryType,
        string $amountDelta,
        ?User $user = null,
        ?PurchaseOrder $purchaseOrder = null,
        ?GoodsReceipt $goodsReceipt = null,
        ?SupplierPayment $supplierPayment = null,
        ?string $description = null,
    ): SupplierLedgerEntry {
        $balanceBefore = (string) $supplier->balance;
        $balanceAfter = bcadd($balanceBefore, $amountDelta, 2);
        if (bccomp($balanceAfter, '0.00', 2) === -1) {
            throw new DomainException('A supplier balance cannot be negative.');
        }
        if (bccomp((string) $supplier->payable_limit, '0.00', 2) === 1
            && bccomp($balanceAfter, (string) $supplier->payable_limit, 2) === 1) {
            throw new DomainException('The supplier payable limit would be exceeded.');
        }

        $oldSupplierValues = ['balance' => $balanceBefore];
        $supplier->update(['balance' => $balanceAfter]);
        $supplier->refresh();
        AuditLogService::updated(Supplier::class, $supplier->id, $oldSupplierValues, ['balance' => (string) $supplier->balance]);

        $entry = SupplierLedgerEntry::query()->create([
            'supplier_id' => $supplier->id,
            'user_id' => $user?->id,
            'purchase_order_id' => $purchaseOrder?->id,
            'goods_receipt_id' => $goodsReceipt?->id,
            'supplier_payment_id' => $supplierPayment?->id,
            'entry_type' => $entryType,
            'amount_delta' => $amountDelta,
            'balance_before' => $balanceBefore,
            'balance_after' => $balanceAfter,
            'description' => $description,
            'occurred_at' => now(),
        ]);
        AuditLogService::created(SupplierLedgerEntry::class, $entry->id, $this->entryValues($entry));

        return $entry;
    }

    private function normalizePositiveAmount(string $amount): string
    {
        $amount = trim($amount);
        if (! preg_match('/^\d+(?:\.\d{1,2})?$/', $amount)) {
            throw new InvalidArgumentException('Amount must be a non-negative decimal with at most two decimal places.');
        }

        $normalizedAmount = bcadd($amount, '0', 2);
        if (bccomp($normalizedAmount, '0.00', 2) !== 1) {
            throw new InvalidArgumentException('Amount must be greater than zero.');
        }

        return $normalizedAmount;
    }

    /** @return array<string, mixed> */
    private function paymentValues(SupplierPayment $payment): array
    {
        return [
            'supplier_id' => $payment->supplier_id,
            'user_id' => $payment->user_id,
            'payment_method_id' => $payment->payment_method_id,
            'purchase_order_id' => $payment->purchase_order_id,
            'status' => $payment->status->value,
            'amount' => (string) $payment->amount,
            'reference_number' => $payment->reference_number,
            'paid_at' => $payment->paid_at?->toISOString(),
        ];
    }

    /** @return array<string, mixed> */
    private function entryValues(SupplierLedgerEntry $entry): array
    {
        return [
            'supplier_id' => $entry->supplier_id,
            'entry_type' => $entry->entry_type->value,
            'amount_delta' => (string) $entry->amount_delta,
            'balance_before' => (string) $entry->balance_before,
            'balance_after' => (string) $entry->balance_after,
            'purchase_order_id' => $entry->purchase_order_id,
            'goods_receipt_id' => $entry->goods_receipt_id,
            'supplier_payment_id' => $entry->supplier_payment_id,
        ];
    }
}
