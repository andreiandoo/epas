<?php
/**
 * bilete.online v2 homepage: data for the homepage sections, on top of the shell data in $V2NAV.
 *
 * One parallel round-trip to the marketplace API (each call cached on its own TTL), then one more for
 * the next ten days of the listed activities. While the catalogue is still thin, sections fall back to
 * other real data (castles and fortresses from the attractions list, categories instead of empty
 * recommendations) or are left out. Nothing here invents prices, counts or availability.
 *
 * Requires v2/nav.php. Sets $V2 = $V2NAV plus the homepage keys.
 */

const V2_ATTRACTION_TYPES = [
    'castel-palat' => 'Castel & palat', 'muzeu' => 'Muzeu', 'monument' => 'Monument',
    'biserica-manastire' => 'Biserică & mănăstire', 'parc-gradina' => 'Parc & grădină',
    'piata-centru-vechi' => 'Piață & centru vechi', 'cladire-istorica' => 'Clădire istorică',
    'punct-panoramic' => 'Punct panoramic', 'lac-natura' => 'Lac & natură', 'teatru-opera' => 'Teatru & operă',
];

// Shown in "Atracții de neratat" until attractions are marked as featured in the admin.
const V2_PICKED_ATTRACTIONS = [
    'castelul-bran-bran', 'castelul-peles-sinaia', 'muzeul-national-al-satului-dimitrie-gusti-bucuresti',
    'cetatea-poenari-arefu', 'castelul-banffy-de-la-bontida-bontida', 'palatul-mogosoaia-mogosoaia',
    'cetatea-deva-deva', 'cetatea-medievala-din-targu-mures-targu-mures', 'castelul-sturdza-de-la-miclauseni-miclauseni',
];

// Hero photo candidates: key (local WebP copies hero-{key}-{900,1440,1920}.webp) => [slug, name, city, type while the API has no record]
const V2_HERO_ATTRACTIONS = [
    'bran' => ['castelul-bran-bran', 'Castelul Bran', 'Bran', 'Castel & palat'],
    'peles' => ['castelul-peles-sinaia', 'Castelul Peleș', 'Sinaia', 'Castel & palat'],
    'banffy' => ['castelul-banffy-de-la-bontida-bontida', 'Castelul Bánffy de la Bonțida', 'Bonțida', 'Castel & palat'],
    'mogosoaia' => ['palatul-mogosoaia-mogosoaia', 'Palatul Mogoșoaia', 'Mogoșoaia', 'Castel & palat'],
    'targu-mures' => ['cetatea-medievala-din-targu-mures-targu-mures', 'Cetatea medievală din Târgu Mureș', 'Târgu Mureș', 'Castel & palat'],
];

// slug => [label, accent, intro, "see all" link, "see all" text, photo, photo focus, categories shown while no activity fits]
const V2_TRAVELERS = [
    'familii' => ['Familii', '#F2A900', 'Experiențe sigure, pe placul copiilor și al părinților.', '/activitati-copii', 'Toate experiențele pentru familii', 'who-familii', '44% 45%', ['familie-copii', 'acvarii-zoo-animale', 'parcuri-de-distractii']],
    'prieteni' => ['Prieteni', '#E43A33', 'Adrenalină, provocări și ieșiri despre care o să tot vorbiți.', '/cauta?traveler_types=prieteni', 'Toate experiențele pentru prieteni', 'who-prieteni', '60% 55%', ['escape-rooms', 'parcuri-de-aventura', 'natura-outdoor']],
    'cupluri' => ['Cupluri', '#2D6CCD', 'Locuri frumoase pentru o zi sau o seară în doi.', '/activitati-cuplu', 'Toate experiențele pentru cupluri', 'who-cupluri', '22% 50%', ['cultura-arta', 'tururi-experiente-turistice', 'muzee-expozitii']],
    'weekend' => ['Activități de weekend', '#2BB673', 'Idei pentru sâmbătă și duminică, în oraș sau într-o escapadă scurtă.', '/activitati-weekend', 'Toate activitățile de weekend', 'who-weekend', '50% 40%', ['natura-outdoor', 'parcuri-de-distractii', 'tururi-experiente-turistice']],
    'cadou' => ['Experiențe cadou', '#F2A900', 'Dăruiește o ieșire în locul unui obiect: un atelier, o aventură sau o zi într-un loc frumos.', '/card-cadou', 'Vezi cardul cadou', 'who-cadou', '50% 50%', ['ateliere-experiente-creative', 'parcuri-de-aventura', 'educatie-invatare-experientiala']],
];
// the traveller types activities are tagged with (traveler_types); "weekend" uses the next days' availability, "cadou" the categories
const V2_TRAVELER_TYPES = ['familii', 'prieteni', 'cupluri'];

