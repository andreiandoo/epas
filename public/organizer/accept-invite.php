<?php
/**
 * Team invitation: /organizator/accept-invite (organizer/accept-invite.php), v2 design.
 *
 * Core e-mails every invited team member a link to /organizator/accept-invite?token=…&email=… (TeamController invite
 * and resend-invite), and .htaccess already routed it here, but viaqui.com never had this page: every invitation
 * link led to a 404.
 *
 * invite.js checks the invitation (organizer.validate-invite), shows who invites and with what role, asks for a password
 * with confirmation and an optional phone, and activates the membership (organizer.accept-invite). When the e-mail
 * already has a password in another team on this marketplace, core reuses it and the page only asks for a click. The
 * member then signs in on /autentificare?ca=venue (and in the mobile app) with the e-mail and that password.
 *
 * The token must not leak: a head script moves token and email out of the address bar (into sessionStorage, so a
 * reload still works) before analytics can read the URL, the page sends no referrer, and the proxy never caches the check.
 *
 * Top to bottom: hero (what the account gives + the card: checking, form, success, or an invalid / failed check).
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/api.php';
require_once __DIR__ . '/../includes/nav-helpers.php';
require_once __DIR__ . '/../includes/v2/helpers.php';
require_once __DIR__ . '/../includes/v2/nav.php';

$pageTitle = v2_t('Team invitation');
$pageDescription = v2_t('Accept the invitation to an operator\'s team on Viaqui and choose your password.');
$canonicalUrl = SITE_URL . '/organizator/accept-invite';
$noindex = true;
$skipPageCache = true; // the URL carries a one-time token

$v2Styles = ['auth-flow.css'];
$v2Scripts = ['invite.js'];
$v2HeaderOverlay = true;
$v2HeadExtra = '<meta name="referrer" content="no-referrer">'
    . '<script>(function(){try{var q=new URLSearchParams(location.search),t=q.get("token"),e=q.get("email");'
    . 'if(t===null&&e===null)return;window.BO_INVITE_LINK={token:t||"",email:e||""};'
    . 'try{if(t&&e)sessionStorage.setItem("bo_invite_link",JSON.stringify({token:t,email:e,at:Date.now()}));}catch(s){}'
    . 'q.delete("token");q.delete("email");var s=q.toString();'
    . 'history.replaceState(null,"",location.pathname+(s?"?"+s:"")+location.hash);}catch(x){}})();</script>'
    . '<script>window.BILETEONLINE = ' . json_encode([
        'siteName' => SITE_NAME,
        'siteUrl' => SITE_URL,
        'apiUrl' => '/api/proxy.php',
        'storageUrl' => STORAGE_URL,
        'env' => API_ENV,
        'locale' => SITE_LOCALE,
        'currency' => defined('SITE_CURRENCY') ? SITE_CURRENCY : 'EUR',
        'supportEmail' => defined('SUPPORT_EMAIL') ? SUPPORT_EMAIL : '',
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) . ';</script>';

$aiAfter = [
    v2_t('You sign in to the operator account, on Viaqui'),
    v2_t('You do check-in from the mobile app, with the same sign-in details'),
    v2_t('You see the activities you were given access to'),
    v2_t('Your access is set by the operator'),
];
$aiEye = '<button class="af-eye" type="button" data-toggle-pass aria-pressed="false" aria-label="' . v2_te('Show password') . '">' . v2_te('show') . '</button>';

include __DIR__ . '/../includes/v2/head.php';
include __DIR__ . '/../includes/v2/header.php';
?>
<main id="main" tabindex="-1">
  <section class="af-hero" aria-labelledby="af-h">
    <svg class="deco-arches" viewBox="0 0 400 400" aria-hidden="true" focusable="false"><path d="M40 400V200a160 160 0 0 1 320 0v200"/><path d="M90 400V200a110 110 0 0 1 220 0v200"/><path d="M140 400V200a60 60 0 0 1 120 0v200"/></svg>
    <div class="af-in">
      <div class="af-copy">
        <p class="af-kicker"><?= v2_te('Invitation · operator team') ?></p>
        <h1 class="af-h" id="af-h"><?= v2_te('Welcome to the team!') ?></h1>
        <p class="af-lead" id="ai-lead"><?= v2_te('An operator on Viaqui added you to their team. Choose a password and the account is active right away.') ?></p>
        <div class="af-tips">
          <b><?= v2_te('After activation:') ?></b>
          <ul>
            <?php foreach ($aiAfter as $aiItem): ?><li><?= v2_ic('check') ?><?= v2_e($aiItem) ?></li><?php endforeach; ?>
          </ul>
        </div>
      </div>

      <section class="af-card" id="af-card" aria-label="<?= v2_te('The team invitation') ?>">
        <!-- the header turns solid when the white card reaches it, not after the whole hero -->
        <div id="hdr-sentinel" aria-hidden="true"></div>
        <p class="af-sr" id="ai-status" role="status"></p>

        <!-- CHECKING -->
        <div class="af-view" id="ai-loading-view">
          <span class="af-spinner" aria-hidden="true"></span>
          <p class="af-card-k"><?= v2_te('Team invitation') ?></p>
          <h2 class="af-card-h" id="ai-loading-h" tabindex="-1"><?= v2_te('Checking the invitation') ?></h2>
          <p class="af-card-p"><?= v2_te('It only takes a moment.') ?></p>
          <noscript><p class="af-error"><?= v2_te('This page needs JavaScript to check the invitation.') ?></p></noscript>
        </div>

        <!-- FORM -->
        <div class="af-view" id="ai-form-view" hidden>
          <a class="af-back" href="/login?ca=venue"><?= v2_ic('arrow-left') ?><?= v2_te('Back to sign in') ?></a>
          <p class="af-card-k"><?= v2_te('Team invitation') ?></p>
          <h2 class="af-card-h" id="ai-form-h" tabindex="-1"><?= v2_te('Activate your account') ?></h2>
          <p class="af-card-p" id="ai-form-p"><?= v2_te('Choose the password you will sign in with, on the site and in the mobile app.') ?></p>
          <div class="ai-org">
            <span class="ai-org-ic" aria-hidden="true"><?= v2_ic('users-three') ?></span>
            <div><small><?= v2_te('Operator') ?></small><b id="ai-org"></b><span id="ai-company" hidden></span></div>
          </div>
          <dl class="ai-who">
            <div id="ai-name-row" hidden><dt><?= v2_te('Name') ?></dt><dd id="ai-name"></dd></div>
            <div><dt><?= v2_te('Email') ?></dt><dd id="ai-email"></dd></div>
            <div><dt><?= v2_te('Role') ?></dt><dd id="ai-role"></dd></div>
          </dl>
          <div class="af-note" id="ai-existing" hidden><b><?= v2_te('You already have a password on Viaqui') ?></b><?= v2_te('Your email is also part of another operator\'s team, so you sign in with the same password. You do not need to choose another one.') ?></div>
          <p class="af-error" id="ai-error" role="alert" hidden></p>
          <form class="af-form" id="ai-form" novalidate>
            <!-- lets password managers save the password for the right account -->
            <input type="email" id="ai-username" name="email" autocomplete="username" hidden>
            <div class="af-field" id="ai-pass-f">
              <label for="ai-pass"><?= v2_te('Password') ?></label>
              <div class="af-pass">
                <input id="ai-pass" name="password" type="password" autocomplete="new-password" minlength="8" maxlength="100" placeholder="<?= v2_te('At least 8 characters') ?>" aria-describedby="ai-strength">
                <?= $aiEye ?>
              </div>
              <div class="af-meter" id="ai-meter" data-score="0" aria-hidden="true"><i></i><i></i><i></i><i></i></div>
              <span class="af-hint" id="ai-strength" aria-live="polite"></span>
            </div>
            <div class="af-field" id="ai-pass2-f">
              <label for="ai-pass2"><?= v2_te('Confirm the password') ?></label>
              <div class="af-pass">
                <input id="ai-pass2" name="password_confirmation" type="password" autocomplete="new-password" minlength="8" maxlength="100" placeholder="<?= v2_te('Type the password again') ?>" aria-describedby="ai-match">
                <?= $aiEye ?>
              </div>
              <span class="af-hint" id="ai-match" aria-live="polite"></span>
            </div>
            <div class="af-field">
              <label for="ai-phone"><?= v2_t('Phone <small>(optional)</small>') ?></label>
              <input id="ai-phone" name="phone" type="tel" autocomplete="tel" inputmode="tel" maxlength="30" placeholder="<?= v2_te('With the country code, e.g. +351 912 345 678') ?>" aria-describedby="ai-phone-hint">
              <span class="af-hint" id="ai-phone-hint"><?= v2_te('The operator sees it, so they can contact you.') ?></span>
            </div>
            <button class="btn btn-primary af-wide" id="ai-submit" type="submit"><?= v2_te('Activate the account') ?></button>
          </form>
        </div>

        <!-- SUCCESS -->
        <div class="af-view" id="ai-done-view" hidden>
          <span class="af-badge" aria-hidden="true"><?= v2_ic('check') ?></span>
          <p class="af-card-k is-ok"><?= v2_te('Done') ?></p>
          <h2 class="af-card-h" id="ai-done-h" tabindex="-1"><?= v2_te('The account is active!') ?></h2>
          <p class="af-card-p" id="ai-done-p"><?= v2_te('Sign in with your email and the password you chose.') ?></p>
          <div class="af-help">
            <b><?= v2_te('Check-in at the entrance') ?></b>
            <ul>
              <li><?= v2_ic('check') ?><?= v2_te('From the mobile scanning app, with the same sign-in details.') ?></li>
              <li><?= v2_ic('check') ?><?= v2_te('You see the activities and the information the operator gave you access to.') ?></li>
            </ul>
          </div>
          <div class="af-actions">
            <a class="btn btn-primary" id="ai-login" href="/login?ca=venue"><?= v2_te('Sign in') ?><?= v2_ic('arrow-right') ?></a>
          </div>
        </div>

        <!-- INVALID LINK, OR THE CHECK FAILED -->
        <div class="af-view" id="ai-bad-view" hidden>
          <span class="af-badge is-bad" aria-hidden="true"><?= v2_ic('x') ?></span>
          <p class="af-card-k is-bad" id="ai-bad-k"><?= v2_te('Invalid link') ?></p>
          <h2 class="af-card-h" id="ai-bad-h" tabindex="-1"><?= v2_te('The invitation is no longer valid') ?></h2>
          <p class="af-card-p" id="ai-bad-p"><?= v2_te('The invitation has expired, was already used, or the link is incomplete. Ask the operator to send it again: an invitation is valid for 7 days.') ?></p>
          <div class="af-actions">
            <button class="btn btn-primary" type="button" id="ai-retry" hidden><?= v2_te('Try again') ?></button>
            <a class="btn btn-primary" id="ai-bad-login" href="/login?ca=venue"><?= v2_te('Go to sign in') ?></a>
          </div>
          <p class="af-small" id="ai-bad-note"><?= v2_t('Already activated the account? Sign in with your email and password. <a href="{url}">Forgot your password?</a>', ['url' => '/forgot-password?ca=venue']) ?></p>
        </div>
      </section>
    </div>
  </section>
</main>
<?php include __DIR__ . '/../includes/v2/footer.php'; ?>
