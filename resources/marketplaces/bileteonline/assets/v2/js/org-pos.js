/* bilete.online v2: on-site sale (/organizator/pos) — the POS ported from Ambilet's "InfoPoint" onto the v2 shell.
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

  var CAT = { access: 'Acces', parking: 'Parcare', rental: 'Închiriere', activity: 'Activitate', extra: 'Extra', package: 'Pachet' };
  var PAY = { cash: 'Cash', card: 'Card', invoice: 'Link pe email' };
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
        name: txt(t.name) + (variant ? ' — ' + txt(variant.label) : '') + (slot ? ' · ' + slot : ''),
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
    $('po-slot-hint').textContent = 'Între ' + (cfg.first_slot || '09:00') + ' și ' + (cfg.last_slot || '18:00') + '.';
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
      list.appendChild(el('li', { class: 'po-lines-empty', text: 'Coșul e gol. Apasă pe un produs ca să îl adaugi.' }));
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
        el('div', { class: 'po-line-t' }, [el('b', { text: it.name }), el('small', { text: lei(it.price) + ' / buc' })]),
        el('span', { class: 'po-line-sum', text: lei(line) }),
      ]));
      var qty = el('div', { class: 'po-qty' });
      qty.appendChild(iconBtn('minus', 'Scade', function () { bump(key, -1); }));
      qty.appendChild(el('b', { text: String(it.qty) }));
      qty.appendChild(iconBtn('plus', 'Adaugă', function () { bump(key, 1); }));
      qty.appendChild(iconBtn('trash', 'Scoate din coș', function () { delete cart[key]; drawCart(); drawProducts(); }, 'po-del'));
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
            el('span', null, [el('b', { text: txt(a.label) }), el('small', { text: (included ? ' · ' + included + ' incluse' : '') + ' · ' + lei(a.price) + '/buc' })]),
          ]);
          line2.appendChild(iconBtn('minus', 'Scade ' + txt(a.label), function () {
            var cur = it.addons[a.id] || 0;
            if (cur <= 1) delete it.addons[a.id]; else it.addons[a.id] = cur - 1;
            drawCart();
          }));
          line2.appendChild(el('b', { text: String(have) }));
          line2.appendChild(iconBtn('plus', 'Adaugă ' + txt(a.label), function () {
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
    if (needAny > accessAny) gate = 'Ai ' + needAny + ' produse care cer bilet de acces, dar în coș sunt ' + accessAny + '.';
    else if (needAdult > accessAdult) gate = 'Ai ' + needAdult + ' produse care cer bilet de acces adult, dar în coș sunt ' + accessAdult + '.';
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
    $('po-cash-label').textContent = open ? 'Casa este deschisă' : 'Casa este închisă';
    $('po-cash-since').textContent = open ? 'de la ' + hhmm(session.opened_at) + (txt(session.opened_label) ? ' · ' + txt(session.opened_label) : '') : '';
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
    $('po-x-note').textContent = 'Doar vânzările din locație. Actualizat ' + F.date(new Date(), { hour: '2-digit', minute: '2-digit', second: '2-digit' }) + '.';
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
    body.appendChild(el('p', { class: 'po-empty', text: 'Se încarcă…' }));
    O.api('/organizer/events/' + eventId + '/leisure/cashier/sessions', { quiet: true }).then(function (r) {
      var list = (r && r.data && r.data.sessions) || [];
      body.textContent = '';
      if (!list.length) { body.appendChild(el('p', { class: 'po-empty', text: 'Nicio sesiune de casă astăzi.' })); return; }
      var wrap = el('div', { class: 'po-sess' });
      list.forEach(function (s) { wrap.appendChild(sessionCard(s)); });
      body.appendChild(wrap);
    }, function () {
      body.textContent = '';
      body.appendChild(el('p', { class: 'po-empty', text: 'Nu am putut încărca desfășurătorul.' }));
    });
  }
  function sessionCard(s) {
    var snap = s.snapshot || {}, totals = snap.pos_only_totals || snap.totals || {};
    var pays = Array.isArray(snap.by_payment) ? snap.by_payment.filter(function (p) { return p.method !== 'online'; }) : [];
    var val = function (m) { var f = pays.filter(function (p) { return p.method === m; })[0]; return f ? f.revenue : 0; };
    var card = el('article', { class: 'po-sess-card' + (s.is_open ? ' is-open' : '') });
    card.appendChild(el('div', { class: 'po-sess-top' }, [
      el('b', { text: (s.is_open ? 'În desfășurare' : 'Închisă') + ' · ' + hhmm(s.opened_at) + ' - ' + (s.closed_at ? hhmm(s.closed_at) : 'acum') }),
      el('span', { class: 'org-tag ' + (s.is_open ? 'is-ok' : 'is-muted'), text: txt(s.opened_label) || 'Casă' }),
    ]));
    var nums = el('div', { class: 'po-sess-nums' });
    [['Cash', val('cash')], ['Card', val('card')], ['Total', totals.revenue != null ? totals.revenue : val('cash') + val('card')], ['Comenzi', totals.orders]].forEach(function (pair, i) {
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
      if (!text || text.charAt(0) === '<') throw new Error('sesiune expirată');
      var blob = new Blob([text], { type: 'text/csv;charset=utf-8' });
      var a = document.createElement('a'), href = URL.createObjectURL(blob);
      a.href = href;
      a.download = 'vanzari-' + ($('po-date').value || today()) + '.csv';
      document.body.appendChild(a);
      a.click();
      document.body.removeChild(a);
      setTimeout(function () { URL.revokeObjectURL(href); }, 4000);
      O.flash('Raportul a fost descărcat.');
    }).catch(function (e) {
      O.flash('Nu am putut exporta raportul' + (e && e.message ? ' (' + e.message + ')' : '') + '.', true);
    }).then(function () { btn.disabled = false; btn.textContent = label; });
  }

  /* =================== the sale =================== */
  function checkout() {
    if (busy || $('po-checkout').disabled) return;
    var keys = Object.keys(cart);
    if (!keys.length) return;
    if (!session) { msg('po-error', 'Casa este închisă. Deschide casa înainte de vânzare.', true); return; }
    var hasCompany = !!($('po-co-name').value.trim() || $('po-co-cui').value.trim());
    if (hasCompany && !$('po-co-invoice').checked) {
      msg('po-error', 'Ai completat date de firmă. Bifează „Generează factură fiscală” sau șterge datele firmei.', true);
      $('po-co-invoice').focus();
      return;
    }
    busy = true;
    msg('po-error', '', false);
    var btn = $('po-checkout');
    btn.disabled = true;
    btn.textContent = 'Se procesează…';
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
        O.flash('Vânzare finalizată. ' + (data.order && data.order.order_number ? 'Comanda ' + txt(data.order.order_number) + '.' : ''));
        if (data.invoice_requested && data.company_billing && data.order && data.order.id) invoiceOffer(data.order.id, data.company_billing);
        refreshCashier(true);
        if (!$('po-sessions').hidden) loadSessions();
      });
    }, function (err) {
      msg('po-error', (err && err.message) || 'Nu am putut finaliza vânzarea. Încearcă din nou.', true);
    }).then(function () {
      busy = false;
      btn.textContent = 'Finalizează vânzarea';
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
            ticket_type_name: txt(t.ticket_type) || 'Bilet',
            variant_label: txt(t.variant && (t.variant.label || t.variant.name)),
            code: t.code || '',
            qr_data: t.code || '',
            visit_date: order.visit_date || '',
            sold_at: F.date(new Date(), { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' }),
            pos_name: 'POS ' + (txt(order.cashier_name) || 'la fața locului'),
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
      currency: order.currency || 'RON',
      vat_payer: !!issuer.vat_payer,
      vat_rate: F.toNum(issuer.vat_rate),
    });
  }
  function buildReceipt(data) {
    var box = $('po-receipt'), order = data.order || {}, issuer = data.issuer || issuers.primary || {};
    var items = Array.isArray(data.items) ? data.items : [], tickets = Array.isArray(data.tickets) ? data.tickets : [];
    box.textContent = '';
    box.appendChild(el('h2', { text: txt(issuer.name) || currentEventName() || 'Locație' }));
    if (txt(issuer.tax_id)) box.appendChild(el('div', { class: 'po-rc-row', text: 'CIF: ' + txt(issuer.tax_id) }));
    if (txt(issuer.address)) box.appendChild(el('div', { class: 'po-rc-row', text: txt(issuer.address) }));
    box.appendChild(el('div', { class: 'po-rc-sep' }));
    var row = function (a, b) { return el('div', { class: 'po-rc-row' }, [el('span', { text: a }), el('span', { text: b })]); };
    box.appendChild(row('Comandă', txt(order.order_number)));
    box.appendChild(row('Data', stamp(order.paid_at || new Date())));
    if (order.visit_date) box.appendChild(row('Vizită', txt(order.visit_date)));
    if (data.customer && txt(data.customer.name)) box.appendChild(row('Client', txt(data.customer.name)));
    box.appendChild(el('div', { class: 'po-rc-sep' }));
    items.forEach(function (i) {
      box.appendChild(row(i.qty + ' × ' + txt(i.name), lei(i.line_total)));
    });
    box.appendChild(el('div', { class: 'po-rc-sep' }));
    box.appendChild(el('div', { class: 'po-rc-row po-rc-total' }, [el('span', { text: 'TOTAL' }), el('span', { text: lei(order.total) })]));
    box.appendChild(row('Plată', PAY[txt(order.payment_method) || payment] || payment));
    if (tickets.length) {
      box.appendChild(el('div', { class: 'po-rc-sep' }));
      tickets.forEach(function (t) { box.appendChild(el('small', { text: txt(t.code) + ' · ' + txt(t.ticket_type) })); });
    }
  }
  function invoiceOffer(orderId, company) {
    var bar = el('p', { class: 'po-msg' }, ['Comandă cu date de firmă: ' + (txt(company.name) || 'firmă') + '. ']);
    var b = el('button', { class: 'btn btn-ghost', type: 'button', text: 'Generează factura' });
    b.addEventListener('click', function () {
      b.disabled = true;
      b.textContent = 'Se generează…';
      O.api('/organizer/orders/' + orderId + '/generate-invoice', { method: 'POST', body: {} }).then(function (r) {
        var url = O.safeHref((r && r.data && r.data.invoice_url) || '', '');
        O.flash('Factura a fost generată.');
        if (url) window.open(url, '_blank', 'noopener');
        bar.remove();
      }, function () {
        b.disabled = false;
        b.textContent = 'Generează factura';
        O.flash('Nu am putut genera factura.', true);
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
      state.textContent = 'Browser neacceptat';
      state.className = 'po-pr-state is-bad';
      info.textContent = 'Tipărirea directă cere Chrome sau Edge. Fără ea, bonul se tipărește prin fereastra de print a browserului.';
      $('po-pr-connect').disabled = true;
      $('po-pr-test').disabled = true;
      $('po-pr-auto').disabled = true;
      return;
    }
    $('po-pr-auto').checked = P.getAutoPrintEnabled();
    P.isReady().then(function (ready) {
      state.textContent = ready ? 'Conectată' : 'Neconectată';
      state.className = 'po-pr-state ' + (ready ? 'is-ok' : '');
      info.textContent = ready ? 'Biletele se pot tipări direct pe imprimantă.' : 'Conectează imprimanta ca să tipărești biletele direct, fără fereastra de print.';
    });
  }
  function printerReprintState() {
    var btn = $('po-pr-reprint'), meta = $('po-pr-reprint-meta');
    var tickets = lastSale && Array.isArray(lastSale.tickets) ? lastSale.tickets : [];
    btn.hidden = !tickets.length;
    if (tickets.length) meta.textContent = ' · ' + (txt(lastSale.order && lastSale.order.order_number) || '') + ' · ' + F.count(tickets.length, 'bilet', 'bilete');
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
    venues.forEach(function (e) { sel.appendChild(new Option(txt(e.title || e.name) || 'Locație #' + e.id, String(e.id))); });
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
      O.flash('Casa a fost deschisă la ' + hhmm(session && session.opened_at) + '.');
    }, function (err) {
      O.flash((err && err.message) || 'Nu am putut deschide casa.', true);
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
    $('po-close-body').appendChild(el('p', { class: 'po-hint', text: 'Casa e deschisă de la ' + hhmm(session && session.opened_at) + '. Se salvează cât cash predai și cât s-a încasat pe card în această sesiune.' }));
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
    b.textContent = 'Se închide…';
    O.api('/organizer/events/' + eventId + '/leisure/cashier/close', { method: 'POST', body: {} }).then(function (r) {
      var snap = (r && r.data && r.data.snapshot) || {};
      var pays = Array.isArray(snap.by_payment) ? snap.by_payment.filter(function (p) { return p.method !== 'online'; }) : [];
      var val = function (m) { var f = pays.filter(function (p) { return p.method === m; })[0]; return f ? f.revenue : 0; };
      session = null;
      drawCashier();
      O.flash('Casa a fost închisă. Cash de predat: ' + lei(val('cash')) + ', card: ' + lei(val('card')) + '.');
      var d = $('po-close-d');
      if (typeof d.close === 'function') { if (d.open) d.close(); } else d.removeAttribute('open');
      if (!$('po-sessions').hidden) loadSessions();
    }, function (err) {
      O.flash((err && err.message) || 'Nu am putut închide casa.', true);
    }).then(function () { b.disabled = false; b.textContent = 'Închide casa'; });
  });
  $('po-anaf').addEventListener('click', function () {
    var cui = $('po-co-cui').value.trim();
    if (!cui) { msg('po-anaf-msg', 'Scrie întâi un CUI.', true); return; }
    var b = this;
    b.disabled = true;
    msg('po-anaf-msg', 'Se caută la ANAF…', false);
    O.api('/organizer/settings/verify-cui', { method: 'POST', body: { cui: cui } }).then(function (r) {
      var d = (r && r.data) || {};
      var co = d.company || d;
      if (txt(co.name)) $('po-co-name').value = txt(co.name);
      if (txt(co.registration_number || co.reg_no)) $('po-co-reg').value = txt(co.registration_number || co.reg_no);
      if (txt(co.address)) $('po-co-address').value = txt(co.address);
      msg('po-anaf-msg', txt(co.name) ? 'Date completate din ANAF.' : 'ANAF nu a returnat date pentru acest CUI.', !txt(co.name));
    }, function (err) {
      msg('po-anaf-msg', (err && err.message) || 'Nu am putut interoga ANAF.', true);
    }).then(function () { b.disabled = false; });
  });
  $('po-pr-connect').addEventListener('click', function () {
    if (typeof window.PosPrinter === 'undefined') return;
    var b = this;
    b.disabled = true;
    msg('po-pr-error', '', true);
    window.PosPrinter.connect().then(function () { printerUi(); }, function (e) {
      msg('po-pr-error', (e && e.message) || 'Conectarea nu a reușit.', true);
    }).then(function () { b.disabled = false; });
  });
  $('po-pr-test').addEventListener('click', function () {
    if (typeof window.PosPrinter === 'undefined' || typeof window.PosPrinter.printTestTicket !== 'function') { O.flash('Conectează întâi imprimanta.', true); return; }
    window.PosPrinter.printTestTicket().then(function () { O.flash('Test trimis la imprimantă.'); }, function (e) { msg('po-pr-error', (e && e.message) || 'Testul nu a reușit.', true); });
  });
  $('po-pr-auto').addEventListener('change', function () {
    if (typeof window.PosPrinter !== 'undefined') window.PosPrinter.setAutoPrintEnabled(this.checked);
  });
  $('po-pr-reprint').addEventListener('click', function () {
    if (!lastSale) return;
    printTickets(lastSale).then(function (done) { O.flash(done ? 'Comanda a fost retipărită.' : 'Imprimanta nu e pregătită.', !done); }, function () { O.flash('Retipărirea nu a reușit.', true); });
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
