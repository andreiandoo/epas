<?php
/**
 * The operator's sales report: /organizator/raport (activities module), v2 design.
 *
 * For a period (today, 7 or 30 days, this or last month, this year, or chosen dates) and one or all locations: the
 * bookings, persons, sales, the bilete.online commission and what the operator keeps, day by day (bars and a table)
 * and product by product, all by payment date, online and at the desk together. The bookings of the period can be
 * downloaded as CSV (by visit date).
 *
 * org-am-report.js reads /organizer/activities-module/summary and .../locations and downloads .../bookings/export.
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/api.php';
require_once __DIR__ . '/../includes/nav-helpers.php';
require_once __DIR__ . '/../includes/v2/helpers.php';
require_once __DIR__ . '/../includes/v2/organizer.php';
require_once __DIR__ . '/../includes/v2/am-labels.php';

$pageTitleRaw = 'Raport — ' . SITE_NAME;
$pageDescription = 'Vânzările locațiilor tale pe o perioadă: rezervări, persoane, încasări, comision și ce îți rămâne, pe zile și pe produse.';
$canonicalUrl = SITE_URL . '/organizator/raport';
$noindex = true;
$skipPageCache = true;

$v2Styles = ['organizer.css', 'org-venue.css', 'org-am.css'];
$v2Scripts = ['organizer.js', 'org-am.js', 'org-am-report.js'];
$v2LegacyScripts = ['assets/js/config.js', 'assets/js/utils.js', 'assets/js/api.js', 'assets/js/auth.js'];
$v2HeadExtra = v2_account_client_config('organizer');
$v2ClientData = ['am' => am_client_labels()];

include __DIR__ . '/../includes/v2/head.php';
v2_org_start('am-report');
?>
<div class="ve am" id="am-rep">
  <header class="ve-head">
    <div>
      <p class="ve-eyebrow"><?= v2_ic('chart-line-up') ?>Raport</p>
      <h1 class="ve-h">Raport vânzări</h1>
      <p class="ve-lead">Ce ai vândut într-o perioadă, online și la casă, după data plății.</p>
    </div>
    <button class="btn btn-ghost" type="button" id="rep-export"><?= v2_ic('file-text') ?>Exportă rezervările (CSV)</button>
  </header>

  <form class="am-filters" id="rep-form">
    <div class="fchips" id="rep-presets" role="group" aria-label="Perioada">
      <button class="fchip" type="button" data-preset="today">Azi</button>
      <button class="fchip" type="button" data-preset="7">7 zile</button>
      <button class="fchip" type="button" data-preset="30">30 de zile</button>
      <button class="fchip" type="button" data-preset="month">Luna aceasta</button>
      <button class="fchip" type="button" data-preset="last-month">Luna trecută</button>
      <button class="fchip" type="button" data-preset="year">Anul acesta</button>
    </div>
    <span class="po-field"><label for="rep-from">De la</label><input class="po-input" type="date" id="rep-from"></span>
    <span class="po-field"><label for="rep-to">Până la</label><input class="po-input" type="date" id="rep-to"></span>
    <span class="po-field"><label for="rep-loc">Locația</label><span class="po-select"><select id="rep-loc"><option value="">Toate locațiile</option></select><?= v2_ic('caret-down') ?></span></span>
    <button class="btn btn-primary" type="submit">Arată</button>
  </form>

  <section class="am-kpis" id="rep-kpis" aria-label="Totaluri"></section>

  <section class="org-panel am-stack" aria-labelledby="rep-days-h">
    <div class="org-panel-head"><div><h2 class="org-panel-h" id="rep-days-h">Pe zile</h2><p class="org-panel-p" id="rep-days-p"></p></div></div>
    <div class="rep-bars" id="rep-bars" aria-hidden="true"></div>
    <div class="ve-table-wrap"><table class="ve-table am-table">
      <thead><tr><th scope="col">Ziua</th><th scope="col">Rezervări</th><th scope="col">Vânzări</th><th scope="col">Îți rămân</th></tr></thead>
      <tbody id="rep-days"></tbody>
    </table></div>
  </section>

  <section class="org-panel" aria-labelledby="rep-prod-h">
    <div class="org-panel-head"><div><h2 class="org-panel-h" id="rep-prod-h">Pe produse</h2></div></div>
    <div class="ve-table-wrap"><table class="ve-table am-table">
      <thead><tr><th scope="col">Produsul</th><th scope="col">Rezervări</th><th scope="col">Persoane</th><th scope="col">Vânzări</th><th scope="col">Îți rămân</th></tr></thead>
      <tbody id="rep-products"></tbody>
    </table></div>
  </section>
</div>
<?php
v2_org_end();
include __DIR__ . '/../includes/v2/foot.php';
