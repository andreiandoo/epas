<?php
/**
 * Viaqui v2 homepage: data for the homepage sections, on top of the shell data in $V2NAV.
 *
 * One parallel round-trip to the marketplace API (each call cached on its own TTL). Real data always wins. While
 * marketplace 4 is still empty in core, the sections that need a catalogue show sample listings instead, and the
 * page says so in a visible note ($V2['sample']). Samples carry no ratings and no counts.
 *
 * Requires v2/nav.php. Sets $V2 = $V2NAV plus the homepage keys.
 */

// Hero photo candidates: key (local WebP copies hero-{key}-{900,1440,1920}.webp) => [name, place, attraction slug]
const V2_HERO_PLACES = [
    'bran' => ['Bran Castle', 'Bran, Romania', 'bran-castle'],
    'peles' => ['Peleș Castle', 'Sinaia, Romania', 'peles-castle'],
    'mogosoaia' => ['Mogoșoaia Palace', 'Near Bucharest, Romania', 'mogosoaia-palace'],
];

// Glyph (v2/home/sections.php sprite) and colour block for each of the twelve category cards, by position.
// f = forest, s = sage, d = sand, p = photo.
const V2_CAT_LOOK = [
    ['f', 'column'], ['p', 'castle'], ['d', 'route'], ['s', 'tree'], ['p', 'mount'], ['f', 'wheel'],
    ['d', 'paw'], ['p', 'kite'], ['s', 'key'], ['f', 'brush'], ['p', 'star'], ['d', 'group'],
];

// What the "this week" block shows until real experiences exist. k = filter key; b = badges.
const V2_SAMPLE_EXPERIENCES = [
    ['Bran Castle: entry ticket', 'Bran', 'castle', 'hero-bran-900', 14, ['inst', 'mob']],
    ['Peleș Castle: ground floor tour', 'Sinaia', 'castle', 'hero-peles-900', 10, ['mob', 'free']],
    ['Dimitrie Gusti Village Museum', 'Bucharest', 'museum', 'cat-muzee-expozitii', 6, ['fam', 'mob']],
    ['Romanian Athenaeum: guided visit', 'Bucharest', 'museum', 'dest-bucuresti', 9, ['inst', 'free']],
    ['Old town walking tour', 'Brașov', 'tour', 'dest-brasov', 17, ['free', 'mob']],
    ['Clock Tower and citadel pass', 'Sighișoara', 'castle', 'dest-sighisoara', 8, ['inst', 'mob']],
    ['Seven Ladders Canyon: via ferrata', 'Brașov', 'nature', 'cat-natura-outdoor', 12, ['inst', 'free']],
    ['Zoo and aquarium combined ticket', 'Constanța', 'family', 'cat-acvarii-zoo-animale', 7, ['fam', 'mob']],
    ['ASTRA open-air museum', 'Sibiu', 'museum', 'dest-sibiu', 8, ['fam', 'free']],
    ['Mogoșoaia Palace and gardens', 'Mogoșoaia', 'castle', 'hero-mogosoaia-900', 4, ['mob', 'free']],
    ['Forest rope course, three levels', 'Brașov', 'nature', 'cat-parcuri-de-aventura', 15, ['fam', 'inst']],
    ['Casino seafront architecture walk', 'Constanța', 'tour', 'dest-constanta', 11, ['free', 'inst']],
];
const V2_SAMPLE_FILTERS = ['castle' => 'Castles', 'museum' => 'Museums', 'nature' => 'Nature', 'tour' => 'Tours', 'family' => 'Families'];

