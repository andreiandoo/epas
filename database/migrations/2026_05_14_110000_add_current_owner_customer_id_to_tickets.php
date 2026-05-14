<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Adds tickets.current_owner_customer_id to support customer-to-customer
 * ticket transfers without rewriting the parent order.
 *
 * Why a separate column instead of repurposing orders.marketplace_customer_id:
 *   The order is a commercial transaction (payment, invoice) that must
 *   stay attached to the original buyer for fiscal audit. Only ticket
 *   ownership moves on transfer; the order's customer reference does not.
 *
 * Backfill: existing tickets default to the parent order's
 * marketplace_customer_id so /cont/bilete listings keep working after
 * the scope change (whereHas('order'...) → where('current_owner_...')).
 *
 * FK has ON DELETE SET NULL — if a customer record is deleted, tickets
 * just become "unowned" rather than blocking the cascade.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('tickets')) {
            return;
        }

        if (!Schema::hasColumn('tickets', 'current_owner_customer_id')) {
            Schema::table('tickets', function (Blueprint $table) {
                $table->unsignedBigInteger('current_owner_customer_id')
                    ->nullable()
                    ->after('marketplace_customer_id');

                // Skip FK on SQLite — Laravel still tries to recreate the
                // whole table when adding constraints and we don't need
                // referential strictness for the dev environment.
                if (DB::connection()->getDriverName() !== 'sqlite') {
                    $table->foreign('current_owner_customer_id', 'tickets_current_owner_customer_id_fk')
                        ->references('id')->on('marketplace_customers')
                        ->nullOnDelete();
                }

                $table->index('current_owner_customer_id', 'tickets_current_owner_customer_id_idx');
            });
        }

        // Backfill: copy the order's marketplace_customer_id onto the
        // ticket. Single SQL statement to avoid pulling models for what
        // can be tens of thousands of rows. Only fills rows where the
        // column is still null (idempotent re-run safe).
        DB::statement(
            'UPDATE tickets t '
            . 'SET current_owner_customer_id = ('
            . '    SELECT o.marketplace_customer_id FROM orders o WHERE o.id = t.order_id'
            . ') '
            . 'WHERE t.current_owner_customer_id IS NULL '
            . '  AND t.order_id IS NOT NULL'
        );
    }

    public function down(): void
    {
        if (!Schema::hasTable('tickets')) {
            return;
        }

        if (Schema::hasColumn('tickets', 'current_owner_customer_id')) {
            Schema::table('tickets', function (Blueprint $table) {
                if (DB::connection()->getDriverName() !== 'sqlite') {
                    $table->dropForeign('tickets_current_owner_customer_id_fk');
                }
                $table->dropIndex('tickets_current_owner_customer_id_idx');
                $table->dropColumn('current_owner_customer_id');
            });
        }
    }
};
