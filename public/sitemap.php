<?php
/**
 * The sitemaps of viaqui.com.
 *
 *   /sitemap.xml                   the index: one entry for each file below
 *   /sitemap-pages.xml             hubs, maps, planner, routes, plus venues and experiences sold online
 *   /sitemap-places.xml            countries and cities
 *   /sitemap-guides.xml            the published guides, each with the date it was published
 *   /sitemap-attractions-{cc}.xml  the attractions of one country (the largest holds about 17,000, under the 50,000 limit)
 *
 * Countries, cities, attractions and routes are read from the static data the map uses (assets/v2/data/map, written by
 * plans/viaqui-data/build_map_data.py), so listing 113,000 attractions costs no request to the API. Venues and
 * experiences come from the API (GET /activities-module/locations and /activities), paged by 50 and cached an hour.
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

// ------------------------------------------------------------------ the index
if ($smPart === '') {
    echo '<?xml version="1.0" encoding="UTF-8"?>', "\n", '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">', "\n";
    foreach (array_merge(['pages', 'guides', 'places'], array_map(fn ($c) => 'attractions-' . strtolower($c['code']), array_values($smCountries))) as $part) {
        echo '  <sitemap><loc>', $smLoc('/sitemap-' . $part . '.xml'), "</loc></sitemap>\n";
    }
    echo '</sitemapindex>', "\n";
    exit;
}

$urls = [];
if ($smPart === 'pages') {
    $urls = [
        ['/', 'daily', '1.0'], ['/cities', 'weekly', '0.8'], ['/attractions', 'weekly', '0.8'], ['/experiences', 'daily', '0.8'], ['/venues', 'daily', '0.8'],
        ['/map', 'weekly', '0.7'], ['/routes', 'weekly', '0.7'], ['/plan', 'monthly', '0.6'], ['/guides', 'weekly', '0.6'], ['/gift-card', 'monthly', '0.5'],
        ['/how-it-works', 'monthly', '0.4'], ['/help', 'monthly', '0.4'], ['/partners', 'monthly', '0.4'],
    ];
    foreach (array_keys($smCountries) as $slug) {
        $urls[] = ['/map/' . $slug, 'weekly', '0.6'];
        $urls[] = ['/plan/' . $slug, 'monthly', '0.5'];
    }
    foreach (array_keys(v2_routes()) as $slug) {
        $urls[] = ['/routes/' . $slug, 'monthly', '0.6'];
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
            $urls[] = ['/venue/' . $l['slug'], 'daily', '0.9'];
        }
    }
    foreach ($collect('/activities', 'experiences') as $a) {
        if (!empty($a['slug'])) {
            $urls[] = ['/experience/' . $a['slug'], 'daily', '0.8'];
        }
    }
} elseif ($smPart === 'guides') {
    // Published guides (GET /blog-articles, paged by 50, cached an hour), newest first, with their date.
    for ($page = 1; $page <= 40; $page++) {
        $resp = api_cached("sitemap_guides_{$page}", fn () => api_get('/blog-articles', ['per_page' => 50, 'page' => $page, 'status' => 'published']), 3600);
        $rows = is_array($resp['data'] ?? null) ? $resp['data'] : [];
        foreach ($rows as $g) {
            if (!empty($g['slug'])) {
                $when = strtotime((string) ($g['updated_at'] ?? $g['published_at'] ?? ''));
                $urls[] = ['/guides/' . $g['slug'], 'monthly', '0.7', $when ? date('Y-m-d', $when) : ''];
            }
        }
        if (!$rows || (int) ($resp['meta']['last_page'] ?? 1) <= $page) {
            break;
        }
    }
} elseif ($smPart === 'places') {
    // Cities that have a page of their own (the small places an import created only have /{slug}/attractions).
    $cities = v2_map_file('places')['cities'] ?? [];
    foreach ($smCountries as $slug => $c) {
        $urls[] = ['/' . $slug, 'weekly', '0.8'];
        foreach ($cities[$c['code']] ?? [] as $citySlug) {
            $urls[] = ['/' . $citySlug, 'weekly', '0.7'];
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
    foreach ($data['points'] ?? [] as $row) {
        $urls[] = ['/attraction/' . $row[$slugAt], 'monthly', '0.6'];
        $seenCity[$row[$cityAt]] = true;
    }
    foreach (array_keys($seenCity) as $ci) {
        if ($ci >= 0 && !empty($data['cities'][$ci][0])) {
            $urls[] = ['/' . $data['cities'][$ci][0] . '/attractions', 'monthly', '0.5'];
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
