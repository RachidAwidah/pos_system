<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_methods', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('code', 50)->unique();
            $table->string('category')->index();
            $table->boolean('requires_reference')->default(false);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        DB::statement("ALTER TABLE payment_methods ADD CONSTRAINT chk_payment_methods_category CHECK (category IN ('cash', 'card', 'bank'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_methods');
    }
};
