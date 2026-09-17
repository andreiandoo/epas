<?php
/**
 * Customer tickets: /cont/bilete and /cont/biletele-mele (v2 design).
 *
 * Inside the v2 account shell (includes/v2/account.php). Sections: hero with the next ticket's QR, four counters,
 * filters (search, status, city, sort, quick filters), the tickets, three info cards (QR, beneficiaries, refunds), the
 * QR dialog, and the login prompt for visitors without a customer session. tickets.js loads
 * GET /customer/tickets/all?filter=all and keeps the filters in the address bar.
 *
 * Fixed on the way: the QR library URL was a 404 (the big QR never appeared); calendar links used a proxy action that
 * doesn't exist (now an .ics file made in the browser); "Editează nume" and "Retur" opened pages that don't exist (now
 * the contact form with the matching reason); the PDF link downloaded any ticket id without logging in (the proxy now
 * checks the ticket is the signed-in customer's, and the page sends the session token).
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/api.php';
require_once __DIR__ . '/../includes/nav-helpers.php';
require_once __DIR__ . '/../includes/v2/helpers.php';
require_once __DIR__ . '/../includes/v2/nav.php';
require_once __DIR__ . '/../includes/v2/account.php';

$pageTitleRaw = 'Biletele mele — ' . SITE_NAME;
$pageDescription = 'Biletele tale pe bilete.online: QR, PDF, beneficiari, calendar, status și acțiuni de retur sau protecție bilet.';
$canonicalUrl = SITE_URL . '/cont/bilete';
$noindex = true;
$skipPageCache = true;

$v2Styles = ['account.css', 'tickets.css'];
$v2Scripts = ['vendor/qrcode.js', 'account.js', 'tickets.js'];
$v2LegacyScripts = ['assets/js/config.js', 'assets/js/utils.js', 'assets/js/api.js', 'assets/js/auth.js'];
$v2HeadExtra = v2_account_client_config();
$v2FooterCompact = true;

include __DIR__ . '/../includes/v2/head.php';
include __DIR__ . '/../includes/v2/header.php';
?>
<main id="main" tabindex="-1" class="page-main acc-page">
  <div class="wrap">
    <?php v2_account_start('tickets'); ?>

    <!-- not signed in -->
    <section class="acc-guard" id="tk-guard" hidden aria-labelledby="tk-guard-h">
      <span class="acc-guard-ic" aria-hidden="true"><?= v2_ic('lock-simple') ?></span>
      <h1 id="tk-guard-h">Trebuie să fii autentificat</h1>
      <p>Intră în cont pentru a vedea biletele.</p>
      <a class="btn btn-primary" href="/autentificare?redirect=%2Fcont%2Fbilete">Intră în cont<?= v2_ic('arrow-right') ?></a>
    </section>

    <div id="tk-content">
      <!-- HERO -->
      <section class="tk-hero" aria-labelledby="tk-h">
        <div class="tk-hero-copy">
          <p class="tk-kicker">Bilete client</p>
          <h1 class="tk-h" id="tk-h">Biletele mele</h1>
          <p class="tk-lead">Toate biletele tale într-un singur loc: QR, PDF, beneficiari, calendar, status, acces și opțiuni de retur.</p>
          <div class="tk-cta">
            <a class="btn btn-light" href="#bilete"><?= v2_ic('ticket') ?>Vezi biletele</a>
            <a class="btn btn-outline-light" href="/recuperare-comanda">Recuperează comandă</a>
          </div>
        </div>
        <article class="tk-next" aria-labelledby="tk-next-t">
          <p class="tk-k">Next QR</p>
          <h2 class="tk-next-t" id="tk-next-t">În curând</h2>
          <p class="tk-next-sub" id="tk-next-sub">Nu ai bilete viitoare</p>
          <div class="tk-next-row">
            <div>
              <p class="tk-next-n"><span id="tk-next-n">0</span>x</p>
              <p class="tk-next-l">bilete viitoare</p>
            </div>
            <button class="tk-next-qr" type="button" id="tk-next-qr" disabled aria-label="QR-ul următorului bilet">
              <span class="tk-qr-slot" id="tk-next-qr-slot"><?= v2_ic('qr-code') ?></span>
              <small>QR open</small>
              <i class="tk-scan" aria-hidden="true"></i>
            </button>
          </div>
        </article>
      </section>

      <!-- COUNTERS -->
      <section class="tk-stats" aria-label="Pe scurt">
        <article class="tk-stat"><p class="tk-k">Bilete viitoare</p><p class="tk-stat-v" id="tk-c-upcoming">0</p><p class="tk-stat-p" id="tk-c-activities">în 0 activități</p></article>
        <article class="tk-stat is-mint"><p class="tk-k">Valide</p><p class="tk-stat-v" id="tk-c-valid">0</p><p class="tk-stat-p">gata de scanare</p></article>
        <article class="tk-stat"><p class="tk-k">Scanate</p><p class="tk-stat-v" id="tk-c-used">0</p><p class="tk-stat-p">istoric complet</p></article>
        <article class="tk-stat is-warm"><p class="tk-k">Acțiuni</p><p class="tk-stat-v" id="tk-c-action">0</p><p class="tk-stat-p">nume de completat</p></article>
      </section>

      <!-- FILTERS -->
      <section class="tk-panel" aria-label="Filtre bilete">
        <form class="tk-filter-row" id="tk-filters" role="search">
          <label class="tk-field is-search"><span>Caută bilet</span>
            <span class="tk-input"><?= v2_ic('magnifying-glass') ?><input type="search" id="tk-q" maxlength="100" placeholder="Activitate, oraș, beneficiar, cod bilet..." autocomplete="off" enterkeyhint="search"></span>
          </label>
          <label class="tk-field"><span>Status</span>
            <span class="tk-select"><select id="tk-status">
              <option value="all">Toate</option>
              <option value="upcoming" selected>Viitoare</option>
              <option value="valid">Valide</option>
              <option value="used">Scanate</option>
              <option value="expired">Expirate</option>
              <option value="action">Necesită acțiune</option>
            </select><?= v2_ic('caret-down') ?></span>
          </label>
          <label class="tk-field"><span>Oraș</span>
            <span class="tk-select"><select id="tk-city"><option value="all">Toate orașele</option></select><?= v2_ic('caret-down') ?></span>
          </label>
          <label class="tk-field"><span>Sortare</span>
            <span class="tk-select"><select id="tk-sort">
              <option value="soon">Cele mai apropiate</option>
              <option value="newest">Cele mai noi</option>
              <option value="activity">Activitate A-Z</option>
            </select><?= v2_ic('caret-down') ?></span>
          </label>
          <button class="btn btn-ghost tk-reset" type="button" id="tk-reset">Reset</button>
        </form>
        <div class="tk-pills" role="group" aria-label="Filtre rapide">
          <button class="tk-pill" type="button" data-status="upcoming" aria-pressed="true">Viitoare</button>
          <button class="tk-pill is-valid" type="button" data-status="valid" aria-pressed="false">Valide</button>
          <button class="tk-pill is-action" type="button" data-status="action" aria-pressed="false">Necesită acțiune</button>
          <button class="tk-pill" type="button" data-status="used" aria-pressed="false">Scanate</button>
        </div>
      </section>

      <!-- TICKETS -->
      <section class="tk-results" id="bilete" aria-labelledby="tk-count">
        <div class="tk-results-head">
          <div><p class="tk-k">Rezultate</p><h2 class="tk-count" id="tk-count">0 bilete</h2></div>
          <div class="tk-bulk">
            <button class="btn btn-ghost" type="button" id="tk-all-pdf" disabled>Descarcă toate PDF</button>
            <button class="btn btn-primary" type="button" id="tk-all-cal" disabled><?= v2_ic('calendar-blank') ?>Adaugă toate în calendar</button>
          </div>
        </div>
        <p class="tk-status" id="tk-status-line" role="status"></p>
        <div class="tk-skel" id="tk-skel" aria-hidden="true"><i></i><i></i></div>
        <ul class="tk-list" id="tk-list" hidden></ul>
        <div class="tk-empty" id="tk-empty" hidden>
          <span class="tk-empty-ic" aria-hidden="true"><?= v2_ic('ticket') ?></span>
          <b id="tk-empty-h">Nu ai bilete încă</b>
          <p id="tk-empty-p">Descoperă activități și rezervă online.</p>
          <a class="btn btn-primary" id="tk-empty-cta" href="/categorii">Descoperă activități</a>
          <button class="btn btn-primary" id="tk-empty-reset" type="button" hidden>Resetează</button>
        </div>
        <div class="tk-error" id="tk-error" hidden>
          <b>Nu am putut încărca biletele.</b>
          <p>Verifică conexiunea și încearcă din nou.</p>
          <button class="btn btn-ghost" type="button" id="tk-retry">Încearcă din nou</button>
        </div>
      </section>

      <!-- GOOD TO KNOW -->
      <section class="tk-info" aria-label="Bine de știut">
        <article class="tk-panel">
          <p class="tk-k">Acces</p>
          <h2>Cum folosești QR-ul?</h2>
          <p>Arată QR-ul de pe telefon, cu luminozitate ridicată. Fiecare bilet are cod unic. Dacă nu se citește din prima, mai încearcă — operatorul are scaner manual ca rezervă.</p>
        </article>
        <article class="tk-panel is-mint">
          <p class="tk-k">Beneficiari</p>
          <h2>Nume diferite?</h2>
          <p>Pentru grupuri, poți avea beneficiari diferiți pe fiecare bilet, dacă activitatea o cere. Editează numele înainte de eveniment ca să eviți probleme la intrare.</p>
        </article>
        <article class="tk-panel is-warm">
          <p class="tk-k">Retur</p>
          <h2>Nu mai poți ajunge?</h2>
          <p>Verifică dacă biletul este eligibil pentru retur. Dacă ai protecție bilet activă (cumpărată la checkout), poți recupera banii fără justificare.</p>
        </article>
      </section>
    </div>

    <dialog class="tk-dialog" id="tk-dialog" aria-labelledby="tk-d-title">
      <div class="tk-d-head">
        <div>
          <p class="tk-k">QR bilet</p>
          <h2 id="tk-d-title">Bilet</h2>
          <p class="tk-d-sub" id="tk-d-name">Beneficiar necompletat</p>
        </div>
        <button class="tk-d-close" type="button" id="tk-d-close" aria-label="Închide"><?= v2_ic('x') ?></button>
      </div>
      <div class="tk-d-stage"><div class="tk-d-qr" id="tk-d-qr"></div><i class="tk-scan" aria-hidden="true"></i></div>
      <p class="tk-d-code" id="tk-d-code"></p>
      <p class="tk-d-tip">Mărește luminozitatea ecranului înainte de scanare.</p>
      <p class="tk-d-msg" id="tk-d-msg" role="status"></p>
      <div class="tk-d-cta">
        <button class="btn btn-primary" type="button" id="tk-d-pdf">Descarcă PDF</button>
        <button class="btn btn-ghost" type="button" id="tk-d-cal"><?= v2_ic('calendar-blank') ?>Adaugă în calendar</button>
      </div>
    </dialog>

    <?php v2_account_end(); ?>
  </div>
</main>
<?php include __DIR__ . '/../includes/v2/footer.php'; ?>
