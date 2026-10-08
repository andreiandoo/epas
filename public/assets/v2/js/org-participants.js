/* viaqui.com v2: organizer participants (/organizator/participanti). One activity at a time: its figures and the
   ticket split from core's stats, the list of tickets, check-in per row or by code, undo, CSV export.
   Up to CLIENT_MAX tickets the whole list is loaded and filtered here (search ignores case and diacritics and finds
   codes and phones); above that the list is paged by core. Address: ?event=&q=&checkin=da|nu&tip=&pagina=.
   Runs inside the organizer shell (window.BO_ORG); text from the API is always written as text. */
(function () {
  'use strict';
  var O = window.BO_ORG, root = document.getElementById('op');
  if (!O || !root) return;
  var F = O.fmt, el = O.el, icon = O.icon;
  var $ = function (id) { return document.getElementById(id); };
  function qsa(sel, ctx) { return [].slice.call((ctx || document).querySelectorAll(sel)); }
  var CLIENT_MAX = 1500, FETCH = 100, PER_PAGE = 50;
  var DOOR = ['pos_app', 'venue_owner_pos', 'pos'];
  var SAFE = /^[A-Za-z0-9_.-]{1,200}$/; // what can travel in the check-in address

  var S = { event: '', q: '', checkin: '', type: '', page: 1 };
  var events = [], current = null, mode = 'client', all = [], rows = [], stats = null, listTotal = 0, lastPage = 1;
  var seq = 0, listSeq = 0, statsTimer = 0, searchTimer = 0, undoTarget = null;

  /* =================== HELPERS =================== */
  function norm(s) { return String(s == null ? '' : s).normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase(); }
  function digits(s) { return String(s == null ? '' : s).replace(/\D+/g, ''); }
  function naiveDay(v) { var m = /^(\d{4}-\d{2}-\d{2})/.exec(String(v || '')); return m ? m[1] : ''; }
  function dayLabel(ymd) { var d = F.dateOf(ymd); return d ? F.date(d, { day: 'numeric', month: 'short', year: 'numeric' }) : ''; }
  function stamp(iso, withYear) {
    var d = F.dateOf(iso);
    if (!d) return '';
    return F.date(d, withYear ? { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' } : { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' });
  }
  function maskEmail(email) {
    if (!email) return '';
    var p = String(email).split('@');
    if (p.length !== 2) return String(email);
    var n = p[0];
    return (n.length <= 3 ? n.charAt(0) + '***' : n.slice(0, 2) + '***' + n.slice(-1)) + '@' + p[1];
  }
  function isLive(ev) {
    if (ev.is_cancelled || ev.is_postponed || ev.is_past || ev.is_ended) return false;
    if (ev.status !== 'published' && ev.status !== 'active') return false;
    var end = naiveDay(ev.ends_at || ev.starts_at);
    return !end || end >= F.ymd();
  }
  function evName(e) { return F.flat(e && (e.name || e.title)) || VQ.t('Experience #{id}', { id: e && e.id }); }
  function evMeta(e) { var d = naiveDay(e.starts_at); return [d ? dayLabel(d) : '', F.flat(e.venue_name), F.flat(e.venue_city)].filter(Boolean).join(' · '); }
  function person(t) {
    var c = t.customer || {}, a = t.attendee || {};
    var an = F.flat(a.name).trim(), cn = F.flat(c.name).trim();
    return { name: an || cn, buyer: an && cn && norm(an) !== norm(cn) ? cn : '', email: a.email || c.email || '', phone: String(c.phone || a.phone || '').trim() };
  }
  function active(t) { return t.status !== 'cancelled' && t.status !== 'refunded'; }
  function codeOf(t) { return String(t.code || t.barcode || ''); }
  function initials(name) {
    var w = String(name || '').split(/\s+/).map(function (x) { return x.replace(/[^\p{L}\p{N}]/gu, ''); }).filter(Boolean);
    return ((w.length > 1 ? w[0].charAt(0) + w[w.length - 1].charAt(0) : (w[0] || '').slice(0, 2)) || '?').toUpperCase();
  }
  function participantsUrl(page, perPage, withFilters) {
    var p = new URLSearchParams();
    p.set('page', String(page));
    p.set('per_page', String(perPage));
    if (withFilters) {
      if (S.q) p.set('search', S.q);
      if (S.checkin) p.set('checked_in', S.checkin === 'da' ? '1' : '0');
      if (S.type) p.set('ticket_type_id', S.type);
    }
    return '/organizer/events/' + encodeURIComponent(S.event) + '/participants?' + p.toString();
  }
  function seatLine(tk) {
    if (!tk) return '';
    var parts = [F.flat(tk.section), tk.row ? VQ.t('row {row}', { row: tk.row }) : '', tk.seat ? VQ.t('seat {seat}', { seat: tk.seat }) : ''].filter(Boolean);
    return parts.length ? parts.join(', ') : F.flat(tk.seat_label);
  }
  function notesOf(v) {
    if (!v) return [];
    if (typeof v === 'string') return [v];
    if (!Array.isArray(v)) v = [v];
    return v.map(function (x) { return typeof x === 'string' ? x : x && F.flat(x.text || x.note || x.message || x.body); }).filter(Boolean);
  }
  /** Core's check-in answers are fixed English sentences: the ones people see, through the language layer. "already" is handled by the caller. */
  function reason(err, fallback) {
    var m = String((err && (err.message || (err.data && err.data.message))) || '');
    if (/already checked in/i.test(m)) return 'already';
    if (/not in a valid status/i.test(m)) return VQ.t('The order of this ticket is not paid, so it cannot be checked in.');
    if (/has been cancelled/i.test(m)) return VQ.t('The ticket was cancelled.');
    if (/has been refunded/i.test(m)) return VQ.t('The ticket was refunded.');
    if (/event not found/i.test(m)) return VQ.t('The experience was not found.');
    if (/not found/i.test(m)) return VQ.t('We found no ticket with this code for the chosen experience.');
    if (err && err.status === 0) return VQ.t('We could not reach the server. Check your connection and try again.');
    return fallback;
  }

  /* =================== ADDRESS =================== */
  function readUrl() {
    var p = new URLSearchParams(location.search);
    S.event = /^\d+$/.test(p.get('event') || '') ? p.get('event') : '';
    S.q = (p.get('q') || '').slice(0, 100);
    S.checkin = p.get('checkin') === 'da' || p.get('checkin') === 'nu' ? p.get('checkin') : '';
    S.type = /^\d+$/.test(p.get('tip') || '') ? p.get('tip') : '';
    S.page = Math.max(1, parseInt(p.get('pagina'), 10) || 1);
  }
  function writeUrl() {
    var p = new URLSearchParams();
    if (S.event) p.set('event', S.event);
    if (S.q) p.set('q', S.q);
    if (S.checkin) p.set('checkin', S.checkin);
    if (S.type) p.set('tip', S.type);
    if (S.page > 1) p.set('pagina', String(S.page));
    var url = location.pathname + (p.toString() ? '?' + p.toString() : '') + location.hash;
    if (url !== location.pathname + location.search + location.hash) history.replaceState(null, '', url);
  }
  function syncFilters() {
    qsa('.op-seg-b', root).forEach(function (b) { b.setAttribute('aria-pressed', String(b.getAttribute('data-checkin') === S.checkin)); });
    if ($('op-q').value !== S.q) $('op-q').value = S.q;
    var sel = $('op-type');
    if (S.type && !qsa('option', sel).some(function (o) { return o.value === S.type; })) sel.appendChild(el('option', { value: S.type, text: VQ.t('Type #{id}', { id: S.type }) }));
    sel.value = S.type;
  }
  function filtering() { return !!(S.q || S.checkin || S.type); }

  /* =================== ACTIVITIES + PICKER =================== */
  function loadEvents() {
    var got = [];
    function page(n) {
      return O.api('/organizer/events?per_page=50&page=' + n).then(function (r) {
        got = got.concat(Array.isArray(r && r.data) ? r.data : []);
        if (F.toNum(O.metaOf(r).last_page) > n && n < 60) return page(n + 1);
      });
    }
    return page(1).then(function () {
      events = got.filter(function (e) { return e && e.id != null; }).sort(function (a, b) {
        var al = isLive(a), bl = isLive(b), ad = String(a.starts_at || ''), bd = String(b.starts_at || '');
        if (al !== bl) return al ? -1 : 1;
        return al ? (ad < bd ? -1 : ad > bd ? 1 : 0) : (ad > bd ? -1 : ad < bd ? 1 : 0);
      });
    });
  }
  var combo = { items: [], active: -1, typed: false };
  var input = $('op-event-q'), list = $('op-event-list');
  function comboOpen() { return !list.hidden; }
  function renderOptions() {
    var q = combo.typed ? norm(input.value.trim()) : '';
    combo.items = events.filter(function (e) { return !q || norm(evName(e) + ' ' + evMeta(e)).indexOf(q) > -1; });
    list.textContent = '';
    if (!combo.items.length) {
      list.appendChild(el('li', { class: 'op-opt-empty', role: 'presentation', text: VQ.t('No experience matches the search.') }));
      setActive(-1);
      return;
    }
    combo.items.forEach(function (e, i) {
      var live = isLive(e);
      list.appendChild(el('li', { class: 'op-opt', id: 'op-opt-' + e.id, role: 'option', 'aria-selected': String(String(e.id) === S.event), 'data-i': String(i) }, [
        el('span', { class: 'op-dot' + (live ? ' is-live' : ''), 'aria-hidden': 'true' }),
        el('span', { class: 'op-opt-t' }, [evName(e), el('span', { class: 'sr', text: live ? ' (' + VQ.t('running') + ')' : '' })]),
        el('span', { class: 'op-opt-m', text: evMeta(e) }),
      ]));
    });
    setActive(Math.min(Math.max(combo.active, 0), combo.items.length - 1));
  }
  function setActive(i) {
    combo.active = i;
    qsa('.op-opt', list).forEach(function (li, n) { li.classList.toggle('is-active', n === i); });
    var li = i > -1 ? list.children[i] : null;
    if (li && li.id) {
      input.setAttribute('aria-activedescendant', li.id);
      if (li.scrollIntoView) li.scrollIntoView({ block: 'nearest' });
    } else input.removeAttribute('aria-activedescendant');
  }
  function openCombo(selectText) {
    if (input.disabled || comboOpen()) return;
    combo.typed = false;
    list.hidden = false;
    input.setAttribute('aria-expanded', 'true');
    combo.active = Math.max(0, events.findIndex(function (e) { return String(e.id) === S.event; }));
    renderOptions();
    if (selectText) input.select(); // the first key typed replaces the activity's name
  }
  function closeCombo(restore) {
    list.hidden = true;
    input.setAttribute('aria-expanded', 'false');
    input.removeAttribute('aria-activedescendant');
    combo.typed = false;
    if (restore) input.value = current ? evName(current) : '';
    $('op-event-clear').hidden = true;
  }
  var justFocused = false;
  input.addEventListener('focus', function () { openCombo(true); input.select(); justFocused = true; });
  input.addEventListener('mouseup', function (e) { // the mouseup after a click-to-focus would drop the selection: typing then replaces the name
    if (justFocused) e.preventDefault();
    justFocused = false;
  });
  input.addEventListener('blur', function () { justFocused = false; });
  input.addEventListener('click', function () { if (!comboOpen()) openCombo(true); });
  input.addEventListener('input', function () {
    var fresh = !comboOpen() || !combo.typed;
    if (!comboOpen()) openCombo(false);
    if (fresh && current) { // typed next to the name still in the field (after Escape, say): keep only what was typed
      var name = evName(current), v = input.value;
      if (v.length > name.length && v.indexOf(name) === 0) input.value = v.slice(name.length);
      else if (v.length > name.length && v.lastIndexOf(name) === v.length - name.length) input.value = v.slice(0, v.length - name.length);
    }
    combo.typed = true;
    combo.active = 0;
    $('op-event-clear').hidden = !input.value;
    renderOptions();
  });
  input.addEventListener('keydown', function (e) {
    var open = comboOpen(), n = combo.items.length;
    if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
      e.preventDefault();
      if (!open) { openCombo(true); return; }
      if (n) setActive(e.key === 'ArrowDown' ? (combo.active + 1) % n : (combo.active - 1 + n) % n);
    } else if ((e.key === 'Home' || e.key === 'End') && open && n) {
      e.preventDefault();
      setActive(e.key === 'Home' ? 0 : n - 1);
    } else if (e.key === 'Enter' && open) {
      e.preventDefault();
      if (combo.active > -1 && combo.items[combo.active]) pickEvent(combo.items[combo.active].id);
    } else if (e.key === 'Escape' && open) {
      e.preventDefault();
      closeCombo(true);
    } else if (e.key === 'Tab' && open) {
      closeCombo(true);
    }
  });
  list.addEventListener('mousedown', function (e) { e.preventDefault(); }); // keep the focus in the field
  list.addEventListener('click', function (e) {
    var li = e.target.closest('.op-opt');
    if (li) pickEvent(combo.items[+li.getAttribute('data-i')].id);
  });
  $('op-event-clear').addEventListener('click', function () {
    input.value = '';
    combo.typed = true;
    $('op-event-clear').hidden = true;
    renderOptions();
    input.focus();
  });
  document.addEventListener('pointerdown', function (e) { if (comboOpen() && !$('op-combo').contains(e.target)) closeCombo(true); });
  function pickEvent(id) {
    closeCombo(false);
    if (String(id) === S.event) { input.value = evName(current); input.blur(); return; }
    S.event = String(id);
    S.q = '';
    S.type = '';
    S.page = 1;
    input.blur();
    loadEvent();
  }
  function renderEventSummary() {
    input.value = evName(current);
    var box = $('op-ev');
    box.hidden = false;
    var bits = [current.starts_at ? evMeta(current) : '', current.starts_at ? (isLive(current) ? VQ.t('running') : VQ.t('ended or unpublished')) : ''].filter(Boolean);
    $('op-ev-when').textContent = bits.join(' · ');
    $('op-ev-edit').href = VQ.url('/organizator/activities/' + encodeURIComponent(S.event));
    $('op-ev-sales').href = VQ.url('/organizator/vanzari?event=' + encodeURIComponent(S.event));
    $('op-manual-ev').textContent = evName(current);
  }

  /* =================== LOADING ONE ACTIVITY =================== */
  function setStat(id, text) { $(id).textContent = text; }
  function statsSkeleton() {
    ['op-s-total', 'op-s-in', 'op-s-wait', 'op-s-rate'].forEach(function (id) { $(id).textContent = ''; $(id).appendChild(el('span', { class: 'org-skel op-sk' })); });
    $('op-s-rate-bar').style.width = '0';
    $('op-insights').hidden = true;
  }
  function listSkeleton() {
    var body = $('op-rows');
    body.textContent = '';
    for (var i = 0; i < 4; i++) body.appendChild(el('tr', { class: 'is-skel', 'aria-hidden': 'true' }, el('td', { colspan: '7' }, el('span', { class: 'org-skel op-sk-row' }))));
    $('op-wrap').hidden = false;
    $('op-empty').hidden = true;
    $('op-pager').hidden = true;
    $('op-mode-note').hidden = true;
  }
  function setBusy(on) {
    $('op-table').classList.toggle('is-loading', on);
    $('op-wrap').setAttribute('aria-busy', String(on));
  }
  function loadEvent() {
    var my = ++seq;
    listSeq++;
    clearTimeout(statsTimer);
    current = events.filter(function (e) { return String(e.id) === S.event; })[0] || { id: +S.event, name: VQ.t('Experience #{id}', { id: S.event }) };
    renderEventSummary();
    $('op-manual').disabled = false;
    $('op-export').disabled = false;
    all = [];
    rows = [];
    stats = null;
    mode = 'client';
    syncFilters();
    writeUrl();
    statsSkeleton();
    listSkeleton();
    renderTypeOptions();
    $('op-count').textContent = VQ.t('Loading…');
    setBusy(true);
    O.api(participantsUrl(1, FETCH, false)).then(function (r) {
      if (my !== seq) return;
      var meta = O.metaOf(r), total = Math.round(F.toNum(meta.total)), last = Math.max(1, Math.round(F.toNum(meta.last_page)) || 1);
      stats = meta.stats || null;
      renderStats();
      renderInsights();
      if (total > CLIENT_MAX) { // too many to keep here: core pages, searches and filters the list
        mode = 'server';
        renderTypeOptions();
        renderModeNote(total);
        return loadServerPage(false);
      }
      all = Array.isArray(r && r.data) ? r.data : [];
      return loadRest(my, last, total).then(function () {
        if (my !== seq) return;
        renderTypeOptions();
        renderList(false);
      });
    }).catch(function (err) {
      if (my !== seq || (err && err.status === 401)) return;
      ['op-s-total', 'op-s-in', 'op-s-wait', 'op-s-rate'].forEach(function (id) { setStat(id, '—'); });
      renderError(err && err.status === 404
        ? [VQ.t('The experience was not found'), VQ.t('It may have been deleted or it is not in your account. Choose another experience from the list.'), false]
        : [VQ.t('We could not load the participants'), VQ.t('Check your connection and try again.'), true]);
    }).then(function () { if (my === seq) setBusy(false); });
  }
  function loadRest(my, last, total) { // the other pages, three at a time; a ticket seen twice (the list moved) counts once
    var pages = [], got = {};
    for (var n = 2; n <= last; n++) pages.push(n);
    if (!pages.length) return Promise.resolve();
    function progress() {
      var have = all.length + Object.keys(got).reduce(function (s, k) { return s + got[k].length; }, 0);
      $('op-count').textContent = VQ.t('Loading participants: {done} of {total}…', { done: F.num(Math.min(have, total)), total: F.num(total) });
    }
    progress();
    function next() {
      if (my !== seq) return Promise.resolve();
      var n = pages.shift();
      if (n == null) return Promise.resolve();
      return O.api(participantsUrl(n, FETCH, false)).then(function (r) {
        got[n] = Array.isArray(r && r.data) ? r.data : [];
        if (my === seq) progress();
        return next();
      });
    }
    return Promise.all([next(), next(), next()]).then(function () {
      var seen = {};
      all.forEach(function (t) { seen[t.id] = true; });
      Object.keys(got).sort(function (a, b) { return a - b; }).forEach(function (k) {
        got[k].forEach(function (t) { if (!seen[t.id]) { seen[t.id] = true; all.push(t); } });
      });
    });
  }
  function loadServerPage(focus) {
    var my = ++listSeq, ev = seq;
    setBusy(true);
    writeUrl();
    return O.api(participantsUrl(S.page, PER_PAGE, true)).then(function (r) {
      if (my !== listSeq || ev !== seq) return;
      var meta = O.metaOf(r);
      listTotal = Math.round(F.toNum(meta.total));
      lastPage = Math.max(1, Math.round(F.toNum(meta.last_page)) || 1);
      if (S.page > lastPage && listTotal > 0) { S.page = lastPage; return loadServerPage(focus); }
      rows = Array.isArray(r && r.data) ? r.data : [];
      if (meta.stats) { stats = meta.stats; renderStats(); }
      renderRows();
      renderPager();
      renderCount();
      updateSegCounts();
      if (focus) focusList();
    }).catch(function (err) {
      if (my !== listSeq || ev !== seq || (err && err.status === 401)) return;
      renderError([VQ.t('We could not load the participants'), VQ.t('Check your connection and try again.'), true]);
    }).then(function () { if (my === listSeq && ev === seq) setBusy(false); });
  }
  function renderModeNote(total) {
    var note = $('op-mode-note');
    note.querySelector('span').textContent = VQ.t('The experience has {tickets}, so the list loads page by page. The search is case-sensitive and does not look for ticket codes: for a code, use Manual check-in.', { tickets: VQ.n(total, 'ticket', 'tickets') });
    note.hidden = false;
  }

  /* =================== FIGURES =================== */
  function renderStats() {
    var s = stats || {};
    var total = F.toNum(s.total), inn = F.toNum(s.checked_in);
    var wait = s.not_checked_in != null ? F.toNum(s.not_checked_in) : Math.max(0, total - inn);
    var rate = s.check_in_rate != null ? F.toNum(s.check_in_rate) : (total > 0 ? Math.round(inn / total * 1000) / 10 : 0);
    setStat('op-s-total', F.num(total));
    setStat('op-s-in', F.num(inn));
    setStat('op-s-wait', F.num(wait));
    setStat('op-s-rate', F.pct(rate, 1));
    $('op-s-rate-bar').style.width = Math.min(100, Math.max(0, rate)) + '%';
  }
  function renderInsights() {
    var s = stats;
    $('op-insights').hidden = !s;
    if (!s) return;
    var total = F.toNum(s.total), cap = F.toNum(s.capacity);
    $('op-cap-card').hidden = cap <= 0;
    if (cap > 0) {
      var used = total / cap * 100;
      $('op-cap-h').textContent = VQ.t('{sold} of {capacity}', { sold: F.num(total), capacity: VQ.n(cap, 'place', 'places') });
      $('op-cap-bar').style.width = Math.min(100, used).toFixed(2) + '%';
      $('op-cap-p').textContent = total > cap ? VQ.t('Over capacity by {tickets}.', { tickets: VQ.n(total - cap, 'ticket', 'tickets') })
        : total === cap ? VQ.t('Capacity reached.') : VQ.n(cap - total, 'place left', 'places left') + ' · ' + VQ.t('{percent} taken', { percent: F.pct(used, 0) });
    }
    var on = F.toNum(s.online_count), door = F.toNum(s.door_count), sum = on + door;
    $('op-split').hidden = sum <= 0;
    $('op-split-on').style.width = (sum ? on / sum * 100 : 0).toFixed(2) + '%';
    $('op-split-door').style.width = (sum ? door / sum * 100 : 0).toFixed(2) + '%';
    var src = $('op-src'), by = s.by_source_and_type || {};
    src.textContent = '';
    if (sum <= 0) src.appendChild(el('p', { class: 'op-src-empty', text: VQ.t('No tickets issued yet.') }));
    else [['online', VQ.t('Online'), on, by.online], ['door', VQ.t('At the door'), door, by.door]].forEach(function (g) {
      var types = Array.isArray(g[3]) ? g[3] : [];
      src.appendChild(el('div', { class: 'op-src-g is-' + g[0] }, [
        el('p', { class: 'op-src-k' }, [el('span', null, [el('i', { 'aria-hidden': 'true' }), g[1]]), el('b', { text: F.num(g[2]) + ' · ' + F.pct(g[2] / sum * 100, 0) })]),
        types.length ? el('ul', { class: 'op-src-list' }, types.map(function (t) { return el('li', null, [F.flat(t.name) || VQ.t('Ticket type'), el('b', { text: F.num(t.sold_count) })]); })) : null,
      ]));
    });
    var hours = Array.isArray(s.hourly_distribution) ? s.hourly_distribution.filter(function (h) { return h && /^\d{2}:00$/.test(h.hour); }) : [];
    $('op-hour-card').hidden = !hours.length;
    if (hours.length) {
      var byHour = {}, max = 0, first = 23, lastH = 0;
      hours.forEach(function (h) { var n = +h.hour.slice(0, 2); byHour[n] = F.toNum(h.value); max = Math.max(max, byHour[n]); first = Math.min(first, n); lastH = Math.max(lastH, n); });
      var box = $('op-hours'), label = [];
      box.textContent = '';
      var span = lastH - first + 1;
      for (var hr = first; hr <= lastH; hr++) {
        var v = byHour[hr] || 0, hh = (hr < 10 ? '0' : '') + hr;
        var bar = el('span', { class: 'op-hour-bar' });
        bar.style.setProperty('--h', String(max ? Math.round(v / max * 100) : 0));
        box.appendChild(el('div', { class: 'op-hour' + (s.peak_hour === hh + ':00' ? ' is-peak' : ''), title: hh + ':00 · ' + VQ.n(v, 'check-in', 'check-ins') }, [bar, el('span', { class: 'op-hour-l', text: span > 12 && (hr - first) % 2 ? '' : hh })]));
        if (v) label.push(hh + ':00 ' + F.num(v));
      }
      box.setAttribute('aria-label', VQ.t('Check-ins by hour: {list}', { list: label.join(', ') }));
      $('op-peak').textContent = s.peak_hour ? VQ.t('Peak hour: {hour}', { hour: s.peak_hour }) : '';
    }
  }
  function renderTypeOptions() {
    var sel = $('op-type'), types = {}, order = [];
    function add(id, name, n) {
      if (id == null) return;
      var k = String(id);
      if (!types[k]) { types[k] = { name: name, n: 0 }; order.push(k); }
      if (!types[k].name && name) types[k].name = name;
      types[k].n += n;
    }
    var by = (stats && stats.by_source_and_type) || {};
    (by.online || []).concat(by.door || []).forEach(function (t) { add(t.ticket_type_id, F.flat(t.name), F.toNum(t.sold_count)); });
    all.forEach(function (t) { if (!types[String(t.ticket_type_id)]) add(t.ticket_type_id, F.flat(t.ticket_type), 0); });
    sel.textContent = '';
    sel.appendChild(el('option', { value: '', text: VQ.t('All types') }));
    order.forEach(function (k) { sel.appendChild(el('option', { value: k, text: (types[k].name || VQ.t('Type #{id}', { id: k })) + (types[k].n ? ' (' + F.num(types[k].n) + ')' : '') })); });
    syncFilters();
  }
  function updateSegCounts() {
    var n = { all: '', in: '', wait: '' };
    if (mode === 'client') {
      var q = norm(S.q.trim()), qd = digits(S.q);
      var base = all.filter(function (t) { return (!S.type || String(t.ticket_type_id) === S.type) && matches(t, q, qd); });
      n.all = F.num(base.length);
      n.in = F.num(base.filter(function (t) { return t.checked_in_at; }).length);
      n.wait = F.num(base.filter(function (t) { return !t.checked_in_at && active(t); }).length);
    } else if (stats && !S.q && !S.type) {
      n.all = F.num(stats.total);
      n.in = F.num(stats.checked_in);
      n.wait = F.num(stats.not_checked_in != null ? stats.not_checked_in : F.toNum(stats.total) - F.toNum(stats.checked_in));
    }
    qsa('.op-seg-b b', root).forEach(function (b) { b.textContent = n[b.getAttribute('data-n')]; });
  }
  function bumpStats(delta) {
    if (stats) {
      stats.checked_in = Math.max(0, F.toNum(stats.checked_in) + delta);
      stats.not_checked_in = Math.max(0, F.toNum(stats.total) - stats.checked_in);
      stats.check_in_rate = F.toNum(stats.total) > 0 ? Math.round(stats.checked_in / F.toNum(stats.total) * 1000) / 10 : 0;
      renderStats();
    }
    updateSegCounts();
    refreshStatsSoon();
  }
  function refreshStatsSoon() { // core's own figures (hours, peak) a moment after a change
    clearTimeout(statsTimer);
    var ev = seq;
    statsTimer = setTimeout(function () {
      O.api(participantsUrl(1, 1, false), { quiet: true }).then(function (r) {
        if (ev !== seq) return;
        var st = O.metaOf(r).stats;
        if (st) { stats = st; renderStats(); renderInsights(); updateSegCounts(); }
      }, function () {});
    }, 1500);
  }

  /* =================== LIST =================== */
  function matches(t, q, qd) {
    if (!q) return true;
    var p = person(t), c = t.customer || {};
    if (norm([p.name, F.flat(c.name), p.email, c.email, t.code, t.barcode, t.order_number, F.flat(t.ticket_type)].join(' ')).indexOf(q) > -1) return true;
    return qd.length >= 4 && digits(p.phone).indexOf(qd) > -1;
  }
  function renderList(focus) {
    if (mode === 'server') return loadServerPage(focus);
    var q = norm(S.q.trim()), qd = digits(S.q);
    var found = all.filter(function (t) {
      if (S.checkin === 'da' && !t.checked_in_at) return false;
      if (S.checkin === 'nu' && (t.checked_in_at || !active(t))) return false;
      if (S.type && String(t.ticket_type_id) !== S.type) return false;
      return matches(t, q, qd);
    });
    listTotal = found.length;
    lastPage = Math.max(1, Math.ceil(found.length / PER_PAGE));
    if (S.page > lastPage) S.page = lastPage;
    rows = found.slice((S.page - 1) * PER_PAGE, S.page * PER_PAGE);
    writeUrl();
    renderRows();
    renderPager();
    renderCount();
    updateSegCounts();
    if (focus) focusList();
  }
  function focusList() {
    var h = $('op-list-h');
    h.scrollIntoView({ block: 'start' });
    h.focus({ preventScroll: true });
  }
  function renderCount() {
    var text = filtering() ? (mode === 'client' ? VQ.t('{found} of {total}', { found: VQ.n(listTotal, 'ticket found', 'tickets found'), total: F.num(all.length) }) : VQ.n(listTotal, 'ticket found', 'tickets found')) : VQ.n(listTotal, 'ticket', 'tickets');
    if (lastPage > 1) text += ' · ' + VQ.t('page {page} of {pages}', { page: F.num(S.page), pages: F.num(lastPage) });
    $('op-count').textContent = text;
    $('op-live').textContent = listTotal ? VQ.n(listTotal, 'participant found', 'participants found') + '.' : VQ.t('No participants found.');
  }
  function renderRows() {
    var body = $('op-rows');
    body.textContent = '';
    $('op-empty').hidden = true;
    $('op-wrap').hidden = !rows.length;
    if (!rows.length) { renderEmpty(); return; }
    rows.forEach(function (t) { body.appendChild(rowFor(t)); });
  }
  function rowFor(t) {
    var p = person(t), inv = !!t.is_invitation, door = DOOR.indexOf(t.source) > -1;
    return el('tr', { 'data-id': String(t.id), class: active(t) ? null : 'is-void', tabindex: '-1' }, [
      el('td', { class: 'c-person', 'data-label': VQ.t('Participant') }, el('div', { class: 'op-person' }, [
        el('span', { class: 'op-avatar', 'aria-hidden': 'true', text: initials(p.name) }),
        el('span', { class: 'op-person-t' }, [
          el('span', { class: 'op-name', text: p.name || '—', title: p.name || null }),
          p.email ? el('span', { class: 'op-sub', text: maskEmail(p.email) }) : null,
          p.buyer ? el('span', { class: 'op-sub', text: VQ.t('Buyer: {name}', { name: p.buyer }), title: p.buyer }) : null,
        ]),
      ])),
      el('td', { class: 'c-phone', 'data-label': VQ.t('Phone') }, p.phone && digits(p.phone).length >= 6
        ? el('a', { class: 'op-tel', href: 'tel:' + p.phone.replace(/[^\d+]/g, ''), text: p.phone })
        : el('span', { class: 'op-sub', text: p.phone || '—' })),
      el('td', { class: 'c-code', 'data-label': VQ.t('Ticket') }, [el('code', { class: 'op-code', text: codeOf(t) || '—' }), el('span', { class: 'op-sub', text: '#' + t.id })]),
      el('td', { class: 'c-type', 'data-label': VQ.t('Ticket type') }, [
        el('span', { class: 'op-type' + (inv ? ' is-inv' : ''), text: F.flat(t.ticket_type) || '—' }),
        inv ? el('span', { class: 'op-badge', text: VQ.t('Invitation') }) : el('span', { class: 'op-sub', text: door ? VQ.t('Sold at the door') : VQ.t('Sold online') }),
      ]),
      el('td', { class: 'c-order', 'data-label': VQ.t('Order') }, [
        el('span', { class: 'op-order' + (inv ? ' is-inv' : ''), text: inv ? VQ.t('Invitation') : (t.order_number || '—') }),
        F.dateOf(t.purchased_at) ? el('span', { class: 'op-sub', text: F.date(F.dateOf(t.purchased_at), { day: '2-digit', month: '2-digit', year: 'numeric' }) }) : null,
      ]),
      el('td', { class: 'c-status', 'data-label': VQ.t('Status') }, statusCell(t)),
      el('td', { class: 'c-act' }, actionCell(t, p)),
    ]);
  }
  function statusCell(t) {
    var box = el('div', { class: 'op-status' });
    if (t.status === 'cancelled') box.appendChild(el('span', { class: 'org-tag is-bad', text: VQ.t('Cancelled') }));
    else if (t.status === 'refunded') box.appendChild(el('span', { class: 'org-tag is-muted', text: VQ.t('Refunded') }));
    if (t.checked_in_at) {
      box.appendChild(el('span', { class: 'org-tag is-ok' }, [icon('check'), VQ.t('Checked in')]));
      box.appendChild(el('span', { class: 'op-sub', text: stamp(t.checked_in_at) + (t.checked_in_by ? ' · ' + F.flat(t.checked_in_by) : '') }));
    } else if (active(t)) {
      box.appendChild(el('span', { class: 'org-tag is-wait' }, [icon('clock'), VQ.t('Waiting')]));
    }
    return box;
  }
  function actionCell(t, p) {
    if (!active(t)) return null;
    var who = p.name || VQ.t('ticket {code}', { code: codeOf(t) });
    if (!t.checked_in_at) {
      if (!SAFE.test(codeOf(t))) return null;
      return el('button', { class: 'op-act is-in', type: 'button', 'data-act': 'in', 'data-id': String(t.id), 'aria-label': VQ.t('Check in {who}', { who: who }) }, [icon('check'), VQ.t('Check in')]);
    }
    // core undoes a check-in only for a ticket with an order, found by its exact barcode
    if (t.is_invitation || !t.order_number || !SAFE.test(String(t.barcode || ''))) return null;
    return el('button', { class: 'op-act is-undo', type: 'button', 'data-act': 'undo', 'data-id': String(t.id), 'aria-label': VQ.t('Undo the check-in of {who}', { who: who }) }, VQ.t('Undo'));
  }
  function findTicket(id) {
    var k = String(id);
    return all.filter(function (t) { return String(t.id) === k; })[0] || rows.filter(function (t) { return String(t.id) === k; })[0] || null;
  }
  function replaceRow(t, focusAction) {
    var old = $('op-rows').querySelector('tr[data-id="' + String(t.id).replace(/"/g, '') + '"]');
    if (!old) return;
    var fresh = rowFor(t);
    old.replaceWith(fresh);
    if (focusAction) (fresh.querySelector('.op-act') || fresh).focus();
  }
  function renderEmpty() {
    var box = $('op-empty');
    box.textContent = '';
    box.classList.remove('is-error');
    box.appendChild(el('span', { class: 'org-empty-ic' }, icon('users-three')));
    if (filtering()) {
      box.appendChild(el('b', { text: VQ.t('No participant matches the filters') }));
      box.appendChild(el('p', { text: VQ.t('Change the check-in filter, the ticket type or the search.') }));
      var reset = el('button', { class: 'btn btn-ghost', type: 'button', text: VQ.t('Show all participants') });
      reset.addEventListener('click', function () { S.q = ''; S.checkin = ''; S.type = ''; S.page = 1; syncFilters(); renderList(false); $('op-q').focus(); });
      box.appendChild(el('div', { class: 'op-empty-cta' }, reset));
    } else {
      box.appendChild(el('b', { text: VQ.t('This experience has no participants') }));
      box.appendChild(el('p', { text: VQ.t('Sold tickets and invitations show up here as soon as they are issued.') }));
    }
    box.hidden = false;
    $('op-pager').hidden = true;
  }
  function renderError(msg) {
    $('op-rows').textContent = '';
    $('op-wrap').hidden = true;
    $('op-pager').hidden = true;
    $('op-count').textContent = '';
    var box = $('op-empty');
    box.textContent = '';
    box.classList.add('is-error');
    box.appendChild(el('span', { class: 'org-empty-ic' }, icon('warning-circle')));
    box.appendChild(el('b', { text: msg[0] }));
    box.appendChild(el('p', { text: msg[1] }));
    if (msg[2]) {
      var retry = el('button', { class: 'btn btn-primary', type: 'button', text: VQ.t('Try again') });
      retry.addEventListener('click', function () { loadEvent(); });
      box.appendChild(el('div', { class: 'op-empty-cta' }, retry));
    }
    box.hidden = false;
  }
  function renderPager() {
    var nav = $('op-pager'), box = $('op-pages'), cur = Math.min(S.page, lastPage);
    nav.hidden = lastPage <= 1;
    box.textContent = '';
    if (lastPage <= 1) return;
    $('op-page-info').textContent = VQ.t('Page {page} of {pages}', { page: F.num(cur), pages: F.num(lastPage) });
    function go(n, label, attrs) {
      var b = el('button', Object.assign({ class: 'op-pg', type: 'button' }, attrs || {}), label);
      b.addEventListener('click', function () { if (n !== S.page) { S.page = n; renderList(true); } });
      return b;
    }
    box.appendChild(go(cur - 1, VQ.t('Previous'), { disabled: cur <= 1, 'aria-label': VQ.t('Previous page') }));
    var pages = [1, cur - 1, cur, cur + 1, lastPage].filter(function (n, i, a) { return n >= 1 && n <= lastPage && a.indexOf(n) === i; }).sort(function (a, b) { return a - b; });
    pages.forEach(function (n, i) {
      if (i && n - pages[i - 1] > 1) box.appendChild(el('span', { class: 'op-gap', 'aria-hidden': 'true', text: '…' }));
      box.appendChild(go(n, F.num(n), n === cur ? { 'aria-current': 'page', 'aria-label': VQ.t('Page {n}, current page', { n: n }) } : { 'aria-label': VQ.t('Page {n}', { n: n }) }));
    });
    box.appendChild(go(cur + 1, VQ.t('Next'), { disabled: cur >= lastPage, 'aria-label': VQ.t('Next page') }));
  }
  function showNoEvents() {
    input.placeholder = VQ.t('You have no experiences yet');
    $('op-stats').hidden = true;
    $('op-insights').hidden = true;
    $('op-list').querySelector('.op-filters').hidden = true;
    $('op-wrap').hidden = true;
    $('op-count').textContent = '';
    var box = $('op-empty');
    box.textContent = '';
    box.appendChild(el('span', { class: 'org-empty-ic' }, icon('calendar-blank')));
    box.appendChild(el('b', { text: VQ.t('You have no experiences yet') }));
    box.appendChild(el('p', { text: VQ.t('Participants show up here after you create an experience and sell the first tickets.') }));
    box.appendChild(el('div', { class: 'op-empty-cta' }, el('a', { class: 'btn btn-primary', href: VQ.url('/organizator/activities?action=create'), text: VQ.t('Create an experience') })));
    box.hidden = false;
  }
  function showEventsError() {
    input.placeholder = VQ.t('We could not load the experiences');
    $('op-wrap').hidden = true;
    $('op-count').textContent = '';
    ['op-s-total', 'op-s-in', 'op-s-wait', 'op-s-rate'].forEach(function (id) { setStat(id, '—'); });
    var box = $('op-empty');
    box.textContent = '';
    box.classList.add('is-error');
    box.appendChild(el('span', { class: 'org-empty-ic' }, icon('warning-circle')));
    box.appendChild(el('b', { text: VQ.t('We could not load the experiences') }));
    box.appendChild(el('p', { text: VQ.t('Check your connection and try again.') }));
    var retry = el('button', { class: 'btn btn-primary', type: 'button', text: VQ.t('Try again') });
    retry.addEventListener('click', start);
    box.appendChild(el('div', { class: 'op-empty-cta' }, retry));
    box.hidden = false;
  }

  /* =================== CHECK-IN =================== */
  function markChecked(tk) { // a ticket core just checked in (or had checked in): the row and the figures follow
    if (!tk || tk.id == null) { refreshStatsSoon(); return; }
    var t = findTicket(tk.id);
    if (!t) { refreshStatsSoon(); return false; }
    var was = !!t.checked_in_at;
    t.checked_in_at = tk.checked_in_at || t.checked_in_at || new Date().toISOString();
    t.checked_in_by = tk.checked_in_by || t.checked_in_by || null;
    replaceRow(t, false);
    if (!was) bumpStats(1); else refreshStatsSoon();
    return true;
  }
  function checkInRequest(code) {
    return O.api('/organizer/events/' + encodeURIComponent(S.event) + '/check-in/' + code, { method: 'POST' });
  }
  $('op-rows').addEventListener('click', function (e) {
    var b = e.target.closest('.op-act');
    if (!b || b.getAttribute('aria-busy') === 'true') return;
    var t = findTicket(b.getAttribute('data-id'));
    if (!t) return;
    if (b.getAttribute('data-act') === 'undo') { openUndo(t, b); return; }
    var name = person(t).name || codeOf(t), ev = seq;
    b.setAttribute('aria-busy', 'true');
    checkInRequest(codeOf(t)).then(function (r) {
      if (ev !== seq) return;
      var tk = (r && r.data && r.data.ticket) || { id: t.id };
      t.checked_in_at = tk.checked_in_at || new Date().toISOString();
      t.checked_in_by = tk.checked_in_by || null;
      replaceRow(t, true);
      bumpStats(1);
      O.flash(VQ.t('Checked in: {who}.', { who: name + (t.ticket_type ? ' · ' + F.flat(t.ticket_type) : '') }));
    }).catch(function (err) {
      if (ev !== seq || (err && err.status === 401)) return;
      b.removeAttribute('aria-busy');
      var why = reason(err, VQ.t('We could not check in the ticket. Try again.'));
      if (why === 'already') {
        var tk = (err.data && err.data.ticket) || {};
        markChecked(Object.assign({ id: t.id }, tk));
        O.flash(tk.checked_in_at ? VQ.t('The ticket of {name} was already checked in at {time}.', { name: name, time: stamp(tk.checked_in_at, true) }) : VQ.t('The ticket of {name} was already checked in.', { name: name }), true);
        return;
      }
      O.flash(why, true);
    });
  });

  /* manual check-in: the dialog stays open, ready for the next code */
  var manual = $('op-manual-d'), codeInput = $('op-code'), opener = null;
  function openDialog(d, from) {
    opener = from || document.activeElement;
    if (typeof d.showModal === 'function') d.showModal(); else d.setAttribute('open', '');
  }
  function closeDialog(d) {
    if (typeof d.close === 'function') d.close(); else d.removeAttribute('open');
  }
  [manual, $('op-undo-d')].forEach(function (d) {
    d.addEventListener('close', function () { if (opener && document.contains(opener)) opener.focus(); opener = null; });
    d.addEventListener('click', function (e) { if (e.target === d || e.target.closest('[data-close]')) closeDialog(d); });
  });
  function codeError(msg) {
    $('op-code-err').textContent = msg || '';
    $('op-code-err').hidden = !msg;
    if (msg) codeInput.setAttribute('aria-invalid', 'true'); else codeInput.removeAttribute('aria-invalid');
  }
  $('op-manual').addEventListener('click', function () {
    if (!S.event) return;
    codeError('');
    $('op-result').hidden = true;
    codeInput.value = '';
    openDialog(manual, this);
    codeInput.focus();
  });
  codeInput.addEventListener('input', function () { if (!$('op-code-err').hidden) codeError(''); });
  function renderResult(kind, d) {
    var box = $('op-result'), tk = (d && d.ticket) || {}, cu = (d && d.customer) || {}, lines = [];
    box.textContent = '';
    box.className = 'op-result is-' + kind;
    if (kind === 'bad') lines.push(d.message);
    else {
      lines.push(F.flat(tk.attendee_name) || F.flat(cu.name));
      lines.push([F.flat(tk.ticket_type), tk.is_invitation ? VQ.t('Invitation') : '', seatLine(tk)].filter(Boolean).join(' · '));
      if (kind === 'dup' && tk.checked_in_at) lines.push(tk.checked_in_by ? VQ.t('Checked in at {time}, by {who}.', { time: stamp(tk.checked_in_at, true), who: F.flat(tk.checked_in_by) }) : VQ.t('Checked in at {time}.', { time: stamp(tk.checked_in_at, true) }));
      notesOf(d.venue_notes).forEach(function (n) { lines.push(VQ.t('Note: {note}', { note: n })); });
    }
    box.appendChild(el('span', { class: 'op-result-ic', 'aria-hidden': 'true' }, icon(kind === 'ok' ? 'check-circle' : kind === 'dup' ? 'clock' : 'warning-circle')));
    box.appendChild(el('div', { class: 'op-result-t' }, [el('b', { text: kind === 'ok' ? VQ.t('Checked in') : kind === 'dup' ? VQ.t('The ticket was already checked in') : VQ.t('Check-in refused') })].concat(lines.filter(Boolean).map(function (l) { return el('span', { text: l }); }))));
    box.hidden = false;
  }
  $('op-manual-form').addEventListener('submit', function (e) {
    e.preventDefault();
    var go = $('op-code-go');
    if (go.getAttribute('aria-busy') === 'true') return;
    var raw = codeInput.value.trim(), m = /\/(?:v|t|verify)\/([A-Za-z0-9_-]+)/.exec(raw), code = m ? m[1] : raw.replace(/\s+/g, '');
    if (!code) { codeError(VQ.t('Enter the code on the ticket.')); codeInput.focus(); return; }
    if (!SAFE.test(code)) { codeError(VQ.t('The code can only have letters, digits, hyphens, dots or underscores.')); codeInput.focus(); return; }
    codeError('');
    var ev = seq, label = go.querySelector('[data-label]');
    go.setAttribute('aria-busy', 'true');
    label.textContent = VQ.t('Checking…');
    checkInRequest(code).then(function (r) {
      if (ev !== seq) return;
      var d = (r && r.data) || {};
      markChecked(d.ticket);
      renderResult('ok', d);
      codeInput.value = '';
    }).catch(function (err) {
      if (ev !== seq || (err && err.status === 401)) return;
      var why = reason(err, VQ.t('We could not check in the ticket. Try again.'));
      if (why === 'already') {
        var d = err.data || {};
        markChecked(d.ticket);
        renderResult('dup', d);
        codeInput.value = '';
        return;
      }
      renderResult('bad', { message: why });
      codeInput.select();
    }).then(function () {
      go.removeAttribute('aria-busy');
      label.textContent = VQ.t('Verify and check in');
      if (manual.open) codeInput.focus();
    });
  });

  /* undo */
  function openUndo(t, from) {
    undoTarget = t;
    var p = person(t);
    $('op-undo-p').textContent = p.name ? VQ.t('Ticket {code} of {name} will show as "Waiting" again and can be scanned once more.', { code: codeOf(t), name: p.name }) : VQ.t('Ticket {code} will show as "Waiting" again and can be scanned once more.', { code: codeOf(t) });
    openDialog($('op-undo-d'), from);
    $('op-undo-d').querySelector('[data-close]').focus();
  }
  $('op-undo-ok').addEventListener('click', function () {
    var b = this, t = undoTarget;
    if (!t || b.getAttribute('aria-busy') === 'true') return;
    var name = person(t).name || codeOf(t), ev = seq;
    b.setAttribute('aria-busy', 'true');
    O.api('/organizer/events/' + encodeURIComponent(S.event) + '/check-in/' + t.barcode, { method: 'DELETE' }).then(function () {
      if (ev !== seq) return;
      t.checked_in_at = null;
      t.checked_in_by = null;
      opener = null;
      closeDialog($('op-undo-d'));
      replaceRow(t, true);
      bumpStats(-1);
      O.flash(VQ.t('Check-in undone for {name}.', { name: name }));
    }).catch(function (err) {
      if (ev !== seq || (err && err.status === 401)) return;
      var m = String((err && err.message) || '');
      closeDialog($('op-undo-d'));
      if (/not checked in/i.test(m)) {
        t.checked_in_at = null;
        opener = null;
        replaceRow(t, true);
        bumpStats(-1);
        O.flash(VQ.t('The ticket of {name} was no longer checked in.', { name: name }));
        return;
      }
      O.flash(/not found/i.test(m) ? VQ.t('The check-in of this ticket cannot be undone from here. Write to support and we will undo it.') : VQ.t('We could not undo the check-in. Try again.'), true);
    }).then(function () { b.removeAttribute('aria-busy'); });
  });

  /* =================== EXPORT =================== */
  function records(text) {
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
  function slug(s) { return norm(s).replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 60) || 'experience'; }
  $('op-export').addEventListener('click', function () {
    var btn = this;
    if (!S.event || btn.getAttribute('aria-busy') === 'true') return;
    var token = typeof BileteOnlineAuth !== 'undefined' && BileteOnlineAuth.getToken ? BileteOnlineAuth.getToken() : null;
    if (!token) { O.flash(VQ.t('Your session has expired. Sign in again.'), true); return; }
    var label = btn.querySelector('[data-label]'), name = evName(current);
    btn.setAttribute('aria-busy', 'true');
    label.textContent = VQ.t('Generating…');
    var base = (window.BILETEONLINE && window.BILETEONLINE.apiUrl) || '/api/proxy.php';
    fetch(base + '?action=organizer.event.participants.export&event_id=' + encodeURIComponent(S.event), { headers: { Authorization: 'Bearer ' + token, Accept: 'text/csv' } }).then(function (res) {
      if (!res.ok) { var e = new Error('export'); e.status = res.status; throw e; }
      return res.text();
    }).then(function (t) {
      // core's file starts with its "Data cumparare,..." header; anything else (a sign-in page, an error) is never saved
      var body = String(t || '').replace(/^﻿/, ''), head = body.replace(/^\s+/, '');
      if (head.indexOf('Data cumparare') !== 0) { var e = new Error('export'); e.html = head.charAt(0) === '<'; throw e; }
      var n = Math.max(0, records(body).length - 1);
      if (!n) { O.flash(VQ.t('The experience has no participants to export yet.')); return; }
      var blob = new Blob(['﻿' + body], { type: 'text/csv;charset=utf-8' });
      var url = URL.createObjectURL(blob), a = el('a', { href: url, download: 'participants-' + slug(name) + '-' + F.ymd() + '.csv', hidden: true });
      document.body.appendChild(a);
      a.click();
      setTimeout(function () { URL.revokeObjectURL(url); a.remove(); }, 1500);
      O.flash(VQ.t('The participant list was exported: {tickets}, each with its seat and check-in.', { tickets: VQ.n(n, 'ticket', 'tickets') }));
    }).catch(function (err) {
      function failed() { O.flash(VQ.t('We could not generate the export. Try again.'), true); }
      function signOut() {
        O.flash(VQ.t('Your session has expired. Taking you to sign in.'), true);
        setTimeout(function () { O.api('/organizer/me').catch(function () {}); }, 1500);
      }
      if (err && err.status === 401) return signOut();
      if (err && err.status === 404) { O.flash(VQ.t('The experience was not found.'), true); return; }
      if (err && err.html) return O.api('/organizer/me', { quiet: true }).then(failed, function (e) { if (e && e.status === 401) signOut(); else failed(); });
      failed();
    }).then(function () {
      btn.removeAttribute('aria-busy');
      label.textContent = VQ.t('Export CSV');
    });
  });

  /* =================== FILTERS =================== */
  qsa('.op-seg-b', root).forEach(function (b) {
    b.addEventListener('click', function () {
      var v = b.getAttribute('data-checkin');
      if (v === S.checkin) return;
      S.checkin = v;
      S.page = 1;
      syncFilters();
      renderList(false);
    });
  });
  $('op-type').addEventListener('change', function () { S.type = this.value; S.page = 1; renderList(false); });
  $('op-q').addEventListener('input', function () {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(function () { S.q = $('op-q').value.trim().slice(0, 100); S.page = 1; renderList(false); }, mode === 'server' ? 400 : 150);
  });

  /* =================== START =================== */
  function start() {
    $('op-empty').hidden = true;
    loadEvents().then(function () {
      if (!events.length && !S.event) { showNoEvents(); return; }
      input.disabled = false;
      input.placeholder = VQ.t('Search experiences…');
      if (!S.event) {
        var first = events.filter(isLive)[0] || events[0];
        S.event = String(first.id);
        S.q = '';
        S.checkin = S.checkin || '';
        S.type = '';
        S.page = 1;
      }
      loadEvent();
    }, function (err) {
      if (err && err.status === 401) return;
      showEventsError();
    });
  }
  O.ready.then(function (ok) {
    if (!ok) return;
    readUrl(); // an activity in the address keeps the search, filter, type and page asked with it
    syncFilters();
    start();
  });
})();
