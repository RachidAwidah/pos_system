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
        DB::statement(<<<'SQL'
            UPDATE orders AS orders_to_update
            LEFT JOIN (
                SELECT
                    order_id,
                    SUM(CASE WHEN type = 'payment' AND status = 'completed' THEN amount ELSE 0 END) AS direct_payments,
                    SUM(CASE WHEN type = 'refund' AND status = 'completed' THEN amount ELSE 0 END) AS refunds
                FROM payments
                GROUP BY order_id
            ) AS payment_totals ON payment_totals.order_id = orders_to_update.id
            LEFT JOIN (
                SELECT order_id, SUM(amount) AS account_payments
                FROM customer_payments
                WHERE status = 'completed'
                GROUP BY order_id
            ) AS account_totals ON account_totals.order_id = orders_to_update.id
            SET
                orders_to_update.paid_amount = GREATEST(
                    0,
                    COALESCE(payment_totals.direct_payments, 0)
                        + COALESCE(account_totals.account_payments, 0)
                        - COALESCE(payment_totals.refunds, 0)
                ),
                orders_to_update.due_amount = GREATEST(
                    0,
                    orders_to_update.final_amount
                        - orders_to_update.refunded_amount
                        - GREATEST(
                            0,
                            COALESCE(payment_totals.direct_payments, 0)
                                + COALESCE(account_totals.account_payments, 0)
                                - COALESCE(payment_totals.refunds, 0)
                        )
                )
            SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('UPDATE orders SET paid_amount = final_amount - due_amount');
    }
};
