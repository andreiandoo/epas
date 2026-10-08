<?php
/**
 * Country page: /{country}, e.g. /italy (v2, Viaqui).
 *
 * Reached through slug.php, which sets $countryRow (name, slug, code from the shell's country list).
 * Query: region = region slug (the cities of one region), sort = name (A to Z; default is largest first), page.
 *
 * Top to bottom: hero (breadcrumb, name, counts), top destinations (first page only), regions, every city
 * (paginated), planning tools.
 */
$pageCacheTTL = 900;
require_once __DIR__ . '/includes/page-cache.php';
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/api.php';
require_once __DIR__ . '/includes/nav-helpers.php';
require_once __DIR__ . '/includes/v2/helpers.php';
require_once __DIR__ . '/includes/v2/nav.php';
require_once __DIR__ . '/includes/v2/places.php';
require_once __DIR__ . '/includes/v2/partners.php';

$cnSlug = (string) ($countryRow['slug'] ?? ($_GET['slug'] ?? ''));
$cnCode = strtoupper((string) ($countryRow['code'] ?? ''));
$cnRegionSlug = isset($_GET['region']) && is_string($_GET['region']) && preg_match('/^[a-z0-9-]{2,80}$/', $_GET['region']) ? $_GET['region'] : '';
$cnSort = ($_GET['sort'] ?? '') === 'name' ? 'name' : 'size';
$cnPage = max(1, min(500, (int) ($_GET['page'] ?? 1)));
$cnPerPage = 60;

$cnParams = ['country' => $cnCode, 'per_page' => $cnPerPage, 'page' => $cnPage, 'sort' => $cnSort === 'name' ? 'name' : 'default'];
if ($cnRegionSlug !== '') {
    $cnParams['region'] = $cnRegionSlug;
}
$cnR = api_cached_many([
    'country' => ['key' => 'v2_country_' . $cnSlug, 'endpoint' => '/locations/countries/' . rawurlencode($cnSlug), 'params' => [], 'ttl' => 3600],
    'cities' => ['key' => 'v2_country_cities_' . md5(json_encode($cnParams)), 'endpoint' => '/locations/cities', 'params' => $cnParams, 'ttl' => 3600],
]);
$cnCountry = $cnR['country']['data']['country'] ?? null;
if (empty($cnR['country']['success']) || !is_array($cnCountry)) {
    require __DIR__ . '/404.php';
    return;
}
$cnName = (string) $cnCountry['name'];
$cnTotal = (int) ($cnCountry['cities_count'] ?? 0);
$cnRegions = array_values(array_filter((array) ($cnR['country']['data']['regions'] ?? []), 'is_array'));

$cnRegion = null;
foreach ($cnRegions as $r) {
    if ($r['slug'] === $cnRegionSlug) {
        $cnRegion = $r;
    }
}
if ($cnRegionSlug !== '' && !$cnRegion) {
    require __DIR__ . '/404.php';
    return;
}

$cnCities = [];
foreach ((array) ($cnR['cities']['data'] ?? []) as $c) {
    if (is_array($c) && !empty($c['slug'])) {
        $cnCities[] = v2_city_from_api($c);
    }
}
$cnListTotal = (int) ($cnR['cities']['meta']['total'] ?? count($cnCities));
$cnLastPage = max(1, (int) ($cnR['cities']['meta']['last_page'] ?? 1));
if ($cnPage > $cnLastPage) {
    require __DIR__ . '/404.php';
    return;
}

// The first, unfiltered page opens with the twelve leading destinations as picture cards; the list follows.
$cnShowTop = $cnPage === 1 && !$cnRegion && $cnSort === 'size';
$cnTop = $cnShowTop ? array_slice($cnCities, 0, 12) : [];

$cnUrl = function (array $over = []) use ($cnSlug, $cnRegionSlug, $cnSort) {
    $q = array_filter(array_merge(['region' => $cnRegionSlug, 'sort' => $cnSort === 'name' ? 'name' : '', 'page' => ''], $over), fn ($v) => $v !== '' && $v !== null && $v !== 1);
    return '/' . $cnSlug . ($q ? '?' . http_build_query($q) : '');
};

