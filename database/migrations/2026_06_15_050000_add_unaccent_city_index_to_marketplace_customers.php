<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Expression index on LOWER(unaccent(city)) so the resident-cities
 * filter's "Bucuresti / BUCUREȘTI / București all match" comparison
 * can hit an index instead of seq-scanning 96k+ customer rows.
 *
 * Without this index, every Filament admin recipient-count rebuild on
 * a newsletter form with target_resident_cities populated did a full
 * scan — multiplied by the 30 php-fpm workers under bot crawl traffic,
 * the box pinned CPU at 100%.
 *
 * Uses CONCURRENTLY so the lock doesn't block live admin traffic on a
 * 96k-row table. Postgres can't run CONCURRENTLY inside a transaction,
 * so the migration disables Laravel's automatic transaction wrapping.
 */
return new class extends Migration {
    public $withinTransaction = false;

    public function up(): void
    {
        if (!Schema::hasTable('marketplace_customers')) {
            return;
        }

        // unaccent() ships as STABLE because it reads from the unaccent
        // dictionary at runtime; Postgres refuses STABLE/VOLATILE
        // functions in expression indexes (the planner can't trust the
        // output is reproducible). The standard fix is a thin SQL
        // wrapper that re-declares IMMUTABLE — safe in practice because
        // the dictionary doesn't change at runtime in our deployment.
        // Same trick used by the events search elsewhere if needed.
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION immutable_unaccent(text)
            RETURNS text AS $$
                SELECT public.unaccent($1)
            $$ LANGUAGE SQL IMMUTABLE PARALLEL SAFE STRICT
        SQL);

        $existing = DB::selectOne(
            "SELECT 1 FROM pg_indexes WHERE indexname = 'mkt_cust_lower_unaccent_city_idx'"
        );
        if ($existing) {
            return;
        }

        DB::statement(<<<'SQL'
            CREATE INDEX CONCURRENTLY mkt_cust_lower_unaccent_city_idx
            ON marketplace_customers (LOWER(immutable_unaccent(COALESCE(city, ''))))
            WHERE city IS NOT NULL AND city <> ''
        SQL);
    }

    public function down(): void
    {
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS mkt_cust_lower_unaccent_city_idx');
        DB::statement('DROP FUNCTION IF EXISTS immutable_unaccent(text)');
    }
};
