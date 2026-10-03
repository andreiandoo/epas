<?php
/**
 * /trasee/{slug} — one editorial route: a map in route mode, the stops in order, and the text.
 * The same address also serves the roads of includes/v2/map-roads.php, with their own template.
 *
 * Copy comes from includes/v2/map-routes.php, everything factual from the `routes` block of
 * assets/v2/data/atractii.summary.json, which the builder resolves against the catalogue.
 */

$pageCacheTTL = 1800;
require_once __DIR__ . '/includes/page-cache.php';
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/api.php';
require_once __DIR__ . '/includes/nav-helpers.php';
require_once __DIR__ . '/includes/v2/helpers.php';
require_once __DIR__ . '/includes/v2/map-routes.php';
require_once __DIR__ . '/includes/v2/map-roads.php';

$summary = v2_map_summary();
$slug = preg_match('/^[a-z][a-z0-9-]{1,80}$/', (string) ($_GET['slug'] ?? '')) ? (string) $_GET['slug'] : '';

// ------------------------------------------------------------------ a road (includes/v2/map-roads.php)
$roadDef  = $slug !== '' ? (MAP_ROADS[$slug] ?? null) : null;
$roadData = $roadDef ? (v2_map_roads()[$slug] ?? null) : null;
if ($roadDef && $roadData) {
    $rdKm   = (int) $roadData['km'];
    $rdMax  = (int) ($roadData['max'] ?? 0);
    $rdUp   = (int) ($roadData['up'] ?? 0);
    $rdTime = v2_hm((int) $roadData['min']);
    $rdBike = $roadDef['modes'] === ['bike'];
    $rdWho  = implode(' și ', array_map(fn ($m) => mb_strtolower(MAP_ROAD_MODES[$m][0]), $roadDef['modes']));

    // The other roads, nearest in kind first: same first mode, then the rest.
    $others = [];
    foreach (MAP_ROADS as $oSlug => $o) {
        $od = v2_map_roads()[$oSlug] ?? null;
        if ($oSlug === $slug || !$od) {
            continue;
        }
        $others[] = [$oSlug, $o['title'], $o['ref'], $o['from'], $o['to'], $o['lead'], $o['modes'], $od['km'], $od['max'] ?? 0, $od['up'] ?? 0, $od['z'] ?? [], 0];
    }
    usort($others, fn ($a, $b) => (int) ($b[6][0] === $roadDef['modes'][0]) <=> (int) ($a[6][0] === $roadDef['modes'][0]));
    $others = array_slice($others, 0, 3);

    $roadPage = [
        'slug' => $slug, 'title' => $roadDef['title'], 'ref' => $roadDef['ref'], 'from' => $roadDef['from'], 'to' => $roadDef['to'],
        'lead' => $roadDef['lead'], 'season' => $roadDef['season'] ?? '', 'modes' => $roadDef['modes'], 'points' => $roadDef['points'],
        'km' => $rdKm, 'min' => (int) $roadData['min'], 'max' => $rdMax, 'low' => (int) ($roadData['min_alt'] ?? 0), 'up' => $rdUp,
        'z' => $roadData['z'] ?? [], 'geometry' => $roadData['geometry'], 'a' => $roadData['a'], 'b' => $roadData['b'],
        'stops' => $roadData['stops'] ?? [], 'others' => $others,
        'breadcrumbs' => [['Acasă', '/'], ['Hartă', '/harta'], ['Trasee', '/trasee'], [$roadDef['title'], '/trasee/' . $slug]],
        'prose' => [
            '<p>' . v2_e($roadDef['intro']) . '</p>',
            '<p>Între ' . v2_e($roadDef['from']) . ' și ' . v2_e($roadDef['to']) . ' sunt <strong>' . v2_e(v2_thousands($rdKm)) . ' km</strong>'
                . ($rdMax > 0 ? ', iar drumul urcă până la <strong>' . v2_e(v2_thousands($rdMax)) . ' m</strong>' : '')
                . ($rdUp > 0 ? '. Mergând în sensul acesta se adună <strong>' . v2_e(v2_thousands($rdUp)) . ' m de urcare</strong>' : '')
                . '. ' . ($rdBike
                    ? 'Traseul e calculat pe rețeaua de biciclete, așa că ține pista acolo unde există.'
                    : 'Cu motorul pornit și fără opriri înseamnă cam <strong>' . v2_e($rdTime) . '</strong>; cu opririle pentru priveliște, socotește mai mult.')
                . '</p>',
            '<p>Butonul <em>Deschide ca plan</em> pune drumul în <a href="/plan?drum=' . v2_e($slug) . '">planificator</a> ca o zi, cu cele două capete ca plecare și sosire și cu atracțiile de pe margine ca opriri. De acolo adaugi o masă, scoți ce nu te interesează, vezi profilul zilei și cauți cazare.</p>',
        ],
        'faq' => [
            ['Când e deschis drumul?', !empty($roadDef['season'])
                ? $roadDef['season']
                : 'Nu are o perioadă de închidere anunțată în fiecare an. Iarna și după ploi mari, verifică starea drumurilor la CNAIR înainte de plecare.'],
            ['De unde vin kilometrii și altitudinile?', 'Linia e calculată pe drumurile din OpenStreetMap, între capetele drumului. Altitudinile sunt citite de pe un model de teren european cu pasul de 25 de metri, în o sută de puncte egal depărtate de-a lungul drumului, deci vârfurile ascuțite ies puțin rotunjite.'],
            ['Pot să-l parcurg în sens invers?', 'Da. Kilometrii rămân aceiași; urcarea totală se schimbă, pentru că ce era coborâre devine urcuș.'],
            ['E potrivit pentru ' . ($rdBike ? 'copii' : 'bicicletă') . '?', $rdBike
                ? 'Profilul îți spune cel mai bine: uită-te la diferența de nivel și la lungime. Unde e pistă separată de trafic, am scris asta în descriere.'
                : (in_array('bike', $roadDef['modes'], true)
                    ? 'Se face și pe bicicletă, dar e un drum cu trafic, fără pistă. Uită-te la urcarea totală înainte să pleci și pornește devreme.'
                    : 'Nu l-am trecut la biciclete: e un drum național cu trafic, fără pistă. Dacă îl faci totuși pe bicicletă, profilul de mai sus îți arată la ce urcare să te aștepți.')],
        ],
    ];

    $pageTitleRaw    = $roadDef['title'] . ($roadDef['ref'] !== '' ? ' (' . $roadDef['ref'] . ')' : '') . ' — hartă, profil și ' . v2_thousands($rdKm) . ' km | bilete.online';
    $pageDescription = $roadDef['title'] . ', ' . $roadDef['from'] . ' – ' . $roadDef['to'] . ': ' . v2_thousands($rdKm) . ' km'
        . ($rdMax > 0 ? ', până la ' . v2_thousands($rdMax) . ' m altitudine' : '') . '. Harta drumului, profilul de altitudine și ce vezi pe margine, pentru ' . $rdWho . '.';
    $canonicalUrl    = SITE_URL . '/trasee/' . $slug;
    $ogImage         = SITE_URL . '/assets/images/og-default.jpg';
    foreach ($roadData['stops'] ?? [] as $st) {
        if (($st[9] ?? '') !== '') {
            $ogImage = $st[9];
            break;
        }
    }
    $structuredData = [[
        '@context' => 'https://schema.org',
        '@type' => 'TouristTrip',
        'name' => $roadDef['title'],
        'description' => $roadDef['lead'],
        'url' => $canonicalUrl,
        'itinerary' => [
            '@type' => 'ItemList',
            'itemListElement' => array_map(
                fn ($st, $i) => ['@type' => 'ListItem', 'position' => $i + 1, 'url' => SITE_URL . '/atractie/' . $st[0], 'name' => $st[1]],
                $roadData['stops'] ?? [],
                array_keys($roadData['stops'] ?? [])
            ),
        ],
    ], [
        '@context' => 'https://schema.org',
        '@type' => 'FAQPage',
        'mainEntity' => array_map(
            fn ($f) => ['@type' => 'Question', 'name' => $f[0], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f[1]]],
            $roadPage['faq']
        ),
    ], [
        '@context' => 'https://schema.org',
        '@type' => 'BreadcrumbList',
        'itemListElement' => array_map(
            fn ($bc, $i) => ['@type' => 'ListItem', 'position' => $i + 1, 'name' => $bc[0], 'item' => SITE_URL . $bc[1]],
            $roadPage['breadcrumbs'],
            array_keys($roadPage['breadcrumbs'])
        ),
    ]];

    require __DIR__ . '/includes/v2/road-page.php';

    return;
}

