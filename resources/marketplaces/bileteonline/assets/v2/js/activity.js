/* bilete.online v2: activity page. The booking widget (date, calendar, slots, variants, estimate) hands the choice
   to BileteOnlineCart.addActivityItem (assets/js/cart.js) exactly as the previous page did, then goes to /cos or
   /finalizare. Plus the photo gallery. */
(function () {
  'use strict';
  var root = document.documentElement;
  var $ = function (id) { return document.getElementById(id); };
  var data = {};
  try { data = JSON.parse(($('v2-data') || {}).textContent || '{}'); } catch (e) {}
  var b = data.booking;
  var MONTHS = ['ianuarie', 'februarie', 'martie', 'aprilie', 'mai', 'iunie', 'iulie', 'august', 'septembrie', 'octombrie', 'noiembrie', 'decembrie'];
  var DOW = ['dum', 'lun', 'mar', 'mie', 'joi', 'vin', 'sâm'];

  function pad(n) { return (n < 10 ? '0' : '') + n; }
  // local calendar date, never toISOString (that is UTC and shifts the day in Romania)
  function iso(d) { return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()); }
  function parse(s) { var p = s.split('-').map(Number); return new Date(p[0], p[1] - 1, p[2]); }
  function lei(cents) { return new Intl.NumberFormat('ro-RO', { maximumFractionDigits: 0 }).format((cents || 0) / 100) + ' lei'; }
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
      if (n === 1) return '1 punct';
      var r = n % 100;
      return new Intl.NumberFormat('ro-RO').format(n) + (n >= 20 && !(r >= 1 && r <= 19) ? ' de puncte' : ' puncte');
    };
    var participantsLabel = function () {
      var n = seatsUsed();
      if (!n) return 'Selectează biletele';
      var min = win.min_participants || 1, max = win.max_participants || 99;
      if (n < min) return 'Minim ' + min + ' participanți';
      if (n > max) return 'Maxim ' + max + ' participanți';
      return n + ' ' + (n === 1 ? 'participant' : 'participanți');
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
      el.sub.textContent = lei(totalCents());
      var showFee = rate > 0 && mode === 'added_on_top';
      el.feeRow.hidden = !showFee;
      el.feeRate.textContent = String(rate).replace('.', ',');
      el.fee.textContent = lei(feeCents());
      el.total.textContent = lei(mode === 'added_on_top' ? totalCents() + feeCents() : totalCents());
      el.points.textContent = '+' + pointsWord(pts);
      el.rewardN.textContent = new Intl.NumberFormat('ro-RO').format(pts);
      el.rewardBig.textContent = '+' + new Intl.NumberFormat('ro-RO').format(pts);
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
        btn.appendChild(node('span', left <= 3 ? 'is-low' : null, left + ' locuri'));
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
        btn.setAttribute('aria-label', d.getDate() + ' ' + MONTHS[d.getMonth()] + ' ' + d.getFullYear() + (selectable ? '' : ', indisponibil'));
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
        btn.appendChild(node('span', null, v === b.today ? 'azi' : DOW[d.getDay()]));
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
      el.calToggle.textContent = state.calOpen ? 'Ascunde calendar' : 'Alege din calendar';
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
        alert('Coșul nu este încărcat. Reîncarcă pagina și încearcă din nou.');
        return;
      }
      var slot = currentSlot();
      if (!slot) return;
      var activityData = {
        id: b.activity_id, slug: b.slug, title: b.title, image: b.cover_image,
        venue: b.venue_name, city: b.venue_city, organizer_id: b.organizer_id,
        duration_minutes: b.duration_minutes,
        commission_rate: rate, commission_mode: mode
      };
      var pushed = 0;
      variants.forEach(function (v) {
        var qty = state.qty[v.id] || 0;
        if (qty <= 0) return;
        var result = BileteOnlineCart.addActivityItem(
          activityData,
          { id: v.id, name: v.name, price_cents: v.price_cents, capacity_share: v.capacity_share || 1 },
          { date: state.date, start_time: slot.start_time, end_time: slot.end_time },
          qty
        );
        if (result) pushed++;
      });
      if (pushed === 0) { alert('Nu am putut adăuga în coș. Verifică data și ora alese, apoi încearcă din nou.'); return; }
      window.location.href = dest === 'checkout' ? '/finalizare' : '/cos';
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
      title.textContent = gallery[at].alt || 'Galerie';
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
