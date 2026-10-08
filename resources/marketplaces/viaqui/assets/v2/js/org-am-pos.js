/* viaqui.com v2: the operator's cash desk (/organizator/pos). Products of a location with their POS prices and what
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

  function money(v) { return typeof BileteOnlineUtils !== 'undefined' ? BileteOnlineUtils.formatCurrency(v || 0) : F.money(v || 0); }
  function lei(cents) { return money((cents || 0) / 100); }
  var CLOSED_MSG = VQ.t('The counter is closed: open it above, then take the payment.');
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
      S.locations.forEach(function (l) { sel.appendChild(el('option', { value: String(l.id), text: l.name || VQ.t('Venue {id}', { id: l.id }) })); });
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
    $('pos-list').appendChild(el('p', { class: 've-state', text: VQ.t('Loading…') }));
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
      $('pos-list').appendChild(el('p', { class: 've-state', text: A.errText(e, VQ.t('We could not load the products.')) }));
    });
  }
  function setDay(d) {
    S.day = {};
    (d.products || []).forEach(function (x) { S.day[x.id] = x; });
    S.hours = d.hours || null;
    $('pos-hours').textContent = S.hours && S.hours.open
      ? (S.hours.last_entry ? VQ.t('Today: {open}–{close}, last entry {last}', { open: hm(S.hours.open), close: hm(S.hours.close), last: hm(S.hours.last_entry) }) : VQ.t('Today: {open}–{close}', { open: hm(S.hours.open), close: hm(S.hours.close) }))
      : VQ.t('Today the venue is closed, according to its opening hours.');
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
      var go = A.button('check', VQ.t('Open the counter'), 'btn btn-primary');
      go.addEventListener('click', function () {
        go.disabled = true;
        A.api('/pos/session', { method: 'POST', body: { location_id: S.loc, opening_cash: A.conv(cash.value, 'number') || 0 } }).then(function (r) {
          S.session = r.data.session;
          O.flash((r && r.message) || VQ.t('The counter is open.'));
          drawSession();
          drawCart();
        }, function (e) { go.disabled = false; O.flash(A.errText(e, VQ.t('We could not open the counter.')), true); });
      });
      box.appendChild(el('div', { class: 'pos-session-t' }, [el('b', { text: VQ.t('The counter is closed') }), el('span', { text: VQ.t('Open it to be able to sell. Enter how much cash is in the drawer.') })]));
      box.appendChild(el('div', { class: 'pos-session-tools' }, [A.field(VQ.t('Cash at opening (€)'), cash), go]));
      return;
    }
    var since = F.date(new Date(s.opened_at), { hour: '2-digit', minute: '2-digit' });
    box.appendChild(el('div', { class: 'pos-session-t' }, [
      el('b', { text: VQ.t('The counter has been open since {time}', { time: since }) + (s.opened_by ? ' · ' + s.opened_by : '') }),
      el('span', { text: [VQ.n(s.sales || 0, 'sale', 'sales'), VQ.t('cash {amount}', { amount: money(s.total_cash) }), VQ.t('card {amount}', { amount: money(s.total_card) }), VQ.t('the drawer should hold {amount}', { amount: money(s.expected_cash) })].join(' · ') }),
    ]));
    var close = A.button('x', VQ.t('Close the counter'), 'btn btn-ghost');
    close.addEventListener('click', openClose);
    box.appendChild(el('div', { class: 'pos-session-tools' }, [close]));
  }
  function openClose() {
    var s = S.session, dl = $('pos-close-sum');
    dl.textContent = '';
    [[VQ.t('Cash at opening'), money(s.opening_cash)], [VQ.t('Taken in cash'), money(s.total_cash)], [VQ.t('Taken by card'), money(s.total_card)], [VQ.t('Should be in the drawer'), money(s.expected_cash)]].forEach(function (r) {
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
      O.flash(s.difference ? VQ.t('The counter is closed. Cash difference: {amount}.', { amount: money(s.difference) }) : VQ.t('The counter is closed. The cash matches.'), !!s.difference);
      S.session = null;
      drawSession();
      drawCart();
    }, function (e) { O.flash(A.errText(e, VQ.t('We could not close the counter.')), true); }).then(function () { b.disabled = false; });
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
      list.appendChild(el('p', { class: 've-state', text: VQ.t('The venue has no approved products yet. Add them from "Products"; after approval they appear here.') }));
      $('pos-cats').hidden = true;
      return;
    }
    var cats = [];
    prods.forEach(function (p) { if (p.display_category && cats.indexOf(p.display_category) < 0 && catName(p.display_category)) cats.push(p.display_category); });
    var tabs = $('pos-cats');
    tabs.textContent = '';
    tabs.hidden = cats.length < 2;
    if (cats.length > 1) {
      [['all', VQ.t('All')]].concat(cats.map(function (c) { return [c, catName(c)]; })).forEach(function (c) {
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
    if (!d.bookable) return [VQ.t('It can no longer be sold today'), 'is-off'];
    if (d.mode === 'day' && d.remaining != null) return [VQ.t('{n} seats left today', { n: d.remaining }), d.remaining <= 10 ? 'is-low' : ''];
    if (d.mode === 'slot') {
      var free = (d.slots || []).filter(function (s) { return s.is_bookable; });
      return [free.length ? VQ.t('next start time: {time}', { time: hm(free[0].start_time) }) : VQ.t('no free start time today'), free.length ? '' : 'is-off'];
    }
    return ['', ''];
  }
  /** The product's icon from the sprite the page printed (includes/v2/product-icons.php); an old emoji or anything unknown shows none. */
  var ICON_KEYS = {};
  ((A.L && A.L.product_icons) || []).forEach(function (x) { ICON_KEYS[x[0]] = true; });
  function productIcon(key) { return typeof key === 'string' && ICON_KEYS[key] ? O.icon('pi-' + key) : null; }
  function productCard(p) {
    var av = availabilityText(p), off = S.day[p.id] && !S.day[p.id].bookable;
    var card = el('article', { class: 'pos-p' + (off ? ' is-off' : '') }, [
      el('div', { class: 'pos-p-head' }, [
        productIcon(p.icon) ? el('span', { class: 'pos-p-ic', 'aria-hidden': 'true' }, [productIcon(p.icon)]) : null,
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
      if (!slots.length) { O.flash(VQ.t('No free start time today for "{name}".', { name: p.title }), true); return; }
      var box = $('pos-time-chips');
      box.textContent = '';
      $('pos-time-h').textContent = p.title + ' · ' + v.name;
      slots.forEach(function (s) {
        var b = el('button', { type: 'button', class: 'pos-chip' }, [el('b', { text: hm(s.start_time) }), el('small', { text: VQ.t('{n} left', { n: s.capacity_remaining }) })]);
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
    if (!S.cart.length) box.appendChild(el('p', { class: 've-sub', text: VQ.t('Choose the products on the left.') }));
    S.cart.forEach(function (l, i) {
      var p = l.product, v = l.variant, step = v.step_qty || 1, min = v.min_per_order || 1;
      var minus = A.button(null, '−', 've-icon-btn', { 'aria-label': VQ.t('Less: {name}', { name: p.title }) });
      var plus = A.button(null, '+', 've-icon-btn', { 'aria-label': VQ.t('More: {name}', { name: p.title }) });
      minus.addEventListener('click', function () { if (l.qty - step < min) S.cart.splice(i, 1); else l.qty -= step; drawCart(); });
      plus.addEventListener('click', function () { if (!v.max_per_order || l.qty + step <= v.max_per_order) { l.qty += step; drawCart(); } });
      var row = el('div', { class: 'pos-line' }, [
        el('div', { class: 'pos-line-t' }, [
          el('b', { text: p.title }),
          el('small', { text: [v.name, l.time ? VQ.t('at {time}', { time: hm(l.time) }) : null].filter(Boolean).join(' · ') }),
        ]),
        el('div', { class: 'pos-qty' }, [minus, el('output', { text: String(l.qty) }), plus]),
        el('b', { class: 'pos-line-sum', text: lei(lineTotal(l)) }),
      ]);
      if (p.requires_vehicle_info) {
        var plate = el('input', { class: 'po-input', value: l.plate, maxlength: 80, placeholder: VQ.t('Number plate'), 'aria-label': VQ.t('Number plate, {name}', { name: p.title }) });
        plate.addEventListener('input', function () { l.plate = plate.value.toUpperCase(); });
        row.appendChild(el('div', { class: 'pos-line-x' }, [plate]));
      }
      if (p.type === 'package') {
        var d = S.day[p.id];
        ((d && d.components) || []).forEach(function (c) {
          if (c.mode !== 'slot') return;
          var comp = (p.components || []).filter(function (x) { return x.item_id === c.item_id; })[0];
          var sel = el('select', { 'aria-label': VQ.t('Time for {name}', { name: comp ? comp.title : VQ.t('service') }) });
          sel.appendChild(el('option', { value: '', text: VQ.t('Time for "{name}"', { name: comp ? comp.title : VQ.t('service') }) }));
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
    if (S.cart.length && !S.session) err(CLOSED_MSG);
    else if ($('pos-err').textContent === CLOSED_MSG) err('');
  }
  $('pos-clear').addEventListener('click', function () { S.cart = []; err(''); drawCart(); });

  /* ---------- the sale ---------- */
  function sell(method) {
    if (S.busy || !S.cart.length) return;
    var missing = S.cart.filter(function (l) { return l.product.requires_vehicle_info && !l.plate.trim(); })[0];
    if (missing) { err(VQ.t('Enter the number plate for "{name}".', { name: missing.product.title })); return; }
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
      err(VQ.t('You filled in the company details. Tick "Issue an invoice" or delete the details.'));
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
      err(A.errText(e, VQ.t('The sale could not be recorded.')));
      if (e && e.status === 409) refreshDay();
    }).then(function () { S.busy = false; drawCart(); });
  }
  $('pos-cash').addEventListener('click', function () { sell('cash'); });
  $('pos-card').addEventListener('click', function () { sell('card'); });

  function showDone(sale, method) {
    $('pos-done-h').textContent = VQ.t('Receipt {number}', { number: sale.order_number });
    var paid = { total: money(sale.total), fee: money(sale.commission || 0) };
    $('pos-done-sum').textContent = (method === 'cash'
      ? (sale.commission ? VQ.t('Paid in cash: {total} (of which ticketing cost {fee}).', paid) : VQ.t('Paid in cash: {total}.', paid))
      : (sale.commission ? VQ.t('Paid by card: {total} (of which ticketing cost {fee}).', paid) : VQ.t('Paid by card: {total}.', paid)))
      + ' ' + VQ.n(sale.tickets.length, 'ticket', 'tickets') + '.'
      + (sale.invoice_number ? ' ' + (sale.company && sale.company.name ? VQ.t('Invoice {number}, for {company}.', { number: F.flat(sale.invoice_number), company: F.flat(sale.company.name) }) : VQ.t('Invoice {number}.', { number: F.flat(sale.invoice_number) })) : '');
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
    if (!cui) { anafMsg(VQ.t('Enter the tax ID (CUI) of the company first.'), true); $('pos-co-cui').focus(); return; }
    if (btn.disabled) return;
    btn.disabled = true;
    anafMsg(VQ.t('Looking it up at ANAF…'));
    O.api('/organizer/settings/verify-cui', { method: 'POST', body: { cui: cui } }).then(function (r) {
      // The proxy answers with company_name / reg_com / full_address (the same shape the venue signup reads).
      var d = (r && r.data) || {}, co = d.company || d;
      var name = F.flat(co.company_name || co.name || co.denumire);
      if (!name) { anafMsg(VQ.t('ANAF found no company with this tax ID.'), true); return; }
      $('pos-co-name').value = name;
      var street = F.flat(co.address).trim(), place = [F.flat(co.city), F.flat(co.county)].filter(Boolean).join(', ');
      var address = F.flat(co.full_address || co.adresa).trim() || [street, place].filter(Boolean).join(', ');
      if (!$('pos-co-address').value.trim()) $('pos-co-address').value = address;
      if (!$('pos-co-reg').value.trim()) $('pos-co-reg').value = F.flat(co.reg_com || co.reg_no || co.registration_number || co.nrRegCom);
      if (co.deregistered) { $('pos-co-invoice').checked = false; anafMsg(VQ.t('ANAF shows the company as struck off. Check the details before issuing the invoice.'), true); $('pos-co-cui').value = F.flat(co.cui) || cui; return; }
      if (co.cui || co.vat_number) $('pos-co-cui').value = F.flat(co.cui || co.vat_number) || cui;
      $('pos-co-invoice').checked = true;
      anafMsg(VQ.t('Details filled in from ANAF.'));
    }, function (e) {
      anafMsg(A.errText(e, VQ.t('We could not reach ANAF. Fill in the details by hand.')), true);
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
        el('p', { class: 'pos-tk-k', text: 'viaqui.com · ' + locName }),
        el('h3', { text: sale.invoice_number ? VQ.t('Invoice {number}', { number: F.flat(sale.invoice_number) }) : VQ.t('Receipt {number}', { number: sale.order_number }) }),
        el('p', { text: F.flat(sale.company.name) }),
        sale.company.cui ? el('p', { text: VQ.t('Tax ID {id}', { id: F.flat(sale.company.cui) }) }) : null,
        sale.company.reg_no ? el('p', { text: F.flat(sale.company.reg_no) }) : null,
        sale.company.address ? el('p', { text: F.flat(sale.company.address) }) : null,
        el('p', { class: 'pos-tk-code', text: money(sale.total) }),
        sale.notes ? el('p', { class: 'pos-tk-k', text: F.flat(sale.notes) }) : null,
        el('p', { class: 'pos-tk-k', text: sale.order_number }),
      ]));
    }
    sale.tickets.forEach(function (t) {
      box.appendChild(el('section', { class: 'pos-tk' }, [
        el('p', { class: 'pos-tk-k', text: 'viaqui.com · ' + locName }),
        el('h3', { text: t.title }),
        t.package ? el('p', { text: VQ.t('From the package "{name}"', { name: t.package }) }) : null,
        el('p', { text: [t.ticket_type, t.date_label, t.time_label].filter(Boolean).join(' · ') }),
        t.plate ? el('p', { text: VQ.t('Car: {plate}', { plate: t.plate }) }) : null,
        qr(t.barcode || t.code),
        el('p', { class: 'pos-tk-code', text: t.code }),
        el('p', { class: 'pos-tk-k', text: sale.order_number + ' · ' + money(t.price) }),
      ]));
    });
    window.print();
  });
  function printThermal() {
    var P = window.PosPrinter, sale = S.last;
    if (!P || !sale) return;
    var locName = (S.catalog && S.catalog.location && S.catalog.location.name) || 'viaqui.com';
    var soldAt = F.date(new Date(), { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
    P.isReady().then(function (ok) { return ok ? true : P.connect(); }).then(function () {
      return sale.tickets.reduce(function (pr, t) {
        return pr.then(function () {
          return P.printTicket({ event_name: t.title, ticket_type_name: [t.ticket_type, t.time_label].filter(Boolean).join(' · '), code: t.code, qr_data: t.barcode || t.code, visit_date: t.date_label || '', sold_at: soldAt, pos_name: locName, unit_price: t.price });
        });
      }, Promise.resolve());
    }).then(function () { O.flash(VQ.t('The tickets were printed.')); }, function (e) { O.flash(e && e.message ? VQ.t('The printer did not answer: {error}', { error: e.message }) : VQ.t('The printer did not answer: check the connection.'), true); });
  }
  $('pos-print-thermal').addEventListener('click', printThermal);
  if (window.PosPrinter && navigator.usb) {
    var pb = $('pos-printer');
    pb.hidden = false;
    var label = function (on) { pb.lastChild.textContent = on ? VQ.t('The printer is connected') : VQ.t('Connect the printer'); };
    window.PosPrinter.isReady().then(label, function () {});
    pb.addEventListener('click', function () { window.PosPrinter.connect().then(function () { label(true); O.flash(VQ.t('The printer is connected.')); }, function (e) { O.flash((e && e.message) || VQ.t('We could not find the printer.'), true); }); });
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
          el('td', { text: s.payment_method === 'cash' ? VQ.t('Cash') : VQ.t('Card') }),
          el('td', { text: money(s.total) }),
        ]));
      });
      $('pos-sales-p').textContent = VQ.n(list.length, 'receipt', 'receipts') + ' · ' + money(sum);
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
