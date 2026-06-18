<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add `channel` to orders so the analytics dashboard can scope revenue /
 * tickets / chart data by the front-end that produced the purchase, not just
 * the page-view funnel.
 *
 * Default 'marketplace' classifies every legacy order correctly: until
 * whitelabel tracking went live in 2026-06-18, every customer-facing path
 * was the main site (ambilet.ro / bilete.online / tics.ro). New orders
 * placed on a whitelabel ZIP-packaged site get `channel='whitelabel'`
 * written by MarketplaceTrackingController when the Purchase event fires
 * with an order_id payload.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('channel', 50)->default('marketplace')->after('source')
                ->comment('Order channel: marketplace | whitelabel | embed_widget');
            $table->index(['channel', 'created_at'], 'idx_orders_channel_created');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex('idx_orders_channel_created');
            $table->dropColumn('channel');
        });
    }
};
