<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Countries, regions and cities of Europe for Viaqui (marketplace client 4).
 *
 * Data: resources/data/europe/viaqui-places.json.gz, built from GeoNames (CC BY 4.0) by
 * plans/viaqui-data/build_places.py: Europe without Russia and Belarus, cities from 10,000 inhabitants plus every
 * national capital, English names, slugs unique across the set.
 *
 * Writes:
 *   geo_countries        one row per country (global table). Existing rows keep their names; only empty fields are filled.
 *   marketplace_regions  first-level regions of each country, for client 4.
 *   marketplace_cities   the cities, for client 4: country, region, coordinates, population, time zone.
 *                        sort_order is the population rank, so "top cities" lists come out sensibly.
 *
 * Idempotent: rows are matched on (marketplace_client_id, slug) and updated in place; pictures, descriptions and the
 * featured flag set later in the admin are not touched. Nothing is ever deleted.
 *
 *   php artisan db:seed --class="Database\Seeders\ViaquiEuropePlacesSeeder"
 */
class ViaquiEuropePlacesSeeder extends Seeder
{
    private const CLIENT_ID = 4;

    // The destinations that lead menus and lists until the owner curates them in the admin. Applied only while the
    // marketplace has no featured city at all, so a later choice made in the admin is never overwritten.
    private const LEAD_CITIES = [
        'rome', 'paris', 'barcelona', 'london', 'vienna', 'prague', 'lisbon', 'amsterdam', 'florence', 'venice', 'athens', 'madrid',
        'berlin', 'budapest', 'bucharest', 'dublin', 'copenhagen', 'brussels', 'krakow', 'porto', 'seville', 'munich', 'milan', 'naples',
        'edinburgh', 'stockholm', 'brasov', 'salzburg', 'dubrovnik', 'nice',
    ];

