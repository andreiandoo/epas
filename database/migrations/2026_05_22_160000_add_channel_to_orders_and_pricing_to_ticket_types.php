<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * E6 — Channel tracking & per-channel pricing.
 *
 * Adds:
 *  - orders.channel (string): 'online' (default), 'pos_fixed', 'pos_mobile',
 *    'embed', 'partner_app'. Existing orders default to 'online'.
 *  - ticket_types.channel_pricing (JSON): absolute prices per channel,
 *    e.g. {"online": 1000, "pos_fixed": 1200, "pos_mobile": 1100}.
 *    Stored as cents. NULL = use default price for all channels.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'channel')) {
                $table->string('channel')->default('online')->index();
            }
            if (! Schema::hasColumn('orders', 'channel_meta')) {
                $table->json('channel_meta')->nullable();
            }
        });

        Schema::table('ticket_types', function (Blueprint $table) {
            if (! Schema::hasColumn('ticket_types', 'channel_pricing')) {
                $table->json('channel_pricing')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('ticket_types', function (Blueprint $table) {
            if (Schema::hasColumn('ticket_types', 'channel_pricing')) {
                $table->dropColumn('channel_pricing');
            }
        });
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'channel_meta')) {
                $table->dropColumn('channel_meta');
            }
            if (Schema::hasColumn('orders', 'channel')) {
                $table->dropColumn('channel');
            }
        });
    }
};
