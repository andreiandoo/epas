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

$atParams = array_filter(['city' => $atCitySlug, 'type' => $atType, 'country' => $atCountry, 'search' => $atQ, 'sort' => $atSort, 'per_page' => 24, 'page' => $atPage], fn ($v) => $v !== '' && $v !== null);
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
$atUrl = function (array $over = []) use ($atBase, $atType, $atCountry, $atQ, $atSort) {
    $q = array_filter(array_merge(['type' => $atType, 'country' => strtolower($atCountry), 'q' => $atQ, 'sort' => $atSort, 'page' => ''], $over), fn ($v) => $v !== '' && $v !== null && $v !== 1);
    return $atBase . ($q ? '?' . http_build_query($q) : '');
};
$atTypeName = $atType !== '' ? V2_ATTRACTION_TYPES[$atType] : '';
$atWhere = $atCityName !== '' ? $atCityName : $atCountryName;
$atHeading = ($atTypeName !== '' ? $atTypeName : 'Attractions') . ($atWhere !== '' ? ' in ' . $atWhere : '');
$atFiltered = $atType !== '' || $atCountry !== '' || $atQ !== '';

$pageTitle = $atHeading . ($atPage > 1 ? ' (page ' . $atPage . ')' : '');
$pageDescription = ($atTotal > 0 ? v2_num($atTotal, 'place', 'places') . ': ' : '') . mb_strtolower($atTypeName !== '' ? $atTypeName : 'castles, museums, cathedrals, caves, parks and viewpoints')
    . ($atWhere !== '' ? ' in ' . $atWhere : ' across Europe') . ', each with a map, what is around it and what you can book nearby.';
$canonicalUrl = SITE_URL . $atUrl(['q' => '', 'sort' => '', 'page' => $atPage > 1 ? $atPage : '']);
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
    <nav class="v-crumbs" aria-label="Breadcrumb">
      <a href="/">Home</a><span aria-hidden="true">/</span>
      <?php if ($atCityName !== ''): ?><a href="/<?= v2_e($atCitySlug) ?>"><?= v2_e($atCityName) ?></a><span aria-hidden="true">/</span><b>Attractions</b>
      <?php elseif ($atFiltered): ?><a href="/attractions">Attractions</a><span aria-hidden="true">/</span><b><?= v2_e($atTypeName !== '' ? $atTypeName : ($atCountryName !== '' ? $atCountryName : 'Search')) ?></b>
      <?php else: ?><b>Attractions</b><?php endif; ?>
    </nav>
    <p class="v-eyebrow">Places to see</p>
    <h1 class="v-phero-h" id="at-h"><?= v2_e(mb_strtolower($atHeading)) ?></h1>
    <p class="v-phero-lede"><?= v2_e(ucfirst($pageDescription)) ?></p>
    <form class="v-afilter" action="<?= v2_e($atBase) ?>" method="get" role="search" aria-label="Filter attractions">
      <label class="v-afield v-afield-q"><span>Name</span><input id="at-q" name="q" type="search" value="<?= v2_e($atQ) ?>" placeholder="Search an attraction by name" autocomplete="off"></label>
      <?php if ($atCitySlug === '' && $atCountries): ?>
      <label class="v-afield"><span>Country</span><select id="at-country" name="country">
        <option value="">All countries</option>
        <?php foreach ($atCountries as $c): ?><option value="<?= v2_e(strtolower((string) ($c['code'] ?? ''))) ?>"<?= ($c['code'] ?? '') === $atCountry ? ' selected' : '' ?>><?= v2_e($c['name']) ?></option><?php endforeach; ?>
      </select></label>
      <?php endif; ?>
      <label class="v-afield"><span>Order</span><select id="at-sort" name="sort"><option value="">Best known first</option><option value="name"<?= $atSort === 'name' ? ' selected' : '' ?>>A to Z</option></select></label>
      <?php if ($atType !== ''): ?><input type="hidden" name="type" value="<?= v2_e($atType) ?>"><?php endif; ?>
      <button class="btn btn-primary" type="submit"><?= v2_ic('magnifying-glass') ?>Show</button>
    </form>
  </div>
