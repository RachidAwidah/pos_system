<?php

namespace App\Services;

use App\Enums\CashMovementType;
use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Enums\ShiftStatus;
use App\Models\CashMovement;
use App\Models\Payment;
use App\Models\Register;
use App\Models\Shift;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

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

    public function close(Shift $shift, User $closedBy, string $closingCash): Shift
    {
        $normalizedClosingCash = $this->normalizeMoney($closingCash);

        return DB::transaction(function () use ($shift, $closedBy, $normalizedClosingCash): Shift {
            $lockedShift = Shift::query()->lockForUpdate()->findOrFail($shift->id);
            $this->ensureOpen($lockedShift);

            $expectedCash = $this->expectedCash($lockedShift);
            $differenceAmount = bcsub($normalizedClosingCash, $expectedCash, 2);
            $oldValues = $this->shiftValues($lockedShift);

            $lockedShift->update([
                'status' => ShiftStatus::Closed,
                'closed_by_user_id' => $closedBy->id,
                'closed_at' => now(),
                'closing_cash' => $normalizedClosingCash,
                'expected_cash' => $expectedCash,
                'difference_amount' => $differenceAmount,
            ]);
            $lockedShift->refresh();
            AuditLogService::updated(Shift::class, $lockedShift->id, $oldValues, $this->shiftValues($lockedShift));

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

    private function expectedCash(Shift $shift): string
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

        return bcsub(
            bcsub(bcadd(bcadd((string) $shift->opening_cash, $cashPayments, 2), $cashIn, 2), $cashRefunds, 2),
            $cashOut,
            2,
        );
    }

    private function ensureOpen(Shift $shift): void
    {
        if ($shift->status !== ShiftStatus::Open) {
            throw new DomainException('Only an open cash session can be changed.');
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
