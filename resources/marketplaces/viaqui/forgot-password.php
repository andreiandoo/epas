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
$fpLogin = $fpVenue ? '/login?ca=venue' : '/login';

$pageTitleRaw = v2_t('Forgot your password?') . ' | ' . SITE_NAME;
$pageDescription = v2_t('Reset the password for your Viaqui account. We send a secure link to the email address you signed up with.');
$canonicalUrl = SITE_URL . '/forgot-password';
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
    'currency' => SITE_CURRENCY,
    'supportEmail' => defined('SUPPORT_EMAIL') ? SUPPORT_EMAIL : '',
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) . ';</script>';

include __DIR__ . '/includes/v2/head.php';
include __DIR__ . '/includes/v2/header.php';
?>
<main id="main" tabindex="-1">
  <section class="af-hero" aria-labelledby="af-h">
    <svg class="deco-arches" viewBox="0 0 400 400" aria-hidden="true" focusable="false"><path d="M40 400V200a160 160 0 0 1 320 0v200"/><path d="M90 400V200a110 110 0 0 1 220 0v200"/><path d="M140 400V200a60 60 0 0 1 120 0v200"/></svg>    <div class="af-in">
      <div class="af-copy">
        <p class="af-kicker"><?= $fpVenue ? v2_te('Password reset · operator') : v2_te('Password reset · customer') ?></p>
        <h1 class="af-h" id="af-h"><?= v2_te('Forgot your password?') ?></h1>
        <p class="af-lead"><?= v2_te('No need to worry: we will send a secure link to your account email. For security, the link is only valid for a limited time.') ?></p>
        <ol class="af-steps">
          <li><small><?= v2_te('Step 1') ?></small><b><?= v2_te('Email') ?></b></li>
          <li><small><?= v2_te('Step 2') ?></small><b><?= v2_te('Link') ?></b></li>
          <li><small><?= v2_te('Step 3') ?></small><b><?= v2_te('New password') ?></b></li>
        </ol>
      </div>

      <section class="af-card" id="af-card" aria-label="<?= v2_te('Send a reset link') ?>">
        <!-- the header turns solid when the white card reaches it, not after the whole hero -->
        <div id="hdr-sentinel" aria-hidden="true"></div>
        <!-- FORM -->
        <div class="af-view" id="fp-form-view">
          <a class="af-back" href="<?= $fpLogin ?>"><?= v2_ic('arrow-left') ?><?= v2_te('Back to sign in') ?></a>
          <h2 class="af-card-h"><?= v2_te('Send a link') ?></h2>
          <p class="af-card-p"><?= $fpVenue ? v2_te('Enter the email for your operator or staff account. If an account is linked to it, you will get a reset link within a few minutes.') : v2_te('Enter the email for your account. If an account is linked to it, you will get a reset link within a few minutes.') ?></p>
          <p class="af-error" id="fp-error" role="alert" hidden></p>
          <form class="af-form" id="fp-form" data-type="<?= $fpVenue ? 'venue' : 'client' ?>" novalidate>
            <div class="af-field">
              <label for="fp-email"><?= v2_te('Email') ?></label>
              <input id="fp-email" name="email" type="email" inputmode="email" autocomplete="email" spellcheck="false" maxlength="255" required placeholder="<?= v2_te('you@example.com') ?>" value="<?= v2_e($prefillEmail) ?>">
            </div>
            <button class="btn btn-primary af-wide" id="fp-submit" type="submit"><?= v2_te('Send reset link') ?></button>
          </form>
          <?php if ($fpVenue): ?>
          <p class="af-card-foot"><?= v2_t('Can\'t remember the account email? <a href="{url}">Contact us</a>', ['url' => '/contact?motiv=locatie']) ?></p>
          <?php else: ?>
          <p class="af-card-foot"><?= v2_t('Forgotten which email you used too? <a href="{url}">Find your order by its number</a>', ['url' => '/find-order']) ?></p>
          <?php endif; ?>
        </div>

        <!-- SENT -->
        <div class="af-view" id="fp-sent-view" hidden>
          <span class="af-badge" aria-hidden="true"><?= v2_ic('envelope-simple') ?></span>
          <p class="af-card-k is-ok"><?= v2_te('Email sent') ?></p>
          <h2 class="af-card-h" id="fp-sent-h" tabindex="-1"><?= v2_te('Check your inbox') ?></h2>
          <p class="af-card-p"><?= v2_te('We sent reset instructions to:') ?></p>
          <p class="af-email" id="fp-sent-email"></p>
          <div class="af-help">
            <b><?= v2_te('Nothing arrived?') ?></b>
            <ul>
              <li><?= v2_ic('check') ?><?= v2_te('Check your Spam or Junk folder.') ?></li>
              <li><?= v2_ic('check') ?><?= v2_te('Make sure the email address is spelled correctly.') ?></li>
              <li><?= v2_ic('check') ?><?= v2_te('It can take a minute or two to arrive.') ?></li>
            </ul>
          </div>
          <p class="af-error" id="fp-resend-error" role="alert" hidden></p>
          <p class="af-note" id="fp-resent" role="status" hidden><?= v2_te('Email sent again. Check your inbox in a few minutes.') ?></p>
          <div class="af-actions is-2">
            <button class="btn btn-ghost" id="fp-resend" type="button"><?= v2_te('Resend email') ?></button>
            <a class="btn btn-primary" id="fp-login" href="<?= $fpLogin ?>"><?= v2_te('Back to sign in') ?></a>
          </div>
          <button class="af-link-btn" id="fp-change" type="button"><?= v2_te('Wrong email? Change it') ?></button>
        </div>
      </section>
    </div>
  </section>
</main>
<?php include __DIR__ . '/includes/v2/footer.php'; ?>
