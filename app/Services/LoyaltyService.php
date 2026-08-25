<?php

namespace App\Services;

use App\Enums\LoyaltyTransactionType;
use App\Models\Customer;
use App\Models\LoyaltyTransaction;
use App\Models\Order;
use App\Models\SalesReturn;
use App\Models\Setting;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class LoyaltyService
{
    public function earnForOrder(Customer $customer, Order $order, ?User $user = null): ?LoyaltyTransaction
    {
        if (! Setting::valueFor('loyalty_enabled', true)) {
            return null;
        }

        $pointsPerCurrency = (string) Setting::valueFor('loyalty_points_per_currency', 1);
        $points = (int) floor((float) $order->final_amount * (float) $pointsPerCurrency);
        if ($points < 1) {
            return null;
        }

        return DB::transaction(function () use ($customer, $order, $user, $points): LoyaltyTransaction {
            $lockedCustomer = Customer::query()->lockForUpdate()->findOrFail($customer->id);
            if ($order->customer_id !== $lockedCustomer->id) {
                throw new InvalidArgumentException('The order does not belong to this customer.');
            }

            $existingTransaction = LoyaltyTransaction::query()
                ->whereBelongsTo($order)
                ->where('transaction_type', LoyaltyTransactionType::Earned->value)
                ->first();
            if ($existingTransaction !== null) {
                return $existingTransaction;
            }

            return $this->applyPoints(
                $lockedCustomer,
                LoyaltyTransactionType::Earned,
                $points,
                $user,
                order: $order,
                reason: 'Points earned from order '.$order->invoice_number,
            );
        }, attempts: 5);
    }

    public function redeemForOrder(Customer $customer, Order $order, User $user, int $points): LoyaltyTransaction
    {
        $minimumPoints = (int) Setting::valueFor('loyalty_minimum_redemption_points', 100);
        if ($points < $minimumPoints) {
            throw new DomainException("At least {$minimumPoints} points are required for redemption.");
        }

        return DB::transaction(function () use ($customer, $order, $user, $points): LoyaltyTransaction {
            $lockedCustomer = Customer::query()->lockForUpdate()->findOrFail($customer->id);
            if ($order->customer_id !== $lockedCustomer->id) {
                throw new InvalidArgumentException('The order does not belong to this customer.');
            }
            if ($points > $lockedCustomer->loyalty_points) {
                throw new DomainException('The customer does not have enough loyalty points.');
            }

            return $this->applyPoints(
                $lockedCustomer,
                LoyaltyTransactionType::Redeemed,
                -$points,
                $user,
                order: $order,
                reason: 'Points redeemed on order '.$order->invoice_number,
            );
        }, attempts: 5);
    }

    public function reverseForReturn(
        Customer $customer,
        SalesReturn $salesReturn,
        User $user,
        int $points,
    ): ?LoyaltyTransaction {
        if ($points < 1) {
            return null;
        }

        return DB::transaction(function () use ($customer, $salesReturn, $user, $points): LoyaltyTransaction {
            $lockedCustomer = Customer::query()->lockForUpdate()->findOrFail($customer->id);
            $salesReturn->loadMissing('order');
            if ($salesReturn->order->customer_id !== $lockedCustomer->id) {
                throw new InvalidArgumentException('The sales return does not belong to this customer.');
            }

            $reversiblePoints = min($points, $lockedCustomer->loyalty_points);
            if ($reversiblePoints < 1) {
                return null;
            }

            return $this->applyPoints(
                $lockedCustomer,
                LoyaltyTransactionType::Reversed,
                -$reversiblePoints,
                $user,
                order: $salesReturn->order,
                salesReturn: $salesReturn,
                reason: 'Points reversed for return '.$salesReturn->return_number,
            );
        }, attempts: 5);
    }

    private function applyPoints(
        Customer $customer,
        LoyaltyTransactionType $type,
        int $pointsDelta,
        ?User $user = null,
        ?Order $order = null,
        ?SalesReturn $salesReturn = null,
        ?string $reason = null,
    ): LoyaltyTransaction {
        if ($pointsDelta === 0) {
            throw new InvalidArgumentException('Loyalty points change cannot be zero.');
        }

        $balanceBefore = $customer->loyalty_points;
        $balanceAfter = $balanceBefore + $pointsDelta;
        if ($balanceAfter < 0) {
            throw new DomainException('Loyalty points cannot become negative.');
        }

        $customer->update(['loyalty_points' => $balanceAfter]);
        $customer->refresh();
        AuditLogService::updated(
            Customer::class,
            $customer->id,
            ['loyalty_points' => $balanceBefore],
            ['loyalty_points' => $customer->loyalty_points],
        );

        $transaction = LoyaltyTransaction::query()->create([
            'customer_id' => $customer->id,
            'user_id' => $user?->id,
            'order_id' => $order?->id,
            'sales_return_id' => $salesReturn?->id,
            'transaction_type' => $type,
            'points_delta' => $pointsDelta,
            'balance_before' => $balanceBefore,
            'balance_after' => $balanceAfter,
            'reason' => $reason,
            'occurred_at' => now(),
        ]);
        AuditLogService::created(LoyaltyTransaction::class, $transaction->id, $this->transactionValues($transaction));

        return $transaction;
    }

    /** @return array<string, mixed> */
    private function transactionValues(LoyaltyTransaction $transaction): array
    {
        return [
            'customer_id' => $transaction->customer_id,
            'transaction_type' => $transaction->transaction_type->value,
            'points_delta' => $transaction->points_delta,
            'balance_before' => $transaction->balance_before,
            'balance_after' => $transaction->balance_after,
            'order_id' => $transaction->order_id,
            'sales_return_id' => $transaction->sales_return_id,
            'reason' => $transaction->reason,
        ];
    }
}
