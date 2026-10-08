<?php
/**
 * viaqui.com — /cart (v2 "Arcada")
 *
 * Basket page. PHP scaffolds the containers; assets/js/pages/cart-page.js fills them from the cart that
 * assets/js/cart.js keeps in localStorage. The element IDs are that script's contract and must stay:
 * timer-bar, countdown, totalItems, cart-loading, cartPageItems, emptyCart, promo-section, promoCode,
 * promoMessage, summary-section, taxesContainer, summaryItems, subtotal, platformCommissionRow,
 * platformCommissionLabel, platformCommissionAmount, processingFeeRow, processingFeeAmount, discountRow,
 * discountAmount, savingsRow, savingsText, savings, totalPrice, pointsReward, pointsRule, pointsEarned, checkoutBtn.
 * How the points are earned is a tooltip on the info icon (pointsRule), not a line under the heading.
 * The points box shows only when the marketplace runs a points programme (rules from /checkout/features).
 * The script shows and hides them with a `hidden` class (base.css).
 * The head (title, steps, count) is for a cart with something in it: an inline script hides it before the first paint
 * when the saved cart is empty, and cart.js keeps it in step with the empty state afterwards.
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/api.php';
require_once __DIR__ . '/includes/nav-helpers.php';
require_once __DIR__ . '/includes/v2/helpers.php';
require_once __DIR__ . '/includes/v2/nav.php';

$pageTitleRaw    = v2_t('Your basket') . ' · ' . SITE_NAME;
$pageDescription = v2_t('Check the tickets and activities you picked, add a promo code, see the points you earn and go on to checkout.');
$canonicalUrl    = SITE_URL . '/cart';
$noindex         = true;
$currentPage     = 'cart';

$v2Styles = ['cart.css'];
$v2FooterSwitch = true; // short footer once there are products in the cart (cart-page.js)
$v2Scripts = ['cart.js'];
$v2LegacyScripts = [
    'assets/js/config.js', 'assets/js/utils.js', 'assets/js/api.js', 'assets/js/auth.js',
    'assets/js/cart.js', 'assets/js/components/notifications.js', 'assets/js/pages/cart-page.js',
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

$emptyCities = array_slice($V2NAV['citiesList'] ?? [], 0, 6);
$emptyCats = array_slice($V2NAV['categories'] ?? [], 0, 6);

// The operator whose page the visitor came from (cookie set on experience / location pages): their ad pixels stay
// loaded through the purchase (ad tracking service). Safe here: this page is never page-cached.
$trackingFromCookie = true;

include __DIR__ . '/includes/v2/head.php';
include __DIR__ . '/includes/v2/header.php';
?>
<main id="main" class="page-main" tabindex="-1">

  <section class="co-head" id="co-head" aria-labelledby="co-h">
    <div class="wrap">
      <div class="co-head-row">
        <div>
          <p class="kicker"><?= v2_te('Step 1 · Basket') ?></p>
          <h1 class="co-h" id="co-h"><?= v2_te('Your basket') ?></h1>
          <p class="co-lead"><?= v2_te('Check your tickets and activities, the fees and the points you earn, then go on to secure payment.') ?></p>
        </div>
        <div class="co-head-side">
          <ol class="steps" aria-label="<?= v2_te('Order steps') ?>">
            <li aria-current="step"><b>1</b><?= v2_te('Basket') ?></li>
            <li><b>2</b><?= v2_te('Checkout') ?></li>
            <li><b>3</b><?= v2_te('Confirmation') ?></li>
          </ol>
          <p class="co-count"><?= v2_t('{count} <span>{word} in your basket</span>', ['count' => '<b id="totalItems">0</b>', 'word' => '<span data-items-word>' . v2_te('tickets') . '</span>']) ?></p>
        </div>
      </div>
    </div>
  </section>
  <script>(function () { try { var c = JSON.parse(localStorage.getItem('bileteonline_cart') || 'null'); if (!c || !Array.isArray(c.items) || !c.items.length) document.getElementById('co-head').hidden = true; } catch (e) {} })();</script>
  <div id="timer-bar" class="co-timer hidden" role="timer" aria-live="off">
    <div class="wrap co-timer-in">
      <?= v2_ic('clock') ?>
      <?= v2_t('<span>Your tickets are held<span class="co-long"> for you</span> for another</span> {time} <span>minutes</span>', ['time' => '<span id="countdown" class="countdown">14:59</span>']) ?>
    </div>
  </div>

  <div class="wrap co-lay">
    <div class="co-main">
      <div id="cart-loading" class="co-skel">
        <span class="sr" role="status"><?= v2_te('Loading your basket…') ?></span>
        <i aria-hidden="true"></i><i aria-hidden="true"></i>
      </div>

      <div id="cartPageItems" class="co-items hidden"></div>

      <div id="emptyCart" class="co-empty hidden">
        <div class="co-empty-art" aria-hidden="true"><?= v2_fallback('cos', 1) ?><?= v2_ic('shopping-cart-simple') ?></div>
        <h1 class="co-empty-h" tabindex="-1"><?= v2_te('Your basket is empty') ?></h1>
        <p><?= v2_te('There is no activity or event in your basket. See what you can book.') ?></p>
        <a class="btn btn-primary" href="/categories"><?= v2_ic('magnifying-glass') ?><?= v2_te('Explore activities') ?></a>
        <p class="co-empty-small"><?= v2_t('Already paid? <a href="{tickets}">See your tickets</a> or <a href="{order}">find your order</a>.', ['tickets' => '/account/tickets', 'order' => '/find-order']) ?></p>
        <?php if ($emptyCities || $emptyCats): ?>
        <div class="co-empty-links">
          <?php if ($emptyCities): ?>
          <div>
            <p class="flabel"><?= v2_te('Popular cities') ?></p>
            <div class="chips-links"><?php foreach ($emptyCities as $c): ?><a href="<?= v2_e($c['href']) ?>"><?= v2_e($c['name']) ?></a><?php endforeach; ?></div>
          </div>
          <?php endif; ?>
          <?php if ($emptyCats): ?>
          <div>
            <p class="flabel"><?= v2_te('Categories') ?></p>
            <div class="chips-links"><?php foreach ($emptyCats as $c): ?><a href="<?= v2_e($c['href']) ?>"><?= v2_e($c['name']) ?></a><?php endforeach; ?></div>
          </div>
          <?php endif; ?>
        </div>
        <?php endif; ?>
      </div>

      <section id="promo-section" class="co-promo hidden" aria-labelledby="promo-h">
        <div class="co-promo-text">
          <h2 id="promo-h"><?= v2_te('Have a promo code?') ?></h2>
          <p><?= v2_te('The discount is checked on the spot and shows in the total straight away.') ?></p>
        </div>
        <form class="co-promo-form" onsubmit="event.preventDefault(); CartPage.applyPromo();">
          <label class="sr" for="promoCode"><?= v2_te('Promo code') ?></label>
          <input id="promoCode" type="text" placeholder="<?= v2_te('e.g. WEEKEND10') ?>" autocomplete="off" autocapitalize="characters" spellcheck="false">
          <button class="btn" type="submit"><?= v2_te('Apply') ?></button>
        </form>
        <p id="promoMessage" class="co-promo-msg hidden" role="status"></p>
      </section>
    </div>

    <aside class="co-side" aria-label="<?= v2_te('Order summary') ?>">
      <div class="cs-skel" aria-hidden="true"></div>
      <div id="summary-section" class="hidden">
        <section class="cs" aria-labelledby="cs-h">
          <div class="cs-top">
            <p class="cs-kicker"><?= v2_te('Order summary') ?></p>
            <h2 class="cs-h" id="cs-h"><?= v2_te('Basket total') ?></h2>
          </div>
          <div class="cs-body">
            <div id="taxesContainer" class="cs-lines"></div>
            <div class="cs-line cs-sub"><span><?= v2_t('Subtotal ({count} {word})', ['count' => '<span id="summaryItems">0</span>', 'word' => '<span data-items-word>' . v2_te('tickets') . '</span>']) ?></span><strong id="subtotal"><?= v2_e(v2_money(0)) ?></strong></div>
            <div id="platformCommissionRow" class="cs-line hidden"><span id="platformCommissionLabel"><?= v2_te('Booking fee') ?></span><strong id="platformCommissionAmount"><?= v2_e(v2_money(0)) ?></strong></div>
            <div id="processingFeeRow" class="cs-line hidden"><span><?= v2_te('Card processing fee') ?></span><strong id="processingFeeAmount"><?= v2_e(v2_money(0)) ?></strong></div>
            <div id="discountRow" class="cs-line cs-disc hidden"><span><?= v2_te('Discount applied') ?></span><strong id="discountAmount">-<?= v2_e(v2_money(0)) ?></strong></div>
            <div class="cs-line cs-total"><span><?= v2_te('Total to pay') ?></span><strong id="totalPrice"><?= v2_e(v2_money(0)) ?></strong></div>
            <div id="savingsRow" class="cs-save hidden"><?= v2_ic('check-circle') ?><span id="savingsText"><?= v2_te('You save:') ?></span><strong id="savings"><?= v2_e(v2_money(0)) ?></strong></div>
            <div class="cs-reward hidden" id="pointsReward">
              <span class="cs-reward-ic" aria-hidden="true"><?= v2_ic('gift') ?></span>
              <div class="cs-reward-t"><b><?= v2_te('You will earn') ?></b><span class="cs-tip"><button class="cs-tip-btn" id="pointsRuleBtn" type="button" aria-describedby="pointsRule" aria-label="<?= v2_te('How points are earned') ?>"><?= v2_ic('info') ?></button><span class="cs-tip-box" id="pointsRule" role="tooltip"><?= v2_te('points on every order') ?></span></span></div>
              <p class="cs-pts"><span id="pointsEarned" class="points-animation">0</span><small><?= v2_te('points') ?></small></p>
            </div>
            <p class="cs-note"><?= v2_te('The card processing fee is worked out at checkout, depending on how you pay.') ?></p>
          </div>
          <div class="cs-foot">
            <a id="checkoutBtn" class="btn btn-primary cs-go" href="/checkout"><?= v2_te('Go to payment') ?><?= v2_ic('arrow-right') ?></a>
            <a class="cs-more" href="/categories"><?= v2_te('Add more activities') ?></a>
          </div>
          <div class="cs-pay">
            <p><?= v2_te('Accepted payment methods') ?></p>
            <ul><li>Visa</li><li>Mastercard</li><li>Apple Pay</li><li>Google Pay</li></ul>
          </div>
        </section>
        <ul class="co-trust">
          <li><?= v2_ic('lock-simple') ?><?= v2_te('Secure payment') ?></li>
          <li><?= v2_ic('qr-code') ?><?= v2_te('QR ticket straight away') ?></li>
        </ul>
      </div>
    </aside>
  </div>

  <div class="co-mbar" id="co-mbar" aria-hidden="true" inert>
    <div><small><?= v2_te('Total to pay') ?></small><b data-total><?= v2_e(v2_money(0)) ?></b></div>
    <a class="btn btn-primary" href="/checkout"><?= v2_te('Go to payment') ?><?= v2_ic('arrow-right') ?></a>
  </div>
</main>

<?php include __DIR__ . '/includes/v2/footer.php'; ?>
