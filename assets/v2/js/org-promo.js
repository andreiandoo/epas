/* bilete.online v2: organizer promo codes (/organizator/promo). The codes from /organizer/promo-codes (the organizer's own
   and the ones an admin added for their activities, which stay read-only), the figures (active codes, uses, and the
   discount given and order value from each used code's /stats), search and a state filter, creating a code for an
   activity and chosen ticket types, editing what core lets change (ticket types, limits, dates, conditions), pausing and
   reactivating, the orders that used a code, deleting. Runs inside the organizer shell (window.BO_ORG); text from the API
   is always written as text. Dates are Bucharest days: a code runs from 00:00 on its first day to 23:59:59 on its last,
   sent to core in UTC (core keeps UTC, so a bare date used to end the code at 03:00 on its last day). */
(function () {
  'use strict';
  var O = window.BO_ORG, root = document.getElementById('op');
  if (!O || !root) return;
  var F = O.fmt, el = O.el, icon = O.icon;
  var $ = function (id) { return document.getElementById(id); };
  function qsa(sel, ctx) { return [].slice.call((ctx || document).querySelectorAll(sel)); }

  var STATES = { active: 'Activ', scheduled: 'Programat', expired: 'Expirat', exhausted: 'Epuizat', inactive: 'Dezactivat' };
  var TAG = { active: 'is-ok', scheduled: 'is-info', expired: 'is-muted', exhausted: 'is-wait', inactive: 'is-bad' };
  var CODE = /^[A-Z0-9][A-Z0-9_-]{2,49}$/;
  var TTS_HELP = 'Bifează unul sau mai multe. Codul funcționează doar pentru tipurile bifate.';
  var LIMIT_MSG = 'Scrie un număr întreg, de cel puțin 1, sau lasă gol pentru nelimitat.';
  var ERR_IDS = ['op-code', 'op-value', 'op-event', 'op-tts', 'op-limit', 'op-limit-cust', 'op-start', 'op-end', 'op-min-amount', 'op-max-disc', 'op-min-tickets'];
  var SERVER_MAP = { code: 'op-code', value: 'op-value', type: 'op-value', event_id: 'op-event', ticket_type_ids: 'op-tts', usage_limit: 'op-limit', usage_limit_per_customer: 'op-limit-cust', starts_at: 'op-start', expires_at: 'op-end', min_purchase_amount: 'op-min-amount', max_discount_amount: 'op-max-disc', min_tickets: 'op-min-tickets' };

  var codes = null, events = null, eventsReq = null, ttCache = {}, ttSeq = 0, edit = null, delTarget = null, usage = null, statsSeq = 0;
  var opener = null, openerKey = null;

  /* =================== HELPERS =================== */
  function val(id) { var n = $(id); return n ? String(n.value || '').trim() : ''; }
  function norm(s) { return String(s == null ? '' : s).normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase(); }
  function num(v) { return v === '' || v == null ? NaN : Number(String(v).replace(',', '.')); }
  function tag(text, cls) { return el('span', { class: 'org-tag ' + (cls || ''), text: text }); }
  function naiveDay(v) { var m = /^(\d{4}-\d{2}-\d{2})/.exec(String(v || '')); return m ? m[1] : ''; }
  function dayLabel(v) { var d = F.dateOf(v); return d ? F.date(d, { day: 'numeric', month: 'short', year: 'numeric' }) : ''; }
  function dayOf(v) { var d = v ? F.dateOf(v) : null; return d ? F.ymd(d) : ''; }
  /** Bucharest wall clock of a moment, read as if it were UTC (for the offset). */
  function wall(ms) {
    var p = {};
    new Intl.DateTimeFormat('en-GB', { timeZone: 'Europe/Bucharest', hourCycle: 'h23', year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit', second: '2-digit' })
      .formatToParts(new Date(ms)).forEach(function (x) { p[x.type] = Number(x.value); });
    return Date.UTC(p.year, p.month - 1, p.day, p.hour % 24, p.minute, p.second);
  }
  /** A Bucharest time on a YYYY-MM-DD day, as the UTC ISO string core stores. */
  function bucharestIso(ymd, h, m, s) {
    var d = ymd.split('-').map(Number), want = Date.UTC(d[0], d[1] - 1, d[2], h, m, s), t = want;
    for (var i = 0; i < 2; i++) t -= wall(t) - want;
    return new Date(t).toISOString().replace(/\.\d{3}Z$/, 'Z');
  }
  function isAdmin(c) { return c.source === 'admin' || !!c.readonly || !/^\d+$/.test(String(c.id)); }
  /** What a buyer would see: core keeps "active" on codes past their end date or limit. */
  function state(c) {
    var used = F.toNum(c.usage_count), limit = F.toNum(c.usage_limit), t = Date.now();
    if (c.status === 'inactive' || c.status === 'disabled' || c.status === 'paused') return 'inactive';
    if (c.status === 'exhausted' || (limit > 0 && used >= limit)) return 'exhausted';
    if (c.status === 'expired' || (c.expires_at && Date.parse(c.expires_at) <= t)) return 'expired';
    if (c.starts_at && Date.parse(c.starts_at) > t) return 'scheduled';
    return c.status === 'active' ? 'active' : 'inactive';
  }
  function discountText(c) { var v = F.toNum(c.value); return (c.type === 'fixed' ? F.money(v) : F.num(v) + '%') + ' reducere'; }
  function eventName(c) { return (c.event && F.flat(c.event.name || c.event.title)) || F.flat(c.event_name) || 'Toate activitățile'; }
  function ttIds(c) {
    var ids = Array.isArray(c.applicable_ticket_type_ids) && c.applicable_ticket_type_ids.length ? c.applicable_ticket_type_ids : c.ticket_type && c.ticket_type.id != null ? [c.ticket_type.id] : [];
    return ids.map(String);
  }
  function busyBtn(btn, on, text) {
    var l = btn.querySelector('[data-label]');
    if (on) { btn.setAttribute('aria-busy', 'true'); if (l) { btn.setAttribute('data-idle', l.textContent); l.textContent = text; } }
    else { btn.removeAttribute('aria-busy'); if (l && btn.hasAttribute('data-idle')) l.textContent = btn.getAttribute('data-idle'); btn.removeAttribute('data-idle'); }
  }
  function isBusy(btn) { return btn.getAttribute('aria-busy') === 'true'; }
  function fieldErr(id, msg) {
    var f = $(id), e = $(id + '-err');
    if (e) { e.textContent = msg || ''; e.hidden = !msg; }
    if (f && /^(INPUT|SELECT|TEXTAREA)$/.test(f.tagName)) { if (msg) f.setAttribute('aria-invalid', 'true'); else f.removeAttribute('aria-invalid'); }
  }
  function formErr(id, msg) { var b = $(id); if (b) { b.textContent = msg || ''; b.hidden = !msg; } }
  function errMessage(err) { return String((err && (err.message || (err.data && err.data.message))) || ''); }
  function saveError(err) {
    var s = err && err.status;
    if (s === 422) return 'Unele câmpuri nu sunt completate corect. Verifică-le și încearcă din nou.';
    if (s === 429) return 'Prea multe încercări într-un timp scurt. Așteaptă un minut și încearcă din nou.';
    if (s === 403) return 'Contul tău nu are voie să schimbe codurile promoționale.';
    if (s === 0 || s == null) return 'Nu am putut ajunge la server. Verifică conexiunea și încearcă din nou.';
    return 'Nu am putut salva codul. Încearcă din nou.';
  }
  function markServer(err) {
    var errors = (err && err.errors) || (err && err.data && err.data.errors) || null, first = null;
    if (!errors || typeof errors !== 'object') return;
    Object.keys(errors).forEach(function (k) {
      var id = SERVER_MAP[k.replace(/\.\d+$/, '')];
      if (id && $(id)) { fieldErr(id, 'Verifică această valoare.'); if (!first) first = id; }
    });
    if (first) focusField(first);
  }
  function focusField(id) {
    if (/^op-(min-amount|max-disc|min-tickets)$/.test(id)) $('op-more').open = true;
    var n = $(id), f = n && /^(FIELDSET|UL)$/.test(n.tagName) ? root.querySelector('#' + id + ' input:not(:disabled), #' + id + ' button') : n;
    if (f && f.focus) f.focus();
  }
  function copyText(text) {
    if (navigator.clipboard && window.isSecureContext) return navigator.clipboard.writeText(text);
    return new Promise(function (resolve, reject) {
      var ta = el('textarea', { class: 'op-sr', readonly: true });
      ta.value = text;
      document.body.appendChild(ta);
      ta.select();
      var ok = false;
      try { ok = document.execCommand('copy'); } catch (e) { ok = false; }
      ta.remove();
      if (ok) resolve(); else reject(new Error('copy'));
    });
  }
  function randomCode() {
    var chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789', rnd = new Uint32Array(8), out = '';
    (window.crypto || window.msCrypto).getRandomValues(rnd);
    for (var i = 0; i < 8; i++) out += chars.charAt(rnd[i] % chars.length);
    return out;
  }
  function focusKey(box) { var a = document.activeElement; return a && a !== document.body && box.contains(a) ? a.getAttribute('data-focus') || '' : null; }
  function keepKey(box, wanted) { var k = focusKey(box), a = document.activeElement; return k === null && wanted && (!a || a === document.body) ? wanted : k; }
  function restoreFocus(box, key, fallback) {
    if (key === null) return;
    var same = key ? box.querySelector('[data-focus="' + key + '"]') : null;
    if (same) same.focus();
    else if (fallback && !fallback.disabled) fallback.focus();
  }
  function pill(ic, text, attrs) { return el('button', Object.assign({ class: 'op-pill', type: 'button' }, attrs || {}), [icon(ic), text]); }

  /* =================== LOAD + FIGURES =================== */
  function load(wanted) {
    $('op-load-err').hidden = true;
    return O.api('/organizer/promo-codes').then(function (r) {
      var d = r && r.data;
      var list = Array.isArray(d) ? d : d && Array.isArray(d.data) ? d.data : d && Array.isArray(d.promo_codes) ? d.promo_codes : [];
      codes = list.filter(function (c) { return c && c.id != null && c.code; });
      var box = $('op-grid'), keep = keepKey(box, wanted);
      $('op-panel').hidden = false;
      render();
      restoreFocus(box, keep, $('op-add'));
      $('op-add').disabled = false;
      loadStats();
    }).catch(function (err) {
      if (err && err.status === 401) return;
      codes = null;
      statsSeq++;
      $('op-load-err').hidden = false;
      $('op-panel').hidden = true;
      $('op-s-note').hidden = true;
      ['active', 'uses', 'discount', 'revenue'].forEach(function (k) { $('op-s-' + k).textContent = '—'; });
    });
  }
  $('op-retry').addEventListener('click', function () { load(); });
  function render() {
    var active = codes.filter(function (c) { return state(c) === 'active'; }).length;
    $('op-s-active').textContent = F.num(active);
    $('op-s-uses').textContent = F.num(codes.reduce(function (s, c) { return s + F.toNum(c.usage_count); }, 0));
    $('op-list-p').textContent = codes.length ? F.count(codes.length, 'cod', 'coduri') + ', dintre care ' + F.count(active, 'activ acum', 'active acum') + '.' : 'Încă nu ai coduri de reducere.';
    drawGrid();
  }
  /** Discount given and order value: core only has them per code, so the used codes are asked four at a time. */
  function loadStats() {
    var seq = ++statsSeq, own = codes.filter(function (c) { return !isAdmin(c) && F.toNum(c.usage_count) > 0; }), i = 0, disc = 0, orders = 0, failed = false;
    var adminUsed = codes.some(function (c) { return isAdmin(c) && F.toNum(c.usage_count) > 0; });
    $('op-s-note').textContent = adminUsed ? 'Reducerile și valoarea comenzilor nu includ codurile adăugate de admin.' : '';
    $('op-s-note').hidden = !adminUsed;
    function next() {
      if (i >= own.length) return Promise.resolve();
      var c = own[i++];
      return O.api('/organizer/promo-codes/' + c.id + '/stats', { quiet: true }).then(function (r) {
        var s = (r && r.data && r.data.stats) || {};
        disc += F.toNum(s.total_discount_given);
        orders += F.toNum(s.total_order_value);
      }, function () { failed = true; }).then(next);
    }
    Promise.all([next(), next(), next(), next()]).then(function () {
      if (seq !== statsSeq) return;
      $('op-s-discount').textContent = failed ? '—' : F.money(disc);
      $('op-s-revenue').textContent = failed ? '—' : F.money(orders);
    });
  }

  /* =================== GRID =================== */
  function shownCodes() {
    var q = norm(val('op-q')), st = $('op-status').value;
    return codes.filter(function (c) {
      return (!q || norm(c.code).indexOf(q) > -1 || norm(c.name).indexOf(q) > -1 || norm(eventName(c)).indexOf(q) > -1) && (!st || state(c) === st);
    });
  }
  function drawGrid() {
    var box = $('op-grid'), list = shownCodes(), filtered = !!(val('op-q') || $('op-status').value);
    box.textContent = '';
    list.forEach(function (c) { box.appendChild(card(c)); });
    if (!codes.length) box.appendChild(el('li', { class: 'op-empty' }, [el('b', { text: 'Niciun cod încă' }), el('p', { text: 'Un cod de reducere se aplică la o activitate și la tipurile de bilete alese, pe o perioadă și cu limitele pe care le stabilești.' })]));
    else if (filtered && !list.length) {
      var reset = el('button', { type: 'button', text: 'Arată toate codurile' });
      reset.addEventListener('click', function () { $('op-q').value = ''; $('op-status').value = ''; drawGrid(); $('op-q').focus(); });
      box.appendChild(el('li', { class: 'op-none' }, ['Niciun cod nu se potrivește filtrelor.', reset]));
    }
    var tile = el('button', { class: 'op-new', type: 'button', 'data-focus': 'code-new' }, [el('span', { class: 'op-new-ic', 'aria-hidden': 'true' }, icon('plus')), el('b', { text: 'Creează cod nou' }), el('small', { text: 'Adaugă un nou cod de reducere' })]);
    tile.addEventListener('click', function () { openCode(null, tile); });
    box.appendChild(el('li', null, tile));
  }
  $('op-q').addEventListener('input', function () { if (codes) drawGrid(); });
  $('op-status').addEventListener('change', function () { if (codes) drawGrid(); });

  function card(c) {
    var st = state(c), admin = isAdmin(c), id = String(c.id), live = st === 'active' || st === 'scheduled';
    var used = F.toNum(c.usage_count), limit = F.toNum(c.usage_limit), n = ttIds(c).length;
    var tags = [tag(STATES[st], TAG[st])];
    if (admin) tags.push(tag('Adăugat de admin', 'is-info'));
    var row = [el('code', { class: 'op-code', text: c.code })];
    if (live) {
      var copy = el('button', { class: 'op-copy', type: 'button', 'data-focus': 'code-copy-' + id, 'aria-label': 'Copiază codul ' + c.code, title: 'Copiază codul' }, icon('copy'));
      copy.addEventListener('click', function () { copyCode(c.code); });
      row.push(copy);
    }
    var whenK = st === 'scheduled' ? 'Începe' : st === 'expired' ? 'Expirat la' : 'Expiră';
    var whenV = st === 'scheduled' ? dayLabel(c.starts_at) : c.expires_at ? dayLabel(c.expires_at) : 'Nelimitat';
    var figs = el('dl', { class: 'op-figs' }, [
      el('div', null, [el('dt', { text: 'Utilizări' }), el('dd', { text: F.num(used) + (limit ? ' / ' + F.num(limit) : '') })]),
      el('div', null, [el('dt', { text: 'Per client' }), el('dd', { text: c.usage_limit_per_customer ? F.num(c.usage_limit_per_customer) : 'Nelimitat' })]),
      el('div', null, [el('dt', { text: whenK }), el('dd', { text: whenV })]),
    ]);
    var parts = [
      el('div', { class: 'op-card-top' }, [el('span', { class: 'op-ic', 'aria-hidden': 'true' }, icon('tag')), el('div', { class: 'op-tags' }, tags)]),
      el('div', { class: 'op-code-row' }, row),
      el('p', { class: 'op-disc', text: discountText(c) }),
      el('p', { class: 'op-ev', text: eventName(c) + (n ? ' · ' + F.count(n, 'tip de bilet', 'tipuri de bilete') : '') }),
      figs,
    ];
    if (limit > 0) {
      var fill = el('i');
      fill.style.width = Math.min(100, Math.round((used / limit) * 100)) + '%';
      parts.push(el('span', { class: 'op-bar', 'aria-hidden': 'true' }, fill));
    }
    if (admin) parts.push(el('p', { class: 'op-ro', text: 'Codul a fost adăugat de admin și nu se poate modifica aici.' }));
    else {
      var acts = [];
      var uses = pill('chart-line-up', 'Utilizări', { 'data-focus': 'code-usage-' + id, 'aria-label': 'Utilizările codului ' + c.code });
      uses.addEventListener('click', function () { openUsage(c, uses); });
      var editBtn = pill('pencil-simple', 'Editează', { 'data-focus': 'code-edit-' + id, 'aria-label': 'Editează codul ' + c.code });
      editBtn.addEventListener('click', function () { openCode(c, editBtn); });
      acts.push(uses, editBtn);
      if (c.status === 'active' && live) {
        var pause = pill('pause', 'Pauză', { 'data-focus': 'code-pause-' + id, 'aria-label': 'Pune pe pauză codul ' + c.code });
        pause.addEventListener('click', function () { toggle(c, pause, false); });
        acts.push(pause);
      } else if (c.status !== 'active') {
        var play = pill('play', 'Activează', { 'data-focus': 'code-play-' + id, 'aria-label': 'Activează codul ' + c.code });
        play.addEventListener('click', function () { toggle(c, play, true); });
        acts.push(play);
      }
      var del = pill('trash', 'Șterge', { class: 'op-pill is-danger', 'data-focus': 'code-del-' + id, 'aria-label': 'Șterge codul ' + c.code });
      del.addEventListener('click', function () { openDelete(c, del); });
      acts.push(del);
      parts.push(el('div', { class: 'op-acts' }, acts));
    }
    return el('li', { class: 'op-card is-' + st }, parts);
  }
  function copyCode(code) {
    copyText(code).then(function () { O.flash('Codul ' + code + ' a fost copiat.'); }, function () { O.flash('Nu am putut copia. Selectează codul și copiază-l manual.', true); });
  }

  /* =================== PAUSE / ACTIVATE =================== */
  function toggle(c, btn, on) {
    if (btn.disabled) return;
    btn.disabled = true;
    O.api('/organizer/promo-codes/' + c.id + '/' + (on ? 'activate' : 'deactivate'), { method: 'POST', body: {} }).then(function () {
      O.flash(on ? 'Codul ' + c.code + ' e din nou activ.' : 'Codul ' + c.code + ' e pe pauză: nu mai poate fi folosit până îl activezi.');
      load((on ? 'code-pause-' : 'code-play-') + c.id);
    }).catch(function (err) {
      btn.disabled = false;
      if (err && err.status === 401) return;
      var m = errMessage(err);
      if (/expired/i.test(m)) { O.flash('Codul ' + c.code + ' a expirat. Mută data de sfârșit în viitor ca să-l poți activa.', true); return; }
      if (/exhausted/i.test(m)) { O.flash('Codul ' + c.code + ' și-a atins limita de utilizări. Mărește limita ca să-l poți activa.', true); return; }
      if (err && err.status === 404) { O.flash('Codul nu mai există.', true); load(); return; }
      O.flash('Nu am putut ' + (on ? 'activa' : 'opri') + ' codul. Încearcă din nou.', true);
    });
  }

  /* =================== ACTIVITIES + TICKET TYPES =================== */
  function isOver(ev) {
    if (ev.is_cancelled || ev.is_past || ev.is_ended) return true;
    var end = naiveDay(ev.ends_at || ev.starts_at);
    return !!end && end < F.ymd();
  }
  function loadEvents() {
    if (eventsReq) return eventsReq;
    var got = [];
    function page(n) {
      return O.api('/organizer/events?per_page=50&page=' + n, { quiet: true }).then(function (r) {
        got = got.concat(Array.isArray(r && r.data) ? r.data : []);
        if (F.toNum(O.metaOf(r).last_page) > n && n < 20) return page(n + 1);
      });
    }
    eventsReq = page(1).then(function () {
      events = got.filter(function (e) { return e && /^\d+$/.test(String(e.id)) && !isOver(e); }).sort(function (a, b) {
        var ad = String(a.starts_at || ''), bd = String(b.starts_at || '');
        return ad < bd ? -1 : ad > bd ? 1 : 0;
      });
      return events;
    }, function (err) { eventsReq = null; throw err; });
    return eventsReq;
  }
  function fillEvents(c) {
    var sel = $('op-event');
    sel.textContent = '';
    if (c) {
      var evId = c.event && c.event.id != null ? String(c.event.id) : '';
      sel.appendChild(el('option', { value: evId, text: c.event ? eventName(c) : 'Fără activitate' }));
      sel.value = evId;
      if (evId) loadTts(evId, ttIds(c));
      else {
        $('op-tt-list').textContent = '';
        $('op-tt-list').appendChild(el('li', { class: 'op-msg', text: 'Codul nu are o activitate, așa că tipurile de bilete nu se pot schimba.' }));
        $('op-tts-all').hidden = true;
        $('op-tts-n').textContent = '';
      }
      return;
    }
    sel.appendChild(el('option', { value: '', text: 'Se încarcă activitățile…' }));
    loadTts('', null);
    loadEvents().then(function (list) {
      if (edit || !$('op-code-d').open) return;
      sel.textContent = '';
      sel.appendChild(el('option', { value: '', text: list.length ? '— Alege o activitate —' : 'Nu ai activități viitoare' }));
      list.forEach(function (e) {
        var day = naiveDay(e.starts_at);
        sel.appendChild(el('option', { value: String(e.id), text: (F.flat(e.name || e.title) || 'Activitatea #' + e.id) + (day ? ' · ' + dayLabel(day) : '') }));
      });
    }, function () {
      if (edit) return;
      sel.textContent = '';
      sel.appendChild(el('option', { value: '', text: 'Nu am putut încărca activitățile' }));
      fieldErr('op-event', 'Nu am putut încărca activitățile. Închide fereastra și încearcă din nou.');
    });
  }
  $('op-event').addEventListener('change', function () { fieldErr('op-event', ''); loadTts(val('op-event'), null); });
  function loadTts(eventId, pre) {
    var seq = ++ttSeq, box = $('op-tt-list');
    fieldErr('op-tts', '');
    $('op-tts-all').hidden = true;
    $('op-tts-n').textContent = TTS_HELP;
    box.textContent = '';
    if (!eventId) { box.appendChild(el('li', { class: 'op-msg', text: 'Alege mai întâi activitatea.' })); return; }
    box.appendChild(el('li', { class: 'op-msg', text: 'Se încarcă tipurile de bilete…' }));
    var got = ttCache[eventId] ? Promise.resolve(ttCache[eventId]) : O.api('/organizer/events/' + eventId, { quiet: true }).then(function (r) {
      var ev = (r && r.data && (r.data.event || r.data)) || {};
      // "bilet gratuit cu cod" types are free already and managed by the admin
      return (ttCache[eventId] = (Array.isArray(ev.ticket_types) ? ev.ticket_types : []).filter(function (t) { return t && /^\d+$/.test(String(t.id)) && !t.free_with_code; }));
    });
    got.then(function (list) {
      if (seq !== ttSeq) return;
      box.textContent = '';
      if (!list.length) { box.appendChild(el('li', { class: 'op-msg', text: 'Activitatea nu are tipuri de bilete.' })); return; }
      var keep = pre || list.map(function (t) { return String(t.id); });
      list.forEach(function (t) {
        var id = String(t.id), price = F.toNum(t.price), cb = el('input', { type: 'checkbox', value: id, 'data-tt': '' });
        cb.checked = keep.indexOf(id) > -1;
        cb.addEventListener('change', function () { fieldErr('op-tts', ''); countTts(); });
        var meta = [price > 0 ? F.money(price) : 'gratuit'];
        if (t.status && t.status !== 'on_sale') meta.push('nu e în vânzare');
        box.appendChild(el('li', null, el('label', { class: 'op-tt' }, [cb, el('span', null, [el('b', { text: F.flat(t.name) || 'Bilet #' + id }), el('small', { text: meta.join(' · ') })])])));
      });
      $('op-tts-all').hidden = list.length < 2;
      countTts();
    }, function () {
      if (seq !== ttSeq) return;
      box.textContent = '';
      var retry = el('button', { class: 'op-linkbtn', type: 'button', text: 'Reîncearcă' });
      retry.addEventListener('click', function () { loadTts(eventId, pre); });
      box.appendChild(el('li', { class: 'op-msg' }, ['Nu am putut încărca tipurile de bilete.', retry]));
    });
  }
  function checkedTts() { return qsa('[data-tt]', root).filter(function (c) { return c.checked; }).map(function (c) { return Number(c.value); }); }
  function countTts() {
    var n = checkedTts().length, total = qsa('[data-tt]', root).length;
    $('op-tts-n').textContent = !total ? TTS_HELP : n ? F.count(n, 'tip de bilet ales', 'tipuri de bilete alese') + ' din ' + total + '.' : 'Bifează cel puțin un tip de bilet.';
  }
  qsa('[data-tts]', root).forEach(function (b) {
    b.addEventListener('click', function () {
      var on = b.getAttribute('data-tts') === 'all';
      qsa('[data-tt]', root).forEach(function (c) { c.checked = on; });
      fieldErr('op-tts', '');
      countTts();
    });
  });

  /* =================== CREATE / EDIT =================== */
  function typeValue() { var r = root.querySelector('input[name="op-type"]:checked'); return r ? r.value : 'percentage'; }
  function syncType() {
    var pct = typeValue() === 'percentage';
    $('op-value-suffix').textContent = pct ? '%' : 'lei';
    $('op-value').max = pct ? '100' : '100000';
    $('op-max-f').hidden = !pct;
  }
  qsa('input[name="op-type"]', root).forEach(function (r) { r.addEventListener('change', function () { fieldErr('op-value', ''); syncType(); }); });
  $('op-code').addEventListener('input', function () {
    var n = this, clean = n.value.toUpperCase().replace(/\s+/g, '');
    if (clean !== n.value) { var at = n.selectionStart; n.value = clean; try { n.setSelectionRange(Math.min(at, clean.length), Math.min(at, clean.length)); } catch (e) {} }
    fieldErr('op-code', '');
  });
  $('op-gen').addEventListener('click', function () { $('op-code').value = randomCode(); fieldErr('op-code', ''); $('op-code').focus(); });
  ERR_IDS.forEach(function (id) {
    var n = $(id);
    if (n && /^(INPUT|SELECT)$/.test(n.tagName) && id !== 'op-code') n.addEventListener('input', function () { fieldErr(id, ''); });
  });

  function openCode(c, from) {
    if (!codes) return;
    edit = c || null;
    $('op-code-h').textContent = c ? 'Editează codul ' + c.code : 'Cod promoțional nou';
    $('op-code-p').textContent = c
      ? 'Codul, reducerea și activitatea nu se mai pot schimba după creare. Pentru altă reducere, creează un cod nou.'
      : 'Codul se aplică la o activitate și doar la tipurile de bilete pe care le bifezi.';
    $('op-fixed').disabled = !!c;
    $('op-code').value = c ? c.code : '';
    qsa('input[name="op-type"]', root).forEach(function (r) { r.checked = r.value === (c && c.type === 'fixed' ? 'fixed' : 'percentage'); });
    syncType();
    $('op-value').value = c ? String(F.toNum(c.value)) : '';
    $('op-limit').value = c && c.usage_limit ? String(c.usage_limit) : '';
    $('op-limit-cust').value = c && c.usage_limit_per_customer ? String(c.usage_limit_per_customer) : '';
    $('op-start').value = c ? dayOf(c.starts_at) : F.ymd();
    $('op-end').value = c ? dayOf(c.expires_at) : '';
    $('op-min-amount').value = c && c.min_purchase_amount != null ? String(c.min_purchase_amount) : '';
    $('op-max-disc').value = c && c.max_discount_amount != null ? String(c.max_discount_amount) : '';
    $('op-min-tickets').value = c && c.min_tickets != null ? String(c.min_tickets) : '';
    $('op-more').open = !!(c && (c.min_purchase_amount || c.max_discount_amount || c.min_tickets));
    ERR_IDS.forEach(function (id) { fieldErr(id, ''); });
    formErr('op-form-err', '');
    $('op-code-go').querySelector('[data-label]').textContent = c ? 'Salvează codul' : 'Creează codul';
    openDialog($('op-code-d'), from);
    fillEvents(c);
    (c ? $('op-limit') : $('op-code')).focus();
  }
  $('op-add').addEventListener('click', function () { openCode(null, this); });

  $('op-code-form').addEventListener('submit', function (ev) {
    ev.preventDefault();
    var d = $('op-code-d'), btn = $('op-code-go'), c = edit, bad = null;
    if (isBusy(btn)) return;
    function need(id, msg) { fieldErr(id, msg); if (msg && !bad) bad = id; }
    function intErr(v, msg) { return v === '' || (/^\d+$/.test(v) && Number(v) >= 1) ? '' : msg; }
    function amountErr(v) { return v === '' || (isFinite(num(v)) && num(v) >= 0) ? '' : 'Scrie o sumă de cel puțin 0, sau lasă gol.'; }
    formErr('op-form-err', '');
    var code = val('op-code').toUpperCase(), type = typeValue(), rawValue = val('op-value'), value = num(rawValue), eventId = val('op-event');
    var tts = checkedTts(), limit = val('op-limit'), perCust = val('op-limit-cust'), start = val('op-start'), end = val('op-end');
    var minAmount = val('op-min-amount'), maxDisc = val('op-max-disc'), minTickets = val('op-min-tickets'), today = F.ymd();
    var used = c ? F.toNum(c.usage_count) : 0, ttsOn = c ? !!(c.event && c.event.id != null) : !!eventId;
    if (!c) {
      need('op-code', !code ? 'Scrie codul promoțional sau generează unul.' : !CODE.test(code) ? 'Codul are 3–50 caractere: litere fără diacritice, cifre, - sau _.' : '');
      need('op-value', !rawValue ? 'Scrie valoarea reducerii.' : !isFinite(value) || value <= 0 ? 'Reducerea trebuie să fie mai mare decât 0.' : type === 'percentage' && value > 100 ? 'Reducerea procentuală poate fi de cel mult 100%.' : '');
      need('op-event', !eventId ? 'Alege activitatea.' : '');
    }
    need('op-tts', ttsOn && !tts.length ? 'Alege cel puțin un tip de bilet.' : '');
    need('op-limit', intErr(limit, LIMIT_MSG) || (c && limit !== '' && Number(limit) < used ? 'Codul a fost folosit deja ' + (used === 1 ? 'o dată' : 'de ' + F.count(used, 'dată', 'ori')) + ', așa că limita nu poate fi mai mică de ' + used + '.' : ''));
    need('op-limit-cust', intErr(perCust, LIMIT_MSG));
    need('op-start', !start && !c ? 'Alege data de început.' : '');
    need('op-end', !end && !c ? 'Alege data de sfârșit.' : end && end < today && (!c || end !== dayOf(c.expires_at)) ? 'Data de sfârșit a trecut deja.' : end && start && end < start ? 'Data de sfârșit nu poate fi înaintea celei de început.' : '');
    need('op-min-amount', amountErr(minAmount));
    need('op-max-disc', type === 'percentage' ? amountErr(maxDisc) : '');
    need('op-min-tickets', intErr(minTickets, 'Scrie un număr întreg, de cel puțin 1, sau lasă gol.'));
    if (bad) { focusField(bad); return; }

    var common = {
      usage_limit: limit === '' ? null : Number(limit),
      usage_limit_per_customer: perCust === '' ? null : Number(perCust),
      starts_at: start ? bucharestIso(start, 0, 0, 0) : null,
      expires_at: end ? bucharestIso(end, 23, 59, 59) : null,
      min_purchase_amount: minAmount === '' ? null : num(minAmount),
      max_discount_amount: type === 'percentage' && maxDisc !== '' ? num(maxDisc) : null,
      min_tickets: minTickets === '' ? null : Number(minTickets),
    };
    var body = c
      ? (ttsOn ? Object.assign({ ticket_type_ids: tts }, common) : common)
      : Object.assign({ code: code, type: type, value: value, applies_to: 'ticket_type', event_id: Number(eventId), ticket_type_ids: tts }, common);
    busyBtn(btn, true, c ? 'Se salvează…' : 'Se creează…');
    d.setAttribute('data-busy', '');
    O.api(c ? '/organizer/promo-codes/' + c.id : '/organizer/promo-codes', { method: c ? 'PUT' : 'POST', body: body }).then(function () {
      d.removeAttribute('data-busy');
      busyBtn(btn, false);
      closeDialog(d);
      O.flash('Codul ' + (c ? c.code + ' a fost salvat.' : code + ' a fost creat.'));
      load(c ? 'code-edit-' + c.id : null);
    }).catch(function (err) {
      d.removeAttribute('data-busy');
      busyBtn(btn, false);
      if (err && err.status === 401) return;
      var m = errMessage(err);
      if (/already exists/i.test(m)) { fieldErr('op-code', 'Există deja un cod cu acest nume. Alege altul.'); $('op-code').focus(); return; }
      if (/cannot exceed 100/i.test(m)) { fieldErr('op-value', 'Reducerea procentuală poate fi de cel mult 100%.'); $('op-value').focus(); return; }
      if (/usage limit cannot be less/i.test(m)) { fieldErr('op-limit', 'Limita nu poate fi mai mică decât utilizările de până acum.'); $('op-limit').focus(); return; }
      if (/ticket types do not belong/i.test(m)) {
        var evId = c ? String(c.event.id) : eventId;
        delete ttCache[evId];
        loadTts(evId, tts.map(String));
        fieldErr('op-tts', 'Unele tipuri de bilete nu mai aparțin activității. Am reîncărcat lista: verifică-le și salvează din nou.');
        return;
      }
      if (/no associated event/i.test(m)) { formErr('op-form-err', 'Codul nu are o activitate, așa că tipurile de bilete nu se pot schimba.'); return; }
      if (err && err.status === 404) {
        if (c) { formErr('op-form-err', 'Codul nu mai există. Am reîncărcat lista.'); load(); }
        else { fieldErr('op-event', 'Activitatea nu mai există sau nu îți aparține.'); $('op-event').focus(); }
        return;
      }
      formErr('op-form-err', saveError(err));
      markServer(err);
    });
  });

  /* =================== USES =================== */
  function openUsage(c, from) {
    var seq = (usage ? usage.seq : 0) + 1;
    usage = { c: c, page: 1, rows: [], seq: seq };
    $('op-usage-h').textContent = 'Utilizările codului ' + c.code;
    $('op-usage-p').textContent = discountText(c) + ' · ' + eventName(c);
    ['op-u-uses', 'op-u-customers', 'op-u-discount', 'op-u-orders'].forEach(function (id) { $(id).textContent = '…'; });
    $('op-uses').textContent = '';
    $('op-uses').appendChild(el('li', { class: 'op-msg', text: 'Se încarcă utilizările…' }));
    $('op-usage-more').hidden = true;
    openDialog($('op-usage-d'), from);
    $('op-usage-d').querySelector('.op-x').focus();
    O.api('/organizer/promo-codes/' + c.id + '/stats', { quiet: true }).then(function (r) {
      if (!usage || seq !== usage.seq) return;
      var s = (r && r.data && r.data.stats) || {}, limit = F.toNum(s.usage_limit);
      $('op-u-uses').textContent = F.num(s.total_uses) + (limit ? ' din ' + F.num(limit) : '');
      $('op-u-customers').textContent = F.num(s.unique_customers);
      $('op-u-discount').textContent = F.money(s.total_discount_given);
      $('op-u-orders').textContent = F.money(s.total_order_value);
    }, function () {
      if (!usage || seq !== usage.seq) return;
      ['op-u-uses', 'op-u-customers', 'op-u-discount', 'op-u-orders'].forEach(function (id) { $(id).textContent = '—'; });
    });
    loadUsagePage(1);
  }
  function loadUsagePage(n) {
    var u = usage, seq = u.seq, more = $('op-usage-more');
    if (n > 1) busyBtn(more, true, 'Se încarcă…');
    O.api('/organizer/promo-codes/' + u.c.id + '/usage?per_page=20&page=' + n, { quiet: true }).then(function (r) {
      if (seq !== usage.seq) return;
      busyBtn(more, false);
      var rows = Array.isArray(r && r.data) ? r.data : [];
      u.rows = n === 1 ? rows : u.rows.concat(rows);
      u.page = n;
      drawUsage(F.toNum(O.metaOf(r).last_page) > n);
    }, function (err) {
      if (seq !== usage.seq) return;
      busyBtn(more, false);
      if (err && err.status === 401) return;
      if (n > 1) { O.flash('Nu am putut încărca mai multe utilizări. Încearcă din nou.', true); return; }
      var box = $('op-uses'), retry = el('button', { class: 'op-linkbtn', type: 'button', text: 'Reîncearcă' });
      retry.addEventListener('click', function () { loadUsagePage(1); });
      box.textContent = '';
      box.appendChild(el('li', { class: 'op-msg' }, ['Nu am putut încărca utilizările.', retry]));
    });
  }
  function drawUsage(hasMore) {
    var box = $('op-uses'), more = $('op-usage-more'), hadFocus = document.activeElement === more;
    box.textContent = '';
    if (!usage.rows.length) box.appendChild(el('li', { class: 'op-msg', text: 'Codul nu a fost folosit încă.' }));
    usage.rows.forEach(function (x) {
      var order = x.order || {}, name = F.flat(x.customer_name).trim(), email = F.flat(x.customer_email).trim(), when = F.dateOf(x.used_at);
      box.appendChild(el('li', { class: 'op-use' }, [
        el('div', { class: 'op-use-t' }, [
          el('b', { text: order.order_number ? 'Comanda ' + F.flat(order.order_number) : 'Comandă' }),
          el('span', { text: [name, email].filter(Boolean).join(' · ') || 'Client' }),
          el('small', { text: when ? F.date(when, { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' }) : '' }),
        ]),
        el('div', { class: 'op-use-v' }, [el('b', { text: '−' + F.money(x.discount_applied) }), el('small', { text: 'din ' + F.money(x.order_total) })]),
      ]));
    });
    more.hidden = !hasMore;
    if (hadFocus && !hasMore) box.focus();
  }
  $('op-usage-more').addEventListener('click', function () { if (usage && !isBusy(this)) loadUsagePage(usage.page + 1); });

  /* =================== DELETE =================== */
  function openDelete(c, from) {
    delTarget = c;
    $('op-del-h').textContent = 'Ștergi codul ' + c.code + '?';
    formErr('op-del-err', '');
    openDialog($('op-del-d'), from);
    $('op-del-d').querySelector('[data-close]').focus();
  }
  $('op-del-go').addEventListener('click', function () {
    var btn = this, d = $('op-del-d'), c = delTarget;
    if (!c || isBusy(btn)) return;
    busyBtn(btn, true, 'Se șterge…');
    d.setAttribute('data-busy', '');
    O.api('/organizer/promo-codes/' + c.id, { method: 'DELETE' }).then(function () {
      d.removeAttribute('data-busy');
      busyBtn(btn, false);
      closeDialog(d);
      O.flash('Codul ' + c.code + ' a fost șters.');
      load();
    }).catch(function (err) {
      d.removeAttribute('data-busy');
      busyBtn(btn, false);
      if (err && err.status === 401) return;
      if (err && err.status === 404) { closeDialog(d); O.flash('Codul fusese deja șters.'); load(); return; }
      formErr('op-del-err', err && (err.status === 0 || err.status == null) ? 'Nu am putut ajunge la server. Verifică conexiunea și încearcă din nou.' : 'Nu am putut șterge codul. Încearcă din nou.');
    });
  });

  /* =================== DIALOGS =================== */
  function openDialog(d, from) {
    opener = from || document.activeElement;
    openerKey = opener && opener.getAttribute ? opener.getAttribute('data-focus') : null;
    if (typeof d.showModal === 'function') d.showModal(); else d.setAttribute('open', '');
  }
  function closeDialog(d) {
    if (!d.open && !d.hasAttribute('open')) return;
    if (typeof d.close === 'function') d.close();
    else { d.removeAttribute('open'); d.dispatchEvent(new Event('close')); }
  }
  qsa('.op-dialog', root).forEach(function (d) {
    d.addEventListener('click', function (e) { if (!d.hasAttribute('data-busy') && e.target.closest('[data-close]')) closeDialog(d); });
    d.addEventListener('cancel', function (e) { if (d.hasAttribute('data-busy')) e.preventDefault(); });
    d.addEventListener('close', function () {
      if (d.id === 'op-usage-d' && usage) usage.seq++;
      if (qsa('.op-dialog', root).some(function (x) { return x.open; })) return;
      var back = opener && document.contains(opener) ? opener : (openerKey && root.querySelector('[data-focus="' + openerKey + '"]')) || $('op-add');
      opener = null;
      openerKey = null;
      if (back && typeof back.focus === 'function' && !back.disabled) back.focus();
    });
  });

  O.ready.then(function (ok) { if (ok) load(); });
})();
