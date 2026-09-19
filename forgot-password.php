<?php
/**
 * Forgot password (customer account): /parola-uitata (v2 design).
 *
 * The customer enters the account email; forgot.js posts it to `/customer/forgot-password` through the API proxy. The
 * backend answers the same way whether an account exists or not (no email enumeration), so the card turns into
 * "check your inbox" on every successful answer. Real failures (no connection, too many attempts, server error) are
 * said, because showing "sent" then would be untrue. The link in the email opens /resetare-parola (reset-password.php).
 * Organizers and venue staff use the same page in organizer mode (?ca=venue; /organizator/forgot-password redirects
 * here): the email goes to `/organizer/forgot-password` and their link opens /organizator/resetare-parola.
 *
 * Top to bottom: hero (steps + the card: form, then the sent state with resend).
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/api.php';
require_once __DIR__ . '/includes/nav-helpers.php';
require_once __DIR__ . '/includes/v2/helpers.php';
require_once __DIR__ . '/includes/v2/nav.php';

$prefillEmail = is_string($_GET['email'] ?? null) && filter_var($_GET['email'], FILTER_VALIDATE_EMAIL) ? $_GET['email'] : '';
$fpVenue = ($_GET['ca'] ?? '') === 'venue';
$fpLogin = $fpVenue ? '/autentificare?ca=venue' : '/autentificare';

$pageTitleRaw = 'Ai uitat parola? — ' . SITE_NAME;
$pageDescription = 'Resetează parola contului tău bilete.online. Trimitem un link sigur pe emailul cu care te-ai înregistrat.';
$canonicalUrl = SITE_URL . '/parola-uitata';
$noindex = true;
$skipPageCache = true;

$v2Styles = ['auth-flow.css'];
$v2Scripts = ['forgot.js'];
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
  <section class="af-hero" aria-labelledby="af-h">
    <svg class="deco-arches" viewBox="0 0 400 400" aria-hidden="true" focusable="false"><path d="M40 400V200a160 160 0 0 1 320 0v200"/><path d="M90 400V200a110 110 0 0 1 220 0v200"/><path d="M140 400V200a60 60 0 0 1 120 0v200"/></svg>    <div class="af-in">
      <div class="af-copy">
        <p class="af-kicker"><?= $fpVenue ? 'Resetare parolă · operator' : 'Resetare parolă · client' ?></p>
        <h1 class="af-h" id="af-h">Ai uitat parola?</h1>
        <p class="af-lead">Nu-ți face griji — îți trimitem un link sigur pe emailul contului. Are valabilitate limitată din motive de securitate.</p>
        <ol class="af-steps">
          <li><small>Pasul 1</small><b>Email</b></li>
          <li><small>Pasul 2</small><b>Link</b></li>
          <li><small>Pasul 3</small><b>Parolă nouă</b></li>
        </ol>
      </div>

      <section class="af-card" id="af-card" aria-label="Trimite link de resetare">
        <!-- the header turns solid when the white card reaches it, not after the whole hero -->
        <div id="hdr-sentinel" aria-hidden="true"></div>
        <!-- FORM -->
        <div class="af-view" id="fp-form-view">
          <a class="af-back" href="<?= $fpLogin ?>"><?= v2_ic('arrow-left') ?>Înapoi la autentificare</a>
          <h2 class="af-card-h">Trimite link</h2>
          <p class="af-card-p">Introdu emailul contului<?= $fpVenue ? ' de operator sau staff' : '' ?>. Dacă există un cont asociat, vei primi un link de resetare în câteva minute.</p>
          <p class="af-error" id="fp-error" role="alert" hidden></p>
          <form class="af-form" id="fp-form" data-type="<?= $fpVenue ? 'venue' : 'client' ?>" novalidate>
            <div class="af-field">
              <label for="fp-email">Email</label>
              <input id="fp-email" name="email" type="email" inputmode="email" autocomplete="email" spellcheck="false" maxlength="255" required placeholder="email@exemplu.ro" value="<?= v2_e($prefillEmail) ?>">
            </div>
            <button class="btn btn-primary af-wide" id="fp-submit" type="submit">Trimite link de resetare</button>
          </form>
          <?php if ($fpVenue): ?>
          <p class="af-card-foot">Nu mai știi emailul contului? <a href="/contact?motiv=locatie">Scrie-ne</a></p>
          <?php else: ?>
          <p class="af-card-foot">Ai uitat și emailul folosit? <a href="/recuperare-comanda">Caută comanda după număr</a></p>
          <?php endif; ?>
        </div>

        <!-- SENT -->
        <div class="af-view" id="fp-sent-view" hidden>
          <span class="af-badge" aria-hidden="true"><?= v2_ic('envelope-simple') ?></span>
          <p class="af-card-k is-ok">Email trimis</p>
          <h2 class="af-card-h" id="fp-sent-h" tabindex="-1">Verifică inbox-ul</h2>
          <p class="af-card-p">Am trimis instrucțiuni de resetare la:</p>
          <p class="af-email" id="fp-sent-email"></p>
          <div class="af-help">
            <b>Nu ai primit nimic?</b>
            <ul>
              <li><?= v2_ic('check') ?>Verifică folderul Spam / Junk.</li>
              <li><?= v2_ic('check') ?>Confirmă că emailul scris este corect.</li>
              <li><?= v2_ic('check') ?>Poate dura 1-2 minute să ajungă.</li>
            </ul>
          </div>
          <p class="af-error" id="fp-resend-error" role="alert" hidden></p>
          <p class="af-note" id="fp-resent" role="status" hidden>Email retrimis. Verifică inbox-ul în câteva minute.</p>
          <div class="af-actions is-2">
            <button class="btn btn-ghost" id="fp-resend" type="button">Retrimite emailul</button>
            <a class="btn btn-primary" id="fp-login" href="<?= $fpLogin ?>">Înapoi la login</a>
          </div>
          <button class="af-link-btn" id="fp-change" type="button">Ai greșit emailul? Schimbă-l</button>
        </div>
      </section>
    </div>
  </section>
</main>
<?php include __DIR__ . '/includes/v2/footer.php'; ?>
