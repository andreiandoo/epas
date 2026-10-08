<?php
/**
 * viaqui.com v2: data for the shared shell (mega menu, mobile menu, footer): categories with
 * subcategories, featured cities, regions and guides. One parallel round-trip to the API, each call
 * cached on its own TTL.
 *
 * Requires includes/config.php, api.php, nav-helpers.php and v2/helpers.php. Sets $V2NAV.
 *
 * Languages: $V2NAV is built on every request and is not stored anywhere, so the few labels made here (the counts of
 * a venue, the alt text of a guide picture) are in the visitor's language. What IS cached is the raw API answer.
 */
require_once __DIR__ . '/helpers.php';   // v2_t(), v2_num(), v2_asset(): loaded here too, in case a page forgot

// Kept empty for the pages copied from bilete.online that still sort by Romanian regions (cities list).
const V2_REGIONS = [];

// WebP copies (320 and 640 px) of the platform's category images: the originals are 700-800 KB PNGs each.
// A category added later falls back to its API image until it gets a copy here.
const V2_CATEGORY_PHOTOS = [
    'escape-rooms', 'muzee-expozitii', 'parcuri-de-distractii', 'parcuri-de-aventura', 'natura-outdoor', 'acvarii-zoo-animale',
    'ateliere-experiente-creative', 'tururi-experiente-turistice', 'educatie-invatare-experientiala', 'familie-copii',
    'corporate-grupuri', 'cultura-arta',
];

// Provisional photos (Wikimedia Commons, credited in the footer) for cities whose record has no image yet.
const V2_CITY_PHOTOS = [
    'brasov' => ['img/dest-brasov.webp', 960, 800, 'Council Square in Brașov seen from above'],
    'sibiu' => ['img/dest-sibiu.webp', 640, 540, 'The Large Square in Sibiu with the Council Tower'],
    'bucuresti' => ['img/dest-bucuresti.webp', 640, 540, 'The Romanian Athenaeum in Bucharest'],
    'constanta' => ['img/dest-constanta.webp', 640, 540, 'The Casino in Constanța on the seafront'],
    'sighisoara' => ['img/dest-sighisoara.webp', 640, 540, 'A street of painted houses in the citadel of Sighișoara'],
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
    'ce-sa-vizitezi-in-brasov-itinerar-weekend' => ['img/insp-brasov.webp', 640, 480, 'Council Square in Brașov with Tâmpa behind it'],
    'ce-sa-vizitezi-sinaia-bucegi' => ['img/hero-900.webp', 900, 643, 'Peleș Castle in Sinaia'],
];

const V2_BLOG_CATEGORIES = [
    // the API already names blog categories in English; nothing to map on Viaqui
];

$v2NavR = api_cached_many([
    'cats' => ['key' => 'v2_categories_all', 'endpoint' => '/events/categories', 'params' => ['all' => 1], 'ttl' => 900],
    'cities' => ['key' => 'v2_cities_featured', 'endpoint' => '/locations/cities/featured', 'params' => [], 'ttl' => 1800],
    // countries with their largest cities: the "Explore" menu, the footer, the homepage lists and the search suggestions
    'countries' => ['key' => 'v2_countries', 'endpoint' => '/locations/countries', 'params' => ['top' => 12], 'ttl' => 3600],
    'blog' => ['key' => 'v2_blog', 'endpoint' => '/blog-articles', 'params' => ['per_page' => 6, 'status' => 'published'], 'ttl' => 900],
    // the published locations (activities module): the header menu groups them by city, /locatii lists them all
    'locations' => ['key' => 'v2_am_locations_nav', 'endpoint' => '/activities-module/locations', 'params' => ['per_page' => 50], 'ttl' => 1800],
]);
$v2NavData = function (string $k) use ($v2NavR): array {
    return !empty($v2NavR[$k]['success']) && is_array($v2NavR[$k]['data'] ?? null) ? $v2NavR[$k]['data'] : [];
};

