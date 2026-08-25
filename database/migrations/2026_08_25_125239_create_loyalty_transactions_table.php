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
        Schema::create('loyalty_transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('customer_id')->constrained('customers')->restrictOnDelete();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('order_id')->nullable()->constrained('orders')->restrictOnDelete();
            $table->foreignUuid('sales_return_id')->nullable()->constrained('sales_returns')->restrictOnDelete();
            $table->string('transaction_type')->index();
            $table->integer('points_delta');
            $table->unsignedInteger('balance_before');
            $table->unsignedInteger('balance_after');
            $table->string('reason')->nullable();
            $table->timestamp('occurred_at')->useCurrent()->index();
            $table->timestamps();

            $table->index(['customer_id', 'occurred_at']);
            $table->unique(['sales_return_id', 'transaction_type']);
        });

        DB::statement("ALTER TABLE loyalty_transactions ADD CONSTRAINT chk_loyalty_transactions_type CHECK (transaction_type IN ('earned', 'redeemed', 'reversed', 'adjustment'))");
        DB::statement('ALTER TABLE loyalty_transactions ADD CONSTRAINT chk_loyalty_transactions_balance CHECK (points_delta <> 0 AND balance_after = balance_before + points_delta)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loyalty_transactions');
    }
};
