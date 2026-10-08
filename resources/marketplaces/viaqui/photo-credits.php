<?php
/**
 * /photo-credits: where the photographs and the place data on Viaqui come from, and how each photo is credited.
 *
 * Most photos are from Wikimedia Commons, under licences that ask for the author and the licence to be named. This page
 * says where that credit is shown for each kind of photo, and how to ask for a correction or a removal.
 */
$pageCacheTTL = 3600;
require_once __DIR__ . '/includes/page-cache.php';
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/api.php';
require_once __DIR__ . '/includes/nav-helpers.php';
require_once __DIR__ . '/includes/v2/helpers.php';
require_once __DIR__ . '/includes/v2/nav.php';
require_once __DIR__ . '/includes/v2/places.php';

$pcEmail = defined('SUPPORT_EMAIL') ? SUPPORT_EMAIL : 'contact@viaqui.com';
$pageTitle = v2_t('Photo credits and data sources');
$pageDescription = v2_t('Where the photographs and the place data on Viaqui come from, how every photo is credited, and how to ask for a correction.');
$canonicalUrl = SITE_URL . '/photo-credits';
$v2Styles = ['places.css'];
$v2Scripts = [];
$v2HeaderOverlay = true;

include __DIR__ . '/includes/v2/head.php';
include __DIR__ . '/includes/v2/header.php';
?>
<main id="main" class="v-places">
<section class="v-phero" aria-labelledby="pc-h">
  <div class="v-phero-topo" aria-hidden="true"></div>
  <div class="wrap v-phero-in">
    <nav class="v-crumbs" aria-label="<?= v2_te('Breadcrumb') ?>"><a href="/"><?= v2_te('Home') ?></a><span aria-hidden="true">/</span><b><?= v2_te('Photo credits') ?></b></nav>
    <p class="v-eyebrow"><?= v2_te('About the photos') ?></p>
    <h1 class="v-phero-h" id="pc-h"><?= v2_te('photo credits') ?></h1>
    <p class="v-phero-lede"><?= v2_te('Most photographs of places on Viaqui were taken by volunteers and shared on Wikimedia Commons. This page says how we credit them and where the rest of our place data comes from.') ?></p>
  </div>
</section>
<div id="hdr-sentinel" aria-hidden="true"></div>

<section class="v-psec">
  <div class="wrap v-prose">
    <h2><?= v2_te('Photographs') ?></h2>
    <p><?= v2_t('Photos of attractions and cities come from <a href="{url}" target="_blank" rel="noopener">Wikimedia Commons</a>, each under the free licence its author chose (most often Creative Commons Attribution or Attribution-ShareAlike). We show them unchanged apart from resizing and cropping to fit the page.', ['url' => 'https://commons.wikimedia.org']) ?></p>
    <ul>
      <li><?= v2_t('<strong>On the page of an attraction</strong> the main photo carries the author\'s name, the licence with a link to its text, and a link to the original file on Commons. Photos of the gallery are credited the same way under the gallery.') ?></li>
      <li><?= v2_t('<strong>On the page of a city or a country</strong> the credit is under the photo at the top of the page.') ?></li>
      <li><?= v2_t('<strong>In lists, on the map and in the trip planner</strong> photos are shown small and lead to the page of the attraction, where the full credit is. In lists, pointing at a photo also shows who took it.') ?></li>
    </ul>
    <p><?= v2_te('Photos uploaded by venues and operators for their own listings belong to them and are used with their permission.') ?></p>

    <h2><?= v2_te('Text') ?></h2>
    <p><?= v2_te('Where the description of an attraction is the introduction of its Wikipedia article, the page says so under the text, with a link to the article and to the licence (Creative Commons Attribution-ShareAlike 4.0).') ?></p>

    <h2><?= v2_te('Place data') ?></h2>
    <ul>
      <li><?= v2_t('Attractions, their types, dates, architects and visitor figures: <a href="{url}" target="_blank" rel="noopener">Wikidata</a> (CC0).', ['url' => 'https://www.wikidata.org']) ?></li>
      <li><?= v2_t('Cities, regions and countries: <a href="{url}" target="_blank" rel="noopener">GeoNames</a> (CC BY 4.0).', ['url' => 'https://www.geonames.org']) ?></li>
      <li><?= v2_t('Opening hours, roads and driving distances: © <a href="{url}" target="_blank" rel="noopener">OpenStreetMap</a> contributors (ODbL).', ['url' => 'https://www.openstreetmap.org/copyright']) ?></li>
      <li><?= v2_t('Region outlines on country pages: <a href="{url}" target="_blank" rel="noopener">Natural Earth</a> (public domain).', ['url' => 'https://www.naturalearthdata.com']) ?></li>
      <li><?= v2_t('Flags: <a href="{url}" target="_blank" rel="noopener">flag-icons</a> (MIT).', ['url' => 'https://github.com/lipis/flag-icons']) ?></li>
    </ul>

    <h2><?= v2_te('Something wrong?') ?></h2>
    <p><?= v2_t('If a photo of yours is credited wrongly, or you would rather it were not used here, write to <a href="mailto:{email}">{email}</a> with the address of the page. We correct or remove it without delay. The same goes for a fact that is out of date: opening hours and visitor figures come from open data and are worth checking with the place itself before you travel.', ['email' => v2_e($pcEmail)]) ?></p>
  </div>
</section>
</main>

<?php include __DIR__ . '/includes/v2/footer.php'; ?>
