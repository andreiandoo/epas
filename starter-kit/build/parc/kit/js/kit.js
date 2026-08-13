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
          if (q > 0) lines.push({ event_id: data.event_id, ticket_type_id: ty.id, title: data.title, url: data.url, image: data.image, ticket: ty.name, price: Number(ty.price), qty: q, currency: data.currency });
        });
        if (!lines.length) return;
        lines.forEach(function (l) { Cart.add(l); });
        window.location.href = CFG.cartUrl || '/cos';
      }
    };
  };

  /* ---- auth (localStorage) --------------------------------------------- */
  var AUTHKEY = CFG.authKey || 'kit_auth';
  var Auth = {
    get: function () { try { return JSON.parse(localStorage.getItem(AUTHKEY)) || null; } catch (e) { return null; } },
    set: function (blob) { localStorage.setItem(AUTHKEY, JSON.stringify(blob)); },
    clear: function () { localStorage.removeItem(AUTHKEY); },
    token: function () { var a = this.get(); return a && a.token; }
  };
  window.KitAuth = Auth;

  window.kitAuthWidget = function () {
    return {
      user: null, firstName: '', initials: '',
      init: function () {
        var a = Auth.get();
        if (a && a.user) {
          var u = a.user;
          this.user = u;
          var fn = (u.first_name || u.firstName || (u.name || '').trim().split(/\s+/)[0] || '').trim();
          this.firstName = fn || 'Cont';
          var full = (u.name || ((u.first_name || '') + ' ' + (u.last_name || ''))).trim() || u.email || '?';
          this.initials = (full.split(/\s+/).map(function (p) { return p[0]; }).slice(0, 2).join('') || '?').toUpperCase();
        }
      }
    };
  };

  /* Account shell: gate a cont/* page on auth; redirect to login if absent.
     `demo` (dev preview with fixtures) skips the gate so the area is viewable. */
  window.kitAccountShell = function (loginUrl, demo) {
    return {
      ready: false,
      init: function () {
        if (!demo && !Auth.token()) { window.location.href = (loginUrl || '/autentificare') + '?next=' + encodeURIComponent(location.pathname); return; }
        this.ready = true;
      },
      logout: function () { proxy('logout', {}, { method: 'POST' }).finally(function () { Auth.clear(); window.location.href = '/'; }); }
    };
  };

  /* ---- Alpine: favorites ------------------------------------------------ */
  var FAVKEY = 'kit_favs';
  function favAll() { try { return JSON.parse(localStorage.getItem(FAVKEY)) || {}; } catch (e) { return {}; } }
  function favKey(type, id) { return type + ':' + id; }
  window.KitFavorites = { has: function (t, id) { return !!favAll()[favKey(t, id)]; },
    ids: function (t) { return Object.keys(favAll()).filter(function (k) { return k.indexOf(t + ':') === 0; }).map(function (k) { return k.split(':')[1]; }); } };
  window.kitFav = function (type, id, labels) {
    return {
      active: false, L: labels || { add: '', remove: '' },
      init: function () { this.active = !!favAll()[favKey(type, id)]; },
      toggle: function () {
        this.active = !this.active;
        var all = favAll(); var k = favKey(type, id);
        if (this.active) all[k] = 1; else delete all[k];
        try { localStorage.setItem(FAVKEY, JSON.stringify(all)); } catch (e) {}
        if (window.KitAuth && KitAuth.token()) proxy('fav-toggle', {}, { method: 'POST', body: { type: type, id: id, active: this.active } });
      }
    };
  };

  /* ---- Alpine: calendar ------------------------------------------------- */
  window.kitCalendar = function (events, firstDate) {
    var f = new Date(firstDate || Date.now());
    return {
      events: events || [], selected: null,
      month: f.getMonth(), year: f.getFullYear(),
      monthNames: ['Ianuarie','Februarie','Martie','Aprilie','Mai','Iunie','Iulie','August','Septembrie','Octombrie','Noiembrie','Decembrie'],
      get cells() {
        var first = new Date(this.year, this.month, 1);
        var last = new Date(this.year, this.month + 1, 0);
        var pad = (first.getDay() + 6) % 7, out = [], self = this;
        for (var i = 0; i < pad; i++) out.push({ key: 'p' + i, day: 0, date: null });
        for (var d = 1; d <= last.getDate(); d++) {
          var ds = self.year + '-' + String(self.month + 1).padStart(2, '0') + '-' + String(d).padStart(2, '0');
          out.push({ key: ds, day: d, date: ds, hasEvent: self.events.some(function (e) { return e.date === ds; }) });
        }
        return out;
      },
      get filtered() {
        var self = this;
        if (this.selected) return this.events.filter(function (e) { return e.date === self.selected; });
        return this.events.filter(function (e) { var d = new Date(e.date); return d.getMonth() === self.month && d.getFullYear() === self.year; });
      },
      prev() { if (this.month === 0) { this.month = 11; this.year--; } else this.month--; this.selected = null; },
      next() { if (this.month === 11) { this.month = 0; this.year++; } else this.month++; this.selected = null; },
      select(d) { this.selected = this.selected === d ? null : d; }
    };
  };

  /* ---- QR modal --------------------------------------------------------- */
  window.kitQR = {
    show: function (code, title) {
      var m = document.getElementById('kit-qr'); if (!m) return;
      document.getElementById('kit-qr-title').textContent = title || 'Bilet';
      document.getElementById('kit-qr-code').textContent = code;
      var c = document.getElementById('kit-qr-canvas'); c.innerHTML = '';
      if (window.QRCode) { new window.QRCode(c, { text: String(code), width: 200, height: 200 }); }
      else { c.innerHTML = '<div class="kit-qr__fallback">' + String(code) + '</div>'; }
      m.hidden = false;
    },
    hide: function () { var m = document.getElementById('kit-qr'); if (m) m.hidden = true; }
  };

  /* ---- newsletter (Alpine) --------------------------------------------- */
  window.kitNewsletter = function () {
    return {
      email: '', busy: false, done: false,
      async submit() {
        if (!this.email) return; this.busy = true;
        try {
          var r = await proxy('newsletter', {}, { method: 'POST', body: { email: this.email } });
          if (r && r.success === false) return;   // profile has no endpoint, or upstream refused
          this.done = true; this.email = '';
        }
        catch (e) {} finally { this.busy = false; }
      }
    };
  };

  /* ---- cookie consent + consent-gated analytics ------------------------- */
  var CONSENT_KEY = 'kit_consent';
  function consentState() { try { return localStorage.getItem(CONSENT_KEY); } catch (e) { return null; } }
  function loadAnalytics() {
    var a = window.KIT_ANALYTICS || {};
    if (a.gtm) { var g = document.createElement('script'); g.async = true; g.src = 'https://www.googletagmanager.com/gtm.js?id=' + a.gtm; document.head.appendChild(g);
      window.dataLayer = window.dataLayer || []; window.dataLayer.push({ 'gtm.start': +new Date(), event: 'gtm.js' }); }
    if (a.ga4) { var s = document.createElement('script'); s.async = true; s.src = 'https://www.googletagmanager.com/gtag/js?id=' + a.ga4; document.head.appendChild(s);
      window.dataLayer = window.dataLayer || []; window.gtag = function () { dataLayer.push(arguments); }; gtag('js', new Date()); gtag('config', a.ga4); }
    if (a.meta) { !function (f, b, e, v, n, t, s) { if (f.fbq) return; n = f.fbq = function () { n.callMethod ? n.callMethod.apply(n, arguments) : n.queue.push(arguments); };
      if (!f._fbq) f._fbq = n; n.push = n; n.loaded = !0; n.version = '2.0'; n.queue = []; t = b.createElement(e); t.async = !0; t.src = v; s = b.getElementsByTagName(e)[0]; s.parentNode.insertBefore(t, s); }(window, document, 'script', 'https://connect.facebook.net/en_US/fbevents.js');
      window.fbq('init', a.meta); window.fbq('track', 'PageView'); }
  }
  window.kitConsent = function () {
    return {
      show: false,
      init() {
        var required = window.KIT_CONSENT_REQUIRED !== false;
        var st = consentState();
        if (!required || st === 'accepted') { loadAnalytics(); this.show = false; return; }
        this.show = st === null; // hidden once a choice is stored
      },
      accept() { try { localStorage.setItem(CONSENT_KEY, 'accepted'); } catch (e) {} loadAnalytics(); this.show = false; },
      reject() { try { localStorage.setItem(CONSENT_KEY, 'rejected'); } catch (e) {} this.show = false; }
    };
  };

  /* ---- proxy helper ----------------------------------------------------- */
  function proxy(action, params, opts) {
    opts = opts || {};
    var url = PROXY + '?action=' + encodeURIComponent(action);
    Object.keys(params || {}).forEach(function (k) { url += '&' + k + '=' + encodeURIComponent(params[k]); });
    if (CFG.locale && !(params && params.locale)) url += '&locale=' + encodeURIComponent(CFG.locale);
    var headers = { 'Accept': 'application/json' };
    // opts.token overrides the customer token — the operator area carries its
    // own identity and must never ride on a visitor's session, or vice versa.
    var tok = opts.token !== undefined
      ? opts.token
      : (window.KitAuth && window.KitAuth.token && window.KitAuth.token());
    if (tok) headers['Authorization'] = 'Bearer ' + tok;
    if (opts.body) headers['Content-Type'] = 'application/json';
    return fetch(url, {
      method: opts.method || 'GET',
      headers: headers,
      credentials: 'include',
      body: opts.body ? JSON.stringify(opts.body) : undefined
    }).then(function (r) { return r.json(); });
  }
  window.KitProxy = proxy;

  /* ---- operator area ----------------------------------------------------
     Venue staff working the counter, on the venue's OWN site. Deliberately a
     separate identity from the customer session: its own localStorage key and
     its own token, so signing a visitor in or out never touches the till.
     Backed by /tenant-client/operator/* via the op-* proxy actions. */
  var OPKEY = CFG.operatorKey || 'kit_operator';
  var Operator = {
    get: function () { try { return JSON.parse(localStorage.getItem(OPKEY)) || null; } catch (e) { return null; } },
    set: function (b) { localStorage.setItem(OPKEY, JSON.stringify(b)); },
    clear: function () { localStorage.removeItem(OPKEY); },
    token: function () { var a = this.get(); return (a && a.token) || null; },
    profile: function () { var a = this.get(); return (a && a.operator) || null; },
    can: function (what) { var p = this.profile(); return !!(p && p.can && p.can[what]); },
    /** Every operator call goes through here so the token is never forgotten. */
    api: function (action, params, opts) {
      opts = Object.assign({}, opts || {}, { token: Operator.token() });
      return proxy(action, params, opts);
    }
  };
  window.KitOperator = Operator;

  /** Login screen. On success stores {token, operator} and enters the panel. */
  window.kitOperatorLogin = function (homeUrl) {
    return {
      email: '', password: '', busy: false, error: '',
      async submit() {
        if (this.busy) return;
        this.error = ''; this.busy = true;
        try {
          var r = await proxy('op-login', {}, { method: 'POST', token: null,
            body: { email: this.email, password: this.password } });
          var d = (r && r.data) || {};
          if (d.token) { Operator.set({ token: d.token, operator: d.operator || {} });
            window.location.href = homeUrl || '/operator'; return; }
          this.error = (r && r.error) || 'Date de autentificare incorecte.';
        } catch (e) { this.error = 'Eroare de conexiune.'; }
        finally { this.busy = false; }
      }
    };
  };

  /**
   * Shell for every operator page: gates on a token, re-validates it against
   * the server (a token deleted server-side must not leave a usable panel), and
   * exposes the capability flags the nav hides items with.
   */
  window.kitOperatorShell = function (loginUrl, required) {
    return {
      ready: false, op: null,
      async init() {
        if (!Operator.token()) { window.location.href = loginUrl || '/operator/login'; return; }
        try {
          var r = await Operator.api('op-me');
          if (!r || r.success === false || !r.data) throw new Error('unauthenticated');
          this.op = r.data;
          Operator.set({ token: Operator.token(), operator: r.data });
        } catch (e) {
          Operator.clear(); window.location.href = loginUrl || '/operator/login'; return;
        }
        if (required && !(this.op.can && this.op.can[required])) {
          this.denied = true; this.ready = true; return;
        }
        this.ready = true;
      },
      denied: false,
      logout() {
        Operator.api('op-logout', {}, { method: 'POST' })
          .finally(function () { Operator.clear(); window.location.href = loginUrl || '/operator/login'; });
      }
    };
  };

  /* ---- vanilla: seat-map hydration with LIVE holds ---------------------
     Contract with /api/public (forwarded by the site proxy). Verified against
     App\Http\Controllers\Api\PublicApi\SeatingController:
       GET  seats?event=ID   → { event_seating_id, seats:[ { seat_uid, row_label,
              seat_label, status, price_cents, price_tier_id, section_name } ], total }
              (`seating` returns the GEOMETRY — canvas/sections/price_tiers — and
               carries NO seats, so the flat map loads from `seats`.)
       POST   hold    { event_seating_id, seat_uids: [uid] } → { held, failed, expires_at }
       DELETE release { event_seating_id, seat_uids: [uid] }
     Seat status values are available | held | sold | blocked | disabled.
     Selecting an available seat holds it; the hold timer (default 10 min) counts
     down; "Continuă" writes the held seats to the cart and goes to checkout. */
  function hydrateSeatMap(el) {
    var eventId  = el.getAttribute('data-event-id');
    var checkout = el.getAttribute('data-checkout') || (CFG.cartUrl || '/cos');
    var canvas   = el.querySelector('[data-seatmap-canvas]');
    var summary  = el.querySelector('[data-seatmap-summary]');
    var countEl  = el.querySelector('[data-seatmap-count]');
    var totalEl  = el.querySelector('[data-seatmap-total]');
    var contBtn  = el.querySelector('[data-seatmap-continue]');
    var timerEl  = document.createElement('span');
    timerEl.className = 'kit-seatmap__timer';
    if (summary) summary.insertBefore(timerEl, summary.firstChild);

    var selected = {};     // uid → seat
    var esid = null;       // event_seating_id
    var deadline = 0, ticking = null;
    var meta = { title: el.getAttribute('data-title') || 'Bilet', image: el.getAttribute('data-image') || '' };

    function seatFree(s) { var st = s.status || 'available'; return st === 'available' || st === 'free'; }

    proxy('seats', { event: eventId, limit: 5000 }).then(function (res) {
      var d = (res && res.data) || res || {};
      esid = d.event_seating_id || d.id || null;
      var seats = d.seats || (Array.isArray(d) ? d : []);
      if (!Array.isArray(seats) || !seats.length) {
        canvas.innerHTML = '<p class="kit-muted" style="text-align:center;padding:2rem">Harta locurilor nu este disponibilă.</p>';
        return;
      }
      canvas.innerHTML = '';
      var grid = document.createElement('div');
      grid.style.cssText = 'display:flex;flex-wrap:wrap;gap:.3rem;justify-content:center';
      seats.forEach(function (s) {
        var b = document.createElement('button');
        b.className = 'kit-seat'; b.type = 'button';
        b.setAttribute('data-status', seatFree(s) ? 'free' : (s.status === 'held' ? 'held' : 'sold'));
        b.title = (s.section_name ? s.section_name + ' · ' : '') + (s.row_label || '') + (s.seat_label || '');
        if (seatFree(s)) b.addEventListener('click', function () { toggle(s, b); });
        grid.appendChild(b);
      });
      canvas.appendChild(grid);
    }).catch(function () {
      canvas.innerHTML = '<p class="kit-muted" style="text-align:center;padding:2rem">Eroare la încărcarea locurilor.</p>';
    });

    function toggle(seat, btn) {
      var uid = seat.seat_uid || (seat.row_label + seat.seat_label);
      btn.disabled = true;
      if (selected[uid]) {
        proxy('release', {}, { method: 'DELETE', body: { event_seating_id: esid, seat_uids: [uid] } })
          .finally(function () { delete selected[uid]; btn.classList.remove('is-selected'); btn.disabled = false; render(); });
      } else {
        proxy('hold', {}, { method: 'POST', body: { event_seating_id: esid, seat_uids: [uid] } })
          .then(function (r) {
            // holdSeats answers { held:[…], failed:[{seat_uid,reason}] }, HTTP 409
            // when it could hold nothing.
            var held = r && (r.held || (r.data && r.data.held));
            if ((r && r.success === false) || (held && !held.length)) {
              alert('Locul nu mai este disponibil.');
              btn.setAttribute('data-status', 'held'); return;
            }
            selected[uid] = seat; btn.classList.add('is-selected'); startTimer();
          })
          .catch(function () { alert('Eroare la rezervarea locului.'); })
          .finally(function () { btn.disabled = false; render(); });
      }
    }
    function startTimer() {
      if (deadline) return;
      var mins = Number(CFG.holdMinutes || 10);
      deadline = Date.now() + mins * 60000;
      ticking = setInterval(tick, 1000); tick();
    }
    function tick() {
      var left = Math.max(0, deadline - Date.now());
      if (left <= 0) { clearInterval(ticking); ticking = null; deadline = 0; selected = {};
        el.querySelectorAll('.kit-seat.is-selected').forEach(function (b) { b.classList.remove('is-selected'); });
        alert('Rezervarea a expirat. Te rugăm să reîncerci.'); render(); return; }
      var m = Math.floor(left / 60000), s = Math.floor((left % 60000) / 1000);
      timerEl.textContent = '⏱ ' + m + ':' + (s < 10 ? '0' : '') + s;
    }
    function render() {
      var keys = Object.keys(selected);
      var total = keys.reduce(function (t, k) { return t + seatPrice(selected[k]); }, 0);
      if (summary) summary.hidden = keys.length === 0;
      if (countEl) countEl.textContent = keys.length + ' ' + (keys.length === 1 ? 'loc' : 'locuri');
      if (totalEl) totalEl.textContent = fmt(total);
      if (!keys.length && ticking) { clearInterval(ticking); ticking = null; deadline = 0; timerEl.textContent = ''; }
    }
    function seatPrice(s) { return Number(s.price_cents != null ? s.price_cents / 100 : (s.price || 0)); }

    if (contBtn) contBtn.addEventListener('click', function (ev) {
      ev.preventDefault();
      var seats = Object.keys(selected).map(function (uid) {
        return { seat_uid: uid, label: (selected[uid].row_label || '') + (selected[uid].seat_label || ''), price: seatPrice(selected[uid]) };
      });
      if (!seats.length) return;
      Cart.add({ event_id: eventId, event_seating_id: esid, title: meta.title, image: meta.image,
        url: location.pathname, seats: seats, price: seats.reduce(function (t, s) { return t + s.price; }, 0), currency: CURRENCY });
      window.location.href = checkout;
    });
  }

  /* ---- vanilla: leisure booking widget --------------------------------
     The leisure counterpart of the seat map. Contract with the proxy:
       GET availability?ticket_type=&month=YYYY-MM
           → { data: { dates: { 'YYYY-MM-DD': {status, remaining,
                                min_price_cents, slot_count} } } }
       GET slots?ticket_type=&date=YYYY-MM-DD
           → { data: { slots: [{id, time_slot_start, time_slot_end,
                                status, remaining, price_cents}] } }
     Nothing is held while browsing: a leisure place is taken at checkout, under
     a row lock, using the capacity_id carried on the cart line. That is why the
     cart line must keep capacity_id / visit_date / slot_time / duration_minutes.
     Day statuses: available | limited | sold_out | closed | unavailable. */
  function hydrateBooking(el) {
    var types = [];
    try { types = JSON.parse(el.getAttribute('data-bookables') || '[]'); } catch (e) {}
    if (!types.length) return;

    var typeSel  = el.querySelector('[data-booking-type]');
    var monthEl  = el.querySelector('[data-booking-month]');
    var daysEl   = el.querySelector('[data-booking-days]');
    var slotsWrap= el.querySelector('[data-booking-slots-wrap]');
    var slotsEl  = el.querySelector('[data-booking-slots]');
    var varsWrap = el.querySelector('[data-booking-variants-wrap]');
    var varsEl   = el.querySelector('[data-booking-variants]');
    var qtyEl    = el.querySelector('[data-booking-qty]');
    var totalEl  = el.querySelector('[data-booking-total]');
    var errEl    = el.querySelector('[data-booking-error]');
    var addBtn   = el.querySelector('[data-booking-add]');

    var MONTHS = ['Ianuarie','Februarie','Martie','Aprilie','Mai','Iunie',
                  'Iulie','August','Septembrie','Octombrie','Noiembrie','Decembrie'];
    var now = new Date();
    var state = {
      type: types[0], month: now.getMonth(), year: now.getFullYear(),
      dates: {}, date: null, slot: null, variant: null, qty: 1
    };

    function typeById(id) {
      for (var i = 0; i < types.length; i++) if (String(types[i].id) === String(id)) return types[i];
      return types[0];
    }
    function ymd(y, m, d) {
      return y + '-' + String(m + 1).padStart(2, '0') + '-' + String(d).padStart(2, '0');
    }
    function fail(msg) { if (!errEl) return; errEl.textContent = msg || ''; errEl.hidden = !msg; }

    function loadMonth() {
      fail('');
      var key = state.year + '-' + String(state.month + 1).padStart(2, '0');
      if (monthEl) monthEl.textContent = MONTHS[state.month] + ' ' + state.year;
      daysEl.innerHTML = '<p class="kit-muted">…</p>';
      proxy('availability', { ticket_type: state.type.id, month: key }).then(function (r) {
        state.dates = (r && r.data && r.data.dates) || {};
        renderDays();
      }).catch(function () {
        state.dates = {}; renderDays();
        fail('Nu am putut încărca disponibilitatea.');
      });
    }

    function renderDays() {
      daysEl.innerHTML = '';
      var first = new Date(state.year, state.month, 1);
      var last  = new Date(state.year, state.month + 1, 0);
      var pad   = (first.getDay() + 6) % 7;               // week starts Monday
      for (var p = 0; p < pad; p++) daysEl.appendChild(document.createElement('span'));
      for (var d = 1; d <= last.getDate(); d++) {
        var ds  = ymd(state.year, state.month, d);
        var inf = state.dates[ds];
        var st  = inf ? inf.status : 'unavailable';
        var b = document.createElement('button');
        b.type = 'button'; b.className = 'kit-booking__day';
        b.setAttribute('data-status', st);
        b.textContent = d;
        if (inf && inf.min_price_cents != null) {
          var tag = document.createElement('small');
          tag.textContent = Math.round(inf.min_price_cents / 100);
          b.appendChild(tag);
        }
        if (st === 'available' || st === 'limited') {
          (function (ds2, btn) {
            btn.addEventListener('click', function () { pickDate(ds2, btn); });
          })(ds, b);
        } else { b.disabled = true; }
        daysEl.appendChild(b);
      }
    }

    function pickDate(ds, btn) {
      state.date = ds; state.slot = null;
      el.querySelectorAll('.kit-booking__day.is-selected')
        .forEach(function (x) { x.classList.remove('is-selected'); });
      btn.classList.add('is-selected');
      slotsEl.innerHTML = '<p class="kit-muted">…</p>';
      slotsWrap.hidden = false;
      proxy('slots', { ticket_type: state.type.id, date: ds }).then(function (r) {
        renderSlots((r && r.data && r.data.slots) || []);
      }).catch(function () { renderSlots([]); });
      renderVariants();
      render();
    }

    function renderSlots(list) {
      slotsEl.innerHTML = '';
      // A venue without time slots still returns one capacity row for the day;
      // treat it as a single implicit "whole day" option.
      if (!list.length) { slotsWrap.hidden = true; state.slot = null; render(); return; }
      slotsWrap.hidden = false;
      list.forEach(function (s) {
        var b = document.createElement('button');
        b.type = 'button'; b.className = 'kit-booking__chip';
        b.textContent = s.time_slot_start
          ? (s.time_slot_start + (s.time_slot_end ? '–' + s.time_slot_end : ''))
          : 'Toată ziua';
        if (s.status !== 'available' && s.status !== 'limited') { b.disabled = true; }
        b.addEventListener('click', function () {
          state.slot = s;
          el.querySelectorAll('[data-booking-slots] .is-selected')
            .forEach(function (x) { x.classList.remove('is-selected'); });
          b.classList.add('is-selected');
          render();
        });
        slotsEl.appendChild(b);
      });
      if (list.length === 1 && !list[0].time_slot_start) {
        state.slot = list[0];
        slotsEl.firstChild.classList.add('is-selected');
      }
      render();
    }

    function renderVariants() {
      var vs = state.type.variants || [];
      varsEl.innerHTML = ''; state.variant = null;
      if (!vs.length) { varsWrap.hidden = true; return; }
      varsWrap.hidden = false;
      vs.forEach(function (v, i) {
        var b = document.createElement('button');
        b.type = 'button'; b.className = 'kit-booking__chip';
        b.textContent = v.label + ' · ' + fmt(v.price);
        b.addEventListener('click', function () {
          state.variant = v;
          el.querySelectorAll('[data-booking-variants] .is-selected')
            .forEach(function (x) { x.classList.remove('is-selected'); });
          b.classList.add('is-selected');
          render();
        });
        varsEl.appendChild(b);
        if (i === 0) { state.variant = v; b.classList.add('is-selected'); }
      });
    }

    function unitPrice() {
      if (state.variant && state.variant.price != null) return Number(state.variant.price);
      if (state.slot && state.slot.price_cents != null) return state.slot.price_cents / 100;
      return Number(state.type.price || 0);
    }
    function render() {
      if (qtyEl) qtyEl.textContent = state.qty;
      var ok = !!state.date && (slotsWrap.hidden || !!state.slot);
      if (totalEl) totalEl.textContent = ok ? fmt(unitPrice() * state.qty) : '—';
      if (addBtn) addBtn.disabled = !ok;
    }

    if (typeSel) typeSel.addEventListener('change', function () {
      state.type = typeById(typeSel.value);
      state.date = null; state.slot = null;
      slotsWrap.hidden = true; renderVariants(); loadMonth(); render();
    });
    el.querySelector('[data-booking-prev]').addEventListener('click', function () {
      if (state.month === 0) { state.month = 11; state.year--; } else state.month--;
      loadMonth();
    });
    el.querySelector('[data-booking-next]').addEventListener('click', function () {
      if (state.month === 11) { state.month = 0; state.year++; } else state.month++;
      loadMonth();
    });
    el.querySelector('[data-booking-minus]').addEventListener('click', function () {
      state.qty = Math.max(1, state.qty - 1); render();
    });
    el.querySelector('[data-booking-plus]').addEventListener('click', function () {
      state.qty = state.qty + 1; render();
    });

    if (addBtn) addBtn.addEventListener('click', function () {
      if (addBtn.disabled) return;
      Cart.add({
        event_id: state.type.event_id,
        ticket_type_id: state.type.id,
        capacity_id: state.slot ? state.slot.id : null,
        visit_date: state.date,
        slot_time: state.slot ? state.slot.time_slot_start : null,
        duration_minutes: state.variant ? state.variant.minutes : null,
        title: state.type.title,
        ticket: state.type.name + (state.variant ? ' · ' + state.variant.label : ''),
        url: location.pathname,
        price: unitPrice(),
        qty: state.qty,
        currency: state.type.currency || CURRENCY
      });
      window.location.href = CFG.cartUrl || '/cos';
    });

    renderVariants();
    loadMonth();
    render();
  }

  var HYDRATORS = { 'seat-map': hydrateSeatMap, 'booking': hydrateBooking };

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
