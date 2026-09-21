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
 * bilete.online never fill, so it showed zeros for the all-time figures and "Nicio activitate programată", while its
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

$pageTitleRaw = 'Panou operator — ' . SITE_NAME;
$pageDescription = 'Panoul operatorului pe bilete.online: sosiri, vânzări, produse și rezervări.';
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
    <p class="ob-bar-t">Ghidul panoului: ce face fiecare pagină și pașii până la prima ta vânzare.</p>
    <button class="btn btn-ghost" type="button" id="ob-guide"><?= v2_ic('play') ?>Ghid rapid</button>
  </div>

  <section class="org-panel ob" id="ob" aria-labelledby="ob-h" hidden>
    <div class="org-panel-head">
      <div>
        <p class="org-k">Pornire</p>
        <h2 class="org-panel-h" id="ob-h">Pașii tăi de pornire</h2>
        <p class="org-panel-p" id="ob-sub">Verificăm unde ai ajuns…</p>
      </div>
      <p class="ob-count" id="ob-count" hidden></p>
    </div>
    <div class="ob-track" id="ob-track" aria-hidden="true" hidden><i id="ob-fill"></i></div>
    <ol class="ob-list" id="ob-list"></ol>
    <p class="sr" id="ob-live" aria-live="polite"></p>
  </section>

  <section class="od-hero" aria-labelledby="od-h">
    <div class="od-hero-main">
      <p class="od-kicker" id="od-kicker">Panou operator</p>
      <h1 class="od-h" id="od-h" tabindex="-1">Bun venit înapoi<span id="od-name"></span>!</h1>
      <p class="od-lead" id="od-week">Se încarcă datele…</p>
      <div class="od-cta">
        <a class="btn btn-light" href="/organizator/rezervari"><?= v2_ic('list') ?>Rezervări</a>
        <a class="btn btn-outline-light" href="/organizator/produse?nou=1"><?= v2_ic('plus') ?>Produs nou</a>
      </div>
      <dl class="od-all" id="od-all">
        <div><dt>Încasări de la început</dt><dd id="od-all-value"><span class="od-sk"></span></dd></div>
        <div><dt>Cât îți rămâne</dt><dd id="od-all-net"><span class="od-sk"></span></dd></div>
        <div><dt>Bilete valide</dt><dd id="od-all-tickets"><span class="od-sk"></span></dd></div>
        <div id="od-all-views-box" hidden><dt>Vizualizări pagini</dt><dd id="od-all-views">—</dd></div>
      </dl>
    </div>
    <article class="od-today" aria-labelledby="od-today-k">
      <div class="od-today-top"><p class="org-k" id="od-today-k">Azi la tine</p><span class="od-today-tag" id="od-today-tag" hidden></span></div>
      <div class="od-today-body" id="od-today-body">
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
    <h2 class="sr" id="od-stats-h">Cifrele contului tău</h2>
    <article class="od-stat is-mint">
      <div class="od-stat-top"><span class="od-stat-ic"><?= v2_ic('coins') ?></span></div>
      <h3 class="od-stat-k" id="od-month-k">Luna aceasta</h3>
      <p class="od-stat-v" id="od-month-v"><span class="org-skel od-sk-v"></span></p>
      <p class="od-stat-p" id="od-month-p"></p>
    </article>
    <article class="od-stat">
      <div class="od-stat-top"><span class="od-stat-ic"><?= v2_ic('shopping-cart-simple') ?></span></div>
      <h3 class="od-stat-k">Online vs casă</h3>
      <p class="od-stat-v" id="od-src-v"><span class="org-skel od-sk-v"></span></p>
      <div class="od-split" id="od-src-bar" aria-hidden="true" hidden><i class="is-on"></i><i class="is-pos"></i></div>
      <p class="od-stat-p" id="od-src-p"></p>
    </article>
    <article class="od-stat">
      <div class="od-stat-top"><span class="od-stat-ic"><?= v2_ic('squares-four') ?></span></div>
      <h3 class="od-stat-k">Produse pe site</h3>
      <p class="od-stat-v" id="od-cat-v"><span class="org-skel od-sk-v"></span></p>
      <p class="od-stat-p" id="od-cat-p"></p>
    </article>
    <article class="od-stat is-warm" id="od-card-conv" hidden>
      <div class="od-stat-top"><span class="od-stat-ic"><?= v2_ic('trend-up') ?></span></div>
      <h3 class="od-stat-k">Rată de conversie</h3>
      <p class="od-stat-v" id="od-conv">—</p>
      <p class="od-stat-p" id="od-conv-p"></p>
    </article>
  </section>

  <div class="od-grid">
    <section class="org-panel od-chart" aria-labelledby="od-chart-h">
      <div class="org-panel-head">
        <div>
          <p class="org-k">Performanță</p>
          <h2 class="org-panel-h" id="od-chart-h">Vânzări pe zile</h2>
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
      <ul class="od-legend" aria-hidden="true"><li class="is-val">Vânzări (lei)</li><li class="is-bk">Rezervări</li></ul>
      <div class="od-plot" id="od-plot" tabindex="0" role="group" aria-roledescription="grafic" aria-label="Grafic vânzări" aria-describedby="od-plot-help" aria-busy="true">
        <p class="od-plot-msg" id="od-plot-msg" hidden>Nicio vânzare în perioada aleasă.</p>
        <div class="od-plot-err" id="od-plot-err" hidden><p>Nu am putut încărca graficul.</p><button class="btn btn-ghost" type="button" id="od-plot-retry">Reîncearcă</button></div>
        <div class="od-tip" id="od-tip" hidden></div>
      </div>
      <p class="sr" id="od-plot-help">Folosește săgețile stânga și dreapta pentru a trece prin zile.</p>
      <p class="sr" id="od-plot-live" aria-live="polite"></p>
      <dl class="od-totals">
        <div><dt>Vânzări</dt><dd id="od-t-val">—</dd></div>
        <div><dt>Rezervări</dt><dd id="od-t-bk">—</dd></div>
        <div><dt>Persoane</dt><dd id="od-t-pers">—</dd></div>
        <div><dt>Comision</dt><dd id="od-t-com">—</dd></div>
        <div><dt>Îți rămân</dt><dd id="od-t-net">—</dd></div>
      </dl>
      <p class="od-note">Aceleași cifre ca în <a href="/organizator/raport">Raport</a>, după data plății, online și la casă la un loc.</p>
    </section>

    <div class="od-side">
      <section class="org-panel od-quick" aria-labelledby="od-quick-h">
        <p class="org-k">Scurtături</p>
        <h2 class="org-panel-h" id="od-quick-h">Acțiuni rapide</h2>
        <div class="od-quick-grid">
          <a class="od-q is-primary" href="/organizator/produse?nou=1"><?= v2_ic('plus') ?><span>Produs nou</span></a>
          <a class="od-q" href="/organizator/rezervari"><?= v2_ic('list') ?><span>Rezervări</span></a>
          <a class="od-q" href="/organizator/raport"><?= v2_ic('chart-line-up') ?><span>Raport</span></a>
          <a class="od-q" href="/organizator/locatii"><?= v2_ic('map-pin') ?><span>Locații</span></a>
        </div>
      </section>
      <section class="org-panel od-months" aria-labelledby="od-months-h">
        <div class="org-panel-head">
          <div><p class="org-k">Istoric</p><h2 class="org-panel-h" id="od-months-h">Ultimele luni</h2></div>
        </div>
        <div id="od-months-body"><span class="org-skel od-sk-block"></span></div>
      </section>
    </div>
  </div>

  <div class="od-grid is-b">
    <section class="org-panel od-bookings" aria-labelledby="od-bk-h">
      <div class="org-panel-head">
        <div><p class="org-k">Cine vine</p><h2 class="org-panel-h" id="od-bk-h" tabindex="-1">Rezervări care urmează</h2><p class="org-panel-p" id="od-bk-p">Se încarcă…</p></div>
        <a class="org-more" href="/organizator/rezervari">Toate rezervările<?= v2_ic('arrow-right') ?></a>
      </div>
      <div id="od-bk-body"><span class="org-skel od-sk-row"></span><span class="org-skel od-sk-row"></span><span class="org-skel od-sk-row"></span></div>
    </section>
    <section class="org-panel od-top" aria-labelledby="od-top-h">
      <div class="org-panel-head">
        <div><p class="org-k">Ce se vinde</p><h2 class="org-panel-h" id="od-top-h">Produse de top</h2><p class="org-panel-p" id="od-top-p">Se încarcă…</p></div>
        <a class="org-more" href="/organizator/produse">Toate produsele<?= v2_ic('arrow-right') ?></a>
      </div>
      <div id="od-top-body"><span class="org-skel od-sk-line"></span><span class="org-skel od-sk-line"></span><span class="org-skel od-sk-line"></span><span class="org-skel od-sk-line"></span></div>
    </section>
  </div>
</div>
<?php
v2_org_end();
include __DIR__ . '/../includes/v2/foot.php';
