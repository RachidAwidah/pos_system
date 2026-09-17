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
        Schema::create('payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('order_id')->constrained('orders')->restrictOnDelete();
            $table->foreignUuid('sales_return_id')->nullable()->constrained('sales_returns')->restrictOnDelete();
            $table->foreignUuid('shift_id')->constrained('shifts')->restrictOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignUuid('payment_method_id')->constrained('payment_methods')->restrictOnDelete();
            $table->string('type')->default('payment')->index();
            $table->string('status')->default('completed')->index();
            $table->decimal('amount', 15, 2);
            $table->decimal('amount_tendered', 15, 2)->nullable();
            $table->decimal('change_amount', 15, 2)->default(0);
            $table->string('reference_number')->nullable();
            $table->timestamp('paid_at')->useCurrent()->index();
            $table->timestamps();

            $table->index(['shift_id', 'paid_at']);
        });

        DB::statement("ALTER TABLE payments ADD CONSTRAINT chk_payments_type CHECK (type IN ('payment', 'refund'))");
        DB::statement("ALTER TABLE payments ADD CONSTRAINT chk_payments_status CHECK (status IN ('completed', 'voided'))");
        DB::statement('ALTER TABLE payments ADD CONSTRAINT chk_payments_amounts CHECK (amount > 0 AND (amount_tendered IS NULL OR amount_tendered >= amount) AND change_amount >= 0)');
        DB::statement("ALTER TABLE payments ADD CONSTRAINT chk_payments_return_link CHECK ((type = 'payment' AND sales_return_id IS NULL) OR (type = 'refund' AND sales_return_id IS NOT NULL))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
