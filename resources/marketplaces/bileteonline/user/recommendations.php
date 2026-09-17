<?php
/**
 * Customer recommendations: /cont/recomandari (v2 design).
 *
 * Inside the v2 account shell (includes/v2/account.php). Hero with the profile signals behind the list, four counters,
 * filters (search, reason, city, budget, quick pills; kept in the URL), the recommendation cards (match, points,
 * family, reasons, "Nu mă interesează" with undo) and the side column: what the engine used, bonus points, discovery.
 * recommendations.js reads GET /customer/recommendations (scored on the server), /customer/rewards/config and, when the
 * programme makes points expire, the dashboard summary for the points expiring soon.
 *
 * Fixed on the way: the "Expiră" counter read expiring points from /customer/rewards, which never sends them, so it was
 * always 0. The "Control personalizare" checkboxes re-scored the cards in the browser with rules of their own (the
 * server's match scores and reasons were replaced) and saved nothing: the section shows the signals the engine really
 * used and links to the settings where they change. The interest signal showed a category slug ("escape-rooms"); it
 * shows the category name. "Vezi puncte" pointed at the old /cont/punctele-mele alias.
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/api.php';
require_once __DIR__ . '/../includes/nav-helpers.php';
require_once __DIR__ . '/../includes/v2/helpers.php';
require_once __DIR__ . '/../includes/v2/nav.php';
require_once __DIR__ . '/../includes/v2/account.php';

$pageTitleRaw = 'Recomandări pentru tine — ' . SITE_NAME;
$pageDescription = 'Recomandări personalizate bilete.online: activități pe baza orașelor preferate, comenzilor, recenziilor, punctelor și profilului de familie.';
$canonicalUrl = SITE_URL . '/cont/recomandari';
$noindex = true;
$skipPageCache = true;

$v2Styles = ['account.css', 'recommendations.css'];
$v2Scripts = ['account.js', 'recommendations.js'];
$v2LegacyScripts = ['assets/js/config.js', 'assets/js/utils.js', 'assets/js/api.js', 'assets/js/auth.js'];
$v2HeadExtra = v2_account_client_config();
$v2FooterCompact = true;
$rcCaret = v2_ic('caret-down');
$rcPills = [['profile', 'Pentru profilul tău'], ['family', 'Cu copiii'], ['points', 'Folosește puncte'], ['weather', 'Weekend / vreme']];

include __DIR__ . '/../includes/v2/head.php';
include __DIR__ . '/../includes/v2/header.php';
?>
<main id="main" tabindex="-1" class="page-main acc-page">
  <div class="wrap">
    <?php v2_account_start('recommendations'); ?>

    <!-- not signed in -->
    <section class="acc-guard" id="rc-guard" hidden aria-labelledby="rc-guard-h">
      <span class="acc-guard-ic" aria-hidden="true"><?= v2_ic('lock-simple') ?></span>
      <h1 id="rc-guard-h">Trebuie să fii autentificat</h1>
      <p>Intră în cont pentru a-ți vedea recomandările.</p>
      <a class="btn btn-primary" href="/autentificare?redirect=%2Fcont%2Frecomandari">Intră în cont<?= v2_ic('arrow-right') ?></a>
    </section>

    <div class="acc-body" id="rc-content">
      <!-- HERO -->
      <section class="acc-hero rc-hero" aria-labelledby="rc-h">
        <div>
          <p class="acc-kicker">Personal discovery</p>
          <h1 class="acc-h" id="rc-h">Recomandări pentru tine</h1>
          <p class="acc-lead">Activități recomandate pe baza orașelor preferate, comenzilor, recenziilor, punctelor disponibile, profilului de familie și intereselor tale.</p>
          <div class="rc-cta">
            <a class="btn btn-light" href="#recomandari"><?= v2_ic('star') ?>Vezi recomandări</a>
            <a class="btn btn-outline-light" href="/cont/setari#profil-preferinte">Rafinează profilul</a>
          </div>
        </div>
        <article class="rc-signals" aria-labelledby="rc-signals-h">
          <p class="acc-k">Profile signals</p>
          <h2 id="rc-signals-h">De ce vezi aceste recomandări?</h2>
          <dl class="rc-sig">
            <div><dt>Oraș preferat</dt><dd id="rc-sig-city">…</dd></div>
            <div><dt>Interes</dt><dd id="rc-sig-interest">…</dd></div>
            <div><dt>Familie</dt><dd id="rc-sig-family">…</dd></div>
            <div><dt>Puncte</dt><dd id="rc-sig-points">…</dd></div>
          </dl>
        </article>
      </section>

      <!-- COUNTERS -->
      <section class="rc-stats" aria-label="Pe scurt">
        <article class="rc-stat"><p class="acc-k">Match bun</p><p class="rc-stat-v" id="rc-s-good">—</p><p class="rc-stat-p">activități potrivite</p></article>
        <article class="rc-stat is-mint"><p class="acc-k">Cu puncte</p><p class="rc-stat-v" id="rc-s-points">—</p><p class="rc-stat-p">poți aplica reducere</p></article>
        <article class="rc-stat"><p class="acc-k">Familie</p><p class="rc-stat-v" id="rc-s-family">—</p><p class="rc-stat-p">potrivite pentru copii</p></article>
        <article class="rc-stat is-rose"><p class="acc-k">Expiră</p><p class="rc-stat-v" id="rc-s-exp">—</p><p class="rc-stat-p" id="rc-s-exp-p">se verifică…</p></article>
      </section>
      <div class="rc-flash" id="rc-flash" role="status" aria-live="polite" hidden><span id="rc-flash-t"></span><button class="rc-flash-undo" type="button" id="rc-flash-undo" hidden>Anulează</button></div>

      <!-- FILTERS -->
      <section class="acc-panel rc-filters" aria-label="Filtrează recomandările">
        <div class="rc-filter-row">
          <div class="acc-field is-search">
            <label for="rc-q">Caută</label>
            <span class="acc-input"><?= v2_ic('magnifying-glass') ?><input id="rc-q" type="search" maxlength="80" placeholder="Escape room, copii, muzeu, Brașov…" autocomplete="off"></span>
          </div>
          <div class="acc-field">
            <label for="rc-reason">Motiv</label>
            <span class="acc-select"><select id="rc-reason"><option value="all">Toate</option><option value="profile">Profil</option><option value="family">Familie</option><option value="points">Puncte</option><option value="history">Istoric</option><option value="weather">Vreme/sezon</option></select><?= $rcCaret ?></span>
          </div>
          <div class="acc-field">
            <label for="rc-city">Oraș</label>
            <span class="acc-select"><select id="rc-city"><option value="all">Toate</option></select><?= $rcCaret ?></span>
          </div>
          <div class="acc-field">
            <label for="rc-budget">Buget</label>
            <span class="acc-select"><select id="rc-budget"><option value="all">Orice</option><option value="low">sub 50 lei</option><option value="mid">50–120 lei</option><option value="high">120+ lei</option></select><?= $rcCaret ?></span>
          </div>
          <button class="btn btn-ghost rc-reset" type="button" id="rc-reset">Reset</button>
        </div>
        <div class="rc-pills" role="group" aria-label="Filtre rapide">
          <?php foreach ($rcPills as [$rcKey, $rcLabel]): ?>
          <button class="rc-pill is-<?= $rcKey ?>" type="button" data-reason="<?= $rcKey ?>" aria-pressed="false"><?= v2_e($rcLabel) ?></button>
          <?php endforeach; ?>
        </div>
      </section>

      <!-- RESULTS + SIDE -->
      <section class="rc-layout" id="recomandari" aria-labelledby="rc-results-h">
        <div class="rc-results">
          <div class="rc-results-head">
            <div><p class="acc-k">Rezultate</p><h2 id="rc-results-h" tabindex="-1">Recomandări</h2></div>
            <div class="rc-results-actions">
              <button class="btn btn-ghost" type="button" id="rc-unhide" hidden>Arată ascunse</button>
              <a class="btn btn-primary" href="/cont/setari#profil-preferinte">Rafinează profilul</a>
            </div>
          </div>

          <div class="rc-skels" id="rc-skel" aria-hidden="true"><div class="rc-skel"></div><div class="rc-skel"></div></div>
          <div class="rc-empty is-error" id="rc-error" hidden>
            <h3>Nu am putut încărca recomandările</h3>
            <p>Verifică conexiunea și încearcă din nou.</p>
            <button class="btn btn-ghost" type="button" id="rc-retry">Reîncearcă</button>
          </div>
          <ul class="rc-grid" id="rc-grid" hidden></ul>
          <div class="rc-empty" id="rc-empty" hidden>
            <h3 id="rc-empty-h">Nu am găsit recomandări.</h3>
            <p id="rc-empty-p">Schimbă filtrele sau completează profilul pentru sugestii mai bune.</p>
            <div class="rc-empty-actions">
              <button class="btn btn-ghost" type="button" id="rc-empty-reset">Resetează filtrele</button>
              <a class="btn btn-primary" href="/cont/setari#profil-preferinte">Adaugă preferințe</a>
            </div>
          </div>
        </div>

        <aside class="rc-aside" aria-label="Despre recomandări">
          <div class="rc-box">
            <p class="acc-k">Control personalizare</p>
            <h2>Ce influențează recomandările</h2>
            <ul class="rc-controls">
              <li id="rc-c-history"><span class="rc-dot" aria-hidden="true"></span><div><b>Istoric comenzi</b><small id="rc-c-history-t">activități cumpărate anterior</small></div></li>
              <li id="rc-c-reviews" class="is-neutral"><span class="rc-dot" aria-hidden="true"></span><div><b>Recenzii</b><small>ratinguri și feedback · <a href="/cont/recenzii">recenziile tale</a></small></div></li>
              <li id="rc-c-family"><span class="rc-dot" aria-hidden="true"></span><div><b>Profil familie</b><small id="rc-c-family-t">vârste copii / tipuri activități</small></div></li>
              <li id="rc-c-cities"><span class="rc-dot" aria-hidden="true"></span><div><b>Orașe favorite</b><small id="rc-c-cities-t">încă nimic ales</small></div></li>
            </ul>
            <p class="rc-note">Recomandările se calculează din aceste semnale. Le schimbi din setările contului.</p>
            <a class="btn btn-primary" href="/cont/setari#profil-preferinte">Editează semnalele</a>
          </div>
          <div class="rc-box is-mint">
            <p class="acc-k">Puncte bonus</p>
            <h2>Ai <span id="rc-p-points">0</span> puncte</h2>
            <p>Poți reduce următoarea comandă cu aproximativ <strong id="rc-p-lei">0 lei</strong>, în funcție de regulile checkout-ului.</p>
            <a class="btn btn-primary" href="/cont/puncte">Vezi puncte</a>
          </div>
          <div class="rc-box is-deep">
            <p class="acc-k">Discovery</p>
            <h2>Vrei altceva?</h2>
            <p>Explorează toate categoriile sau caută manual activități în orașul tău.</p>
            <a class="btn btn-light" href="/categorii">Vezi categorii<?= v2_ic('arrow-right') ?></a>
          </div>
        </aside>
      </section>
    </div>

    <?php v2_account_end(); ?>
  </div>
</main>
<?php include __DIR__ . '/../includes/v2/footer.php'; ?>
