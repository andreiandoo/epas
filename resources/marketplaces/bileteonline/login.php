<?php
/**
 * bilete.online — /autentificare (alias /login), v2 "Arcada"
 *
 * Unified auth page covering 4 flows in one shell:
 *   - Client × Login    → BileteOnlineAuth.loginCustomer(email, password) (+ the 2FA step)
 *   - Client × Register → BileteOnlineAuth.registerCustomer({...})
 *   - Venue  × Login    → BileteOnlineAuth.loginOrganizer(email, password)
 *   - Venue  × Register → BileteOnlineAuth.registerOrganizer({...})
 *
 * assets/v2/js/login.js switches account type and mode and posts through BileteOnlineAuth, which manages tokens,
 * sessions, redirects and the bileteonline:auth:login event. The copy of each state is defined once below and handed
 * to the script, so the first paint already shows the right texts.
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
        'login' => [
            'hero' => 'Intră în contul tău.',
            'heroText' => 'Găsește rapid biletele, comenzile, punctele bonus, cardurile cadou și preferințele tale.',
            'form' => 'Login client',
            'formText' => 'Folosește emailul cu care ai cumpărat sau cu care ți-ai creat contul.',
            'submit' => 'Intră în contul client',
        ],
        'register' => [
            'hero' => 'Creează cont pentru biletele tale.',
            'heroText' => 'Un cont client îți păstrează biletele, comenzile, punctele bonus, recenziile și cardurile cadou într-un singur loc.',
            'form' => 'Cont client nou',
            'formText' => 'Creează cont ca să ai acces la bilete, comenzi, puncte bonus și carduri cadou.',
            'submit' => 'Creează cont client',
        ],
    ],
    'venue' => [
        'login' => [
            'hero' => 'Intră în dashboard-ul locației.',
            'heroText' => 'Acces pentru locații, organizatori și staff: activități, bilete, comenzi, scanări, recenzii și rapoarte.',
            'form' => 'Login locație',
            'formText' => 'Intră cu emailul de administrator sau staff primit pentru locația ta.',
            'submit' => 'Intră în dashboard locație',
        ],
        'register' => [
            'hero' => 'Listează-ți locația.',
            'heroText' => 'Solicită cont pentru locația ta și transformă activitățile în bilete online cu QR, pagini SEO și dashboard.',
            'form' => 'Solicită cont locație',
            'formText' => 'Trimite detaliile locației. Conturile de locație pot fi verificate înainte de activare.',
            'submit' => 'Solicită cont de locație',
        ],
    ],
];
$now = $authCopy[$preselect][$preselectMode];
$isVenue = $preselect === 'venue';
$isLogin = $preselectMode === 'login';
$forType = static fn (string $type): string => $type === $preselect ? '' : ' hidden';

$faqs = [
    ['Am cumpărat fără cont. Cum intru?', 'Dacă ai cumpărat fără cont, poți recupera comanda folosind emailul și numărul comenzii. În unele cazuri, contul poate fi creat automat după checkout și trebuie doar să setezi parola.'],
    ['Login-ul de client este diferit de login-ul pentru locații?', 'Da. Clienții intră pentru bilete, comenzi și puncte. Locațiile intră pentru administrarea activităților, comenzilor, scanărilor și rapoartelor.'],
    ['Ce fac dacă linkul de resetare a expirat?', 'Soliciți un link nou din pagina de resetare parolă. Linkurile temporare expiră din motive de securitate.'],
    ['Cum obțin cont de locație?', 'Poți selecta Locație și Creează cont sau poți merge în pagina Pentru locații. Contul poate necesita verificare înainte de activare.'],
];

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

$ticketSvg = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 9a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2 2 2 0 0 0 0 4 2 2 0 0 1-2 2H5a2 2 0 0 1-2-2 2 2 0 0 0 0-4Z"/><path d="M9 7v10" stroke-dasharray="2 2"/></svg>';
$venueSvg = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 21h18M5 21V7l8-4 6 3v15M9 10h1M9 14h1M14 10h1M14 14h1"/></svg>';

include __DIR__ . '/includes/v2/head.php';
include __DIR__ . '/includes/v2/header.php';
?>
<main id="main" class="page-main" tabindex="-1">

  <section class="au" id="au" data-type="<?= $preselect ?>" data-mode="<?= $preselectMode ?>" aria-labelledby="au-title">
    <div class="wrap">
      <nav class="crumbs" aria-label="Breadcrumb"><a href="/">Acasă</a><span aria-hidden="true">/</span><span aria-current="page">Autentificare</span></nav>

      <div class="au-grid">
        <div class="au-intro">
          <p class="kicker">Client · Locație · Conturi</p>
          <h1 class="au-h" id="au-title"><?= preg_replace('/\S+-\S+/u', '<span class="au-nw">$0</span>', v2_e($now['hero'])) /* hyphenated words stay whole, see .au-nw */ ?></h1>
          <p class="au-text" id="au-text"><?= v2_e($now['heroText']) ?></p>
          <div class="au-quick">
            <button class="btn btn-primary" type="button" data-go-type="client">Intră ca client</button>
            <button class="btn btn-ghost" type="button" data-go-type="venue">Intră ca locație</button>
          </div>
          <ul class="au-tiles" aria-label="Ce găsești în conturi">
            <li><small>Client</small><b>bilete</b></li>
            <li><small>Bonus</small><b>puncte</b></li>
            <li><small>Venue</small><b>QR</b></li>
          </ul>
        </div>

        <div class="au-card" id="au-card">
          <div class="au-panel">
            <div class="au-tabs">
              <div class="au-seg" role="group" aria-label="Tip de cont">
                <button type="button" data-set-type="client" aria-pressed="<?= $isVenue ? 'false' : 'true' ?>">Client</button>
                <button type="button" data-set-type="venue" aria-pressed="<?= $isVenue ? 'true' : 'false' ?>">Locație</button>
              </div>
              <div class="au-seg is-mode" role="group" aria-label="Ce vrei să faci">
                <button type="button" data-set-mode="login" aria-pressed="<?= $isLogin ? 'true' : 'false' ?>">Login</button>
                <button type="button" data-set-mode="register" aria-pressed="<?= $isLogin ? 'false' : 'true' ?>">Creează cont</button>
              </div>
            </div>

            <div class="au-body">
              <div class="au-form-head">
                <div>
                  <p class="kicker" id="au-form-kicker"><?= $isVenue ? 'Cont locație' : 'Cont client' ?></p>
                  <h2 id="au-form-title"><?= v2_e($now['form']) ?></h2>
                </div>
                <span class="au-type-ic" aria-hidden="true"><span data-for="client"<?= $forType('client') ?>><?= $ticketSvg ?></span><span data-for="venue"<?= $forType('venue') ?>><?= $venueSvg ?></span></span>
              </div>
              <p class="au-form-text" id="au-form-text"><?= v2_e($now['formText']) ?></p>

              <p class="au-msg" id="au-msg" role="alert" hidden></p>

              <!-- 2FA challenge: shown after a client login when the account requires it -->
              <form class="au-form" id="au-2fa" hidden>
                <div class="au-box">
                  <b>Autentificare în doi pași</b>
                  <p>Deschide aplicația de autentificare (Google Authenticator, Authy, 1Password) și introdu codul de 6 cifre. Sau folosește unul dintre codurile de recuperare salvate.</p>
                </div>
                <div class="au-field">
                  <label for="au-2fa-code">Cod TOTP sau cod de recuperare</label>
                  <input class="au-code" id="au-2fa-code" placeholder="123 456" autocomplete="one-time-code" inputmode="text" required>
                </div>
                <button class="btn btn-primary au-go" type="submit"><span data-idle>Verifică și intră</span><span data-busy><span class="spin" aria-hidden="true"></span>Se verifică…</span></button>
                <button class="au-back" type="button" id="au-2fa-cancel"><?= v2_ic('arrow-left') ?>Înapoi la login</button>
              </form>

              <!-- LOGIN -->
              <form class="au-form" id="au-login"<?= $isLogin ? '' : ' hidden' ?>>
                <div class="au-field">
                  <label for="au-login-email">Email</label>
                  <input id="au-login-email" type="email" value="<?= v2_e($prefillEmail) ?>" placeholder="<?= $isVenue ? 'email organizator / staff' : 'emailul folosit la comandă' ?>" autocomplete="email" required>
                </div>
                <div class="au-field">
                  <label for="au-login-pass">Parolă</label>
                  <div class="au-pass">
                    <input id="au-login-pass" type="password" placeholder="parola contului" autocomplete="current-password" required>
                    <button class="au-eye" type="button" data-toggle-pass aria-pressed="false" aria-label="Arată parola">arată</button>
                  </div>
                </div>
                <div class="au-line">
                  <label class="au-check"><input class="au-cb" type="checkbox" id="au-remember" checked><span>Ține-mă minte</span></label>
                  <a href="/parola-uitata">Am uitat parola</a>
                </div>
                <div class="au-box" data-for="client"<?= $forType('client') ?>>
                  <b>Ai cumpărat fără cont?</b>
                  <p>Poți recupera comanda după email și număr comandă sau îți poți seta parola pentru contul creat automat.</p>
                  <div class="au-box-links"><a href="/recuperare-comanda">Recuperează comanda</a><a href="/parola-uitata">Setează parola</a></div>
                </div>
                <div class="au-box is-neutral" data-for="venue"<?= $forType('venue') ?>>
                  <b>Acces pentru staff și locații</b>
                  <p>Folosește emailul cu care ai fost invitat în dashboard. Pentru conturi noi, solicită demo sau invită staff din contul principal.</p>
                </div>
                <button class="btn btn-primary au-go" type="submit" id="au-login-submit"><span data-idle><?= v2_e($authCopy[$preselect]['login']['submit']) ?></span><span data-busy><span class="spin" aria-hidden="true"></span>Se conectează…</span></button>
              </form>

              <!-- REGISTER -->
              <form class="au-form" id="au-register"<?= $isLogin ? ' hidden' : '' ?>>
                <div class="au-row2" data-for="client"<?= $forType('client') ?>>
                  <div class="au-field"><label for="au-first">Prenume</label><input id="au-first" placeholder="Prenume" autocomplete="given-name" required></div>
                  <div class="au-field"><label for="au-last">Nume</label><input id="au-last" placeholder="Nume" autocomplete="family-name" required></div>
                </div>
                <div class="au-row2" data-for="venue"<?= $forType('venue') ?>>
                  <div class="au-field"><label for="au-contact">Nume contact</label><input id="au-contact" placeholder="Nume și prenume" autocomplete="name" required></div>
                  <div class="au-field"><label for="au-venue">Nume locație</label><input id="au-venue" placeholder="ex. Mystery Rooms Brașov" autocomplete="organization" required></div>
                </div>
                <div class="au-field">
                  <label for="au-reg-email">Email</label>
                  <input id="au-reg-email" type="email" value="<?= v2_e($prefillEmail) ?>" placeholder="<?= $isVenue ? 'email@locatie.ro' : 'email@example.ro' ?>" autocomplete="email" required>
                </div>
                <div class="au-row2" data-for="venue"<?= $forType('venue') ?>>
                  <div class="au-field"><label for="au-venue-phone">Telefon</label><input id="au-venue-phone" type="tel" placeholder="+40..." autocomplete="tel"></div>
                  <div class="au-field"><label for="au-venue-city">Oraș</label><input id="au-venue-city" placeholder="ex. Brașov" autocomplete="address-level2"></div>
                </div>
                <div class="au-field" data-for="client"<?= $forType('client') ?>>
                  <label for="au-client-phone">Telefon (opțional)</label>
                  <input id="au-client-phone" type="tel" placeholder="0722 123 456" autocomplete="tel">
                </div>
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
                <div class="au-box" data-for="client"<?= $forType('client') ?>>
                  <b>Ce primești în cont?</b>
                  <p>Biletele tale, comenzile, punctele bonus, cardurile cadou, recenziile și preferințele pentru recomandări.</p>
                </div>
                <div class="au-box is-warn" data-for="venue"<?= $forType('venue') ?>>
                  <b>Contul de locație poate necesita aprobare.</b>
                  <p>După trimitere, echipa poate verifica locația și configura accesul la dashboard.</p>
                </div>
                <label class="au-check"><input class="au-cb" type="checkbox" id="au-terms" required><span>Accept <a href="/termeni" target="_blank" rel="noopener">Termenii și condițiile</a> și <a href="/confidentialitate" target="_blank" rel="noopener">Politica de confidențialitate</a>.</span></label>
                <label class="au-check" data-for="client"<?= $forType('client') ?>><input class="au-cb" type="checkbox" id="au-newsletter" checked><span>Vreau să primesc recomandări, oferte și idei de activități în newsletter.</span></label>
                <button class="btn btn-primary au-go" type="submit" id="au-register-submit"><span data-idle><?= v2_e($authCopy[$preselect]['register']['submit']) ?></span><span data-busy><span class="spin" aria-hidden="true"></span>Se procesează…</span></button>
              </form>

              <div class="au-foot">
                <span id="au-switch-text"><?= $isLogin ? 'Nu ai cont?' : 'Ai deja cont?' ?></span>
                <button type="button" id="au-switch"><?= $isLogin ? 'Creează unul acum' : 'Intră în cont' ?></button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <section class="au-why" aria-labelledby="au-why-h">
    <div class="wrap au-why-grid">
      <div class="au-why-text">
        <p class="kicker">De ce cont</p>
        <h2 id="au-why-h">Contul corect pentru rolul corect.</h2>
        <p>Clientul are nevoie să-și găsească rapid biletele și beneficiile. Locația are nevoie de vânzări, check-in, rapoarte și administrare.</p>
      </div>
      <ul class="au-cards">
        <li><small>Client</small><h3>Biletele mele</h3><p>PDF-uri, QR-uri, calendar, statusuri și detalii de acces.</p></li>
        <li class="is-mint"><small>Client</small><h3>Puncte bonus</h3><p>Sold, istoric, folosire în comenzi viitoare și beneficii.</p></li>
        <li class="is-deep"><small>Locație</small><h3>Dashboard</h3><p>Activități, bilete, comenzi, clienți, staff și recenzii.</p></li>
        <li><small>Locație</small><h3>QR check-in</h3><p>Validare bilete la intrare și statusuri clare pentru fiecare cod.</p></li>
      </ul>
    </div>
  </section>

  <section class="au-faq" aria-labelledby="au-faq-h">
    <div class="wrap au-faq-in">
      <div class="au-faq-head">
        <p class="kicker">FAQ</p>
        <h2 id="au-faq-h">Întrebări despre conturi</h2>
      </div>
      <?php foreach ($faqs as $i => $faq): ?>
      <details class="qa"<?= $i === 0 ? ' open' : '' ?>>
        <summary><?= v2_e($faq[0]) ?><span class="pm" aria-hidden="true"><?= v2_ic('plus') ?></span></summary>
        <p><?= v2_e($faq[1]) ?></p>
      </details>
      <?php endforeach; ?>
    </div>
  </section>
</main>

<?php include __DIR__ . '/includes/v2/footer.php'; ?>
