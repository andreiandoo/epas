<?php
/**
 * Customer dashboard: /cont (v2 design).
 *
 * Inside the v2 account shell (includes/v2/account.php). Sections: hero with the next ticket (or the points balance),
 * four stats, upcoming tickets, the three-step personalisation checklist, the referral link, recommendations, recent
 * orders, and three utility cards (support, reviews, gift card). Visitors without a customer session see the login
 * prompt straight away.
 *
 * dashboard.js loads everything with one request (GET /customer/dashboard-bundle) and falls back to the separate
 * endpoints if the bundle fails. Upcoming tickets come from core as `upcoming_events[]` with the details under `event`;
 * the old page looked for `events` / flat fields, so that list and the next-ticket card were always empty.
 * "Deschide bilet" opens /cont/bilete and "Calendar" downloads an .ics file: the old links went to /cont/comenzi/{id},
 * which has no route. The gift card card links to /voucher (/cont/carduri-cadou has no page).
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/api.php';
require_once __DIR__ . '/../includes/nav-helpers.php';
require_once __DIR__ . '/../includes/v2/helpers.php';
require_once __DIR__ . '/../includes/v2/nav.php';
require_once __DIR__ . '/../includes/v2/account.php';

$pageTitleRaw = 'Dashboard client — ' . SITE_NAME;
$pageDescription = 'Dashboard client bilete.online: bilete, comenzi, puncte bonus, carduri cadou, recomandări personalizate, recenzii, suport și setări cont.';
$canonicalUrl = SITE_URL . '/cont';
$noindex = true;
$skipPageCache = true;

$v2Styles = ['account.css', 'dashboard.css'];
$v2Scripts = ['account.js', 'dashboard.js'];
$v2LegacyScripts = ['assets/js/config.js', 'assets/js/utils.js', 'assets/js/api.js', 'assets/js/auth.js'];
$v2HeadExtra = v2_account_client_config();
$v2FooterCompact = true;

include __DIR__ . '/../includes/v2/head.php';
include __DIR__ . '/../includes/v2/header.php';
?>
<main id="main" tabindex="-1" class="page-main acc-page">
  <div class="wrap">
    <?php v2_account_start('dashboard'); ?>

    <!-- not signed in -->
    <section class="acc-guard" id="db-guard" hidden aria-labelledby="db-guard-h">
      <span class="acc-guard-ic" aria-hidden="true"><?= v2_ic('lock-simple') ?></span>
      <h1 id="db-guard-h">Trebuie să fii autentificat</h1>
      <p>Intră în cont pentru a vedea dashboardul.</p>
      <a class="btn btn-primary" href="/autentificare?redirect=%2Fcont">Intră în cont<?= v2_ic('arrow-right') ?></a>
    </section>

    <div id="db-content">
      <!-- HERO -->
      <section class="db-hero" aria-labelledby="db-greet">
        <div class="db-hero-copy">
          <p class="db-kicker">Dashboard client</p>
          <h1 class="db-h" id="db-greet">Salut, <span id="db-first">prieten</span>. <span id="db-greet-tail">Bine ai revenit.</span></h1>
          <p class="db-lead">Aici vezi ce urmează, ce ai cumpărat, câte puncte ai, ce recomandări ți se potrivesc și ce mai ai de rezolvat înainte de următoarea activitate.</p>
          <div class="db-cta">
            <a class="btn btn-light" href="/cont/biletele-mele"><?= v2_ic('ticket') ?>Vezi biletele</a>
            <a class="btn btn-outline-light" href="/cont/recomandari">Recomandări</a>
          </div>
        </div>
        <div class="db-hero-card">
          <div class="db-skel is-card" id="db-hero-skel" aria-hidden="true"></div>
          <article class="db-next" id="db-next" hidden>
            <p class="db-card-k">Next ticket</p>
            <h2 class="db-next-t" id="db-next-title"></h2>
            <p class="db-next-loc" id="db-next-loc"></p>
            <div class="db-next-when">
              <div><p class="db-next-wd" id="db-next-weekday"></p><p class="db-next-time" id="db-next-time"></p><p class="db-next-date" id="db-next-date"></p></div>
              <span class="db-qr" aria-hidden="true"><?= v2_ic('qr-code') ?><small>QR ready</small></span>
            </div>
            <div class="db-next-cta">
              <a class="btn btn-primary" id="db-next-open" href="/cont/bilete">Deschide bilet</a>
              <button class="btn btn-ghost" type="button" id="db-next-cal"><?= v2_ic('calendar-blank') ?>Calendar</button>
            </div>
          </article>
          <article class="db-pointscard" id="db-points-card" hidden>
            <p class="db-card-k">Puncte bonus</p>
            <p class="db-points-big" id="db-points-big">0</p>
            <p class="db-points-lei">≈ <span id="db-points-lei">0 lei</span> reducere</p>
            <a class="btn btn-primary" href="/cont/punctele-mele">Folosește punctele</a>
          </article>
        </div>
      </section>

      <!-- STATS -->
      <section class="db-stats" aria-label="Pe scurt">
        <article class="db-stat"><p class="db-card-k">Bilete viitoare</p><p class="db-stat-v" id="db-stat-tickets">0</p><p class="db-stat-p" id="db-stat-activities">0 activități confirmate</p></article>
        <article class="db-stat is-mint"><p class="db-card-k">Puncte bonus</p><p class="db-stat-v" id="db-stat-points">0</p><p class="db-stat-p">≈ <span id="db-stat-points-lei">0 lei</span> reducere</p></article>
        <article class="db-stat"><p class="db-card-k">Comenzi</p><p class="db-stat-v" id="db-stat-orders">0</p><p class="db-stat-p" id="db-stat-orders-last">fără comenzi încă</p></article>
        <article class="db-stat is-warm"><p class="db-card-k">Profil</p><p class="db-stat-v"><span id="db-stat-profile">0</span>%</p><p class="db-stat-p">completează preferințele</p></article>
      </section>

      <!-- UPCOMING + PERSONALISATION + REFERRAL -->
      <section class="db-row is-main">
        <div class="db-panel">
          <div class="db-panel-head">
            <div><p class="db-card-k">Urmează</p><h2>Bilete viitoare</h2></div>
            <a class="btn btn-ghost" href="/cont/biletele-mele">Toate biletele</a>
          </div>
          <div class="db-skel-list" id="db-upcoming-skel" aria-hidden="true"><i class="db-skel"></i><i class="db-skel"></i></div>
          <div class="db-empty" id="db-upcoming-empty" hidden>
            <span class="db-empty-ic" aria-hidden="true"><?= v2_ic('ticket') ?></span>
            <b>Nu ai bilete viitoare</b>
            <p>Descoperă activități și rezervă online.</p>
            <a class="btn btn-primary" href="/categorii">Descoperă activități</a>
          </div>
          <ul class="db-upcoming" id="db-upcoming" hidden></ul>
        </div>

        <aside class="db-side">
          <div class="db-panel">
            <p class="db-card-k">Personalizare</p>
            <h2>Recomandări mai bune în 3 pași</h2>
            <ul class="db-tasks" id="db-tasks"></ul>
            <a class="btn btn-primary db-mt" href="/cont/setari#profil-preferinte">Completează profilul</a>
          </div>
          <div class="db-panel is-mint">
            <p class="db-card-k">Afiliere</p>
            <h2>Invită prieteni. Primești puncte.</h2>
            <p class="db-p">Distribuie linkul tău și primești puncte bonus când prietenii cumpără prima activitate eligibilă.</p>
            <label class="db-sr" for="db-ref-url">Linkul tău de invitație</label>
            <input class="db-ref" id="db-ref-url" type="text" readonly value="bilete.online/r/—">
            <div class="db-ref-cta">
              <button class="btn btn-primary" type="button" id="db-ref-copy">Copiază</button>
              <a class="btn btn-ghost" href="/cont/punctele-mele#afiliere">Detalii</a>
            </div>
            <p class="db-ref-status" id="db-ref-status" role="status"></p>
          </div>
        </aside>
      </section>

      <!-- RECOMMENDATIONS + ORDERS -->
      <section class="db-row">
        <div class="db-panel">
          <div class="db-panel-head">
            <div><p class="db-card-k">Pentru tine</p><h2>Recomandări</h2></div>
            <a class="db-link" href="/cont/recomandari">Vezi tot<?= v2_ic('arrow-right') ?></a>
          </div>
          <div class="db-skel-grid" id="db-recos-skel" aria-hidden="true"><i class="db-skel is-tall"></i><i class="db-skel is-tall"></i></div>
          <p class="db-empty is-inline" id="db-recos-empty" hidden>Adaugă preferințe în <a href="/cont/setari#profil-preferinte">Setări</a> ca să primești recomandări.</p>
          <ul class="db-recos" id="db-recos" hidden></ul>
        </div>

        <div class="db-panel">
          <div class="db-panel-head">
            <div><p class="db-card-k">Istoric</p><h2>Comenzi recente</h2></div>
            <a class="db-link" href="/cont/comenzile-mele">Toate comenzile<?= v2_ic('arrow-right') ?></a>
          </div>
          <div class="db-skel-list is-thin" id="db-orders-skel" aria-hidden="true"><i class="db-skel"></i><i class="db-skel"></i></div>
          <p class="db-empty is-inline" id="db-orders-empty" hidden>Nu ai comenzi încă.</p>
          <div class="db-table-wrap" id="db-orders-table" hidden>
            <table class="db-table">
              <thead><tr><th scope="col">Comandă</th><th scope="col" class="is-date">Data</th><th scope="col">Total</th><th scope="col">Status</th></tr></thead>
              <tbody id="db-orders"></tbody>
            </table>
          </div>
        </div>
      </section>

      <!-- UTILITY -->
      <section class="db-utility" aria-label="Alte lucruri de rezolvat">
        <article class="db-panel">
          <p class="db-card-k">Suport</p>
          <h2 id="db-u-support-h">Niciun tichet activ</h2>
          <p class="db-p" id="db-u-support-p">Deschide un tichet dacă ai nelămuriri.</p>
          <a class="btn btn-ghost db-mt" href="/cont/tichete-support">Vezi tichete</a>
        </article>
        <article class="db-panel">
          <p class="db-card-k">Recenzii</p>
          <h2 id="db-u-reviews-h">Toate scrise</h2>
          <p class="db-p" id="db-u-reviews-p">Mulțumim că împărtășești experiențele tale.</p>
          <a class="btn btn-ghost db-mt" href="/cont/recenzii">Scrie recenzie</a>
        </article>
        <article class="db-panel is-gift" id="db-u-gift">
          <p class="db-card-k">Card cadou</p>
          <h2 id="db-u-gift-h">Verifică un card cadou</h2>
          <p class="db-p" id="db-u-gift-p">Introdu codul cardului pentru a vedea soldul.</p>
          <a class="btn btn-ghost db-mt" href="/voucher">Verifică sold</a>
        </article>
      </section>
    </div>

    <?php v2_account_end(); ?>
  </div>
</main>
<?php include __DIR__ . '/../includes/v2/footer.php'; ?>