const V2_FAQ = [
    ['Ce tip de bilete găsesc pe bilete.online?', 'Bilete pentru experiențe și atracții: escape rooms, parcuri de distracții, muzee, castele, parcuri de aventură, acvarii, grădini zoologice și ateliere. Toate locurile unde te duci să faci ceva.'],
    ['Cum primesc biletul după cumpărare?', 'Imediat după plată primești biletul cu cod QR pe email și în cont. La intrare doar îl scanezi, fără tipărire obligatorie.'],
    ['Pot anula sau reprograma un bilet?', 'Politica de anulare este stabilită de fiecare locație și e afișată clar pe pagina experienței, înainte de plată.'],
    ['Am o locație. Cum îmi listez activitatea?', 'Îți creezi cont de organizator, primești o pagină dedicată, optimizată pentru căutări, și începi să vinzi cu un comision de 2%. Platforma e operată tehnologic de Tixello.'],
    ['Pot oferi un card cadou?', 'Da. Cumperi un card cadou de orice valoare, scrii un mesaj personalizat și ajunge instant pe email. Se poate folosi la orice locație de pe platformă, timp de 12 luni.'],
    ['E nevoie de cont pentru a cumpăra?', 'Nu. Poți cumpăra ca invitat, doar cu un email. Contul te ajută însă să îți regăsești biletele și istoricul.'],
];

// ------------------------------------------------------------------ fetch
$v2Jobs = [
    'citiesTotal' => ['key' => 'v2_cities_total', 'endpoint' => '/locations/cities', 'params' => ['per_page' => 1], 'ttl' => 21600],
    'attrTotal' => ['key' => 'v2_attractions_total', 'endpoint' => '/attractions', 'params' => ['per_page' => 1], 'ttl' => 21600],
    'attrFeatured' => ['key' => 'v2_attractions_featured', 'endpoint' => '/attractions', 'params' => ['featured' => 1, 'per_page' => 9], 'ttl' => 900],
    'museum' => ['key' => 'v2_attraction_muzeul_satului', 'endpoint' => '/attractions/muzeul-national-al-satului-dimitrie-gusti-bucuresti', 'params' => [], 'ttl' => 86400],
    'activities' => ['key' => 'v2_activities', 'endpoint' => '/activities', 'params' => ['per_page' => 12], 'ttl' => 300],
];
for ($p = 1; $p <= 3; $p++) {
    $v2Jobs['castles' . $p] = ['key' => 'v2_castles_' . $p, 'endpoint' => '/attractions', 'params' => ['type' => 'castel-palat', 'per_page' => 50, 'page' => $p], 'ttl' => 86400];
}
foreach (V2_TRAVELER_TYPES as $k) {
    $v2Jobs['who_' . $k] = ['key' => 'v2_who_' . $k, 'endpoint' => '/activities', 'params' => ['traveler_types' => $k, 'per_page' => 3], 'ttl' => 600];
}
foreach (array_keys(V2_ATTRACTION_TYPES) as $t) {
    $v2Jobs['type_' . $t] = ['key' => 'v2_type_' . $t, 'endpoint' => '/attractions', 'params' => ['type' => $t, 'per_page' => 1], 'ttl' => 86400];
}
$v2R = api_cached_many($v2Jobs);
$v2Data = function (string $k) use ($v2R): array {
    return !empty($v2R[$k]['success']) && is_array($v2R[$k]['data'] ?? null) ? $v2R[$k]['data'] : [];
};

$V2 = $V2NAV;

// ------------------------------------------------------------------ destinations and totals
$V2['destinations'] = [];
foreach (['brasov', 'sibiu', 'bucuresti', 'constanta', 'sighisoara', 'cluj-napoca', 'timisoara'] as $s) {
    if (isset($V2['cities'][$s])) {
        $V2['destinations'][] = $V2['cities'][$s];
    }
}
foreach ($V2['citiesList'] as $c) {
    if (count($V2['destinations']) >= 7) {
        break;
    }
    if (!in_array($c['slug'], array_column($V2['destinations'], 'slug'), true)) {
        $V2['destinations'][] = $c;
    }
}
$V2['totals'] = [
    'attractions' => (int) ($v2Data('attrTotal')['pagination']['total'] ?? 0),
    'cities' => (int) ($v2R['citiesTotal']['meta']['total'] ?? 0),
];

