<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Adds two groups of columns to events:
     *  1. MANUAL attributes — organizer flags them at event creation
     *     (is_indoor, is_kid_friendly, audience_tags, ...). Used to resolve
     *     intent filters like /brasov/activitati-indoor or /activitati-copii.
     *  2. CACHED aggregates — derived from ticket_types + performances by
     *     RefreshEventIntentAggregatesCommand. Kept on the event row so
     *     intent landing pages can filter with a single indexed WHERE
     *     instead of recomputing on every request.
     */
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            // --- MANUAL attributes (organizer fills these in admin) ---
            $table->boolean('is_indoor')->nullable()->after('is_published');
            $table->boolean('is_outdoor')->nullable()->after('is_indoor');
            $table->boolean('is_kid_friendly')->nullable()->after('is_outdoor');
            $table->boolean('is_accessible')->nullable()->after('is_kid_friendly');

            // True = activity is sensitive to weather (avoid suggesting in rain/heat).
            // Tiroliana = true. Muzeu = false. Used by /activitati-zile-ploioase to
            // INCLUDE only non-sensitive options + indoor.
            $table->boolean('is_weather_sensitive')->nullable()->after('is_accessible');

            $table->unsignedSmallInteger('min_age')->nullable()->after('is_weather_sensitive');
            $table->unsignedSmallInteger('max_age')->nullable()->after('min_age');

            // Free-form audience tags. JSON array of slugs from an admin-curated pool:
            // ["romantic","corporate","team-building","aniversari","cuplu","scoala"]
            $table->json('audience_tags')->nullable()->after('max_age');

            // --- CACHED aggregates (refreshed by console command) ---
            // Smallest ACTIVE ticket price across the event's ticket_types.
            // NULL = uncomputed or no tickets. 0 = free. >0 = paid in cents.
            $table->integer('cheapest_price_cents')->nullable()->after('audience_tags');

            // Timestamp of the next upcoming session (performances.starts_at OR
            // computed from event_date + start_time OR earliest multi_slots entry).
            $table->timestamp('next_session_at')->nullable()->after('cheapest_price_cents');

            // Pre-computed temporal flags — let the SQL be a fast indexed WHERE.
            $table->boolean('has_session_today')->default(false)->after('next_session_at');
            $table->boolean('has_session_tomorrow')->default(false)->after('has_session_today');
            $table->boolean('has_session_this_weekend')->default(false)->after('has_session_tomorrow');

            // Indexes for the intent filters we actually run.
            $table->index(['marketplace_client_id', 'is_published', 'is_indoor']);
            $table->index(['marketplace_client_id', 'is_published', 'is_kid_friendly']);
            $table->index(['marketplace_client_id', 'is_published', 'cheapest_price_cents']);
            $table->index(['marketplace_client_id', 'is_published', 'has_session_today']);
            $table->index(['marketplace_client_id', 'is_published', 'has_session_tomorrow']);
            $table->index(['marketplace_client_id', 'is_published', 'has_session_this_weekend']);
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropIndex(['marketplace_client_id', 'is_published', 'is_indoor']);
            $table->dropIndex(['marketplace_client_id', 'is_published', 'is_kid_friendly']);
            $table->dropIndex(['marketplace_client_id', 'is_published', 'cheapest_price_cents']);
            $table->dropIndex(['marketplace_client_id', 'is_published', 'has_session_today']);
            $table->dropIndex(['marketplace_client_id', 'is_published', 'has_session_tomorrow']);
            $table->dropIndex(['marketplace_client_id', 'is_published', 'has_session_this_weekend']);

            $table->dropColumn([
                'is_indoor', 'is_outdoor', 'is_kid_friendly', 'is_accessible',
                'is_weather_sensitive', 'min_age', 'max_age', 'audience_tags',
                'cheapest_price_cents', 'next_session_at',
                'has_session_today', 'has_session_tomorrow', 'has_session_this_weekend',
            ]);
        });
    }
};
