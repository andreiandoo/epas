<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The legacy single-domain column on tenants is no longer the
 * authoritative source — domains are managed via the separate
 * `domains` table (one tenant → many rows there). The Filament
 * /admin/tenants/create form collects new_domains[] in a repeater
 * and writes them to the domains table after save, but doesn't
 * touch the legacy column. That broke tenant creation outright
 * with a NOT NULL violation (`null value in column "domain" of
 * relation "tenants"`).
 *
 * Making the column nullable lets new tenants be created without
 * a primary domain immediately, while the CreateTenant page is
 * updated separately to mirror the primary new_domain into this
 * legacy column where possible (so older code paths that still
 * read tenant.domain keep working — ClientEarnings, MarketplaceClient
 * ConfigController).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('domain', 190)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('domain', 190)->nullable(false)->change();
        });
    }
};
