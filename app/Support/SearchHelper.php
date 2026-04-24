<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class SearchHelper
{
    /**
     * Romanian diacritic → ASCII fold map. Covers both the modern comma-below
     * forms (ș U+0219, ț U+021B) and the legacy cedilla forms (ş U+015F, ţ U+0163)
     * plus the older ASCII-only approximations used in some datasets.
     */
    protected const DIACRITIC_MAP = [
        'ă' => 'a', 'â' => 'a', 'î' => 'i',
        'ș' => 's', 'ş' => 's',
        'ț' => 't', 'ţ' => 't',
    ];

    /**
     * Normalize a user-entered search string: lowercase + strip Romanian
     * diacritics. "IAȘI" and "iasi" both become "iasi".
     */
    public static function normalize(string $value): string
    {
        $value = mb_strtolower($value, 'UTF-8');
        return str_replace(array_keys(self::DIACRITIC_MAP), array_values(self::DIACRITIC_MAP), $value);
    }

    /**
     * Wrap a SQL column expression with nested LOWER + REPLACE calls so the
     * comparison ignores case AND Romanian diacritics. Pair with
     * self::normalize() on the PHP-side search term.
     */
    protected static function foldExpr(string $columnExpr): string
    {
        $sql = "LOWER({$columnExpr})";
        foreach (self::DIACRITIC_MAP as $from => $to) {
            $sql = "REPLACE({$sql}, '{$from}', '{$to}')";
        }
        return $sql;
    }

    /**
     * Apply accent-insensitive, case-insensitive search on a plain string column.
     */
    public static function search(Builder $query, string $column, string $search): Builder
    {
        $search = trim($search);
        if ($search === '') return $query;

        if (DB::getDriverName() === 'pgsql') {
            return $query->whereRaw(
                "unaccent(lower({$column}::text)) LIKE unaccent(lower(?))",
                ['%' . $search . '%']
            );
        }

        $normalized = self::normalize($search);
        return $query->whereRaw(
            self::foldExpr($column) . ' LIKE ?',
            ['%' . $normalized . '%']
        );
    }

    /**
     * Search on a JSON translatable column (e.g. venues.name, events.title)
     * across multiple locales. Case- + accent-insensitive on both drivers.
     */
    public static function searchTranslatable(Builder $query, string $column, string $search, array $locales = ['en', 'ro']): Builder
    {
        $search = trim($search);
        if ($search === '') return $query;

        if (DB::getDriverName() === 'pgsql') {
            return $query->where(function ($q) use ($column, $search, $locales) {
                foreach ($locales as $locale) {
                    $q->orWhereRaw(
                        "unaccent(lower({$column}->>'$locale')) LIKE unaccent(lower(?))",
                        ['%' . $search . '%']
                    );
                }
                $q->orWhereRaw(
                    "unaccent(lower({$column}::text)) LIKE unaccent(lower(?))",
                    ['%' . $search . '%']
                );
            });
        }

        // MySQL: extract each locale from the JSON, then fold case + diacritics.
        // Also run the fold against the raw JSON blob so keys like "ro"/"en"
        // appearing inside serialized strings still match when the locale list
        // misses a value.
        $normalized = self::normalize($search);
        return $query->where(function ($q) use ($column, $normalized, $locales) {
            foreach ($locales as $locale) {
                $expr = "JSON_UNQUOTE(JSON_EXTRACT({$column}, '$.{$locale}'))";
                $q->orWhereRaw(
                    self::foldExpr($expr) . ' LIKE ?',
                    ['%' . $normalized . '%']
                );
            }
            $q->orWhereRaw(
                self::foldExpr($column) . ' LIKE ?',
                ['%' . $normalized . '%']
            );
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
        if ($search === '') return $query;

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
                    ['%' . $search . '%']
                );
            }

            return $query->where(function ($q) use ($ftsExpression, $tsQuery, $search) {
                // Full-text match (fast, uses index)
                $q->whereRaw(
                    "to_tsvector('simple', immutable_unaccent({$ftsExpression})) @@ to_tsquery('simple', immutable_unaccent(?))",
                    [$tsQuery]
                )
                ->orWhereRaw(
                    "unaccent(lower({$ftsExpression})) LIKE unaccent(lower(?))",
                    ['%' . $search . '%']
                );
            });
        }

        // MySQL fallback
        if ($likeFallbackColumn) {
            $normalized = self::normalize($search);
            return $query->whereRaw(
                self::foldExpr($likeFallbackColumn) . ' LIKE ?',
                ['%' . $normalized . '%']
            );
        }

        return $query;
    }
}
