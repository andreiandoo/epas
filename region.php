<?php
/**
 * Region page: /{regiune}, e.g. /muntenia (v2 design).
 *
 * slug.php includes this file when a single-segment slug names a region; /regiune/{slug} and /region/{slug}
 * redirect here. Called directly (region.php?slug=), it looks the region up itself.
 *
 * Data: the region and its visible cities (/locations/regions/{slug}); every listed activity whose city belongs to the
 * region (the activity list has no region filter, so the site's activities are read and matched by city); the shell's
 * city data for counties, photos and the usual city order (featured cities first, like the Explore menu).
 *
 * Top to bottom: compact hero (search over the region's cities, facts, every region), activities in the region or
 * the honest empty state, main cities, every city by county, ideas for the main city, FAQ, final CTA.
 */

$pageCacheTTL = 600;
require_once __DIR__ . '/includes/page-cache.php';
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/api.php';
require_once __DIR__ . '/includes/nav-helpers.php';
require_once __DIR__ . '/includes/v2/helpers.php';

$regionSlug = isset($regionSlug) ? $regionSlug : (is_string($_GET['slug'] ?? null) ? $_GET['slug'] : '');
if (!preg_match('/^[a-z][a-z0-9-]{1,40}$/', $regionSlug)) {
    http_response_code(404);
    require __DIR__ . '/404.php';
    exit;
}
$regionResp = isset($regionResp) ? $regionResp : api_cached('v2_region_' . $regionSlug, function () use ($regionSlug) {
    return api_get('/locations/regions/' . rawurlencode($regionSlug));
}, 3600);
if (!is_array($regionResp) || empty($regionResp['success']) || !is_array($regionResp['data']['region'] ?? null)) {
    http_response_code(404);
    require __DIR__ . '/404.php';
    exit;
}

require_once __DIR__ . '/includes/v2/nav.php';

$rgFold = fn (string $s): string => trim(preg_replace('/[^a-z0-9]+/', '-', mb_strtolower(strtr($s, [
    'ă' => 'a', 'â' => 'a', 'î' => 'i', 'ș' => 's', 'ş' => 's', 'ț' => 't', 'ţ' => 't',
    'Ă' => 'a', 'Â' => 'a', 'Î' => 'i', 'Ș' => 's', 'Ş' => 's', 'Ț' => 't', 'Ţ' => 't',
]))), '-');

$region = $regionResp['data']['region'];
$regionName = navFlatName($region['name'] ?? '') ?: ucfirst($regionSlug);
$regionDescription = navFlatName($region['description'] ?? '');
$regionCitiesRaw = array_values(array_filter((array) ($regionResp['data']['cities'] ?? []), fn ($c) => is_array($c) && !empty($c['slug'])));
$inRegion = array_flip(array_column($regionCitiesRaw, 'slug'));

// ------------------------------------------------------------------ activities in the region
$rgFirst = api_cached_many(['p1' => ['key' => 'v2_all_activities_p1', 'endpoint' => '/activities', 'params' => ['per_page' => 50, 'page' => 1], 'ttl' => 300]]);
$rgPages = [$rgFirst['p1'] ?? []];
$rgLast = min(6, (int) ($rgFirst['p1']['data']['pagination']['last_page'] ?? 1));
if ($rgLast > 1) {
    $rgJobs = [];
    for ($p = 2; $p <= $rgLast; $p++) {
        $rgJobs['p' . $p] = ['key' => 'v2_all_activities_p' . $p, 'endpoint' => '/activities', 'params' => ['per_page' => 50, 'page' => $p], 'ttl' => 300];
    }
    $rgPages = array_merge($rgPages, array_values(api_cached_many($rgJobs)));
}
$activities = [];
$actsByCity = [];
$actCats = [];
foreach ($rgPages as $rgPage) {
    foreach ((array) ($rgPage['data']['items'] ?? []) as $a) {
        $citySlug = is_array($a) && is_array($a['city'] ?? null) ? (string) ($a['city']['slug'] ?? '') : '';
        if ($citySlug === '' || !isset($inRegion[$citySlug]) || !($n = v2_activity($a))) {
            continue;
        }
        $n['cents'] = isset($a['cheapest_price_cents']) ? (int) $a['cheapest_price_cents'] : null;
        $n['featured'] = !empty($a['flags']['is_featured']);
        $activities[] = $n;
        $actsByCity[$citySlug] = ($actsByCity[$citySlug] ?? 0) + 1;
        if ($n['cat'] !== '' && $n['catName'] !== '') {
            $actCats[$n['cat']] = $n['catName'];
        }
    }
}
usort($activities, fn ($a, $b) => [(int) $b['featured'], $a['title']] <=> [(int) $a['featured'], $b['title']]);

