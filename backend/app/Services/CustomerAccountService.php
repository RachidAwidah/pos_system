<?php

namespace App\Services;

use App\Enums\AccountEntryType;
use App\Enums\OrderPaymentStatus;
use App\Enums\PaymentStatus;
use App\Exceptions\BusinessInputException as InvalidArgumentException;
use App\Exceptions\BusinessRuleException as DomainException;
use App\Models\Customer;
use App\Models\CustomerLedgerEntry;
use App\Models\CustomerPayment;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\SalesReturn;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class CustomerAccountService
{
    /** @return array{entries: LengthAwarePaginator, summary: array<string, string>} */
    public function statement(Customer $customer, int $perPage = 25): array
    {
        return DB::transaction(function () use ($customer, $perPage): array {
            $totals = $customer->orders()->selectRaw(
                'COALESCE(SUM(final_amount), 0) AS invoices_total, COALESCE(SUM(paid_amount), 0) AS paid_total, COALESCE(SUM(due_amount), 0) AS due_total'
            )->first();

            return [
                'entries' => $customer->ledgerEntries()->with('order:id,invoice_number')
                    ->orderBy('occurred_at')->orderBy('id')->paginate($perPage),
                'summary' => [
                    'invoices_total' => bcadd((string) $totals->invoices_total, '0', 2),
                    'paid_total' => bcadd((string) $totals->paid_total, '0', 2),
                    'due_total' => bcadd((string) $totals->due_total, '0', 2),
                    'balance' => (string) $customer->fresh()->balance,
                ],
            ];
        });
    }

    public function recordSale(Customer $customer, Order $order, ?User $user = null): ?CustomerLedgerEntry
    {
        $amount = (string) $order->due_amount;
        if (bccomp($amount, '0.00', 2) === 0) {
            return null;
        }

        return DB::transaction(function () use ($customer, $order, $user, $amount): CustomerLedgerEntry {
            $lockedCustomer = Customer::query()->lockForUpdate()->findOrFail($customer->id);
            if ($order->customer_id !== $lockedCustomer->id) {
                throw new InvalidArgumentException('The order does not belong to this customer.');
            }

            $existingEntry = CustomerLedgerEntry::query()
                ->whereBelongsTo($order)
                ->where('entry_type', AccountEntryType::Sale->value)
                ->first();
            if ($existingEntry !== null) {
                return $existingEntry;
            }

            return $this->applyEntry(
                $lockedCustomer,
                AccountEntryType::Sale,
                $this->normalizePositiveAmount($amount),
                $user,
                order: $order,
                description: 'Credit sale '.$order->invoice_number,
            );
        }, attempts: 5);
    }

    public function collectPayment(
        Customer $customer,
        Order $order,
        User $user,
        PaymentMethod $paymentMethod,
        string $amount,
        ?string $referenceNumber = null,
        ?string $notes = null,
    ): CustomerPayment {
        $normalizedAmount = $this->normalizePositiveAmount($amount);

        return DB::transaction(function () use ($customer, $order, $user, $paymentMethod, $normalizedAmount, $referenceNumber, $notes): CustomerPayment {
            $lockedCustomer = Customer::query()->lockForUpdate()->findOrFail($customer->id);
            $lockedOrder = Order::query()->lockForUpdate()->findOrFail($order->id);
            $lockedPaymentMethod = PaymentMethod::query()->lockForUpdate()->findOrFail($paymentMethod->id);

            if ($lockedOrder->customer_id !== $lockedCustomer->id) {
                throw new InvalidArgumentException('The order does not belong to this customer.');
            }
            if (! $lockedPaymentMethod->is_active) {
                throw new DomainException('The selected payment method is inactive.');
            }
            if ($lockedPaymentMethod->requires_reference && trim((string) $referenceNumber) === '') {
                throw new InvalidArgumentException('A reference number is required for this payment method.');
            }
            if (bccomp($normalizedAmount, (string) $lockedOrder->due_amount, 2) === 1) {
                throw new DomainException('The payment exceeds the amount due on the order.');
            }

            $payment = CustomerPayment::query()->create([
                'customer_id' => $lockedCustomer->id,
                'user_id' => $user->id,
                'payment_method_id' => $lockedPaymentMethod->id,
                'order_id' => $lockedOrder->id,
                'status' => PaymentStatus::Completed,
                'amount' => $normalizedAmount,
                'reference_number' => $referenceNumber,
                'notes' => $notes,
                'paid_at' => now(),
            ]);
            AuditLogService::created(CustomerPayment::class, $payment->id, $this->paymentValues($payment));

            $oldOrderValues = $this->orderPaymentValues($lockedOrder);
            $paidAmount = bcadd((string) $lockedOrder->paid_amount, $normalizedAmount, 2);
            $netOrderAmount = bcsub((string) $lockedOrder->final_amount, (string) $lockedOrder->refunded_amount, 2);
            $dueAmount = bcsub($netOrderAmount, $paidAmount, 2);
            $lockedOrder->update([
                'paid_amount' => $paidAmount,
                'due_amount' => $dueAmount,
                'payment_status' => $this->paymentStatus($lockedOrder, $dueAmount),
            ]);
            $lockedOrder->refresh();
            AuditLogService::updated(Order::class, $lockedOrder->id, $oldOrderValues, $this->orderPaymentValues($lockedOrder));

            $this->applyEntry(
                $lockedCustomer,
                AccountEntryType::CustomerPayment,
                bcmul($normalizedAmount, '-1', 2),
                $user,
                order: $lockedOrder,
                customerPayment: $payment,
                description: $notes,
            );

            return $payment;
        }, attempts: 5);
    }

    public function recordReturn(
        Customer $customer,
        SalesReturn $salesReturn,
        string $creditAmount,
        ?User $user = null,
    ): ?CustomerLedgerEntry {
        $normalizedAmount = $this->normalizeNonNegativeAmount($creditAmount);
        if (bccomp($normalizedAmount, '0.00', 2) === 0) {
            return null;
        }

        return DB::transaction(function () use ($customer, $salesReturn, $normalizedAmount, $user): CustomerLedgerEntry {
            $lockedCustomer = Customer::query()->lockForUpdate()->findOrFail($customer->id);
            $salesReturn->loadMissing('order');
            if ($salesReturn->order->customer_id !== $lockedCustomer->id) {
                throw new InvalidArgumentException('The sales return does not belong to this customer.');
            }
            if (bccomp($normalizedAmount, (string) $lockedCustomer->balance, 2) === 1) {
                throw new DomainException('The return credit exceeds the customer balance.');
            }

            $existingEntry = CustomerLedgerEntry::query()
                ->whereBelongsTo($salesReturn)
                ->where('entry_type', AccountEntryType::SalesReturn->value)
                ->first();
            if ($existingEntry !== null) {
                return $existingEntry;
            }

            return $this->applyEntry(
                $lockedCustomer,
                AccountEntryType::SalesReturn,
                bcmul($normalizedAmount, '-1', 2),
                $user,
                order: $salesReturn->order,
                salesReturn: $salesReturn,
                description: 'Credit from return '.$salesReturn->return_number,
            );
        }, attempts: 5);
    }

    private function applyEntry(
        Customer $customer,
        AccountEntryType $entryType,
        string $amountDelta,
        ?User $user = null,
        ?Order $order = null,
        ?SalesReturn $salesReturn = null,
        ?CustomerPayment $customerPayment = null,
        ?string $description = null,
    ): CustomerLedgerEntry {
        $balanceBefore = (string) $customer->balance;
        $balanceAfter = bcadd($balanceBefore, $amountDelta, 2);
        if (bccomp($balanceAfter, '0.00', 2) === -1) {
            throw new DomainException('A customer balance cannot be negative.');
        }
        if (bccomp($amountDelta, '0.00', 2) === 1
            && bccomp($balanceAfter, (string) $customer->credit_limit, 2) === 1) {
            throw new DomainException('The customer credit limit would be exceeded.');
        }

        $oldCustomerValues = ['balance' => $balanceBefore];
        $customer->update(['balance' => $balanceAfter]);
        $customer->refresh();
        AuditLogService::updated(Customer::class, $customer->id, $oldCustomerValues, ['balance' => (string) $customer->balance]);

        $entry = CustomerLedgerEntry::query()->create([
            'customer_id' => $customer->id,
            'user_id' => $user?->id,
            'order_id' => $order?->id,
            'sales_return_id' => $salesReturn?->id,
            'customer_payment_id' => $customerPayment?->id,
            'entry_type' => $entryType,
            'amount_delta' => $amountDelta,
            'balance_before' => $balanceBefore,
            'balance_after' => $balanceAfter,
            'description' => $description,
            'occurred_at' => now(),
        ]);
        AuditLogService::created(CustomerLedgerEntry::class, $entry->id, $this->entryValues($entry));

        return $entry;
    }

    private function normalizePositiveAmount(string $amount): string
    {
        $normalizedAmount = $this->normalizeNonNegativeAmount($amount);
        if (bccomp($normalizedAmount, '0.00', 2) !== 1) {
            throw new InvalidArgumentException('Amount must be greater than zero.');
        }

        return $normalizedAmount;
    }

    private function paymentStatus(Order $order, string $dueAmount): OrderPaymentStatus
    {
        if (bccomp((string) $order->refunded_amount, (string) $order->final_amount, 2) === 0) {
            return OrderPaymentStatus::Refunded;
        }
        if (bccomp((string) $order->refunded_amount, '0.00', 2) === 1) {
            return OrderPaymentStatus::PartiallyRefunded;
        }

        return bccomp($dueAmount, '0.00', 2) === 0
            ? OrderPaymentStatus::Paid
            : OrderPaymentStatus::Partial;
    }

    private function normalizeNonNegativeAmount(string $amount): string
    {
        $amount = trim($amount);
        if (! preg_match('/^\d+(?:\.\d{1,2})?$/', $amount)) {
            throw new InvalidArgumentException('Amount must be a non-negative decimal with at most two decimal places.');
        }

        return bcadd($amount, '0', 2);
    }

    /** @return array<string, mixed> */
    private function paymentValues(CustomerPayment $payment): array
    {
        return [
            'customer_id' => $payment->customer_id,
            'user_id' => $payment->user_id,
            'payment_method_id' => $payment->payment_method_id,
            'order_id' => $payment->order_id,
            'status' => $payment->status->value,
            'amount' => (string) $payment->amount,
            'reference_number' => $payment->reference_number,
            'paid_at' => $payment->paid_at?->toISOString(),
        ];
    }

    /** @return array<string, string> */
    private function orderPaymentValues(Order $order): array
    {
        return [
            'paid_amount' => (string) $order->paid_amount,
            'due_amount' => (string) $order->due_amount,
            'payment_status' => $order->payment_status->value,
        ];
    }

    /** @return array<string, mixed> */
    private function entryValues(CustomerLedgerEntry $entry): array
    {
        return [
            'customer_id' => $entry->customer_id,
            'entry_type' => $entry->entry_type->value,
            'amount_delta' => (string) $entry->amount_delta,
            'balance_before' => (string) $entry->balance_before,
            'balance_after' => (string) $entry->balance_after,
            'order_id' => $entry->order_id,
            'sales_return_id' => $entry->sales_return_id,
            'customer_payment_id' => $entry->customer_payment_id,
        ];
    }
}
