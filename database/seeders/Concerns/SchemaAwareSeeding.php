<?php

namespace Database\Seeders\Concerns;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Helpers for seeders that must survive schema drift between environments.
 *
 * The platform's tables differ slightly across installs (columns added at
 * different times, Translatable fields stored as JSONB on one env and VARCHAR
 * on another). A seeder that hard-codes an attribute list breaks on the first
 * env that lags a migration. These helpers intersect what you WANT to write
 * with what the table actually accepts.
 *
 * Extracted from LeisureDemoSeeder, which proved the approach on Postgres.
 */
trait SchemaAwareSeeding
{
    /**
     * Keep only attributes whose columns exist and are not generated, and
     * flatten translation arrays destined for scalar columns.
     */
    protected function writableAttrs(string $table, array $attrs): array
    {
        $cols = Schema::getColumnListing($table);
        $writable = array_diff($cols, $this->generatedColumns($table));
        $filtered = array_intersect_key($attrs, array_flip($writable));

        $types = $this->columnTypes($table);
        foreach ($filtered as $col => $value) {
            if (! is_array($value)) {
                continue;
            }
            $type = $types[$col] ?? null;
            if ($type && in_array($type, ['json', 'jsonb'], true)) {
                continue;   // Eloquent casts handle these
            }
            // Scalar column holding a translation array — pick one string.
            $filtered[$col] = $value['ro'] ?? $value['en'] ?? (reset($value) ?: null);
        }
        return $filtered;
    }

    /** @return array<string,string> column_name => data_type (pgsql only) */
    protected function columnTypes(string $table): array
    {
        if (! $this->isPgsql()) {
            return [];
        }
        try {
            return DB::table('information_schema.columns')
                ->where('table_name', $table)
                ->pluck('data_type', 'column_name')
                ->toArray();
        } catch (\Throwable) {
            return [];
        }
    }

    /** @return array<int,string> generated/computed columns (pgsql only) */
    protected function generatedColumns(string $table): array
    {
        if (! $this->isPgsql()) {
            return [];
        }
        try {
            return DB::table('information_schema.columns')
                ->where('table_name', $table)
                ->whereIn('is_generated', ['ALWAYS', 'BY DEFAULT'])
                ->pluck('column_name')
                ->toArray();
        } catch (\Throwable) {
            return [];
        }
    }

    private function isPgsql(): bool
    {
        try {
            return DB::connection()->getDriverName() === 'pgsql';
        } catch (\Throwable) {
            return false;
        }
    }
}
