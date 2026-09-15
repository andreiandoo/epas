/* bilete.online v2: organizer dashboard (/organizator/panou). Fills in organizer/dashboard.php inside the organizer
   shell (window.BO_ORG from organizer.js):
   - /organizer/dashboard: the week in one sentence, the all-time figures, the next activity, the month's indicators and
     orders by status, the upcoming activities, the missing payout details notice
   - /organizer/dashboard/analytics-timeline: the chart (net revenue and issued tickets per day; page views and the
     day's activities in the tooltip), the period totals and the 30-day conversion
   - /organizer/dashboard/sales-timeline: the change against the same days of last month (revenue, orders)
   - /organizer/dashboard/recent-orders: the recent activity
   Text from the API is always written as text. */
(function () {
  'use strict';
  var O = window.BO_ORG, root = document.getElementById('od');
  if (!O || !root) return;
  var F = O.fmt, el = O.el, icon = O.icon;
  var $ = function (id) { return document.getElementById(id); };
  var SVGNS = 'http://www.w3.org/2000/svg';
  var today = F.ymd();
  var monthName = F.date(new Date(), { month: 'long' });
  var state = { chart: null, g: null, parts: null, range: { days: 30 }, active: -1, req: 0, width: 0, raf: 0, dashOk: false, deltas: null };

  function cap(s) { return s ? s.charAt(0).toUpperCase() + s.slice(1) : s; }
  function text(id, v) { var n = $(id); if (n) n.textContent = v; }
  function fill(id, kids) {
    var n = $(id);
    if (!n) return null;
    n.textContent = '';
    [].concat(kids).forEach(function (k) { if (k) n.appendChild(typeof k === 'string' ? document.createTextNode(k) : k); });
    return n;
  }
  function dayDiff(a, b) { return Math.round((Date.parse(a + 'T00:00:00Z') - Date.parse(b + 'T00:00:00Z')) / 864e5); }
  /** Big money figures: whole lei from 10 000 up (the exact amount stays in the title), with a smaller " lei". */
  function setBig(id, v) {
    var n = F.toNum(v), exact = F.money(n), shown = Math.abs(n) >= 10000 ? F.num(n) : exact.replace(/ lei$/, '');
    var node = fill(id, [shown, el('small', { text: ' lei' })]);
    if (node) node.title = exact;
  }
  function refocus(container, target) { // a re-render removed the focused control: put the focus somewhere sensible
    if (document.activeElement === document.body || !document.contains(document.activeElement) || container.contains(document.activeElement)) target.focus();
  }

  /* =================== SUMMARY (/organizer/dashboard) =================== */
  var ORDER_ROWS = [['paid', 'Plătite'], ['pending', 'În așteptare'], ['failed', 'Eșuate'], ['cancelled', 'Anulate'], ['expired', 'Expirate'], ['refunded', 'Rambursate']];

  function weekLine(w) {
    if (w <= 0) return 'Nu ai vânzări în ultima săptămână. Hai să schimbăm asta!';
    return 'Ai vândut ' + F.count(w, 'bilet', 'bilete') + ' în ultima săptămână. ' + (w <= 10 ? 'Un început bun!' : w <= 50 ? 'Continuă tot așa!' : 'Excelent!');
  }
  function when(e) {
    var d = F.dateOf(e.starts_at || e.start_date), time = '';
    if (d && e.starts_at) {
      time = F.date(d, { hour: '2-digit', minute: '2-digit' });
      if (time === '00:00') time = '';
    }
    return { d: d, time: time };
  }
  function place(e) { return [F.flat(e.venue), F.flat(e.venue_city)].filter(Boolean).join(', '); }
  function stock(e) {
    var sold = F.toNum(e.tickets_sold), total = F.toNum(e.tickets_total);
    return { sold: sold, total: total, unlimited: total < 0, left: total > 0 ? Math.max(0, total - sold) : null };
  }

  function renderDash(d) {
    var s = d.sales || {}, ev = d.events || {}, at = d.all_time;
    var weekly = F.toNum(d.weekly_sales);
    text('od-week', weekLine(weekly));
    if (at && typeof at === 'object') {
      setBig('od-all-sales', at.total_sales);
      text('od-all-tickets', F.num(at.tickets_sold));
      text('od-all-views', F.num(at.views));
    } else $('od-all').hidden = true;
    $('od-alert').hidden = !(d.account && d.account.has_payout_details === false);

    setBig('od-revenue', s.net_revenue != null ? s.net_revenue : d.revenue_month);
    var gross = F.toNum(s.gross_revenue);
    text('od-revenue-p', gross > 0
      ? 'Net, din ' + F.money(gross) + ' încasați, după comisionul de ' + F.pct(s.commission_rate, 2) + '.'
      : 'Nicio comandă plătită din 1 ' + monthName + '.');
    text('od-tickets', F.num(s.tickets_sold != null ? s.tickets_sold : d.tickets_sold));
    text('od-tickets-p', (weekly > 0 ? F.count(weekly, 'bilet', 'bilete') : 'Niciun bilet') + ' în ultimele 7 zile.');
    text('od-active', F.num(d.active_events != null ? d.active_events : (at && at.ongoing_events)));
    var total = F.toNum(ev.total), unpublished = F.toNum(ev.pending_review);
    text('od-active-p', total > 0
      ? 'Publicate: ' + F.num(ev.published) + ' din ' + F.num(total) + (unpublished > 0 ? ' · nepublicate: ' + F.num(unpublished) : '') + '.'
      : 'Nicio activitate creată încă.');

    renderMonth(s);
    var list = Array.isArray(d.events_list) ? d.events_list.filter(function (e) { return e && e.id != null; }) : [];
    renderNext(list[0]);
    renderEvents(list);
    state.dashOk = true;
    applyDeltas();
  }

  function renderMonth(s) {
    var b = s.order_breakdown || {}, body = $('od-month-body');
    text('od-month-k', 'Luna aceasta · ' + monthName);
    body.textContent = '';
    var total = ORDER_ROWS.reduce(function (t, r) { return t + F.toNum(b[r[0]]); }, 0);
    if (!total) {
      body.appendChild(el('p', { class: 'od-inline', text: 'Nicio comandă din 1 ' + monthName + ' până azi.' }));
      return;
    }
    var many = F.count(total, 'comandă', 'comenzi').replace(/^\S+\s/, '');
    body.appendChild(el('p', { class: 'od-month-total' }, [el('b', { text: F.num(total) }), ' ' + many]));
    var stack = el('div', { class: 'od-stack', 'aria-hidden': 'true' });
    ORDER_ROWS.forEach(function (r) {
      var v = F.toNum(b[r[0]]);
      if (!v) return;
      var seg = el('i', { class: 'is-' + r[0] });
      seg.style.width = (v / total * 100) + '%';
      stack.appendChild(seg);
    });
    body.appendChild(stack);
    body.appendChild(el('ul', { class: 'od-mlist' }, ORDER_ROWS.map(function (r) {
      return el('li', { class: 'is-' + r[0] }, [r[1], el('b', { text: F.num(b[r[0]]) })]);
    })));
    body.appendChild(el('dl', { class: 'od-money' }, [
      el('div', null, [el('dt', { text: 'Încasări' }), el('dd', { text: F.money(s.gross_revenue) })]),
      el('div', null, [el('dt', { text: 'Comision ' + F.pct(s.commission_rate, 2) }), el('dd', { text: '− ' + F.money(s.commission_amount) })]),
      el('div', { class: 'is-net' }, [el('dt', { text: 'Venit net' }), el('dd', { text: F.money(s.net_revenue) })]),
    ]));
  }

  function renderNext(e) {
    var body = $('od-next-body'), tag = $('od-next-tag');
    body.textContent = '';
    tag.hidden = true;
    if (!e) {
      body.appendChild(el('p', { class: 'od-next-t', text: 'Nicio activitate programată' }));
      body.appendChild(el('p', { class: 'od-next-loc', text: 'Când publici o activitate cu dată, o vezi aici cu numărătoarea inversă și check-in-ul.' }));
      body.appendChild(el('div', { class: 'od-next-cta' }, el('a', { class: 'btn btn-primary', href: '/organizator/activities?action=create' }, [icon('plus'), 'Creează o activitate'])));
      return;
    }
    var w = when(e), st = stock(e), name = F.flat(e.name || e.title) || 'Activitate', id = encodeURIComponent(e.id);
    var diff = w.d ? dayDiff(F.ymd(w.d), today) : null;
    if (diff != null && diff >= 0) {
      tag.textContent = diff === 0 ? 'Astăzi' : diff === 1 ? 'Mâine' : 'În ' + F.count(diff, 'zi', 'zile');
      tag.classList.toggle('is-today', diff === 0);
      tag.hidden = false;
    }
    body.appendChild(el('h3', { class: 'od-next-t' }, el('a', { href: '/organizator/activities/' + id, text: name })));
    if (place(e)) body.appendChild(el('p', { class: 'od-next-loc', text: place(e) }));
    if (w.d) {
      body.appendChild(el('div', { class: 'od-next-when' }, [
        el('div', null, [
          el('p', { class: 'od-next-wd', text: F.date(w.d, { weekday: 'long' }) }),
          el('p', { class: 'od-next-big' }, el('time', { datetime: e.starts_at || e.start_date, text: F.date(w.d, { day: 'numeric', month: 'short' }) })),
        ]),
        w.time ? el('p', { class: 'od-next-time', text: w.time }) : el('p', { class: 'od-next-year', text: F.date(w.d, { year: 'numeric' }) }),
      ]));
    }
    body.appendChild(el('dl', { class: 'od-next-stats' }, [
      el('div', null, [el('dt', { text: 'Bilete vândute' }), el('dd', { text: F.num(st.sold) })]),
      el('div', null, [el('dt', { text: 'Disponibile' }), el('dd', { text: st.unlimited ? 'Nelimitat' : st.left == null ? '—' : F.num(st.left) })]),
    ]));
    body.appendChild(el('div', { class: 'od-next-cta' }, [
      el('a', { class: 'btn btn-primary', href: '/organizator/participanti?event=' + id }, [icon('scan'), 'Check-in']),
      el('a', { class: 'btn btn-ghost', href: '/organizator/activities/' + id, text: 'Detalii' }),
    ]));
  }

  function renderEvents(list) {
    var body = $('od-events-body');
    body.textContent = '';
    text('od-events-p', !list.length ? 'Nicio activitate publicată nu urmează.'
      : list.length >= 10 ? 'Primele 10 activități publicate care urmează.'
      : list.length === 1 ? 'O activitate publicată urmează.' : F.num(list.length) + ' activități publicate urmează.');
    if (!list.length) {
      body.appendChild(el('div', { class: 'org-empty' }, [
        el('span', { class: 'org-empty-ic' }, icon('calendar-blank')),
        el('b', { text: 'Nicio activitate viitoare' }),
        el('p', { text: 'Publică o activitate cu dată ca să apară aici, cu vânzările și check-in-ul ei.' }),
        el('div', { class: 'od-empty-cta' }, [
          el('a', { class: 'btn btn-primary', href: '/organizator/activities?action=create' }, [icon('plus'), 'Creează o activitate']),
          el('a', { class: 'btn btn-ghost', href: '/organizator/activities', text: 'Toate activitățile' }),
        ]),
      ]));
      return;
    }
    var year = today.slice(0, 4);
    body.appendChild(el('ul', { class: 'od-ev-list' }, list.map(function (e) {
      var w = when(e), st = stock(e), name = F.flat(e.name || e.title) || 'Activitate', id = encodeURIComponent(e.id), href = '/organizator/activities/' + id;
      var media = el('span', { class: 'od-ev-media', 'aria-hidden': 'true' }), letter = name.charAt(0).toUpperCase();
      if (e.image) {
        var im = el('img', { src: O.img(e.image), alt: '', width: 64, height: 64, loading: 'lazy', decoding: 'async' });
        im.addEventListener('error', function () { media.textContent = letter; }, { once: true });
        media.appendChild(im);
      } else media.textContent = letter;
      var sales;
      if (st.total > 0) {
        var p = Math.min(100, Math.round(st.sold / st.total * 100));
        var meter = el('span', { class: 'od-meter' + (p >= 50 ? '' : p >= 25 ? ' is-mid' : ' is-low'), role: 'progressbar', 'aria-valuemin': 0, 'aria-valuemax': st.total, 'aria-valuenow': Math.min(st.sold, st.total), 'aria-label': 'Bilete vândute: ' + name });
        var bar = el('i');
        bar.style.width = p + '%';
        meter.appendChild(bar);
        sales = [meter, el('span', { text: F.num(st.sold) + ' din ' + F.num(st.total) + ' · ' + p + '%' })];
      } else {
        sales = [el('span', { text: F.count(st.sold, 'bilet vândut', 'bilete vândute') + (st.unlimited ? ' · stoc nelimitat' : '') })];
      }
      var dateText = w.d ? F.date(w.d, { weekday: 'short', day: 'numeric', month: 'short', year: F.ymd(w.d).slice(0, 4) === year ? undefined : 'numeric' }) + (w.time ? ' · ' + w.time : '') : '';
      return el('li', { class: 'od-ev' }, [
        media,
        el('div', { class: 'od-ev-main' }, [
          dateText ? el('p', { class: 'od-ev-when' }, el('time', { datetime: e.starts_at || e.start_date, text: dateText })) : null,
          el('h3', null, el('a', { href: href, text: name })),
          place(e) ? el('p', { class: 'od-ev-meta', text: place(e) }) : null,
          el('div', { class: 'od-ev-sales' }, sales),
        ]),
        el('div', { class: 'od-ev-side' }, [
          e.status === 'published' ? el('span', { class: 'org-tag is-ok', text: 'Publicată' }) : el('span', { class: 'org-tag is-muted', text: 'Draft' }),
          el('div', { class: 'od-ev-act' }, [
            el('a', { class: 'od-ev-btn', href: '/organizator/participanti?event=' + id }, [icon('scan'), 'Check-in', el('span', { class: 'sr', text: ': ' + name })]),
            el('a', { class: 'od-ev-btn', href: href }, ['Detalii', el('span', { class: 'sr', text: ': ' + name })]),
          ]),
        ]),
      ]);
    })));
  }

  function dashFailed() {
    state.dashOk = false;
    applyDeltas(); // no change badge next to a missing figure
    text('od-week', 'Nu am putut încărca datele panoului.');
    ['od-all-sales', 'od-all-tickets', 'od-all-views', 'od-revenue', 'od-tickets', 'od-active'].forEach(function (k) { text(k, '—'); });
    ['od-revenue-p', 'od-tickets-p', 'od-active-p'].forEach(function (k) { text(k, ''); });
    $('od-next-tag').hidden = true;
    fill('od-next-body', el('p', { class: 'od-next-loc', text: 'Nu am putut încărca activitățile.' }));
    text('od-events-p', '');
    fill('od-events-body', el('p', { class: 'od-inline is-error', text: 'Nu am putut încărca activitățile tale.' }));
    fill('od-month-body', el('p', { class: 'od-inline is-error', text: 'Nu am putut încărca comenzile lunii.' }));
  }
  function loadDash() {
    return O.api('/organizer/dashboard').then(function (r) {
      var d = r && r.data;
      if (!d || typeof d !== 'object' || Array.isArray(d)) throw { status: 0 };
      $('od-fail').hidden = true;
      renderDash(d);
      return true;
    }).catch(function (err) {
      if (err && err.status === 401) return false;
      $('od-fail').hidden = false;
      dashFailed();
      return false;
    });
  }

  /* =================== CHANGE VS LAST MONTH (/organizer/dashboard/sales-timeline) =================== */
  function setDelta(id, cur, prev, what, span) {
    var n = $(id);
    if (!n) return;
    if (!(prev > 0)) { n.hidden = true; return; }
    var r = Math.round((cur - prev) / prev * 100);
    n.textContent = '';
    n.className = 'od-delta ' + (r > 0 ? 'is-up' : r < 0 ? 'is-down' : 'is-flat');
    if (r) n.appendChild(icon(r > 0 ? 'trend-up' : 'trend-down'));
    n.appendChild(document.createTextNode((r > 0 ? '+' : r < 0 ? '−' : '') + F.num(Math.abs(r)) + '%'));
    n.appendChild(el('span', { class: 'sr', text: ' ' + what + ' față de ' + span }));
    n.title = cap(what) + ' față de ' + span;
    n.hidden = false;
  }
  function applyDeltas() { // shown once the month's figures they sit next to are on the page
    var x = state.deltas;
    if (!state.dashOk || !x) { $('od-delta-revenue').hidden = true; $('od-delta-orders').hidden = true; return; }
    // commission is a flat share, so the change in takings is the change in net revenue
    setDelta('od-delta-revenue', x.rev[0], x.rev[1], 'venituri', x.span);
    setDelta('od-delta-orders', x.ord[0], x.ord[1], 'comenzi plătite', x.span);
  }
  function loadDelta() {
    var y = +today.slice(0, 4), m = +today.slice(5, 7), day = +today.slice(8, 10);
    var pad = function (v) { return (v < 10 ? '0' : '') + v; };
    var py = m === 1 ? y - 1 : y, pm = m === 1 ? 12 : m - 1;
    var prevStart = py + '-' + pad(pm) + '-01', curStart = today.slice(0, 8) + '01';
    var prevDays = new Date(Date.UTC(py, pm, 0)).getUTCDate(), lastDay = Math.min(day, prevDays);
    var prevEnd = py + '-' + pad(pm) + '-' + pad(lastDay);
    var prevName = F.date(new Date(Date.UTC(py, pm - 1, 15)), { month: 'long' });
    var span = (lastDay === 1 ? '1 ' : '1–' + lastDay + ' ') + prevName;
    return O.api('/organizer/dashboard/sales-timeline?from_date=' + prevStart + '&to_date=' + today + '&group_by=day').then(function (r) {
      var rows = (r && r.data && Array.isArray(r.data.timeline)) ? r.data.timeline : [];
      var cur = { rev: 0, ord: 0 }, prev = { rev: 0, ord: 0 };
      rows.forEach(function (x) {
        var p = String(x.period || '').slice(0, 10), bucket = p >= curStart ? cur : (p >= prevStart && p <= prevEnd ? prev : null);
        if (!bucket) return;
        bucket.rev += F.toNum(x.revenue);
        bucket.ord += F.toNum(x.orders);
      });
      state.deltas = { rev: [cur.rev, prev.rev], ord: [cur.ord, prev.ord], span: span };
      applyDeltas();
    }).catch(function () {});
  }

  /* =================== RECENT ORDERS =================== */
  var ORDER = {
    paid: ['Plătită', 'is-ok', 'check-circle'], confirmed: ['Plătită', 'is-ok', 'check-circle'], completed: ['Plătită', 'is-ok', 'check-circle'],
    pending: ['În așteptare', 'is-wait', 'clock'], failed: ['Eșuată', 'is-bad', 'x'], cancelled: ['Anulată', 'is-muted', 'x'],
    expired: ['Expirată', 'is-muted', 'clock'], refunded: ['Rambursată', 'is-bad', 'arrow-left'], partially_refunded: ['Rambursată parțial', 'is-wait', 'arrow-left'],
  };
  function loadRecent() {
    var body = $('od-recent-body');
    return O.api('/organizer/dashboard/recent-orders?limit=6').then(function (r) {
      var rows = (r && r.data && Array.isArray(r.data.orders)) ? r.data.orders : [];
      body.textContent = '';
      if (!rows.length) {
        body.appendChild(el('p', { class: 'od-inline', text: 'Nicio comandă încă. Comenzile noi apar aici imediat ce sunt plasate.' }));
        return;
      }
      body.appendChild(el('ul', { class: 'od-ro-list' }, rows.map(function (o) {
        var st = ORDER[o.status] || [cap(String(o.status || 'Necunoscut')), 'is-muted', 'receipt'];
        var who = [F.flat(o.customer) || 'Client', F.flat(o.event)].filter(Boolean).join(' · ');
        return el('li', { class: 'od-ro' }, [
          el('span', { class: 'od-ro-ic ' + st[1], 'aria-hidden': 'true' }, icon(st[2])),
          el('div', { class: 'od-ro-t' }, [
            el('p', { class: 'od-ro-h' }, [el('b', { text: o.order_number ? 'Comanda ' + o.order_number : 'Comandă' }), el('span', { class: 'org-tag ' + st[1], text: st[0] })]),
            el('p', { class: 'od-ro-who', text: who, title: who }),
          ]),
          el('div', { class: 'od-ro-v' }, [el('b', { text: F.money(o.total) }), o.created_at ? el('time', { datetime: o.created_at, text: F.ago(o.created_at) }) : null]),
        ]);
      })));
    }).catch(function (err) {
      if (err && err.status === 401) return;
      var hadFocus = body.contains(document.activeElement);
      body.textContent = '';
      var retry = el('button', { class: 'od-link-btn', type: 'button', text: 'Reîncearcă' });
      retry.addEventListener('click', function () {
        retry.disabled = true;
        retry.textContent = 'Se încarcă…';
        loadRecent().then(function () { refocus(body, $('od-recent-h')); });
      });
      body.appendChild(el('p', { class: 'od-inline is-error' }, ['Nu am putut încărca ultimele comenzi. ', retry]));
      if (hadFocus) retry.focus();
    });
  }

  /* =================== CHART (/organizer/dashboard/analytics-timeline) =================== */
  function niceNum(x) {
    if (!(x > 0)) return 1;
    var e = Math.pow(10, Math.floor(Math.log10(x))), f = x / e;
    return (f <= 1 ? 1 : f <= 2 ? 2 : f <= 2.5 ? 2.5 : f <= 5 ? 5 : 10) * e;
  }
  function revScale(min, max) { // four equal steps that cover [min, max] and include 0
    var step = niceNum((max - min) / 4);
    for (var k = 0; k < 8; k++) {
      var lo = Math.floor(min / step) * step;
      if (lo + 4 * step >= max - 1e-9) return { lo: lo, hi: lo + 4 * step };
      step = niceNum(step * 1.001);
    }
    return { lo: Math.floor(min / step) * step, hi: Math.floor(min / step) * step + 4 * step };
  }
  var compact = new Intl.NumberFormat('ro-RO', { notation: 'compact', maximumFractionDigits: 1 });
  function svgNode(tag, attrs, txt) {
    var n = document.createElementNS(SVGNS, tag);
    Object.keys(attrs).forEach(function (k) { n.setAttribute(k, attrs[k]); });
    if (txt != null) n.textContent = txt;
    return n;
  }
  function periodLabel(range) {
    if (range.days) return 'Ultimele ' + F.count(range.days, 'zi', 'zile');
    var a = F.dateOf(range.from), b = F.dateOf(range.to);
    if (!a || !b) return '';
    if (range.from === range.to) return F.date(a, { day: 'numeric', month: 'long', year: 'numeric' });
    return F.date(a, { day: 'numeric', month: 'short', year: range.from.slice(0, 4) === range.to.slice(0, 4) ? undefined : 'numeric' }) + ' – ' + F.date(b, { day: 'numeric', month: 'short', year: 'numeric' });
  }

  function drawChart() {
    var plot = $('od-plot'), old = plot.querySelector('svg');
    if (old) old.remove();
    state.parts = null;
    var d = state.chart;
    if (!d) { state.g = null; return; }
    var W = Math.max(260, Math.floor(plot.clientWidth)), narrow = W < 560, H = narrow ? 220 : 280;
    var n = d.raw_dates.length, rev = d.raw_dates.map(function (_, i) { return F.toNum(d.revenue[i]); }), tix = d.raw_dates.map(function (_, i) { return F.toNum(d.tickets[i]); });
    var padL = narrow ? 42 : 58, padR = narrow ? 30 : 40, padT = 14, padB = 34;
    var rs = revScale(Math.min(0, Math.min.apply(null, rev)), Math.max(0, Math.max.apply(null, rev)));
    var tMax = Math.max(1, Math.ceil(niceNum(Math.max.apply(null, tix.concat([0])) / 4))) * 4;
    var g = state.g = { W: W, H: H, n: n, rev: rev, tix: tix, padL: padL, padR: padR, padT: padT, iw: W - padL - padR, ih: H - padT - padB };
    state.width = W;
    var y = function (v) { return padT + g.ih * (rs.hi - v) / (rs.hi - rs.lo); };
    var yt = function (v) { return padT + g.ih * (1 - v / tMax); };
    var bw = g.iw / n, barW = Math.max(1, Math.min(26, bw * 0.62));
    var svg = svgNode('svg', { class: 'od-svg', width: W, height: H, viewBox: '0 0 ' + W + ' ' + H, 'aria-hidden': 'true', focusable: 'false' });

    for (var i = 0; i <= 4; i++) { // grid: revenue on the left, tickets on the right, same lines
      var v = rs.lo + (rs.hi - rs.lo) * i / 4, yy = Math.round(y(v)) + 0.5;
      svg.appendChild(svgNode('line', { class: 'od-gl' + (Math.abs(v) < 1e-9 ? ' is-zero' : ''), x1: padL, x2: W - padR, y1: yy, y2: yy }));
      svg.appendChild(svgNode('text', { class: 'od-ax', x: padL - 8, y: yy + 4, 'text-anchor': 'end' }, compact.format(v)));
      svg.appendChild(svgNode('text', { class: 'od-ax is-r', x: W - padR + 8, y: yy + 4, 'text-anchor': 'start' }, F.num(tMax * i / 4)));
    }
    var band = svgNode('rect', { class: 'od-band', x: padL, y: padT, width: bw, height: g.ih, visibility: 'hidden' });
    svg.appendChild(band);
    var zero = y(0), bars = rev.map(function (val, k) {
      if (!val) return null;
      var bar = svgNode('rect', { class: 'od-bar' + (val < 0 ? ' is-neg' : ''), x: (padL + k * bw + (bw - barW) / 2).toFixed(2), y: Math.min(y(val), zero).toFixed(2), width: barW.toFixed(2), height: Math.max(1, Math.abs(zero - y(val))).toFixed(2), rx: Math.min(4, barW / 3).toFixed(2) });
      svg.appendChild(bar);
      return bar;
    });
    var pts = tix.map(function (val, k) { return [padL + (k + 0.5) * bw, yt(val)]; });
    if (tix.some(Boolean) && n > 1) {
      var line = pts.map(function (p, k) { return (k ? 'L' : 'M') + p[0].toFixed(1) + ' ' + p[1].toFixed(1); }).join('');
      svg.appendChild(svgNode('path', { class: 'od-area', d: line + 'L' + pts[n - 1][0].toFixed(1) + ' ' + (padT + g.ih) + 'L' + pts[0][0].toFixed(1) + ' ' + (padT + g.ih) + 'Z' }));
      svg.appendChild(svgNode('path', { class: 'od-line', d: line }));
    }
    var evDays = d.event_days && typeof d.event_days === 'object' ? d.event_days : {};
    d.raw_dates.forEach(function (key, k) {
      if (Array.isArray(evDays[key]) && evDays[key].length) svg.appendChild(svgNode('circle', { class: 'od-evt', cx: pts[k][0].toFixed(1), cy: padT + g.ih + 8, r: n > 120 ? 2 : 3 }));
    });
    var slots = Math.min(n, narrow ? 4 : 7), seen = {};
    for (var s = 0; s < slots; s++) {
      var idx = slots === 1 ? 0 : Math.round(s * (n - 1) / (slots - 1));
      if (seen[idx]) continue;
      seen[idx] = true;
      var anchor = slots === 1 ? 'middle' : s === 0 ? 'start' : s === slots - 1 ? 'end' : 'middle';
      var lx = anchor === 'start' ? padL : anchor === 'end' ? W - padR : pts[idx][0];
      svg.appendChild(svgNode('text', { class: 'od-ax', x: lx.toFixed(1), y: H - 6, 'text-anchor': anchor }, String(d.labels[idx] || d.raw_dates[idx])));
    }
    var dot = svgNode('circle', { class: 'od-pt', cx: 0, cy: 0, r: 4.5, visibility: 'hidden' });
    svg.appendChild(dot);
    svg.appendChild(svgNode('rect', { class: 'od-hit', x: padL, y: 0, width: g.iw, height: H }));
    plot.insertBefore(svg, plot.firstChild);
    state.parts = { band: band, dot: dot, bars: bars, pts: pts, bw: bw };
    $('od-plot-msg').hidden = rev.some(Boolean) || tix.some(Boolean);
    if (state.active >= 0 && state.active < n) show(state.active, false);
    else hide();
  }

  function show(i, announce) {
    var d = state.chart, g = state.g, P = state.parts;
    if (!d || !g || !P || i < 0 || i >= g.n) return;
    state.active = i;
    P.band.setAttribute('x', (g.padL + i * P.bw).toFixed(2));
    P.band.setAttribute('visibility', 'visible');
    P.bars.forEach(function (b, k) { if (b) b.classList.toggle('is-on', k === i); });
    P.dot.setAttribute('cx', P.pts[i][0].toFixed(1));
    P.dot.setAttribute('cy', P.pts[i][1].toFixed(1));
    P.dot.setAttribute('visibility', 'visible');
    var key = d.raw_dates[i], day = F.dateOf(key), evs = (d.event_days && Array.isArray(d.event_days[key])) ? d.event_days[key] : [];
    var title = day ? cap(F.date(day, { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' })) : String(key);
    var views = F.toNum((d.views || [])[i]);
    var tip = $('od-tip');
    tip.textContent = '';
    tip.appendChild(el('b', { text: title }));
    tip.appendChild(el('dl', null, [
      el('dt', { text: 'Venit net' }), el('dd', { text: F.money(g.rev[i]) }),
      el('dt', { text: 'Bilete emise' }), el('dd', { text: F.num(g.tix[i]) }),
      el('dt', { text: 'Vizualizări' }), el('dd', { text: F.num(views) }),
    ]));
    if (evs.length) {
      tip.appendChild(el('ul', null, evs.slice(0, 3).map(function (v) {
        return el('li', { text: [F.flat(v && v.title), F.flat(v && v.venue), F.flat(v && v.city)].filter(Boolean).join(' · ') || 'Activitate' });
      }).concat(evs.length > 3 ? [el('li', { class: 'is-more', text: '+ ' + F.count(evs.length - 3, 'altă activitate', 'alte activități') })] : [])));
    }
    tip.hidden = false;
    var cx = P.pts[i][0], tw = tip.offsetWidth, left = cx + 16;
    if (left + tw > g.W - 2) left = cx - 16 - tw;
    tip.style.left = Math.max(0, Math.min(left, g.W - tw)) + 'px';
    if (announce) {
      text('od-plot-live', title + ': venit net ' + F.money(g.rev[i]) + ', ' + F.count(g.tix[i], 'bilet emis', 'bilete emise') + ', ' + F.count(views, 'vizualizare', 'vizualizări') + (evs.length ? ', ' + F.count(evs.length, 'activitate', 'activități') + ' în program' : '') + '.');
    }
  }
  function hide() {
    state.active = -1;
    var P = state.parts;
    if (P) {
      P.band.setAttribute('visibility', 'hidden');
      P.dot.setAttribute('visibility', 'hidden');
      P.bars.forEach(function (b) { if (b) b.classList.remove('is-on'); });
    }
    $('od-tip').hidden = true;
  }
  function lastWithData() {
    var g = state.g;
    for (var i = g.n - 1; i >= 0; i--) if (g.rev[i] || g.tix[i]) return i;
    return g.n - 1;
  }
  function renderTotals(d) {
    var t = d.totals || {}, views = F.toNum(t.views), tix = F.toNum(t.tickets);
    text('od-t-rev', F.money(t.revenue_net != null ? t.revenue_net : t.revenue));
    text('od-t-tix', F.num(tix));
    text('od-t-views', F.num(views));
    text('od-t-conv', views > 0 ? F.pct(tix / views * 100) : '—');
    text('od-t-avg', F.money(t.revenue_per_day));
    var t0 = d.totals || {};
    $('od-plot').setAttribute('aria-label', 'Grafic vânzări, ' + $('od-period').textContent + ': venit net ' + F.money(t0.revenue_net != null ? t0.revenue_net : t0.revenue) + ', ' + F.count(tix, 'bilet emis', 'bilete emise') + ', ' + F.count(views, 'vizualizare', 'vizualizări'));
  }
  function renderConversion(d) {
    var t = d.totals || {}, views = F.toNum(t.views), tix = F.toNum(t.tickets);
    if (views > 0) {
      text('od-conv', F.pct(tix / views * 100));
      text('od-conv-p', F.count(tix, 'bilet emis', 'bilete emise') + ' la ' + F.count(views, 'vizualizare', 'vizualizări') + ' de pagini, în ultimele 30 de zile.');
    } else {
      text('od-conv', '—');
      text('od-conv-p', 'Nu avem vizualizări măsurate în ultimele 30 de zile.');
    }
  }
  function loadChart(range, initial) {
    var id = ++state.req, plot = $('od-plot');
    state.range = range;
    text('od-period', periodLabel(range));
    plot.setAttribute('aria-busy', 'true');
    $('od-plot-err').hidden = true;
    var qs = range.days ? 'days=' + range.days : 'from=' + range.from + '&to=' + range.to;
    return O.api('/organizer/dashboard/analytics-timeline?' + qs).then(function (r) {
      var d = r && r.data;
      if (!d || !Array.isArray(d.raw_dates) || !d.raw_dates.length) throw { status: 0 };
      ['revenue', 'tickets', 'views', 'labels'].forEach(function (k) { if (!Array.isArray(d[k])) d[k] = []; });
      if (initial) renderConversion(d);
      if (id !== state.req) return;
      if (!range.days && d.from && d.to) text('od-period', periodLabel({ from: d.from, to: d.to })); // core may shorten the range
      state.chart = d;
      state.active = -1;
      drawChart();
      renderTotals(d);
    }).catch(function (err) {
      if (err && err.status === 401) return;
      if (initial) { text('od-conv', '—'); text('od-conv-p', 'Nu am putut calcula conversia acum.'); }
      if (id !== state.req) return;
      state.chart = null;
      drawChart();
      $('od-tip').hidden = true;
      $('od-plot-msg').hidden = true;
      $('od-plot-err').hidden = false;
      ['od-t-rev', 'od-t-tix', 'od-t-views', 'od-t-conv', 'od-t-avg'].forEach(function (k) { text(k, '—'); });
    }).then(function () { if (id === state.req) plot.setAttribute('aria-busy', 'false'); });
  }

  /* =================== CONTROLS =================== */
  var plot = $('od-plot'), periods = [].slice.call(root.querySelectorAll('.od-period')), custom = $('od-custom'), form = $('od-range');
  function press(btn) { periods.forEach(function (b) { b.setAttribute('aria-pressed', String(b === btn)); }); }
  periods.forEach(function (btn) {
    btn.addEventListener('click', function () {
      if (btn === custom) {
        var open = form.hidden;
        form.hidden = !open;
        custom.setAttribute('aria-expanded', String(open));
        if (open) {
          var f = $('od-from'), t = $('od-to');
          f.max = t.max = today;
          if (!t.value) t.value = today;
          if (!f.value) f.value = F.ymd(new Date(Date.now() - 29 * 864e5));
          f.focus();
        }
        return;
      }
      form.hidden = true;
      custom.setAttribute('aria-expanded', 'false');
      text('od-range-err', '');
      var days = +btn.getAttribute('data-days');
      if (btn.getAttribute('aria-pressed') === 'true' && state.chart && state.range.days === days) return;
      press(btn);
      loadChart({ days: days });
    });
  });
  form.addEventListener('submit', function (e) {
    e.preventDefault();
    var f = $('od-from'), t = $('od-to'), from = f.value, to = t.value, bad = null, msg = '';
    f.removeAttribute('aria-invalid');
    t.removeAttribute('aria-invalid');
    if (!from || !to) { msg = 'Alege ambele date.'; bad = from ? t : f; }
    else if (from > to) { msg = 'Data de început trebuie să fie înainte de data de sfârșit.'; bad = f; }
    else if (to > today) { msg = 'Data de sfârșit poate fi cel mult azi.'; bad = t; }
    else if (dayDiff(to, from) + 1 > 365) { msg = 'Alege o perioadă de cel mult 365 de zile.'; bad = f; }
    text('od-range-err', msg);
    if (bad) { bad.setAttribute('aria-invalid', 'true'); bad.focus(); return; }
    press(custom);
    loadChart({ from: from, to: to });
  });
  $('od-plot-retry').addEventListener('click', function () { loadChart(state.range); plot.focus(); });

  function indexAt(clientX) {
    var g = state.g, x = clientX - plot.getBoundingClientRect().left - g.padL;
    return Math.max(0, Math.min(g.n - 1, Math.floor(x / (g.iw / g.n))));
  }
  plot.addEventListener('pointermove', function (e) {
    if (!state.g || !state.parts) return;
    var x = e.clientX - plot.getBoundingClientRect().left;
    if (x < state.g.padL - 4 || x > state.g.W - state.g.padR + 4) { if (document.activeElement !== plot) hide(); return; }
    show(indexAt(e.clientX), false);
  });
  plot.addEventListener('pointerdown', function (e) { if (state.g && state.parts && e.pointerType !== 'mouse') show(indexAt(e.clientX), false); });
  plot.addEventListener('pointerleave', function (e) { if (e.pointerType === 'mouse' && document.activeElement !== plot) hide(); });
  plot.addEventListener('keydown', function (e) {
    if (!state.g || !state.parts) return;
    var n = state.g.n, i = state.active;
    if (e.key === 'ArrowRight' || e.key === 'ArrowLeft') i = i < 0 ? lastWithData() : Math.max(0, Math.min(n - 1, i + (e.key === 'ArrowRight' ? 1 : -1)));
    else if (e.key === 'Home') i = 0;
    else if (e.key === 'End') i = n - 1;
    else if (e.key === 'Escape') { if (state.active >= 0) { e.preventDefault(); e.stopPropagation(); hide(); } return; }
    else return;
    e.preventDefault();
    show(i, true);
  });
  plot.addEventListener('blur', function () { hide(); });
  if ('ResizeObserver' in window) {
    new ResizeObserver(function () {
      if (!state.chart || Math.floor(plot.clientWidth) === state.width) return;
      cancelAnimationFrame(state.raf);
      state.raf = requestAnimationFrame(drawChart);
    }).observe(plot);
  }

  $('od-retry').addEventListener('click', function () {
    var btn = this;
    btn.disabled = true;
    btn.textContent = 'Se încarcă…';
    Promise.all([loadDash(), loadDelta(), loadRecent(), state.chart ? null : loadChart(state.range, true)]).then(function (res) {
      btn.disabled = false;
      btn.textContent = 'Reîncearcă';
      if (res[0]) $('od-h').focus();
    });
  });

  /* =================== START =================== */
  O.ready.then(function (ok) {
    if (!ok) return;
    text('od-kicker', 'Panou organizator · ' + cap(F.date(new Date(), { month: 'long', year: 'numeric' })));
    O.onProfile(function (o) {
      var who = String(o.contact_name || '').trim().split(/\s+/)[0] || String(o.representative_first_name || '').trim() || String(o.name || '').trim();
      text('od-name', who ? ', ' + who : '');
    });
    loadDash();
    loadChart({ days: 30 }, true);
    loadDelta();
    loadRecent();
  });
})();
