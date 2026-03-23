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

        $indexes = [
            // Events: FTS on title (jsonb column)
            "idx_events_title_fts" => "
                CREATE INDEX IF NOT EXISTS idx_events_title_fts ON events
                USING GIN (to_tsvector('simple', immutable_unaccent(coalesce(title::text, ''))))
            ",
            // Artists: FTS on name (may be varchar or jsonb)
            "idx_artists_name_fts" => "
                CREATE INDEX IF NOT EXISTS idx_artists_name_fts ON artists
                USING GIN (to_tsvector('simple', immutable_unaccent(coalesce(name::text, ''))))
            ",
            // Venues: FTS on name + city
            "idx_venues_name_fts" => "
                CREATE INDEX IF NOT EXISTS idx_venues_name_fts ON venues
                USING GIN (to_tsvector('simple', immutable_unaccent(coalesce(name::text, '') || ' ' || coalesce(city, ''))))
            ",
            // Orders: FTS on customer email
            "idx_orders_email_fts" => "
                CREATE INDEX IF NOT EXISTS idx_orders_email_fts ON orders
                USING GIN (to_tsvector('simple', coalesce(customer_email, '')))
            ",
            // Marketplace customers: FTS on name + email
            "idx_mp_customers_fts" => "
                CREATE INDEX IF NOT EXISTS idx_mp_customers_fts ON marketplace_customers
                USING GIN (to_tsvector('simple', immutable_unaccent(coalesce(first_name, '') || ' ' || coalesce(last_name, '') || ' ' || coalesce(email, ''))))
            ",
        ];

        foreach ($indexes as $name => $sql) {
            try {
                DB::statement($sql);
            } catch (\Exception $e) {
                // Skip if column types don't match - will work after post-sync fixes
                logger()->warning("FTS index {$name} skipped: " . $e->getMessage());
            }
        }
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
