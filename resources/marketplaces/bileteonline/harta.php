<?php
/**
 * /harta and the sixteen /harta/{slug} landings — the interactive map of every attraction in
 * Romania, whole or narrowed to one type or one historical region (v2, includes/v2/map-page.php).
 *
 * Reads only assets/v2/data/atractii.summary.json (~90 KB); the map itself fetches the full pin
 * dataset in the browser. The landings take their counters, cities and picks from that file's
 * `landings` entry, and their copy from includes/v2/map-landings.php, so no page repeats another.
 *
 * On /harta, ?tip=, ?zona= and ?oras= still narrow the map and the title; where a landing exists
 * for that filter, the canonical points at it instead of at the query URL.
 */

$pageCacheTTL = 1800;
require_once __DIR__ . '/includes/page-cache.php';
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/api.php';
require_once __DIR__ . '/includes/nav-helpers.php';
require_once __DIR__ . '/includes/v2/helpers.php';
require_once __DIR__ . '/includes/v2/am-labels.php';
require_once __DIR__ . '/includes/v2/map-landings.php';

$mapData = v2_map_data();
$summary = v2_map_summary();
if (!$mapData || !$summary) {
    // No dataset, no map page: better a 404 than a page whose whole point never loads.
    http_response_code(404);
    require __DIR__ . '/404.php';
    exit;
}

$slugRx = '/^[a-z][a-z0-9-]{1,50}$/';
$landingSlug = preg_match($slugRx, (string) ($_GET['landing'] ?? '')) ? (string) $_GET['landing'] : '';
$landing = $landingSlug !== '' ? v2_map_landing($landingSlug) : null;
$lData = $landingSlug !== '' ? ($summary['landings'][$landingSlug] ?? null) : null;
if ($landingSlug !== '' && (!$landing || !$lData)) {
    http_response_code(404);
    require __DIR__ . '/404.php';
    exit;
}

// Query filters only apply to the country-wide page; a landing carries its own.
$type = '';
$zone = '';
$city = '';
if (!$landing) {
    $type = (string) ($_GET['tip'] ?? '');
    $type = isset(AM_ATTRACTION_TYPES[$type]) ? $type : '';
    $zone = preg_match($slugRx, (string) ($_GET['zona'] ?? '')) ? (string) $_GET['zona'] : '';
    $city = preg_match($slugRx, (string) ($_GET['oras'] ?? '')) ? (string) $_GET['oras'] : '';
} elseif ($landing['kind'] === 'type') {
    $type = $landing['key'];
}
// A region landing filters by region, not by the county that happens to share its name.
// (the grid loops below use $zRegion, so this one is never shadowed)
$region = ($landing && $landing['kind'] === 'region') ? $landing['key'] : '';

$typeName = $type !== '' ? (AM_ATTRACTION_TYPES[$type] ?? '') : '';
$total = (int) ($lData['total'] ?? $summary['total']);

// The label of whatever the URL narrowed to, for the title and the H1.
$zoneName = '';
if ($landing && $landing['kind'] === 'region') {
    $zoneName = $landing['key'];
} elseif ($zone !== '') {
    foreach (array_merge($summary['regions'] ?? [], array_map(fn ($c) => [$c[0], $c[2]], $summary['counties'] ?? [])) as [$zn, $zc]) {
        if (v2_zone_slug($zn) === $zone) {
            $zoneName = $zn;
            break;
        }
    }
}
$cityName = '';
foreach ($summary['cities'] ?? [] as [$cs, $cn]) {
    if ($cs === $city) {
        $cityName = $cn;
        break;
    }
}
if ($city !== '' && $cityName === '') {
    $c = navGetCityBySlug($city);
    $cityName = is_array($c) ? navFlatName($c['name'] ?? '') : '';
}

$where = $cityName !== '' ? 'în ' . $cityName : ($zoneName !== '' ? 'din ' . $zoneName : 'din România');
$genitive = $type !== '' ? (AM_ATTRACTION_TYPES_GEN[$type] ?? mb_strtolower($typeName)) : 'atracțiilor';
$heading = $landing ? $landing['h1'] : 'Harta ' . $genitive;
$headingEm = $landing ? $landing['em'] : $where;

