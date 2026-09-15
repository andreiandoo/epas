/* bilete.online v2: customer orders (/cont/comenzi). Loads every page of GET /customer/orders (50 a page) and filters in
   the browser (search, status, period, sort), keeping the choice in the address bar as ?q=&status=&perioada=&sort=. The
   counters use the API totals and the points earned from GET /customer/rewards. Opening an order fetches its details
   once (tickets, history). The cost boxes come from the order's own totals: service fee = total - subtotal + discount -
   ticket protection, the way core works it out for the order details. A #<order number> hash opens that order.
   Actions: the order's tickets PDF, the tickets page filtered on the order, and the contact form for an invoice, a
   refund or a lost email (there is no self-service page for those). Text from the API is always written as text. */
(function () {
  'use strict';
  var $ = function (id) { return document.getElementById(id); };
  if (!$('od-content') || !window.BO_ACCOUNT) return;
  var account = window.BO_ACCOUNT;

  var MONTHS = ['ianuarie', 'februarie', 'martie', 'aprilie', 'mai', 'iunie', 'iulie', 'august', 'septembrie', 'octombrie', 'noiembrie', 'decembrie'];
  var EN_MONTHS = { Jan: 'ian', Feb: 'feb', Mar: 'mar', Apr: 'apr', May: 'mai', Jun: 'iun', Jul: 'iul', Aug: 'aug', Sep: 'sep', Oct: 'oct', Nov: 'noi', Dec: 'dec' };
  var STEPS = { 'Comanda plasata': 'Comanda plasată', 'Plata confirmata': 'Plata confirmată', 'Bilete emise': 'Bilete emise', 'Comanda anulata': 'Comanda anulată', 'Comanda rambursata': 'Comanda rambursată' };
  // status → [label, filter group, tag tone]
  var STATUS = {
    paid: ['plătită', 'confirmed', 'is-ok'], confirmed: ['confirmată', 'confirmed', 'is-ok'], completed: ['finalizată', 'confirmed', 'is-muted'],
    free: ['gratuită', 'confirmed', 'is-ok'], pending: ['în așteptare', 'pending', 'is-wait'], processing: ['în procesare', 'pending', 'is-wait'],
    refunded: ['retur', 'refunded', 'is-bad'], partially_refunded: ['retur parțial', 'refunded', 'is-bad'], cancelled: ['anulată', 'refunded', 'is-bad'],
    failed: ['plată eșuată', 'failed', 'is-bad'], expired: ['expirată', 'failed', 'is-muted']
  };
  var PAID = ['paid', 'confirmed', 'completed', 'free'];
  var GROUPS = ['all', 'confirmed', 'pending', 'refunded', 'failed'];
  var PERIODS = ['all', '30', '90', 'year'];
  var SORTS = ['newest', 'oldest', 'value_desc', 'value_asc'];
  var DEFAULTS = { q: '', status: 'all', period: 'all', sort: 'newest' };
  var PER_PAGE = 50, MAX_PAGES = 40;
  var num = new Intl.NumberFormat('ro-RO');
  var whole = new Intl.NumberFormat('ro-RO', { maximumFractionDigits: 0 });
  var cents = new Intl.NumberFormat('ro-RO', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  var PLACEHOLDER = 'data:image/svg+xml;utf8,' + encodeURIComponent('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 88 88"><rect width="88" height="88" fill="#E6F4EC"/><path d="M30 24h28v40l-5-3-4 3-5-3-5 3-4-3-5 3z" fill="none" stroke="#1B7F4E" stroke-width="3" stroke-linejoin="round"/><path d="M36 34h16M36 42h16M36 50h10" stroke="#1B7F4E" stroke-width="3" stroke-linecap="round"/></svg>');
  var state = Object.assign({}, DEFAULTS);
  var orders = [], byNumber = {}, stats = null, loaded = false, openKey = null, details = {}, sayTimer = 0, qTimer = 0;

  // ---------- helpers ----------
  function el(tag, cls, text) { var n = document.createElement(tag); if (cls) n.className = cls; if (text != null) n.textContent = text; return n; }
  function ic(name) {
    var ns = 'http://www.w3.org/2000/svg', svg = document.createElementNS(ns, 'svg'), use = document.createElementNS(ns, 'use');
    svg.setAttribute('class', 'ic'); svg.setAttribute('aria-hidden', 'true'); use.setAttribute('href', '#i-' + name); svg.appendChild(use);
    return svg;
  }
  function txt(v) {
    if (v && typeof v === 'object') v = v.ro || v.en || v.name || Object.keys(v).map(function (k) { return v[k]; })[0];
    return v == null ? '' : String(v);
  }
  function fold(s) { return String(s || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, ''); }
  function amount(v) { var n = Number(v); return isFinite(n) ? n : 0; }
  function plural(n, one, many) {
    n = Math.max(0, Math.floor(Number(n) || 0));
    if (n === 1) return '1 ' + one;
    var r = n % 100;
    return num.format(n) + (n && (r === 0 || r >= 20) ? ' de ' : ' ') + many;
  }
  function money(n) { n = amount(n); return (Math.round(n * 100) % 100 ? cents : whole).format(n) + ' lei'; }
  function parseDate(v) { var d = v ? new Date(v) : null; return d && !isNaN(d.getTime()) ? d : null; }
  function longDate(d) { return d ? d.getDate() + ' ' + MONTHS[d.getMonth()] + ' ' + d.getFullYear() : ''; }
  function roDate(s) { return String(s || '').replace(/\b(Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)\b/, function (m) { return EN_MONTHS[m]; }); }
  function safeUrl(u, fallback) { u = typeof u === 'string' ? u.trim() : ''; return /^(\/(?![\/\\])|https?:\/\/)/i.test(u) ? u : fallback; }
  function show(id, on) { $(id).hidden = !on; }
  function say(message, tone) {
    var line = $('od-status-line');
    clearTimeout(sayTimer);
    line.textContent = message;
    line.classList.toggle('is-error', tone === 'error');
    if (tone !== 'error') sayTimer = setTimeout(function () { line.textContent = ''; }, 8000);
  }
  function guard() { show('od-content', false); show('od-guard', true); }
  function button(cls, label, aria, onClick, icon) {
    var b = el('button', cls);
    b.type = 'button';
    b.appendChild(el('span', null, label));
    if (icon) b.appendChild(ic(icon));
    if (aria) b.setAttribute('aria-label', aria);
    b.addEventListener('click', onClick);
    return b;
  }

  // ---------- data ----------
  function norm(o, i) {
    var status = String(o.status || '').toLowerCase(), meta = STATUS[status] || [status || '—', status, ''];
    var ev = o.event && typeof o.event === 'object' ? o.event : {};
    var total = amount(o.total != null ? o.total : o.total_amount), subtotal = o.subtotal != null ? amount(o.subtotal) : total;
    var discount = amount(o.discount != null ? o.discount : o.promo_discount), insurance = amount(o.insurance_amount);
    var number = (txt(o.order_number || o.reference) || 'BO-' + txt(o.id)).replace(/^#/, '');
    var t = {
      raw: o, id: txt(o.id), number: number, status: status, label: meta[0], group: meta[1], tone: meta[2], seq: i,
      title: txt(ev.name || ev.title) || 'Comandă', city: txt(ev.city), venue: txt(ev.venue), image: ev.image || ev.featured_image || '',
      created: parseDate(o.created_at), paidAt: parseDate(o.paid_at), payment: txt(o.payment_method), promo: txt(o.promo_code),
      tickets: Math.max(0, Math.floor(amount(o.tickets_count))), total: total, subtotal: subtotal, discount: discount, insurance: insurance,
      fee: Math.max(0, Math.round((total - subtotal + discount - insurance) * 100) / 100),
      points: Math.floor(amount(o.points_earned || o.earned_points)), refundable: o.can_request_refund === true
    };
    t.paid = PAID.indexOf(status) !== -1;
    t.haystack = fold([t.number, t.title, t.city, t.venue, t.payment, t.label, t.promo].join(' '));
    return t;
  }
  function inPeriod(t) {
    if (state.period === 'all') return true;
    if (!t.created) return false;
    if (state.period === 'year') return t.created.getFullYear() === new Date().getFullYear();
    return t.created.getTime() >= Date.now() - Number(state.period) * 864e5;
  }
  function created(t) { return t.created ? t.created.getTime() : 0; }
  var SORTERS = {
    newest: function (a, b) { return created(b) - created(a) || a.seq - b.seq; },
    oldest: function (a, b) { return created(a) - created(b) || b.seq - a.seq; },
    value_desc: function (a, b) { return b.total - a.total || created(b) - created(a); },
    value_asc: function (a, b) { return a.total - b.total || created(b) - created(a); }
  };
  function filtered() {
    var words = fold(state.q).split(/\s+/).filter(Boolean);
    return orders.filter(function (t) {
      return (state.status === 'all' || t.group === state.status) && inPeriod(t)
        && words.every(function (w) { return t.haystack.indexOf(w) !== -1; });
    }).sort(SORTERS[state.sort] || SORTERS.newest);
  }

  // ---------- filters + address bar ----------
  function readUrl() {
    var p = new URLSearchParams(window.location.search);
    state.q = (p.get('q') || '').slice(0, 100);
    state.status = GROUPS.indexOf(p.get('status')) !== -1 ? p.get('status') : DEFAULTS.status;
    state.period = PERIODS.indexOf(p.get('perioada')) !== -1 ? p.get('perioada') : DEFAULTS.period;
    state.sort = SORTS.indexOf(p.get('sort')) !== -1 ? p.get('sort') : DEFAULTS.sort;
  }
  function writeUrl() {
    var p = new URLSearchParams(window.location.search);
    [['q', state.q.trim(), ''], ['status', state.status, DEFAULTS.status], ['perioada', state.period, DEFAULTS.period], ['sort', state.sort, DEFAULTS.sort]].forEach(function (x) {
      if (x[1] && x[1] !== x[2]) p.set(x[0], x[1]); else p.delete(x[0]);
    });
    var qs = p.toString();
    try { history.replaceState(history.state, '', window.location.pathname + (qs ? '?' + qs : '') + window.location.hash); } catch (e) {}
  }
  function syncControls(withQuery) {
    if (withQuery) $('od-q').value = state.q;
    $('od-status').value = state.status;
    $('od-period').value = state.period;
    $('od-sort').value = state.sort;
    [].forEach.call(document.querySelectorAll('.acc-pill[data-status]'), function (b) { b.setAttribute('aria-pressed', String(b.getAttribute('data-status') === state.status)); });
  }
  function update(withQuery) { syncControls(withQuery); writeUrl(); if (loaded) render(); }
  function reset() { clearTimeout(qTimer); state = Object.assign({}, DEFAULTS); update(true); }

  $('od-filters').addEventListener('submit', function (e) { e.preventDefault(); });
  $('od-q').addEventListener('input', function () {
    var input = this;
    clearTimeout(qTimer);
    qTimer = setTimeout(function () { state.q = input.value.slice(0, 100); update(false); }, 150);
  });
  $('od-status').addEventListener('change', function () { state.status = this.value; update(false); });
  $('od-period').addEventListener('change', function () { state.period = this.value; update(false); });
  $('od-sort').addEventListener('change', function () { state.sort = this.value; update(false); });
  [].forEach.call(document.querySelectorAll('.acc-pill[data-status]'), function (b) {
    b.addEventListener('click', function () { state.status = b.getAttribute('data-status'); update(false); });
  });
  $('od-reset').addEventListener('click', reset);
  $('od-empty-reset').addEventListener('click', reset);

  // ---------- counters ----------
  function renderCounts() {
    var totalOrders = stats && stats.total_orders != null ? Math.floor(amount(stats.total_orders)) : orders.length;
    var spent = stats && stats.total_spent != null ? amount(stats.total_spent) : orders.filter(function (t) { return t.paid; }).reduce(function (s, t) { return s + t.total; }, 0);
    $('od-k-orders').textContent = num.format(totalOrders);
    $('od-k-spent').textContent = whole.format(spent) + ' lei';
    $('od-k-refunds').textContent = num.format(orders.filter(function (t) { return t.group === 'refunded'; }).length);
    account.setBadges({ orders: totalOrders });
  }
  function loadPoints() {
    var box = $('od-k-points');
    if (!BileteOnlineAPI.customer || typeof BileteOnlineAPI.customer.getPoints !== 'function') { box.textContent = '—'; return; }
    BileteOnlineAPI.customer.getPoints().then(function (resp) {
      var p = resp && resp.data && resp.data.points;
      box.textContent = p && p.lifetime_earned != null ? num.format(Math.floor(amount(p.lifetime_earned))) : '—';
    }, function () { box.textContent = '—'; });
  }

  // ---------- orders ----------
  function img(src) {
    var i = el('img', 'od-img');
    i.alt = ''; i.loading = 'lazy'; i.decoding = 'async';
    i.addEventListener('error', function () { if (i.getAttribute('src') !== PLACEHOLDER) i.src = PLACEHOLDER; });
    i.src = safeUrl(src, PLACEHOLDER);
    return i;
  }
  function cost(list, label, value, cls) {
    var box = el('div');
    box.appendChild(el('dt', null, label));
    box.appendChild(el('dd', cls || null, value));
    list.appendChild(box);
  }
  function key(t) { return t.number.replace(/[^A-Za-z0-9_-]/g, '-'); }
  function card(t) {
    var li = el('li', 'od-card'), order = el('article', 'od-order'), body = el('div', 'od-body'), top = el('div', 'od-top');
    li.id = 'order-' + key(t);
    li.setAttribute('data-number', t.number);

    top.appendChild(img(t.image));
    var head = el('div', 'od-id'), tags = el('div', 'od-tags');
    tags.appendChild(el('span', 'acc-tag ' + t.tone, t.label));
    if (t.payment) tags.appendChild(el('span', 'acc-tag', t.payment));
    if (t.insurance > 0) tags.appendChild(el('span', 'acc-tag is-ok', 'protecție bilet'));
    head.appendChild(tags);
    var h = el('h3', 'od-num', '#' + t.number);
    h.id = 'od-h-' + key(t);
    order.setAttribute('aria-labelledby', h.id);
    head.appendChild(h);
    head.appendChild(el('p', 'od-what', t.title + (t.city ? ' · ' + t.city : '')));
    head.appendChild(el('p', 'od-meta', [longDate(t.created), t.tickets ? plural(t.tickets, 'bilet', 'bilete') : ''].filter(Boolean).join(' · ')));
    top.appendChild(head);
    var total = el('div', 'od-total');
    total.appendChild(el('p', 'acc-k', 'Total comandă'));
    total.appendChild(el('p', 'od-total-v', money(t.total)));
    if (t.points) total.appendChild(el('p', 'od-points', '+' + num.format(t.points) + ' puncte bonus'));
    top.appendChild(total);
    body.appendChild(top);

    var costs = el('dl', 'od-costs');
    cost(costs, 'Bilete', money(t.subtotal));
    cost(costs, 'Comision platformă', money(t.fee));
    cost(costs, 'Protecție bilet', money(t.insurance));
    cost(costs, 'Discount / puncte', t.discount > 0 ? '−' + money(t.discount) : money(0), t.discount > 0 ? 'is-discount' : '');
    body.appendChild(costs);

    var actions = el('div', 'od-actions'), panelId = 'od-d-' + key(t);
    var toggle = button('btn btn-primary od-toggle', 'Detalii comandă', null, function () {
      openKey = openKey === t.number ? null : t.number;
      syncOpen();
    }, 'caret-down');
    toggle.setAttribute('aria-expanded', 'false');
    toggle.setAttribute('aria-controls', panelId);
    actions.appendChild(toggle);
    var tickets = el('a', 'btn btn-ghost', 'Vezi bilete');
    tickets.href = '/cont/bilete?status=all&q=' + encodeURIComponent(t.number);
    actions.appendChild(tickets);
    if (t.paid) actions.appendChild(button('btn btn-ghost', 'PDF bilete', 'Descarcă PDF-ul biletelor din comanda ' + t.number, function (e) { pdf(t, e.currentTarget); }));
    body.appendChild(actions);

    var more = el('div', 'od-more');
    if (t.paid) { var invoice = el('a', null, 'Cere factura'); invoice.href = '/contact?motiv=comanda'; more.appendChild(invoice); }
    if (t.refundable) { var refund = el('a', 'is-refund', 'Cere retur'); refund.href = '/contact?motiv=retur'; more.appendChild(refund); }
    if (more.children.length) body.appendChild(more);

    var panel = el('div', 'od-details');
    panel.id = panelId;
    panel.hidden = true;
    order.appendChild(body);
    order.appendChild(panel);
    li.appendChild(order);
    return li;
  }
  function render() {
    var list = filtered(), ul = $('od-list'), frag = document.createDocumentFragment();
    $('od-count').textContent = plural(list.length, 'comandă', 'comenzi');
    $('od-csv').disabled = !list.length;
    list.forEach(function (t) { frag.appendChild(card(t)); });
    ul.textContent = '';
    ul.appendChild(frag);
    show('od-skel', false);
    show('od-error', false);
    show('od-list', list.length > 0);
    show('od-empty', !list.length);
    if (!list.length) {
      var none = !orders.length;
      $('od-empty-h').textContent = none ? 'Nu ai comenzi încă' : 'Nicio comandă pentru filtrele alese';
      show('od-empty-cta', none);
      show('od-empty-reset', !none);
    }
    syncOpen();
  }

  // ---------- details ----------
  function syncOpen() {
    [].forEach.call(document.querySelectorAll('#od-list .od-card'), function (li) {
      var t = byNumber[li.getAttribute('data-number')], open = !!t && openKey === t.number;
      var btn = li.querySelector('.od-toggle'), panel = li.querySelector('.od-details');
      if (open === !panel.hidden) return;
      btn.setAttribute('aria-expanded', String(open));
      btn.firstChild.textContent = open ? 'Ascunde detaliile' : 'Detalii comandă';
      panel.hidden = !open;
      if (open) fillDetails(t, panel); else panel.textContent = '';
    });
  }
  function summaryRow(list, label, value, cls) {
    var row = el('div', cls || null);
    row.appendChild(el('dt', null, label));
    row.appendChild(el('dd', null, value));
    list.appendChild(row);
  }
  function summary(t) {
    var box = el('aside', 'od-summary'), dl = el('dl');
    box.appendChild(el('p', 'acc-k', 'Sumar financiar'));
    summaryRow(dl, 'Bilete', money(t.subtotal));
    summaryRow(dl, 'Comision platformă', money(t.fee));
    summaryRow(dl, 'Protecție bilet', money(t.insurance));
    summaryRow(dl, 'Discount', t.discount > 0 ? '−' + money(t.discount) : money(0));
    summaryRow(dl, 'Total', money(t.total), 'is-total');
    box.appendChild(dl);
    box.appendChild(el('p', 'od-note', 'Comisioanele sunt afișate separat pentru transparență.'));
    var pay = [t.payment ? 'Plată: ' + t.payment : '', t.paidAt ? 'plătită pe ' + longDate(t.paidAt) : ''].filter(Boolean).join(' · ');
    if (pay) box.appendChild(el('p', 'od-pay', pay));
    if (t.promo) box.appendChild(el('p', 'od-pay', 'Cod promoțional: ' + t.promo));
    var help = el('a', 'od-help', 'Nu ai primit emailul cu biletele? Scrie-ne');
    help.href = '/contact?motiv=comanda';
    box.appendChild(help);
    return box;
  }
  function fillDetails(t, panel) {
    var inner = el('div', 'od-details-in'), main = el('div', 'od-d-main'), slot = el('div', 'od-d-slot');
    main.appendChild(el('p', 'acc-k', 'Bilete din comandă'));
    main.appendChild(slot);
    inner.appendChild(main);
    inner.appendChild(summary(t));
    panel.textContent = '';
    panel.appendChild(inner);

    var cached = details[t.number];
    if (cached && cached.order) { drawDetails(slot, cached.order); return; }
    slot.appendChild(el('p', 'od-d-state', 'Se încarcă biletele…'));
    if (cached && cached.pending) return;
    details[t.number] = { pending: true };
    BileteOnlineAPI.customer.getOrder(t.id || t.number).then(function (resp) {
      var order = resp && resp.data && (resp.data.order || resp.data);
      if (!order || typeof order !== 'object') throw { status: -1 };
      details[t.number] = { order: order };
    }).catch(function (err) {
      details[t.number] = { failed: true, status: err && err.status };
    }).then(function () {
      var live = currentSlot(t);
      if (!live) return;
      var d = details[t.number];
      if (d.order) { drawDetails(live, d.order); return; }
      if (d.status === 401) { guard(); return; }
      live.textContent = '';
      var p = el('p', 'od-d-state is-error', 'Nu am putut încărca biletele. ');
      p.appendChild(button('', 'Încearcă din nou', null, function () {
        delete details[t.number];
        var panelNow = live.closest('.od-details');
        if (panelNow) fillDetails(t, panelNow);
      }));
      live.appendChild(p);
    });
  }
  function currentSlot(t) {
    var found = null;
    [].forEach.call(document.querySelectorAll('#od-list .od-card'), function (li) {
      if (li.getAttribute('data-number') === t.number) found = li.querySelector('.od-details:not([hidden]) .od-d-slot');
    });
    return found;
  }
  function drawDetails(slot, order) {
    slot.textContent = '';
    var tickets = Array.isArray(order.tickets) ? order.tickets.filter(function (x) { return x && typeof x === 'object'; }) : [];
    if (!tickets.length) {
      slot.appendChild(el('p', 'od-d-state', 'Nu există bilete emise pentru această comandă.'));
    } else {
      var ul = el('ul', 'od-tickets');
      tickets.forEach(function (tk) {
        var li = el('li'), box = el('div'), small = el('small');
        var status = String(tk.status || '').toLowerCase(), used = tk.checked_in === true || status === 'used' || status === 'checked_in';
        var cancelled = status === 'cancelled' || status === 'refunded';
        var seat = txt(tk.seat_label || (tk.seat && tk.seat.label));
        box.appendChild(el('b', null, txt(tk.attendee_name).trim() || 'Beneficiar necompletat'));
        small.appendChild(document.createTextNode((txt(tk.type) || 'Standard') + ' · '));
        small.appendChild(el('code', null, txt(tk.barcode || tk.code) || '—'));
        if (seat) small.appendChild(document.createTextNode(' · Loc ' + seat));
        box.appendChild(small);
        li.appendChild(box);
        var side = el('div', 'od-t-side');
        side.appendChild(el('span', 'acc-tag ' + (used ? 'is-muted' : cancelled ? 'is-bad' : 'is-ok'), used ? 'scanat' : cancelled ? (status === 'refunded' ? 'rambursat' : 'anulat') : 'valid'));
        li.appendChild(side);
        if (tk.id != null && !cancelled) {
          var qr = el('a', null, 'Vezi QR');
          qr.href = '/cont/bilete#t-' + encodeURIComponent(txt(tk.id));
          side.appendChild(qr);
        }
        ul.appendChild(li);
      });
      slot.appendChild(ul);
    }
    var steps = Array.isArray(order.timeline) ? order.timeline.filter(function (x) { return x && typeof x === 'object'; }) : [];
    if (steps.length) {
      slot.appendChild(el('p', 'acc-k od-sub', 'Istoric'));
      var ol = el('ol', 'od-timeline');
      steps.forEach(function (s) {
        var li = el('li', /cancel|refund/.test(String(s.status || '')) ? 'is-bad' : null);
        li.appendChild(el('b', null, STEPS[txt(s.title)] || txt(s.title)));
        if (s.date) li.appendChild(el('time', null, roDate(txt(s.date))));
        ol.appendChild(li);
      });
      slot.appendChild(ol);
    }
  }
  function focusHash() {
    var wanted = decodeURIComponent((window.location.hash || '').slice(1)).replace(/^order-/, '').trim().toLowerCase();
    if (!wanted || wanted === 'comenzi') return;
    var t = orders.filter(function (o) { return o.number.toLowerCase() === wanted; })[0];
    if (!t) return;
    if (filtered().indexOf(t) === -1) { state = Object.assign({}, DEFAULTS); syncControls(true); writeUrl(); }
    openKey = t.number;
    render();
    var li = $('order-' + key(t));
    if (!li) return;
    li.classList.add('is-target');
    li.scrollIntoView({ block: 'start', behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth' });
  }
  window.addEventListener('hashchange', function () { if (loaded) focusHash(); });

  // ---------- downloads ----------
  function pdf(t, btn) {
    var token = null;
    try { token = BileteOnlineAuth.getToken(); } catch (e) {}
    var label = btn.firstChild.textContent;
    btn.disabled = true;
    btn.setAttribute('aria-busy', 'true');
    btn.firstChild.textContent = 'Se descarcă…';
    var api = (window.BILETEONLINE && window.BILETEONLINE.apiUrl) || '/api/proxy.php';
    var headers = { Accept: 'application/pdf' };
    if (token) headers.Authorization = 'Bearer ' + token;
    fetch(api + '?action=order.download-tickets-pdf&order=' + encodeURIComponent(t.number), { headers: headers, credentials: 'same-origin', cache: 'no-store' })
      .then(function (r) {
        if (!r.ok || (r.headers.get('Content-Type') || '').indexOf('pdf') === -1) return 'missing';
        return r.blob().then(function (blob) { account.save(blob, 'bilete-' + key(t) + '.pdf'); return 'ok'; });
      }, function () { return 'network'; })
      .then(function (result) {
        btn.disabled = false;
        btn.removeAttribute('aria-busy');
        btn.firstChild.textContent = label;
        if (result === 'ok') say('Am descărcat biletele din comanda #' + t.number + '.', 'ok');
        else if (result === 'missing') say('PDF-ul pentru comanda #' + t.number + ' nu este disponibil acum. Încearcă din nou în câteva minute sau scrie-ne.', 'error');
        else say('Nu am putut descărca PDF-ul. Verifică conexiunea și încearcă din nou.', 'error');
      });
  }
  function csvCell(value) {
    var s = String(value == null ? '' : value);
    if (/^[=+\-@\t\r]/.test(s)) s = "'" + s; // a spreadsheet must not run text from an activity title as a formula
    return '"' + s.replace(/"/g, '""') + '"';
  }
  function exportCsv() {
    var list = filtered();
    if (!list.length) return 0;
    var rows = [['Comandă', 'Data', 'Activitate', 'Oraș', 'Plată', 'Status', 'Nr. bilete', 'Bilete', 'Comision platformă', 'Protecție bilet', 'Discount', 'Total']];
    list.forEach(function (t) {
      rows.push(['#' + t.number, longDate(t.created), t.title, t.city, t.payment, t.label, t.tickets, money(t.subtotal), money(t.fee), money(t.insurance), t.discount > 0 ? '−' + money(t.discount) : money(0), money(t.total)]);
    });
    var csv = rows.map(function (r) { return r.map(csvCell).join(';'); }).join('\r\n');
    var d = new Date(), stamp = d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
    account.save(new Blob(['﻿' + csv + '\r\n'], { type: 'text/csv;charset=utf-8' }), 'comenzi-bilete-online-' + stamp + '.csv');
    return list.length;
  }
  $('od-csv').addEventListener('click', function () {
    var n = exportCsv();
    if (n) say('Am descărcat ' + plural(n, 'comandă', 'comenzi') + ' în CSV.', 'ok');
  });
  $('od-history').addEventListener('click', function () {
    var btn = this;
    btn.disabled = true;
    btn.setAttribute('aria-busy', 'true');
    btn.textContent = 'Se trimite cererea…';
    BileteOnlineAPI.post('/customer/gdpr/export', {}).then(function (resp) {
      if (!(resp && resp.success)) throw { status: -1 };
      say(txt(resp.message) || 'Exportul a fost programat. Vei primi un email când e gata.', 'ok');
    }).catch(function (err) {
      if (err && err.status === 401) { say('Sesiunea a expirat. Intră din nou în cont ca să ceri istoricul.', 'error'); return; }
      var n = exportCsv();
      say(n ? 'Nu am putut porni exportul complet acum, așa că am descărcat în CSV ' + (n === 1 ? 'comanda afișată' : 'cele ' + plural(n, 'comandă afișată', 'comenzi afișate')) + '.' : 'Nu am putut porni exportul acum. Încearcă din nou în câteva minute.', 'error');
    }).then(function () {
      btn.disabled = false;
      btn.removeAttribute('aria-busy');
      btn.textContent = 'Descarcă istoric';
    });
  });

  // ---------- load ----------
  if (!account.isCustomer()) { guard(); return; }
  readUrl();
  syncControls(true);

  function finish(list, partial) {
    orders = list.map(norm);
    byNumber = {};
    orders.forEach(function (t) { byNumber[t.number] = t; });
    loaded = true;
    $('od-history').disabled = false;
    renderCounts();
    render();
    focusHash();
    if (partial) say('Am încărcat doar o parte din comenzi. Reîncarcă pagina mai târziu pentru restul.', 'error');
  }
  function load() {
    show('od-error', false); show('od-empty', false); show('od-list', false); show('od-skel', true);
    $('od-csv').disabled = true;
    var all = [], page = 1;
    (function next() {
      BileteOnlineAPI.customer.getOrders({ per_page: PER_PAGE, page: page }).then(function (resp) {
        var data = resp && resp.data;
        var list = Array.isArray(data) ? data : (data && (data.orders || data.items)) || [];
        all = all.concat(list.filter(function (x) { return x && typeof x === 'object'; }));
        if (resp && resp.stats) stats = resp.stats;
        var last = resp && resp.meta ? Math.floor(amount(resp.meta.last_page)) : 1;
        if (page < last && page < MAX_PAGES) { page++; next(); return; }
        finish(all, false);
      }, function (err) {
        if (err && err.status === 401) { guard(); return; }
        if (all.length) { finish(all, true); return; }
        show('od-skel', false);
        show('od-error', true);
      });
    })();
  }
  $('od-retry').addEventListener('click', load);
  load();
  loadPoints();
})();
