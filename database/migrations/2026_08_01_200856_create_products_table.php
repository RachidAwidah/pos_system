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
        Schema::create('products', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('product_name');
            $table->string('sku')->unique();
            $table->string('barcode')->nullable()->unique();
            $table->foreignUuid('unit_id')->constrained('units')->restrictOnDelete();
            $table->string('type')->default('stock')->index();
            $table->decimal('cost_price', 15, 2)->default(0);
            $table->decimal('price', 15, 2)->default(0);
            $table->decimal('quantity', 15, 3)->default(0);
            $table->decimal('reorder_level', 15, 3)->default(0);
            $table->text('description')->nullable();
            $table->string('image')->nullable();
            $table->foreignUuid('tax_id')->nullable()->constrained('taxes')->nullOnDelete();
            $table->foreignUuid('category_id')->constrained('categories')->restrictOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        DB::statement("ALTER TABLE products ADD CONSTRAINT chk_products_type CHECK (type IN ('stock', 'non_stock', 'service'))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
