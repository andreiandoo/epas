<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Extend api_keys with per-key scopes, custom rate limit, and IP
     * allowlist. All three are nullable — existing keys retain full
     * access, unbounded rate, and any-IP by default. Only NEW keys
     * created with these fields populated get restrictions.
     *
     * Non-breaking:
     *   - Old code that ignores these columns still works
     *   - VerifyApiKey middleware treats NULL scopes / allowed_ips
     *     as "no restriction" (legacy behavior)
     *   - Rate limiter falls back to the route's throttle group when
     *     rate_limit is NULL
     */
    public function up(): void
    {
        Schema::table('api_keys', function (Blueprint $table) {
            // Whitelist of scope strings this key is allowed to use.
            // NULL = legacy behavior (no scope restrictions).
            // Example: ["read.catalog", "read.analytics.venue"]
            $table->json('scopes')->nullable()->after('permissions');

            // Per-key rate limit (requests per minute). NULL = fall back
            // to the route's default throttle group.
            $table->unsignedInteger('rate_limit')->nullable()->after('scopes');

            // IP allowlist. NULL = accept from any IP (legacy behavior).
            // Array of strings, exact match.
            // Example: ["1.2.3.4", "10.0.0.5"]
            $table->json('allowed_ips')->nullable()->after('rate_limit');
        });
    }

    public function down(): void
    {
        Schema::table('api_keys', function (Blueprint $table) {
            $table->dropColumn(['scopes', 'rate_limit', 'allowed_ips']);
        });
    }
};
