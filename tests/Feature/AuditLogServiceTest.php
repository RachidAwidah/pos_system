<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use LogicException;
use Tests\TestCase;

class AuditLogServiceTest extends TestCase
{
    use DatabaseTransactions;

    public function test_update_logs_only_changed_values_and_removes_sensitive_data(): void
    {
        $admin = User::query()->where('email', config('pos.admin_email'))->firstOrFail();
        Sanctum::actingAs($admin);

        AuditLogService::updated(User::class, $admin->id, [
            'full_name' => 'Old Name',
            'email' => 'same@example.com',
            'password_hash' => 'old-secret',
            'integration' => ['access_token' => 'old-token', 'enabled' => false],
        ], [
            'full_name' => 'New Name',
            'email' => 'same@example.com',
            'password_hash' => 'new-secret',
            'integration' => ['access_token' => 'new-token', 'enabled' => true],
        ]);

        $audit = AuditLog::query()
            ->where('entity_type', User::class)
            ->where('entity_id', $admin->id)
            ->where('action', 'update')
            ->latest('logged_at')
            ->firstOrFail();

        $this->assertEquals([
            'full_name' => 'Old Name',
            'password_hash' => '[REDACTED]',
            'integration' => ['access_token' => '[REDACTED]', 'enabled' => false],
        ], $audit->old_values);
        $this->assertEquals([
            'full_name' => 'New Name',
            'password_hash' => '[REDACTED]',
            'integration' => ['access_token' => '[REDACTED]', 'enabled' => true],
        ], $audit->new_values);
        $this->assertStringNotContainsString('old-secret', json_encode($audit->old_values, JSON_THROW_ON_ERROR));
        $this->assertStringNotContainsString('new-token', json_encode($audit->new_values, JSON_THROW_ON_ERROR));
    }

    public function test_unchanged_update_does_not_create_an_audit_record(): void
    {
        $admin = User::query()->where('email', config('pos.admin_email'))->firstOrFail();
        Sanctum::actingAs($admin);
        $beforeCount = AuditLog::query()->count();

        AuditLogService::updated(User::class, $admin->id, ['email' => $admin->email], ['email' => $admin->email]);

        $this->assertSame($beforeCount, AuditLog::query()->count());
    }

    public function test_audit_records_cannot_be_updated(): void
    {
        $admin = User::query()->where('email', config('pos.admin_email'))->firstOrFail();
        AuditLogService::log('test', User::class, $admin->id);
        $audit = AuditLog::query()->where('action', 'test')->latest('logged_at')->firstOrFail();

        $this->expectException(LogicException::class);

        $audit->update(['action' => 'tampered']);
    }

    public function test_audit_records_cannot_be_deleted(): void
    {
        $admin = User::query()->where('email', config('pos.admin_email'))->firstOrFail();
        AuditLogService::log('test', User::class, $admin->id);
        $audit = AuditLog::query()->where('action', 'test')->latest('logged_at')->firstOrFail();

        $this->expectException(LogicException::class);

        $audit->delete();
    }
}
