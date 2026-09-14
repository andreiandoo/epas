<?php
/**
 * bilete.online — homepage v2: data for every section.
 *
 * One parallel round-trip to the marketplace API (each call cached on its own TTL through
 * api_cached_many), then one more for the next ten days of the listed activities. While the
 * catalogue is still thin, sections fall back to other real data (castles and fortresses from
 * the attractions list, categories instead of empty recommendations) or are left out. Nothing
 * here invents prices, counts or availability.
 *
 * Requires includes/config.php, api.php, nav-helpers.php and home-v2/helpers.php.
 */

const HV2_REGIONS = ['Transilvania', 'Muntenia', 'Moldova', 'Oltenia', 'Dobrogea', 'Crișana', 'Banat', 'Maramureș'];

const HV2_ATTRACTION_TYPES = [
    'castel-palat' => 'Castel & palat', 'muzeu' => 'Muzeu', 'monument' => 'Monument',
    'biserica-manastire' => 'Biserică & mănăstire', 'parc-gradina' => 'Parc & grădină',
    'piata-centru-vechi' => 'Piață & centru vechi', 'cladire-istorica' => 'Clădire istorică',
    'punct-panoramic' => 'Punct panoramic', 'lac-natura' => 'Lac & natură', 'teatru-opera' => 'Teatru & operă',
];

// Shown in "Atracții de neratat" until attractions are marked as featured in the admin.
const HV2_PICKED_ATTRACTIONS = [
    'castelul-bran-bran', 'castelul-peles-sinaia', 'muzeul-national-al-satului-dimitrie-gusti-bucuresti',
    'cetatea-poenari-arefu', 'castelul-banffy-de-la-bontida-bontida', 'palatul-mogosoaia-mogosoaia',
    'cetatea-deva-deva', 'cetatea-medievala-din-targu-mures-targu-mures', 'castelul-sturdza-de-la-miclauseni-miclauseni',
];

// slug => [label, accent, intro, "see all" link, photo, photo focus, categories shown while no activity is tagged]
const HV2_TRAVELERS = [
    'familii' => ['Familii', '#F2A900', 'Experiențe sigure, pe placul copiilor și al părinților.', '/activitati-copii', 'who-familii', '44% 45%', ['familie-copii', 'acvarii-zoo-animale', 'parcuri-de-distractii']],
    'prieteni' => ['Prieteni', '#E43A33', 'Adrenalină, provocări și ieșiri despre care o să tot vorbiți.', '/cauta?traveler_types=prieteni', 'who-prieteni', '60% 55%', ['escape-rooms', 'parcuri-de-aventura', 'natura-outdoor']],
    'cupluri' => ['Cupluri', '#2D6CCD', 'Locuri frumoase pentru o zi sau o seară în doi.', '/activitati-cupluri', 'who-cupluri', '22% 50%', ['cultura-arta', 'tururi-experiente-turistice', 'muzee-expozitii']],
];

// Provisional photos (Wikimedia Commons, credited in the footer) for cities whose record has no image yet.
const HV2_CITY_PHOTOS = [
    'brasov' => ['img/dest-brasov.webp', 960, 800, 'Piața Sfatului din Brașov, văzută de sus'],
    'sibiu' => ['img/dest-sibiu.webp', 640, 540, 'Piața Mare din Sibiu, cu Turnul Sfatului'],
    'bucuresti' => ['img/dest-bucuresti.webp', 640, 540, 'Ateneul Român din București'],
    'constanta' => ['img/dest-constanta.webp', 640, 540, 'Cazinoul din Constanța, pe faleză'],
    'sighisoara' => ['img/dest-sighisoara.webp', 640, 540, 'Stradă cu case colorate în cetatea Sighișoara'],
];

// Provisional guide images while most articles have no cover in the API.
const HV2_GUIDE_THUMBS = [
    'ce-sa-vizitezi-in-maramures-itinerar-weekend' => 'img/g-maramures.webp',
    'ce-sa-vizitezi-in-brasov-itinerar-weekend' => 'img/g-brasov.webp',
    'activitati-in-bucuresti-ce-poti-face-in-oras-cand-vrei-o-iesire-altfel' => 'img/g-bucuresti.webp',
    'parcuri-de-distractie-cluj' => 'img/g-cluj.webp',
    'experiente-extreme-pe-litoral' => 'img/g-litoral.webp',
    'ce-sa-vizitezi-sinaia-bucegi' => 'img/g-sinaia.webp',
];
const HV2_GUIDE_COVERS = [
    'ce-sa-vizitezi-in-brasov-itinerar-weekend' => ['img/insp-brasov.webp', 640, 480, 'Piața Sfatului din Brașov, cu Tâmpa în fundal'],
    'ce-sa-vizitezi-sinaia-bucegi' => ['img/hero-900.webp', 900, 643, 'Castelul Peleș din Sinaia'],
];

