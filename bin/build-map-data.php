<?php
/**
 * Builds the static pin dataset behind the interactive attractions map.
 *
 *   php bin/build-map-data.php [--force] [--paginate] [--summary-only]
 *
 * Writes assets/v2/data/atractii.json (the payload the browser downloads once) and
 * assets/v2/data/atractii.meta.json (version + counters, read by v2_map_data()).
 * It lives under assets/ because the deploy webhook preserves data/ -- that folder holds
 * runtime state and is never overwritten, so a dataset there would never reach the server.
 *
 * Preferred source is the core endpoint GET /attractions/map, which returns
 * every geo-located attraction in one packed response. --paginate forces the
 * fallback that walks GET /attractions 50 rows at a time (~146 requests, run
 * concurrently) and assembles the same shape -- use it when the core does not
 * have the map endpoint yet.
 *
 * This is a build step on purpose: the site never assembles 7k pins during a
 * page request. Re-run it after an attractions import, then commit the two
 * files (the deploy webhook ships them like any other asset).
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit("CLI only\n");
}

require_once __DIR__ . '/../includes/config.php';

$argvFlags  = array_slice($argv, 1);
$forceWrite = in_array('--force', $argvFlags, true);
$paginate   = in_array('--paginate', $argvFlags, true);
$summaryOnly = in_array('--summary-only', $argvFlags, true);

$outDir   = BILETEONLINE_ROOT . '/assets/v2/data';
$outFile  = $outDir . '/atractii.json';
$sumFile  = $outDir . '/atractii.summary.json';
$metaFile = $outDir . '/atractii.meta.json';

if (!is_dir($outDir) && !@mkdir($outDir, 0755, true) && !is_dir($outDir)) {
    fwrite(STDERR, "Cannot create {$outDir}\n");
    exit(1);
}

/**
 * One GET against the core API. Longer timeout than includes/api.php (this is
 * a build, not a page render) and it waits out the 120 req/min throttle
 * instead of giving up on the first 429.
 */
function mapFetch(string $endpoint, array $params = [], int $timeout = 120, int $attempts = 4): ?array
{
    for ($i = 1; $i <= $attempts; $i++) {
        $code   = 0;
        $result = mapFetchOnce($endpoint, $params, $timeout, $i === $attempts, $code);
        if ($result !== null) {
            return $result;
        }
        // Retry only what a retry can fix: throttling, upstream errors,
        // transport failures. A 404 (endpoint not deployed) is final.
        if ($code !== 429 && $code !== 0 && $code < 500) {
            return null;
        }
        if ($i < $attempts) {
            sleep(15 * $i);
        }
    }

    return null;
}

function mapFetchOnce(string $endpoint, array $params, int $timeout, bool $loud, ?int &$code = null): ?array
{
    $url = API_BASE_URL . $endpoint . ($params ? '?' . http_build_query($params) : '');
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_HTTPHEADER     => ['Accept: application/json', 'X-API-Key: ' . API_KEY],
    ]);
    $body = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);

    if ($err !== '') {
        if ($loud) {
            fwrite(STDERR, "  ! {$endpoint}: {$err}\n");
        }

        return null;
    }
    if ($code !== 200) {
        if ($loud) {
            fwrite(STDERR, "  ! {$endpoint}: HTTP {$code}\n");
        }

        return null;
    }
    $decoded = json_decode((string) $body, true);

    return is_array($decoded) ? $decoded : null;
}

/**
 * Several GETs at once, keyed by the caller's key.
 *
 * The marketplace API is rate limited to 120 requests/minute, and the
 * fallback needs ~146 of them, so batches are paced against a rolling window
 * and anything that still comes back empty (429 or a blip) is retried with
 * backoff. array_slice with preserve_keys, not array_splice: splice renumbers
 * the batch and the page numbers would be lost.
 */
