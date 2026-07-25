<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Convert tenant_event_categories.name/description from json to jsonb.
 *
 * Postgres cannot run SELECT DISTINCT over a json column ("could not identify an
 * equality operator for type json"), which is exactly what Filament's
 * belongsToMany relationship Select emits when resolving option labels for the
 * event edit form → 500. jsonb has equality operators, so DISTINCT works.
 *
 * pgsql-only: on sqlite/mysql json already behaves fine for this query.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tenant_event_categories')) {
            return;
        }
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE tenant_event_categories ALTER COLUMN name TYPE jsonb USING name::jsonb');
        DB::statement('ALTER TABLE tenant_event_categories ALTER COLUMN description TYPE jsonb USING description::jsonb');
    }

    public function down(): void
    {
        if (! Schema::hasTable('tenant_event_categories')) {
            return;
        }
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE tenant_event_categories ALTER COLUMN name TYPE json USING name::json');
        DB::statement('ALTER TABLE tenant_event_categories ALTER COLUMN description TYPE json USING description::json');
    }
};
