/* bilete.online v2: share link view (/view/{code}). Public page: reads /share/{code}/data through the proxy
   (share-link.data), asks for the password of protected links, refreshes every 30 s while the tab is in view (with the
   X-Auto-Refresh header, so refreshes don't count as new visits) and slows down when the server asks for it. Cards are
   built once and updated in place, so an open activity, the chosen tab and a half-typed search survive every refresh.
   Text from the API is always written as text. */
(function () {
  'use strict';
  var root = document.getElementById('main'), code = root ? root.getAttribute('data-code') : '';
  if (!root || !/^[A-Za-z0-9]{6,20}$/.test(code || '')) return;
  var $ = function (id) { return document.getElementById(id); };
  var SVG = 'http://www.w3.org/2000/svg', TZ = 'Europe/Bucharest', REFRESH = 30000, MAX_DELAY = 300000;
  var nf = new Intl.NumberFormat('ro-RO');
  var money = new Intl.NumberFormat('ro-RO', { maximumFractionDigits: 2 });
  var state = { password: null, data: null, timer: 0, delay: REFRESH, loading: false, stopped: false, last: null };
  var cards = {};

  /* =================== HELPERS =================== */
  function el(tag, attrs, kids) {
    var n = document.createElement(tag);
    Object.keys(attrs || {}).forEach(function (k) {
      var v = attrs[k];
      if (v == null || v === false) return;
      if (k === 'class') n.className = v;
      else if (k === 'text') n.textContent = v;
      else n.setAttribute(k, v === true ? '' : String(v));
    });
    [].concat(kids == null ? [] : kids).forEach(function (c) {
      if (c == null || c === false || c === '') return;
      n.appendChild(typeof c === 'string' || typeof c === 'number' ? document.createTextNode(String(c)) : c);
    });
    return n;
  }
  function icon(name) {
    var s = document.createElementNS(SVG, 'svg'), u = document.createElementNS(SVG, 'use');
    s.setAttribute('class', 'ic');
    s.setAttribute('aria-hidden', 'true');
    s.setAttribute('focusable', 'false');
    u.setAttribute('href', '#i-' + name);
    s.appendChild(u);
    return s;
  }
  function num(v) { var n = typeof v === 'number' ? v : parseFloat(v); return isFinite(n) ? n : 0; }
  function count(n, one, many) { n = Math.round(num(n)); if (n === 1) return '1 ' + one; var r = Math.abs(n) % 100; return nf.format(n) + (n !== 0 && (r === 0 || r >= 20) ? ' de ' : ' ') + many; }
  function lei(v, currency) { var c = String(currency || 'RON').toUpperCase(); return money.format(num(v)) + ' ' + (c === 'RON' ? 'lei' : c); }
  function flat(v) { if (v && typeof v === 'object') return String(v.ro || v.en || ''); return v == null ? '' : String(v); }
  function norm(s) { return String(s == null ? '' : s).normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase(); }
  function day(v) {
    var m = /^(\d{4}-\d{2}-\d{2})/.exec(String(v || ''));
    if (!m) return '';
    var d = new Date(m[1] + 'T12:00:00');
    return isNaN(d.getTime()) ? '' : new Intl.DateTimeFormat('ro-RO', { timeZone: TZ, weekday: 'short', day: 'numeric', month: 'long', year: 'numeric' }).format(d);
  }
  function hhmm(v) { var m = /(\d{2}):(\d{2})/.exec(String(v || '')); return m && m[1] + m[2] !== '0000' ? m[1] + ':' + m[2] : ''; }
  function clock(d) { return new Intl.DateTimeFormat('ro-RO', { timeZone: TZ, hour: '2-digit', minute: '2-digit', second: '2-digit' }).format(d); }
  function capacity(total) { total = Math.round(num(total)); return total < 0 ? null : total; } // null: unlimited
  function pct(sold, total) { return total > 0 ? Math.min(100, sold / total * 100) : 0; }
  function apiUrl() { return (window.BILETEONLINE && window.BILETEONLINE.apiUrl) || '/api/proxy.php'; }

  /* =================== STATES =================== */
  function only(id) { ['sv-loading', 'sv-pass', 'sv-error', 'sv-content'].forEach(function (k) { $(k).hidden = k !== id; }); }
  function showPassword(message) {
    only('sv-pass');
    $('sv-live').hidden = true;
    var err = $('sv-pw-err'), input = $('sv-pw'), btn = $('sv-pw-go');
    err.textContent = message || '';
    err.hidden = !message;
    if (message) input.setAttribute('aria-invalid', 'true'); else input.removeAttribute('aria-invalid');
    btn.removeAttribute('aria-busy');
    btn.querySelector('[data-label]').textContent = 'Vezi datele';
    input.focus();
    if (message) input.select();
  }
  function showError(title, text, retry) {
    only('sv-error');
    $('sv-live').hidden = true;
    var box = $('sv-error');
    box.textContent = '';
    box.className = 'sv-state is-bad';
    box.appendChild(el('span', { class: 'sv-state-ic' }, icon('x')));
    box.appendChild(el('h2', { text: title }));
    box.appendChild(el('p', { text: text }));
    if (retry) {
      var b = el('button', { class: 'btn btn-primary', type: 'button', text: 'Reîncearcă acum' });
      b.addEventListener('click', function () { load(false); });
      box.appendChild(b);
    } else box.appendChild(el('a', { class: 'btn btn-ghost', href: '/' }, [icon('arrow-left'), 'Înapoi la bilete.online']));
  }
  function setLive() {
    var live = $('sv-live'), t = $('sv-live-t'), slow = state.delay > REFRESH, paused = document.hidden;
    live.hidden = !state.data;
    live.classList.toggle('is-slow', slow);
    live.classList.toggle('is-paused', paused);
    t.textContent = 'Actualizat la ' + (state.last ? clock(state.last) : '—') + (paused ? ' · pe pauză cât pagina nu e vizibilă' : slow ? ' · următoarea actualizare în ' + Math.round(state.delay / 1000) + ' s' : ' · se actualizează la fiecare 30 de secunde');
  }

  /* =================== LOAD =================== */
  function schedule() {
    clearTimeout(state.timer);
    if (state.stopped || document.hidden) { setLive(); return; }
    state.timer = setTimeout(function () { load(true); }, state.delay);
    setLive();
  }
  function stop() { state.stopped = true; clearTimeout(state.timer); }
  function slowDown(reason) {
    state.delay = Math.min(MAX_DELAY, state.delay * 2);
    if (!state.data) { showError('Nu am putut încărca datele', reason + ' Reîncercăm automat în ' + Math.round(state.delay / 1000) + ' de secunde.', true); schedule(); return; }
    var note = $('sv-note');
    note.textContent = reason + ' Cifrele de mai jos sunt de la ' + clock(state.last) + '; reîncercăm în ' + Math.round(state.delay / 1000) + ' de secunde.';
    note.hidden = false;
    schedule();
  }
  function load(auto) {
    if (state.loading || state.stopped) return;
    state.loading = true;
    clearTimeout(state.timer);
    var headers = { Accept: 'application/json' }, opts = { headers: headers, credentials: 'same-origin', cache: 'no-store' };
    if (auto) headers['X-Auto-Refresh'] = '1';
    if (state.password) { opts.method = 'POST'; headers['Content-Type'] = 'application/json'; opts.body = JSON.stringify({ password: state.password }); }
    fetch(apiUrl() + '?action=share-link.data&code=' + encodeURIComponent(code), opts).then(function (res) {
      return res.text().then(function (t) { var body = null; try { body = JSON.parse(t); } catch (e) { body = null; } return { status: res.status, ok: res.ok, body: body }; });
    }).then(function (r) {
      state.loading = false;
      var b = r.body || {}, err = String(b.error || '');
      if (r.ok && b.success && b.data && typeof b.data === 'object') {
        state.data = b.data;
        state.last = new Date();
        state.delay = REFRESH;
        $('sv-note').hidden = true;
        render();
        schedule();
        return;
      }
      if (r.status === 401 && err === 'password_required') { stop(); state.password = null; showPassword(''); state.stopped = false; return; }
      if (r.status === 403 && err === 'invalid_password') { stop(); state.password = null; showPassword('Parola nu este corectă. Verifică-o și încearcă din nou.'); state.stopped = false; return; }
      if (r.status === 429 && err === 'too_many_attempts') { stop(); state.password = null; showPassword('Prea multe încercări greșite. Poți încerca din nou peste 10 minute.'); state.stopped = false; return; }
      if (r.status === 410) { stop(); showError('Linkul a fost oprit', 'Organizatorul a oprit accesul la acest link. Dacă ai nevoie de date, cere-i un link nou.'); return; }
      if (r.status === 404 || r.status === 400) { stop(); showError('Linkul nu există', 'Verifică linkul primit sau cere-i organizatorului unul nou.'); return; }
      if (r.status === 429) { slowDown('Prea multe cereri într-un timp scurt.'); return; }
      slowDown('Nu am putut actualiza datele.');
    }, function () {
      state.loading = false;
      slowDown('Nu am putut ajunge la server. Verifică conexiunea.');
    });
  }
  document.addEventListener('visibilitychange', function () {
    if (document.hidden) { clearTimeout(state.timer); setLive(); return; }
    if (state.data && !state.stopped) load(true);
  });

  /* =================== PASSWORD =================== */
  $('sv-pass').addEventListener('submit', function (e) {
    e.preventDefault();
    var input = $('sv-pw'), btn = $('sv-pw-go'), pw = input.value;
    if (btn.getAttribute('aria-busy') === 'true') return;
    if (!pw.trim()) { $('sv-pw-err').textContent = 'Scrie parola primită de la organizator.'; $('sv-pw-err').hidden = false; input.setAttribute('aria-invalid', 'true'); input.focus(); return; }
    state.password = pw.trim();
    state.stopped = false;
    btn.setAttribute('aria-busy', 'true');
    btn.querySelector('[data-label]').textContent = 'Se verifică…';
    load(false);
  });
  $('sv-eye').addEventListener('click', function () {
    var input = $('sv-pw'), show = input.type === 'password';
    input.type = show ? 'text' : 'password';
    this.textContent = show ? 'Ascunde' : 'Arată';
    this.setAttribute('aria-pressed', String(show));
  });

  /* =================== RENDER =================== */
  function stat(k, v, small, p) {
    return el('article', { class: 'sv-stat' }, [el('p', { class: 'sv-stat-k', text: k }), el('p', { class: 'sv-stat-v' }, [v, small ? el('small', { text: small }) : null]), el('p', { class: 'sv-stat-p', text: p || '' })]);
  }
  function render() {
    var d = state.data, events = (Array.isArray(d.events) ? d.events.filter(function (e) { return e && e.id != null; }) : []).slice(), revenue = !!d.show_revenue, people = !!d.show_participants;
    events.sort(function (a, b) { var x = String(a.start_date || ''), y = String(b.start_date || ''); return x < y ? -1 : x > y ? 1 : 0; });
    only('sv-content');
    var one = events.length === 1;
    $('sv-title').textContent = one ? flat(events[0].title) || 'Vânzări în timp real' : 'Vânzări în timp real';
    document.title = (one ? flat(events[0].title) + ' · ' : '') + 'Monitorizare vânzări — bilete.online';
    $('sv-sub').textContent = events.length ? (one ? 'Biletele vândute pentru această activitate, actualizate automat.' : count(events.length, 'activitate urmărită', 'activități urmărite') + ', cu biletele vândute actualizate automat.') : 'Linkul nu mai conține activități.';

    var sold = 0, total = 0, unlimited = false, cash = 0, currency = 'RON';
    events.forEach(function (e) {
      sold += num(e.tickets_sold);
      var cap = capacity(e.tickets_total);
      if (cap === null) unlimited = true; else total += cap;
      cash += num(e.revenue_net);
      if (e.currency) currency = e.currency;
    });
    var stats = $('sv-stats');
    stats.textContent = '';
    stats.appendChild(stat(events.length === 1 ? 'Activitate' : 'Activități', nf.format(events.length), '', ''));
    stats.appendChild(stat('Bilete vândute', nf.format(sold), !unlimited && total > 0 ? 'din ' + nf.format(total) : '', unlimited ? 'Unele bilete nu au o limită de locuri.' : ''));
    stats.appendChild(stat('Grad de ocupare', !unlimited && total > 0 ? Math.round(pct(sold, total)) + '%' : '—', '', !unlimited && total > 0 ? count(Math.max(0, total - sold), 'loc rămas', 'locuri rămase') + '.' : 'Nu se poate calcula fără o limită de locuri.'));
    if (revenue) stats.appendChild(stat('Încasări din bilete', lei(cash, currency), '', 'Valoarea biletelor din comenzile plătite.'));
    stats.style.setProperty('--sv-cols', revenue ? 4 : 3);

    var list = $('sv-events'), seen = {};
    if (!events.length) {
      list.textContent = '';
      cards = {};
      list.appendChild(el('li', { class: 'sv-empty', text: 'Organizatorul nu a lăsat nicio activitate în acest link.' }));
      return;
    }
    var empty = list.querySelector('.sv-empty');
    if (empty) empty.remove();
    events.forEach(function (e, i) {
      var id = String(e.id), card = cards[id] || (cards[id] = buildCard(id));
      seen[id] = true;
      updateCard(card, e, revenue, people, (d.participants && (d.participants[id] || d.participants[e.id])) || []);
      // move a card only when the order changed: moving a node takes the focus away from a search typed inside it
      if (list.children[i] !== card.li) list.insertBefore(card.li, list.children[i] || null);
    });
    Object.keys(cards).forEach(function (id) { if (!seen[id]) { cards[id].li.remove(); delete cards[id]; } });
    setLive();
  }
  function buildCard(id) {
    var c = { id: id, open: false, tab: 'types', query: '' };
    c.title = el('b');
    c.meta = el('span', { class: 'sv-ev-meta' });
    c.tags = el('span', { class: 'sv-ev-tags' });
    c.sold = el('b');
    c.total = el('b');
    c.cash = el('span', { class: 'sv-fig' }, [el('b'), el('small', { text: 'încasări' })]);
    c.bar = el('i');
    c.pctText = el('small');
    c.fill = el('span', { class: 'sv-fill' }, [el('span', { class: 'sv-meter', 'aria-hidden': 'true' }, c.bar), c.pctText]);
    c.figs = el('span', { class: 'sv-ev-figs' }, [el('span', { class: 'sv-fig' }, [c.sold, el('small', { text: 'vândute' })]), el('span', { class: 'sv-fig' }, [c.total, el('small', { text: 'în vânzare' })]), c.cash, c.fill]);
    c.head = el('button', { class: 'sv-ev-head', type: 'button', 'aria-expanded': 'false', 'aria-controls': 'sv-ev-' + id }, [
      el('span', { class: 'sv-ev-t' }, [c.title, c.meta, c.tags]),
      el('span', { class: 'sv-ev-right' }, [c.figs, el('span', { class: 'sv-chev', 'aria-hidden': 'true' }, icon('caret-down'))]),
    ]);
    c.body = el('div', { class: 'sv-ev-body', id: 'sv-ev-' + id, hidden: true });
    c.mobileFigs = null;
    c.head.addEventListener('click', function () {
      c.open = !c.open;
      c.head.setAttribute('aria-expanded', String(c.open));
      c.body.hidden = !c.open;
    });
    c.segTypes = el('button', { type: 'button', 'aria-pressed': 'true', text: 'Tipuri de bilete' });
    c.segPeople = el('button', { type: 'button', 'aria-pressed': 'false' });
    c.seg = el('div', { class: 'sv-seg', role: 'group', 'aria-label': 'Ce arată' }, [c.segTypes, c.segPeople]);
    [[c.segTypes, 'types'], [c.segPeople, 'people']].forEach(function (p) {
      p[0].addEventListener('click', function () { c.tab = p[1]; syncTab(c); });
    });
    c.types = el('ul', { class: 'sv-types' });
    c.search = el('input', { type: 'search', autocomplete: 'off', placeholder: 'Caută după nume, telefon sau bilet', 'aria-label': 'Caută participanți' });
    c.searchBox = el('label', { class: 'sv-psearch' }, [icon('magnifying-glass'), c.search]);
    c.search.addEventListener('input', function () { c.query = c.search.value; drawPeople(c); });
    c.peopleCount = el('p', { class: 'sv-count', 'aria-live': 'polite' });
    c.tbody = el('tbody');
    c.seatHead = el('th', { scope: 'col', text: 'Loc' });
    c.table = el('div', { class: 'sv-table-wrap' }, el('table', { class: 'sv-table' }, [el('caption', { class: 'sr', text: 'Participanți' }), el('thead', null, el('tr', null, [el('th', { scope: 'col', text: 'Nume' }), el('th', { scope: 'col', text: 'Telefon' }), el('th', { scope: 'col', text: 'Bilet' }), c.seatHead])), c.tbody]));
    c.peopleEmpty = el('p', { class: 'sv-empty', text: 'Nu sunt participanți încă.' });
    c.peopleWrap = el('div', { hidden: true }, [c.searchBox, c.peopleCount, c.table, c.peopleEmpty]);
    c.body.appendChild(c.seg);
    c.body.appendChild(c.types);
    c.body.appendChild(c.peopleWrap);
    c.li = el('li', { class: 'sv-ev' }, [c.head, c.body]);
    return c;
  }
  function syncTab(c) {
    var people = c.tab === 'people' && !c.seg.hidden;
    c.segTypes.setAttribute('aria-pressed', String(!people));
    c.segPeople.setAttribute('aria-pressed', String(people));
    c.types.hidden = people;
    c.peopleWrap.hidden = !people;
  }
  function updateCard(c, e, revenue, people, participants) {
    var sold = Math.round(num(e.tickets_sold)), cap = capacity(e.tickets_total), known = cap !== null && cap > 0, p = known ? pct(sold, cap) : 0;
    c.title.textContent = flat(e.title) || 'Activitate';
    c.meta.textContent = '';
    var when = [day(e.start_date), hhmm(e.start_time) || hhmm(e.start_date)].filter(Boolean).join(', ');
    if (when) c.meta.appendChild(el('span', null, [icon('calendar-blank'), when]));
    var where = [flat(e.venue_name), flat(e.city)].filter(Boolean).join(', ');
    if (where) c.meta.appendChild(el('span', null, [icon('map-pin'), where]));
    c.tags.textContent = '';
    if (known && sold >= cap) c.tags.appendChild(el('span', { class: 'sv-tag is-out', text: 'Sold out' }));
    else if (known && p >= 90) c.tags.appendChild(el('span', { class: 'sv-tag is-hot', text: 'Aproape sold out' }));
    c.sold.textContent = nf.format(sold);
    c.total.textContent = cap === null ? '∞' : cap > 0 ? nf.format(cap) : '—';
    c.total.setAttribute('title', cap === null ? 'fără limită de locuri' : '');
    c.cash.hidden = !revenue;
    c.cash.querySelector('b').textContent = lei(e.revenue_net, e.currency);
    c.fill.hidden = !known;
    c.bar.style.width = p.toFixed(1) + '%';
    c.bar.className = p >= 90 ? 'is-hot' : '';
    c.pctText.textContent = Math.round(p) + '%';
    c.head.setAttribute('aria-label', [c.title.textContent, when, where, nf.format(sold) + ' bilete vândute' + (known ? ' din ' + nf.format(cap) + ', ' + Math.round(p) + '%' : cap === null ? ', fără limită de locuri' : ''), revenue ? 'încasări ' + lei(e.revenue_net, e.currency) : ''].filter(Boolean).join('. '));

    var types = Array.isArray(e.ticket_types) ? e.ticket_types : [];
    c.types.textContent = '';
    if (!types.length) c.types.appendChild(el('li', { class: 'sv-empty', text: 'Activitatea nu are tipuri de bilete.' }));
    types.forEach(function (t) {
      var ts = Math.round(num(t.sold)), tc = capacity(t.total), tk = tc !== null && tc > 0, tp = tk ? pct(ts, tc) : 0, bar = el('i', { class: tp >= 90 ? 'is-hot' : null });
      bar.style.width = tp.toFixed(1) + '%';
      c.types.appendChild(el('li', null, [
        el('b', { text: flat(t.name) || 'Bilet' }),
        el('span', null, [nf.format(ts) + ' / ' + (tc === null ? '∞' : tk ? nf.format(tc) : '—'), tk ? el('small', { text: Math.round(tp) + '%' }) : null]),
        tk ? el('span', { class: 'sv-meter', 'aria-hidden': 'true' }, bar) : null,
      ]));
    });

    c.people = Array.isArray(participants) ? participants : [];
    c.seg.hidden = !people;
    c.segPeople.textContent = 'Participanți (' + nf.format(c.people.length) + ')';
    if (!people && c.tab === 'people') c.tab = 'types';
    syncTab(c);
    drawPeople(c);
  }
  function drawPeople(c) {
    var q = norm(c.query.trim()), rows = c.people.filter(function (p) { return !q || norm([flat(p.name), p.phone, flat(p.ticket_type), flat(p.seat_label)].join(' ')).indexOf(q) > -1; });
    var seats = c.people.some(function (p) { return flat(p.seat_label).trim(); });
    c.searchBox.hidden = c.people.length <= 8;
    c.seatHead.hidden = !seats;
    c.tbody.textContent = '';
    rows.forEach(function (p) {
      var phone = String(p.phone || '').trim(), tel = phone.replace(/[^\d+]/g, '');
      c.tbody.appendChild(el('tr', null, [
        el('td', { text: flat(p.name) || '—' }),
        el('td', null, phone && tel.length >= 6 ? el('a', { href: 'tel:' + tel, text: phone }) : phone || '—'),
        el('td', { text: flat(p.ticket_type) || '—' }),
        seats ? el('td', { text: flat(p.seat_label) || '—' }) : null,
      ]));
    });
    c.table.hidden = !rows.length;
    c.peopleEmpty.hidden = !!rows.length;
    c.peopleEmpty.textContent = c.people.length ? 'Nimeni nu se potrivește căutării.' : 'Nu sunt participanți încă.';
    c.peopleCount.textContent = c.people.length > 8 ? (q ? nf.format(rows.length) + ' din ' + count(c.people.length, 'participant', 'participanți') : count(c.people.length, 'participant', 'participanți')) : '';
    c.peopleCount.hidden = !c.peopleCount.textContent;
  }

  load(false);
})();
