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
        Schema::create('filter_conditions', function (Blueprint $table) {
            $table->uuid("id")->primary();
            $table->foreignUuid('filter_id')->references('id')->on('saved_filters')->onDelete('cascade');
            $table->string('column_name');
            $table->string('operator');
            $table->string('filter_value');
            $table->string('logical_operator')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('filter_conditions');
    }
};
