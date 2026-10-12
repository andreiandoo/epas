<?php
/**
 * Viaqui v2 homepage: data for the homepage sections, on top of the shell data in $V2NAV.
 *
 * Everything a visitor can follow comes from the platform: experiences from the API (our own, or a partner's best
 * sellers while we have none), the hero photos, the route of the week and the top lists from the map's data files
 * (assets/v2/data/map), cities, countries, categories and guides from the shell. A block with nothing to show is
 * left out; nothing is invented to fill it.
 *
 * Requires v2/nav.php. Sets $V2 = $V2NAV plus the homepage keys.
 */

// The hero shows places a visitor recognises from outside: the best-known attractions of these types that have a photo.
const V2_HERO_TYPES = ['castles', 'palaces', 'cathedrals', 'landmarks', 'viewpoints', 'fortresses', 'monasteries', 'bridges', 'old-towns-squares'];
// The photo the hero falls back on if the map's data is missing (local file hero-bran-{720,900,1440}.webp).
const V2_HERO_FALLBACK = ['bran', 'Bran Castle', 'Bran, Romania', '/attraction/bran-castle'];

/** A hero photo of ours in the widths it exists in, as a srcset. */
function v2_home_hero_srcset(string $key): string
{
    $set = [];
    foreach ([720, 900, 1440] as $w) {
        $set[] = v2_asset('img/hero-' . $key . '-' . $w . '.webp') . ' ' . $w . 'w';
    }

    return implode(', ', $set);
}

// Glyph (v2/home/sections.php sprite) and colour block for each of the twelve category cards, by position.
// f = forest, s = sage, d = sand, p = photo.
const V2_CAT_LOOK = [
    ['f', 'column'], ['p', 'castle'], ['d', 'route'], ['s', 'tree'], ['p', 'mount'], ['f', 'wheel'],
    ['d', 'paw'], ['p', 'kite'], ['s', 'key'], ['f', 'brush'], ['p', 'star'], ['d', 'group'],
];

/** The questions of the homepage (the section and its FAQPage structured data): [[question, answer]]. */
function v2_home_faq(): array
{
    return [
        [v2_t('How do I get my tickets?'), v2_t('By email, right after payment, as a QR code. You also find them in your account. No printing needed: show the code on your phone at the entrance.')],
        [v2_t('Can I change the date or cancel?'), v2_t('Each venue sets its own cancellation rule. It is shown on the listing, next to the price, before you pay.')],
        [v2_t('Do I need an account to buy?'), v2_t('No. You can check out as a guest with an email address. An account keeps your tickets, favourites and gift cards in one place.')],
        [v2_t('Which payment methods are accepted?'), v2_t('Bank cards, processed by a licensed payment provider. Card details never reach Viaqui.')],
        [v2_t('Is the price the same as at the gate?'), v2_t('The ticket price is set by the venue. Any service fee is shown as a separate line before you confirm the order.')],
        [v2_t('I run a venue. How do I list it?'), v2_t('Create an operator account, add your venue and tickets, and start selling. You pay a commission only on tickets sold. The platform is operated by Tixello.')],
    ];
}

require_once dirname(__DIR__) . '/places.php';
require_once dirname(__DIR__) . '/partners.php';

// ------------------------------------------------------------------ fetch
$v2R = api_cached_many([
    'citiesTotal' => ['key' => 'v2_cities_total', 'endpoint' => '/locations/cities', 'params' => ['per_page' => 1], 'ttl' => 21600],
    'attrTotal' => ['key' => 'v2_attractions_total', 'endpoint' => '/attractions', 'params' => ['per_page' => 1], 'ttl' => 21600],
    'activities' => ['key' => 'v2_activities', 'endpoint' => '/activities', 'params' => ['per_page' => 12], 'ttl' => 300],
]);

$V2 = $V2NAV;
$V2['totals'] = [
    'attractions' => (int) ($v2R['attrTotal']['data']['pagination']['total'] ?? 0),
    'cities' => (int) ($v2R['citiesTotal']['meta']['total'] ?? 0),
];

