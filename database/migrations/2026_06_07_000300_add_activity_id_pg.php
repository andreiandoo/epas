<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * PostgreSQL-correct add of activity_id. The earlier attempts used MySQL-only
 * syntax (`BIGINT UNSIGNED`, `SHOW COLUMNS`, `MODIFY`) which fails on this
 * Postgres database — that's why the column was never actually added even
 * though the migrations recorded as DONE. Postgres supports IF NOT EXISTS on
 * ADD COLUMN / CREATE INDEX, so these statements are clean and idempotent.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Nullable bigint (Postgres has no UNSIGNED — plain bigint is correct).
        DB::statement('ALTER TABLE marketplace_customer_reviews ADD COLUMN IF NOT EXISTS activity_id BIGINT NULL');

        DB::statement('CREATE INDEX IF NOT EXISTS mcr_activity_status_idx ON marketplace_customer_reviews (activity_id, status)');

        // One review per (customer, activity). Postgres treats NULLs as
        // distinct in a unique index, so event-only rows (activity_id IS NULL)
        // never collide with each other.
        DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS mcr_customer_activity_unique ON marketplace_customer_reviews (marketplace_customer_id, activity_id)');

        // Allow activity-only reviews to leave marketplace_event_id empty.
        try {
            DB::statement('ALTER TABLE marketplace_customer_reviews ALTER COLUMN marketplace_event_id DROP NOT NULL');
        } catch (\Throwable $e) {
            // already nullable
        }
    }

    public function down(): void
    {
        try { DB::statement('DROP INDEX IF EXISTS mcr_customer_activity_unique'); } catch (\Throwable $e) {}
        try { DB::statement('DROP INDEX IF EXISTS mcr_activity_status_idx'); } catch (\Throwable $e) {}
        try { DB::statement('ALTER TABLE marketplace_customer_reviews DROP COLUMN IF EXISTS activity_id'); } catch (\Throwable $e) {}
    }
};
