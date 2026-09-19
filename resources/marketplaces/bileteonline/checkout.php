<?php
/**
 * bilete.online — /finalizare (v2 "Arcada")
 *
 * Checkout. PHP scaffolds the form and the summary; assets/js/pages/checkout-page.js fills and submits them. The
 * element IDs and the CheckoutPage.* handlers are that script's contract and must stay: checkout-loading,
 * checkout-form, empty-cart, summary-section, timer-bar, countdown, guest-login-btn, buyer-last-name,
 * buyer-first-name, buyer-email, buyer-email-confirm, email-mismatch-error, buyer-phone, create-account-row,
 * createAccountCheckbox, beneficiaries-count, allTicketsToEmail, differentBeneficiaries, beneficiariesList,
 * insurance-section, insurance-label, insurance-description, insurance-option, insuranceCheckbox, insurance-title,
 * insurance-partial-note, insurance-terms-link, insurance-price, cultural-card-option, cardForm, culturalCardForm,
 * cultural-card-surcharge-text, termsCheckbox, newsletterCheckbox, event-info, items-summary, taxes-container,
 * summary-items, summary-subtotal, platform-commission-row/label/amount, discount-row/label/amount,
 * insurance-row/-label/-amount, cultural-card-row, cultural-card-surcharge-label, cultural-card-amount,
 * processing-fee-row/label/amount, summary-total, savings-text, savings-amount, points-earned, payBtn, pay-btn-text,
 * points-row/label/amount, points-box, points-use-row, use-points, use-points-title/sub, points-note, points-login,
 * points-reward, points-rule (loyalty points: shown only when the marketplace runs automatic rewards),
 * login-modal, checkout-login-form, login-email, login-password, login-submit-btn, login-btn-text.
 * The script shows and hides them with a `hidden` class (base.css). Styles: cart.css (head, timer, layout,
 * skeletons, summary card, empty state, phone bar) + checkout.css.
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/api.php';
require_once __DIR__ . '/includes/nav-helpers.php';
require_once __DIR__ . '/includes/v2/helpers.php';
require_once __DIR__ . '/includes/v2/nav.php';

$pageTitleRaw    = 'Checkout securizat — ' . SITE_NAME;
$pageDescription = 'Finalizează comanda pentru biletele selectate. Plătești securizat cu cardul, Apple Pay, Google Pay sau alte metode disponibile.';
$canonicalUrl    = SITE_URL . '/finalizare';
$noindex         = true;
$currentPage     = 'checkout';

$v2Styles = ['cart.css', 'checkout.css'];
$v2Scripts = ['checkout.js'];
$v2LegacyScripts = [
    'assets/js/config.js', 'assets/js/utils.js', 'assets/js/api.js', 'assets/js/auth.js',
    'assets/js/cart.js', 'assets/js/components/notifications.js', 'assets/js/pages/checkout-page.js',
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

$bookIcon = '<svg class="ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 6.25v13M12 6.25C10.83 5.48 9.25 5 7.5 5S4.17 5.48 3 6.25v13C4.17 18.48 5.75 18 7.5 18s3.33.48 4.5 1.25m0-13C13.17 5.48 14.75 5 16.5 5s3.33.48 4.5 1.25v13C19.83 18.48 18.25 18 16.5 18s-3.33.48-4.5 1.25"/></svg>';

include __DIR__ . '/includes/v2/head.php';
include __DIR__ . '/includes/v2/header.php';
?>
<main id="main" class="page-main" tabindex="-1">

  <section class="co-head" aria-labelledby="ck-h">
    <div class="wrap">
      <div class="co-head-row">
        <div>
          <p class="kicker">Pasul 2 · Checkout</p>
          <h1 class="co-h" id="ck-h">Finalizează comanda</h1>
          <p class="co-lead">Completează datele beneficiarilor, alege metoda de plată și primește biletele cu QR pe email instant.</p>
        </div>
        <div class="co-head-side">
          <ol class="steps" aria-label="Pașii comenzii">
            <li class="is-done"><b><?= v2_ic('check') ?></b>Coș<span class="sr"> (finalizat)</span></li>
            <li aria-current="step"><b>2</b>Checkout</li>
            <li><b>3</b>Confirmare</li>
          </ol>
          <a class="ck-back" href="/cos"><?= v2_ic('arrow-left') ?>Înapoi la coș</a>
        </div>
      </div>
    </div>
  </section>

  <div id="timer-bar" class="co-timer hidden" role="timer" aria-live="off">
    <div class="wrap co-timer-in">
      <?= v2_ic('clock') ?>
      <span>Finalizează comanda în</span>
      <span id="countdown" class="countdown">14:59</span>
      <span>minute</span>
    </div>
  </div>

  <div class="wrap co-lay">
    <div class="co-main">
      <div id="checkout-loading" class="co-skel ck-skel">
        <span class="sr" role="status">Se pregătește comanda…</span>
        <i aria-hidden="true"></i><i aria-hidden="true"></i><i aria-hidden="true"></i>
      </div>

      <div id="checkout-form" class="ck-form hidden">

        <section class="ck-sec" aria-labelledby="ck-s-contact">
          <header class="ck-sec-head">
            <span class="ck-num" aria-hidden="true"></span>
            <div>
              <h2 id="ck-s-contact">Cont și date de contact</h2>
              <p>Poți continua rapid ca vizitator. Datele sunt folosite doar pentru această comandă.</p>
            </div>
            <button type="button" id="guest-login-btn" class="ck-login hidden" onclick="CheckoutPage.showLoginModal()" aria-haspopup="dialog" aria-controls="login-modal"><?= v2_ic('user-circle') ?>Am deja cont · Login</button>
          </header>
          <div class="ck-sec-body ck-grid">
            <div class="ck-field">
              <label for="buyer-last-name">Nume *</label>
              <input type="text" id="buyer-last-name" placeholder="ex. Popescu" autocomplete="family-name" required>
            </div>
            <div class="ck-field">
              <label for="buyer-first-name">Prenume *</label>
              <input type="text" id="buyer-first-name" placeholder="ex. Ion" autocomplete="given-name" required>
            </div>
            <div class="ck-field">
              <label for="buyer-email">Email *</label>
              <input type="email" id="buyer-email" placeholder="email@exemplu.ro" autocomplete="email" inputmode="email" required>
            </div>
            <div class="ck-field">
              <label for="buyer-email-confirm">Confirmă email *</label>
              <input type="email" id="buyer-email-confirm" placeholder="email@exemplu.ro" autocomplete="new-password" inputmode="email" onpaste="return false;" ondrop="return false;" aria-describedby="email-mismatch-error" required>
              <p id="email-mismatch-error" class="ck-err hidden" role="alert">Adresele de email nu coincid</p>
            </div>
            <div class="ck-field ck-wide">
              <label for="buyer-phone">Telefon *</label>
              <input type="tel" id="buyer-phone" placeholder="07XX XXX XXX" autocomplete="tel" inputmode="tel" required>
            </div>
            <div id="create-account-row" class="ck-opt ck-wide hidden">
              <input type="checkbox" id="createAccountCheckbox" class="ck-cb">
              <div>
                <label for="createAccountCheckbox">Creează-mi cont automat după comandă</label>
                <p>Primești parola pe email și poți accesa biletele oricând din contul tău.</p>
              </div>
            </div>
          </div>
        </section>

        <section class="ck-sec" aria-labelledby="ck-s-bene">
          <header class="ck-sec-head">
            <span class="ck-num" aria-hidden="true"></span>
            <div>
              <h2 id="ck-s-bene">Beneficiari bilete</h2>
              <p>Poți pune același nume pe toate biletele sau nume diferite pentru fiecare beneficiar.</p>
            </div>
            <span id="beneficiaries-count" class="ck-count">0 bilete</span>
          </header>
          <div class="ck-sec-body">
            <div class="ck-bene">
              <div id="allTicketsToEmail" class="ck-bene-info"><?= v2_ic('check-circle') ?><p>Toate biletele vor fi trimise pe emailul tău</p></div>
              <label class="ck-switch">
                <input type="checkbox" id="differentBeneficiaries" class="cc-switch" onchange="CheckoutPage.toggleBeneficiaries()" aria-controls="beneficiariesList">
                <span>Folosește date diferite pentru fiecare bilet</span>
              </label>
            </div>
            <div id="beneficiariesList" class="ck-bene-list hidden"></div>
          </div>
        </section>

        <section id="insurance-section" class="ck-sec ck-ins hidden" aria-labelledby="insurance-label">
          <header class="ck-sec-head">
            <span class="ck-num" aria-hidden="true"></span>
            <div>
              <h2 id="insurance-label">Protecție bilet</h2>
              <p id="insurance-description">Adaugă protecție pentru flexibilitate: poți cere retur conform condițiilor pachetului.</p>
            </div>
          </header>
          <div class="ck-sec-body">
            <label id="insurance-option" class="ck-ins-opt" for="insuranceCheckbox">
              <input type="checkbox" id="insuranceCheckbox" class="ck-cb">
              <span class="ck-ins-text">
                <b id="insurance-title">Protecție returnare bilete</b>
                <span>Poți solicita returnarea biletelor în cazul în care evenimentul este amânat sau anulat.</span>
                <em id="insurance-partial-note" class="hidden"></em>
                <a href="#" id="insurance-terms-link" class="hidden" target="_blank" rel="noopener">Vezi termeni și condiții</a>
              </span>
              <strong id="insurance-price">+5,00 lei</strong>
            </label>
          </div>
        </section>

        <section class="ck-sec" aria-labelledby="ck-s-pay">
          <header class="ck-sec-head">
            <span class="ck-num" aria-hidden="true"></span>
            <div>
              <h2 id="ck-s-pay">Metodă de plată</h2>
              <p>Plățile cu cardul sunt procesate securizat prin Stripe. 3D Secure, PCI DSS Level 1.</p>
            </div>
          </header>
          <div class="ck-sec-body ck-pay" role="radiogroup" aria-labelledby="ck-s-pay">
            <label class="payment-option selected">
              <input type="radio" name="payment" value="card" class="sr" checked>
              <span class="payment-radio" aria-hidden="true"></span>
              <span class="ck-pay-logo is-stripe" aria-hidden="true">STRIPE</span>
              <span class="ck-pay-text"><b>Card bancar</b><small>Visa, Mastercard, Maestro, Apple Pay, Google Pay</small></span>
              <span class="ck-pay-brands" aria-hidden="true"><i>Visa</i><i>Mastercard</i></span>
            </label>

            <label id="cultural-card-option" class="payment-option hidden">
              <input type="radio" name="payment" value="card_cultural" class="sr">
              <span class="payment-radio" aria-hidden="true"></span>
              <span class="ck-pay-logo is-cultural" aria-hidden="true"><?= $bookIcon ?></span>
              <span class="ck-pay-text"><b>Card Cultural</b><small>Edenred, Sodexo, Up România</small></span>
            </label>

            <div class="ck-wallets">
              <span>Acceptăm și:</span>
              <span class="ck-wallet">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/><path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/><path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" fill="#FBBC05"/><path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/></svg>
                Google Pay
              </span>
              <span class="ck-wallet is-dark">
                <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M17.05 20.28c-.98.95-2.05.8-3.08.35-1.09-.46-2.09-.48-3.24 0-1.44.62-2.2.44-3.06-.35C2.79 15.25 3.51 7.59 9.05 7.31c1.35.07 2.29.74 3.08.8 1.18-.24 2.31-.93 3.57-.84 1.51.12 2.65.72 3.4 1.8-3.12 1.87-2.38 5.98.48 7.13-.57 1.5-1.31 2.99-2.54 4.09l.01-.01zM12.03 7.25c-.15-2.23 1.66-4.07 3.74-4.25.29 2.58-2.34 4.5-3.74 4.25z"/></svg>
                Pay
              </span>
            </div>

            <div id="cardForm" class="ck-note">
              <p>Vei fi redirecționat către procesatorul de plăți pentru a introduce datele cardului în siguranță.</p>
              <p class="ck-note-sec"><?= v2_ic('lock-simple') ?>Plățile sunt procesate securizat · SSL 256-bit · 3D Secure</p>
            </div>

            <div id="culturalCardForm" class="ck-note is-warn hidden">
              <?= $bookIcon ?>
              <div>
                <p class="ck-note-h">Comision adițional Card Cultural</p>
                <p id="cultural-card-surcharge-text">Tranzacțiile cu card cultural au un comision de procesare suplimentar de <strong>4%</strong> din valoarea totală, datorat costurilor mai mari de procesare pentru acest tip de card.</p>
                <p class="ck-note-small">Vei fi redirecționat către procesatorul de plăți pentru a introduce datele cardului cultural în siguranță.</p>
              </div>
            </div>
          </div>
        </section>

        <section class="ck-sec ck-terms" aria-labelledby="ck-s-terms">
          <header class="ck-sec-head">
            <span class="ck-num" aria-hidden="true"></span>
            <div><h2 id="ck-s-terms">Acorduri</h2></div>
          </header>
          <div class="ck-sec-body ck-checks">
            <label class="ck-check">
              <input type="checkbox" id="termsCheckbox" class="ck-cb" required>
              <span>Am citit și sunt de acord cu <a href="/termeni" target="_blank" rel="noopener">Termenii și condițiile</a>, <a href="/confidentialitate" target="_blank" rel="noopener">Politica de confidențialitate</a> și <a href="/retur" target="_blank" rel="noopener">Politica de returnare</a>.</span>
            </label>
            <label class="ck-check">
              <input type="checkbox" id="newsletterCheckbox" class="ck-cb">
              <span>Vreau să primesc recomandări, oferte și activități noi prin newsletter-ul <?= v2_e(SITE_NAME) ?>.</span>
            </label>
          </div>
        </section>
      </div>

      <div id="empty-cart" class="co-empty hidden">
        <div class="co-empty-art" aria-hidden="true"><?= v2_fallback('cos', 1) ?><?= v2_ic('ticket') ?></div>
        <h2 tabindex="-1">Coșul tău e gol</h2>
        <p>Nu ai bilete în coș. Descoperă activitățile și evenimentele disponibile.</p>
        <a class="btn btn-primary" href="/categorii">Explorează activități<?= v2_ic('arrow-right') ?></a>
        <p class="co-empty-small">Ai plătit deja? <a href="/cont/bilete">Vezi biletele tale</a> sau <a href="/recuperare-comanda">recuperează comanda</a>.</p>
      </div>
    </div>

    <aside class="co-side" aria-label="Sumar checkout">
      <div class="cs-skel" aria-hidden="true"></div>
      <div id="summary-section" class="hidden">
        <section class="cs" aria-labelledby="ck-sum-h">
          <div class="cs-top">
            <p class="cs-kicker">Sumar checkout</p>
            <h2 class="cs-h" id="ck-sum-h">De plată</h2>
          </div>
          <div class="cs-body">
            <div id="event-info" class="ck-event"></div>
            <div id="items-summary" class="cs-lines"></div>
            <div id="taxes-container" class="cs-lines"></div>
            <div class="cs-line cs-sub"><span>Subtotal (<span id="summary-items">0</span> <span data-items-word>bilete</span>)</span><strong id="summary-subtotal">0,00 lei</strong></div>
            <div id="platform-commission-row" class="cs-line hidden"><span id="platform-commission-label">Comision ticketing</span><strong id="platform-commission-amount">0,00 lei</strong></div>
            <div id="discount-row" class="cs-line cs-disc hidden"><span id="discount-label">Reducere</span><strong id="discount-amount">-0,00 lei</strong></div>
            <div id="points-row" class="cs-line cs-disc hidden"><span id="points-row-label">Plătit cu puncte</span><strong id="points-row-amount">-0,00 lei</strong></div>
            <div id="insurance-row" class="cs-line cs-ins hidden"><span id="insurance-row-label">Protecție bilet</span><strong id="insurance-row-amount">+0,00 lei</strong></div>
            <div id="cultural-card-row" class="cs-line hidden"><span id="cultural-card-surcharge-label">Comision card cultural (4%)</span><strong id="cultural-card-amount">+0,00 lei</strong></div>
            <div id="processing-fee-row" class="cs-line hidden"><span id="processing-fee-label">Comision tranzacționare plată</span><strong id="processing-fee-amount">0,00 lei</strong></div>
            <div class="cs-line cs-total"><span>Total de plată</span><strong id="summary-total">0,00 lei</strong></div>
            <p id="savings-text" class="cs-save ck-save hidden"><?= v2_ic('check-circle') ?><span id="savings-amount">Economisești 0 lei!</span></p>
            <!-- loyalty points: pay with them (logged-in customer), shown only when the programme runs -->
            <div id="points-box" class="cs-usepts hidden">
              <div class="cs-usepts-row" id="points-use-row" hidden>
                <input type="checkbox" id="use-points" class="ck-cb" aria-describedby="use-points-sub">
                <label for="use-points"><b id="use-points-title">Folosește punctele</b><small id="use-points-sub"></small></label>
              </div>
              <p id="points-note" class="cs-usepts-note" hidden></p>
              <button type="button" id="points-login" class="link-btn cs-usepts-login" hidden>Intră în cont</button>
            </div>
            <div class="cs-reward hidden" id="points-reward">
              <span class="cs-reward-ic" aria-hidden="true"><?= v2_ic('gift') ?></span>
              <div><b>Vei câștiga</b><p id="points-rule">puncte la fiecare comandă</p></div>
              <p class="cs-pts"><span id="points-earned">0 puncte</span></p>
            </div>
          </div>
          <div class="cs-foot">
            <button type="button" id="payBtn" class="btn btn-primary cs-go" onclick="CheckoutPage.submit()" disabled><?= v2_ic('lock-simple') ?><span id="pay-btn-text">Plasează comanda · 0,00 lei</span></button>
            <p class="ck-hint" id="pay-hint">Bifează acordul cu termenii și condițiile ca să poți plăti.</p>
            <p class="ck-foot-note">Prin plasarea comenzii, confirmi că ai citit și ești de acord cu termenii și condițiile.</p>
          </div>
          <ul class="ck-badges">
            <li><?= v2_ic('lock-simple') ?>SSL 256-bit</li>
            <li><?= v2_ic('check-circle') ?>PCI DSS</li>
            <li><?= v2_ic('credit-card') ?>3D Secure</li>
          </ul>
        </section>
      </div>
    </aside>
  </div>

  <div class="co-mbar" id="co-mbar" aria-hidden="true" inert>
    <div><small>Total de plată</small><b data-total>0,00 lei</b></div>
    <button class="btn btn-primary" type="button" data-pay><?= v2_ic('lock-simple') ?><span>Plătește</span></button>
  </div>
</main>

<div id="login-modal" class="ck-modal hidden" role="dialog" aria-modal="true" aria-labelledby="login-h">
  <div class="ck-modal-panel">
    <div class="ck-modal-top">
      <h2 id="login-h">Conectează-te</h2>
      <button type="button" class="icon-btn" onclick="CheckoutPage.hideLoginModal()" aria-label="Închide fereastra de autentificare"><?= v2_ic('x') ?></button>
    </div>
    <p>Conectează-te pentru a-ți precompleta datele și a finaliza comanda mai rapid.</p>
    <form id="checkout-login-form" onsubmit="return CheckoutPage.handleLogin(event)">
      <div class="ck-field">
        <label for="login-email">Email</label>
        <input type="email" id="login-email" placeholder="email@exemplu.ro" autocomplete="email" required>
      </div>
      <div class="ck-field">
        <label for="login-password">Parola</label>
        <input type="password" id="login-password" placeholder="••••••••" autocomplete="current-password" required>
      </div>
      <button type="submit" id="login-submit-btn" class="btn btn-primary ck-modal-go"><span id="login-btn-text">Conectează-te</span></button>
    </form>
    <div class="ck-modal-links">
      <a href="/parola-uitata" target="_blank" rel="noopener">Ai uitat parola?</a>
      <a href="/inregistrare" target="_blank" rel="noopener">Creează cont</a>
    </div>
  </div>
</div>

<?php include __DIR__ . '/includes/v2/footer.php'; ?>
