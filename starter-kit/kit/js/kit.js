/* ==========================================================================
   kit.js — client runtime shared by every kit template.

   Responsibilities:
     1. Cart (localStorage) — one implementation for all templates.
     2. Alpine components used by SSR component markup (ticket-selector).
     3. Vanilla hydration of [data-component] mounts (seat-map) over the site's
        api/proxy.php — the same proxy pattern teatru/ambilet already ship.

   The proxy endpoint and cart key are read from window.KIT (set by the layout
   if you want to override them); sensible defaults are used otherwise.
   ========================================================================== */
(function () {
  'use strict';

  var CFG = window.KIT || {};
  var PROXY   = CFG.proxy   || '/api/proxy.php';
  var CARTKEY = CFG.cartKey || 'kit_cart';
  var CURRENCY = CFG.currency || 'RON';

  /* ---- cart ------------------------------------------------------------- */
  var Cart = {
    all:  function () { try { return JSON.parse(localStorage.getItem(CARTKEY)) || []; } catch (e) { return []; } },
    save: function (items) { localStorage.setItem(CARTKEY, JSON.stringify(items)); window.dispatchEvent(new CustomEvent('kit:cart', { detail: items })); },
    add:  function (line) { var it = this.all(); it.push(line); this.save(it); },
    count: function () { return this.all().reduce(function (n, l) { return n + (l.qty || l.seats && l.seats.length || 1); }, 0); }
  };
  window.KitCart = Cart;

  function fmt(v) {
    if (v == null || v === '') return '';
    var n = Number(v);
    return (Number.isInteger(n) ? n : n.toFixed(2)).toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.') + ' ' + CURRENCY;
  }

  /* ---- Alpine: ticket-selector ----------------------------------------- */
  window.kitTicketSelector = function (data) {
    return {
      types: data.types || [],
      qty: {},
      fmt: fmt,
      inc: function (i) { this.qty[i] = (this.qty[i] || 0) + 1; },
      dec: function (i) { this.qty[i] = Math.max(0, (this.qty[i] || 0) - 1); },
      total: function () {
        var t = 0, self = this;
        this.types.forEach(function (ty, i) { t += (self.qty[i] || 0) * Number(ty.price || 0); });
        return t;
      },
      addToCart: function () {
        var self = this, lines = [];
        this.types.forEach(function (ty, i) {
          var q = self.qty[i] || 0;
          if (q > 0) lines.push({ event_id: data.event_id, title: data.title, url: data.url, image: data.image, ticket: ty.name, price: Number(ty.price), qty: q, currency: data.currency });
        });
        if (!lines.length) return;
        lines.forEach(function (l) { Cart.add(l); });
        window.location.href = CFG.cartUrl || '/cos';
      }
    };
  };

  /* ---- proxy helper ----------------------------------------------------- */
  function proxy(action, params, opts) {
    opts = opts || {};
    var url = PROXY + '?action=' + encodeURIComponent(action);
    Object.keys(params || {}).forEach(function (k) { url += '&' + k + '=' + encodeURIComponent(params[k]); });
    return fetch(url, {
      method: opts.method || 'GET',
      headers: { 'Accept': 'application/json' },
      credentials: 'include',
      body: opts.body ? JSON.stringify(opts.body) : undefined
    }).then(function (r) { return r.json(); });
  }
  window.KitProxy = proxy;

  /* ---- vanilla: seat-map hydration ------------------------------------- */
  function hydrateSeatMap(el) {
    var eventId  = el.getAttribute('data-event-id');
    var canvas   = el.querySelector('[data-seatmap-canvas]');
    var summary  = el.querySelector('[data-seatmap-summary]');
    var countEl  = el.querySelector('[data-seatmap-count]');
    var totalEl  = el.querySelector('[data-seatmap-total]');
    var selected = {};

    proxy('seating', { event: eventId }).then(function (res) {
      var seats = (res && res.data && (res.data.seats || res.data)) || [];
      if (!Array.isArray(seats) || !seats.length) {
        canvas.innerHTML = '<p class="kit-muted" style="text-align:center;padding:2rem">Harta locurilor nu este disponibilă.</p>';
        return;
      }
      canvas.innerHTML = '';
      var grid = document.createElement('div');
      grid.style.cssText = 'display:flex;flex-wrap:wrap;gap:.3rem;justify-content:center';
      seats.forEach(function (s) {
        var b = document.createElement('button');
        b.className = 'kit-seat';
        b.type = 'button';
        b.setAttribute('data-status', s.status || 'free');
        b.title = (s.row_label || '') + (s.seat_label || '');
        if ((s.status || 'free') === 'free') {
          b.addEventListener('click', function () { toggle(s, b); });
        }
        grid.appendChild(b);
      });
      canvas.appendChild(grid);
    }).catch(function () {
      canvas.innerHTML = '<p class="kit-muted" style="text-align:center;padding:2rem">Eroare la încărcarea locurilor.</p>';
    });

    function toggle(seat, btn) {
      var uid = seat.seat_uid || (seat.row_label + seat.seat_label);
      if (selected[uid]) { delete selected[uid]; btn.classList.remove('is-selected'); }
      else { selected[uid] = seat; btn.classList.add('is-selected'); }
      render();
    }
    function render() {
      var keys = Object.keys(selected);
      var total = keys.reduce(function (t, k) { return t + Number((selected[k].price_cents || 0) / 100 || selected[k].price || 0); }, 0);
      summary.hidden = keys.length === 0;
      countEl.textContent = keys.length + ' ' + (keys.length === 1 ? 'loc' : 'locuri');
      totalEl.textContent = fmt(total);
    }
  }

  var HYDRATORS = { 'seat-map': hydrateSeatMap };

  /* ---- graceful image fallback (no network) ----------------------------
     If a poster/hero fails to load, swap it for an inline SVG tile using the
     theme's primary colour + the image's alt text. Keeps cards intact when a
     storage asset 404s (and makes offline previews look right). */
  function imgFallback(img) {
    if (img.dataset.kitFallback) return;
    img.dataset.kitFallback = '1';
    var cs = getComputedStyle(document.documentElement);
    var c1 = (cs.getPropertyValue('--kit-primary') || '#888').trim();
    var c2 = (cs.getPropertyValue('--kit-primary-dark') || '#555').trim();
    var label = (img.getAttribute('alt') || '').slice(0, 24);
    var svg = '<svg xmlns="http://www.w3.org/2000/svg" width="600" height="800">' +
      '<defs><linearGradient id="g" x1="0" y1="0" x2="1" y2="1">' +
      '<stop offset="0" stop-color="' + c1 + '"/><stop offset="1" stop-color="' + c2 + '"/></linearGradient></defs>' +
      '<rect width="600" height="800" fill="url(#g)"/>' +
      '<text x="300" y="410" fill="rgba(255,255,255,.9)" font-size="34" font-family="sans-serif" ' +
      'text-anchor="middle">' + label.replace(/[<&>]/g, '') + '</text></svg>';
    img.src = 'data:image/svg+xml;utf8,' + encodeURIComponent(svg);
  }

  function boot() {
    document.querySelectorAll('[data-component]').forEach(function (el) {
      var h = HYDRATORS[el.getAttribute('data-component')];
      if (h) h(el);
    });
    document.querySelectorAll('img').forEach(function (img) {
      if (img.complete && img.naturalWidth === 0) imgFallback(img);
      img.addEventListener('error', function () { imgFallback(img); });
    });
  }
  if (document.readyState !== 'loading') boot();
  else document.addEventListener('DOMContentLoaded', boot);
})();
