<?php
/**
 * Partner offers (affiliate programmes joined through Travelpayouts).
 *
 * Three parts:
 *   1. The programme list: which brands we link to and which hosts a link to them may point at.
 *   2. Outgoing links. A page never prints a partner address; it prints /go?p=…&u=…&s=… (v2_partner_href()).
 *      go.php turns the brand address into a partner link through the Travelpayouts links API, once, and keeps it.
 *      `s` is the sub id Travelpayouts reports earnings by: page type, then the place, e.g. "city-rome".
 *   3. WeGoTrip: self-guided audio tours, many sold with the entry ticket. The catalogue API is public and is read
 *      live (cached half a day); includes/v2/partners/wegotrip.json says which of our cities and attractions
 *      WeGoTrip knows (built by plans/viaqui-data/build_wegotrip_index.py).
 *   4. Aviasales: the cheapest return fares found lately to a city, from the large European airports. Read from
 *      the Travelpayouts data API (cached a day); includes/v2/partners/flights.json says which of our cities have
 *      an airport and under which IATA code (built by plans/viaqui-data/build_flights_index.py).
 *   5. "Plan your trip": airport transfer, luggage storage, eSIM and car hire for a city or a country. Plain links
 *      to pages that exist on the partners' sites; includes/v2/partners/trip.json holds them, checked against each
 *      partner's sitemap (built by plans/viaqui-data/build_trip_links.py).
 *
 * Everything is off until the Travelpayouts token and marker are set (v2_partners_on()), because a link without
 * them would send visitors away and earn nothing.
 */

const V2_PARTNER_PROGRAMS = [
    'wegotrip' => ['name' => 'WeGoTrip', 'hosts' => ['wegotrip.com']],
    'aviasales' => ['name' => 'Aviasales', 'hosts' => ['aviasales.com']],
    'welcomepickups' => ['name' => 'Welcome Pickups', 'hosts' => ['welcomepickups.com']],
    'kiwitaxi' => ['name' => 'Kiwitaxi', 'hosts' => ['kiwitaxi.com']],
    'radicalstorage' => ['name' => 'Radical Storage', 'hosts' => ['radicalstorage.com']],
    'airalo' => ['name' => 'Airalo', 'hosts' => ['airalo.com']],
    'localrent' => ['name' => 'Localrent', 'hosts' => ['localrent.com']],
    'autoeurope' => ['name' => 'Auto Europe', 'hosts' => ['autoeurope.eu']],
];

function v2_partners_on(): bool
{
    return defined('TRAVELPAYOUTS_TOKEN') && TRAVELPAYOUTS_TOKEN !== '' && TRAVELPAYOUTS_MARKER > 0;
}

/** True when $url is an https address on one of the programme's hosts (or a subdomain of one). */
function v2_partner_url_ok(string $program, string $url): bool
{
    $hosts = V2_PARTNER_PROGRAMS[$program]['hosts'] ?? [];
    $parts = parse_url($url);
    if (!$hosts || ($parts['scheme'] ?? '') !== 'https' || empty($parts['host']) || isset($parts['user'])) {
        return false;
    }
    $host = strtolower($parts['host']);
    foreach ($hosts as $h) {
        if ($host === $h || substr($host, -strlen($h) - 1) === '.' . $h) {
            return true;
        }
    }
    return false;
}

/** Sub id: lower-case letters, digits and hyphens, at most 60 characters. */
function v2_partner_sub(string $sub): string
{
    return substr(trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($sub)), '-'), 0, 60);
}

/** The address a page links to: our own redirect, never the partner's. */
function v2_partner_href(string $program, string $url, string $sub = ''): string
{
    return '/go?p=' . rawurlencode($program) . '&u=' . rawurlencode($url) . '&s=' . rawurlencode(v2_partner_sub($sub));
}

/**
 * The partner link for a brand address, from the Travelpayouts links API. Null when the API did not give one.
 * Kept for 30 days per address and sub id; a failure is not kept, so the next click asks again.
 */
