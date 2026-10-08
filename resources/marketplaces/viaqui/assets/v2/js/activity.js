/* viaqui.com v2: activity page. The booking widget (date, calendar, slots, variants, estimate) hands the choice
   to BileteOnlineCart.addActivityItem (assets/js/cart.js) exactly as the previous page did, then goes to /cart or
   /checkout. Plus the photo gallery.
   Prices are written in the activity's own currency (variant.currency, else the page's booking.currency, else the
   site's), the one the customer is charged in; the same code goes to the basket with each line. */
(function () {
  'use strict';

  /* One formatter for every price on the page. assets/js/utils.js is not loaded here, so the rules of
     BileteOnlineUtils.formatCurrency are repeated (same table): [sign, sign before the amount?, decimals when not whole]. */
  var CURRENCIES = {
    EUR: ['€', true, 2], GBP: ['£', true, 2], CHF: ['CHF ', true, 2], CZK: [' Kč', false, 0], PLN: [' zł', false, 0],
    HUF: [' Ft', false, 0], RON: [' lei', false, 2], SEK: [' kr', false, 0], NOK: [' kr', false, 0], DKK: [' kr', false, 0],
    ISK: [' kr', false, 0]
  };
  function formatMoney(amount, currency) {
    var code = String(currency || 'EUR').toUpperCase();
    var utils = window.BileteOnlineUtils || (typeof BileteOnlineUtils !== 'undefined' ? BileteOnlineUtils : null);
    if (utils && typeof utils.formatCurrency === 'function') return utils.formatCurrency(amount, code);
    var style = CURRENCIES[code] || [' ' + code, false, 2];
    var n = Number(amount) || 0;
    var digits = style[2] === 0 ? 0 : (Math.abs(n - Math.round(n)) < 0.005 ? 0 : style[2]);
    var formatted = new Intl.NumberFormat('en-GB', { minimumFractionDigits: digits, maximumFractionDigits: digits }).format(n);
    return style[1] ? style[0] + formatted : formatted + style[0];
  }

  var root = document.documentElement;
  var $ = function (id) { return document.getElementById(id); };
  var data = {};
  try { data = JSON.parse(($('v2-data') || {}).textContent || '{}'); } catch (e) {}
  var b = data.booking;
  var MONTHS = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
  var DOW = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

  function pad(n) { return (n < 10 ? '0' : '') + n; }
  // local calendar date, never toISOString (that is UTC and shifts the day east of Greenwich)
  function iso(d) { return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()); }
  function parse(s) { var p = s.split('-').map(Number); return new Date(p[0], p[1] - 1, p[2]); }
  /** A price in cents, written in its currency. */
  function money(cents, currency) { return formatMoney((cents || 0) / 100, currency); }
  function node(tag, cls, text) {
    var n = document.createElement(tag);
    if (cls) n.className = cls;
    if (text !== undefined && text !== null) n.textContent = text;
    return n;
  }
  function trapTab(e, box) {
    if (e.key !== 'Tab') return;
    var f = [].slice.call(box.querySelectorAll('a[href], button:not([disabled])')).filter(function (el) { return el.offsetParent !== null; });
    if (!f.length) return;
    var first = f[0], last = f[f.length - 1];
    if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
    else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
  }

  /* ---------- booking ---------- */
  if (b && $('rezervare')) {
    var variants = b.variants || [];
    var win = b.window || {};
    // The currency the activity is sold in: its variants' (the API sends it with each price), else the page's,
    // else the marketplace's, else euro.
    var pageCur = String(b.currency || (typeof BILETEONLINE_CONFIG !== 'undefined' && BILETEONLINE_CONFIG.CURRENCY) || 'EUR').toUpperCase();
    var curOf = function (v) { return String((v && v.currency) || pageCur).toUpperCase(); };
    var cur = curOf(variants.filter(function (v) { return v && v.currency; })[0]);
    var state = {
      date: b.today,
      slot: null,
      slots: [],
      loading: false,
      qty: {},
      available: [],
      calOpen: false,
      calMonth: parse(b.today).getMonth(),
      calYear: parse(b.today).getFullYear()
    };
    var el = {
      date: $('bk-date'), days: $('bk-days'), calToggle: $('bk-cal-toggle'), cal: $('bk-cal'), calTitle: $('bk-cal-title'), calGrid: $('bk-cal-grid'),
      slots: $('bk-slots'), slotsLoading: $('bk-slots-loading'), slotsNone: $('bk-slots-none'), variants: $('bk-variants'),
      part: $('bk-part'), sum: $('bk-sum'), sub: $('bk-sub'), feeRow: $('bk-fee-row'), feeRate: $('bk-fee-rate'), fee: $('bk-fee'),
      total: $('bk-total'), points: $('bk-points'), cart: $('bk-cart'), checkout: $('bk-checkout'), reward: $('bk-reward'), rewardN: $('bk-reward-n'), rewardBig: $('bk-reward-big')
    };

    var currentSlot = function () {
      var s = null;
      state.slots.forEach(function (x) { if (x.start_time === state.slot) s = x; });
      return s;
    };
    var slotRemaining = function () { var s = currentSlot(); return s ? (s.capacity_remaining || 0) : 0; };
    var seatsUsed = function () { return variants.reduce(function (acc, v) { return acc + (state.qty[v.id] || 0) * (v.capacity_share || 1); }, 0); };
    var totalCents = function () { return variants.reduce(function (acc, v) { return acc + (state.qty[v.id] || 0) * (v.price_cents || 0); }, 0); };
    var rate = b.commission_rate || 0, mode = b.commission_mode || 'included';
    var feeCents = function () { return Math.round(totalCents() * rate / 100); };
    // Loyalty points the booking earns, with the programme's real rule (from /checkout/features via cart.js, on the
    // ticket value); nothing is shown when the marketplace runs no points programme.
    var loyalty = function () {
      return typeof BileteOnlineCart !== 'undefined' && typeof BileteOnlineCart.getLoyaltyConfig === 'function' ? BileteOnlineCart.getLoyaltyConfig() : null;
    };
    var pointsEstimate = function () {
      return loyalty() ? Math.max(0, BileteOnlineCart.estimatePoints(totalCents() / 100)) : 0;
    };
    var pointsWord = function (n) {
      if (n === 1) return '1 point';
      return new Intl.NumberFormat('en-GB').format(n) + ' points';
    };
    var participantsLabel = function () {
      var n = seatsUsed();
      if (!n) return 'Choose your tickets';
      var min = win.min_participants || 1, max = win.max_participants || 99;
      if (n < min) return 'Minimum ' + min + ' ' + (min === 1 ? 'participant' : 'participants');
      if (n > max) return 'Maximum ' + max + ' ' + (max === 1 ? 'participant' : 'participants');
      return n + ' ' + (n === 1 ? 'participant' : 'participants');
    };
    var canSubmit = function () {
      var min = win.min_participants || 1, max = win.max_participants || 99, n = seatsUsed();
      return !!state.slot && n >= min && n <= max && n <= slotRemaining() && totalCents() > 0;
    };

    var renderSummary = function () {
      var n = seatsUsed();
      el.part.hidden = n === 0;
      el.part.textContent = participantsLabel();
      el.variants.hidden = !state.slot;
      variants.forEach(function (v) {
        var out = el.variants.querySelector('[data-qty="' + v.id + '"]');
        if (out) out.textContent = state.qty[v.id] || 0;
      });
      el.sum.hidden = n === 0;
      var pts = pointsEstimate();
      el.reward.hidden = n === 0 || pts <= 0;
      if (el.points && el.points.parentNode) el.points.parentNode.hidden = pts <= 0;
      el.sub.textContent = money(totalCents(), cur);
      var showFee = rate > 0 && mode === 'added_on_top';
      el.feeRow.hidden = !showFee;
      el.feeRate.textContent = String(rate);
      el.fee.textContent = money(feeCents(), cur);
      el.total.textContent = money(mode === 'added_on_top' ? totalCents() + feeCents() : totalCents(), cur);
      el.points.textContent = '+' + pointsWord(pts);
      el.rewardN.textContent = new Intl.NumberFormat('en-GB').format(pts);
      el.rewardBig.textContent = '+' + new Intl.NumberFormat('en-GB').format(pts);
      var ok = canSubmit();
      el.cart.disabled = !ok;
      el.checkout.disabled = !ok;
    };

    var renderSlots = function () {
      el.slotsLoading.hidden = !state.loading;
      el.slotsNone.hidden = state.loading || state.slots.length > 0;
      el.slots.textContent = '';
      if (state.loading) return;
      state.slots.forEach(function (s) {
        var btn = node('button', 'bk-slot');
        btn.type = 'button';
        btn.setAttribute('aria-pressed', String(state.slot === s.start_time));
        btn.appendChild(node('b', null, (s.start_time || '').toString().slice(0, 5)));
        var left = s.capacity_remaining || 0;
        btn.appendChild(node('span', left <= 3 ? 'is-low' : null, left + (left === 1 ? ' place' : ' places')));
        btn.addEventListener('click', function () {
          state.slot = s.start_time;
          Object.keys(state.qty).forEach(function (k) { state.qty[k] = 0; });
          renderSlots();
          renderSummary();
        });
        el.slots.appendChild(btn);
      });
    };

    var loadSlots = function () {
      state.slot = null;
      state.slots = [];
      state.loading = true;
      renderSlots();
      renderSummary();
      var asked = state.date;
      fetch('/api/proxy.php?action=activity.slots&slug=' + encodeURIComponent(b.slug) + '&date=' + encodeURIComponent(asked))
        .then(function (r) { return r.json(); })
        .then(function (j) { if (asked === state.date) state.slots = (j && j.data && j.data.slots) || []; })
        .catch(function () { if (asked === state.date) state.slots = []; })
        .then(function () {
          if (asked !== state.date) return;
          state.loading = false;
          renderSlots();
          renderSummary();
        });
    };

    var renderCalendar = function () {
      el.calTitle.textContent = MONTHS[state.calMonth] + ' ' + state.calYear;
      el.calGrid.textContent = '';
      var first = new Date(state.calYear, state.calMonth, 1);
      var offset = (first.getDay() + 6) % 7;
      var hasList = state.available.length > 0;
      for (var i = 0; i < 42; i++) {
        var d = new Date(state.calYear, state.calMonth, 1 - offset + i);
        var value = iso(d);
        var inRange = value >= b.today && value <= b.max_date;
        var selectable = inRange && (hasList ? state.available.indexOf(value) !== -1 : true);
        var btn = node('button', 'bk-cal-day' + (d.getMonth() === state.calMonth ? '' : ' is-out'), String(d.getDate()));
        btn.type = 'button';
        btn.disabled = !selectable;
        btn.setAttribute('aria-label', d.getDate() + ' ' + MONTHS[d.getMonth()] + ' ' + d.getFullYear() + (selectable ? '' : ', unavailable'));
        if (value === state.date) btn.setAttribute('aria-current', 'date');
        btn.setAttribute('data-date', value);
        el.calGrid.appendChild(btn);
      }
    };

    var renderDays = function () {
      var list = state.available.filter(function (v) { return v >= b.today && v <= b.max_date; }).slice(0, 6);
      el.days.textContent = '';
      el.days.hidden = list.length === 0;
      list.forEach(function (v) {
        var d = parse(v), li = node('li'), btn = node('button', 'bk-day');
        btn.type = 'button';
        btn.setAttribute('data-date', v);
        btn.setAttribute('aria-pressed', String(v === state.date));
        btn.appendChild(node('span', null, v === b.today ? 'Today' : DOW[d.getDay()]));
        btn.appendChild(node('b', null, String(d.getDate())));
        btn.appendChild(node('small', null, MONTHS[d.getMonth()].slice(0, 3)));
        li.appendChild(btn);
        el.days.appendChild(li);
      });
    };

    var selectDate = function (value) {
      state.date = value;
      el.date.value = value;
      var d = parse(value);
      state.calYear = d.getFullYear();
      state.calMonth = d.getMonth();
      renderDays();
      if (state.calOpen) renderCalendar();
      loadSlots();
    };

    el.date.addEventListener('change', function () { if (el.date.value) selectDate(el.date.value); });
    el.calToggle.addEventListener('click', function () {
      state.calOpen = !state.calOpen;
      el.cal.hidden = !state.calOpen;
      el.calToggle.setAttribute('aria-expanded', String(state.calOpen));
      el.calToggle.textContent = state.calOpen ? 'Hide the calendar' : 'Choose from the calendar';
      if (state.calOpen) renderCalendar();
    });
    $('bk-cal-prev').addEventListener('click', function () {
      if (state.calMonth === 0) { state.calMonth = 11; state.calYear--; } else state.calMonth--;
      renderCalendar();
    });
    $('bk-cal-next').addEventListener('click', function () {
      if (state.calMonth === 11) { state.calMonth = 0; state.calYear++; } else state.calMonth++;
      renderCalendar();
    });
    el.calGrid.addEventListener('click', function (e) {
      var btn = e.target.closest('button[data-date]');
      if (btn && !btn.disabled) selectDate(btn.getAttribute('data-date'));
    });
    el.days.addEventListener('click', function (e) {
      var btn = e.target.closest('button[data-date]');
      if (btn) selectDate(btn.getAttribute('data-date'));
    });

    el.variants.addEventListener('click', function (e) {
      var inc = e.target.closest('[data-inc]'), dec = e.target.closest('[data-dec]');
      if (!inc && !dec) return;
      var id = parseInt((inc || dec).getAttribute(inc ? 'data-inc' : 'data-dec'), 10);
      var v = null;
      variants.forEach(function (x) { if (x.id === id) v = x; });
      if (!v) return;
      var current = state.qty[id] || 0;
      if (inc) {
        var max = Math.max(1, v.max_per_order || 10);
        var remainingSeats = slotRemaining() - seatsUsed() + current * (v.capacity_share || 1);
        var maxByCapacity = Math.floor(remainingSeats / (v.capacity_share || 1));
        state.qty[id] = Math.min(current + 1, max, Math.max(0, maxByCapacity));
      } else {
        state.qty[id] = Math.max(0, current - 1);
      }
      renderSummary();
    });

    // the points rules come with /checkout/features: once here, the estimate follows them
    if (typeof BileteOnlineCart !== 'undefined' && typeof BileteOnlineCart.loadLoyaltyConfig === 'function') {
      BileteOnlineCart.loadLoyaltyConfig().then(function () { renderSummary(); }, function () {});
    }

    var submit = function (dest) {
      if (!canSubmit()) return;
      if (typeof BileteOnlineCart === 'undefined' || typeof BileteOnlineCart.addActivityItem !== 'function') {
        alert('The basket did not load. Reload the page and try again.');
        return;
      }
      var slot = currentSlot();
      if (!slot) return;
      var activityData = {
        id: b.activity_id, slug: b.slug, title: b.title, image: b.cover_image,
        venue: b.venue_name, city: b.venue_city, organizer_id: b.organizer_id,
        duration_minutes: b.duration_minutes,
        commission_rate: rate, commission_mode: mode,
        currency: cur
      };
      var pushed = 0;
      variants.forEach(function (v) {
        var qty = state.qty[v.id] || 0;
        if (qty <= 0) return;
        var result = BileteOnlineCart.addActivityItem(
          activityData,
          // the basket reads the line's currency from the variant; without it it assumes euro
          { id: v.id, name: v.name, price_cents: v.price_cents, capacity_share: v.capacity_share || 1, currency: curOf(v) },
          { date: state.date, start_time: slot.start_time, end_time: slot.end_time },
          qty
        );
        if (result) pushed++;
      });
      if (pushed === 0) { alert('We could not add this to your basket. Check the date and time you chose, then try again.'); return; }
      window.location.href = dest === 'checkout' ? '/checkout' : '/cart';
    };
    el.cart.addEventListener('click', function () { submit('cart'); });
    el.checkout.addEventListener('click', function () { submit('checkout'); });

    loadSlots();
    fetch('/api/proxy.php?action=activity.available-dates&slug=' + encodeURIComponent(b.slug))
      .then(function (r) { return r.json(); })
      .then(function (j) {
        state.available = (j && j.data && (j.data.dates || j.data.available_dates)) || [];
        renderDays();
        if (state.calOpen) renderCalendar();
      })
      .catch(function () { state.available = []; });
  }

  /* ---------- gallery lightbox ---------- */
  var lb = $('lb'), gallery = (b && b.gallery) || [];
  if (lb && gallery.length) {
    var img = $('lb-img'), title = $('lb-title'), count = $('lb-count'), at = 0, opener = null;
    var show = function (i) {
      at = (i + gallery.length) % gallery.length;
      img.src = gallery[at].src;
      img.alt = gallery[at].alt || '';
      title.textContent = gallery[at].alt || 'Gallery';
      count.textContent = (at + 1) + ' / ' + gallery.length;
    };
    var close = function () {
      lb.hidden = true;
      root.classList.remove('lb-lock');
      if (opener) opener.focus();
    };
    document.querySelectorAll('[data-gallery]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        opener = btn;
        show(parseInt(btn.getAttribute('data-gallery'), 10) || 0);
        lb.hidden = false;
        root.classList.add('lb-lock');
        lb.querySelector('[data-lb="close"]').focus();
      });
    });
    lb.addEventListener('click', function (e) {
      var t = e.target.closest('[data-lb]');
      if (t) {
        var act = t.getAttribute('data-lb');
        if (act === 'close') close();
        else show(at + (act === 'next' ? 1 : -1));
      } else if (e.target === lb || e.target.classList.contains('lb-fig')) {
        close();
      }
    });
    lb.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') { e.preventDefault(); close(); }
      else if (e.key === 'ArrowRight' && gallery.length > 1) show(at + 1);
      else if (e.key === 'ArrowLeft' && gallery.length > 1) show(at - 1);
      else trapTab(e, lb);
    });
  }
})();
