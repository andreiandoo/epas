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

$pageTitleRaw = 'Dashboard live — Locație — ' . SITE_NAME;
$pageDescription = 'Ce se întâmplă acum în locație: bilete, check-in-uri, încasări, casa deschisă și activitatea recentă.';
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
      <p class="ve-eyebrow"><span class="ve-live"><span></span></span>Live · se actualizează la 20 de secunde</p>
      <h1 class="ve-h" id="ve-title">Dashboard live</h1>
      <p class="ve-lead">Ce se întâmplă acum în locație. Ultima actualizare: <span id="ve-refresh">—</span>.</p>
    </div>
    <div class="ve-head-tools">
      <span class="po-field"><label for="ve-event">Locația</label><span class="po-select"><select id="ve-event" disabled><option>Se încarcă…</option></select><?= v2_ic('caret-down') ?></span></span>
      <div class="ve-head-btns">
        <a class="btn btn-primary" href="/organizator/pos"><?= v2_ic('scan') ?>Emite bilete</a>
        <a class="btn btn-ghost" id="ve-public" href="/" hidden><?= v2_ic('arrow-up-right') ?>Pagina publică</a>
      </div>
    </div>
  </header>

  <div class="org-empty" id="ve-none" hidden>
    <span class="org-empty-ic"><?= v2_ic('door-open') ?></span>
    <b>Nicio locație pregătită</b>
    <p>Paginile de locație funcționează pe o activitate configurată ca locație de agrement. Scrie-ne și îți pregătim locația.</p>
    <a class="btn btn-primary" href="/organizator/suport">Cere activarea<?= v2_ic('arrow-right') ?></a>
  </div>

  <div class="org-empty is-error" id="ve-failed" hidden>
    <span class="org-empty-ic"><?= v2_ic('warning-circle') ?></span>
    <b>Nu am putut încărca dashboardul</b>
    <p>Reîncearcă în câteva secunde.</p>
    <button class="btn btn-primary" type="button" id="ve-retry">Reîncearcă</button>
  </div>

  <div class="ve-main" id="ve-main" hidden>
    <!-- ===== register ===== -->
    <section class="ve-cash" id="ve-cash" aria-labelledby="ve-cash-h">
      <h2 class="ve-sr" id="ve-cash-h">Casa</h2>
      <div class="ve-cash-top">
        <p class="ve-cash-state"><span class="ve-dot" id="ve-cash-dot"></span><b id="ve-cash-label">Se verifică…</b><small id="ve-cash-since"></small></p>
        <a class="btn btn-ghost" href="/organizator/pos" id="ve-cash-cta"><?= v2_ic('door-open') ?>Gestionează casa</a>
      </div>
      <div class="ve-cash-nums" id="ve-cash-nums" hidden>
        <div class="is-warm"><p>Cash de predat</p><b id="ve-cash-cash">—</b></div>
        <div class="is-info"><p>Card încasat</p><b id="ve-cash-card">—</b></div>
        <div class="is-mint"><p>Total în casă</p><b id="ve-cash-total">—</b></div>
        <div><p>Comenzi în sesiune</p><b id="ve-cash-orders">—</b></div>
      </div>
      <p class="ve-note">Doar vânzările din locație. Cele online nu intră în casă.</p>
    </section>

    <!-- ===== live figures ===== -->
    <section class="ve-kpis" aria-label="Cifrele de azi">
      <?= $veKpi('sold', 'ticket', 'Bilete vândute azi', 'hint') ?>
      <?= $veKpi('scanned', 'check-circle', 'Check-in-uri azi') ?>
      <?= $veKpi('revenue', 'coins', 'Încasări azi') ?>
      <?= $veKpi('orders', 'receipt', 'Comenzi azi') ?>
      <?= $veKpi('occupancy', 'users-three', 'Oameni intrați') ?>
    </section>

    <div class="ve-grid">
      <!-- ===== sales over thirty days ===== -->
      <section class="org-panel ve-chart-panel" aria-labelledby="ve-chart-h">
        <div class="org-panel-head">
          <div><h2 class="org-panel-h" id="ve-chart-h">Vânzări, ultimele 30 de zile</h2><p class="org-panel-p">Încasări pe zi și câți oameni au intrat.</p></div>
          <p class="ve-legend"><span class="ve-lg is-rev">Încasări</span><span class="ve-lg is-vis">Vizitatori</span></p>
        </div>
        <div class="ve-chart" id="ve-chart"><span class="org-skel ve-chart-skel"></span></div>
        <p class="ve-empty" id="ve-chart-empty" hidden>Nicio vânzare în ultimele 30 de zile.</p>
      </section>

      <!-- ===== people ===== -->
      <section class="org-panel ve-people" aria-labelledby="ve-people-h">
        <h2 class="org-panel-h" id="ve-people-h">Participanți</h2>
        <p class="ve-big" id="ve-part-total">—</p>
        <p class="ve-note">Bilete de acces. Pachetele se numără pe componente, fără parcare și activități.</p>
        <div class="ve-people-two">
          <div><b id="ve-part-checked">—</b><p>au intrat</p></div>
          <div><b id="ve-part-rate">—</b><p>rată de intrare</p></div>
        </div>
        <a class="btn btn-ghost" href="/organizator/participanti"><?= v2_ic('users-three') ?>Vezi participanții</a>
      </section>
    </div>

    <!-- ===== weather ===== -->
    <section class="org-panel ve-weather" id="ve-weather" hidden aria-labelledby="ve-weather-h">
      <div class="org-panel-head">
        <div><h2 class="org-panel-h" id="ve-weather-h">Vremea, 7 zile</h2><p class="org-panel-p" id="ve-weather-venue">—</p></div>
        <p class="ve-note">Sursa: Open-Meteo</p>
      </div>
      <ul class="ve-days" id="ve-weather-days"></ul>
    </section>

    <!-- ===== comparison ===== -->
    <section class="org-panel ve-compare" aria-labelledby="ve-compare-h">
      <div class="org-panel-head">
        <div><h2 class="org-panel-h" id="ve-compare-h">Azi față de perioadele anterioare</h2><p class="org-panel-p" id="ve-compare-sub">Comparație corectă: fiecare zi e numărată până la aceeași oră ca azi.</p></div>
      </div>
      <div class="ve-table-wrap"><table class="ve-table">
        <thead><tr><th scope="col">Metrică</th><th scope="col" class="ve-right">Azi</th><th scope="col" class="ve-right">Ieri</th><th scope="col" class="ve-right">Săpt. trecută</th><th scope="col" class="ve-right">Luna trecută</th><th scope="col" class="ve-right">Anul trecut</th></tr></thead>
        <tbody id="ve-compare-body"><tr><td colspan="6" class="ve-state">Se încarcă…</td></tr></tbody>
      </table></div>
    </section>

    <!-- ===== stream ===== -->
    <section class="org-panel ve-stream-panel" aria-labelledby="ve-stream-h">
      <div class="org-panel-head">
        <div><h2 class="org-panel-h" id="ve-stream-h">Activitate recentă</h2><p class="org-panel-p">Vânzări și scanări din ultima oră.</p></div>
      </div>
      <ul class="ve-stream" id="ve-stream"><li class="ve-state">Se încarcă…</li></ul>
    </section>
  </div>
</div>
<?php
v2_org_end();
include __DIR__ . '/../includes/v2/foot.php';
