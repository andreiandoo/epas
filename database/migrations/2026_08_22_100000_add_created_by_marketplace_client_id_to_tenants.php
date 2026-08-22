<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds a soft origin marker to tenants so the Marketplace admin panel
 * (super-admin only) can create + list tenants scoped to the marketplace
 * that created them, without ever mixing them with tenants created via
 * the core Tixello admin.
 *
 * Nullable + no FK enforcement: existing rows stay untouched (all
 * pre-existing tenants read as "created via admin Tixello"), and the
 * column is treated as a lightweight scope filter, not a hard relation.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->unsignedBigInteger('created_by_marketplace_client_id')->nullable()->after('demo_parent_id');
            $table->index('created_by_marketplace_client_id', 'idx_tenants_created_by_marketplace_client_id');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropIndex('idx_tenants_created_by_marketplace_client_id');
            $table->dropColumn('created_by_marketplace_client_id');
        });
    }
};
