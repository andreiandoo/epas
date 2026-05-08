<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Extend the tracking_integrations.provider enum with `google_ads`.
 *
 * Background: the existing enum (ga4, gtm, meta, tiktok) covered analytics
 * (GA4, GTM) and the social-ad pixels (Meta, TikTok), but had no slot for
 * Google Ads conversion tracking. The "google" platform on the
 * Ad Tracking service-order checkout had no real home — mapping it onto
 * GA4 would conflate analytics with conversion tracking (different
 * pixel formats: G-XXXXXX vs AW-XXXXXX).
 *
 * Laravel's enum() on Postgres maps to VARCHAR + CHECK constraint, not
 * a native ENUM type, so extending = drop + recreate the CHECK.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('tracking_integrations')) {
            return;
        }

        $driver = DB::connection()->getDriverName();
        if ($driver === 'pgsql') {
            DB::statement("ALTER TABLE tracking_integrations DROP CONSTRAINT IF EXISTS tracking_integrations_provider_check");
            DB::statement("ALTER TABLE tracking_integrations ADD CONSTRAINT tracking_integrations_provider_check CHECK (provider IN ('ga4', 'gtm', 'meta', 'tiktok', 'google_ads'))");
        }
        // SQLite/MySQL paths intentionally a no-op: SQLite doesn't enforce
        // CHECK on enum() and MySQL would need a column type rewrite that
        // we don't run on prod.
    }

    public function down(): void
    {
        if (!Schema::hasTable('tracking_integrations')) {
            return;
        }

        $driver = DB::connection()->getDriverName();
        if ($driver === 'pgsql') {
            // Revert to original 4-value enum. Any rows with provider='google_ads'
            // would block the constraint — caller should clean those up first.
            DB::statement("ALTER TABLE tracking_integrations DROP CONSTRAINT IF EXISTS tracking_integrations_provider_check");
            DB::statement("ALTER TABLE tracking_integrations ADD CONSTRAINT tracking_integrations_provider_check CHECK (provider IN ('ga4', 'gtm', 'meta', 'tiktok'))");
        }
    }
};
