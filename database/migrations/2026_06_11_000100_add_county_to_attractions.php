<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Adds a county (județ) reference to attractions so the admin table can show it
 * and POIs without a marketplace_city still carry a county (backfilled from the
 * import CSV's `judet` column or from the linked city). Postgres-safe +
 * idempotent. No DB-level FK — the Eloquent relation handles resolution and we
 * avoid a hard constraint on a backfilled column.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE attractions ADD COLUMN IF NOT EXISTS marketplace_county_id BIGINT NULL');
        DB::statement('CREATE INDEX IF NOT EXISTS attractions_county_idx ON attractions (marketplace_county_id)');
    }

    public function down(): void
    {
        try { DB::statement('DROP INDEX IF EXISTS attractions_county_idx'); } catch (\Throwable $e) {}
        try { DB::statement('ALTER TABLE attractions DROP COLUMN IF EXISTS marketplace_county_id'); } catch (\Throwable $e) {}
    }
};
