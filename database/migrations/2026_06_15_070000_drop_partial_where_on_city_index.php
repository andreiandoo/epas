<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Recreates the composite city index without the partial WHERE clause.
 *
 * The planner refuses to use a partial expression index when the query's
 * predicate doesn't visibly imply the partial condition. Our
 * resident-cities query filters on
 *   LOWER(immutable_unaccent(COALESCE(city, ''))) IN (...)
 * which logically implies city IS NOT NULL, but Postgres can't prove
 * that through the wrapper functions — so it falls back to seq scan
 * (the EXPLAIN on the first composite index version showed
 * Parallel Seq Scan, 563ms).
 *
 * Drop the partial WHERE so the index covers every row. Slightly
 * larger on disk (~30k extra entries on Ambilet where ~22% of customers
 * have a city set) but lets the planner pick it confidently.
 */
return new class extends Migration {
    public $withinTransaction = false;

    public function up(): void
    {
        if (!Schema::hasTable('marketplace_customers')) {
            return;
        }

        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS mkt_cust_client_unaccent_city_idx');

        DB::statement(<<<'SQL'
            CREATE INDEX CONCURRENTLY mkt_cust_client_unaccent_city_idx
            ON marketplace_customers (
                marketplace_client_id,
                LOWER(immutable_unaccent(COALESCE(city, '')))
            )
        SQL);

        // Refresh stats so the planner picks the new index on first hit.
        DB::statement('ANALYZE marketplace_customers');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS mkt_cust_client_unaccent_city_idx');
    }
};
