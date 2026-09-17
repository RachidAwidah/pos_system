<?php

namespace Tests\Feature;

use App\Enums\ShiftStatus;
use App\Models\AuditLog;
use App\Models\Shift;
use App\Services\CashSessionService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CashSessionApiTest extends TestCase
{
    use DatabaseTransactions;

    public function test_cashier_can_close_at_fifty_dollar_boundary_but_must_explain_difference(): void
    {
        foreach (['50.00', '150.00'] as $counted) {
            $owner = $this->createTestUser();
            $owner->assignRole('Cashier');
            $shift = app(CashSessionService::class)->open($this->createTestRegister(), $owner, '100.00');
            Sanctum::actingAs($owner);
            $this->postJson("/v1/shifts/{$shift->id}/close", ['closing_cash' => $counted])->assertStatus(422);
            $this->assertSame(ShiftStatus::Open, $shift->refresh()->status);
            $this->postJson("/v1/shifts/{$shift->id}/close", [
                'closing_cash' => $counted, 'closing_notes' => 'Counted and documented',
            ])->assertOk()->assertJsonPath('data.closing_cash', $counted);
        }
    }

    public function test_large_difference_requires_admin_and_an_audited_reason(): void
    {
        $owner = $this->createTestUser();
        $owner->assignRole('Cashier');
        $shift = app(CashSessionService::class)->open($this->createTestRegister(), $owner, '100.00');
        Sanctum::actingAs($owner);
        $this->postJson("/v1/shifts/{$shift->id}/close", [
            'closing_cash' => '0', 'closing_notes' => 'Counted shortage', 'admin_override_reason' => 'Not an admin',
        ])->assertStatus(409);
        $this->assertSame(ShiftStatus::Open, $shift->refresh()->status);

        $admin = $this->createTestUser();
        $admin->assignRole('Admin');
        Sanctum::actingAs($admin);
        $this->postJson("/v1/shifts/{$shift->id}/force-close", [
            'closing_cash' => '0', 'reason' => 'Reviewed actual shortage',
        ])->assertOk()->assertJsonPath('data.difference_amount', '-100.00');
        $audit = AuditLog::query()->where('entity_id', $shift->id)->where('action', 'cash_difference')->firstOrFail();
        $this->assertSame('Reviewed actual shortage', $audit->new_values['admin_override_reason']);
    }

    public function test_admin_own_shift_requires_override_for_large_difference(): void
    {
        $admin = $this->createTestUser();
        $admin->assignRole('Admin');
        $shift = app(CashSessionService::class)->open($this->createTestRegister(), $admin, '100.00');
        Sanctum::actingAs($admin);
        $payload = ['closing_cash' => '160.00', 'closing_notes' => 'Counted excess'];
        $this->postJson("/v1/shifts/{$shift->id}/close", $payload)->assertStatus(422);
        $this->postJson("/v1/shifts/{$shift->id}/close", $payload + ['admin_override_reason' => 'Reviewed excess'])
            ->assertOk()->assertJsonPath('data.difference_amount', '60.00');
    }

    public function test_summary_is_restricted_and_matches_cash_movements(): void
    {
        $owner = $this->createTestUser();
        $owner->assignRole('Cashier');
        $service = app(CashSessionService::class);
        $shift = $service->open($this->createTestRegister(), $owner, '100.00');
        $service->cashIn($shift, $owner, '20', 'Float');
        $service->cashOut($shift, $owner, '5', 'Expense');
        Sanctum::actingAs($owner);
        $this->getJson("/v1/shifts/{$shift->id}/summary")->assertOk()
            ->assertJsonPath('data.expected_cash', '115.00')->assertJsonPath('data.cash_in', '20.00')
            ->assertJsonPath('data.cash_out', '5.00')->assertJsonPath('data.currency', 'USD');
        $other = $this->createTestUser();
        $other->assignRole('Cashier');
        Sanctum::actingAs($other);
        $this->getJson("/v1/shifts/{$shift->id}/summary")->assertForbidden();
    }

    public function test_logout_and_logout_all_do_not_settle_open_cash(): void
    {
        foreach (['logout', 'logout-all'] as $endpoint) {
            $user = $this->createTestUser();
            $shift = app(CashSessionService::class)->open($this->createTestRegister(), $user, '100.00');
            $token = $user->createToken('shift-test')->plainTextToken;
            $this->app['auth']->forgetGuards();
            $this->withToken($token)->postJson('/v1/'.$endpoint)->assertOk();
            $this->assertSame(ShiftStatus::Open, $shift->refresh()->status);
            $this->assertNull($shift->closing_cash);
            $this->assertNull($shift->closed_at);
            $this->assertSame(0, $user->tokens()->count());
        }
    }

    public function test_pos_reference_data_restores_the_current_users_open_shift(): void
    {
        $admin = $this->createTestUser();
        $admin->assignRole('Admin');
        Sanctum::actingAs($admin);
        $shift = app(CashSessionService::class)->open($this->createTestRegister(), $admin, '100.00');

        $this->getJson('/v1/pos/reference-data')
            ->assertOk()
            ->assertJsonPath('data.current_shift.id', $shift->id)
            ->assertJsonPath('data.current_shift.status', ShiftStatus::Open->value)
            ->assertJsonPath('data.can_force_close_shifts', true);
    }

    public function test_admin_can_force_close_another_users_shift_with_an_audited_reason(): void
    {
        $owner = $this->createTestUser();
        $shift = app(CashSessionService::class)->open($this->createTestRegister(), $owner, '50.00');
        $admin = $this->createTestUser();
        $admin->assignRole('Admin');
        Sanctum::actingAs($admin);

        $this->postJson("/v1/shifts/{$shift->id}/force-close", [
            'closing_cash' => '50.00',
            'reason' => 'Cashier left without closing the shift.',
        ])->assertOk()
            ->assertJsonPath('data.status', ShiftStatus::Closed->value)
            ->assertJsonPath('data.closed_by_user_id', $admin->id);

        $this->assertSame(ShiftStatus::Closed, $shift->refresh()->status);
        $audit = AuditLog::query()
            ->where('entity_type', Shift::class)
            ->where('entity_id', $shift->id)
            ->where('action', 'force_close')
            ->firstOrFail();
        $this->assertSame('Cashier left without closing the shift.', $audit->new_values['reason']);
    }

    public function test_non_admin_cannot_force_close_another_users_shift(): void
    {
        $owner = $this->createTestUser();
        $shift = app(CashSessionService::class)->open($this->createTestRegister(), $owner, '0');
        $cashier = $this->createTestUser();
        $cashier->assignRole('Cashier');
        Sanctum::actingAs($cashier);

        $this->postJson("/v1/shifts/{$shift->id}/force-close", [
            'closing_cash' => '0',
            'reason' => 'Not allowed.',
        ])->assertForbidden();

        $this->assertSame(ShiftStatus::Open, $shift->refresh()->status);
    }
}