function mapFetchMany(array $jobs, int $concurrency = 12, int $perMinute = 100, int $attempts = 3): array
{
    $out   = [];
    $queue = $jobs;
    $sent  = []; // timestamps of the requests in the current rolling window

    for ($attempt = 1; $attempt <= $attempts && $queue; $attempt++) {
        if ($attempt > 1) {
            fwrite(STDOUT, "\n  retry " . $attempt . ' for ' . count($queue) . " page(s)\n  ");
            sleep(20);
        }
        $failed = [];

        while ($queue) {
            $batch = array_slice($queue, 0, $concurrency, true);
            foreach (array_keys($batch) as $k) {
                unset($queue[$k]);
            }

            // Pace: wait until the last 60s hold room for this batch.
            $sent = array_values(array_filter($sent, fn ($t) => $t > microtime(true) - 60));
            if (count($sent) + count($batch) > $perMinute) {
                $wait = (int) ceil(61 - (microtime(true) - $sent[0]));
                if ($wait > 0) {
                    fwrite(STDOUT, "[pause {$wait}s]");
                    sleep($wait);
                }
                $sent = [];
            }

            $mh      = curl_multi_init();
            $handles = [];
            foreach ($batch as $key => $job) {
                $sent[] = microtime(true);
                $url = API_BASE_URL . $job['endpoint'] . (!empty($job['params']) ? '?' . http_build_query($job['params']) : '');
                $ch = curl_init();
                curl_setopt_array($ch, [
                    CURLOPT_URL            => $url,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_CONNECTTIMEOUT => 10,
                    CURLOPT_TIMEOUT        => 60,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_SSL_VERIFYPEER => true,
                    CURLOPT_HTTPHEADER     => ['Accept: application/json', 'X-API-Key: ' . API_KEY],
                ]);
                $handles[$key] = $ch;
                curl_multi_add_handle($mh, $ch);
            }
            do {
                $status = curl_multi_exec($mh, $running);
                if ($running) {
                    curl_multi_select($mh, 1.0);
                }
            } while ($running && $status === CURLM_OK);

            foreach ($handles as $key => $ch) {
                $body = curl_multi_getcontent($ch);
                $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_multi_remove_handle($mh, $ch);
                curl_close($ch);
                $decoded = $code === 200 ? json_decode((string) $body, true) : null;
                if (is_array($decoded)) {
                    $out[$key] = $decoded;
                } else {
                    $failed[$key] = $batch[$key];
                }
            }
            curl_multi_close($mh);
            fwrite(STDOUT, '.');
        }

        $queue = $failed;
    }

    foreach (array_keys($queue) as $key) {
        $out[$key] = null;
    }
    fwrite(STDOUT, "\n");

    return $out;
}

/** slug => [name, county, region] for every city the marketplace knows. */
function mapCityIndex(): array
{
    $index = [];
    $page  = 1;
    $last  = 1;
    do {
        $resp = mapFetch('/locations/cities', ['per_page' => 50, 'page' => $page], 30);
        $rows = is_array($resp['data'] ?? null) ? $resp['data'] : [];
        foreach ($rows as $c) {
            $slug = (string) ($c['slug'] ?? '');
            if ($slug === '') {
                continue;
            }
            $index[$slug] = [
                (string) ($c['name'] ?? $slug),
                is_array($c['county'] ?? null) ? (string) ($c['county']['name'] ?? '') : null,
                isset($c['region']) ? (string) $c['region'] : null,
            ];
        }
        $last = (int) ($resp['meta']['last_page'] ?? 1);
        $page++;
    } while ($page <= $last && $page <= 20);

    return $index;
}