function v2_partner_link(string $url, string $sub = ''): ?string
{
    if (!v2_partners_on()) {
        return null;
    }
    $res = api_cached('tp_link_' . $url . '|' . $sub, function () use ($url, $sub) {
        $link = ['url' => $url];
        if ($sub !== '') {
            $link['sub_id'] = $sub;
        }
        $ch = curl_init('https://api.travelpayouts.com/links/v1/create');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_TIMEOUT => 6,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'X-Access-Token: ' . TRAVELPAYOUTS_TOKEN],
            CURLOPT_POSTFIELDS => json_encode([
                'trs' => TRAVELPAYOUTS_TRS, 'marker' => TRAVELPAYOUTS_MARKER, 'shorten' => false, 'links' => [$link],
            ]),
        ]);
        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $data = is_string($body) ? json_decode($body, true) : null;
        $made = $data['result']['links'][0] ?? null;
        if ($code !== 200 || !is_array($made) || empty($made['partner_url'])) {
            error_log('Travelpayouts links API: HTTP ' . $code . ' ' . substr((string) $body, 0, 300) . ' for ' . $url);
            return ['success' => false];
        }
        return ['success' => true, 'link' => (string) $made['partner_url']];
    }, 30 * 86400);

    return !empty($res['success']) ? $res['link'] : null;
}

/* ------------------------------------------------------------------ WeGoTrip */

/** ['cities' => [our city slug => WeGoTrip city id], 'attractions' => [our attraction slug => WeGoTrip attraction id]] */
function v2_wegotrip_index(): array
{
    static $index = null;
    if ($index === null) {
        $file = __DIR__ . '/partners/wegotrip.json';
        $index = is_file($file) ? (json_decode((string) file_get_contents($file), true) ?: []) : [];
    }
    return $index;
}

/**
 * Products for one WeGoTrip city or attraction, best sellers first.
 * Returns ['count' => total on WeGoTrip, 'items' => [...], 'all' => WeGoTrip's page of the city]; empty when partner offers are off, the place is not
 * in the index or the API did not answer.
 *
 * @param string $kind   'city' or 'attraction'
 * @param string $prefer a name (the attraction's): products whose title mentions it come first. WeGoTrip ties a
 *                       walking tour to every landmark on its route, so best sellers alone would put a city walk
 *                       above the ticket to the place itself.
 */
function v2_wegotrip_products(string $kind, string $slug, int $limit = 8, string $prefer = ''): array
{
    $none = ['count' => 0, 'items' => [], 'all' => ''];
    $id = (int) (v2_wegotrip_index()[$kind === 'city' ? 'cities' : 'attractions'][$slug] ?? 0);
    if ($id < 1 || !v2_partners_on()) {
        return $none;
    }
    $fetch = $prefer !== '' ? max($limit, 12) : $limit;
    $res = api_cached("wegotrip_{$kind}_{$id}_{$fetch}", function () use ($kind, $id, $fetch) {
        $url = 'https://app.wegotrip.com/api/v2/products/popular/?' . http_build_query([
            'lang' => 'en', 'currency' => SITE_CURRENCY, $kind => $id, 'per_page' => $fetch,
        ]);
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 3, CURLOPT_TIMEOUT => 6,
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
        ]);
        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $data = is_string($body) ? (json_decode($body, true)['data'] ?? null) : null;
        if ($code !== 200 || !is_array($data) || !isset($data['results'])) {
            error_log('WeGoTrip API: HTTP ' . $code . ' for ' . $url);
            return ['success' => false];
        }
        $items = [];
        foreach ($data['results'] as $p) {
            if (empty($p['id']) || empty($p['slug']) || empty($p['title']) || empty($p['city']['id']) || ($p['tags']['available'] ?? true) === false) {
                continue;
            }
            $items[] = [
                'title' => (string) $p['title'],
                // the address format of wegotrip.com, as the product detail returns it in `url`
                'url' => 'https://wegotrip.com/' . $p['city']['slug'] . '-d' . (int) $p['city']['id'] . '/' . $p['slug'] . '-p' . (int) $p['id'] . '/',
                'img' => (string) ($p['preview'] ?? ''),
                'price' => ($p['currencyCode'] ?? '') === SITE_CURRENCY ? (float) ($p['price'] ?? 0) : 0.0,
                'rating' => (float) ($p['rating'] ?? 0),
                'ratings' => (int) ($p['ratingsCount'] ?? 0),
                'duration' => (string) ($p['duration'] ?? ''),
                'category' => (string) ($p['category'] ?? ''),
                'city' => (string) ($p['city']['name'] ?? ''),
            ];
        }
        // WeGoTrip's own page of the city (its slug, not ours), for the "see all" link
        $first = $data['results'][0]['city'] ?? null;
        $all = ($kind === 'city' && !empty($first['slug'])) ? 'https://wegotrip.com/' . $first['slug'] . '-d' . $id . '/' : '';
        return ['success' => true, 'count' => (int) ($data['count'] ?? count($items)), 'items' => $items, 'all' => $all];
    }, 12 * 3600);

    if (empty($res['success'])) {
        return $none;
    }
    $items = $res['items'];
    if ($prefer !== '') {
        $words = array_filter(preg_split('/[^a-z0-9]+/', strtolower(v2_partner_ascii($prefer))), fn ($w) => strlen($w) >= 4);
        $score = function (array $p) use ($words): int {
            $title = strtolower(v2_partner_ascii($p['title']));
            return count(array_filter($words, fn ($w) => strpos($title, $w) !== false));
        };
        $rank = array_map($score, $items);
        $keys = array_keys($items);
        usort($keys, fn ($a, $b) => ($rank[$b] <=> $rank[$a]) ?: ($a <=> $b));   // ties keep the best-seller order
        $items = array_map(fn ($k) => $items[$k], $keys);
    }

    return ['count' => $res['count'], 'items' => array_slice($items, 0, $limit), 'all' => $res['all'] ?? ''];
}

