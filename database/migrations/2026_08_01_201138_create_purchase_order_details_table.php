<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
         Schema::create('purchase_order_details', function (Blueprint $table) {
            $table->uuid("id")->primary();
            $table->foreignUuid('purchase_order_id')->references('id')->on('purchase_orders')->onDelete('cascade');
            $table->foreignUuid('product_id')->constrained('products')->restrictOnDelete();
            $table->decimal('quantity', 15, 3)->unsigned();
            $table->decimal('cost_price', 10, 2)->unsigned();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_order_details');
    }
};
