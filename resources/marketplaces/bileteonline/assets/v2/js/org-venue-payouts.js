/* bilete.online v2: the venue's payouts (/organizator/locatie/deconturi), ported from Ambilet.
   Three reads: the running totals, the settlement of a half-month and the payouts. The settlement says who pays whom:
   bilete.online owes the venue the online net, the venue owes the commission on what it sold at the counter. The
   payouts are grouped by period; a period opens into its payouts and invoices, a payout into its tickets, an invoice
   into its lines. PDFs link out only over https. Runs inside the organizer shell (window.BO_ORG); text from the API is
   always written as text. */
(function () {
  'use strict';
  var O = window.BO_ORG, root = document.getElementById('ve');
  if (!O || !root) return;
  var F = O.fmt, el = O.el;
  var $ = function (id) { return document.getElementById(id); };
  var STATUS = { pending: ['În așteptare', 'is-wait'], approved: ['Aprobat', 'is-info'], processing: ['În plată', 'is-info'], completed: ['Plătit', 'is-ok'], rejected: ['Respins', 'is-bad'], cancelled: ['Anulat', 'is-muted'] };
  var INV = { paid: ['Achitată', 'is-ok'], outstanding: ['Neachitată', 'is-wait'], pending: ['În așteptare', 'is-wait'], cancelled: ['Anulată', 'is-muted'], refunded: ['Rambursată', 'is-bad'] };
  var STAGE = { fiscala: 'Fiscală', proforma: 'Proformă' };
  var MONTHS = ['ian.', 'feb.', 'mar.', 'apr.', 'mai', 'iun.', 'iul.', 'aug.', 'sep.', 'oct.', 'nov.', 'dec.'];
  var eventId = null, currency = 'RON', payouts = [], openKeys = {}, setSeq = 0;

  function txt(v) { return F.flat(v).trim(); }
  function show(id, on) { var e = $(id); if (e) e.hidden = !on; }
  function put(id, value) { var e = $(id); if (e) e.textContent = value; }
  function amount(v, cur) {
    var c = String(cur || currency || '').toUpperCase(), lei = F.money(F.toNum(v));
    return !c || c === 'RON' || c === 'LEI' ? lei : lei.replace(/ lei$/, '') + ' ' + c;
  }
  function day(v, opts) { var d = F.dateOf(v); return d ? F.date(d, opts || { day: 'numeric', month: 'short', year: 'numeric' }) : '—'; }
  function pad(n) { return String(n).padStart(2, '0'); }
  function localYmd(d) { return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()); }
  /** A link that leaves the site: only https, never javascript: or data:. */
  function outHref(u) {
    if (typeof u !== 'string' || !u.trim()) return null;
    try { var x = new URL(u.trim()); return x.protocol === 'https:' ? x.href : null; } catch (e) { return null; }
  }
  function tag(map, key) { var s = map[key] || [txt(key) || '—', 'is-muted']; return el('span', { class: 'org-tag ' + s[1], text: s[0] }); }
  function pdfLink(u, label) {
    var href = outHref(u);
    if (!href) return null;
    var a = el('a', { class: 've-pdf', href: href, target: '_blank', rel: 'noopener noreferrer' });
    a.appendChild(O.icon('file-text'));
    a.appendChild(document.createTextNode(label || 'PDF'));
    a.addEventListener('click', function (ev) { ev.stopPropagation(); });
    return a;
  }
  function toggle(btn, body, key) {
    var open = btn.getAttribute('aria-expanded') !== 'true';
    btn.setAttribute('aria-expanded', String(open));
    body.hidden = !open;
    if (open) openKeys[key] = true; else delete openKeys[key];
  }
  function fold(key, headKids, bodyKids, cls) {
    var wrap = el('div', { class: 've-fold ' + (cls || '') });
    var body = el('div', { class: 've-fold-body', hidden: !openKeys[key] }, bodyKids);
    var btn = el('button', { class: 've-fold-head', type: 'button', 'aria-expanded': String(!!openKeys[key]) }, headKids);
    btn.insertBefore(O.icon('caret-down'), btn.firstChild);
    btn.addEventListener('click', function () { toggle(btn, body, key); });
    wrap.appendChild(btn);
    wrap.appendChild(body);
    return wrap;
  }

  /* =================== running totals =================== */
  function loadCumulative() {
    return O.api('/organizer/events/' + eventId + '/leisure/settlement/cumulative', { quiet: true }).then(function (r) {
      var d = (r && r.data) || {}, on = d.online || {}, pos = d.pos || {};
      currency = txt(d.currency) || currency;
      put('vd-days', F.num(F.toNum(d.days_count)));
      put('vd-since', d.from ? 'de la ' + day(d.from) : '—');
      put('vd-online', amount(on.gross));
      put('vd-pos', amount(pos.gross));
      put('vd-online-comm', amount(on.commission));
      put('vd-pos-comm', amount(pos.commission));
    }, function (err) {
      if (err && err.status === 401) return;
      ['vd-days', 'vd-since', 'vd-online', 'vd-pos', 'vd-online-comm', 'vd-pos-comm'].forEach(function (id) { put(id, '—'); });
    });
  }

  /* =================== settlement =================== */
  function halfMonths() {
    var today = new Date(), out = [], y = today.getFullYear(), m = today.getMonth(), half = today.getDate() <= 15 ? 1 : 2;
    for (var i = 0; i < 24; i++) {
      var f = new Date(y, m, half === 1 ? 1 : 16), t = half === 1 ? new Date(y, m, 15) : new Date(y, m + 1, 0);
      out.push({ from: localYmd(f), to: localYmd(t), label: f.getDate() + '–' + t.getDate() + ' ' + MONTHS[m] + ' ' + y + (i === 0 ? ' (în curs)' : '') });
      if (half === 2) { half = 1; } else { half = 2; m--; if (m < 0) { m = 11; y--; } }
    }
    return out;
  }
  function fillPeriods() {
    var sel = $('vd-period');
    sel.textContent = '';
    halfMonths().forEach(function (p) {
      var o = new Option(p.label, p.from + '|' + p.to);
      sel.appendChild(o);
    });
  }
  function loadSettlement() {
    var v = $('vd-period').value.split('|'), my = ++setSeq, box = $('vd-settle');
    if (v.length !== 2) return;
    box.classList.add('is-loading');
    O.api('/organizer/events/' + eventId + '/leisure/settlement?from=' + v[0] + '&to=' + v[1]).then(function (r) {
      if (my !== setSeq) return;
      drawSettlement((r && r.data) || {});
    }, function (err) {
      if (my !== setSeq) return;
      if (err && err.status === 401) return;
      box.textContent = '';
      box.appendChild(el('p', { class: 've-state', text: 'Nu am putut calcula compensarea pentru această perioadă.' }));
    }).then(function () { if (my === setSeq) box.classList.remove('is-loading'); });
  }
  function line(label, value, cls) {
    return el('div', { class: 've-line' + (cls ? ' ' + cls : '') }, [el('dt', { text: label }), el('dd', { text: value })]);
  }
  function drawSettlement(d) {
    var on = d.online || {}, pos = d.pos || {}, bal = d.balance || {}, cur = txt(d.currency) || currency;
    var box = $('vd-settle');
    box.textContent = '';
    var grid = el('div', { class: 've-settle' });
    grid.appendChild(el('article', { class: 've-sbox' }, [
      el('p', { class: 've-sbox-k' }, [O.icon('globe-simple'), document.createTextNode('Online · încasat de bilete.online')]),
      el('dl', { class: 've-lines' }, [
        line('Vânzări', amount(on.gross, cur)),
        line('Comision bilete.online', amount(on.commission, cur)),
        line('Net datorat locației', amount(on.net, cur), 'is-total'),
      ]),
    ]));
    grid.appendChild(el('article', { class: 've-sbox' }, [
      el('p', { class: 've-sbox-k' }, [O.icon('coins'), document.createTextNode('La casă · încasat de locație')]),
      el('dl', { class: 've-lines' }, [
        line('Vânzări', amount(pos.gross, cur)),
        line('Numerar', amount(pos.cash, cur)),
        line('Card', amount(pos.card, cur)),
        line('Comision datorat de locație', amount(pos.commission, cur), 'is-total'),
      ]),
    ]));
    var dir = bal.direction, who = dir === 'ambilet_to_venue' ? ['bilete.online plătește locației', 'is-in']
      : dir === 'venue_to_ambilet' ? ['Locația plătește bilete.online', 'is-out'] : ['Nimic de plătit', 'is-even'];
    grid.appendChild(el('article', { class: 've-sbox ve-balance ' + who[1] }, [
      el('p', { class: 've-sbox-k', text: 'Soldul perioadei, prin compensare' }),
      el('p', { class: 've-balance-who', text: who[0] }),
      el('p', { class: 've-balance-sum', text: amount(dir === 'settled' || !dir ? 0 : bal.amount, cur) }),
    ]));
    box.appendChild(grid);
    box.appendChild(el('p', { class: 've-note-line', text: 'Perioada ' + day((d.period || {}).from) + ' – ' + day((d.period || {}).to) + ': '
      + amount(bal.ambilet_owes_venue, cur) + ' datorat locației − ' + amount(bal.venue_owes_ambilet, cur) + ' datorat de locație = ' + amount(bal.net, cur) + '.' }));
    var by = d.by_issuer || {}, cards = [];
    [['primary', 'Societatea principală'], ['secondary', 'Societatea secundară']].forEach(function (pair) {
      var s = by[pair[0]];
      if (!s || !txt(s.name)) return;
      if (!(F.toNum(s.online_gross) || F.toNum(s.pos_gross) || F.toNum(s.online_commission) || F.toNum(s.pos_commission))) return;
      var comp = F.toNum(s.compensation);
      cards.push(el('article', { class: 've-issuer' + (pair[0] === 'secondary' ? ' is-second' : '') }, [
        el('p', { class: 've-issuer-k' }, [O.icon('buildings'), document.createTextNode(pair[1])]),
        el('h3', { class: 've-issuer-n', text: txt(s.name) }),
        el('dl', { class: 've-lines' }, [
          line('Vânzări online', amount(s.online_gross, cur)),
          line('Comision online', amount(s.online_commission, cur)),
          line('Vânzări la casă', amount(s.pos_gross, cur)),
          line('Comision la casă', amount(s.pos_commission, cur)),
          line('Compensare', amount(comp, cur), 'is-total ' + (comp > 0 ? 'is-in' : comp < 0 ? 'is-out' : '')),
        ]),
        el('p', { class: 've-sub', text: 'Compensare = vânzări online − toate comisioanele.' }),
      ]));
    });
    if (cards.length) {
      box.appendChild(el('h3', { class: 've-subh', text: 'Pe societăți' }));
      box.appendChild(el('div', { class: 've-issuers' + (cards.length > 1 ? ' is-two' : '') }, cards));
    }
  }

  /* =================== payouts =================== */
  function loadPayouts() {
    var list = $('vd-list');
    return O.api('/organizer/events/' + eventId + '/leisure/payouts').then(function (r) {
      var d = (r && r.data) || {};
      currency = txt(d.currency) || currency;
      payouts = Array.isArray(d.payouts) ? d.payouts : [];
      drawPayouts();
    }, function (err) {
      if (err && err.status === 401) return;
      list.textContent = '';
      list.appendChild(el('p', { class: 've-state', text: 'Nu am putut încărca deconturile.' }));
    });
  }
  function periodLabel(g) {
    if (!g.start && !g.end) return 'Fără perioadă';
    return day(g.start, { day: 'numeric', month: 'short' }) + ' – ' + day(g.end);
  }
  function drawPayouts() {
    var list = $('vd-list');
    list.textContent = '';
    if (!payouts.length) {
      list.appendChild(el('p', { class: 've-state', text: 'Nu există încă deconturi emise pentru această locație.' }));
      return;
    }
    var groups = {}, order = [];
    payouts.forEach(function (p) {
      var key = (p.period_start || '') + '_' + (p.period_end || '');
      if (!groups[key]) { groups[key] = { key: key, start: p.period_start, end: p.period_end, items: [] }; order.push(key); }
      groups[key].items.push(p);
    });
    order.map(function (k) { return groups[k]; }).sort(function (a, b) { return String(b.start || '').localeCompare(String(a.start || '')); }).forEach(function (g) {
      var gross = 0, comm = 0, net = 0, inv = 0;
      g.items.forEach(function (p) { gross += F.toNum(p.gross_amount); comm += F.toNum(p.commission_amount); net += F.toNum(p.amount); inv += (p.invoices || []).length; });
      var head = [
        el('span', { class: 've-fold-title' }, [el('b', { text: periodLabel(g) }), el('small', { class: 've-sub', text: F.count(g.items.length, 'decont', 'deconturi') + ' · ' + F.count(inv, 'factură', 'facturi') })]),
        el('span', { class: 've-fold-nums' }, [
          el('span', null, [el('small', { text: 'Vânzări' }), el('b', { text: amount(gross) })]),
          el('span', null, [el('small', { text: 'Comision' }), el('b', { text: amount(comm) })]),
          el('span', { class: 'is-net' }, [el('small', { text: 'De plată' }), el('b', { text: amount(net) })]),
        ]),
      ];
      var body = [];
      g.items.forEach(function (p) {
        body.push(payoutFold(p));
        (p.invoices || []).forEach(function (i) { body.push(invoiceFold(i, p)); });
      });
      list.appendChild(fold('g:' + g.key, head, body, 've-fold-period'));
    });
  }
  function payoutFold(p) {
    var head = [
      el('span', { class: 've-fold-title' }, [
        el('span', { class: 've-kind', text: 'Decont' }),
        el('b', { class: 've-mono', text: txt(p.decont_series) || txt(p.reference) || ('#' + F.toNum(p.id)) }),
        el('small', { class: 've-sub', text: [txt(p.reference), txt(p.society_name) !== '-' ? txt(p.society_name) : ''].filter(Boolean).join(' · ') }),
      ]),
      el('span', { class: 've-fold-nums' }, [
        el('span', null, [el('small', { text: 'Vânzări' }), el('b', { text: amount(p.gross_amount, p.currency) })]),
        el('span', null, [el('small', { text: 'Comision' }), el('b', { text: amount(p.commission_amount, p.currency) })]),
        el('span', { class: 'is-net' }, [el('small', { text: 'De plată' }), el('b', { text: amount(p.amount, p.currency) })]),
      ]),
      tag(STATUS, p.status),
    ];
    var pdf = pdfLink(p.pdf_url, 'PDF decont');
    if (pdf) head.push(pdf);
    var rows = Array.isArray(p.breakdown) ? p.breakdown : [];
    var body = [];
    var extras = [['Reduceri', p.discount_amount], ['Restituiri', p.refund_amount], ['Taxe', p.fees_amount]].filter(function (x) { return F.toNum(x[1]) > 0; });
    if (extras.length || p.completed_at) {
      body.push(el('p', { class: 've-note-line', text: extras.map(function (x) { return x[0] + ': ' + amount(x[1], p.currency); }).concat(p.completed_at ? ['plătit pe ' + day(p.completed_at)] : []).join(' · ') }));
    }
    if (!rows.length) body.push(el('p', { class: 've-state', text: 'Decontul nu are detaliu pe bilete.' }));
    else {
      var tb = el('tbody');
      rows.forEach(function (b) {
        tb.appendChild(el('tr', null, [
          el('td', { text: txt(b.name) || '—' }),
          el('td', { class: 've-r', text: F.num(F.toNum(b.qty)) }),
          el('td', { class: 've-r', text: amount(b.unit_price, p.currency) }),
          el('td', { class: 've-r', text: amount(b.gross, p.currency) }),
          el('td', { class: 've-r', text: amount(b.commission, p.currency) }),
          el('td', { class: 've-r', text: amount(b.discount, p.currency) }),
          el('td', { class: 've-r' }, el('b', { text: amount(b.net, p.currency) })),
        ]));
      });
      body.push(el('div', { class: 've-table-wrap' }, el('table', { class: 've-table ve-breakdown-table' }, [
        el('thead', null, el('tr', null, ['Tip bilet', 'Bucăți', 'Preț', 'Brut', 'Comision', 'Reducere', 'Net'].map(function (h, i) { return el('th', { scope: 'col', class: i ? 've-r' : '', text: h }); }))),
        tb,
      ])));
    }
    return fold('p:' + F.toNum(p.id), head, body, 've-fold-payout');
  }
  function invoiceFold(i, p) {
    var head = [
      el('span', { class: 've-fold-title' }, [
        el('span', { class: 've-kind is-inv', text: 'Factură' }),
        el('b', { class: 've-mono', text: txt(i.accounting_number) || txt(i.number) || ('#' + F.toNum(i.id)) }),
        el('small', { class: 've-sub', text: [txt(i.accounting_number) ? txt(i.number) : '', i.issue_date ? day(i.issue_date) : '', STAGE[i.accounting_stage] || '', txt(i.type_label)].filter(Boolean).join(' · ') }),
      ]),
      el('span', { class: 've-fold-nums' }, [
        el('span', null, [el('small', { text: 'Fără TVA' }), el('b', { text: amount(i.subtotal, i.currency) })]),
        el('span', null, [el('small', { text: 'TVA' }), el('b', { text: amount(i.vat_amount, i.currency) })]),
        el('span', { class: 'is-net' }, [el('small', { text: 'Total' }), el('b', { text: amount(i.amount, i.currency) })]),
      ]),
      tag(INV, i.status),
    ];
    var pdf = pdfLink(i.accounting_pdf_url, 'PDF factură');
    if (pdf) head.push(pdf);
    var items = Array.isArray(i.items) ? i.items : [], body = [];
    if (!items.length) body.push(el('p', { class: 've-state', text: 'Factura nu are articole înregistrate.' }));
    else {
      var tb = el('tbody');
      items.forEach(function (it) {
        var name = el('td', null, el('b', { text: txt(it.name) || '—' }));
        if (txt(it.description) && txt(it.description) !== txt(it.name)) name.appendChild(el('small', { class: 've-sub', text: txt(it.description) }));
        tb.appendChild(el('tr', null, [
          name,
          el('td', { class: 've-r', text: F.num(F.toNum(it.quantity)) }),
          el('td', { class: 've-r', text: amount(it.unit_price, i.currency) }),
          el('td', { class: 've-r' }, el('b', { text: amount(it.amount, i.currency) })),
        ]));
      });
      body.push(el('div', { class: 've-table-wrap' }, el('table', { class: 've-table ve-items-table' }, [
        el('thead', null, el('tr', null, ['Articol', 'Cantitate', 'Preț', 'Total'].map(function (h, k) { return el('th', { scope: 'col', class: k ? 've-r' : '', text: h }); }))),
        tb,
      ])));
    }
    void p;
    return fold('i:' + F.toNum(i.id), head, body, 've-fold-invoice');
  }

  /* =================== wiring =================== */
  function refresh() {
    if (!eventId) return;
    var btn = $('vd-refresh');
    btn.disabled = true;
    Promise.all([loadCumulative(), loadPayouts()]).then(function () { btn.disabled = false; });
    loadSettlement();
  }
  $('vd-refresh').addEventListener('click', refresh);
  $('vd-period').addEventListener('change', loadSettlement);
  $('ve-retry').addEventListener('click', function () { show('ve-failed', false); start(); });
  $('ve-event').addEventListener('change', function () {
    eventId = F.toNum(this.value);
    openKeys = {};
    refresh();
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
      venues.forEach(function (e) { sel.appendChild(new Option(txt(e.title || e.name) || 'Locație #' + e.id, String(e.id))); });
      sel.disabled = venues.length < 2;
      eventId = F.toNum(venues[0].id);
      show('ve-main', true);
      fillPeriods();
      refresh();
    }, function (err) {
      if (err && err.status === 401) return;
      show('ve-failed', true);
    });
  }
  O.ready.then(function (ok) { if (ok) start(); });
})();
