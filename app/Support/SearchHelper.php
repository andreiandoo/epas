<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class SearchHelper
{
    /**
     * Apply accent-insensitive, case-insensitive search on a column.
     * Uses PostgreSQL full-text search when available for better performance.
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

        return $query->whereRaw(
            "LOWER({$column}) LIKE LOWER(?)",
            ["%{$search}%"]
        );
    }

    /**
     * Search on a JSONB translatable column across multiple locales.
     * Uses unaccent + LIKE for flexible matching.
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
                $q->orWhereRaw(
                    "unaccent(lower({$column}::text)) LIKE unaccent(lower(?))",
                    ["%{$search}%"]
                );
            });
        }

        return $query->where(function ($q) use ($column, $search, $locales) {
            foreach ($locales as $locale) {
                $q->orWhereRaw(
                    "LOWER(JSON_UNQUOTE(JSON_EXTRACT({$column}, '$.{$locale}'))) LIKE LOWER(?)",
                    ["%{$search}%"]
                );
            }
        });
    }

    /**
     * Full-text search using PostgreSQL tsvector.
     * Falls back to LIKE on MySQL.
     * Use this for large tables where performance matters (events, artists, customers).
     *
     * @param string $ftsExpression The tsvector expression matching the index, e.g.:
     *        "immutable_unaccent(coalesce(title->>'en', '') || ' ' || coalesce(title->>'ro', ''))"
     */
    public static function fulltext(Builder $query, string $ftsExpression, string $search, ?string $likeFallbackColumn = null): Builder
    {
        $search = trim($search);
        if (empty($search)) return $query;

        if (DB::getDriverName() === 'pgsql') {
            // Split search into words for tsquery
            $tsQuery = implode(' & ', array_filter(array_map(
                fn ($word) => preg_replace('/[^a-zA-Z0-9\x{00C0}-\x{024F}]/u', '', $word),
                explode(' ', $search)
            )));

            if (empty($tsQuery)) {
                // Fallback to LIKE if no valid words
                return $query->whereRaw(
                    "unaccent(lower({$ftsExpression})) LIKE unaccent(lower(?))",
                    ["%{$search}%"]
                );
            }

            return $query->where(function ($q) use ($ftsExpression, $tsQuery, $search) {
                // Full-text match (fast, uses index)
                $q->whereRaw(
                    "to_tsvector('simple', immutable_unaccent({$ftsExpression})) @@ to_tsquery('simple', immutable_unaccent(?))",
                    [$tsQuery]
                )
                // Also LIKE for partial word matches
                ->orWhereRaw(
                    "unaccent(lower({$ftsExpression})) LIKE unaccent(lower(?))",
                    ["%{$search}%"]
                );
            });
        }

        // MySQL fallback
        if ($likeFallbackColumn) {
            return $query->where($likeFallbackColumn, 'like', "%{$search}%");
        }

        return $query;
    }
}
