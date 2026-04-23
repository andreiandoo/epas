<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * PostgreSQL refuses DISTINCT / GROUP BY on `json` columns (no equality
 * operator). Filament's Select::relationship for artists in EventResource
 * runs `select distinct "artists".*` as part of the pivot-ordered query,
 * which now includes the newly added `achievements` and `discography`
 * columns and blows up. Switching to `jsonb` fixes equality without any
 * data migration (jsonb accepts the same json text) and keeps the model
 * 'array' cast untouched.
 *
 * Also nudges existing JSON columns on artists to jsonb for consistency
 * and so any future relationship query picks up jsonb too.
 */
return new class extends Migration {
    protected array $columns = [
        'bio_html',
        'youtube_videos',
        'booking_agency',
        'achievements',
        'discography',
    ];

    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        foreach ($this->columns as $col) {
            if (!Schema::hasColumn('artists', $col)) {
                continue;
            }
            DB::statement(sprintf(
                'ALTER TABLE artists ALTER COLUMN %s TYPE jsonb USING %s::jsonb',
                $col,
                $col
            ));
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        foreach ($this->columns as $col) {
            if (!Schema::hasColumn('artists', $col)) {
                continue;
            }
            DB::statement(sprintf(
                'ALTER TABLE artists ALTER COLUMN %s TYPE json USING %s::json',
                $col,
                $col
            ));
        }
    }
};