/** Walk GET /attractions and assemble the same payload the core map endpoint returns. */
function mapBuildByPagination(): ?array
{
    $first = mapFetch('/attractions', ['per_page' => 50, 'page' => 1], 30);
    $total = (int) ($first['data']['pagination']['total'] ?? 0);
    $last  = (int) ($first['data']['pagination']['last_page'] ?? 0);
    if ($total === 0 || $last === 0) {
        fwrite(STDERR, "Pagination fallback: /attractions returned nothing\n");

        return null;
    }
    // Cities first: only a handful of requests, and doing them before the
    // 146-page burst keeps them out of the throttled window.
    $cityMeta = mapCityIndex();

    fwrite(STDOUT, "  paginating {$total} attractions over {$last} pages\n  ");

    $jobs = [];
    for ($p = 2; $p <= $last; $p++) {
        $jobs[$p] = ['endpoint' => '/attractions', 'params' => ['per_page' => 50, 'page' => $p]];
    }
    $pages = [1 => $first] + mapFetchMany($jobs);
    ksort($pages);

    $types   = [];
    $typeIdx = [];
    $cities  = [];
    $cityIdx = [];
    $zones   = [];
    $zoneIdx = [];
    $points  = [];
    $missing = 0;

    foreach ($pages as $page => $resp) {
        $items = $resp['data']['items'] ?? null;
        if (!is_array($items)) {
            fwrite(STDERR, "  ! page {$page} missing\n");
            $missing++;
            continue;
        }
        foreach ($items as $a) {
            $lat = $a['latitude'] ?? null;
            $lng = $a['longitude'] ?? null;
            if (!is_numeric($lat) || !is_numeric($lng)) {
                continue;
            }

            $t = -1;
            if (is_array($a['type'] ?? null) && !empty($a['type']['slug'])) {
                $ts = (string) $a['type']['slug'];
                if (!isset($typeIdx[$ts])) {
                    $typeIdx[$ts] = count($types);
                    $types[]      = [$ts, (string) ($a['type']['name'] ?? $ts), $a['type']['icon'] ?? null, null, 0];
                }
                $t = $typeIdx[$ts];
            }

            $c = -1;
            $z = -1;
            if (is_array($a['city'] ?? null) && !empty($a['city']['slug'])) {
                $cs = (string) $a['city']['slug'];
                if (!isset($cityIdx[$cs])) {
                    $meta         = $cityMeta[$cs] ?? [(string) ($a['city']['name'] ?? $cs), null, null];
                    $cityIdx[$cs] = count($cities);
                    $cities[]     = [$cs, $meta[0], $meta[1], $meta[2], 0];
                }
                $c = $cityIdx[$cs];

                // Zone (county + region) is only as good as /locations/cities
                // here -- it only lists a few hundred of the cities the
                // attractions reference. The core map endpoint reads the
                // county off the attraction itself and covers all of them.
                [, , $county, $region] = $cities[$c];
                if ($county) {
                    $zk = $county . '|' . (string) $region;
                    if (!isset($zoneIdx[$zk])) {
                        $zoneIdx[$zk] = count($zones);
                        $zones[]      = [$county, $region, 0];
                    }
                    $z = $zoneIdx[$zk];
                }
            }

            $flags = 0;
            if (!empty($a['cover_image_url'])) {
                $flags |= 1;
            }
            if (!empty($a['is_featured'])) {
                $flags |= 2;
            }
            if ((int) ($a['activities_count'] ?? 0) > 0) {
                $flags |= 4;
            }

            $points[] = [
                (string) ($a['name'] ?? ''),
                (string) ($a['slug'] ?? ''),
                $t,
                $c,
                $z,
                (int) round(((float) $lat) * 100000),
                (int) round(((float) $lng) * 100000),
                $flags,
                (string) ($a['cover_image_url'] ?? ''),
            ];

            if ($t >= 0) {
                $types[$t][4]++;
            }
            if ($c >= 0) {
                $cities[$c][4]++;
            }
            if ($z >= 0) {
                $zones[$z][2]++;
            }
        }
    }

    if ($missing > 0) {
        fwrite(STDERR, "Pagination fallback: {$missing} page(s) failed, refusing a partial dataset\n");

        return null;
    }

    return [
        'v'            => substr(sha1(count($points) . '|' . $total), 0, 12),
        'generated_at' => date('c'),
        'fields'       => ['name', 'slug', 'type', 'city', 'zone', 'lat_e5', 'lng_e5', 'flags', 'img'],
        'flags'        => ['image' => 1, 'featured' => 2, 'activities' => 4],
        'type_fields'  => ['slug', 'name', 'emoji', 'color', 'count'],
        'city_fields'  => ['slug', 'name', 'county', 'region', 'count'],
        'zone_fields'  => ['county', 'region', 'count'],
        'types'        => $types,
        'cities'       => $cities,
        'zones'        => $zones,
        'points'       => $points,
        'total'        => count($points),
    ];
}

/**
 * Everything on the marketplace that can actually be booked — the experiences and the leisure
 * locations — resolved into the same shape the planner uses for a stop, so a plan can mix "go and
 * look at this" with "and here is a thing you can buy a ticket for".
 *
 * The catalogue is thin here on purpose: this reads whatever exists, and writes an empty list when
 * nothing does. Coordinates are the weak point — the activities list carries none, so the builder
 * asks each activity's detail for its venue, and falls back to the centre of its city, flagged as
 * approximate so the planner and the page can say so.
 *
 * Row: [kind, slug, title, city, citySlug, county, lat, lng, approx, priceCents, minutes, img, category]
 */
