<?php

namespace Tests\Feature;

use App\Enums\CashMovementType;
use App\Enums\ShiftStatus;
use App\Models\CashMovement;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Register;
use App\Models\User;
use App\Services\CashSessionService;
use DomainException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use LogicException;
use Tests\TestCase;

class CashSessionServiceTest extends TestCase
{
    use DatabaseTransactions;

    public function test_cash_session_tracks_movements_and_closes_with_expected_cash(): void
    {
        $register = Register::query()->where('code', 'MAIN-REG-01')->firstOrFail();
        $user = User::query()->firstOrFail();
        $service = app(CashSessionService::class);
        $shift = $service->open($register, $user, '100.00');
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'shift_id' => $shift->id,
        ]);
        Payment::factory()->create([
            'order_id' => $order->id,
            'shift_id' => $shift->id,
            'user_id' => $user->id,
            'payment_method_id' => PaymentMethod::query()->where('code', 'CASH')->firstOrFail()->id,
            'amount' => '75.00',
        ]);

        $cashIn = $service->cashIn($shift, $user, '20.00', 'Petty cash replenishment');
        $cashOut = $service->cashOut($shift, $user, '10.00', 'Local delivery expense');
        $closedShift = $service->close($shift, $user, '180.00');

        $this->assertSame(CashMovementType::CashIn, $cashIn->type);
        $this->assertSame(CashMovementType::CashOut, $cashOut->type);
        $this->assertSame(ShiftStatus::Closed, $closedShift->status);
        $this->assertSame('185.00', $closedShift->expected_cash);
        $this->assertSame('-5.00', $closedShift->difference_amount);
        $this->assertNotNull($closedShift->closed_at);
    }

    public function test_register_and_opener_cannot_have_duplicate_open_sessions(): void
    {
        $register = Register::query()->where('code', 'MAIN-REG-01')->firstOrFail();
        $user = User::query()->firstOrFail();
        $service = app(CashSessionService::class);
        $service->open($register, $user, '100.00');

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('This register already has an open cash session.');
        $service->open($register, $user, '100.00');
    }

    public function test_cash_out_cannot_exceed_expected_register_cash(): void
    {
        $register = Register::query()->where('code', 'MAIN-REG-01')->firstOrFail();
        $user = User::query()->firstOrFail();
        $shift = app(CashSessionService::class)->open($register, $user, '50.00');

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Cash out cannot exceed the expected cash currently in the register.');
        app(CashSessionService::class)->cashOut($shift, $user, '50.01', 'Invalid withdrawal');
    }

    public function test_closed_session_cannot_receive_new_cash_movements(): void
    {
        $register = Register::query()->where('code', 'MAIN-REG-01')->firstOrFail();
        $user = User::query()->firstOrFail();
        $service = app(CashSessionService::class);
        $shift = $service->open($register, $user, '50.00');
        $service->close($shift, $user, '50.00');

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Only an open cash session can be changed.');
        $service->cashIn($shift, $user, '10.00', 'Late deposit');
    }

    public function test_cash_movements_are_append_only(): void
    {
        $movement = CashMovement::factory()->create();

        $this->expectException(LogicException::class);
        $movement->update(['reason' => 'Changed']);
    }
}
