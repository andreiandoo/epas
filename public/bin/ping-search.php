<?php
/**
 * viaqui.com: tell the search engines what is new. Run once a day from cron (command line only):
 *
 *   php bin/ping-search.php              the daily run
 *   php bin/ping-search.php --dry-run    show what would be sent, send nothing, keep the state as it is
 *   php bin/ping-search.php --force      run even while the site is hidden from search engines (SITE_PRELAUNCH)
 *
 * What a run does:
 *   1. Guides: every guide that is new or changed since the last run (read from the API, compared with the state
 *      file) is sent to IndexNow (Bing, Yandex, Seznam) and, when a Google service account is configured, to Google's
 *      Indexing API (at most 150 a day).
 *   2. Pages of the platform: a daily slice of the other addresses (hubs, countries, cities, routes, then the
 *      attractions, country by country) is sent to IndexNow, so the whole site goes round in turn.
 *   3. Google Search Console: the sitemap index is submitted again (the official way to say "read it again").
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
 * Note on Google: the Indexing API is documented by Google for job postings and livestreams only; for other pages it
 * may be ignored. It is used here for guides the same way it is used for events on tics.ro. The sitemap is what
 * Google officially reads.
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
$state += ['guides' => [], 'offset' => 0];

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
$toIndexNow = array_values(array_unique(array_merge($changed, $slice)));
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

$hasGoogle = (string) ($secrets['google_credentials'] ?? '') !== '' && is_file((string) $secrets['google_credentials']);
if (!$hasGoogle) {
    $say('Google: no service account in the secrets file (google_credentials), skipped.');
} elseif ($dryRun) {
    $say('Google (dry run): would submit the sitemap again and send ' . min(150, count($changed)) . ' guides to the Indexing API.');
} else {
    // the sitemap, again
    if ($token = $googleToken('https://www.googleapis.com/auth/webmasters')) {
        $sites = array_filter([(string) ($secrets['google_site'] ?? '')]) ?: ['sc-domain:' . parse_url(SITE_URL, PHP_URL_HOST), SITE_URL . '/'];
        $done = false;
        foreach ($sites as $site) {
            [$code, $out] = $http('PUT', 'https://www.googleapis.com/webmasters/v3/sites/' . rawurlencode($site) . '/sitemaps/' . rawurlencode(SITE_URL . '/sitemap.xml'), ['Authorization: Bearer ' . $token, 'Content-Length: 0']);
            $say('Search Console: sitemap submitted for ' . $site . ', answer ' . $code . ($code >= 300 ? ' ' . substr(preg_replace('/\s+/', ' ', $out), 0, 200) : '') . '.');
            if ($code >= 200 && $code < 300) {
                $done = true;
                break;
            }
        }
        if (!$done) {
            $say('Search Console: the sitemap was not accepted. Check that the service account is a user of the property and set google_site.');
        }
    }
    // the guides, one by one
    if ($changed && ($token = $googleToken('https://www.googleapis.com/auth/indexing'))) {
        $sent = 0;
        $failed = 0;
        foreach (array_slice($changed, 0, 150) as $url) {
            [$code, $out] = $http('POST', 'https://indexing.googleapis.com/v3/urlNotifications:publish', ['Authorization: Bearer ' . $token, 'Content-Type: application/json'], json_encode(['url' => $url, 'type' => 'URL_UPDATED'], JSON_UNESCAPED_SLASHES));
            if ($code >= 200 && $code < 300) {
                $sent++;
            } else {
                $failed++;
                if ($failed === 1) {
                    $say('Indexing API: answer ' . $code . ' ' . substr(preg_replace('/\s+/', ' ', $out), 0, 200));
                }
            }
        }
        $say('Indexing API: ' . $sent . ' guides sent, ' . $failed . ' refused.');
    }
}

// ------------------------------------------------------------------ remember
if (!$dryRun) {
    if ($guides) {      // an API that did not answer must not make every guide look new tomorrow
        $state['guides'] = $guides;
    }
    $state['offset'] = $total ? ($offset + $perDay) % $total : 0;
    $state['last_run'] = date('c');
    if (@file_put_contents($stateFile, json_encode($state, JSON_UNESCAPED_SLASHES)) === false) {
        $say('Could not write the state file ' . $stateFile . ': the same addresses will be sent again tomorrow.');
    }
}
$say('Done.');
