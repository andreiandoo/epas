<?php
/**
 * Order recovery: /recuperare-comanda (v2 design).
 *
 * Guest order recovery. The customer enters the order number and the email used at checkout; recover.js posts the pair
 * to `/customer/recover-order` through the API proxy, which verifies it server-side and re-sends the tickets email.
 * A signed-in customer can then attach the order to their account (`/customer/recover-order/attach`). Someone who is
 * not signed in gets login / register links; the login link brings them back here, where the found order is restored
 * so they can attach it without searching (and re-sending the email) again. See OrderRecoveryController for the
 * security model: everything is verified on the backend, this page is only the form and the result.
 *
 * Top to bottom: hero (steps + the search card, which turns into the found order), FAQ with a link to contact.
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/api.php';
require_once __DIR__ . '/includes/nav-helpers.php';
require_once __DIR__ . '/includes/v2/helpers.php';
require_once __DIR__ . '/includes/v2/nav.php';

// Prefill the order number from emails and from the way back after logging in: ?order=MKT-XXXX
$prefillOrder = is_string($_GET['order'] ?? null)
    ? strtoupper(substr(preg_replace('/[^A-Za-z0-9\-]/', '', $_GET['order']), 0, 64))
    : '';

$faqs = [
    ['Unde găsesc numărul comenzii?', 'Este în emailul de confirmare primit după plată (format MKT-XXXXXXXX sau ACT-XXXXXXXX). Dacă l-ai pierdut, caută în inbox „bilete.online” sau verifică folderul Spam.'],
    ['Ce email folosesc?', 'Emailul cu care ai făcut comanda — cel la care ai cerut să primești biletele. Trebuie să corespundă exact comenzii.'],
    ['Am cumpărat fără cont. Pot atașa comanda?', 'Da. Creează-ți un cont cu același email, intră în cont, apoi revino pe această pagină și apasă „Atașează comanda la contul meu”.'],
    ['Nu primesc emailul cu biletele. Ce fac?', 'Verifică Spam / Promoții, apoi reîncearcă aici. Dacă tot nu apare, scrie-ne din pagina de Contact cu numărul comenzii.'],
];

$pageTitleRaw = 'Recuperează comanda — ' . SITE_NAME;
$pageDescription = 'Nu găsești biletele sau emailul de confirmare? Introdu numărul comenzii și emailul folosit la cumpărare ca să-ți retrimitem biletele și să atașezi comanda la cont.';
$canonicalUrl = SITE_URL . '/recuperare-comanda';
$noindex = true;
$skipPageCache = true;

$v2Styles = ['recover.css'];
$v2Scripts = ['recover.js'];
$v2LegacyScripts = ['assets/js/config.js', 'assets/js/utils.js', 'assets/js/api.js', 'assets/js/auth.js'];
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
  <section class="rc-hero" aria-labelledby="rc-h">
    <svg class="deco-arches" viewBox="0 0 400 400" aria-hidden="true" focusable="false"><path d="M40 400V200a160 160 0 0 1 320 0v200"/><path d="M90 400V200a110 110 0 0 1 220 0v200"/><path d="M140 400V200a60 60 0 0 1 120 0v200"/></svg>
    <svg class="rc-line draw-clip" viewBox="0 590 3240 310" aria-hidden="true" focusable="false"><use href="#drum-g"/></svg>
    <div class="rc-in">
      <div class="rc-copy">
        <p class="rc-kicker">Recuperare comandă · bilete QR</p>
        <h1 class="rc-h" id="rc-h">Nu-ți găsești biletele?</h1>
        <p class="rc-lead">Introdu numărul comenzii și emailul folosit la cumpărare. Îți retrimitem biletele pe email și, dacă ești logat, poți atașa comanda la contul tău.</p>
        <ol class="rc-steps">
          <li><small>Pasul 1</small><b>Cod + email</b></li>
          <li><small>Pasul 2</small><b>Verificare</b></li>
          <li><small>Pasul 3</small><b>Bilete</b></li>
        </ol>
      </div>

      <section class="rc-card" id="rc-card" aria-label="Formular recuperare comandă">
        <!-- the header turns solid when the white card reaches it, not after the whole hero:
             its light links would disappear over the card -->
        <div id="hdr-sentinel" aria-hidden="true"></div>
        <!-- FORM -->
        <div id="rc-form-view">
          <p class="rc-card-k">Caută comanda</p>
          <h2 class="rc-card-h">Recuperare</h2>
          <p class="rc-card-p">Datele trebuie să corespundă comenzii originale.</p>
          <p class="rc-error" id="rc-error" role="alert" hidden></p>
          <form id="rc-form" novalidate>
            <div class="rc-field">
              <label for="rc-order">Număr comandă</label>
              <input id="rc-order" name="order_number" class="is-code" type="text" autocomplete="off" autocapitalize="characters" spellcheck="false" maxlength="64" required placeholder="ex. MKT-W08ABJWH" aria-describedby="rc-order-hint" value="<?= v2_e($prefillOrder) ?>">
              <span class="rc-hint" id="rc-order-hint">Îl găsești în emailul de confirmare primit după plată.</span>
            </div>
            <div class="rc-field">
              <label for="rc-email">Email folosit la comandă</label>
              <input id="rc-email" name="email" type="email" inputmode="email" autocomplete="email" spellcheck="false" maxlength="255" required placeholder="email@exemplu.ro">
            </div>
            <button class="btn btn-primary rc-submit" id="rc-submit" type="submit">Caută și retrimite biletele</button>
          </form>
          <p class="rc-card-foot" data-when="guest">Ai deja cont? <a href="/autentificare">Intră în cont</a> și vezi toate comenzile în <a href="/cont/comenzi">Comenzile mele</a>.</p>
          <p class="rc-card-foot" data-when="customer" hidden>Ești în cont. Toate comenzile tale sunt în <a href="/cont/comenzi">Comenzile mele</a>.</p>
        </div>

        <!-- RESULT -->
        <div class="rc-result" id="rc-result" hidden>
          <div class="rc-r-head">
            <span class="rc-badge" aria-hidden="true"><?= v2_ic('check') ?></span>
            <div>
              <p class="rc-card-k is-ok">Comandă găsită</p>
              <h2 class="rc-card-h rc-code" id="rc-r-number" tabindex="-1"></h2>
            </div>
          </div>

          <div class="rc-ticket">
            <dl class="rc-rows">
              <div><dt>Activitate / eveniment</dt><dd id="rc-r-event">—</dd></div>
              <div><dt>Bilete</dt><dd id="rc-r-tickets">—</dd></div>
              <div id="rc-r-date-row" hidden><dt>Plasată pe</dt><dd id="rc-r-date">—</dd></div>
              <div><dt>Status</dt><dd><span class="rc-status" id="rc-r-status" data-tone="wait">—</span></dd></div>
            </dl>
            <div class="rc-total"><span>Total</span><b id="rc-r-total">—</b></div>
          </div>

          <div class="rc-note is-ok" id="rc-sent" hidden>
            <?= v2_ic('envelope-simple') ?>
            <div><b>Ți-am retrimis biletele pe email</b><p>Verifică inbox-ul (și folderul Spam) pentru <strong id="rc-r-email"></strong>.</p></div>
          </div>
          <div class="rc-note is-warn" id="rc-not-sent" hidden>
            <?= v2_ic('clock') ?>
            <div><b>Nu am putut retrimite emailul acum</b><p>Încearcă din nou peste câteva minute. Dacă tot nu ajunge, <a href="/contact">scrie-ne</a> cu numărul comenzii.</p></div>
          </div>

          <!-- signed in: attach the order to the account -->
          <div class="rc-account" id="rc-attach-box" hidden>
            <b>Păstrează comanda în cont</b>
            <p>Comanda și biletele ei vor apărea în <a href="/cont/comenzi">Comenzile mele</a>.</p>
            <p class="rc-error" id="rc-attach-error" role="alert" hidden></p>
            <button class="btn btn-primary rc-wide" id="rc-attach" type="button"><?= v2_ic('user-circle') ?><span>Atașează comanda la contul meu</span></button>
          </div>
          <div class="rc-note is-ok" id="rc-attached" tabindex="-1" hidden>
            <?= v2_ic('check-circle') ?>
            <div><b id="rc-attached-t">Comanda a fost atașată contului tău</b><p><a href="/cont/comenzi">Vezi în Comenzile mele<?= v2_ic('arrow-right') ?></a></p></div>
          </div>

          <!-- not signed in: log in (and come back here) or create an account -->
          <div class="rc-account" id="rc-guest-box" hidden>
            <b>Vrei comanda în contul tău?</b>
            <p id="rc-guest-p"></p>
            <div class="rc-account-actions">
              <a class="btn btn-primary" id="rc-login" href="/autentificare">Intră în cont</a>
              <a class="btn btn-ghost" id="rc-register" href="/inregistrare">Creează cont</a>
            </div>
          </div>

          <button class="btn btn-ghost rc-wide rc-again" id="rc-again" type="button">Caută altă comandă</button>
        </div>
      </section>
    </div>
  </section>

  <section class="sec rc-faq" aria-labelledby="rc-faq-h">
    <div class="wrap rc-faq-grid">
      <div>
        <p class="kicker">Întrebări</p>
        <h2 id="rc-faq-h">Despre recuperarea comenzii</h2>
        <a class="btn btn-primary rc-faq-cta" href="/contact"><?= v2_ic('headset') ?>Tot nu găsești comanda? Scrie-ne</a>
      </div>
      <div>
        <?php foreach ($faqs as $fi => [$faqQ, $faqA]): ?>
        <details class="qa"<?= $fi === 0 ? ' open' : '' ?>><summary><?= v2_e($faqQ) ?><span class="pm"><?= v2_ic('plus') ?></span></summary><p><?= v2_e($faqA) ?></p></details>
        <?php endforeach; ?>
      </div>
    </div>
  </section>
</main>
<?php include __DIR__ . '/includes/v2/footer.php'; ?>