// ------------------------------------------------------------------ cities
$navRegion = null;
foreach ($V2NAV['regions'] as $r) {
    if ($r['slug'] === $regionSlug) {
        $navRegion = $r;
    }
}
$featuredRank = array_flip(array_column($navRegion['featured'] ?? [], 'slug'));
$moreRank = array_flip(array_map(fn ($m) => ltrim($m['href'], '/'), $navRegion['more'] ?? []));
$cities = [];
foreach ($regionCitiesRaw as $c) {
    $slug = (string) $c['slug'];
    $featured = $V2NAV['cities'][$slug] ?? null;
    $known = $V2NAV['allCities'][$slug] ?? [];
    $name = navFlatName($c['name'] ?? '') ?: ($known['name'] ?? $slug);
    $apiImage = v2_media_url($c['image'] ?? null);
    $cities[] = [
        'slug' => $slug,
        'name' => $name,
        'href' => '/' . $slug,
        'county' => (string) ($known['county'] ?? ''),
        'acts' => $actsByCity[$slug] ?? 0,
        'photo' => $featured['photo'] ?? ($apiImage ? [$apiImage, 0, 0, ''] : null),
        'order' => [-($actsByCity[$slug] ?? 0), $featuredRank[$slug] ?? 999, $moreRank[$slug] ?? 999, $rgFold($name)],
    ];
}
usort($cities, fn ($a, $b) => $a['order'] <=> $b['order']);
$cityCount = count($cities);
// the cities the Explore menu recommends, plus any with activities or a photo (never the alphabet's small towns)
$topCities = array_slice(array_values(array_filter($cities, fn ($c) => $c['acts'] > 0 || $c['photo'] || isset($featuredRank[$c['slug']]))), 0, 8);
$mainCity = $cities[0] ?? null;

// Every city by county (diacritics folded for the order); cities without a county close the list.
$counties = [];
foreach ($cities as $city) {
    $counties[$city['county'] !== '' ? $city['county'] : ''][] = $city;
}
uksort($counties, fn ($a, $b) => [$a === '' ? 1 : 0, $rgFold($a)] <=> [$b === '' ? 1 : 0, $rgFold($b)]);
foreach ($counties as &$countyCities) {
    usort($countyCities, fn ($a, $b) => [-$a['acts'], $rgFold($a['name'])] <=> [-$b['acts'], $rgFold($b['name'])]);
}
unset($countyCities);
$countyCount = count(array_filter(array_keys($counties), fn ($k) => $k !== ''));

$actCount = count($activities);
$activeCities = array_values(array_filter($cities, fn ($c) => $c['acts'] > 0));
$namesList = function (array $list): string {
    $names = array_column($list, 'name');
    return count($names) > 1 ? implode(', ', array_slice($names, 0, -1)) . ' și ' . end($names) : (string) ($names[0] ?? '');
};
$leadCities = array_slice($cities, 0, 3);
$lead = $regionDescription !== ''
    ? $regionDescription
    : ($cityCount > 3
        ? 'Orașele din ' . $regionName . ' într-un singur loc: ' . implode(', ', array_column($leadCities, 'name')) . ' și alte ' . v2_num($cityCount - 3, 'oraș', 'orașe') . '. Alege orașul și vezi activitățile, atracțiile și ideile de weekend de acolo.'
        : 'Alege orașul din ' . $regionName . ' și vezi activitățile, atracțiile și ideile de weekend de acolo.');
