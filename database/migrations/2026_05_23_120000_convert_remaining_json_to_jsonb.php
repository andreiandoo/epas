<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Re-run the json -> jsonb conversion to catch columns added AFTER the
     * 2026_03_23 conversion ran. Specifically:
     *
     *   - venues.google_reviews_cached (added 2026_04_23 as `json`)
     *
     * The previous 2026_03_23 sweep had already migrated everything in place
     * at that point, but any column declared with `$table->json(...)` AFTER
     * that date stays as `json` until the next sweep runs. PostgreSQL's
     * `json` type has no equality operator, so any query that does
     * `SELECT DISTINCT venues.*` (Filament 4's BelongsToMany option loader
     * adds `->distinct()` to the relationship query) errors with
     * `could not identify an equality operator for type json`.
     *
     * Symptom that triggered this migration:
     *   - /marketplace/marketplace-venue-categories/{id}/edit returned 500
     *     because the venues Select->relationship() preload runs
     *     `SELECT DISTINCT venues.*` which includes the still-`json`
     *     `google_reviews_cached` column.
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        $jsonColumns = DB::select("
            SELECT table_name, column_name
            FROM information_schema.columns
            WHERE table_schema = 'public'
              AND data_type = 'json'
            ORDER BY table_name, ordinal_position
        ");

        foreach ($jsonColumns as $col) {
            $table = $col->table_name;
            $column = $col->column_name;

            try {
                DB::statement("ALTER TABLE \"{$table}\" ALTER COLUMN \"{$column}\" TYPE jsonb USING \"{$column}\"::jsonb");
            } catch (\Throwable $e) {
                // If the conversion fails for a specific column (corrupted value, FK constraint, etc.)
                // log and skip so the migration keeps going. The original 500 only blocks the venue
                // categories edit page — we don't want to make migrations un-runnable here.
                logger()->warning('json→jsonb conversion failed', [
                    'table' => $table,
                    'column' => $column,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    public function down(): void
    {
        // Intentionally not reversed — converting jsonb back to json removes
        // useful Postgres functionality (equality, indexing) and gains nothing.
    }
};