const HV2_BLOG_CATEGORIES = [
    'Family & kids' => 'Familie & copii', 'City guides' => 'Ghiduri de oraș',
    'Adventure & adrenaline' => 'Aventură & adrenalină', 'Culture & history' => 'Cultură & istorie',
];

const HV2_FAQ = [
    ['Ce tip de bilete găsesc pe bilete.online?', 'Bilete pentru experiențe și atracții: escape rooms, parcuri de distracții, muzee, castele, parcuri de aventură, acvarii, grădini zoologice și ateliere. Toate locurile unde te duci să faci ceva.'],
    ['Cum primesc biletul după cumpărare?', 'Imediat după plată primești biletul cu cod QR pe email și în cont. La intrare doar îl scanezi, fără tipărire obligatorie.'],
    ['Pot anula sau reprograma un bilet?', 'Politica de anulare este stabilită de fiecare locație și e afișată clar pe pagina experienței, înainte de plată.'],
    ['Am o locație. Cum îmi listez activitatea?', 'Îți creezi cont de organizator, primești o pagină dedicată, optimizată pentru căutări, și începi să vinzi cu un comision de 1%. Platforma e operată tehnologic de Tixello.'],
    ['Pot oferi un card cadou?', 'Da. Cumperi un card cadou de orice valoare, scrii un mesaj personalizat și ajunge instant pe email. Se poate folosi la orice locație de pe platformă, timp de 12 luni.'],
    ['E nevoie de cont pentru a cumpăra?', 'Nu. Poți cumpăra ca invitat, doar cu un email. Contul te ajută însă să îți regăsești biletele și istoricul.'],
];

// ------------------------------------------------------------------ fetch
$hv2Jobs = [
    'cats' => ['key' => 'hv2_categories_all', 'endpoint' => '/events/categories', 'params' => ['all' => 1], 'ttl' => 900],
    'cities' => ['key' => 'hv2_cities_featured', 'endpoint' => '/locations/cities/featured', 'params' => [], 'ttl' => 1800],
    'regions' => ['key' => 'hv2_regions', 'endpoint' => '/locations/regions', 'params' => [], 'ttl' => 3600],
    'citiesTotal' => ['key' => 'hv2_cities_total', 'endpoint' => '/locations/cities', 'params' => ['per_page' => 1], 'ttl' => 21600],
    'attrTotal' => ['key' => 'hv2_attractions_total', 'endpoint' => '/attractions', 'params' => ['per_page' => 1], 'ttl' => 21600],
    'attrFeatured' => ['key' => 'hv2_attractions_featured', 'endpoint' => '/attractions', 'params' => ['featured' => 1, 'per_page' => 9], 'ttl' => 900],
    'museum' => ['key' => 'hv2_attraction_muzeul_satului', 'endpoint' => '/attractions/muzeul-national-al-satului-dimitrie-gusti-bucuresti', 'params' => [], 'ttl' => 86400],
    'blog' => ['key' => 'hv2_blog', 'endpoint' => '/blog-articles', 'params' => ['per_page' => 6, 'status' => 'published'], 'ttl' => 900],
    'activities' => ['key' => 'hv2_activities', 'endpoint' => '/activities', 'params' => ['per_page' => 12], 'ttl' => 300],
];
for ($p = 1; $p <= 3; $p++) {
    $hv2Jobs['castles' . $p] = ['key' => 'hv2_castles_' . $p, 'endpoint' => '/attractions', 'params' => ['type' => 'castel-palat', 'per_page' => 50, 'page' => $p], 'ttl' => 86400];
}
foreach (array_keys(HV2_TRAVELERS) as $k) {
    $hv2Jobs['who_' . $k] = ['key' => 'hv2_who_' . $k, 'endpoint' => '/activities', 'params' => ['traveler_types' => $k, 'per_page' => 3], 'ttl' => 600];
}
foreach (array_keys(HV2_ATTRACTION_TYPES) as $t) {
    $hv2Jobs['type_' . $t] = ['key' => 'hv2_type_' . $t, 'endpoint' => '/attractions', 'params' => ['type' => $t, 'per_page' => 1], 'ttl' => 86400];
}
$hv2R = api_cached_many($hv2Jobs);
$hv2Data = function (string $k) use ($hv2R): array {
    return !empty($hv2R[$k]['success']) && is_array($hv2R[$k]['data'] ?? null) ? $hv2R[$k]['data'] : [];
};

