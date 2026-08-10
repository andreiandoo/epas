<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Convert every json column Shorts added to jsonb (Postgres only).
 *
 * Same reason as 2026_07_25_170000_tenant_event_categories_json_to_jsonb, which
 * fixed this exact class of bug already: Postgres cannot run SELECT DISTINCT or
 * GROUP BY over a `json` column ("could not identify an equality operator for
 * type json"), and that is precisely what Filament emits when a relationship
 * Select resolves option labels — a 500 on the admin page. jsonb has equality
 * operators, so DISTINCT works, and it is indexable.
 *
 * It also removes a second trap: `json` has no LIKE operator either, which is why
 * ShortRightsGuard casts to text before matching territory codes.
 *
 * pgsql-only: on sqlite/mysql json already behaves fine for these queries.
 * Safe to run on live data — the columns hold valid JSON by construction, and
 * USING <col>::jsonb converts in place.
 */
return new class extends Migration
{
    /**
     * Column-per-table, all added by the Shorts migrations.
     *
     * @var array<string, array<int, string>>
     */
    private const COLUMNS = [
        'shorts' => ['hashtags', 'content_flags', 'territories'],
        'short_events' => ['meta'],
        'short_promotions' => ['targeting'],
    ];

    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        foreach (self::COLUMNS as $table => $columns) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            foreach ($columns as $column) {
                if (! Schema::hasColumn($table, $column)) {
                    continue;
                }

                if ($this->columnType($table, $column) !== 'json') {
                    continue;
                }

                DB::statement("ALTER TABLE {$table} ALTER COLUMN {$column} TYPE jsonb USING {$column}::jsonb");
            }
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        foreach (self::COLUMNS as $table => $columns) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            foreach ($columns as $column) {
                if (Schema::hasColumn($table, $column) && $this->columnType($table, $column) === 'jsonb') {
                    DB::statement("ALTER TABLE {$table} ALTER COLUMN {$column} TYPE json USING {$column}::json");
                }
            }
        }
    }

    private function columnType(string $table, string $column): ?string
    {
        return DB::table('information_schema.columns')
            ->where('table_name', $table)
            ->where('column_name', $column)
            ->value('data_type');
    }
};
