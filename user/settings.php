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

$pageTitleRaw = 'Setări cont — ' . SITE_NAME;
$pageDescription = 'Gestionează datele tale de contact, parola, preferințele pentru recomandări, profilul familiei, notificările, plățile și opțiunile de confidențialitate.';
$canonicalUrl = SITE_URL . '/cont/setari';
$noindex = true;
$skipPageCache = true;

$v2Styles = ['account.css', 'settings.css'];
$v2Scripts = ['vendor/qrcode.js', 'account.js', 'settings.js'];
$v2LegacyScripts = ['assets/js/config.js', 'assets/js/utils.js', 'assets/js/api.js', 'assets/js/auth.js'];
$v2HeadExtra = v2_account_client_config();

$stTabs = [
    'personal' => 'Date personale',
    'security' => 'Securitate',
    'preferences' => 'Preferințe',
    'family' => 'Familie',
    'notifications' => 'Notificări',
    'payments' => 'Plăți',
    'privacy' => 'Privacy / GDPR',
];
$stPanelIds = ['preferences' => 'profil-preferinte', 'family' => 'familie'];
$stPanel = function (string $key) use ($stPanelIds): string { return $stPanelIds[$key] ?? 'st-p-' . $key; };
$stNotifications = [
    'tickets' => ['Bilete și comenzi', 'confirmări, QR, modificări, remindere înainte de activitate'],
    'points' => ['Puncte bonus', 'puncte câștigate, puncte care expiră, campanii loyalty'],
    'recommendations' => ['Recomandări', 'activități potrivite după profil, oraș și istoric'],
    'newsletter' => ['Newsletter', 'ghiduri, oferte, activități noi și idei de weekend'],
    'reviews' => ['Recenzii', 'remindere pentru activități evaluate și status moderare'],
    'support' => ['Support', 'răspunsuri la tichete și actualizări de retur'],
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
      <h1 id="st-guard-h">Trebuie să fii autentificat</h1>
      <p>Intră în cont pentru a-ți vedea setările.</p>
      <a class="btn btn-primary" href="/autentificare?redirect=%2Fcont%2Fsetari">Intră în cont<?= v2_ic('arrow-right') ?></a>
    </section>

    <div class="acc-body" id="st-content">
      <!-- HERO -->
      <section class="acc-hero st-hero" aria-labelledby="st-h">
        <div>
          <p class="acc-kicker">Account settings</p>
          <h1 class="acc-h" id="st-h">Setări cont</h1>
          <p class="acc-lead">Administrează datele personale, securitatea, preferințele pentru recomandări, notificările, plățile și opțiunile de confidențialitate.</p>
          <div class="st-cta">
            <button class="btn btn-light" type="button" data-open-tab="preferences"><?= v2_ic('star') ?>Completează preferințe</button>
            <button class="btn btn-outline-light" type="button" data-open-tab="privacy">Privacy &amp; GDPR</button>
          </div>
        </div>
        <article class="st-completion" aria-labelledby="st-completion-k">
          <p class="acc-k" id="st-completion-k">Profile completion</p>
          <p class="st-completion-v"><span id="st-completion">0</span>%</p>
          <p class="st-completion-l">profil complet pentru recomandări</p>
          <div class="st-bar" role="progressbar" id="st-completion-bar" aria-label="Profil completat" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0"><i></i></div>
          <p class="st-completion-hint" id="st-missing">Toate câmpurile esențiale sunt completate.</p>
        </article>
      </section>

      <!-- SUMMARY -->
      <section class="st-stats" aria-label="Pe scurt">
        <article class="st-stat"><p class="acc-k">Securitate</p><p class="st-stat-v" id="st-s-security">—</p><p class="st-stat-p" id="st-s-security-p">se verifică…</p></article>
        <article class="st-stat is-mint"><p class="acc-k">Preferințe</p><p class="st-stat-v" id="st-s-prefs">—</p><p class="st-stat-p">pentru recomandări</p></article>
        <article class="st-stat"><p class="acc-k">Notificări</p><p class="st-stat-v" id="st-s-notif">—</p><p class="st-stat-p">email + push</p></article>
        <article class="st-stat is-warm"><p class="acc-k">GDPR</p><p class="st-stat-v">control</p><p class="st-stat-p">export / ștergere</p></article>
      </section>

      <!-- TABS -->
      <div class="st-tabs-wrap" id="st-tabs-wrap">
        <div class="st-tabs" role="tablist" aria-label="Secțiuni setări" data-tabs>
          <?php $first = true; foreach ($stTabs as $key => $label): ?>
          <button class="st-tab" type="button" role="tab" id="st-tab-<?= $key ?>" aria-controls="<?= $stPanel($key) ?>" aria-selected="<?= $first ? 'true' : 'false' ?>"<?= $first ? '' : ' tabindex="-1"' ?>><?= v2_e($label) ?></button>
          <?php $first = false; endforeach; ?>
        </div>
      </div>
      <p class="st-flash" id="st-flash" role="status" aria-live="polite"></p>
      <div class="st-callout is-bad" id="st-load-error" role="alert" hidden>
        <b>Nu am putut încărca datele contului</b>
        <p>Verifică conexiunea și încearcă din nou. Până atunci nu salvăm nimic, ca să nu suprascriem datele tale.</p>
        <div class="st-actions"><button class="btn btn-ghost" type="button" id="st-retry">Reîncearcă</button></div>
      </div>

      <!-- 1. PERSONAL -->
      <section class="acc-panel st-panel" id="<?= $stPanel('personal') ?>" role="tabpanel" aria-labelledby="st-tab-personal">
        <p class="acc-k">Date personale</p>
        <h2>Cine ești și cum te contactăm</h2>
        <p class="st-lead">Aceste informații apar pe bilete, pe email-urile de confirmare și pe facturi.</p>
        <form class="st-form" id="st-profile-form" novalidate>
          <div class="st-grid">
            <div class="acc-field"><label for="st-first">Prenume</label><span class="acc-input is-plain"><input id="st-first" autocomplete="given-name" maxlength="100" required></span></div>
            <div class="acc-field"><label for="st-last">Nume</label><span class="acc-input is-plain"><input id="st-last" autocomplete="family-name" maxlength="100" required></span></div>
            <div class="acc-field">
              <label for="st-email">Email</label>
              <span class="acc-input is-plain"><input id="st-email" type="email" autocomplete="email" disabled aria-describedby="st-email-note"></span>
              <small class="st-verified" id="st-verified">—</small>
              <small class="st-note" id="st-email-note">Emailul nu poate fi schimbat de aici. <a href="/contact?motiv=altele">Scrie-ne</a> dacă trebuie actualizat.</small>
            </div>
            <div class="acc-field"><label for="st-phone">Telefon</label><span class="acc-input is-plain"><input id="st-phone" type="tel" autocomplete="tel" maxlength="50" placeholder="0722 123 456"></span></div>
            <div class="acc-field">
              <label for="st-city">Oraș principal</label>
              <span class="acc-select"><select id="st-city" data-city-select><option value="">— alege orașul —</option></select><?= $stCaret ?></span>
              <small class="st-note">Alege din lista de orașe acoperite. Folosit pentru recomandări locale.</small>
            </div>
            <div class="acc-field">
              <label for="st-birth">Data nașterii</label>
              <span class="acc-input is-plain"><input id="st-birth" inputmode="numeric" maxlength="10" placeholder="zz/ll/aaaa" autocomplete="bday" aria-describedby="st-birth-note"></span>
              <small class="st-note" id="st-birth-note">Format: zi/lună/an (exemplu: 15/06/1992)</small>
            </div>
            <div class="acc-field is-wide">
              <label for="st-gender">Gen (opțional)</label>
              <span class="acc-select"><select id="st-gender"><option value="">— alege —</option><option value="female">Femeie</option><option value="male">Bărbat</option><option value="other">Altul</option></select><?= $stCaret ?></span>
            </div>
          </div>
          <p class="st-error" id="st-profile-error" role="alert" hidden></p>
          <div class="st-actions">
            <button class="btn btn-primary" type="submit" id="st-profile-save">Salvează datele</button>
            <button class="btn btn-ghost" type="button" id="st-verify-send" hidden>Trimite link verificare</button>
          </div>
        </form>
      </section>

      <!-- 2. SECURITY -->
      <section class="acc-panel st-panel" id="<?= $stPanel('security') ?>" role="tabpanel" aria-labelledby="st-tab-security" hidden>
        <p class="acc-k">Securitate</p>
        <h2>Parolă, sesiuni și protecția contului</h2>
        <div class="st-two">
          <div class="st-stack">
            <div class="st-block">
              <h3>Schimbă parola</h3>
              <form class="st-form" id="st-pass-form" novalidate>
                <div class="acc-field"><label for="st-pass-current">Parolă curentă</label><span class="acc-input is-plain"><input id="st-pass-current" type="password" autocomplete="current-password" required></span></div>
                <div class="st-grid">
                  <div class="acc-field"><label for="st-pass-new">Parolă nouă</label><span class="acc-input is-plain"><input id="st-pass-new" type="password" autocomplete="new-password" minlength="8" required></span></div>
                  <div class="acc-field"><label for="st-pass-confirm">Confirmă parola nouă</label><span class="acc-input is-plain"><input id="st-pass-confirm" type="password" autocomplete="new-password" minlength="8" required></span></div>
                </div>
                <p class="st-error" id="st-pass-error" role="alert" hidden></p>
                <div class="st-actions"><button class="btn btn-primary" type="submit" id="st-pass-save">Actualizează parola</button></div>
              </form>
            </div>

            <div class="st-block" id="st-2fa">
              <div class="st-block-head">
                <div>
                  <h3>Autentificare în doi pași</h3>
                  <p class="st-note">Recomandat pentru protecție suplimentară. Vei avea nevoie de un cod TOTP de 6 cifre la fiecare login.</p>
                </div>
                <span class="acc-tag" id="st-2fa-tag">inactiv</span>
              </div>
              <div id="st-2fa-off">
                <button class="btn btn-primary" type="button" id="st-2fa-start">Activează 2FA</button>
              </div>
              <div class="st-2fa-setup" id="st-2fa-setup" hidden>
                <div class="st-2fa-qr">
                  <div class="st-qr-box" id="st-2fa-qr"></div>
                  <p class="st-note">Scanează QR cu Google Authenticator, Authy, 1Password sau Bitwarden.</p>
                </div>
                <div class="st-2fa-steps">
                  <p class="st-note">Sau introdu secretul manual:</p>
                  <code class="st-code" id="st-2fa-secret"></code>
                  <button class="btn btn-ghost st-copy" type="button" id="st-2fa-secret-copy">Copiază secretul</button>
                  <label class="st-strong" for="st-2fa-code">Pas 2 — introdu codul de 6 cifre afișat în aplicație:</label>
                  <span class="acc-input is-plain"><input class="st-otp" id="st-2fa-code" inputmode="numeric" maxlength="6" autocomplete="one-time-code" placeholder="123456"></span>
                  <p class="st-error" id="st-2fa-error" role="alert" hidden></p>
                  <div class="st-actions">
                    <button class="btn btn-primary" type="button" id="st-2fa-confirm" disabled>Verifică și activează</button>
                    <button class="btn btn-ghost" type="button" id="st-2fa-cancel">Renunță</button>
                  </div>
                </div>
                <div class="st-recovery">
                  <b>Coduri de recuperare</b>
                  <p>Salvează aceste coduri într-un loc sigur — fiecare poate fi folosit o singură dată dacă pierzi accesul la aplicație.</p>
                  <ol class="st-codes" id="st-2fa-codes"></ol>
                  <button class="btn btn-ghost" type="button" data-copy-codes="st-2fa-codes">Copiază codurile</button>
                </div>
              </div>
              <div id="st-2fa-on" hidden>
                <p class="st-note" id="st-2fa-summary"></p>
                <div class="st-actions">
                  <button class="btn st-danger" type="button" data-inline="st-2fa-disable">Dezactivează 2FA</button>
                  <button class="btn btn-ghost" type="button" data-inline="st-2fa-regen">Regenerează coduri</button>
                </div>
                <form class="st-inline" id="st-2fa-disable" hidden novalidate>
                  <label for="st-2fa-disable-pass">Confirmă parola pentru a dezactiva 2FA:</label>
                  <div class="st-inline-row">
                    <span class="acc-input is-plain"><input id="st-2fa-disable-pass" type="password" autocomplete="current-password"></span>
                    <button class="btn st-danger" type="submit">Dezactivează</button>
                    <button class="btn btn-ghost" type="button" data-inline-close>Renunță</button>
                  </div>
                  <p class="st-error" role="alert" hidden></p>
                </form>
                <form class="st-inline" id="st-2fa-regen" hidden novalidate>
                  <label for="st-2fa-regen-pass">Confirmă parola pentru a regenera codurile (cele vechi nu vor mai funcționa):</label>
                  <div class="st-inline-row">
                    <span class="acc-input is-plain"><input id="st-2fa-regen-pass" type="password" autocomplete="current-password"></span>
                    <button class="btn btn-primary" type="submit">Regenerează</button>
                    <button class="btn btn-ghost" type="button" data-inline-close>Renunță</button>
                  </div>
                  <p class="st-error" role="alert" hidden></p>
                </form>
                <div class="st-recovery" id="st-2fa-newcodes" hidden>
                  <b>Codurile noi de recuperare</b>
                  <p>Salvează-le într-un loc sigur. Cele vechi nu mai funcționează.</p>
                  <ol class="st-codes" id="st-2fa-newcodes-list"></ol>
                  <button class="btn btn-ghost" type="button" data-copy-codes="st-2fa-newcodes-list">Copiază codurile</button>
                </div>
              </div>
            </div>

            <div class="st-block">
              <h3>Sesiuni active</h3>
              <p class="st-note">Dispozitivele unde ești conectat acum. Închide-le pe cele necunoscute.</p>
              <p class="st-state" id="st-sessions-state">Se încarcă sesiunile…</p>
              <ul class="st-sessions" id="st-sessions" hidden></ul>
              <div class="st-actions">
                <button class="btn st-danger" type="button" data-confirm-sessions="others">Închide restul sesiunilor</button>
                <button class="btn btn-ghost" type="button" data-confirm-sessions="all">Deconectare totală (inclusiv aceasta)</button>
              </div>
              <div class="st-inline" id="st-sessions-confirm" hidden>
                <p id="st-sessions-confirm-t"></p>
                <div class="st-inline-row">
                  <button class="btn st-danger" type="button" id="st-sessions-yes">Da, continuă</button>
                  <button class="btn btn-ghost" type="button" id="st-sessions-no">Renunță</button>
                </div>
              </div>
            </div>
          </div>
          <aside class="st-aside is-mint">
            <b>Status securitate</b>
            <p id="st-security-status">Se verifică…</p>
          </aside>
        </div>
      </section>

      <!-- 3. PREFERENCES -->
      <section class="acc-panel st-panel" id="<?= $stPanel('preferences') ?>" role="tabpanel" aria-labelledby="st-tab-preferences" hidden>
        <p class="acc-k">Preferințe recomandări</p>
        <h2>Ce fel de activități vrei să primești?</h2>
        <p class="st-lead">Aceste câmpuri sunt cele mai importante pentru pagina de <a href="/cont/recomandari">Recomandări</a>. Cu cât sunt mai clare, cu atât putem propune activități mai potrivite.</p>
        <div class="st-two">
          <div class="st-stack">
            <div class="st-block">
              <h3>Categorii preferate</h3>
              <p class="st-state" id="st-cats-state">Se încarcă categoriile…</p>
              <div class="st-chips" id="st-cats" role="group" aria-label="Categorii preferate"></div>
              <p class="st-note">Poți alege până la 20 de categorii.</p>
            </div>
            <div class="st-block">
              <h3>Orașe de interes</h3>
              <div class="st-grid">
                <div class="acc-field">
                  <label for="st-city2">Oraș principal</label>
                  <span class="acc-select"><select id="st-city2" data-city-select><option value="">— alege orașul —</option></select><?= $stCaret ?></span>
                  <small class="st-note">Folosit ca semnal principal pentru recomandări.</small>
                </div>
                <div class="acc-field">
                  <label for="st-radius">Rază recomandări</label>
                  <span class="acc-select"><select id="st-radius"><option value="">— alege —</option><option value="city">Doar orașul meu</option><option value="25km">+25 km</option><option value="50km">+50 km</option><option value="country">Toată țara</option></select><?= $stCaret ?></span>
                </div>
              </div>
              <div class="acc-field st-multi">
                <label for="st-city-search">Orașe secundare unde mergi des</label>
                <span class="acc-input"><?= v2_ic('magnifying-glass') ?><input id="st-city-search" type="search" placeholder="Caută oraș…" autocomplete="off" aria-controls="st-city-list"></span>
                <div class="st-chips is-selected" id="st-sec-chips" aria-live="polite"></div>
                <ul class="st-city-list" id="st-city-list" aria-label="Orașe secundare"></ul>
              </div>
            </div>
            <div class="st-block">
              <h3>Buget și ritm</h3>
              <div class="st-grid is-3">
                <div class="acc-field"><label for="st-budget">Buget per persoană</label><span class="acc-select"><select id="st-budget"><option value="">— alege —</option><option value="under_50">sub 50 lei</option><option value="50_120">50–120 lei</option><option value="120_250">120–250 lei</option><option value="250_plus">250+ lei</option></select><?= $stCaret ?></span></div>
                <div class="acc-field"><label for="st-frequency">Frecvență</label><span class="acc-select"><select id="st-frequency"><option value="">— alege —</option><option value="spontaneous">spontan</option><option value="monthly">2-3 ori / lună</option><option value="weekly">săptămânal</option></select><?= $stCaret ?></span></div>
                <div class="acc-field"><label for="st-moment">Tip moment</label><span class="acc-select"><select id="st-moment"><option value="">— alege —</option><option value="weekend">weekend</option><option value="afterwork">după job</option><option value="vacations">vacanțe</option><option value="anytime">oricând</option></select><?= $stCaret ?></span></div>
              </div>
            </div>
          </div>
          <aside class="st-aside is-deep">
            <p class="acc-k">Recommendation engine</p>
            <h3>Semnale utile</h3>
            <ul class="st-signals" id="st-signals">
              <li data-signal="cats">Categorii preferate <span></span></li>
              <li data-signal="cities">Orașe și rază</li>
              <li data-signal="budget">Buget</li>
              <li data-signal="frequency">Frecvență</li>
              <li data-signal="moment">Tip moment</li>
              <li class="is-auto">Istoric comenzi (automat)</li>
            </ul>
            <button class="btn btn-light" type="button" id="st-prefs-save">Salvează preferințele</button>
          </aside>
        </div>
      </section>

      <!-- 4. FAMILY -->
      <section class="acc-panel st-panel" id="<?= $stPanel('family') ?>" role="tabpanel" aria-labelledby="st-tab-family" hidden>
        <p class="acc-k">Familie &amp; beneficiari</p>
        <h2>Beneficiari salvați și profil familie</h2>
        <p class="st-lead">Adaugă persoane pentru care cumperi des bilete (copil, partener, prieten). Apar automat la checkout și ne ajută să-ți propunem activități potrivite.</p>
        <div class="st-two">
          <div class="st-stack">
            <p class="st-state" id="st-ben-state">Se încarcă beneficiarii…</p>
            <ul class="st-bens" id="st-bens" hidden></ul>
            <form class="st-block st-ben-form" id="st-ben-form" hidden novalidate>
              <h3 id="st-ben-form-h">Beneficiar nou</h3>
              <div class="st-grid">
                <div class="acc-field is-wide"><label for="st-ben-name">Nume complet</label><span class="acc-input is-plain"><input id="st-ben-name" maxlength="150" autocomplete="off" required></span></div>
                <div class="acc-field"><label for="st-ben-relation">Relație</label><span class="acc-select"><select id="st-ben-relation"><option value="">— alege —</option><option value="self">Eu însumi</option><option value="partner">Partener</option><option value="child">Copil</option><option value="parent">Părinte</option><option value="sibling">Frate / soră</option><option value="friend">Prieten</option><option value="other">Altă relație</option></select><?= $stCaret ?></span></div>
                <div class="acc-field"><label for="st-ben-birth">Data nașterii</label><span class="acc-input is-plain"><input id="st-ben-birth" type="date"></span></div>
                <div class="acc-field"><label for="st-ben-email">Email (opțional)</label><span class="acc-input is-plain"><input id="st-ben-email" type="email" maxlength="200" autocomplete="off"></span></div>
                <div class="acc-field"><label for="st-ben-phone">Telefon (opțional)</label><span class="acc-input is-plain"><input id="st-ben-phone" type="tel" maxlength="30" autocomplete="off"></span></div>
                <div class="acc-field is-wide"><label for="st-ben-notes">Note (opțional)</label><textarea class="st-textarea" id="st-ben-notes" maxlength="1000" rows="3" placeholder="alergii, preferințe, mărime tricou…"></textarea></div>
              </div>
              <p class="st-error" id="st-ben-error" role="alert" hidden></p>
              <div class="st-actions">
                <button class="btn btn-primary" type="submit" id="st-ben-save">Salvează</button>
                <button class="btn btn-ghost" type="button" id="st-ben-cancel">Renunță</button>
              </div>
            </form>
          </div>
          <aside class="st-aside is-mint">
            <b>De ce contează?</b>
            <p>Beneficiarii frecvenți te scapă de retastarea numelor la checkout. Profilul familiei (vârste, interese) ajută motorul de recomandări să-ți propună activități potrivite pentru toată echipa.</p>
            <small>Limită: 25 beneficiari per cont.</small>
          </aside>
        </div>
      </section>

      <!-- 5. NOTIFICATIONS -->
      <section class="acc-panel st-panel" id="<?= $stPanel('notifications') ?>" role="tabpanel" aria-labelledby="st-tab-notifications" hidden>
        <p class="acc-k">Notificări &amp; newsletter</p>
        <h2>Ce vrei să primești?</h2>
        <div class="st-toggles">
          <?php foreach ($stNotifications as $key => [$title, $desc]): ?>
          <label class="st-toggle" for="st-n-<?= $key ?>">
            <span class="st-toggle-t"><b><?= v2_e($title) ?></b><small><?= v2_e($desc) ?></small></span>
            <input class="st-switch" type="checkbox" role="switch" id="st-n-<?= $key ?>" data-notif="<?= $key ?>">
          </label>
          <?php endforeach; ?>
        </div>
        <div class="st-callout is-warm">
          <b>Newsletter personalizat</b>
          <p>Poți primi recomandări pe orașe, activități pentru copii, oferte, puncte care expiră și ghiduri editoriale. Dezabonarea e disponibilă în orice email.</p>
        </div>
        <div class="st-actions"><button class="btn btn-primary" type="button" id="st-notif-save">Salvează notificările</button></div>
      </section>

      <!-- 6. PAYMENTS -->
      <section class="acc-panel st-panel" id="<?= $stPanel('payments') ?>" role="tabpanel" aria-labelledby="st-tab-payments" hidden>
        <p class="acc-k">Plăți · Stripe</p>
        <h2>Metode de plată și facturare</h2>
        <p class="st-lead">Salvează cardul ca să nu-l mai introduci la fiecare comandă. Datele cardului sunt stocate la Stripe — niciodată pe serverele bilete.online.</p>
        <div class="st-two">
          <div class="st-stack">
            <div class="st-callout is-bad" id="st-pay-off" hidden>
              <b>Procesatorul de plăți nu este încă activ</b>
              <p>Echipa bilete.online finalizează integrarea Stripe. Vei putea salva carduri imediat ce e gata.</p>
            </div>
            <p class="st-state" id="st-cards-state">Se încarcă cardurile…</p>
            <ul class="st-cards" id="st-cards" hidden></ul>
            <div class="st-empty" id="st-cards-empty" hidden><b>Niciun card salvat</b><p>Adaugă un card ca să cumperi mai rapid data viitoare.</p></div>
            <div class="st-actions is-end" id="st-card-add-row" hidden><button class="btn btn-primary" type="button" id="st-card-add"><?= v2_ic('plus') ?>Adaugă card</button></div>
            <div class="st-block" id="st-card-form" hidden>
              <h3>Card nou</h3>
              <p class="st-note">Datele cardului sunt criptate și trimise direct la Stripe.</p>
              <div class="st-card-element" id="st-card-element"></div>
              <p class="st-error" id="st-card-error" role="alert" hidden></p>
              <div class="st-actions">
                <button class="btn btn-primary" type="button" id="st-card-save" disabled>Salvează cardul</button>
                <button class="btn btn-ghost" type="button" id="st-card-cancel">Renunță</button>
              </div>
            </div>
          </div>
          <aside class="st-aside">
            <b>Date facturare</b>
            <p>Salvăm datele de facturare cu profilul tău, pe baza câmpurilor din tab-ul „Date personale” (nume + adresă).</p>
            <button class="btn btn-ghost" type="button" data-open-tab="personal">Mergi la datele personale</button>
            <small>PCI-DSS: bilete.online nu stochează niciodată numere de card. Stocăm doar un identificator opac (Stripe payment method id) + brand + ultimele 4 cifre pentru afișare.</small>
          </aside>
        </div>
      </section>

      <!-- 7. PRIVACY -->
      <section class="acc-panel st-panel" id="<?= $stPanel('privacy') ?>" role="tabpanel" aria-labelledby="st-tab-privacy" hidden>
        <p class="acc-k">Privacy &amp; GDPR</p>
        <h2>Controlezi datele tale</h2>
        <div class="st-privacy">
          <article class="st-block">
            <h3>Export date personale</h3>
            <p class="st-note">Descarcă o copie cu datele de cont, comenzile, biletele, preferințele, beneficiarii și punctele.</p>
            <div class="st-export" id="st-export" tabindex="-1"><p class="st-state">Se verifică exporturile…</p></div>
          </article>
          <article class="st-block is-mint">
            <h3>Personalizare recomandări</h3>
            <p class="st-note">Permite folosirea istoricului, recenziilor și preferințelor pentru recomandări mai relevante.</p>
            <label class="st-toggle is-inline" for="st-personalization">
              <input class="st-switch" type="checkbox" role="switch" id="st-personalization">
              <b id="st-personalization-l">Activ</b>
            </label>
          </article>
          <article class="st-block is-warm">
            <h3>Tracking marketing</h3>
            <p class="st-note">Controlează consimțământul pentru pixeli, analytics și campanii personalizate.</p>
            <div class="st-actions">
              <button class="btn btn-ghost" type="button" data-cc-action="open">Setări cookies</button>
              <a class="st-link" href="/cookies">Politica de cookies</a>
            </div>
          </article>
          <article class="st-block is-danger">
            <h3>Ștergere cont</h3>
            <p class="st-note">Datele personale vor fi anonimizate. Comenzile rămân în istoricul fiscal conform obligațiilor legale. Nu poți șterge contul dacă ai bilete viitoare neutilizate.</p>
            <form class="st-form" id="st-delete-form" novalidate>
              <div class="acc-field"><label for="st-delete-pass">Parola curentă</label><span class="acc-input is-plain"><input id="st-delete-pass" type="password" autocomplete="current-password" required></span></div>
              <div class="acc-field"><label for="st-delete-reason">Motivul ștergerii (opțional)</label><textarea class="st-textarea" id="st-delete-reason" maxlength="500" rows="3"></textarea></div>
              <label class="st-check" for="st-delete-confirm"><input type="checkbox" id="st-delete-confirm"><span>Înțeleg că datele mele vor fi anonimizate și că această acțiune este definitivă.</span></label>
              <p class="st-error" id="st-delete-error" role="alert" hidden></p>
              <div class="st-actions"><button class="btn st-danger" type="submit" id="st-delete-submit" disabled>Șterge contul</button></div>
              <div class="st-inline" id="st-delete-final" hidden>
                <p>Ultimul pas: contul va fi anonimizat și nu îl mai poți recupera.</p>
                <div class="st-inline-row">
                  <button class="btn st-danger" type="button" id="st-delete-yes">Da, șterge definitiv contul</button>
                  <button class="btn btn-ghost" type="button" id="st-delete-no">Renunță</button>
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
