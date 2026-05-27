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
        $key = self::fold($freeText);
        if ($key === '' || ! self::tablesReady()) {
            return null;
        }
        $iso = strtoupper(trim($countryIso)) ?: 'RO';

        return GeoCounty::query()
            ->whereHas('country', fn ($q) => $q->where('iso2', $iso))
            ->where('name_ascii', $key)
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
        $key = self::fold($freeText);
        if ($key === '' || ! self::tablesReady()) {
            return null;
        }

        if ($countyId) {
            $inCounty = GeoLocality::query()
                ->where('county_id', (int) $countyId)
                ->where('name_ascii', $key)
                ->orderBy('sort_order')
                ->first();
            if ($inCounty) {
                return $inCounty;
            }
        }

        $iso = strtoupper(trim($countryIso)) ?: 'RO';

        return GeoLocality::query()
            ->whereHas('country', fn ($q) => $q->where('iso2', $iso))
            ->where('name_ascii', $key)
            ->orderBy('sort_order')
            ->first();
    }

    /** Diacritic-folded, lowercased key (mirrors the seeder's fold()). */
    public static function fold(?string $value): string
    {
        return strtolower(trim(Str::ascii((string) $value)));
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
