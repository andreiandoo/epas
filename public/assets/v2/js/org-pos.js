/* viaqui.com v2: on-site sale (/organizator/pos) — the POS ported from Ambilet's "InfoPoint" onto the v2 shell.
   Reads the organizer's activities, keeps the ones set up as a venue, then works on one of them:
   /organizer/events/{id}/leisure/config for the products, .../leisure/cashier/* for the register and
   .../leisure/pos-sale for the sale. The receipt goes to a thermal printer over WebUSB (pos-printer.js) when one is
   connected, otherwise through the browser's print dialog. Text from the API is always written as text. */
(function () {
  'use strict';
  var O = window.BO_ORG, root = document.getElementById('po');
  if (!O || !root) return;
  var F = O.fmt, el = O.el;
  var $ = function (id) { return document.getElementById(id); };
  var qsa = function (sel) { return Array.prototype.slice.call(root.querySelectorAll(sel)); };

  var CAT = { access: VQ.t('Access'), parking: VQ.t('Parking'), rental: VQ.t('Rental'), activity: VQ.t('Activity'), extra: VQ.t('Extra'), package: VQ.t('Package') };
  var PAY = { cash: VQ.t('Cash'), card: VQ.t('Card'), invoice: VQ.t('Link by email') };
  var events = [], eventId = null, types = [], categories = [], issuers = {}, commission = { rate: 0, fixed: 0, mode: 'included' };
  var cart = {}, payment = 'cash', locale = 'ro', session = null, lastSale = null, xTimer = null, busy = false;

  function txt(v) { return F.flat(v).trim(); }
  function lei(v) { return F.money(F.toNum(v)); }
  function today() { return F.ymd(new Date()); }
  function hhmm(v) { var d = F.dateOf(v); return d ? F.date(d, { hour: '2-digit', minute: '2-digit' }) : '—'; }
  function stamp(v) { var d = F.dateOf(v); return d ? F.date(d, { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' }) : '—'; }
  function show(id, on) { var e = $(id); if (e) e.hidden = !on; }
  function msg(id, text, isError) {
    var e = $(id);
    if (!e) return;
    e.textContent = text || '';
    e.classList.toggle('is-error', !!isError);
    e.hidden = !text;
  }

  /* =================== products =================== */
  function posTypes() {
    return types.filter(function (t) {
      if (t.is_active === false) return false;
      if (t.status && t.status !== 'active') return false;
      var meta = t.meta || {};
      var hasPosPrice = t.pos_price !== null && t.pos_price !== undefined && t.pos_price !== '';
      return !!meta.pos_only || hasPosPrice;
    });
  }
  function priceOf(t, variant) {
    if (variant && variant.price != null) return F.toNum(variant.price);
    if (t.pos_price !== null && t.pos_price !== undefined && t.pos_price !== '') return F.toNum(t.pos_price);
    return F.toNum(t.price_max != null && t.price_max !== 0 ? t.price_max : t.price);
  }
  function catName(c) {
    if (!c) return '';
    return txt(c.name) || String(c.id || '');
  }
  function drawProducts() {
    var box = $('po-cats'), q = ($('po-q').value || '').trim().toLowerCase();
    var list = posTypes().filter(function (t) { return !q || txt(t.name).toLowerCase().indexOf(q) > -1; });
    box.textContent = '';
    show('po-prod-skel', false);
    show('po-prod-empty', !list.length);
    if (!list.length) return;
    var groups = [], byId = {};
    categories.forEach(function (c) {
      var g = { id: String(c.id), name: catName(c), items: [] };
      byId[g.id] = g;
      groups.push(g);
    });
    var loose = { id: '', name: '', items: [] };
    list.forEach(function (t) {
      var g = t.ticket_group != null && byId[String(t.ticket_group)] ? byId[String(t.ticket_group)] : loose;
      g.items.push(t);
    });
    groups.push(loose);
    groups.forEach(function (g) {
      if (!g.items.length) return;
      var wrap = el('div', { class: 'po-cat' });
      if (g.name) wrap.appendChild(el('p', { class: 'po-cat-h', text: g.name }));
      var ul = el('ul', { class: 'po-items' });
      g.items.forEach(function (t) { ul.appendChild(productCard(t)); });
      wrap.appendChild(ul);
      box.appendChild(wrap);
    });
  }
  function productCard(t) {
    var variants = Array.isArray(t.variants) ? t.variants : [];
    var li = el('li');
    if (variants.length) {
      var card = el('div', { class: 'po-item is-multi' }, [
        el('span', { class: 'po-item-tag', text: CAT[t.service_category] || t.service_category || '' }),
        el('b', { text: txt(t.name) }),
      ]);
      var vars = el('div', { class: 'po-vars' });
      variants.forEach(function (v) {
        var b = el('button', { class: 'po-var', type: 'button' }, [
          el('span', { text: txt(v.label) }),
          el('span', { text: lei(priceOf(t, v)) }),
        ]);
        b.addEventListener('click', function () { add(t, v); });
        vars.appendChild(b);
      });
      card.appendChild(vars);
      li.appendChild(card);
      return li;
    }
    var qty = qtyOf(t.id);
    var btn = el('button', { class: 'po-item', type: 'button' }, [
      el('span', { class: 'po-item-tag', text: CAT[t.service_category] || t.service_category || '' }),
      el('b', { text: txt(t.name) }),
      el('span', { class: 'po-item-price', text: lei(priceOf(t, null)) }),
    ]);
    if (qty) btn.appendChild(el('span', { class: 'po-item-qty', text: String(qty) }));
    btn.addEventListener('click', function () { add(t, null); });
    li.appendChild(btn);
    return li;
  }
  function qtyOf(id) {
    var n = 0;
    Object.keys(cart).forEach(function (k) { if (cart[k].ticket_type_id === id) n += cart[k].qty; });
    return n;
  }
  function stepOf(t) {
    var min = Math.max(1, Math.round(F.toNum(t && t.min_per_order)) || 1);
    var meta = (t && t.meta) || {};
    var step = Math.max(1, Math.round(F.toNum(meta.step_qty)) || (meta.is_group_ticket ? min : 1));
    return { min: min, step: step };
  }
  function add(t, variant) {
    var slots = t.slots_config && t.slots_config.enabled, physical = t.physical_inventory && t.physical_inventory.enabled;
    if (slots || physical) { askSlot(t, variant); return; }
    put(t, variant, null);
  }
  function put(t, variant, slot) {
    var key = String(t.id) + (variant ? '|' + variant.id : '') + (slot ? '@' + slot : '');
    var rules = stepOf(t);
    if (!cart[key]) {
      cart[key] = {
        ticket_type_id: t.id,
        qty: 0,
        price: priceOf(t, variant),
        name: txt(t.name) + (variant ? ': ' + txt(variant.label) : '') + (slot ? ' · ' + slot : ''),
        category: t.service_category || 'access',
        variant: variant ? { id: variant.id, label: txt(variant.label), duration_minutes: variant.duration_minutes || null } : null,
        slot_time: slot,
        addons: {},
      };
    }
    cart[key].qty = cart[key].qty === 0 ? Math.max(rules.min, rules.step) : cart[key].qty + rules.step;
    drawCart();
    drawProducts();
  }
  function askSlot(t, variant) {
    var cfg = t.slots_config || {}, d = $('po-slot-d');
    if (!d) return;
    $('po-slot-t').textContent = txt(t.name);
    $('po-slot-hint').textContent = VQ.t('Between {from} and {to}.', { from: cfg.first_slot || '09:00', to: cfg.last_slot || '18:00' });
    var input = $('po-slot-time');
    input.value = cfg.first_slot || '09:00';
    d.returnValue = '';
    if (typeof d.showModal === 'function') d.showModal(); else d.setAttribute('open', '');
    var go = function () {
      var v = input.value;
      if (!/^\d{2}:\d{2}$/.test(v)) return;
      close();
      put(t, variant, v);
    };
    var close = function () {
      $('po-slot-ok').removeEventListener('click', go);
      if (typeof d.close === 'function') { if (d.open) d.close(); } else d.removeAttribute('open');
    };
    $('po-slot-ok').addEventListener('click', go);
    d.addEventListener('close', function () { $('po-slot-ok').removeEventListener('click', go); }, { once: true });
  }

  /* =================== cart =================== */
  function commissionPer(price) {
    if ((commission.mode || 'included') !== 'added_on_top') return 0;
    return Math.max(F.toNum(price) * F.toNum(commission.rate) / 100, F.toNum(commission.fixed));
  }
  function addonsOf(id) {
    var t = types.filter(function (x) { return x.id === id; })[0];
    return t && Array.isArray(t.addons) ? t.addons : [];
  }
  function drawCart() {
    var list = $('po-lines'), keys = Object.keys(cart);
    list.textContent = '';
    show('po-clear', keys.length > 0);
    if (!keys.length) {
      list.appendChild(el('li', { class: 'po-lines-empty', text: VQ.t('The basket is empty. Press a product to add it.') }));
    }
    var subtotal = 0, commissionTotal = 0, addonsTotal = 0, accessAny = 0, accessAdult = 0, needAny = 0, needAdult = 0;
    keys.forEach(function (key) {
      var it = cart[key], t = types.filter(function (x) { return x.id === it.ticket_type_id; })[0] || {};
      var line = it.qty * it.price;
      subtotal += line;
      commissionTotal += commissionPer(it.price) * it.qty;
      var meta = t.meta || {}, req = meta.access_requirement;
      if (['none', 'any', 'adult_only'].indexOf(req) < 0) req = t.requires_access_ticket ? 'any' : 'none';
      if ((t.service_category || it.category) === 'access') { accessAny += it.qty; if (!meta.is_child_ticket) accessAdult += it.qty; }
      if (req === 'any') needAny += it.qty;
      if (req === 'adult_only') needAdult += it.qty;

      var row = el('li', { class: 'po-line' });
      row.appendChild(el('div', { class: 'po-line-top' }, [
        el('div', { class: 'po-line-t' }, [el('b', { text: it.name }), el('small', { text: VQ.t('{price} each', { price: lei(it.price) }) })]),
        el('span', { class: 'po-line-sum', text: lei(line) }),
      ]));
      var qty = el('div', { class: 'po-qty' });
      qty.appendChild(iconBtn('minus', VQ.t('One less'), function () { bump(key, -1); }));
      qty.appendChild(el('b', { text: String(it.qty) }));
      qty.appendChild(iconBtn('plus', VQ.t('One more'), function () { bump(key, 1); }));
      qty.appendChild(iconBtn('trash', VQ.t('Remove from the basket'), function () { delete cart[key]; drawCart(); drawProducts(); }, 'po-del'));
      row.appendChild(qty);

      var addons = addonsOf(it.ticket_type_id);
      if (addons.length) {
        var box = el('div', { class: 'po-addons' });
        addons.forEach(function (a) {
          var have = it.addons[a.id] || 0;
          var included = Math.max(0, Math.round(F.toNum(a.included_qty))) * it.qty;
          var max = (Math.max(0, Math.round(F.toNum(a.included_qty))) + Math.max(0, Math.round(F.toNum(a.max_per_unit) || 5))) * it.qty;
          var paid = Math.max(0, have - included);
          addonsTotal += paid * F.toNum(a.price);
          var line2 = el('div', { class: 'po-addon' }, [
            el('span', null, [el('b', { text: txt(a.label) }), el('small', { text: (included ? ' · ' + VQ.t('{n} included', { n: included }) : '') + ' · ' + VQ.t('{price} each', { price: lei(a.price) }) })]),
          ]);
          line2.appendChild(iconBtn('minus', VQ.t('One less: {name}', { name: txt(a.label) }), function () {
            var cur = it.addons[a.id] || 0;
            if (cur <= 1) delete it.addons[a.id]; else it.addons[a.id] = cur - 1;
            drawCart();
          }));
          line2.appendChild(el('b', { text: String(have) }));
          line2.appendChild(iconBtn('plus', VQ.t('One more: {name}', { name: txt(a.label) }), function () {
            var cur = it.addons[a.id] || 0;
            if (cur >= max) return;
            it.addons[a.id] = cur + 1;
            drawCart();
          }));
          box.appendChild(line2);
        });
        row.appendChild(box);
      }
      list.appendChild(row);
    });

    var gate = '';
    if (needAny > accessAny) gate = VQ.t('{need} products in the basket need an access ticket, but it has only {have}.', { need: needAny, have: accessAny });
    else if (needAdult > accessAdult) gate = VQ.t('{need} products in the basket need an adult access ticket, but it has only {have}.', { need: needAdult, have: accessAdult });
    msg('po-access', gate, false);
    $('po-access').hidden = !gate;

    var total = subtotal + commissionTotal + addonsTotal;
    $('po-subtotal').textContent = lei(subtotal + addonsTotal);
    $('po-commission').textContent = lei(commissionTotal);
    $('po-com-row').hidden = commissionTotal <= 0;
    $('po-total').textContent = lei(total);
    $('po-checkout').disabled = busy || !keys.length || !session || !!gate;
  }
  function iconBtn(icon, label, fn, cls) {
    var b = el('button', { class: cls || '', type: 'button', 'aria-label': label });
    b.appendChild(O.icon(icon));
    b.addEventListener('click', fn);
    return b;
  }
  function bump(key, dir) {
    var it = cart[key];
    if (!it) return;
    var t = types.filter(function (x) { return x.id === it.ticket_type_id; })[0];
    var rules = stepOf(t);
    if (dir > 0) it.qty += rules.step;
    else {
      var next = it.qty - rules.step;
      if (next < rules.min) delete cart[key]; else it.qty = next;
    }
    drawCart();
    drawProducts();
  }
  function clearCart() {
    cart = {};
    drawCart();
    drawProducts();
  }

  /* =================== register =================== */
  function drawCashier() {
    var open = !!session, box = $('po-cash');
    box.classList.toggle('is-open', open);
    box.classList.toggle('is-closed', !open);
    $('po-cash-label').textContent = open ? VQ.t('The register is open') : VQ.t('The register is closed');
    $('po-cash-since').textContent = open ? VQ.t('since {time}', { time: hhmm(session.opened_at) }) + (txt(session.opened_label) ? ' · ' + txt(session.opened_label) : '') : '';
    show('po-open', !open);
    show('po-close', open);
    show('po-xtoggle', open);
    show('po-locked', !open);
    $('po-main').classList.toggle('is-locked', !open);
    if (!open) { $('po-x').hidden = true; $('po-xtoggle').setAttribute('aria-expanded', 'false'); stopX(); }
    else if (!$('po-x').hidden) paintX();
    drawCart();
  }
  function paintX() {
    var live = (session && session.live) || {};
    $('po-x-cash').textContent = lei(live.cash);
    $('po-x-card').textContent = lei(live.card);
    $('po-x-total').textContent = lei(live.total);
    $('po-x-orders').textContent = F.num(F.toNum(live.orders));
    $('po-x-note').textContent = VQ.t('On-site sales only. Updated {time}.', { time: F.date(new Date(), { hour: '2-digit', minute: '2-digit', second: '2-digit' }) });
  }
  function refreshCashier(quiet) {
    if (!eventId) return Promise.resolve();
    return O.api('/organizer/events/' + eventId + '/leisure/cashier/current', { quiet: true }).then(function (r) {
      session = (r && r.data && r.data.session) || null;
      drawCashier();
    }, function () { if (!quiet) session = null; drawCashier(); });
  }
  function startX() { stopX(); paintX(); xTimer = setInterval(function () { refreshCashier(true).then(paintX); }, 10000); }
  function stopX() { if (xTimer) { clearInterval(xTimer); xTimer = null; } }

  /* =================== sessions =================== */
  function loadSessions() {
    var body = $('po-sess-body');
    body.textContent = '';
    body.appendChild(el('p', { class: 'po-empty', text: VQ.t('Loading…') }));
    O.api('/organizer/events/' + eventId + '/leisure/cashier/sessions', { quiet: true }).then(function (r) {
      var list = (r && r.data && r.data.sessions) || [];
      body.textContent = '';
      if (!list.length) { body.appendChild(el('p', { class: 'po-empty', text: VQ.t('No register sessions today.') })); return; }
      var wrap = el('div', { class: 'po-sess' });
      list.forEach(function (s) { wrap.appendChild(sessionCard(s)); });
      body.appendChild(wrap);
    }, function () {
      body.textContent = '';
      body.appendChild(el('p', { class: 'po-empty', text: VQ.t('We could not load the session log.') }));
    });
  }
  function sessionCard(s) {
    var snap = s.snapshot || {}, totals = snap.pos_only_totals || snap.totals || {};
    var pays = Array.isArray(snap.by_payment) ? snap.by_payment.filter(function (p) { return p.method !== 'online'; }) : [];
    var val = function (m) { var f = pays.filter(function (p) { return p.method === m; })[0]; return f ? f.revenue : 0; };
    var card = el('article', { class: 'po-sess-card' + (s.is_open ? ' is-open' : '') });
    card.appendChild(el('div', { class: 'po-sess-top' }, [
      el('b', { text: (s.is_open ? VQ.t('In progress') : VQ.t('Closed')) + ' · ' + hhmm(s.opened_at) + ' - ' + (s.closed_at ? hhmm(s.closed_at) : VQ.t('now')) }),
      el('span', { class: 'org-tag ' + (s.is_open ? 'is-ok' : 'is-muted'), text: txt(s.opened_label) || VQ.t('Register') }),
    ]));
    var nums = el('div', { class: 'po-sess-nums' });
    [[VQ.t('Cash'), val('cash')], [VQ.t('Card'), val('card')], [VQ.t('Total'), totals.revenue != null ? totals.revenue : val('cash') + val('card')], [VQ.t('Orders'), totals.orders]].forEach(function (pair, i) {
      nums.appendChild(el('div', null, [el('p', { text: pair[0] }), el('b', { text: i === 3 ? F.num(F.toNum(pair[1])) : lei(pair[1]) })]));
    });
    card.appendChild(nums);
    return card;
  }
  function exportCsv() {
    var btn = $('po-csv'), label = btn.textContent;
    btn.disabled = true;
    var token = '';
    try { token = localStorage.getItem('bileteonline_organizer_token') || ''; } catch (e) {}
    var url = '/api/proxy.php?action=organizer.event.leisure.cashier.sales-csv&event=' + encodeURIComponent(eventId) + '&date=' + encodeURIComponent($('po-date').value || today());
    fetch(url, { headers: token ? { Authorization: 'Bearer ' + token } : {} }).then(function (res) {
      if (!res.ok) throw new Error('HTTP ' + res.status);
      return res.text();
    }).then(function (text) {
      if (!text || text.charAt(0) === '<') throw new Error(VQ.t('session expired'));
      var blob = new Blob([text], { type: 'text/csv;charset=utf-8' });
      var a = document.createElement('a'), href = URL.createObjectURL(blob);
      a.href = href;
      a.download = 'sales-' + ($('po-date').value || today()) + '.csv';
      document.body.appendChild(a);
      a.click();
      document.body.removeChild(a);
      setTimeout(function () { URL.revokeObjectURL(href); }, 4000);
      O.flash(VQ.t('The report was downloaded.'));
    }).catch(function (e) {
      O.flash(e && e.message ? VQ.t('We could not export the report ({reason}).', { reason: e.message }) : VQ.t('We could not export the report.'), true);
    }).then(function () { btn.disabled = false; btn.textContent = label; });
  }

  /* =================== the sale =================== */
  function checkout() {
    if (busy || $('po-checkout').disabled) return;
    var keys = Object.keys(cart);
    if (!keys.length) return;
    if (!session) { msg('po-error', VQ.t('The register is closed. Open it before selling.'), true); return; }
    var hasCompany = !!($('po-co-name').value.trim() || $('po-co-cui').value.trim());
    if (hasCompany && !$('po-co-invoice').checked) {
      msg('po-error', VQ.t('You filled in company details. Tick "Generate a tax invoice" or clear the company details.'), true);
      $('po-co-invoice').focus();
      return;
    }
    busy = true;
    msg('po-error', '', false);
    var btn = $('po-checkout');
    btn.disabled = true;
    btn.textContent = VQ.t('Processing…');
    var items = keys.map(function (k) {
      var it = cart[k], addons = Object.keys(it.addons || {}).filter(function (a) { return it.addons[a] > 0; }).map(function (a) { return { addon_id: a, qty: it.addons[a] }; });
      var row = { ticket_type_id: it.ticket_type_id, qty: it.qty, variant_id: it.variant ? it.variant.id : null };
      if (it.slot_time) { row.slot_time = it.slot_time; row.start_time = it.slot_time; }
      if (addons.length) row.addons = addons;
      return row;
    });
    var body = {
      date: $('po-date').value || today(),
      items: items,
      customer: {
        name: $('po-c-name').value.trim() || null,
        email: $('po-c-email').value.trim() || null,
        phone: $('po-c-phone').value.trim() || null,
        vehicle_plate: $('po-c-plate').value.trim() || null,
        notes: $('po-c-notes').value.trim() || null,
      },
      generate_invoice: $('po-co-invoice').checked,
      locale: locale,
      payment_method: payment,
    };
    if (hasCompany) {
      body.company = {
        name: $('po-co-name').value.trim() || null,
        cui: $('po-co-cui').value.trim() || null,
        reg_no: $('po-co-reg').value.trim() || null,
        address: $('po-co-address').value.trim() || null,
        iban: $('po-co-iban').value.trim() || null,
        contact_person: $('po-co-contact').value.trim() || null,
      };
    }
    O.api('/organizer/events/' + eventId + '/leisure/pos-sale', { method: 'POST', body: body }).then(function (r) {
      var data = (r && r.data) || {};
      lastSale = data;
      printerReprintState();
      return printSale(data).then(function () {
        clearCart();
        ['po-c-name', 'po-c-email', 'po-c-phone', 'po-c-plate', 'po-c-notes', 'po-co-name', 'po-co-cui', 'po-co-reg', 'po-co-address', 'po-co-iban', 'po-co-contact'].forEach(function (id) { $(id).value = ''; });
        $('po-co-invoice').checked = false;
        O.flash(data.order && data.order.order_number ? VQ.t('Sale completed. Order {number}.', { number: txt(data.order.order_number) }) : VQ.t('Sale completed.'));
        if (data.invoice_requested && data.company_billing && data.order && data.order.id) invoiceOffer(data.order.id, data.company_billing);
        refreshCashier(true);
        if (!$('po-sessions').hidden) loadSessions();
      });
    }, function (err) {
      msg('po-error', (err && err.message) || VQ.t('We could not complete the sale. Try again.'), true);
    }).then(function () {
      busy = false;
      btn.textContent = VQ.t('Complete the sale');
      drawCart();
    });
  }
  function printSale(data) {
    var thermal = false;
    var step = Promise.resolve();
    if (typeof window.PosPrinter !== 'undefined' && window.PosPrinter.isSupported() && window.PosPrinter.getAutoPrintEnabled()) {
      step = printTickets(data).then(function (done) { thermal = done; }, function () { thermal = false; });
    }
    return step.then(function () {
      if (thermal && $('po-co-invoice').checked) return printInvoice(data).catch(function () {});
    }).then(function () {
      if (thermal) return;
      buildReceipt(data);
      $('po-receipt').hidden = false;
      return new Promise(function (resolve) {
        setTimeout(function () {
          try { window.print(); } catch (e) {}
          $('po-receipt').hidden = true;
          resolve();
        }, 150);
      });
    });
  }
  function printTickets(data) {
    var P = window.PosPrinter;
    return P.isReady().then(function (ready) {
      if (!ready) return false;
      var tickets = Array.isArray(data.tickets) ? data.tickets : [];
      if (!tickets.length) return false;
      var order = data.order || {}, primary = data.issuer || issuers.primary || {}, secondary = data.issuer_secondary || issuers.secondary || null;
      var chain = Promise.resolve();
      tickets.forEach(function (t) {
        if (t.service_category === 'package') return;
        chain = chain.then(function () {
          return P.printTicket({
            issuer: (t.issuing_company === 'secondary' && secondary) ? secondary : primary,
            event_name: txt((data.event && data.event.name) || currentEventName()),
            ticket_type_name: txt(t.ticket_type) || VQ.t('Ticket'),
            variant_label: txt(t.variant && (t.variant.label || t.variant.name)),
            code: t.code || '',
            qr_data: t.code || '',
            visit_date: order.visit_date || '',
            sold_at: F.date(new Date(), { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' }),
            pos_name: txt(order.cashier_name) ? VQ.t('POS {name}', { name: txt(order.cashier_name) }) : VQ.t('POS on site'),
          });
        }).then(function () { return new Promise(function (r) { setTimeout(r, 150); }); });
      });
      return chain.then(function () { return true; });
    });
  }
  function printInvoice(data) {
    var P = window.PosPrinter, order = data.order || {}, items = Array.isArray(data.items) ? data.items : [];
    var issuer = issuers.primary || data.issuer || {};
    var total = items.reduce(function (s, i) { return s + F.toNum(i.line_total); }, 0);
    return P.printInvoice({
      issuer: issuer,
      series: data.invoice_number || (txt(issuer.invoice_series) || 'P1') + '/' + String(txt(order.order_number) || Date.now()).replace(/\D/g, '').slice(-8),
      customer: data.customer || {},
      buyer_company: data.company_billing || null,
      issued_at: order.paid_at || new Date(),
      items: items.map(function (i) { return { name: txt(i.name), qty: i.qty, unit_price: i.unit_price, total: i.line_total }; }),
      total: total,
      currency: order.currency || 'EUR',
      vat_payer: !!issuer.vat_payer,
      vat_rate: F.toNum(issuer.vat_rate),
    });
  }
  function buildReceipt(data) {
    var box = $('po-receipt'), order = data.order || {}, issuer = data.issuer || issuers.primary || {};
    var items = Array.isArray(data.items) ? data.items : [], tickets = Array.isArray(data.tickets) ? data.tickets : [];
    box.textContent = '';
    box.appendChild(el('h2', { text: txt(issuer.name) || currentEventName() || VQ.t('Venue') }));
    if (txt(issuer.tax_id)) box.appendChild(el('div', { class: 'po-rc-row', text: VQ.t('Tax ID: {id}', { id: txt(issuer.tax_id) }) }));
    if (txt(issuer.address)) box.appendChild(el('div', { class: 'po-rc-row', text: txt(issuer.address) }));
    box.appendChild(el('div', { class: 'po-rc-sep' }));
    var row = function (a, b) { return el('div', { class: 'po-rc-row' }, [el('span', { text: a }), el('span', { text: b })]); };
    box.appendChild(row(VQ.t('Order'), txt(order.order_number)));
    box.appendChild(row(VQ.t('Date'), stamp(order.paid_at || new Date())));
    if (order.visit_date) box.appendChild(row(VQ.t('Visit'), txt(order.visit_date)));
    if (data.customer && txt(data.customer.name)) box.appendChild(row(VQ.t('Customer'), txt(data.customer.name)));
    box.appendChild(el('div', { class: 'po-rc-sep' }));
    items.forEach(function (i) {
      box.appendChild(row(i.qty + ' × ' + txt(i.name), lei(i.line_total)));
    });
    box.appendChild(el('div', { class: 'po-rc-sep' }));
    box.appendChild(el('div', { class: 'po-rc-row po-rc-total' }, [el('span', { text: VQ.t('TOTAL') }), el('span', { text: lei(order.total) })]));
    box.appendChild(row(VQ.t('Payment'), PAY[txt(order.payment_method) || payment] || payment));
    if (tickets.length) {
      box.appendChild(el('div', { class: 'po-rc-sep' }));
      tickets.forEach(function (t) { box.appendChild(el('small', { text: txt(t.code) + ' · ' + txt(t.ticket_type) })); });
    }
  }
  function invoiceOffer(orderId, company) {
    var bar = el('p', { class: 'po-msg' }, [VQ.t('Order with company details: {name}.', { name: txt(company.name) || VQ.t('company') }) + ' ']);
    var b = el('button', { class: 'btn btn-ghost', type: 'button', text: VQ.t('Generate the invoice') });
    b.addEventListener('click', function () {
      b.disabled = true;
      b.textContent = VQ.t('Generating…');
      O.api('/organizer/orders/' + orderId + '/generate-invoice', { method: 'POST', body: {} }).then(function (r) {
        var url = O.safeHref((r && r.data && r.data.invoice_url) || '', '');
        O.flash(VQ.t('The invoice was generated.'));
        if (url) window.open(url, '_blank', 'noopener');
        bar.remove();
      }, function () {
        b.disabled = false;
        b.textContent = VQ.t('Generate the invoice');
        O.flash(VQ.t('We could not generate the invoice.'), true);
      });
    });
    bar.appendChild(b);
    var host = $('po-cash');
    host.parentNode.insertBefore(bar, host.nextSibling);
  }
  function currentEventName() {
    var e = events.filter(function (x) { return x.id === eventId; })[0];
    return e ? txt(e.title || e.name) : '';
  }

  /* =================== printer panel =================== */
  function printerUi() {
    var P = window.PosPrinter, state = $('po-pr-state'), info = $('po-pr-info');
    if (typeof P === 'undefined' || !P.isSupported()) {
      state.textContent = VQ.t('Browser not supported');
      state.className = 'po-pr-state is-bad';
      info.textContent = VQ.t('Direct printing needs Chrome or Edge. Without it, the receipt is printed through the browser\'s print dialog.');
      $('po-pr-connect').disabled = true;
      $('po-pr-test').disabled = true;
      $('po-pr-auto').disabled = true;
      return;
    }
    $('po-pr-auto').checked = P.getAutoPrintEnabled();
    P.isReady().then(function (ready) {
      state.textContent = ready ? VQ.t('Connected') : VQ.t('Not connected');
      state.className = 'po-pr-state ' + (ready ? 'is-ok' : '');
      info.textContent = ready ? VQ.t('Tickets can be printed straight to the printer.') : VQ.t('Connect the printer to print tickets directly, without the print dialog.');
    });
  }
  function printerReprintState() {
    var btn = $('po-pr-reprint'), meta = $('po-pr-reprint-meta');
    var tickets = lastSale && Array.isArray(lastSale.tickets) ? lastSale.tickets : [];
    btn.hidden = !tickets.length;
    if (tickets.length) meta.textContent = ' · ' + (txt(lastSale.order && lastSale.order.order_number) || '') + ' · ' + VQ.n(tickets.length, 'ticket', 'tickets');
  }

  /* =================== activities + config =================== */
  function loadEvents() {
    return O.api('/organizer/events?per_page=50&page=1').then(function (r) {
      var rows = Array.isArray(r && r.data) ? r.data : (r && r.data && r.data.items) || [];
      events = rows.filter(function (e) { return e && e.id != null; });
      var venues = events.filter(function (e) { return (e.display_template || '') === 'leisure_venue'; });
      if (!venues.length && events.length) return probe(events.slice(0, 8));
      return venues;
    });
  }
  function probe(list) {
    var found = [];
    var chain = Promise.resolve();
    list.forEach(function (e) {
      chain = chain.then(function () {
        return O.api('/organizer/events/' + e.id + '/leisure/config', { quiet: true }).then(function () { found.push(e); }, function () {});
      });
    });
    return chain.then(function () { return found; });
  }
  function fillEvents(venues) {
    var sel = $('po-event');
    sel.textContent = '';
    venues.forEach(function (e) { sel.appendChild(new Option(txt(e.title || e.name) || VQ.t('Venue #{id}', { id: e.id }), String(e.id))); });
    sel.disabled = venues.length < 2;
    eventId = venues.length ? F.toNum(venues[0].id) : null;
  }
  function loadConfig() {
    show('po-prod-skel', true);
    return O.api('/organizer/events/' + eventId + '/leisure/config').then(function (r) {
      var d = (r && r.data) || {};
      types = Array.isArray(d.ticket_types) ? d.ticket_types : [];
      categories = Array.isArray(d.ticket_categories) ? d.ticket_categories : [];
      issuers = d.issuers || {};
      commission = d.commission || commission;
      clearCart();
      drawProducts();
    });
  }

  /* =================== wiring =================== */
  $('po-date').value = today();
  $('po-q').addEventListener('input', drawProducts);
  $('po-clear').addEventListener('click', clearCart);
  $('po-checkout').addEventListener('click', checkout);
  $('po-retry').addEventListener('click', function () { show('po-failed', false); start(); });
  $('po-event').addEventListener('change', function () {
    eventId = F.toNum(this.value);
    refreshCashier();
    loadConfig();
  });
  qsa('.po-pay-b').forEach(function (b) {
    b.addEventListener('click', function () {
      payment = b.getAttribute('data-pay');
      qsa('.po-pay-b').forEach(function (x) { var on = x === b; x.classList.toggle('is-on', on); x.setAttribute('aria-pressed', String(on)); });
    });
  });
  qsa('.po-lang-b').forEach(function (b) {
    b.addEventListener('click', function () {
      locale = b.getAttribute('data-lang');
      qsa('.po-lang-b').forEach(function (x) { var on = x === b; x.classList.toggle('is-on', on); x.setAttribute('aria-pressed', String(on)); });
    });
  });
  $('po-open').addEventListener('click', function () {
    var b = $('po-open');
    b.disabled = true;
    O.api('/organizer/events/' + eventId + '/leisure/cashier/open', { method: 'POST', body: {} }).then(function (r) {
      session = (r && r.data && r.data.session) || null;
      drawCashier();
      O.flash(VQ.t('The register was opened at {time}.', { time: hhmm(session && session.opened_at) }));
    }, function (err) {
      O.flash((err && err.message) || VQ.t('We could not open the register.'), true);
    }).then(function () { b.disabled = false; });
  });
  $('po-xtoggle').addEventListener('click', function () {
    var open = $('po-x').hidden;
    $('po-x').hidden = !open;
    this.setAttribute('aria-expanded', String(open));
    if (open) { refreshCashier(true).then(startX); } else stopX();
  });
  $('po-sessions-btn').addEventListener('click', function () {
    var open = $('po-sessions').hidden;
    $('po-sessions').hidden = !open;
    this.setAttribute('aria-expanded', String(open));
    if (open) loadSessions();
  });
  $('po-sess-refresh').addEventListener('click', loadSessions);
  $('po-csv').addEventListener('click', exportCsv);
  $('po-close').addEventListener('click', function () {
    var d = $('po-close-d');
    $('po-close-body').textContent = '';
    $('po-close-body').appendChild(el('p', { class: 'po-hint', text: VQ.t('The register has been open since {time}. The cash you hand over and the card payments of this session are saved.', { time: hhmm(session && session.opened_at) }) }));
    if (typeof d.showModal === 'function') d.showModal(); else d.setAttribute('open', '');
  });
  qsa('[data-po-close]').forEach(function (b) {
    b.addEventListener('click', function () {
      var d = $('po-close-d');
      if (typeof d.close === 'function') { if (d.open) d.close(); } else d.removeAttribute('open');
    });
  });
  $('po-close-confirm').addEventListener('click', function () {
    var b = this;
    b.disabled = true;
    b.textContent = VQ.t('Closing…');
    O.api('/organizer/events/' + eventId + '/leisure/cashier/close', { method: 'POST', body: {} }).then(function (r) {
      var snap = (r && r.data && r.data.snapshot) || {};
      var pays = Array.isArray(snap.by_payment) ? snap.by_payment.filter(function (p) { return p.method !== 'online'; }) : [];
      var val = function (m) { var f = pays.filter(function (p) { return p.method === m; })[0]; return f ? f.revenue : 0; };
      session = null;
      drawCashier();
      O.flash(VQ.t('The register was closed. Cash to hand over: {cash}, card: {card}.', { cash: lei(val('cash')), card: lei(val('card')) }));
      var d = $('po-close-d');
      if (typeof d.close === 'function') { if (d.open) d.close(); } else d.removeAttribute('open');
      if (!$('po-sessions').hidden) loadSessions();
    }, function (err) {
      O.flash((err && err.message) || VQ.t('We could not close the register.'), true);
    }).then(function () { b.disabled = false; b.textContent = VQ.t('Close the register'); });
  });
  $('po-anaf').addEventListener('click', function () {
    var cui = $('po-co-cui').value.trim();
    if (!cui) { msg('po-anaf-msg', VQ.t('Enter a tax ID first.'), true); return; }
    var b = this;
    b.disabled = true;
    msg('po-anaf-msg', VQ.t('Looking up the company…'), false);
    O.api('/organizer/settings/verify-cui', { method: 'POST', body: { cui: cui } }).then(function (r) {
      var d = (r && r.data) || {};
      // The proxy answers with company_name / reg_com / full_address (the shape the settings page reads).
      var co = d.company || d, name = txt(co.company_name || co.name);
      var address = txt(co.full_address) || [txt(co.address), txt(co.city), txt(co.county)].filter(Boolean).join(', ');
      if (name) $('po-co-name').value = name;
      if (txt(co.reg_com || co.registration_number || co.reg_no)) $('po-co-reg').value = txt(co.reg_com || co.registration_number || co.reg_no);
      if (address) $('po-co-address').value = address;
      msg('po-anaf-msg', !name ? VQ.t('No details were returned for this tax ID.') : co.deregistered ? VQ.t('The company is listed as struck off. Check the details before issuing the invoice.') : VQ.t('Details filled in from the tax register.'), !name || !!co.deregistered);
    }, function (err) {
      msg('po-anaf-msg', (err && err.message) || VQ.t('We could not query the tax register.'), true);
    }).then(function () { b.disabled = false; });
  });
  $('po-pr-connect').addEventListener('click', function () {
    if (typeof window.PosPrinter === 'undefined') return;
    var b = this;
    b.disabled = true;
    msg('po-pr-error', '', true);
    window.PosPrinter.connect().then(function () { printerUi(); }, function (e) {
      msg('po-pr-error', (e && e.message) || VQ.t('The connection failed.'), true);
    }).then(function () { b.disabled = false; });
  });
  $('po-pr-test').addEventListener('click', function () {
    if (typeof window.PosPrinter === 'undefined' || typeof window.PosPrinter.printTestTicket !== 'function') { O.flash(VQ.t('Connect the printer first.'), true); return; }
    window.PosPrinter.printTestTicket().then(function () { O.flash(VQ.t('Test sent to the printer.')); }, function (e) { msg('po-pr-error', (e && e.message) || VQ.t('The test failed.'), true); });
  });
  $('po-pr-auto').addEventListener('change', function () {
    if (typeof window.PosPrinter !== 'undefined') window.PosPrinter.setAutoPrintEnabled(this.checked);
  });
  $('po-pr-reprint').addEventListener('click', function () {
    if (!lastSale) return;
    printTickets(lastSale).then(function (done) { O.flash(done ? VQ.t('The order was reprinted.') : VQ.t('The printer is not ready.'), !done); }, function () { O.flash(VQ.t('The reprint failed.'), true); });
  });

  function start() {
    show('po-main', false);
    show('po-none', false);
    loadEvents().then(function (venues) {
      if (!venues.length) { show('po-none', true); return; }
      fillEvents(venues);
      show('po-main', true);
      printerUi();
      printerReprintState();
      return Promise.all([refreshCashier(), loadConfig()]);
    }, function (err) {
      if (err && err.status === 401) return;
      show('po-failed', true);
    });
  }
  O.ready.then(function (ok) { if (ok) start(); });
})();