$fromPrice = null;
foreach ($activities as $a) {
    if ($a['cents'] !== null && $a['cents'] > 0) {
        $fromPrice = $fromPrice === null ? $a['cents'] : min($fromPrice, $a['cents']);
    }
}
$priceHtml = function (?int $cents): string {
    if ($cents === null) {
        return '';
    }
    if ($cents === 0) {
        return '<span class="xp-price"><b>Gratuit</b></span>';
    }
    return '<span class="xp-price">de la<b>' . v2_e(v2_thousands((int) round($cents / 100))) . ' lei</b></span>';
};

$hubs = [];
if ($mainCity) {
    $mc = $mainCity['name'];
    $hubs = [
        ['Copii', "Activități cu copiii în {$mc}", 'Muzee interactive, parcuri, ateliere și experiențe pentru familie.', "/{$mainCity['slug']}/activitati-copii"],
        ['Weekend', "Weekend în {$mc}", 'Idei pentru sâmbătă și duminică: copii, grupuri, cupluri.', "/{$mainCity['slug']}/activitati-weekend"],
        ['Indoor', "Indoor în {$mc}", 'Activități la adăpost, pentru zile reci sau ploioase.', "/{$mainCity['slug']}/activitati-indoor"],
        ['Buget', "Sub 50 lei în {$mc}", 'Activități accesibile, fără să golești portofelul.', "/{$mainCity['slug']}/activitati-sub-50-lei"],
    ];
}

$faqs = [
    [
        'Ce orașe din ' . $regionName . ' au activități pe bilete.online?',
        $activeCities
            ? 'Acum: ' . implode(', ', array_map(fn ($c) => $c['name'] . ' (' . v2_num($c['acts'], 'activitate', 'activități') . ')', $activeCities)) . '. Lista se actualizează singură când o locație din regiune își publică activitățile.'
            : 'Încă nicio activitate nu este listată în ' . $regionName . '. Lista se actualizează singură când o locație din regiune își publică activitățile.',
    ],
    ['Cum găsesc ce e de făcut într-un oraș din ' . $regionName . '?', 'Scrie numele orașului în căutarea de sus sau alege-l din lista pe județe. Pagina orașului strânge activitățile, atracțiile și ideile pentru copii, weekend sau zile ploioase.'],
    ['Pot rezerva online?', 'Da. Alegi data și ora, plătești online și primești biletul cu cod QR pe email; la intrare arăți codul de pe telefon.'],
    ['Am o locație în ' . $regionName . '. Cum apar aici?', 'Îți creezi contul de locație, adaugi activitățile și programul, iar după publicare apar automat pe pagina orașului și pe această pagină.'],
];

