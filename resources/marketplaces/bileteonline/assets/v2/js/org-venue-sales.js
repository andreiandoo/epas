/* bilete.online v2: the venue's sales (/organizator/locatie/vanzari), ported from Ambilet.
   A period asks core for two things at once: the timeline (the sales over time, how they were paid, each ticket type
   and category) and the summary (gross, commission and net by channel, orders, tickets, the cash sessions). The
   company invoices load when their panel is opened, over the same period. The chart is drawn here, as SVG. Runs inside
   the organizer shell (window.BO_ORG); text from the API is always written as text. */
(function () {
  'use strict';
  var O = window.BO_ORG, root = document.getElementById('ve');
  if (!O || !root) return;
  var F = O.fmt, el = O.el, SVGNS = 'http://www.w3.org/2000/svg';
  var $ = function (id) { return document.getElementById(id); };
  var qsa = function (sel) { return Array.prototype.slice.call(root.querySelectorAll(sel)); };
  var CAT = { access: 'Acces', parking: 'Parcare', rental: 'Închirieri', activity: 'Activități', extra: 'Extra', package: 'Pachete' };
  var PAY = { cash: 'Numerar', card: 'Card', invoice: 'Link pe email', online: 'Online' };
  var MONTHS = ['ian.', 'feb.', 'mar.', 'apr.', 'mai', 'iun.', 'iul.', 'aug.', 'sep.', 'oct.', 'nov.', 'dec.'];
  var INV_PER = 20;
  var eventId = null, from = null, to = null, groupBy = 'day', seq = 0, currency = 'RON', chartRows = [];
  var invPage = 1, invLast = 1, invLoaded = false, invSeq = 0, invTimer = null;

  function txt(v) { return F.flat(v).trim(); }
  function show(id, on) { var e = $(id); if (e) e.hidden = !on; }
  function amount(v) {
    var c = String(currency || '').toUpperCase(), lei = F.money(F.toNum(v));
    return !c || c === 'RON' || c === 'LEI' ? lei : lei.replace(/ lei$/, '') + ' ' + c;
  }
  function day(v) { var d = F.dateOf(v); return d ? F.date(d, { day: 'numeric', month: 'short', year: 'numeric' }) : '—'; }
  function stamp(v) { var d = F.dateOf(v); return d ? F.date(d, { day: '2-digit', month: '2-digit', year: '2-digit', hour: '2-digit', minute: '2-digit' }) : '—'; }
  function share(part, total) { return total > 0 ? F.pct(part / total * 100, 0) : '—'; }
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
  /** A bucket's label: a day ("12 sep."), an ISO week ("2026-37" → "săpt. 37") or a month ("2026-09" → "sep. 2026"). */
  function bucketLabel(key, long) {
    var k = txt(key), m;
    if ((m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(k))) {
      var d = F.dateOf(k);
      return d ? F.date(d, long ? { weekday: 'short', day: 'numeric', month: 'short' } : { day: 'numeric', month: 'short' }) : k;
    }
    if ((m = /^(\d{4})-(\d{2})$/.exec(k)) && groupBy === 'month') return MONTHS[parseInt(m[2], 10) - 1] + ' ' + m[1];
    if ((m = /^(\d{4})-(\d{1,2})$/.exec(k))) return 'săpt. ' + parseInt(m[2], 10) + (long ? ' · ' + m[1] : '');
    return k;
  }

  /* =================== the period =================== */
  function setRange(value) {
    qsa('.ve-range').forEach(function (b) { b.setAttribute('aria-pressed', String(b.getAttribute('data-range') === value)); });
    show('vs-custom', value === 'custom');
    if (value === 'custom') {
      if (from) $('vs-from').value = from;
      if (to) $('vs-to').value = to;
      return;
    }
    from = F.ymd(new Date(Date.now() - parseInt(value, 10) * 86400000));
    to = F.ymd(new Date());
    load();
  }
  function load() {
    if (!eventId || !from || !to) return;
    var my = ++seq, q = 'from=' + from + '&to=' + to, base = '/organizer/events/' + eventId + '/leisure/';
    $('ve-main').classList.add('is-loading');
    $('vs-period').textContent = 'Plătite între ' + day(from) + ' și ' + day(to);
    var tl = O.api(base + 'sales-timeline?' + q + '&group_by=' + groupBy).then(function (r) { return (r && r.data) || {}; }, function (e) { return { __error: e }; });
    var sm = O.api(base + 'sales/summary?' + q).then(function (r) { return (r && r.data) || {}; }, function (e) { return { __error: e }; });
    Promise.all([tl, sm]).then(function (res) {
      if (my !== seq) return;
      $('ve-main').classList.remove('is-loading');
      var t = res[0], s = res[1];
      if ((t.__error && t.__error.status === 401) || (s.__error && s.__error.status === 401)) return;
      if (!s.__error) drawSummary(s); else blankSummary();
      if (!t.__error) drawTimeline(t); else blankTimeline();
      if (t.__error || s.__error) O.flash('O parte din cifre nu s-a putut încărca. Reîncearcă.', true);
      if (invLoaded || invOpen()) { invLoaded = false; if (invOpen()) loadInvoices(1); }
    });
  }

  /* =================== the summary =================== */
  function put(id, value) { var e = $(id); if (e) e.textContent = value; }
  function drawSummary(d) {
    var t = d.totals || {};
    currency = txt(d.currency) || 'RON';
    put('vs-rev', amount(t.revenue_total));
    put('vs-rev-online', amount(t.revenue_online));
    put('vs-rev-pos', amount(t.revenue_pos));
    put('vs-comm', amount(t.commission_total));
    put('vs-comm-online', amount(t.commission_online));
    put('vs-comm-pos', amount(t.commission_pos));
    put('vs-net', amount(t.net_total));
    put('vs-net-online', amount(t.net_online));
    put('vs-net-pos', amount(t.net_pos));
    put('vs-orders', F.num(F.toNum(t.orders)));
    put('vs-avg', amount(t.avg_order));
    put('vs-physical', F.num(F.toNum(t.tickets_physical)));
    put('vs-transactions', F.num(F.toNum(t.tickets_transactions)));
    var sessions = Array.isArray(d.sessions) ? d.sessions : [];
    put('vs-sessions-n', F.num(sessions.length));
    drawSessions(sessions);
  }
  function blankSummary() {
    ['vs-rev', 'vs-rev-online', 'vs-rev-pos', 'vs-comm', 'vs-comm-online', 'vs-comm-pos', 'vs-net', 'vs-net-online', 'vs-net-pos',
      'vs-orders', 'vs-avg', 'vs-physical', 'vs-transactions', 'vs-sessions-n'].forEach(function (id) { put(id, '—'); });
    state('vs-sessions', 8, 'Nu am putut încărca sesiunile de casă.');
  }
  function drawSessions(list) {
    var body = $('vs-sessions');
    body.textContent = '';
    if (!list.length) { state('vs-sessions', 8, 'Nicio sesiune de casă în perioada aleasă.'); return; }
    list.forEach(function (s) {
      body.appendChild(el('tr', null, [
        el('td', null, el('b', { text: txt(s.operator) || '—' })),
        el('td', { text: stamp(s.opened_at) }),
        el('td', null, s.is_open ? el('span', { class: 'org-tag is-ok', text: 'deschisă acum' }) : document.createTextNode(stamp(s.closed_at))),
        el('td', { class: 've-r', text: s.is_open ? '—' : amount(s.cash) }),
        el('td', { class: 've-r', text: s.is_open ? '—' : amount(s.card) }),
        el('td', { class: 've-r', text: s.is_open ? '—' : F.num(F.toNum(s.orders)) }),
        el('td', { class: 've-r', text: s.is_open ? '—' : F.num(F.toNum(s.tickets_sold)) }),
        el('td', { class: 've-r' }, s.is_open ? document.createTextNode('se închide la final de tură') : el('b', { text: amount(s.revenue) })),
      ]));
    });
  }

  /* =================== the timeline =================== */
  function drawTimeline(d) {
    // categories
    var cats = $('vs-cats'), entries = Object.keys(d.by_category || {}).map(function (k) { return [k, F.toNum(d.by_category[k])]; })
      .filter(function (e) { return e[1] > 0; }).sort(function (a, b) { return b[1] - a[1]; });
    var catTotal = entries.reduce(function (s, e) { return s + e[1]; }, 0);
    cats.textContent = '';
    if (!entries.length) cats.appendChild(el('li', null, el('span', { text: '—' })));
    entries.forEach(function (e) {
      cats.appendChild(el('li', null, [el('b', { text: CAT[e[0]] || e[0] }), el('span', { text: F.num(e[1]) + ' · ' + share(e[1], catTotal) })]));
    });
    // how it was paid
    var pay = { cash: 0, card: 0, online: 0 };
    (d.by_payment_method || []).forEach(function (r) { var k = txt(r.method).toLowerCase(); if (k in pay) pay[k] += F.toNum(r.revenue); });
    var payTotal = pay.cash + pay.card + pay.online;
    ['cash', 'card', 'online'].forEach(function (k) {
      put('vs-pay-' + k, amount(pay[k]));
      put('vs-pay-' + k + '-pct', share(pay[k], payTotal));
    });
    // ticket types
    var list = $('vs-types'), types = Array.isArray(d.by_ticket_type) ? d.by_ticket_type : [];
    var maxT = types.reduce(function (m, r) { return Math.max(m, F.toNum(r.tickets)); }, 0);
    list.textContent = '';
    if (!types.length) list.appendChild(el('li', { class: 've-state', text: 'Nicio vânzare în perioada aleasă.' }));
    types.forEach(function (r) {
      var bar = el('i', { 'data-cat': txt(r.service_category) || 'access' });
      bar.style.width = (maxT > 0 ? Math.max(2, F.toNum(r.tickets) / maxT * 100) : 0).toFixed(1) + '%';
      list.appendChild(el('li', null, [
        el('div', { class: 've-tb-top' }, [
          el('b', { text: txt(r.name) || 'Bilet', title: txt(r.name) }),
          el('span', { text: F.count(F.toNum(r.tickets), 'bilet', 'bilete') + ' · ' + amount(r.revenue) }),
        ]),
        el('small', { class: 've-sub', text: CAT[r.service_category] || txt(r.service_category) }),
        el('div', { class: 've-tb-bar' }, bar),
      ]));
    });
    chartRows = (Array.isArray(d.rows) ? d.rows : []).filter(function (x) { return x && x.date; });
    drawChart();
  }
  function blankTimeline() {
    $('vs-cats').textContent = '';
    $('vs-cats').appendChild(el('li', null, el('span', { text: '—' })));
    ['cash', 'card', 'online'].forEach(function (k) { put('vs-pay-' + k, '—'); put('vs-pay-' + k + '-pct', '—'); });
    $('vs-types').textContent = '';
    $('vs-types').appendChild(el('li', { class: 've-state', text: 'Nu am putut încărca tipurile de bilet.' }));
    chartRows = [];
    drawChart();
  }
  function drawChart() {
    var box = $('vs-chart');
    box.textContent = '';
    var rows = chartRows;
    var any = rows.some(function (x) { return F.toNum(x.revenue) > 0 || F.toNum(x.tickets) > 0; });
    show('vs-chart-empty', !any);
    if (!any) return;
    var W = Math.max(320, box.clientWidth || 640), H = 260, padL = 52, padR = 12, padT = 14, padB = 26;
    var iw = W - padL - padR, ih = H - padT - padB;
    var revs = rows.map(function (x) { return F.toNum(x.revenue); }), tks = rows.map(function (x) { return F.toNum(x.tickets); });
    var maxRev = Math.max.apply(null, revs.concat([1])), maxT = Math.max.apply(null, tks.concat([1]));
    var n = rows.length, step = iw / Math.max(1, n);
    var node = svg('svg', { viewBox: '0 0 ' + W + ' ' + H, role: 'img', 'aria-label': 'Încasări și bilete în perioada aleasă' });
    [0, 0.5, 1].forEach(function (t) {
      var y = padT + ih * t;
      node.appendChild(svg('line', { class: 've-grid-line', x1: padL, x2: W - padR, y1: y.toFixed(1), y2: y.toFixed(1) }));
      var label = svg('text', { class: 've-axis', x: 4, y: (y + 3).toFixed(1) });
      label.textContent = F.num(Math.round(maxRev * (1 - t)));
      node.appendChild(label);
    });
    var bars = rows.map(function (row, i) {
      var h = (tks[i] / maxT) * ih * 0.55, x = padL + i * step + step * 0.18, w = Math.max(2, step * 0.64);
      var b = svg('rect', { class: 've-bar', x: x.toFixed(1), y: (padT + ih - h).toFixed(1), width: w.toFixed(1), height: Math.max(0, h).toFixed(1), rx: 2 });
      node.appendChild(b);
      return b;
    });
    var pts = rows.map(function (row, i) { return [padL + i * step + step / 2, padT + ih - (revs[i] / maxRev) * ih]; });
    var line = pts.map(function (p, i) { return (i ? 'L' : 'M') + p[0].toFixed(1) + ' ' + p[1].toFixed(1); }).join('');
    if (pts.length > 1) {
      node.appendChild(svg('path', { class: 've-area', d: line + 'L' + pts[pts.length - 1][0].toFixed(1) + ' ' + (padT + ih) + 'L' + pts[0][0].toFixed(1) + ' ' + (padT + ih) + 'Z' }));
      node.appendChild(svg('path', { class: 've-line', d: line }));
    } else if (pts.length === 1) {
      node.appendChild(svg('circle', { class: 've-pt', cx: pts[0][0].toFixed(1), cy: pts[0][1].toFixed(1), r: 4 }));
    }
    var ticks = n <= 1 ? [0] : [0, Math.floor((n - 1) / 2), n - 1];
    ticks.filter(function (v, i, a) { return a.indexOf(v) === i; }).forEach(function (i) {
      var t = svg('text', { class: 've-axis', x: (padL + i * step + step / 2).toFixed(1), y: H - 6, 'text-anchor': i === 0 && n > 1 ? 'start' : (i === n - 1 && n > 1 ? 'end' : 'middle') });
      t.textContent = bucketLabel(rows[i].date, false);
      node.appendChild(t);
    });
    var tip = el('div', { class: 've-tip', hidden: true, role: 'status' });
    rows.forEach(function (row, i) {
      var hit = svg('rect', { class: 've-hit', x: (padL + i * step).toFixed(1), y: padT, width: step.toFixed(1), height: ih });
      var on = function () {
        bars.forEach(function (b, j) { b.classList.toggle('is-on', i === j); });
        tip.textContent = '';
        tip.appendChild(el('b', { text: bucketLabel(row.date, true) }));
        tip.appendChild(el('span', { text: amount(row.revenue) }));
        tip.appendChild(el('br'));
        tip.appendChild(el('span', { text: F.count(F.toNum(row.tickets), 'bilet', 'bilete') + ' · ' + F.count(F.toNum(row.orders), 'comandă', 'comenzi') }));
        tip.style.left = ((padL + i * step + step / 2) / W * 100).toFixed(2) + '%';
        tip.style.top = ((padT + ih - (revs[i] / maxRev) * ih) / H * 100).toFixed(2) + '%';
        tip.hidden = false;
      };
      hit.addEventListener('pointerenter', on);
      hit.addEventListener('click', on);
      node.appendChild(hit);
    });
    node.addEventListener('pointerleave', function () { tip.hidden = true; bars.forEach(function (b) { b.classList.remove('is-on'); }); });
    box.appendChild(node);
    box.appendChild(tip);
  }
  var redraw = null;
  window.addEventListener('resize', function () {
    clearTimeout(redraw);
    redraw = setTimeout(function () { if (chartRows.length) drawChart(); }, 200);
  });

  /* =================== the CSV =================== */
  function exportCsv() {
    if (!eventId || !from || !to) return;
    var btn = $('vs-csv');
    btn.disabled = true;
    var base = (window.BILETEONLINE_CONFIG && window.BILETEONLINE_CONFIG.apiUrl) || '/api/proxy.php';
    var url = base + '?action=organizer.event.leisure.sales.range-csv&event=' + encodeURIComponent(eventId) + '&from=' + from + '&to=' + to;
    var token = null;
    try { token = typeof BileteOnlineAuth !== 'undefined' ? BileteOnlineAuth.getToken() : null; } catch (e) {}
    fetch(url, { headers: token ? { Authorization: 'Bearer ' + token } : {} }).then(function (res) {
      if (!res.ok) throw new Error('HTTP ' + res.status);
      // Only a real CSV is saved: anything else (an error page, a login page) must not become a file named .csv.
      if ((res.headers.get('content-type') || '').toLowerCase().indexOf('text/csv') === -1) throw new Error('not a csv');
      return res.blob();
    }).then(function (blob) {
      var a = document.createElement('a'), href = URL.createObjectURL(blob);
      a.href = href;
      a.download = 'vanzari-' + from + '_' + to + '.csv';
      document.body.appendChild(a);
      a.click();
      document.body.removeChild(a);
      setTimeout(function () { URL.revokeObjectURL(href); }, 4000);
      O.flash('Vânzările pe bilete au fost descărcate.');
    }).catch(function () {
      O.flash('Nu am putut descărca vânzările.', true);
    }).then(function () { btn.disabled = false; });
  }

  /* =================== company invoices =================== */
  function invOpen() { return $('vs-inv-btn').getAttribute('aria-expanded') === 'true'; }
  function loadInvoices(page) {
    if (!eventId || !from || !to) return;
    var my = ++invSeq;
    invPage = page;
    var q = ['from=' + from, 'to=' + to, 'per_page=' + INV_PER, 'page=' + invPage], s = $('vs-inv-q').value.trim();
    if (s) q.push('search=' + encodeURIComponent(s));
    state('vs-inv', 7, 'Se încarcă…');
    O.api('/organizer/events/' + eventId + '/leisure/invoices?' + q.join('&')).then(function (r) {
      if (my !== invSeq) return;
      invLoaded = true;
      var d = (r && r.data) || {}, pg = d.pagination || {}, rows = Array.isArray(d.orders) ? d.orders : [];
      invLast = F.toNum(pg.last_page) || 1;
      invPage = F.toNum(pg.current_page) || invPage;
      var body = $('vs-inv');
      body.textContent = '';
      if (!rows.length) {
        state('vs-inv', 7, s ? 'Nicio factură care să se potrivească.' : 'Nicio factură pe firmă în perioada aleasă.');
        $('vs-inv-count').textContent = '';
        $('vs-inv-prev').disabled = true;
        $('vs-inv-next').disabled = true;
        return;
      }
      rows.forEach(function (o) {
        var firm = el('td', null, el('b', { text: txt(o.company_name) || '—' }));
        var ids = [txt(o.company_cui) && 'CUI ' + txt(o.company_cui), txt(o.company_reg_no)].filter(Boolean).join(' · ');
        if (ids) firm.appendChild(el('small', { class: 've-sub', text: ids }));
        if (txt(o.company_address)) firm.appendChild(el('small', { class: 've-sub', text: txt(o.company_address) }));
        if (txt(o.company_iban)) firm.appendChild(el('small', { class: 've-sub ve-mono', text: txt(o.company_iban) }));
        var contact = el('td', null, el('span', { text: txt(o.company_contact_person) || txt(o.customer_name) || '—' }));
        [txt(o.customer_email), txt(o.customer_phone)].filter(Boolean).forEach(function (v) { contact.appendChild(el('small', { class: 've-sub', text: v })); });
        body.appendChild(el('tr', null, [
          el('td', null, [el('b', { class: 've-mono', text: txt(o.order_number) || '—' }), el('small', { class: 've-sub', text: stamp(o.paid_at) })]),
          el('td', null, txt(o.invoice_number) ? el('b', { class: 've-mono', text: txt(o.invoice_number) }) : el('span', { class: 'org-tag is-wait', text: 'cerută, neemisă' })),
          firm,
          contact,
          el('td', null, [el('span', { text: PAY[o.payment_method] || txt(o.payment_method) || '—' }), el('small', { class: 've-sub', text: txt(o.operator_name) })]),
          el('td', { class: 've-r', text: F.num(F.toNum(o.tickets_count)) }),
          el('td', { class: 've-r' }, el('b', { text: amount(o.total) })),
        ]));
      });
      $('vs-inv-count').textContent = F.count(F.toNum(pg.total) || rows.length, 'factură', 'facturi') + ' · pagina ' + F.num(invPage) + ' din ' + F.num(invLast);
      $('vs-inv-prev').disabled = invPage <= 1;
      $('vs-inv-next').disabled = invPage >= invLast;
    }, function (err) {
      if (my !== invSeq) return;
      if (err && err.status === 401) return;
      state('vs-inv', 7, 'Nu am putut încărca facturile.');
    });
  }

  /* =================== wiring =================== */
  qsa('.ve-range').forEach(function (b) { b.addEventListener('click', function () { setRange(b.getAttribute('data-range')); }); });
  $('vs-apply').addEventListener('click', function () {
    var f = $('vs-from').value, t = $('vs-to').value;
    if (!f || !t) { O.flash('Alege ambele date.', true); return; }
    if (f > t) { O.flash('Data de început e după cea de sfârșit.', true); return; }
    from = f;
    to = t;
    load();
  });
  $('vs-group').addEventListener('change', function () { groupBy = this.value; load(); });
  $('vs-csv').addEventListener('click', exportCsv);
  $('vs-inv-btn').addEventListener('click', function () {
    var open = !invOpen();
    this.setAttribute('aria-expanded', String(open));
    this.firstChild.nodeValue = open ? 'Ascunde facturile' : 'Arată facturile';
    show('vs-inv-body', open);
    if (open && !invLoaded) loadInvoices(1);
  });
  $('vs-inv-q').addEventListener('input', function () { clearTimeout(invTimer); invTimer = setTimeout(function () { loadInvoices(1); }, 300); });
  $('vs-inv-prev').addEventListener('click', function () { if (invPage > 1) loadInvoices(invPage - 1); });
  $('vs-inv-next').addEventListener('click', function () { if (invPage < invLast) loadInvoices(invPage + 1); });
  $('ve-retry').addEventListener('click', function () { show('ve-failed', false); start(); });
  $('ve-event').addEventListener('change', function () {
    eventId = F.toNum(this.value);
    invLoaded = false;
    load();
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
      setRange('7');
    }, function (err) {
      if (err && err.status === 401) return;
      show('ve-failed', true);
    });
  }
  O.ready.then(function (ok) { if (ok) start(); });
})();
