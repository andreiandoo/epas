<?php
/**
 * Builds the static pin dataset behind the interactive attractions map.
 *
 *   php bin/build-map-data.php [--force] [--paginate]
 *
 * Writes data/map/atractii.json (the payload the browser downloads once) and
 * data/map/atractii.meta.json (version + counters, read by v2_map_data()).
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

$outDir   = BILETEONLINE_ROOT . '/data/map';
$outFile  = $outDir . '/atractii.json';
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
                [, $county, $region] = $cities[$c];
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
        'fields'       => ['name', 'slug', 'type', 'city', 'zone', 'lat_e5', 'lng_e5', 'flags'],
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

// ---------------------------------------------------------------- build

fwrite(STDOUT, 'Source: ' . API_BASE_URL . ' (' . API_ENV . ")\n");

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

fwrite(STDOUT, sprintf(
    "Wrote %s\n  %d pins, %d types, %d cities, %s, v=%s\n",
    $outFile,
    $newTotal,
    count($payload['types']),
    count($payload['cities']),
    number_format(strlen($json) / 1024, 1) . ' KB',
    $version
));
