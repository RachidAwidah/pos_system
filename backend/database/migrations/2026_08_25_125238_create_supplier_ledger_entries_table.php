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
        Schema::create('supplier_ledger_entries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('supplier_id')->constrained('suppliers')->restrictOnDelete();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('purchase_order_id')->nullable()->constrained('purchase_orders')->restrictOnDelete();
            $table->foreignUuid('goods_receipt_id')->nullable()->constrained('goods_receipts')->restrictOnDelete();
            $table->foreignUuid('supplier_payment_id')->nullable()->constrained('supplier_payments')->restrictOnDelete();
            $table->string('entry_type')->index();
            $table->decimal('amount_delta', 15, 2);
            $table->decimal('balance_before', 15, 2);
            $table->decimal('balance_after', 15, 2);
            $table->string('description')->nullable();
            $table->timestamp('occurred_at')->useCurrent()->index();
            $table->timestamps();

            $table->index(['supplier_id', 'occurred_at']);
            $table->unique(['goods_receipt_id', 'entry_type']);
            $table->unique('supplier_payment_id');
        });

        DB::statement("ALTER TABLE supplier_ledger_entries ADD CONSTRAINT chk_supplier_ledger_entries_type CHECK (entry_type IN ('purchase', 'supplier_payment', 'supplier_return', 'adjustment'))");
        DB::statement('ALTER TABLE supplier_ledger_entries ADD CONSTRAINT chk_supplier_ledger_entries_balance CHECK (amount_delta <> 0 AND balance_after = balance_before + amount_delta)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('supplier_ledger_entries');
    }
};