function mapBookables(array $payload): array
{
    $f = array_flip($payload['fields']);

    // City centres, averaged from the attractions we already have, for the rows without a venue.
    $sums = [];
    foreach ($payload['points'] as $p) {
        $ci = $p[$f['city']];
        if ($ci < 0) {
            continue;
        }
        $slug = $payload['cities'][$ci][0];
        $sums[$slug] = $sums[$slug] ?? [0, 0, 0, $payload['cities'][$ci][2] ?? null];
        $sums[$slug][0] += $p[$f['lat_e5']] / 100000;
        $sums[$slug][1] += $p[$f['lng_e5']] / 100000;
        $sums[$slug][2]++;
    }
    $centre = [];
    foreach ($sums as $slug => $s) {
        $centre[$slug] = [round($s[0] / $s[2], 5), round($s[1] / $s[2], 5), $s[3]];
    }

    $out = [];

    // ---- experiences
    $page = 1;
    do {
        $resp = mapFetch('/activities', ['per_page' => 50, 'page' => $page], 30);
        $items = $resp['data']['items'] ?? [];
        foreach ($items as $a) {
            $slug = (string) ($a['slug'] ?? '');
            if ($slug === '') {
                continue;
            }
            $citySlug = (string) (($a['city'] ?? [])['slug'] ?? '');
            $cityName = (string) (($a['city'] ?? [])['name'] ?? '');

            // The list has no coordinates; the detail carries the venue's.
            $lat = null;
            $lng = null;
            $detail = mapFetch('/activities/' . rawurlencode($slug), [], 20, 2);
            $venue = $detail['data']['activity']['venue'] ?? ($detail['data']['venue'] ?? null);
            if (is_array($venue) && is_numeric($venue['lat'] ?? null) && is_numeric($venue['lng'] ?? null)) {
                $lat = round((float) $venue['lat'], 5);
                $lng = round((float) $venue['lng'], 5);
            }
            $approx = 0;
            if ($lat === null && isset($centre[$citySlug])) {
                [$lat, $lng] = $centre[$citySlug];
                $approx = 1;
            }
            if ($lat === null) {
                continue;   // nowhere to put it on a map, so it cannot be a stop
            }

            $out[] = [
                'activity', $slug, (string) ($a['title'] ?? $slug), $cityName, $citySlug,
                $centre[$citySlug][2] ?? '', $lat, $lng, $approx,
                (int) ($a['cheapest_price_cents'] ?? 0), (int) ($a['duration_minutes'] ?? 0),
                (string) ($a['cover_image_url'] ?? ''), (string) (($a['category'] ?? [])['name'] ?? ''),
            ];
        }
        $last = (int) ($resp['data']['pagination']['last_page'] ?? 1);
        $page++;
    } while ($page <= $last && $page <= 10);

    // ---- leisure locations
    $page = 1;
    do {
        $resp = mapFetch('/activities-module/locations', ['per_page' => 50, 'page' => $page], 30);
        $items = $resp['data']['items'] ?? [];
        foreach ($items as $l) {
            $slug = (string) ($l['slug'] ?? '');
            if ($slug === '') {
                continue;
            }
            $citySlug = (string) (($l['city'] ?? [])['slug'] ?? '');
            $cityName = (string) (($l['city'] ?? [])['name'] ?? '');
            $lat = is_numeric($l['latitude'] ?? null) ? round((float) $l['latitude'], 5) : null;
            $lng = is_numeric($l['longitude'] ?? null) ? round((float) $l['longitude'], 5) : null;
            $approx = 0;
            if ($lat === null && isset($centre[$citySlug])) {
                [$lat, $lng] = $centre[$citySlug];
                $approx = 1;
            }
            if ($lat === null) {
                continue;
            }
            $counts = $l['counts'] ?? [];
            $out[] = [
                'location', $slug, (string) ($l['name'] ?? $slug), $cityName, $citySlug,
                $centre[$citySlug][2] ?? '', $lat, $lng, $approx,
                (int) ($l['min_price_cents'] ?? 0),
                90,   // a leisure location is a visit, not a timed activity
                (string) ($l['cover_image'] ?? ''),
                (string) (($l['category'] ?? [])['name'] ?? ''),
                array_sum(array_map('intval', is_array($counts) ? $counts : [])),
            ];
        }
        $last = (int) ($resp['data']['pagination']['last_page'] ?? 1);
        $page++;
    } while ($page <= $last && $page <= 10);

    return $out;
}

/**
 * The editorial routes of includes/v2/map-routes.php, resolved against the dataset: every stop
 * gets its real name, city, type and coordinates, and the route gets the straight-line distance
 * between its stops and the box that contains them.
 *
 * A stop the dataset does not have is a typo in the route file, so the build stops rather than
 * shipping a route with a hole in it.
 */
/**
 * Road distances and the real driving line for one route, from the FOSSGIS OSRM instance.
 *
 * One request per route at build time -- never at page render -- so the service sees a dozen calls
 * when the routes change and nothing in between. Returns null when routing is unavailable, and the
 * caller falls back to straight-line distances and says so on the page.
 */
