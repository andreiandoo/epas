<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration {
    public function up(): void
    {
        // Orders - composite indexes for common queries
        Schema::table('orders', function (Blueprint $table) {
            $table->index('status', 'idx_orders_status');
            $table->index(['customer_id', 'status'], 'idx_orders_customer_status');
            $table->index(['tenant_id', 'created_at'], 'idx_orders_tenant_created');
            $table->index(['marketplace_client_id', 'status', 'paid_at'], 'idx_orders_mp_status_paid');
        });

        // Tickets - missing indexes
        Schema::table('tickets', function (Blueprint $table) {
            if (Schema::hasColumn('tickets', 'event_id')) {
                $table->index('event_id', 'idx_tickets_event_id');
            }
            if (Schema::hasColumn('tickets', 'marketplace_event_id')) {
                $table->index('marketplace_event_id', 'idx_tickets_mp_event_id');
            }
            if (Schema::hasColumn('tickets', 'checked_in_at')) {
                $table->index('checked_in_at', 'idx_tickets_checked_in');
            }
        });

        // Events - composite and flag indexes
        Schema::table('events', function (Blueprint $table) {
            $table->index(['tenant_id', 'created_at'], 'idx_events_tenant_created');
            $table->index(['marketplace_client_id', 'event_date'], 'idx_events_mp_date');
        });

        // GIN indexes for JSONB columns (PostgreSQL only)
        if (DB::getDriverName() === 'pgsql') {
            // Check column type before creating GIN index
            $jsonbColumns = [
                ['events', 'title'],
                ['events', 'description'],
                ['artists', 'name'],
                ['event_types', 'name'],
                ['event_genres', 'name'],
                ['artist_types', 'name'],
                ['artist_genres', 'name'],
            ];
            foreach ($jsonbColumns as [$table, $col]) {
                try {
                    $colType = DB::selectOne("SELECT data_type FROM information_schema.columns WHERE table_name = ? AND column_name = ? AND table_schema = 'public'", [$table, $col]);
                    if ($colType && $colType->data_type === 'jsonb') {
                        DB::statement("CREATE INDEX IF NOT EXISTS idx_{$table}_{$col}_gin ON \"{$table}\" USING GIN (\"{$col}\")");
                    }
                } catch (\Exception $e) {
                    // Skip if table/column doesn't exist
                }
            }
        }

        // Customers
        Schema::table('customers', function (Blueprint $table) {
            if (!$this->indexExists('customers', 'idx_customers_tenant_email')) {
                $table->index(['tenant_id', 'email'], 'idx_customers_tenant_email');
            }
        });
    }

    public function down(): void
    {
        // Drop in reverse order
        Schema::table('customers', function (Blueprint $table) {
            $table->dropIndex('idx_customers_tenant_email');
        });
        Schema::table('events', function (Blueprint $table) {
            $table->dropIndex('idx_events_tenant_created');
            $table->dropIndex('idx_events_mp_date');
        });
        Schema::table('tickets', function (Blueprint $table) {
            if (Schema::hasColumn('tickets', 'event_id')) $table->dropIndex('idx_tickets_event_id');
            if (Schema::hasColumn('tickets', 'marketplace_event_id')) $table->dropIndex('idx_tickets_mp_event_id');
            if (Schema::hasColumn('tickets', 'checked_in_at')) $table->dropIndex('idx_tickets_checked_in');
        });
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex('idx_orders_status');
            $table->dropIndex('idx_orders_customer_status');
            $table->dropIndex('idx_orders_tenant_created');
            $table->dropIndex('idx_orders_mp_status_paid');
        });
        if (DB::getDriverName() === 'pgsql') {
            $indexes = ['idx_events_title_gin', 'idx_events_description_gin', 'idx_artists_name_gin', 'idx_event_types_name_gin', 'idx_event_genres_name_gin', 'idx_artist_types_name_gin', 'idx_artist_genres_name_gin'];
            foreach ($indexes as $idx) {
                DB::statement("DROP INDEX IF EXISTS \"{$idx}\"");
            }
        }
    }

    private function indexExists(string $table, string $indexName): bool
    {
        return collect(Schema::getIndexes($table))->contains(fn ($idx) => $idx['name'] === $indexName);
    }
};
