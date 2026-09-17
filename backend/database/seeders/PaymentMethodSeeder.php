<?php

namespace Database\Seeders;

use App\Models\PaymentMethod;
use Illuminate\Database\Seeder;

class PaymentMethodSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $methods = [
            ['name' => 'Cash', 'code' => 'CASH', 'category' => 'cash', 'requires_reference' => false],
            ['name' => 'Card', 'code' => 'CARD', 'category' => 'card', 'requires_reference' => true],
            ['name' => 'Bank Transfer', 'code' => 'BANK', 'category' => 'bank', 'requires_reference' => true],
        ];

        foreach ($methods as $method) {
            PaymentMethod::query()->updateOrCreate(['code' => $method['code']], $method + ['is_active' => true]);
        }
    }
}
