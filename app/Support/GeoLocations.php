<?php

namespace App\Support;

use App\Models\GeoCounty;
use App\Models\GeoLocality;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Read accessor over the centralized geo dataset (geo_countries /
 * geo_counties / geo_localities). Built for Filament Select options and
 * diacritic-insensitive matching of existing free-text values.
 *
 * All names returned are native-language (e.g. "București", "Argeș").
 */
class GeoLocations
{
    /**
     * County options for a country: [county_id => "Native Name"].
     * Cached — the county list is tiny and effectively static.
     */
    public static function countyOptions(string $countryIso = 'RO'): array
    {
        if (! self::tablesReady()) {
            return [];
        }
        $iso = strtoupper(trim($countryIso)) ?: 'RO';

        $cached = Cache::get("geo:counties:{$iso}");
        if (is_array($cached) && $cached !== []) {
            return $cached;
        }

        $options = GeoCounty::query()
            ->whereHas('country', fn ($q) => $q->where('iso2', $iso))
            ->orderBy('sort_order')
            ->orderBy('name_native')
            ->pluck('name_native', 'id')
            ->all();

        // Only cache a populated list — avoids pinning an empty result for
        // an hour if a page happens to load before the seeder runs.
        if ($options !== []) {
            Cache::put("geo:counties:{$iso}", $options, 3600);
        }

        return $options;
    }

    /**
     * Locality options for a county: [locality_id => "Native Name"].
     */
    public static function localityOptions(int|string|null $countyId): array
    {
        if (! $countyId || ! self::tablesReady()) {
            return [];
        }

        return GeoLocality::query()
            ->where('county_id', (int) $countyId)
            ->orderBy('name_native')
            ->pluck('name_native', 'id')
            ->all();
    }

    /**
     * Match a free-text county/state value (with or without diacritics)
     * to its canonical GeoCounty, or null if nothing matches.
     */
    public static function matchCounty(?string $freeText, string $countryIso = 'RO'): ?GeoCounty
    {
        if (! self::tablesReady()) {
            return null;
        }
        $iso = strtoupper(trim($countryIso)) ?: 'RO';
        $keys = array_unique(array_filter([
            self::normalizeForMatch($freeText, false, $iso),
            self::fold($freeText),
        ]));
        if (! $keys) {
            return null;
        }

        return GeoCounty::query()
            ->whereHas('country', fn ($q) => $q->where('iso2', $iso))
            ->whereIn('name_ascii', $keys)
            ->first();
    }

    /**
     * Match a free-text city value to its canonical GeoLocality.
     * Prefers a match within the given county; falls back to a
     * country-wide match (returns the lowest sort_order on ties, which
     * favours the county seat / main settlement).
     */
    public static function matchLocality(?string $freeText, int|string|null $countyId = null, string $countryIso = 'RO'): ?GeoLocality
    {
        if (! self::tablesReady()) {
            return null;
        }
        $iso = strtoupper(trim($countryIso)) ?: 'RO';
        // Build a small set of candidate keys (normalized + raw fold +
        // prefix-stripped variant) so robust matching catches "Bucharest",
        // "Municipiu Botoșani", "Bistrita - Nasaud" etc.
        $keys = array_values(array_unique(array_filter([
            self::normalizeForMatch($freeText, true, $iso),
            self::normalizeForMatch($freeText, false, $iso),
            self::fold($freeText),
        ])));
        if (! $keys) {
            return null;
        }

        if ($countyId) {
            $inCounty = GeoLocality::query()
                ->where('county_id', (int) $countyId)
                ->whereIn('name_ascii', $keys)
                ->orderBy('sort_order')
                ->first();
            if ($inCounty) {
                return $inCounty;
            }
        }

        return GeoLocality::query()
            ->whereHas('country', fn ($q) => $q->where('iso2', $iso))
            ->whereIn('name_ascii', $keys)
            ->orderBy('sort_order')
            ->first();
    }

    /** Diacritic-folded, lowercased key (mirrors the seeder's fold()). */
    public static function fold(?string $value): string
    {
        return strtolower(trim(Str::ascii((string) $value)));
    }

    /**
     * Count Romanian diacritic characters in a string (ă/â/î/ș/ț plus
     * cedilla legacy ş/ţ and their uppercase variants). Used to decide
     * whether a rewrite improves or degrades the spelling.
     */
    public static function countDiacritics(?string $value): int
    {
        if (! $value) {
            return 0;
        }
        $chars = ['ă', 'â', 'î', 'ș', 'ț', 'ş', 'ţ', 'Ă', 'Â', 'Î', 'Ș', 'Ț', 'Ş', 'Ţ'];
        $count = 0;
        foreach ($chars as $ch) {
            $count += substr_count($value, $ch);
        }
        return $count;
    }

    /**
     * Stronger normalization for matching: fold, then collapse whitespace
     * (incl. around hyphens), apply RO aliases (Bucharest→București),
     * optionally strip Romanian administrative prefixes ("Municipiul ",
     * "Municipiu ", "Orașul ", "Oraș "). Always keeps the original fold
     * as a fallback inside the matchers.
     */
    public static function normalizeForMatch(?string $value, bool $stripLocalityPrefix = false, string $countryIso = 'RO'): string
    {
        $key = self::fold($value);
        if ($key === '') {
            return '';
        }
        // Collapse spaces around hyphens then normalise runs of whitespace
        // so "Bistrita - Nasaud" matches "Bistrița-Năsăud" after folding.
        $key = (string) preg_replace('/\s*-\s*/', '-', $key);
        $key = (string) preg_replace('/\s+/', ' ', $key);
        $key = trim($key);

        if ($stripLocalityPrefix) {
            $key = (string) preg_replace('/^(municipiul|municipiu|orasul|oras)\s+/u', '', $key);
            $key = trim($key);
        }

        // Country-specific aliases (English / legacy spellings → canonical
        // folded form). RO only for now; extend per country as needed.
        $iso = strtoupper(trim($countryIso)) ?: 'RO';
        if ($iso === 'RO') {
            $aliases = [
                'bucharest' => 'bucuresti',
            ];
            $key = $aliases[$key] ?? $key;
        }

        return $key;
    }

    /**
     * Guard so the venue forms stay functional if the code is deployed
     * before the geo migration runs — every accessor degrades to
     * empty/null instead of throwing on a missing table. Cached per
     * request.
     */
    protected static ?bool $tablesReady = null;

    protected static function tablesReady(): bool
    {
        if (self::$tablesReady === null) {
            try {
                self::$tablesReady = Schema::hasTable('geo_counties') && Schema::hasTable('geo_localities');
            } catch (\Throwable $e) {
                self::$tablesReady = false;
            }
        }
        return self::$tablesReady;
    }
}
