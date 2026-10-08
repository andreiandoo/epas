<?php
/**
 * New password (customer account): /resetare-parola and /reset-password (v2 design).
 *
 * Opened from the emails core sends: password reset (Customer\AuthController::sendPasswordResetEmail), "set your
 * password" for accounts created at checkout, and the account-created notification. They all link to
 * `/reset-password?token=…&email=…`, which had no page on viaqui.com (404), so none of those links worked.
 *
 * Organizers get the same page in organizer mode (?ca=venue): core e-mails them /organizator/resetare-parola (and the
 * notification /organizer/reset-password), which had no page either; .htaccess rewrites those paths here with ca=venue,
 * and reset.js then posts to `/organizer/reset-password`.
 *
 * reset.js posts token, email and the new password (with confirmation) to `/customer/reset-password` through the API
 * proxy. Core checks the token (60 minutes, 7 days for bulk invitations), needs 8+ characters, and signs the account
 * out everywhere, so a matching local session is cleared after success.
 *
 * The token must not leak: a head script moves token and email out of the address bar (into sessionStorage, so a
 * reload still works) before analytics can read the URL, and the page sends no referrer.
 *
 * Top to bottom: hero (password tips + the card: form, then success or expired link).
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/api.php';
require_once __DIR__ . '/includes/nav-helpers.php';
require_once __DIR__ . '/includes/v2/helpers.php';
require_once __DIR__ . '/includes/v2/nav.php';

// Only decides which card shows before the script runs; the values themselves are read in the browser.
$hasLink = is_string($_GET['token'] ?? null) && $_GET['token'] !== ''
    && is_string($_GET['email'] ?? null) && filter_var($_GET['email'], FILTER_VALIDATE_EMAIL);
$rpVenue = ($_GET['ca'] ?? '') === 'venue';
$rpLogin = $rpVenue ? '/login?ca=venue' : '/login';

$pageTitleRaw = v2_t('Set a new password') . ' | ' . SITE_NAME;
$pageDescription = v2_t('Set a new password for your Viaqui account.');
$canonicalUrl = SITE_URL . '/reset-password';
$noindex = true;
$skipPageCache = true; // the URL carries a one-time token

$v2Styles = ['auth-flow.css'];
$v2Scripts = ['reset.js'];
$v2LegacyScripts = ['assets/js/config.js', 'assets/js/utils.js', 'assets/js/api.js'];
$v2HeaderOverlay = true;
$v2HeadExtra = '<meta name="referrer" content="no-referrer">'
    . '<script>(function(){try{var q=new URLSearchParams(location.search),t=q.get("token"),e=q.get("email");'
    . 'if(t===null&&e===null)return;window.BO_RESET_LINK={token:t||"",email:e||""};'
    . 'try{if(t&&e)sessionStorage.setItem("bo_reset_link",JSON.stringify({token:t,email:e,at:Date.now()}));}catch(s){}'
    . 'q.delete("token");q.delete("email");var s=q.toString();'
    . 'history.replaceState(null,"",location.pathname+(s?"?"+s:"")+location.hash);}catch(x){}})();</script>'
    . '<script>window.BILETEONLINE = ' . json_encode([
        'siteName' => SITE_NAME,
        'siteUrl' => SITE_URL,
        'apiUrl' => '/api/proxy.php',
        'storageUrl' => STORAGE_URL,
        'env' => API_ENV,
        'locale' => SITE_LOCALE,
        'currency' => SITE_CURRENCY,
        'supportEmail' => defined('SUPPORT_EMAIL') ? SUPPORT_EMAIL : '',
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) . ';</script>';

$tips = [v2_t('At least 8 characters'), v2_t('Upper and lower case letters'), v2_t('At least one number'), v2_t('A special character (!@#$%)')];

include __DIR__ . '/includes/v2/head.php';
include __DIR__ . '/includes/v2/header.php';
?>
<main id="main" tabindex="-1">
  <section class="af-hero" aria-labelledby="af-h">
    <svg class="deco-arches" viewBox="0 0 400 400" aria-hidden="true" focusable="false"><path d="M40 400V200a160 160 0 0 1 320 0v200"/><path d="M90 400V200a110 110 0 0 1 220 0v200"/><path d="M140 400V200a60 60 0 0 1 120 0v200"/></svg>    <div class="af-in">
      <div class="af-copy">
        <p class="af-kicker"><?= $rpVenue ? v2_te('New password · operator') : v2_te('New password · customer') ?></p>
        <h1 class="af-h" id="af-h"><?= v2_te('Almost there!') ?></h1>
        <p class="af-lead"><?= v2_te('Set a new password for your account and you will have access to everything again.') ?></p>
        <div class="af-tips">
          <b><?= v2_te('Tips for a strong password:') ?></b>
          <ul>
            <?php foreach ($tips as $tip): ?><li><?= v2_ic('check') ?><?= v2_e($tip) ?></li><?php endforeach; ?>
          </ul>
        </div>
      </div>

      <section class="af-card" id="af-card" aria-label="<?= v2_te('Set a new password') ?>">
        <!-- the header turns solid when the white card reaches it, not after the whole hero -->
        <div id="hdr-sentinel" aria-hidden="true"></div>
        <!-- FORM -->
        <div class="af-view" id="rp-form-view"<?= $hasLink ? '' : ' hidden' ?>>
          <a class="af-back" href="<?= $rpLogin ?>"><?= v2_ic('arrow-left') ?><?= v2_te('Back to sign in') ?></a>
          <p class="af-card-k"><?= v2_te('Password reset') ?></p>
          <h2 class="af-card-h"><?= v2_te('Set a new password') ?></h2>
          <p class="af-card-p"><?= v2_t('Enter the new password for your account<span id="rp-for" hidden>: <strong id="rp-email"></strong></span>.') ?></p>
          <p class="af-error" id="rp-error" role="alert" hidden></p>
          <form class="af-form" id="rp-form" data-type="<?= $rpVenue ? 'venue' : 'client' ?>" novalidate>
            <!-- lets password managers save the new password for the right account -->
            <input type="email" id="rp-username" name="email" autocomplete="username" hidden>
            <div class="af-field">
              <label for="rp-pass"><?= v2_te('New password') ?></label>
              <div class="af-pass">
                <input id="rp-pass" name="password" type="password" autocomplete="new-password" minlength="8" maxlength="255" required placeholder="<?= v2_te('At least 8 characters') ?>" aria-describedby="rp-strength">
                <button class="af-eye" type="button" data-toggle-pass aria-pressed="false" aria-label="<?= v2_te('Show password') ?>"><?= v2_te('show') ?></button>
              </div>
              <div class="af-meter" id="rp-meter" data-score="0" aria-hidden="true"><i></i><i></i><i></i><i></i></div>
              <span class="af-hint" id="rp-strength" aria-live="polite"></span>
            </div>
            <div class="af-field">
              <label for="rp-pass2"><?= v2_te('Confirm new password') ?></label>
              <div class="af-pass">
                <input id="rp-pass2" name="password_confirmation" type="password" autocomplete="new-password" minlength="8" maxlength="255" required placeholder="<?= v2_te('Enter the password again') ?>" aria-describedby="rp-match">
                <button class="af-eye" type="button" data-toggle-pass aria-pressed="false" aria-label="<?= v2_te('Show password') ?>"><?= v2_te('show') ?></button>
              </div>
              <span class="af-hint" id="rp-match" aria-live="polite"></span>
            </div>
            <button class="btn btn-primary af-wide" id="rp-submit" type="submit"><?= v2_te('Save new password') ?></button>
          </form>
        </div>

        <!-- SUCCESS -->
        <div class="af-view" id="rp-done-view" hidden>
          <span class="af-badge" aria-hidden="true"><?= v2_ic('check') ?></span>
          <p class="af-card-k is-ok"><?= v2_te('Done') ?></p>
          <h2 class="af-card-h" id="rp-done-h" tabindex="-1"><?= v2_te('Password changed!') ?></h2>
          <p class="af-card-p"><?= v2_te('Your password has been updated. You can now sign in with the new one.') ?></p>
          <div class="af-actions">
            <a class="btn btn-primary" id="rp-login" href="<?= $rpLogin ?>"><?= v2_te('Go to sign in') ?><?= v2_ic('arrow-right') ?></a>
          </div>
        </div>

        <!-- EXPIRED OR INCOMPLETE LINK -->
        <div class="af-view" id="rp-expired-view"<?= $hasLink ? ' hidden' : '' ?>>
          <span class="af-badge is-bad" aria-hidden="true"><?= v2_ic('x') ?></span>
          <p class="af-card-k is-bad"><?= v2_te('Invalid link') ?></p>
          <h2 class="af-card-h" id="rp-expired-h" tabindex="-1"><?= v2_te('Link expired') ?></h2>
          <p class="af-card-p"><?= v2_te('This reset link has expired or has already been used. Please request a new one.') ?></p>
          <div class="af-actions">
            <a class="btn btn-primary" id="rp-new-link" href="<?= $rpVenue ? '/forgot-password?ca=venue' : '/forgot-password' ?>"><?= v2_te('Request a new link') ?></a>
            <a class="btn btn-ghost" href="<?= $rpLogin ?>"><?= v2_te('Back to sign in') ?></a>
          </div>
        </div>
      </section>
    </div>
  </section>
</main>
<?php include __DIR__ . '/includes/v2/footer.php'; ?>
