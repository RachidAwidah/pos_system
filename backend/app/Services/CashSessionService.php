<?php

namespace App\Services;

use App\Enums\CashMovementType;
use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Enums\ShiftStatus;
use App\Exceptions\BusinessInputException as InvalidArgumentException;
use App\Exceptions\BusinessRuleException as DomainException;
use App\Models\CashMovement;
use App\Models\Payment;
use App\Models\Register;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CashSessionService
{
    public function open(
        Register $register,
        User $openedBy,
        string $openingCash,
        ?string $notes = null,
    ): Shift {
        $normalizedOpeningCash = $this->normalizeMoney($openingCash);

        return DB::transaction(function () use ($register, $openedBy, $normalizedOpeningCash, $notes): Shift {
            $lockedRegister = Register::query()
                ->with('warehouse')
                ->lockForUpdate()
                ->findOrFail($register->id);
            $lockedUser = User::query()->lockForUpdate()->findOrFail($openedBy->id);

            if (! $lockedRegister->is_active || ! $lockedRegister->warehouse->is_active) {
                throw new DomainException('An inactive register or warehouse cannot open a cash session.');
            }

            if (Shift::query()->whereBelongsTo($lockedRegister)->where('status', ShiftStatus::Open->value)->exists()) {
                throw new DomainException('This register already has an open cash session.');
            }

            if (Shift::query()->whereBelongsTo($lockedUser, 'openedBy')->where('status', ShiftStatus::Open->value)->exists()) {
                throw new DomainException('This user already has an open cash session.');
            }

            $shift = Shift::query()->create([
                'register_id' => $lockedRegister->id,
                'opened_by_user_id' => $lockedUser->id,
                'status' => ShiftStatus::Open,
                'opened_at' => now(),
                'opening_cash' => $normalizedOpeningCash,
                'notes' => $notes,
            ]);
            AuditLogService::created(Shift::class, $shift->id, $this->shiftValues($shift));

            return $shift;
        }, attempts: 5);
    }

    public function cashIn(Shift $shift, User $user, string $amount, string $reason): CashMovement
    {
        return $this->recordMovement($shift, $user, CashMovementType::CashIn, $amount, $reason);
    }

    public function cashOut(Shift $shift, User $user, string $amount, string $reason): CashMovement
    {
        return $this->recordMovement($shift, $user, CashMovementType::CashOut, $amount, $reason);
    }

    public function close(Shift $shift, User $closedBy, string $closingCash, ?string $closingNotes = null, ?string $adminOverrideReason = null): Shift
    {
        return $this->closeSession($shift, $closedBy, $closingCash, null, $closingNotes, $adminOverrideReason);
    }

    public function forceClose(Shift $shift, User $closedBy, string $closingCash, string $reason, ?string $closingNotes = null): Shift
    {
        if (! $closedBy->hasRole('Admin')) {
            throw new DomainException('Only an administrator can force-close another cash session.');
        }

        $reason = trim($reason);
        if ($reason === '') {
            throw new InvalidArgumentException('A force-close reason is required.');
        }

        return $this->closeSession($shift, $closedBy, $closingCash, $reason, $closingNotes ?? $reason, $reason);
    }

    private function closeSession(
        Shift $shift,
        User $closedBy,
        string $closingCash,
        ?string $forceCloseReason = null,
        ?string $closingNotes = null,
        ?string $adminOverrideReason = null,
    ): Shift {
        $normalizedClosingCash = $this->normalizeMoney($closingCash);
        $normalizedClosingNotes = $closingNotes !== null ? trim($closingNotes) : null;
        $normalizedAdminOverrideReason = $adminOverrideReason !== null ? trim($adminOverrideReason) : null;

        return DB::transaction(function () use ($shift, $closedBy, $normalizedClosingCash, $forceCloseReason, $normalizedClosingNotes, $normalizedAdminOverrideReason): Shift {
            $lockedShift = Shift::query()->lockForUpdate()->findOrFail($shift->id);
            $this->ensureOpen($lockedShift);
            if ($forceCloseReason === null) {
                $this->ensureOwnedBy($lockedShift, $closedBy);
            }

            $expectedCash = $this->expectedCash($lockedShift);
            $differenceAmount = bcsub($normalizedClosingCash, $expectedCash, 2);
            $largeDifference = bccomp($differenceAmount, '50.00', 2) > 0 || bccomp($differenceAmount, '-50.00', 2) < 0;

            if ($forceCloseReason === null && bccomp($differenceAmount, '0', 2) !== 0 && ($normalizedClosingNotes === null || $normalizedClosingNotes === '')) {
                throw new InvalidArgumentException('يجب إدخال سبب الفرق في المبلغ.');
            }

            if ($largeDifference && ! $closedBy->hasRole('Admin')) {
                throw new DomainException('الفرق يتجاوز 50 دولارًا؛ يجب أن يغلق مسؤول النظام الشفت بعد مراجعة النقد.');
            }
            if ($largeDifference && ($normalizedAdminOverrideReason === null || $normalizedAdminOverrideReason === '')) {
                throw new InvalidArgumentException('يجب إدخال سبب التجاوز الإداري للفرق الذي يتجاوز 50 دولارًا.');
            }

            $oldValues = $this->shiftValues($lockedShift);

            $lockedShift->update([
                'status' => ShiftStatus::Closed,
                'closed_by_user_id' => $closedBy->id,
                'closed_at' => now(),
                'closing_cash' => $normalizedClosingCash,
                'expected_cash' => $expectedCash,
                'difference_amount' => $differenceAmount,
                'closing_notes' => $normalizedClosingNotes,
            ]);
            $lockedShift->refresh();
            AuditLogService::updated(Shift::class, $lockedShift->id, $oldValues, $this->shiftValues($lockedShift));

            if ($forceCloseReason !== null) {
                AuditLogService::log('force_close', Shift::class, $lockedShift->id, [
                    'opened_by_user_id' => $lockedShift->opened_by_user_id,
                ], [
                    'closed_by_user_id' => $closedBy->id,
                    'reason' => $forceCloseReason,
                ]);
            }

            if (bccomp($differenceAmount, '0', 2) !== 0) {
                AuditLogService::log('cash_difference', Shift::class, $lockedShift->id, [
                    'expected_cash' => $expectedCash,
                    'closing_cash' => $normalizedClosingCash,
                ], [
                    'difference_amount' => $differenceAmount,
                    'type' => bccomp($differenceAmount, '0', 2) === 1 ? 'overage' : 'shortage',
                    'closing_notes' => $normalizedClosingNotes,
                    'admin_override_reason' => $normalizedAdminOverrideReason,
                ]);
            }

            return $lockedShift;
        }, attempts: 5);
    }

    private function recordMovement(
        Shift $shift,
        User $user,
        CashMovementType $type,
        string $amount,
        string $reason,
    ): CashMovement {
        $normalizedAmount = $this->normalizePositiveMoney($amount);
        $normalizedReason = trim($reason);

        if ($normalizedReason === '') {
            throw new InvalidArgumentException('A cash movement reason is required.');
        }

        return DB::transaction(function () use ($shift, $user, $type, $normalizedAmount, $normalizedReason): CashMovement {
            $lockedShift = Shift::query()->lockForUpdate()->findOrFail($shift->id);
            $this->ensureOpen($lockedShift);
            $this->ensureOwnedBy($lockedShift, $user);

            if ($type === CashMovementType::CashOut && bccomp($normalizedAmount, $this->expectedCash($lockedShift), 2) === 1) {
                throw new DomainException('Cash out cannot exceed the expected cash currently in the register.');
            }

            $movement = CashMovement::query()->create([
                'shift_id' => $lockedShift->id,
                'user_id' => $user->id,
                'type' => $type,
                'amount' => $normalizedAmount,
                'reason' => $normalizedReason,
                'occurred_at' => now(),
            ]);
            AuditLogService::created(CashMovement::class, $movement->id, $this->movementValues($movement));

            return $movement;
        }, attempts: 5);
    }

    public function expectedCash(Shift $shift): string
    {
        return $this->cashSummary($shift)['expected_cash'];
    }

    /** @return array<string, string> */
    public function cashSummary(Shift $shift): array
    {
        $cashPayments = (string) Payment::query()
            ->whereBelongsTo($shift)
            ->where('type', PaymentType::Payment->value)
            ->where('status', PaymentStatus::Completed->value)
            ->whereHas('paymentMethod', fn ($query) => $query->where('category', 'cash'))
            ->sum('amount');
        $cashRefunds = (string) Payment::query()
            ->whereBelongsTo($shift)
            ->where('type', PaymentType::Refund->value)
            ->where('status', PaymentStatus::Completed->value)
            ->whereHas('paymentMethod', fn ($query) => $query->where('category', 'cash'))
            ->sum('amount');
        $cashIn = (string) $shift->cashMovements()
            ->where('type', CashMovementType::CashIn->value)
            ->sum('amount');
        $cashOut = (string) $shift->cashMovements()
            ->where('type', CashMovementType::CashOut->value)
            ->sum('amount');

        $expectedCash = bcsub(
            bcsub(bcadd(bcadd((string) $shift->opening_cash, $cashPayments, 2), $cashIn, 2), $cashRefunds, 2),
            $cashOut,
            2,
        );

        return [
            'currency' => 'USD',
            'opening_cash' => (string) $shift->opening_cash,
            'cash_payments' => bcadd($cashPayments, '0', 2),
            'cash_refunds' => bcadd($cashRefunds, '0', 2),
            'cash_in' => bcadd($cashIn, '0', 2),
            'cash_out' => bcadd($cashOut, '0', 2),
            'expected_cash' => $expectedCash,
        ];
    }

    private function ensureOpen(Shift $shift): void
    {
        if ($shift->status !== ShiftStatus::Open) {
            throw new DomainException('Only an open cash session can be changed.');
        }
    }

    private function ensureOwnedBy(Shift $shift, User $user): void
    {
        if ($shift->opened_by_user_id !== $user->id) {
            throw new DomainException('Only the user who opened the cash session can change it.');
        }
    }

    private function normalizePositiveMoney(string $amount): string
    {
        $normalizedAmount = $this->normalizeMoney($amount);

        if (bccomp($normalizedAmount, '0.00', 2) !== 1) {
            throw new InvalidArgumentException('Amount must be greater than zero.');
        }

        return $normalizedAmount;
    }

    private function normalizeMoney(string $amount): string
    {
        $amount = trim($amount);

        if (! preg_match('/^\d+(?:\.\d{1,2})?$/', $amount)) {
            throw new InvalidArgumentException('Amount must be a non-negative decimal with at most two decimal places.');
        }

        return bcadd($amount, '0', 2);
    }

    /** @return array<string, mixed> */
    private function shiftValues(Shift $shift): array
    {
        return [
            'register_id' => $shift->register_id,
            'opened_by_user_id' => $shift->opened_by_user_id,
            'closed_by_user_id' => $shift->closed_by_user_id,
            'status' => $shift->status->value,
            'opened_at' => $shift->opened_at?->toISOString(),
            'closed_at' => $shift->closed_at?->toISOString(),
            'opening_cash' => (string) $shift->opening_cash,
            'closing_cash' => $shift->closing_cash === null ? null : (string) $shift->closing_cash,
            'expected_cash' => $shift->expected_cash === null ? null : (string) $shift->expected_cash,
            'difference_amount' => $shift->difference_amount === null ? null : (string) $shift->difference_amount,
            'notes' => $shift->notes,
            'closing_notes' => $shift->closing_notes,
        ];
    }

    /** @return array<string, mixed> */
    private function movementValues(CashMovement $movement): array
    {
        return [
            'shift_id' => $movement->shift_id,
            'user_id' => $movement->user_id,
            'type' => $movement->type->value,
            'amount' => (string) $movement->amount,
            'reason' => $movement->reason,
            'occurred_at' => $movement->occurred_at?->toISOString(),
        ];
    }
}
