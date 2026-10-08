<?php
/**
 * Venue report: /organizator/locatie/raport (venue-report.php), v2 design.
 *
 * Sixth screen of the "Locație" section, ported from Ambilet: the accounting view of a period. The totals and the
 * commission formula in force; what each issuing company took (gross, net, VAT when it pays VAT, commission, by
 * payment method); by payment method; online against the counter; by POS operator; by ticket type with its issuer;
 * the physical tickets issued; and the scans of a thirty-day window, each day openable into every scan it had. The
 * report exports as CSV from what is loaded.
 *
 * org-venue-report.js reads /organizer/events, then .../leisure/raport, .../leisure/scans and .../leisure/scans-detail.
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/api.php';
require_once __DIR__ . '/../includes/nav-helpers.php';
require_once __DIR__ . '/../includes/v2/helpers.php';
require_once __DIR__ . '/../includes/v2/organizer.php';

$pageTitleRaw = v2_t('Venue report: {site}', ['site' => SITE_NAME]);
$pageDescription = v2_t('The venue\'s accounting report: by company, payment method, staff member, ticket type and scans.');
$canonicalUrl = SITE_URL . '/organizator/locatie/raport';
$noindex = true;
$skipPageCache = true;

$v2Styles = ['organizer.css', 'org-venue.css'];
$v2Scripts = ['organizer.js', 'org-venue-report.js'];
$v2LegacyScripts = ['assets/js/config.js', 'assets/js/utils.js', 'assets/js/api.js', 'assets/js/auth.js'];
$v2HeadExtra = v2_account_client_config('organizer');

$vrKpi = function (string $id, string $icon, string $label) {
    return '<article class="ve-kpi"><span class="ve-kpi-ic">' . v2_ic($icon) . '</span><div><b id="vr-' . $id . '">—</b><p>' . $label . '</p></div></article>';
};

include __DIR__ . '/../includes/v2/head.php';
v2_org_start('venue-report');
?>
<div class="ve" id="ve">
  <header class="ve-head">
    <div>
      <p class="ve-eyebrow"><?= v2_ic('file-text') ?><?= v2_te('Venue · Report') ?></p>
      <h1 class="ve-h"><?= v2_te('Report') ?></h1>
      <p class="ve-lead"><?= v2_te('The period by company, payment method, staff member and ticket type, plus the scans at the gates.') ?></p>
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
    <b><?= v2_te('We could not load the report') ?></b>
    <p><?= v2_te('Try again in a few seconds.') ?></p>
    <button class="btn btn-primary" type="button" id="ve-retry"><?= v2_te('Try again') ?></button>
  </div>

  <div class="ve-main" id="ve-main" hidden>
    <section class="org-panel" aria-labelledby="vr-filters-h">
      <h2 class="ve-sr" id="vr-filters-h"><?= v2_te('Period') ?></h2>
      <div class="ve-filters">
        <div class="ve-ranges" role="group" aria-label="<?= v2_te('Payment period') ?>">
          <?php foreach ([['7', v2_t('Last 7 days')], ['14', v2_t('14 days')], ['30', v2_t('One month')], ['90', v2_t('3 months')], ['180', v2_t('6 months')], ['custom', v2_t('Custom period')]] as [$val, $label]): ?>
          <button class="ve-range" type="button" data-range="<?= $val ?>" aria-pressed="<?= $val === '30' ? 'true' : 'false' ?>"><?= v2_e($label) ?></button>
          <?php endforeach; ?>
        </div>
        <div class="ve-dates" id="vr-custom" hidden>
          <span class="po-field"><label for="vr-from"><?= v2_te('From') ?></label><input class="po-input" type="date" id="vr-from"></span>
          <span class="po-field"><label for="vr-to"><?= v2_te('To') ?></label><input class="po-input" type="date" id="vr-to"></span>
          <button class="btn btn-ghost" type="button" id="vr-apply"><?= v2_te('Apply') ?></button>
        </div>
        <div class="ve-tools">
          <button class="btn btn-ghost" type="button" id="vr-csv"><?= v2_ic('download-simple') ?><?= v2_te('Export CSV') ?></button>
          <span class="ve-sub" id="vr-period">—</span>
        </div>
      </div>
    </section>

    <section class="ve-money" aria-label="<?= v2_te('Totals') ?>">
      <article class="ve-mcard is-gross"><p><?= v2_te('Total revenue') ?></p><b id="vr-revenue">—</b><p class="ve-mnote" id="vr-avg-line">—</p></article>
      <article class="ve-mcard is-fee"><p><?= v2_te('Ticketing commission') ?></p><b id="vr-commission">—</b><p class="ve-mnote" id="vr-formula">—</p></article>
      <article class="ve-mcard is-net"><p><?= v2_te('Net revenue') ?></p><b id="vr-net">—</b><p class="ve-mnote"><?= v2_te('after commission') ?></p></article>
    </section>

    <section class="ve-kpis is-4" aria-label="<?= v2_te('Volumes') ?>">
      <?= $vrKpi('orders', 'receipt', v2_te('Orders')) ?>
      <?= $vrKpi('tickets', 'ticket', v2_te('Tickets sold (a package = one)')) ?>
      <?= $vrKpi('physical-kpi', 'scan', v2_te('Physical tickets issued')) ?>
      <?= $vrKpi('avg', 'shopping-cart-simple', v2_te('Average basket')) ?>
    </section>

    <section class="ve-issuers" id="vr-issuers" aria-label="<?= v2_te('By issuing company') ?>"></section>

    <div class="ve-grid ve-grid-even">
      <section class="org-panel" aria-labelledby="vr-pay-h">
        <div class="org-panel-head"><div><h2 class="org-panel-h" id="vr-pay-h"><?= v2_te('By payment method') ?></h2><p class="org-panel-p"><?= v2_te('Cash and card at the register, online.') ?></p></div></div>
        <ul class="ve-brows" id="vr-pay"><li class="ve-state"><?= v2_te('Loading…') ?></li></ul>
      </section>
      <section class="org-panel" aria-labelledby="vr-src-h">
        <div class="org-panel-head"><div><h2 class="org-panel-h" id="vr-src-h"><?= v2_te('Online or at the register') ?></h2><p class="org-panel-p"><?= v2_te('Where the sales came from.') ?></p></div></div>
        <ul class="ve-brows" id="vr-sources"><li class="ve-state"><?= v2_te('Loading…') ?></li></ul>
      </section>
    </div>

    <section class="org-panel" aria-labelledby="vr-cash-h">
      <div class="org-panel-head"><div><h2 class="org-panel-h" id="vr-cash-h"><?= v2_te('By staff member') ?></h2><p class="org-panel-p"><?= v2_te('Who sold at the register, with the orders, tickets and revenue of each.') ?></p></div></div>
      <div class="ve-table-wrap"><table class="ve-table ve-cashier-table">
        <thead><tr><th scope="col"><?= v2_te('Staff') ?></th><th scope="col" class="ve-r"><?= v2_te('Orders') ?></th><th scope="col" class="ve-r"><?= v2_te('Tickets') ?></th><th scope="col" class="ve-r"><?= v2_te('Revenue') ?></th><th scope="col" class="ve-r"><?= v2_te('Commission') ?></th></tr></thead>
        <tbody id="vr-cashiers"><tr><td colspan="5" class="ve-state"><?= v2_te('Loading…') ?></td></tr></tbody>
      </table></div>
    </section>

    <section class="org-panel" aria-labelledby="vr-types-h">
      <div class="org-panel-head"><div><h2 class="org-panel-h" id="vr-types-h"><?= v2_te('By ticket type') ?></h2><p class="org-panel-p"><?= v2_te('The transactions: packages and tickets sold separately, with the company that issues them.') ?></p></div></div>
      <div class="ve-table-wrap"><table class="ve-table ve-report-table">
        <thead><tr><th scope="col"><?= v2_te('Ticket') ?></th><th scope="col"><?= v2_te('Category') ?></th><th scope="col"><?= v2_te('Issuer') ?></th><th scope="col" class="ve-r"><?= v2_te('Tickets') ?></th><th scope="col" class="ve-r"><?= v2_te('Revenue') ?></th><th scope="col" class="ve-r"><?= v2_te('Commission') ?></th></tr></thead>
        <tbody id="vr-types"><tr><td colspan="6" class="ve-state"><?= v2_te('Loading…') ?></td></tr></tbody>
      </table></div>
    </section>

    <section class="org-panel" aria-labelledby="vr-comp-h">
      <div class="org-panel-head"><div><h2 class="org-panel-h" id="vr-comp-h"><?= v2_te('Physical tickets issued') ?></h2><p class="org-panel-p"><?= v2_t('What was actually printed or sent: package components and single tickets, without the package ticket itself. In total: {n}.', ['n' => '<b id="vr-physical">—</b>']) ?></p></div></div>
      <div class="ve-table-wrap"><table class="ve-table">
        <thead><tr><th scope="col"><?= v2_te('Ticket') ?></th><th scope="col"><?= v2_te('Category') ?></th><th scope="col" class="ve-r"><?= v2_te('Quantity') ?></th></tr></thead>
        <tbody id="vr-comps"><tr><td colspan="3" class="ve-state"><?= v2_te('Loading…') ?></td></tr></tbody>
      </table></div>
    </section>

    <section class="org-panel" aria-labelledby="vr-scan-h">
      <div class="org-panel-head">
        <div><h2 class="org-panel-h" id="vr-scan-h"><?= v2_te('Scans') ?></h2><p class="org-panel-p"><?= v2_t('{range}. Press a day to see every scan.', ['range' => '<span id="vr-scan-range">—</span>']) ?></p></div>
        <span class="ve-weeknav">
          <button class="btn btn-ghost" type="button" id="vr-scan-prev" aria-label="<?= v2_te('30 days back') ?>"><?= v2_ic('arrow-left') ?></button>
          <button class="btn btn-ghost" type="button" id="vr-scan-today"><?= v2_te('Today in the middle') ?></button>
          <button class="btn btn-ghost" type="button" id="vr-scan-next" aria-label="<?= v2_te('30 days forward') ?>"><?= v2_ic('arrow-right') ?></button>
        </span>
      </div>
      <p class="ve-legend ve-scan-legend"><span class="ve-sl is-exp"><?= v2_te('Expected') ?></span><span class="ve-sl is-valid"><?= v2_te('Valid tickets') ?></span><span class="ve-sl is-staff"><?= v2_te('Staff') ?></span><span class="ve-sl is-bad"><?= v2_te('Refused') ?></span></p>
      <div class="ve-chart ve-scan-chart" id="vr-scan-chart"></div>
      <p class="ve-count" id="vr-scan-totals"></p>
    </section>
  </div>

  <div class="ve-modal" id="vr-modal" role="dialog" aria-modal="true" aria-labelledby="vr-modal-h" hidden>
    <div class="ve-modal-card ve-modal-wide">
      <div class="ve-modal-head ve-modal-plain"><h2 id="vr-modal-h"><?= v2_te('The day\'s scans') ?></h2><p class="ve-sub" id="vr-modal-totals">—</p></div>
      <div class="ve-modal-body">
        <div class="ve-table-wrap"><table class="ve-table ve-scan-table">
          <thead><tr><th scope="col"><?= v2_te('Time') ?></th><th scope="col"><?= v2_te('Who') ?></th><th scope="col"><?= v2_te('Result') ?></th><th scope="col"><?= v2_te('Code') ?></th><th scope="col"><?= v2_te('Email') ?></th><th scope="col"><?= v2_te('Detail') ?></th></tr></thead>
          <tbody id="vr-modal-rows"><tr><td colspan="6" class="ve-state"><?= v2_te('Loading…') ?></td></tr></tbody>
        </table></div>
      </div>
      <div class="ve-modal-foot"><button class="btn btn-ghost" type="button" id="vr-modal-close"><?= v2_te('Close') ?></button></div>
    </div>
  </div>
</div>
<?php
v2_org_end();
include __DIR__ . '/../includes/v2/foot.php';
