<?php
/**
 * Organizer activity report: /organizator/report/{id} (report.php?event={id}), v2 design.
 *
 * Inside the v2 organizer shell. One activity's report: net revenue, tickets sold, page views and conversion; sales
 * over time (net revenue per day with tickets) and how tickets split by type; ticket type performance; goals and
 * marketing campaigns; traffic sources and top visitor locations; refunds; the latest orders; the financial summary.
 * Print (a clean A4 layout) and PDF export. org-report.js reads /organizer/events/{id}/analytics, /goals, /milestones,
 * /organizer/orders (refunded + latest) and the PDF through the proxy.
 *
 * Fixed on the way:
 * - the refunds list was always "no refunds" (core sends only the total): the refunded orders are listed;
 * - the chart library came unpinned from a CDN (and without it the page had no charts): the charts are drawn here, with
 *   a tooltip, keyboard steps and an accessible summary; the date labels were English ("Sep 15");
 * - core spreads page views over the days in proportion to ticket sales, so they are not shown per day;
 * - with no activity in the address the page redirected to the dashboard: it now says so and links to the activities.
 *
 * Every text goes through v2_t() / v2_te() (org-report.js: VQ.t / VQ.n).
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/api.php';
require_once __DIR__ . '/../includes/nav-helpers.php';
require_once __DIR__ . '/../includes/v2/helpers.php';
require_once __DIR__ . '/../includes/v2/organizer.php';

$orEventId = isset($_GET['event']) && ctype_digit((string) $_GET['event']) ? (string) (int) $_GET['event'] : '';

$pageTitle = v2_t('Activity report');
$pageDescription = v2_t('The report of an activity on Viaqui: sales, tickets, traffic, goals, refunds and the financial summary.');
$canonicalUrl = SITE_URL . '/organizator/report' . ($orEventId !== '' ? '/' . $orEventId : '');
$noindex = true;
$skipPageCache = true;

$v2Styles = ['organizer.css', 'org-report.css'];
$v2Scripts = ['organizer.js', 'org-report.js'];
$v2LegacyScripts = ['assets/js/config.js', 'assets/js/utils.js', 'assets/js/api.js', 'assets/js/auth.js'];
$v2HeadExtra = v2_account_client_config('organizer');

/** A figure card. $label is HTML (already translated and escaped). */
$orStat = function (string $key, string $label) {
    return '<article class="or-stat"><p class="or-stat-k">' . $label . '</p><p class="or-stat-v" id="or-s-' . $key . '"><span class="org-skel or-sk"></span></p><p class="or-stat-p" id="or-s-' . $key . '-p"></p></article>';
};

