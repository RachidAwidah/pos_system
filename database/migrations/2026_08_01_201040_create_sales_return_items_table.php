<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_return_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('sales_return_id')->constrained('sales_returns')->cascadeOnDelete();
            $table->foreignUuid('order_item_id')->constrained('order_items')->restrictOnDelete();
            $table->foreignUuid('product_id')->constrained('products')->restrictOnDelete();
            $table->decimal('quantity', 15, 3);
            $table->decimal('subtotal_amount', 15, 2);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('refund_amount', 15, 2);
            $table->boolean('restock')->default(true);
            $table->string('reason')->nullable();
            $table->timestamps();

            $table->unique(['sales_return_id', 'order_item_id']);
        });

        DB::statement('ALTER TABLE sales_return_items ADD CONSTRAINT chk_sales_return_items_values CHECK (quantity > 0 AND subtotal_amount >= 0 AND tax_amount >= 0 AND refund_amount >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_return_items');
    }
};
