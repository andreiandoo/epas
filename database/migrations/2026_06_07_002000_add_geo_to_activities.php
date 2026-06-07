<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * F2 — Nearby. Denormalised geo coordinates on activities so proximity
 * (Haversine) queries don't need a venue JOIN and can be indexed. Backfilled
 * from the related venue's lat/lng. Activities without a venue can have coords
 * set directly in the admin. Postgres-safe + idempotent.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE activities ADD COLUMN IF NOT EXISTS latitude DECIMAL(10,7) NULL');
        DB::statement('ALTER TABLE activities ADD COLUMN IF NOT EXISTS longitude DECIMAL(10,7) NULL');
        DB::statement('CREATE INDEX IF NOT EXISTS activities_geo_idx ON activities (latitude, longitude)');

        // Backfill from venue where the activity has no coords of its own.
        try {
            DB::statement('UPDATE activities a SET latitude = v.lat, longitude = v.lng FROM venues v WHERE a.venue_id = v.id AND a.latitude IS NULL AND v.lat IS NOT NULL AND v.lng IS NOT NULL');
        } catch (\Throwable $e) {
        }
    }

    public function down(): void
    {
        try { DB::statement('DROP INDEX IF EXISTS activities_geo_idx'); } catch (\Throwable $e) {}
        try { DB::statement('ALTER TABLE activities DROP COLUMN IF EXISTS latitude'); } catch (\Throwable $e) {}
        try { DB::statement('ALTER TABLE activities DROP COLUMN IF EXISTS longitude'); } catch (\Throwable $e) {}
    }
};
