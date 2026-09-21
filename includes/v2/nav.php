<?php
/**
 * bilete.online v2: data for the shared shell (mega menu, mobile menu, footer): categories with
 * subcategories, featured cities, regions and guides. One parallel round-trip to the API, each call
 * cached on its own TTL.
 *
 * Requires includes/config.php, api.php, nav-helpers.php and v2/helpers.php. Sets $V2NAV.
 */

const V2_REGIONS = ['Transilvania', 'Muntenia', 'Moldova', 'Oltenia', 'Dobrogea', 'Crișana', 'Banat', 'Maramureș'];

// WebP copies (320 and 640 px) of the platform's category images: the originals are 700-800 KB PNGs each.
// A category added later falls back to its API image until it gets a copy here.
const V2_CATEGORY_PHOTOS = [
    'escape-rooms', 'muzee-expozitii', 'parcuri-de-distractii', 'parcuri-de-aventura', 'natura-outdoor', 'acvarii-zoo-animale',
    'ateliere-experiente-creative', 'tururi-experiente-turistice', 'educatie-invatare-experientiala', 'familie-copii',
    'corporate-grupuri', 'cultura-arta',
];

// Provisional photos (Wikimedia Commons, credited in the footer) for cities whose record has no image yet.
const V2_CITY_PHOTOS = [
    'brasov' => ['img/dest-brasov.webp', 960, 800, 'Piața Sfatului din Brașov, văzută de sus'],
    'sibiu' => ['img/dest-sibiu.webp', 640, 540, 'Piața Mare din Sibiu, cu Turnul Sfatului'],
    'bucuresti' => ['img/dest-bucuresti.webp', 640, 540, 'Ateneul Român din București'],
    'constanta' => ['img/dest-constanta.webp', 640, 540, 'Cazinoul din Constanța, pe faleză'],
    'sighisoara' => ['img/dest-sighisoara.webp', 640, 540, 'Stradă cu case colorate în cetatea Sighișoara'],
];

// Provisional guide images while most articles have no cover in the API.
const V2_GUIDE_THUMBS = [
    'ce-sa-vizitezi-in-maramures-itinerar-weekend' => 'img/g-maramures.webp',
    'ce-sa-vizitezi-in-brasov-itinerar-weekend' => 'img/g-brasov.webp',
    'activitati-in-bucuresti-ce-poti-face-in-oras-cand-vrei-o-iesire-altfel' => 'img/g-bucuresti.webp',
    'parcuri-de-distractie-cluj' => 'img/g-cluj.webp',
    'experiente-extreme-pe-litoral' => 'img/g-litoral.webp',
    'ce-sa-vizitezi-sinaia-bucegi' => 'img/g-sinaia.webp',
];
const V2_GUIDE_COVERS = [
    'ce-sa-vizitezi-in-brasov-itinerar-weekend' => ['img/insp-brasov.webp', 640, 480, 'Piața Sfatului din Brașov, cu Tâmpa în fundal'],
    'ce-sa-vizitezi-sinaia-bucegi' => ['img/hero-900.webp', 900, 643, 'Castelul Peleș din Sinaia'],
];

const V2_BLOG_CATEGORIES = [
    'Family & kids' => 'Familie & copii', 'City guides' => 'Ghiduri de oraș',
    'Adventure & adrenaline' => 'Aventură & adrenalină', 'Culture & history' => 'Cultură & istorie',
];

