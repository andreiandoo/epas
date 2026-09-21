<?php
/**
 * /sitemap.xml: hubs, locations and experiences sold online (activities module).
 *
 * Locations come from GET /activities-module/locations, experiences from GET /activities (only experiences sold
 * online when the module is on). Both are paged by 50 and cached for an hour.
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/api.php';

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

$urls = [
    ['/locatii', 'daily', '0.8'],
    ['/experiente', 'daily', '0.8'],
    ['/atractii', 'weekly', '0.7'],
    ['/harta', 'weekly', '0.7'],
];
$cities = [];
foreach ($collect('/activities-module/locations', 'locations') as $l) {
    if (!empty($l['slug'])) {
        $urls[] = ['/locatie/' . $l['slug'], 'daily', '0.9'];
        if (!empty($l['city']['slug'])) {
            $cities[$l['city']['slug']] = true;
        }
    }
}
foreach (array_keys($cities) as $c) {
    $urls[] = ['/' . $c . '/locatii', 'weekly', '0.6'];
}
foreach ($collect('/activities', 'experiences') as $a) {
    if (!empty($a['slug'])) {
        $urls[] = ['/experienta/' . $a['slug'], 'daily', '0.8'];
    }
}

header('Content-Type: application/xml; charset=utf-8');
header('Cache-Control: public, max-age=3600');
echo '<?xml version="1.0" encoding="UTF-8"?>', "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">', "\n";
$seen = [];
foreach ($urls as [$path, $freq, $prio]) {
    if (isset($seen[$path])) {
        continue;
    }
    $seen[$path] = true;
    echo '  <url><loc>', htmlspecialchars(SITE_URL . $path, ENT_XML1 | ENT_QUOTES, 'UTF-8'), '</loc><changefreq>', $freq, '</changefreq><priority>', $prio, "</priority></url>\n";
}
echo '</urlset>', "\n";