    public function run(): void
    {
        $file = resource_path('data/europe/viaqui-places.json.gz');
        $data = json_decode((string) gzdecode((string) file_get_contents($file)), true);
        if (! is_array($data) || empty($data['cities'])) {
            throw new \RuntimeException("Cannot read {$file}");
        }
        if (! DB::table('marketplace_clients')->where('id', self::CLIENT_ID)->exists()) {
            throw new \RuntimeException('Marketplace client ' . self::CLIENT_ID . ' does not exist.');
        }
        $now = now();

        // ---------------------------------------------------------------- countries (global reference table)
        $known = DB::table('geo_countries')->get()->keyBy('iso2');
        foreach ($data['countries'] as $c) {
            $row = $known[$c['code']] ?? null;
            if (! $row) {
                DB::table('geo_countries')->insert([
                    'iso2' => $c['code'], 'iso3' => $c['iso3'], 'name_native' => $c['name'], 'name_en' => $c['name'],
                    'phone_code' => $c['phone'] ? mb_substr($c['phone'], 0, 8) : null, 'is_active' => true,
                    'sort_order' => 100 + $c['sort'], 'created_at' => $now, 'updated_at' => $now,
                ]);
                continue;
            }
            $fill = array_filter([
                'iso3' => $row->iso3 ? null : $c['iso3'],
                'name_en' => $row->name_en ? null : $c['name'],
            ]);
            if ($fill) {
                DB::table('geo_countries')->where('id', $row->id)->update($fill + ['updated_at' => $now]);
            }
        }

        // ---------------------------------------------------------------- regions
        $regionRows = [];
        foreach ($data['regions'] as $i => $r) {
            $regionRows[] = [
                'marketplace_client_id' => self::CLIENT_ID, 'slug' => $r['slug'],
                'name' => json_encode(['en' => $r['name']], JSON_UNESCAPED_UNICODE),
                'code' => $r['code'], 'country' => $r['country'], 'sort_order' => $i + 1, 'is_visible' => true,
                'city_count' => $r['cities'], 'created_at' => $now, 'updated_at' => $now,
            ];
        }
        foreach (array_chunk($regionRows, 200) as $chunk) {
            DB::table('marketplace_regions')->upsert($chunk, ['marketplace_client_id', 'slug'], ['name', 'code', 'country', 'sort_order', 'city_count', 'updated_at']);
        }
        $regionIdBySlug = DB::table('marketplace_regions')->where('marketplace_client_id', self::CLIENT_ID)->pluck('id', 'slug');
        $regionIdByKey = [];
        foreach ($data['regions'] as $r) {
            $regionIdByKey[$r['key']] = $regionIdBySlug[$r['slug']] ?? null;
        }

        // ---------------------------------------------------------------- cities
        $cityRows = [];
        foreach ($data['cities'] as $c) {
            [$cityImage, $cityCredit] = $this->commonsPhoto($c);
            $cityRows[] = [
                'image_url' => $cityImage, 'image_credit' => $cityCredit,
                'marketplace_client_id' => self::CLIENT_ID, 'slug' => $c['s'],
                'name' => json_encode(['en' => $c['n']], JSON_UNESCAPED_UNICODE),
                'region_id' => $c['r'] ? ($regionIdByKey[$c['r']] ?? null) : null,
                'country' => $c['c'], 'latitude' => $c['la'], 'longitude' => $c['lo'], 'timezone' => $c['tz'],
                'population' => $c['p'], 'sort_order' => $c['k'], 'is_visible' => true, 'is_capital' => (bool) $c['cap'],
                'created_at' => $now, 'updated_at' => $now,
            ];
        }
        foreach (array_chunk($cityRows, 500) as $chunk) {
            DB::table('marketplace_cities')->upsert($chunk, ['marketplace_client_id', 'slug'], ['name', 'region_id', 'country', 'latitude', 'longitude', 'timezone', 'population', 'sort_order', 'is_capital', 'updated_at']);
        }

        // City photos from Wikimedia Commons: only where the city has none (never over a photo set in the admin)
        if (DB::getSchemaBuilder()->hasColumn('marketplace_cities', 'image_credit')) {
            $noPhoto = DB::table('marketplace_cities')->where('marketplace_client_id', self::CLIENT_ID)->whereNull('image_url')->pluck('slug')->flip();
            $withPhoto = array_values(array_filter($cityRows, fn ($r) => $r['image_url'] && isset($noPhoto[$r['slug']])));
            foreach (array_chunk($withPhoto, 500) as $chunk) {
                DB::table('marketplace_cities')->upsert($chunk, ['marketplace_client_id', 'slug'], ['image_url', 'image_credit', 'updated_at']);
            }
            echo count($withPhoto) . " cities received a photo.\n";
        } else {
            throw new \RuntimeException('Column marketplace_cities.image_credit is missing: run php artisan migrate first.');
        }

        if (! DB::table('marketplace_cities')->where('marketplace_client_id', self::CLIENT_ID)->where('is_featured', true)->exists()) {
            DB::table('marketplace_cities')->where('marketplace_client_id', self::CLIENT_ID)->whereIn('slug', self::LEAD_CITIES)->update(['is_featured' => true]);
        }

        $this->command?->info(sprintf('Viaqui places: %d countries, %d regions, %d cities (client %d).', count($data['countries']), count($regionRows), count($cityRows), self::CLIENT_ID));
        echo sprintf("Viaqui places: %d countries, %d regions, %d cities.\n", count($data['countries']), DB::table('marketplace_regions')->where('marketplace_client_id', self::CLIENT_ID)->count(), DB::table('marketplace_cities')->where('marketplace_client_id', self::CLIENT_ID)->count());
    }

    /** A hot-link to a 960 px rendition on Wikimedia Commons and the credit its licence asks for; [null, null] without a usable photo. */
    private function commonsPhoto(array $row): array
    {
        if (empty($row['img'])) {
            return [null, null];
        }
        $url = 'https://commons.wikimedia.org/wiki/Special:FilePath/' . rawurlencode($row['img']) . '?width=960';
        if (strlen($url) > 255) {
            return [null, null];
        }

        return [$url, json_encode([
            'author' => $row['cr'][0] ?? '', 'license' => $row['cr'][1] ?? '', 'license_url' => $row['cr'][2] ?? '',
            'source' => 'Wikimedia Commons', 'source_url' => 'https://commons.wikimedia.org/wiki/File:' . rawurlencode(str_replace(' ', '_', $row['img'])),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)];
    }
}