$cnHeading = $cnRegion ? $cnRegion['name'] : $cnName;
$pageTitle = $cnRegion
    ? v2_t('{region}, {country}: cities and things to do', ['region' => $cnRegion['name'], 'country' => $cnName])
    : v2_t('{country}: cities and things to do', ['country' => $cnName]);
if ($cnPage > 1) {
    $pageTitle = v2_t('{title} (page {page})', ['title' => $pageTitle, 'page' => $cnPage]);
}
if ($cnRegion) {
    $pageDescription = v2_t('{cities} in {region}, {country}. Pick a city to see the attractions, tours and experiences you can book there.', ['cities' => v2_num((int) $cnRegion['cities_count'], 'city', 'cities'), 'region' => $cnRegion['name'], 'country' => $cnName]);
} elseif (count($cnRegions) > 1) {
    $pageDescription = v2_t('{cities} in {country}, across {regions}. Pick a city to see the attractions, tours and experiences you can book there.', ['cities' => v2_num($cnTotal, 'city', 'cities'), 'country' => $cnName, 'regions' => v2_num(count($cnRegions), 'region', 'regions')]);
} else {
    $pageDescription = v2_t('{cities} in {country}. Pick a city to see the attractions, tours and experiences you can book there.', ['cities' => v2_num($cnTotal, 'city', 'cities'), 'country' => $cnName]);
}
$canonicalUrl = SITE_URL . $cnUrl(['sort' => '', 'page' => $cnPage > 1 ? $cnPage : '']);
$noindex = $cnSort === 'name';
$structuredData = [[
    '@context' => 'https://schema.org', '@type' => 'BreadcrumbList',
    'itemListElement' => array_values(array_filter([
        ['@type' => 'ListItem', 'position' => 1, 'name' => v2_t('Home'), 'item' => SITE_URL . '/'],
        ['@type' => 'ListItem', 'position' => 2, 'name' => v2_t('Destinations'), 'item' => SITE_URL . '/cities'],
        ['@type' => 'ListItem', 'position' => 3, 'name' => $cnName, 'item' => SITE_URL . '/' . $cnSlug],
        $cnRegion ? ['@type' => 'ListItem', 'position' => 4, 'name' => $cnRegion['name'], 'item' => SITE_URL . $cnUrl(['sort' => '', 'page' => ''])] : null,
    ])),
]];
$v2Styles = ['places.css'];
$v2Scripts = ['places.js'];
$v2HeaderOverlay = true;