// ------------------------------------------------------------------ SEO
$pageTitleRaw = 'Activități în ' . $regionName . ': ' . v2_num($cityCount, 'oraș', 'orașe') . ' — ' . SITE_NAME;
$pageDescription = 'Activități, experiențe și bilete în ' . $regionName . ($leadCities ? ': ' . ($cityCount > 3 ? implode(', ', array_column($leadCities, 'name')) . ' și alte ' . v2_num($cityCount - 3, 'oraș', 'orașe') : $namesList($leadCities)) : '') . '. Alege orașul și rezervă online.';
$canonicalUrl = SITE_URL . '/' . $regionSlug;
$ogImage = v2_media_url($region['image'] ?? null) ?: ($topCities && $topCities[0]['photo'] ? $topCities[0]['photo'][0] : v2_asset('img/dest-brasov.webp'));
$breadcrumbs = [['Acasă', '/'], ['Orașe', '/orase'], [$regionName, '/' . $regionSlug]];
$structuredData = [[
    '@context' => 'https://schema.org',
    '@type' => 'CollectionPage',
    'name' => 'Activități în ' . $regionName,
    'description' => $pageDescription,
    'url' => $canonicalUrl,
    'inLanguage' => 'ro-RO',
    'about' => ['@type' => 'Place', 'name' => $regionName, 'containedInPlace' => ['@type' => 'Country', 'name' => 'România']],
    'mainEntity' => [
        '@type' => 'ItemList',
        'numberOfItems' => $cityCount,
        'itemListElement' => array_map(fn ($pos, $city) => [
            '@type' => 'ListItem',
            'position' => $pos + 1,
            'name' => $city['name'],
            'url' => SITE_URL . $city['href'],
        ], array_keys($cities), $cities),
    ],
], [
    '@context' => 'https://schema.org',
    '@type' => 'FAQPage',
    'mainEntity' => array_map(fn ($f) => ['@type' => 'Question', 'name' => $f[0], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f[1]]], $faqs),
], [
    '@context' => 'https://schema.org',
    '@type' => 'BreadcrumbList',
    'itemListElement' => array_map(fn ($bc, $i) => ['@type' => 'ListItem', 'position' => $i + 1, 'name' => $bc[0], 'item' => SITE_URL . $bc[1]], $breadcrumbs, array_keys($breadcrumbs)),
]];

$rgArches = '<svg class="deco-arches" viewBox="0 0 400 400" aria-hidden="true" focusable="false"><path d="M40 400V200a160 160 0 0 1 320 0v200"/><path d="M90 400V200a110 110 0 0 1 220 0v200"/><path d="M140 400V200a60 60 0 0 1 120 0v200"/></svg>';
$v2Styles = ['cities.css', 'region.css'];
$v2Scripts = ['region.js'];
$v2HeaderOverlay = true;

