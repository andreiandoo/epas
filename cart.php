<?php
/**
 * bilete.online — /cos (v2 "Arcada")
 *
 * Cart page. PHP scaffolds the containers; assets/js/pages/cart-page.js fills them from the cart that
 * assets/js/cart.js keeps in localStorage. The element IDs are that script's contract and must stay:
 * timer-bar, countdown, totalItems, cart-loading, cartPageItems, emptyCart, promo-section, promoCode,
 * promoMessage, summary-section, taxesContainer, summaryItems, subtotal, platformCommissionRow,
 * platformCommissionLabel, platformCommissionAmount, processingFeeRow, processingFeeAmount, discountRow,
 * discountAmount, savingsRow, savingsText, savings, totalPrice, pointsEarned, checkoutBtn.
 * The script shows and hides them with a `hidden` class (base.css).
 * The head (title, steps, count) is for a cart with something in it: an inline script hides it before the first paint
 * when the saved cart is empty, and cart.js keeps it in step with the empty state afterwards.
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/api.php';
require_once __DIR__ . '/includes/nav-helpers.php';
require_once __DIR__ . '/includes/v2/helpers.php';
require_once __DIR__ . '/includes/v2/nav.php';

$pageTitleRaw    = 'Coșul tău — ' . SITE_NAME;
$pageDescription = 'Verifică biletele și activitățile selectate, aplică puncte bonus sau coduri promoționale și continuă spre checkout.';
$canonicalUrl    = SITE_URL . '/cos';
$noindex         = true;
$currentPage     = 'cart';

$v2Styles = ['cart.css'];
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
    'currency' => 'RON',
    'supportEmail' => defined('SUPPORT_EMAIL') ? SUPPORT_EMAIL : '',
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) . ';</script>';

$emptyCities = array_slice($V2NAV['citiesList'] ?? [], 0, 6);
$emptyCats = array_slice($V2NAV['categories'] ?? [], 0, 6);

include __DIR__ . '/includes/v2/head.php';
include __DIR__ . '/includes/v2/header.php';
?>
<main id="main" class="page-main" tabindex="-1">

  <section class="co-head" id="co-head" aria-labelledby="co-h">
    <div class="wrap">
      <nav class="crumbs" aria-label="Breadcrumb"><a href="/">Acasă</a><span aria-hidden="true">/</span><span aria-current="page">Coș</span></nav>
      <div class="co-head-row">
        <div>
          <p class="kicker">Pasul 1 · Coș</p>
          <h1 class="co-h" id="co-h">Coșul tău</h1>
          <p class="co-lead">Verifică biletele și activitățile, comisioanele, punctele bonus, apoi continuă spre plata securizată.</p>
        </div>
        <div class="co-head-side">
          <ol class="steps" aria-label="Pașii comenzii">
            <li aria-current="step"><b>1</b>Coș</li>
            <li><b>2</b>Checkout</li>
            <li><b>3</b>Confirmare</li>
          </ol>
          <p class="co-count"><b id="totalItems">0</b> <span><span data-items-word>bilete</span> în coș</span></p>
        </div>
      </div>
    </div>
  </section>
  <script>(function () { try { var c = JSON.parse(localStorage.getItem('bileteonline_cart') || 'null'); if (!c || !Array.isArray(c.items) || !c.items.length) document.getElementById('co-head').hidden = true; } catch (e) {} })();</script>
  <div id="timer-bar" class="co-timer hidden" role="timer" aria-live="off">
    <div class="wrap co-timer-in">
      <?= v2_ic('clock') ?>
      <span>Biletele sunt rezervate<span class="co-long"> pentru tine</span> încă</span>
      <span id="countdown" class="countdown">14:59</span>
      <span>minute</span>
    </div>
  </div>

  <div class="wrap co-lay">
    <div class="co-main">
      <div id="cart-loading" class="co-skel">
        <span class="sr" role="status">Se încarcă coșul…</span>
        <i aria-hidden="true"></i><i aria-hidden="true"></i>
      </div>

      <div id="cartPageItems" class="co-items hidden"></div>

      <div id="emptyCart" class="co-empty hidden">
        <div class="co-empty-art" aria-hidden="true"><?= v2_fallback('cos', 1) ?><?= v2_ic('shopping-cart-simple') ?></div>
        <h1 class="co-empty-h" tabindex="-1">Coșul tău e gol</h1>
        <p>Nu ai nicio activitate sau eveniment în coș. Descoperă-le pe cele disponibile.</p>
        <a class="btn btn-primary" href="/categorii"><?= v2_ic('magnifying-glass') ?>Explorează activități</a>
        <p class="co-empty-small">Ai plătit deja? <a href="/cont/bilete">Vezi biletele tale</a> sau <a href="/recuperare-comanda">recuperează comanda</a>.</p>
        <?php if ($emptyCities || $emptyCats): ?>
        <div class="co-empty-links">
          <?php if ($emptyCities): ?>
          <div>
            <p class="flabel">Orașe populare</p>
            <div class="chips-links"><?php foreach ($emptyCities as $c): ?><a href="<?= v2_e($c['href']) ?>"><?= v2_e($c['name']) ?></a><?php endforeach; ?></div>
          </div>
          <?php endif; ?>
          <?php if ($emptyCats): ?>
          <div>
            <p class="flabel">Categorii</p>
            <div class="chips-links"><?php foreach ($emptyCats as $c): ?><a href="<?= v2_e($c['href']) ?>"><?= v2_e($c['name']) ?></a><?php endforeach; ?></div>
          </div>
          <?php endif; ?>
        </div>
        <?php endif; ?>
      </div>

      <section id="promo-section" class="co-promo hidden" aria-labelledby="promo-h">
        <div class="co-promo-text">
          <h2 id="promo-h">Ai un cod promoțional?</h2>
          <p>Reducerea se verifică pe loc și se vede imediat în total.</p>
        </div>
        <form class="co-promo-form" onsubmit="event.preventDefault(); CartPage.applyPromo();">
          <label class="sr" for="promoCode">Cod promoțional</label>
          <input id="promoCode" type="text" placeholder="ex. WEEKEND10" autocomplete="off" autocapitalize="characters" spellcheck="false">
          <button class="btn" type="submit">Aplică</button>
        </form>
        <p id="promoMessage" class="co-promo-msg hidden" role="status"></p>
      </section>
    </div>

    <aside class="co-side" aria-label="Sumar comandă">
      <div class="cs-skel" aria-hidden="true"></div>
      <div id="summary-section" class="hidden">
        <section class="cs" aria-labelledby="cs-h">
          <div class="cs-top">
            <p class="cs-kicker">Sumar comandă</p>
            <h2 class="cs-h" id="cs-h">Total coș</h2>
          </div>
          <div class="cs-body">
            <div id="taxesContainer" class="cs-lines"></div>
            <div class="cs-line cs-sub"><span>Subtotal (<span id="summaryItems">0</span> <span data-items-word>bilete</span>)</span><strong id="subtotal">0,00 lei</strong></div>
            <div id="platformCommissionRow" class="cs-line hidden"><span id="platformCommissionLabel">Comision ticketing</span><strong id="platformCommissionAmount">0,00 lei</strong></div>
            <div id="processingFeeRow" class="cs-line hidden"><span>Taxa procesare card</span><strong id="processingFeeAmount">0,00 lei</strong></div>
            <div id="discountRow" class="cs-line cs-disc hidden"><span>Reducere aplicată</span><strong id="discountAmount">-0,00 lei</strong></div>
            <div class="cs-line cs-total"><span>Total de plată</span><strong id="totalPrice">0,00 lei</strong></div>
            <div id="savingsRow" class="cs-save hidden"><?= v2_ic('check-circle') ?><span id="savingsText">Economisești:</span><strong id="savings">0,00 lei</strong></div>
            <div class="cs-reward">
              <span class="cs-reward-ic" aria-hidden="true"><?= v2_ic('gift') ?></span>
              <div><b>Vei câștiga</b><p>1 punct / 10 lei cheltuiți</p></div>
              <p class="cs-pts"><span id="pointsEarned" class="points-animation">0</span><small>puncte</small></p>
            </div>
            <p class="cs-note">Taxa de procesare card se calculează la checkout, în funcție de metoda de plată.</p>
          </div>
          <div class="cs-foot">
            <a id="checkoutBtn" class="btn btn-primary cs-go" href="/finalizare">Continuă spre plată<?= v2_ic('arrow-right') ?></a>
            <a class="cs-more" href="/categorii">Mai adaugă activități</a>
          </div>
          <div class="cs-pay">
            <p>Metode de plată acceptate</p>
            <ul><li>Visa</li><li>Mastercard</li><li>Apple Pay</li><li>Google Pay</li></ul>
          </div>
        </section>
        <ul class="co-trust">
          <li><?= v2_ic('lock-simple') ?>Plată securizată</li>
          <li><?= v2_ic('qr-code') ?>Bilet QR instant</li>
        </ul>
      </div>
    </aside>
  </div>

  <div class="co-mbar" id="co-mbar" aria-hidden="true" inert>
    <div><small>Total de plată</small><b data-total>0,00 lei</b></div>
    <a class="btn btn-primary" href="/finalizare">Continuă spre plată<?= v2_ic('arrow-right') ?></a>
  </div>
</main>

<?php include __DIR__ . '/includes/v2/footer.php'; ?>
