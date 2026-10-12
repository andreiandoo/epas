<?php
/**
 * The sitemaps of viaqui.com.
 *
 *   /sitemap.xml                   the index: one entry for each file below
 *   /sitemap-pages.xml             hubs, the categories that have something to list, attractions by country, maps,
 *                                  planner, routes, plus venues and experiences sold online
 *   /sitemap-places.xml            countries and cities
 *   /sitemap-guides.xml            the published guides, each with the date it was published
 *   /sitemap-attractions-{cc}.xml  the attractions of one country (the largest holds about 17,000, under the 50,000 limit)
 *
 * Countries, cities, attractions and routes are read from the static data the map uses (assets/v2/data/map, written by
 * plans/viaqui-data/build_map_data.py), so listing 113,000 attractions costs no request to the API. Venues and
 * experiences come from the API (GET /activities-module/locations and /activities), paged by 50 and cached an hour.
 *
 * <lastmod> is the day the data behind a page last changed: for countries, cities and attractions the day their data
 * file changed in the repository (includes/v2/lastmod.json, written at build time by deploy-viaqui.yml from the git
 * history; the file's own date when that list is missing), for guides, venues and experiences the date the API gives.
 * A page with no such date carries none: a made-up date teaches a search engine to ignore all of them.
 *
 * While the site is hidden from search engines (SITE_PRELAUNCH) the sitemaps still answer; robots.txt keeps crawlers out.
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/api.php';
require_once __DIR__ . '/includes/v2/helpers.php';
require_once __DIR__ . '/includes/v2/places.php';

$smIndex = v2_map_file('index');
$smCountries = $smIndex['countries'] ?? [];
$smPart = preg_match('/^[a-z][a-z-]{1,40}$/', (string) ($_GET['part'] ?? '')) ? (string) $_GET['part'] : '';

header('Content-Type: application/xml; charset=utf-8');
header('Cache-Control: public, max-age=3600');
$smLoc = fn (string $path) => htmlspecialchars(SITE_URL . $path, ENT_XML1 | ENT_QUOTES, 'UTF-8');

// The day a data file of the map last changed ('it' => 2026-10-08), '' when the file is missing.
$smDates = is_file(__DIR__ . '/includes/v2/lastmod.json') ? (json_decode((string) file_get_contents(__DIR__ . '/includes/v2/lastmod.json'), true) ?: []) : [];
$smDate = function (string $name) use ($smDates): string {
    $rel = 'assets/v2/data/map/' . $name . '.json';
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($smDates[$rel] ?? ''))) {
        return $smDates[$rel];
    }
    $at = @filemtime(__DIR__ . '/' . $rel);
    return $at ? date('Y-m-d', $at) : '';
};
$smApiDate = function (array $row): string {
    $when = strtotime((string) ($row['updated_at'] ?? $row['published_at'] ?? ''));
    return $when ? date('Y-m-d', $when) : '';
};

// ------------------------------------------------------------------ the index
if ($smPart === '') {
    echo '<?xml version="1.0" encoding="UTF-8"?>', "\n", '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">', "\n";
    $parts = ['pages' => '', 'guides' => '', 'places' => max($smDate('places'), $smDate('index'))];
    foreach ($smCountries as $c) {
        $parts['attractions-' . strtolower($c['code'])] = $smDate(strtolower($c['code']));
    }
    foreach ($parts as $part => $when) {
        echo '  <sitemap><loc>', $smLoc('/sitemap-' . $part . '.xml'), '</loc>', $when !== '' ? '<lastmod>' . $when . '</lastmod>' : '', "</sitemap>\n";
    }
    echo '</sitemapindex>', "\n";
    exit;
}

$urls = [];
if ($smPart === 'pages') {
    $smAll = $smDate('index');
    $urls = [
        ['/', 'daily', '1.0'], ['/cities', 'weekly', '0.8', max($smDate('places'), $smAll)], ['/attractions', 'weekly', '0.8', $smAll], ['/experiences', 'daily', '0.8'], ['/venues', 'daily', '0.8'],
        ['/map', 'weekly', '0.7', $smAll], ['/routes', 'weekly', '0.7', $smDate('routes')], ['/plan', 'monthly', '0.6'], ['/guides', 'weekly', '0.6'], ['/gift-card', 'monthly', '0.5'],
        ['/how-it-works', 'monthly', '0.4'], ['/help', 'monthly', '0.4'], ['/partners', 'monthly', '0.4'],
    ];
    // Categories, only those that list something: an empty one answers "noindex" (category.php). A main category
    // counts our own listings or a partner's; a subcategory lists our own only. $v2Parents and $v2Children are the
    // raw rows of the API as includes/v2/nav.php sorted them.
    require_once __DIR__ . '/includes/nav-helpers.php';
    require_once __DIR__ . '/includes/v2/nav.php';
    require_once __DIR__ . '/includes/v2/partners.php';
    $smCount = fn (array $c): int => (int) ($c['activities_count'] ?? 0) ?: (int) ($c['event_count'] ?? 0);
    foreach ($v2Parents ?? [] as $c) {
        if ($smCount($c) > 0 || v2_wegotrip_category((string) $c['slug'])) {
            $urls[] = ['/' . $c['slug'], 'daily', '0.8'];
        }
        foreach ($v2Children[$c['id'] ?? 0] ?? [] as $s) {
            if ($smCount($s) > 0) {
                $s['parent_slug'] = $c['slug'];
                $urls[] = ['/' . bo_short_category_slug($s), 'daily', '0.7'];
            }
        }
    }
    foreach ($smCountries as $slug => $c) {
        $when = $smDate(strtolower($c['code']));
        $urls[] = ['/attractions?country=' . strtolower($c['code']), 'weekly', '0.7', $when];
        $urls[] = ['/map/' . $slug, 'weekly', '0.6', $when];
        $urls[] = ['/plan/' . $slug, 'monthly', '0.5', $when];
    }
    foreach (array_keys(v2_routes()) as $slug) {
        $urls[] = ['/routes/' . $slug, 'monthly', '0.6', $smDate('routes')];
    }
    $collect = function (string $endpoint, string $key): array {
        $rows = [];
        for ($page = 1; $page <= 40; $page++) {
            $resp = api_cached("sitemap_{$key}_{$page}", fn () => api_get($endpoint, ['per_page' => 50, 'page' => $page]), 3600);
            $items = (!empty($resp['success']) && is_array($resp['data']['items'] ?? null)) ? $resp['data']['items'] : [];
            $rows = array_merge($rows, $items);
            if ((int) ($resp['data']['pagination']['last_page'] ?? 1) <= $page || !$items) {
                break;
            }
        }
        return $rows;
    };
    foreach ($collect('/activities-module/locations', 'locations') as $l) {
        if (!empty($l['slug'])) {
            $urls[] = ['/venue/' . $l['slug'], 'daily', '0.9', $smApiDate($l)];
        }
    }
    foreach ($collect('/activities', 'experiences') as $a) {
        if (!empty($a['slug'])) {
            $urls[] = ['/experience/' . $a['slug'], 'daily', '0.8', $smApiDate($a)];
        }
    }
} elseif ($smPart === 'guides') {
    // Published guides (GET /blog-articles, paged by 50, cached an hour), newest first, with their date.
    for ($page = 1; $page <= 40; $page++) {
        $resp = api_cached("sitemap_guides_{$page}", fn () => api_get('/blog-articles', ['per_page' => 50, 'page' => $page, 'status' => 'published']), 3600);
        $rows = is_array($resp['data'] ?? null) ? $resp['data'] : [];
        foreach ($rows as $g) {
            if (!empty($g['slug'])) {
                $urls[] = ['/guides/' . $g['slug'], 'monthly', '0.7', $smApiDate($g)];
            }
        }
        if (!$rows || (int) ($resp['meta']['last_page'] ?? 1) <= $page) {
            break;
        }
    }
} elseif ($smPart === 'places') {
    // Cities that have a page of their own (the small places an import created only have /{slug}/attractions).
    $cities = v2_map_file('places')['cities'] ?? [];
    // a country or city page shows its places and its attractions, so it is as new as the newer of the two files
    foreach ($smCountries as $slug => $c) {
        $when = max($smDate('places'), $smDate(strtolower($c['code'])));
        $urls[] = ['/' . $slug, 'weekly', '0.8', $when];
        foreach ($cities[$c['code']] ?? [] as $citySlug) {
            $urls[] = ['/' . $citySlug, 'weekly', '0.7', $when];
        }
    }
} elseif (preg_match('/^attractions-([a-z]{2})$/', $smPart, $m)) {
    $file = __DIR__ . '/assets/v2/data/map/' . $m[1] . '.json';
    $data = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;
    if (!is_array($data)) {
        http_response_code(404);
        exit;
    }
    $slugAt = array_search('slug', $data['fields'] ?? [], true);
    $cityAt = array_search('city', $data['fields'] ?? [], true);
    $seenCity = [];
    $when = $smDate($m[1]);
    foreach ($data['points'] ?? [] as $row) {
        $urls[] = ['/attraction/' . $row[$slugAt], 'monthly', '0.6', $when];
        $seenCity[$row[$cityAt]] = true;
    }
    foreach (array_keys($seenCity) as $ci) {
        if ($ci >= 0 && !empty($data['cities'][$ci][0])) {
            $urls[] = ['/' . $data['cities'][$ci][0] . '/attractions', 'monthly', '0.5', $when];
        }
    }
} else {
    http_response_code(404);
    exit;
}

echo '<?xml version="1.0" encoding="UTF-8"?>', "\n", '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">', "\n";
$seen = [];
foreach ($urls as $row) {
    [$path, $freq, $prio] = $row;
    if (isset($seen[$path])) {
        continue;
    }
    $seen[$path] = true;
    $lastmod = !empty($row[3]) ? '<lastmod>' . $row[3] . '</lastmod>' : '';
    echo '  <url><loc>', $smLoc($path), '</loc>', $lastmod, '<changefreq>', $freq, '</changefreq><priority>', $prio, "</priority></url>\n";
}
echo '</urlset>', "\n";