function mapRoadRoute(array $coords): ?array
{
    $pairs = [];
    foreach ($coords as [$lat, $lng]) {
        $pairs[] = $lng . ',' . $lat;   // OSRM takes lng,lat
    }
    $url = 'https://routing.openstreetmap.de/routed-car/route/v1/driving/' . implode(';', $pairs)
        . '?overview=simplified&geometries=polyline&annotations=false&steps=false';

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT        => 45,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_USERAGENT      => 'bilete.online route builder (' . SUPPORT_EMAIL . ')',
    ]);
    $body = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);

    if ($code !== 200) {
        fwrite(STDERR, '  ! routing: HTTP ' . $code . ' ' . $err . "
");

        return null;
    }
    $d = json_decode((string) $body, true);
    $r = $d['routes'][0] ?? null;
    if (($d['code'] ?? '') !== 'Ok' || !is_array($r) || count($r['legs'] ?? []) !== count($coords) - 1) {
        fwrite(STDERR, "  ! routing: unusable answer
");

        return null;
    }

    $legs = [[0.0, 0]];   // the first stop has nothing before it
    foreach ($r['legs'] as $l) {
        $legs[] = [round($l['distance'] / 1000, 1), (int) round($l['duration'] / 60)];
    }

    return [
        'km'       => (int) round($r['distance'] / 1000),
        'min'      => (int) round($r['duration'] / 60),
        'legs'     => $legs,
        'geometry' => (string) $r['geometry'],
    ];
}

function mapRoutes(array $payload): array
{
    // Reuse the road data already computed for an unchanged route, so a summary rebuild does not
    // call the routing service again.
    $prev = [];
    $prevFile = BILETEONLINE_ROOT . '/assets/v2/data/atractii.summary.json';
    if (is_file($prevFile)) {
        $old = json_decode((string) file_get_contents($prevFile), true);
        $prev = is_array($old['routes'] ?? null) ? $old['routes'] : [];
    }

    $routesFile = BILETEONLINE_ROOT . '/includes/v2/map-routes.php';
    if (!is_file($routesFile)) {
        return [];
    }
    require_once $routesFile;

    $f      = array_flip($payload['fields']);
    $types  = $payload['types'];
    $cities = $payload['cities'];
    $zones  = $payload['zones'];

    $bySlug = [];
    foreach ($payload['points'] as $p) {
        $bySlug[$p[$f['slug']]] = $p;
    }

    $km = function (float $aLat, float $aLng, float $bLat, float $bLng): float {
        $r = M_PI / 180;
        $dLat = ($bLat - $aLat) * $r;
        $dLng = ($bLng - $aLng) * $r;
        $x = sin($dLat / 2) ** 2 + cos($aLat * $r) * cos($bLat * $r) * sin($dLng / 2) ** 2;

        return 12742 * asin(min(1, sqrt($x)));
    };

    $out = [];
    foreach (MAP_ROUTES as $slug => $route) {
        $stops = [];
        $prev = null;
        $total = 0.0;
        $lats = [];
        $lngs = [];

        foreach ($route['stops'] as $i => $stopSlug) {
            if (!isset($bySlug[$stopSlug])) {
                fwrite(STDERR, "Route {$slug}: no attraction with slug {$stopSlug}\n");
                exit(1);
            }
            $p = $bySlug[$stopSlug];
            $lat = $p[$f['lat_e5']] / 100000;
            $lng = $p[$f['lng_e5']] / 100000;
            $t = $p[$f['type']] >= 0 ? $types[$p[$f['type']]] : null;
            $c = $p[$f['city']] >= 0 ? $cities[$p[$f['city']]] : null;
            $z = $p[$f['zone']] >= 0 ? $zones[$p[$f['zone']]] : null;

            $leg = $prev === null ? 0.0 : $km($prev[0], $prev[1], $lat, $lng);
            $total += $leg;
            $prev = [$lat, $lng];
            $lats[] = $lat;
            $lngs[] = $lng;

            $stops[] = [
                $stopSlug,
                $p[$f['name']],
                $c ? $c[1] : '',
                $c ? $c[0] : '',
                $z ? $z[0] : '',
                $t ? $t[1] : '',
                $t ? $t[2] : '',
                round($lat, 5),
                round($lng, 5),
                $p[$f['img']],
                round($leg, 1),
            ];
        }

        $coords = array_map(fn ($st) => [$st[7], $st[8]], $stops);
        $roadKey = substr(sha1(json_encode($coords)), 0, 12);
        $road = null;
        if (($prev[$slug]['road_key'] ?? null) === $roadKey && isset($prev[$slug]['road'])) {
            $road = $prev[$slug]['road'];
        } elseif (count($coords) > 1) {
            fwrite(STDOUT, '  routing ' . $slug . ' ... ');
            $road = mapRoadRoute($coords);
            fwrite(STDOUT, $road ? $road['km'] . " km
" : "straight line
");
            sleep(1);   // one request per route, spaced out; the service is a community one
        }
        if ($road) {
            foreach ($stops as $i => $st) {
                $stops[$i][10] = $road['legs'][$i][0] ?? 0;
                $stops[$i][11] = $road['legs'][$i][1] ?? 0;
            }
        } else {
            foreach ($stops as $i => $st) {
                $stops[$i][11] = 0;
            }
        }

        $out[$slug] = [
            'stops'    => $stops,
            'count'    => count($stops),
            'road'     => $road,
            'road_key' => $roadKey,
            'km'       => $road ? $road['km'] : (int) round($total),
            'straight' => (int) round($total),
            'photos'   => count(array_filter($stops, fn ($s) => $s[9] !== '')),
            'counties' => array_values(array_unique(array_filter(array_column($stops, 4)))),
            'bounds'   => [round(min($lats), 5), round(min($lngs), 5), round(max($lats), 5), round(max($lngs), 5)],
        ];
    }

    return $out;
}

/**
 * Per-landing counters and picks, one entry per /harta/{slug} page: the attractions of one type,
 * or of one historical region. Without these every landing would print the same cities and the
 * same 36 photos, which is the definition of a doorway page.
 *
 * Keys are the URL slugs from includes/v2/map-landings.php; the builder does not need to know the
 * copy, only how to slice the dataset.
 */
function mapLandings(array $payload): array
{
    $f      = array_flip($payload['fields']);
    $types  = $payload['types'];
    $cities = $payload['cities'];
    $zones  = $payload['zones'];

    // URL slug => [kind, key]. Mirrors MAP_LANDINGS in includes/v2/map-landings.php.
    $defs = [
        'castele'              => ['type', 'castel-palat'],
        'muzee'                => ['type', 'muzeu'],
        'monumente'            => ['type', 'monument'],
        'biserici-si-manastiri' => ['type', 'biserica-manastire'],
        'parcuri-si-gradini'   => ['type', 'parc-gradina'],
        'cladiri-istorice'     => ['type', 'cladire-istorica'],
        'teatre-si-opere'      => ['type', 'teatru-opera'],
        'lacuri-si-natura'     => ['type', 'lac-natura'],
        'transilvania'         => ['region', 'Transilvania'],
        'muntenia'             => ['region', 'Muntenia'],
        'moldova'              => ['region', 'Moldova'],
        'banat'                => ['region', 'Banat'],
        'oltenia'              => ['region', 'Oltenia'],
        'dobrogea'             => ['region', 'Dobrogea'],
        'crisana'              => ['region', 'Crișana'],
        'maramures'            => ['region', 'Maramureș'],
    ];

    $typeIndexBySlug = array_flip(array_column($types, 0));
    $out = [];

    foreach ($defs as $slug => [$kind, $key]) {
        $keep = null;
        if ($kind === 'type') {
            $ti = $typeIndexBySlug[$key] ?? null;
            if ($ti === null) {
                continue;
            }
            $keep = fn ($p) => $p[$f['type']] === $ti;
        } else {
            $zoneIds = [];
            foreach ($zones as $zi => $z) {
                if (($z[1] ?? null) === $key) {
                    $zoneIds[$zi] = true;
                }
            }
            if (!$zoneIds) {
                continue;
            }
            $keep = fn ($p) => isset($zoneIds[$p[$f['zone']]]);
        }

        $total    = 0;
        $byCity   = [];
        $byZone   = [];
        $byType   = [];
        $withPic  = [];
        $noPic    = [];
        foreach ($payload['points'] as $p) {
            if (!$keep($p)) {
                continue;
            }
            $total++;
            if ($p[$f['city']] >= 0) {
                $byCity[$p[$f['city']]] = ($byCity[$p[$f['city']]] ?? 0) + 1;
            }
            if ($p[$f['zone']] >= 0) {
                $byZone[$p[$f['zone']]] = ($byZone[$p[$f['zone']]] ?? 0) + 1;
            }
            if ($p[$f['type']] >= 0) {
                $byType[$p[$f['type']]] = ($byType[$p[$f['type']]] ?? 0) + 1;
            }
            if ($p[$f['img']]) {
                $withPic[] = $p;
            } elseif (count($noPic) < 400) {
                $noPic[] = $p;
            }
        }
        if ($total === 0) {
            continue;
        }

        arsort($byCity);
        arsort($byZone);
        arsort($byType);

        $cityRows = [];
        foreach (array_slice($byCity, 0, 14, true) as $ci => $n) {
            $cityRows[] = [$cities[$ci][0], $cities[$ci][1], $cities[$ci][2], $cities[$ci][3], $n];
        }
        $zoneRows = [];
        foreach (array_slice($byZone, 0, 10, true) as $zi => $n) {
            $zoneRows[] = [$zones[$zi][0], $zones[$zi][1], $n];
        }
        $typeRows = [];
        foreach (array_slice($byType, 0, 10, true) as $ti2 => $n) {
            $typeRows[] = [$types[$ti2][0], $types[$ti2][1], $types[$ti2][2], $n];
        }

        // Four types have no cover photo at all (parks, historic buildings, theatres, lakes), and
        // an empty grid helps nobody -- those fall back to rows without a photo, which the page
        // renders with the brand-line placeholder. The links are the point.
        $pool = count($withPic) >= 12 ? $withPic : array_merge($withPic, $noPic);

        // A type landing spreads its picks over as many cities as it can. A region landing spreads
        // them over types instead: half of Transylvania is churches, so picking by city alone gave
        // it almost the same 18 rows as the churches landing.
        if ($kind === 'region') {
            $buckets = [];
            foreach ($pool as $p) {
                $buckets[$p[$f['type']]][] = $p;
            }
            uasort($buckets, fn ($a, $b) => count($b) <=> count($a));
            $ordered = [];
            for ($round = 0; $round < 60; $round++) {
                $any = false;
                foreach ($buckets as $bucket) {
                    if (isset($bucket[$round])) {
                        $ordered[] = $bucket[$round];
                        $any = true;
                    }
                }
                if (!$any) {
                    break;
                }
            }
            $pool = $ordered;
        }

        $picks = [];
        $seenCity = [];
        foreach ([true, false] as $onePerCity) {
            foreach ($pool as $p) {
                if (count($picks) >= 18) {
                    break 2;
                }
                if (isset($picks[$p[$f['slug']]])) {
                    continue;
                }
                $ci = $p[$f['city']];
                if ($onePerCity && $ci >= 0 && isset($seenCity[$ci])) {
                    continue;
                }
                $seenCity[$ci] = true;
                $t = $p[$f['type']] >= 0 ? $types[$p[$f['type']]] : null;
                $c = $ci >= 0 ? $cities[$ci] : null;
                $picks[$p[$f['slug']]] = [$p[$f['slug']], $p[$f['name']], $t ? $t[1] : '', $t ? $t[2] : '', $c ? $c[1] : '', $c ? $c[0] : '', $p[$f['img']]];
            }
        }

        $out[$slug] = [
            'kind'     => $kind,
            'key'      => $key,
            'total'    => $total,
            'photos'   => count($withPic),
            'cities'   => $cityRows,
            'zones'    => $zoneRows,
            'types'    => $typeRows,
            'picks'    => array_values($picks),
        ];
    }

    return $out;
}

/**
 * A few KB of counters and hand-picked rows derived from the full payload, so /harta can render
 * real server-side content (type cards, regions, cities, a grid of attractions) without parsing
 * the ~950 KB pin file on every request.
 */
function mapSummary(array $payload): array
{
    $f        = array_flip($payload['fields']);
    $types    = $payload['types'];
    $cities   = $payload['cities'];
    $zones    = $payload['zones'];
    $imageBit = (int) ($payload['flags']['image'] ?? 1);
    $actBit   = (int) ($payload['flags']['activities'] ?? 4);

    $regions = [];
    foreach ($zones as $z) {
        if (!$z[1]) {
            continue;
        }
        $regions[$z[1]] = ($regions[$z[1]] ?? 0) + (int) $z[2];
    }
    arsort($regions);

    $countyRows = [];
    foreach ($zones as $z) {
        if ((int) $z[2] > 0) {
            $countyRows[] = [$z[0], $z[1], (int) $z[2]];
        }
    }
    usort($countyRows, fn ($a, $b) => $b[2] <=> $a[2]);

    $cityRows = [];
    foreach ($cities as $c) {
        if ((int) $c[4] > 0) {
            $cityRows[] = [$c[0], $c[1], $c[2], $c[3], (int) $c[4]];
        }
    }
    usort($cityRows, fn ($a, $b) => $b[4] <=> $a[4]);

    $typeRows = [];
    foreach ($types as $t) {
        if ((int) $t[4] > 0) {
            $typeRows[] = [$t[0], $t[1], $t[2], (int) $t[4]];
        }
    }
    usort($typeRows, fn ($a, $b) => $b[3] <=> $a[3]);

    // Picks for the grid: only attractions that have a photo (a photo means somebody curated the
    // row), bookable ones first, taken round-robin per type so one type cannot fill the grid.
    $typeSlugs = array_column($types, 0);
    $byType    = [];
    foreach ($payload['points'] as $p) {
        if (!($p[$f['flags']] & $imageBit)) {
            continue;
        }
        $byType[$p[$f['type']]][] = $p;
    }
    foreach ($byType as &$bucket) {
        usort($bucket, fn ($a, $b) => ($b[$f['flags']] & $actBit) <=> ($a[$f['flags']] & $actBit));
    }
    unset($bucket);

    $order = [];
    foreach ($typeRows as $t) {
        $i = array_search($t[0], $typeSlugs, true);
        if ($i !== false) {
            $order[] = $i;
        }
    }

    $picks = [];
    for ($round = 0; count($picks) < 36 && $round < 40; $round++) {
        $added = false;
        foreach ($order as $ti) {
            if (!isset($byType[$ti][$round])) {
                continue;
            }
            $p = $byType[$ti][$round];
            $t = $p[$f['type']] >= 0 ? $types[$p[$f['type']]] : null;
            $c = $p[$f['city']] >= 0 ? $cities[$p[$f['city']]] : null;
            $picks[] = [$p[$f['slug']], $p[$f['name']], $t ? $t[1] : '', $t ? $t[2] : '', $c ? $c[1] : '', $c ? $c[0] : '', $p[$f['img']]];
            $added = true;
            if (count($picks) >= 36) {
                break;
            }
        }
        if (!$added) {
            break;
        }
    }

    return [
        'v'        => $payload['v'] ?? '',
        'total'    => count($payload['points']),
        'types'    => $typeRows,
        'regions'  => array_map(fn ($n, $c) => [$n, $c], array_keys($regions), array_values($regions)),
        'counties' => array_slice($countyRows, 0, 24),
        'cities'   => array_slice($cityRows, 0, 40),
        'picks'    => $picks,
        'landings' => mapLandings($payload),
        'routes'   => mapRoutes($payload),
        'bookables' => mapBookables($payload),
    ];
}

// ---------------------------------------------------------------- build

fwrite(STDOUT, 'Source: ' . API_BASE_URL . ' (' . API_ENV . ")\n");

// --summary-only rebuilds just the derived summary from the dataset already on disk, for when the
// picks or the counters change but the pins did not (the full build costs ~146 throttled requests).
if ($summaryOnly) {
    $existing = is_file($outFile) ? json_decode((string) file_get_contents($outFile), true) : null;
    if (!is_array($existing['points'] ?? null)) {
        fwrite(STDERR, "No dataset at {$outFile} to summarise
");
        exit(1);
    }
    file_put_contents($sumFile, json_encode(mapSummary($existing), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    fwrite(STDOUT, 'Wrote ' . $sumFile . ' (' . number_format(filesize($sumFile) / 1024, 1) . " KB)
");
    exit(0);
}

$payload = null;
$source  = 'paginate';
if (!$paginate) {
    fwrite(STDOUT, "Trying /attractions/map ...\n");
    $resp = mapFetch('/attractions/map');
    if (is_array($resp['data']['points'] ?? null)) {
        $payload = $resp['data'];
        $source  = 'map-endpoint';
        fwrite(STDOUT, '  got ' . count($payload['points']) . " pins in one call\n");
    } else {
        fwrite(STDOUT, "  not available, falling back to pagination\n");
    }
}
if ($payload === null) {
    $payload = mapBuildByPagination();
}
if ($payload === null || empty($payload['points'])) {
    fwrite(STDERR, "Build failed, keeping the existing dataset\n");
    exit(1);
}

// A collapse in the point count almost always means a broken upstream, not a
// real deletion -- keep the previous file unless --force says otherwise.
$previous  = is_file($metaFile) ? json_decode((string) file_get_contents($metaFile), true) : null;
$prevTotal = (int) ($previous['total'] ?? 0);
$newTotal  = count($payload['points']);
if (!$forceWrite && $prevTotal > 0 && $newTotal < $prevTotal * 0.8) {
    fwrite(STDERR, "Refusing to write: {$newTotal} pins vs {$prevTotal} before (use --force)\n");
    exit(1);
}

$json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
if ($json === false) {
    fwrite(STDERR, 'json_encode failed: ' . json_last_error_msg() . "\n");
    exit(1);
}

$version = substr(sha1($json), 0, 12);
$meta = [
    'v'            => $version,
    'total'        => $newTotal,
    'types'        => count($payload['types']),
    'cities'       => count($payload['cities']),
    'bytes'        => strlen($json),
    'generated_at' => date('c'),
    'source'       => $source,
];

if (file_put_contents($outFile, $json) === false) {
    fwrite(STDERR, "Cannot write {$outFile}\n");
    exit(1);
}
file_put_contents($metaFile, json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . "\n");
file_put_contents($sumFile, json_encode(mapSummary($payload), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

fwrite(STDOUT, sprintf(
    "Wrote %s\n  %d pins, %d types, %d cities, %s, v=%s\n",
    $outFile,
    $newTotal,
    count($payload['types']),
    count($payload['cities']),
    number_format(strlen($json) / 1024, 1) . ' KB',
    $version
));
