<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds per-campaign toggles so the admin can strip "stale" (never-opened)
 * and "no-click" cohorts from the recipient set at send time. The cohort
 * definitions live as has_stale_no_opens / has_stale_no_clicks rules on
 * MarketplaceContactList so they're also reusable as dynamic lists.
 *
 * Window hours travel with the toggle so the admin can override the
 * defaults per campaign (48h for opens, 96h for clicks). Hard floor of
 * 24h enforced in the form so we never categorize someone as stale
 * within the same-day cooldown.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('marketplace_newsletters', function (Blueprint $table) {
            if (!Schema::hasColumn('marketplace_newsletters', 'exclude_stale_no_opens')) {
                $table->boolean('exclude_stale_no_opens')->default(false)->after('recent_recipient_window_hours');
            }
            if (!Schema::hasColumn('marketplace_newsletters', 'stale_no_opens_window_hours')) {
                $table->unsignedSmallInteger('stale_no_opens_window_hours')->default(48)->after('exclude_stale_no_opens');
            }
            if (!Schema::hasColumn('marketplace_newsletters', 'exclude_stale_no_clicks')) {
                $table->boolean('exclude_stale_no_clicks')->default(false)->after('stale_no_opens_window_hours');
            }
            if (!Schema::hasColumn('marketplace_newsletters', 'stale_no_clicks_window_hours')) {
                $table->unsignedSmallInteger('stale_no_clicks_window_hours')->default(96)->after('exclude_stale_no_clicks');
            }
        });
    }

    public function down(): void
    {
        Schema::table('marketplace_newsletters', function (Blueprint $table) {
            foreach (['exclude_stale_no_opens', 'stale_no_opens_window_hours', 'exclude_stale_no_clicks', 'stale_no_clicks_window_hours'] as $col) {
                if (Schema::hasColumn('marketplace_newsletters', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
