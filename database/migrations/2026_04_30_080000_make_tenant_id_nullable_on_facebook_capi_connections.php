<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('facebook_capi_connections')) {
            return;
        }

        DB::statement('ALTER TABLE facebook_capi_connections ALTER COLUMN tenant_id DROP NOT NULL');
    }

    public function down(): void
    {
        // Intentionally not restoring NOT NULL: rows owned by marketplace
        // organizers (no tenant_id) would otherwise become invalid.
    }
};
