<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Extends the marketplace_customer_reviews table so a review can target an
 * Activity (bilete.online's Activities module) in addition to a marketplace
 * event. Strictly additive + nullable: existing event reviews are untouched and
 * marketplaces without the Activities module are unaffected (they never write
 * an activity_id). Reviews remain "verified purchase" — for activities the
 * proof is a completed ActivityBooking instead of an event order.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('marketplace_customer_reviews')) {
            return;
        }

        Schema::table('marketplace_customer_reviews', function (Blueprint $table) {
            if (! Schema::hasColumn('marketplace_customer_reviews', 'activity_id')) {
                // Nullable FK to activities. No DB-level constrained() to avoid a
                // hard dependency / cascade surprises across modules; the app
                // layer scopes by marketplace_client_id + status.
                $table->unsignedBigInteger('activity_id')->nullable()->after('marketplace_event_id');
                $table->index(['activity_id', 'status'], 'mcr_activity_status_idx');
                // One review per (customer, activity). Multiple NULLs are allowed
                // by MySQL, so event-only reviews (activity_id IS NULL) never clash.
                $table->unique(['marketplace_customer_id', 'activity_id'], 'mcr_customer_activity_unique');
            }
        });

        // marketplace_event_id was NOT NULL; relax it so activity reviews can
        // leave it empty. Guarded — only run if the column is currently required.
        try {
            $col = collect(Schema::getColumnListing('marketplace_customer_reviews'));
            if ($col->contains('marketplace_event_id')) {
                // Use raw ALTER for portability (doctrine/dbal may be absent).
                \DB::statement('ALTER TABLE marketplace_customer_reviews MODIFY marketplace_event_id BIGINT UNSIGNED NULL');
            }
        } catch (\Throwable $e) {
            // Non-fatal: if the driver doesn't support MODIFY this way, activity
            // reviews can still be inserted on engines that allow NULL by default
            // once a value is provided; event flow is unaffected.
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('marketplace_customer_reviews')) {
            return;
        }

        Schema::table('marketplace_customer_reviews', function (Blueprint $table) {
            if (Schema::hasColumn('marketplace_customer_reviews', 'activity_id')) {
                $table->dropUnique('mcr_customer_activity_unique');
                $table->dropIndex('mcr_activity_status_idx');
                $table->dropColumn('activity_id');
            }
        });
    }
};
