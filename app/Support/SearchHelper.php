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
     *
     * On Postgres we deliberately avoid `column->>'locale'` because some
     * translatable columns ended up created as plain text rather than
     * jsonb on production (the model still stores JSON, but the column
     * type is text), and `text->>'…'` is a hard error. A fold-and-LIKE
     * on the cast-to-text representation matches both jsonb and text
     * storage: searching for "iasi" finds the substring inside the
     * serialized {"en":"Iași",…} blob just as well as inside a real
     * jsonb extracted value.
     */
    public static function searchTranslatable(Builder $query, string $column, string $search, array $locales = ['en', 'ro']): Builder
    {
        $search = trim($search);
        if ($search === '') return $query;

        $normalized = self::normalize($search);
        $isPgsql = DB::getDriverName() === 'pgsql';

        if ($isPgsql) {
            return $query->whereRaw(
                self::foldExpr("{$column}::text") . ' LIKE ?',
                ['%' . $normalized . '%']
            );
        }

        // MySQL/MariaDB: JSON_EXTRACT works regardless of underlying column
        // type when the value is JSON, and the raw blob fold catches the
        // edge case where it isn't.
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
