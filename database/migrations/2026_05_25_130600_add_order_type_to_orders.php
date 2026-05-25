<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Activities module — A1 / step 7: tag orders with their purchase type.
 *
 * Adds `orders.order_type` to distinguish:
 *   - 'event_ticket'    — checkout from an event (default — every existing row)
 *   - 'activity_booking' — checkout from an activity (slot-based)
 *
 * Non-breaking by design:
 *   - Column is added with default 'event_ticket'. Postgres backfills the
 *     value into every existing row at ALTER time, so no existing logic
 *     that branches on this column (none currently) gets surprised by
 *     NULLs.
 *   - All existing report / payout / invoice / commission code that
 *     SELECTs from `orders` ignores order_type and behaves identically
 *     for old rows. New activity flow filters explicitly by
 *     order_type = 'activity_booking' when it lands in A5.
 *   - No CHECK constraint on values (would require dropping when adding
 *     a future type like 'gift_card'). Application enforces the enum.
 *
 * Performed in three deliberate steps so the production migration can
 * be observed and rolled back if anything goes wrong:
 *   1. add the column (NULL default)
 *   2. backfill 'event_ticket' on existing rows
 *   3. set NOT NULL + DEFAULT 'event_ticket' for new rows
 *
 * On non-pgsql we use the simpler ->default(...)->after(...) form since
 * the multi-step pgsql dance isn't needed for sqlite/mysql.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('orders')) {
            return;
        }

        if (Schema::hasColumn('orders', 'order_type')) {
            return;
        }

        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            // Step 1 — add nullable so we never block on a single huge UPDATE.
            DB::statement('ALTER TABLE "orders" ADD COLUMN "order_type" varchar(32) NULL');

            // Step 2 — backfill in one statement (orders table is bounded; this is fast).
            DB::statement("UPDATE \"orders\" SET \"order_type\" = 'event_ticket' WHERE \"order_type\" IS NULL");

            // Step 3 — make NOT NULL and set the future default.
            DB::statement('ALTER TABLE "orders" ALTER COLUMN "order_type" SET NOT NULL');
            DB::statement("ALTER TABLE \"orders\" ALTER COLUMN \"order_type\" SET DEFAULT 'event_ticket'");
        } else {
            // sqlite + mysql — a single ALTER with DEFAULT does the backfill for us.
            Schema::table('orders', function (Blueprint $table) {
                $table->string('order_type', 32)
                    ->default('event_ticket')
                    ->after('id');
            });
        }

        // Index for the activity-side queries (small table; cheap index).
        // Wrapped in try/catch because index naming differs across drivers.
        try {
            Schema::table('orders', function (Blueprint $table) {
                $table->index('order_type', 'orders_order_type_idx');
            });
        } catch (\Throwable $e) {
            // ignore — index may already exist from a partial prior run
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('orders') || ! Schema::hasColumn('orders', 'order_type')) {
            return;
        }

        Schema::table('orders', function (Blueprint $table) {
            try {
                $table->dropIndex('orders_order_type_idx');
            } catch (\Throwable $e) {
                // ignore
            }
            $table->dropColumn('order_type');
        });
    }
};
