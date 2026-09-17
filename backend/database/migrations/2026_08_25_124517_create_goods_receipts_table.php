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
        Schema::create('goods_receipts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('receipt_number')->unique();
            $table->foreignUuid('purchase_order_id')->constrained('purchase_orders')->restrictOnDelete();
            $table->foreignUuid('warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->foreignUuid('received_by_user_id')->constrained('users')->restrictOnDelete();
            $table->string('status')->default('completed')->index();
            $table->decimal('subtotal_amount', 15, 2)->default(0);
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->string('supplier_reference')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('received_at')->useCurrent()->index();
            $table->timestamps();

            $table->index(['purchase_order_id', 'received_at']);
        });

        DB::statement("ALTER TABLE goods_receipts ADD CONSTRAINT chk_goods_receipts_status CHECK (status IN ('completed', 'voided'))");
        DB::statement('ALTER TABLE goods_receipts ADD CONSTRAINT chk_goods_receipts_amounts CHECK (subtotal_amount >= 0 AND discount_amount >= 0 AND tax_amount >= 0 AND total_amount >= 0 AND discount_amount <= subtotal_amount)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('goods_receipts');
    }
};
