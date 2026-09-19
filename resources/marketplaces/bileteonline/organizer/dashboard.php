<?php
/**
 * Organizer dashboard: /organizator/panou (v2 design).
 *
 * Inside the v2 organizer shell (includes/v2/organizer.php). Hero with the week in one sentence, the all-time figures
 * and the next activity (countdown, sold / available, check-in); four indicators for the month (net revenue with the
 * change against the same days of last month, tickets sold, activities in progress, conversion); the sales chart (net
 * revenue and issued tickets per day, page views and the day's activities in the tooltip; 7 / 30 / 90 days or a custom
 * range) with the period totals; quick actions; this month's orders by status with the money; the upcoming activities;
 * the recent orders. org-dashboard.js reads /organizer/dashboard, /organizer/dashboard/analytics-timeline,
 * /organizer/dashboard/sales-timeline and /organizer/dashboard/recent-orders.
 *
 * Fixed on the way: the three "+0%" changes and the 0% conversion were placeholders (the API sends none of them), and
 * the recent activity was always empty (it read a field the API doesn't have); an activity with unlimited tickets showed
 * "12/-1" and one without a quota was measured against an invented 100; "Creează prima activitate" opened the list
 * (?new=1 does nothing there) instead of the new-activity form; the next activity had no link to itself; Chart.js came
 * unpinned from a CDN (the chart is drawn in SVG now, with keyboard access).
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/api.php';
require_once __DIR__ . '/../includes/nav-helpers.php';
require_once __DIR__ . '/../includes/v2/helpers.php';
require_once __DIR__ . '/../includes/v2/organizer.php';

$pageTitleRaw = 'Panou operator — ' . SITE_NAME;
$pageDescription = 'Panoul operatorului pe bilete.online: vânzări, activități, participanți și comenzi recente.';
$canonicalUrl = SITE_URL . '/organizator/panou';
$noindex = true;
$skipPageCache = true;

$v2Styles = ['organizer.css', 'org-dashboard.css'];
$v2Scripts = ['organizer.js', 'org-dashboard.js'];
$v2LegacyScripts = ['assets/js/config.js', 'assets/js/utils.js', 'assets/js/api.js', 'assets/js/auth.js'];
$v2HeadExtra = v2_account_client_config('organizer');

include __DIR__ . '/../includes/v2/head.php';
v2_org_start('dashboard');
?>
<div class="od" id="od">
  <section class="od-hero" aria-labelledby="od-h">
    <div class="od-hero-main">
      <p class="od-kicker" id="od-kicker">Panou operator</p>
      <h1 class="od-h" id="od-h" tabindex="-1">Bun venit înapoi<span id="od-name"></span>!</h1>
      <p class="od-lead" id="od-week">Se încarcă datele…</p>
      <div class="od-cta">
        <a class="btn btn-light" href="/organizator/activities?action=create"><?= v2_ic('plus') ?>Activitate nouă</a>
        <a class="btn btn-outline-light" href="/organizator/participanti"><?= v2_ic('scan') ?>Check-in</a>
      </div>
      <dl class="od-all" id="od-all">
        <div><dt>Vânzări nete de la început</dt><dd id="od-all-sales"><span class="od-sk"></span></dd></div>
        <div><dt>Bilete valide</dt><dd id="od-all-tickets"><span class="od-sk"></span></dd></div>
        <div><dt>Vizualizări pagini</dt><dd id="od-all-views"><span class="od-sk"></span></dd></div>
      </dl>
    </div>
    <article class="od-next" aria-labelledby="od-next-k">
      <div class="od-next-top"><p class="org-k" id="od-next-k">Următoarea activitate</p><span class="od-next-tag" id="od-next-tag" hidden></span></div>
      <div class="od-next-body" id="od-next-body">
        <span class="org-skel od-sk-t"></span><span class="org-skel od-sk-p"></span><span class="org-skel od-sk-b"></span>
      </div>
    </article>
  </section>

  <div class="od-alert" id="od-alert" hidden>
    <span class="od-alert-ic"><?= v2_ic('wallet') ?></span>
    <div class="od-alert-t"><p><b>Datele pentru plăți lipsesc</b></p><p>Adaugă contul bancar (IBAN) în setări ca să poți primi banii din vânzări.</p></div>
    <a class="btn btn-ghost" href="/organizator/setari#bank">Adaugă datele</a>
  </div>

  <div class="org-empty is-error od-fail" id="od-fail" role="alert" hidden>
    <span class="org-empty-ic"><?= v2_ic('warning-circle') ?></span>
    <b>Nu am putut încărca panoul</b>
    <p>Verifică conexiunea și încearcă din nou.</p>
    <button class="btn btn-primary" type="button" id="od-retry">Reîncearcă</button>
  </div>

  <section class="od-stats" aria-labelledby="od-stats-h">
    <h2 class="sr" id="od-stats-h">Indicatorii lunii</h2>
    <article class="od-stat is-mint">
      <div class="od-stat-top"><span class="od-stat-ic"><?= v2_ic('coins') ?></span><span class="od-delta" id="od-delta-revenue" hidden></span></div>
      <h3 class="od-stat-k">Venituri luna aceasta</h3>
      <p class="od-stat-v" id="od-revenue"><span class="org-skel od-sk-v"></span></p>
      <p class="od-stat-p" id="od-revenue-p"></p>
    </article>
    <article class="od-stat">
      <div class="od-stat-top"><span class="od-stat-ic"><?= v2_ic('ticket') ?></span></div>
      <h3 class="od-stat-k">Bilete vândute luna aceasta</h3>
      <p class="od-stat-v" id="od-tickets"><span class="org-skel od-sk-v"></span></p>
      <p class="od-stat-p" id="od-tickets-p"></p>
    </article>
    <article class="od-stat">
      <div class="od-stat-top"><span class="od-stat-ic"><?= v2_ic('calendar-blank') ?></span></div>
      <h3 class="od-stat-k">Activități în derulare</h3>
      <p class="od-stat-v" id="od-active"><span class="org-skel od-sk-v"></span></p>
      <p class="od-stat-p" id="od-active-p"></p>
    </article>
    <article class="od-stat is-warm">
      <div class="od-stat-top"><span class="od-stat-ic"><?= v2_ic('trend-up') ?></span></div>
      <h3 class="od-stat-k">Rată de conversie</h3>
      <p class="od-stat-v" id="od-conv"><span class="org-skel od-sk-v"></span></p>
      <p class="od-stat-p" id="od-conv-p"></p>
    </article>
  </section>

  <div class="od-grid">
    <section class="org-panel od-chart" aria-labelledby="od-chart-h">
      <div class="org-panel-head">
        <div>
          <p class="org-k">Performanță</p>
          <h2 class="org-panel-h" id="od-chart-h">Vânzări bilete</h2>
          <p class="org-panel-p" id="od-period">Ultimele 30 de zile</p>
        </div>
        <div class="od-periods" role="group" aria-label="Perioada graficului">
          <button class="od-period" type="button" data-days="7" aria-pressed="false">7 zile</button>
          <button class="od-period" type="button" data-days="30" aria-pressed="true">30 zile</button>
          <button class="od-period" type="button" data-days="90" aria-pressed="false">90 zile</button>
          <button class="od-period" type="button" data-days="custom" id="od-custom" aria-pressed="false" aria-expanded="false" aria-controls="od-range">Personalizat</button>
        </div>
      </div>
      <form class="od-range" id="od-range" novalidate hidden>
        <label class="od-range-f"><span>De la</span><input type="date" id="od-from" aria-describedby="od-range-err"></label>
        <label class="od-range-f"><span>Până la</span><input type="date" id="od-to" aria-describedby="od-range-err"></label>
        <button class="btn btn-primary" type="submit">Aplică</button>
        <p class="od-range-err" id="od-range-err" role="alert"></p>
      </form>
      <ul class="od-legend" aria-hidden="true"><li class="is-rev">Venit net (lei)</li><li class="is-tix">Bilete emise</li><li class="is-evt">Zile cu activități</li></ul>
      <div class="od-plot" id="od-plot" tabindex="0" role="group" aria-roledescription="grafic" aria-label="Grafic vânzări" aria-describedby="od-plot-help" aria-busy="true">
        <p class="od-plot-msg" id="od-plot-msg" hidden>Nicio vânzare în perioada aleasă.</p>
        <div class="od-plot-err" id="od-plot-err" hidden><p>Nu am putut încărca graficul.</p><button class="btn btn-ghost" type="button" id="od-plot-retry">Reîncearcă</button></div>
        <div class="od-tip" id="od-tip" hidden></div>
      </div>
      <p class="sr" id="od-plot-help">Folosește săgețile stânga și dreapta pentru a trece prin zile.</p>
      <p class="sr" id="od-plot-live" aria-live="polite"></p>
      <dl class="od-totals">
        <div><dt>Venit net</dt><dd id="od-t-rev">—</dd></div>
        <div><dt>Bilete emise</dt><dd id="od-t-tix">—</dd></div>
        <div><dt>Vizualizări</dt><dd id="od-t-views">—</dd></div>
        <div><dt>Conversie</dt><dd id="od-t-conv">—</dd></div>
        <div><dt>Medie pe zi</dt><dd id="od-t-avg">—</dd></div>
      </dl>
    </section>

    <div class="od-side">
      <section class="org-panel od-quick" aria-labelledby="od-quick-h">
        <p class="org-k">Scurtături</p>
        <h2 class="org-panel-h" id="od-quick-h">Acțiuni rapide</h2>
        <div class="od-quick-grid">
          <a class="od-q is-primary" href="/organizator/activities?action=create"><?= v2_ic('plus') ?><span>Activitate nouă</span></a>
          <a class="od-q" href="/organizator/participanti"><?= v2_ic('scan') ?><span>Check-in</span></a>
          <a class="od-q" href="/organizator/vanzari"><?= v2_ic('chart-line-up') ?><span>Vânzări</span></a>
          <a class="od-q" href="/organizator/sold"><?= v2_ic('wallet') ?><span>Sold</span></a>
        </div>
      </section>
      <section class="org-panel od-month" aria-labelledby="od-month-h">
        <div class="org-panel-head">
          <div><p class="org-k" id="od-month-k">Luna aceasta</p><h2 class="org-panel-h" id="od-month-h">Comenzi</h2></div>
          <span class="od-delta" id="od-delta-orders" hidden></span>
        </div>
        <div id="od-month-body"><span class="org-skel od-sk-block"></span></div>
      </section>
    </div>
  </div>

  <div class="od-grid is-b">
    <section class="org-panel od-events" aria-labelledby="od-events-h">
      <div class="org-panel-head">
        <div><p class="org-k">Programul tău</p><h2 class="org-panel-h" id="od-events-h">Activitățile tale</h2><p class="org-panel-p" id="od-events-p">Se încarcă…</p></div>
        <a class="org-more" href="/organizator/activities">Vezi toate<?= v2_ic('arrow-right') ?></a>
      </div>
      <div id="od-events-body"><span class="org-skel od-sk-row"></span><span class="org-skel od-sk-row"></span><span class="org-skel od-sk-row"></span></div>
    </section>
    <section class="org-panel od-recent" aria-labelledby="od-recent-h">
      <div class="org-panel-head">
        <div><p class="org-k">Ultimele comenzi</p><h2 class="org-panel-h" id="od-recent-h" tabindex="-1">Activitate recentă</h2></div>
        <a class="org-more" href="/organizator/vanzari">Toate vânzările<?= v2_ic('arrow-right') ?></a>
      </div>
      <div id="od-recent-body"><span class="org-skel od-sk-line"></span><span class="org-skel od-sk-line"></span><span class="org-skel od-sk-line"></span><span class="org-skel od-sk-line"></span></div>
    </section>
  </div>
</div>
<?php
v2_org_end();
include __DIR__ . '/../includes/v2/foot.php';
