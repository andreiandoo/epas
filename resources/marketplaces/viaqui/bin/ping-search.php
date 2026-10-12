<?php
/**
 * viaqui.com: tell the search engines what is new. Run once a day from cron (command line only):
 *
 *   php bin/ping-search.php              the daily run
 *   php bin/ping-search.php --dry-run    show what would be sent, send nothing, keep the state as it is
 *   php bin/ping-search.php --force      run even while the site is hidden from search engines (SITE_PRELAUNCH)
 *   php bin/ping-search.php --status     say where the priority pages stand in Google (from the last runs), send nothing
 *
 * What a run does:
 *   1. Guides: every guide that is new or changed since the last run (read from the API, compared with the state
 *      file) is sent to IndexNow (Bing, Yandex, Seznam) and, when a Google service account is configured, to Google's
 *      Indexing API (at most 150 a day).
 *   2. Pages of the platform: a daily slice of the other addresses (hubs, countries, cities, routes, then the
 *      attractions, country by country) is sent to IndexNow, so the whole site goes round in turn.
 *   3. Google Search Console: the sitemap index is submitted again (the official way to say "read it again").
 *   4. Google, the priority pages (hubs, guides, countries, routes, the largest cities, the best-known attractions,
 *      about 11,000 addresses): up to 1,500 a day are looked up with the URL Inspection API, which answers whether
 *      an address is indexed, waiting, or refused and why. Those still waiting are sent to the Indexing API, the
 *      most important first, at most 190 a day together with the guides and not again for 30 days. The run ends
 *      with the count for each state and the addresses that need a look (noindex, redirect, not found).
 *
 * Settings, in the secrets file of the site (secrets.php above the web folder, see includes/config.php):
 *   'indexnow_key'       => 32 hex characters; the site then answers https://viaqui.com/<key>.txt with the key
 *   'google_credentials' => path of the service account's JSON key file (outside the web folder). The account's
 *                           e-mail must be added in Search Console > Settings > Users and permissions as Owner
 *                           (Owner is required by the Indexing API; Full is enough for the sitemap).
 *   'google_site'        => the property as Search Console names it: "sc-domain:viaqui.com" (domain property) or
 *                           "https://viaqui.com/" (URL-prefix property). Both are tried when it is not set.
 * Without a setting, its part is skipped and the run says so. The state is kept in viaqui-ping-state.json beside
 * the secrets file.
 *
 * Note on Google: nothing in its APIs does what the "Request indexing" button of Search Console does. The URL
 * Inspection API only reads the state of an address. The Indexing API is documented for job postings and livestreams
 * only; for other pages Google may ignore the notification, and it says it may withdraw access from accounts that
 * misuse it. It is used here the way it is used for events on tics.ro, within its daily allowance. The sitemap is
 * what Google officially reads.
 * The state file keeps, for each priority page: [verdict, state as Search Console words it, day sent to the Indexing
 * API, day inspected] (days counted from 1970).
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$root = dirname(__DIR__);
$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? 'viaqui.com';
$_SERVER['REQUEST_URI'] = $_SERVER['REQUEST_URI'] ?? '/';
require_once $root . '/includes/config.php';
require_once $root . '/includes/api.php';
require_once $root . '/includes/v2/helpers.php';
require_once $root . '/includes/v2/places.php';

$dryRun = in_array('--dry-run', $argv, true);
$force = in_array('--force', $argv, true);
$say = function (string $line): void { echo '[' . date('Y-m-d H:i:s') . '] ' . $line . "\n"; };

if (defined('SITE_PRELAUNCH') && SITE_PRELAUNCH && !$force) {
    $say('The site is hidden from search engines (SITE_PRELAUNCH): nothing sent. Use --force to run anyway.');
    exit(0);
}

$secrets = $GLOBALS['viaquiSecrets'] ?? [];
$stateFile = (is_file(dirname($root) . '/secrets.php') ? dirname($root) : $root . '/data') . '/viaqui-ping-state.json';
$state = is_file($stateFile) ? (json_decode((string) file_get_contents($stateFile), true) ?: []) : [];
$state += ['guides' => [], 'offset' => 0, 'indexnow_retry' => [], 'google' => []];

$http = function (string $method, string $url, array $headers = [], ?string $body = null): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_TIMEOUT => 20,
        CURLOPT_CUSTOMREQUEST => $method, CURLOPT_HTTPHEADER => $headers,
    ]);
    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    }
    $out = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return [$code, is_string($out) ? $out : ''];
};

// ------------------------------------------------------------------ 1. guides: new or changed
$guides = [];
for ($page = 1; $page <= 40; $page++) {
    $resp = api_get('/blog-articles', ['per_page' => 50, 'page' => $page, 'status' => 'published']);
    $rows = is_array($resp['data'] ?? null) ? $resp['data'] : [];
    foreach ($rows as $row) {
        if (!empty($row['slug'])) {
            // what makes a guide "changed": its publication date, title and length
            $guides[(string) $row['slug']] = md5(json_encode([$row['published_at'] ?? '', $row['title'] ?? '', $row['word_count'] ?? '', $row['updated_at'] ?? '']));
        }
    }
    if (!$rows || (int) ($resp['meta']['last_page'] ?? 1) <= $page) {
        break;
    }
}
$changed = [];
foreach ($guides as $slug => $mark) {
    if (($state['guides'][$slug] ?? '') !== $mark) {
        $changed[] = SITE_URL . '/guides/' . $slug;
    }
}
$say(count($guides) . ' published guides, ' . count($changed) . ' new or changed.');

// ------------------------------------------------------------------ 2. the daily slice of the platform's pages
$all = [SITE_URL . '/', SITE_URL . '/cities', SITE_URL . '/attractions', SITE_URL . '/experiences', SITE_URL . '/guides', SITE_URL . '/map', SITE_URL . '/routes', SITE_URL . '/plan'];
$index = v2_map_file('index');
$countries = $index['countries'] ?? [];
$cities = v2_map_file('places')['cities'] ?? [];
foreach ($countries as $slug => $c) {
    $all[] = SITE_URL . '/' . $slug;
    $all[] = SITE_URL . '/map/' . $slug;
    foreach ($cities[$c['code']] ?? [] as $citySlug) {
        $all[] = SITE_URL . '/' . $citySlug;
    }
}
foreach (array_keys(v2_routes()) as $slug) {
    $all[] = SITE_URL . '/routes/' . $slug;
}
foreach ($countries as $c) {
    $file = $root . '/assets/v2/data/map/' . strtolower($c['code']) . '.json';
    $data = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;
    $slugAt = is_array($data) ? array_search('slug', $data['fields'] ?? [], true) : false;
    if ($slugAt === false) {
        continue;
    }
    foreach ($data['points'] ?? [] as $row) {
        $all[] = SITE_URL . '/attraction/' . $row[$slugAt];
    }
    unset($data);
}
$perDay = 500;
$total = count($all);
$offset = $total ? ((int) $state['offset']) % $total : 0;
$slice = array_slice($all, $offset, $perDay);
if (count($slice) < $perDay && $total > $perDay) {
    $slice = array_merge($slice, array_slice($all, 0, $perDay - count($slice)));     // wrapped round to the start
}
$say($total . ' addresses on the platform; today ' . count($slice) . ' of them, from number ' . ($offset + 1) . '.');

// ------------------------------------------------------------------ IndexNow
$indexNowKey = (string) ($secrets['indexnow_key'] ?? '');
// with what IndexNow refused last time (it answers 403 until it has read the key file of a new site)
$toIndexNow = array_values(array_unique(array_merge((array) $state['indexnow_retry'], $changed, $slice)));
$indexNowFailed = false;
if (!preg_match('/^[a-f0-9]{16,64}$/', $indexNowKey)) {
    $say('IndexNow: no key in the secrets file (indexnow_key), skipped.');
} elseif ($dryRun) {
    $say('IndexNow (dry run): would send ' . count($toIndexNow) . ' addresses.');
} else {
    $host = (string) parse_url(SITE_URL, PHP_URL_HOST);
    [$code, $out] = $http('POST', 'https://api.indexnow.org/indexnow', ['Content-Type: application/json; charset=utf-8'], json_encode([
        'host' => $host, 'key' => $indexNowKey, 'keyLocation' => SITE_URL . '/' . $indexNowKey . '.txt', 'urlList' => $toIndexNow,
    ], JSON_UNESCAPED_SLASHES));
    $say('IndexNow: ' . count($toIndexNow) . ' addresses sent, answer ' . $code . ($code >= 300 ? ' ' . substr($out, 0, 200) : '') . '.');
    $indexNowFailed = $code < 200 || $code >= 300;
    if ($indexNowFailed) {
        $say('IndexNow: these addresses are kept and sent again at the next run.');
    }
}

// ------------------------------------------------------------------ Google: the pages worth asking for first
// In the order they are checked: hubs, guides, countries, routes, the ten largest cities of each country, Europe's
// best-known attractions, then more cities and more attractions (about 11,000 addresses; the rest of the site is
// left to the sitemaps). Paths, not full addresses: they are the keys of the state.
$priority = ['/', '/cities', '/attractions', '/guides', '/map', '/routes', '/experiences', '/plan'];
foreach (array_keys($guides) as $slug) {
    $priority[] = '/guides/' . $slug;
}
foreach ($countries as $slug => $c) {
    $priority[] = '/' . $slug;
    $priority[] = '/attractions?country=' . strtolower($c['code']);
}
foreach (array_keys(v2_routes()) as $slug) {
    $priority[] = '/routes/' . $slug;
}
$europe = v2_map_file('europe') ?? [];
$europeSlugAt = array_search('slug', $europe['fields'] ?? [], true);
$best = $europeSlugAt === false ? [] : array_map(fn ($row) => '/attraction/' . $row[$europeSlugAt], $europe['points'] ?? []);
unset($europe);
$cityRound = function (int $from, int $count) use ($countries, $cities): array {
    $out = [];
    foreach ($countries as $c) {
        foreach (array_slice($cities[$c['code']] ?? [], $from, $count) as $citySlug) {
            $out[] = '/' . $citySlug;
        }
    }
    return $out;
};
$priority = array_values(array_unique(array_merge($priority, $cityRound(0, 10), array_slice($best, 0, 1500), $cityRound(10, 30), array_slice($best, 1500))));

// What Search Console says about an address that is not in the index yet but could be: only these are worth a
// notification. Anything else that is not "indexed" (noindex, redirect, not found, blocked) is a fault to look at.
$waiting = ['Discovered - currently not indexed', 'Crawled - currently not indexed', 'URL is unknown to Google'];
$today = intdiv(time(), 86400);
$googleSummary = function () use (&$state, $priority, $waiting, $say): void {
    $by = [];
    $faults = [];
    foreach ($priority as $path) {
        $row = $state['google'][$path] ?? null;
        $row = $row && !empty($row[3]) ? $row : null;      // sent but not inspected yet counts as not checked
        $what = $row ? ($row[0] === 'PASS' ? 'Indexed' : $row[1]) : 'Not checked yet';
        $by[$what] = ($by[$what] ?? 0) + 1;
        if ($row && $row[0] !== 'PASS' && !in_array($row[1], $waiting, true) && count($faults) < 25) {
            $faults[] = $path . ' (' . $row[1] . ')';
        }
    }
    arsort($by);
    $say('Google, the ' . count($priority) . ' priority pages: ' . implode(', ', array_map(fn ($k, $n) => $n . ' ' . $k, array_keys($by), $by)) . '.');
    if ($faults) {
        $say('Google, to look at: ' . implode('; ', $faults) . '.');
    }
};
if (in_array('--status', $argv, true)) {
    $googleSummary();
    exit(0);
}

// ------------------------------------------------------------------ Google (service account)
$googleToken = function (string $scope) use ($secrets, $http, $say): ?string {
    $path = (string) ($secrets['google_credentials'] ?? '');
    if ($path === '' || !is_file($path)) {
        return null;
    }
    $sa = json_decode((string) file_get_contents($path), true);
    if (!isset($sa['client_email'], $sa['private_key'])) {
        $say('Google: the credentials file is not a service account key.');
        return null;
    }
    $b64 = fn (string $d): string => rtrim(strtr(base64_encode($d), '+/', '-_'), '=');
    $now = time();
    $segments = $b64((string) json_encode(['alg' => 'RS256', 'typ' => 'JWT'])) . '.' . $b64((string) json_encode([
        'iss' => $sa['client_email'], 'scope' => $scope, 'aud' => 'https://oauth2.googleapis.com/token', 'iat' => $now, 'exp' => $now + 3600,
    ]));
    $signature = '';
    if (!openssl_sign($segments, $signature, $sa['private_key'], OPENSSL_ALGO_SHA256)) {
        $say('Google: could not sign the request with the key.');
        return null;
    }
    [$code, $out] = $http('POST', 'https://oauth2.googleapis.com/token', ['Content-Type: application/x-www-form-urlencoded'], http_build_query([
        'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer', 'assertion' => $segments . '.' . $b64($signature),
    ]));
    $token = json_decode($out, true)['access_token'] ?? null;
    if (!$token) {
        $say('Google: no access token (answer ' . $code . ' ' . substr($out, 0, 200) . ').');
    }
    return $token ?: null;
};

// Several POST requests at a time (the inspections: one request per address, each a second or two).
$postMany = function (string $url, array $headers, array $bodies, int $parallel = 6): array {
    $answers = [];
    foreach (array_chunk($bodies, $parallel, true) as $chunk) {
        $multi = curl_multi_init();
        $handles = [];
        foreach ($chunk as $key => $body) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_TIMEOUT => 40, CURLOPT_POST => true, CURLOPT_HTTPHEADER => $headers, CURLOPT_POSTFIELDS => $body]);
            curl_multi_add_handle($multi, $ch);
            $handles[$key] = $ch;
        }
        do {
            $status = curl_multi_exec($multi, $running);
            if ($running) {
                curl_multi_select($multi, 1.0);
            }
        } while ($running && $status === CURLM_OK);
        foreach ($handles as $key => $ch) {
            $answers[$key] = [(int) curl_getinfo($ch, CURLINFO_HTTP_CODE), (string) curl_multi_getcontent($ch)];
            curl_multi_remove_handle($multi, $ch);
            curl_close($ch);
        }
        curl_multi_close($multi);
    }
    return $answers;
};

$inspectPerDay = 1500;      // Search Console allows 2,000 inspections a day for a property; the rest stays for people
$notifyPerDay = 190;        // the Indexing API allows 200 notifications a day
$recheckAfter = 7;          // days before an address that was not indexed is inspected again
$confirmAfter = 60;         // days before an indexed address is looked at again
$notifyAgainAfter = 30;     // days before the same address is sent to the Indexing API again

$hasGoogle = (string) ($secrets['google_credentials'] ?? '') !== '' && is_file((string) $secrets['google_credentials']);
if (!$hasGoogle) {
    $say('Google: no service account in the secrets file (google_credentials), skipped.');
} elseif ($dryRun) {
    $say('Google (dry run): would submit the sitemap again, inspect up to ' . $inspectPerDay . ' of the ' . count($priority) . ' priority pages and send up to ' . $notifyPerDay . ' addresses to the Indexing API (' . min($notifyPerDay, count($changed)) . ' guides first).');
} else {
    $notified = 0;
    $notify = function (string $url, string $token) use ($http, $say, &$notified): bool {
        static $refused = 0;
        [$code, $out] = $http('POST', 'https://indexing.googleapis.com/v3/urlNotifications:publish', ['Authorization: Bearer ' . $token, 'Content-Type: application/json'], json_encode(['url' => $url, 'type' => 'URL_UPDATED'], JSON_UNESCAPED_SLASHES));
        if ($code >= 200 && $code < 300) {
            $notified++;
            return true;
        }
        if ($refused++ === 0) {
            $say('Indexing API: answer ' . $code . ' ' . substr(preg_replace('/\s+/', ' ', $out), 0, 200));
        }
        return false;
    };

    // the sitemap, again
    $googleSite = '';
    $gscToken = $googleToken('https://www.googleapis.com/auth/webmasters');
    if ($gscToken) {
        $sites = array_filter([(string) ($secrets['google_site'] ?? '')]) ?: ['sc-domain:' . parse_url(SITE_URL, PHP_URL_HOST), SITE_URL . '/'];
        foreach ($sites as $site) {
            [$code, $out] = $http('PUT', 'https://www.googleapis.com/webmasters/v3/sites/' . rawurlencode($site) . '/sitemaps/' . rawurlencode(SITE_URL . '/sitemap.xml'), ['Authorization: Bearer ' . $gscToken, 'Content-Length: 0']);
            $say('Search Console: sitemap submitted for ' . $site . ', answer ' . $code . ($code >= 300 ? ' ' . substr(preg_replace('/\s+/', ' ', $out), 0, 200) : '') . '.');
            if ($code >= 200 && $code < 300) {
                $googleSite = $site;
                break;
            }
        }
        if ($googleSite === '') {
            $say('Search Console: the sitemap was not accepted. Check that the service account is a user of the property and set google_site.');
        }
    }

    // the guides that are new or changed, one by one
    $indexingToken = $googleToken('https://www.googleapis.com/auth/indexing');
    if ($changed && $indexingToken) {
        $failed = 0;
        foreach (array_slice($changed, 0, $notifyPerDay) as $url) {
            if ($notify($url, $indexingToken)) {
                $row = $state['google'][substr($url, strlen(SITE_URL))] ?? [];
                $state['google'][substr($url, strlen(SITE_URL))] = [$row[0] ?? '', $row[1] ?? '', $today, $row[3] ?? 0];
            } else {
                $failed++;
            }
        }
        $say('Indexing API: ' . $notified . ' guides sent, ' . $failed . ' refused.');
    }

    // the priority pages: ask Search Console where each one stands (URL Inspection API, read only)
    if ($gscToken && $googleSite !== '') {
        $fresh = [];
        $again = [];
        foreach ($priority as $path) {
            $row = $state['google'][$path] ?? null;
            if (!$row || empty($row[3])) {
                $fresh[] = $path;
            } elseif ($today - $row[3] >= ($row[0] === 'PASS' ? $confirmAfter : $recheckAfter)) {
                $again[] = $path;
            }
        }
        // a third of the day goes to second looks, so that the first look still reaches the end of the list
        $again = array_slice($again, 0, $fresh ? intdiv($inspectPerDay, 3) : $inspectPerDay);
        $due = array_merge($again, array_slice($fresh, 0, $inspectPerDay - count($again)));
        $checked = 0;
        $stopped = '';
        foreach (array_chunk($due, 60) as $batch) {
            $bodies = [];
            foreach ($batch as $path) {
                $bodies[$path] = json_encode(['inspectionUrl' => SITE_URL . $path, 'siteUrl' => $googleSite, 'languageCode' => 'en'], JSON_UNESCAPED_SLASHES);
            }
            foreach ($postMany('https://searchconsole.googleapis.com/v1/urlInspection/index:inspect', ['Authorization: Bearer ' . $gscToken, 'Content-Type: application/json'], $bodies) as $path => [$code, $out]) {
                $result = json_decode($out, true)['inspectionResult']['indexStatusResult'] ?? null;
                if ($code === 200 && is_array($result)) {
                    $state['google'][$path] = [(string) ($result['verdict'] ?? ''), (string) ($result['coverageState'] ?? ''), (int) ($state['google'][$path][2] ?? 0), $today];
                    $checked++;
                } elseif ($code === 429 || $code === 403 || $code === 401) {
                    $stopped = $code . ' ' . substr(preg_replace('/\s+/', ' ', $out), 0, 200);
                }
            }
            if ($stopped !== '') {
                break;
            }
        }
        $say('Search Console: ' . $checked . ' of ' . count($due) . ' pages inspected today' . ($stopped !== '' ? ', stopped at answer ' . $stopped : '') . '.');
    }

    // ... and tell the Indexing API about those that are still waiting, the most important first
    if ($indexingToken) {
        $before = $notified;
        foreach ($priority as $path) {
            if ($notified >= $notifyPerDay) {
                break;
            }
            $row = $state['google'][$path] ?? null;
            if (!$row || empty($row[3]) || $row[0] === 'PASS' || !in_array($row[1], $waiting, true) || ($row[2] && $today - $row[2] < $notifyAgainAfter)) {
                continue;
            }
            if (!$notify(SITE_URL . $path, $indexingToken)) {
                break;      // the day's allowance is used up, or the API refuses: no point in going on
            }
            $state['google'][$path][2] = $today;
        }
        $say('Indexing API: ' . ($notified - $before) . ' pages that are not indexed yet sent.');
    }
    $googleSummary();
}

// ------------------------------------------------------------------ remember
if (!$dryRun) {
    if ($guides) {      // an API that did not answer must not make every guide look new tomorrow
        $state['guides'] = $guides;
    }
    $state['offset'] = $total ? ($offset + $perDay) % $total : 0;
    $state['indexnow_retry'] = $indexNowFailed ? array_slice($toIndexNow, 0, 5000) : [];
    $state['google'] = array_intersect_key($state['google'], array_flip($priority));      // pages that left the list are forgotten
    $state['last_run'] = date('c');
    if (@file_put_contents($stateFile, json_encode($state, JSON_UNESCAPED_SLASHES)) === false) {
        $say('Could not write the state file ' . $stateFile . ': the same addresses will be sent again tomorrow.');
    }
}
$say('Done.');
