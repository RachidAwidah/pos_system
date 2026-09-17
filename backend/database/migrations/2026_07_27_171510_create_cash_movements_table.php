<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_movements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('shift_id')->constrained('shifts')->restrictOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->restrictOnDelete();
            $table->string('type')->index();
            $table->decimal('amount', 15, 2);
            $table->string('reason');
            $table->timestamp('occurred_at')->index();
            $table->timestamps();

            $table->index(['shift_id', 'occurred_at']);
        });

        DB::statement("ALTER TABLE cash_movements ADD CONSTRAINT chk_cash_movements_type CHECK (type IN ('cash_in', 'cash_out'))");
        DB::statement('ALTER TABLE cash_movements ADD CONSTRAINT chk_cash_movements_amount CHECK (amount > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_movements');
    }
};
