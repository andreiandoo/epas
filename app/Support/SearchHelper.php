<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class SearchHelper
{
    /**
     * Apply accent-insensitive, case-insensitive search on a column.
     * Works with both plain text and JSONB translatable columns.
     */
    public static function search(Builder $query, string $column, string $search): Builder
    {
        $search = trim($search);
        if (empty($search)) return $query;

        if (DB::getDriverName() === 'pgsql') {
            return $query->whereRaw(
                "unaccent(lower({$column}::text)) LIKE unaccent(lower(?))",
                ["%{$search}%"]
            );
        }

        // MySQL fallback - basic LIKE (MySQL is accent-insensitive by default with utf8_general_ci)
        return $query->whereRaw(
            "LOWER({$column}) LIKE LOWER(?)",
            ["%{$search}%"]
        );
    }

    /**
     * Search on a JSONB translatable column across multiple locales.
     */
    public static function searchTranslatable(Builder $query, string $column, string $search, array $locales = ['en', 'ro']): Builder
    {
        $search = trim($search);
        if (empty($search)) return $query;

        if (DB::getDriverName() === 'pgsql') {
            return $query->where(function ($q) use ($column, $search, $locales) {
                foreach ($locales as $locale) {
                    $q->orWhereRaw(
                        "unaccent(lower({$column}->>'$locale')) LIKE unaccent(lower(?))",
                        ["%{$search}%"]
                    );
                }
                // Also search the full text representation
                $q->orWhereRaw(
                    "unaccent(lower({$column}::text)) LIKE unaccent(lower(?))",
                    ["%{$search}%"]
                );
            });
        }

        // MySQL fallback
        return $query->where(function ($q) use ($column, $search, $locales) {
            foreach ($locales as $locale) {
                $q->orWhereRaw(
                    "LOWER(JSON_UNQUOTE(JSON_EXTRACT({$column}, '$.{$locale}'))) LIKE LOWER(?)",
                    ["%{$search}%"]
                );
            }
        });
    }
}