$HV2 = [];

// ------------------------------------------------------------------ categories (+ subcategories)
$hv2Parents = [];
$hv2Children = [];
foreach ((array) ($hv2Data('cats')['categories'] ?? []) as $c) {
    if (!is_array($c) || empty($c['slug'])) {
        continue;
    }
    if (empty($c['parent_id'])) {
        $hv2Parents[] = $c;
    } else {
        $hv2Children[$c['parent_id']][] = $c;
    }
}
// WebP copies (320 and 640 px) of the platform's category images: the originals are 700-800 KB PNGs each.
// A category added later falls back to its API image until it gets a copy here.
const HV2_CATEGORY_PHOTOS = [
    'escape-rooms', 'muzee-expozitii', 'parcuri-de-distractii', 'parcuri-de-aventura', 'natura-outdoor', 'acvarii-zoo-animale',
    'ateliere-experiente-creative', 'tururi-experiente-turistice', 'educatie-invatare-experientiala', 'familie-copii',
    'corporate-grupuri', 'cultura-arta',
];
$HV2['categories'] = [];
foreach ($hv2Parents as $c) {
    $local = in_array($c['slug'], HV2_CATEGORY_PHOTOS, true);
    $apiImage = hv2_media_url($c['image'] ?? null);
    $subs = [];
    foreach (array_slice($hv2Children[$c['id'] ?? 0] ?? [], 0, 10) as $s) {
        $s['parent_slug'] = $c['slug'];
        $subs[] = ['name' => navFlatName($s['name'] ?? ''), 'href' => '/' . bo_short_category_slug($s)];
    }
    $HV2['categories'][] = [
        'slug' => $c['slug'],
        'name' => navFlatName($c['name'] ?? ''),
        'desc' => trim((string) ($c['description'] ?? '')),
        'image' => $local ? hv2_asset('img/cat-' . $c['slug'] . '.webp') : $apiImage,
        'thumb' => $local ? hv2_asset('img/cat-' . $c['slug'] . '-320.webp') : $apiImage,
        'srcset' => $local ? hv2_asset('img/cat-' . $c['slug'] . '-320.webp') . ' 320w, ' . hv2_asset('img/cat-' . $c['slug'] . '.webp') . ' 640w' : '',
        'count' => (int) ($c['activities_count'] ?? 0) ?: (int) ($c['event_count'] ?? 0),
        'href' => '/' . $c['slug'],
        'subs' => $subs,
    ];
}
$HV2['categoryBySlug'] = array_column($HV2['categories'], null, 'slug');

// ------------------------------------------------------------------ cities and regions
$hv2Cities = [];
foreach ((array) ($hv2Data('cities')['cities'] ?? []) as $c) {
    if (!is_array($c) || empty($c['slug'])) {
        continue;
    }
    $img = hv2_media_url($c['image'] ?? null);
    $local = HV2_CITY_PHOTOS[$c['slug']] ?? null;
    $hv2Cities[$c['slug']] = [
        'slug' => $c['slug'],
        'name' => navFlatName($c['name'] ?? ''),
        'region' => (string) ($c['region'] ?? ''),
        'count' => (int) ($c['activities_count'] ?? 0) ?: (int) ($c['events_count'] ?? 0),
        'capital' => !empty($c['is_capital']),
        'href' => '/' . $c['slug'],
        'photo' => $img ? [$img, 0, 0, ''] : ($local ? [hv2_asset($local[0]), $local[1], $local[2], $local[3]] : null),
    ];
}
// Most experiences first; while counts are still equal, the large tourist cities lead instead of the alphabet.
$hv2CityRank = array_flip(['bucuresti', 'brasov', 'cluj-napoca', 'sibiu', 'constanta', 'timisoara', 'iasi', 'oradea', 'sighisoara', 'sinaia',
    'craiova', 'alba-iulia', 'targu-mures', 'baia-mare', 'suceava', 'tulcea', 'arad', 'pitesti', 'galati', 'ploiesti', 'hunedoara', 'bran']);
