/* bilete.online v2: the venue's team and rota (/organizator/locatie/echipa), ported from Ambilet.
   Three things the core keeps apart: the venue's own people, who carry a code the scanning app reads when they come
   to work; the weekly rota, which is built on the account's members; and the timesheet those scans produce, which
   the core also gives as a CSV. Runs inside the organizer shell (window.BO_ORG); text from the API is always written
   as text. */
(function () {
  'use strict';
  var O = window.BO_ORG, root = document.getElementById('ve');
  if (!O || !root) return;
  var F = O.fmt, el = O.el;
  var $ = function (id) { return document.getElementById(id); };
  var ROLES = {
    gate_scanner: 'Scanare la poartă', sales_operator: 'Vânzare', shift_manager: 'Șef de tură', accountant: 'Contabilitate',
    operator_boats: 'Bărci', operator_pontoon: 'Ponton', operator_pontoon_rental: 'Închirieri ponton', operator_sled: 'Sanie',
    operator_tow_validation: 'Validare teleschi', admin_mobile: 'Administrare mobilă', field_seller: 'Vânzare pe teren',
  };
  var DAYS = ['Luni', 'Marți', 'Miercuri', 'Joi', 'Vineri', 'Sâmbătă', 'Duminică'];
  var eventId = null, staff = [], members = [], shifts = [], weekStart = monday(new Date());
  var editStaff = null, editShift = null, lastFocus = null, timesLoaded = false;

  function txt(v) { return F.flat(v).trim(); }
  function stamp(v) { var d = F.dateOf(v); return d ? F.date(d, { day: '2-digit', month: '2-digit', year: '2-digit', hour: '2-digit', minute: '2-digit' }) : '—'; }
  function hhmm(v) { var d = F.dateOf(v); return d ? F.date(d, { hour: '2-digit', minute: '2-digit' }) : '—'; }
  function dayLabel(d) { return F.date(d, { day: 'numeric', month: 'short' }); }
  function show(id, on) { var e = $(id); if (e) e.hidden = !on; }
  function monday(d) {
    var r = new Date(d.getFullYear(), d.getMonth(), d.getDate());
    r.setDate(r.getDate() - ((r.getDay() + 6) % 7));
    return r;
  }
  function addDays(d, n) { var r = new Date(d); r.setDate(r.getDate() + n); return r; }
  function localInput(d) {
    var p = function (n) { return String(n).padStart(2, '0'); };
    return d.getFullYear() + '-' + p(d.getMonth() + 1) + '-' + p(d.getDate()) + 'T' + p(d.getHours()) + ':' + p(d.getMinutes());
  }
  function state(bodyId, cols, text) {
    var body = $(bodyId);
    body.textContent = '';
    body.appendChild(el('tr', null, el('td', { colspan: cols, class: 've-state', text: text })));
  }
  function openModal(id) { lastFocus = document.activeElement; show(id, true); }
  function closeModal(id) {
    show(id, false);
    if (lastFocus && lastFocus.focus) lastFocus.focus();
  }

  /* =================== the venue's people =================== */
  function loadStaff() {
    state('vt-people', 7, 'Se încarcă…');
    return O.api('/organizer/leisure/staff').then(function (r) {
      staff = ((r && r.data && r.data.staff) || []).filter(function (s) { return s && s.id != null; });
      drawStaff();
      fillStaffFilter();
    }, function (err) {
      if (err && err.status === 401) return;
      state('vt-people', 7, 'Nu am putut încărca oamenii locației.');
    });
  }
  function drawStaff() {
    var body = $('vt-people');
    body.textContent = '';
    if (!staff.length) {
      state('vt-people', 7, 'Niciun om adăugat încă. Adaugă-i ca să se poată ponta.');
      $('vt-people-count').textContent = '';
      return;
    }
    staff.forEach(function (s) {
      var edit = el('button', { class: 've-icon-btn', type: 'button', 'aria-label': 'Schimbă datele' });
      edit.appendChild(O.icon('pencil-simple'));
      edit.addEventListener('click', function () { openStaff(s); });
      var name = el('td', null, [el('b', { text: txt(s.full_name) || (txt(s.first_name) + ' ' + txt(s.last_name)).trim() || '—' })]);
      if (!s.active) name.appendChild(el('small', { class: 've-sub', text: 'scos din echipă' }));
      body.appendChild(el('tr', { class: s.active ? '' : 'is-off' }, [
        name,
        el('td', { text: txt(s.position) || '—' }),
        el('td', { text: txt(s.phone) || '—' }),
        el('td', null, txt(s.qr_code) ? el('span', { class: 've-qr' }, [O.icon('qr-code'), el('span', { class: 've-mono', text: txt(s.qr_code) })]) : document.createTextNode('—')),
        el('td', { class: 've-r', text: F.num(F.toNum(s.checkins_count)) }),
        el('td', { text: s.last_checkin_at ? stamp(s.last_checkin_at) : '—' }),
        el('td', null, edit),
      ]));
    });
    var active = staff.filter(function (s) { return s.active; }).length;
    $('vt-people-count').textContent = F.count(staff.length, 'om', 'oameni') + ', din care ' + F.num(active) + ' în activitate.';
  }
  function openStaff(s) {
    editStaff = s || null;
    $('vt-modal-h').textContent = s ? 'Schimbă datele omului' : 'Adaugă un om';
    $('vt-f-first').value = s ? txt(s.first_name) : '';
    $('vt-f-last').value = s ? txt(s.last_name) : '';
    $('vt-f-phone').value = s ? txt(s.phone) : '';
    $('vt-f-pos').value = s ? txt(s.position) : '';
    $('vt-f-notes').value = s ? txt(s.notes) : '';
    var qr = $('vt-f-qr');
    qr.textContent = '';
    if (s && txt(s.qr_code)) {
      qr.appendChild(document.createTextNode('Codul lui de pontaj: '));
      qr.appendChild(el('b', { class: 've-mono', text: txt(s.qr_code) }));
      qr.appendChild(document.createTextNode(' — rămâne același.'));
    }
    qr.hidden = !(s && txt(s.qr_code));
    show('vt-f-off', !!(s && s.active));
    openModal('vt-modal');
    $('vt-f-first').focus();
  }
  function saveStaff() {
    var first = $('vt-f-first').value.trim(), last = $('vt-f-last').value.trim();
    if (!first || !last) { O.flash('Scrie prenumele și numele.', true); return; }
    var body = {
      first_name: first, last_name: last,
      phone: $('vt-f-phone').value.trim() || null,
      position: $('vt-f-pos').value.trim() || null,
      notes: $('vt-f-notes').value.trim() || null,
    };
    var btn = $('vt-f-save');
    btn.disabled = true;
    var req = editStaff
      ? O.api('/organizer/leisure/staff/' + F.toNum(editStaff.id), { method: 'PUT', body: body })
      : O.api('/organizer/leisure/staff', { method: 'POST', body: body });
    req.then(function () {
      closeModal('vt-modal');
      O.flash(editStaff ? 'Datele au fost schimbate.' : 'Omul a fost adăugat. Codul lui de pontaj e în listă.');
      loadStaff();
    }, function (err) {
      O.flash((err && err.message) || 'Nu am putut salva.', true);
    }).then(function () { btn.disabled = false; });
  }
  function offStaff() {
    if (!editStaff) return;
    var btn = $('vt-f-off');
    btn.disabled = true;
    O.api('/organizer/leisure/staff/' + F.toNum(editStaff.id), { method: 'DELETE' }).then(function () {
      closeModal('vt-modal');
      O.flash('Omul a fost scos din echipă. Pontajele lui rămân în raport.');
      loadStaff();
    }, function (err) {
      O.flash((err && err.message) || 'Nu am putut scoate omul din echipă.', true);
    }).then(function () { btn.disabled = false; });
  }

  /* =================== the weekly rota =================== */
  function loadWeek() {
    if (!eventId) return;
    $('vt-week-label').textContent = dayLabel(weekStart) + ' – ' + dayLabel(addDays(weekStart, 6));
    state('vt-week', 8, 'Se încarcă…');
    O.api('/organizer/events/' + eventId + '/leisure/shifts?week=' + F.ymd(weekStart)).then(function (r) {
      var d = (r && r.data) || {};
      members = Array.isArray(d.members) ? d.members : [];
      shifts = Array.isArray(d.shifts) ? d.shifts : [];
      drawWeek();
    }, function (err) {
      if (err && err.status === 401) return;
      state('vt-week', 8, 'Nu am putut încărca programul.');
    });
  }
  function drawWeek() {
    var head = $('vt-week-head');
    head.textContent = '';
    head.appendChild(el('th', { scope: 'col', text: 'Membru' }));
    DAYS.forEach(function (name, i) {
      head.appendChild(el('th', { scope: 'col' }, [el('span', { text: name }), el('small', { class: 've-sub', text: dayLabel(addDays(weekStart, i)) })]));
    });
    var body = $('vt-week');
    body.textContent = '';
    if (!members.length) {
      state('vt-week', 8, 'Niciun membru activ în cont. Adaugă-i din Echipă, apoi le poți da ture.');
      return;
    }
    var bucket = {};
    shifts.forEach(function (s) {
      var d = F.dateOf(s.start_at);
      if (!d) return;
      var idx = Math.floor((new Date(d.getFullYear(), d.getMonth(), d.getDate()) - weekStart) / 86400000);
      if (idx < 0 || idx > 6) return;
      var key = (s.team_member_id == null ? 'x' : s.team_member_id) + '_' + idx;
      (bucket[key] = bucket[key] || []).push(s);
    });
    members.forEach(function (m) {
      var tr = el('tr', null, el('td', null, [el('b', { text: txt(m.name) || '—' }), el('small', { class: 've-sub', text: txt(m.email) })]));
      for (var i = 0; i < 7; i++) {
        tr.appendChild(dayCell(m, i, bucket[m.id + '_' + i] || []));
      }
      body.appendChild(tr);
    });
    var loose = [];
    for (var i2 = 0; i2 < 7; i2++) loose.push(bucket['x_' + i2] || []);
    if (loose.some(function (l) { return l.length; })) {
      var tr2 = el('tr', null, el('td', null, el('b', { text: 'Fără membru' })));
      loose.forEach(function (list, idx) { tr2.appendChild(dayCell(null, idx, list)); });
      body.appendChild(tr2);
    }
  }
  function dayCell(member, dayIndex, list) {
    var td = el('td', { class: 've-cell' });
    list.forEach(function (s) {
      var chip = el('button', { class: 've-chip', type: 'button', 'data-role': txt(s.role) });
      chip.appendChild(el('b', { text: ROLES[s.role] || txt(s.role) || 'Tură' }));
      chip.appendChild(el('span', { text: hhmm(s.start_at) + '–' + hhmm(s.end_at) + (txt(s.gate) ? ' · ' + txt(s.gate) : '') }));
      chip.addEventListener('click', function (ev) { ev.stopPropagation(); openShift(s, null, null); });
      td.appendChild(chip);
    });
    var add = el('button', { class: 've-add', type: 'button', 'aria-label': 'Adaugă o tură' });
    add.appendChild(O.icon('plus'));
    add.addEventListener('click', function () { openShift(null, member, addDays(weekStart, dayIndex)); });
    td.appendChild(add);
    return td;
  }
  function openShift(s, member, date) {
    editShift = s || null;
    $('vs-modal-h').textContent = s ? 'Schimbă tura' : 'Adaugă o tură';
    var sel = $('vs-f-member');
    sel.textContent = '';
    sel.appendChild(new Option('Fără membru', ''));
    members.forEach(function (m) { sel.appendChild(new Option(txt(m.name) || ('Membru #' + m.id), String(m.id))); });
    if (s) {
      sel.value = s.team_member_id == null ? '' : String(s.team_member_id);
      var sd = F.dateOf(s.start_at), ed = F.dateOf(s.end_at);
      $('vs-f-start').value = sd ? localInput(sd) : '';
      $('vs-f-end').value = ed ? localInput(ed) : '';
      $('vs-f-role').value = ROLES[s.role] ? s.role : 'gate_scanner';
      $('vs-f-gate').value = txt(s.gate);
      $('vs-f-notes').value = txt(s.notes);
    } else {
      sel.value = member ? String(member.id) : '';
      var day = date || new Date();
      $('vs-f-start').value = localInput(new Date(day.getFullYear(), day.getMonth(), day.getDate(), 9, 0));
      $('vs-f-end').value = localInput(new Date(day.getFullYear(), day.getMonth(), day.getDate(), 17, 0));
      $('vs-f-role').value = 'gate_scanner';
      $('vs-f-gate').value = '';
      $('vs-f-notes').value = '';
    }
    show('vs-f-del', !!s);
    openModal('vs-modal');
    $('vs-f-start').focus();
  }
  function saveShift() {
    var start = $('vs-f-start').value, end = $('vs-f-end').value;
    if (!start || !end) { O.flash('Pune ora de început și cea de sfârșit.', true); return; }
    if (end <= start) { O.flash('Tura se termină înainte să înceapă.', true); return; }
    var body = {
      team_member_id: $('vs-f-member').value ? F.toNum($('vs-f-member').value) : null,
      start_at: start, end_at: end, role: $('vs-f-role').value,
      gate: $('vs-f-gate').value.trim() || null,
      notes: $('vs-f-notes').value.trim() || null,
    };
    var btn = $('vs-f-save');
    btn.disabled = true;
    var base = '/organizer/events/' + eventId + '/leisure/shifts';
    var req = editShift ? O.api(base + '/' + F.toNum(editShift.id), { method: 'PUT', body: body }) : O.api(base, { method: 'POST', body: body });
    req.then(function () {
      closeModal('vs-modal');
      O.flash(editShift ? 'Tura a fost schimbată.' : 'Tura a fost adăugată.');
      loadWeek();
    }, function (err) {
      O.flash((err && err.message) || 'Nu am putut salva tura.', true);
    }).then(function () { btn.disabled = false; });
  }
  function delShift() {
    if (!editShift) return;
    var btn = $('vs-f-del');
    btn.disabled = true;
    O.api('/organizer/events/' + eventId + '/leisure/shifts/' + F.toNum(editShift.id), { method: 'DELETE' }).then(function () {
      closeModal('vs-modal');
      O.flash('Tura a fost ștearsă.');
      loadWeek();
    }, function (err) {
      O.flash((err && err.message) || 'Nu am putut șterge tura.', true);
    }).then(function () { btn.disabled = false; });
  }

  /* =================== the timesheet =================== */
  function fillStaffFilter() {
    var sel = $('vt-tstaff'), keep = sel.value;
    sel.textContent = '';
    sel.appendChild(new Option('Toți oamenii', ''));
    staff.forEach(function (s) { sel.appendChild(new Option(txt(s.full_name) || ('Om #' + s.id), String(s.id))); });
    sel.value = keep;
  }
  function timeQuery() {
    var q = [];
    if ($('vt-tfrom').value) q.push('from=' + $('vt-tfrom').value);
    if ($('vt-tto').value) q.push('to=' + $('vt-tto').value);
    if ($('vt-tstaff').value) q.push('staff_id=' + encodeURIComponent($('vt-tstaff').value));
    return q;
  }
  function loadTimes() {
    if (!eventId) return;
    state('vt-times', 5, 'Se încarcă…');
    var q = timeQuery().concat(['limit=500']);
    O.api('/organizer/leisure/staff-checkins?' + q.join('&')).then(function (r) {
      timesLoaded = true;
      var d = (r && r.data) || {}, items = Array.isArray(d.checkins) ? d.checkins : [];
      var per = Array.isArray(d.per_staff) ? d.per_staff : [];
      var cards = $('vt-per-staff');
      cards.textContent = '';
      per.slice().sort(function (a, b) { return F.toNum(b.total) - F.toNum(a.total); }).forEach(function (p) {
        cards.appendChild(el('article', { class: 've-kpi' }, el('div', null, [
          el('b', { text: F.num(F.toNum(p.total)) }),
          el('p', { text: txt(p.staff_name) || '—' }),
          el('small', { text: p.last_at ? 'ultimul: ' + stamp(p.last_at) : '' }),
        ])));
      });
      var body = $('vt-times');
      body.textContent = '';
      if (!items.length) {
        state('vt-times', 5, 'Niciun pontaj în perioada aleasă.');
        $('vt-time-count').textContent = '';
        return;
      }
      items.forEach(function (c) {
        body.appendChild(el('tr', null, [
          el('td', { text: txt(c.staff_name) || '—' }),
          el('td', { text: txt(c.position) || '—' }),
          el('td', { text: txt(c.event_name) || '—' }),
          el('td', { text: txt(c.location) || '—' }),
          el('td', { text: stamp(c.checked_in_at) }),
        ]));
      });
      $('vt-time-count').textContent = 'Afișate ' + F.num(items.length) + ' din ' + F.count(F.toNum(d.total_count) || items.length, 'pontaj', 'pontaje') + '.';
    }, function (err) {
      if (err && err.status === 401) return;
      state('vt-times', 5, 'Nu am putut încărca pontajul.');
    });
  }
  function exportTimes() {
    var btn = $('vt-tcsv');
    btn.disabled = true;
    var base = (window.BILETEONLINE_CONFIG && window.BILETEONLINE_CONFIG.apiUrl) || '/api/proxy.php';
    var url = base + '?action=organizer.leisure.staff.export' + timeQuery().map(function (p) { return '&' + p; }).join('');
    var token = null;
    try { token = typeof BileteOnlineAuth !== 'undefined' ? BileteOnlineAuth.getToken() : null; } catch (e) {}
    fetch(url, { headers: token ? { Authorization: 'Bearer ' + token } : {} }).then(function (res) {
      if (!res.ok) throw new Error('HTTP ' + res.status);
      return res.blob();
    }).then(function (blob) {
      var a = document.createElement('a'), href = URL.createObjectURL(blob);
      a.href = href;
      a.download = 'pontaj-' + F.ymd(new Date()) + '.csv';
      document.body.appendChild(a);
      a.click();
      document.body.removeChild(a);
      setTimeout(function () { URL.revokeObjectURL(href); }, 4000);
      O.flash('Pontajul a fost descărcat.');
    }).catch(function () {
      O.flash('Nu am putut descărca pontajul.', true);
    }).then(function () { btn.disabled = false; });
  }

  /* =================== wiring =================== */
  $('vt-add').addEventListener('click', function () { openStaff(null); });
  $('vt-f-cancel').addEventListener('click', function () { closeModal('vt-modal'); });
  $('vt-f-save').addEventListener('click', saveStaff);
  $('vt-f-off').addEventListener('click', offStaff);
  $('vt-modal').addEventListener('click', function (ev) { if (ev.target === $('vt-modal')) closeModal('vt-modal'); });
  $('vs-f-cancel').addEventListener('click', function () { closeModal('vs-modal'); });
  $('vs-f-save').addEventListener('click', saveShift);
  $('vs-f-del').addEventListener('click', delShift);
  $('vs-modal').addEventListener('click', function (ev) { if (ev.target === $('vs-modal')) closeModal('vs-modal'); });
  document.addEventListener('keydown', function (ev) {
    if (ev.key !== 'Escape') return;
    if (!$('vt-modal').hidden) closeModal('vt-modal');
    if (!$('vs-modal').hidden) closeModal('vs-modal');
  });
  $('vt-prev').addEventListener('click', function () { weekStart = addDays(weekStart, -7); loadWeek(); });
  $('vt-next').addEventListener('click', function () { weekStart = addDays(weekStart, 7); loadWeek(); });
  $('vt-today').addEventListener('click', function () { weekStart = monday(new Date()); loadWeek(); });
  $('vt-time-btn').addEventListener('click', function () {
    var open = this.getAttribute('aria-expanded') !== 'true';
    this.setAttribute('aria-expanded', String(open));
    this.firstChild.nodeValue = open ? 'Ascunde pontajul' : 'Arată pontajul';
    show('vt-time-body', open);
    if (open && !timesLoaded) loadTimes();
  });
  ['vt-tfrom', 'vt-tto', 'vt-tstaff'].forEach(function (id) { $(id).addEventListener('change', loadTimes); });
  $('vt-tcsv').addEventListener('click', exportTimes);
  $('ve-retry').addEventListener('click', function () { show('ve-failed', false); start(); });
  $('ve-event').addEventListener('change', function () {
    eventId = F.toNum(this.value);
    timesLoaded = false;
    loadWeek();
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
      loadStaff();
      loadWeek();
    }, function (err) {
      if (err && err.status === 401) return;
      show('ve-failed', true);
    });
  }
  O.ready.then(function (ok) { if (ok) start(); });
})();