include __DIR__ . '/../includes/v2/head.php';
v2_org_start('events');
?>
<div class="or" id="or" data-event="<?= htmlspecialchars($orEventId, ENT_QUOTES) ?>">
  <header class="or-head">
    <a class="or-back" href="/organizator/activities"><?= v2_ic('arrow-left') ?><?= v2_te('Back to activities') ?></a>
    <div class="or-head-row">
      <div class="or-head-t">
        <p class="org-k"><?= v2_te('Activity report') ?></p>
        <h1 class="or-h" id="or-title"><?= v2_te('Activity report') ?></h1>
        <p class="or-info" id="or-info"></p>
      </div>
      <div class="or-actions">
        <button class="btn btn-ghost" type="button" id="or-print" disabled><?= v2_ic('printer') ?><?= v2_te('Print') ?></button>
        <button class="btn btn-primary" type="button" id="or-pdf" disabled><?= v2_ic('file-text') ?><span data-label><?= v2_te('Export PDF') ?></span></button>
      </div>
    </div>
  </header>

  <div class="org-empty or-none" id="or-none" hidden></div>

  <div class="or-body" id="or-body"<?= $orEventId === '' ? ' hidden' : '' ?>>
    <section class="or-stats" aria-labelledby="or-stats-h">
      <h2 class="sr" id="or-stats-h"><?= v2_te('At a glance') ?></h2>
      <?= $orStat('revenue', v2_te('Net revenue')) ?>
      <?= $orStat('tickets', v2_te('Tickets sold')) ?>
      <?= $orStat('views', v2_te('Views')) ?>
      <?= $orStat('conversion', v2_te('Conversion rate')) ?>
    </section>

    <div class="or-grid is-charts">
      <section class="org-panel or-chart" aria-labelledby="or-chart-h">
        <div class="org-panel-head">
          <div><p class="org-k"><?= v2_te('Over time') ?></p><h2 class="org-panel-h" id="or-chart-h"><?= v2_te('Sales performance over time') ?></h2><p class="org-panel-p" id="or-chart-p"></p></div>
        </div>
        <ul class="or-legend" aria-hidden="true"><li><i class="is-bar"></i><?= v2_te('Net revenue per day') ?></li><li><i class="is-line"></i><?= v2_te('Tickets sold') ?></li></ul>
        <div class="or-plot" id="or-plot" tabindex="0" role="group" aria-roledescription="<?= v2_te('chart') ?>" aria-label="<?= v2_te('Sales chart') ?>">
          <div class="or-tip" id="or-tip" hidden></div>
          <p class="or-plot-msg" id="or-plot-msg" hidden><?= v2_te('No sales yet.') ?></p>
        </div>
        <p class="sr" id="or-plot-live" aria-live="polite"></p>
        <dl class="or-totals" id="or-totals"></dl>
      </section>

      <section class="org-panel or-donut-panel" aria-labelledby="or-donut-h">
        <div class="org-panel-head"><div><p class="org-k"><?= v2_te('By type') ?></p><h2 class="org-panel-h" id="or-donut-h"><?= v2_te('Ticket split') ?></h2></div></div>
        <div class="or-donut" id="or-donut"></div>
        <ul class="or-donut-legend" id="or-donut-legend"></ul>
      </section>
    </div>

    <section class="org-panel" aria-labelledby="or-types-h">
      <div class="org-panel-head"><div><p class="org-k"><?= v2_te('Tickets') ?></p><h2 class="org-panel-h" id="or-types-h"><?= v2_te('Ticket type performance') ?></h2></div></div>
      <div class="or-table-wrap">
        <table class="or-table">
          <caption class="sr"><?= v2_te('Sales of each ticket type') ?></caption>
          <thead><tr><th scope="col"><?= v2_te('Ticket type') ?></th><th scope="col" class="is-num"><?= v2_te('Price') ?></th><th scope="col" class="is-num"><?= v2_te('Sold') ?></th><th scope="col" class="is-num"><?= v2_te('Revenue') ?></th><th scope="col" class="is-share"><?= v2_te('% of total') ?></th></tr></thead>
          <tbody id="or-types"><tr><td colspan="5"><span class="org-skel or-sk-row"></span></td></tr></tbody>
          <tfoot id="or-types-foot"></tfoot>
        </table>
      </div>
    </section>

    <div class="or-grid">
      <section class="org-panel" aria-labelledby="or-goals-h">
        <div class="org-panel-head"><div><p class="org-k"><?= v2_te('Targets') ?></p><h2 class="org-panel-h" id="or-goals-h"><?= v2_te('Goals') ?></h2></div></div>
        <div class="or-goals" id="or-goals"><span class="org-skel or-sk-row"></span></div>
      </section>
      <section class="org-panel" aria-labelledby="or-camp-h">
        <div class="org-panel-head"><div><p class="org-k"><?= v2_te('Promotion') ?></p><h2 class="org-panel-h" id="or-camp-h"><?= v2_te('Marketing campaigns') ?></h2></div></div>
        <div class="or-camps" id="or-camps"><span class="org-skel or-sk-row"></span></div>
      </section>
    </div>

    <div class="or-grid">
      <section class="org-panel" aria-labelledby="or-traffic-h">
        <div class="org-panel-head"><div><p class="org-k"><?= v2_te('Where they come from') ?></p><h2 class="org-panel-h" id="or-traffic-h"><?= v2_te('Traffic sources') ?></h2></div></div>
        <ul class="or-bars" id="or-traffic"></ul>
      </section>
      <section class="org-panel" aria-labelledby="or-loc-h">
        <div class="org-panel-head"><div><p class="org-k"><?= v2_te('Where they are') ?></p><h2 class="org-panel-h" id="or-loc-h"><?= v2_te('Top buyer locations') ?></h2><p class="org-panel-p"><?= v2_te('Visitors of the page, by city.') ?></p></div></div>
        <ol class="or-rank" id="or-locations"></ol>
      </section>
    </div>

    <div class="or-grid">
      <section class="org-panel" aria-labelledby="or-refunds-h">
        <div class="org-panel-head">
          <div><p class="org-k"><?= v2_te('Money returned') ?></p><h2 class="org-panel-h" id="or-refunds-h"><?= v2_te('Refunds') ?></h2></div>
          <p class="or-refunds-total" id="or-refunds-total"></p>
        </div>
        <ul class="or-rows" id="or-refunds"></ul>
      </section>
      <section class="org-panel" aria-labelledby="or-orders-h">
        <div class="org-panel-head">
          <div><p class="org-k"><?= v2_te('Sales') ?></p><h2 class="org-panel-h" id="or-orders-h"><?= v2_te('Latest orders') ?></h2></div>
          <a class="org-more or-all-sales" id="or-all-sales" href="/organizator/vanzari"><?= v2_te('All sales') ?><?= v2_ic('arrow-right') ?></a>
        </div>
        <ul class="or-rows" id="or-orders"></ul>
      </section>
    </div>

    <section class="or-fin" aria-labelledby="or-fin-h">
      <h2 class="or-fin-h" id="or-fin-h"><?= v2_te('Financial summary') ?></h2>
      <dl class="or-fin-grid">
        <div><dt><?= v2_te('Gross revenue') ?></dt><dd id="or-f-gross">—</dd></div>
        <div><dt><?= v2_te('Refunds') ?></dt><dd class="is-refund" id="or-f-refunds">—</dd></div>
        <div><dt id="or-f-comm-l"><?= v2_te('Platform commission') ?></dt><dd class="is-comm" id="or-f-comm">—</dd></div>
        <div><dt><?= v2_te('Net revenue') ?></dt><dd class="is-net" id="or-f-net">—</dd></div>
      </dl>
      <p class="or-fin-p" id="or-fin-p"></p>
    </section>
    <p class="or-generated" id="or-generated"></p>
  </div>
</div>
<?php
v2_org_end();
include __DIR__ . '/../includes/v2/foot.php';
