/* bilete.online v2: organizer extra services (/organizator/servicii). Prices from /organizer/services/pricing, the
   services list from /organizer/services/orders, the three-step order (activity, configuration, payment) posted to
   /organizer/services/orders, then card payment through /orders/{id}/pay (redirect or POST form to the payment page
   core returns). Promotion and ad tracking are the services shown, as on the old page. Runs inside the organizer shell
   (window.BO_ORG); text from the API is always written as text. */
(function () {
  'use strict';
  var O = window.BO_ORG, root = document.getElementById('ox');
  if (!O || !root) return;
  var F = O.fmt, el = O.el, icon = O.icon;
  var $ = function (id) { return document.getElementById(id); };
  function qsa(sel) { return [].slice.call(root.querySelectorAll(sel)); }

  var TYPES = { featuring: 'Promovare', email: 'Email marketing', tracking: 'Ad tracking', campaign: 'Campanie ads' };
  var STATUS = { active: ['Activ', 'is-ok'], pending_payment: ['Așteaptă plata', 'is-wait'], pending: ['În procesare', 'is-info'], processing: ['În procesare', 'is-info'], completed: ['Finalizat', 'is-muted'], cancelled: ['Anulat', 'is-muted'] };
  var LOCS = { home_hero: 'Prima pagină - Hero', home_recommendations: 'Prima pagină - Recomandări', category: 'Pagina categorie activitate', city: 'Pagina oraș activitate' };
  var PLATS = { facebook: 'Facebook Pixel', google: 'Google Ads', tiktok: 'TikTok Pixel' };
  var PEEK = {
    home_hero: ['Prima pagină — Hero Banner', 'Activitatea ta apare ca banner principal pe prima pagină, vizibilă imediat la accesarea site-ului. Poziția cu cea mai mare vizibilitate.'],
    home_recommendations: ['Prima pagină — Secțiunea Recomandări', 'Activitatea ta apare prima în secțiunea „Recomandate pentru tine” de pe prima pagină, vizibilă pentru toți vizitatorii site-ului.'],
    category: ['Pagina Categorie Activitate', 'Activitatea ta apare ca activitate recomandată la începutul paginii de categorie. Ajunge la publicul targetat pe tipul de activitate.'],
    city: ['Pagina Oraș Activitate', 'Activitatea ta apare în secțiunea „Populare în [oraș]” pe pagina dedicată orașului activității. Ajunge la utilizatorii care caută activități în zona lor.'],
  };
  var pricing = { featuring: { home_hero: 120, home_recommendations: 80, category: 60, city: 40 }, tracking: { per_platform_monthly: 49, discounts: { 1: 0, 3: 0.1, 6: 0.15, 12: 0.25 } } };
  var events = [], orders = null, type = '', step = 1, opener = null;

  function txt(v) { return F.flat(v).trim(); }
  function imgUrl(v) { var u = txt(v); return /^https:\/\//i.test(u) || /^\/(?!\/)/.test(u) ? u : ''; }
  function naiveDay(v) { var m = /^(\d{4}-\d{2}-\d{2})/.exec(String(v || '')); return m ? m[1] : ''; }
  function day(v) { var d = F.dateOf(naiveDay(v) || v); return d ? F.date(d, { day: 'numeric', month: 'short', year: 'numeric' }) : ''; }
  function showErr(id, msg) { $(id).textContent = msg || ''; $(id).hidden = !msg; }
  function busyBtn(btn, on, text) {
    var l = btn.querySelector('[data-label]');
    if (on) { btn.setAttribute('aria-busy', 'true'); if (l) { btn.setAttribute('data-idle', l.textContent); l.textContent = text; } }
    else { btn.removeAttribute('aria-busy'); if (l && btn.hasAttribute('data-idle')) l.textContent = btn.getAttribute('data-idle'); btn.removeAttribute('data-idle'); }
  }
  function locPrice(k) { var p = F.toNum((pricing.featuring || {})[k]); return p > 0 ? p : 40; }
  function platPrice() { var p = F.toNum((pricing.tracking || {}).per_platform_monthly); return p > 0 ? p : 49; }
  function discount(months) { var d = (pricing.tracking || {}).discounts || {}; return F.toNum(d[months]) || ({ 3: 0.1, 6: 0.15, 12: 0.25 }[months] || 0); }

  /* =================== RETURN FROM PAYMENT =================== */
  (function () {
    var q = new URLSearchParams(location.search), clean = false;
    if (q.get('featuring_activated') === '1') { banner('Promovare activată!', 'Activitatea ta este acum afișată în secțiunile selectate.'); clean = true; }
    else if (q.get('payment_success') === '1' || q.get('payment') === 'success') { banner('Plata confirmată!', 'Serviciul a fost activat cu succes. Activitatea ta va apărea în secțiunile selectate.'); clean = true; }
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
        if (p.tracking && typeof p.tracking === 'object') pricing.tracking = p.tracking.pricing || p.tracking;
      }
    }, function () {}).then(prices);
    O.api('/organizer/events?per_page=50', { quiet: true }).then(function (r) {
      var sel = $('ox-event'), list = Array.isArray(r && r.data) ? r.data : [];
      events = list.filter(function (e) { var end = naiveDay(e.ends_at || e.starts_at); return e && e.id != null && !e.is_cancelled && !e.is_past && e.is_editable !== false && (!end || end >= F.ymd()); });
      if (!events.length) sel.appendChild(el('option', { value: '', disabled: true, text: 'Momentan nu ai activități în derulare pentru care să faci promovare' }));
      events.forEach(function (e) { sel.appendChild(el('option', { value: String(e.id), text: txt(e.name || e.title) || 'Activitatea #' + e.id })); });
    }, function () {});
    loadOrders();
  });
  function prices() {
    var feat = Object.keys(LOCS).map(locPrice);
    $('ox-price-feat').textContent = F.money(Math.min.apply(null, feat));
    $('ox-price-track').textContent = F.money(platPrice());
    qsa('[data-price-loc]').forEach(function (n) { n.textContent = F.money(locPrice(n.getAttribute('data-price-loc'))) + ' / zi'; });
    qsa('[data-price-plat]').forEach(function (n) { n.textContent = F.money(platPrice()) + ' / lună'; });
  }
  function loadOrders() {
    O.api('/organizer/services/orders').then(function (r) {
      var d = r && r.data;
      orders = Array.isArray(d) ? d : d && Array.isArray(d.data) ? d.data : [];
      drawOrders();
    }, function (err) {
      if (err && err.status === 401) return;
      var body = $('ox-rows'), retry = el('button', { class: 'ox-pill', type: 'button', text: 'Reîncearcă' });
      retry.addEventListener('click', loadOrders);
      body.textContent = '';
      body.appendChild(el('tr', null, el('td', { colspan: 6, class: 'ox-state' }, ['Nu am putut încărca serviciile. ', retry])));
    });
  }
  function drawOrders() {
    var body = $('ox-rows'), f = $('ox-filter').value, list = orders.filter(function (s) { return !f || s.type === f; });
    body.textContent = '';
    $('ox-list-p').textContent = orders.length ? F.count(list.length, 'serviciu', 'servicii') + (f ? ' de acest tip' : '') + '.' : '';
    if (!list.length) { body.appendChild(el('tr', null, el('td', { colspan: 6, class: 'ox-state', text: orders.length ? 'Niciun serviciu de acest tip.' : 'Nu ai servicii active' }))); return; }
    list.forEach(function (s) {
      var id = txt(s.id), st = STATUS[s.status] || [txt(s.status) || '—', 'is-muted'], first = [el('span', { class: 'org-tag is-info', text: TYPES[s.type] || txt(s.type) || 'Serviciu' })];
      if (s.needs_pixel_setup && /^[\w-]+$/.test(id)) {
        var missing = (Array.isArray(s.missing_pixel_platforms) ? s.missing_pixel_platforms : []).map(function (p) { return PLATS[p] ? PLATS[p].replace(' Pixel', '') : txt(p); }).join(', ');
        first.push(el('br'), el('a', { class: 'ox-alert', href: '/organizator/services/' + id, title: 'Completează Pixel ID' }, [icon('warning-circle'), 'Necesită Pixel ID' + (missing ? ': ' + missing : '')]));
      }
      var start = day(s.service_start_date), end = day(s.service_end_date);
      body.appendChild(el('tr', null, [
        el('td', null, first),
        el('td', { text: txt(s.event_name) || '—' }),
        el('td', { text: txt(s.details) || '—' }),
        el('td', { text: start || end ? (start || '—') + ' - ' + (end || '—') : '—' }),
        el('td', null, el('span', { class: 'org-tag ' + st[1], text: st[0] })),
        el('td', { class: 'ox-right' }, /^[\w-]+$/.test(id) ? el('a', { class: 'ox-pill', href: '/organizator/services/' + id, 'aria-label': 'Vezi detaliile serviciului' }, [icon('eye'), 'Vezi']) : null),
      ]));
    });
  }
  $('ox-filter').addEventListener('change', function () { if (orders) drawOrders(); });

  /* =================== ORDER WINDOW =================== */
  var TITLES = { featuring: 'Promovare activitate', tracking: 'Tracking campanii ads' };
  qsa('[data-open]').forEach(function (b) { b.addEventListener('click', function () { openWizard(b.getAttribute('data-open'), b); }); });
  function openWizard(t, from) {
    type = t;
    step = 1;
    opener = from;
    $('ox-form').reset();
    $('ox-d-h').textContent = TITLES[t] || 'Configurează serviciul';
    $('ox-feat').hidden = t !== 'featuring';
    $('ox-track').hidden = t !== 'tracking';
    qsa('[id^="ox-pixel-f-"]').forEach(function (n) { n.hidden = true; });
    $('ox-ev').hidden = true;
    ['ox-event-err', 'ox-loc-err', 'ox-dates-err', 'ox-plat-err', 'ox-form-err'].forEach(function (id) { showErr(id, ''); });
    $('ox-start').min = $('ox-end').min = F.ymd();
    payMethod();
    show();
    $('ox-d').showModal();
    $('ox-event').focus();
  }
  function show() {
    [1, 2, 3].forEach(function (i) { $('ox-step-' + i).hidden = i !== step; });
    qsa('.ox-steps li').forEach(function (li) { var i = Number(li.getAttribute('data-step')); li.classList.toggle('is-on', i === step); li.classList.toggle('is-past', i < step); });
    $('ox-back').hidden = step === 1;
    $('ox-next').hidden = step === 3;
    $('ox-pay').hidden = step !== 3;
  }
  function eventOf() { var id = $('ox-event').value; return events.filter(function (e) { return String(e.id) === id; })[0] || null; }
  $('ox-event').addEventListener('change', function () {
    var e = eventOf();
    showErr('ox-event-err', '');
    $('ox-ev').hidden = !e;
    if (!e) return;
    var box = $('ox-ev-img'), src = imgUrl(e.image || e.poster_url);
    box.textContent = '';
    if (src) box.appendChild(el('img', { src: src, alt: '' }));
    $('ox-ev-name').textContent = txt(e.name || e.title);
    $('ox-ev-date').textContent = day(e.starts_at || e.date);
    $('ox-ev-venue').textContent = txt(e.venue_name) || txt(e.venue && e.venue.name) || txt(e.venue_city);
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
      if (!eventOf()) { showErr('ox-event-err', 'Alege activitatea.'); $('ox-event').focus(); return false; }
      return true;
    }
    if (type === 'featuring') {
      var start = $('ox-start').value, end = $('ox-end').value;
      if (!checked('ox-loc').length) { showErr('ox-loc-err', 'Alege cel puțin un loc de afișare.'); root.querySelector('input[name="ox-loc"]').focus(); return false; }
      var msg = !start || !end ? 'Alege perioada de promovare.' : start < F.ymd() ? 'Data de început nu poate fi în trecut.' : end <= start ? 'Data de sfârșit trebuie să fie după data de început.' : '';
      showErr('ox-dates-err', msg);
      if (msg) { (!start ? $('ox-start') : $('ox-end')).focus(); return false; }
    }
    if (type === 'tracking' && !checked('ox-plat').length) { showErr('ox-plat-err', 'Alege cel puțin o platformă.'); root.querySelector('input[name="ox-plat"]').focus(); return false; }
    return true;
  }
  function days() {
    var s = new Date($('ox-start').value + 'T00:00:00Z'), e = new Date($('ox-end').value + 'T00:00:00Z');
    return Math.max(Math.round((e - s) / 86400000), 1);
  }
  function summary() {
    var e = eventOf(), rows = [['Activitate', txt(e && (e.name || e.title))]], total = 0;
    var cat = e && (txt(e.category_name) || txt(e.category && (e.category.name || e.category)));
    if (cat) rows.push(['Categorie', cat]);
    if (type === 'featuring') {
      var n = days();
      checked('ox-loc').forEach(function (k) { var p = locPrice(k) * n; total += p; rows.push([LOCS[k] + ' (' + F.count(n, 'zi', 'zile') + ')', F.money(p)]); });
    } else {
      var m = Number($('ox-duration').value), off = discount(m);
      checked('ox-plat').forEach(function (k) { var p = Math.round(platPrice() * m * (1 - off) * 100) / 100; total += p; rows.push([PLATS[k] + ' (' + F.count(m, 'lună', 'luni') + ')', F.money(p)]); });
    }
    var dl = $('ox-summary');
    dl.textContent = '';
    rows.forEach(function (r) { dl.appendChild(el('div', null, [el('dt', { text: r[0] }), el('dd', { text: r[1] })])); });
    $('ox-total').textContent = F.money(Math.round(total * 100) / 100);
  }
  $('ox-next').addEventListener('click', function () {
    if (!validate()) return;
    if (step === 2) summary();
    step++;
    show();
    var first = $('ox-step-' + step).querySelector('input:not([type="hidden"]), select');
    if (first) first.focus();
  });
  $('ox-back').addEventListener('click', function () { step--; showErr('ox-form-err', ''); show(); });
  function payMethod() {
    var card = (root.querySelector('input[name="ox-pay"]:checked') || {}).value !== 'transfer';
    $('ox-pay-card').hidden = !card;
    $('ox-pay-transfer').hidden = card;
    $('ox-pay').querySelector('[data-label]').textContent = card ? 'Plătește acum' : 'Trimite comanda';
  }
  qsa('input[name="ox-pay"]').forEach(function (r) { r.addEventListener('change', payMethod); });

  $('ox-form').addEventListener('submit', function (ev) {
    ev.preventDefault();
    var btn = $('ox-pay'), method = root.querySelector('input[name="ox-pay"]:checked').value, e = eventOf();
    if (btn.getAttribute('aria-busy') === 'true' || !e) return;
    showErr('ox-form-err', '');
    var body = { service_type: type, event_id: Number(e.id), payment_method: method };
    if (type === 'featuring') {
      var s = $('ox-start').value, en = $('ox-end').value, startAt, endAt;
      if (s === F.ymd()) { var now = new Date(), hm = String(now.getHours()).padStart(2, '0') + ':' + String(now.getMinutes()).padStart(2, '0'); startAt = s + 'T' + hm; endAt = en + 'T' + hm; }
      else { startAt = s + 'T07:00'; endAt = en + 'T00:00'; }
      body.config = { locations: checked('ox-loc'), start_date: startAt, end_date: endAt };
    } else {
      var plats = checked('ox-plat'), ids = {};
      plats.forEach(function (p) { var v = $('ox-pixel-' + p).value.trim(); if (v) ids[p] = v; });
      body.config = { platforms: plats, duration_months: Number($('ox-duration').value) || 1 };
      if (Object.keys(ids).length) body.config.pixel_ids = ids;
    }
    busyBtn(btn, true, 'Se procesează…');
    $('ox-d').setAttribute('data-busy', '');
    O.api('/organizer/services/orders', { method: 'POST', body: body }).then(function (r) {
      var order = r && r.data && r.data.order;
      if (!order || !order.id) throw { status: -1 };
      if (method === 'card' && F.toNum(order.total) > 0) {
        busyBtn(btn, false);
        busyBtn(btn, true, 'Se redirecționează către plată…');
        return O.api('/organizer/services/orders/' + encodeURIComponent(order.id) + '/pay', { method: 'POST', body: { return_url: location.origin + '/organizator/servicii?payment=success', cancel_url: location.origin + '/organizator/servicii?cancelled=1' } }).then(function (p) {
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
      O.flash(method === 'transfer' ? 'Comanda a fost înregistrată. Îți trimitem pe email instrucțiunile de plată prin transfer bancar.' : 'Serviciul a fost activat cu succes.');
      loadOrders();
    }).catch(function (err) {
      $('ox-d').removeAttribute('data-busy');
      busyBtn(btn, false);
      if (err && err.status === 401) return;
      var s = err && err.status, m = String((err && err.message) || '');
      var text = err && err.pay ? (/payment method|configuration/i.test(m) || s === 400 ? 'Comanda a fost salvată, dar plata cu cardul nu e disponibilă acum. O găsești în „Comenzile mele”; alege transfer bancar sau încearcă mai târziu.' : 'Comanda a fost salvată, dar nu am putut deschide plata. O găsești în „Comenzile mele” și o poți plăti de acolo.')
        : s === -2 ? 'Nu am primit adresa de plată. Comanda o găsești în „Comenzile mele”.'
        : s === 404 ? 'Activitatea nu mai există sau nu îți aparține.'
        : s === 400 ? 'Serviciul nu e disponibil momentan.'
        : s === 422 ? 'Unele date nu sunt acceptate. Verifică-le și încearcă din nou.'
        : 'Nu am putut înregistra comanda. Încearcă din nou.';
      showErr('ox-form-err', text);
    });
  });

  /* =================== PLACEMENT PREVIEW =================== */
  qsa('[data-peek]').forEach(function (b) { b.addEventListener('click', function () { peek(b.getAttribute('data-peek'), b); }); });
  function peek(k, from) {
    var info = PEEK[k], e = eventOf(), name = e ? txt(e.name || e.title) : 'Activitatea ta', src = e ? imgUrl(e.image || e.poster_url) : '';
    var meta = e ? [day(e.starts_at || e.date), txt(e.venue_name) || txt(e.venue_city)].filter(Boolean).join(' · ') : 'Data și locația activității';
    $('ox-peek-h').textContent = info[0];
    $('ox-peek-p').textContent = info[1];
    var box = $('ox-mock'), hot = el('div', { class: 'ox-mock-hot' + (k === 'home_hero' ? ' is-banner' : '') }, [src ? el('img', { src: src, alt: '' }) : null, el('span', { class: 'ox-mock-badge', text: '★ Promovat · activitatea ta' }), el('b', { text: name }), el('small', { text: meta })]);
    var bar = el('div', { class: 'ox-mock-bar' }, [el('b', { text: 'bilete.online' }), document.createTextNode(k === 'category' ? 'Acasă › Categorie' : k === 'city' ? 'Acasă › Orașul activității' : 'Prima pagină')]);
    box.textContent = '';
    box.appendChild(bar);
    if (k === 'home_hero') { box.appendChild(hot); box.appendChild(el('div', { class: 'ox-mock-row' }, [1, 2, 3, 4].map(function () { return el('div', { class: 'ox-mock-ghost' }); }))); }
    else if (k === 'home_recommendations') { box.appendChild(el('div', { class: 'ox-mock-ghost' })); box.appendChild(el('div', { class: 'ox-mock-row' }, [hot, el('div', { class: 'ox-mock-ghost' }), el('div', { class: 'ox-mock-ghost' }), el('div', { class: 'ox-mock-ghost' })])); }
    else { box.appendChild(el('div', { class: 'ox-mock-bar', text: k === 'category' ? 'Activități recomandate în categorie' : 'Populare în orașul tău' })); box.appendChild(hot); box.appendChild(el('div', { class: 'ox-mock-row' }, [1, 2, 3, 4].map(function () { return el('div', { class: 'ox-mock-ghost' }); }))); }
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