/** Letters without their accents ("Musée" → "Musee"), for comparing names. */
function v2_partner_ascii(string $s): string
{
    $out = function_exists('iconv') ? @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s) : false;
    return $out !== false ? $out : $s;
}

/* ------------------------------------------------------------------ Aviasales */

/** Where fares are shown from: IATA city code => name. The large airports of Europe, one or two per country. */
const V2_FLIGHT_ORIGINS = [
    'LON' => 'London', 'PAR' => 'Paris', 'AMS' => 'Amsterdam', 'BER' => 'Berlin', 'FRA' => 'Frankfurt', 'MUC' => 'Munich',
    'MAD' => 'Madrid', 'BCN' => 'Barcelona', 'ROM' => 'Rome', 'MIL' => 'Milan', 'VIE' => 'Vienna', 'ZRH' => 'Zurich',
    'BRU' => 'Brussels', 'DUB' => 'Dublin', 'LIS' => 'Lisbon', 'ATH' => 'Athens', 'CPH' => 'Copenhagen',
    'STO' => 'Stockholm', 'OSL' => 'Oslo', 'HEL' => 'Helsinki', 'WAW' => 'Warsaw', 'PRG' => 'Prague', 'BUD' => 'Budapest',
    'BUH' => 'Bucharest',
];

/**
 * The cheapest return fares to one of our cities, one per origin, cheapest first.
 * Each: ['from' => 'London', 'price' => 47.0, 'out' => '2026-11-03', 'back' => '2026-11-10', 'direct' => true, 'url' => …].
 * Empty when partner offers are off, the city has no airport in the index or the API gave nothing.
 */
