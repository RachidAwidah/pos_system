<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            ['general', 'store_name', 'My POS Store', 'string', true],
            ['general', 'store_address', '', 'string', true],
            ['general', 'store_phone', '', 'string', true],
            ['general', 'store_email', '', 'string', true],
            ['financial', 'default_currency', 'USD', 'string', true],
            ['financial', 'default_tax_rate', '0', 'float', true],
            ['financial', 'tax_included', 'false', 'boolean', true],
            ['inventory', 'allow_negative_stock', 'false', 'boolean', false],
            ['inventory', 'cost_method', 'average', 'string', false],
            ['printing', 'thermal_printer_width', '80', 'integer', true],
            ['printing', 'invoice_footer_message', 'Thank you for your purchase', 'string', true],
            ['general', 'date_format', 'Y-m-d', 'string', true],
            ['general', 'language', 'ar', 'string', true],
            ['general', 'rtl_enabled', 'true', 'boolean', true],
        ];

        foreach ($settings as [$group, $key, $value, $type, $isPublic]) {
            Setting::query()->updateOrCreate(
                ['key' => $key],
                compact('group', 'value', 'type') + ['is_public' => $isPublic],
            );
        }
    }
}
