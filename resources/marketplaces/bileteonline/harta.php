<?php
/**
 * /harta — the interactive map of every attraction in Romania (v2 design, includes/v2/map-page.php).
 *
 * Reads only assets/v2/data/atractii.summary.json (a few KB); the map itself fetches the full pin
 * dataset in the browser. ?tip=, ?zona= and ?oras= are read by the map and also narrow the page
 * title, so a shared link describes what it shows.
 */

$pageCacheTTL = 1800;
require_once __DIR__ . '/includes/page-cache.php';
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/api.php';
require_once __DIR__ . '/includes/nav-helpers.php';
require_once __DIR__ . '/includes/v2/helpers.php';
require_once __DIR__ . '/includes/v2/am-labels.php';

$mapData = v2_map_data();
$summary = v2_map_summary();
if (!$mapData || !$summary) {
    // No dataset, no map page: better a 404 than a page whose whole point never loads.
    http_response_code(404);
    require __DIR__ . '/404.php';
    exit;
}

$total  = (int) $summary['total'];
$type   = (string) ($_GET['tip'] ?? '');
$type   = isset(AM_ATTRACTION_TYPES[$type]) ? $type : '';
$zone   = preg_match('/^[a-z][a-z0-9-]{1,50}$/', (string) ($_GET['zona'] ?? '')) ? (string) $_GET['zona'] : '';
$city   = preg_match('/^[a-z][a-z0-9-]{1,50}$/', (string) ($_GET['oras'] ?? '')) ? (string) $_GET['oras'] : '';

