<?php
/**
 * 404 — page not found (v2 "Arcada").
 *
 * Required directly by the pages whose slug doesn't resolve (city.php, category.php, city-intent.php, atractie.php,
 * single-activitate.php, locatie.php, ghid.php, slug.php, activitate.php). The including page may already have set
 * its own v2 variables, so every one of them is reset here before the shell renders.
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/api.php';
require_once __DIR__ . '/includes/nav-helpers.php';
require_once __DIR__ . '/includes/v2/helpers.php';
require_once __DIR__ . '/includes/v2/nav.php';

if (!headers_sent()) {
    http_response_code(404);
}
$skipPageCache = true;

$pageTitleRaw = 'Pagina nu a fost găsită · bilete.online';
$pageDescription = 'Pagina pe care o cauți nu există sau a fost mutată. Caută în alte categorii sau orașe.';
$canonicalUrl = SITE_URL . '/404';
$noindex = true;
$currentPage = '404';
$ogImage = null;
$structuredData = [];
$v2Styles = ['notfound.css'];
$v2Scripts = [];
$v2LegacyScripts = [];
$v2HeaderOverlay = false;
$v2BodyClass = '';
$v2HeadExtra = '';
$v2ClientData = [];

// Top categories + cities for recovery suggestions
$topCats = array_slice($V2NAV['categories'] ?? [], 0, 6);
$topCities = array_slice($V2NAV['citiesList'] ?? [], 0, 8);

include __DIR__ . '/includes/v2/head.php';
include __DIR__ . '/includes/v2/header.php';
?>
<main id="main" class="page-main" tabindex="-1">
  <section class="nf" aria-labelledby="nf-h">
    <div class="wrap nf-grid">
      <div class="nf-text">
        <p class="kicker">404 · Not found</p>
        <h1 class="nf-h" id="nf-h">Pagina <em>nu e</em><br>pe hartă.</h1>
        <p class="nf-lead">Linkul pe care ai venit nu mai duce nicăieri sau a fost mutat. Hai să te aducem înapoi la ce vrei să faci.</p>

        <form class="nf-search" role="search" action="/cauta" method="get">
          <?= v2_ic('magnifying-glass') ?>
          <label class="sr" for="nf-q">Caută pe bilete.online</label>
          <input id="nf-q" name="q" type="search" placeholder="Caută o activitate, o atracție sau un oraș" autocomplete="off">
          <button class="btn btn-primary" type="submit">Caută</button>
        </form>

        <div class="nf-cta">
          <a class="btn btn-primary" href="/"><?= v2_ic('arrow-left') ?>Înapoi la homepage</a>
          <a class="btn btn-ghost" href="/categorii">Vezi toate categoriile</a>
        </div>
      </div>

      <div class="nf-art" aria-hidden="true">
        <div class="nf-ticket">
          <div class="nf-ticket-top">
            <span class="nf-stamp">Void</span>
            <span class="nf-num">404</span>
            <svg class="nf-line" viewBox="1455 585 1210 310"><use href="#drum-g"/></svg>
          </div>
          <div class="nf-ticket-body">
            <p class="nf-ticket-k">Bilet invalid</p>
            <p class="nf-ticket-t">Acces respins</p>
            <p class="nf-ticket-f"><span>QR illegible</span><b><?= v2_ic('x') ?></b></p>
          </div>
        </div>
      </div>
    </div>
  </section>

  <?php if ($topCats || $topCities): ?>
  <section class="nf-more" aria-label="Sugestii">
    <div class="wrap nf-more-grid">
      <?php if ($topCats): ?>
      <div>
        <p class="flabel">Poate căutai</p>
        <div class="chips-links"><?php foreach ($topCats as $cat): ?><a href="<?= v2_e($cat['href']) ?>"><?= v2_e($cat['name']) ?></a><?php endforeach; ?></div>
      </div>
      <?php endif; ?>
      <?php if ($topCities): ?>
      <div>
        <p class="flabel">Sau alege un oraș</p>
        <div class="chips-links"><?php foreach ($topCities as $c): ?><a href="<?= v2_e($c['href']) ?>"><?= v2_e($c['name']) ?></a><?php endforeach; ?></div>
      </div>
      <?php endif; ?>
    </div>
  </section>
  <?php endif; ?>
</main>

<?php include __DIR__ . '/includes/v2/footer.php'; ?>
