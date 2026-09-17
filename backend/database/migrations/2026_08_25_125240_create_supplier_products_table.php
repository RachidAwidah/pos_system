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
        Schema::create('supplier_products', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('supplier_id')->constrained('suppliers')->cascadeOnDelete();
            $table->foreignUuid('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('supplier_sku')->nullable();
            $table->decimal('last_cost', 15, 4)->nullable();
            $table->decimal('minimum_order_quantity', 15, 3)->default(1);
            $table->unsignedSmallInteger('lead_time_days')->nullable();
            $table->boolean('is_preferred')->default(false)->index();
            $table->timestamps();

            $table->unique(['supplier_id', 'product_id']);
        });

        DB::statement('ALTER TABLE supplier_products ADD CONSTRAINT chk_supplier_products_values CHECK ((last_cost IS NULL OR last_cost >= 0) AND minimum_order_quantity > 0)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('supplier_products');
    }
};