// The label of whatever the URL narrowed to, for the title and the H1.
$zoneName = '';
foreach (array_merge($summary['regions'] ?? [], array_map(fn ($c) => [$c[0], $c[2]], $summary['counties'] ?? [])) as [$zn, $zc]) {
    if (v2_zone_slug($zn) === $zone) {
        $zoneName = $zn;
        break;
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
$typeName = $type !== '' ? AM_ATTRACTION_TYPES[$type] : '';

$where   = $cityName !== '' ? 'în ' . $cityName : ($zoneName !== '' ? 'din ' . $zoneName : 'din România');
$genitive = $type !== '' ? (AM_ATTRACTION_TYPES_GEN[$type] ?? mb_strtolower($typeName)) : 'atracțiilor';
$heading = 'Harta ' . $genitive;

$mapPage = [
    'kicker' => 'Hartă interactivă',
    'h1'     => $heading,
    'h1em'   => $where,
    'lead'   => 'Castele, muzee, mănăstiri, monumente, parcuri și puncte panoramice — toate pe aceeași hartă. Alege tipurile care te interesează, caută un loc anume sau vezi ce e lângă tine.',
    'stats'  => array_values(array_filter([
        [v2_thousands($total), ' atracții'],
        [(string) count($summary['types'] ?? []), ' tipuri'],
        [v2_thousands((int) ($mapData['cities'] ?? 0)), ' localități'],
    ])),
    'breadcrumbs' => array_values(array_filter([
        ['Acasă', '/'],
        ['Atracții', '/atractii'],
        ['Hartă', '/harta'],
    ])),
    'summary' => $summary,
    'config'  => [
        'dataUrl'  => $mapData['url'],
        'cartoKey' => defined('CARTO_API_KEY') ? CARTO_API_KEY : '',
        'urlState' => true,
        'preset'   => 'popular',
        'types'    => $type !== '' ? [$type] : [],
        'zone'     => $zone,
        'city'     => $city,
        'title'    => 'Atracții ' . $where,
        'base'     => '/atractie/',
    ],
    'prose' => [
        '<p>Harta adună toate cele ' . v2_e(v2_thousands($total)) . ' de atracții pe care le urmărim în România: castele și palate, muzee, biserici și mănăstiri, monumente, clădiri istorice, parcuri și grădini, lacuri, puncte panoramice, teatre și piețe vechi. Fiecare punct duce la pagina locului, cu descriere, adresă și ce se poate face în apropiere.</p>',
        '<p>Harta pornește pe selecția <strong>Populare</strong>, care lasă deoparte cele ' . v2_e(v2_thousands((int) (array_column($summary['types'] ?? [], 3, 0)['biserica-manastire'] ?? 0))) . ' de biserici și mănăstiri — sunt peste jumătate din total și ar acoperi restul. Le poți aprinde oricând din filtrul de tipuri, împreună cu orice altă combinație.</p>',
        '<p>Căutarea funcționează fără diacritice și caută și după oraș, iar butonul <strong>Lângă mine</strong> centrează harta pe poziția ta și ordonează lista după distanță. Orice filtrare ajunge în adresa paginii, deci un link copiat de aici deschide exact ce vedeai. Pentru liste clasice, cu paginare și text, rămâne <a href="/atractii">pagina de atracții</a>.</p>',
    ],
    'faq' => [
        ['De unde vin punctele de pe hartă?', 'Din catalogul de atracții al bilete.online: ' . v2_thousands($total) . ' de locuri din toată țara, fiecare cu coordonate verificate și cu pagină proprie pe site.'],
        ['Pot vedea doar castelele, sau doar muzeele?', 'Da. Filtrul de tip e cu selecție multiplă: apasă pe un tip ca să vezi numai acel tip, apoi adaugă altele. Numărul de lângă fiecare tip arată câte locuri conține.'],
        ['De ce nu apar bisericile de la început?', 'Sunt peste jumătate din toate atracțiile și ar acoperi restul hărții. Selecția Populare le ascunde implicit; apasă pe „Biserică & mănăstire” ca să le aduci înapoi.'],
        ['Pot cumpăra bilet direct de pe hartă?', 'Acolo unde locul are bilete sau experiențe de vânzare, punctul e marcat distinct, iar pagina atracției are butonul de rezervare. Restul atracțiilor sunt puncte de vizitat, fără bilet.'],
        ['Merge pe telefon?', 'Da. Pe telefon harta ocupă tot ecranul, iar lista rezultatelor urcă de jos și poate fi trasă în trei poziții, ca să vezi cât vrei din hartă și cât din listă.'],
    ],
];

$pageTitleRaw    = $heading . ' ' . $where . ' | bilete.online';
$pageDescription = 'Hartă interactivă cu atracții ' . $where . ($typeName !== '' ? ' (' . mb_strtolower($typeName) . ')' : ''). ': castele, muzee, mănăstiri, monumente, parcuri și puncte panoramice. Filtrează după tip, caută un loc sau vezi ce e lângă tine.';
$canonicalUrl    = SITE_URL . '/harta' . ($type !== '' ? '?tip=' . rawurlencode($type) : ($zone !== '' ? '?zona=' . rawurlencode($zone) : ''));
$ogImage         = ($summary['picks'][0][6] ?? '') ?: (SITE_URL . '/assets/images/og-default.jpg');

$structuredData = [[
    '@context' => 'https://schema.org',
    '@type' => 'CollectionPage',
    'name' => $mapPage['h1'] . ' ' . $where,
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
        $summary['picks'] ?? [],
        array_keys($summary['picks'] ?? [])
    ),
], [
    '@context' => 'https://schema.org',
    '@type' => 'FAQPage',
    'mainEntity' => array_map(
        fn ($f) => ['@type' => 'Question', 'name' => $f[0], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f[1]]],
        $mapPage['faq']
    ),
], [
    '@context' => 'https://schema.org',
    '@type' => 'BreadcrumbList',
    'itemListElement' => array_map(
        fn ($bc, $i) => ['@type' => 'ListItem', 'position' => $i + 1, 'name' => $bc[0], 'item' => SITE_URL . $bc[1]],
        $mapPage['breadcrumbs'],
        array_keys($mapPage['breadcrumbs'])
    ),
]];

require __DIR__ . '/includes/v2/map-page.php';