$v2NavR = api_cached_many([
    'cats' => ['key' => 'v2_categories_all', 'endpoint' => '/events/categories', 'params' => ['all' => 1], 'ttl' => 900],
    'cities' => ['key' => 'v2_cities_featured', 'endpoint' => '/locations/cities/featured', 'params' => [], 'ttl' => 1800],
    'regions' => ['key' => 'v2_regions', 'endpoint' => '/locations/regions', 'params' => [], 'ttl' => 3600],
    // every visible city (the menu counts them per region): /orase lists them, the newsletter city field suggests them
    'allCities1' => ['key' => 'v2_cities_all_1', 'endpoint' => '/locations/cities', 'params' => ['per_page' => 200, 'page' => 1, 'sort' => 'name'], 'ttl' => 21600],
    'allCities2' => ['key' => 'v2_cities_all_2', 'endpoint' => '/locations/cities', 'params' => ['per_page' => 200, 'page' => 2, 'sort' => 'name'], 'ttl' => 21600],
    'blog' => ['key' => 'v2_blog', 'endpoint' => '/blog-articles', 'params' => ['per_page' => 6, 'status' => 'published'], 'ttl' => 900],
    // the published locations (activities module): the header menu groups them by city, /locatii lists them all
    'locations' => ['key' => 'v2_am_locations_nav', 'endpoint' => '/activities-module/locations', 'params' => ['per_page' => 50], 'ttl' => 1800],
]);
$v2NavData = function (string $k) use ($v2NavR): array {
    return !empty($v2NavR[$k]['success']) && is_array($v2NavR[$k]['data'] ?? null) ? $v2NavR[$k]['data'] : [];
};

$V2NAV = [];

// ------------------------------------------------------------------ categories (+ subcategories)
$v2Parents = [];
$v2Children = [];
foreach ((array) ($v2NavData('cats')['categories'] ?? []) as $c) {
    if (!is_array($c) || empty($c['slug'])) {
        continue;
    }
    if (empty($c['parent_id'])) {
        $v2Parents[] = $c;
    } else {
        $v2Children[$c['parent_id']][] = $c;
    }
}
$V2NAV['categories'] = [];
foreach ($v2Parents as $c) {
    $local = in_array($c['slug'], V2_CATEGORY_PHOTOS, true);
    $apiImage = v2_media_url($c['image'] ?? null);
    $subs = [];
    foreach (array_slice($v2Children[$c['id'] ?? 0] ?? [], 0, 10) as $s) {
        $s['parent_slug'] = $c['slug'];
        $subs[] = ['name' => navFlatName($s['name'] ?? ''), 'href' => '/' . bo_short_category_slug($s)];
    }
    $V2NAV['categories'][] = [
        'slug' => $c['slug'],
        'name' => navFlatName($c['name'] ?? ''),
        'desc' => trim((string) ($c['description'] ?? '')),
        'image' => $local ? v2_asset('img/cat-' . $c['slug'] . '.webp') : $apiImage,
        'thumb' => $local ? v2_asset('img/cat-' . $c['slug'] . '-320.webp') : $apiImage,
        'srcset' => $local ? v2_asset('img/cat-' . $c['slug'] . '-320.webp') . ' 320w, ' . v2_asset('img/cat-' . $c['slug'] . '.webp') . ' 640w' : '',
        'count' => (int) ($c['activities_count'] ?? 0) ?: (int) ($c['event_count'] ?? 0),
        'href' => '/' . $c['slug'],
        'subs' => $subs,
    ];
}
$V2NAV['categoryBySlug'] = array_column($V2NAV['categories'], null, 'slug');

// ------------------------------------------------------------------ cities and regions
$v2Cities = [];
foreach ((array) ($v2NavData('cities')['cities'] ?? []) as $c) {
    if (!is_array($c) || empty($c['slug'])) {
        continue;
    }
    $img = v2_media_url($c['image'] ?? null);
    $local = V2_CITY_PHOTOS[$c['slug']] ?? null;
    $v2Cities[$c['slug']] = [
        'slug' => $c['slug'],
        'name' => navFlatName($c['name'] ?? ''),
        'region' => (string) ($c['region'] ?? ''),
        'count' => (int) ($c['activities_count'] ?? 0) ?: (int) ($c['events_count'] ?? 0),
        'capital' => !empty($c['is_capital']),
        'href' => '/' . $c['slug'],
        'photo' => $img ? [$img, 0, 0, ''] : ($local ? [v2_asset($local[0]), $local[1], $local[2], $local[3]] : null),
    ];
}
// Most experiences first; while counts are still equal, the large tourist cities lead instead of the alphabet.
$v2CityRank = array_flip(['bucuresti', 'brasov', 'cluj-napoca', 'sibiu', 'constanta', 'timisoara', 'iasi', 'oradea', 'sighisoara', 'sinaia',
    'craiova', 'alba-iulia', 'targu-mures', 'baia-mare', 'suceava', 'tulcea', 'arad', 'pitesti', 'galati', 'ploiesti', 'hunedoara', 'bran']);