// ------------------------------------------------------------------ grids under the map
$grids = [];
if ($landing && $landing['kind'] === 'type') {
    // One type, so the useful second axis is where in the country it is.
    $byRegion = [];
    foreach ($lData['zones'] ?? [] as [$county, $zRegion, $n]) {
        if ($zRegion) {
            $byRegion[$zRegion] = ($byRegion[$zRegion] ?? 0) + (int) $n;
        }
    }
    arsort($byRegion);
    $rows = [];
    foreach ($byRegion as $rName => $rCount) {
        $rows[] = ['🗺️', $rName, $rCount, '/harta/' . (v2_map_landing_for_region($rName) ?: v2_zone_slug($rName))];
    }
    if ($rows) {
        $grids[] = ['id' => 'zones', 'h' => 'În ce regiuni se găsesc', 'rows' => $rows];
    }
    $rows = [];
    foreach ($lData['zones'] ?? [] as [$county, $zRegion, $n]) {
        $rows[] = ['📍', $county, $n, '/harta?zona=' . rawurlencode(v2_zone_slug($county))];
    }
    if ($rows) {
        $grids[] = ['id' => 'counties', 'h' => 'Județele cu cele mai multe', 'rows' => $rows];
    }
} elseif ($landing) {
    // One region, so the useful second axis is what kind of places it holds.
    $rows = [];
    foreach ($lData['types'] ?? [] as [$tSlug, $tName, $tEmoji, $tCount]) {
        $ls = v2_map_landing_for_type($tSlug);
        $rows[] = [$tEmoji, $tName, $tCount, $ls !== '' ? '/harta/' . $ls : '/atractii?tip=' . rawurlencode($tSlug)];
    }
    if ($rows) {
        $grids[] = ['id' => 'types', 'h' => 'Ce fel de locuri sunt aici', 'rows' => $rows, 'unit' => 'în regiune'];
    }
    $rows = [];
    foreach ($lData['zones'] ?? [] as [$county, $zRegion, $n]) {
        $rows[] = ['📍', $county, $n, '/harta?zona=' . rawurlencode(v2_zone_slug($county))];
    }
    if ($rows) {
        $grids[] = ['id' => 'counties', 'h' => 'Pe județe', 'rows' => $rows];
    }
} else {
    $rows = [];
    foreach ($summary['types'] ?? [] as [$tSlug, $tName, $tEmoji, $tCount]) {
        $ls = v2_map_landing_for_type($tSlug);
        $rows[] = [$tEmoji, $tName, $tCount, $ls !== '' ? '/harta/' . $ls : '/atractii?tip=' . rawurlencode($tSlug)];
    }
    $grids[] = ['id' => 'types', 'h' => 'Ce fel de locuri cauți', 'rows' => $rows, 'more' => ['Toate atracțiile', '/atractii']];

    $rows = [];
    foreach ($summary['regions'] ?? [] as [$rName, $rCount]) {
        $rows[] = ['🗺️', $rName, $rCount, '/harta/' . (v2_map_landing_for_region($rName) ?: v2_zone_slug($rName))];
    }
    $grids[] = ['id' => 'zones', 'h' => 'Pe regiuni istorice', 'rows' => $rows, 'unit' => 'de atracții'];
}

// ------------------------------------------------------------------ editorial
$churches = (int) (array_column($summary['types'] ?? [], 3, 0)['biserica-manastire'] ?? 0);
$topCity = $lData['cities'][0] ?? ($summary['cities'][0] ?? null);
$topZone = $lData['zones'][0] ?? ($summary['counties'][0] ?? null);

