<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add a universal `discount_code` lookup key + `discount_source` marker
     * to event_ticket_type_promo_series so the table can host both
     * MarketplaceOrganizerPromoCode tiers and Coupon\CouponCode tiers in
     * one schema. Marketplace orders reference the canonical applied
     * discount via order.meta.promo_code.{code, source}, not via the
     * orders.promo_code_id column (which only the tenant-side flow sets),
     * so lookups by id alone miss coupon-driven discounts entirely —
     * which is exactly what blocked the initial Phase B sync for
     * HAILAQFEEL!.
     *
     * The new uniqueness scope is (ticket_type_id, discount_code,
     * is_intrinsic_red) with `''` meaning "parent / no discount". The old
     * (ticket_type_id, promo_code_id, is_intrinsic_red) unique is dropped.
     * promo_code_id is kept (nullable) for back-compat with any reader
     * that still resolves an MarketplaceOrganizerPromoCode FK.
     */
    public function up(): void
    {
        Schema::table('event_ticket_type_promo_series', function (Blueprint $table) {
            // Empty-string default lets us include "no discount" (parent)
            // rows in the new unique constraint — pgsql treats NULL as
            // distinct under unique, which would let duplicates slip in.
            $table->string('discount_code', 255)->nullable()->default('')->after('promo_code_id');
            $table->string('discount_source', 20)->nullable()->default('')->after('discount_code');
        });

        // Backfill existing rows. Parent / RED rows stay at '' (defaults).
        // Organizer-promo rows (where promo_code_id is set) get the code
        // string + 'organizer_promo' source.
        DB::statement(<<<SQL
            UPDATE event_ticket_type_promo_series ettps
            SET discount_code = COALESCE((
                    SELECT code FROM mkt_promo_codes
                    WHERE id = ettps.promo_code_id
                ), ''),
                discount_source = CASE
                    WHEN promo_code_id IS NOT NULL THEN 'organizer_promo'
                    WHEN is_intrinsic_red THEN 'intrinsic_red'
                    ELSE ''
                END
        SQL);

        // Swap uniqueness. Drop the old promo_code_id-based unique, add
        // the new discount_code-based one.
        Schema::table('event_ticket_type_promo_series', function (Blueprint $table) {
            $table->dropUnique('ettps_unique_tier');
        });
        Schema::table('event_ticket_type_promo_series', function (Blueprint $table) {
            $table->unique(
                ['ticket_type_id', 'discount_code', 'is_intrinsic_red'],
                'ettps_unique_tier_v2'
            );
        });
    }

    public function down(): void
    {
        Schema::table('event_ticket_type_promo_series', function (Blueprint $table) {
            $table->dropUnique('ettps_unique_tier_v2');
        });
        Schema::table('event_ticket_type_promo_series', function (Blueprint $table) {
            $table->unique(
                ['ticket_type_id', 'promo_code_id', 'is_intrinsic_red'],
                'ettps_unique_tier'
            );
            $table->dropColumn(['discount_code', 'discount_source']);
        });
    }
};