uasort($v2Cities, function ($a, $b) use ($v2CityRank) {
    return [$b['count'], (int) $b['capital'], $v2CityRank[$a['slug']] ?? 999, $a['name']]
        <=> [$a['count'], (int) $a['capital'], $v2CityRank[$b['slug']] ?? 999, $b['name']];
});
$V2NAV['cities'] = $v2Cities;
$V2NAV['citiesList'] = array_values($v2Cities);
$V2NAV['allCities'] = [];
foreach (['allCities1', 'allCities2'] as $v2Page) {
    foreach ($v2NavData($v2Page) as $c) {
        if (!is_array($c) || empty($c['slug']) || isset($V2NAV['allCities'][$c['slug']])) {
            continue;
        }
        $V2NAV['allCities'][$c['slug']] = [
            'slug' => $c['slug'],
            'name' => navFlatName($c['name'] ?? ''),
            'region' => (string) ($c['region'] ?? ''),
            'county' => is_array($c['county'] ?? null) ? navFlatName($c['county']['name'] ?? '') : '',
            'count' => (int) ($c['events_count'] ?? 0),
            'image' => v2_media_url($c['image'] ?? null),
        ];
    }
}

// ------------------------------------------------------------------ locations (activities module), by city
// What the "Locații" menu shows: the cities that have published locations, each with its locations (picture, what
// you can book there, price from). Empty while nothing is published — the header then keeps the plain link.
$V2NAV['locations'] = ['total' => 0, 'cities' => []];
$v2LocCities = [];
foreach ((array) ($v2NavData('locations')['items'] ?? []) as $l) {
    if (!is_array($l) || empty($l['slug']) || empty($l['name'])) {
        continue;
    }
    $citySlug = (string) ($l['city']['slug'] ?? '');
    $cityName = navFlatName($l['city']['name'] ?? '');
    if ($citySlug === '' || $cityName === '') {
        continue;
    }
    $counts = is_array($l['counts'] ?? null) ? $l['counts'] : [];
    $offer = array_filter([
        !empty($counts['access']) ? v2_num((int) $counts['access'], 'bilet', 'bilete') : '',
        !empty($counts['experience']) ? v2_exp((int) $counts['experience']) : '',
        !empty($counts['package']) ? v2_num((int) $counts['package'], 'pachet', 'pachete') : '',
    ]);
    $v2LocCities[$citySlug]['name'] = $cityName;
    $v2LocCities[$citySlug]['slug'] = $citySlug;
    $v2LocCities[$citySlug]['items'][] = [
        'name' => navFlatName($l['name']),
        'href' => '/locatie/' . $l['slug'],
        'photo' => v2_media_url($l['cover_image'] ?? null),
        'category' => navFlatName($l['category']['name'] ?? ''),
        'offer' => $offer ? implode(' · ', $offer) : '',
        'price' => !empty($l['min_price_cents']) ? (int) round($l['min_price_cents'] / 100) : null,
        'lodging' => !empty($l['has_lodging']),
    ];
}
foreach ($v2LocCities as $citySlug => $city) {
    $v2LocCities[$citySlug]['count'] = count($city['items']);
}
// most locations first, then alphabetically, so the first tab is the city with the most to see
uasort($v2LocCities, function ($a, $b) {
    return $b['count'] <=> $a['count'] ?: strcoll($a['name'], $b['name']);
});
$V2NAV['locations'] = [
    'total' => array_sum(array_column($v2LocCities, 'count')),
    'cities' => array_values($v2LocCities),
];

