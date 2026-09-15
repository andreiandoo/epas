<?php
/**
 * New password (customer account): /resetare-parola and /reset-password (v2 design).
 *
 * Opened from the emails core sends: password reset (Customer\AuthController::sendPasswordResetEmail), "set your
 * password" for accounts created at checkout, and the account-created notification. They all link to
 * `/reset-password?token=…&email=…`, which had no page on bilete.online (404), so none of those links worked.
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
$rpLogin = $rpVenue ? '/autentificare?ca=venue' : '/autentificare';

$pageTitleRaw = 'Setează parola nouă — ' . SITE_NAME;
$pageDescription = 'Setează o parolă nouă pentru contul tău bilete.online.';
$canonicalUrl = SITE_URL . '/resetare-parola';
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
        'currency' => 'RON',
        'supportEmail' => defined('SUPPORT_EMAIL') ? SUPPORT_EMAIL : '',
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) . ';</script>';

$tips = ['Minim 8 caractere', 'Litere mari și mici', 'Cel puțin o cifră', 'Un caracter special (!@#$%)'];

include __DIR__ . '/includes/v2/head.php';
include __DIR__ . '/includes/v2/header.php';
?>
<main id="main" tabindex="-1">
  <section class="af-hero" aria-labelledby="af-h">
    <svg class="deco-arches" viewBox="0 0 400 400" aria-hidden="true" focusable="false"><path d="M40 400V200a160 160 0 0 1 320 0v200"/><path d="M90 400V200a110 110 0 0 1 220 0v200"/><path d="M140 400V200a60 60 0 0 1 120 0v200"/></svg>    <div class="af-in">
      <div class="af-copy">
        <nav class="crumbs" aria-label="Breadcrumb"><a href="/">Acasă</a><span aria-hidden="true">/</span><a href="<?= $rpLogin ?>">Autentificare</a><span aria-hidden="true">/</span><span aria-current="page">Parolă nouă</span></nav>
        <p class="af-kicker"><?= $rpVenue ? 'Parolă nouă · organizator' : 'Parolă nouă · client' ?></p>
        <h1 class="af-h" id="af-h">Aproape gata!</h1>
        <p class="af-lead">Setează o parolă nouă pentru contul tău și vei putea accesa din nou toate funcționalitățile.</p>
        <div class="af-tips">
          <b>Sfaturi pentru o parolă sigură:</b>
          <ul>
            <?php foreach ($tips as $tip): ?><li><?= v2_ic('check') ?><?= v2_e($tip) ?></li><?php endforeach; ?>
          </ul>
        </div>
      </div>

      <section class="af-card" id="af-card" aria-label="Setează parola nouă">
        <!-- the header turns solid when the white card reaches it, not after the whole hero -->
        <div id="hdr-sentinel" aria-hidden="true"></div>
        <!-- FORM -->
        <div class="af-view" id="rp-form-view"<?= $hasLink ? '' : ' hidden' ?>>
          <a class="af-back" href="<?= $rpLogin ?>"><?= v2_ic('arrow-left') ?>Înapoi la autentificare</a>
          <p class="af-card-k">Resetare parolă</p>
          <h2 class="af-card-h">Setează parolă nouă</h2>
          <p class="af-card-p">Introdu noua parolă pentru contul tău<span id="rp-for" hidden>: <strong id="rp-email"></strong></span>.</p>
          <p class="af-error" id="rp-error" role="alert" hidden></p>
          <form class="af-form" id="rp-form" data-type="<?= $rpVenue ? 'venue' : 'client' ?>" novalidate>
            <!-- lets password managers save the new password for the right account -->
            <input type="email" id="rp-username" name="email" autocomplete="username" hidden>
            <div class="af-field">
              <label for="rp-pass">Parolă nouă</label>
              <div class="af-pass">
                <input id="rp-pass" name="password" type="password" autocomplete="new-password" minlength="8" maxlength="255" required placeholder="Minim 8 caractere" aria-describedby="rp-strength">
                <button class="af-eye" type="button" data-toggle-pass aria-pressed="false" aria-label="Arată parola">arată</button>
              </div>
              <div class="af-meter" id="rp-meter" data-score="0" aria-hidden="true"><i></i><i></i><i></i><i></i></div>
              <span class="af-hint" id="rp-strength" aria-live="polite"></span>
            </div>
            <div class="af-field">
              <label for="rp-pass2">Confirmă parola nouă</label>
              <div class="af-pass">
                <input id="rp-pass2" name="password_confirmation" type="password" autocomplete="new-password" minlength="8" maxlength="255" required placeholder="Reintrodu parola" aria-describedby="rp-match">
                <button class="af-eye" type="button" data-toggle-pass aria-pressed="false" aria-label="Arată parola">arată</button>
              </div>
              <span class="af-hint" id="rp-match" aria-live="polite"></span>
            </div>
            <button class="btn btn-primary af-wide" id="rp-submit" type="submit">Salvează parola nouă</button>
          </form>
        </div>

        <!-- SUCCESS -->
        <div class="af-view" id="rp-done-view" hidden>
          <span class="af-badge" aria-hidden="true"><?= v2_ic('check') ?></span>
          <p class="af-card-k is-ok">Gata</p>
          <h2 class="af-card-h" id="rp-done-h" tabindex="-1">Parolă schimbată!</h2>
          <p class="af-card-p">Parola ta a fost actualizată cu succes. Poți acum să te autentifici cu noua parolă.</p>
          <div class="af-actions">
            <a class="btn btn-primary" id="rp-login" href="<?= $rpLogin ?>">Mergi la autentificare<?= v2_ic('arrow-right') ?></a>
          </div>
        </div>

        <!-- EXPIRED OR INCOMPLETE LINK -->
        <div class="af-view" id="rp-expired-view"<?= $hasLink ? ' hidden' : '' ?>>
          <span class="af-badge is-bad" aria-hidden="true"><?= v2_ic('x') ?></span>
          <p class="af-card-k is-bad">Link invalid</p>
          <h2 class="af-card-h" id="rp-expired-h" tabindex="-1">Link expirat</h2>
          <p class="af-card-p">Linkul de resetare a expirat sau a fost deja folosit. Te rugăm să soliciți un nou link.</p>
          <div class="af-actions">
            <a class="btn btn-primary" id="rp-new-link" href="<?= $rpVenue ? '/parola-uitata?ca=venue' : '/parola-uitata' ?>">Solicită link nou</a>
            <a class="btn btn-ghost" href="<?= $rpLogin ?>">Înapoi la autentificare</a>
          </div>
        </div>
      </section>
    </div>
  </section>
</main>
<?php include __DIR__ . '/includes/v2/footer.php'; ?>
