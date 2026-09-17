<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE orders ADD CONSTRAINT chk_orders_amounts CHECK (subtotal_amount >= 0 AND discount_amount >= 0 AND tax_amount >= 0 AND final_amount >= 0 AND paid_amount >= 0 AND due_amount >= 0 AND refunded_amount >= 0 AND discount_amount <= subtotal_amount AND refunded_amount <= final_amount AND paid_amount + refunded_amount <= final_amount AND due_amount = final_amount - refunded_amount - paid_amount)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE orders DROP CHECK chk_orders_amounts');
    }
};
