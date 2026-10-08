/* viaqui.com v2: organizer extra services (/organizator/servicii). Prices from /organizer/services/pricing, the
   services list from /organizer/services/orders, the order window posted to /organizer/services/orders, then card
   payment through /orders/{id}/pay (redirect or POST form to the payment page core returns). Three services are
   shown: promoting one activity, promoting a whole LOCATION (its page and its products, picked from
   /organizer/activities-module/locations) and ad tracking, which is bought for the WHOLE ACCOUNT - it covers every
   location, experience and product the operator sells, so its window has no activity step. Runs inside the organizer
   shell (window.BO_ORG); text from the API is always written as text. */
(function () {
  'use strict';
  var O = window.BO_ORG, root = document.getElementById('ox');
  if (!O || !root) return;
  var F = O.fmt, el = O.el, icon = O.icon;
  var $ = function (id) { return document.getElementById(id); };
  function qsa(sel) { return [].slice.call(root.querySelectorAll(sel)); }

  var TYPES = { featuring: VQ.t('Experience promotion'), location_featuring: VQ.t('Venue promotion'), email: VQ.t('Email marketing'), tracking: VQ.t('Ad tracking'), campaign: VQ.t('Ad campaign') };
  // What an order applies to: one activity, one location, or the whole operator account.
  var SCOPE = { featuring: 'event', location_featuring: 'location', tracking: 'account' };
  var STATUS = { active: [VQ.t('Active'), 'is-ok'], pending_payment: [VQ.t('Awaiting payment'), 'is-wait'], pending: [VQ.t('Processing'), 'is-info'], processing: [VQ.t('Processing'), 'is-info'], completed: [VQ.t('Completed'), 'is-muted'], cancelled: [VQ.t('Cancelled'), 'is-muted'] };
  var LOCS = { home_hero: VQ.t('Home page: Hero'), home_recommendations: VQ.t('Home page: Recommendations'), category: VQ.t('Category page'), city: VQ.t('City page') };
  var PLATS = { facebook: 'Facebook Pixel', google: 'Google Ads', tiktok: 'TikTok Pixel' };
  // [title, what happens for an experience, what happens for a venue]

  var PEEK = {
    home_hero: [VQ.t('Home page: Hero banner'), VQ.t('Your experience appears as the main banner on the home page, visible as soon as the site opens. The position with the highest visibility.'), VQ.t('Your venue appears as the main banner on the home page, visible as soon as the site opens. The position with the highest visibility.')],
    home_recommendations: [VQ.t('Home page: Recommendations section'), VQ.t('Your experience appears first in the "Recommended for you" section on the home page, visible to every visitor of the site.'), VQ.t('Your venue appears first in the "Recommended for you" section on the home page, visible to every visitor of the site.')],
    category: [VQ.t('Category page'), VQ.t('Your experience appears in the "Promoted in [category]" section at the top of the category page and first in its list, with the "Promoted" label. It reaches the people looking for exactly that kind of experience.'), VQ.t('Your venue appears in the "Promoted in [category]" section at the top of the category page and first in its list, with the "Promoted" label. It reaches the people looking for exactly that kind of experience.')],
    city: [VQ.t('City page'), VQ.t('Your experience appears in the "Popular in [city]" section on the city page and first in its list, with the "Promoted" label. It reaches the people looking for things to do in their area.'), VQ.t('Your venue appears in the "Popular in [city]" section on the city page and first in its list, with the "Promoted" label. It reaches the people looking for things to do in their area.')],
  };
  var PLACE_DEFAULT = { home_hero: 120, home_recommendations: 80, category: 60, city: 40 };
  var pricing = { featuring: PLACE_DEFAULT, location_featuring: PLACE_DEFAULT, tracking: { per_platform_monthly: 49, discounts: { 1: 0, 3: 0.1, 6: 0.15, 12: 0.25 } } };
  var events = [], places = [], orders = null, type = '', step = 1, opener = null;

  function txt(v) { return F.flat(v).trim(); }
  /** Money in the currency of the site (euro), written the way the rest of the site writes it. */
  function money(v) { return typeof BileteOnlineUtils !== 'undefined' && BileteOnlineUtils.formatCurrency ? BileteOnlineUtils.formatCurrency(F.toNum(v), true) : '€' + F.num(v); }
  function imgUrl(v) { var u = txt(v); return /^https:\/\//i.test(u) || /^\/(?!\/)/.test(u) ? u : ''; }
  function naiveDay(v) { var m = /^(\d{4}-\d{2}-\d{2})/.exec(String(v || '')); return m ? m[1] : ''; }
  function day(v) { var d = F.dateOf(naiveDay(v) || v); return d ? F.date(d, { day: 'numeric', month: 'short', year: 'numeric' }) : ''; }
  function showErr(id, msg) { $(id).textContent = msg || ''; $(id).hidden = !msg; }
  function busyBtn(btn, on, text) {
    var l = btn.querySelector('[data-label]');
    if (on) { btn.setAttribute('aria-busy', 'true'); if (l) { btn.setAttribute('data-idle', l.textContent); l.textContent = text; } }
    else { btn.removeAttribute('aria-busy'); if (l && btn.hasAttribute('data-idle')) l.textContent = btn.getAttribute('data-idle'); btn.removeAttribute('data-idle'); }
  }
  function placeTable(t) { var p = pricing[t || type]; return p && typeof p === 'object' ? p : PLACE_DEFAULT; }
  function locPrice(k, t) { var p = F.toNum(placeTable(t)[k]); return p > 0 ? p : PLACE_DEFAULT[k] || 40; }
  function minPlace(t) { return Math.min.apply(null, Object.keys(LOCS).map(function (k) { return locPrice(k, t); })); }
  function platPrice() { var p = F.toNum((pricing.tracking || {}).per_platform_monthly); return p > 0 ? p : 49; }
  function discount(months) { var d = (pricing.tracking || {}).discounts || {}; return F.toNum(d[months]) || ({ 3: 0.1, 6: 0.15, 12: 0.25 }[months] || 0); }

  /* =================== RETURN FROM PAYMENT =================== */
  (function () {
    var q = new URLSearchParams(location.search), clean = false;
    if (q.get('featuring_activated') === '1') { banner(VQ.t('Promotion turned on'), VQ.t('Your experience is now shown in the sections you chose.')); clean = true; }
    else if (q.get('payment_success') === '1' || q.get('payment') === 'success') { banner(VQ.t('Payment confirmed'), VQ.t('The service has been turned on. Your experience will appear in the sections you chose.')); clean = true; }
    if (q.get('cancelled') === '1' || q.get('payment') === 'cancel') { $('ox-cancelled').hidden = false; clean = true; }
    if (clean || q.toString()) { try { history.replaceState(null, '', location.pathname); } catch (e) {} }
    function banner(h, p) { $('ox-success-h').textContent = h; $('ox-success-p').textContent = p; $('ox-success').hidden = false; }
  })();
  qsa('[data-dismiss]').forEach(function (b) { b.addEventListener('click', function () { $(b.getAttribute('data-dismiss')).hidden = true; }); });

  /* =================== LOAD =================== */
  O.ready.then(function (ok) {
    if (!ok) return;
    O.api('/organizer/services/pricing', { quiet: true }).then(function (r) {
      var p = r && r.data && (r.data.pricing || r.data);
      if (p && typeof p === 'object') {
        if (p.featuring && typeof p.featuring === 'object') pricing.featuring = p.featuring.pricing || p.featuring;
        if (p.location_featuring && typeof p.location_featuring === 'object') pricing.location_featuring = p.location_featuring.pricing || p.location_featuring;
        if (p.tracking && typeof p.tracking === 'object') pricing.tracking = p.tracking.pricing || p.tracking;
      }
    }, function () {}).then(prices);
    // The operator's own locations and products (activities module; viaqui.com has no events). Quiet: an account
    // without them simply has nothing to promote, and the window says so. A product is promotable when it is sold
    // online: published, approved, not POS-only, at a published location.
    var locNames = {};
    O.api('/organizer/activities-module/locations', { quiet: true }).then(function (r) {
      var d = (r && r.data) || {}, list = Array.isArray(d.locations) ? d.locations : Array.isArray(d) ? d : [];
      var sel = $('ox-place');
      list.forEach(function (l) { if (l && l.id != null) locNames[l.id] = l; });
      places = list.filter(function (l) { return l && l.id != null && l.is_published; });
      if (!places.length) sel.appendChild(el('option', { value: '', disabled: true, text: VQ.t('You have no published venue yet') }));
      places.forEach(function (l) { sel.appendChild(el('option', { value: String(l.id), text: txt(l.name) || VQ.t('Venue #{id}', { id: l.id }) })); });
    }, function () {}).then(function () {
      return O.api('/organizer/activities-module/products', { quiet: true });
    }).then(function (r) {
      var d = (r && r.data) || {}, list = Array.isArray(d.products) ? d.products : [];
      var sel = $('ox-event');
      events = list.filter(function (p) {
        var loc = p && p.location_id != null ? locNames[p.location_id] : null;
        return p && p.id != null && p.is_published && !p.pos_only && (!p.review_status || p.review_status === 'approved') && (!loc || loc.is_published);
      }).map(function (p) {
        var loc = p.location_id != null ? locNames[p.location_id] : null;
        return { id: p.id, name: txt(p.title) || VQ.t('Experience #{id}', { id: p.id }), image: p.image && p.image.url, place: loc ? txt(loc.name) : '', price: F.toNum(p.min_price), type: p.type };
      });
      if (!events.length) sel.appendChild(el('option', { value: '', disabled: true, text: VQ.t('You have no published experience we can promote yet') }));
      events.forEach(function (e) { sel.appendChild(el('option', { value: String(e.id), text: e.place ? e.name + ' · ' + e.place : e.name })); });
    }, function () {});
    loadOrders();
  });
  function prices() {
    $('ox-price-feat').textContent = money(minPlace('featuring'));
    $('ox-price-loc').textContent = money(minPlace('location_featuring'));
    $('ox-price-track').textContent = money(platPrice());
    qsa('[data-price-plat]').forEach(function (n) { n.textContent = VQ.t('{price} / month', { price: money(platPrice()) }); });
  }
  /** The per-placement prices inside the window follow the service being bought. */
  function placePrices() {
    qsa('[data-price-loc]').forEach(function (n) { n.textContent = VQ.t('{price} / day', { price: money(locPrice(n.getAttribute('data-price-loc'))) }); });
  }
  function loadOrders() {
    O.api('/organizer/services/orders').then(function (r) {
      var d = r && r.data;
      orders = Array.isArray(d) ? d : d && Array.isArray(d.data) ? d.data : [];
      drawOrders();
    }, function (err) {
      if (err && err.status === 401) return;
      var body = $('ox-rows'), retry = el('button', { class: 'ox-pill', type: 'button', text: VQ.t('Try again') });
      retry.addEventListener('click', loadOrders);
      body.textContent = '';
      body.appendChild(el('tr', null, el('td', { colspan: 6, class: 'ox-state' }, [VQ.t('We could not load the services.') + ' ', retry])));
    });
  }
  function drawOrders() {
    var body = $('ox-rows'), f = $('ox-filter').value, list = orders.filter(function (s) { return !f || s.type === f; });
    body.textContent = '';
    $('ox-list-p').textContent = orders.length ? (f ? VQ.t('{services} of this type', { services: VQ.n(list.length, 'service', 'services') }) : VQ.n(list.length, 'service', 'services')) + '.' : '';
    if (!list.length) { body.appendChild(el('tr', null, el('td', { colspan: 6, class: 'ox-state', text: orders.length ? VQ.t('No service of this type.') : VQ.t('You have no active services') }))); return; }
    list.forEach(function (s) {
      var id = txt(s.id), st = STATUS[s.status] || [txt(s.status) || '—', 'is-muted'], first = [el('span', { class: 'org-tag is-info', text: TYPES[s.type] || txt(s.type) || VQ.t('Service') })];
      if (s.needs_pixel_setup && /^[\w-]+$/.test(id)) {
        var missing = (Array.isArray(s.missing_pixel_platforms) ? s.missing_pixel_platforms : []).map(function (p) { return PLATS[p] ? PLATS[p].replace(' Pixel', '') : txt(p); }).join(', ');
        first.push(el('br'), el('a', { class: 'ox-alert', href: VQ.url('/organizator/services/' + id), title: VQ.t('Add the Pixel ID') }, [icon('warning-circle'), missing ? VQ.t('Pixel ID needed: {platforms}', { platforms: missing }) : VQ.t('Pixel ID needed')]));
      }
      var start = day(s.service_start_date), end = day(s.service_end_date);
      var applies = s.scope === 'account' ? VQ.t('Whole account') : txt(s.location_name) || txt(s.activity_name) || txt(s.event_name) || '—';
      body.appendChild(el('tr', null, [
        el('td', null, first),
        el('td', { text: applies }),
        el('td', { text: txt(s.details) || '—' }),
        el('td', { text: start || end ? (start || '—') + ' - ' + (end || '—') : '—' }),
        el('td', null, el('span', { class: 'org-tag ' + st[1], text: st[0] })),
        el('td', { class: 'ox-right' }, /^[\w-]+$/.test(id) ? el('a', { class: 'ox-pill', href: VQ.url('/organizator/services/' + id), 'aria-label': VQ.t('View the service details') }, [icon('eye'), VQ.t('View')]) : null),
      ]));
    });
  }
  $('ox-filter').addEventListener('change', function () { if (orders) drawOrders(); });

  /* =================== ORDER WINDOW =================== */
  var TITLES = { featuring: VQ.t('Experience promotion'), location_featuring: VQ.t('Venue promotion'), tracking: VQ.t('Ad campaign tracking') };
  var STEP_LABELS = {
    event: [VQ.t('Choose the experience'), VQ.t('Set up'), VQ.t('Payment')],
    location: [VQ.t('Choose the venue'), VQ.t('Set up'), VQ.t('Payment')],
    account: [VQ.t('Set up'), VQ.t('Payment')],
  };
  function scope() { return SCOPE[type] || 'event'; }
  /** Account-level services skip the first step: they are bought for everything the operator sells. */
  function stepList() { return scope() === 'account' ? [2, 3] : [1, 2, 3]; }
  qsa('[data-open]').forEach(function (b) { b.addEventListener('click', function () { openWizard(b.getAttribute('data-open'), b); }); });
  function openWizard(t, from) {
    type = t;
    opener = from;
    step = stepList()[0];
    $('ox-form').reset();
    $('ox-d-h').textContent = TITLES[t] || VQ.t('Set up the service');
    $('ox-feat').hidden = !isPlacement();
    $('ox-track').hidden = t !== 'tracking';
    $('ox-feat-legend').textContent = scope() === 'location' ? VQ.t('Where do you want the venue to appear?') : VQ.t('Where do you want the experience to appear?');
    $('ox-pick-event').hidden = scope() !== 'event';
    $('ox-pick-place').hidden = scope() !== 'location';
    qsa('[id^="ox-pixel-f-"]').forEach(function (n) { n.hidden = true; });
    $('ox-ev').hidden = true;
    ['ox-event-err', 'ox-place-err', 'ox-loc-err', 'ox-dates-err', 'ox-plat-err', 'ox-form-err'].forEach(function (id) { showErr(id, ''); });
    $('ox-start').min = $('ox-end').min = F.ymd();
    placePrices();
    payMethod();
    show();
    $('ox-d').showModal();
    (scope() === 'location' ? $('ox-place') : scope() === 'event' ? $('ox-event') : root.querySelector('input[name="ox-plat"]')).focus();
  }
  function isPlacement() { return type === 'featuring' || type === 'location_featuring'; }
  function show() {
    var list = stepList();
    [1, 2, 3].forEach(function (i) { $('ox-step-' + i).hidden = i !== step; });
    qsa('.ox-steps li').forEach(function (li) {
      var i = Number(li.getAttribute('data-step')), at = list.indexOf(i);
      li.hidden = at < 0;
      if (at < 0) return;
      li.querySelector('b').textContent = String(at + 1);
      li.querySelector('[data-step-label]').textContent = STEP_LABELS[scope()][at];
      li.classList.toggle('is-on', i === step);
      li.classList.toggle('is-past', i < step);
    });
    $('ox-back').hidden = step === list[0];
    $('ox-next').hidden = step === 3;
    $('ox-pay').hidden = step !== 3;
  }
  function eventOf() { var id = $('ox-event').value; return events.filter(function (e) { return String(e.id) === id; })[0] || null; }
  function placeOf() { var id = $('ox-place').value; return places.filter(function (l) { return String(l.id) === id; })[0] || null; }
  /** Name shown for whatever the order applies to - an activity, a location, or the whole account. */
  function subjectName() {
    var e = eventOf(), l = placeOf();
    return scope() === 'account' ? VQ.t('Your whole account') : scope() === 'location' ? (l ? txt(l.name) : '') : (e ? e.name : '');
  }
  $('ox-event').addEventListener('change', function () {
    var e = eventOf();
    showErr('ox-event-err', '');
    $('ox-ev').hidden = !e;
    if (!e) return;
    var box = $('ox-ev-img'), src = imgUrl(e.image);
    box.textContent = '';
    if (src) box.appendChild(el('img', { src: src, alt: '' }));
    $('ox-ev-name').textContent = e.name;
    $('ox-ev-date').textContent = e.price > 0 ? VQ.t('from {price}', { price: money(e.price) }) : '';
    $('ox-ev-venue').textContent = e.place;
  });
  $('ox-place').addEventListener('change', function () {
    var l = placeOf();
    showErr('ox-place-err', '');
    $('ox-ev').hidden = !l;
    if (!l) return;
    var box = $('ox-ev-img'), src = imgUrl(l.cover_image);
    box.textContent = '';
    if (src) box.appendChild(el('img', { src: src, alt: '' }));
    $('ox-ev-name').textContent = txt(l.name);
    $('ox-ev-date').textContent = VQ.n(Math.round(F.toNum(l.products_count)), 'product', 'products');
    $('ox-ev-venue').textContent = txt(l.address);
  });
  qsa('input[name="ox-plat"]').forEach(function (c) {
    c.addEventListener('change', function () {
      var f = $('ox-pixel-f-' + c.value);
      f.hidden = !c.checked;
      if (!c.checked) $('ox-pixel-' + c.value).value = '';
      showErr('ox-plat-err', '');
    });
  });
  qsa('input[name="ox-loc"]').forEach(function (c) { c.addEventListener('change', function () { showErr('ox-loc-err', ''); }); });
  function checked(name) { return qsa('input[name="' + name + '"]').filter(function (c) { return c.checked; }).map(function (c) { return c.value; }); }
  function validate() {
    if (step === 1) {
      if (scope() === 'location') {
        if (!placeOf()) { showErr('ox-place-err', places.length ? VQ.t('Choose the venue.') : VQ.t('You have no published venue we can promote yet.')); $('ox-place').focus(); return false; }
        return true;
      }
      if (!eventOf()) { showErr('ox-event-err', VQ.t('Choose the experience.')); $('ox-event').focus(); return false; }
      return true;
    }
    if (isPlacement()) {
      var start = $('ox-start').value, end = $('ox-end').value;
      if (!checked('ox-loc').length) { showErr('ox-loc-err', VQ.t('Choose at least one placement.')); root.querySelector('input[name="ox-loc"]').focus(); return false; }
      var msg = !start || !end ? VQ.t('Choose the promotion period.') : start < F.ymd() ? VQ.t('The start date cannot be in the past.') : end < start ? VQ.t('The end date cannot be before the start date.') : '';
      showErr('ox-dates-err', msg);
      if (msg) { (!start ? $('ox-start') : $('ox-end')).focus(); return false; }
    }
    if (type === 'tracking' && !checked('ox-plat').length) { showErr('ox-plat-err', VQ.t('Choose at least one platform.')); root.querySelector('input[name="ox-plat"]').focus(); return false; }
    return true;
  }
  /** Both dates included: 1-3 October is 3 days, the same count core charges (start 00:00, end 23:59). */
  function days() {
    var s = new Date($('ox-start').value + 'T00:00:00Z'), e = new Date($('ox-end').value + 'T00:00:00Z');
    return Math.max(Math.round((e - s) / 86400000) + 1, 1);
  }
  function summary() {
    var e = eventOf(), s = scope();
    var rows = [[s === 'account' ? VQ.t('Applies to') : s === 'location' ? VQ.t('Venue') : VQ.t('Experience'), subjectName()]], total = 0;
    if (s === 'event' && e && e.place) rows.push([VQ.t('Venue'), e.place]);
    if (isPlacement()) {
      var n = days();
      checked('ox-loc').forEach(function (k) { var p = locPrice(k) * n; total += p; rows.push([LOCS[k] + ' (' + VQ.n(n, 'day', 'days') + ')', money(p)]); });
    } else {
      var m = Number($('ox-duration').value), off = discount(m);
      checked('ox-plat').forEach(function (k) { var p = Math.round(platPrice() * m * (1 - off) * 100) / 100; total += p; rows.push([PLATS[k] + ' (' + VQ.n(m, 'month', 'months') + ')', money(p)]); });
    }
    var dl = $('ox-summary');
    dl.textContent = '';
    rows.forEach(function (r) { dl.appendChild(el('div', null, [el('dt', { text: r[0] }), el('dd', { text: r[1] })])); });
    $('ox-total').textContent = money(Math.round(total * 100) / 100);
  }
  $('ox-next').addEventListener('click', function () {
    if (!validate()) return;
    if (step === 2) summary();
    var list = stepList();
    step = list[Math.min(list.indexOf(step) + 1, list.length - 1)];
    show();
    var first = $('ox-step-' + step).querySelector('input:not([type="hidden"]), select');
    if (first) first.focus();
  });
  $('ox-back').addEventListener('click', function () {
    var list = stepList();
    step = list[Math.max(list.indexOf(step) - 1, 0)];
    showErr('ox-form-err', '');
    show();
  });
  function payMethod() {
    var card = (root.querySelector('input[name="ox-pay"]:checked') || {}).value !== 'transfer';
    $('ox-pay-card').hidden = !card;
    $('ox-pay-transfer').hidden = card;
    $('ox-pay').querySelector('[data-label]').textContent = card ? VQ.t('Pay now') : VQ.t('Send order');
  }
  qsa('input[name="ox-pay"]').forEach(function (r) { r.addEventListener('change', payMethod); });

  $('ox-form').addEventListener('submit', function (ev) {
    ev.preventDefault();
    var btn = $('ox-pay'), method = root.querySelector('input[name="ox-pay"]:checked').value;
    var e = eventOf(), l = placeOf(), s = scope();
    if (btn.getAttribute('aria-busy') === 'true') return;
    if (s === 'event' && !e) return;
    if (s === 'location' && !l) return;
    showErr('ox-form-err', '');
    // Ad tracking is bought for the whole account, so it carries no activity; a location promotion carries the
    // location instead. Core accepts both only for viaqui.com (activities module).
    var body = { service_type: type, payment_method: method };
    if (s === 'event') body.activity_id = Number(e.id);
    if (s === 'location') body.location_id = Number(l.id);
    if (isPlacement()) {
      // Whole days, both included: the promotion shows from the first day to the end of the last one.
      var st = $('ox-start').value, en = $('ox-end').value;
      body.config = { locations: checked('ox-loc'), start_date: st + 'T00:00', end_date: en + 'T23:59' };
    } else {
      var plats = checked('ox-plat'), ids = {};
      plats.forEach(function (p) { var v = $('ox-pixel-' + p).value.trim(); if (v) ids[p] = v; });
      body.config = { platforms: plats, duration_months: Number($('ox-duration').value) || 1 };
      if (Object.keys(ids).length) body.config.pixel_ids = ids;
    }
    busyBtn(btn, true, VQ.t('Processing…'));
    $('ox-d').setAttribute('data-busy', '');
    O.api('/organizer/services/orders', { method: 'POST', body: body }).then(function (r) {
      var order = r && r.data && r.data.order;
      if (!order || !order.id) throw { status: -1 };
      if (method === 'card' && F.toNum(order.total) > 0) {
        busyBtn(btn, false);
        busyBtn(btn, true, VQ.t('Redirecting to payment…'));
        return O.api('/organizer/services/orders/' + encodeURIComponent(order.id) + '/pay', { method: 'POST', body: { return_url: location.origin + VQ.url('/organizator/servicii') + '?payment=success', cancel_url: location.origin + VQ.url('/organizator/servicii') + '?cancelled=1' } }).then(function (p) {
          var d = (p && p.data) || {}, url = txt(d.payment_url);
          if (!/^https:\/\//i.test(url)) throw { status: -2 };
          if (String(d.method).toUpperCase() === 'POST' && d.form_data && typeof d.form_data === 'object') {
            var form = el('form', { method: 'POST', action: url, hidden: true });
            Object.keys(d.form_data).forEach(function (k) { form.appendChild(el('input', { type: 'hidden', name: k, value: String(d.form_data[k]) })); });
            document.body.appendChild(form);
            form.submit();
          } else location.href = url;
          return 'redirect';
        }, function (err) {
          loadOrders();
          throw { status: err && err.status, pay: true, message: err && err.message };
        });
      }
      $('ox-d').removeAttribute('data-busy');
      busyBtn(btn, false);
      $('ox-d').close();
      O.flash(method === 'transfer' ? VQ.t('Your order has been registered. We will email you the instructions for paying by bank transfer.') : VQ.t('The service has been turned on.'));
      loadOrders();
    }).catch(function (err) {
      $('ox-d').removeAttribute('data-busy');
      busyBtn(btn, false);
      if (err && err.status === 401) return;
      var s = err && err.status, m = String((err && err.message) || '');
      var text = err && err.pay ? (/payment method|configuration/i.test(m) || s === 400 ? VQ.t('Your order has been saved, but card payment is not available right now. You will find it under "My orders": choose bank transfer or try again later.') : VQ.t('Your order has been saved, but we could not open the payment. You will find it under "My orders" and can pay for it from there.'))
        : s === -2 ? VQ.t('We did not receive the payment address. You will find the order under "My orders".')
        : s === 409 ? fullText(err)
        : s === 404 ? (scope() === 'location' ? VQ.t('The venue no longer exists or is not yours.') : VQ.t('The experience no longer exists or is not yours.'))
        : s === 400 ? VQ.t('The service is not available at the moment.')
        : s === 422 ? VQ.t('Some details were not accepted. Check them and try again.')
        : VQ.t('We could not register the order. Try again.');
      showErr('ox-form-err', text);
    });
  });

  /** Placements already holding as many promotions as they show at once, for some day of the chosen period. */
  function fullText(err) {
    var e = (err && (err.errors || (err.data && err.data.errors))) || {}, list = Array.isArray(e.full_placements) ? e.full_placements : [];
    var names = list.map(function (k) { return LOCS[k] || txt(k); }).filter(Boolean);
    return (names.length ? VQ.t('Taken in the chosen period: {placements}.', { placements: names.join(', ') }) : VQ.t('Some placements are taken in the chosen period.')) + ' ' + VQ.t('Choose other dates or another placement.');
  }

  /* =================== PLACEMENT PREVIEW =================== */
  qsa('[data-peek]').forEach(function (b) { b.addEventListener('click', function () { peek(b.getAttribute('data-peek'), b); }); });
  function peek(k, from) {
    var info = PEEK[k], e = eventOf(), l = placeOf(), loc = scope() === 'location';
    var subject = loc ? VQ.t('Your venue') : VQ.t('Your experience');
    var name = subjectName() || subject, src = loc ? (l ? imgUrl(l.cover_image) : '') : (e ? imgUrl(e.image) : '');
    var meta = loc
      ? (l ? [txt(l.address), VQ.n(Math.round(F.toNum(l.products_count)), 'product', 'products')].filter(Boolean).join(' · ') : VQ.t('The address and products of the venue'))
      : (e ? [e.place, e.price > 0 ? VQ.t('from {price}', { price: money(e.price) }) : ''].filter(Boolean).join(' · ') : VQ.t('The venue and price of the experience'));
    $('ox-peek-h').textContent = info[0];
    $('ox-peek-p').textContent = info[loc ? 2 : 1];
    var box = $('ox-mock'), hot = el('div', { class: 'ox-mock-hot' + (k === 'home_hero' ? ' is-banner' : '') }, [src ? el('img', { src: src, alt: '' }) : null, el('span', { class: 'ox-mock-badge', text: loc ? VQ.t('★ Promoted · your venue') : VQ.t('★ Promoted · your experience') }), el('b', { text: name }), el('small', { text: meta })]);
    var bar = el('div', { class: 'ox-mock-bar' }, [el('b', { text: 'viaqui.com' }), document.createTextNode(k === 'category' ? VQ.t('Home › Category') : k === 'city' ? VQ.t('Home › City') : VQ.t('Home page'))]);
    box.textContent = '';
    box.appendChild(bar);
    if (k === 'home_hero') { box.appendChild(hot); box.appendChild(el('div', { class: 'ox-mock-row' }, [1, 2, 3, 4].map(function () { return el('div', { class: 'ox-mock-ghost' }); }))); }
    else if (k === 'home_recommendations') { box.appendChild(el('div', { class: 'ox-mock-ghost' })); box.appendChild(el('div', { class: 'ox-mock-row' }, [hot, el('div', { class: 'ox-mock-ghost' }), el('div', { class: 'ox-mock-ghost' }), el('div', { class: 'ox-mock-ghost' })])); }
    else { box.appendChild(el('div', { class: 'ox-mock-bar', text: k === 'category' ? VQ.t('Recommended in the category') : VQ.t('Popular in your city') })); box.appendChild(hot); box.appendChild(el('div', { class: 'ox-mock-row' }, [1, 2, 3, 4].map(function () { return el('div', { class: 'ox-mock-ghost' }); }))); }
    $('ox-peek-d').showModal();
    $('ox-peek-d').querySelector('.ox-x').focus();
    $('ox-peek-d').setAttribute('data-from', '');
    $('ox-peek-d')._from = from;
  }

  /* =================== DIALOGS =================== */
  ['ox-d', 'ox-peek-d'].forEach(function (id) {
    var d = $(id);
    d.addEventListener('click', function (e) { if (!d.hasAttribute('data-busy') && e.target.closest('[data-close]')) d.close(); });
    d.addEventListener('cancel', function (e) { if (d.hasAttribute('data-busy')) e.preventDefault(); });
    d.addEventListener('close', function () {
      var back = id === 'ox-peek-d' ? d._from : opener;
      if (back && document.contains(back) && back.getClientRects().length) back.focus();
    });
  });
})();
