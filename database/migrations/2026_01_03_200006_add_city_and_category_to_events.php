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
        // Add city reference for marketplace events (if not exists)
        if (!Schema::hasColumn('events', 'marketplace_city_id')) {
            Schema::table('events', function (Blueprint $table) {
                $table->foreignId('marketplace_city_id')->nullable()->after('event_website_url')
                    ->constrained('marketplace_cities')->nullOnDelete();
            });
        }

        // Add custom event category for marketplace events (if not exists)
        if (!Schema::hasColumn('events', 'marketplace_event_category_id')) {
            Schema::table('events', function (Blueprint $table) {
                $table->foreignId('marketplace_event_category_id')->nullable()->after('marketplace_city_id')
                    ->constrained('marketplace_event_categories')->nullOnDelete();
            });
        }

        // Add indexes for filtering (wrap in try-catch)
        try {
            Schema::table('events', function (Blueprint $table) {
                $table->index(['marketplace_client_id', 'marketplace_city_id'], 'events_marketplace_city_idx');
            });
        } catch (\Exception $e) {
            // Index already exists
        }

        try {
            Schema::table('events', function (Blueprint $table) {
                $table->index(['marketplace_client_id', 'marketplace_event_category_id'], 'events_marketplace_category_idx');
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
        Schema::table('events', function (Blueprint $table) {
            $table->dropIndex('events_marketplace_city_idx');
            $table->dropIndex('events_marketplace_category_idx');
            $table->dropForeign(['marketplace_city_id']);
            $table->dropForeign(['marketplace_event_category_id']);
            $table->dropColumn(['marketplace_city_id', 'marketplace_event_category_id']);
        });
    }
};
