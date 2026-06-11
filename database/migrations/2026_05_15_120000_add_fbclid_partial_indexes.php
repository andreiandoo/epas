<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Partial indexes that make the ROAS attribution query usable on
 * multi-month windows. Without them, the Filament page times out at
 * Cloudflare's 100s limit because the whereExists subqueries fall back
 * to seq scans on huge tables.
 *
 * Idempotent. Postgres-specific (CREATE INDEX IF NOT EXISTS WHERE …).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return; // partial indexes WHERE clauses are Postgres
        }

        if (Schema::hasTable('core_customer_events')) {
            DB::statement('CREATE INDEX IF NOT EXISTS core_customer_events_order_id_fbclid_idx
                ON core_customer_events (order_id) WHERE fbclid IS NOT NULL');
        }

        if (Schema::hasTable('core_customers')) {
            DB::statement('CREATE INDEX IF NOT EXISTS core_customers_email_fbclid_idx
                ON core_customers (email) WHERE last_fbclid IS NOT NULL');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') return;
        DB::statement('DROP INDEX IF EXISTS core_customer_events_order_id_fbclid_idx');
        DB::statement('DROP INDEX IF EXISTS core_customers_email_fbclid_idx');
    }
};
