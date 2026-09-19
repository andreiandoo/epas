<?php
/**
 * Team invitation: /organizator/accept-invite (organizer/accept-invite.php), v2 design.
 *
 * Core e-mails every invited team member a link to /organizator/accept-invite?token=…&email=… (TeamController invite
 * and resend-invite), and .htaccess already routed it here, but bilete.online never had this page: every invitation
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

$pageTitleRaw = 'Invitație în echipă — ' . SITE_NAME;
$pageDescription = 'Acceptă invitația în echipa unui operator de pe bilete.online și alege-ți parola.';
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
        'currency' => 'RON',
        'supportEmail' => defined('SUPPORT_EMAIL') ? SUPPORT_EMAIL : '',
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) . ';</script>';

$aiAfter = [
    'Intri în contul operatorului, pe bilete.online',
    'Faci check-in din aplicația mobilă, cu aceleași date',
    'Vezi activitățile la care ai primit acces',
    'Accesul tău îl stabilește operatorul',
];
$aiEye = '<button class="af-eye" type="button" data-toggle-pass aria-pressed="false" aria-label="Arată parola">arată</button>';

include __DIR__ . '/../includes/v2/head.php';
include __DIR__ . '/../includes/v2/header.php';
?>
<main id="main" tabindex="-1">
  <section class="af-hero" aria-labelledby="af-h">
    <svg class="deco-arches" viewBox="0 0 400 400" aria-hidden="true" focusable="false"><path d="M40 400V200a160 160 0 0 1 320 0v200"/><path d="M90 400V200a110 110 0 0 1 220 0v200"/><path d="M140 400V200a60 60 0 0 1 120 0v200"/></svg>
    <div class="af-in">
      <div class="af-copy">
        <p class="af-kicker">Invitație · echipa operatorului</p>
        <h1 class="af-h" id="af-h">Bun venit în echipă!</h1>
        <p class="af-lead" id="ai-lead">Un operator de pe bilete.online te-a adăugat în echipa lui. Alege o parolă și contul devine activ pe loc.</p>
        <div class="af-tips">
          <b>După activare:</b>
          <ul>
            <?php foreach ($aiAfter as $aiItem): ?><li><?= v2_ic('check') ?><?= v2_e($aiItem) ?></li><?php endforeach; ?>
          </ul>
        </div>
      </div>

      <section class="af-card" id="af-card" aria-label="Invitația în echipă">
        <!-- the header turns solid when the white card reaches it, not after the whole hero -->
        <div id="hdr-sentinel" aria-hidden="true"></div>
        <p class="af-sr" id="ai-status" role="status"></p>

        <!-- CHECKING -->
        <div class="af-view" id="ai-loading-view">
          <span class="af-spinner" aria-hidden="true"></span>
          <p class="af-card-k">Invitație în echipă</p>
          <h2 class="af-card-h" id="ai-loading-h" tabindex="-1">Verificăm invitația</h2>
          <p class="af-card-p">Durează doar o clipă.</p>
          <noscript><p class="af-error">Pagina are nevoie de JavaScript ca să verifice invitația.</p></noscript>
        </div>

        <!-- FORM -->
        <div class="af-view" id="ai-form-view" hidden>
          <a class="af-back" href="/autentificare?ca=venue"><?= v2_ic('arrow-left') ?>Înapoi la autentificare</a>
          <p class="af-card-k">Invitație în echipă</p>
          <h2 class="af-card-h" id="ai-form-h" tabindex="-1">Activează-ți contul</h2>
          <p class="af-card-p" id="ai-form-p">Alege parola cu care vei intra în cont, pe site și în aplicația mobilă.</p>
          <div class="ai-org">
            <span class="ai-org-ic" aria-hidden="true"><?= v2_ic('users-three') ?></span>
            <div><small>Operator</small><b id="ai-org"></b><span id="ai-company" hidden></span></div>
          </div>
          <dl class="ai-who">
            <div id="ai-name-row" hidden><dt>Nume</dt><dd id="ai-name"></dd></div>
            <div><dt>Email</dt><dd id="ai-email"></dd></div>
            <div><dt>Rol</dt><dd id="ai-role"></dd></div>
          </dl>
          <div class="af-note" id="ai-existing" hidden><b>Ai deja o parolă pe bilete.online</b>Emailul tău face parte și din echipa altui operator, așa că intri cu aceeași parolă. Nu trebuie să alegi alta.</div>
          <p class="af-error" id="ai-error" role="alert" hidden></p>
          <form class="af-form" id="ai-form" novalidate>
            <!-- lets password managers save the password for the right account -->
            <input type="email" id="ai-username" name="email" autocomplete="username" hidden>
            <div class="af-field" id="ai-pass-f">
              <label for="ai-pass">Parola</label>
              <div class="af-pass">
                <input id="ai-pass" name="password" type="password" autocomplete="new-password" minlength="8" maxlength="100" placeholder="Minim 8 caractere" aria-describedby="ai-strength">
                <?= $aiEye ?>
              </div>
              <div class="af-meter" id="ai-meter" data-score="0" aria-hidden="true"><i></i><i></i><i></i><i></i></div>
              <span class="af-hint" id="ai-strength" aria-live="polite"></span>
            </div>
            <div class="af-field" id="ai-pass2-f">
              <label for="ai-pass2">Confirmă parola</label>
              <div class="af-pass">
                <input id="ai-pass2" name="password_confirmation" type="password" autocomplete="new-password" minlength="8" maxlength="100" placeholder="Reintrodu parola" aria-describedby="ai-match">
                <?= $aiEye ?>
              </div>
              <span class="af-hint" id="ai-match" aria-live="polite"></span>
            </div>
            <div class="af-field">
              <label for="ai-phone">Telefon <small>(opțional)</small></label>
              <input id="ai-phone" name="phone" type="tel" autocomplete="tel" inputmode="tel" maxlength="30" placeholder="07xx xxx xxx" aria-describedby="ai-phone-hint">
              <span class="af-hint" id="ai-phone-hint">Îl vede operatorul, ca să te poată contacta.</span>
            </div>
            <button class="btn btn-primary af-wide" id="ai-submit" type="submit">Activează contul</button>
          </form>
        </div>

        <!-- SUCCESS -->
        <div class="af-view" id="ai-done-view" hidden>
          <span class="af-badge" aria-hidden="true"><?= v2_ic('check') ?></span>
          <p class="af-card-k is-ok">Gata</p>
          <h2 class="af-card-h" id="ai-done-h" tabindex="-1">Contul e activ!</h2>
          <p class="af-card-p" id="ai-done-p">Intră în contul <strong id="ai-done-org"></strong> cu emailul <strong id="ai-done-email"></strong> și <span id="ai-done-pass">parola aleasă</span>.</p>
          <div class="af-help">
            <b>Check-in la intrare</b>
            <ul>
              <li><?= v2_ic('check') ?>Din aplicația mobilă de scanare, cu aceleași date de autentificare.</li>
              <li><?= v2_ic('check') ?>Vezi activitățile și informațiile la care ți-a dat acces operatorul.</li>
            </ul>
          </div>
          <div class="af-actions">
            <a class="btn btn-primary" id="ai-login" href="/autentificare?ca=venue">Intră în cont<?= v2_ic('arrow-right') ?></a>
          </div>
        </div>

        <!-- INVALID LINK, OR THE CHECK FAILED -->
        <div class="af-view" id="ai-bad-view" hidden>
          <span class="af-badge is-bad" aria-hidden="true"><?= v2_ic('x') ?></span>
          <p class="af-card-k is-bad" id="ai-bad-k">Link invalid</p>
          <h2 class="af-card-h" id="ai-bad-h" tabindex="-1">Invitația nu mai e valabilă</h2>
          <p class="af-card-p" id="ai-bad-p">Invitația a expirat, a fost deja folosită sau linkul e incomplet. Cere-i operatorului să ți-o retrimită: o invitație e valabilă 7 zile.</p>
          <div class="af-actions">
            <button class="btn btn-primary" type="button" id="ai-retry" hidden>Încearcă din nou</button>
            <a class="btn btn-primary" id="ai-bad-login" href="/autentificare?ca=venue">Mergi la autentificare</a>
          </div>
          <p class="af-small" id="ai-bad-note">Ai activat deja contul? Intră cu emailul și parola ta. <a href="/parola-uitata?ca=venue">Ai uitat parola?</a></p>
        </div>
      </section>
    </div>
  </section>
</main>
<?php include __DIR__ . '/../includes/v2/footer.php'; ?>