require_once __DIR__ . '/seed.php';

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
    // local WebP copy: the copied Romanian slugs, or the picture the starter catalogue gives the English slug
    $imgKey = in_array($c['slug'], V2_CATEGORY_PHOTOS, true) ? $c['slug'] : (V2_SEED_CATEGORIES[$c['slug']][1] ?? null);
    $local = $imgKey !== null;
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
        'image' => $local ? v2_asset('img/cat-' . $imgKey . '.webp') : $apiImage,
        'thumb' => $local ? v2_asset('img/cat-' . $imgKey . '-320.webp') : $apiImage,
        'srcset' => $local ? v2_asset('img/cat-' . $imgKey . '-320.webp') . ' 320w, ' . v2_asset('img/cat-' . $imgKey . '.webp') . ' 640w' : '',
        'count' => (int) ($c['activities_count'] ?? 0) ?: (int) ($c['event_count'] ?? 0),
        'href' => '/' . $c['slug'],
        'subs' => $subs,
    ];
}
$V2NAV['categoryBySlug'] = array_column($V2NAV['categories'], null, 'slug');

// ------------------------------------------------------------------ countries and cities
// Viaqui spans a continent, so the shell never loads every city: core returns the countries with their largest
// cities (/locations/countries). $V2NAV['regions'] keeps its name for the header, but each entry is a country.
$v2CityRow = function (array $c, string $country) {
    $img = v2_media_url($c['image'] ?? null);
    $local = V2_SEED_CITY_PHOTOS[$c['slug']] ?? null;
    return [
        'slug' => $c['slug'],
        'name' => navFlatName($c['name'] ?? ''),
        'region' => $country,
        'count' => (int) ($c['activities_count'] ?? 0) ?: (int) ($c['events_count'] ?? 0),
        'capital' => !empty($c['is_capital']),
        'population' => (int) ($c['population'] ?? 0),
        'href' => '/' . $c['slug'],
        'photo' => $img ? [$img, 0, 0, ''] : ($local ? [v2_asset($local[0]), $local[1], $local[2], $local[3]] : null),
    ];
};
$v2Cities = [];
$V2NAV['regions'] = [];
$V2NAV['citiesTotal'] = 0;
foreach ((array) ($v2NavData('countries')['countries'] ?? []) as $country) {
    if (!is_array($country) || empty($country['name']) || empty($country['top_cities'])) {
        continue;
    }
    $featured = [];
    foreach ((array) $country['top_cities'] as $c) {
        if (is_array($c) && !empty($c['slug'])) {
            $row = $v2CityRow($c, (string) $country['name']);
            $featured[] = $row;
            $v2Cities[$row['slug']] = $row;
        }
    }
    $V2NAV['citiesTotal'] += (int) ($country['cities_count'] ?? 0);
    $V2NAV['regions'][] = [
        'name' => (string) $country['name'],
        // the country page lives at /{slug} (slug.php -> country.php)
        'slug' => (string) ($country['slug'] ?? ''),
        'code' => (string) ($country['code'] ?? ''),
        'citiesCount' => (int) ($country['cities_count'] ?? count($featured)),
        'featured' => $featured,
        'more' => [],
    ];
}
// every country for the lists; the menu shows the first twelve (core returns them in menu order)
$V2NAV['countriesAll'] = array_map(function ($r) {
    return ['name' => $r['name'], 'slug' => $r['slug'], 'citiesCount' => $r['citiesCount']];
}, $V2NAV['regions']);
$V2NAV['countriesFull'] = $V2NAV['regions'];
$V2NAV['regions'] = array_slice($V2NAV['regions'], 0, 12);
// cities marked as featured in the admin lead every list; they keep the experience counts the API gives them
foreach ((array) ($v2NavData('cities')['cities'] ?? []) as $c) {
    if (is_array($c) && !empty($c['slug'])) {
        $row = $v2CityRow($c, (string) ($v2Cities[$c['slug']]['region'] ?? ($c['region'] ?? '')));
        $row['featuredFlag'] = true;
        $row['population'] = $v2Cities[$c['slug']]['population'] ?? 0;
        $v2Cities[$c['slug']] = $row;
    }
}
// featured first, then the most experiences, then the largest
uasort($v2Cities, function ($a, $b) {
    return [(int) !empty($b['featuredFlag']), $b['count'], $b['population']] <=> [(int) !empty($a['featuredFlag']), $a['count'], $a['population']];
});
$V2NAV['cities'] = $v2Cities;
$V2NAV['citiesList'] = array_values($v2Cities);
// the cities the "A weekend in" / "With kids in" links of the menu point to
$V2NAV['intentCities'] = array_slice(array_keys($v2Cities), 0, 6);
$V2NAV['allCities'] = array_map(function ($c) {
    return ['slug' => $c['slug'], 'name' => $c['name'], 'region' => $c['region'], 'county' => '', 'count' => $c['count'], 'image' => $c['photo'][0] ?? null];
}, $v2Cities);

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
        !empty($counts['access']) ? v2_num((int) $counts['access'], 'ticket', 'tickets') : '',
        !empty($counts['experience']) ? v2_exp((int) $counts['experience']) : '',
        !empty($counts['package']) ? v2_num((int) $counts['package'], 'package', 'packages') : '',
    ]);
    $v2LocCities[$citySlug]['name'] = $cityName;
    $v2LocCities[$citySlug]['slug'] = $citySlug;
    $v2LocCities[$citySlug]['items'][] = [
        'name' => navFlatName($l['name']),
        'href' => '/venue/' . $l['slug'],
        'photo' => v2_media_url($l['cover_image'] ?? null),
        'category' => navFlatName($l['category']['name'] ?? ''),
        'offer' => $offer ? implode(' · ', $offer) : '',
        'price' => !empty($l['min_price_cents']) ? (int) round($l['min_price_cents'] / 100) : null,
        'priceLabel' => v2_own_price_label($l['min_price_cents'] ?? 0, $l['currency'] ?? null),
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
        'href' => '/guides/' . $b['slug'],
        'thumb' => $img ?: (isset(V2_GUIDE_THUMBS[$b['slug']]) ? v2_asset(V2_GUIDE_THUMBS[$b['slug']]) : null),
        'cover' => $img ? [$img, 0, 0, v2_t('Picture from the guide: {title}', ['title' => $title])] : ($local ? [v2_asset($local[0]), $local[1], $local[2], $local[3]] : null),
        'hasOwnImage' => (bool) $img,
    ];
}

