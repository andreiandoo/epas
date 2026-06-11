<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Adds attribution_method to orders so we can distinguish how a newsletter
 * conversion was tracked:
 *
 *  - 'url_param'    : strict — the order carried the ?nl=X URL param /
 *                     localStorage value set when the customer clicked
 *                     a tracked link. Highest confidence.
 *  - 'email_match'  : loose — post-purchase email matched a recipient
 *                     who clicked a tracked link inside a configurable
 *                     lookback window (default 14 days). Covers in-app
 *                     browsers, cross-device flows, cleared localStorage.
 *  - NULL           : legacy / unknown (orders predating this column).
 *
 * Backfill: orders that already had newsletter_attribution_id set were
 * filled via the URL flow, so they get attribution_method='url_param'.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'attribution_method')) {
                $table->string('attribution_method', 32)->nullable()->after('newsletter_attribution_id');
            }
        });

        DB::table('orders')
            ->whereNotNull('newsletter_attribution_id')
            ->whereNull('attribution_method')
            ->update(['attribution_method' => 'url_param']);
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'attribution_method')) {
                $table->dropColumn('attribution_method');
            }
        });
    }
};