if ($landing) {
    $stats = 'Sunt <strong>' . v2_e(v2_thousands($total)) . '</strong> de puncte pe hartă';
    if ($topZone) {
        $stats .= ', cele mai multe în județul ' . v2_e($topZone[0]) . ' (' . v2_e(v2_thousands((int) $topZone[2])) . ')';
    }
    if ($topCity) {
        $stats .= ', iar orașul cu cele mai multe e ' . v2_e($topCity[1]) . ', cu ' . v2_e(v2_thousands((int) $topCity[4])) . '.';
    } else {
        $stats .= '.';
    }
    $prose = [
        '<p>' . v2_e($landing['intro']) . '</p>',
        '<p>' . $stats . ' Harta arată fiecare punct acolo unde se află; apasă pe unul ca să deschizi pagina locului, cu descriere, adresă și ce se mai poate face în jur.</p>',
        '<p>Căutarea funcționează fără diacritice și caută și după oraș, iar butonul <strong>Lângă mine</strong> ordonează lista după distanța față de tine. Pentru lista clasică, cu paginare, rămâne <a href="/atractii' . ($type !== '' ? '?tip=' . v2_e($type) : '') . '">pagina de atracții</a>, iar harta întreagă a țării e pe <a href="/harta">/harta</a>.</p>',
    ];
} else {
    $prose = [
        '<p>Harta adună toate cele ' . v2_e(v2_thousands($total)) . ' de atracții pe care le urmărim în România: castele și palate, muzee, biserici și mănăstiri, monumente, clădiri istorice, parcuri și grădini, lacuri, puncte panoramice, teatre și piețe vechi. Fiecare punct duce la pagina locului, cu descriere, adresă și ce poate fi făcut în apropiere.</p>',
        '<p>Harta pornește pe selecția <strong>Populare</strong>, care lasă deoparte cele ' . v2_e(v2_thousands($churches)) . ' de biserici și mănăstiri — sunt peste jumătate din total și ar acoperi restul. Le poți aprinde oricând din filtrul de tipuri, împreună cu orice altă combinație.</p>',
        '<p>Căutarea funcționează fără diacritice și caută și după oraș, iar butonul <strong>Lângă mine</strong> centrează harta pe poziția ta și ordonează lista după distanță. Orice filtrare ajunge în adresa paginii, deci un link copiat de aici deschide exact ce vedeai. Pentru liste clasice, cu paginare și text, rămâne <a href="/atractii">pagina de atracții</a>.</p>',
    ];
}

$sharedFaq = [
    ['Pot cumpăra bilet direct de pe hartă?', 'Acolo unde locul are bilete sau experiențe de vânzare, punctul e marcat distinct, iar pagina atracției are butonul de rezervare. Restul atracțiilor sunt puncte de vizitat, fără bilet.'],
    ['Merge pe telefon?', 'Da. Pe telefon harta ocupă tot ecranul, iar lista rezultatelor urcă de jos și poate fi trasă în trei poziții, ca să vezi cât vrei din hartă și cât din listă.'],
];
$faq = $landing
    ? array_merge($landing['faq'], $sharedFaq)
    : array_merge([
        ['De unde vin punctele de pe hartă?', 'Din catalogul de atracții al bilete.online: ' . v2_thousands($total) . ' de locuri din toată țara, fiecare cu coordonate verificate și cu pagină proprie pe site.'],
        ['Pot vedea doar castelele, sau doar muzeele?', 'Da. Filtrul de tip e cu selecție multiplă: apasă pe un tip ca să vezi numai acel tip, apoi adaugă altele. Numărul de lângă fiecare tip arată câte locuri conține.'],
        ['De ce nu apar bisericile de la început?', 'Sunt peste jumătate din toate atracțiile și ar acoperi restul hărții. Selecția Populare le ascunde implicit; apasă pe „Biserică & mănăstire” ca să le aduci înapoi.'],
    ], $sharedFaq);

// ------------------------------------------------------------------ page
$breadcrumbs = [['Acasă', '/'], ['Atracții', '/atractii'], ['Hartă', '/harta']];
if ($landing) {
    $breadcrumbs[] = [trim($landing['h1'] . ' ' . $landing['em']), '/harta/' . $landingSlug];
}

