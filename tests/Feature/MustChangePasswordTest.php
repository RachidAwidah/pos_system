<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MustChangePasswordTest extends TestCase
{
    use DatabaseTransactions;

    public function test_user_is_redirected_until_password_is_changed(): void
    {
        $user = User::factory()->create(['must_change_password' => true]);

        $this->actingAs($user)->get('/')->assertRedirect(route('password.change.form'));
        $this->actingAs($user)->get('/password/change')->assertOk();
    }

    public function test_user_can_change_required_password(): void
    {
        $user = User::factory()->create([
            'password_hash' => Hash::make('OldPassword!123'),
            'must_change_password' => true,
        ]);

        $this->actingAs($user)->post('/password/change', [
            'old_password' => 'OldPassword!123',
            'new_password' => 'NewPassword!123',
            'new_password_confirmation' => 'NewPassword!123',
        ])->assertRedirect(route('dashboard'));

        $this->assertFalse($user->refresh()->must_change_password);
        $this->assertTrue(Hash::check('NewPassword!123', $user->password_hash));
    }

    public function test_user_cannot_change_to_a_weak_password(): void
    {
        $user = User::factory()->create([
            'password_hash' => Hash::make('OldPassword!123'),
            'must_change_password' => true,
        ]);

        $this->actingAs($user)->post('/password/change', [
            'old_password' => 'OldPassword!123',
            'new_password' => 'password123',
            'new_password_confirmation' => 'password123',
        ])->assertSessionHasErrors('new_password');

        $this->assertTrue($user->refresh()->must_change_password);
    }

    public function test_user_can_logout_before_completing_the_required_password_change(): void
    {
        $user = User::factory()->create(['must_change_password' => true]);

        $this->actingAs($user)->post('/logout')->assertRedirect(route('login'));

        $this->assertGuest();
    }
}
