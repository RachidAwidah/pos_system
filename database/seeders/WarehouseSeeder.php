<?php

namespace Database\Seeders;

use App\Models\Warehouse;
use Illuminate\Database\Seeder;

class WarehouseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Warehouse::query()->updateOrCreate(
            ['code' => 'MAIN'],
            [
                'name' => 'المستودع الرئيسي',
                'address' => null,
                'is_default' => true,
                'is_active' => true,
            ],
        );
    }
}
