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
 * - the refunds list was always "Nicio rambursare" (core sends only the total): the refunded orders are listed;
 * - the chart library came unpinned from a CDN (and without it the page had no charts): the charts are drawn here, with
 *   a tooltip, keyboard steps and an accessible summary; the date labels were English ("Sep 15");
 * - core spreads page views over the days in proportion to ticket sales, so they are not shown per day;
 * - with no activity in the address the page redirected to the dashboard: it now says so and links to the activities.
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/api.php';
require_once __DIR__ . '/../includes/nav-helpers.php';
require_once __DIR__ . '/../includes/v2/helpers.php';
require_once __DIR__ . '/../includes/v2/organizer.php';

$orEventId = isset($_GET['event']) && ctype_digit((string) $_GET['event']) ? (string) (int) $_GET['event'] : '';

$pageTitleRaw = 'Raport activitate — ' . SITE_NAME;
$pageDescription = 'Raportul unei activități pe bilete.online: vânzări, bilete, trafic, obiective, rambursări și sumar financiar.';
$canonicalUrl = SITE_URL . '/organizator/report' . ($orEventId !== '' ? '/' . $orEventId : '');
$noindex = true;
$skipPageCache = true;

$v2Styles = ['organizer.css', 'org-report.css'];
$v2Scripts = ['organizer.js', 'org-report.js'];
$v2LegacyScripts = ['assets/js/config.js', 'assets/js/utils.js', 'assets/js/api.js', 'assets/js/auth.js'];
$v2HeadExtra = v2_account_client_config('organizer');

$orStat = function (string $key, string $label) {
    return '<article class="or-stat"><p class="or-stat-k">' . $label . '</p><p class="or-stat-v" id="or-s-' . $key . '"><span class="org-skel or-sk"></span></p><p class="or-stat-p" id="or-s-' . $key . '-p"></p></article>';
};