</section>
<div id="hdr-sentinel" aria-hidden="true"></div>

<section class="v-psec" aria-labelledby="at-list-h">
  <div class="wrap">
    <nav class="v-types" aria-label="Type of attraction">
      <a href="<?= v2_e($atUrl(['type' => ''])) ?>"<?= $atType === '' ? ' aria-current="true"' : '' ?>>All types</a>
      <?php foreach (V2_ATTRACTION_TYPES as $ts => $tn): ?><a href="<?= v2_e($atUrl(['type' => $ts])) ?>"<?= $atType === $ts ? ' aria-current="true"' : '' ?>><?= v2_e($tn) ?></a><?php endforeach; ?>
    </nav>

    <div class="v-phead v-phead-list">
      <div><p class="v-eyebrow"><?= number_format($atTotal) ?> <?= $atTotal === 1 ? 'place' : 'places' ?><?= $atQ !== '' ? ' for “' . v2_e($atQ) . '”' : '' ?></p><h2 class="v-ph2" id="at-list-h"><?= v2_e($atHeading) ?></h2></div>
      <?php if ($atFiltered): ?><a class="v-plink" href="<?= v2_e($atBase) ?>">Clear the filters<?= v2_ic('x') ?></a><?php endif; ?>
    </div>

    <?php if ($atItems): ?>
    <div class="v-pgrid v-agrid">
      <?php foreach ($atItems as $i => $a): ?>
      <a class="v-pcard" href="<?= v2_e($a['href']) ?>">
        <span class="v-pcard-ph"><?= $a['image'] ? '<img src="' . v2_e(str_replace('?width=960', '?width=480', $a['image'])) . '" alt="" loading="lazy" decoding="async">' : v2_fallback($a['name'], $i) ?></span>
        <strong><?= v2_e($a['name']) ?></strong>
        <span><?= v2_e(implode(' · ', array_filter([$a['type'], $a['city']]))) ?></span>
      </a>
      <?php endforeach; ?>
    </div>
    <p class="v-acredit">Photographs from Wikimedia Commons. The author and the licence of each photo are on the attraction's page.</p>
    <?php else: ?>
    <p class="v-pempty">Nothing matches these filters yet. Try another type<?= $atCitySlug === '' ? ' or another country' : '' ?>.</p>
    <?php endif; ?>

    <?php if ($atLast > 1): ?>
    <nav class="v-pager" aria-label="Pages">
      <?php if ($atPage > 1): ?><a class="v-pager-step" href="<?= v2_e($atUrl(['page' => $atPage - 1])) ?>" rel="prev"><?= v2_ic('arrow-left') ?>Previous</a><?php endif; ?>
      <span>Page <?= $atPage ?> of <?= number_format($atLast) ?></span>
      <?php if ($atPage < $atLast): ?><a class="v-pager-step" href="<?= v2_e($atUrl(['page' => $atPage + 1])) ?>" rel="next">Next<?= v2_ic('arrow-right') ?></a><?php endif; ?>
    </nav>
    <?php endif; ?>
  </div>
</section>

<section class="v-psec v-ptools-sec" aria-labelledby="at-tools-h">
  <div class="wrap"><div class="v-ptools">
    <div><p class="v-eyebrow">Plan</p><h2 class="v-ph2" id="at-tools-h">See them in the right order.</h2><p>Pick the places you want and let the planner arrange them by day, in the order they link up on the road.</p></div>
    <div class="v-ptools-cta"><a class="btn v-btn-forest" href="/plan"><?= v2_ic('compass') ?>Trip planner</a><a class="btn v-btn-ghost" href="<?= v2_e($atCitySlug !== '' ? '/map?city=' . $atCitySlug : ($atCountry !== '' ? v2_map_href($atCountry) : '/map')) ?>"><?= v2_ic('map-trifold') ?>Attractions map</a><a class="btn v-btn-ghost" href="/cities"><?= v2_ic('map-pin') ?>Destinations</a></div>
  </div></div>
</section>
</main>

<?php include __DIR__ . '/includes/v2/footer.php'; ?>
