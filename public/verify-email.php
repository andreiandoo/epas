<?php
/**
 * Email verification: /verify-email (v2 design).
 *
 * Two ways in:
 *   1. The link from the verification email (?token=…&email=…&type=customer|organizer): verify.js posts it to
 *      `/customer/verify-email` or `/organizer/verify-email` and shows success or the failed-link card.
 *      Core sends the same URL to organizers (type=organizer); before, the page always used the customer endpoint,
 *      so every organizer link failed with "Account not found".
 *   2. No link (where register sends a new customer): "check your inbox", with resend.
 * Resend goes to `/customer/resend-verification` (or the organizer one) for the email we know: the link's, the signed-in
 * account's, or one the visitor types (before, resend silently did nothing when no email was known).
 *
 * Like /resetare-parola, a head script moves token and email out of the address bar before analytics reads the URL
 * (kept in sessionStorage for a reload), and the page sends no referrer.
 *
 * Top to bottom: hero (title and lead follow the state, steps) + the card in one of four states.
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/api.php';
require_once __DIR__ . '/includes/nav-helpers.php';
require_once __DIR__ . '/includes/v2/helpers.php';
require_once __DIR__ . '/includes/v2/nav.php';

// Only decides the state shown before the script runs; the values themselves are read in the browser.
$hasLink = is_string($_GET['token'] ?? null) && $_GET['token'] !== ''
    && is_string($_GET['email'] ?? null) && filter_var($_GET['email'], FILTER_VALIDATE_EMAIL);
$isOrganizer = ($_GET['type'] ?? '') === 'organizer';

$vfCopy = [
    'pending' => ['Check your email.', 'We have sent you an email with a verification link. One click and your account is ready.'],
    'processing' => ['Verifying…', 'We are checking your link. Please keep this page open.'],
    'success' => ['Email verified ✓', 'Your account is fully active: discover, book and get in with your QR code.'],
    'error' => ['This link is not valid.', 'Verification links expire for security reasons. Request a new one and try again.'],
];
$vfState = $hasLink ? 'processing' : 'pending';
// hyphenated words stay whole in the big heading
$vfNowrap = static fn (string $text): string => preg_replace('/(\S+-\S+)/u', '<span class="af-nw">$1</span>', v2_e($text));

$pageTitleRaw = 'Verify your email | ' . SITE_NAME;
$pageDescription = 'Verify your email to unlock every feature of your Viaqui account.';
$canonicalUrl = SITE_URL . '/verify-email';
$noindex = true;
$skipPageCache = true; // the URL carries a one-time token

$v2Styles = ['auth-flow.css'];
$v2Scripts = ['verify.js'];
$v2LegacyScripts = ['assets/js/config.js', 'assets/js/utils.js', 'assets/js/api.js', 'assets/js/auth.js'];
$v2HeaderOverlay = true;
$v2ClientData = ['copy' => $vfCopy];
$v2HeadExtra = '<meta name="referrer" content="no-referrer">'
    . '<script>(function(){try{var q=new URLSearchParams(location.search),t=q.get("token"),e=q.get("email"),y=q.get("type");'
    . 'if(t===null&&e===null)return;window.BO_VERIFY_LINK={token:t||"",email:e||"",type:y==="organizer"?"organizer":"customer"};'
    . 'try{if(t&&e)sessionStorage.setItem("bo_verify_link",JSON.stringify({token:t,email:e,type:window.BO_VERIFY_LINK.type,at:Date.now()}));}catch(s){}'
    . 'history.replaceState(null,"",location.pathname+location.hash);}catch(x){}})();</script>'
    . '<script>window.BILETEONLINE = ' . json_encode([
        'siteName' => SITE_NAME,
        'siteUrl' => SITE_URL,
        'apiUrl' => '/api/proxy.php',
        'storageUrl' => STORAGE_URL,
        'env' => API_ENV,
        'locale' => SITE_LOCALE,
        'currency' => 'RON',
        'supportEmail' => defined('SUPPORT_EMAIL') ? SUPPORT_EMAIL : '',
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) . ';</script>';

$accountHref = $isOrganizer ? '/organizator/panou' : '/account';
$accountLabel = $isOrganizer ? 'Go to dashboard' : 'Go to account';

include __DIR__ . '/includes/v2/head.php';
include __DIR__ . '/includes/v2/header.php';
?>
<main id="main" tabindex="-1">
  <section class="af-hero" aria-labelledby="af-h">
    <svg class="deco-arches" viewBox="0 0 400 400" aria-hidden="true" focusable="false"><path d="M40 400V200a160 160 0 0 1 320 0v200"/><path d="M90 400V200a110 110 0 0 1 220 0v200"/><path d="M140 400V200a60 60 0 0 1 120 0v200"/></svg>
    <div class="af-in">
      <div class="af-copy">
        <p class="af-kicker" id="vf-kicker">Email verification · <?= $isOrganizer ? 'operator' : 'customer' ?> account</p>
        <h1 class="af-h" id="af-h"><?= $vfNowrap($vfCopy[$vfState][0]) ?></h1>
        <p class="af-lead" id="vf-lead"><?= v2_e($vfCopy[$vfState][1]) ?></p>
        <ol class="af-steps">
          <li><small>Step 1</small><b>Email</b></li>
          <li><small>Step 2</small><b>Click the link</b></li>
          <li><small>Step 3</small><b>Active</b></li>
        </ol>
      </div>

      <section class="af-card" id="af-card" aria-label="Email verification status">
        <!-- the header turns solid when the white card reaches it, not after the whole hero -->
        <div id="hdr-sentinel" aria-hidden="true"></div>
        <p class="af-sr" id="vf-status" role="status"></p>

        <!-- PROCESSING: link present, request in flight -->
        <div class="af-view" id="vf-processing"<?= $hasLink ? '' : ' hidden' ?>>
          <span class="af-spinner" aria-hidden="true"></span>
          <p class="af-card-k">Verifying</p>
          <h2 class="af-card-h">Working on it…</h2>
          <p class="af-card-p">We are checking your link. It only takes a second.</p>
        </div>

        <!-- SUCCESS -->
        <div class="af-view" id="vf-success" hidden>
          <span class="af-badge" aria-hidden="true"><?= v2_ic('check') ?></span>
          <p class="af-card-k is-ok">Email verified</p>
          <h2 class="af-card-h" id="vf-success-h" tabindex="-1">Well done!</h2>
          <p class="af-card-p">Your account is active. You can now use everything Viaqui has to offer.</p>
          <div class="af-actions is-2">
            <a class="btn btn-primary" data-vf-account href="<?= $accountHref ?>"><?= $accountLabel ?></a>
            <a class="btn btn-ghost" href="/categories">Discover activities</a>
          </div>
        </div>

        <!-- ERROR: invalid, expired or incomplete link -->
        <div class="af-view" id="vf-error" hidden>
          <span class="af-badge is-bad" aria-hidden="true"><?= v2_ic('x') ?></span>
          <p class="af-card-k is-bad">Verification failed</p>
          <h2 class="af-card-h" id="vf-error-h" tabindex="-1">The link did not work</h2>
          <p class="af-card-p" id="vf-error-p">This verification link is invalid or has expired. Request a new one.</p>
          <div class="af-field is-inline" id="vf-error-field" hidden>
            <label for="vf-error-email">Account email</label>
            <input id="vf-error-email" type="email" inputmode="email" autocomplete="email" spellcheck="false" maxlength="255" placeholder="you@example.com">
          </div>
          <p class="af-error" id="vf-error-msg" role="alert" hidden></p>
          <p class="af-note" id="vf-error-note" role="status" hidden>✓ Email sent again. Check your inbox in a few minutes.</p>
          <div class="af-stack">
            <button class="btn btn-ghost" id="vf-retry" type="button" hidden>Try again</button>
            <button class="btn btn-primary" id="vf-error-resend" type="button">Resend verification email</button>
          </div>
          <p class="af-small">or <a data-vf-account-text href="<?= $accountHref ?>"><?= $isOrganizer ? 'go to your dashboard' : 'go to your account' ?></a> (some features may be limited until your email is verified)</p>
        </div>

        <!-- PENDING: after registering, no link yet -->
        <div class="af-view" id="vf-pending"<?= $hasLink ? ' hidden' : '' ?>>
          <span class="af-badge" aria-hidden="true"><?= v2_ic('envelope-simple') ?></span>
          <p class="af-card-k">Check your inbox</p>
          <h2 class="af-card-h" id="vf-pending-h" tabindex="-1">Almost there.</h2>
          <p class="af-card-p"><span id="vf-sent-to" hidden>We have sent a verification email to <strong id="vf-user-email"></strong>.</span><span id="vf-sent-any">We have sent you a verification email.</span><br>Click the link in the email to activate your account.</p>
          <div class="af-note" id="vf-pending-note" role="status" hidden><b>✓ Email sent again</b>Check your inbox in a few minutes.</div>
          <div class="af-help">
            <b>Didn't get the email?</b>
            <ul>
              <li><?= v2_ic('check') ?><span>Check your <strong>Spam</strong> and <strong>Promotions</strong> folders.</span></li>
              <li><?= v2_ic('check') ?><span>It can take a minute or two to arrive.</span></li>
              <li><?= v2_ic('check') ?><span>Make sure the email you signed up with is correct.</span></li>
            </ul>
          </div>
          <div class="af-field is-inline" id="vf-pending-field" hidden>
            <label for="vf-pending-email">Account email</label>
            <input id="vf-pending-email" type="email" inputmode="email" autocomplete="email" spellcheck="false" maxlength="255" placeholder="you@example.com">
          </div>
          <p class="af-error" id="vf-pending-msg" role="alert" hidden></p>
          <div class="af-stack">
            <button class="btn btn-primary" id="vf-pending-resend" type="button">Resend email</button>
            <a class="btn btn-ghost" data-vf-account href="<?= $accountHref ?>"><?= $accountLabel ?></a>
          </div>
          <p class="af-small">You can use your account without verifying, but some features need a verified email.</p>
        </div>
      </section>
    </div>
  </section>
</main>
<?php include __DIR__ . '/includes/v2/footer.php'; ?>
