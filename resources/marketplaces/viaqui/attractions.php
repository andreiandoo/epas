<?php
/**
 * Attractions: /attractions and /{city}/attractions (v2, Viaqui).
 *
 * Reads GET /attractions of the core API. Query, all optional and all in the address so a list can be shared:
 *   type = attraction type slug · country = ISO code · q = text in the name · sort = name · page
 * The city comes from the path (.htaccess sends /{city}/attractions here as ?city=).
 *
 * Cover photos are hot-linked from Wikimedia Commons; the credit each licence asks for is on the attraction's page,
 * and the list says so.
 */
$pageCacheTTL = 600;
require_once __DIR__ . '/includes/page-cache.php';
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/api.php';
require_once __DIR__ . '/includes/nav-helpers.php';
require_once __DIR__ . '/includes/v2/helpers.php';
require_once __DIR__ . '/includes/v2/nav.php';
require_once __DIR__ . '/includes/v2/places.php';

$atCitySlug = isset($_GET['city']) && is_string($_GET['city']) && preg_match('/^[a-z][a-z0-9-]{1,80}$/', $_GET['city']) ? $_GET['city'] : '';
$atType = isset($_GET['type']) && is_string($_GET['type']) && isset(V2_ATTRACTION_TYPES[$_GET['type']]) ? $_GET['type'] : '';
$atCountry = isset($_GET['country']) && is_string($_GET['country']) && preg_match('/^[a-zA-Z]{2}$/', $_GET['country']) ? strtoupper($_GET['country']) : '';
$atQ = isset($_GET['q']) && is_string($_GET['q']) ? mb_substr(trim($_GET['q']), 0, 80) : '';
$atSort = ($_GET['sort'] ?? '') === 'name' ? 'name' : '';
$atUnesco = ($_GET['unesco'] ?? '') === '1';      // World Heritage Sites only
$atPage = max(1, min(2000, (int) ($_GET['page'] ?? 1)));

$atCountries = $V2NAV['countriesFull'] ?? [];
$atCountryName = '';
foreach ($atCountries as $c) {
    if (($c['code'] ?? '') === $atCountry && $atCountry !== '') {
        $atCountryName = (string) $c['name'];
    }
}
if ($atCountry !== '' && $atCountryName === '') {
    $atCountry = '';
}
if ($atCitySlug !== '') {
    $atCountry = '';   // a city already says where
}

$atParams = array_filter(['city' => $atCitySlug, 'type' => $atType, 'country' => $atCountry, 'search' => $atQ, 'sort' => $atSort, 'unesco' => $atUnesco ? '1' : '', 'per_page' => 24, 'page' => $atPage], fn ($v) => $v !== '' && $v !== null);
$atJobs = ['list' => ['key' => 'v2_attractions_' . md5(json_encode($atParams)), 'endpoint' => '/attractions', 'params' => $atParams, 'ttl' => 900]];
if ($atCitySlug !== '') {
    $atJobs['city'] = ['key' => 'city_full_' . $atCitySlug, 'endpoint' => '/locations/cities/' . rawurlencode($atCitySlug), 'params' => [], 'ttl' => 300];
}
$atR = api_cached_many($atJobs);
$atCity = $atCitySlug !== '' ? ($atR['city']['data']['city'] ?? null) : null;
if ($atCitySlug !== '' && (!is_array($atCity) || empty($atR['city']['success']))) {
    require __DIR__ . '/404.php';
    return;
}
$atCityName = $atCity ? navFlatName($atCity['name'] ?? '') : '';

$atItems = [];
foreach ((array) ($atR['list']['data']['items'] ?? []) as $row) {
    if (is_array($row) && ($a = v2_attraction($row))) {
        $a['subtitle'] = navFlatName($row['subtitle'] ?? '');
        $a['unesco'] = !empty($row['is_unesco']);
        // who took the photo, said where the photo is shown (the full credit, with the licence link, is on the attraction's page)
        $cr = is_array($row['cover_credit'] ?? null) ? $row['cover_credit'] : null;
        $a['credit'] = $cr && !empty($cr['license']) ? v2_t('Photo: {author}, {licence}, Wikimedia Commons', ['author' => ($cr['author'] ?? '') !== '' ? $cr['author'] : v2_t('unknown author'), 'licence' => $cr['license']]) : '';
        $atItems[] = $a;
    }
}
$atTotal = (int) ($atR['list']['data']['pagination']['total'] ?? count($atItems));
$atLast = max(1, (int) ($atR['list']['data']['pagination']['last_page'] ?? 1));
if ($atPage > 1 && !$atItems) {
    require __DIR__ . '/404.php';
    return;
}

