<?php
/**
 * Venue live dashboard: /organizator/locatie/live (venue-live.php), v2 design.
 *
 * The first screen of the "Locație" section, ported from Ambilet's leisure dashboard: what the venue is doing right
 * now (tickets and visitors today, check-ins, takings, orders), the open register, the seven-day forecast, today
 * against yesterday / last week / last month / last year, the last thirty days of sales, the people expected and
 * checked in, and the stream of what just happened.
 *
 * It works on an activity set up as a venue (display_template = leisure_venue); the page says so when there is none.
 * org-venue-live.js reads /organizer/events, then the venue's live, cashier, weather, compare, sales-timeline and
 * participants endpoints. Everything on the page is live data; nothing is drawn from stand-ins.
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/api.php';
require_once __DIR__ . '/../includes/nav-helpers.php';
require_once __DIR__ . '/../includes/v2/helpers.php';
require_once __DIR__ . '/../includes/v2/organizer.php';

$pageTitleRaw = v2_t('Venue live dashboard: {site}', ['site' => SITE_NAME]);
$pageDescription = v2_t('What is happening at the venue right now: tickets, check-ins, revenue, the open register and recent activity.');
$canonicalUrl = SITE_URL . '/organizator/locatie/live';
$noindex = true;
$skipPageCache = true;

$v2Styles = ['organizer.css', 'org-venue.css'];
$v2Scripts = ['organizer.js', 'org-venue-live.js'];
$v2LegacyScripts = ['assets/js/config.js', 'assets/js/utils.js', 'assets/js/api.js', 'assets/js/auth.js'];
$v2HeadExtra = v2_account_client_config('organizer');

$veKpi = function (string $key, string $icon, string $label, string $hint = '') {
    return '<article class="ve-kpi"><span class="ve-kpi-ic">' . v2_ic($icon) . '</span><div><b id="ve-k-' . $key . '">—</b><p>' . $label . '</p>'
        . ($hint ? '<small id="ve-k-' . $key . '-hint" hidden></small>' : '') . '</div></article>';
};

include __DIR__ . '/../includes/v2/head.php';
v2_org_start('venue-live');
?>
<div class="ve" id="ve">
  <header class="ve-head">
    <div>
      <p class="ve-eyebrow"><span class="ve-live"><span></span></span><?= v2_te('Live · refreshes every 20 seconds') ?></p>
      <h1 class="ve-h" id="ve-title"><?= v2_te('Live dashboard') ?></h1>
      <p class="ve-lead"><?= v2_t('What is happening at the venue right now. Last updated: {time}.', ['time' => '<span id="ve-refresh">—</span>']) ?></p>
    </div>
    <div class="ve-head-tools">
      <span class="po-field"><label for="ve-event"><?= v2_te('Venue') ?></label><span class="po-select"><select id="ve-event" disabled><option><?= v2_te('Loading…') ?></option></select><?= v2_ic('caret-down') ?></span></span>
      <div class="ve-head-btns">
        <a class="btn btn-primary" href="/organizator/pos"><?= v2_ic('scan') ?><?= v2_te('Issue tickets') ?></a>
        <a class="btn btn-ghost" id="ve-public" href="/" hidden><?= v2_ic('arrow-up-right') ?><?= v2_te('Public page') ?></a>
      </div>
    </div>
  </header>

  <div class="org-empty" id="ve-none" hidden>
    <span class="org-empty-ic"><?= v2_ic('door-open') ?></span>
    <b><?= v2_te('No venue set up yet') ?></b>
    <p><?= v2_te('Venue pages work on an experience set up as a venue. Write to us and we will set yours up.') ?></p>
    <a class="btn btn-primary" href="/organizator/suport"><?= v2_te('Ask for activation') ?><?= v2_ic('arrow-right') ?></a>
  </div>

  <div class="org-empty is-error" id="ve-failed" hidden>
    <span class="org-empty-ic"><?= v2_ic('warning-circle') ?></span>
    <b><?= v2_te('We could not load the dashboard') ?></b>
    <p><?= v2_te('Try again in a few seconds.') ?></p>
    <button class="btn btn-primary" type="button" id="ve-retry"><?= v2_te('Try again') ?></button>
  </div>

  <div class="ve-main" id="ve-main" hidden>
    <!-- ===== register ===== -->
    <section class="ve-cash" id="ve-cash" aria-labelledby="ve-cash-h">
      <h2 class="ve-sr" id="ve-cash-h"><?= v2_te('Register') ?></h2>
      <div class="ve-cash-top">
        <p class="ve-cash-state"><span class="ve-dot" id="ve-cash-dot"></span><b id="ve-cash-label"><?= v2_te('Checking…') ?></b><small id="ve-cash-since"></small></p>
        <a class="btn btn-ghost" href="/organizator/pos" id="ve-cash-cta"><?= v2_ic('door-open') ?><?= v2_te('Manage the register') ?></a>
      </div>
      <div class="ve-cash-nums" id="ve-cash-nums" hidden>
        <div class="is-warm"><p><?= v2_te('Cash to hand over') ?></p><b id="ve-cash-cash">—</b></div>
        <div class="is-info"><p><?= v2_te('Card payments') ?></p><b id="ve-cash-card">—</b></div>
        <div class="is-mint"><p><?= v2_te('Total in the register') ?></p><b id="ve-cash-total">—</b></div>
        <div><p><?= v2_te('Orders this session') ?></p><b id="ve-cash-orders">—</b></div>
      </div>
      <p class="ve-note"><?= v2_te('On-site sales only. Online sales do not go through the register.') ?></p>
    </section>

    <!-- ===== live figures ===== -->
    <section class="ve-kpis" aria-label="<?= v2_te('Today\'s figures') ?>">
      <?= $veKpi('sold', 'ticket', v2_te('Tickets sold today'), 'hint') ?>
      <?= $veKpi('scanned', 'check-circle', v2_te('Check-ins today')) ?>
      <?= $veKpi('revenue', 'coins', v2_te('Revenue today')) ?>
      <?= $veKpi('orders', 'receipt', v2_te('Orders today')) ?>
      <?= $veKpi('occupancy', 'users-three', v2_te('People inside')) ?>
    </section>

    <div class="ve-grid">
      <!-- ===== sales over thirty days ===== -->
      <section class="org-panel ve-chart-panel" aria-labelledby="ve-chart-h">
        <div class="org-panel-head">
          <div><h2 class="org-panel-h" id="ve-chart-h"><?= v2_te('Sales, last 30 days') ?></h2><p class="org-panel-p"><?= v2_te('Revenue per day and how many people came in.') ?></p></div>
          <p class="ve-legend"><span class="ve-lg is-rev"><?= v2_te('Revenue') ?></span><span class="ve-lg is-vis"><?= v2_te('Visitors') ?></span></p>
        </div>
        <div class="ve-chart" id="ve-chart"><span class="org-skel ve-chart-skel"></span></div>
        <p class="ve-empty" id="ve-chart-empty" hidden><?= v2_te('No sales in the last 30 days.') ?></p>
      </section>

      <!-- ===== people ===== -->
      <section class="org-panel ve-people" aria-labelledby="ve-people-h">
        <h2 class="org-panel-h" id="ve-people-h"><?= v2_te('Participants') ?></h2>
        <p class="ve-big" id="ve-part-total">—</p>
        <p class="ve-note"><?= v2_te('Access tickets. Packages are counted by their parts, without parking and activities.') ?></p>
        <div class="ve-people-two">
          <div><b id="ve-part-checked">—</b><p><?= v2_te('checked in') ?></p></div>
          <div><b id="ve-part-rate">—</b><p><?= v2_te('check-in rate') ?></p></div>
        </div>
        <a class="btn btn-ghost" href="/organizator/participanti"><?= v2_ic('users-three') ?><?= v2_te('See participants') ?></a>
      </section>
    </div>

    <!-- ===== weather ===== -->
    <section class="org-panel ve-weather" id="ve-weather" hidden aria-labelledby="ve-weather-h">
      <div class="org-panel-head">
        <div><h2 class="org-panel-h" id="ve-weather-h"><?= v2_te('Weather, 7 days') ?></h2><p class="org-panel-p" id="ve-weather-venue">—</p></div>
        <p class="ve-note"><?= v2_te('Source: Open-Meteo') ?></p>
      </div>
      <ul class="ve-days" id="ve-weather-days"></ul>
    </section>

    <!-- ===== comparison ===== -->
    <section class="org-panel ve-compare" aria-labelledby="ve-compare-h">
      <div class="org-panel-head">
        <div><h2 class="org-panel-h" id="ve-compare-h"><?= v2_te('Today against earlier periods') ?></h2><p class="org-panel-p" id="ve-compare-sub"><?= v2_te('A fair comparison: each day is counted up to the same time as today.') ?></p></div>
      </div>
      <div class="ve-table-wrap"><table class="ve-table">
        <thead><tr><th scope="col"><?= v2_te('Metric') ?></th><th scope="col" class="ve-right"><?= v2_te('Today') ?></th><th scope="col" class="ve-right"><?= v2_te('Yesterday') ?></th><th scope="col" class="ve-right"><?= v2_te('Last week') ?></th><th scope="col" class="ve-right"><?= v2_te('Last month') ?></th><th scope="col" class="ve-right"><?= v2_te('Last year') ?></th></tr></thead>
        <tbody id="ve-compare-body"><tr><td colspan="6" class="ve-state"><?= v2_te('Loading…') ?></td></tr></tbody>
      </table></div>
    </section>

    <!-- ===== stream ===== -->
    <section class="org-panel ve-stream-panel" aria-labelledby="ve-stream-h">
      <div class="org-panel-head">
        <div><h2 class="org-panel-h" id="ve-stream-h"><?= v2_te('Recent activity') ?></h2><p class="org-panel-p"><?= v2_te('Sales and scans from the last hour.') ?></p></div>
      </div>
      <ul class="ve-stream" id="ve-stream"><li class="ve-state"><?= v2_te('Loading…') ?></li></ul>
    </section>
  </div>
</div>
<?php
v2_org_end();
include __DIR__ . '/../includes/v2/foot.php';
