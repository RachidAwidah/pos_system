<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Reset sequence numbers for existing purchase orders (seeder data)
        // New purchase orders will get sequential numbers starting from 1
        DB::table('purchase_orders')->update(['sequence_number' => null, 'purchase_order_number' => null]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No-op
    }
};
