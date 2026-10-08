/* viaqui.com v2: customer tickets (/cont/bilete). Loads GET /customer/tickets/all?filter=all once and filters in the
   browser (search, status, city, sort), keeping the choice in the address bar as ?q=&status=&oras=&sort=. Each ticket
   gets its QR made on the page from the ticket code (the same value the PDF's QR holds and the scanner accepts), a PDF
   download sent with the session token (the proxy checks the ticket is the customer's), an .ics file, and links to its
   order and to the contact form for name changes and refunds (neither has a self-service page). A #t-<id> hash opens
   the list on that ticket. Text from the API is always written as text. */
(function () {
  'use strict';
  var $ = function (id) { return document.getElementById(id); };
  if (!$('tk-content') || !window.BO_ACCOUNT) return;
  var account = window.BO_ACCOUNT;

  var LOC = VQ.locale === 'en' ? 'en-GB' : VQ.locale;
  var LABELS = { valid: VQ.t('valid'), paid: VQ.t('paid'), confirmed: VQ.t('confirmed'), pending: VQ.t('pending'), cancelled: VQ.t('cancelled'), refunded: VQ.t('refunded') };
  var READY = ['valid', 'paid', 'confirmed', 'pending'];
  var STATUSES = ['all', 'upcoming', 'valid', 'used', 'expired', 'action'];
  var SORTS = ['soon', 'newest', 'activity'];
  var DEFAULTS = { q: '', status: 'upcoming', city: 'all', sort: 'soon' };
  var FAR = 8.64e15;
  var num = new Intl.NumberFormat(LOC);
  var state = Object.assign({}, DEFAULTS);
  var tickets = [], loaded = false, current = null, opener = null, bulkBusy = false, sayTimer = 0, qTimer = 0;
  var today = new Date(); today.setHours(0, 0, 0, 0);

  // ---------- helpers ----------
  function el(tag, cls, text) { var n = document.createElement(tag); if (cls) n.className = cls; if (text != null) n.textContent = text; return n; }
  function ic(name) {
    var ns = 'http://www.w3.org/2000/svg', svg = document.createElementNS(ns, 'svg'), use = document.createElementNS(ns, 'use');
    svg.setAttribute('class', 'ic'); svg.setAttribute('aria-hidden', 'true'); use.setAttribute('href', '#i-' + name); svg.appendChild(use);
    return svg;
  }
  function txt(v) {
    if (v && typeof v === 'object') v = v[VQ.locale] || v.en || v.ro || v.name || Object.keys(v).map(function (k) { return v[k]; })[0];
    return v == null ? '' : String(v);
  }
  function fold(s) { return String(s || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, ''); }

  function show(id, on) { $(id).hidden = !on; }
  function parseDate(v) { var d = v ? new Date(v) : null; return d && !isNaN(d.getTime()) ? d : null; }
  function shortDate(d) { return d.toLocaleDateString(LOC, { day: 'numeric', month: 'short', year: 'numeric' }); }
  function when(t) {
    if (!t.date) return '—';
    if (t.end) return (t.date.getFullYear() !== t.end.getFullYear() ? shortDate(t.date) : t.date.toLocaleDateString(LOC, { day: 'numeric', month: 'short' })) + ' – ' + shortDate(t.end);
    return shortDate(t.date) + (t.time ? ' · ' + t.time : '');
  }
  function fileName(s) { return String(s || '').replace(/[^A-Za-z0-9_-]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 60) || 'ticket'; }
  function say(message, tone) {
    clearTimeout(sayTimer);
    [$('tk-status-line'), $('tk-d-msg')].forEach(function (line) {
      line.textContent = message;
      line.classList.toggle('is-error', tone === 'error');
    });
    if (tone !== 'error') sayTimer = setTimeout(function () { $('tk-status-line').textContent = ''; $('tk-d-msg').textContent = ''; }, 8000);
  }
  function guard() { show('tk-content', false); show('tk-guard', true); account.toLogin(); } // the message shows only while the login page loads

  // ---------- data ----------
  function norm(raw, i) {
    var ev = raw.event && typeof raw.event === 'object' ? raw.event : {};
    var status = String(raw.status || '').toLowerCase();
    var date = parseDate(ev.date || raw.event_date), end = parseDate(ev.end_date);
    var time = txt(ev.time).match(/^\d{1,2}:\d{2}/);
    var t = {
      id: txt(raw.id), seq: Number(raw.id) || i, code: txt(raw.code || raw.barcode), status: status,
      used: raw.checked_in === true || status === 'checked_in' || status === 'used',
      cancelled: status === 'cancelled' || status === 'refunded',
      title: txt(ev.name || ev.title || raw.event_title) || VQ.t('Ticket'), venue: txt(ev.venue || raw.venue_name), city: txt(ev.city || raw.event_city),
      date: date, end: end && date && end > date ? end : null, time: time ? time[0] : '', eventKey: txt(ev.id || ev.slug || ev.name),
      attendee: txt(raw.attendee_name).trim(), type: txt(raw.type || raw.ticket_type) || VQ.t('Standard'),
      seat: txt(raw.seat_label), order: txt(raw.order_number),
      protection: !!(raw.has_protection || raw.protection || raw.protected || (raw.options && raw.options.protection))
    };
    t.upcoming = typeof ev.is_upcoming === 'boolean' ? ev.is_upcoming : !!(date && (t.end || date) >= today);
    t.haystack = fold([t.title, t.venue, t.city, t.attendee, t.code, t.type, t.order, t.seat].join(' '));
    return t;
  }
  function isReady(t) { return READY.indexOf(t.status) !== -1 && t.upcoming && !t.used && !t.cancelled; }
  function needsName(t) { return t.upcoming && !t.attendee && !t.used && !t.cancelled; }
  function matches(t, status) {
    switch (status) {
      case 'upcoming': return t.upcoming && !t.cancelled;
      case 'valid': return isReady(t);
      case 'used': return t.used;
      case 'expired': return !t.upcoming && !t.used;
      case 'action': return needsName(t);
      default: return true;
    }
  }
  function time(t) { return t.date ? t.date.getTime() : FAR; }
  function bySoon(a, b) {
    if (a.upcoming !== b.upcoming) return a.upcoming ? -1 : 1;
    return a.upcoming ? time(a) - time(b) : time(b) - time(a);
  }
  function filtered() {
    var words = fold(state.q).split(/\s+/).filter(Boolean);
    var list = tickets.filter(function (t) {
      return matches(t, state.status) && (state.city === 'all' || t.city === state.city)
        && words.every(function (w) { return t.haystack.indexOf(w) !== -1; });
    });
    if (state.sort === 'activity') list.sort(function (a, b) { return a.title.localeCompare(b.title, VQ.locale) || time(a) - time(b); });
    else if (state.sort === 'newest') list.sort(function (a, b) { return b.seq - a.seq; });
    else list.sort(bySoon);
    return list;
  }

  // ---------- filters + address bar ----------
  function readUrl() {
    var p = new URLSearchParams(window.location.search);
    state.q = (p.get('q') || '').slice(0, 100);
    state.status = STATUSES.indexOf(p.get('status')) !== -1 ? p.get('status') : DEFAULTS.status;
    state.city = p.get('oras') || 'all';
    state.sort = SORTS.indexOf(p.get('sort')) !== -1 ? p.get('sort') : DEFAULTS.sort;
  }
  function writeUrl() {
    var p = new URLSearchParams(window.location.search);
    [['q', state.q.trim(), ''], ['status', state.status, DEFAULTS.status], ['oras', state.city, 'all'], ['sort', state.sort, DEFAULTS.sort]].forEach(function (x) {
      if (x[1] && x[1] !== x[2]) p.set(x[0], x[1]); else p.delete(x[0]);
    });
    var qs = p.toString();
    try { history.replaceState(history.state, '', window.location.pathname + (qs ? '?' + qs : '') + window.location.hash); } catch (e) {}
  }
  function syncControls(withQuery) {
    if (withQuery) $('tk-q').value = state.q;
    $('tk-status').value = state.status;
    var city = $('tk-city');
    city.value = state.city;
    if (loaded && city.value !== state.city) { state.city = 'all'; city.value = 'all'; }
    $('tk-sort').value = state.sort;
    [].forEach.call(document.querySelectorAll('.tk-pill'), function (b) { b.setAttribute('aria-pressed', String(b.getAttribute('data-status') === state.status)); });
  }
  function update(withQuery) { syncControls(withQuery); writeUrl(); if (loaded) render(); }
  function reset() { clearTimeout(qTimer); state = Object.assign({}, DEFAULTS); update(true); }

  $('tk-filters').addEventListener('submit', function (e) { e.preventDefault(); });
  $('tk-q').addEventListener('input', function () {
    var input = this;
    clearTimeout(qTimer);
    qTimer = setTimeout(function () { state.q = input.value.slice(0, 100); update(false); }, 150);
  });
  $('tk-status').addEventListener('change', function () { state.status = this.value; update(false); });
  $('tk-city').addEventListener('change', function () { state.city = this.value; update(false); });
  $('tk-sort').addEventListener('change', function () { state.sort = this.value; update(false); });
  [].forEach.call(document.querySelectorAll('.tk-pill'), function (b) {
    b.addEventListener('click', function () { state.status = b.getAttribute('data-status'); update(false); });
  });
  $('tk-reset').addEventListener('click', reset);
  $('tk-empty-reset').addEventListener('click', reset);

  // ---------- rendering ----------
  function buildCities() {
    var select = $('tk-city'), seen = {};
    tickets.forEach(function (t) { if (t.city) seen[t.city] = true; });
    while (select.options.length > 1) select.remove(1);
    Object.keys(seen).sort(function (a, b) { return a.localeCompare(b, VQ.locale); }).forEach(function (c) { select.appendChild(new Option(c, c)); });
  }
  function renderCounts() {
    var up = 0, events = {}, ready = 0, used = 0, action = 0;
    tickets.forEach(function (t) {
      if (t.upcoming && !t.cancelled) { up++; events[t.eventKey || t.title] = true; }
      if (isReady(t)) ready++;
      if (t.used) used++;
      if (needsName(t)) action++;
    });
    $('tk-c-upcoming').textContent = num.format(up);
    $('tk-c-activities').textContent = VQ.t('in {activities}', { activities: VQ.n(Object.keys(events).length, 'activity', 'activities') });
    $('tk-c-valid').textContent = num.format(ready);
    $('tk-c-used').textContent = num.format(used);
    $('tk-c-action').textContent = num.format(action);
    $('tk-next-n').textContent = num.format(up);
    account.setBadges({ tickets: up });
  }
  function renderNext() {
    var next = tickets.filter(function (t) { return t.upcoming && !t.cancelled && !t.used; }).sort(bySoon)[0] || null;
    var btn = $('tk-next-qr'), slot = $('tk-next-qr-slot');
    $('tk-next-t').textContent = next ? next.title : VQ.t('Coming soon');
    $('tk-next-sub').textContent = next ? [when(next), next.city].filter(function (x) { return x && x !== '—'; }).join(' · ') : VQ.t('You have no upcoming tickets');
    btn.disabled = !(next && next.code);
    if (!next || !next.code) return;
    var svg = account.qr(next.code);
    if (svg) { slot.textContent = ''; slot.appendChild(svg); }
    btn.setAttribute('aria-label', VQ.t('Large QR: {title}', { title: next.title }));
    btn.onclick = function () { openQr(next, btn); };
  }
  function badge(t) {
    if (t.used) return [VQ.t('scanned'), 'is-used'];
    if (t.cancelled) return [LABELS[t.status], 'is-bad'];
    if (!t.upcoming && READY.indexOf(t.status) !== -1) return [VQ.t('expired'), 'is-past'];
    if (t.status === 'pending') return [LABELS.pending, 'is-wait'];
    if (READY.indexOf(t.status) !== -1) return [LABELS[t.status], 'is-ok'];
    return [LABELS[t.status] || t.status || '—', ''];
  }
  /** The date as unbreakable pieces, so a narrow tile wraps as "18 sep 2026" / "· 18:30". */
  function whenNode(t) {
    if (!t.date || t.end || !t.time) return document.createTextNode(when(t));
    var frag = document.createDocumentFragment();
    frag.appendChild(el('span', 'tk-nw', shortDate(t.date)));
    frag.appendChild(document.createTextNode(' '));
    frag.appendChild(el('span', 'tk-nw', '· ' + t.time));
    return frag;
  }
  function fact(list, label, value, cls, wide) {
    var box = el('div', wide ? 'is-wide' : null), dd = el('dd', cls || null);
    if (typeof value === 'string') dd.textContent = value; else dd.appendChild(value);
    box.appendChild(el('dt', null, label));
    box.appendChild(dd);
    list.appendChild(box);
  }
  function button(cls, label, aria, onClick, icon) {
    var b = el('button', cls);
    b.type = 'button';
    if (icon) b.appendChild(ic(icon));
    b.appendChild(document.createTextNode(label));
    if (aria) b.setAttribute('aria-label', aria);
    b.addEventListener('click', onClick);
    return b;
  }
  function card(t) {
    var li = el('li', 'tk-card' + (t.cancelled || (!t.upcoming && !t.used) ? ' is-muted' : ''));
    li.id = 't-' + t.id;
    var ticket = el('article', 'tk-ticket'), main = el('div', 'tk-main'), tags = el('div', 'tk-tags'), b = badge(t);
    var orderUrl = t.order ? VQ.url('/account/orders') + '#' + encodeURIComponent(t.order) : VQ.url('/account/orders');

    tags.appendChild(el('span', 'tk-badge ' + b[1], b[0]));
    if (t.city) tags.appendChild(el('span', 'tk-badge', t.city));
    if (t.protection) tags.appendChild(el('span', 'tk-badge is-prot', VQ.t('ticket protection')));
    main.appendChild(tags);
    var h = el('h3', null, t.title);
    h.id = 'tk-t-' + t.id;
    ticket.setAttribute('aria-labelledby', h.id);
    main.appendChild(h);
    if (t.venue) main.appendChild(el('p', 'tk-venue', t.venue));

    var facts = el('dl', 'tk-facts');
    fact(facts, VQ.t('Date'), whenNode(t));
    fact(facts, VQ.t('Guest'), t.attendee || VQ.t('not filled in'), t.attendee ? '' : 'is-empty');
    fact(facts, VQ.t('Ticket type'), t.type);
    fact(facts, VQ.t('Code'), t.code || '—', 'is-code');
    if (t.seat) fact(facts, VQ.t('Seat'), t.seat, '', true);
    main.appendChild(facts);

    if (needsName(t)) {
      var notice = el('div', 'tk-notice');
      notice.appendChild(el('b', null, VQ.t('Add a guest name')));
      notice.appendChild(el('p', null, VQ.t('Fill in the name before the activity to avoid trouble at the entrance.')));
      main.appendChild(notice);
    }

    var actions = el('div', 'tk-actions'), open = el('a', 'btn btn-primary', VQ.t('Open'));
    open.href = orderUrl;
    open.setAttribute('aria-label', VQ.t('Open the order for {title}', { title: t.title }));
    actions.appendChild(open);
    if (t.id) actions.appendChild(button('btn btn-ghost', VQ.t('PDF'), VQ.t('Download PDF: {title}', { title: t.title }), function (e) { pdfOne(t, e.currentTarget); }));
    if (t.date && !t.cancelled) actions.appendChild(button('btn btn-ghost', VQ.t('Calendar'), VQ.t('Add to calendar: {title}', { title: t.title }), function () { calendarFor([t], t.title); }, 'calendar-blank'));
    if (t.code && !t.cancelled) actions.appendChild(button('btn btn-ghost', VQ.t('Large QR'), VQ.t('Large QR: {title}', { title: t.title }), function (e) { openQr(t, e.currentTarget); }, 'qr-code'));
    main.appendChild(actions);
    if (t.upcoming && !t.used && !t.cancelled) {
      var more = el('div', 'tk-more'), rename = el('a', null, VQ.t('Change the name')), refund = el('a', 'tk-refund', VQ.t('Refund'));
      rename.href = VQ.url('/contact') + '?motiv=bilete';
      refund.href = VQ.url('/contact') + '?motiv=retur';
      more.appendChild(rename);
      more.appendChild(refund);
      main.appendChild(more);
    }
    ticket.appendChild(main);

    var stub = el('div', 'tk-stub'), qrBtn = el('button', 'tk-stub-qr');
    qrBtn.type = 'button';
    var svg = t.code && !t.cancelled ? account.qr(t.code) : null;
    qrBtn.appendChild(svg || ic('qr-code'));
    if (t.used || t.cancelled) qrBtn.appendChild(el('span', 'tk-stub-state', t.used ? VQ.t('Scanned') : (t.status === 'refunded' ? VQ.t('Refunded') : VQ.t('Cancelled'))));
    if (t.code && !t.cancelled) {
      qrBtn.setAttribute('aria-label', VQ.t('Large QR: {title}', { title: t.title }));
      qrBtn.addEventListener('click', function () { openQr(t, qrBtn); });
    } else {
      qrBtn.disabled = true;
      qrBtn.setAttribute('aria-label', t.cancelled ? VQ.t('Ticket {status}, no QR', { status: LABELS[t.status] || VQ.t('cancelled') }) : VQ.t('QR not available'));
    }
    stub.appendChild(qrBtn);
    if (t.code) stub.appendChild(el('p', 'tk-stub-code', t.code.slice(-6).toUpperCase()));
    stub.appendChild(el('p', 'tk-k', VQ.t('Order')));
    if (t.order) {
      var orderLink = el('a', 'tk-order', '#' + t.order);
      orderLink.href = orderUrl;
      stub.appendChild(orderLink);
    } else {
      stub.appendChild(el('p', 'tk-order', '—'));
    }
    ticket.appendChild(stub);
    li.appendChild(ticket);
    return li;
  }
  function render() {
    var list = filtered(), ul = $('tk-list'), frag = document.createDocumentFragment();
    $('tk-count').textContent = VQ.n(list.length, 'ticket', 'tickets');
    $('tk-all-pdf').disabled = bulkBusy || !list.length;
    $('tk-all-cal').disabled = !list.length;
    list.forEach(function (t) { frag.appendChild(card(t)); });
    ul.textContent = '';
    ul.appendChild(frag);
    show('tk-skel', false);
    show('tk-error', false);
    show('tk-list', list.length > 0);
    show('tk-empty', !list.length);
    if (!list.length) {
      var none = !tickets.length;
      $('tk-empty-h').textContent = none ? VQ.t('You have no tickets yet') : VQ.t('Nothing matches');
      $('tk-empty-p').textContent = none ? VQ.t('Find things to do and book online.') : VQ.t('Change the filters or reset the search.');
      show('tk-empty-cta', none);
      show('tk-empty-reset', !none);
    }
  }
  function focusHash() {
    var m = /^#t-(\d+)$/.exec(window.location.hash);
    if (!m) return;
    var t = tickets.filter(function (x) { return x.id === m[1]; })[0];
    if (!t) return;
    if (filtered().indexOf(t) === -1) { state = Object.assign({}, DEFAULTS, { status: 'all' }); update(true); }
    var li = $('t-' + t.id);
    if (!li) return;
    li.classList.add('is-target');
    li.scrollIntoView({ block: 'center', behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth' });
  }

  // ---------- PDF ----------
  function pdf(t, btn) {
    var token = null;
    try { token = BileteOnlineAuth.getToken(); } catch (e) {}
    if (!token) return Promise.resolve('auth');
    var label = btn ? btn.textContent : '';
    if (btn) { btn.disabled = true; btn.setAttribute('aria-busy', 'true'); btn.textContent = VQ.t('Downloading…'); }
    var api = (window.BILETEONLINE && window.BILETEONLINE.apiUrl) || '/api/proxy.php';
    return fetch(api + '?action=ticket.download-pdf&id=' + encodeURIComponent(t.id), {
      headers: { Authorization: 'Bearer ' + token, Accept: 'application/pdf' }, credentials: 'same-origin', cache: 'no-store'
    }).then(function (r) {
      if (r.status === 401) return 'auth';
      if (!r.ok || (r.headers.get('Content-Type') || '').indexOf('pdf') === -1) return 'missing';
      return r.blob().then(function (blob) { account.save(blob, 'ticket-' + fileName(t.code || t.id) + '.pdf'); return 'ok'; });
    }, function () { return 'network'; }).then(function (result) {
      if (btn) { btn.disabled = false; btn.removeAttribute('aria-busy'); btn.textContent = label; }
      return result;
    });
  }
  function pdfMessage(result, t) {
    if (result === 'auth') return VQ.t('Your session has expired. Sign in again to download your tickets.');
    if (result === 'missing') return VQ.t('The PDF for “{title}” is not available right now. Try again in a few minutes or write to us.', { title: t.title });
    return VQ.t('We could not download the PDF. Check your connection and try again.');
  }
  function pdfOne(t, btn) {
    pdf(t, btn).then(function (result) {
      if (result === 'ok') say(VQ.t('The ticket for “{title}” was downloaded.', { title: t.title }), 'ok');
      else say(pdfMessage(result, t), 'error');
    });
  }
  $('tk-all-pdf').addEventListener('click', function () {
    var btn = this, list = filtered().filter(function (t) { return t.id; });
    if (bulkBusy || !list.length) return;
    var i = 0, ok = 0, failed = 0, lastError = '';
    bulkBusy = true;
    btn.disabled = true;
    btn.setAttribute('aria-busy', 'true');
    (function step() {
      if (i >= list.length) return finish();
      var t = list[i++];
      btn.textContent = VQ.t('Downloading {done}/{total}…', { done: i, total: list.length });
      pdf(t, null).then(function (result) {
        if (result === 'ok') ok++;
        else { failed++; lastError = pdfMessage(result, t); if (result === 'auth') { failed += list.length - i; i = list.length; } }
        setTimeout(step, result === 'ok' && i < list.length ? 400 : 0); // spaced so browsers accept several downloads
      });
    })();
    function finish() {
      bulkBusy = false;
      btn.removeAttribute('aria-busy');
      btn.textContent = VQ.t('Download all PDFs');
      btn.disabled = !filtered().length;
      if (!failed) say(VQ.t('Downloaded: {files}.', { files: VQ.n(ok, 'PDF', 'PDFs') }), 'ok');
      else say((ok ? VQ.t('Downloaded: {files}.', { files: VQ.n(ok, 'PDF', 'PDFs') }) + ' ' : '') + VQ.t('Could not download: {tickets}.', { tickets: VQ.n(failed, 'ticket', 'tickets') }) + ' ' + lastError, 'error');
    }
  });

  // ---------- calendar ----------
  function calendarFor(list, name) {
    var groups = {}, keys = [];
    list.forEach(function (t) {
      if (!t.date || t.cancelled) return;
      var key = (t.eventKey || t.title) + '|' + t.date.getTime();
      if (!groups[key]) { groups[key] = { t: t, names: [], count: 0 }; keys.push(key); }
      groups[key].count++;
      if (t.attendee && groups[key].names.indexOf(t.attendee) === -1) groups[key].names.push(t.attendee);
    });
    var page = window.location.origin + VQ.url('/account/tickets');
    var events = keys.map(function (key) {
      var g = groups[key], t = g.t;
      return {
        title: t.title, date: t.date, end: t.end, venue: t.venue, city: t.city,
        uid: 'bilet-' + (t.order || 'x') + '-' + (t.eventKey || t.id) + '-' + t.date.getTime(),
        note: g.names.length
          ? VQ.t('{tickets} ({names}). Your tickets are in your Viaqui account: {url}', { tickets: VQ.n(g.count, 'ticket', 'tickets'), names: g.names.join(', '), url: page })
          : VQ.t('{tickets}. Your tickets are in your Viaqui account: {url}', { tickets: VQ.n(g.count, 'ticket', 'tickets'), url: page })
      };
    });
    if (!events.length) { say(VQ.t('The chosen tickets have no date to add to a calendar.'), 'error'); return; }
    account.calendar(events, name);
    say(events.length === 1 ? VQ.t('The calendar file for “{title}” is ready.', { title: events[0].title }) : VQ.t('A calendar file with {activities} is ready.', { activities: VQ.n(events.length, 'activity', 'activities') }), 'ok');
  }
  $('tk-all-cal').addEventListener('click', function () { calendarFor(filtered(), 'my-tickets'); });

  // ---------- QR dialog ----------
  var dialog = $('tk-dialog');
  function openQr(t, from) {
    current = t;
    opener = from || null;
    $('tk-d-title').textContent = t.title;
    $('tk-d-name').textContent = t.attendee || VQ.t('No guest name yet');
    $('tk-d-code').textContent = t.code;
    $('tk-d-msg').textContent = '';
    var box = $('tk-d-qr'), svg = account.qr(t.code, VQ.t('QR code for {title}', { title: t.title }));
    box.textContent = '';
    if (svg) box.appendChild(svg); else box.textContent = t.code || VQ.t('QR not available');
    $('tk-d-cal').hidden = !t.date;
    if (typeof dialog.showModal === 'function') { if (!dialog.open) dialog.showModal(); }
    else dialog.setAttribute('open', '');
  }
  function closeQr() {
    if (typeof dialog.close === 'function') { if (dialog.open) dialog.close(); }
    else { dialog.removeAttribute('open'); dialog.dispatchEvent(new Event('close')); }
  }
  dialog.addEventListener('close', function () {
    current = null;
    if (opener && document.body.contains(opener)) opener.focus();
  });
  dialog.addEventListener('click', function (e) { if (e.target === dialog) closeQr(); }); // backdrop
  $('tk-d-close').addEventListener('click', closeQr);
  $('tk-d-pdf').addEventListener('click', function () { if (current) pdfOne(current, this); });
  $('tk-d-cal').addEventListener('click', function () { if (current) calendarFor([current], current.title); });

  // ---------- load ----------
  if (!account.isCustomer()) { guard(); return; }
  readUrl();
  syncControls(true);

  function load() {
    show('tk-error', false); show('tk-empty', false); show('tk-list', false); show('tk-skel', true);
    $('tk-all-pdf').disabled = true;
    $('tk-all-cal').disabled = true;
    BileteOnlineAPI.customer.getAllTickets('all').then(function (resp) {
      var data = resp && resp.data;
      var list = data && Array.isArray(data.tickets) ? data.tickets : (data && Array.isArray(data.items) ? data.items : (Array.isArray(data) ? data : []));
      tickets = list.filter(function (x) { return x && typeof x === 'object'; }).map(norm);
      loaded = true;
      buildCities();
      syncControls(false);
      writeUrl();
      renderCounts();
      renderNext();
      render();
      focusHash();
    }, function (err) {
      if (err && err.status === 401) { guard(); return; }
      show('tk-skel', false);
      show('tk-error', true);
    });
  }
  $('tk-retry').addEventListener('click', load);
  load();
})();
