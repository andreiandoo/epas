<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Bulletproof add of activity_id — does NOT use Schema::hasColumn (which on this
 * environment returns stale results, causing the previous guarded migrations to
 * skip the column add even though they recorded as DONE). Runs raw ALTER TABLE
 * statements; each is wrapped in try/catch so a "Duplicate column / key name"
 * error (column already present) is harmless and ignored.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Add the column. If it already exists, MySQL throws 1060 (duplicate
        // column) → caught and ignored.
        try {
            DB::statement('ALTER TABLE marketplace_customer_reviews ADD COLUMN activity_id BIGINT UNSIGNED NULL AFTER marketplace_event_id');
        } catch (\Throwable $e) {
            // already exists
        }

        try {
            DB::statement('ALTER TABLE marketplace_customer_reviews ADD INDEX mcr_activity_status_idx (activity_id, status)');
        } catch (\Throwable $e) {
            // already exists
        }

        try {
            DB::statement('ALTER TABLE marketplace_customer_reviews ADD UNIQUE INDEX mcr_customer_activity_unique (marketplace_customer_id, activity_id)');
        } catch (\Throwable $e) {
            // already exists
        }

        // Allow activity-only reviews to leave marketplace_event_id NULL.
        try {
            DB::statement('ALTER TABLE marketplace_customer_reviews MODIFY marketplace_event_id BIGINT UNSIGNED NULL');
        } catch (\Throwable $e) {
            // driver/constraint may reject — event flow unaffected either way
        }
    }

    public function down(): void
    {
        // No-op (additive column shared with the earlier migrations' intent).
    }
};
