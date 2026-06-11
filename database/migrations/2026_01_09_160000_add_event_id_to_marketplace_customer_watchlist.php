<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketplace_customer_watchlist', function (Blueprint $table) {
            // Add event_id column for events from the main events table
            if (!Schema::hasColumn('marketplace_customer_watchlist', 'event_id')) {
                $table->unsignedBigInteger('event_id')->nullable()->after('marketplace_event_id');
                $table->foreign('event_id', 'mcw_event_fk')
                    ->references('id')->on('events')->onDelete('cascade');
            }

            // Make marketplace_event_id nullable (since we might use event_id instead)
            // Note: We can't easily change nullable status on existing FK, so we'll handle it in code
        });

        // Add index for event_id lookups
        try {
            Schema::table('marketplace_customer_watchlist', function (Blueprint $table) {
                $table->index(['marketplace_customer_id', 'event_id'], 'mcw_customer_event_idx');
            });
        } catch (\Exception $e) {
            // Index might already exist
        }
    }

    public function down(): void
    {
        Schema::table('marketplace_customer_watchlist', function (Blueprint $table) {
            try {
                $table->dropForeign('mcw_event_fk');
            } catch (\Exception $e) {}

            try {
                $table->dropIndex('mcw_customer_event_idx');
            } catch (\Exception $e) {}

            if (Schema::hasColumn('marketplace_customer_watchlist', 'event_id')) {
                $table->dropColumn('event_id');
            }
        });
    }
};
