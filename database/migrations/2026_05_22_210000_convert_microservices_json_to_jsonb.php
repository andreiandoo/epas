<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Postgres `json` columns block SELECT DISTINCT t.* because the type has
     * no equality operator. Filament's AttachAction on a belongsToMany builds
     * exactly such a query when listing attachable microservices, which fails
     * with `42883 could not identify an equality operator for type json` on
     * `/admin/marketplace-clients/{id}/edit` → payment methods tab.
     *
     * Converting the affected columns to `jsonb` (which DOES support equality)
     * permanently fixes the bug without changing data, model casts, or call
     * sites — jsonb is a strict superset for Eloquent's array cast.
     */
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return; // SQLite / MySQL dev environments — no-op
        }

        $columns = ['features', 'dependencies', 'metadata', 'config_schema', 'required_env_vars'];

        foreach ($columns as $column) {
            // Idempotent: only convert if the column is currently `json`.
            // Re-running on jsonb (or after manual conversion) is a no-op.
            DB::statement(<<<SQL
                DO \$\$
                BEGIN
                    IF EXISTS (
                        SELECT 1 FROM information_schema.columns
                        WHERE table_name = 'microservices'
                          AND column_name = '{$column}'
                          AND data_type = 'json'
                    ) THEN
                        ALTER TABLE microservices
                            ALTER COLUMN {$column}
                            TYPE jsonb
                            USING {$column}::jsonb;
                    END IF;
                END \$\$;
            SQL);
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        $columns = ['features', 'dependencies', 'metadata', 'config_schema', 'required_env_vars'];

        foreach ($columns as $column) {
            DB::statement(<<<SQL
                DO \$\$
                BEGIN
                    IF EXISTS (
                        SELECT 1 FROM information_schema.columns
                        WHERE table_name = 'microservices'
                          AND column_name = '{$column}'
                          AND data_type = 'jsonb'
                    ) THEN
                        ALTER TABLE microservices
                            ALTER COLUMN {$column}
                            TYPE json
                            USING {$column}::json;
                    END IF;
                END \$\$;
            SQL);
        }
    }
};
