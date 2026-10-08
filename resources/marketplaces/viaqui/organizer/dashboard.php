<?php
/**
 * Organizer dashboard: /organizator/panou (v2 design).
 *
 * Inside the v2 organizer shell (includes/v2/organizer.php). Everything the panel shows comes from the activities
 * module, through the very same endpoint /organizator/raport reads (/organizer/activities-module/summary, by payment
 * date), so the two pages agree to the cent: the hero with today's arrivals and the next seven days, the figures since
 * the account started (takings, what the operator keeps, valid tickets), the cards for the month (sales, commission and
 * what is left — the site against the desk — the catalogue, and the conversion only when the product pages have views),
 * the sales chart per day for a chosen period, the last months, the bookings that are coming and the best-selling
 * products.
 *
 * Rebuilt on the activities module: the panel used to read /organizer/dashboard* (the Event model), which operators of
 * viaqui.com never fill, so it showed zeros for the all-time figures and "Nicio activitate programată", while its
 * own "venituri" line contradicted Raport for the same month. Nothing is computed here any more: the commission has a
 * floor of 1,50 lei per ticket, so only what the API returns is shown. Gone with it: the orders-by-status panel (the
 * activities module has no such breakdown), the change against last month (nothing sends it) and the links to
 * /organizator/activities, /participanti and /vanzari, which do not exist for these accounts.
 *
 * Above all of that, for the operator who has just finished signing up: the "Ghid rapid" bar and "Pașii tăi de
 * pornire", the seven-point start checklist whose ticks are read from the API (org-steps.js) and which goes away for
 * good once every point is done; the button replays the guided tour of the panel (org-tour.js), which also runs by
 * itself the first time an operator lands here. The tour anchors on #ob, #ob-guide and .od-stats — keep them.
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/api.php';
require_once __DIR__ . '/../includes/nav-helpers.php';
require_once __DIR__ . '/../includes/v2/helpers.php';
require_once __DIR__ . '/../includes/v2/organizer.php';

$pageTitle = v2_t('Operator dashboard');
$pageDescription = v2_t('The operator dashboard on Viaqui: arrivals, sales, products and bookings.');
$canonicalUrl = SITE_URL . '/organizator/panou';
$noindex = true;
$skipPageCache = true;

$v2Styles = ['organizer.css', 'org-dashboard.css', 'org-steps.css', 'org-tour.css'];
$v2Scripts = ['organizer.js', 'org-dashboard.js', 'org-tour.js', 'org-steps.js'];
$v2LegacyScripts = ['assets/js/config.js', 'assets/js/utils.js', 'assets/js/api.js', 'assets/js/auth.js'];
$v2HeadExtra = v2_account_client_config('organizer');

include __DIR__ . '/../includes/v2/head.php';
v2_org_start('dashboard');
?>
<div class="od" id="od">
  <div class="ob-bar">
    <span class="ob-bar-ic"><?= v2_ic('question') ?></span>
    <p class="ob-bar-t"><?= v2_te('The dashboard guide: what each page does and the steps to your first sale.') ?></p>
    <button class="btn btn-ghost" type="button" id="ob-guide"><?= v2_ic('play') ?><?= v2_te('Quick guide') ?></button>
  </div>

  <section class="org-panel ob" id="ob" aria-labelledby="ob-h" hidden>
    <div class="org-panel-head">
      <div>
        <p class="org-k"><?= v2_te('Getting started') ?></p>
        <h2 class="org-panel-h" id="ob-h"><?= v2_te('Your first steps') ?></h2>
        <p class="org-panel-p" id="ob-sub"><?= v2_te('Checking where you are…') ?></p>
      </div>
      <p class="ob-count" id="ob-count" hidden></p>
    </div>
    <div class="ob-track" id="ob-track" aria-hidden="true" hidden><i id="ob-fill"></i></div>
    <ol class="ob-list" id="ob-list"></ol>
    <p class="sr" id="ob-live" aria-live="polite"></p>
  </section>

  <section class="od-hero" aria-labelledby="od-h">
    <div class="od-hero-main">
      <p class="od-kicker" id="od-kicker"><?= v2_te('Operator dashboard') ?></p>
      <h1 class="od-h" id="od-h" tabindex="-1"><span id="od-name"><?= v2_te('Welcome back!') ?></span></h1>
      <p class="od-lead" id="od-week"><?= v2_te('Loading the data…') ?></p>
      <div class="od-cta">
        <a class="btn btn-light" href="/organizator/rezervari"><?= v2_ic('list') ?><?= v2_te('Bookings') ?></a>
        <a class="btn btn-outline-light" href="/organizator/produse?nou=1"><?= v2_ic('plus') ?><?= v2_te('New product') ?></a>
      </div>
      <dl class="od-all" id="od-all">
        <div><dt><?= v2_te('Takings since the start') ?></dt><dd id="od-all-value"><span class="od-sk"></span></dd></div>
        <div><dt><?= v2_te('What you keep') ?></dt><dd id="od-all-net"><span class="od-sk"></span></dd></div>
        <div><dt><?= v2_te('Valid tickets') ?></dt><dd id="od-all-tickets"><span class="od-sk"></span></dd></div>
        <div id="od-all-views-box" hidden><dt><?= v2_te('Page views') ?></dt><dd id="od-all-views">—</dd></div>
      </dl>
    </div>
    <article class="od-today" aria-labelledby="od-today-k">
      <div class="od-today-top"><p class="org-k" id="od-today-k"><?= v2_te('Today at your venue') ?></p><span class="od-today-tag" id="od-today-tag" hidden></span></div>
      <div class="od-today-body" id="od-today-body">
        <span class="org-skel od-sk-t"></span><span class="org-skel od-sk-p"></span><span class="org-skel od-sk-b"></span>
      </div>
    </article>
  </section>

  <div class="od-alert" id="od-alert" hidden>
    <span class="od-alert-ic"><?= v2_ic('wallet') ?></span>
    <div class="od-alert-t"><p><b><?= v2_te('Payout details are missing') ?></b></p><p><?= v2_te('Add your bank account (IBAN) in the settings so you can receive your sales money.') ?></p></div>
    <a class="btn btn-ghost" href="/organizator/setari#bank"><?= v2_te('Add the details') ?></a>
  </div>

  <div class="org-empty is-error od-fail" id="od-fail" role="alert" hidden>
    <span class="org-empty-ic"><?= v2_ic('warning-circle') ?></span>
    <b><?= v2_te('We could not load the dashboard') ?></b>
    <p><?= v2_te('Check your connection and try again.') ?></p>
    <button class="btn btn-primary" type="button" id="od-retry"><?= v2_te('Try again') ?></button>
  </div>

  <section class="od-stats" aria-labelledby="od-stats-h">
    <h2 class="sr" id="od-stats-h"><?= v2_te('Your account figures') ?></h2>
    <article class="od-stat is-mint">
      <div class="od-stat-top"><span class="od-stat-ic"><?= v2_ic('coins') ?></span></div>
      <h3 class="od-stat-k" id="od-month-k"><?= v2_te('This month') ?></h3>
      <p class="od-stat-v" id="od-month-v"><span class="org-skel od-sk-v"></span></p>
      <p class="od-stat-p" id="od-month-p"></p>
    </article>
    <article class="od-stat">
      <div class="od-stat-top"><span class="od-stat-ic"><?= v2_ic('shopping-cart-simple') ?></span></div>
      <h3 class="od-stat-k"><?= v2_te('Online vs desk') ?></h3>
      <p class="od-stat-v" id="od-src-v"><span class="org-skel od-sk-v"></span></p>
      <div class="od-split" id="od-src-bar" aria-hidden="true" hidden><i class="is-on"></i><i class="is-pos"></i></div>
      <p class="od-stat-p" id="od-src-p"></p>
    </article>
    <article class="od-stat">
      <div class="od-stat-top"><span class="od-stat-ic"><?= v2_ic('squares-four') ?></span></div>
      <h3 class="od-stat-k"><?= v2_te('Products on the site') ?></h3>
      <p class="od-stat-v" id="od-cat-v"><span class="org-skel od-sk-v"></span></p>
      <p class="od-stat-p" id="od-cat-p"></p>
    </article>
    <article class="od-stat is-warm" id="od-card-conv" hidden>
      <div class="od-stat-top"><span class="od-stat-ic"><?= v2_ic('trend-up') ?></span></div>
      <h3 class="od-stat-k"><?= v2_te('Conversion rate') ?></h3>
      <p class="od-stat-v" id="od-conv">—</p>
      <p class="od-stat-p" id="od-conv-p"></p>
    </article>
  </section>

  <div class="od-grid">
    <section class="org-panel od-chart" aria-labelledby="od-chart-h">
      <div class="org-panel-head">
        <div>
          <p class="org-k"><?= v2_te('Performance') ?></p>
          <h2 class="org-panel-h" id="od-chart-h"><?= v2_te('Sales by day') ?></h2>
          <p class="org-panel-p" id="od-period"><?= v2_te('Last 30 days') ?></p>
        </div>
        <div class="od-periods" role="group" aria-label="<?= v2_te('Chart period') ?>">
          <button class="od-period" type="button" data-days="7" aria-pressed="false"><?= v2_te('7 days') ?></button>
          <button class="od-period" type="button" data-days="30" aria-pressed="true"><?= v2_te('30 days') ?></button>
          <button class="od-period" type="button" data-days="90" aria-pressed="false"><?= v2_te('90 days') ?></button>
          <button class="od-period" type="button" data-days="custom" id="od-custom" aria-pressed="false" aria-expanded="false" aria-controls="od-range"><?= v2_te('Custom') ?></button>
        </div>
      </div>
      <form class="od-range" id="od-range" novalidate hidden>
        <label class="od-range-f"><span><?= v2_te('From') ?></span><input type="date" id="od-from" aria-describedby="od-range-err"></label>
        <label class="od-range-f"><span><?= v2_te('To') ?></span><input type="date" id="od-to" aria-describedby="od-range-err"></label>
        <button class="btn btn-primary" type="submit"><?= v2_te('Apply') ?></button>
        <p class="od-range-err" id="od-range-err" role="alert"></p>
      </form>
      <ul class="od-legend" aria-hidden="true"><li class="is-val"><?= v2_te('Sales') ?></li><li class="is-bk"><?= v2_te('Bookings') ?></li></ul>
      <div class="od-plot" id="od-plot" tabindex="0" role="group" aria-roledescription="<?= v2_te('chart') ?>" aria-label="<?= v2_te('Sales chart') ?>" aria-describedby="od-plot-help" aria-busy="true">
        <p class="od-plot-msg" id="od-plot-msg" hidden><?= v2_te('No sales in the chosen period.') ?></p>
        <div class="od-plot-err" id="od-plot-err" hidden><p><?= v2_te('We could not load the chart.') ?></p><button class="btn btn-ghost" type="button" id="od-plot-retry"><?= v2_te('Try again') ?></button></div>
        <div class="od-tip" id="od-tip" hidden></div>
      </div>
      <p class="sr" id="od-plot-help"><?= v2_te('Use the left and right arrow keys to move through the days.') ?></p>
      <p class="sr" id="od-plot-live" aria-live="polite"></p>
      <dl class="od-totals">
        <div><dt><?= v2_te('Sales') ?></dt><dd id="od-t-val">—</dd></div>
        <div><dt><?= v2_te('Bookings') ?></dt><dd id="od-t-bk">—</dd></div>
        <div><dt><?= v2_te('People') ?></dt><dd id="od-t-pers">—</dd></div>
        <div><dt><?= v2_te('Commission') ?></dt><dd id="od-t-com">—</dd></div>
        <div><dt><?= v2_te('You keep') ?></dt><dd id="od-t-net">—</dd></div>
      </dl>
      <p class="od-note"><?= v2_t('The same figures as in the <a href="{url}">Report</a>, by payment date, online and desk sales together.', ['url' => '/organizator/raport']) ?></p>
    </section>

    <div class="od-side">
      <section class="org-panel od-quick" aria-labelledby="od-quick-h">
        <p class="org-k"><?= v2_te('Shortcuts') ?></p>
        <h2 class="org-panel-h" id="od-quick-h"><?= v2_te('Quick actions') ?></h2>
        <div class="od-quick-grid">
          <a class="od-q is-primary" href="/organizator/produse?nou=1"><?= v2_ic('plus') ?><span><?= v2_te('New product') ?></span></a>
          <a class="od-q" href="/organizator/rezervari"><?= v2_ic('list') ?><span><?= v2_te('Bookings') ?></span></a>
          <a class="od-q" href="/organizator/raport"><?= v2_ic('chart-line-up') ?><span><?= v2_te('Report') ?></span></a>
          <a class="od-q" href="/organizator/locatii"><?= v2_ic('map-pin') ?><span><?= v2_te('Venues') ?></span></a>
        </div>
      </section>
      <section class="org-panel od-months" aria-labelledby="od-months-h">
        <div class="org-panel-head">
          <div><p class="org-k"><?= v2_te('History') ?></p><h2 class="org-panel-h" id="od-months-h"><?= v2_te('Recent months') ?></h2></div>
        </div>
        <div id="od-months-body"><span class="org-skel od-sk-block"></span></div>
      </section>
    </div>
  </div>

  <div class="od-grid is-b">
    <section class="org-panel od-bookings" aria-labelledby="od-bk-h">
      <div class="org-panel-head">
        <div><p class="org-k"><?= v2_te('Who is coming') ?></p><h2 class="org-panel-h" id="od-bk-h" tabindex="-1"><?= v2_te('Upcoming bookings') ?></h2><p class="org-panel-p" id="od-bk-p"><?= v2_te('Loading…') ?></p></div>
        <a class="org-more" href="/organizator/rezervari"><?= v2_te('All bookings') ?><?= v2_ic('arrow-right') ?></a>
      </div>
      <div id="od-bk-body"><span class="org-skel od-sk-row"></span><span class="org-skel od-sk-row"></span><span class="org-skel od-sk-row"></span></div>
    </section>
    <section class="org-panel od-top" aria-labelledby="od-top-h">
      <div class="org-panel-head">
        <div><p class="org-k"><?= v2_te('What sells') ?></p><h2 class="org-panel-h" id="od-top-h"><?= v2_te('Top products') ?></h2><p class="org-panel-p" id="od-top-p"><?= v2_te('Loading…') ?></p></div>
        <a class="org-more" href="/organizator/produse"><?= v2_te('All products') ?><?= v2_ic('arrow-right') ?></a>
      </div>
      <div id="od-top-body"><span class="org-skel od-sk-line"></span><span class="org-skel od-sk-line"></span><span class="org-skel od-sk-line"></span><span class="org-skel od-sk-line"></span></div>
    </section>
  </div>
</div>
<?php
v2_org_end();
include __DIR__ . '/../includes/v2/foot.php';
