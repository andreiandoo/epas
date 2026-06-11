<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add a virtual/generated `is_active` column so that existing
     * `wherePivot('is_active', true)` calls keep working against
     * the new `tenant_microservices` table which uses a `status` enum.
     */
    public function up(): void
    {
        if (!Schema::hasTable('tenant_microservices')) {
            return;
        }

        // Skip if column already exists
        if (Schema::hasColumn('tenant_microservices', 'is_active')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::statement("ALTER TABLE tenant_microservices ADD COLUMN is_active BOOLEAN GENERATED ALWAYS AS (status::text = 'active') STORED");
        } elseif ($driver === 'sqlite') {
            // SQLite supports generated columns since 3.31.0
            DB::statement("ALTER TABLE tenant_microservices ADD COLUMN is_active BOOLEAN GENERATED ALWAYS AS (status = 'active') STORED");
        } else {
            // MySQL / MariaDB
            DB::statement("ALTER TABLE tenant_microservices ADD COLUMN is_active BOOLEAN GENERATED ALWAYS AS (status = 'active') STORED");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('tenant_microservices', 'is_active')) {
            Schema::table('tenant_microservices', function ($table) {
                $table->dropColumn('is_active');
            });
        }
    }
};