$v2RegionsRaw = $v2NavData('regions');
$v2RegionsRaw = isset($v2RegionsRaw['regions']) ? $v2RegionsRaw['regions'] : $v2RegionsRaw;
$v2RegionInfo = [];
foreach ((array) $v2RegionsRaw as $r) {
    if (is_array($r) && !empty($r['name'])) {
        $v2RegionInfo[$r['name']] = $r;
    }
}
$V2NAV['regions'] = [];
foreach (V2_REGIONS as $name) {
    $featured = array_values(array_filter($V2NAV['citiesList'], function ($c) use ($name) {
        return $c['region'] === $name;
    }));
    $seen = array_column($featured, 'slug');
    $more = [];
    foreach ((array) ($v2RegionInfo[$name]['top_cities'] ?? []) as $tc) {
        if (is_array($tc) && !empty($tc['slug']) && !in_array($tc['slug'], $seen, true)) {
            $more[] = ['name' => navFlatName($tc['name'] ?? ''), 'href' => '/' . $tc['slug']];
            $seen[] = $tc['slug'];
        }
    }
    $V2NAV['regions'][] = [
        'name' => $name,
        // the region page lives at /{slug} (slug.php); the API slug wins, the folded name stands in without it
        'slug' => (string) ($v2RegionInfo[$name]['slug'] ?? trim(preg_replace('/[^a-z0-9]+/', '-', strtr(mb_strtolower($name), ['ă' => 'a', 'â' => 'a', 'î' => 'i', 'ș' => 's', 'ş' => 's', 'ț' => 't', 'ţ' => 't'])), '-')),
        'citiesCount' => (int) ($v2RegionInfo[$name]['cities_count'] ?? count($featured)),
        'featured' => $featured,
        'more' => $more,
    ];
}

// ------------------------------------------------------------------ guides
$v2Blog = $v2NavData('blog');
$v2Blog = $v2Blog['articles'] ?? $v2Blog['items'] ?? $v2Blog;
$V2NAV['guides'] = [];
foreach ((array) $v2Blog as $b) {
    if (!is_array($b) || empty($b['slug'])) {
        continue;
    }
    $title = navFlatName($b['title'] ?? '');
    $img = v2_media_url($b['image_url'] ?? null);
    $catName = is_array($b['category'] ?? null) ? (string) ($b['category']['name'] ?? '') : '';
    $excerpt = trim((string) ($b['excerpt'] ?? ''));
    if (mb_strlen($excerpt) > 140) {
        $cut = mb_substr($excerpt, 0, 140);
        $excerpt = rtrim(mb_substr($cut, 0, mb_strrpos($cut, ' ') ?: 140), " ,.;:") . '…';
    }
    $local = V2_GUIDE_COVERS[$b['slug']] ?? null;
    $V2NAV['guides'][] = [
        'slug' => $b['slug'],
        'title' => $title,
        'excerpt' => $excerpt,
        'category' => V2_BLOG_CATEGORIES[$catName] ?? $catName,
        'readTime' => (int) ($b['read_time'] ?? 0),
        'href' => '/ghiduri/' . $b['slug'],
        'thumb' => $img ?: (isset(V2_GUIDE_THUMBS[$b['slug']]) ? v2_asset(V2_GUIDE_THUMBS[$b['slug']]) : null),
        'cover' => $img ? [$img, 0, 0, 'Imagine din ghidul: ' . $title] : ($local ? [v2_asset($local[0]), $local[1], $local[2], $local[3]] : null),
        'hasOwnImage' => (bool) $img,
    ];
}
