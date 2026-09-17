<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('settings')->where('key', 'stripe_secret_key')->delete();
    }

    public function down(): void
    {
        DB::table('settings')->insert([
            'group' => 'payment',
            'key' => 'stripe_secret_key',
            'value' => '',
            'type' => 'string',
            'is_public' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
};