// ------------------------------------------------------------------ experiences for "this week"
$v2Acts = [];
foreach ((array) ($v2R['activities']['data']['items'] ?? []) as $a) {
    if (is_array($a) && ($n = v2_activity($a))) {
        $v2Acts[] = $n;
    }
}
$V2['cards'] = [];
$V2['filters'] = [];
foreach ($v2Acts as $a) {
    if ($a['cat'] !== '') {
        $V2['filters'][$a['cat']] = $a['catName'] ?: $a['cat'];
    }
    $V2['cards'][] = [
        'title' => $a['title'], 'place' => $a['city'], 'k' => $a['cat'], 'image' => $a['image'], 'href' => $a['href'],
        'price' => $a['priceLabel'], 'badges' => ['mob'], 'via' => '',
        'rating' => $a['reviews'] > 0 ? [$a['rating'], $a['reviews']] : null,
    ];
}
// Nothing of our own to book yet: the partner's best sellers across Europe, three from each main category it covers
// (the same products the category pages list), taken in turn so the first row mixes the categories.
if (!$V2['cards']) {
    $v2Rows = [];
    $v2Seen = [];
    foreach (array_keys(V2_WEGOTRIP_CATEGORY_IDS) as $v2Cat) {
        $v2Name = $V2['categoryBySlug'][$v2Cat]['name'] ?? '';
        $v2Row = [];
        foreach ($v2Name !== '' ? v2_wegotrip_category($v2Cat) : [] as $pp) {
            if (count($v2Row) >= 3) {
                break;
            }
            if (isset($v2Seen[$pp['url']]) || $pp['img'] === '') {
                continue;
            }
            $v2Seen[$pp['url']] = true;
            $v2Row[] = [
                'title' => $pp['title'], 'place' => $pp['city'], 'k' => $v2Cat, 'image' => $pp['img'], 'href' => v2_partner_href('wegotrip', $pp['url'], 'home'),
                'price' => $pp['price'] > 0 ? v2_price_local($pp['price'], $pp['cc']) : '', 'badges' => [], 'via' => 'WeGoTrip',
                'rating' => $pp['ratings'] >= 5 ? [round($pp['rating'], 1), $pp['ratings']] : null,
            ];
        }
        if ($v2Row) {
            $V2['filters'][$v2Cat] = $v2Name;
            $v2Rows[] = $v2Row;
        }
    }
    for ($i = 0; $i < 3; $i++) {
        foreach ($v2Rows as $v2Row) {
            if (isset($v2Row[$i])) {
                $V2['cards'][] = $v2Row[$i];
            }
        }
    }
}

// the next ten days, for the date strip
/** PHP's short English weekday ("Mon") and month ("Jan"), in the visitor's language. */
function v2_short_day(string $en): string
{
    switch ($en) {
        case 'Mon': return v2_t('Mon');
        case 'Tue': return v2_t('Tue');
        case 'Wed': return v2_t('Wed');
        case 'Thu': return v2_t('Thu');
        case 'Fri': return v2_t('Fri');
        case 'Sat': return v2_t('Sat');
        case 'Sun': return v2_t('Sun');
        default: return $en;
    }
}
function v2_short_month(string $en): string
{
    switch ($en) {
        case 'Jan': return v2_t('Jan');
        case 'Feb': return v2_t('Feb');
        case 'Mar': return v2_t('Mar');
        case 'Apr': return v2_t('Apr');
        case 'May': return v2_t('May');
        case 'Jun': return v2_t('Jun');
        case 'Jul': return v2_t('Jul');
        case 'Aug': return v2_t('Aug');
        case 'Sep': return v2_t('Sep');
        case 'Oct': return v2_t('Oct');
        case 'Nov': return v2_t('Nov');
        case 'Dec': return v2_t('Dec');
        default: return $en;
    }
}
$v2Today = new DateTimeImmutable('today', new DateTimeZone('Europe/Bucharest'));
$V2['days'] = [];
for ($i = 0; $i < 10; $i++) {
    $d = $v2Today->modify('+' . $i . ' day');
    $V2['days'][] = ['iso' => $d->format('Y-m-d'), 'label' => $i === 0 ? v2_t('Today') : ($i === 1 ? v2_t('Tomorrow') : v2_short_day($d->format('D'))), 'day' => (int) $d->format('j'), 'month' => v2_short_month($d->format('M'))];
}

// ------------------------------------------------------------------ hero photos
$v2Europe = v2_map_file('europe.summary') ?? [];
$v2Types = v2_attraction_types_t();
// The country of each of those places, from the map's own list (the summary names only the locality, and for a
// famous place that is often a district nobody looks for: the Eiffel Tower stands in "Paris 16 Passy").
$v2CountryOf = [];
$v2Map = v2_map_file('europe') ?? [];
$v2At = array_flip($v2Map['fields'] ?? []);
if (isset($v2At['slug'], $v2At['zone'])) {
    $v2Wanted = array_flip(array_column($v2Europe['picks'] ?? [], 0));
    foreach ($v2Map['points'] ?? [] as $v2Row) {
        if (isset($v2Wanted[$v2Row[$v2At['slug']]])) {
            $v2CountryOf[$v2Row[$v2At['slug']]] = (string) ($v2Map['zones'][$v2Row[$v2At['zone']]][0] ?? '');
        }
    }
}
unset($v2Map);
$V2['hero'] = [];
foreach ($v2Europe['picks'] ?? [] as $p) {
    $v2File = v2_commons_name((string) ($p[6] ?? ''));
    if (count($V2['hero']) >= 5) {
        break;
    }
    if ($v2File === null || empty($p[0]) || !in_array($p[3] ?? '', V2_HERO_TYPES, true)) {
        continue;
    }
    $v2Set = [];
    foreach ([480, 640, 960, 1280] as $w) {
        $v2Set[] = v2_commons_thumb($v2File, $w) . ' ' . $w . 'w';
    }
    $v2Cr = is_array($p[7] ?? null) ? $p[7] : [];
    $V2['hero'][] = [
        'name' => (string) $p[1],
        'meta' => ($v2CountryOf[$p[0]] ?? '') !== '' ? $v2CountryOf[$p[0]] : ($v2Types[$p[3]] ?? ''),
        'href' => '/attraction/' . $p[0],
        'src' => v2_commons_thumb($v2File, 960), 'srcset' => implode(', ', $v2Set),
        'credit' => !empty($v2Cr[1]) ? v2_t('Photo: {author}, {licence}', ['author' => ($v2Cr[0] ?? '') !== '' ? $v2Cr[0] : v2_t('unknown author'), 'licence' => $v2Cr[1]]) : '',
    ];
}
if (!$V2['hero']) {
    [$v2Key, $v2Name, $v2Meta, $v2Href] = V2_HERO_FALLBACK;
    $V2['hero'][] = ['name' => $v2Name, 'meta' => $v2Meta, 'href' => $v2Href, 'src' => v2_asset('img/hero-' . $v2Key . '-900.webp'), 'srcset' => v2_home_hero_srcset($v2Key), 'credit' => ''];
}

