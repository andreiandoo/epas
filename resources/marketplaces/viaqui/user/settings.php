<?php
/**
 * Customer settings: /cont/setari (v2 design).
 *
 * Inside the v2 account shell (includes/v2/account.php). Hero with the profile completion, four summary cards, and seven
 * tabs (base.js [data-tabs]): personal data, security (password, 2FA, sessions), recommendation preferences
 * (#profil-preferinte), family / beneficiaries (#familie), notifications, payment methods (Stripe) and privacy / GDPR
 * (export, personalisation, cookies, account deletion). settings.js talks to /customer/me, /customer/profile,
 * /customer/password, /customer/settings, /customer/2fa/*, /customer/sessions, /customer/beneficiaries,
 * /customer/payment-methods, /customer/gdpr/* and /customer/account.
 *
 * Fixed on the way: adding a beneficiary never worked (POST went to the list action, which the proxy forced to GET) and
 * neither did deleting one (DELETE went to the update action, forced to PUT): the proxy dispatches both on the method.
 * "Trimite link verificare" posted no email, which core requires (422 every time): it sends the account email now.
 * "Deconectare totală" only signed out this device: it closes the other sessions first. The export archive link points
 * at a core URL that is a 404 and needs the API key: the archive downloads through the proxy with the session token.
 * The 2FA QR came from a CDN script; it is drawn locally now. Browser confirm() prompts became inline confirmations.
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/api.php';
require_once __DIR__ . '/../includes/nav-helpers.php';
require_once __DIR__ . '/../includes/v2/helpers.php';
require_once __DIR__ . '/../includes/v2/nav.php';
require_once __DIR__ . '/../includes/v2/account.php';

$pageTitleRaw = v2_t('Account settings: {site}', ['site' => SITE_NAME]);
$pageDescription = v2_t('Manage your contact details, password, recommendation preferences, family profile, notifications, payments and privacy options.');
$canonicalUrl = SITE_URL . '/account/settings';
$noindex = true;
$skipPageCache = true;

$v2Styles = ['account.css', 'settings.css'];
$v2Scripts = ['vendor/qrcode.js', 'account.js', 'settings.js'];
$v2LegacyScripts = ['assets/js/config.js', 'assets/js/utils.js', 'assets/js/api.js', 'assets/js/auth.js'];
$v2HeadExtra = v2_account_client_config();
$v2FooterCompact = true;

$stTabs = [
    'personal' => v2_t('Personal details'),
    'security' => v2_t('Security'),
    'preferences' => v2_t('Preferences'),
    'family' => v2_t('Family'),
    'notifications' => v2_t('Notifications'),
    'payments' => v2_t('Payments'),
    'privacy' => v2_t('Privacy / GDPR'),
];
$stPanelIds = ['preferences' => 'profil-preferinte', 'family' => 'familie'];
$stPanel = function (string $key) use ($stPanelIds): string { return $stPanelIds[$key] ?? 'st-p-' . $key; };
$stNotifications = [
    'tickets' => [v2_t('Tickets and orders'), v2_t('confirmations, QR codes, changes, reminders before an activity')],
    'points' => [v2_t('Bonus points'), v2_t('points earned, points about to expire, loyalty campaigns')],
    'recommendations' => [v2_t('Recommendations'), v2_t('activities that fit your profile, city and history')],
    'newsletter' => [v2_t('Newsletter'), v2_t('guides, offers, new activities and weekend ideas')],
    'reviews' => [v2_t('Reviews'), v2_t('reminders to review activities and moderation status')],
    'support' => [v2_t('Support'), v2_t('replies to tickets and refund updates')],
];
$stCaret = v2_ic('caret-down');

include __DIR__ . '/../includes/v2/head.php';
include __DIR__ . '/../includes/v2/header.php';
?>
<main id="main" tabindex="-1" class="page-main acc-page">
  <div class="wrap">
    <?php v2_account_start('settings'); ?>

    <!-- not signed in -->
    <section class="acc-guard" id="st-guard" hidden aria-labelledby="st-guard-h">
      <span class="acc-guard-ic" aria-hidden="true"><?= v2_ic('lock-simple') ?></span>
      <h1 id="st-guard-h"><?= v2_te('You need to sign in') ?></h1>
      <p><?= v2_te('Sign in to see your settings.') ?></p>
      <a class="btn btn-primary" href="/login?redirect=%2Faccount%2Fsettings"><?= v2_te('Sign in') ?><?= v2_ic('arrow-right') ?></a>
    </section>

    <div class="acc-body" id="st-content">
      <!-- HERO -->
      <section class="acc-hero st-hero" aria-labelledby="st-h">
        <div>
          <p class="acc-kicker"><?= v2_te('Account settings') ?></p>
          <h1 class="acc-h" id="st-h"><?= v2_te('Account settings') ?></h1>
          <p class="acc-lead"><?= v2_te('Manage your personal details, security, recommendation preferences, notifications, payments and privacy options.') ?></p>
          <div class="st-cta">
            <button class="btn btn-light" type="button" data-open-tab="preferences"><?= v2_ic('star') ?><?= v2_te('Fill in preferences') ?></button>
            <button class="btn btn-outline-light" type="button" data-open-tab="privacy"><?= v2_te('Privacy & GDPR') ?></button>
          </div>
        </div>
        <article class="st-completion" aria-labelledby="st-completion-k">
          <p class="acc-k" id="st-completion-k"><?= v2_te('Profile completion') ?></p>
          <p class="st-completion-v"><span id="st-completion">0</span>%</p>
          <p class="st-completion-l"><?= v2_te('of your profile is complete for recommendations') ?></p>
          <div class="st-bar" role="progressbar" id="st-completion-bar" aria-label="<?= v2_te('Profile completed') ?>" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0"><i></i></div>
          <p class="st-completion-hint" id="st-missing"><?= v2_te('All the essential fields are filled in.') ?></p>
        </article>
      </section>

      <!-- SUMMARY -->
      <section class="st-stats" aria-label="<?= v2_te('At a glance') ?>">
        <article class="st-stat"><p class="acc-k"><?= v2_te('Security') ?></p><p class="st-stat-v" id="st-s-security">—</p><p class="st-stat-p" id="st-s-security-p"><?= v2_te('checking…') ?></p></article>
        <article class="st-stat is-mint"><p class="acc-k"><?= v2_te('Preferences') ?></p><p class="st-stat-v" id="st-s-prefs">—</p><p class="st-stat-p"><?= v2_te('for recommendations') ?></p></article>
        <article class="st-stat"><p class="acc-k"><?= v2_te('Notifications') ?></p><p class="st-stat-v" id="st-s-notif">—</p><p class="st-stat-p"><?= v2_te('email + push') ?></p></article>
        <article class="st-stat is-warm"><p class="acc-k"><?= v2_te('GDPR') ?></p><p class="st-stat-v"><?= v2_te('control') ?></p><p class="st-stat-p"><?= v2_te('export / deletion') ?></p></article>
      </section>

      <!-- TABS -->
      <div class="st-tabs-wrap" id="st-tabs-wrap">
        <div class="st-tabs" role="tablist" aria-label="<?= v2_te('Settings sections') ?>" data-tabs>
          <?php $first = true; foreach ($stTabs as $key => $label): ?>
          <button class="st-tab" type="button" role="tab" id="st-tab-<?= $key ?>" aria-controls="<?= $stPanel($key) ?>" aria-selected="<?= $first ? 'true' : 'false' ?>"<?= $first ? '' : ' tabindex="-1"' ?>><?= v2_e($label) ?></button>
          <?php $first = false; endforeach; ?>
        </div>
      </div>
      <p class="st-flash" id="st-flash" role="status" aria-live="polite"></p>
      <div class="st-callout is-bad" id="st-load-error" role="alert" hidden>
        <b><?= v2_te('We could not load your account details') ?></b>
        <p><?= v2_te('Check your connection and try again. Until then nothing is saved, so your details are not overwritten.') ?></p>
        <div class="st-actions"><button class="btn btn-ghost" type="button" id="st-retry"><?= v2_te('Try again') ?></button></div>
      </div>

      <!-- 1. PERSONAL -->
      <section class="acc-panel st-panel" id="<?= $stPanel('personal') ?>" role="tabpanel" aria-labelledby="st-tab-personal">
        <p class="acc-k"><?= v2_te('Personal details') ?></p>
        <h2><?= v2_te('Who you are and how we reach you') ?></h2>
        <p class="st-lead"><?= v2_te('These details appear on tickets, confirmation emails and invoices.') ?></p>
        <form class="st-form" id="st-profile-form" novalidate>
          <div class="st-grid">
            <div class="acc-field"><label for="st-first"><?= v2_te('First name') ?></label><span class="acc-input is-plain"><input id="st-first" autocomplete="given-name" maxlength="100" required></span></div>
            <div class="acc-field"><label for="st-last"><?= v2_te('Last name') ?></label><span class="acc-input is-plain"><input id="st-last" autocomplete="family-name" maxlength="100" required></span></div>
            <div class="acc-field">
              <label for="st-email"><?= v2_te('Email') ?></label>
              <span class="acc-input is-plain"><input id="st-email" type="email" autocomplete="email" disabled aria-describedby="st-email-note"></span>
              <small class="st-verified" id="st-verified">—</small>
              <small class="st-note" id="st-email-note"><?= v2_t('The email cannot be changed here. <a href="{url}">Write to us</a> if it needs updating.', ['url' => '/contact?motiv=altele']) ?></small>
            </div>
            <div class="acc-field"><label for="st-phone"><?= v2_te('Phone') ?></label><span class="acc-input is-plain"><input id="st-phone" type="tel" autocomplete="tel" maxlength="50" placeholder="+43 660 123 4567"></span></div>
            <div class="acc-field">
              <label for="st-city"><?= v2_te('Main city') ?></label>
              <span class="acc-select"><select id="st-city" data-city-select><option value=""><?= v2_te('choose a city') ?></option></select><?= $stCaret ?></span>
              <small class="st-note"><?= v2_te('Choose from the cities we cover. Used for local recommendations.') ?></small>
            </div>
            <div class="acc-field">
              <label for="st-birth"><?= v2_te('Date of birth') ?></label>
              <span class="acc-input is-plain"><input id="st-birth" inputmode="numeric" maxlength="10" placeholder="<?= v2_te('dd/mm/yyyy') ?>" autocomplete="bday" aria-describedby="st-birth-note"></span>
              <small class="st-note" id="st-birth-note"><?= v2_te('Format: day/month/year (example: 15/06/1992)') ?></small>
            </div>
            <div class="acc-field is-wide">
              <label for="st-gender"><?= v2_te('Gender (optional)') ?></label>
              <span class="acc-select"><select id="st-gender"><option value=""><?= v2_te('choose') ?></option><option value="female"><?= v2_te('Woman') ?></option><option value="male"><?= v2_te('Man') ?></option><option value="other"><?= v2_te('Other') ?></option></select><?= $stCaret ?></span>
            </div>
          </div>
          <p class="st-error" id="st-profile-error" role="alert" hidden></p>
          <div class="st-actions">
            <button class="btn btn-primary" type="submit" id="st-profile-save"><?= v2_te('Save details') ?></button>
            <button class="btn btn-ghost" type="button" id="st-verify-send" hidden><?= v2_te('Send verification link') ?></button>
          </div>
        </form>
      </section>

      <!-- 2. SECURITY -->
      <section class="acc-panel st-panel" id="<?= $stPanel('security') ?>" role="tabpanel" aria-labelledby="st-tab-security" hidden>
        <p class="acc-k"><?= v2_te('Security') ?></p>
        <h2><?= v2_te('Password, sessions and account protection') ?></h2>
        <div class="st-two">
          <div class="st-stack">
            <div class="st-block">
              <h3><?= v2_te('Change password') ?></h3>
              <form class="st-form" id="st-pass-form" novalidate>
                <div class="acc-field"><label for="st-pass-current"><?= v2_te('Current password') ?></label><span class="acc-input is-plain"><input id="st-pass-current" type="password" autocomplete="current-password" required></span></div>
                <div class="st-grid">
                  <div class="acc-field"><label for="st-pass-new"><?= v2_te('New password') ?></label><span class="acc-input is-plain"><input id="st-pass-new" type="password" autocomplete="new-password" minlength="8" required></span></div>
                  <div class="acc-field"><label for="st-pass-confirm"><?= v2_te('Confirm the new password') ?></label><span class="acc-input is-plain"><input id="st-pass-confirm" type="password" autocomplete="new-password" minlength="8" required></span></div>
                </div>
                <p class="st-error" id="st-pass-error" role="alert" hidden></p>
                <div class="st-actions"><button class="btn btn-primary" type="submit" id="st-pass-save"><?= v2_te('Update password') ?></button></div>
              </form>
            </div>

            <div class="st-block" id="st-2fa">
              <div class="st-block-head">
                <div>
                  <h3><?= v2_te('Two-step sign-in') ?></h3>
                  <p class="st-note"><?= v2_te('Recommended for extra protection. You will need a 6-digit TOTP code every time you sign in.') ?></p>
                </div>
                <span class="acc-tag" id="st-2fa-tag"><?= v2_te('off') ?></span>
              </div>
              <div id="st-2fa-off">
                <button class="btn btn-primary" type="button" id="st-2fa-start"><?= v2_te('Turn on 2FA') ?></button>
              </div>
              <div class="st-2fa-setup" id="st-2fa-setup" hidden>
                <div class="st-2fa-qr">
                  <div class="st-qr-box" id="st-2fa-qr"></div>
                  <p class="st-note"><?= v2_te('Scan the QR code with Google Authenticator, Authy, 1Password or Bitwarden.') ?></p>
                </div>
                <div class="st-2fa-steps">
                  <p class="st-note"><?= v2_te('Or enter the secret by hand:') ?></p>
                  <code class="st-code" id="st-2fa-secret"></code>
                  <button class="btn btn-ghost st-copy" type="button" id="st-2fa-secret-copy"><?= v2_te('Copy the secret') ?></button>
                  <label class="st-strong" for="st-2fa-code"><?= v2_te('Step 2: enter the 6-digit code shown in the app:') ?></label>
                  <span class="acc-input is-plain"><input class="st-otp" id="st-2fa-code" inputmode="numeric" maxlength="6" autocomplete="one-time-code" placeholder="123456"></span>
                  <p class="st-error" id="st-2fa-error" role="alert" hidden></p>
                  <div class="st-actions">
                    <button class="btn btn-primary" type="button" id="st-2fa-confirm" disabled><?= v2_te('Check and turn on') ?></button>
                    <button class="btn btn-ghost" type="button" id="st-2fa-cancel"><?= v2_te('Cancel') ?></button>
                  </div>
                </div>
                <div class="st-recovery">
                  <b><?= v2_te('Recovery codes') ?></b>
                  <p><?= v2_te('Keep these codes somewhere safe: each one can be used once if you lose access to the app.') ?></p>
                  <ol class="st-codes" id="st-2fa-codes"></ol>
                  <button class="btn btn-ghost" type="button" data-copy-codes="st-2fa-codes"><?= v2_te('Copy the codes') ?></button>
                </div>
              </div>
              <div id="st-2fa-on" hidden>
                <p class="st-note" id="st-2fa-summary"></p>
                <div class="st-actions">
                  <button class="btn st-danger" type="button" data-inline="st-2fa-disable"><?= v2_te('Turn off 2FA') ?></button>
                  <button class="btn btn-ghost" type="button" data-inline="st-2fa-regen"><?= v2_te('Make new codes') ?></button>
                </div>
                <form class="st-inline" id="st-2fa-disable" hidden novalidate>
                  <label for="st-2fa-disable-pass"><?= v2_te('Confirm your password to turn off 2FA:') ?></label>
                  <div class="st-inline-row">
                    <span class="acc-input is-plain"><input id="st-2fa-disable-pass" type="password" autocomplete="current-password"></span>
                    <button class="btn st-danger" type="submit"><?= v2_te('Turn off') ?></button>
                    <button class="btn btn-ghost" type="button" data-inline-close><?= v2_te('Cancel') ?></button>
                  </div>
                  <p class="st-error" role="alert" hidden></p>
                </form>
                <form class="st-inline" id="st-2fa-regen" hidden novalidate>
                  <label for="st-2fa-regen-pass"><?= v2_te('Confirm your password to make new codes (the old ones will stop working):') ?></label>
                  <div class="st-inline-row">
                    <span class="acc-input is-plain"><input id="st-2fa-regen-pass" type="password" autocomplete="current-password"></span>
                    <button class="btn btn-primary" type="submit"><?= v2_te('Make new codes') ?></button>
                    <button class="btn btn-ghost" type="button" data-inline-close><?= v2_te('Cancel') ?></button>
                  </div>
                  <p class="st-error" role="alert" hidden></p>
                </form>
                <div class="st-recovery" id="st-2fa-newcodes" hidden>
                  <b><?= v2_te('Your new recovery codes') ?></b>
                  <p><?= v2_te('Keep them somewhere safe. The old ones no longer work.') ?></p>
                  <ol class="st-codes" id="st-2fa-newcodes-list"></ol>
                  <button class="btn btn-ghost" type="button" data-copy-codes="st-2fa-newcodes-list"><?= v2_te('Copy the codes') ?></button>
                </div>
              </div>
            </div>

            <div class="st-block">
              <h3><?= v2_te('Active sessions') ?></h3>
              <p class="st-note"><?= v2_te('The devices where you are signed in now. Close the ones you do not recognise.') ?></p>
              <p class="st-state" id="st-sessions-state"><?= v2_te('Loading sessions…') ?></p>
              <ul class="st-sessions" id="st-sessions" hidden></ul>
              <div class="st-actions">
                <button class="btn st-danger" type="button" data-confirm-sessions="others"><?= v2_te('Close the other sessions') ?></button>
                <button class="btn btn-ghost" type="button" data-confirm-sessions="all"><?= v2_te('Sign out everywhere (this device too)') ?></button>
              </div>
              <div class="st-inline" id="st-sessions-confirm" hidden>
                <p id="st-sessions-confirm-t"></p>
                <div class="st-inline-row">
                  <button class="btn st-danger" type="button" id="st-sessions-yes"><?= v2_te('Yes, continue') ?></button>
                  <button class="btn btn-ghost" type="button" id="st-sessions-no"><?= v2_te('Cancel') ?></button>
                </div>
              </div>
            </div>
          </div>
          <aside class="st-aside is-mint">
            <b><?= v2_te('Security status') ?></b>
            <p id="st-security-status"><?= v2_te('Checking…') ?></p>
          </aside>
        </div>
      </section>

      <!-- 3. PREFERENCES -->
      <section class="acc-panel st-panel" id="<?= $stPanel('preferences') ?>" role="tabpanel" aria-labelledby="st-tab-preferences" hidden>
        <p class="acc-k"><?= v2_te('Recommendation preferences') ?></p>
        <h2><?= v2_te('What kind of activities would you like to see?') ?></h2>
        <p class="st-lead"><?= v2_t('These fields matter most for your <a href="{url}">Recommendations</a> page. The clearer they are, the better the activities we can suggest.', ['url' => '/account/recommendations']) ?></p>
        <div class="st-two">
          <div class="st-stack">
            <div class="st-block">
              <h3><?= v2_te('Favourite categories') ?></h3>
              <p class="st-state" id="st-cats-state"><?= v2_te('Loading categories…') ?></p>
              <div class="st-chips" id="st-cats" role="group" aria-label="<?= v2_te('Favourite categories') ?>"></div>
              <p class="st-note"><?= v2_te('You can choose up to 20 categories.') ?></p>
            </div>
            <div class="st-block">
              <h3><?= v2_te('Cities you are interested in') ?></h3>
              <div class="st-grid">
                <div class="acc-field">
                  <label for="st-city2"><?= v2_te('Main city') ?></label>
                  <span class="acc-select"><select id="st-city2" data-city-select><option value=""><?= v2_te('choose a city') ?></option></select><?= $stCaret ?></span>
                  <small class="st-note"><?= v2_te('Used as the main signal for recommendations.') ?></small>
                </div>
                <div class="acc-field">
                  <label for="st-radius"><?= v2_te('Recommendation radius') ?></label>
                  <span class="acc-select"><select id="st-radius"><option value=""><?= v2_te('choose') ?></option><option value="city"><?= v2_te('Only my city') ?></option><option value="25km">+25 km</option><option value="50km">+50 km</option><option value="country"><?= v2_te('The whole country') ?></option></select><?= $stCaret ?></span>
                </div>
              </div>
              <div class="acc-field st-multi">
                <label for="st-city-search"><?= v2_te('Other cities you often go to') ?></label>
                <span class="acc-input"><?= v2_ic('magnifying-glass') ?><input id="st-city-search" type="search" placeholder="<?= v2_te('Search for a city…') ?>" autocomplete="off" aria-controls="st-city-list"></span>
                <div class="st-chips is-selected" id="st-sec-chips" aria-live="polite"></div>
                <ul class="st-city-list" id="st-city-list" aria-label="<?= v2_te('Other cities') ?>"></ul>
              </div>
            </div>
            <div class="st-block">
              <h3><?= v2_te('Budget and pace') ?></h3>
              <div class="st-grid is-3">
                <div class="acc-field"><label for="st-budget"><?= v2_te('Budget per person') ?></label><span class="acc-select"><select id="st-budget"><option value=""><?= v2_te('choose') ?></option><option value="under_50"><?= v2_te('under {amount}', ['amount' => v2_money(50)]) ?></option><option value="50_120"><?= v2_te('{from} to {to}', ['from' => v2_money(50), 'to' => v2_money(120)]) ?></option><option value="120_250"><?= v2_te('{from} to {to}', ['from' => v2_money(120), 'to' => v2_money(250)]) ?></option><option value="250_plus"><?= v2_te('{amount} and over', ['amount' => v2_money(250)]) ?></option></select><?= $stCaret ?></span></div>
                <div class="acc-field"><label for="st-frequency"><?= v2_te('How often') ?></label><span class="acc-select"><select id="st-frequency"><option value=""><?= v2_te('choose') ?></option><option value="spontaneous"><?= v2_te('on the spur of the moment') ?></option><option value="monthly"><?= v2_te('2 or 3 times a month') ?></option><option value="weekly"><?= v2_te('every week') ?></option></select><?= $stCaret ?></span></div>
                <div class="acc-field"><label for="st-moment"><?= v2_te('When') ?></label><span class="acc-select"><select id="st-moment"><option value=""><?= v2_te('choose') ?></option><option value="weekend"><?= v2_te('weekends') ?></option><option value="afterwork"><?= v2_te('after work') ?></option><option value="vacations"><?= v2_te('holidays') ?></option><option value="anytime"><?= v2_te('any time') ?></option></select><?= $stCaret ?></span></div>
              </div>
            </div>
          </div>
          <aside class="st-aside is-deep">
            <p class="acc-k"><?= v2_te('Recommendation engine') ?></p>
            <h3><?= v2_te('Useful signals') ?></h3>
            <ul class="st-signals" id="st-signals">
              <li data-signal="cats"><?= v2_te('Favourite categories') ?> <span></span></li>
              <li data-signal="cities"><?= v2_te('Cities and radius') ?></li>
              <li data-signal="budget"><?= v2_te('Budget') ?></li>
              <li data-signal="frequency"><?= v2_te('How often') ?></li>
              <li data-signal="moment"><?= v2_te('When') ?></li>
              <li class="is-auto"><?= v2_te('Order history (automatic)') ?></li>
            </ul>
            <button class="btn btn-light" type="button" id="st-prefs-save"><?= v2_te('Save preferences') ?></button>
          </aside>
        </div>
      </section>

      <!-- 4. FAMILY -->
      <section class="acc-panel st-panel" id="<?= $stPanel('family') ?>" role="tabpanel" aria-labelledby="st-tab-family" hidden>
        <p class="acc-k"><?= v2_te('Family & guests') ?></p>
        <h2><?= v2_te('Saved guests and family profile') ?></h2>
        <p class="st-lead"><?= v2_te('Add the people you often buy tickets for (a child, a partner, a friend). They show up at checkout and help us suggest activities that fit.') ?></p>
        <div class="st-two">
          <div class="st-stack">
            <p class="st-state" id="st-ben-state"><?= v2_te('Loading guests…') ?></p>
            <ul class="st-bens" id="st-bens" hidden></ul>
            <form class="st-block st-ben-form" id="st-ben-form" hidden novalidate>
              <h3 id="st-ben-form-h"><?= v2_te('New guest') ?></h3>
              <div class="st-grid">
                <div class="acc-field is-wide"><label for="st-ben-name"><?= v2_te('Full name') ?></label><span class="acc-input is-plain"><input id="st-ben-name" maxlength="150" autocomplete="off" required></span></div>
                <div class="acc-field"><label for="st-ben-relation"><?= v2_te('Relationship') ?></label><span class="acc-select"><select id="st-ben-relation"><option value=""><?= v2_te('choose') ?></option><option value="self"><?= v2_te('Myself') ?></option><option value="partner"><?= v2_te('Partner') ?></option><option value="child"><?= v2_te('Child') ?></option><option value="parent"><?= v2_te('Parent') ?></option><option value="sibling"><?= v2_te('Brother / sister') ?></option><option value="friend"><?= v2_te('Friend') ?></option><option value="other"><?= v2_te('Other relationship') ?></option></select><?= $stCaret ?></span></div>
                <div class="acc-field"><label for="st-ben-birth"><?= v2_te('Date of birth') ?></label><span class="acc-input is-plain"><input id="st-ben-birth" type="date"></span></div>
                <div class="acc-field"><label for="st-ben-email"><?= v2_te('Email (optional)') ?></label><span class="acc-input is-plain"><input id="st-ben-email" type="email" maxlength="200" autocomplete="off"></span></div>
                <div class="acc-field"><label for="st-ben-phone"><?= v2_te('Phone (optional)') ?></label><span class="acc-input is-plain"><input id="st-ben-phone" type="tel" maxlength="30" autocomplete="off"></span></div>
                <div class="acc-field is-wide"><label for="st-ben-notes"><?= v2_te('Notes (optional)') ?></label><textarea class="st-textarea" id="st-ben-notes" maxlength="1000" rows="3" placeholder="<?= v2_te('allergies, preferences, T-shirt size…') ?>"></textarea></div>
              </div>
              <p class="st-error" id="st-ben-error" role="alert" hidden></p>
              <div class="st-actions">
                <button class="btn btn-primary" type="submit" id="st-ben-save"><?= v2_te('Save') ?></button>
                <button class="btn btn-ghost" type="button" id="st-ben-cancel"><?= v2_te('Cancel') ?></button>
              </div>
            </form>
          </div>
          <aside class="st-aside is-mint">
            <b><?= v2_te('Why does it matter?') ?></b>
            <p><?= v2_te('Saved guests spare you typing the names again at checkout. The family profile (ages, interests) helps the recommendation engine suggest activities that suit the whole group.') ?></p>
            <small><?= v2_te('Limit: 25 guests per account.') ?></small>
          </aside>
        </div>
      </section>

      <!-- 5. NOTIFICATIONS -->
      <section class="acc-panel st-panel" id="<?= $stPanel('notifications') ?>" role="tabpanel" aria-labelledby="st-tab-notifications" hidden>
        <p class="acc-k"><?= v2_te('Notifications & newsletter') ?></p>
        <h2><?= v2_te('What would you like to receive?') ?></h2>
        <div class="st-toggles">
          <?php foreach ($stNotifications as $key => [$title, $desc]): ?>
          <label class="st-toggle" for="st-n-<?= $key ?>">
            <span class="st-toggle-t"><b><?= v2_e($title) ?></b><small><?= v2_e($desc) ?></small></span>
            <input class="st-switch" type="checkbox" role="switch" id="st-n-<?= $key ?>" data-notif="<?= $key ?>">
          </label>
          <?php endforeach; ?>
        </div>
        <div class="st-callout is-warm">
          <b><?= v2_te('A newsletter made for you') ?></b>
          <p><?= v2_te('You can get recommendations by city, activities for children, offers, points about to expire and editorial guides. Every email has an unsubscribe link.') ?></p>
        </div>
        <div class="st-actions"><button class="btn btn-primary" type="button" id="st-notif-save"><?= v2_te('Save notifications') ?></button></div>
      </section>

      <!-- 6. PAYMENTS -->
      <section class="acc-panel st-panel" id="<?= $stPanel('payments') ?>" role="tabpanel" aria-labelledby="st-tab-payments" hidden>
        <p class="acc-k"><?= v2_te('Payments · Stripe') ?></p>
        <h2><?= v2_te('Payment methods and billing') ?></h2>
        <p class="st-lead"><?= v2_te('Save your card so you do not have to enter it with every order. Card details are stored by Stripe, never on Viaqui servers.') ?></p>
        <div class="st-two">
          <div class="st-stack">
            <div class="st-callout is-bad" id="st-pay-off" hidden>
              <b><?= v2_te('The payment processor is not active yet') ?></b>
              <p><?= v2_te('The Viaqui team is finishing the Stripe integration. You will be able to save cards as soon as it is ready.') ?></p>
            </div>
            <p class="st-state" id="st-cards-state"><?= v2_te('Loading cards…') ?></p>
            <ul class="st-cards" id="st-cards" hidden></ul>
            <div class="st-empty" id="st-cards-empty" hidden><b><?= v2_te('No saved cards') ?></b><p><?= v2_te('Add a card to buy faster next time.') ?></p></div>
            <div class="st-actions is-end" id="st-card-add-row" hidden><button class="btn btn-primary" type="button" id="st-card-add"><?= v2_ic('plus') ?><?= v2_te('Add a card') ?></button></div>
            <div class="st-block" id="st-card-form" hidden>
              <h3><?= v2_te('New card') ?></h3>
              <p class="st-note"><?= v2_te('Card details are encrypted and sent straight to Stripe.') ?></p>
              <div class="st-card-element" id="st-card-element"></div>
              <p class="st-error" id="st-card-error" role="alert" hidden></p>
              <div class="st-actions">
                <button class="btn btn-primary" type="button" id="st-card-save" disabled><?= v2_te('Save card') ?></button>
                <button class="btn btn-ghost" type="button" id="st-card-cancel"><?= v2_te('Cancel') ?></button>
              </div>
            </div>
          </div>
          <aside class="st-aside">
            <b><?= v2_te('Billing details') ?></b>
            <p><?= v2_te('We keep your billing details with your profile, from the fields in the “Personal details” tab (name + address).') ?></p>
            <button class="btn btn-ghost" type="button" data-open-tab="personal"><?= v2_te('Go to personal details') ?></button>
            <small><?= v2_te('PCI-DSS: Viaqui never stores card numbers. We keep only an opaque identifier (the Stripe payment method id), the brand and the last 4 digits, to show them to you.') ?></small>
          </aside>
        </div>
      </section>

      <!-- 7. PRIVACY -->
      <section class="acc-panel st-panel" id="<?= $stPanel('privacy') ?>" role="tabpanel" aria-labelledby="st-tab-privacy" hidden>
        <p class="acc-k"><?= v2_te('Privacy & GDPR') ?></p>
        <h2><?= v2_te('You are in control of your data') ?></h2>
        <div class="st-privacy">
          <article class="st-block">
            <h3><?= v2_te('Export your personal data') ?></h3>
            <p class="st-note"><?= v2_te('Download a copy of your account details, orders, tickets, preferences, guests and points.') ?></p>
            <div class="st-export" id="st-export" tabindex="-1"><p class="st-state"><?= v2_te('Checking for exports…') ?></p></div>
          </article>
          <article class="st-block is-mint">
            <h3><?= v2_te('Personalised recommendations') ?></h3>
            <p class="st-note"><?= v2_te('Lets us use your history, reviews and preferences for more relevant recommendations.') ?></p>
            <label class="st-toggle is-inline" for="st-personalization">
              <input class="st-switch" type="checkbox" role="switch" id="st-personalization">
              <b id="st-personalization-l"><?= v2_te('On') ?></b>
            </label>
          </article>
          <article class="st-block is-warm">
            <h3><?= v2_te('Marketing tracking') ?></h3>
            <p class="st-note"><?= v2_te('Manage your consent for pixels, analytics and personalised campaigns.') ?></p>
            <div class="st-actions">
              <button class="btn btn-ghost" type="button" data-cc-action="open"><?= v2_te('Cookie settings') ?></button>
              <a class="st-link" href="/cookies"><?= v2_te('Cookie policy') ?></a>
            </div>
          </article>
          <article class="st-block is-danger">
            <h3><?= v2_te('Delete account') ?></h3>
            <p class="st-note"><?= v2_te('Your personal data will be anonymised. Orders stay in the tax records, as the law requires. You cannot delete the account while you have unused tickets for upcoming activities.') ?></p>
            <form class="st-form" id="st-delete-form" novalidate>
              <div class="acc-field"><label for="st-delete-pass"><?= v2_te('Current password') ?></label><span class="acc-input is-plain"><input id="st-delete-pass" type="password" autocomplete="current-password" required></span></div>
              <div class="acc-field"><label for="st-delete-reason"><?= v2_te('Reason for deleting (optional)') ?></label><textarea class="st-textarea" id="st-delete-reason" maxlength="500" rows="3"></textarea></div>
              <label class="st-check" for="st-delete-confirm"><input type="checkbox" id="st-delete-confirm"><span><?= v2_te('I understand that my data will be anonymised and that this cannot be undone.') ?></span></label>
              <p class="st-error" id="st-delete-error" role="alert" hidden></p>
              <div class="st-actions"><button class="btn st-danger" type="submit" id="st-delete-submit" disabled><?= v2_te('Delete account') ?></button></div>
              <div class="st-inline" id="st-delete-final" hidden>
                <p><?= v2_te('Last step: the account will be anonymised and you cannot get it back.') ?></p>
                <div class="st-inline-row">
                  <button class="btn st-danger" type="button" id="st-delete-yes"><?= v2_te('Yes, delete the account for good') ?></button>
                  <button class="btn btn-ghost" type="button" id="st-delete-no"><?= v2_te('Cancel') ?></button>
                </div>
              </div>
            </form>
          </article>
        </div>
      </section>
    </div>

    <?php v2_account_end(); ?>
  </div>
</main>
<?php include __DIR__ . '/../includes/v2/footer.php'; ?>
