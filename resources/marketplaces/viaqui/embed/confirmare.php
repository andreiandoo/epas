<?php
/**
 * The order confirmation inside the booking widget: /embed/confirmare?comanda=<order number>&a=<allow token>
 * (embed code v2). The operator's page loads it in the widget after the payment (embed/retur.php → #bo-back).
 *
 * Reads the order like the thank-you page (proxy action order-confirmation), asks again every 5 s for a minute while
 * the payment is still being confirmed, and shows: paid / waiting / failed, what was bought, the tickets as a PDF and
 * a way to buy again. On a paid order it tells the operator's page once ({type: 'bo-embed-purchase'}), which fires
 * the page's own conversion events, and empties the cart kept in the frame. Framed only by the sites in the signed
 * allow token.
 */

require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/nav-helpers.php';
require_once dirname(__DIR__) . '/includes/v2/helpers.php';
require_once dirname(__DIR__) . '/includes/embed-return.php';

$aRaw = isset($_GET['a']) && is_string($_GET['a']) ? $_GET['a'] : '';
$allow = $aRaw !== '' ? bo_embed_allow_verify($aRaw) : null;
$order = isset($_GET['comanda']) && is_string($_GET['comanda']) && preg_match('/^[A-Za-z0-9-]{1,64}$/', $_GET['comanda']) ? $_GET['comanda'] : '';

header('Content-Security-Policy: frame-ancestors ' . bo_embed_frame_ancestors($allow));
header('X-Robots-Tag: noindex');
header('Cache-Control: no-store');
header('Content-Type: text/html; charset=utf-8');

$again = $allow ? '/embed/locatie/' . rawurlencode($allow['slug']) : '';
?><!DOCTYPE html>
<html lang="<?= v2_e(v2_locale()) ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex">
<title><?= v2_te('Your order') ?> · <?= v2_e(SITE_NAME) ?></title>
<link rel="preload" href="/assets/v2/fonts/Geist-latin.woff2" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="<?= v2_asset('css/base.css') ?>">
<link rel="stylesheet" href="<?= v2_asset('css/embed-ck.css') ?>">
<base target="_blank">
</head>
<body class="emb-ck">
<?php readfile(dirname(__DIR__) . '/includes/v2/sprite.svg'); ?>
<main class="ecf" id="main" data-state="loading">
  <div class="ecf-card">
    <span class="ecf-ic" aria-hidden="true">
      <span data-show="loading"><?= v2_ic('clock') ?></span>
      <span data-show="paid"><?= v2_ic('check') ?></span>
      <span data-show="pending"><?= v2_ic('clock') ?></span>
      <span data-show="failed missing"><?= v2_ic('x') ?></span>
    </span>
    <h1 class="ecf-h" id="ecf-h" tabindex="-1"><?= v2_te('Loading your order…') ?></h1>
    <p class="ecf-lead" id="ecf-lead" role="status"></p>
    <?php if ($order !== ''): ?><p class="ecf-ref"><?= v2_t('Order <b>#{number}</b>', ['number' => v2_e($order)]) ?></p><?php endif; ?>

    <ul class="ecf-lines" id="ecf-lines" data-show="paid pending"></ul>
    <p class="ecf-total" id="ecf-total" data-show="paid pending" hidden></p>

    <div class="ecf-act">
      <a class="btn btn-primary" id="ecf-pdf" data-show="paid" href="/api/proxy.php?action=order.download-tickets-pdf&amp;order=<?= rawurlencode($order) ?>" download><?= v2_ic('ticket') ?><?= v2_te('Download tickets (PDF)') ?></a>
      <?php if ($again !== ''): ?><a class="btn btn-ghost" href="<?= v2_e($again) ?>" target="_self"><?= v2_te('Buy more tickets') ?></a><?php endif; ?>
    </div>
    <p class="ecf-small" data-show="paid"><span id="ecf-mail"><?= v2_te('Your QR code tickets have also been sent by email.') ?></span> <?= v2_te('Show the code on your phone at the entrance.') ?></p>
    <p class="ecf-small"><?= v2_t('Seller: {site}. A question about your order? Write to <a href="mailto:{email}">{email}</a>.', ['site' => v2_e(SITE_NAME), 'email' => v2_e(SUPPORT_EMAIL)]) ?></p>
  </div>