include __DIR__ . '/includes/v2/head.php';
include __DIR__ . '/includes/v2/header.php';
?>
<main id="main" class="v-places">
<?php
// The photo of the hero: the country's best-known place that is a sight rather than an interior, from the map's
// summary of the country (assets/v2/data/map/<cc>.summary.json; each pick carries its photo credit).
$cnShot = null;
foreach (v2_map_file(strtolower($cnCode) . '.summary')['picks'] ?? [] as $cnPick) {
    if (!empty($cnPick[6]) && !in_array($cnPick[3], ['museums', 'theatres-operas', 'zoos', 'aquariums'], true)) {
        $cnShot = $cnPick;
        break;
    }
}
?>
<section class="v-phero" aria-labelledby="cn-h">
  <div class="v-phero-topo" aria-hidden="true"></div>
  <div class="wrap v-phero-in<?= $cnShot ? ' has-shot' : '' ?>">
    <div class="v-phero-copy">
    <nav class="v-crumbs" aria-label="<?= v2_te('Breadcrumb') ?>">
      <a href="/"><?= v2_te('Home') ?></a><span aria-hidden="true">/</span><a href="/cities"><?= v2_te('Destinations') ?></a><span aria-hidden="true">/</span>
      <?php if ($cnRegion): ?><a href="/<?= v2_e($cnSlug) ?>"><?= v2_e($cnName) ?></a><span aria-hidden="true">/</span><b><?= v2_e($cnRegion['name']) ?></b><?php else: ?><b><?= v2_e($cnName) ?></b><?php endif; ?>
    </nav>
    <p class="v-eyebrow"><?= v2_flag($cnCode) ?><?= $cnRegion ? v2_te('Region of {country}', ['country' => $cnName]) : v2_te('Country') ?></p>
    <h1 class="v-phero-h" id="cn-h"><?= v2_e(mb_strtolower($cnHeading)) ?></h1>
    <p class="v-phero-lede"><?= v2_e($pageDescription) ?></p>
    <ul class="v-pstats">
      <li><b><?= number_format($cnRegion ? (int) $cnRegion['cities_count'] : $cnTotal) ?></b><span><?= v2_e(v2_plural($cnRegion ? (int) $cnRegion['cities_count'] : $cnTotal, 'city', 'cities and towns')) ?></span></li>
      <?php if (!$cnRegion && count($cnRegions) > 1): ?><li><b><?= count($cnRegions) ?></b><span><?= v2_e(v2_plural(count($cnRegions), 'region', 'regions')) ?></span></li><?php endif; ?>
      <li><a class="btn v-btn-cream" href="/plan/<?= v2_e($cnSlug) ?>"><?= v2_ic('compass') ?><?= v2_te('Plan a trip') ?></a></li>
    </ul>
    </div>
    <?php if ($cnShot): $cnShotCr = $cnShot[7] ?? []; ?>
    <figure class="v-phero-shot">
      <a href="/attraction/<?= v2_e($cnShot[0]) ?>"><img src="<?= v2_e(v2_thumb($cnShot[6], 960)) ?>" alt="<?= v2_e($cnShot[1]) ?>" fetchpriority="high" decoding="async"></a>
      <figcaption><b><?= v2_e($cnShot[1]) ?><?= $cnShot[4] !== '' ? ', ' . v2_e($cnShot[4]) : '' ?></b><?php if (!empty($cnShotCr[1])): ?><?= v2_te('Photo: {author}', ['author' => ($cnShotCr[0] ?? '') !== '' ? $cnShotCr[0] : v2_t('unknown author')]) ?> · <?php if (!empty($cnShotCr[2])): ?><a href="<?= v2_e($cnShotCr[2]) ?>" target="_blank" rel="noopener nofollow license"><?= v2_e($cnShotCr[1]) ?></a><?php else: ?><?= v2_e($cnShotCr[1]) ?><?php endif; ?> · Wikimedia Commons<?php endif; ?></figcaption>
    </figure>
    <?php endif; ?>
  </div>
</section>
<div id="hdr-sentinel" aria-hidden="true"></div>

<?php if ($cnTop): ?>
<section class="v-psec" aria-labelledby="cn-top-h">
  <div class="wrap">
    <div class="v-phead"><div><p class="v-eyebrow"><?= v2_te('Start here') ?></p><h2 class="v-ph2" id="cn-top-h"><?= v2_te('Top destinations in {country}', ['country' => $cnName]) ?></h2></div></div>
    <div class="v-pgrid">
      <?php foreach ($cnTop as $i => $c): ?><?= v2_city_card($c, $i) ?><?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<?php if (count($cnRegions) > 1):
    // Region outlines (includes/v2/regions/{cc}.json, built by plans/viaqui-data/build_region_maps.py from Natural Earth).
    // Without a file for this country the section is the list alone.
    $cnMapFile = __DIR__ . '/includes/v2/regions/' . strtolower($cnCode) . '.json';
    $cnMap = is_file($cnMapFile) ? json_decode((string) file_get_contents($cnMapFile), true) : null;
    $cnRegionBySlug = array_column($cnRegions, null, 'slug');
    // How many attractions each region holds: counted by the map's dataset (assets/v2/data/map/<cc>.summary.json).
    $cnRegionAttr = array_column(v2_map_file(strtolower($cnCode) . '.summary')['regions'] ?? [], 2, 1);
    // "1,512 attractions · 85 cities": what the list and the bubble on the map say about a region
    $cnRegionSays = function (array $r) use ($cnRegionAttr): string {
        $n = (int) ($cnRegionAttr[$r['slug']] ?? 0);
        return implode(' · ', array_filter([$n > 0 ? v2_num($n, 'attraction', 'attractions') : '', v2_num((int) $r['cities_count'], 'city', 'cities')]));
    };
