<?php
/**
 * viaqui.com — /autentificare (alias /login), v2 "Arcada"
 *
 * One focused card and nothing else on the page (compact footer). It covers 4 flows:
 *   - Client × Login    → BileteOnlineAuth.loginCustomer(email, password) (+ the 2FA step)
 *   - Client × Register → BileteOnlineAuth.registerCustomer({...})
 *   - Venue  × Login    → BileteOnlineAuth.loginOrganizer(email, password)
 *   - Venue  × Register → BileteOnlineAuth.registerOrganizer({...})
 *
 * Two tabs choose between signing in and creating an account. The account type is not a second switch: the card is
 * the client's, and a link under it turns it into the venue card (a different colour and title), with a link back.
 * assets/v2/js/login.js switches the state and posts through BileteOnlineAuth, which manages tokens, sessions,
 * redirects and the bileteonline:auth:login event. The copy of each state is defined once below and handed to the
 * script, so the first paint already shows the right texts.
 *
 * Query: ?ca=venue selects the venue account, ?mode=register the sign-up form, ?email= prefills the email,
 * ?redirect= is where a client lands after logging in (a path on this site only).
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/api.php';
require_once __DIR__ . '/includes/nav-helpers.php';
require_once __DIR__ . '/includes/v2/helpers.php';
require_once __DIR__ . '/includes/v2/nav.php';

$pageTitleRaw    = 'Sign in or create an account | ' . SITE_NAME;
$pageDescription = 'Sign in to your Viaqui account or create a new one for tickets, orders, bonus points and gift cards. Operators sign in separately.';
$canonicalUrl    = SITE_URL . '/login';
$noindex         = true;     // login pages must not be indexed
$currentPage     = 'login';

$preselect     = isset($_GET['ca']) && $_GET['ca'] === 'venue' ? 'venue' : 'client';
$preselectMode = (isset($_GET['mode']) && $_GET['mode'] === 'register') ? 'register' : 'login';
$prefillEmail  = isset($_GET['email']) && filter_var($_GET['email'], FILTER_VALIDATE_EMAIL) ? (string) $_GET['email'] : '';
// Only a path on this site: it must start with one "/" (no "//host", no "/\host", no scheme, no whitespace),
// otherwise ?redirect= could send someone to another site right after they log in.
$redirectParam = is_string($_GET['redirect'] ?? null) ? $_GET['redirect'] : '';
$redirectAfter = preg_match('#^/(?![/\\\\])\S*$#', $redirectParam) ? $redirectParam : '/account';

$authCopy = [
    'client' => [
        'login' => ['title' => 'Sign in', 'text' => 'Your tickets, orders and points, all in one place.', 'submit' => 'Sign in'],
        'register' => ['title' => 'Create an account', 'text' => 'We keep your tickets, orders and bonus points for you.', 'submit' => 'Create account'],
    ],
    'venue' => [
        'login' => ['title' => 'Venue account', 'text' => 'For administrators and staff: activities, tickets, scans and reports.', 'submit' => 'Go to dashboard'],
        'register' => ['title' => 'List your venue', 'text' => 'Send us your venue details. The account may be reviewed before it is activated.', 'submit' => 'Send request'],
    ],
];
$now = $authCopy[$preselect][$preselectMode];
$isVenue = $preselect === 'venue';
$isLogin = $preselectMode === 'login';
$forType = static fn (string $type): string => $type === $preselect ? '' : ' hidden';

$v2Styles = ['login.css'];
$v2Scripts = ['login.js'];
$v2LegacyScripts = [
    'assets/js/config.js', 'assets/js/utils.js', 'assets/js/api.js', 'assets/js/auth.js',
    'assets/js/components/notifications.js',
];
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
$v2ClientData = [
    'accountType' => $preselect,
    'mode' => $preselectMode,
    'redirectAfter' => $redirectAfter,
    'copy' => $authCopy,
];
$v2FooterCompact = true;

$ticketSvg = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 9a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2 2 2 0 0 0 0 4 2 2 0 0 1-2 2H5a2 2 0 0 1-2-2 2 2 0 0 0 0-4Z"/><path d="M9 7v10" stroke-dasharray="2 2"/></svg>';
$venueSvg = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 21h18M5 21V7l8-4 6 3v15M9 10h1M9 14h1M14 10h1M14 14h1"/></svg>';

include __DIR__ . '/includes/v2/head.php';
include __DIR__ . '/includes/v2/header.php';
?>
<main id="main" class="page-main" tabindex="-1">
  <section class="au" id="au" data-type="<?= $preselect ?>" data-mode="<?= $preselectMode ?>" aria-labelledby="au-title">
    <div class="au-card" id="au-card">
      <div class="au-top">
        <span class="au-kind" id="au-kind"><span class="au-kind-ic" aria-hidden="true"><span data-for="client"<?= $forType('client') ?>><?= $ticketSvg ?></span><span data-for="venue"<?= $forType('venue') ?>><?= $venueSvg ?></span></span><span id="au-kind-text"><?= $isVenue ? 'Venue account' : 'Customer account' ?></span></span>
        <h1 class="au-h" id="au-title"><?= v2_e($now['title']) ?></h1>
        <p class="au-text" id="au-text"><?= v2_e($now['text']) ?></p>
      </div>

      <div class="au-tabs" role="tablist" aria-label="Sign in or create an account">
        <button type="button" role="tab" id="au-tab-login" data-set-mode="login" aria-controls="au-login" aria-selected="<?= $isLogin ? 'true' : 'false' ?>"<?= $isLogin ? '' : ' tabindex="-1"' ?>>Sign in</button>
        <button type="button" role="tab" id="au-tab-register" data-set-mode="register" aria-controls="au-register" aria-selected="<?= $isLogin ? 'false' : 'true' ?>"<?= $isLogin ? ' tabindex="-1"' : '' ?>><?= $isVenue ? 'New venue account' : 'New account' ?></button>
      </div>

      <p class="au-msg" id="au-msg" role="alert" hidden></p>

      <!-- 2FA challenge: shown after a client login when the account requires it -->
      <form class="au-form" id="au-2fa" hidden>
        <p class="au-note">Enter the 6-digit code from your authenticator app, or a recovery code.</p>
        <div class="au-field">
          <label for="au-2fa-code">Verification code</label>
          <input class="au-code" id="au-2fa-code" placeholder="123 456" autocomplete="one-time-code" inputmode="text" required>
        </div>
        <button class="btn btn-primary au-go" type="submit"><span data-idle>Verify and sign in</span><span data-busy><span class="spin" aria-hidden="true"></span>Verifying…</span></button>
        <button class="au-back" type="button" id="au-2fa-cancel"><?= v2_ic('arrow-left') ?>Back to sign in</button>
      </form>

      <!-- LOGIN -->
      <form class="au-form" id="au-login" role="tabpanel" aria-labelledby="au-tab-login"<?= $isLogin ? '' : ' hidden' ?>>
        <div class="au-field">
          <label for="au-login-email">Email</label>
          <input id="au-login-email" type="email" value="<?= v2_e($prefillEmail) ?>" placeholder="<?= $isVenue ? 'administrator or staff email' : 'you@example.com' ?>" autocomplete="email" required>
        </div>
        <div class="au-field">
          <div class="au-label-row"><label for="au-login-pass">Password</label><a href="<?= $isVenue ? '/forgot-password?ca=venue' : '/forgot-password' ?>" id="au-forgot">Forgot your password?</a></div>
          <div class="au-pass">
            <input id="au-login-pass" type="password" placeholder="your password" autocomplete="current-password" required>
            <button class="au-eye" type="button" data-toggle-pass aria-pressed="false" aria-label="Show password">show</button>
          </div>
        </div>
        <label class="au-check"><input class="au-cb" type="checkbox" id="au-remember" checked><span>Keep me signed in</span></label>
        <button class="btn btn-primary au-go" type="submit" id="au-login-submit"><span data-idle><?= v2_e($authCopy[$preselect]['login']['submit']) ?></span><span data-busy><span class="spin" aria-hidden="true"></span>Signing in…</span></button>
        <p class="au-aside" data-for="client"<?= $forType('client') ?>>Bought without an account? <a href="/find-order">Find your order</a></p>
      </form>

      <!-- REGISTER -->
      <form class="au-form" id="au-register" role="tabpanel" aria-labelledby="au-tab-register"<?= $isLogin ? ' hidden' : '' ?>>
        <p class="au-invite" id="au-invite" role="status" hidden></p>
        <div class="au-row2" data-for="client"<?= $forType('client') ?>>
          <div class="au-field"><label for="au-first">First name</label><input id="au-first" autocomplete="given-name" required></div>
          <div class="au-field"><label for="au-last">Last name</label><input id="au-last" autocomplete="family-name" required></div>
        </div>
        <div class="au-row2" data-for="venue"<?= $forType('venue') ?>>
          <div class="au-field"><label for="au-contact">Contact name</label><input id="au-contact" placeholder="Full name" autocomplete="name" required></div>
          <div class="au-field"><label for="au-venue">Venue name</label><input id="au-venue" placeholder="e.g. Old Town Escape Rooms" autocomplete="organization" required></div>
        </div>
        <div class="au-field">
          <label for="au-reg-email">Email</label>
          <input id="au-reg-email" type="email" value="<?= v2_e($prefillEmail) ?>" placeholder="<?= $isVenue ? 'you@yourvenue.com' : 'you@example.com' ?>" autocomplete="email" required>
        </div>
        <div class="au-row2" data-for="venue"<?= $forType('venue') ?>>
          <div class="au-field"><label for="au-venue-phone">Phone</label><input id="au-venue-phone" type="tel" placeholder="+351..." autocomplete="tel"></div>
          <div class="au-field"><label for="au-venue-city">City</label><input id="au-venue-city" placeholder="e.g. Lisbon" autocomplete="address-level2"></div>
        </div>
        <div class="au-field" data-for="client"<?= $forType('client') ?>>
          <label for="au-client-phone">Phone <small>(optional)</small></label>
          <input id="au-client-phone" type="tel" placeholder="+351 912 345 678" autocomplete="tel">
        </div>
        <div class="au-row2">
          <div class="au-field">
            <label for="au-reg-pass">Password</label>
            <div class="au-pass">
              <input id="au-reg-pass" type="password" placeholder="at least 8 characters" autocomplete="new-password" minlength="8" required>
              <button class="au-eye" type="button" data-toggle-pass aria-pressed="false" aria-label="Show password">show</button>
            </div>
          </div>
          <div class="au-field">
            <label for="au-reg-pass2">Confirm password</label>
            <input id="au-reg-pass2" type="password" placeholder="repeat your password" autocomplete="new-password" minlength="8" required>
          </div>
        </div>
        <label class="au-check"><input class="au-cb" type="checkbox" id="au-terms" required><span>I accept the <a href="/terms" target="_blank" rel="noopener">Terms</a> and the <a href="/privacy" target="_blank" rel="noopener">Privacy Policy</a>.</span></label>
        <label class="au-check" data-for="client"<?= $forType('client') ?>><input class="au-cb" type="checkbox" id="au-newsletter" checked><span>Email me ideas for things to do and offers.</span></label>
        <button class="btn btn-primary au-go" type="submit" id="au-register-submit"><span data-idle><?= v2_e($authCopy[$preselect]['register']['submit']) ?></span><span data-busy><span class="spin" aria-hidden="true"></span>Working…</span></button>
      </form>

      <div class="au-switch">
        <p data-for="client"<?= $forType('client') ?>><?= $venueSvg ?><span>Do you run a venue or organise activities?</span><button type="button" data-set-type="venue">Sign in as a venue</button></p>
        <p data-for="venue"<?= $forType('venue') ?>><?= $ticketSvg ?><span>Buying tickets for yourself?</span><button type="button" data-set-type="client">Sign in as a customer</button></p>
      </div>
    </div>
  </section>
</main>

<?php include __DIR__ . '/includes/v2/footer.php'; ?>
