<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuditLogApiTest extends TestCase
{
    use DatabaseTransactions;

    public function test_guests_and_unprivileged_users_cannot_read_audit_logs(): void
    {
        $audit = $this->record();
        $this->getJson('/v1/audit-logs')->assertUnauthorized();
        $this->getJson('/v1/audit-logs/'.$audit->id)->assertUnauthorized();

        foreach (['Cashier', 'Manager'] as $role) {
            $user = $this->createTestUser();
            $user->assignRole($role);
            Sanctum::actingAs($user);
            $this->getJson('/v1/audit-logs')->assertForbidden();
            $this->getJson('/v1/audit-logs/'.$audit->id)->assertForbidden();
        }
    }

    public function test_admin_bypasses_empty_permissions_and_list_reads_do_not_generate_audits(): void
    {
        $this->loginAdmin();
        Role::query()->where('role_name', 'Admin')->firstOrFail()->permissions()->detach();
        auth()->user()->unsetRelation('roles');
        $count = AuditLog::query()->count();
        $this->getJson('/v1/audit-logs')->assertOk()->assertJsonStructure(['data', 'links', 'meta']);
        $this->assertSame($count, AuditLog::query()->count());
    }

    public function test_explicit_permission_grants_read_access_only(): void
    {
        $role = Role::factory()->create();
        $role->permissions()->attach(Permission::query()->where('permission_key', 'audit_logs.view')->firstOrFail());
        $user = $this->createTestUser();
        $user->assignRole($role);
        Sanctum::actingAs($user);
        $audit = $this->record();
        $this->getJson('/v1/audit-logs')->assertOk();
        $this->getJson('/v1/audit-logs/'.$audit->id)->assertOk();
        $this->patchJson('/v1/audit-logs/'.$audit->id, ['action' => 'tamper'])->assertStatus(405);
        $this->deleteJson('/v1/audit-logs/'.$audit->id)->assertStatus(405);
        $this->getJson('/v1/users')->assertForbidden();
    }

    public function test_filters_are_combined_and_date_bounds_are_inclusive(): void
    {
        $this->loginAdmin();
        $user = $this->createTestUser();
        $entityId = (string) Str::uuid();
        $base = ['user_id' => $user->id, 'entity_id' => $entityId, 'entity_type' => User::class, 'action' => 'update'];
        $first = $this->record([...$base, 'logged_at' => '2026-09-01 00:00:00']);
        $last = $this->record([...$base, 'logged_at' => '2026-09-02 23:59:59']);
        $this->record([...$base, 'logged_at' => '2026-09-03 00:00:00']);
        $this->record([...$base, 'logged_at' => '2026-08-31 23:59:59']);
        $this->record([...$base, 'action' => 'create', 'logged_at' => '2026-09-02 12:00:00']);
        $this->record([...$base, 'entity_type' => Role::class, 'logged_at' => '2026-09-02 12:00:00']);
        $this->record([...$base, 'user_id' => null, 'logged_at' => '2026-09-02 12:00:00']);
        $this->record([...$base, 'entity_id' => (string) Str::uuid(), 'logged_at' => '2026-09-02 12:00:00']);
        $filters = [...$base, 'from' => '2026-09-01', 'to' => '2026-09-02'];
        $this->getJson('/v1/audit-logs?'.http_build_query($filters))
            ->assertOk()->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $last->id)->assertJsonPath('data.1.id', $first->id);
    }

    public function test_each_filter_can_be_used_independently(): void
    {
        $this->loginAdmin();
        $user = $this->createTestUser();
        $audit = $this->record(['user_id' => $user->id, 'action' => 'unique_test_action', 'entity_type' => 'UniqueTestEntity', 'logged_at' => '2030-01-01 12:00:00']);
        foreach (['user_id' => $user->id, 'action' => $audit->action, 'entity_type' => $audit->entity_type, 'entity_id' => $audit->entity_id, 'from' => '2030-01-01'] as $key => $value) {
            $this->getJson('/v1/audit-logs?'.http_build_query([$key => $value]))
                ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $audit->id);
        }
        $this->getJson('/v1/audit-logs?to=2000-01-01')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_pagination_is_stable_and_retains_filters(): void
    {
        $this->loginAdmin();
        $entityId = (string) Str::uuid();
        $older = $this->record(['entity_id' => $entityId, 'logged_at' => '2026-09-01 10:00:00']);
        $newer = $this->record(['entity_id' => $entityId, 'logged_at' => '2026-09-01 11:00:00']);
        $response = $this->getJson('/v1/audit-logs?'.http_build_query(['entity_id' => $entityId, 'per_page' => 1]))
            ->assertOk()->assertJsonPath('meta.total', 2)->assertJsonPath('data.0.id', $newer->id);
        $this->assertStringContainsString('entity_id='.$entityId, $response->json('links.next'));
        $this->getJson('/v1/audit-logs?'.http_build_query(['entity_id' => $entityId, 'per_page' => 1, 'page' => 2]))
            ->assertOk()->assertJsonPath('data.0.id', $older->id);
    }

    public function test_invalid_filter_inputs_are_rejected(): void
    {
        $this->loginAdmin();
        foreach ([['per_page' => 101], ['per_page' => 0], ['page' => 0], ['user_id' => 'invalid'], ['entity_id' => 'invalid'], ['action' => ['create']], ['entity_type' => ['User']], ['from' => 'yesterday'], ['from' => '2026-09-02', 'to' => '2026-09-01']] as $filters) {
            $this->getJson('/v1/audit-logs?'.http_build_query($filters))->assertUnprocessable();
        }
    }

    public function test_legacy_secrets_are_redacted_in_list_and_details_without_modifying_history(): void
    {
        $this->loginAdmin();
        $values = [
            'name' => 'Visible name', 'password_hash' => 'secret-password',
            'integration' => ['apiKey' => 'secret-key', 'accessToken' => 'secret-token'],
            'settings' => [['key' => 'GEMINI_API_KEY', 'value' => 'secret-value']],
            'headers' => ['Authorization' => 'secret-auth', 'Cookie' => 'secret-cookie'],
        ];
        $audit = $this->record(['old_values' => $values, 'new_values' => $values, 'user_agent' => 'secret-user-agent']);
        $count = AuditLog::query()->count();
        foreach (['/v1/audit-logs?entity_id='.$audit->entity_id, '/v1/audit-logs/'.$audit->id] as $url) {
            $response = $this->getJson($url)->assertOk();
            $this->assertStringNotContainsString('secret-', $response->getContent());
            $this->assertStringContainsString('Visible name', $response->getContent());
            $this->assertStringContainsString('[REDACTED]', $response->getContent());
        }
        $this->assertEquals($values, $audit->refresh()->old_values);
        $this->assertSame($count + 1, AuditLog::query()->count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'view', 'entity_type' => AuditLog::class, 'entity_id' => $audit->id]);
    }

    public function test_missing_actor_and_unknown_record_are_handled(): void
    {
        $this->loginAdmin();
        $audit = $this->record(['user_id' => null]);
        $this->getJson('/v1/audit-logs/'.$audit->id)->assertOk()->assertJsonPath('data.user', null);
        $this->getJson('/v1/audit-logs/'.Str::uuid())->assertNotFound();
    }

    public function test_new_secret_formats_are_redacted_before_storage(): void
    {
        $this->loginAdmin();
        $id = (string) Str::uuid();
        AuditLogService::created(User::class, $id, ['apiKey' => 'secret-key', 'nested' => ['name' => 'accessToken', 'value' => 'secret-token']]);
        $values = AuditLog::query()->where('entity_id', $id)->firstOrFail()->new_values;
        $this->assertSame('[REDACTED]', $values['apiKey']);
        $this->assertSame('[REDACTED]', $values['nested']['value']);
    }

    public function test_admin_must_change_temporary_password_before_reading_logs(): void
    {
        $user = $this->createTestUser();
        $user->assignRole('Admin');
        $user->update(['must_change_password' => true]);
        Sanctum::actingAs($user);
        $this->getJson('/v1/audit-logs')->assertForbidden();
    }

    private function loginAdmin(): void
    {
        $user = $this->createTestUser();
        $user->assignRole('Admin');
        Sanctum::actingAs($user);
    }

    /** @param array<string, mixed> $attributes */
    private function record(array $attributes = []): AuditLog
    {
        return AuditLog::query()->create([
            'action' => 'create', 'entity_type' => User::class, 'entity_id' => (string) Str::uuid(), 'logged_at' => now(),
            ...$attributes,
        ]);
    }
}