?>
<section class="v-psec v-psec-tint" id="regions" aria-labelledby="cn-reg-h">
  <div class="wrap">
    <div class="v-phead"><div><p class="v-eyebrow"><?= v2_te('By region') ?></p><h2 class="v-ph2" id="cn-reg-h"><?= v2_te('Regions of {country}', ['country' => $cnName]) ?></h2></div><?php if ($cnRegion): ?><a class="v-plink" href="/<?= v2_e($cnSlug) ?>"><?= v2_te('All of {name}', ['name' => $cnName]) ?><?= v2_ic('arrow-right') ?></a><?php endif; ?></div>
    <div class="v-rmap<?= $cnMap ? '' : ' is-nomap' ?>" data-vrmap>
      <ul class="v-rlist">
        <?php foreach ($cnRegions as $r): ?>
        <li><a href="<?= v2_e($cnUrl(['region' => $r['slug'], 'page' => '', 'sort' => ''])) ?>#all" data-r="<?= v2_e($r['slug']) ?>"<?= $cnRegion && $cnRegion['slug'] === $r['slug'] ? ' aria-current="true"' : '' ?> data-says="<?= v2_e($cnRegionSays($r)) ?>"><?= v2_e($r['name']) ?><span><?php $cnN = (int) ($cnRegionAttr[$r['slug']] ?? 0); ?><?= $cnN > 0 ? v2_e(v2_num($cnN, 'attraction', 'attractions')) : v2_e(v2_num((int) $r['cities_count'], 'city', 'cities')) ?></span></a></li>
        <?php endforeach; ?>
      </ul>
      <?php if ($cnMap): ?>
      <figure class="v-rfig">
        <svg viewBox="-6 -6 <?= (float) $cnMap['w'] + 12 ?> <?= (float) $cnMap['h'] + 12 ?>" role="img" aria-label="<?= v2_te('Map of the regions of {country}', ['country' => $cnName]) ?>">
          <?php foreach ($cnMap['regions'] as $mapSlug => $mapPaths): $mapRegion = $cnRegionBySlug[$mapSlug] ?? null; ?>
          <?php if ($mapRegion): ?>
          <a href="<?= v2_e($cnUrl(['region' => $mapSlug, 'page' => '', 'sort' => ''])) ?>#all" data-r="<?= v2_e($mapSlug) ?>" data-name="<?= v2_e($mapRegion['name']) ?>" data-says="<?= v2_e($cnRegionSays($mapRegion)) ?>"<?= $cnRegion && $cnRegion['slug'] === $mapSlug ? ' aria-current="true"' : '' ?> aria-label="<?= v2_te('{region}: {what}', ['region' => $mapRegion['name'], 'what' => $cnRegionSays($mapRegion)]) ?>"><?php foreach ($mapPaths as $d): ?><path d="<?= v2_e($d) ?>"/><?php endforeach; ?></a>
          <?php else: ?>
          <g class="is-off"><?php foreach ($mapPaths as $d): ?><path d="<?= v2_e($d) ?>"/><?php endforeach; ?></g>
          <?php endif; ?>
          <?php endforeach; ?>
        </svg>
        <div class="v-rbub" data-vrbub hidden aria-hidden="true"><b></b><span></span></div>
        <figcaption class="v-rtip" data-vrtip aria-hidden="true"><?= $cnRegion ? v2_e($cnRegion['name']) : v2_te('Point at a region to see what it holds') ?></figcaption>
      </figure>
      <?php endif; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<section class="v-psec" id="all" aria-labelledby="cn-all-h">
  <div class="wrap">
    <div class="v-phead">
      <div><p class="v-eyebrow"><?= v2_e(v2_num($cnListTotal, 'place', 'places')) ?></p><h2 class="v-ph2" id="cn-all-h"><?= $cnRegion ? v2_te('Cities in {region}', ['region' => $cnRegion['name']]) : v2_te('Every city in {country}', ['country' => $cnName]) ?></h2></div>
      <p class="v-sort" role="group" aria-label="<?= v2_te('Order') ?>">
        <a href="<?= v2_e($cnUrl(['sort' => '', 'page' => ''])) ?>#all"<?= $cnSort === 'size' ? ' aria-current="true"' : '' ?>><?= v2_te('Largest first') ?></a>
        <a href="<?= v2_e($cnUrl(['sort' => 'name', 'page' => ''])) ?>#all"<?= $cnSort === 'name' ? ' aria-current="true"' : '' ?> rel="nofollow"><?= v2_te('A to Z') ?></a>
      </p>
    </div>
    <?php if ($cnCities): ?>
    <ul class="v-clist">
      <?php foreach ($cnCities as $c): ?>
      <li><a href="<?= v2_e($c['href']) ?>"><b><?= v2_e($c['name']) ?></b><?php if ($c['capital']): ?><i><?= v2_te('Capital') ?></i><?php endif; ?><span><?= v2_e(implode(' · ', array_filter([$cnRegion ? '' : $c['region'], $c['population'] ? v2_population($c['population']) : '']))) ?></span></a></li>
      <?php endforeach; ?>
    </ul>
    <?php else: ?>
    <p class="v-pempty"><?= v2_te('No cities are listed here yet.') ?></p>
    <?php endif; ?>

    <?php if ($cnLastPage > 1): ?>
    <nav class="v-pager" aria-label="<?= v2_te('Pages') ?>">
      <?php if ($cnPage > 1): ?><a class="v-pager-step" href="<?= v2_e($cnUrl(['page' => $cnPage - 1])) ?>#all" rel="prev"><?= v2_ic('arrow-left') ?><?= v2_te('Previous') ?></a><?php endif; ?>
      <span><?= v2_te('Page {page} of {pages}', ['page' => $cnPage, 'pages' => $cnLastPage]) ?></span>
      <?php if ($cnPage < $cnLastPage): ?><a class="v-pager-step" href="<?= v2_e($cnUrl(['page' => $cnPage + 1])) ?>#all" rel="next"><?= v2_te('Next') ?><?= v2_ic('arrow-right') ?></a><?php endif; ?>
    </nav>
    <?php endif; ?>
  </div>
