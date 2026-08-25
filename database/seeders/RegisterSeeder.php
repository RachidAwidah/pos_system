<?php

namespace Database\Seeders;

use App\Models\Register;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;

class RegisterSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $warehouse = Warehouse::query()->where('code', 'MAIN')->firstOrFail();

        Register::query()->updateOrCreate(
            ['code' => 'MAIN-REG-01'],
            [
                'warehouse_id' => $warehouse->id,
                'name' => 'Main Register',
                'is_active' => true,
            ],
        );
    }
}
