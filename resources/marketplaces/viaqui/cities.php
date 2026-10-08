<?php
/**
 * Destinations: /cities (v2, Viaqui).
 *
 * The index of everywhere Viaqui covers: the featured destinations as picture cards, then every country with its
 * largest cities and a link to the country page (where all of its cities are listed). The page stays light however
 * many cities the database holds, because it only prints what the shell already loaded (v2/nav.php).
 *
 * /cities?country=it (links of the first version of the menu) redirects to the country page.
 */
$pageCacheTTL = 900;
require_once __DIR__ . '/includes/page-cache.php';
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/api.php';
require_once __DIR__ . '/includes/nav-helpers.php';
require_once __DIR__ . '/includes/v2/helpers.php';
require_once __DIR__ . '/includes/v2/nav.php';
require_once __DIR__ . '/includes/v2/places.php';

$dsCountries = $V2NAV['countriesFull'] ?? $V2NAV['regions'];
if (isset($_GET['country']) && is_string($_GET['country'])) {
    foreach ($dsCountries as $c) {
        if (strcasecmp((string) ($c['code'] ?? ''), $_GET['country']) === 0) {
            header('Location: /' . $c['slug'], true, 301);
            exit;
        }
    }
}

$dsFeatured = array_slice(array_values(array_filter($V2NAV['citiesList'], fn ($c) => !empty($c['featuredFlag']))), 0, 12) ?: array_slice($V2NAV['citiesList'], 0, 12);
$dsTotal = (int) ($V2NAV['citiesTotal'] ?? 0) ?: array_sum(array_column($dsCountries, 'citiesCount'));

$pageTitle = v2_t('Destinations: cities and countries');
$pageDescription = v2_t('{cities} in {countries}. Choose a country, then a city, and see the attractions, tours and experiences you can book there.', ['cities' => v2_num($dsTotal, 'city', 'cities'), 'countries' => v2_num(count($dsCountries), 'country', 'countries')]);
$canonicalUrl = SITE_URL . '/cities';
$structuredData = [[
    '@context' => 'https://schema.org', '@type' => 'BreadcrumbList',
    'itemListElement' => [
        ['@type' => 'ListItem', 'position' => 1, 'name' => v2_t('Home'), 'item' => SITE_URL . '/'],
        ['@type' => 'ListItem', 'position' => 2, 'name' => v2_t('Destinations'), 'item' => SITE_URL . '/cities'],
    ],
]];
$v2Styles = ['places.css'];
$v2Scripts = ['places.js'];
$v2HeaderOverlay = true;

include __DIR__ . '/includes/v2/head.php';
include __DIR__ . '/includes/v2/header.php';
?>
<main id="main" class="v-places">
<section class="v-phero" aria-labelledby="ds-h">
  <div class="v-phero-topo" aria-hidden="true"></div>
  <div class="wrap v-phero-in">
    <nav class="v-crumbs" aria-label="<?= v2_te('Breadcrumb') ?>"><a href="/"><?= v2_te('Home') ?></a><span aria-hidden="true">/</span><b><?= v2_te('Destinations') ?></b></nav>
    <p class="v-eyebrow"><?= v2_te('Where to') ?></p>
    <h1 class="v-phero-h" id="ds-h"><?= v2_te('destinations') ?></h1>
    <p class="v-phero-lede"><?= v2_e($pageDescription) ?></p>
    <ul class="v-pstats">
      <li><b><?= number_format($dsTotal) ?></b><span><?= v2_e(v2_plural($dsTotal, 'city', 'cities and towns')) ?></span></li>
      <li><b><?= count($dsCountries) ?></b><span><?= v2_e(v2_plural(count($dsCountries), 'country', 'countries')) ?></span></li>
      <li><a class="btn v-btn-cream" href="/map"><?= v2_ic('map-trifold') ?><?= v2_te('Open the map') ?></a></li>
    </ul>
  </div>
</section>
<div id="hdr-sentinel" aria-hidden="true"></div>

<?php if ($dsFeatured): ?>
<section class="v-psec" aria-labelledby="ds-top-h">
  <div class="wrap">
    <div class="v-phead"><div><p class="v-eyebrow"><?= v2_te('Start here') ?></p><h2 class="v-ph2" id="ds-top-h"><?= v2_te('Where people are heading') ?></h2></div></div>
    <div class="v-pgrid">
      <?php foreach ($dsFeatured as $i => $c): ?><?= v2_city_card($c, $i) ?><?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<section class="v-psec v-psec-tint" aria-labelledby="ds-all-h">
  <div class="wrap">
    <div class="v-phead"><div><p class="v-eyebrow"><?= v2_te('By country') ?></p><h2 class="v-ph2" id="ds-all-h"><?= v2_te('Every country') ?></h2></div>
      <label class="v-cfind"><?= v2_ic('magnifying-glass') ?><span class="sr"><?= v2_te('Search a country or a city') ?></span><input type="search" id="ds-find" placeholder="<?= v2_te('Search a country or a city') ?>" autocomplete="off" spellcheck="false"></label>
    </div>
    <div class="v-countries">
      <?php foreach ($dsCountries as $c): $dsCities = array_slice($c['featured'] ?? [], 0, 8); ?>
      <article class="v-country" id="c-<?= v2_e(strtolower((string) ($c['code'] ?? $c['slug']))) ?>" data-find="<?= v2_e(mb_strtolower($c['name'] . ' ' . implode(' ', array_column($c['featured'] ?? [], 'name')))) ?>">
        <h3><a href="/<?= v2_e($c['slug']) ?>"><?= v2_flag((string) ($c['code'] ?? '')) ?><?= v2_e($c['name']) ?></a><span><?= v2_e(v2_num((int) $c['citiesCount'], 'city', 'cities')) ?></span></h3>
        <?php if ($dsCities): ?>
        <ul><?php foreach ($dsCities as $city): ?><li><a href="<?= v2_e($city['href']) ?>"><?= v2_e($city['name']) ?></a></li><?php endforeach; ?></ul>
        <?php endif; ?>
        <a class="v-plink" href="/<?= v2_e($c['slug']) ?>"><?= v2_te('All of {name}', ['name' => $c['name']]) ?><?= v2_ic('arrow-right') ?></a>
      </article>
      <?php endforeach; ?>
    </div>
    <p class="v-pempty" id="ds-none" hidden><?= v2_t('No country or city with this name among the ones listed here. Try the <a href="/search">search</a>.') ?></p>
  </div>
</section>

<section class="v-psec v-ptools-sec" aria-labelledby="ds-tools-h">
  <div class="wrap"><div class="v-ptools">
    <div><p class="v-eyebrow"><?= v2_te('Plan') ?></p><h2 class="v-ph2" id="ds-tools-h"><?= v2_te('Not sure where yet?') ?></h2><p><?= v2_te('Start from the map and see what is near you, or follow a ready-made route with the stops already in order.') ?></p></div>
    <div class="v-ptools-cta"><a class="btn v-btn-forest" href="/map"><?= v2_ic('map-trifold') ?><?= v2_te('Attractions map') ?></a><a class="btn v-btn-ghost" href="/routes"><?= v2_ic('path') ?><?= v2_te('Routes') ?></a><a class="btn v-btn-ghost" href="/plan"><?= v2_ic('compass') ?><?= v2_te('Trip planner') ?></a></div>
  </div></div>
</section>
</main>

<?php include __DIR__ . '/includes/v2/footer.php'; ?>