include __DIR__ . '/includes/v2/head.php';
include __DIR__ . '/includes/v2/header.php';
?>
<main id="main" tabindex="-1">
  <!-- ===================== HERO ===================== -->
  <section class="ct-hero rg-hero" aria-labelledby="rg-h">
    <?= $rgArches ?>
    <svg class="ct-line draw-clip" viewBox="0 590 3240 310" aria-hidden="true" focusable="false"><use href="#drum-g"/></svg>
    <div class="ct-in rg-in">
      <div>
        <nav class="crumbs" aria-label="Breadcrumb">
          <?php foreach ($breadcrumbs as $i => [$bcName, $bcHref]): ?>
            <?php if ($i > 0): ?><span aria-hidden="true">/</span><?php endif; ?>
            <?php if ($i < count($breadcrumbs) - 1): ?><a href="<?= v2_e($bcHref) ?>"><?= v2_e($bcName) ?></a><?php else: ?><span aria-current="page"><?= v2_e($bcName) ?></span><?php endif; ?>
          <?php endforeach; ?>
        </nav>
        <p class="ct-kicker">Regiune · România</p>
        <h1 class="ct-h rg-h" id="rg-h">Activități în <?= v2_e($regionName) ?></h1>
        <p class="ct-lead"><?= v2_e($lead) ?></p>
        <ul class="rg-facts" aria-label="Pe scurt">
          <li><?= v2_e(v2_num($cityCount, 'oraș', 'orașe')) ?></li>
          <?php if ($countyCount > 0): ?><li><?= v2_e(v2_num($countyCount, 'județ', 'județe')) ?></li><?php endif; ?>
          <?php if ($actCount > 0): ?><li><?= v2_e(v2_num($actCount, 'activitate', 'activități')) ?></li><?php endif; ?>
          <?php if ($fromPrice !== null): ?><li>de la <?= v2_e(v2_thousands((int) round($fromPrice / 100))) ?> lei</li><?php endif; ?>
        </ul>
        <?php if ($cityCount > 0): ?>
        <form class="ct-search rg-search" id="rg-form" action="#toate-orasele" role="search">
          <label class="sr" for="rg-q">Caută un oraș din <?= v2_e($regionName) ?></label>
          <input id="rg-q" type="search" autocomplete="off" enterkeyhint="search" maxlength="60" placeholder="Caută un oraș din <?= v2_e($regionName) ?>..." aria-controls="rg-counties">
          <button type="submit" aria-label="Arată orașele găsite"><?= v2_ic('magnifying-glass') ?></button>
        </form>
        <p class="ct-status rg-status" id="rg-status" role="status"></p>
        <?php endif; ?>
      </div>

      <nav class="rg-switch" aria-labelledby="rg-switch-h">
        <p class="rg-switch-h" id="rg-switch-h">Toate regiunile</p>
        <ul>
          <?php foreach ($V2NAV['regions'] as $r): $isHere = $r['slug'] === $regionSlug; ?>
          <li><a href="/<?= v2_e($r['slug']) ?>"<?= $isHere ? ' aria-current="page"' : '' ?>><?= v2_e($r['name']) ?><small><?= v2_e(v2_num($r['citiesCount'], 'oraș', 'orașe')) ?></small></a></li>
          <?php endforeach; ?>
        </ul>
      </nav>
    </div>
  </section>
  <div id="hdr-sentinel" aria-hidden="true"></div>

  <!-- ===================== ACTIVITIES ===================== -->
  <section class="sec rg-acts" id="activitati" aria-labelledby="rg-acts-h">
    <div class="wrap">
      <?php if ($activities): ?>
      <div class="sec-head">
        <div>
          <p class="kicker"><?= v2_e(v2_num($actCount, 'activitate', 'activități')) ?><?= $activeCities ? ' · ' . v2_e(v2_num(count($activeCities), 'oraș', 'orașe')) : '' ?></p>
          <h2 id="rg-acts-h">Ce poți face în <?= v2_e($regionName) ?></h2>
        </div>
        <a class="sec-link" href="/cauta">Caută în toată țara<?= v2_ic('arrow-right') ?></a>
      </div>
      <?php if (count($actCats) > 1): ?>
      <ul class="rg-cats" aria-label="Categorii în <?= v2_e($regionName) ?>">
        <?php foreach ($actCats as $catSlug => $catName): ?><li><a href="/<?= v2_e($catSlug) ?>"><?= v2_e($catName) ?></a></li><?php endforeach; ?>
      </ul>
      <?php endif; ?>
      <ul class="xp-grid">
        <?php foreach (array_slice($activities, 0, 8) as $i => $a): ?>
        <li class="xp">
          <a href="<?= v2_e($a['href']) ?>">
            <span class="xp-media"><?= $a['image'] ? v2_photo([$a['image'], 0, 0, '']) : v2_fallback($a['title'], $i) ?></span>
            <span class="xp-body">
              <span class="xp-cat"><?= v2_e($a['catName']) ?></span>
              <span class="xp-title"><?= v2_e($a['title']) ?></span>
              <span class="xp-meta">
                <?php if ($a['city'] !== ''): ?><span><?= v2_ic('map-pin') ?><?= v2_e($a['city']) ?></span><?php endif; ?>
                <?php if ($a['dur'] !== ''): ?><span><?= v2_ic('clock') ?><?= v2_e($a['dur']) ?></span><?php endif; ?>
              </span>
              <span class="xp-foot"><span class="xp-go">Vezi activitatea<?= v2_ic('arrow-right') ?></span><?= $priceHtml($a['cents']) ?></span>
            </span>
          </a>
        </li>
        <?php endforeach; ?>
      </ul>
      <?php if ($actCount > 8 && $mainCity): ?>
      <p class="rg-more-acts">Încă <?= v2_e(v2_num($actCount - 8, 'activitate', 'activități')) ?> în <?= v2_e($regionName) ?>: le găsești pe paginile orașelor de mai jos.</p>
      <?php endif; ?>
      <?php else: ?>
      <div class="rg-empty">
        <span class="rg-empty-ic"><?= v2_ic('map-pin') ?></span>
        <div>
          <p class="kicker">Activități</p>
          <h2 id="rg-acts-h">Încă nu sunt activități listate în <?= v2_e($regionName) ?>.</h2>
          <p>Orașele de mai jos au deja pagini proprii și se umplu pe măsură ce locațiile din regiune își publică activitățile. Până atunci, vezi ce e disponibil în alte regiuni.</p>
        </div>
        <div class="rg-empty-cta">
          <a class="btn btn-primary" href="/cauta"><?= v2_ic('magnifying-glass') ?>Caută activități</a>
          <a class="btn btn-ghost" href="/parteneri">Ai o locație aici?</a>
        </div>
      </div>
      <?php endif; ?>
    </div>
  </section>

  <!-- ===================== MAIN CITIES ===================== -->
  <?php if ($topCities): ?>
  <section class="sec rg-top" aria-labelledby="rg-top-h">
    <div class="wrap">
      <div class="sec-head">
        <div><p class="kicker">De unde să începi</p><h2 id="rg-top-h">Orașe recomandate în <?= v2_e($regionName) ?></h2></div>
        <a class="sec-link" href="#toate-orasele">Toate cele <?= v2_e(v2_num($cityCount, 'oraș', 'orașe')) ?><?= v2_ic('caret-down') ?></a>
      </div>
      <ul class="ct-grid rg-top-grid is-n<?= min(4, count($topCities)) ?>">
        <?php foreach ($topCities as $ci => $city): ?>
        <li class="ct-card">
          <a class="ct-top" href="<?= v2_e($city['href']) ?>">
            <span class="ct-media"><?= $city['photo'] ? v2_photo([$city['photo'][0], 0, 0, $city['photo'][3] ?? '']) : v2_fallback($city['name'], $ci) ?></span>
            <span class="ct-over"><small><?= v2_e($city['county'] !== '' && $city['county'] !== $city['name'] ? 'jud. ' . $city['county'] : $regionName) ?></small><h3><?= v2_e($city['name']) ?></h3></span>
            <?php if ($city['acts'] > 0): ?><span class="ct-badge"><?= v2_e(v2_num($city['acts'], 'activitate', 'activități')) ?></span><?php endif; ?>
          </a>
          <div class="ct-body">
            <ul class="ct-links" aria-label="<?= v2_e($city['name']) ?>: pagini locale">
              <li><a class="is-kids" href="<?= v2_e($city['href']) ?>/activitati-copii">Copii</a></li>
              <li><a href="<?= v2_e($city['href']) ?>/activitati-weekend">Weekend</a></li>
              <li><a href="<?= v2_e($city['href']) ?>/activitati-indoor">Indoor</a></li>
              <li><a class="is-main" href="<?= v2_e($city['href']) ?>">Vezi orașul<?= v2_ic('arrow-right') ?></a></li>
            </ul>
          </div>
        </li>
        <?php endforeach; ?>
      </ul>
    </div>
  </section>
  <?php endif; ?>

  <!-- ===================== EVERY CITY, BY COUNTY ===================== -->
  <?php if ($cities): ?>
  <section class="sec ct-az rg-all" id="toate-orasele" aria-labelledby="rg-all-h" tabindex="-1">
    <?php readfile(__DIR__ . '/includes/v2/topo.svg'); ?>
    <div class="wrap">
      <div class="rg-all-head">
        <div class="ct-az-intro">
          <p class="kicker"><?= $countyCount > 0 ? 'Pe județe' : 'Index' ?></p>
          <h2 id="rg-all-h">Toate cele <?= v2_e(v2_num($cityCount, 'oraș', 'orașe')) ?> din <?= v2_e($regionName) ?></h2>
        </div>
        <p class="rg-all-count" id="rg-count" aria-live="polite"><?= $cityCount ?> din <?= $cityCount ?> orașe</p>
      </div>
      <ul class="rg-counties" id="rg-counties">
        <?php foreach ($counties as $countyName => $countyCities): ?>
        <li class="ct-letter rg-county">
          <h3><?= v2_e($countyName !== '' ? $countyName : 'Alte localități') ?><small><?= count($countyCities) ?></small></h3>
          <ul>
            <?php foreach ($countyCities as $city): ?>
            <li data-q="<?= v2_e(implode(' ', [$city['name'], $countyName])) ?>"><a href="<?= v2_e($city['href']) ?>"><?= v2_e($city['name']) ?><?php if ($city['acts'] > 0): ?><span class="rg-n"><?= v2_e(v2_num($city['acts'], 'activitate', 'activități')) ?></span><?php endif; ?></a></li>
            <?php endforeach; ?>
          </ul>
        </li>
        <?php endforeach; ?>
      </ul>
      <div class="rg-none" id="rg-none" hidden>
        <p>Niciun oraș din <?= v2_e($regionName) ?> nu se potrivește căutării.</p>
        <div class="rg-none-cta">
          <button class="btn btn-outline-light" type="button" id="rg-reset">Arată toate orașele</button>
          <a class="btn btn-light" id="rg-elsewhere" href="/orase">Caută în toate regiunile<?= v2_ic('arrow-right') ?></a>
        </div>
      </div>
    </div>
  </section>
  <?php endif; ?>

  <!-- ===================== IDEAS FOR THE MAIN CITY ===================== -->
  <?php if ($hubs): ?>
  <section class="sec ct-hubs" aria-labelledby="rg-hubs-h">
    <div class="wrap ct-hubs-grid">
      <div class="ct-hubs-intro">
        <p class="kicker">Idei în <?= v2_e($mainCity['name']) ?></p>
        <h2 id="rg-hubs-h">Știi deja cu cine ieși? Începe de aici.</h2>
        <p>Pagini gata filtrate pentru cel mai căutat oraș din <?= v2_e($regionName) ?>: cu copiii, în weekend, la adăpost sau cu buget mic.</p>
      </div>
      <ul class="ct-hub-list">
        <?php foreach ($hubs as [$hubKicker, $hubTitle, $hubText, $hubUrl]): ?>
        <li><a class="ct-hub" href="<?= v2_e($hubUrl) ?>"><small><?= v2_e($hubKicker) ?></small><b><?= v2_e($hubTitle) ?></b><span><?= v2_e($hubText) ?></span><?= v2_ic('arrow-right') ?></a></li>
        <?php endforeach; ?>
      </ul>
    </div>
  </section>
  <?php endif; ?>

  <!-- ===================== FAQ ===================== -->
  <section class="sec ct-faq" aria-labelledby="rg-faq-h">
    <div class="wrap ct-faq-grid">
      <div><p class="kicker">FAQ</p><h2 id="rg-faq-h">Despre <?= v2_e($regionName) ?> pe bilete.online</h2></div>
      <div>
        <?php foreach ($faqs as $fi => [$faqQ, $faqA]): ?>
        <details class="qa"<?= $fi === 0 ? ' open' : '' ?>><summary><?= v2_e($faqQ) ?><span class="pm"><?= v2_ic('plus') ?></span></summary><p><?= v2_e($faqA) ?></p></details>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <!-- ===================== FINAL CTA ===================== -->
  <section class="ct-final" aria-labelledby="rg-final-h">
    <div class="wrap">
      <div class="ct-final-in">
        <?= $rgArches ?>
        <div>
          <p class="kicker">Altă regiune?</p>
          <h2 id="rg-final-h">Toată țara, pe orașe și pe categorii.</h2>
          <p>Vezi toate orașele cu activități sau pornește de la tipul de experiență: escape rooms, muzee, parcuri, ateliere, natură.</p>
        </div>
        <div class="ct-final-cta">
          <a class="btn btn-light" href="/orase">Toate orașele<?= v2_ic('arrow-right') ?></a>
          <a class="btn btn-outline-light" href="/categorii">Vezi categoriile</a>
        </div>
      </div>
    </div>
  </section>
</main>
<?php include __DIR__ . '/includes/v2/footer.php'; ?>
