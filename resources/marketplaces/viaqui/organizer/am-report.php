<?php
/**
 * The operator's sales report: /organizator/raport (activities module), v2 design.
 *
 * For a period (today, 7 or 30 days, this or last month, this year, or chosen dates) and one or all locations: the
 * bookings, persons, sales, the viaqui.com commission and what the operator keeps, day by day (bars and a table)
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

$pageTitleRaw = v2_t('Report: {site}', ['site' => SITE_NAME]);
$pageDescription = v2_t('The sales of your venues over a period: bookings, people, takings, commission and what you keep, by day and by product.');
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
      <p class="ve-eyebrow"><?= v2_ic('chart-line-up') ?><?= v2_te('Report') ?></p>
      <h1 class="ve-h"><?= v2_te('Sales report') ?></h1>
      <p class="ve-lead"><?= v2_te('What you sold over a period, online and at the counter, by payment date.') ?></p>
    </div>
    <button class="btn btn-ghost" type="button" id="rep-export"><?= v2_ic('file-text') ?><?= v2_te('Export the bookings (CSV)') ?></button>
  </header>

  <form class="am-filters" id="rep-form">
    <div class="fchips" id="rep-presets" role="group" aria-label="<?= v2_te('Period') ?>">
      <button class="fchip" type="button" data-preset="today"><?= v2_te('Today') ?></button>
      <button class="fchip" type="button" data-preset="7"><?= v2_te('7 days') ?></button>
      <button class="fchip" type="button" data-preset="30"><?= v2_te('30 days') ?></button>
      <button class="fchip" type="button" data-preset="month"><?= v2_te('This month') ?></button>
      <button class="fchip" type="button" data-preset="last-month"><?= v2_te('Last month') ?></button>
      <button class="fchip" type="button" data-preset="year"><?= v2_te('This year') ?></button>
    </div>
    <span class="po-field"><label for="rep-from"><?= v2_te('From') ?></label><input class="po-input" type="date" id="rep-from"></span>
    <span class="po-field"><label for="rep-to"><?= v2_te('Until') ?></label><input class="po-input" type="date" id="rep-to"></span>
    <span class="po-field"><label for="rep-loc"><?= v2_te('Venue') ?></label><span class="po-select"><select id="rep-loc"><option value=""><?= v2_te('All venues') ?></option></select><?= v2_ic('caret-down') ?></span></span>
    <button class="btn btn-primary" type="submit"><?= v2_te('Show') ?></button>
  </form>

  <section class="am-kpis" id="rep-kpis" aria-label="<?= v2_te('Totals') ?>"></section>

  <section class="org-panel am-stack" aria-labelledby="rep-days-h">
    <div class="org-panel-head"><div><h2 class="org-panel-h" id="rep-days-h"><?= v2_te('By day') ?></h2><p class="org-panel-p" id="rep-days-p"></p></div></div>
    <div class="rep-bars" id="rep-bars" aria-hidden="true"></div>
    <div class="ve-table-wrap"><table class="ve-table am-table">
      <thead><tr><th scope="col"><?= v2_te('Day') ?></th><th scope="col"><?= v2_te('Bookings') ?></th><th scope="col"><?= v2_te('Sales') ?></th><th scope="col"><?= v2_te('You keep') ?></th></tr></thead>
      <tbody id="rep-days"></tbody>
    </table></div>
  </section>

  <section class="org-panel" aria-labelledby="rep-prod-h">
    <div class="org-panel-head"><div><h2 class="org-panel-h" id="rep-prod-h"><?= v2_te('By product') ?></h2></div></div>
    <div class="ve-table-wrap"><table class="ve-table am-table">
      <thead><tr><th scope="col"><?= v2_te('Product') ?></th><th scope="col"><?= v2_te('Bookings') ?></th><th scope="col"><?= v2_te('People') ?></th><th scope="col"><?= v2_te('Sales') ?></th><th scope="col"><?= v2_te('You keep') ?></th></tr></thead>
      <tbody id="rep-products"></tbody>
    </table></div>
  </section>
</div>
<?php
v2_org_end();
include __DIR__ . '/../includes/v2/foot.php';
