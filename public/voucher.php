<?php
/**
 * Gift card balance check: /voucher (and the old /verifica-card-cadou) (v2 design).
 *
 * Public page. The customer enters a gift card code (and the PIN when the card has one); voucher.js posts it to
 * `/customer/gift-cards/check-balance` through the API proxy and shows balance, validity and status. The backend does
 * the validation (marketplace-scoped, code masked in the answer, PIN required when set).
 *
 * Top to bottom: hero (steps + the check card, which turns into the result), FAQ with a link to buy a gift card.
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/api.php';
require_once __DIR__ . '/includes/nav-helpers.php';
require_once __DIR__ . '/includes/v2/helpers.php';
require_once __DIR__ . '/includes/v2/nav.php';

// Prefill from emails: ?cod=XXXX (or ?code=).
$prefillCode = '';
foreach (['cod', 'code'] as $param) {
    if ($prefillCode === '' && is_string($_GET[$param] ?? null)) {
        $prefillCode = strtoupper(substr(preg_replace('/[^A-Za-z0-9\-]/', '', $_GET[$param]), 0, 40));
    }
}

$faqs = [
    [v2_t('Where do I find the gift card code?'), v2_t('For digital cards, the code is in the gift email. For physical cards, it is printed on the card. The PIN appears only on physical cards, on the back, under a scratch-off strip.')],
    [v2_t('How do I use the card at checkout?'), v2_t('When you complete an order, enter the code in the "Gift card / voucher" field. The available balance is deducted automatically. If the order value is higher, you pay the difference by bank card.')],
    [v2_t('Does the card expire?'), v2_t('viaqui.com gift cards are valid for 12 months from the date of issue. After that date, the remaining balance can no longer be used.')],
    [v2_t('Can I use the card for several orders?'), v2_t('Yes. The balance goes down with each use until it runs out or the card expires. You can check the current balance here at any time.')],
    [v2_t('The card is not valid. What do I do?'), v2_t('Check that you typed the code correctly (watch out for 0 / O or 1 / I). If it still does not work, write to us through <a href="{url}">Contact</a> with the code and the email address it was sent to.', ['url' => '/contact'])],
];

$pageTitle = v2_t('Check a gift card');
$pageDescription = v2_t('Check the available balance and the validity of a viaqui.com gift card. Enter the code and, if needed, the PIN.');
$canonicalUrl = SITE_URL . '/voucher';
$noindex = true;
$skipPageCache = true;

$v2Styles = ['voucher.css'];
$v2Scripts = ['voucher.js'];
$v2LegacyScripts = ['assets/js/config.js', 'assets/js/utils.js', 'assets/js/api.js'];
$v2HeaderOverlay = true;
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

include __DIR__ . '/includes/v2/head.php';
include __DIR__ . '/includes/v2/header.php';
?>
<main id="main" tabindex="-1">
  <section class="vc-hero" aria-labelledby="vc-h">
    <svg class="deco-arches" viewBox="0 0 400 400" aria-hidden="true" focusable="false"><path d="M40 400V200a160 160 0 0 1 320 0v200"/><path d="M90 400V200a110 110 0 0 1 220 0v200"/><path d="M140 400V200a60 60 0 0 1 120 0v200"/></svg>
    <svg class="vc-line draw-clip" viewBox="0 590 3240 310" aria-hidden="true" focusable="false"><use href="#drum-g"/></svg>
    <div class="vc-in">
      <div class="vc-copy">
        <p class="vc-kicker"><?= v2_te('Gift card · voucher · balance') ?></p>
        <h1 class="vc-h" id="vc-h"><?= v2_te('Check a gift card.') ?></h1>
        <p class="vc-lead"><?= v2_te('Enter the gift card code to see how much is left on it and until when it can be used. For cards with a PIN, you need the PIN too.') ?></p>
        <ol class="vc-steps">
          <li><small><?= v2_te('Step 1') ?></small><b><?= v2_te('Code') ?></b></li>
          <li><small><?= v2_te('Step 2') ?></small><b><?= v2_te('Balance') ?></b></li>
          <li><small><?= v2_te('Step 3') ?></small><b><?= v2_te('Use it') ?></b></li>
        </ol>
      </div>

      <section class="vc-card" id="vc-card" aria-label="<?= v2_te('Gift card check form') ?>">
        <!-- the header turns solid when the white card reaches it (beside the text on desktop, under it on a phone),
             not after the whole hero: its light links would disappear over the card -->
        <div id="hdr-sentinel" aria-hidden="true"></div>
        <!-- FORM -->
        <div id="vc-form-view">
          <p class="vc-card-k"><?= v2_te('Check the code') ?></p>
          <h2 class="vc-card-h"><?= v2_te('Gift card') ?></h2>
          <p class="vc-card-p"><?= v2_te('The code is on the physical card or in the gift email.') ?></p>
          <p class="vc-error" id="vc-error" role="alert" hidden></p>
          <form id="vc-form" novalidate>
            <div class="vc-field">
              <label for="vc-code"><?= v2_te('Gift card code') ?></label>
              <input id="vc-code" name="code" class="is-code" type="text" autocomplete="off" autocapitalize="characters" spellcheck="false" maxlength="40" required placeholder="<?= v2_te('e.g. GIFT-2026-XXXX') ?>" value="<?= v2_e($prefillCode) ?>">
            </div>
            <div class="vc-field">
              <label for="vc-pin"><?= v2_te('PIN (if needed)') ?></label>
              <input id="vc-pin" name="pin" type="text" inputmode="numeric" autocomplete="off" maxlength="12" placeholder="<?= v2_te('optional') ?>" aria-describedby="vc-pin-hint">
              <span class="vc-hint" id="vc-pin-hint"><?= v2_te('Only physical cards have a PIN, printed on the back of the card.') ?></span>
            </div>
            <button class="btn btn-primary vc-submit" id="vc-submit" type="submit"><?= v2_te('Check the balance') ?></button>
          </form>
          <p class="vc-card-foot"><?= v2_t('No gift card yet? <a href="{url}">Buy one</a>', ['url' => '/gift-card']) ?></p>
        </div>

        <!-- RESULT -->
        <div class="vc-result" id="vc-result" data-state="ok" hidden>
          <span class="vc-badge" aria-hidden="true"><svg class="ic is-ok"><use href="#i-check"/></svg><svg class="ic is-bad"><use href="#i-x"/></svg></span>
          <p class="vc-card-k" id="vc-r-kicker"><?= v2_te('Valid card') ?></p>
          <h2 class="vc-card-h vc-code" id="vc-r-code" tabindex="-1"></h2>
          <dl class="vc-stats">
            <div class="is-balance"><dt><?= v2_te('Available balance') ?></dt><dd><b id="vc-r-balance">—</b><span id="vc-r-currency"><?= v2_e(SITE_CURRENCY) ?></span></dd></div>
            <div><dt><?= v2_te('Valid until') ?></dt><dd><b id="vc-r-expires">—</b><span id="vc-r-expiry-label"></span></dd></div>
          </dl>
          <dl class="vc-meta">
            <div><dt><?= v2_te('Status') ?></dt><dd id="vc-r-status">—</dd></div>
            <div><dt><?= v2_te('Initial value') ?></dt><dd id="vc-r-initial">—</dd></div>
          </dl>
          <div class="vc-note is-ok"><b><?= v2_te('The card can be used at checkout') ?></b><p><?= v2_te('When you complete an order, enter the code in the "Gift card / voucher" field and the balance is deducted automatically.') ?></p></div>
          <div class="vc-note is-bad"><b><?= v2_te('The card cannot be used right now') ?></b><p id="vc-r-reason"></p></div>
          <div class="vc-actions">
            <a class="btn btn-primary" href="/categories"><?= v2_te('Use it on an order') ?><?= v2_ic('arrow-right') ?></a>
            <button class="btn btn-ghost" type="button" id="vc-again"><?= v2_te('Check another card') ?></button>
          </div>
        </div>
      </section>
    </div>
  </section>

  <section class="sec vc-faq" aria-labelledby="vc-faq-h">
    <div class="wrap vc-faq-grid">
      <div>
        <p class="kicker"><?= v2_te('Questions') ?></p>
        <h2 id="vc-faq-h"><?= v2_te('The viaqui.com gift card') ?></h2>
        <a class="btn btn-primary vc-faq-cta" href="/gift-card"><?= v2_ic('gift') ?><?= v2_te('Buy a gift card') ?></a>
      </div>
      <div>
        <?php foreach ($faqs as $fi => [$faqQ, $faqA]): ?>
        <details class="qa"<?= $fi === 0 ? ' open' : '' ?>><summary><?= v2_e($faqQ) ?><span class="pm"><?= v2_ic('plus') ?></span></summary><p><?= $faqA /* static copy with trusted markup */ ?></p></details>
        <?php endforeach; ?>
      </div>
    </div>
  </section>
</main>
<?php include __DIR__ . '/includes/v2/footer.php'; ?>