// ------------------------------------------------------------------ an editorial route (includes/v2/map-routes.php)
$route = $slug !== '' ? v2_map_route($slug) : null;
$rData = $slug !== '' ? ($summary['routes'][$slug] ?? null) : null;
if (!$route || !$rData) {
    http_response_code(404);
    require __DIR__ . '/404.php';
    exit;
}

$stops = $rData['stops'];
// The data file carries each stop's type as a name and an emoji; the map shows the type's SVG icon instead.
$typeSlugByName = [];
foreach ($summary['types'] ?? [] as $t) {
    $typeSlugByName[(string) ($t[1] ?? '')] = (string) ($t[0] ?? '');
}
foreach ($stops as $si => $s) {
    $stops[$si][6] = am_place_icon($typeSlugByName[(string) ($s[5] ?? '')] ?? null);
}
$road = $rData['road'] ?? null;          // real driving distance and time, or null when routing failed
$roadKm = $road ? (int) $road['km'] : (int) $rData['km'];
$roadMin = $road ? (int) $road['min'] : 0;
$driveTxt = v2_hm($roadMin);
$counties = $rData['counties'];
$cover = '';
foreach ($stops as $s) {
    if ($s[9] !== '') {
        $cover = $s[9];
        break;
    }
}

// Cards for the other routes, ordered as they are declared.
$others = [];
foreach (MAP_ROUTES as $oSlug => $o) {
    if ($oSlug === $slug || !isset($summary['routes'][$oSlug])) {
        continue;
    }
    $od = $summary['routes'][$oSlug];
    $oImg = '';
    foreach ($od['stops'] as $os) {
        if ($os[9] !== '') {
            $oImg = $os[9];
            break;
        }
    }
    $others[] = [$oSlug, $o['title'], $o['lead'], $o['emoji'], $o['pace'], $od['count'], $od['km'], $oImg];
}
shuffle($others);
$others = array_slice($others, 0, 3);

