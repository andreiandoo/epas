/* bilete.online v2: organizer sales (/organizator/vanzari). Filters (period or month, activity, status, search), the
   figures core computes for them, the per-activity breakdown, the orders table with sort and pages, and the CSV export.
   Filters, sort and page live in the address (?event=&status=&de_la=&pana_la=&luna=&q=&sort=&pagina=&defalcat=1).
   Runs inside the organizer shell (window.BO_ORG); text from the API is always written as text. */
(function () {
  'use strict';
  var O = window.BO_ORG, root = document.getElementById('os');
  if (!O || !root) return;
  var F = O.fmt, el = O.el, icon = O.icon;
  var $ = function (id) { return document.getElementById(id); };
  function qsa(sel, ctx) { return [].slice.call((ctx || document).querySelectorAll(sel)); }
  var PER_PAGE = 25;
  var SORTABLE = ['order_number', 'customer_name', 'total', 'status', 'source', 'created_at'];
  var STATUSES = ['', 'completed', 'pending', 'failed', 'expired', 'cancelled', 'refunded'];
  var STATUS_TAG = {
    completed: ['Finalizată', 'is-ok'], paid: ['Finalizată', 'is-ok'], confirmed: ['Finalizată', 'is-ok'], pending: ['În așteptare', 'is-wait'],
    cancelled: ['Anulată', 'is-bad'], refunded: ['Rambursată', 'is-info'], partially_refunded: ['Parțial rambursată', 'is-wait'], failed: ['Eșuată', 'is-bad'], expired: ['Expirată', 'is-muted'],
  };
  var SOURCES = { marketplace: 'bilete.online', widget: 'Widget', pos: 'POS', pos_app: 'Aplicație', api: 'API', manual: 'Manual', legacy_import: 'Import', test_order: 'Test' };
  var MIX = [['paid', 'Finalizate', 'completed', '#1B7F4E'], ['pending', 'În așteptare', 'pending', '#F2A900'], ['failed', 'Eșuate', 'failed', '#E43A33'], ['cancelled', 'Anulate', 'cancelled', '#8E958F'], ['expired', 'Expirate', 'expired', '#C5CAC6'], ['refunded', 'Rambursate', 'refunded', '#2D6CCD']];

  var DEFAULTS = { mode: 'range', event: '', status: 'completed', from: '', to: '', month: '', q: '', sort: 'created_at', dir: 'desc', page: 1, breakdown: false };
  var S = Object.assign({}, DEFAULTS);
  var events = [], eventsLoaded = false, seq = 0, bdSeq = 0, bdKey = '', firstLoad = true;

  function isDate(v) { return /^\d{4}-\d{2}-\d{2}$/.test(String(v || '')); }
  function pad(n) { return (n < 10 ? '0' : '') + n; }
  function dayLabel(ymd, opts) { var d = F.dateOf(ymd); return d ? F.date(d, opts) : ''; }
  function naiveDay(v) { var m = /^(\d{4}-\d{2}-\d{2})/.exec(String(v || '')); return m ? m[1] : ''; }
  function maskEmail(email) {
    if (!email) return '—';
    var parts = String(email).split('@');
    if (parts.length !== 2) return String(email);
    var name = parts[0];
    return (name.length > 2 ? name.charAt(0) + new Array(name.length - 1).join('*') + name.charAt(name.length - 1) : name) + '@' + parts[1];
  }
  function stamp(iso) { // the day and the hour, in Bucharest
    var d = F.dateOf(iso);
    if (!d) return el('span', { text: '—' });
    return [el('span', { text: F.date(d, { day: '2-digit', month: '2-digit', year: 'numeric' }) }), el('span', { class: 'os-time', text: F.date(d, { hour: '2-digit', minute: '2-digit' }) })];
  }
  function seatText(list) { // "Parter, rândul B: locurile 12, 13; Balcon: locul 4"
    var heads = [], by = {};
    (Array.isArray(list) ? list : []).forEach(function (s) {
      if (!s) return;
      var head = [F.flat(s.section), s.row != null && s.row !== '' ? 'rândul ' + s.row : ''].filter(Boolean).join(', ');
      if (!by[head]) { by[head] = []; heads.push(head); }
      if (s.seat != null && s.seat !== '') by[head].push(String(s.seat));
    });
    return heads.map(function (h) {
      var n = by[h], seats = n.length ? (n.length > 1 ? 'locurile ' : 'locul ') + n.join(', ') : '';
      return h && seats ? h + ': ' + seats : h || seats;
    }).filter(Boolean).join('; ');
  }

  /* =================== STATE <-> ADDRESS <-> CONTROLS =================== */
  function readUrl() {
    var p = new URLSearchParams(location.search);
    S = Object.assign({}, DEFAULTS);
    if (/^\d+$/.test(p.get('event') || '')) S.event = p.get('event');
    var st = p.get('status');
    if (st === 'toate') S.status = '';
    else if (STATUSES.indexOf(st) > 0) S.status = st;
    if (/^\d{4}-\d{2}$/.test(p.get('luna') || '')) { S.mode = 'month'; S.month = p.get('luna'); }
    if (isDate(p.get('de_la'))) S.from = p.get('de_la');
    if (isDate(p.get('pana_la'))) S.to = p.get('pana_la');
    S.q = (p.get('q') || '').slice(0, 100);
    var so = (p.get('sort') || '').split('.');
    if (SORTABLE.indexOf(so[0]) > -1) { S.sort = so[0]; S.dir = so[1] === 'asc' ? 'asc' : 'desc'; }
    S.page = Math.max(1, parseInt(p.get('pagina'), 10) || 1);
    S.breakdown = p.get('defalcat') === '1';
  }
  function writeUrl() {
    var p = new URLSearchParams();
    if (S.event) p.set('event', S.event);
    if (S.status !== 'completed') p.set('status', S.status || 'toate');
    if (S.mode === 'month' && S.month) p.set('luna', S.month);
    else { if (S.from) p.set('de_la', S.from); if (S.to) p.set('pana_la', S.to); }
    if (S.q) p.set('q', S.q);
    if (!(S.sort === 'created_at' && S.dir === 'desc')) p.set('sort', S.sort + '.' + S.dir);
    if (S.page > 1) p.set('pagina', String(S.page));
    if (S.breakdown) p.set('defalcat', '1');
    var url = location.pathname + (p.toString() ? '?' + p.toString() : '') + location.hash;
    if (url !== location.pathname + location.search + location.hash) history.replaceState(null, '', url);
  }
  function syncControls() {
    qsa('.os-seg-b', root).forEach(function (b) { b.setAttribute('aria-pressed', String(b.getAttribute('data-mode') === S.mode)); });
    qsa('[data-for-mode]', root).forEach(function (n) { n.hidden = n.getAttribute('data-for-mode') !== S.mode; });
    $('os-status').value = S.status;
    $('os-from').value = S.from;
    $('os-to').value = S.to;
    $('os-to').min = S.from; // the pickers keep the period the right way round
    $('os-from').max = S.to;
    $('os-month').value = S.month;
    if ($('os-q').value !== S.q) $('os-q').value = S.q;
    $('os-breakdown').checked = S.breakdown;
    if (eventsLoaded) setEventValue();
    var sortValue = S.sort + ':' + S.dir, sm = $('os-sort-m');
    if (!qsa('option', sm).some(function (o) { return o.value === sortValue; })) sm.appendChild(el('option', { value: sortValue, text: sortLabel() }));
    sm.value = sortValue;
    qsa('#os-table th[data-sort]').forEach(function (th) {
      th.setAttribute('aria-sort', th.getAttribute('data-sort') === S.sort ? (S.dir === 'asc' ? 'ascending' : 'descending') : 'none');
    });
    $('os-reset').hidden = !(S.event || S.status !== 'completed' || S.mode !== 'range' || S.from || S.to || S.q);
  }
  function sortLabel() {
    var th = document.querySelector('#os-table th[data-sort="' + S.sort + '"]');
    return (th ? th.textContent.trim() : S.sort) + (S.dir === 'asc' ? ', crescător' : ', descrescător');
  }
  function currentMonth() { return F.ymd().slice(0, 7); }
  function dates() {
    if (S.mode === 'month') {
      if (!/^\d{4}-\d{2}$/.test(S.month)) return { from: '', to: '' };
      var y = +S.month.slice(0, 4), m = +S.month.slice(5, 7), last = new Date(Date.UTC(y, m, 0)).getUTCDate();
      return { from: S.month + '-01', to: S.month + '-' + pad(last) };
    }
    if (S.from && S.to && S.from > S.to) return { from: S.from, to: S.to, error: 'Data de început trebuie să fie înainte de data de sfârșit.' };
    return { from: S.from, to: S.to };
  }
  function periodLabel(d) {
    if (S.mode === 'month' && S.month) return cap(dayLabel(S.month + '-15', { month: 'long', year: 'numeric' }));
    if (d.from && d.to) return dayLabel(d.from, { day: 'numeric', month: 'short', year: 'numeric' }) + ' – ' + dayLabel(d.to, { day: 'numeric', month: 'short', year: 'numeric' });
    if (d.from) return 'De la ' + dayLabel(d.from, { day: 'numeric', month: 'short', year: 'numeric' });
    if (d.to) return 'Până la ' + dayLabel(d.to, { day: 'numeric', month: 'short', year: 'numeric' });
    return 'Toată perioada';
  }
  function cap(s) { return s ? s.charAt(0).toUpperCase() + s.slice(1) : s; }
  function filterParams(d, extra) { // activity + period; status and search are added where they apply
    var p = new URLSearchParams();
    if (S.event) p.set('event_id', S.event);
    if (d.from) p.set('from_date', d.from);
    if (d.to) p.set('to_date', d.to);
    Object.keys(extra || {}).forEach(function (k) { p.set(k, String(extra[k])); });
    return p;
  }

  /* =================== ACTIVITIES (filter + breakdown) =================== */
  function isLive(ev) {
    if (ev.is_cancelled || ev.is_postponed || ev.is_past || ev.is_ended) return false;
    if (ev.status !== 'published' && ev.status !== 'active') return false;
    var end = naiveDay(ev.ends_at || ev.starts_at);
    return !end || end >= F.ymd();
  }
  function loadEvents() {
    var rows = [];
    function page(n) { // the list is paginated (50 per page through the proxy): read every page
      return O.api('/organizer/events?per_page=50&page=' + n).then(function (r) {
        rows = rows.concat(Array.isArray(r && r.data) ? r.data : []);
        var last = F.toNum(O.metaOf(r).last_page);
        if (last > n && n < 60) return page(n + 1);
      });
    }
    return page(1).then(function () {
      events = rows.filter(function (e) { return e && e.id != null; }).sort(function (a, b) {
        var al = isLive(a), bl = isLive(b), ad = String(a.starts_at || ''), bd = String(b.starts_at || '');
        if (al !== bl) return al ? -1 : 1;
        return al ? (ad < bd ? -1 : ad > bd ? 1 : 0) : (ad > bd ? -1 : ad < bd ? 1 : 0);
      });
    }, function () { events = []; }).then(function () {
      eventsLoaded = true;
      fillEvents();
    });
  }
  function eventLabel(ev) {
    var day = naiveDay(ev.starts_at);
    var meta = [day ? dayLabel(day, { day: 'numeric', month: 'short', year: 'numeric' }) : '', F.flat(ev.venue_name)].filter(Boolean).join(' · ');
    return (F.flat(ev.name || ev.title) || 'Activitatea #' + ev.id) + (meta ? ' — ' + meta : '');
  }
  function fillEvents() {
    var sel = $('os-event');
    sel.textContent = '';
    sel.appendChild(el('option', { value: '', text: 'Toate activitățile' }));
    var live = events.filter(isLive), other = events.filter(function (e) { return !isLive(e); });
    if (live.length) sel.appendChild(el('optgroup', { label: 'În derulare' }, live.map(function (e) { return el('option', { value: String(e.id), text: eventLabel(e) }); })));
    if (other.length) sel.appendChild(el('optgroup', { label: 'Încheiate, amânate, anulate sau nepublicate' }, other.map(function (e) { return el('option', { value: String(e.id), text: eventLabel(e) }); })));
    setEventValue();
  }
  function setEventValue() {
    var sel = $('os-event');
    if (S.event && !qsa('option', sel).some(function (o) { return o.value === S.event; })) sel.appendChild(el('option', { value: S.event, text: 'Activitatea #' + S.event }));
    sel.value = S.event;
  }

  /* =================== ORDERS =================== */
  function setLoading(on) {
    $('os-table').classList.toggle('is-loading', on && !firstLoad);
    $('os-orders-wrap').setAttribute('aria-busy', String(on));
  }
  function showFilterError(msg) {
    var e = $('os-f-err');
    e.textContent = msg;
    e.hidden = !msg;
    [$('os-from'), $('os-to')].forEach(function (i) { if (msg) i.setAttribute('aria-invalid', 'true'); else i.removeAttribute('aria-invalid'); });
  }
  function loadOrders(focusList) {
    var d = dates();
    syncControls();
    if (d.error) { showFilterError(d.error); return; }
    showFilterError('');
    writeUrl();
    var my = ++seq;
    setLoading(true);
    var p = filterParams(d, { page: S.page, per_page: PER_PAGE, sort_by: S.sort, sort_dir: S.dir });
    if (S.status) p.set('status', S.status);
    if (S.q) p.set('search', S.q);
    // core counts its figures on completed orders only, and splits by status only inside the status filter: with a
    // status chosen, both come from the same filters without it
    var sp = filterParams(d, { per_page: 1 });
    if (S.q) sp.set('search', S.q);
    var summary = S.status ? O.api('/organizer/orders?' + sp.toString(), { quiet: true }).then(O.metaOf, function () { return null; }) : null;
    if (summary) summary.then(function (m) {
      if (my !== seq) return;
      if (m) { renderStats(m); renderMix(m); return; }
      ['os-s-orders', 'os-s-tickets', 'os-s-net', 'os-s-gross'].forEach(function (id) { setStat(id, '—'); });
      $('os-mix').hidden = true;
    });
    O.api('/organizer/orders?' + p.toString()).then(function (r) {
      if (my !== seq) return;
      var rows = Array.isArray(r && r.data) ? r.data : [], meta = O.metaOf(r);
      var last = Math.max(1, Math.round(F.toNum(meta.last_page)) || 1);
      if (S.page > last && F.toNum(meta.total) > 0) { S.page = last; loadOrders(focusList); return; } // a page that no longer exists
      firstLoad = false;
      if (!summary) { renderStats(meta); renderMix(meta); }
      renderRows(rows, meta);
      renderPager(meta);
      if (focusList) { $('os-o-h').scrollIntoView({ block: 'start' }); $('os-o-h').focus({ preventScroll: true }); }
    }).catch(function (err) {
      if (my !== seq || (err && err.status === 401)) return;
      firstLoad = false;
      renderError();
    }).then(function () { if (my === seq) setLoading(false); });
    loadBreakdown();
  }
  function setStat(id, text) { $(id).textContent = text; }
  function renderStats(meta) {
    setStat('os-s-orders', F.num(meta.completed_orders));
    setStat('os-s-tickets', F.num(meta.total_tickets));
    setStat('os-s-net', F.money(meta.total_revenue));
    setStat('os-s-gross', F.money(meta.gross_revenue));
  }
  function renderMix(meta) {
    var b = meta.order_breakdown, box = $('os-mix'), list = $('os-mix-list');
    list.textContent = '';
    box.hidden = !b || typeof b !== 'object';
    if (box.hidden) return;
    MIX.forEach(function (m) {
      var on = S.status === m[2];
      var btn = el('button', { class: 'os-mix-b', type: 'button', 'aria-pressed': String(on), title: on ? 'Arată toate statusurile' : 'Arată doar comenzile: ' + m[1].toLowerCase() }, [el('i', { 'aria-hidden': 'true' }), m[1], el('b', { text: F.num(b[m[0]]) })]);
      btn.querySelector('i').style.setProperty('--dot', m[3]);
      btn.addEventListener('click', function () { S.status = on ? '' : m[2]; S.page = 1; loadOrders(); });
      list.appendChild(btn);
    });
  }
  function statusTag(status) {
    var t = STATUS_TAG[status] || [status || '—', 'is-muted'];
    return el('span', { class: 'org-tag ' + t[1], text: t[0] });
  }
  function renderRows(rows, meta) {
    var body = $('os-rows'), total = Math.round(F.toNum(meta.total));
    body.textContent = '';
    $('os-empty').hidden = true;
    $('os-orders-wrap').hidden = false;
    $('os-o-count').textContent = F.count(total, 'comandă', 'comenzi') + (S.status ? '' : ' · fără cele anulate sau expirate') + (total > PER_PAGE ? ' · pagina ' + F.num(S.page) + ' din ' + F.num(Math.max(1, F.toNum(meta.last_page))) : '');
    $('os-live').textContent = total ? F.count(total, 'comandă găsită', 'comenzi găsite') + '.' : 'Nicio comandă găsită.';
    if (!rows.length) {
      $('os-orders-wrap').hidden = true;
      renderEmpty(false);
      return;
    }
    rows.forEach(function (o) {
      var types = Array.isArray(o.ticket_types) ? o.ticket_types.filter(Boolean) : [];
      var seats = seatText(o.seats);
      var di = o.discount_info;
      body.appendChild(el('tr', null, [
        el('td', { class: 'c-order', 'data-label': 'Comandă' }, [
          el('span', { class: 'os-num', text: o.order_number || '—' }),
          el('span', { class: 'os-sub', text: '#' + o.id }),
          o.event ? el('span', { class: 'os-event', text: F.flat(o.event) }) : null,
        ]),
        el('td', { class: 'c-customer', 'data-label': 'Participant' }, [
          el('span', { class: 'os-name', text: F.flat(o.customer) || '—', title: F.flat(o.customer) || null }),
          el('span', { class: 'os-sub', text: maskEmail(o.customer_email) }),
          o.customer_phone ? el('span', { class: 'os-sub', text: o.customer_phone }) : null,
        ]),
        el('td', { class: 'c-tickets', 'data-label': 'Bilete' }, [ // how many, of which types, on which seats
          el('span', { class: 'os-count', text: F.count(o.tickets_count, 'bilet', 'bilete') }),
          types.length ? el('span', { class: 'os-types' }, types.map(function (t) { return el('span', { class: 'os-type', text: F.flat(t) }); })) : null,
          seats ? el('span', { class: 'os-seats', text: seats }) : null,
        ]),
        el('td', { class: 'is-num', 'data-label': 'Valoare' }, [
          el('span', { class: 'os-val', text: F.money(o.net_total != null ? o.net_total : o.total) }),
          di && F.toNum(di.discount_amount) > 0 ? el('span', { class: 'os-disc' }, [di.code ? 'Cod ' + F.flat(di.code) + ': ' : 'Redus ', el('span', { class: 'os-amt', text: '−' + F.money(di.discount_amount) })]) : null,
        ]),
        el('td', { class: 'c-status', 'data-label': 'Status' }, statusTag(o.status)),
        el('td', { 'data-label': 'Sursă' }, el('span', { class: 'os-source', text: SOURCES[o.source] || o.source || 'bilete.online' })),
        el('td', { 'data-label': 'Data' }, el('time', { class: 'os-date', datetime: o.created_at || null }, stamp(o.created_at))),
      ]));
    });
  }
  function resetFilters() {
    var keepBreakdown = S.breakdown;
    S = Object.assign({}, DEFAULTS, { breakdown: keepBreakdown });
    loadOrders();
  }
  function renderEmpty(error) {
    var box = $('os-empty'), filtered = S.event || S.status !== 'completed' || S.mode !== 'range' || S.from || S.to || S.q, cta = [];
    box.textContent = '';
    box.classList.toggle('is-error', !!error);
    box.appendChild(el('span', { class: 'org-empty-ic' }, icon(error ? 'warning-circle' : 'receipt')));
    box.appendChild(el('b', { text: error ? 'Nu am putut încărca comenzile' : 'Nu există comenzi pentru filtrele selectate' }));
    box.appendChild(el('p', { text: error ? 'Verifică conexiunea și încearcă din nou.' : filtered ? 'Schimbă perioada, statusul sau căutarea.' : 'Comenzile finalizate apar aici imediat ce clienții cumpără.' }));
    if (error) { var retry = el('button', { class: 'btn btn-primary', type: 'button', text: 'Reîncearcă' }); retry.addEventListener('click', function () { loadOrders(); }); cta.push(retry); }
    else if (filtered) { var reset = el('button', { class: 'btn btn-ghost', type: 'button', text: 'Resetează filtrele' }); reset.addEventListener('click', resetFilters); cta.push(reset); }
    if (cta.length) box.appendChild(el('div', { class: 'os-empty-cta' }, cta));
    box.hidden = false;
  }
  function renderError() {
    $('os-rows').textContent = '';
    $('os-orders-wrap').hidden = true;
    $('os-pager').hidden = true;
    $('os-o-count').textContent = '';
    if (!S.status) { // with a status the figures come from their own call
      ['os-s-orders', 'os-s-tickets', 'os-s-net', 'os-s-gross'].forEach(function (id) { setStat(id, '—'); });
      $('os-mix').hidden = true;
    }
    renderEmpty(true);
  }
  function renderPager(meta) {
    var last = Math.max(1, Math.round(F.toNum(meta.last_page)) || 1), cur = Math.min(S.page, last), nav = $('os-pager'), box = $('os-pages');
    nav.hidden = last <= 1;
    box.textContent = '';
    if (last <= 1) return;
    $('os-page-info').textContent = 'Pagina ' + F.num(cur) + ' din ' + F.num(last);
    function go(n, label, attrs) {
      var b = el('button', Object.assign({ class: 'os-pg', type: 'button' }, attrs || {}), label);
      b.addEventListener('click', function () { if (n !== S.page) { S.page = n; loadOrders(true); } });
      return b;
    }
    box.appendChild(go(cur - 1, 'Anterior', { disabled: cur <= 1, 'aria-label': 'Pagina anterioară' }));
    var pages = [1, cur - 1, cur, cur + 1, last].filter(function (n, i, a) { return n >= 1 && n <= last && a.indexOf(n) === i; }).sort(function (a, b) { return a - b; });
    pages.forEach(function (n, i) {
      if (i && n - pages[i - 1] > 1) box.appendChild(el('span', { class: 'os-gap', 'aria-hidden': 'true', text: '…' }));
      box.appendChild(go(n, F.num(n), n === cur ? { 'aria-current': 'page', 'aria-label': 'Pagina ' + n + ', pagina curentă' } : { 'aria-label': 'Pagina ' + n }));
    });
    box.appendChild(go(cur + 1, 'Următoarea', { disabled: cur >= last, 'aria-label': 'Pagina următoare' }));
  }

  /* =================== BREAKDOWN =================== */
  function loadBreakdown(force) {
    var sec = $('os-bd');
    sec.hidden = !S.breakdown;
    if (!S.breakdown || !eventsLoaded) return;
    var d = dates();
    if (d.error) return;
    var key = [d.from, d.to, events.length].join('|');
    if (!force && key === bdKey) return; // only the period changes it: status, search, activity, sort and page don't
    bdKey = key;
    var my = ++bdSeq, body = $('os-bd-body'), foot = $('os-bd-foot'), progress = $('os-bd-progress');
    $('os-bd-period').textContent = periodLabel(d) + ' · comenzi finalizate';
    body.textContent = '';
    foot.textContent = '';
    if (!events.length) { body.appendChild(el('tr', null, el('td', { class: 'os-bd-msg', colspan: '4', text: 'Nu există activități.' }))); progress.textContent = ''; return; }
    body.appendChild(el('tr', null, el('td', { class: 'os-bd-msg', colspan: '4', text: 'Se calculează…' })));
    // an activity that never sold a ticket has no sales in any period: only the others are asked, three at a time (the
    // proxy allows 300 calls a minute per visitor; a refused call waits and is asked again)
    var asked = events.filter(function (e) { return !('tickets_sold' in e || 'revenue' in e) || F.toNum(e.tickets_sold) > 0 || F.toNum(e.tickets_paid) > 0 || F.toNum(e.revenue) > 0; });
    var queue = asked.slice(), results = [], done = 0, failed = 0;
    progress.textContent = asked.length ? 'Se calculează: 0 din ' + F.num(asked.length) : '';
    function ask(ev, tries) {
      var p = filterParams(d, { event_id: ev.id, per_page: 1 });
      return O.api('/organizer/orders?' + p.toString(), { quiet: true }).catch(function (err) {
        if (!(err && err.status === 429) || tries >= 3 || my !== bdSeq) throw err;
        return new Promise(function (res) { setTimeout(res, 4000 * (tries + 1)); }).then(function () {
          if (my !== bdSeq) throw err;
          return ask(ev, tries + 1);
        });
      });
    }
    function next() {
      if (my !== bdSeq) return Promise.resolve();
      var ev = queue.shift();
      if (!ev) return Promise.resolve();
      return ask(ev, 0).then(function (r) {
        var m = O.metaOf(r);
        results.push({ ev: ev, orders: F.toNum(m.completed_orders), tickets: F.toNum(m.total_tickets), revenue: F.toNum(m.total_revenue) });
      }, function () { failed++; }).then(function () {
        done++;
        if (my === bdSeq) progress.textContent = 'Se calculează: ' + F.num(done) + ' din ' + F.num(asked.length);
        return next();
      });
    }
    Promise.all([next(), next(), next()]).then(function () {
      if (my !== bdSeq) return;
      progress.textContent = failed ? F.count(failed, 'activitate nu a putut fi calculată', 'activități nu au putut fi calculate') + '.' : '';
      body.textContent = '';
      var rows = results.filter(function (x) { return x.orders || x.tickets || x.revenue; }).sort(function (a, b) { return b.revenue - a.revenue || b.orders - a.orders; });
      if (!rows.length) { body.appendChild(el('tr', null, el('td', { class: 'os-bd-msg', colspan: '4', text: 'Nicio vânzare în perioada selectată.' }))); return; }
      var sum = { orders: 0, tickets: 0, revenue: 0 };
      rows.forEach(function (x) {
        sum.orders += x.orders; sum.tickets += x.tickets; sum.revenue += x.revenue;
        var name = F.flat(x.ev.name || x.ev.title) || 'Activitatea #' + x.ev.id;
        var btn = el('button', { class: 'os-bd-name', type: 'button', text: name, title: 'Arată comenzile pentru această activitate' });
        btn.addEventListener('click', function () { S.event = String(x.ev.id); S.page = 1; loadOrders(true); });
        body.appendChild(el('tr', null, [
          el('td', null, btn),
          el('td', { class: 'is-num', 'data-label': 'Comenzi', text: F.num(x.orders) }),
          el('td', { class: 'is-num', 'data-label': 'Bilete', text: F.num(x.tickets) }),
          el('td', { class: 'is-num', 'data-label': 'Venituri nete', text: F.money(x.revenue) }),
        ]));
      });
      foot.appendChild(el('tr', null, [
        el('td', { text: 'Total · ' + F.count(rows.length, 'activitate', 'activități') }),
        el('td', { class: 'is-num', 'data-label': 'Comenzi', text: F.num(sum.orders) }),
        el('td', { class: 'is-num', 'data-label': 'Bilete', text: F.num(sum.tickets) }),
        el('td', { class: 'is-num', 'data-label': 'Venituri nete', text: F.money(sum.revenue) }),
      ]));
    });
  }

  /* =================== EXPORT =================== */
  function records(text) { // CSV records, quotes respected; each kept as written
    var out = [], cur = '', quoted = false;
    for (var i = 0; i < text.length; i++) {
      var ch = text.charAt(i);
      if (ch === '"') { quoted = !quoted; cur += ch; }
      else if ((ch === '\n' || ch === '\r') && !quoted) {
        if (ch === '\r' && text.charAt(i + 1) === '\n') i++;
        if (cur.length) out.push(cur);
        cur = '';
      } else cur += ch;
    }
    if (cur.length) out.push(cur);
    return out;
  }
  function mergeCsv(texts) {
    var header = '', rows = [];
    texts.forEach(function (t) {
      var recs = records(String(t || '').replace(/^﻿/, ''));
      if (!recs.length) return;
      if (!header) header = recs[0];
      rows = rows.concat(recs.slice(1));
    });
    var at = function (r) { return r.replace(/^"/, '').slice(0, 16); }; // every row starts with "Y-m-d H:i", quoted
    rows.sort(function (a, b) { var x = at(a), y = at(b); return x > y ? -1 : x < y ? 1 : 0; }); // newest first; stable, so an order's tickets stay together
    return { header: header, rows: rows };
  }
  function exportCsv() {
    var btn = $('os-export');
    if (btn.getAttribute('aria-busy') === 'true') return;
    var d = dates();
    if (d.error) { showFilterError(d.error); $('os-from').focus(); return; }
    var token = typeof BileteOnlineAuth !== 'undefined' && BileteOnlineAuth.getToken ? BileteOnlineAuth.getToken() : null;
    if (!token) { O.flash('Sesiunea a expirat. Autentifică-te din nou.', true); return; }
    // core's export matches the status exactly, while its list counts paid and confirmed orders as "Finalizate"
    var statuses = S.status === 'completed' ? ['paid', 'confirmed', 'completed'] : [S.status];
    var label = btn.querySelector('[data-label]');
    btn.setAttribute('aria-busy', 'true');
    label.textContent = 'Se generează…';
    var base = (window.BILETEONLINE && window.BILETEONLINE.apiUrl) || '/api/proxy.php';
    Promise.all(statuses.map(function (st) {
      var p = new URLSearchParams();
      p.set('action', 'organizer.orders.export');
      if (S.event) p.set('event_id', S.event);
      if (st) p.set('status', st);
      if (d.from) p.set('from_date', d.from);
      if (d.to) p.set('to_date', d.to);
      return fetch(base + '?' + p.toString(), { headers: { Authorization: 'Bearer ' + token, Accept: 'text/csv' } }).then(function (res) {
        if (!res.ok) { var e = new Error('export'); e.status = res.status; throw e; }
        return res.text();
      }).then(function (t) {
        // core's file starts with its "Data,..." header; a refused token gets core's sign-in page instead (as 200,
        // the proxy follows the redirect), which must never be saved as a .csv
        var head = String(t || '').replace(/^﻿/, '').replace(/^\s+/, '');
        if (head.indexOf('Data,') === 0) return t;
        var e = new Error('export');
        e.html = head.charAt(0) === '<';
        throw e;
      });
    })).then(function (texts) {
      var csv = mergeCsv(texts);
      if (!csv.rows.length) { O.flash('Nu există comenzi de exportat pentru filtrele alese.'); return; }
      var name = ['bilete-online-vanzari', S.event ? 'activitate-' + S.event : '', S.mode === 'month' && S.month ? S.month : [d.from, d.to].filter(Boolean).join('_'), F.ymd()].filter(Boolean).join('-') + '.csv';
      var blob = new Blob(['﻿' + [csv.header].concat(csv.rows).join('\n') + '\n'], { type: 'text/csv;charset=utf-8' });
      var url = URL.createObjectURL(blob), a = el('a', { href: url, download: name, hidden: true });
      document.body.appendChild(a);
      a.click();
      setTimeout(function () { URL.revokeObjectURL(url); a.remove(); }, 1500);
      O.flash('Exportul a fost descărcat: ' + F.count(csv.rows.length, 'rând', 'rânduri') + ', câte unul pe bilet.' + (S.status ? '' : ' Include și comenzile anulate sau expirate.') + (S.q ? ' Căutarea nu se aplică exportului.' : ''));
    }).catch(function (err) {
      function failed() { O.flash('Nu am putut genera exportul. Încearcă din nou.', true); }
      function signOut() {
        O.flash('Sesiunea a expirat. Te trimitem la autentificare.', true);
        setTimeout(function () { O.api('/organizer/me').catch(function () {}); }, 1500); // a refused token ends the session there
      }
      if (err && err.status === 401) return signOut();
      if (err && err.html) { // a web page instead of the file: ask core whether the session still stands
        return O.api('/organizer/me', { quiet: true }).then(failed, function (e) { if (e && e.status === 401) signOut(); else failed(); });
      }
      failed();
    }).then(function () {
      btn.removeAttribute('aria-busy');
      label.textContent = 'Export CSV';
    });
  }

  /* =================== EVENTS =================== */
  var searchTimer = 0;
  qsa('.os-seg-b', root).forEach(function (b) {
    b.addEventListener('click', function () {
      var mode = b.getAttribute('data-mode');
      if (mode === S.mode) return;
      S.mode = mode;
      if (mode === 'month' && !S.month) S.month = currentMonth();
      S.page = 1;
      loadOrders();
    });
  });
  $('os-event').addEventListener('change', function () { S.event = this.value; S.page = 1; loadOrders(); });
  $('os-status').addEventListener('change', function () { S.status = this.value; S.page = 1; loadOrders(); });
  $('os-from').addEventListener('change', function () { S.from = this.value; S.page = 1; loadOrders(); });
  $('os-to').addEventListener('change', function () { S.to = this.value; S.page = 1; loadOrders(); });
  $('os-month').addEventListener('change', function () { S.month = this.value; S.page = 1; loadOrders(); });
  $('os-q').addEventListener('input', function () {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(function () { S.q = $('os-q').value.trim().slice(0, 100); S.page = 1; loadOrders(); }, 300);
  });
  $('os-breakdown').addEventListener('change', function () {
    S.breakdown = this.checked;
    writeUrl();
    syncControls();
    if (S.breakdown) loadBreakdown(true); else { bdSeq++; $('os-bd').hidden = true; }
  });
  $('os-reset').addEventListener('click', function () { resetFilters(); $('os-event').focus(); });
  $('os-sort-m').addEventListener('change', function () {
    var v = this.value.split(':');
    S.sort = v[0]; S.dir = v[1] === 'asc' ? 'asc' : 'desc'; S.page = 1;
    loadOrders();
  });
  qsa('#os-table th[data-sort] .os-th').forEach(function (b) {
    b.addEventListener('click', function () {
      var col = b.parentNode.getAttribute('data-sort');
      if (S.sort === col) S.dir = S.dir === 'asc' ? 'desc' : 'asc';
      else { S.sort = col; S.dir = 'desc'; }
      S.page = 1;
      loadOrders();
    });
  });
  $('os-export').addEventListener('click', exportCsv);

  /* =================== START =================== */
  O.ready.then(function (ok) {
    if (!ok) return;
    readUrl();
    syncControls();
    loadEvents().then(function () { loadBreakdown(); });
    loadOrders();
  });
})();