function v2_flights_to(string $slug, int $limit = 6): array
{
    static $index = null;
    if ($index === null) {
        $file = __DIR__ . '/partners/flights.json';
        $index = is_file($file) ? (json_decode((string) file_get_contents($file), true) ?: []) : [];
    }
    $code = (string) ($index[$slug] ?? '');
    if ($code === '' || !v2_partners_on()) {
        return [];
    }
    $res = api_cached('flights_to_' . $code, function () use ($code) {
        $url = 'https://api.travelpayouts.com/aviasales/v3/get_latest_prices?' . http_build_query([
            'destination' => $code, 'currency' => strtolower(SITE_CURRENCY), 'period_type' => 'year',
            'one_way' => 'false', 'sorting' => 'price', 'limit' => 1000,
        ]);
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 3, CURLOPT_TIMEOUT => 8, CURLOPT_ENCODING => '',
            CURLOPT_HTTPHEADER => ['Accept: application/json', 'X-Access-Token: ' . TRAVELPAYOUTS_TOKEN],
        ]);
        $body = curl_exec($ch);
        $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $rows = is_string($body) ? (json_decode($body, true)['data'] ?? null) : null;
        if ($http !== 200 || !is_array($rows)) {
            error_log('Aviasales data API: HTTP ' . $http . ' for ' . $code);
            return ['success' => false];
        }
        $best = [];
        foreach ($rows as $r) {
            $from = (string) ($r['origin'] ?? '');
            $price = (float) ($r['value'] ?? 0);
            if (!isset(V2_FLIGHT_ORIGINS[$from]) || $from === $code || $price <= 0 || ($r['actual'] ?? true) === false
                || empty($r['depart_date']) || empty($r['return_date'])) {
                continue;
            }
            if (!isset($best[$from]) || $price < $best[$from]['price']) {
                $out = substr((string) $r['depart_date'], 0, 10);
                $back = substr((string) $r['return_date'], 0, 10);
                $best[$from] = [
                    'from' => V2_FLIGHT_ORIGINS[$from], 'price' => $price, 'out' => $out, 'back' => $back,
                    'direct' => (int) ($r['number_of_changes'] ?? 0) === 0,
                    // Aviasales search address: origin, day and month out, destination, day and month back, one adult
                    'url' => 'https://www.aviasales.com/search/' . $from . substr($out, 8, 2) . substr($out, 5, 2) . $code
                        . substr($back, 8, 2) . substr($back, 5, 2) . '1?currency=' . strtolower(SITE_CURRENCY) . '&locale=en',
                ];
            }
        }
        usort($best, fn ($a, $b) => $a['price'] <=> $b['price']);
        return ['success' => true, 'fares' => array_slice($best, 0, 12)];
    }, 86400);

    if (empty($res['success'])) {
        return [];
    }
    // a fare found for a day that has passed since it was cached is no longer on sale
    $today = date('Y-m-d');
    return array_slice(array_values(array_filter($res['fares'], fn ($f) => $f['out'] > $today)), 0, $limit);
}

/** "3 – 10 Nov" / "28 Oct – 4 Nov" */
function v2_flight_dates(string $out, string $back): string
{
    $a = strtotime($out);
    $b = strtotime($back);
    if (!$a || !$b) {
        return '';
    }
    return date('M', $a) === date('M', $b) ? date('j', $a) . ' – ' . date('j M', $b) : date('j M', $a) . ' – ' . date('j M', $b);
}

/* ------------------------------------------------------------------ Plan your trip */

/**
 * The services on offer for a city (pass its slug) or for a whole country (pass '' and the country code).
 * A city takes what its country has when it has nothing of its own for the eSIM and the car.
 * Each: ['kind' => 'transfer', 'icon' => …, 'title' => …, 'text' => …, 'program' => …, 'name' => partner, 'url' => …].
 */
function v2_trip_links(string $citySlug, string $countryCode, string $cityName = '', string $countryName = ''): array
{
    static $index = null;
    if (!v2_partners_on()) {
        return [];
    }
    if ($index === null) {
        $file = __DIR__ . '/partners/trip.json';
        $index = is_file($file) ? (json_decode((string) file_get_contents($file), true) ?: []) : [];
    }
    $city = $citySlug !== '' ? ($index['city'][$citySlug] ?? []) : [];
    $country = $index['country'][strtoupper($countryCode)] ?? [];
    $countryName = v2_partner_the($countryName);
    $where = $cityName !== '' ? $cityName : $countryName;
    $carHere = isset($city['car']) && $cityName !== '';

    $copy = [
        'transfer' => ['path', 'Airport transfer', $citySlug !== ''
            ? 'A driver meets you at arrivals and takes you to your door in ' . $where . ', at a price fixed in advance.'
            : 'A driver meets you at arrivals, at a price fixed in advance, in the cities and resorts of ' . $where . '.'],
        'luggage' => ['lock-simple', 'Luggage storage', 'Leave your bags near the station or in the centre and see ' . $where . ' with your hands free.'],
        'esim' => ['phone', 'eSIM for ' . ($countryName !== '' ? $countryName : 'your trip'), 'Mobile data from the moment you land, with no roaming bill. Installed on your phone before you leave.'],
        'car' => ['compass', 'Car hire', $carHere
            ? 'Pick up a car in ' . $cityName . ' and see the towns and coast around it at your own pace.'
            : 'Hire a car' . ($countryName !== '' ? ' in ' . $countryName : '') . ' and reach the places the trains do not.'],
    ];
    $out = [];
    foreach (['transfer', 'luggage', 'esim', 'car'] as $kind) {
        // a country has no luggage page; a city borrows the country's eSIM and car pages
        $pick = $city[$kind] ?? (($kind === 'esim' || $kind === 'car' || $citySlug === '') ? ($country[$kind] ?? null) : null);
        if (!is_array($pick) || !isset(V2_PARTNER_PROGRAMS[$pick[0]]) || !v2_partner_url_ok($pick[0], (string) $pick[1])) {
            continue;
        }
        $out[] = ['kind' => $kind, 'icon' => $copy[$kind][0], 'title' => $copy[$kind][1], 'text' => $copy[$kind][2],
            'program' => $pick[0], 'name' => V2_PARTNER_PROGRAMS[$pick[0]]['name'], 'url' => (string) $pick[1]];
    }
    return $out;
}

