/* bilete.online v2: the purchase conversion for the operator's ad pixels (ad tracking service, /organizator/servicii).
   thank-you.php loads the pixels of the order's operator right away (includes/tracking.php) and says where a Google
   Ads conversion goes (window.BO_ADS.google_ads = "AW-…/label"); thank-you.js calls BO_AdConversions.purchase(order)
   once the order shows as paid. Sent once per order and browser: Meta "Purchase" (event id purchase_{order id}, the
   same one the server-side Conversions API uses, so Meta counts it once), TikTok "CompletePayment", and the Google
   Ads conversion when a label is set. The pixels load with the visitor's consent state and honour it; nothing is
   sent here that they would not send anyway. */
(function () {
  'use strict';
  var WAIT_MS = 20000, STEP_MS = 400;

  function sentKey(id) { return 'bo_conv_' + id; }
  function wasSent(id) { try { return localStorage.getItem(sentKey(id)) === '1'; } catch (e) { return false; } }
  function markSent(id) { try { localStorage.setItem(sentKey(id), '1'); } catch (e) {} }

  function payload(order) {
    var items = Array.isArray(order.items) ? order.items : [];
    var ids = [], contents = [], qty = 0;
    items.forEach(function (it) {
      var id = it && it.product_id != null ? String(it.product_id) : '';
      var q = Math.max(1, parseInt(it && it.quantity, 10) || 1);
      qty += q;
      if (id) {
        if (ids.indexOf(id) === -1) ids.push(id);
        contents.push({ id: id, quantity: q, item_price: parseFloat(it.price) || 0 });
      }
    });
    return {
      id: String(order.id),
      value: Math.round((parseFloat(order.total) || 0) * 100) / 100,
      currency: String(order.currency || 'RON'),
      ids: ids,
      contents: contents,
      qty: qty || 1
    };
  }

  function send(p) {
    var ads = window.BO_ADS || {}, sent = false;
    if (typeof window.fbq === 'function') {
      window.fbq('track', 'Purchase', { value: p.value, currency: p.currency, content_ids: p.ids, contents: p.contents, content_type: 'product', num_items: p.qty }, { eventID: 'purchase_' + p.id });
      sent = true;
    }
    if (window.ttq && typeof window.ttq.track === 'function') {
      window.ttq.track('CompletePayment', { value: p.value, currency: p.currency, content_type: 'product', contents: p.contents.map(function (c) { return { content_id: c.id, quantity: c.quantity, price: c.item_price }; }) }, { event_id: 'purchase_' + p.id });
      sent = true;
    }
    if (ads.google_ads && typeof window.gtag === 'function') {
      window.gtag('event', 'conversion', { send_to: ads.google_ads, value: p.value, currency: p.currency, transaction_id: p.id });
      sent = true;
    }
    return sent;
  }

  window.BO_AdConversions = {
    purchase: function (order) {
      if (!order || order.id == null || wasSent(order.id)) return;
      var p = payload(order), waited = 0;
      // The pixel scripts are injected by v2/head.php; wait until at least one is there.
      (function tryNow() {
        var ready = typeof window.fbq === 'function' || (window.ttq && typeof window.ttq.track === 'function') || ((window.BO_ADS || {}).google_ads && typeof window.gtag === 'function');
        if (ready) {
          // A tick more so a pixel injected in the same batch is there too.
          setTimeout(function () { if (!wasSent(p.id) && send(p)) markSent(p.id); }, 300);
          return;
        }
        waited += STEP_MS;
        if (waited < WAIT_MS) setTimeout(tryNow, STEP_MS);
      })();
    }
  };
})();
