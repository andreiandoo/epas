<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('facebook_capi_events') || !Schema::hasColumn('facebook_capi_events', 'event_source_url')) {
            return;
        }

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE facebook_capi_events ALTER COLUMN event_source_url TYPE TEXT');
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('facebook_capi_events') || !Schema::hasColumn('facebook_capi_events', 'event_source_url')) {
            return;
        }

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE facebook_capi_events ALTER COLUMN event_source_url TYPE VARCHAR(2048)');
        }
    }
};
