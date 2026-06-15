<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Replaces the single-column LOWER(immutable_unaccent(city)) index with
 * a composite (marketplace_client_id, LOWER(immutable_unaccent(city)))
 * one. The resident-cities filter always scopes by marketplace_client_id
 * first, and the planner kept choosing the existing
 * marketplace_client_id_status_index — pulling 98k rows then filtering
 * 97.7k of them in memory (1.5s execution). With the composite, the
 * planner can satisfy both predicates from a single bitmap.
 */
return new class extends Migration {
    public $withinTransaction = false;

    public function up(): void
    {
        if (!Schema::hasTable('marketplace_customers')) {
            return;
        }

        // Drop the single-column predecessor — superseded by the composite.
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS mkt_cust_lower_unaccent_city_idx');

        $existing = DB::selectOne(
            "SELECT 1 FROM pg_indexes WHERE indexname = 'mkt_cust_client_unaccent_city_idx'"
        );
        if ($existing) {
            return;
        }

        DB::statement(<<<'SQL'
            CREATE INDEX CONCURRENTLY mkt_cust_client_unaccent_city_idx
            ON marketplace_customers (
                marketplace_client_id,
                LOWER(immutable_unaccent(COALESCE(city, '')))
            )
            WHERE city IS NOT NULL AND city <> ''
        SQL);
    }

    public function down(): void
    {
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS mkt_cust_client_unaccent_city_idx');
    }
};