// ------------------------------------------------------------------ starter catalogue
// Marketplace 4 starts empty in core. Until it has categories, the shell shows the starter catalogue of v2/seed.php
// (categories, first countries and cities, guides) instead of an empty menu. $V2NAV['seed'] tells pages it is on.
$V2NAV['seed'] = false;
if (!$V2NAV['categories']) {
    require_once __DIR__ . '/seed.php';
    $v2Seed = v2_seed_nav();
    foreach (['categories', 'categoryBySlug', 'intentCities', 'seed'] as $v2SeedKey) {
        $V2NAV[$v2SeedKey] = $v2Seed[$v2SeedKey];
    }
    if (!$V2NAV['guides']) {
        $V2NAV['guides'] = $v2Seed['guides'];
    }
}

// No countries from core yet (empty database, or the API did not answer): the starter list of countries and cities.
if (!$V2NAV['regions']) {
    require_once __DIR__ . '/seed.php';
    $v2Seed = $v2Seed ?? v2_seed_nav();
    foreach (['cities', 'citiesList', 'allCities', 'regions'] as $v2SeedKey) {
        $V2NAV[$v2SeedKey] = $v2Seed[$v2SeedKey];
    }
    $V2NAV['intentCities'] = $v2Seed['intentCities'];
    $V2NAV['seed'] = true;
}