include __DIR__ . '/../includes/v2/head.php';
v2_org_start('events');
?>
<div class="or" id="or" data-event="<?= htmlspecialchars($orEventId, ENT_QUOTES) ?>">
  <header class="or-head">
    <a class="or-back" href="/organizator/activities"><?= v2_ic('arrow-left') ?>Înapoi la activități</a>
    <div class="or-head-row">
      <div class="or-head-t">
        <p class="org-k">Raport activitate</p>
        <h1 class="or-h" id="or-title">Raport activitate</h1>
        <p class="or-info" id="or-info"></p>
      </div>
      <div class="or-actions">
        <button class="btn btn-ghost" type="button" id="or-print" disabled><?= v2_ic('printer') ?>Printează</button>
        <button class="btn btn-primary" type="button" id="or-pdf" disabled><?= v2_ic('file-text') ?><span data-label>Export PDF</span></button>
      </div>
    </div>
  </header>

  <div class="org-empty or-none" id="or-none" hidden></div>

  <div class="or-body" id="or-body"<?= $orEventId === '' ? ' hidden' : '' ?>>
    <section class="or-stats" aria-labelledby="or-stats-h">
      <h2 class="sr" id="or-stats-h">Pe scurt</h2>
      <?= $orStat('revenue', 'Venituri nete') ?>
      <?= $orStat('tickets', 'Bilete vândute') ?>
      <?= $orStat('views', 'Vizualizări') ?>
      <?= $orStat('conversion', 'Rată conversie') ?>
    </section>

    <div class="or-grid is-charts">
      <section class="org-panel or-chart" aria-labelledby="or-chart-h">
        <div class="org-panel-head">
          <div><p class="org-k">În timp</p><h2 class="org-panel-h" id="or-chart-h">Performanță vânzări în timp</h2><p class="org-panel-p" id="or-chart-p"></p></div>
        </div>
        <ul class="or-legend" aria-hidden="true"><li><i class="is-bar"></i>Venit net pe zi</li><li><i class="is-line"></i>Bilete vândute</li></ul>
        <div class="or-plot" id="or-plot" tabindex="0" role="group" aria-roledescription="grafic" aria-label="Grafic vânzări">
          <div class="or-tip" id="or-tip" hidden></div>
          <p class="or-plot-msg" id="or-plot-msg" hidden>Nicio vânzare încă.</p>
        </div>
        <p class="sr" id="or-plot-live" aria-live="polite"></p>
        <dl class="or-totals" id="or-totals"></dl>
      </section>

      <section class="org-panel or-donut-panel" aria-labelledby="or-donut-h">
        <div class="org-panel-head"><div><p class="org-k">Pe tipuri</p><h2 class="org-panel-h" id="or-donut-h">Distribuție bilete</h2></div></div>
        <div class="or-donut" id="or-donut"></div>
        <ul class="or-donut-legend" id="or-donut-legend"></ul>
      </section>
    </div>

    <section class="org-panel" aria-labelledby="or-types-h">
      <div class="org-panel-head"><div><p class="org-k">Bilete</p><h2 class="org-panel-h" id="or-types-h">Performanță tipuri bilete</h2></div></div>
      <div class="or-table-wrap">
        <table class="or-table">
          <caption class="sr">Vânzările fiecărui tip de bilet</caption>
          <thead><tr><th scope="col">Tip bilet</th><th scope="col" class="is-num">Preț</th><th scope="col" class="is-num">Vândute</th><th scope="col" class="is-num">Venituri</th><th scope="col" class="is-share">% din total</th></tr></thead>
          <tbody id="or-types"><tr><td colspan="5"><span class="org-skel or-sk-row"></span></td></tr></tbody>
          <tfoot id="or-types-foot"></tfoot>
        </table>
      </div>
    </section>

    <div class="or-grid">
      <section class="org-panel" aria-labelledby="or-goals-h">
        <div class="org-panel-head"><div><p class="org-k">Ținte</p><h2 class="org-panel-h" id="or-goals-h">Obiective</h2></div></div>
        <div class="or-goals" id="or-goals"><span class="org-skel or-sk-row"></span></div>
      </section>
      <section class="org-panel" aria-labelledby="or-camp-h">
        <div class="org-panel-head"><div><p class="org-k">Promovare</p><h2 class="org-panel-h" id="or-camp-h">Campanii marketing</h2></div></div>
        <div class="or-camps" id="or-camps"><span class="org-skel or-sk-row"></span></div>
      </section>
    </div>

    <div class="or-grid">
      <section class="org-panel" aria-labelledby="or-traffic-h">
        <div class="org-panel-head"><div><p class="org-k">De unde vin</p><h2 class="org-panel-h" id="or-traffic-h">Surse de trafic</h2></div></div>
        <ul class="or-bars" id="or-traffic"></ul>
      </section>
      <section class="org-panel" aria-labelledby="or-loc-h">
        <div class="org-panel-head"><div><p class="org-k">Unde sunt</p><h2 class="org-panel-h" id="or-loc-h">Locații top cumpărători</h2><p class="org-panel-p">Vizitatorii paginii, după oraș.</p></div></div>
        <ol class="or-rank" id="or-locations"></ol>
      </section>
    </div>

    <div class="or-grid">
      <section class="org-panel" aria-labelledby="or-refunds-h">
        <div class="org-panel-head">
          <div><p class="org-k">Bani returnați</p><h2 class="org-panel-h" id="or-refunds-h">Rambursări</h2></div>
          <p class="or-refunds-total" id="or-refunds-total"></p>
        </div>
        <ul class="or-rows" id="or-refunds"></ul>
      </section>
      <section class="org-panel" aria-labelledby="or-orders-h">
        <div class="org-panel-head">
          <div><p class="org-k">Vânzări</p><h2 class="org-panel-h" id="or-orders-h">Ultimele comenzi</h2></div>
          <a class="org-more or-all-sales" id="or-all-sales" href="/organizator/vanzari">Toate vânzările<?= v2_ic('arrow-right') ?></a>
        </div>
        <ul class="or-rows" id="or-orders"></ul>
      </section>
    </div>

    <section class="or-fin" aria-labelledby="or-fin-h">
      <h2 class="or-fin-h" id="or-fin-h">Sumar financiar</h2>
      <dl class="or-fin-grid">
        <div><dt>Venituri brute</dt><dd id="or-f-gross">—</dd></div>
        <div><dt>Rambursări</dt><dd class="is-refund" id="or-f-refunds">—</dd></div>
        <div><dt id="or-f-comm-l">Comision platformă</dt><dd class="is-comm" id="or-f-comm">—</dd></div>
        <div><dt>Venituri nete</dt><dd class="is-net" id="or-f-net">—</dd></div>
      </dl>
      <p class="or-fin-p" id="or-fin-p"></p>
    </section>
    <p class="or-generated" id="or-generated"></p>
  </div>
</div>
<?php
v2_org_end();
include __DIR__ . '/../includes/v2/foot.php';
