<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds three columns to marketplace_newsletters:
 *
 *  - target_organizer_ids   JSON, nullable
 *      Newsletter targets all customers who bought a valid ticket at
 *      any event of these organizers. Optionally narrowed by city via
 *      target_city_ids. Empty/null = ignored.
 *
 *  - target_city_ids        JSON, nullable
 *      Persisted version of the city pre-filter (was UI-only / dehydrated
 *      false before). Used at recipient-build time to narrow the event /
 *      organizer expansion.
 *
 *  - purchase_count         INT default 0
 *  - purchase_amount_cents  BIGINT default 0
 *      Aggregates updated by the OrderObserver when a paid order carries
 *      newsletter_attribution_id pointing at this campaign. Surfaced in
 *      the EditNewsletter stats panel as "Cumpărări" + revenue.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('marketplace_newsletters', function (Blueprint $table) {
            if (!Schema::hasColumn('marketplace_newsletters', 'target_organizer_ids')) {
                $table->json('target_organizer_ids')->nullable()->after('target_event_ids');
            }
            if (!Schema::hasColumn('marketplace_newsletters', 'target_city_ids')) {
                $table->json('target_city_ids')->nullable()->after('target_organizer_ids');
            }
            if (!Schema::hasColumn('marketplace_newsletters', 'purchase_count')) {
                $table->unsignedInteger('purchase_count')->default(0)->after('unsubscribed_count');
            }
            if (!Schema::hasColumn('marketplace_newsletters', 'purchase_amount_cents')) {
                $table->unsignedBigInteger('purchase_amount_cents')->default(0)->after('purchase_count');
            }
        });
    }

    public function down(): void
    {
        Schema::table('marketplace_newsletters', function (Blueprint $table) {
            foreach (['target_organizer_ids', 'target_city_ids', 'purchase_count', 'purchase_amount_cents'] as $col) {
                if (Schema::hasColumn('marketplace_newsletters', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
