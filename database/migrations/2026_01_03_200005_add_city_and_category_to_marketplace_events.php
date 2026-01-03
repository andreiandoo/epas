<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add city reference (if not exists)
        if (!Schema::hasColumn('marketplace_events', 'marketplace_city_id')) {
            Schema::table('marketplace_events', function (Blueprint $table) {
                $table->foreignId('marketplace_city_id')->nullable()->after('venue_city')
                    ->constrained('marketplace_cities')->nullOnDelete();
            });
        }

        // Add custom event category (if not exists)
        if (!Schema::hasColumn('marketplace_events', 'marketplace_event_category_id')) {
            Schema::table('marketplace_events', function (Blueprint $table) {
                $table->foreignId('marketplace_event_category_id')->nullable()->after('category')
                    ->constrained('marketplace_event_categories')->nullOnDelete();
            });
        }

        // Add indexes for filtering (wrap in try-catch)
        try {
            Schema::table('marketplace_events', function (Blueprint $table) {
                $table->index(['marketplace_client_id', 'marketplace_city_id', 'status'], 'mkt_events_city_status_idx');
            });
        } catch (\Exception $e) {
            // Index already exists
        }

        try {
            Schema::table('marketplace_events', function (Blueprint $table) {
                $table->index(['marketplace_client_id', 'marketplace_event_category_id', 'status'], 'mkt_events_category_status_idx');
            });
        } catch (\Exception $e) {
            // Index already exists
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('marketplace_events', function (Blueprint $table) {
            $table->dropIndex('mkt_events_city_status_idx');
            $table->dropIndex('mkt_events_category_status_idx');
            $table->dropForeign(['marketplace_city_id']);
            $table->dropForeign(['marketplace_event_category_id']);
            $table->dropColumn(['marketplace_city_id', 'marketplace_event_category_id']);
        });
    }
};
