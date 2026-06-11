<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds newsletter_attribution_id to orders so a paid order can be
 * credited back to the newsletter campaign that drove it.
 *
 * Set on order creation by the checkout layer (frontend reads `nl=`
 * from the landing URL → localStorage with 30-day TTL → sends it with
 * the order payload). OrderObserver picks it up on status=paid and
 * fans out a TYPE_PURCHASE row to marketplace_newsletter_link_events
 * plus increments purchase_count / purchase_amount_cents on the
 * newsletter.
 *
 * Nullable + no FK to marketplace_newsletters so a deleted campaign
 * doesn't cascade-orphan order rows.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'newsletter_attribution_id')) {
                $table->unsignedBigInteger('newsletter_attribution_id')->nullable()->after('marketplace_organizer_id');
                $table->index('newsletter_attribution_id', 'orders_newsletter_attr_idx');
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'newsletter_attribution_id')) {
                $table->dropIndex('orders_newsletter_attr_idx');
                $table->dropColumn('newsletter_attribution_id');
            }
        });
    }
};