</section>

<?php $cnTrip = ($cnPage === 1 && !$cnRegion) ? v2_trip_links('', (string) $cnCode, '', $cnName) : []; ?>
<?php if ($cnTrip): ?>
<section class="v-psec ptrip-sec" id="plan-your-trip" aria-labelledby="cn-trip-h">
  <div class="wrap">
    <div class="v-phead"><div><p class="v-eyebrow"><?= v2_te('Before you go') ?></p><h2 class="v-ph2" id="cn-trip-h"><?= v2_te('Plan your trip to {country}', ['country' => v2_partner_the($cnName)]) ?></h2></div></div>
    <?= v2_trip_tiles($cnTrip, 'country-' . $cnSlug) ?>
  </div>
</section>
<?php endif; ?>

<section class="v-psec v-ptools-sec" aria-labelledby="cn-tools-h">
  <div class="wrap"><div class="v-ptools">
    <div><p class="v-eyebrow"><?= v2_te('Plan') ?></p><h2 class="v-ph2" id="cn-tools-h"><?= v2_te('Turn {country} into an itinerary.', ['country' => $cnName]) ?></h2><p><?= v2_te('Choose where you start and how many days you have. The planner puts the places in the order they link up on the road.') ?></p></div>
    <div class="v-ptools-cta"><a class="btn v-btn-forest" href="/plan"><?= v2_ic('compass') ?><?= v2_te('Trip planner') ?></a><a class="btn v-btn-ghost" href="<?= v2_e(v2_map_href($cnCode)) ?>"><?= v2_ic('map-trifold') ?><?= v2_te('Attractions map') ?></a><a class="btn v-btn-ghost" href="/routes"><?= v2_ic('path') ?><?= v2_te('Routes') ?></a></div>
  </div></div>
</section>
</main>

<?php include __DIR__ . '/includes/v2/footer.php'; ?>
