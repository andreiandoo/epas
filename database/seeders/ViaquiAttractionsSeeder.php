<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Attractions of Viaqui (marketplace client 4), one data file per country.
 *
 * Data: resources/data/europe/attractions/<CC>.json.gz, built by plans/viaqui-data/build_attractions.py from
 * Wikidata (CC0). Photographs are not copied: cover_image_url points at a 960 px rendition on Wikimedia Commons
 * and cover_image_credit holds the author and the licence, which must be shown next to the photo.
 *
 * Writes, for client 4 only:
 *   attraction_types    the types (castles, museums, …), matched on slug.
 *   marketplace_cities  the small places that get a page because an attraction stands in them (below the
 *                       10,000-inhabitant threshold of the places import). Only missing ones are added.
 *   attractions         matched on slug. A second run refreshes name, type, city, coordinates and popularity;
 *                       a cover photo already set is never replaced. Nothing is deleted.
 *
 * Requires the places import (ViaquiEuropePlacesSeeder) and the migration that adds country / wikidata_id /
 * popularity to attractions.
 *
 *   run()               every country file present
 *   run(['RO', 'IT'])   only these
 */
class ViaquiAttractionsSeeder extends Seeder
{
    private const CLIENT_ID = 4;

    public function run(?array $countries = null): void
    {
        $dir = resource_path('data/europe/attractions');
        $files = glob($dir . '/*.json.gz') ?: [];
        if ($countries) {
            $wanted = array_map('strtoupper', $countries);
            $files = array_values(array_filter($files, fn ($f) => in_array(strtoupper(basename($f, '.json.gz')), $wanted, true)));
        }
        if (! $files) {
            throw new \RuntimeException("No attraction files in {$dir}");
        }
        foreach (['country', 'wikidata_id', 'popularity'] as $column) {
            if (! DB::getSchemaBuilder()->hasColumn('attractions', $column)) {
                throw new \RuntimeException("Column attractions.{$column} is missing: run php artisan migrate first.");
            }
        }

        $places = json_decode((string) gzdecode((string) file_get_contents(resource_path('data/europe/viaqui-places.json.gz'))), true);
        $regionSlugByKey = array_column($places['regions'] ?? [], 'slug', 'key');
        $regionIdBySlug = DB::table('marketplace_regions')->where('marketplace_client_id', self::CLIENT_ID)->pluck('id', 'slug');
        $now = now();

        foreach ($files as $file) {
            $data = json_decode((string) gzdecode((string) file_get_contents($file)), true);
            if (! is_array($data) || empty($data['attractions'])) {
                echo 'Skipped (unreadable): ' . basename($file) . "\n";
                continue;
            }
            $country = (string) $data['country'];

            // ------------------------------------------------------------ types
            $typeRows = [];
            foreach ($data['types'] as $t) {
                $typeRows[] = [
                    'marketplace_client_id' => self::CLIENT_ID, 'slug' => $t['slug'],
                    'name' => json_encode(['en' => $t['name']], JSON_UNESCAPED_UNICODE), 'icon_emoji' => $t['emoji'],
                    'sort_order' => $t['sort'], 'is_visible' => true, 'created_at' => $now, 'updated_at' => $now,
                ];
            }
            DB::table('attraction_types')->upsert($typeRows, ['marketplace_client_id', 'slug'], ['name', 'icon_emoji', 'sort_order', 'updated_at']);
            $typeId = DB::table('attraction_types')->where('marketplace_client_id', self::CLIENT_ID)->pluck('id', 'slug');

            // ------------------------------------------------------------ small places that hold an attraction
            $cityRows = [];
            foreach ($data['extra_cities'] as $c) {
                $cityRows[] = [
                    'marketplace_client_id' => self::CLIENT_ID, 'slug' => $c['s'],
                    'name' => json_encode(['en' => $c['n']], JSON_UNESCAPED_UNICODE),
                    'region_id' => $c['r'] ? ($regionIdBySlug[$regionSlugByKey[$c['r']] ?? ''] ?? null) : null,
                    'country' => $c['c'], 'latitude' => $c['la'], 'longitude' => $c['lo'], 'timezone' => $c['tz'],
                    'population' => $c['p'], 'sort_order' => 100000, 'is_visible' => true, 'is_capital' => false,
                    'created_at' => $now, 'updated_at' => $now,
                ];
            }
            foreach (array_chunk($cityRows, 500) as $chunk) {
                DB::table('marketplace_cities')->insertOrIgnore($chunk);
            }
            $cityId = DB::table('marketplace_cities')->where('marketplace_client_id', self::CLIENT_ID)->where('country', $country)->pluck('id', 'slug');

            // ------------------------------------------------------------ attractions
            $rows = [];
            foreach ($data['attractions'] as $a) {
                $cover = null;
                $credit = null;
                if (! empty($a['img'])) {
                    $url = 'https://commons.wikimedia.org/wiki/Special:FilePath/' . rawurlencode($a['img']) . '?width=960';
                    if (strlen($url) <= 255) {
                        $cover = $url;
                        $credit = json_encode([
                            'author' => $a['cr'][0] ?? '', 'license' => $a['cr'][1] ?? '', 'license_url' => $a['cr'][2] ?? '',
                            'source' => 'Wikimedia Commons', 'source_url' => 'https://commons.wikimedia.org/wiki/File:' . rawurlencode(str_replace(' ', '_', $a['img'])),
                        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                    }
                }
                $rows[] = [
                    'marketplace_client_id' => self::CLIENT_ID, 'slug' => mb_substr($a['s'], 0, 191),
                    'name' => json_encode(['en' => $a['n']], JSON_UNESCAPED_UNICODE),
                    'subtitle' => ! empty($a['d']) ? json_encode(['en' => $a['d']], JSON_UNESCAPED_UNICODE) : null,
                    'attraction_type_id' => $typeId[$a['t']] ?? null,
                    'marketplace_city_id' => ! empty($a['city']) ? ($cityId[$a['city']] ?? null) : null,
                    'latitude' => $a['la'], 'longitude' => $a['lo'],
                    'cover_image_url' => $cover, 'cover_image_credit' => $credit,
                    'country' => $country, 'wikidata_id' => $a['q'], 'popularity' => $a['k'],
                    // better-known places first inside the curated order (featured, then sort_order)
                    'sort_order' => max(0, 1000 - (int) $a['k']),
                    'is_featured' => false, 'is_visible' => true, 'created_at' => $now, 'updated_at' => $now,
                ];
            }
            foreach (array_chunk($rows, 500) as $chunk) {
                DB::table('attractions')->upsert($chunk, ['marketplace_client_id', 'slug'],
                    ['name', 'subtitle', 'attraction_type_id', 'marketplace_city_id', 'latitude', 'longitude', 'country', 'wikidata_id', 'popularity', 'sort_order', 'updated_at']);
            }

            echo sprintf("%s: %d attractions, %d small places, %d with a photo.\n", $country, count($rows), count($cityRows), count(array_filter($rows, fn ($r) => $r['cover_image_url'])));
        }

        echo 'Viaqui attractions in the database: ' . DB::table('attractions')->where('marketplace_client_id', self::CLIENT_ID)->whereNull('deleted_at')->count() . "\n";
    }
}
