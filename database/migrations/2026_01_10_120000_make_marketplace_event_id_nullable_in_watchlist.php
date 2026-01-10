<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Drop the existing foreign key constraint first
        Schema::table('marketplace_customer_watchlist', function (Blueprint $table) {
            $table->dropForeign(['marketplace_event_id']);
        });

        // Modify the column to be nullable
        Schema::table('marketplace_customer_watchlist', function (Blueprint $table) {
            $table->unsignedBigInteger('marketplace_event_id')->nullable()->change();
        });

        // Re-add the foreign key constraint
        Schema::table('marketplace_customer_watchlist', function (Blueprint $table) {
            $table->foreign('marketplace_event_id')
                ->references('id')
                ->on('marketplace_events')
                ->onDelete('cascade');
        });

        // Drop the old unique constraint that requires marketplace_event_id
        try {
            Schema::table('marketplace_customer_watchlist', function (Blueprint $table) {
                $table->dropUnique('mcw_customer_event_unique');
            });
        } catch (\Exception $e) {
            // Constraint might not exist or have different name
        }

        // Add a new unique constraint that allows for either event_id or marketplace_event_id
        // This prevents duplicates while allowing one of them to be null
        try {
            Schema::table('marketplace_customer_watchlist', function (Blueprint $table) {
                $table->unique(
                    ['marketplace_customer_id', 'event_id', 'marketplace_event_id'],
                    'mcw_customer_events_unique'
                );
            });
        } catch (\Exception $e) {
            // Index might already exist
        }
    }

    public function down(): void
    {
        // Revert changes
        Schema::table('marketplace_customer_watchlist', function (Blueprint $table) {
            $table->dropForeign(['marketplace_event_id']);
        });

        Schema::table('marketplace_customer_watchlist', function (Blueprint $table) {
            $table->unsignedBigInteger('marketplace_event_id')->nullable(false)->change();
        });

        Schema::table('marketplace_customer_watchlist', function (Blueprint $table) {
            $table->foreign('marketplace_event_id')
                ->references('id')
                ->on('marketplace_events')
                ->onDelete('cascade');
        });

        try {
            Schema::table('marketplace_customer_watchlist', function (Blueprint $table) {
                $table->dropUnique('mcw_customer_events_unique');
            });
        } catch (\Exception $e) {}

        try {
            Schema::table('marketplace_customer_watchlist', function (Blueprint $table) {
                $table->unique(
                    ['marketplace_customer_id', 'marketplace_event_id'],
                    'mcw_customer_event_unique'
                );
            });
        } catch (\Exception $e) {}
    }
};
