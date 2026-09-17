<?php

namespace Tests\Unit;

use App\Http\Requests\BulkUpdateSettingsRequest;
use App\Http\Requests\UpdateSettingRequest;
use App\Models\Setting;
use App\Services\StripeKeyResolver;
use Illuminate\Routing\Route;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\Factory;
use PHPUnit\Framework\TestCase;

class StripeKeyResolverTest extends TestCase
{
    public function test_malformed_or_missing_settings_fall_back_to_configuration(): void
    {
        foreach ([null, '', 'not-a-key', 'pk_test_example', 'sk_test_REPLACE', [], false] as $stored) {
            $this->assertSame('sk_test_configured', StripeKeyResolver::secret($stored, 'sk_test_configured'));
        }
        $this->assertNull(StripeKeyResolver::secret('invalid', 'sk_test_REPLACE_ME'));
    }

    public function test_valid_stored_keys_keep_priority_and_are_trimmed(): void
    {
        foreach (['sk_test_stored', 'sk_live_stored', 'rk_test_stored', 'rk_live_stored'] as $stored) {
            $this->assertSame($stored, StripeKeyResolver::secret(' '.$stored.' ', 'sk_test_configured'));
        }
    }

    public function test_bulk_settings_reject_invalid_publishable_key_and_allow_empty_fallback(): void
    {
        $factory = new Factory(new Translator(new ArrayLoader, 'ar'));
        $request = new BulkUpdateSettingsRequest;

        foreach (['invalid', 'pk_test_REPLACE', 'other_test_value'] as $value) {
            $validator = $factory->make(['settings' => ['stripe_publishable_key' => $value]], $request->rules(), $request->messages());
            $this->assertTrue($validator->fails());
            $this->assertTrue($validator->errors()->has('settings.stripe_publishable_key'));
        }

        foreach ([null, '', 'pk_test_example', 'pk_live_example'] as $value) {
            $this->assertTrue($factory->make(['settings' => ['stripe_publishable_key' => $value]], $request->rules())->passes());
        }

        $this->assertTrue($factory->make(['settings' => ['store_name' => 'Example']], $request->rules())->passes());
    }

    public function test_single_publishable_key_setting_rejects_invalid_key_without_database(): void
    {
        $factory = new Factory(new Translator(new ArrayLoader, 'ar'));

        $setting = new Setting;
        $setting->setRawAttributes(['key' => 'stripe_publishable_key', 'type' => 'string']);
        $request = UpdateSettingRequest::create('/settings/example', 'PATCH');
        $route = new Route('PATCH', 'settings/{setting}', fn () => null);
        $route->bind($request);
        $route->setParameter('setting', $setting);
        $request->setRouteResolver(fn () => $route);
        $this->assertTrue($factory->make(['value' => 'invalid'], $request->rules())->fails());
        $this->assertTrue($factory->make(['value' => null], $request->rules())->passes());
    }
}