// The road in the journey block: [city slug in the starter catalogue, lowercase label, region, photo, alt, one line]
const V2_JOURNEY = [
    ['bucharest', 'bucharest', 'Wallachia', 'dest-bucuresti', 'The Romanian Athenaeum in Bucharest', 'Palaces, hidden courtyards and a concert hall that locals paid for one coin at a time.'],
    ['sinaia', 'sinaia', 'Prahova Valley', 'hero-peles-900', 'Peleș Castle in Sinaia', 'A royal summer residence in the pines, an hour and a half from the capital.'],
    ['brasov', 'brașov', 'Transylvania', 'dest-brasov', 'Council Square in Brașov seen from above', 'A walled old town under a mountain, with Bran and the bear sanctuary close by.'],
    ['sighisoara', 'sighișoara', 'Transylvania', 'dest-sighisoara', 'The citadel of Sighișoara', 'A lived-in medieval citadel: clock tower, covered stairway, painted houses.'],
    ['sibiu', 'sibiu', 'Transylvania', 'dest-sibiu', 'The Large Square in Sibiu', 'Squares that open into each other and roofs that seem to watch you pass.'],
    ['constanta', 'constanța', 'Black Sea coast', 'dest-constanta', 'The Casino in Constanța on the seafront', 'The Black Sea, an Art Nouveau casino and two thousand years of harbour life.'],
];

const V2_FAQ = [
    ['How do I get my tickets?', 'By email, right after payment, as a QR code. You also find them in your account. No printing needed: show the code on your phone at the entrance.'],
    ['Can I change the date or cancel?', 'Each venue sets its own cancellation rule. It is shown on the listing, next to the price, before you pay.'],
    ['Do I need an account to buy?', 'No. You can check out as a guest with an email address. An account keeps your tickets, favourites and gift cards in one place.'],
    ['Which payment methods are accepted?', 'Bank cards, processed by a licensed payment provider. Card details never reach Viaqui.'],
    ['Is the price the same as at the gate?', 'The ticket price is set by the venue. Any service fee is shown as a separate line before you confirm the order.'],
    ['I run a venue. How do I list it?', 'Create an operator account, add your venue and tickets, and start selling. You pay a commission only on tickets sold. The platform is operated by Tixello.'],
];

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
$V2['sample'] = !$v2Acts;
$V2['cards'] = [];
$V2['filters'] = [];
if ($v2Acts) {
    foreach ($v2Acts as $a) {
        if ($a['cat'] !== '') {
            $V2['filters'][$a['cat']] = $a['catName'] ?: $a['cat'];
        }
        $V2['cards'][] = [
            'title' => $a['title'], 'place' => $a['city'], 'k' => $a['cat'], 'image' => $a['image'], 'href' => $a['href'],
            'price' => $a['price'] ? v2_money($a['price']) : '', 'badges' => ['mob'],
            'rating' => $a['reviews'] > 0 ? [$a['rating'], $a['reviews']] : null,
        ];
    }
} else {
    $V2['filters'] = V2_SAMPLE_FILTERS;
    foreach (V2_SAMPLE_EXPERIENCES as [$title, $place, $k, $img, $price, $badges]) {
        $V2['cards'][] = [
            'title' => $title, 'place' => $place, 'k' => $k, 'image' => v2_asset('img/' . $img . '.webp'), 'href' => '/search?q=' . rawurlencode($title),
            'price' => v2_money($price), 'badges' => $badges, 'rating' => null,
        ];
    }
}

// the next ten days, for the date strip
$v2Today = new DateTimeImmutable('today', new DateTimeZone('Europe/Bucharest'));
$V2['days'] = [];
for ($i = 0; $i < 10; $i++) {
    $d = $v2Today->modify('+' . $i . ' day');
    $V2['days'][] = ['iso' => $d->format('Y-m-d'), 'label' => $i === 0 ? 'Today' : ($i === 1 ? 'Tomorrow' : $d->format('D')), 'day' => (int) $d->format('j'), 'month' => $d->format('M')];
}

// ------------------------------------------------------------------ journey stops
$V2['journey'] = [];
foreach (V2_JOURNEY as [$slug, $label, $region, $img, $alt, $text]) {
    $city = $V2['cities'][$slug] ?? null;
    $V2['journey'][] = ['label' => $label, 'region' => $region, 'image' => v2_asset('img/' . $img . '.webp'), 'alt' => $alt, 'text' => $text,
        'href' => $city['href'] ?? '/' . $slug, 'count' => (int) ($city['count'] ?? 0)];
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
        return [$c['name'], 'Category', $c['href']];
    }, $V2['categories']),
];
