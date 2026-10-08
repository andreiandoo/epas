<?php
/**
 * viaqui.com — /checkout (v2 "Arcada")
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
 * points-reward, points-rule (loyalty points: shown only when the marketplace runs automatic rewards, with how they
 * are earned in a tooltip on the info icon),
 * login-modal, checkout-login-form, login-email, login-password, login-submit-btn, login-btn-text.
 * The script shows and hides them with a `hidden` class (base.css). Styles: cart.css (head, timer, layout,
 * skeletons, summary card, empty state, phone bar) + checkout.css.
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/api.php';
require_once __DIR__ . '/includes/nav-helpers.php';
require_once __DIR__ . '/includes/v2/helpers.php';
require_once __DIR__ . '/includes/v2/nav.php';

$pageTitleRaw    = 'Secure checkout — ' . SITE_NAME;
$pageDescription = 'Complete the order for the tickets in your basket. Pay securely by card, Apple Pay or Google Pay.';
$canonicalUrl    = SITE_URL . '/checkout';
$noindex         = true;
$currentPage     = 'checkout';

// Who takes the card payment (Stripe, …) — the section and its logo say the real one.
$ckPay = v2_payment_provider();
$ckPayKey = $ckPay['provider'] ?? '';
$ckPayLabel = $ckPay['label'] ?? '';
$ckPayLogo = v2_payment_logo($ckPay) ?? '';

$v2Styles = ['cart.css', 'checkout.css'];
$v2FooterCompact = true; // the checkout always has products in the cart: short footer
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
    'currency' => SITE_CURRENCY,
    'supportEmail' => defined('SUPPORT_EMAIL') ? SUPPORT_EMAIL : '',
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) . ';</script>';

$bookIcon = '<svg class="ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 6.25v13M12 6.25C10.83 5.48 9.25 5 7.5 5S4.17 5.48 3 6.25v13C4.17 18.48 5.75 18 7.5 18s3.33.48 4.5 1.25m0-13C13.17 5.48 14.75 5 16.5 5s3.33.48 4.5 1.25v13C19.83 18.48 18.25 18 16.5 18s-3.33.48-4.5 1.25"/></svg>';

// Checkout inside the booking widget on an operator's site (embed/finalizare.php sets $ckEmbed): no site header or
// footer, no pixels, guest only, links open in a new tab; checkout-page.js reads window.BO_EMBED.
$ckEmbed = $ckEmbed ?? null;
if ($ckEmbed) {
    $trackingHeadScripts = '';
    $v2Styles[] = 'embed-ck.css';
    $v2HeadExtra .= '<base target="_blank"><script>window.BO_EMBED = ' . json_encode([
        'return' => $ckEmbed['return'],
        'cancel' => $ckEmbed['cancel'],
        'confirm' => $ckEmbed['confirm'],
        'page' => $ckEmbed['page'],
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) . ';</script>';
} else {
    // The operator whose page the visitor came from (cookie set on experience / location pages): their ad pixels stay
    // loaded through the purchase (ad tracking service). Safe here: this page is never page-cached.
    $trackingFromCookie = true;
}

include __DIR__ . '/includes/v2/head.php';
if ($ckEmbed) {
    require_once __DIR__ . '/includes/v2/legal.php';
    echo '<body class="emb-ck">';
    readfile(__DIR__ . '/includes/v2/sprite.svg');
    ?>
<header class="eck-head">
  <a class="eck-back" href="#" data-emb-back target="_self"><?= v2_ic('arrow-left') ?>Back to tickets</a>
  <p class="eck-by"><?= v2_ic('lock-simple') ?><span>Secure payment through <b><?= v2_e(SITE_NAME) ?></b> · sold by <?= v2_e(V2_LEGAL_COMPANY['name']) ?>, company no. <?= v2_e(V2_LEGAL_COMPANY['cui']) ?></span></p>
</header>
<?php if (!empty($ckEmbed['cancelled'])): ?>
<p class="eck-note" role="status"><?= v2_ic('info') ?><span>The payment was cancelled and nothing was charged. You can try again below.</span></p>
<?php endif; ?>
<?php
} else {
    include __DIR__ . '/includes/v2/header.php';
}
?>
<main id="main" class="page-main" tabindex="-1">

  <section class="co-head" aria-labelledby="ck-h">
    <div class="wrap">
      <div class="co-head-row">
        <div>
          <p class="kicker">Step 2 · Checkout</p>
          <h1 class="co-h" id="ck-h">Complete your order</h1>
          <p class="co-lead">Fill in your details, pay by card and get your QR tickets by email straight away.</p>
        </div>
        <div class="co-head-side">
          <ol class="steps" aria-label="Order steps">
            <li class="is-done"><b><?= v2_ic('check') ?></b>Basket<span class="sr"> (done)</span></li>
            <li aria-current="step"><b>2</b>Checkout</li>
            <li><b>3</b>Confirmation</li>
          </ol>
          <a class="ck-back" href="/cart"><?= v2_ic('arrow-left') ?>Back to basket</a>
        </div>
      </div>
    </div>
  </section>

  <div id="timer-bar" class="co-timer hidden" role="timer" aria-live="off">
    <div class="wrap co-timer-in">
      <?= v2_ic('clock') ?>
      <span>Complete your order within</span>
      <span id="countdown" class="countdown">14:59</span>
      <span>minutes</span>
    </div>
  </div>

  <div class="wrap co-lay">
    <div class="co-main">
      <div id="checkout-loading" class="co-skel ck-skel">
        <span class="sr" role="status">Preparing your order…</span>
        <i aria-hidden="true"></i><i aria-hidden="true"></i><i aria-hidden="true"></i>
      </div>

      <div id="checkout-form" class="ck-form hidden">

        <section class="ck-sec" aria-labelledby="ck-s-contact">
          <header class="ck-sec-head">
            <span class="ck-num" aria-hidden="true"></span>
            <div>
              <h2 id="ck-s-contact">Account and contact details</h2>
              <p>You can check out as a guest. Your details are used for this order only.</p>
            </div>
            <button type="button" id="guest-login-btn" class="ck-login hidden" onclick="CheckoutPage.showLoginModal()" aria-haspopup="dialog" aria-controls="login-modal"><?= v2_ic('user-circle') ?>I have an account · Log in</button>
          </header>
          <div class="ck-sec-body ck-grid">
            <div class="ck-field">
              <label for="buyer-last-name">Last name *</label>
              <input type="text" id="buyer-last-name" placeholder="e.g. Smith" autocomplete="family-name" required>
            </div>
            <div class="ck-field">
              <label for="buyer-first-name">First name *</label>
              <input type="text" id="buyer-first-name" placeholder="e.g. Anna" autocomplete="given-name" required>
            </div>
            <div class="ck-field">
              <label for="buyer-email">Email *</label>
              <input type="email" id="buyer-email" placeholder="you@example.com" autocomplete="email" inputmode="email" required>
            </div>
            <div class="ck-field">
              <label for="buyer-email-confirm">Confirm email *</label>
              <input type="email" id="buyer-email-confirm" placeholder="you@example.com" autocomplete="new-password" inputmode="email" onpaste="return false;" ondrop="return false;" aria-describedby="email-mismatch-error" required>
              <p id="email-mismatch-error" class="ck-err hidden" role="alert">The email addresses do not match</p>
            </div>
            <div class="ck-field ck-wide">
              <label for="buyer-phone">Phone *</label>
              <input type="tel" id="buyer-phone" placeholder="+44 20 7946 0000" autocomplete="tel" inputmode="tel" required>
            </div>
            <div id="create-account-row" class="ck-opt ck-wide hidden">
              <input type="checkbox" id="createAccountCheckbox" class="ck-cb">
              <div>
                <label for="createAccountCheckbox">Create an account for me after this order</label>
                <p>We email you a password and your tickets are always there in your account.</p>
              </div>
            </div>
          </div>
        </section>

        <section class="ck-sec" aria-labelledby="ck-s-bene">
          <header class="ck-sec-head">
            <span class="ck-num" aria-hidden="true"></span>
            <div>
              <h2 id="ck-s-bene">Ticket holders</h2>
              <p>Put the same name on every ticket, or a different name for each person.</p>
            </div>
            <span id="beneficiaries-count" class="ck-count">0 tickets</span>
          </header>
          <div class="ck-sec-body">
            <div class="ck-bene">
              <div id="allTicketsToEmail" class="ck-bene-info"><?= v2_ic('check-circle') ?><p>All tickets will be sent to your email address</p></div>
              <label class="ck-switch">
                <input type="checkbox" id="differentBeneficiaries" class="cc-switch" onchange="CheckoutPage.toggleBeneficiaries()" aria-controls="beneficiariesList">
                <span>Use different details for each ticket</span>
              </label>
            </div>
            <div id="beneficiariesList" class="ck-bene-list hidden"></div>
          </div>
        </section>

        <section id="insurance-section" class="ck-sec ck-ins hidden" aria-labelledby="insurance-label">
          <header class="ck-sec-head">
            <span class="ck-num" aria-hidden="true"></span>
            <div>
              <h2 id="insurance-label">Ticket protection</h2>
              <p id="insurance-description">Add protection for more flexibility: you can ask for a refund under the terms of the cover.</p>
            </div>
          </header>
          <div class="ck-sec-body">
            <label id="insurance-option" class="ck-ins-opt" for="insuranceCheckbox">
              <input type="checkbox" id="insuranceCheckbox" class="ck-cb">
              <span class="ck-ins-text">
                <b id="insurance-title">Ticket refund protection</b>
                <span>You can ask for a refund of your tickets if the event is postponed or cancelled.</span>
                <em id="insurance-partial-note" class="hidden"></em>
                <a href="#" id="insurance-terms-link" class="hidden" target="_blank" rel="noopener">See the terms and conditions</a>
              </span>
              <strong id="insurance-price"></strong>
            </label>
          </div>
        </section>

        <section class="ck-sec" aria-labelledby="ck-s-pay">
          <header class="ck-sec-head">
            <span class="ck-num" aria-hidden="true"></span>
            <div>
              <h2 id="ck-s-pay">Payment method</h2>
              <p>Card payments are processed securely<?= $ckPayLabel !== '' ? ' by ' . v2_e($ckPayLabel) : '' ?>. 3D Secure, PCI DSS Level 1.</p>
            </div>
          </header>
          <div class="ck-sec-body ck-pay" role="radiogroup" aria-labelledby="ck-s-pay">
            <label class="payment-option selected">
              <input type="radio" name="payment" value="card" class="sr" checked>
              <span class="payment-radio" aria-hidden="true"></span>
              <?php if ($ckPayLogo !== ''): ?>
              <img class="ck-pay-logo is-img" src="<?= v2_e($ckPayLogo) ?>" alt="<?= v2_e($ckPayLabel) ?>" width="418" height="75" loading="lazy" decoding="async">
              <?php else: ?>
              <span class="ck-pay-logo is-<?= v2_e($ckPayKey ?: 'card') ?>" aria-hidden="true"><?= v2_e($ckPayLabel !== '' ? mb_strtoupper(mb_substr($ckPayLabel, 0, 7)) : 'CARD') ?></span>
              <?php endif; ?>
              <span class="ck-pay-text"><b>Card</b><small>Visa, Mastercard, Maestro, Apple Pay, Google Pay</small></span>
              <span class="ck-pay-brands" aria-hidden="true"><i>Visa</i><i>Mastercard</i></span>
            </label>

            <label id="cultural-card-option" class="payment-option hidden">
              <input type="radio" name="payment" value="card_cultural" class="sr">
              <span class="payment-radio" aria-hidden="true"></span>
              <span class="ck-pay-logo is-cultural" aria-hidden="true"><?= $bookIcon ?></span>
              <span class="ck-pay-text"><b>Culture card</b><small>Prepaid culture voucher cards</small></span>
            </label>

            <div class="ck-wallets">
              <span>We also accept:</span>
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
              <p>You will be taken to a secure payment page<?= $ckPayLabel !== '' ? ' provided by ' . v2_e($ckPayLabel) : '' ?> to enter your card details.</p>
              <p class="ck-note-sec"><?= v2_ic('lock-simple') ?>Secure card payment · SSL 256-bit · 3D Secure</p>
            </div>

            <div id="culturalCardForm" class="ck-note is-warn hidden">
              <?= $bookIcon ?>
              <div>
                <p class="ck-note-h">Extra fee for culture cards</p>
                <p id="cultural-card-surcharge-text">Payments by culture card carry an extra processing fee of <strong>4%</strong> of the total, because this type of card costs more to process.</p>
                <p class="ck-note-small">You will be taken to a secure payment page to enter your culture card details.</p>
              </div>
            </div>
          </div>
        </section>

        <section class="ck-sec ck-terms" aria-labelledby="ck-s-terms">
          <header class="ck-sec-head">
            <span class="ck-num" aria-hidden="true"></span>
            <div><h2 id="ck-s-terms">Agreements</h2></div>
          </header>
          <div class="ck-sec-body ck-checks">
            <label class="ck-check">
              <input type="checkbox" id="termsCheckbox" class="ck-cb" required>
              <span>I have read and agree to the <a href="/terms" target="_blank" rel="noopener">Terms and conditions</a>, the <a href="/privacy" target="_blank" rel="noopener">Privacy policy</a> and the <a href="/terms#anulare-rambursare" target="_blank" rel="noopener">Refund policy</a>.</span>
            </label>
            <label class="ck-check">
              <input type="checkbox" id="newsletterCheckbox" class="ck-cb">
              <span>I would like to receive recommendations, offers and new experiences in the <?= v2_e(SITE_NAME) ?> newsletter.</span>
            </label>
          </div>
        </section>
      </div>

      <div id="empty-cart" class="co-empty hidden">
        <div class="co-empty-art" aria-hidden="true"><?= v2_fallback('cos', 1) ?><?= v2_ic('ticket') ?></div>
        <h2 tabindex="-1">Your basket is empty</h2>
        <p>There are no tickets in your basket. See the experiences and attractions you can book.</p>
        <?php if ($ckEmbed): ?>
        <a class="btn btn-primary" href="#" data-emb-back target="_self"><?= v2_ic('arrow-left') ?>Back to tickets</a>
        <?php else: ?>
        <a class="btn btn-primary" href="/categories">Explore experiences<?= v2_ic('arrow-right') ?></a>
        <p class="co-empty-small">Already paid? <a href="/account/tickets">See your tickets</a> or <a href="/find-order">find your order</a>.</p>
        <?php endif; ?>
      </div>
    </div>

    <aside class="co-side" aria-label="Checkout summary">
      <div class="cs-skel" aria-hidden="true"></div>
      <div id="summary-section" class="hidden">
        <section class="cs" aria-labelledby="ck-sum-h">
          <div class="cs-top">
            <p class="cs-kicker">Checkout summary</p>
            <h2 class="cs-h" id="ck-sum-h">To pay</h2>
          </div>
          <div class="cs-body">
            <div id="event-info" class="ck-event"></div>
            <div id="items-summary" class="cs-lines"></div>
            <div id="taxes-container" class="cs-lines"></div>
            <div class="cs-line cs-sub"><span>Subtotal (<span id="summary-items">0</span> <span data-items-word>tickets</span>)</span><strong id="summary-subtotal"></strong></div>
            <div id="platform-commission-row" class="cs-line hidden"><span id="platform-commission-label">Booking fee</span><strong id="platform-commission-amount"></strong></div>
            <div id="discount-row" class="cs-line cs-disc hidden"><span id="discount-label">Discount</span><strong id="discount-amount"></strong></div>
            <div id="points-row" class="cs-line cs-disc hidden"><span id="points-row-label">Paid with points</span><strong id="points-row-amount"></strong></div>
            <div id="insurance-row" class="cs-line cs-ins hidden"><span id="insurance-row-label">Ticket protection</span><strong id="insurance-row-amount"></strong></div>
            <div id="cultural-card-row" class="cs-line hidden"><span id="cultural-card-surcharge-label">Culture card fee (4%)</span><strong id="cultural-card-amount"></strong></div>
            <div id="processing-fee-row" class="cs-line hidden"><span id="processing-fee-label">Payment processing fee</span><strong id="processing-fee-amount"></strong></div>
            <div class="cs-line cs-total"><span>Total to pay</span><strong id="summary-total"></strong></div>
            <p id="savings-text" class="cs-save ck-save hidden"><?= v2_ic('check-circle') ?><span id="savings-amount"></span></p>
            <!-- loyalty points: pay with them (logged-in customer), shown only when the programme runs -->
            <div id="points-box" class="cs-usepts hidden">
              <div class="cs-usepts-row" id="points-use-row" hidden>
                <input type="checkbox" id="use-points" class="ck-cb" aria-describedby="use-points-sub">
                <label for="use-points"><b id="use-points-title">Use your points</b><small id="use-points-sub"></small></label>
              </div>
              <p id="points-note" class="cs-usepts-note" hidden></p>
              <button type="button" id="points-login" class="link-btn cs-usepts-login" hidden>Log in</button>
            </div>
            <div class="cs-reward hidden" id="points-reward">
              <span class="cs-reward-ic" aria-hidden="true"><?= v2_ic('gift') ?></span>
              <div class="cs-reward-t"><b>You will earn</b><span class="cs-tip"><button class="cs-tip-btn" id="points-rule-btn" type="button" aria-describedby="points-rule" aria-label="How points are earned"><?= v2_ic('info') ?></button><span class="cs-tip-box" id="points-rule" role="tooltip">points on every order</span></span></div>
              <p class="cs-pts"><span id="points-earned">0 points</span></p>
            </div>
          </div>
          <div class="cs-foot">
            <button type="button" id="payBtn" class="btn btn-primary cs-go" onclick="CheckoutPage.submit()" disabled><?= v2_ic('lock-simple') ?><span id="pay-btn-text">Place order</span></button>
            <p class="ck-hint" id="pay-hint">Tick the box to agree to the terms and conditions before you pay.</p>
            <p class="ck-foot-note">By placing the order you confirm that you have read and agree to the terms and conditions.</p>
            <p class="ck-foot-note" id="ck-currency-note">You pay in <b id="ck-currency-code">EUR</b>. On the payment page you may be offered to pay in your own currency instead, at the payment provider's exchange rate.</p>
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
    <div><small>Total to pay</small><b data-total></b></div>
    <button class="btn btn-primary" type="button" data-pay><?= v2_ic('lock-simple') ?><span>Pay</span></button>
  </div>
</main>

<div id="login-modal" class="ck-modal hidden" role="dialog" aria-modal="true" aria-labelledby="login-h">
  <div class="ck-modal-panel">
    <div class="ck-modal-top">
      <h2 id="login-h">Log in</h2>
      <button type="button" class="icon-btn" onclick="CheckoutPage.hideLoginModal()" aria-label="Close the login window"><?= v2_ic('x') ?></button>
    </div>
    <p>Log in to have your details filled in and finish the order faster.</p>
    <form id="checkout-login-form" onsubmit="return CheckoutPage.handleLogin(event)">
      <div class="ck-field">
        <label for="login-email">Email</label>
        <input type="email" id="login-email" placeholder="you@example.com" autocomplete="email" required>
      </div>
      <div class="ck-field">
        <label for="login-password">Password</label>
        <input type="password" id="login-password" placeholder="••••••••" autocomplete="current-password" required>
      </div>
      <button type="submit" id="login-submit-btn" class="btn btn-primary ck-modal-go"><span id="login-btn-text">Log in</span></button>
    </form>
    <div class="ck-modal-links">
      <a href="/forgot-password" target="_blank" rel="noopener">Forgot your password?</a>
      <a href="/register" target="_blank" rel="noopener">Create an account</a>
    </div>
  </div>
</div>

<?php if ($ckEmbed): ?>
<script>
(function () {
  // the operator's page sizes the frame to the content (embed/bo-widget.js)
  if (window.parent === window) return;
  var last = 0, main = document.getElementById('main');
  function post() {
    // the bottom of the content, not the document (that is never shorter than the frame, so it could not shrink)
    var h = Math.ceil(main.getBoundingClientRect().bottom + window.pageYOffset + 24);
    if (h && h !== last) { last = h; window.parent.postMessage({ type: 'bo-embed-height', height: h }, '*'); }
  }
  if ('ResizeObserver' in window) new ResizeObserver(post).observe(main);
  window.addEventListener('load', post);
  setInterval(post, 1500);
  document.addEventListener('click', function (e) {
    if (e.target.closest('[data-emb-back]')) { e.preventDefault(); history.back(); }
  });
  // "Checkout summary" stays in view while the operator's page scrolls (bo-widget.js sends where the frame is)
  window.addEventListener('message', function (e) {
    var d = e.data;
    if (e.source !== window.parent || !d || d.type !== 'bo-embed-viewport') return;
    var stick = Math.max(0, Math.round((Number(d.offset) || 16) - (Number(d.top) || 0)));
    document.body.style.setProperty('--eck-stick', stick + 'px');
  });
})();
</script>
<?php include __DIR__ . '/includes/v2/foot.php'; ?>
<?php else: ?>
<?php include __DIR__ . '/includes/v2/footer.php'; ?>
<?php endif; ?>
