<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Extend zoom_connections to support marketplace + organizer scoping,
 * mirroring the FacebookCapiConnection tri-scope pattern:
 *   - tenant_id                 (original scaffolding, tenant-scoped)
 *   - marketplace_client_id     (per-marketplace, owned by the mp admin)
 *   - marketplace_organizer_id  (per-organizer, owned by the organizer)
 *
 * Exactly one of the three should be populated per row. This is
 * enforced at the application layer (ZoomService::createConnection),
 * not by a CHECK constraint, so we keep MySQL/MariaDB portability.
 *
 * The unique(tenant_id, user_id) index becomes ambiguous once
 * tenant_id can be null — drop it. Zoom user_id is unique per external
 * Zoom account, so we replace with a plain unique index on user_id
 * scoped by whichever owner is populated. (Actually — same user_id
 * across DIFFERENT owners must remain unique because Zoom user_ids
 * are global across zoom.us. So one plain unique on user_id is the
 * right constraint.)
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('zoom_connections')) {
            // Scaffold migration never ran; skip. Wire it back before
            // running this one — DatabaseSeeder change below chains them.
            return;
        }

        Schema::table('zoom_connections', function (Blueprint $table) {
            if (! Schema::hasColumn('zoom_connections', 'marketplace_client_id')) {
                $table->unsignedBigInteger('marketplace_client_id')->nullable()->after('tenant_id')->index();
            }
            if (! Schema::hasColumn('zoom_connections', 'marketplace_organizer_id')) {
                $table->unsignedBigInteger('marketplace_organizer_id')->nullable()->after('marketplace_client_id')->index();
            }
            // Allow tenant_id to be nullable now that ownership can come from
            // the other two columns.
            try {
                $table->unsignedBigInteger('tenant_id')->nullable()->change();
            } catch (\Throwable $e) {
                // doctrine/dbal may be missing on the target env; ignore.
            }
        });

        // Drop the (tenant_id, user_id) unique — it excludes non-tenant
        // connections. Replace with a plain unique on user_id since Zoom
        // user_ids are globally unique across zoom.us.
        try {
            Schema::table('zoom_connections', function (Blueprint $table) {
                $table->dropUnique(['tenant_id', 'user_id']);
            });
        } catch (\Throwable $e) {
            // Unique may not exist on already-migrated envs.
        }

        try {
            Schema::table('zoom_connections', function (Blueprint $table) {
                $table->unique('user_id', 'zoom_connections_user_id_unique');
            });
        } catch (\Throwable $e) {
            // Already present.
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('zoom_connections')) {
            return;
        }

        try {
            Schema::table('zoom_connections', function (Blueprint $table) {
                $table->dropUnique('zoom_connections_user_id_unique');
            });
        } catch (\Throwable $e) {}

        try {
            Schema::table('zoom_connections', function (Blueprint $table) {
                $table->unique(['tenant_id', 'user_id']);
            });
        } catch (\Throwable $e) {}

        Schema::table('zoom_connections', function (Blueprint $table) {
            foreach (['marketplace_client_id', 'marketplace_organizer_id'] as $c) {
                if (Schema::hasColumn('zoom_connections', $c)) {
                    $table->dropColumn($c);
                }
            }
        });
    }
};
