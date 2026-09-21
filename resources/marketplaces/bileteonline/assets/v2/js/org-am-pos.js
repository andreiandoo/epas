/* bilete.online v2: the operator's cash desk (/organizator/pos). Products of a location with their POS prices and what
   is left today, a cart (quantities within the product's limits, start times, package times, plates), the operator's
   commission as online, an optional customer, payment in cash or by card. After a sale the tickets are listed and can
   be printed from the browser (QR codes, 80 mm) or on a thermal printer (window.PosPrinter, WebUSB). The cash session
   is opened with the cash in the drawer and closed with the cash counted. The server checks everything again. */
(function () {
  'use strict';
  var O = window.BO_ORG, A = window.BO_AM, root = document.getElementById('am-pos');
  if (!O || !A || !root) return;
  var el = O.el, F = O.fmt;
  var $ = function (id) { return document.getElementById(id); };
  var LOC_KEY = 'bo_pos_location';
  var S = { locations: [], loc: null, catalog: null, day: {}, hours: null, session: null, cart: [], cat: 'all', last: null, busy: false };
  var today = F.ymd(new Date());

  function lei(cents) { return F.money((cents || 0) / 100); }
  function hm(t) { return t ? String(t).slice(0, 5) : ''; }
  function show(id, on) { $(id).hidden = !on; }
  function err(msg) { $('pos-err').textContent = msg || ''; $('pos-err').hidden = !msg; }
  function productById(id) { return (S.catalog && S.catalog.products || []).filter(function (p) { return p.id === id; })[0] || null; }

  /* ---------- modals ---------- */
  var lastFocus = null;
  function openModal(id) { lastFocus = document.activeElement; $(id).hidden = false; var f = $(id).querySelector('button, input, textarea'); if (f) f.focus(); }
  function closeModal(id) { $(id).hidden = true; if (lastFocus && lastFocus.focus) lastFocus.focus(); }
  root.addEventListener('click', function (e) {
    var c = e.target.closest('[data-close]');
    if (c) closeModal(c.closest('.ve-modal').id);
  });
  document.addEventListener('keydown', function (e) {
    if (e.key !== 'Escape') return;
    ['pos-time', 'pos-close', 'pos-done'].forEach(function (id) { if (!$(id).hidden) closeModal(id); });
  });

  /* ---------- loading ---------- */
  function loadLocations() {
    return A.api('/locations').then(function (r) {
      S.locations = (r && r.data && r.data.locations) || [];
      var sel = $('pos-loc');
      sel.textContent = '';
      if (!S.locations.length) { show('pos-none', true); show('pos-main', false); sel.appendChild(el('option', { value: '', text: '—' })); return; }
      S.locations.forEach(function (l) { sel.appendChild(el('option', { value: String(l.id), text: l.name || ('Locația ' + l.id) })); });
      var keep = null;
      try { keep = localStorage.getItem(LOC_KEY); } catch (e) {}
      if (keep && S.locations.some(function (l) { return String(l.id) === keep; })) sel.value = keep;
      S.loc = parseInt(sel.value, 10);
      return loadLocation();
    });
  }
  function loadLocation() {
    S.cart = [];
    drawCart();
    $('pos-list').textContent = '';
    $('pos-list').appendChild(el('p', { class: 've-state', text: 'Se încarcă…' }));
    return Promise.all([
      A.api('/pos/catalog?location_id=' + S.loc),
      A.api('/pos/day?location_id=' + S.loc + '&date=' + today),
      A.api('/pos/session?location_id=' + S.loc),
    ]).then(function (res) {
      S.catalog = (res[0] && res[0].data) || { products: [] };
      setDay((res[1] && res[1].data) || {});
      S.session = (res[2] && res[2].data && res[2].data.session) || null;
      show('pos-main', true);
      drawSession();
      drawProducts();
      loadSales();
    }, function (e) {
      if (e && e.status === 401) return;
      $('pos-list').textContent = '';
      $('pos-list').appendChild(el('p', { class: 've-state', text: A.errText(e, 'Nu am putut încărca produsele.') }));
    });
  }
  function setDay(d) {
    S.day = {};
    (d.products || []).forEach(function (x) { S.day[x.id] = x; });
    S.hours = d.hours || null;
    $('pos-hours').textContent = S.hours && S.hours.open
      ? 'Azi: ' + hm(S.hours.open) + '–' + hm(S.hours.close) + (S.hours.last_entry ? ', ultima intrare ' + hm(S.hours.last_entry) : '')
      : 'Azi locația e închisă după program.';
  }
  function refreshDay() {
    return A.api('/pos/day?location_id=' + S.loc + '&date=' + today).then(function (r) { setDay((r && r.data) || {}); drawProducts(); }, function () {});
  }

  /* ---------- the cash session ---------- */
  function drawSession() {
    var box = $('pos-session'), s = S.session;
    box.hidden = false;
    box.textContent = '';
    box.className = 'pos-session' + (s ? ' is-open' : '');
    if (!s) {
      var cash = el('input', { class: 'po-input', id: 'pos-open-cash', type: 'number', min: 0, step: '0.01', inputmode: 'decimal', placeholder: '0' });
      var go = A.button('check', 'Deschide casa', 'btn btn-primary');
      go.addEventListener('click', function () {
        go.disabled = true;
        A.api('/pos/session', { method: 'POST', body: { location_id: S.loc, opening_cash: A.conv(cash.value, 'number') || 0 } }).then(function (r) {
          S.session = r.data.session;
          O.flash((r && r.message) || 'Casa e deschisă.');
          drawSession();
          drawCart();
        }, function (e) { go.disabled = false; O.flash(A.errText(e, 'Nu am putut deschide casa.'), true); });
      });
      box.appendChild(el('div', { class: 'pos-session-t' }, [el('b', { text: 'Casa e închisă' }), el('span', { text: 'Deschide-o ca să poți vinde. Scrie cât numerar e în sertar.' })]));
      box.appendChild(el('div', { class: 'pos-session-tools' }, [A.field('Numerar la deschidere (lei)', cash), go]));
      return;
    }
    var since = F.date(new Date(s.opened_at), { hour: '2-digit', minute: '2-digit' });
    box.appendChild(el('div', { class: 'pos-session-t' }, [
      el('b', { text: 'Casa e deschisă de la ' + since + (s.opened_by ? ' · ' + s.opened_by : '') }),
      el('span', { text: s.sales + (s.sales === 1 ? ' vânzare' : ' vânzări') + ' · numerar ' + F.money(s.total_cash) + ' · card ' + F.money(s.total_card) + ' · în sertar ar trebui ' + F.money(s.expected_cash) }),
    ]));
    var close = A.button('x', 'Închide casa', 'btn btn-ghost');
    close.addEventListener('click', openClose);
    box.appendChild(el('div', { class: 'pos-session-tools' }, [close]));
  }
  function openClose() {
    var s = S.session, dl = $('pos-close-sum');
    dl.textContent = '';
    [['Numerar la deschidere', F.money(s.opening_cash)], ['Încasat numerar', F.money(s.total_cash)], ['Încasat card', F.money(s.total_card)], ['Ar trebui în sertar', F.money(s.expected_cash)]].forEach(function (r) {
      dl.appendChild(el('div', null, [el('dt', { text: r[0] }), el('dd', { text: r[1] })]));
    });
    $('pos-counted').value = String(s.expected_cash);
    $('pos-notes').value = '';
    openModal('pos-close');
  }
  $('pos-close-go').addEventListener('click', function () {
    var b = $('pos-close-go');
    b.disabled = true;
    A.api('/pos/session/' + S.session.id + '/close', { method: 'POST', body: { counted_cash: A.conv($('pos-counted').value, 'number'), notes: $('pos-notes').value.trim() || null } }).then(function (r) {
      var s = r.data.session;
      closeModal('pos-close');
      O.flash('Casa e închisă.' + (s.difference ? ' Diferență la numerar: ' + F.money(s.difference) + '.' : ' Numerarul se potrivește.'), !!s.difference);
      S.session = null;
      drawSession();
      drawCart();
    }, function (e) { O.flash(A.errText(e, 'Nu am putut închide casa.'), true); }).then(function () { b.disabled = false; });
  });

  /* ---------- products ---------- */
  function catName(id) {
    var l = S.locations.filter(function (x) { return x.id === S.loc; })[0];
    var c = ((l && l.display_categories) || []).filter(function (x) { return x.id === id; })[0];
    return c ? c.name : null;
  }
  function drawProducts() {
    var list = $('pos-list'), prods = (S.catalog && S.catalog.products) || [];
    list.textContent = '';
    if (!prods.length) {
      list.appendChild(el('p', { class: 've-state', text: 'Locația nu are încă produse aprobate. Adaugă-le din „Produse”; după aprobare apar aici.' }));
      $('pos-cats').hidden = true;
      return;
    }
    var cats = [];
    prods.forEach(function (p) { if (p.display_category && cats.indexOf(p.display_category) < 0 && catName(p.display_category)) cats.push(p.display_category); });
    var tabs = $('pos-cats');
    tabs.textContent = '';
    tabs.hidden = cats.length < 2;
    if (cats.length > 1) {
      [['all', 'Toate']].concat(cats.map(function (c) { return [c, catName(c)]; })).forEach(function (c) {
        var b = el('button', { type: 'button', class: 'fchip', 'aria-current': S.cat === c[0] ? 'true' : null, text: c[1] });
        b.addEventListener('click', function () { S.cat = c[0]; drawProducts(); });
        tabs.appendChild(b);
      });
    }
    prods.filter(function (p) { return S.cat === 'all' || p.display_category === S.cat; }).forEach(function (p) { list.appendChild(productCard(p)); });
  }
  function availabilityText(p) {
    var d = S.day[p.id];
    if (!d) return ['', ''];
    if (!d.bookable) return ['Nu se mai poate vinde azi', 'is-off'];
    if (d.mode === 'day' && d.remaining != null) return [d.remaining + ' locuri azi', d.remaining <= 10 ? 'is-low' : ''];
    if (d.mode === 'slot') {
      var free = (d.slots || []).filter(function (s) { return s.is_bookable; });
      return [free.length ? 'următoarea oră: ' + hm(free[0].start_time) : 'nicio oră liberă azi', free.length ? '' : 'is-off'];
    }
    return ['', ''];
  }
  function productCard(p) {
    var av = availabilityText(p), off = S.day[p.id] && !S.day[p.id].bookable;
    var card = el('article', { class: 'pos-p' + (off ? ' is-off' : '') }, [
      el('div', { class: 'pos-p-head' }, [
        p.icon ? el('span', { class: 'pos-p-ic', 'aria-hidden': 'true', text: p.icon }) : null,
        el('div', null, [el('b', { text: p.title }), av[0] ? el('small', { class: av[1], text: av[0] }) : null]),
      ]),
    ]);
    var vs = el('div', { class: 'pos-vars' });
    p.variants.forEach(function (v) {
      var b = el('button', { type: 'button', class: 'pos-v', disabled: off ? true : null }, [el('span', { text: v.name }), el('b', { text: lei(v.price_cents) })]);
      b.addEventListener('click', function () { pick(p, v); });
      vs.appendChild(b);
    });
    card.appendChild(vs);
    return card;
  }
  function slotsFor(p, v) {
    var d = S.day[p.id];
    if (!d) return [];
    var byV = d.slots_by_variant && d.slots_by_variant[v.id];
    return (byV || d.slots || []);
  }
  function pick(p, v) {
    err('');
    if (p.booking_mode === 'slot' && p.type !== 'package') {
      var slots = slotsFor(p, v).filter(function (s) { return s.is_bookable; });
      if (!slots.length) { O.flash('Nicio oră liberă azi la „' + p.title + '”.', true); return; }
      var box = $('pos-time-chips');
      box.textContent = '';
      $('pos-time-h').textContent = p.title + ' · ' + v.name;
      slots.forEach(function (s) {
        var b = el('button', { type: 'button', class: 'pos-chip' }, [el('b', { text: hm(s.start_time) }), el('small', { text: s.capacity_remaining + ' loc.' })]);
        b.addEventListener('click', function () { closeModal('pos-time'); add(p, v, s.start_time); });
        box.appendChild(b);
      });
      openModal('pos-time');
      return;
    }
    add(p, v, null);
  }
  function add(p, v, time) {
    var key = p.id + ':' + v.id + ':' + (time || '');
    var line = S.cart.filter(function (l) { return l.key === key; })[0];
    var step = v.step_qty || 1, min = v.min_per_order || 1;
    if (line) line.qty = line.qty + step;
    else S.cart.push(line = { key: key, product: p, variant: v, time: time, qty: min, plate: '', comps: {} });
    if (v.max_per_order && line.qty > v.max_per_order) line.qty = v.max_per_order;
    drawCart();
  }

  /* ---------- the cart ---------- */
  function lineTotal(l) { return l.variant.price_cents * l.qty; }
  function totals() {
    var sub = 0;
    S.cart.forEach(function (l) { sub += lineTotal(l); });
    // as online: the percentage, never under the minimum per ticket (the server applies the same rule)
    var c = (S.catalog && S.catalog.commission) || { rate: 0, mode: 'included', floor: 0 };
    var floorCents = Math.round((c.floor || 0) * 100);
    var fee = c.mode === 'added_on_top' ? S.cart.reduce(function (a, l) {
      return a + Math.max(c.rate > 0 ? Math.round(lineTotal(l) * c.rate / 100) : 0, floorCents * Math.max(1, l.qty));
    }, 0) : 0;
    return { sub: sub, fee: fee, rate: c.rate, total: sub + fee };
  }
  function drawCart() {
    var box = $('pos-lines');
    box.textContent = '';
    if (!S.cart.length) box.appendChild(el('p', { class: 've-sub', text: 'Alege produsele din stânga.' }));
    S.cart.forEach(function (l, i) {
      var p = l.product, v = l.variant, step = v.step_qty || 1, min = v.min_per_order || 1;
      var minus = A.button(null, '−', 've-icon-btn', { 'aria-label': 'Mai puțin: ' + p.title });
      var plus = A.button(null, '+', 've-icon-btn', { 'aria-label': 'Mai mult: ' + p.title });
      minus.addEventListener('click', function () { if (l.qty - step < min) S.cart.splice(i, 1); else l.qty -= step; drawCart(); });
      plus.addEventListener('click', function () { if (!v.max_per_order || l.qty + step <= v.max_per_order) { l.qty += step; drawCart(); } });
      var row = el('div', { class: 'pos-line' }, [
        el('div', { class: 'pos-line-t' }, [
          el('b', { text: p.title }),
          el('small', { text: [v.name, l.time ? 'ora ' + hm(l.time) : null].filter(Boolean).join(' · ') }),
        ]),
        el('div', { class: 'pos-qty' }, [minus, el('output', { text: String(l.qty) }), plus]),
        el('b', { class: 'pos-line-sum', text: lei(lineTotal(l)) }),
      ]);
      if (p.requires_vehicle_info) {
        var plate = el('input', { class: 'po-input', value: l.plate, maxlength: 80, placeholder: 'Nr. înmatriculare', 'aria-label': 'Număr de înmatriculare, ' + p.title });
        plate.addEventListener('input', function () { l.plate = plate.value.toUpperCase(); });
        row.appendChild(el('div', { class: 'pos-line-x' }, [plate]));
      }
      if (p.type === 'package') {
        var d = S.day[p.id];
        ((d && d.components) || []).forEach(function (c) {
          if (c.mode !== 'slot') return;
          var comp = (p.components || []).filter(function (x) { return x.item_id === c.item_id; })[0];
          var sel = el('select', { 'aria-label': 'Ora pentru ' + (comp ? comp.title : 'serviciu') });
          sel.appendChild(el('option', { value: '', text: 'Ora pentru „' + (comp ? comp.title : 'serviciu') + '”' }));
          (c.slots || []).filter(function (s) { return s.is_bookable; }).forEach(function (s) { sel.appendChild(el('option', { value: s.start_time, text: hm(s.start_time) })); });
          sel.value = l.comps[c.item_id] || '';
          sel.addEventListener('change', function () { l.comps[c.item_id] = sel.value || null; });
          row.appendChild(el('div', { class: 'pos-line-x' }, [el('span', { class: 'po-select' }, [sel, O.icon('caret-down')])]));
        });
      }
      box.appendChild(row);
    });
    var t = totals();
    $('pos-totals').hidden = !S.cart.length;
    $('pos-clear').hidden = !S.cart.length;
    $('pos-sub').textContent = lei(t.sub);
    $('pos-fee-row').hidden = !t.fee;
    $('pos-fee').textContent = lei(t.fee);
    $('pos-total').textContent = lei(t.total);
    var can = S.cart.length > 0 && !!S.session && !S.busy;
    $('pos-cash').disabled = !can;
    $('pos-card').disabled = !can;
    if (S.cart.length && !S.session) err('Casa e închisă: deschide-o sus, apoi încasează.');
    else if ($('pos-err').textContent.indexOf('Casa e închisă') === 0) err('');
  }
  $('pos-clear').addEventListener('click', function () { S.cart = []; err(''); drawCart(); });

  /* ---------- the sale ---------- */
  function sell(method) {
    if (S.busy || !S.cart.length) return;
    var missing = S.cart.filter(function (l) { return l.product.requires_vehicle_info && !l.plate.trim(); })[0];
    if (missing) { err('Scrie numărul de înmatriculare la „' + missing.product.title + '”.'); return; }
    var items = S.cart.map(function (l) {
      var it = { activity_id: l.product.id, variant_id: l.variant.id, quantity: l.qty };
      if (l.time) it.slot_start_time = l.time;
      if (l.product.requires_vehicle_info) it.meta = { vehicle_plate: l.plate.trim() };
      if (l.product.type === 'package') it.components = (l.product.components || []).map(function (c) { return { item_id: c.item_id, slot_start_time: l.comps[c.item_id] || null }; });
      return it;
    });
    var email = $('pos-c-email').value.trim();
    var company = companyFields();
    // the invoice number is taken at the sale, so company details without the tick cannot be invoiced later
    if (company && !$('pos-co-invoice').checked) {
      err('Ai completat datele firmei. Bifează „Emite factură” sau șterge datele.');
      $('pos-company').open = true;
      return;
    }
    var body = {
      location_id: S.loc, payment_method: method, items: items,
      customer: {
        name: $('pos-c-name').value.trim() || null, email: email || null,
        phone: $('pos-c-phone').value.trim() || null, notes: $('pos-c-notes').value.trim() || null,
      },
      send_email: !!email && $('pos-c-send').checked,
    };
    if (company) {
      body.company = company;
      body.generate_invoice = true;
    }
    S.busy = true;
    err('');
    drawCart();
    A.api('/pos/sale', { method: 'POST', body: body }).then(function (r) {
      S.last = r.data.sale;
      S.cart = [];
      ['pos-c-name', 'pos-c-email', 'pos-c-phone', 'pos-c-notes', 'pos-co-cui', 'pos-co-name',
        'pos-co-reg', 'pos-co-iban', 'pos-co-address', 'pos-co-contact'].forEach(function (id) { $(id).value = ''; });
      $('pos-c-send').checked = $('pos-co-invoice').checked = false;
      $('pos-customer').open = $('pos-company').open = false;
      anafMsg('');
      showDone(S.last, method);
      refreshDay();
      loadSession();
      loadSales();
    }, function (e) {
      err(A.errText(e, 'Vânzarea nu s-a putut înregistra.'));
      if (e && e.status === 409) refreshDay();
    }).then(function () { S.busy = false; drawCart(); });
  }
  $('pos-cash').addEventListener('click', function () { sell('cash'); });
  $('pos-card').addEventListener('click', function () { sell('card'); });

  function showDone(sale, method) {
    $('pos-done-h').textContent = 'Bonul ' + sale.order_number;
    $('pos-done-sum').textContent = 'Încasat ' + (method === 'cash' ? 'numerar' : 'cu cardul') + ': ' + F.money(sale.total)
      + (sale.commission ? ' (din care cost ticketing ' + F.money(sale.commission) + ')' : '') + '. '
      + sale.tickets.length + (sale.tickets.length === 1 ? ' bilet.' : ' bilete.')
      + (sale.invoice_number ? ' Factura ' + F.flat(sale.invoice_number) + (sale.company && sale.company.name ? ', pe ' + F.flat(sale.company.name) : '') + '.' : '');
    var ul = $('pos-done-tickets');
    ul.textContent = '';
    sale.tickets.forEach(function (t) {
      ul.appendChild(el('li', null, [el('b', { text: t.title + (t.package ? ' (' + t.package + ')' : '') }), el('span', { text: [t.ticket_type, t.time_label, t.plate].filter(Boolean).join(' · ') }), el('code', { text: t.code })]));
    });
    $('pos-print-thermal').hidden = !(window.PosPrinter && navigator.usb);
    openModal('pos-done');
    if (window.PosPrinter && window.PosPrinter.getAutoPrintEnabled && window.PosPrinter.getAutoPrintEnabled()) printThermal();
  }

  /* ---------- the company the invoice is made out to ---------- */
  /** What the operator filled in, or null when the block is empty. */
  function companyFields() {
    var out = {}, any = false;
    [['name', 'pos-co-name'], ['cui', 'pos-co-cui'], ['reg_no', 'pos-co-reg'],
      ['address', 'pos-co-address'], ['iban', 'pos-co-iban'], ['contact_person', 'pos-co-contact']].forEach(function (f) {
      var v = $(f[1]).value.trim();
      out[f[0]] = v || null;
      if (v) any = true;
    });
    return any ? out : null;
  }
  function anafMsg(text, bad) {
    var n = $('pos-anaf-msg');
    n.textContent = text;
    n.hidden = !text;
    n.classList.toggle('is-bad', !!bad);
  }
  $('pos-anaf').addEventListener('click', function () {
    var btn = this, cui = $('pos-co-cui').value.trim();
    if (!cui) { anafMsg('Scrie întâi CUI-ul firmei.', true); $('pos-co-cui').focus(); return; }
    if (btn.disabled) return;
    btn.disabled = true;
    anafMsg('Se caută la ANAF…');
    O.api('/organizer/settings/verify-cui', { method: 'POST', body: { cui: cui } }).then(function (r) {
      var d = (r && r.data) || {}, co = d.company || d;
      var name = F.flat(co.name || co.denumire);
      if (!name) { anafMsg('ANAF nu a găsit nicio firmă cu acest CUI.', true); return; }
      $('pos-co-name').value = name;
      if (!$('pos-co-address').value.trim()) $('pos-co-address').value = F.flat(co.address || co.adresa);
      if (!$('pos-co-reg').value.trim()) $('pos-co-reg').value = F.flat(co.reg_no || co.registration_number || co.nrRegCom);
      if (co.cui || co.vat_number) $('pos-co-cui').value = F.flat(co.cui || co.vat_number) || cui;
      $('pos-co-invoice').checked = true;
      anafMsg('Date completate din ANAF.');
    }, function (e) {
      anafMsg(A.errText(e, 'Nu am putut interoga ANAF. Completează manual.'), true);
    }).then(function () { btn.disabled = false; });
  });

  /* ---------- printing ---------- */
  function qr(text) {
    var lib = window.qrcode;
    if (typeof lib !== 'function' || !text) return null;
    var code;
    try { code = lib(0, 'M'); code.addData(String(text)); code.make(); } catch (e) { return null; }
    var n = code.getModuleCount(), q = 2, size = n + q * 2, d = '';
    for (var r = 0; r < n; r++) for (var c = 0; c < n; c++) if (code.isDark(r, c)) d += 'M' + (c + q) + ' ' + (r + q) + 'h1v1h-1z';
    var NS = 'http://www.w3.org/2000/svg', svg = document.createElementNS(NS, 'svg'), path = document.createElementNS(NS, 'path');
    svg.setAttribute('viewBox', '0 0 ' + size + ' ' + size);
    svg.setAttribute('shape-rendering', 'crispEdges');
    path.setAttribute('d', d);
    svg.appendChild(path);
    return svg;
  }
  $('pos-print').addEventListener('click', function () {
    var sale = S.last, box = $('pos-print'), locName = (S.catalog && S.catalog.location && S.catalog.location.name) || '';
    if (!sale) return;
    box.textContent = '';
    // a sale made out to a company starts with its own slip: the invoice number, the company and the total
    if (sale.company) {
      box.appendChild(el('section', { class: 'pos-tk is-bill' }, [
        el('p', { class: 'pos-tk-k', text: 'bilete.online · ' + locName }),
        el('h3', { text: sale.invoice_number ? 'Factura ' + F.flat(sale.invoice_number) : 'Bon ' + sale.order_number }),
        el('p', { text: F.flat(sale.company.name) }),
        sale.company.cui ? el('p', { text: 'CUI ' + F.flat(sale.company.cui) }) : null,
        sale.company.reg_no ? el('p', { text: F.flat(sale.company.reg_no) }) : null,
        sale.company.address ? el('p', { text: F.flat(sale.company.address) }) : null,
        el('p', { class: 'pos-tk-code', text: F.money(sale.total) }),
        sale.notes ? el('p', { class: 'pos-tk-k', text: F.flat(sale.notes) }) : null,
        el('p', { class: 'pos-tk-k', text: sale.order_number }),
      ]));
    }
    sale.tickets.forEach(function (t) {
      box.appendChild(el('section', { class: 'pos-tk' }, [
        el('p', { class: 'pos-tk-k', text: 'bilete.online · ' + locName }),
        el('h3', { text: t.title }),
        t.package ? el('p', { text: 'Din pachetul „' + t.package + '”' }) : null,
        el('p', { text: [t.ticket_type, t.date_label, t.time_label].filter(Boolean).join(' · ') }),
        t.plate ? el('p', { text: 'Mașina: ' + t.plate }) : null,
        qr(t.barcode || t.code),
        el('p', { class: 'pos-tk-code', text: t.code }),
        el('p', { class: 'pos-tk-k', text: sale.order_number + ' · ' + F.money(t.price) }),
      ]));
    });
    window.print();
  });
  function printThermal() {
    var P = window.PosPrinter, sale = S.last;
    if (!P || !sale) return;
    var locName = (S.catalog && S.catalog.location && S.catalog.location.name) || 'bilete.online';
    var soldAt = F.date(new Date(), { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
    P.isReady().then(function (ok) { return ok ? true : P.connect(); }).then(function () {
      return sale.tickets.reduce(function (pr, t) {
        return pr.then(function () {
          return P.printTicket({ event_name: t.title, ticket_type_name: [t.ticket_type, t.time_label].filter(Boolean).join(' · '), code: t.code, qr_data: t.barcode || t.code, visit_date: t.date_label || '', sold_at: soldAt, pos_name: locName, unit_price: t.price });
        });
      }, Promise.resolve());
    }).then(function () { O.flash('Biletele s-au tipărit.'); }, function (e) { O.flash('Imprimanta nu a răspuns: ' + ((e && e.message) || 'verifică legătura.'), true); });
  }
  $('pos-print-thermal').addEventListener('click', printThermal);
  if (window.PosPrinter && navigator.usb) {
    var pb = $('pos-printer');
    pb.hidden = false;
    var label = function (on) { pb.lastChild.textContent = on ? 'Imprimanta e conectată' : 'Conectează imprimanta'; };
    window.PosPrinter.isReady().then(label, function () {});
    pb.addEventListener('click', function () { window.PosPrinter.connect().then(function () { label(true); O.flash('Imprimanta e conectată.'); }, function (e) { O.flash((e && e.message) || 'Nu am găsit imprimanta.', true); }); });
  }

  /* ---------- the day's sales ---------- */
  function loadSession() {
    return A.api('/pos/session?location_id=' + S.loc).then(function (r) { S.session = (r && r.data && r.data.session) || null; drawSession(); }, function () {});
  }
  function loadSales() {
    A.api('/pos/sales?location_id=' + S.loc + '&date=' + today).then(function (r) {
      var list = (r && r.data && r.data.sales) || [], tb = $('pos-sales');
      $('pos-sales-box').hidden = !list.length;
      tb.textContent = '';
      var sum = 0;
      list.forEach(function (s) {
        sum += s.total;
        tb.appendChild(el('tr', null, [
          el('td', { text: F.date(new Date(s.created_at), { hour: '2-digit', minute: '2-digit' }) }),
          el('td', { text: s.order_number }),
          el('td', { text: s.lines.map(function (l) { return l.quantity + ' × ' + l.title + (l.variant ? ' (' + l.variant + ')' : ''); }).join(', ') }),
          el('td', { text: s.payment_method === 'cash' ? 'Numerar' : 'Card' }),
          el('td', { text: F.money(s.total) }),
        ]));
      });
      $('pos-sales-p').textContent = list.length + (list.length === 1 ? ' bon' : ' bonuri') + ' · ' + F.money(sum);
    }, function () {});
  }

  $('pos-loc').addEventListener('change', function () {
    S.loc = parseInt($('pos-loc').value, 10);
    S.cat = 'all';
    try { localStorage.setItem(LOC_KEY, String(S.loc)); } catch (e) {}
    loadLocation();
  });

  O.ready.then(function (ok) { if (ok) loadLocations().catch(function () {}); });
})();
