<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\PersonalAccessToken;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use DatabaseTransactions;

    public function test_user_can_login_and_receive_a_uuid_token(): void
    {
        $user = User::query()->where('email', config('pos.admin_email'))->firstOrFail();
        $user->update(['password_hash' => Hash::make('KnownPassword!123')]);

        $response = $this->postJson('/v1/login', [
            'email' => $user->email,
            'password' => 'KnownPassword!123',
            'device_name' => 'test-terminal',
        ]);

        $response->assertOk()->assertJsonStructure(['token', 'user' => ['id', 'name', 'roles']]);
        $token = PersonalAccessToken::query()->firstOrFail();
        $this->assertSame(36, strlen($token->id));
        $this->assertSame($user->id, $token->tokenable_id);
    }

    public function test_invalid_credentials_are_rejected(): void
    {
        $this->postJson('/v1/login', [
            'email' => config('pos.admin_email'),
            'password' => 'incorrect-password',
        ])->assertUnprocessable()->assertJsonValidationErrors('email');
    }

    public function test_user_can_logout_and_revoke_current_token(): void
    {
        $user = User::query()->where('email', config('pos.admin_email'))->firstOrFail();
        $user->tokens()->delete();
        $plainTextToken = $user->createToken('test-terminal')->plainTextToken;
        $otherToken = $user->createToken('other-terminal')->accessToken;

        $this->withToken($plainTextToken)->postJson('/v1/logout')->assertOk();
        $this->assertSame(1, $user->tokens()->count());
        $this->assertTrue($user->tokens()->whereKey($otherToken->id)->exists());
    }

    public function test_authenticated_user_can_retrieve_current_profile(): void
    {
        $user = User::query()->where('email', config('pos.admin_email'))->firstOrFail();
        $user->update(['must_change_password' => true]);
        $plainTextToken = $user->createToken('me-terminal')->plainTextToken;
        $viewAuditCount = AuditLog::query()
            ->where('action', 'view')
            ->where('entity_type', User::class)
            ->where('entity_id', $user->id)
            ->count();

        $this->withToken($plainTextToken)->getJson('/v1/me')
            ->assertOk()
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonPath('user.email', $user->email)
            ->assertJsonPath('user.must_change_password', true)
            ->assertJsonStructure(['user' => ['roles', 'permissions']]);

        $this->assertSame($viewAuditCount, AuditLog::query()
            ->where('action', 'view')
            ->where('entity_type', User::class)
            ->where('entity_id', $user->id)
            ->count());
    }

    public function test_me_requires_a_valid_token(): void
    {
        $this->getJson('/v1/me')->assertUnauthorized();
    }

    public function test_user_can_logout_from_all_devices(): void
    {
        $user = User::query()->where('email', config('pos.admin_email'))->firstOrFail();
        $user->update(['must_change_password' => true]);
        $user->tokens()->delete();
        $plainTextToken = $user->createToken('current-terminal')->plainTextToken;
        $user->createToken('other-terminal');

        $this->withToken($plainTextToken)->postJson('/v1/logout-all')->assertOk();

        $this->assertSame(0, $user->tokens()->count());
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'action' => 'logout_all',
            'entity_type' => User::class,
            'entity_id' => $user->id,
        ]);
    }

    public function test_api_login_is_rate_limited_by_email_and_ip_address(): void
    {
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->postJson('/v1/login', [
                'email' => 'rate.limit@example.com',
                'password' => 'Incorrect!123',
            ])->assertUnprocessable();
        }

        $this->postJson('/v1/login', [
            'email' => 'rate.limit@example.com',
            'password' => 'Incorrect!123',
        ])->assertTooManyRequests();
    }

    public function test_expired_sanctum_token_cannot_access_protected_routes(): void
    {
        $user = User::query()->where('email', config('pos.admin_email'))->firstOrFail();
        $user->update(['must_change_password' => false]);
        $token = $user->createToken('expired-terminal');
        $token->accessToken->forceFill(['created_at' => now()->subMinutes(721)])->save();

        $this->withToken($token->plainTextToken)->getJson('/v1/users')->assertUnauthorized();
    }
}
