<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Database-level guarantee that no two 'valid' or 'used' tickets can
     * carry the same seat_uid for the same event. This is the last line of
     * defense after the application-side fixes — if every other guard fails,
     * Postgres rejects the second INSERT and the customer hits a clean error
     * instead of double-booking silently.
     *
     * The seat_uid was previously kept only inside `tickets.meta` JSON
     * (see CheckoutController.php:826-868), which a unique constraint can't
     * cover. We add a denormalised column and backfill it from existing
     * meta JSON. The unique index is PARTIAL — only enforced when the
     * ticket is in a live status AND has a seat assigned (general admission
     * tickets have seat_uid IS NULL and are unaffected).
     *
     * The model write path (CheckoutController) needs to set the new column
     * alongside the meta entry; that companion change ships in a separate
     * commit.
     */
    public function up(): void
    {
        if (!Schema::hasColumn('tickets', 'seat_uid')) {
            Schema::table('tickets', function (Blueprint $table) {
                $table->string('seat_uid', 64)->nullable()->after('seat_label');
                $table->index('seat_uid', 'tickets_seat_uid_idx');
            });
        }

        // Backfill from existing meta JSON. PostgreSQL-only — guarded so a
        // SQLite local dev environment doesn't fail the migration. Skips
        // rows where meta has no seat_uid (general admission).
        if (DB::getDriverName() === 'pgsql') {
            DB::statement(<<<SQL
                UPDATE tickets
                SET seat_uid = (meta::jsonb ->> 'seat_uid')
                WHERE seat_uid IS NULL
                  AND meta IS NOT NULL
                  AND (meta::jsonb ->> 'seat_uid') IS NOT NULL
                  AND (meta::jsonb ->> 'seat_uid') <> ''
            SQL);

            // Pre-check: detect existing duplicates BEFORE creating the
            // unique index. The index creation would otherwise fail with
            // a confusing pgsql error mid-migration. If duplicates exist
            // we LOG a warning + SKIP the CREATE UNIQUE INDEX step but
            // the migration is still marked as run. The column + backfill
            // succeed regardless; the application-side cleanup fix and
            // hold extension don't depend on the unique index. To install
            // the index later (after resolving the duplicates), run:
            //   php artisan tickets:install-seat-unique-index
            $duplicates = DB::select(<<<SQL
                SELECT event_id, seat_uid, COUNT(*) AS dup_count,
                       array_agg(id ORDER BY id) AS ticket_ids
                FROM tickets
                WHERE seat_uid IS NOT NULL
                  AND status IN ('valid', 'used')
                GROUP BY event_id, seat_uid
                HAVING COUNT(*) > 1
                LIMIT 50
            SQL);

            if (!empty($duplicates)) {
                $items = [];
                foreach ($duplicates as $d) {
                    $items[] = sprintf(
                        'event=%d seat=%s tickets=%s',
                        $d->event_id,
                        $d->seat_uid,
                        is_string($d->ticket_ids) ? $d->ticket_ids : json_encode($d->ticket_ids)
                    );
                }
                \Illuminate\Support\Facades\Log::warning(
                    '[SeatUidMigration] Skipping CREATE UNIQUE INDEX — existing duplicates detected. '
                    . 'Run `php artisan tickets:install-seat-unique-index` after resolving each conflict. '
                    . 'Conflicts: ' . implode(' | ', $items)
                );
                return; // skip index creation, migration succeeds
            }

            // Partial unique index — only enforces uniqueness when the
            // ticket is in a live status (valid / used) AND actually has
            // a seat. Cancelled, refunded, pending tickets, and general
            // admission tickets can coexist with anything.
            //
            // Filtered indexes are pgsql-only (MySQL doesn't support WHERE
            // on a UNIQUE), so this entire block is pgsql-only. SQLite dev
            // environments rely on application-side checks; production is
            // pgsql so this is the real guard.
            DB::statement(<<<SQL
                CREATE UNIQUE INDEX IF NOT EXISTS tickets_seat_uid_event_unique
                ON tickets (event_id, seat_uid)
                WHERE seat_uid IS NOT NULL
                  AND status IN ('valid', 'used')
            SQL);
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS tickets_seat_uid_event_unique');
        }
        if (Schema::hasColumn('tickets', 'seat_uid')) {
            Schema::table('tickets', function (Blueprint $table) {
                $table->dropIndex('tickets_seat_uid_idx');
                $table->dropColumn('seat_uid');
            });
        }
    }
};
