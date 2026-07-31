<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ad click IDs (fbclid, gclid, ttclid, ...) and UTM params can exceed 255 chars —
 * notably Meta's newer encrypted "E_C_P_..." fbclid (~260+), plus fbc = fb.1.{ts}.{fbclid}.
 * These columns were varchar(255) and overflowed on insert (SQLSTATE 22001), losing
 * the whole tracking row.
 *
 * Widen them to varchar(1024). All are currently varchar (never text), so this is a
 * length INCREASE only: Postgres does it as a metadata-only change — no table rewrite
 * and no index rebuild (varchar_ops stays valid, so the fbclid partial index is kept).
 * hasColumn guards make it safe regardless of which add-column migrations ran.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('core_customer_events', function (Blueprint $table) {
            foreach ([
                'fbclid', 'fbc', 'fbp', 'gclid', 'ttclid', 'li_fat_id',
                'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content',
            ] as $col) {
                if (Schema::hasColumn('core_customer_events', $col)) {
                    $table->string($col, 1024)->nullable()->change();
                }
            }
            if (Schema::hasColumn('core_customer_events', 'content_name')) {
                $table->string('content_name', 512)->nullable()->change();
            }
        });
    }

    public function down(): void
    {
        // No-op: shrinking back to 255 could truncate/fail on existing long values.
    }
};
