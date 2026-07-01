<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds a routing-mode switch for transactional emails per marketplace.
 *
 * Values:
 *   - 'auto'                → historical behaviour: try the transactional
 *                             provider first, fall back to primary on
 *                             failure. Backwards-compatible default.
 *   - 'primary_only'        → skip the transactional provider entirely
 *                             and send every transactional template
 *                             through the primary transport (Brevo on
 *                             Ambilet). Useful for A/B testing which
 *                             provider is actually delivering.
 *   - 'transactional_only'  → try the transactional provider ONLY; if
 *                             it fails, return an error (no fallback).
 *                             Useful to expose silent transactional
 *                             failures that were previously masked by
 *                             the auto-fallback.
 *
 * Column intentionally nullable+default so pre-existing rows read as
 * 'auto' and no code path breaks.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('marketplace_clients', function (Blueprint $table) {
            $table->string('transactional_mode', 32)->default('auto')->after('transactional_smtp_settings');
        });
    }

    public function down(): void
    {
        Schema::table('marketplace_clients', function (Blueprint $table) {
            $table->dropColumn('transactional_mode');
        });
    }
};