</main>
<script>
(function () {
  'use strict';
  var ORDER = <?= json_encode($order) ?>;
  // The page's texts, from PHP (this page loads no i18n.js): {placeholders} are filled below.
  var T = <?= json_encode([
      'ticket' => v2_t('Ticket'),
      'totalPaid' => v2_t('Total paid: {amount}'),
      'failedH' => v2_t('The payment did not go through'),
      'failedP' => v2_t('Nothing was charged. You can try again: choose your tickets once more.'),
      'pendingH' => v2_t('Your payment is being checked'),
      'pendingP' => v2_t('It usually takes a few seconds. This page updates by itself.'),
      'lateP' => v2_t('The confirmation is taking longer than usual. Your tickets arrive by email as soon as the payment is confirmed.'),
      'sentTo' => v2_t('Your QR code tickets have also been sent by email to {email}.'),
      'paidH' => v2_t('Your tickets are ready'),
      'paidP' => v2_t('The payment was confirmed and your tickets have been issued.'),
      'missingH' => v2_t('We could not find the order'),
      'missingP' => v2_t('Check the email you received after paying.'),
      'loadH' => v2_t('We could not load the order'),
      'loadP' => v2_t('Your tickets arrive by email as soon as the payment is confirmed.'),
  ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
  var LOCALE = <?= json_encode(v2_locale() === 'en' ? 'en-GB' : v2_locale()) ?>;
  var main = document.getElementById('main'), tries = 0;
  var $ = function (id) { return document.getElementById(id); };
  var inFrame = window.parent !== window;

  function post(msg) { if (inFrame) { try { window.parent.postMessage(msg, '*'); } catch (e) {} } }
  function size() {
    var h = Math.ceil(main.getBoundingClientRect().bottom + window.pageYOffset + 16);
    post({ type: 'bo-embed-height', height: h });
  }
  if ('ResizeObserver' in window) new ResizeObserver(size).observe(main);
  window.addEventListener('load', size);

  function money(v, cur) {
    var n = Number(v) || 0;
    var shown = n.toLocaleString(LOCALE, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    return !cur || /^eur$/i.test(cur) ? '€' + shown : shown + ' ' + cur; // the currency the order carries, the euro otherwise
  }
  function state(s, h, lead) {
    main.setAttribute('data-state', s);
    $('ecf-h').textContent = h;
    $('ecf-lead').textContent = lead || '';
    size();
  }
  function lines(o) {
    var ul = $('ecf-lines');
    ul.textContent = '';
    (Array.isArray(o.items) ? o.items : []).forEach(function (it) {
      var li = document.createElement('li'), b = document.createElement('b'), s = document.createElement('span');
      b.textContent = (it.quantity > 1 ? it.quantity + ' × ' : '') + String(it.name || T.ticket);
      s.textContent = [it.location, it.date_label, it.time_label].filter(Boolean).join(' · ');
      li.appendChild(b);
      if (s.textContent) li.appendChild(s);
      ul.appendChild(li);
    });
    $('ecf-total').hidden = !(Number(o.total) > 0);
    $('ecf-total').textContent = T.totalPaid.replace('{amount}', money(o.total, o.currency));
  }
  function emptyCart() {
    try {
      localStorage.setItem('bileteonline_cart', JSON.stringify({ items: [], updatedAt: new Date().toISOString() }));
      ['bileteonline_cart_promo', 'bileteonline_cart_reservation', 'cart_end_time'].forEach(function (k) { localStorage.removeItem(k); });
    } catch (e) {}
  }
  function show(o) {
    var st = String(o.status || ''), ps = String(o.payment_status || '');
    var failed = /^(failed|cancelled|expired)$/.test(st) || /^(failed|declined|expired)$/.test(ps);
    var pending = !failed && (st === 'pending' || ps === 'pending');
    lines(o);
    if (failed) {
      state('failed', T.failedH, T.failedP);
      return;
    }
    if (pending) {
      state('pending', T.pendingH, T.pendingP);
      if (tries++ < 12) setTimeout(load, 5000);
      else $('ecf-lead').textContent = T.lateP;
      return;
    }
    if (o.customer_email) $('ecf-mail').textContent = T.sentTo.replace('{email}', o.customer_email);
    state('paid', T.paidH, T.paidP);
    emptyCart();
    post({ type: 'bo-embed-purchase', order: String(o.order_number || ORDER), order_id: o.id, value: Number(o.total) || 0, currency: o.currency || 'EUR' });
  }
  function load() {
    if (!ORDER) { state('missing', T.missingH, T.missingP); return; }
    fetch('/api/proxy.php?action=order-confirmation&id=' + encodeURIComponent(ORDER), { headers: { Accept: 'application/json' }, cache: 'no-store' })
      .then(function (r) { return r.json(); })
      .then(function (r) {
        var o = r && r.success !== false && r.data && r.data.order;
        if (!o) { state('missing', T.missingH, T.missingP); return; }
        show(o);
      }, function () {
        if (tries++ < 12) setTimeout(load, 5000);
        else state('missing', T.loadH, T.loadP);
      });
  }
  post({ type: 'bo-embed-top' });
  load();
})();
</script>
</body>
</html>
