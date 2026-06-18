<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Analytics index for orders.channel.
 *
 * The `channel` column itself was already introduced by the E6 migration
 * (2026_05_22_160000_add_channel_to_orders_and_pricing_to_ticket_types)
 * with the values: 'online' (default), 'pos_fixed', 'pos_mobile', 'embed',
 * 'partner_app'. From 2026-06-18, the whitelabel front-end appends a new
 * value 'whitelabel' (stamped by MarketplaceTrackingController when a
 * Purchase event arrives with channel='whitelabel'). This migration only
 * adds the composite (channel, created_at) index needed by the per-channel
 * analytics dashboard — no schema change, no data backfill.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('orders', 'channel')) {
            // Defensive — should never run (E6 already added it on existing
            // environments), but covers a fresh DB built without E6.
            Schema::table('orders', function (Blueprint $table) {
                $table->string('channel')->default('online');
            });
        }

        $indexExists = collect(DB::select(<<<'SQL'
            SELECT indexname FROM pg_indexes
            WHERE tablename = 'orders' AND indexname = 'idx_orders_channel_created'
        SQL))->isNotEmpty();

        if (! $indexExists) {
            Schema::table('orders', function (Blueprint $table) {
                $table->index(['channel', 'created_at'], 'idx_orders_channel_created');
            });
        }
    }

    public function down(): void
    {
        $indexExists = collect(DB::select(<<<'SQL'
            SELECT indexname FROM pg_indexes
            WHERE tablename = 'orders' AND indexname = 'idx_orders_channel_created'
        SQL))->isNotEmpty();

        if ($indexExists) {
            Schema::table('orders', function (Blueprint $table) {
                $table->dropIndex('idx_orders_channel_created');
            });
        }
    }
};
