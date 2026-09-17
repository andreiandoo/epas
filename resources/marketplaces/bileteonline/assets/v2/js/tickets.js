/* bilete.online v2: customer tickets (/cont/bilete). Loads GET /customer/tickets/all?filter=all once and filters in the
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

  var MONTHS = ['ian', 'feb', 'mar', 'apr', 'mai', 'iun', 'iul', 'aug', 'sep', 'oct', 'noi', 'dec'];
  var LABELS = { valid: 'valid', paid: 'plătit', confirmed: 'confirmat', pending: 'în așteptare', cancelled: 'anulat', refunded: 'rambursat' };
  var READY = ['valid', 'paid', 'confirmed', 'pending'];
  var STATUSES = ['all', 'upcoming', 'valid', 'used', 'expired', 'action'];
  var SORTS = ['soon', 'newest', 'activity'];
  var DEFAULTS = { q: '', status: 'upcoming', city: 'all', sort: 'soon' };
  var FAR = 8.64e15;
  var num = new Intl.NumberFormat('ro-RO');
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
    if (v && typeof v === 'object') v = v.ro || v.en || v.name || Object.keys(v).map(function (k) { return v[k]; })[0];
    return v == null ? '' : String(v);
  }
  function fold(s) { return String(s || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, ''); }
  function plural(n, one, many) {
    n = Math.max(0, Math.floor(Number(n) || 0));
    if (n === 1) return '1 ' + one;
    var r = n % 100;
    return num.format(n) + (n && (r === 0 || r >= 20) ? ' de ' : ' ') + many;
  }
  function show(id, on) { $(id).hidden = !on; }
  function parseDate(v) { var d = v ? new Date(v) : null; return d && !isNaN(d.getTime()) ? d : null; }
  function shortDate(d) { return d.getDate() + ' ' + MONTHS[d.getMonth()] + ' ' + d.getFullYear(); }
  function when(t) {
    if (!t.date) return '—';
    if (t.end) return t.date.getDate() + ' ' + MONTHS[t.date.getMonth()] + (t.date.getFullYear() !== t.end.getFullYear() ? ' ' + t.date.getFullYear() : '') + ' – ' + shortDate(t.end);
    return shortDate(t.date) + (t.time ? ' · ' + t.time : '');
  }
  function fileName(s) { return String(s || '').replace(/[^A-Za-z0-9_-]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 60) || 'bilet'; }
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
      title: txt(ev.name || ev.title || raw.event_title) || 'Bilet', venue: txt(ev.venue || raw.venue_name), city: txt(ev.city || raw.event_city),
      date: date, end: end && date && end > date ? end : null, time: time ? time[0] : '', eventKey: txt(ev.id || ev.slug || ev.name),
      attendee: txt(raw.attendee_name).trim(), type: txt(raw.type || raw.ticket_type) || 'Standard',
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
    if (state.sort === 'activity') list.sort(function (a, b) { return a.title.localeCompare(b.title, 'ro') || time(a) - time(b); });
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
    Object.keys(seen).sort(function (a, b) { return a.localeCompare(b, 'ro'); }).forEach(function (c) { select.appendChild(new Option(c, c)); });
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
    $('tk-c-activities').textContent = 'în ' + plural(Object.keys(events).length, 'activitate', 'activități');
    $('tk-c-valid').textContent = num.format(ready);
    $('tk-c-used').textContent = num.format(used);
    $('tk-c-action').textContent = num.format(action);
    $('tk-next-n').textContent = num.format(up);
    account.setBadges({ tickets: up });
  }
  function renderNext() {
    var next = tickets.filter(function (t) { return t.upcoming && !t.cancelled && !t.used; }).sort(bySoon)[0] || null;
    var btn = $('tk-next-qr'), slot = $('tk-next-qr-slot');
    $('tk-next-t').textContent = next ? next.title : 'În curând';
    $('tk-next-sub').textContent = next ? [when(next), next.city].filter(function (x) { return x && x !== '—'; }).join(' · ') : 'Nu ai bilete viitoare';
    btn.disabled = !(next && next.code);
    if (!next || !next.code) return;
    var svg = account.qr(next.code);
    if (svg) { slot.textContent = ''; slot.appendChild(svg); }
    btn.setAttribute('aria-label', 'QR mare: ' + next.title);
    btn.onclick = function () { openQr(next, btn); };
  }
  function badge(t) {
    if (t.used) return ['scanat', 'is-used'];
    if (t.cancelled) return [LABELS[t.status], 'is-bad'];
    if (!t.upcoming && READY.indexOf(t.status) !== -1) return ['expirat', 'is-past'];
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
    var orderUrl = t.order ? '/cont/comenzi#' + encodeURIComponent(t.order) : '/cont/comenzi';

    tags.appendChild(el('span', 'tk-badge ' + b[1], b[0]));
    if (t.city) tags.appendChild(el('span', 'tk-badge', t.city));
    if (t.protection) tags.appendChild(el('span', 'tk-badge is-prot', 'protecție bilet'));
    main.appendChild(tags);
    var h = el('h3', null, t.title);
    h.id = 'tk-t-' + t.id;
    ticket.setAttribute('aria-labelledby', h.id);
    main.appendChild(h);
    if (t.venue) main.appendChild(el('p', 'tk-venue', t.venue));

    var facts = el('dl', 'tk-facts');
    fact(facts, 'Data', whenNode(t));
    fact(facts, 'Beneficiar', t.attendee || 'necompletat', t.attendee ? '' : 'is-empty');
    fact(facts, 'Tip bilet', t.type);
    fact(facts, 'Cod', t.code || '—', 'is-code');
    if (t.seat) fact(facts, 'Loc', t.seat, '', true);
    main.appendChild(facts);

    if (needsName(t)) {
      var notice = el('div', 'tk-notice');
      notice.appendChild(el('b', null, 'Adaugă beneficiar'));
      notice.appendChild(el('p', null, 'Completează numele înainte de eveniment pentru a evita probleme la intrare.'));
      main.appendChild(notice);
    }

    var actions = el('div', 'tk-actions'), open = el('a', 'btn btn-primary', 'Deschide');
    open.href = orderUrl;
    open.setAttribute('aria-label', 'Deschide comanda pentru ' + t.title);
    actions.appendChild(open);
    if (t.id) actions.appendChild(button('btn btn-ghost', 'PDF', 'Descarcă PDF: ' + t.title, function (e) { pdfOne(t, e.currentTarget); }));
    if (t.date && !t.cancelled) actions.appendChild(button('btn btn-ghost', 'Calendar', 'Adaugă în calendar: ' + t.title, function () { calendarFor([t], t.title); }, 'calendar-blank'));
    if (t.code && !t.cancelled) actions.appendChild(button('btn btn-ghost', 'QR mare', 'QR mare: ' + t.title, function (e) { openQr(t, e.currentTarget); }, 'qr-code'));
    main.appendChild(actions);
    if (t.upcoming && !t.used && !t.cancelled) {
      var more = el('div', 'tk-more'), rename = el('a', null, 'Schimbă numele'), refund = el('a', 'tk-refund', 'Retur');
      rename.href = '/contact?motiv=bilete';
      refund.href = '/contact?motiv=retur';
      more.appendChild(rename);
      more.appendChild(refund);
      main.appendChild(more);
    }
    ticket.appendChild(main);

    var stub = el('div', 'tk-stub'), qrBtn = el('button', 'tk-stub-qr');
    qrBtn.type = 'button';
    var svg = t.code && !t.cancelled ? account.qr(t.code) : null;
    qrBtn.appendChild(svg || ic('qr-code'));
    if (t.used || t.cancelled) qrBtn.appendChild(el('span', 'tk-stub-state', t.used ? 'Scanat' : (t.status === 'refunded' ? 'Rambursat' : 'Anulat')));
    if (t.code && !t.cancelled) {
      qrBtn.setAttribute('aria-label', 'QR mare: ' + t.title);
      qrBtn.addEventListener('click', function () { openQr(t, qrBtn); });
    } else {
      qrBtn.disabled = true;
      qrBtn.setAttribute('aria-label', t.cancelled ? 'Bilet ' + (LABELS[t.status] || 'anulat') + ', fără QR' : 'QR indisponibil');
    }
    stub.appendChild(qrBtn);
    if (t.code) stub.appendChild(el('p', 'tk-stub-code', t.code.slice(-6).toUpperCase()));
    stub.appendChild(el('p', 'tk-k', 'Comandă'));
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
    $('tk-count').textContent = plural(list.length, 'bilet', 'bilete');
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
      $('tk-empty-h').textContent = none ? 'Nu ai bilete încă' : 'Nicio potrivire';
      $('tk-empty-p').textContent = none ? 'Descoperă activități și rezervă online.' : 'Schimbă filtrele sau resetează căutarea.';
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
    if (btn) { btn.disabled = true; btn.setAttribute('aria-busy', 'true'); btn.textContent = 'Se descarcă…'; }
    var api = (window.BILETEONLINE && window.BILETEONLINE.apiUrl) || '/api/proxy.php';
    return fetch(api + '?action=ticket.download-pdf&id=' + encodeURIComponent(t.id), {
      headers: { Authorization: 'Bearer ' + token, Accept: 'application/pdf' }, credentials: 'same-origin', cache: 'no-store'
    }).then(function (r) {
      if (r.status === 401) return 'auth';
      if (!r.ok || (r.headers.get('Content-Type') || '').indexOf('pdf') === -1) return 'missing';
      return r.blob().then(function (blob) { account.save(blob, 'bilet-' + fileName(t.code || t.id) + '.pdf'); return 'ok'; });
    }, function () { return 'network'; }).then(function (result) {
      if (btn) { btn.disabled = false; btn.removeAttribute('aria-busy'); btn.textContent = label; }
      return result;
    });
  }
  function pdfMessage(result, t) {
    if (result === 'auth') return 'Sesiunea a expirat. Intră din nou în cont ca să descarci biletele.';
    if (result === 'missing') return 'PDF-ul pentru „' + t.title + '” nu este disponibil acum. Încearcă din nou în câteva minute sau scrie-ne.';
    return 'Nu am putut descărca PDF-ul. Verifică conexiunea și încearcă din nou.';
  }
  function pdfOne(t, btn) {
    pdf(t, btn).then(function (result) {
      if (result === 'ok') say('Am descărcat biletul pentru „' + t.title + '”.', 'ok');
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
      btn.textContent = 'Se descarcă ' + i + '/' + list.length + '…';
      pdf(t, null).then(function (result) {
        if (result === 'ok') ok++;
        else { failed++; lastError = pdfMessage(result, t); if (result === 'auth') { failed += list.length - i; i = list.length; } }
        setTimeout(step, result === 'ok' && i < list.length ? 400 : 0); // spaced so browsers accept several downloads
      });
    })();
    function finish() {
      bulkBusy = false;
      btn.removeAttribute('aria-busy');
      btn.textContent = 'Descarcă toate PDF';
      btn.disabled = !filtered().length;
      if (!failed) say('Am descărcat ' + plural(ok, 'PDF', 'PDF-uri') + '.', 'ok');
      else say((ok ? 'Am descărcat ' + plural(ok, 'PDF', 'PDF-uri') + '. ' : '') + plural(failed, 'bilet nu a putut fi descărcat.', 'bilete nu au putut fi descărcate.') + ' ' + lastError, 'error');
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
    var page = window.location.origin + '/cont/bilete';
    var events = keys.map(function (key) {
      var g = groups[key], t = g.t;
      return {
        title: t.title, date: t.date, end: t.end, venue: t.venue, city: t.city,
        uid: 'bilet-' + (t.order || 'x') + '-' + (t.eventKey || t.id) + '-' + t.date.getTime(),
        note: plural(g.count, 'bilet', 'bilete') + (g.names.length ? ' (' + g.names.join(', ') + ')' : '') + '. Biletele tale sunt în contul bilete.online: ' + page
      };
    });
    if (!events.length) { say('Biletele alese nu au o dată de adăugat în calendar.', 'error'); return; }
    account.calendar(events, name);
    say(events.length === 1 ? 'Am pregătit fișierul de calendar pentru „' + events[0].title + '”.' : 'Am pregătit un fișier de calendar cu ' + plural(events.length, 'activitate', 'activități') + '.', 'ok');
  }
  $('tk-all-cal').addEventListener('click', function () { calendarFor(filtered(), 'biletele-mele'); });

  // ---------- QR dialog ----------
  var dialog = $('tk-dialog');
  function openQr(t, from) {
    current = t;
    opener = from || null;
    $('tk-d-title').textContent = t.title;
    $('tk-d-name').textContent = t.attendee || 'Beneficiar necompletat';
    $('tk-d-code').textContent = t.code;
    $('tk-d-msg').textContent = '';
    var box = $('tk-d-qr'), svg = account.qr(t.code, 'Cod QR pentru ' + t.title);
    box.textContent = '';
    if (svg) box.appendChild(svg); else box.textContent = t.code || 'QR indisponibil';
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
