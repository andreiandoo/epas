<?php
/**
 * Viaqui v2: shared pieces of the destination pages (/cities, /{country}).
 *
 * Styles: assets/v2/css/places.css (classes start with "v-").
 */

// Attraction types of Viaqui, in the order the filter shows them. Slugs match attraction_types in core
// (ViaquiAttractionsSeeder, plans/viaqui-data/build_attractions.py).
const V2_ATTRACTION_TYPES = [
    'castles' => 'Castles', 'palaces' => 'Palaces', 'museums' => 'Museums', 'cathedrals' => 'Cathedrals', 'churches' => 'Churches',
    'monasteries' => 'Monasteries', 'fortresses' => 'Fortresses', 'archaeological-sites' => 'Archaeological sites',
    'old-towns-squares' => 'Old towns & squares', 'unesco-sites' => 'UNESCO World Heritage', 'landmarks' => 'Landmarks', 'viewpoints' => 'Viewpoints',
    'theatres-operas' => 'Theatres & operas', 'national-parks' => 'National parks', 'caves' => 'Caves', 'waterfalls' => 'Waterfalls',
    'lakes' => 'Lakes', 'beaches' => 'Beaches', 'zoos' => 'Zoos', 'aquariums' => 'Aquariums', 'botanical-gardens' => 'Botanical gardens',
    'theme-parks' => 'Theme parks', 'thermal-baths' => 'Thermal baths', 'cable-cars' => 'Cable cars', 'salt-mines' => 'Salt mines',
    'wineries' => 'Wineries', 'bridges' => 'Bridges', 'lighthouses' => 'Lighthouses',
];

/** The flag of a country as a small image (assets/v2/img/flags, from the MIT-licensed flag-icons set); '' when we have none. */
function v2_flag(string $code): string
{
    $code = strtolower($code);
    if (!preg_match('/^[a-z]{2}$/', $code) || !is_file(dirname(__DIR__, 2) . '/assets/v2/img/flags/' . $code . '.svg')) {
        return '';
    }
    return '<img class="v-flag" src="' . v2_asset('img/flags/' . $code . '.svg') . '" alt="" width="24" height="18" loading="lazy" decoding="async">';
}

/** "Photo: author · licence · source", as the Commons licences ask; '' without a credit. */
function v2_photo_credit(?array $credit, string $what = 'Photo'): string
{
    if (!$credit || (empty($credit['license']) && empty($credit['source']))) {
        return '';
    }
    $out = v2_e($what) . ': ' . v2_e(($credit['author'] ?? '') !== '' ? $credit['author'] : 'unknown author');
    if (!empty($credit['license'])) {
        $out .= ' · ' . (!empty($credit['license_url']) ? '<a href="' . v2_e($credit['license_url']) . '" target="_blank" rel="noopener nofollow license">' . v2_e($credit['license']) . '</a>' : v2_e($credit['license']));
    }
    if (!empty($credit['source_url'])) {
        $out .= ' · <a href="' . v2_e($credit['source_url']) . '" target="_blank" rel="noopener nofollow">' . v2_e($credit['source'] ?? 'source') . '</a>';
    }
    return $out;
}

/** "2.3 million", "367,000", "11,900": a population a reader takes in at a glance. */
function v2_population(int $n): string
{
    if ($n >= 1000000) {
        return rtrim(rtrim(number_format($n / 1000000, 1), '0'), '.') . ' million';
    }
    if ($n >= 100000) {
        return number_format(round($n / 1000) * 1000);
    }
    return $n > 0 ? number_format(round($n / 100) * 100) : '';
}

/** A city as an arch card: photo when there is one, the line illustration otherwise. */
function v2_city_card(array $c, int $i = 0): string
{
    $photo = $c['photo'] ?? null;
    $meta = array_filter([$c['region'] ?? '', !empty($c['population']) ? v2_population((int) $c['population']) . ' inhabitants' : '']);
    return '<a class="v-pcard" href="' . v2_e($c['href']) . '">'
        . '<span class="v-pcard-ph">' . ($photo ? v2_photo([$photo[0], 0, 0, $photo[3] ?? '']) : v2_fallback($c['name'], $i))
        . (!empty($c['capital']) ? '<small>Capital</small>' : '') . '</span>'
        . '<strong>' . v2_e($c['name']) . '</strong>'
        . ($meta ? '<span>' . v2_e(implode(' · ', $meta)) . '</span>' : '')
        . '</a>';
}

/** A city row of the API (/locations/cities) in the shape the cards and lists use. */
function v2_city_from_api(array $c): array
{
    $img = v2_media_url($c['image'] ?? null);
    $local = V2_SEED_CITY_PHOTOS[$c['slug']] ?? null;
    return [
        'slug' => $c['slug'],
        'name' => navFlatName($c['name'] ?? ''),
        'region' => (string) ($c['region'] ?? ''),
        'population' => (int) ($c['population'] ?? 0),
        'capital' => !empty($c['is_capital']),
        'href' => '/' . $c['slug'],
        'photo' => $img ? [$img, 0, 0, ''] : ($local ? [v2_asset($local[0]), $local[1], $local[2], $local[3]] : null),
    ];
}

/**
 * A file of the attractions map (assets/v2/data/map, written by plans/viaqui-data/build_map_data.py), decoded:
 * 'index' (country slug => code, name, total, v), 'europe.summary' or '<cc>.summary'. Null when it is missing.
 */
function v2_map_file(string $name): ?array
{
    static $seen = [];
    if (!array_key_exists($name, $seen)) {
        $file = dirname(__DIR__, 2) . '/assets/v2/data/map/' . $name . '.json';
        $data = preg_match('/^[a-z.]+$/', $name) && is_file($file) ? json_decode((string) file_get_contents($file), true) : null;
        $seen[$name] = is_array($data) ? $data : null;
    }
    return $seen[$name];
}

/** The country page of the map for a country code ('IT' => '/map/italy'); '/map' when that country has no map. */
function v2_map_href(string $code): string
{
    foreach ((v2_map_file('index')['countries'] ?? []) as $slug => $c) {
        if (strcasecmp((string) ($c['code'] ?? ''), $code) === 0) {
            return '/map/' . $slug;
        }
    }
    return '/map';
}

/**
 * The editorial routes (assets/v2/data/map/routes.json, written by plans/viaqui-data/build_routes.py):
 * slug => title, lead, intro, pace, icon, countries, regions, stops, count, km, min, road, geometry. [] when missing.
 */
function v2_routes(): array
{
    return v2_map_file('routes')['routes'] ?? [];
}

/** A route as the row includes/v2/route-cards.php prints: [slug, title, lead, icon, pace, stops, km, photo, minutes]. */
function v2_route_card(string $slug, array $r): array
{
    $img = '';
    foreach ($r['stops'] as $s) {
        if (($s[9] ?? '') !== '') {
            $img = $s[9];
            break;
        }
    }
    return [$slug, $r['title'], $r['lead'], $r['icon'], $r['pace'], (int) $r['count'], (int) $r['km'], $img, (int) ($r['min'] ?? 0)];
}
