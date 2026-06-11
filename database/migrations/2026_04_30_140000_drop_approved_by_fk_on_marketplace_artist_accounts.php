<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The original `approved_by` FK pointed at `users.id`, but the marketplace
 * panel authenticates through the `marketplace_admin` guard (a separate
 * `marketplace_admins` table). When a marketplace admin approves or
 * creates an artist account, `auth()->id()` returns the marketplace_admin
 * id — which doesn't exist in `users`, blowing up the insert with a
 * 23503 FK violation.
 *
 * We drop the FK constraint and keep `approved_by` as a plain nullable
 * bigint. Future enhancement (V2): make this polymorphic with an
 * `approved_by_type` column, so we can join back to either users or
 * marketplace_admins.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('marketplace_artist_accounts')) {
            return;
        }

        Schema::table('marketplace_artist_accounts', function (Blueprint $table) {
            // Drop the FK only — column stays.
            try {
                $table->dropForeign(['approved_by']);
            } catch (\Throwable $e) {
                // Idempotent: if the FK was never created (fresh install
                // running both migrations together) the drop is a no-op.
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('marketplace_artist_accounts')) {
            return;
        }

        // Best-effort restore. Will fail if data already references rows
        // that aren't in `users` (which is exactly the scenario this
        // migration was created to support), so re-adding is intentional
        // skipped on rollback unless the operator manually cleans up.
        Schema::table('marketplace_artist_accounts', function (Blueprint $table) {
            try {
                $table->foreign('approved_by')->references('id')->on('users')->nullOnDelete();
            } catch (\Throwable $e) {
                // No-op — see comment above.
            }
        });
    }
};
