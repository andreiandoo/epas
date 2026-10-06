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
<html lang="ro">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex">
<title>Comanda ta · <?= v2_e(SITE_NAME) ?></title>
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
    <h1 class="ecf-h" id="ecf-h" tabindex="-1">Se încarcă comanda…</h1>
    <p class="ecf-lead" id="ecf-lead" role="status"></p>
    <?php if ($order !== ''): ?><p class="ecf-ref">Comanda <b>#<?= v2_e($order) ?></b></p><?php endif; ?>

    <ul class="ecf-lines" id="ecf-lines" data-show="paid pending"></ul>
    <p class="ecf-total" id="ecf-total" data-show="paid pending" hidden></p>

    <div class="ecf-act">
      <a class="btn btn-primary" id="ecf-pdf" data-show="paid" href="/api/proxy.php?action=order.download-tickets-pdf&amp;order=<?= rawurlencode($order) ?>" download><?= v2_ic('ticket') ?>Descarcă biletele (PDF)</a>
      <?php if ($again !== ''): ?><a class="btn btn-ghost" href="<?= v2_e($again) ?>" target="_self">Cumpără alte bilete</a><?php endif; ?>
    </div>
    <p class="ecf-small" data-show="paid">Biletele cu cod QR au plecat și pe email<span id="ecf-mail"></span>. La intrare arăți codul de pe telefon.</p>
    <p class="ecf-small">Vânzător: <?= v2_e(SITE_NAME) ?>. Ai o întrebare despre comandă? Scrie la <a href="mailto:<?= v2_e(SUPPORT_EMAIL) ?>"><?= v2_e(SUPPORT_EMAIL) ?></a>.</p>
  </div>
</main>
<script>
(function () {
  'use strict';
  var ORDER = <?= json_encode($order) ?>;
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
    return n.toLocaleString('ro-RO', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' ' + (!cur || /^ron$/i.test(cur) ? 'lei' : cur);
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
      b.textContent = (it.quantity > 1 ? it.quantity + ' × ' : '') + String(it.name || 'Bilet');
      s.textContent = [it.location, it.date_label, it.time_label].filter(Boolean).join(' · ');
      li.appendChild(b);
      if (s.textContent) li.appendChild(s);
      ul.appendChild(li);
    });
    $('ecf-total').hidden = !(Number(o.total) > 0);
    $('ecf-total').textContent = 'Total plătit: ' + money(o.total, o.currency);
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
      state('failed', 'Plata nu a fost procesată', 'Nu s-a încasat nimic. Poți încerca din nou: alege biletele încă o dată.');
      return;
    }
    if (pending) {
      state('pending', 'Plata e în verificare', 'Durează de obicei câteva secunde. Pagina se actualizează singură.');
      if (tries++ < 12) setTimeout(load, 5000);
      else $('ecf-lead').textContent = 'Confirmarea întârzie. Biletele îți vin pe email imediat ce plata e confirmată.';
      return;
    }
    if (o.customer_email) $('ecf-mail').textContent = ' la ' + o.customer_email;
    state('paid', 'Biletele tale sunt gata', 'Plata a fost confirmată și biletele au fost emise.');
    emptyCart();
    post({ type: 'bo-embed-purchase', order: String(o.order_number || ORDER), order_id: o.id, value: Number(o.total) || 0, currency: o.currency || 'RON' });
  }
  function load() {
    if (!ORDER) { state('missing', 'Comanda nu a fost găsită', 'Verifică emailul primit după plată.'); return; }
    fetch('/api/proxy.php?action=order-confirmation&id=' + encodeURIComponent(ORDER), { headers: { Accept: 'application/json' }, cache: 'no-store' })
      .then(function (r) { return r.json(); })
      .then(function (r) {
        var o = r && r.success !== false && r.data && r.data.order;
        if (!o) { state('missing', 'Comanda nu a fost găsită', 'Verifică emailul primit după plată.'); return; }
        show(o);
      }, function () {
        if (tries++ < 12) setTimeout(load, 5000);
        else state('missing', 'Nu am putut încărca comanda', 'Biletele îți vin pe email imediat ce plata e confirmată.');
      });
  }
  post({ type: 'bo-embed-top' });
  load();
})();
</script>
</body>
</html>
