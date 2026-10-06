/*!
 * viaqui.com booking widget: the script for the operator's page (embed code v2), next to the widget iframe(s):
 *
 *   <iframe src="https://viaqui.com/embed/locatie/{slug}" ...></iframe>
 *   <script src="https://viaqui.com/embed/bo-widget.js" async></script>
 *
 * With it, the whole purchase happens on the operator's site: the checkout runs inside the widget, the customer
 * leaves the page only for the card page of the payment processor, and comes back to this same page, where the
 * widget shows the confirmation and the tickets.
 *
 * What it does, for iframes loaded from viaqui.com /embed/ only, and only on messages from viaqui.com:
 *  - sizes each widget to its content ({type: 'bo-embed-height'});
 *  - tells the widget the address of this page ({type: 'bo-embed-hello'}), which is where the customer returns;
 *  - sends this page to the card payment page ({type: 'bo-embed-pay'}), only to a known payment processor;
 *  - after the payment, reads #bo-back=… (set by viaqui.com /embed/retur) and shows the confirmation in the widget;
 *  - on a paid order, fires the window event "bileteonline:purchase" (detail: order, value, currency) and, when the
 *    page has them, the Meta Pixel "Purchase" and Google tag "purchase" events, once per order.
 */
(function () {
  'use strict';
  if (window.__boWidget) return;
  window.__boWidget = true;

  var script = document.currentScript;
  var SITE = 'https://viaqui.com';
  try { if (script && script.src) SITE = new URL(script.src).origin; } catch (e) {}
  var PREFIX = SITE + '/embed/';
  // Card pages the widget may send this page to (the processors viaqui.com works with), plus viaqui.com itself.
  var PAY_HOSTS = /(^|\.)(mobilpay\.ro|netopia-payments\.com|euplatesc\.ro|payu\.ro|stripe\.com)$/i;

  function frames() {
    return [].filter.call(document.querySelectorAll('iframe'), function (f) { return (f.getAttribute('src') || f.src || '').indexOf(PREFIX) === 0; });
  }
  function frameOf(win) {
    var list = frames();
    for (var i = 0; i < list.length; i++) if (list[i].contentWindow === win) return list[i];
    return null;
  }
  function here() { return location.href.split('#')[0]; }
  function hello(f) {
    try { f.contentWindow.postMessage({ type: 'bo-embed-hello', v: 2, href: here() }, SITE); } catch (e) {}
  }
  function payTo(d, f) {
    var url;
    try { url = new URL(String(d.url || '')); } catch (e) { return; }
    if (url.protocol !== 'https:' || !(PAY_HOSTS.test(url.hostname) || url.origin === SITE)) return;
    // A page whose security policy blocks it (form-action / navigate-to) cannot go there: the widget then offers a
    // button that opens the card page from inside the frame.
    document.addEventListener('securitypolicyviolation', function (ev) {
      if (/form-action|navigate-to/.test(ev.violatedDirective || ev.effectiveDirective || '')) {
        try { f.contentWindow.postMessage({ type: 'bo-embed-pay-blocked' }, SITE); } catch (e) {}
      }
    }, { once: true });
    if (String(d.method || '').toUpperCase() === 'POST' && d.fields && typeof d.fields === 'object') {
      var form = document.createElement('form');
      form.method = 'POST';
      form.action = url.href;
      form.style.display = 'none';
      Object.keys(d.fields).forEach(function (k) {
        var i = document.createElement('input');
        i.type = 'hidden';
        i.name = k;
        i.value = String(d.fields[k]);
        form.appendChild(i);
      });
      document.body.appendChild(form);
      form.submit();
    } else {
      location.assign(url.href);
    }
  }
  function purchased(d) {
    var id = String(d.order || '');
    if (!id) return;
    try { if (sessionStorage.getItem('bo_purchase_' + id)) return; sessionStorage.setItem('bo_purchase_' + id, '1'); } catch (e) {}
    var value = Number(d.value) || 0, currency = String(d.currency || 'RON');
    try { window.dispatchEvent(new CustomEvent('bileteonline:purchase', { detail: { order: id, value: value, currency: currency } })); } catch (e) {}
    try { if (typeof window.fbq === 'function') window.fbq('track', 'Purchase', { value: value, currency: currency }, { eventID: 'purchase_' + (d.order_id || id) }); } catch (e) {}
    try { if (typeof window.gtag === 'function') window.gtag('event', 'purchase', { transaction_id: id, value: value, currency: currency }); } catch (e) {}
  }

  window.addEventListener('message', function (e) {
    if (e.origin !== SITE) return;
    var d = e.data, f = frameOf(e.source);
    if (!f || !d || typeof d !== 'object') return;
    if (d.type === 'bo-embed-height') {
      var h = Number(d.height);
      if (h > 0) f.style.height = Math.min(Math.max(h, 200), 20000) + 'px';
      queue();
    } else if (d.type === 'bo-embed-ready') {
      hello(f);
    } else if (d.type === 'bo-embed-top') {
      // the widget moved to another step: bring its top into view
      var r = f.getBoundingClientRect();
      if (r.top < 0 || r.top > window.innerHeight * 0.6) window.scrollTo({ top: window.pageYOffset + r.top - 16, behavior: 'smooth' });
    } else if (d.type === 'bo-embed-pay') {
      payTo(d, f);
    } else if (d.type === 'bo-embed-purchase') {
      purchased(d);
    }
  });

  function start() {
    // Back from the payment: show the confirmation (or the checkout again, when cancelled) in the widget.
    var m = /[#&]bo-back=([^&]+)/.exec(location.hash || '');
    if (m) {
      var path = '';
      try { path = decodeURIComponent(m[1]); } catch (e) {}
      try { history.replaceState(null, '', location.pathname + location.search); } catch (e) {}
      var first = frames()[0];
      if (first && /^\/embed\/(confirmare|finalizare)\?[^#]*$/.test(path)) {
        first.setAttribute('loading', 'eager');
        first.src = SITE + path;
        setTimeout(function () { try { first.scrollIntoView({ block: 'start' }); } catch (e) {} }, 60);
      }
    }
    frames().forEach(function (f) {
      f.addEventListener('load', function () { hello(f); viewport(); });
      hello(f);
    });
    window.addEventListener('scroll', queue, { passive: true });
    window.addEventListener('resize', queue);
    viewport();
  }

  // Where each widget sits in the window, so a part of it can stay in view while this page scrolls (the checkout's
  // summary): the frame is as tall as its content and never scrolls itself. data-offset on the script: the height of
  // a fixed header on this page, in px (default 16).
  var OFFSET = Math.max(0, parseInt(script && script.getAttribute('data-offset'), 10) || 16), ticking = false;
  function viewport() {
    ticking = false;
    frames().forEach(function (f) {
      var r = f.getBoundingClientRect();
      try { f.contentWindow.postMessage({ type: 'bo-embed-viewport', top: r.top, height: window.innerHeight, offset: OFFSET }, SITE); } catch (e) {}
    });
  }
  function queue() {
    if (ticking) return;
    ticking = true;
    (window.requestAnimationFrame || setTimeout)(viewport);
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start);
  else start();
})();
