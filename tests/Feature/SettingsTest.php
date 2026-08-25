<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    use DatabaseTransactions;

    public function test_public_settings_do_not_expose_private_values(): void
    {
        $response = $this->getJson('/v1/settings/public')->assertOk();
        $keys = collect($response->json('data'))->pluck('key');

        $this->assertTrue($keys->contains('store_name'));
        $this->assertFalse($keys->contains('allow_negative_stock'));
    }

    public function test_admin_can_update_a_setting(): void
    {
        $admin = User::query()->where('email', config('pos.admin_email'))->firstOrFail();
        $admin->update(['must_change_password' => false]);
        Sanctum::actingAs($admin);
        $setting = Setting::query()->where('key', 'store_name')->firstOrFail();

        $this->putJson("/v1/settings/{$setting->id}", ['value' => 'Updated POS'])
            ->assertOk()
            ->assertJsonPath('data.value', 'Updated POS');

        $this->assertSame('Updated POS', Setting::valueFor('store_name'));

        $audit = AuditLog::query()
            ->where('action', 'update')
            ->where('entity_type', Setting::class)
            ->where('entity_id', $setting->id)
            ->firstOrFail();

        $this->assertArrayHasKey('store_name', $audit->old_values);
        $this->assertSame(['store_name' => 'Updated POS'], $audit->new_values);
    }
}
