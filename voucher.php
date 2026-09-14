<?php
/**
 * Gift card balance check: /voucher and /verifica-card-cadou (v2 design).
 *
 * Public page. The customer enters a gift card code (and the PIN when the card has one); voucher.js posts it to
 * `/customer/gift-cards/check-balance` through the API proxy and shows balance, validity and status. The backend does
 * the validation (marketplace-scoped, code masked in the answer, PIN required when set).
 *
 * Top to bottom: hero (steps + the check card, which turns into the result), FAQ with a link to buy a gift card.
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/api.php';
require_once __DIR__ . '/includes/nav-helpers.php';
require_once __DIR__ . '/includes/v2/helpers.php';
require_once __DIR__ . '/includes/v2/nav.php';

// Prefill from emails: ?cod=XXXX (or ?code=).
$prefillCode = '';
foreach (['cod', 'code'] as $param) {
    if ($prefillCode === '' && is_string($_GET[$param] ?? null)) {
        $prefillCode = strtoupper(substr(preg_replace('/[^A-Za-z0-9\-]/', '', $_GET[$param]), 0, 40));
    }
}

$faqs = [
    ['Unde găsesc codul cardului cadou?', 'Pentru cardurile digitale, codul apare în emailul de cadou. Pentru cele fizice, este tipărit pe card. PIN-ul apare doar pe cardurile fizice, pe spate, sub o folie de răzuit.'],
    ['Cum folosesc cardul la checkout?', 'La finalizarea unei comenzi, introdu codul în câmpul „Card cadou / voucher”. Soldul disponibil se scade automat. Dacă valoarea comenzii e mai mare, plătești diferența cu cardul bancar.'],
    ['Cardul are expirare?', 'Cardurile cadou bilete.online au valabilitate de 12 luni de la emitere. După această dată, soldul rămas nu mai poate fi folosit.'],
    ['Pot folosi cardul în mai multe comenzi?', 'Da. Soldul scade pe fiecare folosire până la epuizare sau expirare. Verifică oricând balanța curentă aici.'],
    ['Cardul nu este valid. Ce fac?', 'Verifică să fi tastat codul corect (atenție la 0 / O sau 1 / I). Dacă tot nu merge, scrie-ne la <a href="/contact">Contact</a> cu codul și emailul cu care a fost primit.'],
];

$pageTitleRaw = 'Verifică un card cadou — ' . SITE_NAME;
$pageDescription = 'Verifică soldul disponibil și valabilitatea unui card cadou bilete.online. Introdu codul și, dacă este necesar, PIN-ul.';
$canonicalUrl = SITE_URL . '/voucher';
$noindex = true;
$skipPageCache = true;

$v2Styles = ['voucher.css'];
$v2Scripts = ['voucher.js'];
$v2LegacyScripts = ['assets/js/config.js', 'assets/js/utils.js', 'assets/js/api.js'];
$v2HeaderOverlay = true;
$v2HeadExtra = '<script>window.BILETEONLINE = ' . json_encode([
    'siteName' => SITE_NAME,
    'siteUrl' => SITE_URL,
    'apiUrl' => '/api/proxy.php',
    'storageUrl' => STORAGE_URL,
    'env' => API_ENV,
    'locale' => SITE_LOCALE,
    'currency' => 'RON',
    'supportEmail' => defined('SUPPORT_EMAIL') ? SUPPORT_EMAIL : '',
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) . ';</script>';

include __DIR__ . '/includes/v2/head.php';
include __DIR__ . '/includes/v2/header.php';
?>
<main id="main" tabindex="-1">
  <section class="vc-hero" aria-labelledby="vc-h">
    <svg class="deco-arches" viewBox="0 0 400 400" aria-hidden="true" focusable="false"><path d="M40 400V200a160 160 0 0 1 320 0v200"/><path d="M90 400V200a110 110 0 0 1 220 0v200"/><path d="M140 400V200a60 60 0 0 1 120 0v200"/></svg>
    <svg class="vc-line draw-clip" viewBox="0 590 3240 310" aria-hidden="true" focusable="false"><use href="#drum-g"/></svg>
    <div class="vc-in">
      <div class="vc-copy">
        <nav class="crumbs" aria-label="Breadcrumb"><a href="/">Acasă</a><span aria-hidden="true">/</span><a href="/ajutor">Ajutor</a><span aria-hidden="true">/</span><span aria-current="page">Verifică voucher</span></nav>
        <p class="vc-kicker">Card cadou · voucher · sold</p>
        <h1 class="vc-h" id="vc-h">Verifică un card cadou.</h1>
        <p class="vc-lead">Introdu codul cardului cadou ca să vezi cât mai are disponibil și până când poate fi folosit. Pentru cardurile cu PIN, ai nevoie și de PIN.</p>
        <ol class="vc-steps">
          <li><small>Pasul 1</small><b>Cod</b></li>
          <li><small>Pasul 2</small><b>Sold</b></li>
          <li><small>Pasul 3</small><b>Folosește</b></li>
        </ol>
      </div>

      <section class="vc-card" id="vc-card" aria-label="Formular verificare voucher">
        <!-- the header turns solid when the white card reaches it (beside the text on desktop, under it on a phone),
             not after the whole hero: its light links would disappear over the card -->
        <div id="hdr-sentinel" aria-hidden="true"></div>
        <!-- FORM -->
        <div id="vc-form-view">
          <p class="vc-card-k">Verifică codul</p>
          <h2 class="vc-card-h">Card cadou</h2>
          <p class="vc-card-p">Codul apare pe cardul fizic sau în emailul de cadou.</p>
          <p class="vc-error" id="vc-error" role="alert" hidden></p>
          <form id="vc-form" novalidate>
            <div class="vc-field">
              <label for="vc-code">Cod card cadou</label>
              <input id="vc-code" name="code" class="is-code" type="text" autocomplete="off" autocapitalize="characters" spellcheck="false" maxlength="40" required placeholder="ex. GIFT-2026-XXXX" value="<?= v2_e($prefillCode) ?>">
            </div>
            <div class="vc-field">
              <label for="vc-pin">PIN (dacă este necesar)</label>
              <input id="vc-pin" name="pin" type="text" inputmode="numeric" autocomplete="off" maxlength="12" placeholder="opțional" aria-describedby="vc-pin-hint">
              <span class="vc-hint" id="vc-pin-hint">Doar cardurile fizice au PIN, tipărit pe spatele cardului.</span>
            </div>
            <button class="btn btn-primary vc-submit" id="vc-submit" type="submit">Verifică soldul</button>
          </form>
          <p class="vc-card-foot">Nu ai încă un card cadou? <a href="/card-cadou">Cumpără unul</a></p>
        </div>

        <!-- RESULT -->
        <div class="vc-result" id="vc-result" data-state="ok" hidden>
          <span class="vc-badge" aria-hidden="true"><svg class="ic is-ok"><use href="#i-check"/></svg><svg class="ic is-bad"><use href="#i-x"/></svg></span>
          <p class="vc-card-k" id="vc-r-kicker">Card valabil</p>
          <h2 class="vc-card-h vc-code" id="vc-r-code" tabindex="-1"></h2>
          <dl class="vc-stats">
            <div class="is-balance"><dt>Sold disponibil</dt><dd><b id="vc-r-balance">—</b><span id="vc-r-currency">RON</span></dd></div>
            <div><dt>Valabil până la</dt><dd><b id="vc-r-expires">—</b><span id="vc-r-expiry-label"></span></dd></div>
          </dl>
          <dl class="vc-meta">
            <div><dt>Status</dt><dd id="vc-r-status">—</dd></div>
            <div><dt>Valoare inițială</dt><dd id="vc-r-initial">—</dd></div>
          </dl>
          <div class="vc-note is-ok"><b>Cardul poate fi folosit la checkout</b><p>La finalizarea unei comenzi, introdu codul în câmpul „Card cadou / voucher” și soldul se scade automat.</p></div>
          <div class="vc-note is-bad"><b>Cardul nu poate fi folosit acum</b><p id="vc-r-reason"></p></div>
          <div class="vc-actions">
            <a class="btn btn-primary" href="/categorii">Folosește la o comandă<?= v2_ic('arrow-right') ?></a>
            <button class="btn btn-ghost" type="button" id="vc-again">Verifică alt card</button>
          </div>
        </div>
      </section>
    </div>
  </section>

  <section class="sec vc-faq" aria-labelledby="vc-faq-h">
    <div class="wrap vc-faq-grid">
      <div>
        <p class="kicker">Întrebări</p>
        <h2 id="vc-faq-h">Cardul cadou bilete.online</h2>
        <a class="btn btn-primary vc-faq-cta" href="/card-cadou"><?= v2_ic('gift') ?>Cumpără un card cadou</a>
      </div>
      <div>
        <?php foreach ($faqs as $fi => [$faqQ, $faqA]): ?>
        <details class="qa"<?= $fi === 0 ? ' open' : '' ?>><summary><?= v2_e($faqQ) ?><span class="pm"><?= v2_ic('plus') ?></span></summary><p><?= $faqA /* static copy with trusted markup */ ?></p></details>
        <?php endforeach; ?>
      </div>
    </div>
  </section>
</main>
<?php include __DIR__ . '/includes/v2/footer.php'; ?>