// ------------------------------------------------------------------ attractions
$v2Pool = [];
for ($p = 1; $p <= 3; $p++) {
    foreach ((array) ($v2Data('castles' . $p)['items'] ?? []) as $a) {
        if (is_array($a) && ($n = v2_attraction($a))) {
            $v2Pool[$n['slug']] = $n;
        }
    }
}
$v2Museum = $v2Data('museum')['attraction'] ?? null;
if (is_array($v2Museum) && ($n = v2_attraction($v2Museum))) {
    $v2Pool[$n['slug']] = $n;
}
$v2Featured = [];
foreach ((array) ($v2Data('attrFeatured')['items'] ?? []) as $a) {
    if (is_array($a) && ($n = v2_attraction($a)) && $n['image']) {
        $v2Featured[] = $n;
    }
}
$v2Picked = [];
foreach (V2_PICKED_ATTRACTIONS as $s) {
    if (!empty($v2Pool[$s]['image'])) {
        $v2Picked[] = $v2Pool[$s];
    }
}
$V2['attractions'] = count($v2Featured) >= 4 ? $v2Featured : $v2Picked;
$V2['topAttractions'] = $V2['attractions'];
$v2Seen = array_column($V2['topAttractions'], 'slug');
foreach ($v2Pool as $s => $a) {
    if (count($V2['topAttractions']) >= 24) {
        break;
    }
    if (!in_array($s, $v2Seen, true) && preg_match('/^(castelul|cetatea|palatul-(parlamentului|cotroceni|mogosoaia))-/', $s)) {
        $V2['topAttractions'][] = $a;
        $v2Seen[] = $s;
    }
}
$V2['heroOptions'] = [];
foreach (V2_HERO_ATTRACTIONS as $key => [$slug, $name, $city, $type]) {
    $a = $v2Pool[$slug] ?? null;
    $V2['heroOptions'][] = [
        'key' => $key,
        'name' => $a['name'] ?? $name,
        'city' => $a ? $a['city'] : $city,
        'type' => ($a['type'] ?? '') !== '' ? $a['type'] : $type,
        'href' => $a['href'] ?? '/atractie/' . $slug,
    ];
}

$V2['types'] = [];
foreach (V2_ATTRACTION_TYPES as $slug => $label) {
    $total = (int) ($v2Data('type_' . $slug)['pagination']['total'] ?? 0);
    if ($total > 0) {
        $V2['types'][] = ['slug' => $slug, 'name' => $label, 'count' => $total];
    }
}
usort($V2['types'], function ($a, $b) {
    return $b['count'] <=> $a['count'];
});

$V2['popular'] = array_map(function ($a) {
    return ['name' => $a['name'], 'href' => $a['href']];
}, array_slice($V2['attractions'], 0, 3));
if (isset($V2['cities']['bucuresti'])) {
    $V2['popular'][] = ['name' => $V2['cities']['bucuresti']['name'], 'href' => $V2['cities']['bucuresti']['href']];
}

// ------------------------------------------------------------------ guides block
$V2['guideLead'] = null;
foreach ($V2['guides'] as $g) {
    if ($g['hasOwnImage']) {
        $V2['guideLead'] = $g;
        break;
    }
}
$V2['guideLead'] = $V2['guideLead'] ?? ($V2['guides'][0] ?? null);
$V2['guideSide'] = [];
foreach ($V2['guides'] as $g) {
    if (count($V2['guideSide']) >= 2) {
        break;
    }
    if ($V2['guideLead'] && $g['slug'] !== $V2['guideLead']['slug'] && $g['cover']) {
        $V2['guideSide'][] = $g;
    }
}

// ------------------------------------------------------------------ activities and the next ten days
$v2Tz = new DateTimeZone('Europe/Bucharest');
$v2Today = new DateTimeImmutable('today', $v2Tz);
$V2['today'] = $v2Today->format('Y-m-d');
$v2Months = ['ianuarie', 'februarie', 'martie', 'aprilie', 'mai', 'iunie', 'iulie', 'august', 'septembrie', 'octombrie', 'noiembrie', 'decembrie'];
$V2['monthLabel'] = $v2Months[(int) $v2Today->format('n') - 1] . ' ' . $v2Today->format('Y');
$v2DowShort = ['dum', 'lun', 'mar', 'mie', 'joi', 'vin', 'sâm'];
$v2DowLong = ['duminică', 'luni', 'marți', 'miercuri', 'joi', 'vineri', 'sâmbătă'];

