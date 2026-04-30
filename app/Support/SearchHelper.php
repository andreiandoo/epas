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
     *
     * Both branches use the LOWER + REPLACE fold (not Postgres's unaccent
     * extension), so the search works on databases that don't have the
     * unaccent extension installed. The extension is still used by
     * fulltext() where the indexed query needs immutable_unaccent.
     */
    public static function search(Builder $query, string $column, string $search): Builder
    {
        $search = trim($search);
        if ($search === '') return $query;

        $normalized = self::normalize($search);
        $columnExpr = DB::getDriverName() === 'pgsql' ? "{$column}::text" : $column;
        return $query->whereRaw(
            self::foldExpr($columnExpr) . ' LIKE ?',
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

        $normalized = self::normalize($search);
        $isPgsql = DB::getDriverName() === 'pgsql';

        return $query->where(function ($q) use ($column, $normalized, $locales, $isPgsql) {
            foreach ($locales as $locale) {
                // Postgres: ->>'locale' returns the JSON value as text.
                // MySQL/MariaDB: JSON_UNQUOTE(JSON_EXTRACT(...)) does the same.
                $expr = $isPgsql
                    ? "{$column}->>'{$locale}'"
                    : "JSON_UNQUOTE(JSON_EXTRACT({$column}, '$.{$locale}'))";
                $q->orWhereRaw(
                    self::foldExpr($expr) . ' LIKE ?',
                    ['%' . $normalized . '%']
                );
            }
            // Safety net: also fold the raw column so values stored under a
            // locale not in the list still match.
            $rawExpr = $isPgsql ? "{$column}::text" : $column;
            $q->orWhereRaw(
                self::foldExpr($rawExpr) . ' LIKE ?',
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
