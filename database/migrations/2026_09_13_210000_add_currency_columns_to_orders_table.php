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
        Schema::table('orders', function (Blueprint $table) {
            $table->string('display_currency', 3)->default('USD')->after('cancellation_reason');
            $table->decimal('exchange_rate_used', 15, 6)->nullable()->after('display_currency');
            $table->string('rate_provider', 50)->nullable()->after('exchange_rate_used');
        });

        // Add CHECK constraint for display_currency
        DB::statement("ALTER TABLE orders ADD CONSTRAINT orders_display_currency_check CHECK (display_currency IN ('USD', 'SYP', 'TRY'))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['display_currency', 'exchange_rate_used', 'rate_provider']);
        });
    }
};
