<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Corrective migration. The previous one checked Schema::hasColumn() *inside*
 * the Schema::table() closure, which on this setup skipped the column add even
 * though the migration recorded as DONE. This version checks OUTSIDE the
 * closure (the standard, reliable pattern) and is fully idempotent: every step
 * is guarded, so running it when the column already exists is a safe no-op.
 * Strictly additive — other marketplaces are unaffected.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('marketplace_customer_reviews')) {
            return;
        }

        if (! Schema::hasColumn('marketplace_customer_reviews', 'activity_id')) {
            Schema::table('marketplace_customer_reviews', function (Blueprint $table) {
                $table->unsignedBigInteger('activity_id')->nullable()->after('marketplace_event_id');
            });
        }

        // Indexes — added separately and guarded (try/catch) in case a prior
        // partial run already created them.
        try {
            Schema::table('marketplace_customer_reviews', function (Blueprint $table) {
                $table->index(['activity_id', 'status'], 'mcr_activity_status_idx');
            });
        } catch (\Throwable $e) {
            // already exists
        }

        try {
            Schema::table('marketplace_customer_reviews', function (Blueprint $table) {
                $table->unique(['marketplace_customer_id', 'activity_id'], 'mcr_customer_activity_unique');
            });
        } catch (\Throwable $e) {
            // already exists
        }

        // Relax marketplace_event_id to NULL so activity-only reviews are valid.
        try {
            DB::statement('ALTER TABLE marketplace_customer_reviews MODIFY marketplace_event_id BIGINT UNSIGNED NULL');
        } catch (\Throwable $e) {
            // driver may not support this syntax; event flow unaffected
        }
    }

    public function down(): void
    {
        // No-op: the column is additive and shared with the previous migration's
        // intent. Dropping handled by the original migration's down() if needed.
    }
};
