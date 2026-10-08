<?php
/**
 * Venue sales: /organizator/locatie/vanzari (venue-sales.php), v2 design.
 *
 * Fifth screen of the "Locație" section, ported from Ambilet: what the venue sold over a period, online and at the
 * counter. Gross, the ticketing commission and what is left, each split by channel; orders, the average basket, the
 * tickets issued and their categories; cash, card and online takings; the sales over time; each ticket type; the
 * cash sessions of the period; and the company invoices asked for at the counter. The per-ticket CSV comes from core.
 * Ambilet sent the sessions and the invoices to two pages of their own; here they are panels of this one.
 *
 * org-venue-sales.js reads /organizer/events, then .../leisure/sales-timeline and .../leisure/sales/summary for the
 * period, .../leisure/invoices for the panel, and downloads .../leisure/sales/range-csv.
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/api.php';
require_once __DIR__ . '/../includes/nav-helpers.php';
require_once __DIR__ . '/../includes/v2/helpers.php';
require_once __DIR__ . '/../includes/v2/organizer.php';

$pageTitleRaw = v2_t('Venue sales: {site}', ['site' => SITE_NAME]);
$pageDescription = v2_t('The venue\'s sales by period: gross, commission and net, online and at the register, by ticket type and register session.');
$canonicalUrl = SITE_URL . '/organizator/locatie/vanzari';
$noindex = true;
$skipPageCache = true;

$v2Styles = ['organizer.css', 'org-venue.css'];
$v2Scripts = ['organizer.js', 'org-venue-sales.js'];
$v2LegacyScripts = ['assets/js/config.js', 'assets/js/utils.js', 'assets/js/api.js', 'assets/js/auth.js'];
$v2HeadExtra = v2_account_client_config('organizer');

$vsSplit = function (string $key) {
    return '<dl class="ve-split"><div><dt>' . v2_ic('globe-simple') . v2_te('Online') . '</dt><dd id="vs-' . $key . '-online">—</dd></div>'
        . '<div><dt>' . v2_ic('coins') . v2_te('At the register') . '</dt><dd id="vs-' . $key . '-pos">—</dd></div></dl>';
};
$vsPay = function (string $key, string $icon, string $label) {
    return '<article class="ve-kpi"><span class="ve-kpi-ic">' . v2_ic($icon) . '</span><div><b id="vs-pay-' . $key . '">—</b><p>' . $label
        . '</p><small>' . v2_t('{pct} of revenue', ['pct' => '<span id="vs-pay-' . $key . '-pct">—</span>']) . '</small></div></article>';
};

include __DIR__ . '/../includes/v2/head.php';
v2_org_start('venue-sales');
?>
<div class="ve" id="ve">
  <header class="ve-head">
    <div>
      <p class="ve-eyebrow"><?= v2_ic('chart-line-up') ?><?= v2_te('Venue · Sales') ?></p>
      <h1 class="ve-h"><?= v2_te('Sales') ?></h1>
      <p class="ve-lead"><?= v2_te('What the venue sold in a period, on the site and at the register: how much came in, how much is left after commission and from what.') ?></p>
    </div>
    <div class="ve-head-tools">
      <span class="po-field"><label for="ve-event"><?= v2_te('Venue') ?></label><span class="po-select"><select id="ve-event" disabled><option><?= v2_te('Loading…') ?></option></select><?= v2_ic('caret-down') ?></span></span>
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
    <b><?= v2_te('We could not load the sales') ?></b>
    <p><?= v2_te('Try again in a few seconds.') ?></p>
    <button class="btn btn-primary" type="button" id="ve-retry"><?= v2_te('Try again') ?></button>
  </div>

  <div class="ve-main" id="ve-main" hidden>
    <section class="org-panel" aria-labelledby="vs-filters-h">
      <h2 class="ve-sr" id="vs-filters-h"><?= v2_te('Period') ?></h2>
      <div class="ve-filters">
        <div class="ve-ranges" role="group" aria-label="<?= v2_te('Payment period') ?>">
          <?php foreach ([['7', v2_t('Last 7 days')], ['14', v2_t('14 days')], ['30', v2_t('One month')], ['90', v2_t('3 months')], ['180', v2_t('6 months')], ['custom', v2_t('Custom period')]] as [$val, $label]): ?>
          <button class="ve-range" type="button" data-range="<?= $val ?>" aria-pressed="<?= $val === '7' ? 'true' : 'false' ?>"><?= v2_e($label) ?></button>
          <?php endforeach; ?>
        </div>
        <div class="ve-dates" id="vs-custom" hidden>
          <span class="po-field"><label for="vs-from"><?= v2_te('From') ?></label><input class="po-input" type="date" id="vs-from"></span>
          <span class="po-field"><label for="vs-to"><?= v2_te('To') ?></label><input class="po-input" type="date" id="vs-to"></span>
          <button class="btn btn-ghost" type="button" id="vs-apply"><?= v2_te('Apply') ?></button>
        </div>
        <div class="ve-tools">
          <span class="po-select"><select id="vs-group" aria-label="<?= v2_te('Chart by') ?>"><option value="day"><?= v2_te('Chart by day') ?></option><option value="week"><?= v2_te('Chart by week') ?></option><option value="month"><?= v2_te('Chart by month') ?></option></select><?= v2_ic('caret-down') ?></span>
          <button class="btn btn-ghost" type="button" id="vs-csv"><?= v2_ic('download-simple') ?><?= v2_te('Export CSV by ticket') ?></button>
          <span class="ve-sub" id="vs-period">—</span>
        </div>
      </div>
    </section>

    <section class="ve-money" aria-label="<?= v2_te('Money') ?>">
      <article class="ve-mcard is-gross"><p><?= v2_te('Total sold') ?></p><b id="vs-rev">—</b><?= $vsSplit('rev') ?></article>
      <article class="ve-mcard is-fee"><p><?= v2_te('Ticketing commission') ?></p><b id="vs-comm">—</b><?= $vsSplit('comm') ?></article>
      <article class="ve-mcard is-net"><p><?= v2_te('Left after commission') ?></p><b id="vs-net">—</b><?= $vsSplit('net') ?></article>
    </section>

    <section class="ve-kpis is-4" aria-label="<?= v2_te('Orders and tickets') ?>">
      <article class="ve-kpi"><span class="ve-kpi-ic"><?= v2_ic('receipt') ?></span><div><b id="vs-orders">—</b><p><?= v2_te('Paid orders') ?></p></div></article>
      <article class="ve-kpi"><span class="ve-kpi-ic"><?= v2_ic('shopping-cart-simple') ?></span><div><b id="vs-avg">—</b><p><?= v2_te('Average basket') ?></p></div></article>
      <article class="ve-kpi"><span class="ve-kpi-ic"><?= v2_ic('ticket') ?></span><div><b id="vs-physical">—</b><p><?= v2_te('Tickets issued') ?></p><small><?= v2_t('{n} with a value (a package = one)', ['n' => '<span id="vs-transactions">—</span>']) ?></small></div></article>
      <article class="ve-kpi is-wide"><div><p><?= v2_te('By category') ?></p><ul class="ve-catlist" id="vs-cats"><li><span>—</span></li></ul></div></article>
    </section>

    <section class="ve-kpis is-4" aria-label="<?= v2_te('How it was paid') ?>">
      <?= $vsPay('cash', 'coins', v2_te('Cash at the register')) ?>
      <?= $vsPay('card', 'credit-card', v2_te('Card at the register')) ?>
      <?= $vsPay('online', 'globe-simple', v2_te('Online')) ?>
      <article class="ve-kpi"><span class="ve-kpi-ic"><?= v2_ic('clock') ?></span><div><b id="vs-sessions-n">—</b><p><?= v2_te('Register sessions') ?></p><small><a href="#vs-sessions-h"><?= v2_te('see the shifts') ?></a></small></div></article>
    </section>

    <div class="ve-grid">
      <section class="org-panel" aria-labelledby="vs-chart-h">
        <div class="org-panel-head">
          <div><h2 class="org-panel-h" id="vs-chart-h"><?= v2_te('Sales over time') ?></h2><p class="org-panel-p"><?= v2_te('The line shows revenue, the bars show tickets with a value.') ?></p></div>
          <p class="ve-legend"><span class="ve-lg"><?= v2_te('Revenue') ?></span><span class="ve-lg is-vis"><?= v2_te('Tickets') ?></span></p>
        </div>
        <div class="ve-chart" id="vs-chart"></div>
        <p class="ve-state" id="vs-chart-empty" hidden><?= v2_te('No sales in the chosen period.') ?></p>
      </section>
      <section class="org-panel" aria-labelledby="vs-types-h">
        <div class="org-panel-head"><div><h2 class="org-panel-h" id="vs-types-h"><?= v2_te('By ticket type') ?></h2><p class="org-panel-p"><?= v2_te('Tickets with a value, and revenue.') ?></p></div></div>
        <ul class="ve-types-bars" id="vs-types"><li class="ve-state"><?= v2_te('Loading…') ?></li></ul>
      </section>
    </div>

    <section class="org-panel" aria-labelledby="vs-sessions-h">
      <div class="org-panel-head"><div><h2 class="org-panel-h" id="vs-sessions-h"><?= v2_te('Register sessions') ?></h2><p class="org-panel-p"><?= v2_te('The shifts opened or closed in the period, with what each one took.') ?></p></div></div>
      <div class="ve-table-wrap"><table class="ve-table ve-sessions-table">
        <thead><tr>
          <th scope="col"><?= v2_te('Cashier') ?></th><th scope="col"><?= v2_te('Opened') ?></th><th scope="col"><?= v2_te('Closed') ?></th>
          <th scope="col" class="ve-r"><?= v2_te('Cash') ?></th><th scope="col" class="ve-r"><?= v2_te('Card') ?></th><th scope="col" class="ve-r"><?= v2_te('Orders') ?></th>
          <th scope="col" class="ve-r"><?= v2_te('Tickets') ?></th><th scope="col" class="ve-r"><?= v2_te('Revenue') ?></th>
        </tr></thead>
        <tbody id="vs-sessions"><tr><td colspan="8" class="ve-state"><?= v2_te('Loading…') ?></td></tr></tbody>
      </table></div>
    </section>

    <section class="org-panel ve-hist" aria-labelledby="vs-inv-h">
      <div class="org-panel-head">
        <div><h2 class="org-panel-h" id="vs-inv-h"><?= v2_ic('buildings') ?> <?= v2_te('Company invoices') ?></h2>
        <p class="org-panel-p"><?= v2_te('Orders at the register for which an invoice was requested or issued, in the same period.') ?></p></div>
        <button class="btn btn-ghost ve-hist-btn" type="button" id="vs-inv-btn" aria-expanded="false" aria-controls="vs-inv-body"><?= v2_te('Show invoices') ?><?= v2_ic('caret-down') ?></button>
      </div>
      <div id="vs-inv-body" hidden>
        <div class="ve-hist-row">
          <label class="ve-search"><?= v2_ic('magnifying-glass') ?><input id="vs-inv-q" type="search" autocomplete="off" placeholder="<?= v2_te('Company, tax ID, invoice or order number') ?>" aria-label="<?= v2_te('Search invoices') ?>"></label>
        </div>
        <div class="ve-table-wrap"><table class="ve-table ve-inv-table">
          <thead><tr>
            <th scope="col"><?= v2_te('Order') ?></th><th scope="col"><?= v2_te('Invoice') ?></th><th scope="col"><?= v2_te('Company') ?></th><th scope="col"><?= v2_te('Contact') ?></th>
            <th scope="col"><?= v2_te('Payment') ?></th><th scope="col" class="ve-r"><?= v2_te('Tickets') ?></th><th scope="col" class="ve-r"><?= v2_te('Total') ?></th>
          </tr></thead>
          <tbody id="vs-inv"><tr><td colspan="7" class="ve-state"><?= v2_te('Loading…') ?></td></tr></tbody>
        </table></div>
        <div class="ve-pager">
          <span id="vs-inv-count"></span>
          <span class="ve-pg">
            <button type="button" id="vs-inv-prev" disabled><?= v2_ic('arrow-left') ?><?= v2_te('Previous') ?></button>
            <button type="button" id="vs-inv-next" disabled><?= v2_te('Next') ?><?= v2_ic('arrow-right') ?></button>
          </span>
        </div>
      </div>
    </section>
  </div>
</div>
<?php
v2_org_end();
include __DIR__ . '/../includes/v2/foot.php';
