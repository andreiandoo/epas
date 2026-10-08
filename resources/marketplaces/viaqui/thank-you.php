<?php
/**
 * viaqui.com — /thank-you, v2 "Arcada"
 *
 * Order confirmation after payment. PHP scaffolds the page; assets/js/pages/thank-you.js loads the order from the
 * order-confirmation API and sets the state on #main.ty (loading, success, pending, failed, notfound). Blocks list the
 * states they belong to in data-show and thank-you.css hides the others. IDs used by the script: ty-title,
 * printingText, confetti, ticketsCarousel, ticketsCount, ticketsScroll, scrollIndicators, ticketsPrev, ticketsNext,
 * emailCardTitle, buyerEmail, orderDetails, orderStatus, eventInfo, ticketsSummary, paymentSummary,
 * paymentProcessorBadge, paymentProcessorBadgeText, cardNumber, pointsEarned, pointsTitle, pointsText, earnedPoints,
 * thankYouMessage, thankYouMessageBody, downloadBtn, calendarBtn, shareFb, shareWa, backSection.
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/api.php';
require_once __DIR__ . '/includes/nav-helpers.php';
require_once __DIR__ . '/includes/v2/helpers.php';
require_once __DIR__ . '/includes/v2/nav.php';

$orderRef = trim((string) ($_GET['order'] ?? $_GET['orderId'] ?? ''));

$pageTitleRaw    = v2_t('Order confirmed') . ' · ' . SITE_NAME;
$pageDescription = v2_t('Your order is confirmed. Download your QR tickets and check the order details.');
$canonicalUrl    = SITE_URL . '/thank-you';
$noindex         = true;
$currentPage     = 'thank-you';
$skipPageCache   = true;

$v2HeaderOverlay = true;
$v2Styles = ['thank-you.css'];
$v2LegacyScripts = [
    'assets/js/config.js', 'assets/js/utils.js', 'assets/js/api.js', 'assets/js/auth.js',
    'assets/js/components/notifications.js', 'assets/js/pages/thank-you.js',
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

// Ad tracking service: the pixels of the operator who sold this order load right away (not on first interaction),
// and ad-conversions.js sends them the purchase once thank-you.js shows the order as paid. Only the operator id is
// cached, never the order.
$tyOrg = null;
if ($orderRef !== '' && preg_match('/^[A-Za-z0-9-]{1,64}$/', $orderRef)) {
    $tyOrg = api_cached('ty_org_' . $orderRef, function () use ($orderRef) {
        $r = api_get('/orders/' . rawurlencode($orderRef));
        if (empty($r['success'])) {
            return ['success' => false];
        }
        return ['success' => true, 'organizer_id' => (int) ($r['data']['order']['organizer_id'] ?? 0)];
    }, 86400);
}
$organizerTrackingId = !empty($tyOrg['organizer_id']) ? (int) $tyOrg['organizer_id'] : null;
$trackingEager = true;
$v2Scripts = ['ad-conversions.js'];

$downloadIcon ='<svg class="ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 4v11m0 0-4.5-4.5M12 15l4.5-4.5M5 19.5h14"/></svg>';

include __DIR__ . '/includes/v2/head.php';
include __DIR__ . '/includes/v2/header.php';
?>
<div class="confetti-container" id="confetti" aria-hidden="true"></div>

<main id="main" class="ty" data-state="loading" tabindex="-1">

  <section class="ty-hero" aria-labelledby="ty-title">
    <div class="wrap">
      <div class="ty-steps">
        <ol class="steps" aria-label="<?= v2_te('Order steps') ?>">
          <li class="is-done"><b><?= v2_ic('check') ?></b><?= v2_te('Basket') ?></li>
          <li class="is-done"><b><?= v2_ic('check') ?></b><?= v2_te('Checkout') ?></li>
          <li id="ty-step-confirm" aria-current="step"><b><span data-show="loading success"><?= v2_ic('check') ?></span><span data-show="pending">3</span><span data-show="failed notfound"><?= v2_ic('x') ?></span></b><?= v2_te('Confirmation') ?></li>
        </ol>
      </div>
      <?php if ($orderRef !== ''): ?>
      <p class="ty-ref"><?= v2_t('Order <b>#{ref}</b>', ['ref' => v2_e($orderRef)]) ?></p>
      <?php endif; ?>
      <div class="ty-icon" aria-hidden="true">
        <span data-show="loading success"><?= v2_ic('check') ?></span>
        <span data-show="pending"><?= v2_ic('clock') ?></span>
        <span data-show="failed notfound"><?= v2_ic('x') ?></span>
      </div>
      <p class="ty-kicker" data-show="loading success"><?= v2_te('Step 3 · Order confirmed') ?></p>
      <p class="ty-kicker" data-show="pending"><?= v2_te('Step 3 · Payment being checked') ?></p>
      <p class="ty-kicker" data-show="failed notfound"><?= v2_te('Step 3 · Confirmation') ?></p>
      <h1 class="ty-h" id="ty-title"><?= v2_te('Your tickets are ready.') ?></h1>
      <p id="printingText" class="ty-lead" role="status"><?= v2_te('Your payment went through. Your tickets are being issued now…') ?></p>
      <?php /* bought through an operator's booking widget: the way back to their site (thank-you.js, api/embed-return.php) */ ?>
      <p class="ty-return" id="ty-return" hidden><span><?= v2_t('You bought this on {name}.', ['name' => '<b data-return-name></b>']) ?></span><a class="btn btn-primary" data-return-link href="#"><?= v2_ic('arrow-left') ?><span><?= v2_t('Back to {name}', ['name' => '<span data-return-name></span>']) ?></span></a></p>
    </div>
    <svg class="ty-line" viewBox="0 590 3240 310" aria-hidden="true"><use href="#drum-g"/></svg>
  </section>
  <div id="hdr-sentinel" aria-hidden="true"></div>

  <div class="wrap ty-wrap">

    <div class="ty-printer" data-show="loading success" aria-hidden="true">
      <div class="ty-slot"></div>
      <div class="ty-print">
        <p class="ty-print-top"><span><?= v2_e(SITE_NAME) ?></span><b><?= v2_te('Ticket') ?></b></p>
        <p class="ty-print-body"><span><?= v2_te('Experience') ?></span><b><?= v2_te('Issuing…') ?></b></p>
      </div>
    </div>

    <section class="ty-tickets ty-reveal" id="ticketsCarousel" data-show="success" aria-labelledby="ty-tickets-h" style="--d:.05s">
      <p class="kicker"><?= v2_te('Tickets issued') ?></p>
      <h2 id="ty-tickets-h"><?= v2_te('Your tickets are ready') ?></h2>
      <p id="ticketsCount"><?= v2_te('Loading…') ?></p>
      <p class="ty-swipe" aria-hidden="true"><?= v2_ic('arrow-left') ?><?= v2_te('Swipe to see all your tickets') ?><?= v2_ic('arrow-right') ?></p>
      <div class="ty-scroll" id="ticketsScroll" aria-label="<?= v2_te('Tickets in this order') ?>"></div>
      <div class="ty-nav">
        <button class="ty-arrow" type="button" id="ticketsPrev" aria-label="<?= v2_te('Previous ticket') ?>" hidden><?= v2_ic('arrow-left') ?></button>
        <div class="ty-dots" id="scrollIndicators"></div>
        <button class="ty-arrow" type="button" id="ticketsNext" aria-label="<?= v2_te('Next ticket') ?>" hidden><?= v2_ic('arrow-right') ?></button>
      </div>
    </section>

    <div class="ty-email ty-reveal" id="emailCard" data-show="success pending" style="--d:.15s">
      <span class="ty-email-ic" aria-hidden="true"><?= v2_ic('envelope-simple') ?></span>
      <div>
        <p class="ty-email-t" id="emailCardTitle"><?= v2_te('Your tickets were sent by email') ?></p>
        <p id="buyerEmail"><?= v2_te('Loading…') ?></p>
      </div>
      <span class="ty-email-ok" aria-hidden="true"><span data-show="success"><?= v2_ic('check-circle') ?></span><span data-show="pending"><?= v2_ic('clock') ?></span></span>
    </div>

    <section class="ty-card" id="orderDetails" data-show="loading success pending failed" aria-labelledby="ty-details-h">
      <div class="ty-card-head">
        <div>
          <p class="kicker"><?= v2_te('Order details') ?></p>
          <h2 id="ty-details-h"><?= v2_te('Summary') ?></h2>
        </div>
        <span class="ty-status" id="orderStatus"><?= v2_te('Confirmed') ?></span>
      </div>
      <div class="ty-card-body">
        <div id="eventInfo" class="ty-event">
          <span class="ty-skel ty-skel-media"></span>
          <span class="ty-skel-lines"><span class="ty-skel"></span><span class="ty-skel"></span><span class="ty-skel"></span></span>
        </div>

        <div id="ticketsSummary" class="ty-box">
          <p class="ty-sub-h"><?= v2_te('Tickets bought') ?></p>
          <span class="ty-skel ty-skel-block"></span>
        </div>

        <div class="ty-pay">
          <div>
            <p class="ty-sub-h"><?= v2_te('Payment method') ?></p>
            <div class="ty-method">
              <span id="paymentProcessorBadge" class="ty-badge"><span id="paymentProcessorBadgeText">STRIPE</span></span>
              <div>
                <b><?= v2_te('Card') ?></b>
                <p id="cardNumber">**** **** **** ****</p>
              </div>
            </div>
          </div>
          <div id="paymentSummary">
            <p class="ty-sub-h"><?= v2_te('Payment summary') ?></p>
            <div class="ty-lines"><span class="ty-skel"></span><span class="ty-skel"></span><span class="ty-skel ty-skel-lg"></span></div>
          </div>
        </div>

        <div id="pointsEarned" class="ty-points" hidden>
          <span class="ty-points-ic" aria-hidden="true"><?= v2_ic('gift') ?></span>
          <div>
            <b id="pointsTitle"><?= v2_te('You earn points with this order') ?></b>
            <p id="pointsText"><?= v2_t('They are added to your account after the activity. You can see them in <a href="{url}">My points</a>.', ['url' => '/account/points']) ?></p>
          </div>
          <p class="ty-points-n"><span id="earnedPoints">+0</span><small><?= v2_te('points') ?></small></p>
        </div>
      </div>
    </section>

    <section class="ty-msg hidden" id="thankYouMessage" data-show="success pending" aria-labelledby="ty-msg-h">
      <p class="kicker" id="ty-msg-h"><?= v2_te('A message from the organiser') ?></p>
      <div class="ty-msg-body" id="thankYouMessageBody"></div>
    </section>

    <div class="ty-actions ty-reveal" data-show="success" style="--d:.25s">
      <a href="#" id="downloadBtn" class="ty-action">
        <span class="ty-action-ic" aria-hidden="true"><?= $downloadIcon ?></span>
        <span><b><?= v2_te('Print your tickets') ?></b><small><?= v2_te('Print them or save them as a PDF') ?></small></span>
        <?= v2_ic('arrow-right') ?>
      </a>
      <a href="#" id="calendarBtn" class="ty-action">
        <span class="ty-action-ic" aria-hidden="true"><?= v2_ic('calendar-blank') ?></span>
        <span><b><?= v2_te('Add to calendar') ?></b><small>Google Calendar / iCal</small></span>
        <?= v2_ic('arrow-right') ?>
      </a>
    </div>

    <section class="ty-next ty-reveal" data-show="success pending" aria-labelledby="ty-next-h" style="--d:.35s">
      <p class="kicker"><?= v2_te('What happens next') ?></p>
      <h2 id="ty-next-h"><?= v2_te('Next steps') ?></h2>
      <ol>
        <li><span>01</span><b><?= v2_te('Check your email') ?></b><p><?= v2_te('Your tickets were sent to the address on the order.') ?></p></li>
        <li><span>02</span><b><?= v2_te('Add it to your calendar') ?></b><p><?= v2_te('So you get a reminder before the activity.') ?></p></li>
        <li><span>03</span><b><?= v2_te('Show the QR code') ?></b><p><?= v2_te('You do not need to print the ticket.') ?></p></li>
      </ol>
    </section>

    <div class="ty-share ty-reveal" data-show="success pending" style="--d:.45s">
      <p><?= v2_te('Tell your friends') ?></p>
      <div>
        <a href="#" id="shareFb" class="is-fb" aria-label="<?= v2_te('Share on Facebook') ?>" rel="noopener">
          <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
        </a>
        <a href="#" id="shareWa" class="is-wa" aria-label="<?= v2_te('Send on WhatsApp') ?>" rel="noopener">
          <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
        </a>
        <button type="button" onclick="ThankYouPage.copyLink()" aria-label="<?= v2_te('Copy the link') ?>">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="8" y="8" width="12" height="12" rx="2.5"/><path d="M16 8V6.5A2.5 2.5 0 0 0 13.5 4h-7A2.5 2.5 0 0 0 4 6.5v7A2.5 2.5 0 0 0 6.5 16H8"/></svg>
        </button>
      </div>
    </div>

    <div class="ty-back" id="backSection">
      <a href="/" class="btn btn-primary"><?= v2_ic('arrow-left') ?><?= v2_te('Back to the homepage') ?></a>
    </div>
  </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', function () {
    if (typeof ProfileCompletionModal !== 'undefined') ProfileCompletionModal.triggerAfterPurchase();
});
</script>

<?php include __DIR__ . '/includes/v2/footer.php'; ?>