uasort($hv2Cities, function ($a, $b) use ($hv2CityRank) {
    return [$b['count'], (int) $b['capital'], $hv2CityRank[$a['slug']] ?? 999, $a['name']]
        <=> [$a['count'], (int) $a['capital'], $hv2CityRank[$b['slug']] ?? 999, $b['name']];
});
$HV2['cities'] = $hv2Cities;
$HV2['citiesList'] = array_values($hv2Cities);

$hv2RegionsRaw = $hv2Data('regions');
$hv2RegionsRaw = isset($hv2RegionsRaw['regions']) ? $hv2RegionsRaw['regions'] : $hv2RegionsRaw;
$hv2RegionInfo = [];
foreach ((array) $hv2RegionsRaw as $r) {
    if (is_array($r) && !empty($r['name'])) {
        $hv2RegionInfo[$r['name']] = $r;
    }
}
$HV2['regions'] = [];
foreach (HV2_REGIONS as $name) {
    $featured = array_values(array_filter($HV2['citiesList'], function ($c) use ($name) {
        return $c['region'] === $name;
    }));
    $seen = array_column($featured, 'slug');
    $more = [];
    foreach ((array) ($hv2RegionInfo[$name]['top_cities'] ?? []) as $tc) {
        if (is_array($tc) && !empty($tc['slug']) && !in_array($tc['slug'], $seen, true)) {
            $more[] = ['name' => navFlatName($tc['name'] ?? ''), 'href' => '/' . $tc['slug']];
            $seen[] = $tc['slug'];
        }
    }
    $HV2['regions'][] = [
        'name' => $name,
        'citiesCount' => (int) ($hv2RegionInfo[$name]['cities_count'] ?? count($featured)),
        'featured' => $featured,
        'more' => $more,
    ];
}

$HV2['destinations'] = [];
foreach (['brasov', 'sibiu', 'bucuresti', 'constanta', 'sighisoara', 'cluj-napoca', 'timisoara'] as $s) {
    if (isset($hv2Cities[$s])) {
        $HV2['destinations'][] = $hv2Cities[$s];
    }
}
foreach ($HV2['citiesList'] as $c) {
    if (count($HV2['destinations']) >= 7) {
        break;
    }
    if (!in_array($c['slug'], array_column($HV2['destinations'], 'slug'), true)) {
        $HV2['destinations'][] = $c;
    }
}

$HV2['totals'] = [
    'attractions' => (int) ($hv2Data('attrTotal')['pagination']['total'] ?? 0),
    'cities' => (int) ($hv2R['citiesTotal']['meta']['total'] ?? 0),
];

// ------------------------------------------------------------------ attractions
$hv2Pool = [];
for ($p = 1; $p <= 3; $p++) {
    foreach ((array) ($hv2Data('castles' . $p)['items'] ?? []) as $a) {
        if (is_array($a) && ($n = hv2_attraction($a))) {
            $hv2Pool[$n['slug']] = $n;
        }
    }
}
$hv2Museum = $hv2Data('museum')['attraction'] ?? null;
if (is_array($hv2Museum) && ($n = hv2_attraction($hv2Museum))) {
    $hv2Pool[$n['slug']] = $n;
}
$hv2Featured = [];
foreach ((array) ($hv2Data('attrFeatured')['items'] ?? []) as $a) {
    if (is_array($a) && ($n = hv2_attraction($a)) && $n['image']) {
        $hv2Featured[] = $n;
    }
}
$hv2Picked = [];
foreach (HV2_PICKED_ATTRACTIONS as $s) {
    if (!empty($hv2Pool[$s]['image'])) {
        $hv2Picked[] = $hv2Pool[$s];
    }
}
$HV2['attractions'] = count($hv2Featured) >= 4 ? $hv2Featured : $hv2Picked;
$HV2['topAttractions'] = $HV2['attractions'];
$hv2Seen = array_column($HV2['topAttractions'], 'slug');
foreach ($hv2Pool as $s => $a) {
    if (count($HV2['topAttractions']) >= 24) {
        break;
    }
    if (!in_array($s, $hv2Seen, true) && preg_match('/^(castelul|cetatea|palatul-(parlamentului|cotroceni|mogosoaia))-/', $s)) {
        $HV2['topAttractions'][] = $a;
        $hv2Seen[] = $s;
    }
}
$HV2['heroAttraction'] = $hv2Pool['castelul-peles-sinaia'] ?? null;

