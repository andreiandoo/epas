<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add `channel` column to core_customer_events so we can distinguish traffic
 * sources for marketplace events:
 *   - 'marketplace'   → ambilet.ro / bilete.online / tics.ro main sites
 *   - 'whitelabel'    → organizer's own ZIP-packaged site (bilete.<organizer>.ro)
 *   - 'embed_widget'  → iframe widget embedded on third-party domains
 *
 * Default 'marketplace' so existing rows (which all came from the main site
 * since whitelabel had no tracking before) are correctly classified without
 * needing a separate backfill UPDATE.
 *
 * Indexed jointly with marketplace_event_id + event_type because the analytics
 * funnel charts always pivot by channel within a single event's date range.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('core_customer_events', function (Blueprint $table) {
            $table->string('channel', 50)->default('marketplace')->after('marketplace_client_id')
                ->comment('Traffic source channel: marketplace | whitelabel | embed_widget');
            $table->index(['marketplace_event_id', 'channel', 'event_type', 'created_at'], 'idx_mp_event_channel_tracking');
        });
    }

    public function down(): void
    {
        Schema::table('core_customer_events', function (Blueprint $table) {
            $table->dropIndex('idx_mp_event_channel_tracking');
            $table->dropColumn('channel');
        });
    }
};
