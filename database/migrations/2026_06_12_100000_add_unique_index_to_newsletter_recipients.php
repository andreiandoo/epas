<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Adds a unique index on (newsletter_id, marketplace_customer_id) so
 * createRecipients can switch from a per-row updateOrCreate
 * (SELECT + INSERT per customer — N+1 queries) to a bulk upsert. A
 * 22k-recipient send was holding the Filament action open for tens of
 * seconds; the 65k-recipient runs queued behind it would have timed out.
 *
 * The previous updateOrCreate path could in theory produce duplicates
 * under a race (parallel action clicks). Dedupe defensively before
 * adding the constraint.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('marketplace_newsletter_recipients')) {
            return;
        }

        // Keep only the most recent row per (newsletter_id, customer_id)
        // pair. Postgres-only because all production DBs are pgsql; the
        // raw subquery is faster than chunked PHP for the 10k+ existing
        // rows on Ambilet.
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('
                DELETE FROM marketplace_newsletter_recipients a
                USING marketplace_newsletter_recipients b
                WHERE a.id < b.id
                  AND a.newsletter_id = b.newsletter_id
                  AND a.marketplace_customer_id = b.marketplace_customer_id
            ');
        }

        Schema::table('marketplace_newsletter_recipients', function (Blueprint $table) {
            $table->unique(
                ['newsletter_id', 'marketplace_customer_id'],
                'mkt_nl_recip_newsletter_customer_unique'
            );
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('marketplace_newsletter_recipients')) {
            return;
        }
        Schema::table('marketplace_newsletter_recipients', function (Blueprint $table) {
            $table->dropUnique('mkt_nl_recip_newsletter_customer_unique');
        });
    }
};
