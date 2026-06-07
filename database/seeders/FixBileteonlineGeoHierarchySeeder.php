<?php

namespace Database\Seeders;

use App\Models\MarketplaceCity;
use App\Models\MarketplaceCounty;
use App\Models\MarketplaceRegion;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Bilete.online geo hierarchy fix.
 *
 * Background: `marketplace_regions` had been populated with the 41
 * Romanian counties as if they were macro-regions. Counties (Județe)
 * table was empty as a result, and CityResource's "Județ" dropdown had
 * no values to pick from. The user manually verified that the regions
 * table should hold ONLY the 8 historical macro-regions (Transilvania,
 * Muntenia, Moldova, Dobrogea, Banat, Oltenia, Crișana, Maramureș) and
 * counties belong in their own table.
 *
 * Strategy: this seeder is non-destructive on the city rows the user
 * has been editing for the past weeks (custom descriptions, lat/lng,
 * SEO bodies, FAQs, image_url, AND the freshly-seeded
 * getyourguide_city_id values from AddGetyourguideCityIdsSeeder). It:
 *
 *   1. Wipes marketplace_regions + marketplace_counties scoped to
 *      bilete.online ONLY (marketplace_client_id = 3). FK constraints
 *      on the cities table are `nullOnDelete` so existing city rows
 *      survive — they just get NULL region_id + county_id temporarily.
 *
 *   2. Re-seeds the 8 macro-regions in the correct table.
 *
 *   3. Re-seeds the 42 county entries (the 41 Romanian counties plus
 *      București as its own administrative unit), each linked to the
 *      right macro-region.
 *
 *   4. For every EXISTING city row, looks up which county it belongs
 *      to by matching the city's slug against the curated data from
 *      RomaniaLocationSeeder, then UPDATES only `region_id` and
 *      `county_id`. Every other column on the city is left exactly
 *      as the user typed it.
 *
 *   5. Prints a report of any cities that couldn't be auto-linked so
 *      the user can finish those in the admin (those would be cities
 *      added manually with slugs we don't have a mapping for).
 *
 * Run on the live server:
 *   php artisan db:seed --class=FixBileteonlineGeoHierarchySeeder --force
 *
 * Idempotent: re-running is safe — the wipe-and-rebuild is bounded to
 * the lookup tables, and the city updates re-link by stable slug
 * matches.
 */
class FixBileteonlineGeoHierarchySeeder extends Seeder
{
    /** Target marketplace — bilete.online's client id. */
    protected const MARKETPLACE_CLIENT_ID = 3;

    public function run(): void
    {
        $mcId = static::MARKETPLACE_CLIENT_ID;

        // The curated data lives on RomaniaLocationSeeder so we don't
        // have to maintain two copies of "what cities are in which
        // county". This class reuses the parent's protected getters.
        $dataProvider = new RomaniaLocationSeeder();

        $this->command->info("Wiping wrongly-seeded regions + empty counties for bilete.online (mc {$mcId})...");

        DB::transaction(function () use ($mcId) {
            // Both tables are scoped by marketplace_client_id so we
            // only blow away bilete.online's rows — Ambilet, Tics,
            // etc. keep whatever they have.
            //
            // FK contract: marketplace_cities.region_id and .county_id
            // both have onDelete='set null', so existing city rows
            // simply lose their fk pointers and survive the delete.
            DB::table('marketplace_counties')->where('marketplace_client_id', $mcId)->delete();
            DB::table('marketplace_regions')->where('marketplace_client_id', $mcId)->delete();
        });

        $this->command->info("Re-seeding 8 macro-regions...");
        $regions = $this->createRegionsThroughReflection($dataProvider, $mcId);
        $this->command->info("  Done: " . count($regions) . " regions inserted");

        $this->command->info("Re-seeding counties...");
        $countiesData = $this->getCountiesData($dataProvider);
        [$citySlugToCounty, $countyCount] = $this->createCounties($mcId, $regions, $countiesData);
        $this->command->info("  Done: {$countyCount} counties inserted");
        $this->command->info("  Built slug→county map for " . count($citySlugToCounty) . " known cities");

        $this->command->info("Re-linking existing city rows (region_id + county_id ONLY)...");
        [$linked, $unlinked] = $this->relinkCities($mcId, $citySlugToCounty);
        $this->command->info("  Linked:   {$linked} cities");
        $this->command->info("  Unlinked: " . count($unlinked) . " cities (no slug match in curated data)");

        if (!empty($unlinked)) {
            $this->command->warn("Cities the seeder couldn't auto-link (review in admin):");
            foreach ($unlinked as $row) {
                $this->command->line(sprintf(
                    "  - id=%d  slug=%s  name=%s",
                    $row['id'],
                    $row['slug'],
                    $row['name']
                ));
            }
        }

        $this->command->info("Updating county.city_count...");
        $this->recountCounties($mcId);

        $this->command->info("Done. Bilete.online geo hierarchy is fixed.");
    }

    /**
     * Reuses the parent seeder's protected createRegions() method. The
     * parent uses updateOrCreate, but we already wiped so they are all
     * fresh inserts. Same data, no copy-paste drift.
     *
     * Both `command` and `createRegions` are accessed via reflection so
     * we don't have to widen visibility on RomaniaLocationSeeder just
     * for one bilete.online-specific consumer.
     */
    protected function createRegionsThroughReflection(RomaniaLocationSeeder $provider, int $mcId): array
    {
        $providerReflection = new \ReflectionClass($provider);

        $cmdProp = $providerReflection->getProperty('command');
        $cmdProp->setAccessible(true);
        $cmdProp->setValue($provider, $this->command);

        $method = $providerReflection->getMethod('createRegions');
        $method->setAccessible(true);
        return $method->invoke($provider, $mcId);
    }

    /**
     * Read the curated counties + cities data from the parent seeder.
     */
    protected function getCountiesData(RomaniaLocationSeeder $provider): array
    {
        $providerReflection = new \ReflectionClass($provider);
        $method = $providerReflection->getMethod('getCountiesWithCities');
        $method->setAccessible(true);
        return $method->invoke($provider);
    }

    /**
     * Insert counties + build a slug → County map we use in the next
     * step to re-link city rows. Returns [slug-map, county-count].
     */
    protected function createCounties(int $mcId, array $regions, array $countiesData): array
    {
        $citySlugToCounty = [];
        $count = 0;

        foreach ($countiesData as $countyData) {
            $region = $regions[$countyData['region']] ?? null;

            $county = MarketplaceCounty::create([
                'marketplace_client_id' => $mcId,
                'region_id'   => $region?->id,
                'code'        => $countyData['code'],
                'name'        => $countyData['name'],
                'slug'        => Str::slug($countyData['name']['ro']),
                'country'     => 'RO',
                'sort_order'  => $countyData['sort'] ?? 0,
                'is_visible'  => true,
                'is_featured' => $countyData['featured'] ?? false,
            ]);
            $count++;

            foreach ($countyData['cities'] as $cityData) {
                $citySlug = Str::slug($cityData['name']['ro']);
                $citySlugToCounty[$citySlug] = $county;
            }
        }

        return [$citySlugToCounty, $count];
    }

    /**
     * For every existing city, find its county via slug match and
     * update ONLY region_id + county_id. Everything else on the row
     * (name, lat/lng, description, image_url, getyourguide_city_id, …)
     * is left exactly as the user has it.
     */
    protected function relinkCities(int $mcId, array $citySlugToCounty): array
    {
        $existing = MarketplaceCity::where('marketplace_client_id', $mcId)
            ->get(['id', 'slug', 'name']);

        $linked = 0;
        $unlinked = [];

        foreach ($existing as $city) {
            $county = $citySlugToCounty[$city->slug] ?? null;
            if (!$county) {
                $name = is_array($city->name)
                    ? ($city->name['ro'] ?? $city->name['en'] ?? reset($city->name) ?? '')
                    : (string) $city->name;
                $unlinked[] = ['id' => $city->id, 'slug' => $city->slug, 'name' => $name];
                continue;
            }

            // Surgical update — only the two FK columns. DB::table is
            // used instead of Eloquent ->update() so model observers,
            // translatable mutators, and updated_at touch don't fire
            // and accidentally rewrite anything else on the row.
            DB::table('marketplace_cities')
                ->where('id', $city->id)
                ->update([
                    'region_id'  => $county->region_id,
                    'county_id'  => $county->id,
                    'updated_at' => now(),
                ]);
            $linked++;
        }

        return [$linked, $unlinked];
    }

    /**
     * Fresh county.city_count from real city rows — the parent seeder
     * sets it to count($countyData['cities']) which is the CURATED
     * count, not the actual count of rows existing in the DB.
     */
    protected function recountCounties(int $mcId): void
    {
        $counts = DB::table('marketplace_cities')
            ->where('marketplace_client_id', $mcId)
            ->whereNotNull('county_id')
            ->groupBy('county_id')
            ->selectRaw('county_id, COUNT(*) as c')
            ->pluck('c', 'county_id');

        foreach ($counts as $countyId => $c) {
            DB::table('marketplace_counties')
                ->where('id', $countyId)
                ->update(['city_count' => $c]);
        }
    }
}
