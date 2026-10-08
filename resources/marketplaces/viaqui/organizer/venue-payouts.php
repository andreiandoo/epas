<?php
/**
 * Venue payouts: /organizator/locatie/deconturi (venue-payouts.php), v2 design.
 *
 * Seventh screen of the "Locație" section, ported from Ambilet. A venue is paid in two directions at once: online
 * sales are collected by viaqui.com, which owes the venue their net, while the counter takes cash and card itself
 * and owes the POS commission. The page shows the running totals, the settlement of a half-month (who pays whom, and
 * per issuing company), and every payout issued, grouped by period, each with its tickets, its invoices and their
 * lines, and the PDFs.
 *
 * org-venue-payouts.js reads /organizer/events, then .../leisure/settlement/cumulative, .../leisure/settlement and
 * .../leisure/payouts.
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/api.php';
require_once __DIR__ . '/../includes/nav-helpers.php';
require_once __DIR__ . '/../includes/v2/helpers.php';
require_once __DIR__ . '/../includes/v2/organizer.php';

$pageTitleRaw = v2_t('Venue payouts: {site}', ['site' => SITE_NAME]);
$pageDescription = v2_t('The venue\'s payouts: the settlement between online sales and sales at the register, the payouts issued and their invoices.');
$canonicalUrl = SITE_URL . '/organizator/locatie/deconturi';
$noindex = true;
$skipPageCache = true;

$v2Styles = ['organizer.css', 'org-venue.css'];
$v2Scripts = ['organizer.js', 'org-venue-payouts.js'];
$v2LegacyScripts = ['assets/js/config.js', 'assets/js/utils.js', 'assets/js/api.js', 'assets/js/auth.js'];
$v2HeadExtra = v2_account_client_config('organizer');

include __DIR__ . '/../includes/v2/head.php';
v2_org_start('venue-payouts');
?>
<div class="ve" id="ve">
  <header class="ve-head">
    <div>
      <p class="ve-eyebrow"><?= v2_ic('wallet') ?><?= v2_te('Venue · Payouts') ?></p>
      <h1 class="ve-h"><?= v2_te('Payouts') ?></h1>
      <p class="ve-lead"><?= v2_te('viaqui.com collects online sales, you collect at the register. Here you see who owes whom and every payout issued.') ?></p>
    </div>
    <div class="ve-head-tools">
      <span class="po-field"><label for="ve-event"><?= v2_te('Venue') ?></label><span class="po-select"><select id="ve-event" disabled><option><?= v2_te('Loading…') ?></option></select><?= v2_ic('caret-down') ?></span></span>
      <button class="btn btn-ghost" type="button" id="vd-refresh"><?= v2_ic('arrow-counter-clockwise') ?><?= v2_te('Refresh') ?></button>
    </div>
  </header>

  <div class="org-empty" id="ve-none" hidden>
    <span class="org-empty-ic"><?= v2_ic('door-open') ?></span>
    <b><?= v2_te('No venue set up yet') ?></b>
    <p><?= v2_te('Venue pages work on an experience set up as a venue.') ?></p>
    <a class="btn btn-primary" href="/organizator/suport"><?= v2_te('Ask for activation') ?><?= v2_ic('arrow-right') ?></a>
  </div>

  <div class="org-empty is-error" id="ve-failed" hidden>
    <span class="org-empty-ic"><?= v2_ic('warning-circle') ?></span>
    <b><?= v2_te('We could not load the payouts') ?></b>
    <p><?= v2_te('Try again in a few seconds.') ?></p>
    <button class="btn btn-primary" type="button" id="ve-retry"><?= v2_te('Try again') ?></button>
  </div>

  <div class="ve-main" id="ve-main" hidden>
    <section class="ve-kpis ve-kpis-5" aria-label="<?= v2_te('Running totals') ?>">
      <article class="ve-kpi"><span class="ve-kpi-ic"><?= v2_ic('calendar-blank') ?></span><div><b id="vd-days">—</b><p><?= v2_te('Days of sales') ?></p><small id="vd-since">—</small></div></article>
      <article class="ve-kpi"><span class="ve-kpi-ic"><?= v2_ic('globe-simple') ?></span><div><b id="vd-online">—</b><p><?= v2_te('Online sales') ?></p></div></article>
      <article class="ve-kpi"><span class="ve-kpi-ic"><?= v2_ic('coins') ?></span><div><b id="vd-pos">—</b><p><?= v2_te('Sales at the register') ?></p></div></article>
      <article class="ve-kpi"><span class="ve-kpi-ic"><?= v2_ic('percent') ?></span><div><b id="vd-online-comm">—</b><p><?= v2_te('Online commissions') ?></p></div></article>
      <article class="ve-kpi"><span class="ve-kpi-ic"><?= v2_ic('percent') ?></span><div><b id="vd-pos-comm">—</b><p><?= v2_te('Register commissions') ?></p></div></article>
    </section>

    <section class="org-panel" aria-labelledby="vd-settle-h">
      <div class="org-panel-head">
        <div><h2 class="org-panel-h" id="vd-settle-h"><?= v2_te('Settlement by period') ?></h2>
        <p class="org-panel-p"><?= v2_te('The online net owed to the venue is offset against the commission owed for sales at the register.') ?></p></div>
        <span class="po-field"><label for="vd-period"><?= v2_te('Period') ?></label><span class="po-select"><select id="vd-period"></select><?= v2_ic('caret-down') ?></span></span>
      </div>
      <div id="vd-settle"><p class="ve-state"><?= v2_te('Loading…') ?></p></div>
    </section>

    <section class="org-panel" aria-labelledby="vd-list-h">
      <div class="org-panel-head">
        <div><h2 class="org-panel-h" id="vd-list-h"><?= v2_te('Payouts issued') ?></h2>
        <p class="org-panel-p"><?= v2_te('By period. Open a period to see its payouts and invoices, then a payout to see its tickets.') ?></p></div>
      </div>
      <div class="ve-periods" id="vd-list"><p class="ve-state"><?= v2_te('Loading…') ?></p></div>
    </section>
  </div>
</div>
<?php
v2_org_end();
include __DIR__ . '/../includes/v2/foot.php';