/** "the United Kingdom", "the Netherlands", "the Faroe Islands"; other names unchanged. */
function v2_partner_the(string $country): string
{
    return preg_match('/^(United|Isle)\b|\b(Republic|Islands|Netherlands)$/', $country) ? 'the ' . $country : $country;
}

/** The tiles of the "Plan your trip" block and the line that says who sells. $sub is the sub id stem, e.g. "city-rome". */
function v2_trip_tiles(array $links, string $sub): string
{
    ob_start(); ?>
      <ul class="ptrip">
        <?php foreach ($links as $l): ?>
        <li><a href="<?= v2_e(v2_partner_href($l['program'], $l['url'], $sub . '-' . $l['kind'])) ?>" target="_blank" rel="sponsored nofollow noopener">
          <span class="ptrip-ic"><?= v2_ic($l['icon']) ?></span>
          <b><?= v2_e($l['title']) ?></b>
          <span class="ptrip-text"><?= v2_e($l['text']) ?></span>
          <span class="ptrip-go">On <?= v2_e($l['name']) ?><?= v2_ic('arrow-right') ?></span>
        </a></li>
        <?php endforeach; ?>
      </ul>
      <p class="partner-note">These services are sold by our partners: you book and pay on their sites. Viaqui may earn a commission, at no extra cost to you.</p>
    <?php
    return (string) ob_get_clean();
}

/** Cards for partner products, in the .xp markup the own listings use, each marked with the partner's name. */
function v2_partner_cards(array $items, string $program, string $sub): string
{
    $name = V2_PARTNER_PROGRAMS[$program]['name'] ?? '';
    ob_start();
    foreach ($items as $i => $p) { ?>
        <li class="xp xp-partner">
          <a href="<?= v2_e(v2_partner_href($program, $p['url'], $sub)) ?>" target="_blank" rel="sponsored nofollow noopener">
            <span class="xp-media"><?= $p['img'] !== '' ? v2_photo([$p['img'], 0, 0, '']) : v2_fallback($p['title'], $i) ?><span class="xp-via">on <?= v2_e($name) ?></span></span>
            <span class="xp-body">
              <?php if ($p['category'] !== ''): ?><span class="xp-cat"><?= v2_e($p['category']) ?></span><?php endif; ?>
              <span class="xp-title"><?= v2_e($p['title']) ?></span>
              <span class="xp-meta"><?php if ($p['duration'] !== ''): ?><span><?= v2_ic('clock') ?><?= v2_e($p['duration']) ?></span><?php endif; ?><?php if ($p['rating'] > 0 && $p['ratings'] >= 5): ?><span class="xp-rating"><?= v2_ic('star') ?><?= v2_e(number_format($p['rating'], 1)) ?> (<?= v2_e(v2_thousands($p['ratings'])) ?>)</span><?php endif; ?></span>
              <span class="xp-foot"><span class="xp-avail">Book on <?= v2_e($name) ?><?= v2_ic('arrow-right') ?></span><?php if ($p['price'] > 0): ?><span class="xp-price">from<b><?= v2_e(v2_money($p['price'])) ?></b></span><?php endif; ?></span>
            </span>
          </a>
        </li>
    <?php }
    return (string) ob_get_clean();
}

/** The line under a block of partner cards: who sells, and that we earn from it. */
function v2_partner_note(string $program): string
{
    $name = V2_PARTNER_PROGRAMS[$program]['name'] ?? 'our partner';
    return '<p class="partner-note">Sold and delivered by ' . v2_e($name) . '. You book on their site at their price; Viaqui may earn a commission, at no extra cost to you.</p>';
}
