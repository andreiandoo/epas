<?php
/**
 * Region page (v2 design), from the Romanian site this one was copied from, where /{region} listed the cities of
 * a historical region by county.
 *
 * On Viaqui slug.php no longer includes this file (a slug is a category, a country or a city; regions are reached
 * through the country page), so it only answers when called directly (region.php?slug=) and core knows the region.
 * The "regions" of the shell ($V2NAV['regions']) are countries here.
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
require_once __DIR__ . '/includes/v2/promoted.php';

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
usort($activities, fn ($a, $b) => [(int) $b['promoted'], (int) $b['featured'], $a['title']] <=> [(int) $a['promoted'], (int) $a['featured'], $b['title']]);

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
    return count($names) > 1 ? v2_t('{list} and {last}', ['list' => implode(', ', array_slice($names, 0, -1)), 'last' => end($names)]) : (string) ($names[0] ?? '');
};
$leadCities = array_slice($cities, 0, 3);
$lead = $regionDescription !== ''
    ? $regionDescription
    : ($cityCount > 3
        ? v2_t('The cities of {region} in one place: {cities} and {n} more. Choose a city to see its activities, attractions and weekend ideas.', ['region' => $regionName, 'cities' => implode(', ', array_column($leadCities, 'name')), 'n' => $cityCount - 3])
        : v2_t('Choose a city in {region} to see its activities, attractions and weekend ideas.', ['region' => $regionName]));
// The lowest price, compared in euro (operators sell in their own currencies) and shown as its own label.
$fromPrice = null;
$fromPriceLabel = '';
foreach ($activities as $a) {
    if ($a['cents'] !== null && $a['cents'] > 0 && ($fromPrice === null || $a['priceEur'] < $fromPrice)) {
        $fromPrice = $a['priceEur'];
        $fromPriceLabel = $a['priceLabel'];
    }
}
$priceHtml = function (?int $cents, string $label): string {
    if ($cents === null) {
        return '';
    }
    if ($cents === 0) {
        return '<span class="xp-price"><b>' . v2_te('Free') . '</b></span>';
    }
    return '<span class="xp-price">' . v2_t('from<b>{price}</b>', ['price' => v2_e($label)]) . '</span>';
};

$hubs = [];
if ($mainCity) {
    $mc = $mainCity['name'];
    $hubs = [
        [v2_t('Kids'), v2_t('Things to do with kids in {city}', ['city' => $mc]), v2_t('Hands-on museums, parks, workshops and family experiences.'), "/{$mainCity['slug']}/with-kids"],
        [v2_t('Weekend'), v2_t('A weekend in {city}', ['city' => $mc]), v2_t('Ideas for Saturday and Sunday: kids, groups, couples.'), "/{$mainCity['slug']}/weekend-ideas"],
        [v2_t('Indoor'), v2_t('Indoors in {city}', ['city' => $mc]), v2_t('Activities under a roof, for cold or rainy days.'), "/{$mainCity['slug']}/activitati-indoor"],
        [v2_t('Budget'), v2_t('On a budget in {city}', ['city' => $mc]), v2_t('Affordable activities that go easy on your wallet.'), "/{$mainCity['slug']}/activitati-sub-50-lei"],
    ];
}

$faqs = [
    [
        v2_t('Which cities in {region} have activities on Viaqui?', ['region' => $regionName]),
        $activeCities
            ? v2_t('Right now: {cities}. The list updates itself when a venue in the region publishes its activities.', ['cities' => implode(', ', array_map(fn ($c) => $c['name'] . ' (' . v2_num($c['acts'], 'activity', 'activities') . ')', $activeCities))])
            : v2_t('No activity is listed in {region} yet. The list updates itself when a venue in the region publishes its activities.', ['region' => $regionName]),
    ],
    [v2_t('How do I find things to do in a city in {region}?', ['region' => $regionName]), v2_t('Type the name of the city in the search at the top, or pick it from the list by county. The city page gathers the activities, the attractions and ideas for kids, weekends or rainy days.')],
    [v2_t('Can I book online?'), v2_t('Yes. You pick the date and time, pay online and get your ticket with a QR code by email. At the entrance you show the code on your phone.')],
    [v2_t('I run a venue in {region}. How do I appear here?', ['region' => $regionName]), v2_t('Create your venue account, add your activities and opening hours, and once published they appear automatically on the city page and on this page.')],
];

// ------------------------------------------------------------------ SEO
$pageTitleRaw = v2_t('Things to do in {region}: {cities}', ['region' => $regionName, 'cities' => v2_num($cityCount, 'city', 'cities')]) . ' | ' . SITE_NAME;
if (!$leadCities) {
    $pageDescription = v2_t('Activities, experiences and tickets in {region}. Choose a city and book online.', ['region' => $regionName]);
} elseif ($cityCount > 3) {
    $pageDescription = v2_t('Activities, experiences and tickets in {region}: {cities} and {n} more. Choose a city and book online.', ['region' => $regionName, 'cities' => implode(', ', array_column($leadCities, 'name')), 'n' => $cityCount - 3]);
} else {
    $pageDescription = v2_t('Activities, experiences and tickets in {region}: {cities}. Choose a city and book online.', ['region' => $regionName, 'cities' => $namesList($leadCities)]);
}
$canonicalUrl = SITE_URL . '/' . $regionSlug;
$ogImage = v2_media_url($region['image'] ?? null) ?: ($topCities && $topCities[0]['photo'] ? $topCities[0]['photo'][0] : v2_asset('img/dest-brasov.webp'));
$breadcrumbs = [[v2_t('Home'), '/'], [v2_t('Cities'), '/cities'], [$regionName, '/' . $regionSlug]];
$structuredData = [[
    '@context' => 'https://schema.org',
    '@type' => 'CollectionPage',
    'name' => v2_t('Things to do in {region}', ['region' => $regionName]),
    'description' => $pageDescription,
    'url' => $canonicalUrl,
    'inLanguage' => v2_locale(),
    'about' => ['@type' => 'Place', 'name' => $regionName],
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
        <nav class="crumbs" aria-label="<?= v2_te('Breadcrumb') ?>">
          <?php foreach ($breadcrumbs as $i => [$bcName, $bcHref]): ?>
            <?php if ($i > 0): ?><span aria-hidden="true">/</span><?php endif; ?>
            <?php if ($i < count($breadcrumbs) - 1): ?><a href="<?= v2_e($bcHref) ?>"><?= v2_e($bcName) ?></a><?php else: ?><span aria-current="page"><?= v2_e($bcName) ?></span><?php endif; ?>
          <?php endforeach; ?>
        </nav>
        <p class="ct-kicker"><?= v2_te('Region') ?></p>
        <h1 class="ct-h rg-h" id="rg-h"><?= v2_te('Things to do in {region}', ['region' => $regionName]) ?></h1>
        <p class="ct-lead"><?= v2_e($lead) ?></p>
        <ul class="rg-facts" aria-label="<?= v2_te('At a glance') ?>">
          <li><?= v2_e(v2_num($cityCount, 'city', 'cities')) ?></li>
          <?php if ($countyCount > 0): ?><li><?= v2_e(v2_num($countyCount, 'county', 'counties')) ?></li><?php endif; ?>
          <?php if ($actCount > 0): ?><li><?= v2_e(v2_num($actCount, 'activity', 'activities')) ?></li><?php endif; ?>
          <?php if ($fromPrice !== null && $fromPriceLabel !== ''): ?><li><?= v2_te('from {price}', ['price' => $fromPriceLabel]) ?></li><?php endif; ?>
        </ul>
        <?php if ($cityCount > 0): ?>
        <form class="ct-search rg-search" id="rg-form" action="#toate-orasele" role="search">
          <label class="sr" for="rg-q"><?= v2_te('Search for a city in {region}', ['region' => $regionName]) ?></label>
          <input id="rg-q" type="search" autocomplete="off" enterkeyhint="search" maxlength="60" placeholder="<?= v2_te('Search for a city in {region}…', ['region' => $regionName]) ?>" aria-controls="rg-counties">
          <button type="submit" aria-label="<?= v2_te('Show the cities found') ?>"><?= v2_ic('magnifying-glass') ?></button>
        </form>
        <p class="ct-status rg-status" id="rg-status" role="status"></p>
        <?php endif; ?>
      </div>

      <nav class="rg-switch" aria-labelledby="rg-switch-h">
        <p class="rg-switch-h" id="rg-switch-h"><?= v2_te('Countries') ?></p>
        <ul>
          <?php foreach ($V2NAV['regions'] as $r): $isHere = $r['slug'] === $regionSlug; ?>
          <li><a href="/<?= v2_e($r['slug']) ?>"<?= $isHere ? ' aria-current="page"' : '' ?>><?= v2_e($r['name']) ?><small><?= v2_e(v2_num($r['citiesCount'], 'city', 'cities')) ?></small></a></li>
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
          <p class="kicker"><?= v2_e(v2_num($actCount, 'activity', 'activities')) ?><?= $activeCities ? ' · ' . v2_e(v2_num(count($activeCities), 'city', 'cities')) : '' ?></p>
          <h2 id="rg-acts-h"><?= v2_te('What you can do in {region}', ['region' => $regionName]) ?></h2>
        </div>
        <a class="sec-link" href="/search"><?= v2_te('Search everywhere') ?><?= v2_ic('arrow-right') ?></a>
      </div>
      <?php if (count($actCats) > 1): ?>
      <ul class="rg-cats" aria-label="<?= v2_te('Categories in {region}', ['region' => $regionName]) ?>">
        <?php foreach ($actCats as $catSlug => $catName): ?><li><a href="/<?= v2_e($catSlug) ?>"><?= v2_e($catName) ?></a></li><?php endforeach; ?>
      </ul>
      <?php endif; ?>
      <ul class="xp-grid">
        <?php foreach (array_slice($activities, 0, 8) as $i => $a): ?>
        <li class="xp">
          <a href="<?= v2_e($a['href']) ?>">
            <span class="xp-media"><?= $a['image'] ? v2_photo([$a['image'], 0, 0, '']) : v2_fallback($a['title'], $i) ?><?= !empty($a['promoted']) ? v2_promoted_tag() : '' ?></span>
            <span class="xp-body">
              <span class="xp-cat"><?= v2_e($a['catName']) ?></span>
              <span class="xp-title"><?= v2_e($a['title']) ?></span>
              <span class="xp-meta">
                <?php if ($a['city'] !== ''): ?><span><?= v2_ic('map-pin') ?><?= v2_e($a['city']) ?></span><?php endif; ?>
                <?php if ($a['dur'] !== ''): ?><span><?= v2_ic('clock') ?><?= v2_e($a['dur']) ?></span><?php endif; ?>
              </span>
              <span class="xp-foot"><span class="xp-go"><?= v2_te('View activity') ?><?= v2_ic('arrow-right') ?></span><?= $priceHtml($a['cents'], $a['priceLabel']) ?></span>
            </span>
          </a>
        </li>
        <?php endforeach; ?>
      </ul>
      <?php if ($actCount > 8 && $mainCity): ?>
      <p class="rg-more-acts"><?= v2_te('{more} in {region}: you will find them on the city pages below.', ['more' => v2_num($actCount - 8, 'more activity', 'more activities'), 'region' => $regionName]) ?></p>
      <?php endif; ?>
      <?php else: ?>
      <div class="rg-empty">
        <span class="rg-empty-ic"><?= v2_ic('map-pin') ?></span>
        <div>
          <p class="kicker"><?= v2_te('Activities') ?></p>
          <h2 id="rg-acts-h"><?= v2_te('No activities are listed in {region} yet.', ['region' => $regionName]) ?></h2>
          <p><?= v2_te('The cities below already have their own pages, and they fill up as venues in the region publish their activities. Until then, see what is available elsewhere.') ?></p>
        </div>
        <div class="rg-empty-cta">
          <a class="btn btn-primary" href="/search"><?= v2_ic('magnifying-glass') ?><?= v2_te('Search activities') ?></a>
          <a class="btn btn-ghost" href="/partners"><?= v2_te('Do you run a venue here?') ?></a>
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
        <div><p class="kicker"><?= v2_te('Where to start') ?></p><h2 id="rg-top-h"><?= v2_te('Recommended cities in {region}', ['region' => $regionName]) ?></h2></div>
        <a class="sec-link" href="#toate-orasele"><?= v2_te('All {cities}', ['cities' => v2_num($cityCount, 'city', 'cities')]) ?><?= v2_ic('caret-down') ?></a>
      </div>
      <ul class="ct-grid rg-top-grid is-n<?= min(4, count($topCities)) ?>">
        <?php foreach ($topCities as $ci => $city): ?>
        <li class="ct-card">
          <a class="ct-top" href="<?= v2_e($city['href']) ?>">
            <span class="ct-media"><?= $city['photo'] ? v2_photo([$city['photo'][0], 0, 0, $city['photo'][3] ?? '']) : v2_fallback($city['name'], $ci) ?></span>
            <span class="ct-over"><small><?= v2_e($city['county'] !== '' && $city['county'] !== $city['name'] ? $city['county'] : $regionName) ?></small><h3><?= v2_e($city['name']) ?></h3></span>
            <?php if ($city['acts'] > 0): ?><span class="ct-badge"><?= v2_e(v2_num($city['acts'], 'activity', 'activities')) ?></span><?php endif; ?>
          </a>
          <div class="ct-body">
            <ul class="ct-links" aria-label="<?= v2_te('{city}: local pages', ['city' => $city['name']]) ?>">
              <li><a class="is-kids" href="<?= v2_e($city['href']) ?>/with-kids"><?= v2_te('Kids') ?></a></li>
              <li><a href="<?= v2_e($city['href']) ?>/weekend-ideas"><?= v2_te('Weekend') ?></a></li>
              <li><a href="<?= v2_e($city['href']) ?>/activitati-indoor"><?= v2_te('Indoor') ?></a></li>
              <li><a class="is-main" href="<?= v2_e($city['href']) ?>"><?= v2_te('View city') ?><?= v2_ic('arrow-right') ?></a></li>
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
          <p class="kicker"><?= $countyCount > 0 ? v2_te('By county') : v2_te('Index') ?></p>
          <h2 id="rg-all-h"><?= v2_te('All {cities} in {region}', ['cities' => v2_num($cityCount, 'city', 'cities'), 'region' => $regionName]) ?></h2>
        </div>
        <p class="rg-all-count" id="rg-count" aria-live="polite"><?= v2_te('{shown} of {total} cities', ['shown' => $cityCount, 'total' => $cityCount]) ?></p>
      </div>
      <ul class="rg-counties" id="rg-counties">
        <?php foreach ($counties as $countyName => $countyCities): ?>
        <li class="ct-letter rg-county">
          <h3><?= v2_e($countyName !== '' ? $countyName : v2_t('Other places')) ?><small><?= count($countyCities) ?></small></h3>
          <ul>
            <?php foreach ($countyCities as $city): ?>
            <li data-q="<?= v2_e(implode(' ', [$city['name'], $countyName])) ?>"><a href="<?= v2_e($city['href']) ?>"><?= v2_e($city['name']) ?><?php if ($city['acts'] > 0): ?><span class="rg-n"><?= v2_e(v2_num($city['acts'], 'activity', 'activities')) ?></span><?php endif; ?></a></li>
            <?php endforeach; ?>
          </ul>
        </li>
        <?php endforeach; ?>
      </ul>
      <div class="rg-none" id="rg-none" hidden>
        <p><?= v2_te('No city in {region} matches your search.', ['region' => $regionName]) ?></p>
        <div class="rg-none-cta">
          <button class="btn btn-outline-light" type="button" id="rg-reset"><?= v2_te('Show all cities') ?></button>
          <a class="btn btn-light" id="rg-elsewhere" href="/cities"><?= v2_te('Search all cities') ?><?= v2_ic('arrow-right') ?></a>
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
        <p class="kicker"><?= v2_te('Ideas in {city}', ['city' => $mainCity['name']]) ?></p>
        <h2 id="rg-hubs-h"><?= v2_te('Already know who you are going with? Start here.') ?></h2>
        <p><?= v2_te('Ready-filtered pages for the most searched city in {region}: with kids, at the weekend, indoors or on a small budget.', ['region' => $regionName]) ?></p>
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
      <div><p class="kicker"><?= v2_te('FAQ') ?></p><h2 id="rg-faq-h"><?= v2_te('About {region} on Viaqui', ['region' => $regionName]) ?></h2></div>
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
          <p class="kicker"><?= v2_te('Somewhere else?') ?></p>
          <h2 id="rg-final-h"><?= v2_te('All of Europe, by city and by category.') ?></h2>
          <p><?= v2_te('See every city with activities, or start from the kind of experience: escape rooms, museums, parks, workshops, nature.') ?></p>
        </div>
        <div class="ct-final-cta">
          <a class="btn btn-light" href="/cities"><?= v2_te('All cities') ?><?= v2_ic('arrow-right') ?></a>
          <a class="btn btn-outline-light" href="/categories"><?= v2_te('See the categories') ?></a>
        </div>
      </div>
    </div>
  </section>
</main>
<?php include __DIR__ . '/includes/v2/footer.php'; ?>