$where = count($counties) === 1
    ? 'în județul ' . $counties[0]
    : (count($counties) > 1 ? 'prin ' . v2_num(count($counties), 'județ', 'județe') : '');

$routePage = [
    'slug'  => $slug,
    'title' => $route['title'],
    'lead'  => $route['lead'],
    'emoji' => $route['emoji'],
    'pace'  => $route['pace'],
    'km'    => $roadKm,
    'road'  => (bool) $road,
    'drive' => $driveTxt,
    'stops' => $stops,
    'others' => $others,
    'breadcrumbs' => [['Acasă', '/'], ['Hartă', '/harta'], ['Trasee', '/trasee'], [$route['title'], '/trasee/' . $slug]],
    'config' => [
        'cartoKey'   => defined('CARTO_API_KEY') ? CARTO_API_KEY : '',
        'urlState'   => false,
        'fixed'      => true,
        'title'      => $route['title'],
        'base'       => '/atractie/',
        'routeKm'    => $roadKm,
        'routeMin'   => $roadMin,
        'routeRoad'  => (bool) $road,
        'routeGeometry' => $road['geometry'] ?? '',
        // Route mode builds its dataset from these, so no pin file is downloaded here.
        'routeStops' => $stops,
    ],
    'prose' => [
        '<p>' . v2_e($route['intro']) . '</p>',
        '<p>Traseul are <strong>' . count($stops) . ' opriri</strong>' . ($where !== '' ? ', ' . v2_e($where) : '')
            . ($road
                ? ', iar de la prima la ultima sunt <strong>' . v2_e(v2_thousands($roadKm)) . ' km pe șosea</strong>'
                    . ($driveTxt !== '' ? ', adică <strong>' . v2_e($driveTxt) . '</strong> de condus fără opriri' : '') . '. '
                : ', iar între prima și ultima sunt <strong>' . v2_e(v2_thousands($roadKm)) . ' km în linie dreaptă</strong>. ')
            . 'Numerele de pe hartă și din listă sunt aceeași ordine; apasă pe o oprire ca să o vezi pe hartă sau deschide pagina ei pentru descriere și adresă.</p>',
        '<p>Traseul e o sugestie, nu un program: îl poți parcurge în sens invers, îl poți tăia în două zile sau poți lua doar opririle care îți ies în drum. Toate punctele sunt și pe <a href="/harta">harta atracțiilor</a>, împreună cu ce se mai află în jurul lor.</p>',
    ],
    'faq' => [
        ['Cât durează traseul?', ($driveTxt !== '' ? 'Doar condusul înseamnă ' . $driveTxt . '. ' : '') . 'Cu opririle, depinde cât stai la fiecare. Am notat ' . $route['pace'] . ' ca reper, pentru un ritm în care apuci să intri, nu doar să treci pe lângă.'],
        $road
            ? ['Distanța e pe șosea?', 'Da. Cei ' . v2_thousands($roadKm) . ' km și ' . ($driveTxt !== '' ? 'cele ' . $driveTxt : 'timpul de mers') . ' sunt calculați pe drumurile reale, nu în linie dreaptă, cu datele OpenStreetMap. Nu includ opririle, traficul și ocolirile.']
            : ['Distanța e pe șosea?', 'Nu. Pentru acest traseu nu am putut calcula drumul, așa că cei ' . v2_thousands($roadKm) . ' km sunt măsurați în linie dreaptă. Pentru kilometrajul real, deschide traseul în Google Maps cu butonul de mai sus.'],
        ['Se plătește la fiecare oprire?', 'Nu. O parte dintre opriri sunt obiective cu acces liber; unde se vinde bilet, apare pe pagina obiectivului. Programul îl stabilește fiecare administrator.'],
        ['Pot schimba ordinea?', 'Da. Ordinea de aici e cea care scurtează drumul, dar traseul merge la fel de bine invers sau rupt în bucăți.'],
    ],
];

