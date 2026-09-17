<?php
/**
 * bilete.online — /autentificare (alias /login), v2 "Arcada"
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

$pageTitleRaw    = 'Autentificare și creare cont — ' . SITE_NAME;
$pageDescription = 'Intră în contul tău bilete.online sau creează un cont nou pentru bilete, comenzi, puncte bonus și carduri cadou. Login separat pentru locații / organizatori.';
$canonicalUrl    = SITE_URL . '/autentificare';
$noindex         = true;     // login pages must not be indexed
$currentPage     = 'login';

$preselect     = isset($_GET['ca']) && $_GET['ca'] === 'venue' ? 'venue' : 'client';
$preselectMode = (isset($_GET['mode']) && $_GET['mode'] === 'register') ? 'register' : 'login';
$prefillEmail  = isset($_GET['email']) && filter_var($_GET['email'], FILTER_VALIDATE_EMAIL) ? (string) $_GET['email'] : '';
// Only a path on this site: it must start with one "/" (no "//host", no "/\host", no scheme, no whitespace),
// otherwise ?redirect= could send someone to another site right after they log in.
$redirectParam = is_string($_GET['redirect'] ?? null) ? $_GET['redirect'] : '';
$redirectAfter = preg_match('#^/(?![/\\\\])\S*$#', $redirectParam) ? $redirectParam : '/cont';

$authCopy = [
    'client' => [
        'login' => ['title' => 'Intră în cont', 'text' => 'Biletele, comenzile și punctele tale, într-un singur loc.', 'submit' => 'Intră în cont'],
        'register' => ['title' => 'Creează cont', 'text' => 'Îți păstrăm biletele, comenzile și punctele bonus.', 'submit' => 'Creează contul'],
    ],
    'venue' => [
        'login' => ['title' => 'Contul locației', 'text' => 'Pentru administratori și staff: activități, bilete, scanări și rapoarte.', 'submit' => 'Intră în dashboard'],
        'register' => ['title' => 'Listează-ți locația', 'text' => 'Trimite detaliile locației. Contul poate fi verificat înainte de activare.', 'submit' => 'Trimite cererea'],
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
        <span class="au-kind" id="au-kind"><span class="au-kind-ic" aria-hidden="true"><span data-for="client"<?= $forType('client') ?>><?= $ticketSvg ?></span><span data-for="venue"<?= $forType('venue') ?>><?= $venueSvg ?></span></span><span id="au-kind-text"><?= $isVenue ? 'Cont locație' : 'Cont client' ?></span></span>
        <h1 class="au-h" id="au-title"><?= v2_e($now['title']) ?></h1>
        <p class="au-text" id="au-text"><?= v2_e($now['text']) ?></p>
      </div>

      <div class="au-tabs" role="tablist" aria-label="Intră în cont sau creează unul">
        <button type="button" role="tab" id="au-tab-login" data-set-mode="login" aria-controls="au-login" aria-selected="<?= $isLogin ? 'true' : 'false' ?>"<?= $isLogin ? '' : ' tabindex="-1"' ?>>Intră în cont</button>
        <button type="button" role="tab" id="au-tab-register" data-set-mode="register" aria-controls="au-register" aria-selected="<?= $isLogin ? 'false' : 'true' ?>"<?= $isLogin ? ' tabindex="-1"' : '' ?>><?= $isVenue ? 'Cont nou de locație' : 'Cont nou' ?></button>
      </div>

      <p class="au-msg" id="au-msg" role="alert" hidden></p>

      <!-- 2FA challenge: shown after a client login when the account requires it -->
      <form class="au-form" id="au-2fa" hidden>
        <p class="au-note">Introdu codul de 6 cifre din aplicația de autentificare sau un cod de recuperare.</p>
        <div class="au-field">
          <label for="au-2fa-code">Cod de verificare</label>
          <input class="au-code" id="au-2fa-code" placeholder="123 456" autocomplete="one-time-code" inputmode="text" required>
        </div>
        <button class="btn btn-primary au-go" type="submit"><span data-idle>Verifică și intră</span><span data-busy><span class="spin" aria-hidden="true"></span>Se verifică…</span></button>
        <button class="au-back" type="button" id="au-2fa-cancel"><?= v2_ic('arrow-left') ?>Înapoi la login</button>
      </form>

      <!-- LOGIN -->
      <form class="au-form" id="au-login" role="tabpanel" aria-labelledby="au-tab-login"<?= $isLogin ? '' : ' hidden' ?>>
        <div class="au-field">
          <label for="au-login-email">Email</label>
          <input id="au-login-email" type="email" value="<?= v2_e($prefillEmail) ?>" placeholder="<?= $isVenue ? 'email administrator / staff' : 'email@exemplu.ro' ?>" autocomplete="email" required>
        </div>
        <div class="au-field">
          <div class="au-label-row"><label for="au-login-pass">Parolă</label><a href="<?= $isVenue ? '/parola-uitata?ca=venue' : '/parola-uitata' ?>" id="au-forgot">Ai uitat parola?</a></div>
          <div class="au-pass">
            <input id="au-login-pass" type="password" placeholder="parola contului" autocomplete="current-password" required>
            <button class="au-eye" type="button" data-toggle-pass aria-pressed="false" aria-label="Arată parola">arată</button>
          </div>
        </div>
        <label class="au-check"><input class="au-cb" type="checkbox" id="au-remember" checked><span>Ține-mă minte</span></label>
        <button class="btn btn-primary au-go" type="submit" id="au-login-submit"><span data-idle><?= v2_e($authCopy[$preselect]['login']['submit']) ?></span><span data-busy><span class="spin" aria-hidden="true"></span>Se conectează…</span></button>
        <p class="au-aside" data-for="client"<?= $forType('client') ?>>Ai cumpărat fără cont? <a href="/recuperare-comanda">Recuperează comanda</a></p>
      </form>

      <!-- REGISTER -->
      <form class="au-form" id="au-register" role="tabpanel" aria-labelledby="au-tab-register"<?= $isLogin ? ' hidden' : '' ?>>
        <p class="au-invite" id="au-invite" role="status" hidden></p>
        <div class="au-row2" data-for="client"<?= $forType('client') ?>>
          <div class="au-field"><label for="au-first">Prenume</label><input id="au-first" autocomplete="given-name" required></div>
          <div class="au-field"><label for="au-last">Nume</label><input id="au-last" autocomplete="family-name" required></div>
        </div>
        <div class="au-row2" data-for="venue"<?= $forType('venue') ?>>
          <div class="au-field"><label for="au-contact">Nume contact</label><input id="au-contact" placeholder="Nume și prenume" autocomplete="name" required></div>
          <div class="au-field"><label for="au-venue">Nume locație</label><input id="au-venue" placeholder="ex. Mystery Rooms Brașov" autocomplete="organization" required></div>
        </div>
        <div class="au-field">
          <label for="au-reg-email">Email</label>
          <input id="au-reg-email" type="email" value="<?= v2_e($prefillEmail) ?>" placeholder="<?= $isVenue ? 'email@locatie.ro' : 'email@exemplu.ro' ?>" autocomplete="email" required>
        </div>
        <div class="au-row2" data-for="venue"<?= $forType('venue') ?>>
          <div class="au-field"><label for="au-venue-phone">Telefon</label><input id="au-venue-phone" type="tel" placeholder="+40..." autocomplete="tel"></div>
          <div class="au-field"><label for="au-venue-city">Oraș</label><input id="au-venue-city" placeholder="ex. Brașov" autocomplete="address-level2"></div>
        </div>
        <div class="au-field" data-for="client"<?= $forType('client') ?>>
          <label for="au-client-phone">Telefon <small>(opțional)</small></label>
          <input id="au-client-phone" type="tel" placeholder="0722 123 456" autocomplete="tel">
        </div>
        <div class="au-row2">
          <div class="au-field">
            <label for="au-reg-pass">Parolă</label>
            <div class="au-pass">
              <input id="au-reg-pass" type="password" placeholder="minim 8 caractere" autocomplete="new-password" minlength="8" required>
              <button class="au-eye" type="button" data-toggle-pass aria-pressed="false" aria-label="Arată parola">arată</button>
            </div>
          </div>
          <div class="au-field">
            <label for="au-reg-pass2">Confirmă parola</label>
            <input id="au-reg-pass2" type="password" placeholder="repetă parola" autocomplete="new-password" minlength="8" required>
          </div>
        </div>
        <label class="au-check"><input class="au-cb" type="checkbox" id="au-terms" required><span>Accept <a href="/termeni" target="_blank" rel="noopener">Termenii</a> și <a href="/confidentialitate" target="_blank" rel="noopener">Politica de confidențialitate</a>.</span></label>
        <label class="au-check" data-for="client"<?= $forType('client') ?>><input class="au-cb" type="checkbox" id="au-newsletter" checked><span>Vreau idei de activități și oferte pe email.</span></label>
        <button class="btn btn-primary au-go" type="submit" id="au-register-submit"><span data-idle><?= v2_e($authCopy[$preselect]['register']['submit']) ?></span><span data-busy><span class="spin" aria-hidden="true"></span>Se procesează…</span></button>
      </form>

      <div class="au-switch">
        <p data-for="client"<?= $forType('client') ?>><?= $venueSvg ?><span>Ai o locație sau organizezi activități?</span><button type="button" data-set-type="venue">Intră ca locație</button></p>
        <p data-for="venue"<?= $forType('venue') ?>><?= $ticketSvg ?><span>Cumperi bilete pentru tine?</span><button type="button" data-set-type="client">Intră ca client</button></p>
      </div>
    </div>
  </section>
</main>

<?php include __DIR__ . '/includes/v2/footer.php'; ?>