$HV2['types'] = [];
foreach (HV2_ATTRACTION_TYPES as $slug => $label) {
    $total = (int) ($hv2Data('type_' . $slug)['pagination']['total'] ?? 0);
    if ($total > 0) {
        $HV2['types'][] = ['slug' => $slug, 'name' => $label, 'count' => $total];
    }
}
usort($HV2['types'], function ($a, $b) {
    return $b['count'] <=> $a['count'];
});

$HV2['popular'] = array_map(function ($a) {
    return ['name' => $a['name'], 'href' => $a['href']];
}, array_slice($HV2['attractions'], 0, 3));
if (isset($hv2Cities['bucuresti'])) {
    $HV2['popular'][] = ['name' => $hv2Cities['bucuresti']['name'], 'href' => $hv2Cities['bucuresti']['href']];
}

// ------------------------------------------------------------------ guides
$hv2Blog = $hv2Data('blog');
$hv2Blog = $hv2Blog['articles'] ?? $hv2Blog['items'] ?? $hv2Blog;
$HV2['guides'] = [];
foreach ((array) $hv2Blog as $b) {
    if (!is_array($b) || empty($b['slug'])) {
        continue;
    }
    $title = navFlatName($b['title'] ?? '');
    $img = hv2_media_url($b['image_url'] ?? null);
    $catName = is_array($b['category'] ?? null) ? (string) ($b['category']['name'] ?? '') : '';
    $excerpt = trim((string) ($b['excerpt'] ?? ''));
    if (mb_strlen($excerpt) > 140) {
        $cut = mb_substr($excerpt, 0, 140);
        $excerpt = rtrim(mb_substr($cut, 0, mb_strrpos($cut, ' ') ?: 140), " ,.;:") . '…';
    }
    $local = HV2_GUIDE_COVERS[$b['slug']] ?? null;
    $HV2['guides'][] = [
        'slug' => $b['slug'],
        'title' => $title,
        'excerpt' => $excerpt,
        'category' => HV2_BLOG_CATEGORIES[$catName] ?? $catName,
        'readTime' => (int) ($b['read_time'] ?? 0),
        'href' => '/ghiduri/' . $b['slug'],
        'thumb' => $img ?: (isset(HV2_GUIDE_THUMBS[$b['slug']]) ? hv2_asset(HV2_GUIDE_THUMBS[$b['slug']]) : null),
        'cover' => $img ? [$img, 0, 0, 'Imagine din ghidul: ' . $title] : ($local ? [hv2_asset($local[0]), $local[1], $local[2], $local[3]] : null),
        'hasOwnImage' => (bool) $img,
    ];
}
$HV2['guideLead'] = null;
foreach ($HV2['guides'] as $g) {
    if ($g['hasOwnImage']) {
        $HV2['guideLead'] = $g;
        break;
    }
}
$HV2['guideLead'] = $HV2['guideLead'] ?? ($HV2['guides'][0] ?? null);
$HV2['guideSide'] = [];
foreach ($HV2['guides'] as $g) {
    if (count($HV2['guideSide']) >= 2) {
        break;
    }
    if ($HV2['guideLead'] && $g['slug'] !== $HV2['guideLead']['slug'] && $g['cover']) {
        $HV2['guideSide'][] = $g;
    }
}

// ------------------------------------------------------------------ activities and the next ten days
$hv2Tz = new DateTimeZone('Europe/Bucharest');
$hv2Today = new DateTimeImmutable('today', $hv2Tz);
$HV2['today'] = $hv2Today->format('Y-m-d');
$hv2Months = ['ianuarie', 'februarie', 'martie', 'aprilie', 'mai', 'iunie', 'iulie', 'august', 'septembrie', 'octombrie', 'noiembrie', 'decembrie'];
$HV2['monthLabel'] = $hv2Months[(int) $hv2Today->format('n') - 1] . ' ' . $hv2Today->format('Y');
$hv2DowShort = ['dum', 'lun', 'mar', 'mie', 'joi', 'vin', 'sâm'];
$hv2DowLong = ['duminică', 'luni', 'marți', 'miercuri', 'joi', 'vineri', 'sâmbătă'];

