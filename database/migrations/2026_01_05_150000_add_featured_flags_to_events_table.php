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
        Schema::table('events', function (Blueprint $table) {
            // Featured flags for marketplace website
            $table->boolean('is_homepage_featured')->default(false)->after('is_promoted');
            $table->boolean('is_general_featured')->default(false)->after('is_homepage_featured');
            $table->boolean('is_category_featured')->default(false)->after('is_general_featured');

            // Index for quick lookups
            $table->index(['marketplace_client_id', 'is_homepage_featured'], 'events_mp_homepage_featured_idx');
            $table->index(['marketplace_client_id', 'is_general_featured'], 'events_mp_general_featured_idx');
            $table->index(['marketplace_client_id', 'is_category_featured', 'marketplace_event_category_id'], 'events_mp_category_featured_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropIndex('events_mp_homepage_featured_idx');
            $table->dropIndex('events_mp_general_featured_idx');
            $table->dropIndex('events_mp_category_featured_idx');

            $table->dropColumn([
                'is_homepage_featured',
                'is_general_featured',
                'is_category_featured',
            ]);
        });
    }
};
