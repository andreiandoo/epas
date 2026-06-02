<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Health-tracking columns for Facebook CAPI connections, consumed by the
 * `capi:health-check` scheduled command:
 *
 *   - `last_health_status` : 'alerting' while the connection is in a
 *      failure cycle, NULL when healthy. The command flips this on
 *      healthy→alerting / alerting→healthy transitions only.
 *   - `last_alerted_at`    : timestamp of the most recent unhealthy
 *      notification dispatch. Used to keep the alert one-shot per
 *      incident (never spam while still alerting).
 *
 * Both nullable + default null → strictly additive; existing flows
 * (auto-create on organizer edit, observer dispatch, etc.) keep
 * working unchanged. Existing rows pick up NULL on both columns
 * which the command treats as "healthy / not yet evaluated".
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('facebook_capi_connections')) {
            return;
        }
        Schema::table('facebook_capi_connections', function (Blueprint $table) {
            if (! Schema::hasColumn('facebook_capi_connections', 'last_health_status')) {
                $table->string('last_health_status', 16)->nullable()->after('status');
            }
            if (! Schema::hasColumn('facebook_capi_connections', 'last_alerted_at')) {
                $table->timestamp('last_alerted_at')->nullable()->after('last_health_status');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('facebook_capi_connections')) {
            return;
        }
        Schema::table('facebook_capi_connections', function (Blueprint $table) {
            if (Schema::hasColumn('facebook_capi_connections', 'last_alerted_at')) {
                $table->dropColumn('last_alerted_at');
            }
            if (Schema::hasColumn('facebook_capi_connections', 'last_health_status')) {
                $table->dropColumn('last_health_status');
            }
        });
    }
};