$v2Acts = [];
foreach ((array) ($v2Data('activities')['items'] ?? []) as $a) {
    if (is_array($a) && ($n = v2_activity($a))) {
        $v2Acts[] = $n;
    }
}
if ($v2Acts) {
    $dateJobs = [];
    foreach ($v2Acts as $a) {
        $dateJobs[$a['slug']] = ['key' => 'v2_dates_' . $a['slug'], 'endpoint' => '/activities/' . rawurlencode($a['slug']) . '/available-dates', 'params' => ['days' => 10], 'ttl' => 600];
    }
    $v2Dates = api_cached_many($dateJobs);
    foreach ($v2Acts as $i => $a) {
        $dates = $v2Dates[$a['slug']]['data']['dates'] ?? [];
        $dates = array_values(array_filter((array) $dates, function ($d) {
            return is_string($d) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $d);
        }));
        $v2Acts[$i]['dates'] = $dates;
        $next = null;
        foreach ($dates as $d) {
            if ($d >= $V2['today']) {
                $next = $d;
                break;
            }
        }
        $label = '';
        if ($next) {
            $nextDay = new DateTimeImmutable($next, $v2Tz);
            $diff = (int) $v2Today->diff($nextDay)->format('%r%a');
            $label = $diff === 0 ? 'azi' : ($diff === 1 ? 'mâine' : $v2DowLong[(int) $nextDay->format('w')]);
        }
        $v2Acts[$i]['nextLabel'] = $label;
    }
}
$V2['activities'] = $v2Acts;
$V2['days'] = [];
$v2WeekendDays = [];
for ($i = 0; $i < 10; $i++) {
    $d = $v2Today->modify('+' . $i . ' day');
    $iso = $d->format('Y-m-d');
    if (in_array((int) $d->format('w'), [0, 6], true)) {
        $v2WeekendDays[] = $iso;
    }
    $count = 0;
    foreach ($v2Acts as $a) {
        if (in_array($iso, $a['dates'], true)) {
            $count++;
        }
    }
    $V2['days'][] = ['iso' => $iso, 'label' => $i === 0 ? 'Azi' : ($i === 1 ? 'Mâine' : $v2DowShort[(int) $d->format('w')]), 'day' => (int) $d->format('j'), 'count' => $count];
}
$V2['hasDates'] = array_sum(array_column($V2['days'], 'count')) > 0;
$v2Prices = array_filter(array_column($v2Acts, 'price'));
$V2['minPrice'] = $v2Prices ? min($v2Prices) : 0;
$V2['activityTabs'] = ['all' => 'Toate'];
foreach ($v2Acts as $a) {
    if ($a['cat'] !== '' && !isset($V2['activityTabs'][$a['cat']])) {
        $V2['activityTabs'][$a['cat']] = $a['catName'] ?: $a['cat'];
    }
}

// ------------------------------------------------------------------ recommended for
$V2['who'] = [];
$v2WhoCard = function (array $n): array {
    return [
        'title' => $n['title'],
        'meta' => v2_e($n['city']) . ($n['price'] ? ($n['city'] ? ', ' : '') . 'de la <b>' . $n['price'] . ' lei</b>' : ''),
        'image' => $n['image'],
        'href' => $n['href'],
    ];
};
foreach (V2_TRAVELERS as $k => [$label, $color, $intro, $href, $allText, $img, $pos, $fallbackCats]) {
    $cards = [];
    if (in_array($k, V2_TRAVELER_TYPES, true)) {
        foreach ((array) ($v2Data('who_' . $k)['items'] ?? []) as $a) {
            if (count($cards) < 3 && is_array($a) && ($n = v2_activity($a))) {
                $cards[] = $v2WhoCard($n);
            }
        }
    } elseif ($k === 'weekend') {
        foreach ($v2Acts as $n) {
            if (count($cards) < 3 && array_intersect($n['dates'], $v2WeekendDays)) {
                $cards[] = $v2WhoCard($n);
            }
        }
    }
    if (!$cards) {
        foreach ($fallbackCats as $s) {
            if (isset($V2['categoryBySlug'][$s])) {
                $c = $V2['categoryBySlug'][$s];
                $cards[] = ['title' => $c['name'], 'meta' => 'Categorie', 'image' => $c['thumb'], 'href' => $c['href']];
            }
        }
    }
    $V2['who'][] = ['k' => $k, 'label' => $label, 'color' => $color, 'intro' => $intro, 'href' => $href, 'all' => $allText, 'img' => $img, 'pos' => $pos, 'cards' => $cards];
}

// ------------------------------------------------------------------ search suggestions (client side)
$V2['suggest'] = [
    'cities' => array_map(function ($c) {
        return [$c['name'], $c['region'], $c['href']];
    }, array_slice($V2['citiesList'], 0, 40)),
    'attractions' => array_map(function ($a) {
        return [$a['name'], $a['city'], $a['href']];
    }, $V2['topAttractions']),
    'categories' => array_map(function ($c) {
        return [$c['name'], 'Categorie', $c['href']];
    }, $V2['categories']),
];
