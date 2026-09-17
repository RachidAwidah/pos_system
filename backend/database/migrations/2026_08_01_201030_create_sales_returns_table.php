<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_returns', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('return_number')->unique();
            $table->foreignUuid('order_id')->constrained('orders')->restrictOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignUuid('shift_id')->constrained('shifts')->restrictOnDelete();
            $table->foreignUuid('warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->string('status')->default('draft')->index();
            $table->decimal('subtotal_amount', 15, 2)->default(0);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('refund_amount', 15, 2)->default(0);
            $table->string('reason');
            $table->timestamp('returned_at')->nullable()->index();
            $table->timestamps();
        });

        DB::statement("ALTER TABLE sales_returns ADD CONSTRAINT chk_sales_returns_status CHECK (status IN ('draft', 'completed', 'cancelled'))");
        DB::statement('ALTER TABLE sales_returns ADD CONSTRAINT chk_sales_returns_amounts CHECK (subtotal_amount >= 0 AND tax_amount >= 0 AND refund_amount >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_returns');
    }
};
