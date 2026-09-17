<?php
/**
 * Cookie policy: /cookies (v2 design).
 *
 * The policy text, the four categories and the ways to change the choice. The banner and the settings dialog are
 * global (includes/v2/footer.php + base.js); the buttons here open that dialog through data-cc-action="open", so
 * nothing is wiped and the page doesn't reload (the old page deleted the saved choice and reloaded to bring the banner
 * back). cookies.js shows, on each category card and under the hero buttons, what the visitor has allowed right now,
 * and follows the dialog as it saves.
 *
 * The old text pointed to a floating cookie button that the v2 pages don't have; that sentence now names the buttons
 * on this page and the Cookies link in the footer.
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/api.php';

// 30-minute page cache: static content (the visitor's own choice is read in the browser).
$pageCacheTTL = 1800;
require_once __DIR__ . '/includes/page-cache.php';

require_once __DIR__ . '/includes/nav-helpers.php';
require_once __DIR__ . '/includes/v2/helpers.php';
require_once __DIR__ . '/includes/v2/nav.php';

$cpCards = [
    ['essential', 'Necesare', 'Esențiale', 'Coș, checkout, login, sesiune, securitate, memorarea consimțământului și măsurare de audiență first-party strict agregată. Mereu active.', ''],
    ['analytics', 'Măsurare', 'Analytics', 'Instrumente terțe (Google Analytics), conversii și erori. Pornite doar cu acordul tău.', 'is-soft'],
    ['personalization', 'Recomandări', 'Personalizare', 'Recomandări după oraș/categorii vizitate, filtre preferate. Local, fără reclame externe.', 'is-mint'],
    ['marketing', 'Campanii', 'Marketing', 'Pixeli Meta / Google / TikTok pentru campanii și remarketing. Doar după accept explicit.', 'is-deep'],
];

$pageTitleRaw = 'Politica de cookies — ' . SITE_NAME;
$pageDescription = 'Cum folosește bilete.online cookies pentru funcționarea platformei, analytics, personalizare și marketing. Cum îți gestionezi preferințele.';
$canonicalUrl = SITE_URL . '/cookies';

$v2Styles = ['cookies.css'];
$v2Scripts = ['cookies.js'];
$v2HeaderOverlay = true;

include __DIR__ . '/includes/v2/head.php';
include __DIR__ . '/includes/v2/header.php';
?>
<main id="main" tabindex="-1">
  <section class="cp-hero" aria-labelledby="cp-h">
    <svg class="deco-arches" viewBox="0 0 400 400" aria-hidden="true" focusable="false"><path d="M40 400V200a160 160 0 0 1 320 0v200"/><path d="M90 400V200a110 110 0 0 1 220 0v200"/><path d="M140 400V200a60 60 0 0 1 120 0v200"/></svg>
    <div class="cp-in">
      <p class="cp-kicker">Politică cookies</p>
      <h1 class="cp-h" id="cp-h">Cum folosim cookies pe bilete.online</h1>
      <p class="cp-lead">Folosim cookies pentru funcționarea platformei (coș, checkout, login) și, doar cu acordul tău, pentru analytics, personalizare și marketing. Poți schimba alegerea oricând din banner sau din butonul de mai jos.</p>
      <div class="cp-cta">
        <button class="btn btn-light" type="button" data-cc-action="open"><?= v2_ic('lock-simple') ?>Schimbă preferințele</button>
        <a class="btn btn-outline-light" href="/confidentialitate">Politica de confidențialitate</a>
      </div>
      <p class="cp-status" id="cp-status" role="status"></p>
    </div>
    <div id="hdr-sentinel" aria-hidden="true"></div>
  </section>

  <section class="sec cp-body" aria-label="Categorii și politică">
    <div class="wrap">
      <div class="cp-cards">
        <?php foreach ($cpCards as [$cardKey, $cardK, $cardTitle, $cardText, $cardTone]): ?>
        <article class="cp-card <?= $cardTone ?>">
          <div class="cp-card-top">
            <p class="cp-card-k"><?= v2_e($cardK) ?></p>
            <span class="cp-pill" data-cp-state="<?= $cardKey ?>" data-on="<?= $cardKey === 'essential' ? 'true' : 'false' ?>"><?= $cardKey === 'essential' ? 'Mereu active' : 'Oprit' ?></span>
          </div>
          <h2><?= v2_e($cardTitle) ?></h2>
          <p><?= v2_e($cardText) ?></p>
        </article>
        <?php endforeach; ?>
      </div>

      <div class="cp-prose">
        <h2>Ce sunt cookies?</h2>
        <p>Cookies sunt fișiere mici stocate de browser pentru a-ți face experiența online predictibilă: coșul tău rămâne plin, sesiunea de login persistă, preferințele se țin minte.</p>

        <h2>Categoriile noastre de cookies</h2>
        <p><strong>Esențiale</strong> — necesare pentru funcționarea platformei (coș, checkout, login, securitate) plus măsurarea de audiență first-party, strict agregată: fără partajare cu terți, fără urmărire cross-site, cu IP anonimizat și retenție limitată. Conform ghidului CNIL privind audience measurement, această măsurare poate fi exceptată de la consimțământ, așa că rămâne mereu activă. Aceste cookies nu pot fi dezactivate.</p>
        <p><strong>Analytics</strong> — instrumente terțe (de ex. Google Analytics), conversii și rapoarte avansate. Se activează doar cu acordul tău.</p>
        <p><strong>Personalizare</strong> — folosim preferințele tale (orașul, categoriile vizitate) pentru recomandări mai relevante. Opțional.</p>
        <p><strong>Marketing</strong> — pixeli pentru campanii Meta, Google Ads, TikTok și audiențe personalizate. Doar cu accept explicit.</p>

        <h2>Cum îți gestionezi alegerea</h2>
        <p>La prima vizită apare un banner unde poți alege: <strong>Acceptă toate</strong>, <strong>Refuză opționale</strong> sau <strong>Personalizează</strong>.</p>
        <p>Poți schimba oricând setările din butonul „Schimbă preferințele” din partea de sus a acestei pagini sau din cel de la finalul ei. Pagina aceasta este legată din subsolul oricărei pagini, la <strong>Cookies</strong>.</p>

        <h2>Drepturile tale</h2>
        <p>Conform GDPR, ai dreptul să fii informat despre prelucrarea datelor tale, să ai acces la ele, să le rectifici, să le ștergi sau să te opui prelucrării. Pentru orice solicitare GDPR, scrie-ne pe <a href="/contact">pagina de contact</a> sau consultă <a href="/confidentialitate">Politica de confidențialitate</a>.</p>
      </div>

      <div class="cp-final">
        <div>
          <p class="cp-final-k">Schimbă oricând</p>
          <h2>Vrei să-ți modifici preferințele?</h2>
        </div>
        <button class="btn cp-btn-white" type="button" data-cc-action="open">Deschide setările cookies</button>
      </div>
    </div>
  </section>
</main>
<?php include __DIR__ . '/includes/v2/footer.php'; ?>