$mapPage = [
    'h1'     => $heading,
    'h1em'   => $headingEm,
    'lead'   => $landing ? $landing['lead'] : 'Alege tipurile care te interesează, caută un loc anume sau vezi ce e lângă tine. Treci cu mouse-ul peste un punct ca să-l vezi, apasă ca să deschizi atracția.',
    'stats'  => array_values(array_filter([
        [v2_thousands($total), ' atracții'],
        $landing ? null : [(string) count($summary['types'] ?? []), ' tipuri'],
        $landing ? null : [v2_thousands((int) ($mapData['cities'] ?? 0)), ' localități'],
        $landing && !empty($lData['zones']) ? [(string) count($lData['zones']), ' județe în top'] : null,
    ])),
    'breadcrumbs' => $breadcrumbs,
    'summary' => $summary,
    'grids'   => $grids,
    'cities'  => $lData['cities'] ?? null,
    'picks'   => $lData['picks'] ?? null,
    'citiesHeading' => $landing ? 'Orașele cu cele mai multe' : 'Orașele cu cele mai multe atracții',
    'picksHeading'  => $landing ? 'Câteva dintre ele' : 'Locuri de deschis pe hartă',
    'config'  => [
        'dataUrl'  => $mapData['url'],
        'cartoKey' => defined('CARTO_API_KEY') ? CARTO_API_KEY : '',
        'urlState' => true,
        'preset'   => 'popular',
        'types'    => $type !== '' ? [$type] : [],
        'zone'     => $zone,
        'region'   => $region,
        'city'     => $city,
        // the landing's filter lives in the path, so the map must not echo it into the query
        'fixed'    => (bool) $landing,
        'title'    => 'Atracții ' . $where,
        'base'     => '/atractie/',
    ],
    'prose' => $prose,
    'faq'   => $faq,
];

$pageTitleRaw = $landing
    ? $landing['h1'] . ' ' . $landing['em'] . ' | bilete.online'
    : $heading . ' ' . $where . ' | bilete.online';
$pageDescription = $landing
    ? $landing['lead'] . ' ' . v2_thousands($total) . ' de puncte pe harta interactivă, filtrabile și căutabile.'
    : 'Hartă interactivă cu atracții ' . $where . ': castele, muzee, mănăstiri, monumente, parcuri și puncte panoramice. Filtrează după tip, caută un loc sau vezi ce e lângă tine.';

// A filter that has a landing of its own points there, so the two URLs do not compete.
$canonicalPath = '/harta';
if ($landing) {
    $canonicalPath = '/harta/' . $landingSlug;
} elseif ($type !== '' && ($ls = v2_map_landing_for_type($type)) !== '') {
    $canonicalPath = '/harta/' . $ls;
} elseif ($zone !== '' && $zoneName !== '' && ($ls = v2_map_landing_for_region($zoneName)) !== '') {
    $canonicalPath = '/harta/' . $ls;
} elseif ($type !== '') {
    $canonicalPath = '/harta?tip=' . rawurlencode($type);
} elseif ($zone !== '') {
    $canonicalPath = '/harta?zona=' . rawurlencode($zone);
}
$canonicalUrl = SITE_URL . $canonicalPath;

$ldPicks = $mapPage['picks'] ?? ($summary['picks'] ?? []);
$ogImage = '';
foreach ($ldPicks as $p) {
    if (!empty($p[6])) {
        $ogImage = $p[6];
        break;
    }
}
$ogImage = $ogImage ?: (SITE_URL . '/assets/images/og-default.jpg');

$structuredData = [[
    '@context' => 'https://schema.org',
    '@type' => 'CollectionPage',
    'name' => trim($heading . ' ' . $headingEm),
    'description' => $pageDescription,
    'url' => $canonicalUrl,
    'inLanguage' => 'ro-RO',
], [
    '@context' => 'https://schema.org',
    '@type' => 'ItemList',
    'name' => 'Atracții pe hartă',
    'numberOfItems' => $total,
    'itemListElement' => array_map(
        fn ($p, $i) => ['@type' => 'ListItem', 'position' => $i + 1, 'url' => SITE_URL . '/atractie/' . $p[0], 'name' => $p[1]],
        $ldPicks,
        array_keys($ldPicks)
    ),
], [
    '@context' => 'https://schema.org',
    '@type' => 'FAQPage',
    'mainEntity' => array_map(
        fn ($f) => ['@type' => 'Question', 'name' => $f[0], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f[1]]],
        $faq
    ),
], [
    '@context' => 'https://schema.org',
    '@type' => 'BreadcrumbList',
    'itemListElement' => array_map(
        fn ($bc, $i) => ['@type' => 'ListItem', 'position' => $i + 1, 'name' => $bc[0], 'item' => SITE_URL . $bc[1]],
        $breadcrumbs,
        array_keys($breadcrumbs)
    ),
]];

require __DIR__ . '/includes/v2/map-page.php';
