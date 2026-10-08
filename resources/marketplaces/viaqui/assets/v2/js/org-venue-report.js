/* viaqui.com v2: the venue's report (/organizator/locatie/raport), ported from Ambilet.
   One call to core describes a period: totals, the commission formula, each issuing company, payment methods,
   sources, operators, ticket types and the physical tickets issued. The scans live on their own thirty-day window,
   drawn here as SVG; a day opens into every scan it had. The CSV is built from the loaded report. Runs inside the
   organizer shell (window.BO_ORG); text from the API is always written as text. */
(function () {
  'use strict';
  var O = window.BO_ORG, root = document.getElementById('ve');
  if (!O || !root) return;
  var F = O.fmt, el = O.el, SVGNS = 'http://www.w3.org/2000/svg';
  var $ = function (id) { return document.getElementById(id); };
  var qsa = function (sel) { return Array.prototype.slice.call(root.querySelectorAll(sel)); };
  var CAT = { access: VQ.t('Access'), parking: VQ.t('Parking'), rental: VQ.t('Rental'), activity: VQ.t('Activity'), extra: VQ.t('Extra'), package: VQ.t('Package') };
  var PAY = { cash: [VQ.t('Cash at the register'), 'coins'], card: [VQ.t('Card at the register'), 'credit-card'], online: [VQ.t('Online'), 'globe-simple'] };
  var SRC = { pos: [VQ.t('At the register'), 'coins'], online: [VQ.t('Online'), 'globe-simple'] };
  var MODE = { included: VQ.t('included in the price'), added_on_top: VQ.t('added on top of the price') };
  var eventId = null, from = null, to = null, seq = 0, report = null, currency = 'EUR';
  var scanOffset = 0, scanRows = [], scanSeq = 0, lastFocus = null;

  function txt(v) { return F.flat(v).trim(); }
  function show(id, on) { var e = $(id); if (e) e.hidden = !on; }
  function put(id, value) { var e = $(id); if (e) e.textContent = value; }
  function amount(v) {
    var c = String(currency || '').toUpperCase();
    if (c && window.BileteOnlineUtils && typeof BileteOnlineUtils.formatCurrency === 'function') return BileteOnlineUtils.formatCurrency(F.toNum(v), c);
    return F.money(F.toNum(v));
  }
  function share(part, total) { return total > 0 ? Math.round(part / total * 100) : 0; }
  function day(v, opts) { var d = F.dateOf(v); return d ? F.date(d, opts || { day: 'numeric', month: 'short', year: 'numeric' }) : '—'; }
  function svg(tag, attrs) {
    var n = document.createElementNS(SVGNS, tag);
    Object.keys(attrs || {}).forEach(function (k) { n.setAttribute(k, attrs[k]); });
    return n;
  }
  function state(bodyId, cols, text) {
    var body = $(bodyId);
    body.textContent = '';
    body.appendChild(el('tr', null, el('td', { colspan: cols, class: 've-state', text: text })));
  }
  function listState(id, text) {
    var list = $(id);
    list.textContent = '';
    list.appendChild(el('li', { class: 've-state', text: text }));
  }
  function nameOf(v) { return txt(v) || '—'; }
  function issuerName(key) {
    var by = (report && report.by_issuer) || {}, row = key === 'secondary' ? by.secondary : by.primary;
    return (row && txt(row.name)) || (key === 'secondary' ? VQ.t('Second company') : VQ.t('Main company'));
  }

  /* =================== the period =================== */
  function setRange(value) {
    qsa('.ve-range').forEach(function (b) { b.setAttribute('aria-pressed', String(b.getAttribute('data-range') === value)); });
    show('vr-custom', value === 'custom');
    if (value === 'custom') {
      if (from) $('vr-from').value = from;
      if (to) $('vr-to').value = to;
      return;
    }
    from = F.ymd(new Date(Date.now() - parseInt(value, 10) * 86400000));
    to = F.ymd(new Date());
    load();
  }
  function load() {
    if (!eventId || !from || !to) return;
    var my = ++seq;
    $('ve-main').classList.add('is-loading');
    put('vr-period', VQ.t('Paid between {from} and {to}', { from: day(from), to: day(to) }));
    O.api('/organizer/events/' + eventId + '/leisure/raport?from=' + from + '&to=' + to).then(function (r) {
      if (my !== seq) return;
      report = (r && r.data) || {};
      draw(report);
    }, function (err) {
      if (my !== seq) return;
      if (err && err.status === 401) return;
      report = null;
      blank();
      O.flash(VQ.t('We could not load the report. Try again.'), true);
    }).then(function () { if (my === seq) $('ve-main').classList.remove('is-loading'); });
  }

  /* =================== drawing the report =================== */
  function draw(d) {
    var t = d.totals || {}, cc = d.commission_config || {};
    currency = txt(d.currency) || 'EUR';
    put('vr-revenue', amount(t.revenue));
    put('vr-commission', amount(t.commission));
    put('vr-net', amount(t.net_revenue));
    put('vr-orders', F.num(F.toNum(t.orders)));
    put('vr-tickets', F.num(F.toNum(t.tickets)));
    put('vr-physical-kpi', F.num(F.toNum(d.total_physical_tickets)));
    put('vr-avg', amount(t.avg_order));
    put('vr-avg-line', VQ.t('Average commission per order: {amount}', { amount: amount(t.avg_commission_per_order) }));
    put('vr-formula', (txt(cc.formula) ? txt(cc.formula) + ' · ' : '') + (MODE[cc.mode] ? MODE[cc.mode] + ' · ' : '') + VQ.t('effective {pct}', { pct: F.pct(F.toNum(t.commission_pct_effective), 2) }));
    drawIssuers(d.by_issuer || {});
    var total = F.toNum(t.revenue);
    drawBars('vr-pay', (d.by_payment_method || []).map(function (r) {
      var p = PAY[r.method] || [txt(r.method) || '—', 'coins'];
      return { key: txt(r.method), label: p[0], icon: p[1], row: r };
    }), total);
    drawBars('vr-sources', (d.by_source || []).map(function (r) {
      var s = SRC[r.source] || [txt(r.source) || '—', 'globe-simple'];
      return { key: txt(r.source), label: s[0], icon: s[1], row: r };
    }), total);
    drawCashiers(d.by_cashier || []);
    drawTypes(d.by_ticket_type || []);
    drawComponents(d.by_component_type || [], F.toNum(d.total_physical_tickets));
  }
  function blank() {
    ['vr-revenue', 'vr-commission', 'vr-net', 'vr-orders', 'vr-tickets', 'vr-physical-kpi', 'vr-avg', 'vr-avg-line', 'vr-formula', 'vr-physical'].forEach(function (id) { put(id, '—'); });
    $('vr-issuers').textContent = '';
    listState('vr-pay', VQ.t('We could not load the report.'));
    listState('vr-sources', VQ.t('We could not load the report.'));
    state('vr-cashiers', 5, VQ.t('We could not load the report.'));
    state('vr-types', 6, VQ.t('We could not load the report.'));
    state('vr-comps', 3, VQ.t('We could not load the report.'));
  }
  function drawIssuers(by) {
    var box = $('vr-issuers');
    box.textContent = '';
    [['primary', VQ.t('Main company')], ['secondary', VQ.t('Second company')]].forEach(function (pair) {
      var row = by[pair[0]];
      if (!row) return;
      var gross = F.toNum(row.revenue), vat = F.toNum(row.vat_amount);
      var card = el('article', { class: 've-issuer' + (pair[0] === 'secondary' ? ' is-second' : ''), 'data-issuer': pair[0] });
      card.appendChild(el('p', { class: 've-issuer-k' }, [O.icon('buildings'), document.createTextNode(pair[1])]));
      card.appendChild(el('h2', { class: 've-issuer-n', text: nameOf(row.name) }));
      card.appendChild(el('p', { class: 've-issuer-sum' }, [el('b', { text: amount(gross) }), el('span', { text: VQ.t('gross') })]));
      var facts = [VQ.n(F.toNum(row.tickets), 'ticket', 'tickets'), VQ.t('net {amount}', { amount: amount(row.net_revenue != null ? row.net_revenue : gross) })];
      if (row.vat_payer && vat > 0) facts.push(VQ.t('VAT {rate}: {amount}', { rate: F.pct(F.toNum(row.vat_rate), 0), amount: amount(vat) }));
      else facts.push(VQ.t('no VAT'));
      facts.push(VQ.t('commission {amount}', { amount: amount(row.commission) }));
      card.appendChild(el('p', { class: 've-sub ve-issuer-facts', text: facts.join(' · ') }));
      var pay = row.by_payment || {}, sum = ['cash', 'card', 'online'].reduce(function (s, k) { return s + F.toNum(pay[k]); }, 0);
      var list = el('ul', { class: 've-issuer-pay' });
      if (sum <= 0) list.appendChild(el('li', { class: 've-sub', text: VQ.t('No revenue in the period.') }));
      else ['cash', 'card', 'online'].forEach(function (k) {
        var v = F.toNum(pay[k]);
        list.appendChild(el('li', null, [el('span', null, [O.icon(PAY[k][1]), document.createTextNode(PAY[k][0])]), el('span', { class: 've-sub', text: share(v, sum) + '%' }), el('b', { text: amount(v) })]));
      });
      card.appendChild(list);
      box.appendChild(card);
    });
    box.classList.toggle('is-two', box.children.length > 1);
  }
  function drawBars(id, items, total) {
    var list = $(id);
    list.textContent = '';
    if (!items.length) { listState(id, VQ.t('No sales in the period.')); return; }
    items.sort(function (a, b) { return F.toNum(b.row.revenue) - F.toNum(a.row.revenue); }).forEach(function (it) {
      var r = it.row, pct = share(F.toNum(r.revenue), total);
      var bar = el('i', { 'data-key': it.key });
      bar.style.width = pct + '%';
      list.appendChild(el('li', null, [
        el('div', { class: 've-tb-top' }, [
          el('b', null, [O.icon(it.icon), document.createTextNode(it.label)]),
          el('span', { text: amount(r.revenue) + ' · ' + pct + '%' }),
        ]),
        el('div', { class: 've-tb-bar' }, bar),
        el('small', { class: 've-sub', text: VQ.n(F.toNum(r.orders), 'order', 'orders') + ' · ' + VQ.n(F.toNum(r.tickets), 'ticket', 'tickets') + ' · ' + VQ.t('commission {amount}', { amount: amount(r.commission) }) }),
      ]));
    });
  }
  function drawCashiers(rows) {
    var body = $('vr-cashiers');
    body.textContent = '';
    if (!rows.length) { state('vr-cashiers', 5, VQ.t('No sales at the register in the period.')); return; }
    rows.slice().sort(function (a, b) { return F.toNum(b.revenue) - F.toNum(a.revenue); }).forEach(function (r) {
      body.appendChild(el('tr', null, [
        el('td', null, el('b', { text: nameOf(r.cashier_label) })),
        el('td', { class: 've-r', text: F.num(F.toNum(r.orders)) }),
        el('td', { class: 've-r', text: F.num(F.toNum(r.tickets)) }),
        el('td', { class: 've-r' }, el('b', { text: amount(r.revenue) })),
        el('td', { class: 've-r', text: amount(r.commission) }),
      ]));
    });
  }
  function drawTypes(rows) {
    var body = $('vr-types');
    body.textContent = '';
    if (!rows.length) { state('vr-types', 6, VQ.t('No sales in the period.')); return; }
    rows.slice().sort(function (a, b) { return F.toNum(b.revenue) - F.toNum(a.revenue); }).forEach(function (r) {
      body.appendChild(el('tr', null, [
        el('td', null, el('b', { text: nameOf(r.name) })),
        el('td', { text: CAT[r.service_category] || txt(r.service_category) || '—' }),
        el('td', { text: issuerName(r.issuing_company === 'secondary' ? 'secondary' : 'primary') }),
        el('td', { class: 've-r', text: F.num(F.toNum(r.tickets)) }),
        el('td', { class: 've-r' }, el('b', { text: amount(r.revenue) })),
        el('td', { class: 've-r', text: amount(r.commission) }),
      ]));
    });
  }
  function drawComponents(rows, total) {
    put('vr-physical', F.num(total));
    var body = $('vr-comps');
    body.textContent = '';
    if (!rows.length) { state('vr-comps', 3, VQ.t('No tickets issued in the period.')); return; }
    rows.slice().sort(function (a, b) { return F.toNum(b.tickets) - F.toNum(a.tickets); }).forEach(function (r) {
      body.appendChild(el('tr', null, [
        el('td', { text: nameOf(r.name) }),
        el('td', { text: CAT[r.service_category] || txt(r.service_category) || '—' }),
        el('td', { class: 've-r' }, el('b', { text: F.num(F.toNum(r.tickets)) })),
      ]));
    });
  }

  /* =================== the CSV =================== */
  function exportCsv() {
    if (!report) { O.flash(VQ.t('Load the report first.'), true); return; }
    var d = report, t = d.totals || {}, cc = d.commission_config || {}, rows = [];
    var n = function (v) { return String(Math.round(F.toNum(v) * 100) / 100).replace('.', ','); };
    rows.push([VQ.t('Report'), d.from || from, d.to || to]);
    rows.push([]);
    rows.push([VQ.t('TOTALS')]);
    rows.push([VQ.t('Orders'), t.orders || 0]);
    rows.push([VQ.t('Tickets sold'), t.tickets || 0]);
    rows.push([VQ.t('Physical tickets issued'), d.total_physical_tickets || 0]);
    rows.push([VQ.t('Gross revenue ({currency})', { currency: currency }), n(t.revenue)]);
    rows.push([VQ.t('Ticketing commission ({currency})', { currency: currency }), n(t.commission)]);
    rows.push([VQ.t('Net revenue ({currency})', { currency: currency }), n(t.net_revenue)]);
    rows.push([VQ.t('Effective commission %'), n(t.commission_pct_effective)]);
    rows.push([VQ.t('Average basket ({currency})', { currency: currency }), n(t.avg_order)]);
    rows.push([VQ.t('Commission formula'), txt(cc.formula), MODE[cc.mode] || txt(cc.mode)]);
    rows.push([]);
    rows.push([VQ.t('COMPANY'), VQ.t('Tickets'), VQ.t('Gross'), VQ.t('Net'), VQ.t('VAT'), VQ.t('Commission'), VQ.t('Cash'), VQ.t('Card'), VQ.t('Online')]);
    ['primary', 'secondary'].forEach(function (k) {
      var r = (d.by_issuer || {})[k];
      if (!r) return;
      var p = r.by_payment || {};
      rows.push([txt(r.name), r.tickets || 0, n(r.revenue), n(r.net_revenue), n(r.vat_amount), n(r.commission), n(p.cash), n(p.card), n(p.online)]);
    });
    rows.push([]);
    rows.push([VQ.t('PAYMENT METHOD'), VQ.t('Orders'), VQ.t('Tickets'), VQ.t('Revenue'), VQ.t('Commission')]);
    (d.by_payment_method || []).forEach(function (r) { rows.push([(PAY[r.method] || [r.method])[0], r.orders || 0, r.tickets || 0, n(r.revenue), n(r.commission)]); });
    rows.push([]);
    rows.push([VQ.t('SOURCE'), VQ.t('Orders'), VQ.t('Tickets'), VQ.t('Revenue'), VQ.t('Commission')]);
    (d.by_source || []).forEach(function (r) { rows.push([(SRC[r.source] || [r.source])[0], r.orders || 0, r.tickets || 0, n(r.revenue), n(r.commission)]); });
    rows.push([]);
    rows.push([VQ.t('STAFF'), VQ.t('Orders'), VQ.t('Tickets'), VQ.t('Revenue'), VQ.t('Commission')]);
    (d.by_cashier || []).forEach(function (r) { rows.push([txt(r.cashier_label), r.orders || 0, r.tickets || 0, n(r.revenue), n(r.commission)]); });
    rows.push([]);
    rows.push([VQ.t('TICKET TYPE'), VQ.t('Category'), VQ.t('Issuer'), VQ.t('Tickets'), VQ.t('Revenue'), VQ.t('Commission')]);
    (d.by_ticket_type || []).forEach(function (r) {
      rows.push([txt(r.name), CAT[r.service_category] || txt(r.service_category), issuerName(r.issuing_company === 'secondary' ? 'secondary' : 'primary'), r.tickets || 0, n(r.revenue), n(r.commission)]);
    });
    rows.push([]);
    rows.push([VQ.t('PHYSICAL TICKETS ISSUED'), VQ.t('Category'), VQ.t('Quantity')]);
    (d.by_component_type || []).forEach(function (r) { rows.push([txt(r.name), CAT[r.service_category] || txt(r.service_category), r.tickets || 0]); });
    var cell = function (v) { return '"' + String(v == null ? '' : v).replace(/"/g, '""') + '"'; };
    var blob = new Blob(['﻿' + rows.map(function (r) { return r.map(cell).join(';'); }).join('\r\n')], { type: 'text/csv;charset=utf-8' });
    var a = document.createElement('a'), href = URL.createObjectURL(blob);
    a.href = href;
    a.download = 'report-' + (d.from || from) + '_' + (d.to || to) + '.csv';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    setTimeout(function () { URL.revokeObjectURL(href); }, 4000);
    O.flash(VQ.t('The report was downloaded.'));
  }

  /* =================== scans =================== */
  function scanWindow() {
    var mid = new Date(Date.now() + scanOffset * 86400000);
    return { from: F.ymd(new Date(mid.getTime() - 15 * 86400000)), to: F.ymd(new Date(mid.getTime() + 14 * 86400000)) };
  }
  function loadScans() {
    if (!eventId) return;
    var my = ++scanSeq, w = scanWindow();
    put('vr-scan-range', scanOffset === 0
      ? VQ.t('{from} to {to}, today in the middle', { from: day(w.from, { day: 'numeric', month: 'short' }), to: day(w.to) })
      : VQ.t('{from} to {to}', { from: day(w.from, { day: 'numeric', month: 'short' }), to: day(w.to) }));
    O.api('/organizer/events/' + eventId + '/leisure/scans?from=' + w.from + '&to=' + w.to).then(function (r) {
      if (my !== scanSeq) return;
      var d = (r && r.data) || {}, tt = d.totals || {};
      scanRows = (Array.isArray(d.rows) ? d.rows : []).filter(function (x) { return x && x.date; });
      put('vr-scan-totals', VQ.t('{valid} valid tickets of {expected} expected · {staff} staff · {refused} refused', { valid: F.num(F.toNum(tt.valid)), expected: F.num(F.toNum(tt.expected)), staff: F.num(F.toNum(tt.staff)), refused: F.num(F.toNum(tt.invalid)) }));
      drawScans();
    }, function (err) {
      if (my !== scanSeq) return;
      if (err && err.status === 401) return;
      scanRows = [];
      $('vr-scan-chart').textContent = '';
      put('vr-scan-totals', VQ.t('We could not load the scans.'));
    });
  }
  function drawScans() {
    var box = $('vr-scan-chart');
    box.textContent = '';
    var rows = scanRows;
    if (!rows.length) return;
    var W = Math.max(320, box.clientWidth || 640), H = 240, padL = 34, padR = 8, padT = 10, padB = 24;
    var iw = W - padL - padR, ih = H - padT - padB, n = rows.length, step = iw / n;
    var max = rows.reduce(function (m, r) {
      return Math.max(m, F.toNum(r.expected), F.toNum(r.valid) + F.toNum(r.staff) + F.toNum(r.invalid));
    }, 1);
    var node = svg('svg', { viewBox: '0 0 ' + W + ' ' + H, role: 'img', 'aria-label': VQ.t('Scans per day: expected, valid tickets, staff and refused') });
    [0, 0.5, 1].forEach(function (t) {
      var y = padT + ih * t;
      node.appendChild(svg('line', { class: 've-grid-line', x1: padL, x2: W - padR, y1: y.toFixed(1), y2: y.toFixed(1) }));
      var label = svg('text', { class: 've-axis', x: 2, y: (y + 3).toFixed(1) });
      label.textContent = F.num(Math.round(max * (1 - t)));
      node.appendChild(label);
    });
    var today = F.ymd(new Date());
    rows.forEach(function (r, i) {
      var x0 = padL + i * step, bw = Math.max(1.5, step * 0.34), base = padT + ih;
      var h = function (v) { return F.toNum(v) / max * ih; };
      var he = h(r.expected);
      if (he > 0) node.appendChild(svg('rect', { class: 've-sb is-exp', x: (x0 + step * 0.12).toFixed(1), y: (base - he).toFixed(1), width: bw.toFixed(1), height: he.toFixed(1), rx: 1.5 }));
      var y = base;
      [['valid', 'is-valid'], ['staff', 'is-staff'], ['invalid', 'is-bad']].forEach(function (part) {
        var hp = h(r[part[0]]);
        if (hp <= 0) return;
        y -= hp;
        node.appendChild(svg('rect', { class: 've-sb ' + part[1], x: (x0 + step * 0.12 + bw + 1).toFixed(1), y: y.toFixed(1), width: bw.toFixed(1), height: hp.toFixed(1) }));
      });
      if (r.date === today) node.appendChild(svg('line', { class: 've-today', x1: (x0 + step / 2).toFixed(1), x2: (x0 + step / 2).toFixed(1), y1: padT, y2: base }));
      if (i % 5 === 0 || i === n - 1) {
        var t = svg('text', { class: 've-axis', x: (x0 + step / 2).toFixed(1), y: H - 6, 'text-anchor': 'middle' });
        t.textContent = day(r.date, { day: 'numeric', month: 'short' });
        node.appendChild(t);
      }
      var total = F.toNum(r.valid) + F.toNum(r.staff) + F.toNum(r.invalid);
      var hit = svg('rect', { class: 've-hit', x: x0.toFixed(1), y: padT, width: step.toFixed(1), height: ih, tabindex: 0, role: 'button', 'data-date': r.date,
        'aria-label': VQ.t('{date}: {valid} valid of {expected} expected, {staff} staff, {refused} refused', { date: day(r.date), valid: F.num(F.toNum(r.valid)), expected: F.num(F.toNum(r.expected)), staff: F.num(F.toNum(r.staff)), refused: F.num(F.toNum(r.invalid)) }) });
      var title = svg('title');
      title.textContent = day(r.date) + ' · ' + VQ.n(total, 'scan', 'scans');
      hit.appendChild(title);
      hit.addEventListener('click', function () { openDay(r.date); });
      hit.addEventListener('keydown', function (ev) { if (ev.key === 'Enter' || ev.key === ' ') { ev.preventDefault(); openDay(r.date); } });
      node.appendChild(hit);
    });
    box.appendChild(node);
  }
  function openDay(date) {
    lastFocus = document.activeElement;
    put('vr-modal-h', VQ.t('Scans on {date}', { date: day(date, { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' }) }));
    put('vr-modal-totals', VQ.t('Loading…'));
    state('vr-modal-rows', 6, VQ.t('Loading…'));
    show('vr-modal', true);
    $('vr-modal-close').focus();
    O.api('/organizer/events/' + eventId + '/leisure/scans-detail?date=' + encodeURIComponent(date)).then(function (r) {
      var d = (r && r.data) || {}, items = Array.isArray(d.items) ? d.items : [], tt = d.totals || {};
      put('vr-modal-totals', VQ.t('{valid} valid · {staff} staff · {refused} refused · {total} in total', { valid: F.num(F.toNum(tt.valid)), staff: F.num(F.toNum(tt.staff)), refused: F.num(F.toNum(tt.invalid)), total: VQ.n(items.length, 'scan', 'scans') }));
      var body = $('vr-modal-rows');
      body.textContent = '';
      if (!items.length) { state('vr-modal-rows', 6, VQ.t('No scans on this day.')); return; }
      items.forEach(function (it) {
        var tag = it.type === 'ticket_valid' ? [VQ.t('Valid'), 'is-ok'] : it.type === 'staff' ? [VQ.t('Staff'), 'is-info']
          : [it.status === 'duplicate' ? VQ.t('Already used') : VQ.t('Refused'), 'is-bad'];
        body.appendChild(el('tr', null, [
          el('td', { class: 've-mono', text: txt(it.time) || '—' }),
          el('td', { text: nameOf(it.name) }),
          el('td', null, el('span', { class: 'org-tag ' + tag[1], text: tag[0] })),
          el('td', { class: 've-mono', text: txt(it.code) || '—' }),
          el('td', { text: txt(it.email) || '—' }),
          el('td', { class: 've-sub', text: txt(it.ticket_type) || '—' }),
        ]));
      });
    }, function (err) {
      if (err && err.status === 401) return;
      put('vr-modal-totals', '');
      state('vr-modal-rows', 6, VQ.t('We could not load the day\'s scans.'));
    });
  }
  function closeDay() {
    show('vr-modal', false);
    if (lastFocus && lastFocus.focus) lastFocus.focus();
  }
  var redraw = null;
  window.addEventListener('resize', function () {
    clearTimeout(redraw);
    redraw = setTimeout(function () { if (scanRows.length) drawScans(); }, 200);
  });

  /* =================== wiring =================== */
  qsa('.ve-range').forEach(function (b) { b.addEventListener('click', function () { setRange(b.getAttribute('data-range')); }); });
  $('vr-apply').addEventListener('click', function () {
    var f = $('vr-from').value, t = $('vr-to').value;
    if (!f || !t) { O.flash(VQ.t('Choose both dates.'), true); return; }
    if (f > t) { O.flash(VQ.t('The start date is after the end date.'), true); return; }
    from = f;
    to = t;
    load();
  });
  $('vr-csv').addEventListener('click', exportCsv);
  $('vr-scan-prev').addEventListener('click', function () { scanOffset -= 30; loadScans(); });
  $('vr-scan-next').addEventListener('click', function () { scanOffset += 30; loadScans(); });
  $('vr-scan-today').addEventListener('click', function () { scanOffset = 0; loadScans(); });
  $('vr-modal-close').addEventListener('click', closeDay);
  $('vr-modal').addEventListener('click', function (ev) { if (ev.target === $('vr-modal')) closeDay(); });
  document.addEventListener('keydown', function (ev) { if (ev.key === 'Escape' && !$('vr-modal').hidden) closeDay(); });
  $('ve-retry').addEventListener('click', function () { show('ve-failed', false); start(); });
  $('ve-event').addEventListener('change', function () {
    eventId = F.toNum(this.value);
    load();
    loadScans();
  });

  function loadEvents() {
    return O.api('/organizer/events?per_page=50&page=1').then(function (r) {
      var list = Array.isArray(r && r.data) ? r.data : (r && r.data && r.data.items) || [];
      var events = list.filter(function (e) { return e && e.id != null; });
      var venues = events.filter(function (e) { return (e.display_template || '') === 'leisure_venue'; });
      if (!venues.length && events.length) return probe(events.slice(0, 8));
      return venues;
    });
  }
  function probe(list) {
    var found = [], chain = Promise.resolve();
    list.forEach(function (e) {
      chain = chain.then(function () {
        return O.api('/organizer/events/' + e.id + '/leisure/config', { quiet: true }).then(function () { found.push(e); }, function () {});
      });
    });
    return chain.then(function () { return found; });
  }
  function start() {
    show('ve-main', false);
    show('ve-none', false);
    loadEvents().then(function (venues) {
      if (!venues.length) { show('ve-none', true); return; }
      var sel = $('ve-event');
      sel.textContent = '';
      venues.forEach(function (e) { sel.appendChild(new Option(txt(e.title || e.name) || VQ.t('Venue #{id}', { id: e.id }), String(e.id))); });
      sel.disabled = venues.length < 2;
      eventId = F.toNum(venues[0].id);
      show('ve-main', true);
      setRange('30');
      loadScans();
    }, function (err) {
      if (err && err.status === 401) return;
      show('ve-failed', true);
    });
  }
  O.ready.then(function (ok) { if (ok) start(); });
})();