$atBase = $atCitySlug !== '' ? '/' . $atCitySlug . '/attractions' : '/attractions';
$atUrl = function (array $over = []) use ($atBase, $atType, $atCountry, $atQ, $atSort, $atUnesco) {
    $q = array_filter(array_merge(['type' => $atType, 'country' => strtolower($atCountry), 'q' => $atQ, 'sort' => $atSort, 'unesco' => $atUnesco ? '1' : '', 'page' => ''], $over), fn ($v) => $v !== '' && $v !== null && $v !== 1);
    return $atBase . ($q ? '?' . http_build_query($q) : '');
};
$atTypes = v2_attraction_types_t();
$atTypeName = $atType !== '' ? $atTypes[$atType] : '';
$atWhere = $atCityName !== '' ? $atCityName : $atCountryName;
// One whole sentence for each shape of the heading: "World Heritage castles in Italy", "Castles in Rome", "Attractions".
if ($atUnesco && $atTypeName !== '') {
    $atHeading = $atWhere !== ''
        ? v2_t('World Heritage {types} in {place}', ['types' => mb_strtolower($atTypeName), 'place' => $atWhere])
        : v2_t('World Heritage {types}', ['types' => mb_strtolower($atTypeName)]);
} elseif ($atUnesco) {
    $atHeading = $atWhere !== '' ? v2_t('World Heritage sites in {place}', ['place' => $atWhere]) : v2_t('World Heritage sites');
} elseif ($atTypeName !== '') {
    $atHeading = $atWhere !== '' ? v2_t('{types} in {place}', ['types' => $atTypeName, 'place' => $atWhere]) : $atTypeName;
} else {
    $atHeading = $atWhere !== '' ? v2_t('Attractions in {place}', ['place' => $atWhere]) : v2_t('Attractions');
}
$atFiltered = $atType !== '' || $atCountry !== '' || $atQ !== '' || $atUnesco;

$pageTitle = $atPage > 1 ? v2_t('{title} (page {page})', ['title' => $atHeading, 'page' => $atPage]) : $atHeading;
$atWhat = $atTypeName !== '' ? mb_strtolower($atTypeName) : v2_t('castles, museums, cathedrals, caves, parks and viewpoints');
$pageDescription = $atWhere !== ''
    ? v2_t('{what} in {place}, each with a map, what is around it and what you can book nearby.', ['what' => $atWhat, 'place' => $atWhere])
    : v2_t('{what} across Europe, each with a map, what is around it and what you can book nearby.', ['what' => $atWhat]);
if ($atTotal > 0) {
    $pageDescription = v2_t('{count}: {text}', ['count' => v2_num($atTotal, 'place', 'places'), 'text' => $pageDescription]);
}
$canonicalUrl = SITE_URL . $atUrl(['q' => '', 'sort' => '', 'page' => $atPage > 1 ? $atPage : '']);      // the UNESCO filter keeps its own address
$noindex = $atQ !== '' || $atSort !== '';
$structuredData = [[
    '@context' => 'https://schema.org', '@type' => 'ItemList', 'name' => $atHeading, 'numberOfItems' => $atTotal,
    'itemListElement' => array_map(fn ($it, $i) => ['@type' => 'ListItem', 'position' => ($atPage - 1) * 24 + $i + 1, 'url' => SITE_URL . $it['href'], 'name' => $it['name']], $atItems, array_keys($atItems)),
]];
$v2Styles = ['places.css'];
$v2Scripts = [];
$v2HeaderOverlay = true;

