<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Enhances core_sessions table with tracking intelligence fields.
     */
    public function up(): void
    {
        Schema::table('core_sessions', function (Blueprint $table) {
            // Session token from client SDK (tx_sid)
            $table->string('session_token', 64)->nullable()->unique()->after('session_id');

            // Sequence tracking
            $table->integer('sequence_count')->default(0)->after('events');

            // First touch attribution (utm, click_ids, referrer, landing_page captured at session start)
            $table->jsonb('first_touch')->nullable()->after('sequence_count');

            // Consent snapshots
            $table->jsonb('consent_snapshot_initial')->nullable()->after('first_touch');
            $table->jsonb('consent_snapshot_final')->nullable()->after('consent_snapshot_initial');

            // Enhanced engagement tracking
            $table->integer('engagement_active_ms')->default(0)->after('consent_snapshot_final');
            $table->integer('engagement_total_ms')->default(0)->after('engagement_active_ms');
            $table->integer('max_scroll_pct')->default(0)->after('engagement_total_ms');
            $table->integer('visibility_changes')->default(0)->after('max_scroll_pct');
            $table->integer('focus_changes')->default(0)->after('visibility_changes');

            // Link to tx_events for quick lookups
            $table->integer('tx_events_count')->default(0)->after('focus_changes');
        });

        // Add index on session_token for fast lookups
        Schema::table('core_sessions', function (Blueprint $table) {
            $table->index(['tenant_id', 'session_token'], 'idx_sessions_tenant_token');
            $table->index(['tenant_id', 'visitor_id', 'started_at'], 'idx_sessions_tenant_visitor_started');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('core_sessions', function (Blueprint $table) {
            $table->dropIndex('idx_sessions_tenant_token');
            $table->dropIndex('idx_sessions_tenant_visitor_started');
        });

        Schema::table('core_sessions', function (Blueprint $table) {
            $table->dropColumn([
                'session_token',
                'sequence_count',
                'first_touch',
                'consent_snapshot_initial',
                'consent_snapshot_final',
                'engagement_active_ms',
                'engagement_total_ms',
                'max_scroll_pct',
                'visibility_changes',
                'focus_changes',
                'tx_events_count',
            ]);
        });
    }
};
