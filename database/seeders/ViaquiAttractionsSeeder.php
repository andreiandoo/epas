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
        foreach (['country', 'wikidata_id', 'popularity', 'description_credit', 'facts', 'is_unesco'] as $column) {
            if (! DB::getSchemaBuilder()->hasColumn('attractions', $column)) {
                throw new \RuntimeException("Column attractions.{$column} is missing: run php artisan migrate first.");
            }
        }

        if (! DB::getSchemaBuilder()->hasColumn('marketplace_cities', 'image_credit')) {
            throw new \RuntimeException('Column marketplace_cities.image_credit is missing: run php artisan migrate first.');
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
                [$cityImage, $cityCredit] = $this->commonsPhoto($c);
                $cityRows[] = [
                    'image_url' => $cityImage, 'image_credit' => $cityCredit,
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
            // a photo for the small places that are already there without one (never over a photo set in the admin)
            $noPhoto = DB::table('marketplace_cities')->where('marketplace_client_id', self::CLIENT_ID)->where('country', $country)->whereNull('image_url')->pluck('slug')->flip();
            $withPhoto = array_values(array_filter($cityRows, fn ($r) => $r['image_url'] && isset($noPhoto[$r['slug']])));
            foreach (array_chunk($withPhoto, 500) as $chunk) {
                DB::table('marketplace_cities')->upsert($chunk, ['marketplace_client_id', 'slug'], ['image_url', 'image_credit', 'updated_at']);
            }
            $cityId = DB::table('marketplace_cities')->where('marketplace_client_id', self::CLIENT_ID)->where('country', $country)->pluck('id', 'slug');

            // ------------------------------------------------------------ attractions
            $rows = [];
            foreach ($data['attractions'] as $a) {
                [$cover, $credit] = $this->commonsPhoto($a);
                // Interim description: the introduction of the English Wikipedia article, with the credit CC BY-SA asks for
                $description = ! empty($a['x']) ? json_encode(['en' => $a['x']], JSON_UNESCAPED_UNICODE) : null;
                $descriptionCredit = ! empty($a['x']) ? json_encode([
                    'source' => 'Wikipedia', 'source_url' => 'https://en.wikipedia.org/wiki/' . rawurlencode(str_replace(' ', '_', $a['w'])),
                    'license' => 'CC BY-SA 4.0', 'license_url' => 'https://creativecommons.org/licenses/by-sa/4.0/',
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null;
                // Facts the data files carry beyond the basics (plans/viaqui-data/build_facts.py); only what is known is stored.
                $facts = array_filter([
                    'types' => $a['ts'] ?? null, 'website' => $a['web'] ?? null, 'year' => $a['yr'] ?? null,
                    'styles' => $a['sty'] ?? null, 'architects' => $a['arch'] ?? null, 'visitors' => $a['vis'] ?? null,
                    'native_name' => $a['nat'] ?? null, 'hours' => $a['hrs'] ?? null, 'pageviews' => $a['pv'] ?? null,
                    'also_in' => $a['also'] ?? null,
                    'gallery' => ! empty($a['gal']) ? array_map(fn ($g) => [
                        'url' => 'https://commons.wikimedia.org/wiki/Special:FilePath/' . rawurlencode($g[0]) . '?width=1280',
                        'author' => $g[1], 'license' => $g[2], 'license_url' => $g[3],
                        'source_url' => 'https://commons.wikimedia.org/wiki/File:' . rawurlencode(str_replace(' ', '_', $g[0])),
                    ], $a['gal']) : null,
                ], fn ($v) => $v !== null && $v !== [] && $v !== '');
                $gallery = ! empty($a['gal'])
                    ? array_map(fn ($g) => 'https://commons.wikimedia.org/wiki/Special:FilePath/' . rawurlencode($g[0]) . '?width=1280', $a['gal'])
                    : null;
                $popularity = (int) ($a['pop'] ?? $a['k']);
                $rows[] = [
                    'facts' => $facts ? json_encode($facts, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
                    'is_unesco' => ! empty($a['u']),
                    'gallery' => $gallery ? json_encode($gallery, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
                    'address' => $a['adr'] ?? null,
                    'marketplace_client_id' => self::CLIENT_ID, 'slug' => mb_substr($a['s'], 0, 191),
                    'name' => json_encode(['en' => $a['n']], JSON_UNESCAPED_UNICODE),
                    'subtitle' => ! empty($a['d']) ? json_encode(['en' => $a['d']], JSON_UNESCAPED_UNICODE) : null,
                    'attraction_type_id' => $typeId[$a['t']] ?? null,
                    'marketplace_city_id' => ! empty($a['city']) ? ($cityId[$a['city']] ?? null) : null,
                    'latitude' => $a['la'], 'longitude' => $a['lo'],
                    'cover_image_url' => $cover, 'cover_image_credit' => $credit,
                    'description' => $description, 'description_credit' => $descriptionCredit,
                    'country' => $country, 'wikidata_id' => $a['q'], 'popularity' => $popularity,
                    // better-known places first inside the curated order (featured, then sort_order)
                    'sort_order' => max(0, 1000 - $popularity),
                    'is_featured' => false, 'is_visible' => true, 'created_at' => $now, 'updated_at' => $now,
                ];
            }
            // An attraction is the same one as long as its Wikidata item is: when a newer file gives it another slug (names
            // follow the English Wikipedia title since 2026-10-07), the row moves to the new slug instead of staying behind
            // as a second copy. Two steps, through a temporary slug, so that rows swapping slugs do not collide.
            $slugsOf = [];
            foreach ($rows as $r) {
                $slugsOf[$r['wikidata_id']][$r['slug']] = true;
            }
            $current = DB::table('attractions')->where('marketplace_client_id', self::CLIENT_ID)->where('country', $country)
                ->whereNotNull('wikidata_id')->get(['id', 'slug', 'wikidata_id']);
            $held = [];
            foreach ($current as $row) {
                if (isset($slugsOf[$row->wikidata_id][$row->slug])) {
                    $held[$row->wikidata_id][$row->slug] = true;
                }
            }
            $moves = [];
            $claimed = [];
            foreach ($current as $row) {
                if (! isset($slugsOf[$row->wikidata_id]) || isset($slugsOf[$row->wikidata_id][$row->slug])) {
                    continue;
                }
                foreach (array_keys($slugsOf[$row->wikidata_id]) as $target) {
                    if (! isset($held[$row->wikidata_id][$target]) && ! isset($claimed[$target])) {
                        $claimed[$target] = true;
                        $moves[$row->id] = $target;
                        break;
                    }
                }
            }
            if ($moves) {
                // a target slug may still belong to another row of this client (another country's file, or a row added by hand)
                $busy = [];
                foreach (array_chunk(array_values($moves), 1000) as $chunk) {
                    $busy += DB::table('attractions')->where('marketplace_client_id', self::CLIENT_ID)->whereIn('slug', $chunk)->pluck('id', 'slug')->all();
                }
                $moves = array_filter($moves, fn ($target) => ! isset($busy[$target]) || isset($moves[$busy[$target]]));
                foreach ($moves as $id => $target) {
                    DB::table('attractions')->where('id', $id)->update(['slug' => 'moving-' . $id]);
                }
                foreach ($moves as $id => $target) {
                    DB::table('attractions')->where('id', $id)->update(['slug' => $target]);
                }
            }
            $moved = count($moves);

            foreach (array_chunk($rows, 500) as $chunk) {
                DB::table('attractions')->upsert($chunk, ['marketplace_client_id', 'slug'],
                    ['name', 'subtitle', 'attraction_type_id', 'marketplace_city_id', 'latitude', 'longitude', 'country', 'wikidata_id', 'popularity', 'sort_order',
                        'facts', 'is_unesco', 'updated_at']);
            }
            // Rows that existed before keep what they have, except where it is empty or was itself imported: a description
            // is filled in where there is none or where the one in place came from Wikipedia, a cover only where there is none.
            $existing = DB::table('attractions')->where('marketplace_client_id', self::CLIENT_ID)->where('country', $country)
                ->get(['slug', 'description', 'description_credit', 'cover_image_url'])->keyBy('slug');
            $textRows = array_values(array_filter($rows, function ($r) use ($existing) {
                $e = $existing[$r['slug']] ?? null;
                return $e && $r['description'] && ($e->description === null || $e->description_credit !== null) && $e->description !== $r['description'];
            }));
            foreach (array_chunk($textRows, 500) as $chunk) {
                DB::table('attractions')->upsert($chunk, ['marketplace_client_id', 'slug'], ['description', 'description_credit', 'updated_at']);
            }
            $more = DB::table('attractions')->where('marketplace_client_id', self::CLIENT_ID)->where('country', $country)->get(['slug', 'gallery', 'address'])->keyBy('slug');
            $empty = fn ($v) => $v === null || $v === '' || $v === '[]' || $v === 'null';
            foreach (['gallery', 'address'] as $column) {
                $fill = array_values(array_filter($rows, fn ($r) => $r[$column] && isset($more[$r['slug']]) && $empty($more[$r['slug']]->{$column})));
                foreach (array_chunk($fill, 500) as $chunk) {
                    DB::table('attractions')->upsert($chunk, ['marketplace_client_id', 'slug'], [$column, 'updated_at']);
                }
            }
            $coverRows = array_values(array_filter($rows, fn ($r) => $r['cover_image_url'] && isset($existing[$r['slug']]) && $existing[$r['slug']]->cover_image_url === null));
            foreach (array_chunk($coverRows, 500) as $chunk) {
                DB::table('attractions')->upsert($chunk, ['marketplace_client_id', 'slug'], ['cover_image_url', 'cover_image_credit', 'updated_at']);
            }

            // ------------------------------------------------------------ what an earlier file held and this one dropped
            // The rules of the data files change (thresholds, which small places earn a page). Rows that came from an
            // import (they carry a wikidata_id) and are no longer in the file are removed, unless an experience is
            // linked to them; small places created by an import that hold nothing any more are removed too.
            // Judged by slug, not by Wikidata id: a row left behind under an older slug of an item the file still holds
            // is a second copy of it, and goes as well.
            $inFile = array_flip(array_column($rows, 'slug'));
            $staleIds = DB::table('attractions')->where('marketplace_client_id', self::CLIENT_ID)->where('country', $country)
                ->whereNotNull('wikidata_id')->pluck('slug', 'id')->reject(fn ($slug) => isset($inFile[$slug]))->keys();
            $removed = 0;
            foreach ($staleIds->chunk(1000) as $chunk) {
                $linked = DB::table('activity_attraction')->whereIn('attraction_id', $chunk)->pluck('attraction_id');
                $removed += DB::table('attractions')->whereIn('id', $chunk->diff($linked))->delete();
            }
            $keepSmall = array_flip(array_column($data['extra_cities'], 's'));
            $smallIds = DB::table('marketplace_cities')->where('marketplace_client_id', self::CLIENT_ID)->where('country', $country)
                ->where('sort_order', 100000)->pluck('slug', 'id')->reject(fn ($slug) => isset($keepSmall[$slug]))->keys();
            $removedCities = 0;
            foreach ($smallIds->chunk(1000) as $chunk) {
                $used = DB::table('attractions')->whereIn('marketplace_city_id', $chunk)->distinct()->pluck('marketplace_city_id');
                foreach (['activities' => 'marketplace_city_id', 'activity_locations' => 'marketplace_city_id', 'events' => 'marketplace_city_id'] as $table => $column) {
                    if (DB::getSchemaBuilder()->hasColumn($table, $column)) {
                        $used = $used->merge(DB::table($table)->whereIn($column, $chunk)->distinct()->pluck($column));
                    }
                }
                $removedCities += DB::table('marketplace_cities')->whereIn('id', $chunk->diff($used))->delete();
            }

            echo sprintf("%s: %d attractions (%d with a description), %d small places, %d with a photo; %d moved to a new address; removed %d attractions and %d small places no longer in the file.\n",
                $country, count($rows), count(array_filter($rows, fn ($r) => $r['description'])), count($cityRows), count(array_filter($rows, fn ($r) => $r['cover_image_url'])), $moved, $removed, $removedCities);
        }

        echo 'Viaqui attractions in the database: ' . DB::table('attractions')->where('marketplace_client_id', self::CLIENT_ID)->whereNull('deleted_at')->count() . "\n";
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