include __DIR__ . '/includes/v2/head.php';
include __DIR__ . '/includes/v2/header.php';
?>
<main id="main" class="v-places">
<section class="v-phero" aria-labelledby="at-h">
  <div class="v-phero-topo" aria-hidden="true"></div>
  <div class="wrap v-phero-in">
    <nav class="v-crumbs" aria-label="<?= v2_te('Breadcrumb') ?>">
      <a href="/"><?= v2_te('Home') ?></a><span aria-hidden="true">/</span>
      <?php if ($atCityName !== ''): ?><a href="/<?= v2_e($atCitySlug) ?>"><?= v2_e($atCityName) ?></a><span aria-hidden="true">/</span><b><?= v2_te('Attractions') ?></b>
      <?php elseif ($atFiltered): ?><a href="/attractions"><?= v2_te('Attractions') ?></a><span aria-hidden="true">/</span><b><?= v2_e($atTypeName !== '' ? $atTypeName : ($atCountryName !== '' ? $atCountryName : v2_t('Search'))) ?></b>
      <?php else: ?><b><?= v2_te('Attractions') ?></b><?php endif; ?>
    </nav>
    <p class="v-eyebrow"><?= v2_te('Places to see') ?></p>
    <h1 class="v-phero-h" id="at-h"><?= v2_e(mb_strtolower($atHeading)) ?></h1>
    <p class="v-phero-lede"><?= v2_e(mb_strtoupper(mb_substr($pageDescription, 0, 1)) . mb_substr($pageDescription, 1)) ?></p>
    <form class="v-afilter" action="<?= v2_e($atBase) ?>" method="get" role="search" aria-label="<?= v2_te('Filter attractions') ?>">
      <label class="v-afield v-afield-q"><span><?= v2_te('Name') ?></span><input id="at-q" name="q" type="search" value="<?= v2_e($atQ) ?>" placeholder="<?= v2_te('Search an attraction by name') ?>" autocomplete="off"></label>
      <?php if ($atCitySlug === '' && $atCountries): ?>
      <label class="v-afield"><span><?= v2_te('Country') ?></span><select id="at-country" name="country">
        <option value=""><?= v2_te('All countries') ?></option>
        <?php foreach ($atCountries as $c): ?><option value="<?= v2_e(strtolower((string) ($c['code'] ?? ''))) ?>"<?= ($c['code'] ?? '') === $atCountry ? ' selected' : '' ?>><?= v2_e($c['name']) ?></option><?php endforeach; ?>
      </select></label>
      <?php endif; ?>
      <label class="v-afield"><span><?= v2_te('Order') ?></span><select id="at-sort" name="sort"><option value=""><?= v2_te('Best known first') ?></option><option value="name"<?= $atSort === 'name' ? ' selected' : '' ?>><?= v2_te('A to Z') ?></option></select></label>
      <?php if ($atType !== ''): ?><input type="hidden" name="type" value="<?= v2_e($atType) ?>"><?php endif; ?>
      <?php if ($atUnesco): ?><input type="hidden" name="unesco" value="1"><?php endif; ?>
      <button class="btn btn-primary" type="submit"><?= v2_ic('magnifying-glass') ?><?= v2_te('Show') ?></button>
    </form>
  </div>
</section>
<div id="hdr-sentinel" aria-hidden="true"></div>

