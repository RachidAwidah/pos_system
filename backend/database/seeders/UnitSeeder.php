<?php

namespace Database\Seeders;

use App\Models\Unit;
use Illuminate\Database\Seeder;

class UnitSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Unit::query()->firstOrCreate(
            ['symbol' => 'pc'],
            ['name' => 'Piece', 'decimal_places' => 0],
        );
    }
}
