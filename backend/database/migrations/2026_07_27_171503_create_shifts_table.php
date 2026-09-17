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
        Schema::create('shifts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('register_id')->constrained('registers')->restrictOnDelete();
            $table->foreignUuid('opened_by_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignUuid('closed_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->enum('status', ['open', 'closed'])->default('open')->index();
            $table->timestamp('opened_at')->index();
            $table->timestamp('closed_at')->nullable()->index();
            $table->decimal('opening_cash', 15, 2)->default(0);
            $table->decimal('closing_cash', 15, 2)->nullable();
            $table->decimal('expected_cash', 15, 2)->nullable();
            $table->decimal('difference_amount', 15, 2)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['register_id', 'status']);
        });

        DB::statement('ALTER TABLE shifts ADD CONSTRAINT chk_shifts_cash_values CHECK (opening_cash >= 0 AND (closing_cash IS NULL OR closing_cash >= 0) AND (expected_cash IS NULL OR expected_cash >= 0))');
        DB::statement("ALTER TABLE shifts ADD CONSTRAINT chk_shifts_closure CHECK ((status = 'open' AND closed_at IS NULL AND closed_by_user_id IS NULL AND closing_cash IS NULL AND expected_cash IS NULL AND difference_amount IS NULL) OR (status = 'closed' AND closed_at IS NOT NULL AND closed_by_user_id IS NOT NULL AND closing_cash IS NOT NULL AND expected_cash IS NOT NULL AND difference_amount IS NOT NULL))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shifts');
    }
};
