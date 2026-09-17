<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->unsignedBigInteger('sequence_number')->nullable()->after('id');
        });

        // Populate existing records with sequential numbers
        $orders = DB::table('purchase_orders')->orderBy('created_at')->get(['id']);
        $sequence = 1;
        foreach ($orders as $order) {
            DB::table('purchase_orders')
                ->where('id', $order->id)
                ->update(['sequence_number' => $sequence++]);
        }

        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->unsignedBigInteger('sequence_number')->nullable(false)->change();
            $table->unique('sequence_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropUnique('purchase_orders_sequence_number_unique');
            $table->dropColumn('sequence_number');
        });
    }
};