// ------------------------------------------------------------------ the route of the week
// One of the editorial routes (assets/v2/data/map/routes.json), a new one each week; its stops are attractions.
$V2['route'] = null;
$v2Routes = v2_routes();
if ($v2Routes) {
    $v2Slugs = array_keys($v2Routes);
    $v2Slug = $v2Slugs[((int) date('o') * 53 + (int) date('W')) % count($v2Slugs)];
    $v2Route = $v2Routes[$v2Slug];
    $v2Stops = array_values(array_filter($v2Route['stops'] ?? [], fn ($st) => !empty($st[0]) && ($st[9] ?? '') !== ''));
    // seven stops at most, spread along the road, the first and the last always among them
    if (count($v2Stops) > 7) {
        $v2Keep = [];
        for ($i = 0; $i < 7; $i++) {
            $v2Keep[(int) round($i * (count($v2Stops) - 1) / 6)] = true;
        }
        $v2Stops = array_values(array_intersect_key($v2Stops, $v2Keep));
    }
    if (count($v2Stops) >= 3) {
        $v2TypeSlug = array_flip(V2_ATTRACTION_TYPES);
        $V2['route'] = [
            'title' => (string) $v2Route['title'], 'lead' => (string) ($v2Route['lead'] ?? ''), 'href' => '/routes/' . $v2Slug,
            'count' => (int) ($v2Route['count'] ?? count($v2Stops)), 'km' => (int) ($v2Route['km'] ?? 0), 'pace' => (string) ($v2Route['pace'] ?? ''),
            'from' => (string) ($v2Stops[0][2] ?: $v2Stops[0][1]), 'to' => (string) (end($v2Stops)[2] ?: end($v2Stops)[1]),
            'total' => count($v2Routes),
            'stops' => array_map(fn ($st) => [
                'name' => (string) $st[1], 'href' => '/attraction/' . $st[0], 'image' => v2_thumb($st[9], 640),
                'type' => $v2Types[$v2TypeSlug[$st[5] ?? ''] ?? ''] ?? (string) ($st[5] ?? ''),
                'where' => implode(', ', array_unique(array_filter([(string) ($st[2] ?? ''), (string) ($st[4] ?? '')]))),
            ], $v2Stops),
        ];
    }
}

// ------------------------------------------------------------------ top lists: attractions and routes
$V2['topAttractions'] = [];
foreach (array_slice($v2Europe['picks'] ?? [], 0, 24) as $p) {
    if (!empty($p[0])) {
        $V2['topAttractions'][] = ['name' => (string) $p[1], 'href' => '/attraction/' . $p[0], 'note' => ($v2CountryOf[$p[0]] ?? '') !== '' ? $v2CountryOf[$p[0]] : ($v2Types[$p[3] ?? ''] ?? '')];
    }
}
$V2['topRoutes'] = [];
foreach ($v2Routes as $v2Slug => $v2Route) {
    $V2['topRoutes'][] = ['name' => (string) $v2Route['title'], 'href' => '/routes/' . $v2Slug, 'km' => (int) ($v2Route['km'] ?? 0)];
}

// ------------------------------------------------------------------ guides block
$V2['guideLead'] = null;
foreach ($V2['guides'] as $g) {
    if ($g['cover']) {
        $V2['guideLead'] = $g;
        break;
    }
}
$V2['guideSide'] = [];
foreach ($V2['guides'] as $g) {
    if (count($V2['guideSide']) < 2 && $V2['guideLead'] && $g['slug'] !== $V2['guideLead']['slug']) {
        $V2['guideSide'][] = $g;
    }
}

// ------------------------------------------------------------------ search suggestions (client side)
$V2['suggest'] = [
    'cities' => array_map(function ($c) {
        return [$c['name'], $c['region'], $c['href']];
    }, array_slice($V2['citiesList'], 0, 60)),
    'categories' => array_map(function ($c) {
        return [$c['name'], v2_t('Category'), $c['href']];
    }, $V2['categories']),
    'attractions' => array_map(function ($a) {
        return [$a['name'], $a['note'] !== '' ? $a['note'] : v2_t('Attraction'), $a['href']];
    }, $V2['topAttractions']),
];
