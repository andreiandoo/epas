/* viaqui.com v2: booking for the activities module (locations, access tickets, experiences, packages).
   One engine for the location page (every product sold there, one date) and the experience page (one product,
   plus the location's access tickets when the experience needs one). Reads the page's #v2-data .booking:
     { mode: 'location'|'product', location: {slug,name,city}|null, product_slug, products: [...], categories: [...],
       today: 'Y-m-d', max_days: n, focus_product_id }
   Availability comes from the proxy (am.location.day / am.product.day and the .calendar actions); the choice goes to
   BileteOnlineCart.addBookingItem (assets/js/cart.js), then to /cart or /checkout. The server checks everything again
   at checkout; the rules here only spare the customer a refused order.
   In the booking widget on the operator's site (embed/locatie.php, cfg.embed) the frame cannot reach this site's
   storage, so the same lines go to /checkout#bo-import=… in a new tab, where cart.js adds them.
   Prices are written in the product's own currency (variant.currency, else product.currency, else cfg.currency,
   else the site's), the one the customer is charged in; the same code goes to the basket with each line. */
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

  var $ = function (id) { return document.getElementById(id); };
  var reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var data = {};
  try { data = JSON.parse(($('v2-data') || {}).textContent || '{}'); } catch (e) {}
  var cfg = data.booking;
  var root = $('bkx');
  if (!cfg || !root || !Array.isArray(cfg.products)) return;

  var MONTHS = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
  var DOW = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
  var DOW_LONG = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

  // The currency of the page (from the PHP page), else the marketplace's, else euro.
  var SITE_CUR = String(cfg.currency || (typeof BILETEONLINE_CONFIG !== 'undefined' && BILETEONLINE_CONFIG.CURRENCY) || 'EUR').toUpperCase();
  /** The currency a product's variant is sold in. */
  function curOf(p, v) { return String((v && v.currency) || (p && p.currency) || SITE_CUR).toUpperCase(); }
  /** A price in cents, written in its currency. */
  function money(cents, currency) { return formatMoney((cents || 0) / 100, currency || SITE_CUR); }

  function pad(n) { return (n < 10 ? '0' : '') + n; }
  function iso(d) { return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()); }
  function parse(s) { var p = s.split('-').map(Number); return new Date(p[0], p[1] - 1, p[2]); }
  function addDays(s, n) { var d = parse(s); d.setDate(d.getDate() + n); return iso(d); }
  function hm(t) { return t ? String(t).slice(0, 5) : ''; }
  function dateLabel(s) { var d = parse(s); return DOW_LONG[d.getDay()] + ', ' + d.getDate() + ' ' + MONTHS[d.getMonth()]; }
  function shortDate(s) { var d = parse(s); return DOW[d.getDay()] + ', ' + d.getDate() + ' ' + MONTHS[d.getMonth()].slice(0, 3); }
  function el(tag, attrs, kids) {
    var n = document.createElement(tag);
    if (attrs) Object.keys(attrs).forEach(function (k) {
      var v = attrs[k];
      if (v === null || v === undefined || v === false) return;
      if (k === 'text') n.textContent = v;
      else if (k === 'class') n.className = v;
      else if (k === 'on') Object.keys(v).forEach(function (ev) { n.addEventListener(ev, v[ev]); });
      else n.setAttribute(k, v === true ? '' : v);
    });
    (kids || []).forEach(function (c) { if (c !== null && c !== undefined && c !== false) n.appendChild(typeof c === 'string' ? document.createTextNode(c) : c); });
    return n;
  }
  /** A product's icon (a key from includes/v2/product-icons.php, whose sprite the page printed), or null. */
  function productIcon(key) {
    if (typeof key !== 'string' || !/^[a-z]{1,16}$/.test(key) || !document.getElementById('i-pi-' + key)) return null;
    var NS = 'http://www.w3.org/2000/svg', s = document.createElementNS(NS, 'svg'), u = document.createElementNS(NS, 'use');
    s.setAttribute('class', 'ic');
    s.setAttribute('aria-hidden', 'true');
    s.setAttribute('focusable', 'false');
    u.setAttribute('href', '#i-pi-' + key);
    s.appendChild(u);
    return s;
  }
  function api(action, params) {
    var q = Object.keys(params).map(function (k) { return encodeURIComponent(k) + '=' + encodeURIComponent(params[k]); }).join('&');
    return fetch('/api/proxy.php?action=' + action + '&' + q, { headers: { Accept: 'application/json' } })
      .then(function (r) { return r.json().then(function (j) { if (!r.ok) throw j; return j.data || j; }); });
  }

  var products = cfg.products;
  // The location's day/calendar cover every product sold there; one product alone asks its own endpoints
  // (exact prices and seats in the day strip), e.g. an experience page without access tickets next to it.
  var viaLocation = !!cfg.location && (cfg.mode === 'location' || products.length > 1);
  var byId = {};
  products.forEach(function (p) { byId[p.id] = p; });
  var today = cfg.today;
  var lastDay = addDays(today, Math.max(1, cfg.max_days || 90));

  var state = {
    date: null,
    day: {},            // product id → availability from /day
    hours: null,
    loading: false,
    calendar: {},       // Y-m-d → {status, min_price_cents} — the location's, when we ask it
    pcal: {},           // the focused product's own calendar, which is what a product page shows
    calOpen: false,
    calMonth: parse(today).getMonth(),
    calYear: parse(today).getFullYear(),
    tab: 'all',
    qty: {},            // "pid:vid" → n
    time: {},           // "pid:vid" → HH:MM:SS   (timed products)
    comp: {},           // "pid:itemId" → HH:MM:SS (package components)
    addons: {},         // "pid:vid:aid" → n
    plate: {},          // "pid:vid" → plate
    error: ''
  };

  var key = function (p, v) { return p.id + ':' + v.id; };
  var variantsOf = function (p) { return p.variants || []; };
  var slotsFor = function (p, v) {
    var av = state.day[p.id];
    if (!av || av.mode !== 'slot') return [];
    var by = av.slots_by_variant || {};
    return (by[v.id] || by[String(v.id)] || av.slots || []);
  };
  var dayBookable = function (p) { var av = state.day[p.id]; return !!(av && av.bookable); };
  // rate in percent, floor in the product's currency per ticket (the minimum viaqui.com charges); the server applies the same rule
  var commissionOf = function (p) { return p.commission || { rate: 0, mode: 'included', floor: 0 }; };
  var feeOf = function (c, valueCents, quantity) {
    if (c.mode !== 'added_on_top') return 0;
    var fee = c.rate > 0 ? Math.round(valueCents * c.rate / 100) : 0;
    var floor = Math.round((c.floor || 0) * 100) * Math.max(1, quantity);
    return Math.max(fee, floor);
  };

  /* ---------------- selection → cart lines ---------------- */
  function lines() {
    var out = [];
    products.forEach(function (p) {
      variantsOf(p).forEach(function (v) {
        var q = state.qty[key(p, v)] || 0;
        if (q <= 0) return;
        var addons = (p.addons || []).map(function (a) {
          var n = state.addons[key(p, v) + ':' + a.id] || 0;
          if (n <= 0) return null;
          var paid = Math.max(0, n - (a.included_qty || 0) * q);
          return { id: a.id, name: a.name, qty: n, included: Math.min(n, (a.included_qty || 0) * q), paid_qty: paid, price: a.price_cents / 100, total: paid * a.price_cents / 100 };
        }).filter(Boolean);
        var t = p.booking_mode === 'slot' && p.type !== 'package' ? state.time[key(p, v)] || null : null;
        var slot = null;
        if (t) slotsFor(p, v).forEach(function (s) { if (s.start_time === t) slot = s; });
        out.push({
          product: p, variant: v, quantity: q, time: t, end: slot ? slot.end_time : null, addons: addons,
          addonsTotal: addons.reduce(function (acc, a) { return acc + a.total; }, 0),
          plate: (state.plate[key(p, v)] || '').trim(),
          components: p.type === 'package' ? (p.components || []).map(function (c) {
            return { item_id: c.item_id, slot_start_time: state.comp[p.id + ':' + c.item_id] || null, title: c.title, booking_mode: c.booking_mode };
          }) : []
        });
      });
    });
    return out;
  }

  // Access tickets already in the cart for this location and date also count.
  function cartAccess() {
    var got = { any: 0, adult: 0 };
    if (cfg.embed || typeof BileteOnlineCart === 'undefined' || !cfg.location) return got;
    (BileteOnlineCart.getCart().items || []).forEach(function (it) {
      if (it.type !== 'activity' || !it.activity || it.activity.location_slug !== cfg.location.slug || it.booking_date !== state.date) return;
      if (it.activity.product_type === 'access') {
        var persons = it.variant && it.variant.persons_counted ? it.variant.persons_counted : (it.quantity || 0);
        got.any += persons;
        if (!(it.variant && it.variant.is_child)) got.adult += persons;
      }
    });
    return got;
  }

  /* Each problem says where it is solved, so the sidebar can point at the section instead of
     describing it and leaving the visitor to find it. `at` is a token resolved in renderList. */
  function problems(ls) {
    var errs = [];
    var add = function (msg, at) { errs.push({ msg: msg, at: at || '' }); };
    var access = cartAccess();
    var needs = { any: 0, adult: 0 };
    var titleNeeds = { any: null, adult: null };
    ls.forEach(function (l) {
      var p = l.product, v = l.variant, q = l.quantity;
      var min = Math.max(1, v.min_per_order || 1), max = v.max_per_order || 0, step = v.step_qty || 1;
      if (q < min) add('The minimum for “' + p.title + '” is ' + min + '.', 'p:' + p.id);
      if (max && q > max) add('You can book at most ' + max + ' of “' + p.title + '”.', 'p:' + p.id);
      if (step > 1 && (q - min) % step) add('“' + p.title + '” is sold ' + step + ' at a time.', 'p:' + p.id);
      if (p.booking_mode === 'slot' && p.type !== 'package' && !l.time) add('Choose a time for “' + p.title + ' — ' + v.name + '”.', 'time:' + key(p, v));
      if (p.requires_vehicle_info && !l.plate) add('Enter the vehicle registration number for “' + p.title + '”.', 'plate:' + key(p, v));
      l.components.forEach(function (c) { if (c.booking_mode === 'slot' && !c.slot_start_time) add('Choose a time for “' + c.title + '” in the package.', 'p:' + p.id); });
      var persons = v.price_type === 'per_unit' ? q * Math.max(1, v.persons_max || 1) : q;
      if (p.type === 'access') { access.any += persons; if (!v.is_child) access.adult += persons; }
      if (p.type === 'package') (p.components || []).forEach(function (c) {
        var cp = byId[c.product_id];
        if (c.type === 'access') { access.any += c.quantity * q; if (!(cp && cp.variants && cp.variants.some(function (x) { return x.name === c.variant && x.is_child; }))) access.adult += c.quantity * q; }
      });
      if (p.type === 'experience' && (p.access_requirement === 'any' || p.access_requirement === 'adult')) {
        needs[p.access_requirement] += q;
        titleNeeds[p.access_requirement] = p.title;
      }
    });
    if (needs.adult > access.adult) add('“' + titleNeeds.adult + '” also needs an adult entry ticket for each person, on the same day.', 'access');
    if (needs.any > access.any) add('“' + titleNeeds.any + '” also needs entry tickets for the same day.', 'access');
    return errs;
  }

  function totals(ls) {
    var sub = 0, fee = 0;
    ls.forEach(function (l) {
      var value = l.variant.price_cents * l.quantity + Math.round(l.addonsTotal * 100);
      sub += value;
      fee += feeOf(commissionOf(l.product), value, l.quantity);
    });
    // The card processing fee isn't part of this total and isn't shown here: it depends on the payment method chosen
    // in the checkout, which is where it is added (the cart page says the same). `card` only says whether this
    // marketplace passes it to the customer, so the note can be shown.
    var card = 0;
    if (sub > 0 && typeof BileteOnlineCart !== 'undefined' && typeof BileteOnlineCart.computeProcessingFee === 'function') {
      try {
        var pf = BileteOnlineCart.computeProcessingFee((sub + fee) / 100);
        card = Math.round((pf.amount || 0) * 100);
      } catch (e) {}
    }
    return { sub: sub, fee: fee, card: card, total: sub + fee };
  }

  /* ---------------- rendering ---------------- */
  var elDays = $('bkx-days'), elCal = $('bkx-cal'), elCalGrid = $('bkx-cal-grid'), elCalTitle = $('bkx-cal-title'),
      elCalToggle = $('bkx-cal-toggle'), elHours = $('bkx-hours'), elTabs = $('bkx-tabs'), elList = $('bkx-list'),
      elSum = $('bkx-sum'), elLines = $('bkx-lines'), elSub = $('bkx-sub'), elFeeRow = $('bkx-fee-row'), elFee = $('bkx-fee'),
      elFeeLabel = $('bkx-fee-label'), elTotal = $('bkx-total'), elErr = $('bkx-err'), elCart = $('bkx-cart'), elGo = $('bkx-go'),
      elBar = $('bkx-bar'), elBarTotal = $('bkx-bar-total'), elBarCount = $('bkx-bar-count'),
      elCardNote = $('bkx-card-note');

  function renderDays() {
    elDays.textContent = '';
    for (var i = 0; i < 14; i++) {
      var d = addDays(today, i);
      if (d > lastDay) break;
      var c = dayInfo(d);
      var closed = c.status === 'closed' || c.status === 'full';
      var btn = el('button', {
        type: 'button', class: 'bkx-day' + (closed ? ' is-closed' : '') + (c.status === 'limited' ? ' is-limited' : ''),
        'aria-pressed': String(d === state.date), 'data-date': d,
        'aria-label': dateLabel(d) + (c.status === 'closed' ? ', closed' : c.status === 'full' ? ', sold out' : '')
      }, [
        el('span', { text: i === 0 ? 'Today' : i === 1 ? 'Tomorrow' : DOW[parse(d).getDay()] }),
        el('b', { text: String(parse(d).getDate()) }),
        el('small', { text: c.min_price_cents ? money(c.min_price_cents, c.currency) : (c.status === 'closed' ? 'closed' : c.status === 'full' ? 'sold out' : MONTHS[parse(d).getMonth()].slice(0, 3)) })
      ]);
      elDays.appendChild(el('li', null, [btn]));
    }
  }

  function renderCalendar() {
    elCalTitle.textContent = MONTHS[state.calMonth] + ' ' + state.calYear;
    elCalGrid.textContent = '';
    var first = new Date(state.calYear, state.calMonth, 1);
    var offset = (first.getDay() + 6) % 7;
    for (var i = 0; i < 42; i++) {
      var dt = new Date(state.calYear, state.calMonth, 1 - offset + i);
      var v = iso(dt), c = dayInfo(v);
      var inRange = v >= today && v <= lastDay;
      var open = inRange && c.status !== 'closed' && c.status !== 'full';
      var btn = el('button', {
        type: 'button', class: 'bkx-cday' + (dt.getMonth() === state.calMonth ? '' : ' is-out') + (c.status === 'limited' ? ' is-limited' : ''),
        'data-date': v, disabled: !open, 'aria-current': v === state.date ? 'date' : null,
        'aria-label': dt.getDate() + ' ' + MONTHS[dt.getMonth()] + (open ? '' : ', unavailable')
      // the cell is narrow: the amount alone, without the currency sign (the day strip and the list carry it)
      }, [el('b', { text: String(dt.getDate()) }), c.min_price_cents && open ? el('small', { text: money(c.min_price_cents, c.currency).replace(/^[^\d]+|[^\d]+$/g, '') }) : null]);
      elCalGrid.appendChild(btn);
    }
  }

  function renderHours() {
    var h = state.hours;
    if (state.loading) { elHours.textContent = 'Checking availability…'; return; }
    if (!state.date) { elHours.textContent = ''; return; }
    var any = products.some(dayBookable);
    if (h && h.open) {
      elHours.textContent = dateLabel(state.date) + ': open ' + hm(h.open) + '–' + hm(h.close) + (h.last_entry ? ', last entry ' + hm(h.last_entry) : '') + '.';
    } else {
      elHours.textContent = dateLabel(state.date) + (any ? '' : ': no tickets are sold on this day. Choose another date.');
    }
  }

  function renderTabs() {
    var cats = (cfg.categories || []).filter(function (c) { return products.some(function (p) { return p.display_category === c.id; }); });
    elTabs.textContent = '';
    elTabs.hidden = cats.length < 2;
    if (cats.length < 2) return;
    [{ id: 'all', name: 'All' }].concat(cats).forEach(function (c) {
      elTabs.appendChild(el('button', { type: 'button', class: 'bkx-tab', 'aria-pressed': String(state.tab === c.id), 'data-tab': c.id, text: c.name }));
    });
  }

  function stepper(label, value, onDec, onInc, disInc) {
    return el('div', { class: 'bkx-step' }, [
      el('button', { type: 'button', 'aria-label': 'One fewer: ' + label, disabled: value <= 0, on: { click: onDec } }, ['−']),
      el('output', { 'aria-live': 'polite', text: String(value) }),
      el('button', { type: 'button', 'aria-label': 'One more: ' + label, disabled: !!disInc, on: { click: onInc } }, ['+'])
    ]);
  }

  function availabilityNote(p) {
    var av = state.day[p.id];
    if (state.loading || !av) return null;
    if (!av.bookable) {
      var why = { closed: 'Closed on this day', full: 'Sold out on this day', past: 'This date has passed', too_late: 'Tickets for today are no longer on sale', too_far: 'Too far ahead to book' }[av.reason] || 'Unavailable on this day';
      return el('p', { class: 'bkx-avail is-off', text: why + '.' });
    }
    if (av.mode === 'day' && av.remaining !== null && av.remaining !== undefined && av.remaining <= 20) {
      return el('p', { class: 'bkx-avail is-low', text: av.remaining === 1 ? '1 place left' : av.remaining + ' places left' });
    }
    return null;
  }

  function productRow(p) {
    var off = !dayBookable(p);
    var badges = [];
    if (p.type === 'package') badges.push('Package');
    if (p.booking_mode === 'day' && p.type !== 'package') badges.push('Valid all day');
    if (p.booking_mode === 'slot') badges.push('Fixed time');
    if (p.type === 'experience' && p.access_requirement === 'adult') badges.push('Needs an adult entry ticket');
    if (p.type === 'experience' && p.access_requirement === 'any') badges.push('Needs an entry ticket');

    var head = el('div', { class: 'bkx-p-head' }, [
      productIcon(p.icon) ? el('span', { class: 'bkx-p-ic', 'aria-hidden': 'true' }, [productIcon(p.icon)]) : null,
      el('div', { class: 'bkx-p-t' }, [
        el('h3', { text: p.title }),
        p.short_description ? el('p', { text: p.short_description }) : null,
        el('ul', { class: 'bkx-badges' }, badges.map(function (b) { return el('li', { text: b }); })),
        p.type === 'experience' && cfg.mode === 'location' && p.slug ? el('a', { class: 'bkx-more', href: '/experience/' + p.slug, text: 'See the experience' }) : null
      ])
    ]);
    var row = el('article', { class: 'bkx-p' + (off ? ' is-off' : ''), 'data-product': p.id, 'data-type': p.type }, [head, availabilityNote(p)]);

    if (p.type === 'package' && (p.components || []).length) {
      row.appendChild(el('ul', { class: 'bkx-incl' }, p.components.map(function (c) {
        return el('li', { text: c.quantity + ' × ' + c.title + (c.variant ? ' (' + c.variant + ')' : '') });
      })));
    }

    variantsOf(p).forEach(function (v) {
      var k = key(p, v), q = state.qty[k] || 0;
      var max = v.max_per_order || 99;
      var price = el('span', { class: 'bkx-v-price' }, [money(v.price_cents, curOf(p, v)), p.unit_label ? el('small', { text: ' / ' + p.unit_label }) : (v.price_type === 'per_unit' && v.persons_max ? el('small', { text: ' / up to ' + v.persons_max + ' people' }) : null)]);
      var meta = [];
      if (v.duration_minutes) meta.push(v.duration_minutes >= 60 && v.duration_minutes % 60 === 0 ? (v.duration_minutes / 60) + ' h' : v.duration_minutes + ' min');
      if (v.validity_days > 1) meta.push('valid for ' + v.validity_days + ' days');
      if ((v.min_per_order || 0) > 1) meta.push('minimum ' + v.min_per_order);
      if (v.companion_label) meta.push('+ 1 ' + v.companion_label.toLowerCase() + ' free');
      var line = el('div', { class: 'bkx-v' }, [
        el('div', { class: 'bkx-v-t' }, [el('b', { text: v.name }), meta.length ? el('small', { text: meta.join(' · ') }) : null, v.description ? el('small', { text: v.description }) : null]),
        price,
        stepper(v.name, q,
          function () { var min = Math.max(1, v.min_per_order || 1); setQty(p, v, q <= min ? 0 : q - (v.step_qty || 1)); },
          function () { var min = Math.max(1, v.min_per_order || 1); setQty(p, v, q === 0 ? min : q + (v.step_qty || 1)); },
          off || q >= max)
      ]);
      row.appendChild(line);

      if (q > 0 && p.type !== 'package' && p.booking_mode === 'slot') row.appendChild(timeChips(p, v, k));
      if (q > 0 && (p.addons || []).length) row.appendChild(addonList(p, v, k, q));
      if (q > 0 && p.requires_vehicle_info) {
        var id = 'bkx-plate-' + p.id + '-' + v.id;
        row.appendChild(el('div', { class: 'bkx-plate', 'data-at': 'plate:' + k }, [
          el('label', { for: id, text: 'Vehicle registration number' + (q > 1 ? ' (all of them, separated by commas)' : '') }),
          el('input', { id: id, type: 'text', maxlength: '80', autocomplete: 'off', value: state.plate[k] || '', placeholder: 'e.g. AB12 CDE', on: { input: function (e) { state.plate[k] = e.target.value.toUpperCase(); renderSummary(); } } })
        ]));
      }
    });

    if (p.type === 'package' && variantsOf(p).some(function (v) { return (state.qty[key(p, v)] || 0) > 0; })) {
      var av = state.day[p.id];
      (av && av.components || []).forEach(function (c) {
        if (c.mode !== 'slot') return;
        var comp = (p.components || []).filter(function (x) { return x.item_id === c.item_id; })[0];
        var ck = p.id + ':' + c.item_id;
        row.appendChild(el('div', { class: 'bkx-times' }, [
          el('p', { class: 'bkx-label', text: 'Time for “' + (comp ? comp.title : 'service') + '”' }),
          el('div', { class: 'bkx-chips', role: 'group' }, (c.slots || []).map(function (s) {
            return el('button', { type: 'button', class: 'bkx-chip', 'aria-pressed': String(state.comp[ck] === s.start_time), disabled: !s.is_bookable,
              on: { click: function () { state.comp[ck] = s.start_time; render(); } } }, [el('b', { text: hm(s.start_time) })]);
          }))
        ]));
      });
    }
    return row;
  }

  function timeChips(p, v, k) {
    var slots = slotsFor(p, v);
    return el('div', { class: 'bkx-times', 'data-at': 'time:' + k }, [
      el('p', { class: 'bkx-label', text: 'Start time' + (v.duration_minutes ? ' (' + v.name + ')' : '') }),
      slots.length ? el('div', { class: 'bkx-chips', role: 'group', 'aria-label': 'Times for ' + v.name }, slots.map(function (s) {
        var left = s.capacity_remaining || 0;
        return el('button', {
          type: 'button', class: 'bkx-chip', 'aria-pressed': String(state.time[k] === s.start_time), disabled: !s.is_bookable,
          on: { click: function () { state.time[k] = s.start_time; render(); } }
        }, [el('b', { text: hm(s.start_time) }), s.is_bookable ? el('small', { class: left <= 3 ? 'is-low' : null, text: left + ' left' }) : el('small', { text: 'full' })]);
      })) : el('p', { class: 'bkx-note', text: 'No times left on this day.' })
    ]);
  }

  /* How many of an add-on a booking may take. The operator sets two numbers: how many come free
     with each unit (`included_qty`) and how many *paid* ones may be added on top (`max_per_unit`),
     and the server validates the sum. Two things were confusing about that:
       - a free add-on (price 0) with four included still offered four more "paid" ones at 0,
         which is not something anyone meant to sell;
       - the ceiling was never written down, so a stepper that stopped at two looked arbitrary.
     So: a priced add-on keeps included + paid, a free one stops at what is included, and the
     note always says the limit. */
  function addonCap(a, q) {
    var inc = a.included_qty || 0, paid = a.max_per_unit || 0;
    if (!a.price_cents && inc > 0) return inc * q;     // nothing to buy beyond what comes with it
    return (inc + paid) * q;
  }

  function addonNote(a, q, cap, currency) {
    var bits = [];
    if (a.included_qty) bits.push(a.included_qty + ' included with each');
    if (a.price_cents) bits.push((a.included_qty ? 'then ' : '') + money(a.price_cents, currency) + ' each');
    else if (!a.included_qty) bits.push('free');
    if (cap) bits.push('maximum ' + cap + (q > 1 ? ' for ' + q : ''));
    return bits.join(' · ');
  }

  function addonList(p, v, k, q) {
    return el('div', { class: 'bkx-addons' }, [el('p', { class: 'bkx-label', text: 'Extras' })].concat((p.addons || []).map(function (a) {
      var ak = k + ':' + a.id, n = state.addons[ak] || 0;
      var cap = addonCap(a, q);
      if (n > cap) { state.addons[ak] = n = cap; }      // the quantity went down under a chosen add-on
      return el('div', { class: 'bkx-v bkx-addon' }, [
        el('div', { class: 'bkx-v-t' }, [el('b', { text: a.name }), el('small', { text: addonNote(a, q, cap, curOf(p, v)) })]),
        stepper(a.name, n, function () { state.addons[ak] = Math.max(0, n - 1); render(); }, function () { state.addons[ak] = Math.min(cap, n + 1); render(); }, n >= cap)
      ]);
    })));
  }

  function setQty(p, v, q) {
    var k = key(p, v);
    state.qty[k] = Math.max(0, q);
    if (!state.qty[k]) { delete state.time[k]; }
    render();
  }

  function renderList() {
    elList.textContent = '';
    var shown = products.filter(function (p) { return state.tab === 'all' || p.display_category === state.tab; });
    var cats = cfg.categories || [];
    if (state.tab === 'all' && cats.length > 1) {
      cats.concat([{ id: null, name: 'Other' }]).forEach(function (c) {
        var group = shown.filter(function (p) { return c.id === null ? !cats.some(function (x) { return x.id === p.display_category; }) : p.display_category === c.id; });
        if (!group.length) return;
        elList.appendChild(el('h3', { class: 'bkx-cat', text: c.name }));
        group.forEach(function (p) { elList.appendChild(productRow(p)); });
      });
    } else {
      shown.forEach(function (p) { elList.appendChild(productRow(p)); });
    }
  }

  function renderSummary() {
    var ls = lines(), t = totals(ls), errs = problems(ls);
    var count = ls.reduce(function (a, l) { return a + l.quantity; }, 0);
    // an order is paid in one currency (the basket refuses a second one): the totals are in the first line's
    var cur = ls.length ? curOf(ls[0].product, ls[0].variant) : SITE_CUR;
    elSum.hidden = !ls.length;
    elBar.hidden = !ls.length;
    elLines.textContent = '';
    ls.forEach(function (l) {
      elLines.appendChild(el('li', null, [
        el('span', { text: l.quantity + ' × ' + l.product.title + (l.product.variants.length > 1 || l.product.type === 'package' ? ' — ' + l.variant.name : '') + (l.time ? ', ' + hm(l.time) : '') }),
        el('b', { text: money(l.variant.price_cents * l.quantity, curOf(l.product, l.variant)) })
      ]));
      /* Every add-on gets its own line. Folding them into the ticket's price made the subtotal
         move for no visible reason, which is the one thing a summary must never do. */
      l.addons.forEach(function (a) {
        elLines.appendChild(el('li', { class: 'bkx-line-sub' }, [
          el('span', { text: a.qty + ' × ' + a.name + (a.included ? (a.paid_qty ? ' (' + a.included + ' included)' : ' (included)') : '') }),
          el('b', { text: a.total > 0 ? money(Math.round(a.total * 100), curOf(l.product, l.variant)) : 'included' })
        ]));
      });
    });
    elSub.textContent = money(t.sub, cur);
    elFeeRow.hidden = !t.fee;
    if (elFeeLabel) elFeeLabel.textContent = 'Booking fee';
    elFee.textContent = money(t.fee, cur);
    if (elCardNote) elCardNote.hidden = !t.card;
    elTotal.textContent = money(t.total, cur);
    elBarTotal.textContent = money(t.total, cur);
    elBarCount.textContent = count === 1 ? '1 ticket' : count + ' tickets';
    var first = errs[0] || null;
    var msg = state.error || (first ? first.msg : '');
    elErr.textContent = '';
    if (msg) {
      elErr.appendChild(document.createTextNode(msg));
      // The message names a section; make it the way there rather than a description of it.
      if (!state.error && first && first.at && wanted(first.at)) {
        elErr.appendChild(el('button', { type: 'button', class: 'bkx-err-go', text: 'Show me',
          on: { click: function () { showWanted(first.at, true); } } }));
      }
    }
    elErr.hidden = !msg;
    showWanted(state.error ? '' : (first ? first.at : ''), false);
    var ok = ls.length > 0 && !errs.length && !state.loading;
    elCart.disabled = !ok;
    elGo.disabled = !ok;
  }

  /** The element a problem token points at, or null when it is not on screen. */
  function wanted(at) {
    if (!at) return null;
    if (at === 'access') return elList.querySelector('[data-type="access"]');
    if (at.indexOf('p:') === 0) return elList.querySelector('[data-product="' + at.slice(2) + '"]');
    return elList.querySelector('[data-at="' + at + '"]');
  }

  /** Outline the section that has to be dealt with, and optionally scroll to it. */
  function showWanted(at, scroll) {
    [].forEach.call(elList.querySelectorAll('.is-wanted'), function (n) { n.classList.remove('is-wanted'); });
    if (!at) return;
    var targets = at === 'access'
      ? [].slice.call(elList.querySelectorAll('[data-type="access"]'))
      : [wanted(at)].filter(Boolean);
    targets.forEach(function (n) { n.classList.add('is-wanted'); });
    if (scroll && targets[0]) {
      targets[0].scrollIntoView({ behavior: reduceMotion ? 'auto' : 'smooth', block: 'center' });
    }
  }

  function render() {
    state.error = '';
    renderDays();
    if (state.calOpen) renderCalendar();
    renderHours();
    renderTabs();
    renderList();
    renderSummary();
  }

  /* ---------------- data ---------------- */
  function loadDay(date) {
    state.date = date;
    state.loading = true;
    state.time = {};
    state.comp = {};
    render();
    var asked = date;
    var req = viaLocation
      ? api('am.location.day', { slug: cfg.location.slug, date: date }).then(function (d) {
          state.hours = d.hours || null;
          var map = {};
          (d.products || []).forEach(function (x) { map[x.id] = x; });
          return map;
        })
      : api('am.product.day', { slug: cfg.product_slug, date: date }).then(function (d) {
          state.hours = null;
          var map = {};
          map[products[0].id] = d;
          return map;
        });
    req.then(function (map) {
      if (asked !== state.date) return;
      state.day = map;
    }, function () {
      if (asked !== state.date) return;
      state.day = {};
      state.error = 'We could not check availability. Please try again.';
    }).then(function () {
      if (asked !== state.date) return;
      state.loading = false;
      render();
      if (state.error) { elErr.textContent = state.error; elErr.hidden = false; }
    });
  }

  function loadCalendar(from) {
    var to = addDays(from, 45);
    if (to > lastDay) to = lastDay;
    var jobs = [];
    if (viaLocation) {
      jobs.push(api('am.location.calendar', { slug: cfg.location.slug, from: from, to: to })
        .then(function (d) { (d.days || []).forEach(function (x) { state.calendar[x.date] = x; }); }, function () {}));
    }
    /* An experience page must price the experience. Asking the location gives the cheapest thing
       sold there — the parking, at 25 — under every date of a page whose own prices start at
       40, so the product's own calendar is fetched too and wins wherever it has an answer. */
    if (cfg.product_slug && (!viaLocation || cfg.mode === 'product')) {
      jobs.push(api('am.product.calendar', { slug: cfg.product_slug, from: from, to: to })
        .then(function (d) { (d.days || []).forEach(function (x) { state.pcal[x.date] = x; }); }, function () {}));
    }
    return Promise.all(jobs);
  }

  /** What the day strip and the calendar show for a date: the focused product first. */
  function dayInfo(d) {
    return state.pcal[d] || state.calendar[d] || {};
  }

  /* ---------------- events ---------------- */
  // Two weeks of dates in a strip a mouse cannot grab: press and drag, or wheel sideways.
  if (window.EPHDrag) window.EPHDrag(elDays);

  elDays.addEventListener('click', function (e) {
    var b = e.target.closest('button[data-date]');
    if (b) loadDay(b.getAttribute('data-date'));
  });
  elCalToggle.addEventListener('click', function () {
    state.calOpen = !state.calOpen;
    elCal.hidden = !state.calOpen;
    elCalToggle.setAttribute('aria-expanded', String(state.calOpen));
    elCalToggle.textContent = state.calOpen ? 'Hide the calendar' : 'Another date';
    if (state.calOpen) renderCalendar();
  });
  $('bkx-cal-prev').addEventListener('click', function () {
    if (state.calMonth === 0) { state.calMonth = 11; state.calYear--; } else state.calMonth--;
    renderCalendar();
  });
  $('bkx-cal-next').addEventListener('click', function () {
    if (state.calMonth === 11) { state.calMonth = 0; state.calYear++; } else state.calMonth++;
    var first = iso(new Date(state.calYear, state.calMonth, 1));
    if (!state.calendar[first] && !state.pcal[first] && first <= lastDay) loadCalendar(first < today ? today : first).then(renderCalendar);
    renderCalendar();
  });
  elCalGrid.addEventListener('click', function (e) {
    var b = e.target.closest('button[data-date]');
    if (b && !b.disabled) { loadDay(b.getAttribute('data-date')); }
  });
  elTabs.addEventListener('click', function (e) {
    var b = e.target.closest('button[data-tab]');
    if (b) { state.tab = b.getAttribute('data-tab'); render(); }
  });

  function submit(dest) {
    var ls = lines();
    if (!ls.length || problems(ls).length) { renderSummary(); return; }
    if (cfg.embed) { openCheckout(ls.map(item)); return; }
    if (typeof BileteOnlineCart === 'undefined' || typeof BileteOnlineCart.addBookingItem !== 'function') {
      state.error = 'The basket did not load. Reload the page and try again.';
      renderSummary();
      return;
    }
    var added = 0;
    ls.forEach(function (l) { if (BileteOnlineCart.addBookingItem(item(l))) added++; });
    if (!added) { state.error = 'We could not add this to your basket. Please try again.'; renderSummary(); return; }
    window.location.href = dest === 'checkout' ? '/checkout' : '/cart';
  }
  /* Checkout inside the widget (embed code v2, embed/bo-widget.js on the operator's page). The page tells the frame
     its address ({type: 'bo-embed-hello'}); when it comes from one of the operator's allowed sites, "Continue to
     payment" opens the checkout in this same frame (/embed/finalizare) instead of a new tab on viaqui.com, and the
     customer comes back to that page after paying. Without the script (embed code v1) nothing changes. */
  var parentHref = null;
  function allowedOrigin(o) {
    return (cfg.embed_origins || []).some(function (a) {
      if (a === o) return true;
      var m = /^(https?):\/\/\*\.(.+)$/.exec(a);
      return !!m && o.indexOf(m[1] + '://') === 0 && o.slice(-(m[2].length + 1)) === '.' + m[2];
    });
  }
  function canStore() {
    try { localStorage.setItem('bo_probe', '1'); localStorage.removeItem('bo_probe'); return true; } catch (e) { return false; }
  }
  if (cfg.embed && cfg.embed_allow && window.parent !== window) {
    window.addEventListener('message', function (e) {
      var d = e.data;
      if (e.source !== window.parent || !d || d.type !== 'bo-embed-hello' || !allowedOrigin(e.origin)) return;
      var href = String(d.href || '');
      if (href !== e.origin && href.indexOf(e.origin + '/') !== 0) return;
      parentHref = href;
      var note = document.getElementById('bkx-pay-note');
      if (note) note.textContent = 'You pay here, securely, by card. Your tickets arrive by email straight after payment.';
    });
    try { window.parent.postMessage({ type: 'bo-embed-ready' }, '*'); } catch (e) {}
  }
  function checkoutHere(items) {
    if (!parentHref || !canStore() || typeof BileteOnlineCart === 'undefined' || typeof BileteOnlineCart.addBookingItem !== 'function') return false;
    try {
      // the checkout shows exactly what was picked in the widget
      if (typeof BileteOnlineCart.clear === 'function') BileteOnlineCart.clear({ skipRelease: true });
      var added = 0;
      items.forEach(function (o) { if (BileteOnlineCart.addBookingItem(Object.assign({}, o, { replace: true, quiet: true }))) added++; });
      if (!added) return false;
    } catch (e) {
      return false;
    }
    try { window.parent.postMessage({ type: 'bo-embed-top' }, '*'); } catch (e) {}
    window.location.href = '/embed/finalizare?a=' + encodeURIComponent(cfg.embed_allow) + '&u=' + encodeURIComponent(parentHref);
    return true;
  }
  // A new tab on viaqui.com with the lines in the address (cart.js adds them); a blocked pop-up leaves a link.
  function openCheckout(items) {
    if (checkoutHere(items)) return;
    var url = (cfg.site_url || '') + '/checkout#bo-import=' + encodeURIComponent(JSON.stringify(items)) + (cfg.return_token ? '&bo-return=' + encodeURIComponent(cfg.return_token) : '');
    var w = null;
    try { w = window.open(url, '_blank'); } catch (e) {}
    if (w) return;
    var box = $('bkx-err');
    box.textContent = 'Your browser blocked the new tab. ';
    box.appendChild(el('a', { href: url, target: '_blank', rel: 'noopener', text: 'Open the payment page on viaqui.com' }));
    box.hidden = false;
  }
  function item(l) {
    var p = l.product, c = commissionOf(p);
    return {
      product: {
        id: p.id, slug: p.slug, title: p.title, image: p.image || (cfg.location && cfg.location.image) || null, product_type: p.type,
        booking_mode: p.booking_mode, location_slug: cfg.location ? cfg.location.slug : null,
        venue: cfg.location ? cfg.location.name : null, city: cfg.location ? cfg.location.city : null,
        commission_rate: c.rate, commission_mode: c.mode, commission_floor: c.floor || 0,
        currency: curOf(p, null)
      },
      variant: {
        id: l.variant.id, name: l.variant.name, price_cents: l.variant.price_cents, capacity_share: l.variant.capacity_share || 1,
        currency: curOf(p, l.variant),   // the basket reads the line's currency from here; without it it assumes euro
        is_child: !!l.variant.is_child, persons_counted: l.variant.price_type === 'per_unit' ? l.quantity * Math.max(1, l.variant.persons_max || 1) : l.quantity
      },
      date: state.date,
      date_label: shortDate(state.date),
      start_time: l.time,
      end_time: l.end,
      quantity: l.quantity,
      addons: l.addons,
      components: l.components.map(function (x) { return { item_id: x.item_id, slot_start_time: x.slot_start_time }; }),
      component_labels: l.components.filter(function (x) { return x.slot_start_time; }).map(function (x) { return x.title + ', ' + hm(x.slot_start_time); }),
      meta: l.plate ? { vehicle_plate: l.plate } : {}
    };
  }
  elCart.addEventListener('click', function () { submit('cart'); });
  elGo.addEventListener('click', function () { submit('checkout'); });
  $('bkx-bar-go').addEventListener('click', function () {
    var box = $('bkx-sum');
    if (box && box.scrollIntoView) box.scrollIntoView({ behavior: 'smooth', block: 'center' });
  });

  /* ---------------- start ---------------- */
  if (cfg.focus_product_id && byId[cfg.focus_product_id]) {
    var fv = byId[cfg.focus_product_id].variants[0];
    if (fv) state.qty[key(byId[cfg.focus_product_id], fv)] = 0;
  }
  if (typeof BileteOnlineCart !== 'undefined' && typeof BileteOnlineCart.loadPaymentFeeConfig === 'function') {
    BileteOnlineCart.loadPaymentFeeConfig().then(function (c) { if (c) renderSummary(); }, function () {});
  }
  loadCalendar(today).then(function () {
    var first = null;
    for (var i = 0; i <= 45 && !first; i++) {
      var d = addDays(today, i);
      var c = dayInfo(d);
      if (c.status && c.status !== 'closed' && c.status !== 'full') first = d;
    }
    loadDay(first || today);
  });
  render();
})();
