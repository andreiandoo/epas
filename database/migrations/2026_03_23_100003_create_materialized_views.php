<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('
            CREATE MATERIALIZED VIEW IF NOT EXISTS mv_event_stats AS
            SELECT
                e.id as event_id,
                e.tenant_id,
                e.marketplace_client_id,
                e.event_date,
                e.is_cancelled,
                e.is_sold_out,
                COALESCE(SUM(tt.quota_sold), 0) as tickets_sold,
                COALESCE(SUM(tt.quota_total), 0) as total_capacity,
                COALESCE(SUM(tt.quota_sold * tt.price_cents), 0) as revenue_cents,
                COUNT(DISTINCT tt.id) as ticket_type_count,
                COALESCE(SUM(CASE WHEN tt.quota_total > 0 THEN tt.quota_sold * 100.0 / tt.quota_total ELSE 0 END) / NULLIF(COUNT(DISTINCT tt.id), 0), 0) as avg_sell_through
            FROM events e
            LEFT JOIN ticket_types tt ON tt.event_id = e.id
            GROUP BY e.id, e.tenant_id, e.marketplace_client_id, e.event_date, e.is_cancelled, e.is_sold_out
        ');

        DB::statement('CREATE UNIQUE INDEX ON mv_event_stats (event_id)');
        DB::statement('CREATE INDEX ON mv_event_stats (tenant_id)');
        DB::statement('CREATE INDEX ON mv_event_stats (marketplace_client_id)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('DROP MATERIALIZED VIEW IF EXISTS mv_event_stats');
    }
};
