<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Index event_date on events — used by upcoming/past scopes, date range filters
        Schema::table('events', function (Blueprint $table) {
            $table->index('event_date');
            $table->index('marketplace_client_id');
            $table->index('marketplace_organizer_id');
            $table->index(['marketplace_client_id', 'is_published', 'event_date']);
        });

        // Index marketplace_event_id on service_orders — used by hasActivePaidPromotion()
        Schema::table('service_orders', function (Blueprint $table) {
            $table->index(['marketplace_event_id', 'service_type', 'status'], 'so_event_featuring_idx');
        });

        // Index event_id on tickets — used by event detail joins
        if (!$this->hasIndex('tickets', 'tickets_event_id_index')) {
            Schema::table('tickets', function (Blueprint $table) {
                if (Schema::hasColumn('tickets', 'event_id')) {
                    $table->index('event_id');
                }
            });
        }

        // Index marketplace_event_id on orders
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'marketplace_event_id')) {
                $table->index('marketplace_event_id');
            }
        });

        // Composite index on ticket_types for price sort subquery
        Schema::table('ticket_types', function (Blueprint $table) {
            $table->index(['event_id', 'status', 'price_cents'], 'tt_event_price_idx');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropIndex(['event_date']);
            $table->dropIndex(['marketplace_client_id']);
            $table->dropIndex(['marketplace_organizer_id']);
            $table->dropIndex(['marketplace_client_id', 'is_published', 'event_date']);
        });

        Schema::table('service_orders', function (Blueprint $table) {
            $table->dropIndex('so_event_featuring_idx');
        });

        Schema::table('tickets', function (Blueprint $table) {
            if (Schema::hasColumn('tickets', 'event_id')) {
                $table->dropIndex(['event_id']);
            }
        });

        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'marketplace_event_id')) {
                $table->dropIndex(['marketplace_event_id']);
            }
        });

        Schema::table('ticket_types', function (Blueprint $table) {
            $table->dropIndex('tt_event_price_idx');
        });
    }

    private function hasIndex(string $table, string $indexName): bool
    {
        $indexes = Schema::getIndexes($table);
        foreach ($indexes as $index) {
            if ($index['name'] === $indexName) {
                return true;
            }
        }
        return false;
    }
};
