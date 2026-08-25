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
        Schema::create('customer_ledger_entries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('customer_id')->constrained('customers')->restrictOnDelete();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('order_id')->nullable()->constrained('orders')->restrictOnDelete();
            $table->foreignUuid('sales_return_id')->nullable()->constrained('sales_returns')->restrictOnDelete();
            $table->foreignUuid('customer_payment_id')->nullable()->constrained('customer_payments')->restrictOnDelete();
            $table->string('entry_type')->index();
            $table->decimal('amount_delta', 15, 2);
            $table->decimal('balance_before', 15, 2);
            $table->decimal('balance_after', 15, 2);
            $table->string('description')->nullable();
            $table->timestamp('occurred_at')->useCurrent()->index();
            $table->timestamps();

            $table->index(['customer_id', 'occurred_at']);
            $table->unique(['sales_return_id', 'entry_type']);
            $table->unique('customer_payment_id');
        });

        DB::statement("ALTER TABLE customer_ledger_entries ADD CONSTRAINT chk_customer_ledger_entries_type CHECK (entry_type IN ('sale', 'customer_payment', 'sales_return', 'adjustment'))");
        DB::statement('ALTER TABLE customer_ledger_entries ADD CONSTRAINT chk_customer_ledger_entries_balance CHECK (amount_delta <> 0 AND balance_after = balance_before + amount_delta)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_ledger_entries');
    }
};
