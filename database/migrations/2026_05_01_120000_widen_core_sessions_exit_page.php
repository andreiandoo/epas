<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('core_sessions') || !Schema::hasColumn('core_sessions', 'exit_page')) {
            return;
        }

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE core_sessions ALTER COLUMN exit_page TYPE TEXT');
            // Same potential overflow on landing_page (URL captured at session start).
            if (Schema::hasColumn('core_sessions', 'landing_page')) {
                DB::statement('ALTER TABLE core_sessions ALTER COLUMN landing_page TYPE TEXT');
            }
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('core_sessions')) {
            return;
        }

        if (DB::connection()->getDriverName() === 'pgsql') {
            if (Schema::hasColumn('core_sessions', 'exit_page')) {
                DB::statement('ALTER TABLE core_sessions ALTER COLUMN exit_page TYPE VARCHAR(2048)');
            }
            if (Schema::hasColumn('core_sessions', 'landing_page')) {
                DB::statement('ALTER TABLE core_sessions ALTER COLUMN landing_page TYPE VARCHAR(2048)');
            }
        }
    }
};
