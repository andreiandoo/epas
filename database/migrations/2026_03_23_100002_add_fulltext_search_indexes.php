<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        // Create immutable unaccent function for use in indexes
        DB::statement("
            CREATE OR REPLACE FUNCTION immutable_unaccent(text)
            RETURNS text AS \$\$
                SELECT unaccent(\$1);
            \$\$ LANGUAGE sql IMMUTABLE PARALLEL SAFE;
        ");

        // Events: full-text search on title (all locales)
        DB::statement("
            CREATE INDEX IF NOT EXISTS idx_events_title_fts ON events
            USING GIN (to_tsvector('simple', immutable_unaccent(coalesce(title->>'en', '') || ' ' || coalesce(title->>'ro', ''))))
        ");

        // Artists: full-text search on name
        DB::statement("
            CREATE INDEX IF NOT EXISTS idx_artists_name_fts ON artists
            USING GIN (to_tsvector('simple', immutable_unaccent(coalesce(name->>'en', '') || ' ' || coalesce(name->>'ro', ''))))
        ");

        // Venues: full-text search on name + city
        DB::statement("
            CREATE INDEX IF NOT EXISTS idx_venues_name_fts ON venues
            USING GIN (to_tsvector('simple', immutable_unaccent(coalesce(name::text, '') || ' ' || coalesce(city, ''))))
        ");

        // Orders: full-text on customer email
        DB::statement("
            CREATE INDEX IF NOT EXISTS idx_orders_email_fts ON orders
            USING GIN (to_tsvector('simple', coalesce(customer_email, '')))
        ");

        // Marketplace customers: full-text on name + email
        DB::statement("
            CREATE INDEX IF NOT EXISTS idx_mp_customers_fts ON marketplace_customers
            USING GIN (to_tsvector('simple', immutable_unaccent(coalesce(first_name, '') || ' ' || coalesce(last_name, '') || ' ' || coalesce(email, ''))))
        ");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('DROP INDEX IF EXISTS idx_events_title_fts');
        DB::statement('DROP INDEX IF EXISTS idx_artists_name_fts');
        DB::statement('DROP INDEX IF EXISTS idx_venues_name_fts');
        DB::statement('DROP INDEX IF EXISTS idx_orders_email_fts');
        DB::statement('DROP INDEX IF EXISTS idx_mp_customers_fts');
        DB::statement('DROP FUNCTION IF EXISTS immutable_unaccent(text)');
    }
};