$hv2Acts = [];
foreach ((array) ($hv2Data('activities')['items'] ?? []) as $a) {
    if (is_array($a) && ($n = hv2_activity($a))) {
        $hv2Acts[] = $n;
    }
}
if ($hv2Acts) {
    $dateJobs = [];
    foreach ($hv2Acts as $a) {
        $dateJobs[$a['slug']] = ['key' => 'hv2_dates_' . $a['slug'], 'endpoint' => '/activities/' . rawurlencode($a['slug']) . '/available-dates', 'params' => ['days' => 10], 'ttl' => 600];
    }
    $hv2Dates = api_cached_many($dateJobs);
    foreach ($hv2Acts as $i => $a) {
        $dates = $hv2Dates[$a['slug']]['data']['dates'] ?? [];
        $dates = array_values(array_filter((array) $dates, function ($d) {
            return is_string($d) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $d);
        }));
        $hv2Acts[$i]['dates'] = $dates;
        $next = null;
        foreach ($dates as $d) {
            if ($d >= $HV2['today']) {
                $next = $d;
                break;
            }
        }
        $label = '';
        if ($next) {
            $diff = (int) $hv2Today->diff(new DateTimeImmutable($next, $hv2Tz))->format('%r%a');
            $label = $diff === 0 ? 'azi' : ($diff === 1 ? 'mâine' : $hv2DowLong[(int) (new DateTimeImmutable($next, $hv2Tz))->format('w')]);
        }
        $hv2Acts[$i]['nextLabel'] = $label;
    }
}
$HV2['activities'] = $hv2Acts;
$HV2['days'] = [];
for ($i = 0; $i < 10; $i++) {
    $d = $hv2Today->modify('+' . $i . ' day');
    $iso = $d->format('Y-m-d');
    $count = 0;
    foreach ($hv2Acts as $a) {
        if (in_array($iso, $a['dates'], true)) {
            $count++;
        }
    }
    $HV2['days'][] = ['iso' => $iso, 'label' => $i === 0 ? 'Azi' : ($i === 1 ? 'Mâine' : $hv2DowShort[(int) $d->format('w')]), 'day' => (int) $d->format('j'), 'count' => $count];
}
$HV2['hasDates'] = array_sum(array_column($HV2['days'], 'count')) > 0;
$hv2Prices = array_filter(array_column($hv2Acts, 'price'));
$HV2['minPrice'] = $hv2Prices ? min($hv2Prices) : 0;
$HV2['activityTabs'] = ['all' => 'Toate'];
foreach ($hv2Acts as $a) {
    if ($a['cat'] !== '' && !isset($HV2['activityTabs'][$a['cat']])) {
        $HV2['activityTabs'][$a['cat']] = $a['catName'] ?: $a['cat'];
    }
}

// ------------------------------------------------------------------ recommended for
$HV2['who'] = [];
foreach (HV2_TRAVELERS as $k => [$label, $color, $intro, $href, $img, $pos, $fallbackCats]) {
    $cards = [];
    foreach ((array) ($hv2Data('who_' . $k)['items'] ?? []) as $a) {
        if (count($cards) < 3 && is_array($a) && ($n = hv2_activity($a))) {
            $cards[] = [
                'title' => $n['title'],
                'meta' => hv2_e($n['city']) . ($n['price'] ? ($n['city'] ? ', ' : '') . 'de la <b>' . $n['price'] . ' lei</b>' : ''),
                'image' => $n['image'],
                'href' => $n['href'],
            ];
        }
    }
    if (!$cards) {
        foreach ($fallbackCats as $s) {
            if (isset($HV2['categoryBySlug'][$s])) {
                $c = $HV2['categoryBySlug'][$s];
                $cards[] = ['title' => $c['name'], 'meta' => 'Categorie', 'image' => $c['thumb'], 'href' => $c['href']];
            }
        }
    }
    $HV2['who'][] = ['k' => $k, 'label' => $label, 'color' => $color, 'intro' => $intro, 'href' => $href, 'img' => $img, 'pos' => $pos, 'cards' => $cards];
}

// ------------------------------------------------------------------ search suggestions (client side)
$HV2['suggest'] = [
    'cities' => array_map(function ($c) {
        return [$c['name'], $c['region'], $c['href']];
    }, array_slice($HV2['citiesList'], 0, 40)),
    'attractions' => array_map(function ($a) {
        return [$a['name'], $a['city'], $a['href']];
    }, $HV2['topAttractions']),
    'categories' => array_map(function ($c) {
        return [$c['name'], 'Categorie', $c['href']];
    }, $HV2['categories']),
];