$pageTitleRaw    = $route['title'] . ' — traseu cu ' . count($stops) . ' opriri | bilete.online';
$pageDescription = $route['lead'] . ' Traseu cu ' . count($stops) . ' opriri și ' . v2_thousands($roadKm) . ' km pe șosea'
    . ($driveTxt !== '' ? ' (' . $driveTxt . ' de condus)' : '') . ', cu hartă și navigare.';
$canonicalUrl    = SITE_URL . '/trasee/' . $slug;
$ogImage         = $cover ?: (SITE_URL . '/assets/images/og-default.jpg');

$structuredData = [[
    '@context' => 'https://schema.org',
    '@type' => 'ItemList',
    'name' => $route['title'],
    'description' => $route['lead'],
    'numberOfItems' => count($stops),
    'itemListOrder' => 'https://schema.org/ItemListOrderAscending',
    'itemListElement' => array_map(
        fn ($s, $i) => [
            '@type' => 'ListItem',
            'position' => $i + 1,
            'url' => SITE_URL . '/atractie/' . $s[0],
            'name' => $s[1],
        ],
        $stops,
        array_keys($stops)
    ),
], [
    '@context' => 'https://schema.org',
    '@type' => 'FAQPage',
    'mainEntity' => array_map(
        fn ($f) => ['@type' => 'Question', 'name' => $f[0], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f[1]]],
        $routePage['faq']
    ),
], [
    '@context' => 'https://schema.org',
    '@type' => 'BreadcrumbList',
    'itemListElement' => array_map(
        fn ($bc, $i) => ['@type' => 'ListItem', 'position' => $i + 1, 'name' => $bc[0], 'item' => SITE_URL . $bc[1]],
        $routePage['breadcrumbs'],
        array_keys($routePage['breadcrumbs'])
    ),
]];

require __DIR__ . '/includes/v2/route-page.php';