<section class="v-psec" aria-labelledby="at-list-h">
  <div class="wrap">
    <nav class="v-types" aria-label="<?= v2_te('Type of attraction') ?>">
      <a class="v-types-u" href="<?= v2_e($atUrl(['unesco' => $atUnesco ? '' : '1'])) ?>"<?= $atUnesco ? ' aria-current="true"' : '' ?>><?= v2_ic('star') ?><?= v2_te('UNESCO World Heritage') ?></a>
      <a href="<?= v2_e($atUrl(['type' => ''])) ?>"<?= $atType === '' ? ' aria-current="true"' : '' ?>><?= v2_te('All types') ?></a>
      <?php foreach ($atTypes as $ts => $tn): ?><a href="<?= v2_e($atUrl(['type' => $ts])) ?>"<?= $atType === $ts ? ' aria-current="true"' : '' ?>><?= v2_e($tn) ?></a><?php endforeach; ?>
    </nav>

    <div class="v-phead v-phead-list">
      <div><p class="v-eyebrow"><?= $atQ !== '' ? v2_te('{places} for “{query}”', ['places' => v2_num($atTotal, 'place', 'places'), 'query' => $atQ]) : v2_e(v2_num($atTotal, 'place', 'places')) ?></p><h2 class="v-ph2" id="at-list-h"><?= v2_e($atHeading) ?></h2></div>
      <?php if ($atFiltered): ?><a class="v-plink" href="<?= v2_e($atBase) ?>"><?= v2_te('Clear the filters') ?><?= v2_ic('x') ?></a><?php endif; ?>
    </div>

    <?php if ($atItems): ?>
    <div class="v-pgrid v-agrid">
      <?php foreach ($atItems as $i => $a): ?>
      <a class="v-pcard" href="<?= v2_e($a['href']) ?>">
        <span class="v-pcard-ph"><?= $a['image'] ? '<img src="' . v2_e(v2_thumb($a['image'], 480)) . '" alt=""' . ($a['credit'] !== '' ? ' title="' . v2_e($a['credit']) . '"' : '') . ' loading="lazy" decoding="async">' : v2_fallback($a['name'], $i) ?><?php if ($a['unesco']): ?><small class="v-pcard-u"><?= v2_ic('star') ?>UNESCO</small><?php endif; ?></span>
        <strong><?= v2_e($a['name']) ?></strong>
        <span><?= v2_e(implode(' · ', array_filter([$a['type'], $a['city']]))) ?></span>
      </a>
      <?php endforeach; ?>
    </div>
    <p class="v-acredit"><?= v2_te('Photographs from Wikimedia Commons: point at a photo to see who took it; the full credit and the licence are on the attraction\'s page.') ?> <a href="/photo-credits"><?= v2_te('About the photos') ?></a></p>
    <?php else: ?>
    <p class="v-pempty"><?= $atCitySlug === '' ? v2_te('Nothing matches these filters yet. Try another type or another country.') : v2_te('Nothing matches these filters yet. Try another type.') ?></p>
    <?php endif; ?>

    <?php if ($atLast > 1): ?>
    <nav class="v-pager" aria-label="<?= v2_te('Pages') ?>">
      <?php if ($atPage > 1): ?><a class="v-pager-step" href="<?= v2_e($atUrl(['page' => $atPage - 1])) ?>" rel="prev"><?= v2_ic('arrow-left') ?><?= v2_te('Previous') ?></a><?php endif; ?>
      <span><?= v2_te('Page {page} of {pages}', ['page' => $atPage, 'pages' => number_format($atLast)]) ?></span>
      <?php if ($atPage < $atLast): ?><a class="v-pager-step" href="<?= v2_e($atUrl(['page' => $atPage + 1])) ?>" rel="next"><?= v2_te('Next') ?><?= v2_ic('arrow-right') ?></a><?php endif; ?>
    </nav>
    <?php endif; ?>
  </div>
</section>

<section class="v-psec v-ptools-sec" aria-labelledby="at-tools-h">
  <div class="wrap"><div class="v-ptools">
    <div><p class="v-eyebrow"><?= v2_te('Plan') ?></p><h2 class="v-ph2" id="at-tools-h"><?= v2_te('See them in the right order.') ?></h2><p><?= v2_te('Pick the places you want and let the planner arrange them by day, in the order they link up on the road.') ?></p></div>
    <div class="v-ptools-cta"><a class="btn v-btn-forest" href="/plan"><?= v2_ic('compass') ?><?= v2_te('Trip planner') ?></a><a class="btn v-btn-ghost" href="<?= v2_e($atCitySlug !== '' ? '/map?city=' . $atCitySlug : ($atCountry !== '' ? v2_map_href($atCountry) : '/map')) ?>"><?= v2_ic('map-trifold') ?><?= v2_te('Attractions map') ?></a><a class="btn v-btn-ghost" href="/cities"><?= v2_ic('map-pin') ?><?= v2_te('Destinations') ?></a></div>
  </div></div>
</section>
</main>

<?php include __DIR__ . '/includes/v2/footer.php'; ?>
