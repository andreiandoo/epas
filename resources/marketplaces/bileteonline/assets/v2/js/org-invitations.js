/* bilete.online v2: organizer invitations (/organizator/invitatii?event={id}). The activity from /organizer/events/{id}
   (or a list to choose one), a new series in three steps (details or seats, the guests typed or from a CSV, done), the
   series of the activity from /organizer/invitations with their guests, per-invitation PDF, delete, regenerate and ZIP,
   and the seat picker drawn from /organizer/events/{id}/seating-map with pan and zoom. Runs inside the organizer shell
   (window.BO_ORG); text from the API is always written as text, and the venue's icon SVGs are rebuilt from their shapes
   only. Downloads go through the proxy with the session token and are checked before saving: an expired session gets
   the sign-in page back (status 200, the proxy follows the redirect), which must never be saved as a ZIP / PDF / CSV. */
(function () {
  'use strict';
  var O = window.BO_ORG, root = document.getElementById('oi');
  if (!O || !root) return;
  var F = O.fmt, el = O.el, icon = O.icon;
  var $ = function (id) { return document.getElementById(id); };
  function qsa(sel, ctx) { return [].slice.call((ctx || document).querySelectorAll(sel)); }

  var SVGNS = 'http://www.w3.org/2000/svg', MAX = 50;
  var FIELDS = [['first_name', 'Prenume', 100], ['last_name', 'Nume', 100], ['email', 'Email', 180], ['phone', 'Telefon', 50], ['company', 'Companie', 150], ['notes', 'Note', 500]];
  var EMAIL = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/;
  var qs = new URLSearchParams(location.search), EVENT = qs.get('event') || qs.get('event_id') || '';
  if (!/^\d+$/.test(EVENT)) EVENT = '';

  var ev = null, seated = false, seating = null, seatInfo = {}, picked = [], pickedSeatingId = null, draft = [];
  var mode = 'manual', csvRows = null, rowData = [], generating = false, doneBatch = null, doneName = '';
  var batches = [], hPage = 1, hMore = false, hTotal = 0, openSet = {}, invites = {}, invErr = {}, confirmRun = null, confirmBusy = '';
  var opener = null, openerKey = null;
  var view = { zoom: 1, x: 0, y: 0, min: 0.2, max: 4 }, panBound = false;

  /* =================== HELPERS =================== */
  function val(id) { var n = $(id); return n ? String(n.value || '').trim() : ''; }
  function norm(s) { return String(s == null ? '' : s).normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase(); }
  function slug(s) { return norm(s).replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 60) || 'serie'; }
  function tag(text, cls) { return el('span', { class: 'org-tag ' + (cls || ''), text: text }); }
  function naiveDay(v) { var m = /^(\d{4}-\d{2}-\d{2})/.exec(String(v || '')); return m ? m[1] : ''; }
  function timeOf(v) { var m = /T(\d{2}):(\d{2})/.exec(String(v || '')); return m && m[1] + ':' + m[2] !== '00:00' ? m[1] + ':' + m[2] : ''; }
  function dayLabel(v) { var d = F.dateOf(v); return d ? F.date(d, { day: 'numeric', month: 'long', year: 'numeric' }) : ''; }
  function isOver(e) {
    if (e.is_cancelled || e.is_past || e.is_ended) return true;
    var end = naiveDay(e.ends_at || e.starts_at);
    return !!end && end < F.ymd();
  }
  function busyBtn(btn, on, text) {
    var l = btn.querySelector('[data-label]');
    if (on) { btn.setAttribute('aria-busy', 'true'); if (l) { btn.setAttribute('data-idle', l.textContent); l.textContent = text; } }
    else { btn.removeAttribute('aria-busy'); if (l && btn.hasAttribute('data-idle')) l.textContent = btn.getAttribute('data-idle'); btn.removeAttribute('data-idle'); }
  }
  function isBusy(btn) { return btn.getAttribute('aria-busy') === 'true'; }
  function showErr(id, msg) { var n = $(id); if (n) { n.textContent = msg || ''; n.hidden = !msg; } }
  function fieldErr(id, msg) { showErr(id + '-err', msg); var f = $(id); if (f) { if (msg) f.setAttribute('aria-invalid', 'true'); else f.removeAttribute('aria-invalid'); } }
  function errMessage(err) { return String((err && (err.message || (err.data && err.data.message))) || ''); }
  function errorsOf(err) { return (err && (err.errors || (err.data && err.data.errors))) || {}; }
  function romanian(m) { return /[ăâîșțĂÂÎȘȚ]/.test(m); }
  function offline(err) { var s = err && err.status; return s === 0 || s == null || s === 502 || s === 503 || s === 504; }
  function pill(ic, text, attrs) { return el('button', Object.assign({ class: 'oi-pill', type: 'button' }, attrs || {}), [icon(ic), el('span', { 'data-label': '' }, text)]); }
  function focusKey(box) { var a = document.activeElement; return a && a !== document.body && box.contains(a) ? a.getAttribute('data-focus') || '' : null; }
  function keepKey(box, wanted) { var k = focusKey(box), a = document.activeElement; return k === null && wanted && (!a || a === document.body) ? wanted : k; }
  function restoreFocus(box, key) { if (!key) return; var same = box.querySelector('[data-focus="' + key + '"]'); if (same) same.focus(); }
  function seatRef(s) {
    if (!s) return '';
    var parts = [];
    if (s.section_name) parts.push(s.section_name);
    if (s.row_label) parts.push('Rândul ' + s.row_label);
    if (s.seat_label) parts.push('Locul ' + s.seat_label);
    return parts.length ? parts.join(' · ') : String(s.seat_uid || '');
  }
  function sortSeats(list) {
    var c = function (a, b) { return String(a || '').localeCompare(String(b || ''), 'ro', { numeric: true }); };
    return list.sort(function (a, b) { return c(a.section_name, b.section_name) || c(a.row_label, b.row_label) || c(a.seat_label, b.seat_label); });
  }

  /* =================== ACTIVITY =================== */
  function loadAllEvents() {
    var got = [];
    function page(n) {
      return O.api('/organizer/events?per_page=50&page=' + n, { quiet: true }).then(function (r) {
        got = got.concat(Array.isArray(r && r.data) ? r.data : []);
        if (F.toNum(O.metaOf(r).last_page) > n && n < 20) return page(n + 1);
      });
    }
    return page(1).then(function () {
      return got.filter(function (e) { return e && /^\d+$/.test(String(e.id)); }).sort(function (a, b) {
        var ad = String(a.starts_at || ''), bd = String(b.starts_at || '');
        return ad < bd ? -1 : ad > bd ? 1 : 0;
      });
    });
  }
  function choose(reason) {
    $('oi-loading').hidden = true;
    $('oi-choose').hidden = false;
    $('oi-missing').hidden = !reason;
    if (reason === 'error') $('oi-missing-p').textContent = 'Nu am putut încărca activitatea. Verifică conexiunea și reîncarcă pagina, sau alege din listă.';
    var box = $('oi-events');
    box.textContent = '';
    box.appendChild(el('li', { class: 'oi-msg', text: 'Se încarcă activitățile…' }));
    loadAllEvents().then(function (list) {
      var upcoming = list.filter(function (e) { return !isOver(e); });
      box.textContent = '';
      if (!upcoming.length) { box.appendChild(el('li', { class: 'oi-msg', text: 'Nu ai activități care n-au trecut încă.' })); return; }
      upcoming.forEach(function (e) {
        var day = naiveDay(e.starts_at), t = timeOf(e.starts_at);
        var meta = [day ? dayLabel(day) + (t ? ', ' + t : '') : '', F.flat(e.venue_name)].filter(Boolean).join(' · ');
        box.appendChild(el('li', null, el('a', { class: 'oi-ev', href: '/organizator/invitatii?event=' + e.id }, [
          el('span', null, [el('b', { text: F.flat(e.name || e.title) || 'Activitatea #' + e.id }), meta ? el('small', { text: meta }) : null]),
          icon('arrow-right'),
        ])));
      });
    }, function (err) {
      if (err && err.status === 401) return;
      box.textContent = '';
      var retry = el('button', { class: 'oi-link', type: 'button', text: 'Reîncearcă' });
      retry.addEventListener('click', function () { choose(reason); });
      box.appendChild(el('li', { class: 'oi-msg' }, ['Nu am putut încărca activitățile.', retry]));
    });
  }
  function loadEvent() {
    O.api('/organizer/events/' + EVENT).then(function (r) {
      var e = r && r.data && (r.data.event || r.data);
      if (!e || e.id == null) throw { status: 404 };
      ev = e;
      seated = !!(e.has_seating || e.seating_layout_id || e.seating_layout);
      $('oi-loading').hidden = true;
      $('oi-event-name').textContent = F.flat(e.name || e.title) || 'Activitate';
      var day = naiveDay(e.starts_at || e.event_date || e.range_start_date), t = timeOf(e.starts_at);
      $('oi-event-date').textContent = day ? dayLabel(day) + (t ? ', ora ' + t : '') : '';
      $('oi-event-date-w').hidden = !day;
      var venue = [F.flat(e.venue_name || (e.venue && e.venue.name)), F.flat(e.venue_city)].filter(Boolean).join(', ');
      $('oi-event-venue').textContent = venue;
      $('oi-event-venue-w').hidden = !venue;
      $('oi-event-seated').hidden = !seated;
      $('oi-event-over').hidden = !isOver(e);
      $('oi-event').hidden = false;
      $('oi-builder').hidden = false;
      $('oi-history').hidden = false;
      setupStep1();
      go(1, false);
      loadHistory();
    }).catch(function (err) {
      if (err && err.status === 401) return;
      choose(err && (err.status === 404 || err.status === 403) ? 'missing' : 'error');
    });
  }

  /* =================== STEPS =================== */
  function setupStep1() {
    $('oi-qty-f').hidden = seated;
    $('oi-seats').hidden = !seated;
    $('oi-modes').hidden = seated;
    $('oi-steps-1').textContent = seated ? 'Locuri' : 'Detalii serie';
    $('oi-step1-h').textContent = seated ? 'Pasul 1 — Alege locurile' : 'Pasul 1 — Detalii serie';
    $('oi-step1-p').textContent = seated
      ? 'Dă un nume seriei (opțional, pentru organizare — ex. „Firma X”, „Sponsori”) și alege locurile de pe hartă.'
      : 'Dă un nume seriei (opțional, pentru organizare — ex. „Firma X”, „Sponsori”) și alege numărul de invitații.';
    drawChips();
  }
  function go(n, focus) {
    [1, 2, 3].forEach(function (i) { $('oi-step-' + i).hidden = i !== n; });
    qsa('.oi-steps li', root).forEach(function (li) {
      var i = Number(li.getAttribute('data-step'));
      li.classList.toggle('is-on', i === n);
      li.classList.toggle('is-past', i < n);
      if (i === n) li.setAttribute('aria-current', 'step'); else li.removeAttribute('aria-current');
    });
    if (focus) $('oi-step' + n + '-h').focus();
  }
  $('oi-next').addEventListener('click', function () {
    var count;
    if (seated) {
      if (!picked.length) { showErr('oi-seats-err', 'Alege cel puțin un loc pe hartă.'); $('oi-open-map').focus(); return; }
      showErr('oi-seats-err', '');
      count = picked.length;
    } else {
      var raw = val('oi-qty');
      if (!/^\d+$/.test(raw) || Number(raw) < 1 || Number(raw) > MAX) { fieldErr('oi-qty', 'Alege între 1 și 50 de invitații. Pentru mai multe, creează mai multe serii.'); $('oi-qty').focus(); return; }
      fieldErr('oi-qty', '');
      count = Number(raw);
    }
    buildRows(count);
    setMode(seated ? 'manual' : mode);
    showErr('oi-gen-err', '');
    go(2, true);
  });
  $('oi-qty').addEventListener('input', function () { fieldErr('oi-qty', ''); });
  $('oi-back-1').addEventListener('click', function () { readRows(); showErr('oi-gen-err', ''); go(1, true); });

  function readRows() {
    qsa('#oi-rows tr').forEach(function (tr, i) {
      var rec = rowData[i] || (rowData[i] = {});
      qsa('input[data-field]', tr).forEach(function (inp) { rec[inp.getAttribute('data-field')] = inp.value; });
    });
  }
  function buildRows(count) {
    readRows();
    var body = $('oi-rows');
    body.textContent = '';
    $('oi-col-seat').hidden = !seated;
    for (var i = 0; i < count; i++) {
      var rec = rowData[i] || {}, cells = [el('td', { text: String(i + 1) })];
      if (seated) cells.push(el('td', null, el('span', { class: 'oi-seat-tag', text: seatRef(picked[i]) })));
      FIELDS.forEach(function (f) {
        var inp = el('input', { type: f[0] === 'email' ? 'email' : f[0] === 'phone' ? 'tel' : 'text', maxlength: f[2], autocomplete: 'off', spellcheck: f[0] === 'notes' ? null : 'false', 'data-field': f[0], placeholder: f[1], 'aria-label': f[1] + ', invitatul ' + (i + 1) });
        inp.value = rec[f[0]] || '';
        inp.addEventListener('input', function () { inp.removeAttribute('aria-invalid'); });
        cells.push(el('td', null, inp));
      });
      body.appendChild(el('tr', null, cells));
    }
  }
  function setMode(m) {
    mode = m;
    qsa('#oi-modes [data-mode]').forEach(function (b) { b.setAttribute('aria-pressed', String(b.getAttribute('data-mode') === m)); });
    $('oi-pane-manual').hidden = m !== 'manual';
    $('oi-pane-csv').hidden = m !== 'csv';
  }
  qsa('#oi-modes [data-mode]').forEach(function (b) {
    b.addEventListener('click', function () { setMode(b.getAttribute('data-mode')); showErr('oi-gen-err', ''); });
  });

  /* =================== CSV =================== */
  $('oi-csv-pick').addEventListener('click', function () { $('oi-csv').click(); });
  $('oi-csv').addEventListener('change', function () {
    var input = this, file = input.files && input.files[0];
    showErr('oi-csv-err', '');
    showErr('oi-gen-err', '');
    if (!file) return;
    input.value = '';
    if (file.size > 2 * 1024 * 1024) { csvRows = null; $('oi-csv-name').textContent = file.name; showErr('oi-csv-err', 'Fișierul e prea mare. Un CSV cu 50 de invitați are câțiva kilobytes.'); return; }
    var reader = new FileReader();
    reader.onload = function () {
      try {
        var res = parseCsv(String(reader.result || ''));
        csvRows = res.rows;
        $('oi-csv-name').textContent = file.name + ' — ' + F.count(res.rows.length, 'invitat detectat', 'invitați detectați') + (res.skipped ? ', ' + F.count(res.skipped, 'rând ignorat', 'rânduri ignorate') + ' (fără prenume, nume sau email)' : '') + '.';
      } catch (e) {
        csvRows = null;
        $('oi-csv-name').textContent = file.name;
        showErr('oi-csv-err', e.message);
      }
    };
    reader.onerror = function () { csvRows = null; showErr('oi-csv-err', 'Nu am putut citi fișierul. Încearcă din nou.'); };
    reader.readAsText(file);
  });
  function splitCsv(line, sep) {
    var out = [], cur = '', inQ = false;
    for (var i = 0; i < line.length; i++) {
      var ch = line[i];
      if (ch === '"') { if (inQ && line[i + 1] === '"') { cur += '"'; i++; } else inQ = !inQ; }
      else if (ch === sep && !inQ) { out.push(cur); cur = ''; }
      else cur += ch;
    }
    out.push(cur);
    return out;
  }
  function parseCsv(text) {
    if (text.charCodeAt(0) === 0xFEFF) text = text.slice(1);
    var lines = text.split(/\r?\n/).filter(function (l) { return l.trim() !== ''; });
    if (lines.length < 2) throw new Error('CSV-ul e gol sau conține doar antetul.');
    // Excel with Romanian settings separates columns with ";"
    var sep = lines[0].indexOf(';') > -1 && lines[0].indexOf(',') === -1 ? ';' : ',';
    var head = splitCsv(lines[0], sep).map(function (h) { return h.trim().toLowerCase(); });
    var iF = head.indexOf('first_name'), iL = head.indexOf('last_name'), iE = head.indexOf('email');
    if (iF < 0 || iL < 0 || iE < 0) throw new Error('Lipsesc coloane obligatorii. Antetul trebuie să conțină first_name, last_name, email.');
    var extra = [['phone', head.indexOf('phone'), 50], ['company', head.indexOf('company'), 150], ['notes', head.indexOf('notes'), 500]], rows = [], skipped = 0;
    for (var i = 1; i < lines.length; i++) {
      var c = splitCsv(lines[i], sep), first = (c[iF] || '').trim(), last = (c[iL] || '').trim(), email = (c[iE] || '').trim();
      if (!first || !last || !email) { skipped++; continue; }
      var rec = { first_name: first.slice(0, 100), last_name: last.slice(0, 100), email: email };
      extra.forEach(function (x) { var v = x[1] > -1 ? (c[x[1]] || '').trim() : ''; if (v) rec[x[0]] = v.slice(0, x[2]); });
      rows.push(rec);
    }
    if (!rows.length) throw new Error('Niciun rând valid (toate trebuie să aibă prenume, nume, email).');
    if (rows.length > MAX) throw new Error('CSV-ul are ' + rows.length + ' de invitați, iar o serie poate avea cel mult 50. Împarte fișierul în mai multe.');
    var bad = rows.filter(function (r) { return !EMAIL.test(r.email) || r.email.length > 180; }).length;
    if (bad) throw new Error((bad === 1 ? 'O adresă de email nu e validă' : F.count(bad, 'adresă', 'adrese') + ' de email nu sunt valide') + '. Corectează fișierul și încarcă-l din nou.');
    return { rows: rows, skipped: skipped };
  }
  $('oi-csv-template').addEventListener('click', function () {
    download('organizer.invitations.csv-template', null, 'csv', 'invitatii-template.csv', this).then(function (ok) { if (ok) O.flash('Template-ul CSV a fost descărcat.'); });
  });

  /* =================== DOWNLOADS =================== */
  function download(action, params, kind, name, btn) {
    var token = typeof BileteOnlineAuth !== 'undefined' && BileteOnlineAuth.getToken ? BileteOnlineAuth.getToken() : null;
    if (!token) { O.flash('Sesiunea a expirat. Autentifică-te din nou.', true); return Promise.resolve(false); }
    if (btn && isBusy(btn)) return Promise.resolve(false);
    var base = (window.BILETEONLINE && window.BILETEONLINE.apiUrl) || '/api/proxy.php', disposition = '';
    var url = base + '?action=' + action + (params ? '&' + new URLSearchParams(params).toString() : '');
    if (btn) busyBtn(btn, true, 'Se descarcă…');
    return fetch(url, { headers: { Authorization: 'Bearer ' + token, Accept: kind === 'zip' ? 'application/zip' : kind === 'pdf' ? 'application/pdf' : 'text/csv' } }).then(function (res) {
      disposition = res.headers.get('content-disposition') || '';
      if (!res.ok) {
        return res.text().then(function (t) { var j = {}; try { j = JSON.parse(t) || {}; } catch (e) {} var er = new Error(j.message || 'download'); er.status = res.status; throw er; });
      }
      return res.blob();
    }).then(function (blob) {
      return blob.slice(0, 5).arrayBuffer().then(function (buf) {
        var head = String.fromCharCode.apply(null, new Uint8Array(buf));
        var ok = kind === 'zip' ? head.slice(0, 2) === 'PK' : kind === 'pdf' ? head === '%PDF-' : blob.size > 0 && head.charAt(0) !== '<' && head.charAt(3) !== '<';
        if (!ok) { var er = new Error('not the file'); er.html = true; throw er; }
        var m = /filename\*?=(?:UTF-8'')?"?([^";]+)"?/i.exec(disposition), file = name;
        if (m) { try { file = decodeURIComponent(m[1]); } catch (e) { file = m[1]; } }
        var href = URL.createObjectURL(blob), a = el('a', { href: href, download: file, hidden: true });
        document.body.appendChild(a);
        a.click();
        setTimeout(function () { URL.revokeObjectURL(href); a.remove(); }, 1500);
        return true;
      });
    }).catch(function (err) {
      if (err && (err.status === 401 || err.html)) {
        O.flash('Sesiunea a expirat. Te trimitem la autentificare.', true);
        setTimeout(function () { O.api('/organizer/me').catch(function () {}); }, 1500);
        return false;
      }
      var m = errMessage(err);
      if (/No PDFs available/i.test(m)) O.flash('Seria nu are încă PDF-uri. Apasă „Regenerează”, apoi descarcă din nou.', true);
      else if (/PDF nu este disponibil/i.test(m)) O.flash('PDF-ul acestei invitații lipsește. Regenerează seria, apoi descarcă din nou.', true);
      else if (err && err.status === 404) O.flash('Nu mai găsim fișierul cerut. Reîncarcă lista de serii.', true);
      else O.flash('Nu am putut descărca fișierul. Încearcă din nou.', true);
      return false;
    }).then(function (ok) { if (btn) busyBtn(btn, false); return ok; });
  }

  /* =================== GENERATE =================== */
  function markRows(errors) {
    var n = 0, rows = qsa('#oi-rows tr');
    Object.keys(errors).forEach(function (k) {
      var m = /^recipients\.(\d+)\.(\w+)$/.exec(k), tr = m && rows[Number(m[1])], inp = tr && tr.querySelector('[data-field="' + m[2] + '"]');
      if (inp) { inp.setAttribute('aria-invalid', 'true'); if (!n) inp.focus(); n++; }
    });
    return n;
  }
  $('oi-generate').addEventListener('click', function () {
    var btn = this, recipients;
    if (isBusy(btn)) return;
    showErr('oi-gen-err', '');
    showErr('oi-csv-err', '');
    if (mode === 'csv' && !seated) {
      if (!csvRows) { showErr('oi-csv-err', 'Alege mai întâi un fișier CSV.'); $('oi-csv-pick').focus(); return; }
      recipients = csvRows.slice();
    } else {
      readRows();
      var bad = [];
      recipients = qsa('#oi-rows tr').map(function (tr) {
        var rec = {};
        qsa('input[data-field]', tr).forEach(function (inp) {
          var v = inp.value.trim(), f = inp.getAttribute('data-field');
          if (f === 'email' && v && !EMAIL.test(v)) { inp.setAttribute('aria-invalid', 'true'); bad.push(inp); }
          if (v) rec[f] = v;
        });
        return rec;
      });
      if (bad.length) {
        showErr('oi-gen-err', bad.length === 1 ? 'O adresă de email nu e validă. Corecteaz-o sau lasă câmpul gol.' : F.count(bad.length, 'adresă', 'adrese') + ' de email nu sunt valide. Corectează-le sau lasă câmpurile goale.');
        bad[0].focus();
        return;
      }
    }
    if (!recipients.length) { showErr('oi-gen-err', 'Adaugă cel puțin un invitat.'); return; }
    if (recipients.length > MAX) { showErr('oi-gen-err', 'O serie poate avea cel mult 50 de invitații.'); return; }
    if (seated && recipients.length !== picked.length) { showErr('oi-gen-err', 'Numărul de invitați (' + recipients.length + ') trebuie să fie egal cu numărul de locuri alese (' + picked.length + ').'); return; }
    var body = { event_id: Number(EVENT), recipients: recipients }, name = val('oi-name'), label = val('oi-label'), mark = val('oi-watermark');
    if (name) body.name = name;
    if (label) body.ticket_label = label;
    if (mark) body.watermark = mark;
    if (seated) {
      body.event_seating_id = pickedSeatingId;
      body.seats = picked.map(function (s) { return { seat_uid: s.seat_uid, section_name: s.section_name, row_label: s.row_label, seat_label: s.seat_label }; });
    }
    generating = true;
    busyBtn(btn, true, 'Se generează…');
    $('oi-wait').hidden = false;
    $('oi-back-1').disabled = true;
    O.api('/organizer/invitations', { method: 'POST', body: body }).then(function (r) {
      var d = (r && r.data) || {}, batch = d.batch || {}, n = F.toNum(d.rendered);
      doneBatch = batch.id != null ? String(batch.id) : null;
      doneName = F.flat(batch.name) || name || 'Seria';
      $('oi-step3-h').textContent = n > 0 ? 'Gata! Invitațiile au fost generate.' : 'Seria a fost creată, fără PDF-uri';
      $('oi-done-p').textContent = n > 0
        ? F.count(n, 'invitație generată', 'invitații generate') + ' în seria „' + doneName + '”.'
        : 'Seria „' + doneName + '” a fost creată, dar PDF-urile nu s-au generat. Apasă „Regenerează” la seria din listă.';
      $('oi-done-ic').classList.toggle('is-wait', n === 0);
      $('oi-done-zip').hidden = !(n > 0 && doneBatch);
      if (seated) { picked = []; pickedSeatingId = null; seating = null; drawChips(); }
      csvRows = null;
      rowData = [];
      $('oi-csv-name').textContent = '';
      go(3, true);
      loadHistory();
    }).catch(function (err) {
      if (err && err.status === 401) return;
      var m = errMessage(err), errors = errorsOf(err), s = err && err.status, gone = errors.unavailable_seats || (err && err.data && err.data.unavailable_seats);
      if (s === 409 && Array.isArray(gone)) {
        gone = gone.map(String);
        var refs = picked.filter(function (x) { return gone.indexOf(String(x.seat_uid)) > -1; }).map(seatRef);
        picked = picked.filter(function (x) { return gone.indexOf(String(x.seat_uid)) === -1; });
        seating = null;
        drawChips();
        go(1, true);
        showErr('oi-seats-err', 'Între timp ' + (refs.length === 1 ? 's-a ocupat ' + refs[0] : 's-au ocupat ' + F.count(refs.length || gone.length, 'loc', 'locuri') + (refs.length ? ': ' + refs.join(', ') : '')) + '. Le-am scos din selecție; alege altele pe hartă.');
        return;
      }
      if (s === 422) {
        var marked = markRows(errors);
        showErr('oi-gen-err', marked ? 'Verifică datele marcate în tabel.' : romanian(m) ? m : 'Unele date nu sunt acceptate. Verifică-le și încearcă din nou.');
        return;
      }
      if (s === 404) { showErr('oi-gen-err', 'Activitatea nu mai există sau nu îți aparține.'); return; }
      if (offline(err)) {
        showErr('oi-gen-err', 'Generarea durează mai mult decât de obicei. Seria poate apărea în listă peste câteva momente: verifică lista înainte să încerci din nou, ca să nu generezi de două ori.');
        loadHistory();
        setTimeout(function () { loadHistory(); }, 8000);
        return;
      }
      showErr('oi-gen-err', romanian(m) ? m : 'Nu am putut genera invitațiile. Încearcă din nou.');
    }).then(function () {
      generating = false;
      busyBtn(btn, false);
      $('oi-wait').hidden = true;
      $('oi-back-1').disabled = false;
    });
  });
  window.addEventListener('beforeunload', function (e) { if (generating) { e.preventDefault(); e.returnValue = ''; } });
  $('oi-done-zip').addEventListener('click', function () {
    var btn = this;
    if (!doneBatch) return;
    download('organizer.invitations.download', { batch_id: doneBatch }, 'zip', 'invitatii-' + slug(doneName) + '.zip', btn).then(function (ok) {
      if (ok) { O.flash('Arhiva cu invitațiile a fost descărcată.'); loadHistory(); }
    });
  });
  $('oi-again').addEventListener('click', function () {
    ['oi-name', 'oi-label', 'oi-watermark'].forEach(function (id) { $(id).value = ''; });
    $('oi-qty').value = '1';
    rowData = [];
    csvRows = null;
    $('oi-csv-name').textContent = '';
    setMode('manual');
    if (seated) { picked = []; pickedSeatingId = null; drawChips(); }
    showErr('oi-seats-err', '');
    fieldErr('oi-qty', '');
    go(1, true);
  });

  /* =================== SERIES =================== */
  function loadHistory(more, wanted) {
    var page = more ? hPage + 1 : 1, btn = $('oi-history-more');
    if (more) busyBtn(btn, true, 'Se încarcă…');
    return O.api('/organizer/invitations?event_id=' + EVENT + '&per_page=20&page=' + page, { quiet: true }).then(function (r) {
      var d = r && r.data, rows = Array.isArray(d) ? d : d && Array.isArray(d.data) ? d.data : d && Array.isArray(d.items) ? d.items : [];
      rows = rows.filter(function (b) { return b && b.id != null; });
      batches = more ? batches.concat(rows) : rows;
      hPage = page;
      var meta = O.metaOf(r);
      hMore = F.toNum(meta.last_page) > page;
      hTotal = F.toNum(meta.total) || batches.length;
      if (more) busyBtn(btn, false);
      drawHistory(wanted);
    }, function (err) {
      if (more) { busyBtn(btn, false); O.flash('Nu am putut încărca mai multe serii. Încearcă din nou.', true); return; }
      if (err && err.status === 401) return;
      var box = $('oi-batches'), retry = el('button', { class: 'oi-link', type: 'button', text: 'Reîncearcă' });
      retry.addEventListener('click', function () { loadHistory(); });
      box.textContent = '';
      box.appendChild(el('li', { class: 'oi-msg' }, ['Nu am putut încărca seriile.', retry]));
    });
  }
  $('oi-history-more').addEventListener('click', function () { if (!isBusy(this)) loadHistory(true); });
  function drawHistory(wanted) {
    var box = $('oi-batches'), keep = keepKey(box, wanted);
    box.textContent = '';
    if (!batches.length) box.appendChild(el('li', { class: 'oi-msg', text: 'Încă nu ai generat invitații pentru această activitate.' }));
    batches.forEach(function (b) { box.appendChild(batchRow(b)); });
    $('oi-history-more').hidden = !hMore;
    var count = batches.reduce(function (s, b) { return s + F.toNum(b.qty_generated); }, 0);
    $('oi-history-p').textContent = !batches.length ? '' : F.count(hTotal, 'serie', 'serii') + (hMore ? '' : ', ' + F.count(count, 'invitație', 'invitații') + ' în total') + '.';
    restoreFocus(box, keep);
  }
  function batchRow(b) {
    var id = String(b.id), isOpen = !!openSet[id], name = F.flat(b.name) || 'Seria #' + id, created = F.dateOf(b.created_at);
    var st = b.status === 'ready' ? tag('Gata', 'is-ok') : b.status === 'draft' ? tag('Fără PDF-uri', 'is-wait') : tag(F.flat(b.status) || 'În lucru', 'is-muted');
    var meta = [created ? F.date(created, { day: 'numeric', month: 'short', year: 'numeric' }) : '', F.num(F.toNum(b.qty_planned)) + ' planificate', F.num(F.toNum(b.qty_rendered)) + ' generate', F.num(F.toNum(b.qty_downloaded)) + ' descărcate'].filter(Boolean).join(' · ');
    var viewBtn = pill('caret-down', 'Vezi invitați', { 'data-focus': 'b-view-' + id, 'aria-expanded': String(isOpen), 'aria-controls': 'oi-inv-' + id });
    viewBtn.addEventListener('click', function () { toggleInvites(id); });
    var regen = pill('arrow-counter-clockwise', 'Regenerează', { 'data-focus': 'b-regen-' + id, title: 'Refă PDF-urile cu șablonul și datele actuale' });
    regen.addEventListener('click', function () { askRegen(b, regen); });
    var zip = pill('download-simple', 'Descarcă ZIP', { class: 'oi-pill is-primary', 'data-focus': 'b-zip-' + id });
    zip.addEventListener('click', function () {
      download('organizer.invitations.download', { batch_id: id }, 'zip', 'invitatii-' + slug(name) + '.zip', zip).then(function (ok) {
        if (!ok) return;
        O.flash('Arhiva cu invitațiile a fost descărcată.');
        delete invites[id];
        loadHistory(false, 'b-zip-' + id).then(function () { refreshInvites(id); });
      });
    });
    var panel = el('div', { class: 'oi-invites', id: 'oi-inv-' + id, hidden: !isOpen });
    if (isOpen) fillInvites(panel, b);
    return el('li', { class: 'oi-batch' }, [
      el('div', { class: 'oi-batch-row' }, [
        el('div', { class: 'oi-batch-t' }, [el('b', { text: name }), el('small', { text: meta })]),
        el('div', { class: 'oi-batch-act' }, [st, viewBtn, regen, zip]),
      ]),
      panel,
    ]);
  }
  function toggleInvites(id) {
    if (openSet[id]) { delete openSet[id]; drawHistory('b-view-' + id); return; }
    openSet[id] = true;
    drawHistory('b-view-' + id);
    if (!invites[id]) refreshInvites(id);
  }
  function refreshInvites(id) {
    if (!openSet[id]) { delete invites[id]; return Promise.resolve(); }
    delete invErr[id];
    return O.api('/organizer/invitations/' + id, { quiet: true }).then(function (r) {
      invites[id] = r && r.data && Array.isArray(r.data.invites) ? r.data.invites : [];
      drawHistory();
    }, function (err) {
      if (err && err.status === 401) return;
      delete invites[id];
      invErr[id] = true;
      drawHistory();
    });
  }
  function fillInvites(panel, b) {
    var id = String(b.id), list = invites[id];
    if (invErr[id]) {
      var retry = el('button', { class: 'oi-link', type: 'button', text: 'Reîncearcă', 'data-focus': 'b-retry-' + id });
      retry.addEventListener('click', function () { refreshInvites(id); });
      panel.appendChild(el('p', { class: 'oi-msg' }, ['Nu am putut încărca invitații.', retry]));
      return;
    }
    if (!list) { panel.appendChild(el('p', { class: 'oi-msg', text: 'Se încarcă invitații…' })); return; }
    if (!list.length) { panel.appendChild(el('p', { class: 'oi-msg', text: 'Fără invitați.' })); return; }
    var hasSeat = list.some(function (i) { return i.seat_ref || (i.recipient && i.recipient.seat); });
    var head = ['Nume', 'Email'].concat(hasSeat ? ['Loc'] : [], ['Telefon', 'Companie', 'Cod', 'Acțiuni']);
    panel.appendChild(el('div', { class: 'oi-inv-wrap' }, el('table', { class: 'oi-inv' }, [
      el('thead', null, el('tr', null, head.map(function (h) { return el('th', { scope: 'col', text: h }); }))),
      el('tbody', null, list.map(function (i) { return inviteRow(b, i, hasSeat); })),
    ])));
  }
  function inviteRow(b, i, hasSeat) {
    var r = i.recipient || {}, bid = String(b.id), iid = String(i.id), seat = r.seat || null, code = F.flat(i.code);
    var name = F.flat(r.name).trim() || [F.flat(r.first_name), F.flat(r.last_name)].join(' ').trim();
    var ref = F.flat(i.seat_ref) || (seat ? seatRef({ section_name: seat.section, row_label: seat.row, seat_label: seat.label, seat_uid: seat.uid }) : '');
    var codeCell = [el('code', { text: code })];
    if (i.downloaded_at) { var dd = F.dateOf(i.downloaded_at); codeCell.push(el('span', { class: 'oi-dl', title: 'Descărcată', text: '✓ ' + (dd ? F.date(dd, { day: 'numeric', month: 'short' }) : 'descărcată') })); }
    var acts = [];
    if (i.has_pdf) {
      var dl = el('button', { class: 'oi-link', type: 'button', 'data-focus': 'i-dl-' + iid, 'aria-label': 'Descarcă PDF-ul invitației ' + (name || code) }, el('span', { 'data-label': '' }, 'Descarcă'));
      dl.addEventListener('click', function () {
        download('organizer.invitations.download-invite', { batch_id: bid, invite_id: iid }, 'pdf', 'invitatie-' + slug(name || 'invitat') + '-' + code + '.pdf', dl).then(function (ok) {
          if (!ok) return;
          if (!i.downloaded_at) { i.downloaded_at = new Date().toISOString(); b.qty_downloaded = F.toNum(b.qty_downloaded) + 1; }
          drawHistory('i-dl-' + iid);
        });
      });
      acts.push(dl);
    } else acts.push(el('span', { class: 'oi-nopdf', title: 'Regenerează seria ca să apară PDF-ul', text: 'PDF lipsă' }));
    var del = el('button', { class: 'oi-link is-danger', type: 'button', 'data-focus': 'i-del-' + iid, 'aria-label': 'Șterge invitația ' + (name || code) }, 'Șterge');
    del.addEventListener('click', function () { askDelete(b, i, name, ref, del); });
    acts.push(del);
    var cells = [el('td', { text: name || '—' }), el('td', { text: F.flat(r.email) || '—' })];
    if (hasSeat) cells.push(el('td', { text: ref || '—' }));
    cells.push(el('td', { text: F.flat(r.phone) || '—' }), el('td', { text: F.flat(r.company) || '—' }), el('td', null, codeCell), el('td', null, acts));
    return el('tr', null, cells);
  }

  /* =================== CONFIRM =================== */
  function ask(o, from) {
    $('oi-confirm-h').textContent = o.h;
    $('oi-confirm-p').textContent = o.p;
    var btn = $('oi-confirm-go');
    btn.querySelector('[data-label]').textContent = o.go;
    btn.className = 'btn ' + (o.danger ? 'oi-danger' : 'btn-primary');
    showErr('oi-confirm-err', '');
    confirmRun = o.run;
    confirmBusy = o.busy;
    openDialog($('oi-confirm-d'), from);
    $('oi-confirm-d').querySelector('[data-close]').focus();
  }
  $('oi-confirm-go').addEventListener('click', function () {
    var btn = this, d = $('oi-confirm-d');
    if (!confirmRun || isBusy(btn)) return;
    busyBtn(btn, true, confirmBusy);
    d.setAttribute('data-busy', '');
    confirmRun().then(function () {
      d.removeAttribute('data-busy');
      busyBtn(btn, false);
      closeDialog(d);
    }, function (text) {
      d.removeAttribute('data-busy');
      busyBtn(btn, false);
      if (text) showErr('oi-confirm-err', text); else closeDialog(d);
    });
  });
  function askRegen(b, from) {
    var id = String(b.id), name = F.flat(b.name) || 'Seria #' + id;
    ask({
      h: 'Regenerezi seria „' + name + '”?',
      p: 'Toate PDF-urile din serie se refac cu șablonul și datele actuale ale activității. Codurile invitațiilor rămân aceleași. Poate dura până la un minut.',
      go: 'Regenerează PDF-urile', busy: 'Se regenerează…',
      run: function () {
        return O.api('/organizer/invitations/' + id + '/generate', { method: 'POST', body: {} }).then(function (r) {
          var n = F.toNum(r && r.data && r.data.rendered);
          O.flash(F.count(n, 'invitație regenerată', 'invitații regenerate') + '. Descarcă din nou arhiva ca să ai PDF-urile noi.');
          delete invites[id];
          loadHistory(false, 'b-regen-' + id).then(function () { refreshInvites(id); });
        }, function (err) {
          if (err && err.status === 401) return Promise.reject('');
          if (err && err.status === 404) { loadHistory(); return Promise.reject(/no longer exists/i.test(errMessage(err)) ? 'Activitatea acestei serii nu mai există, așa că PDF-urile nu se pot reface.' : 'Seria nu mai există.'); }
          if (offline(err)) { setTimeout(function () { loadHistory(); }, 8000); return Promise.reject('Regenerarea durează mai mult decât de obicei. Verifică seria peste un minut înainte să încerci din nou.'); }
          return Promise.reject('Nu am putut regenera PDF-urile. Încearcă din nou.');
        });
      },
    }, from);
  }
  function askDelete(b, i, name, ref, from) {
    var bid = String(b.id), code = F.flat(i.code);
    ask({
      h: 'Ștergi invitația?',
      p: (name ? name + ' · ' : '') + 'cod ' + code + '. Invitația nu mai e valabilă: biletul ei se anulează' + (ref ? ' și locul (' + ref + ') se eliberează pe hartă' : '') + '. Nu se poate recupera.',
      go: 'Șterge invitația', busy: 'Se șterge…', danger: true,
      run: function () {
        return O.api('/organizer/invitations/' + bid + '/invites', { method: 'DELETE', body: { invite_ids: [Number(i.id)] } }).then(function (r) {
          var d = (r && r.data) || {}, remaining = F.toNum(d.batch_remaining);
          if (F.toNum(d.deleted) < 1) return Promise.reject('Invitația nu a putut fi ștearsă. Încearcă din nou.');
          O.flash(remaining === 0 ? 'Invitația a fost ștearsă. Seria nu mai avea alte invitații, așa că a fost ștearsă și ea.' : 'Invitația a fost ștearsă.' + (F.toNum(d.seats_released) ? ' Locul a fost eliberat.' : ''));
          if (remaining === 0) { delete openSet[bid]; delete invites[bid]; }
          else invites[bid] = (invites[bid] || []).filter(function (x) { return String(x.id) !== String(i.id); });
          if (seated) seating = null; // the released seat shows as free the next time the map opens
          loadHistory(false, remaining === 0 ? null : 'b-view-' + bid);
        }, function (err) {
          if (err && err.status === 401) return Promise.reject('');
          if (err && err.status === 404) { delete invites[bid]; loadHistory(false).then(function () { refreshInvites(bid); }); return Promise.reject('Invitația sau seria nu mai există.'); }
          return Promise.reject('Nu am putut șterge invitația. Încearcă din nou.');
        });
      },
    }, from);
  }

  /* =================== SEATS =================== */
  function drawChips() {
    var box = $('oi-chips'), sum = $('oi-seats-sum');
    box.textContent = '';
    picked.forEach(function (s) {
      var x = el('button', { type: 'button', 'aria-label': 'Scoate ' + seatRef(s) }, icon('x'));
      x.addEventListener('click', function () {
        var at = picked.indexOf(s);
        picked.splice(at, 1);
        drawChips();
        var rest = qsa('#oi-chips button');
        (rest[Math.min(at, rest.length - 1)] || $('oi-open-map')).focus();
      });
      box.appendChild(el('li', { class: 'oi-chip' }, [seatRef(s), x]));
    });
    sum.textContent = picked.length ? F.count(picked.length, 'loc selectat', 'locuri selectate') + ' (cel mult 50).' : 'Niciun loc selectat.';
    sum.classList.toggle('is-on', picked.length > 0);
  }
  $('oi-open-map').addEventListener('click', function () { openMap(this); });
  function openMap(from) {
    draft = picked.slice();
    showErr('oi-map-err', '');
    openDialog($('oi-map-d'), from);
    countMap();
    $('oi-map-d').querySelector('.oi-x').focus();
    if (seating) showMap(); else loadMap();
  }
  function loadMap() {
    var msg = $('oi-map-msg');
    $('oi-map').hidden = true;
    msg.hidden = false;
    msg.textContent = 'Se încarcă harta…';
    O.api('/organizer/events/' + EVENT + '/seating-map', { quiet: true }).then(function (r) {
      var d = r && r.data;
      if (!d || !Array.isArray(d.sections)) throw { status: 404 };
      seating = d;
      if ($('oi-map-d').open) showMap();
    }).catch(function (err) {
      if (!$('oi-map-d').open) return;
      if (err && err.status === 401) { closeDialog($('oi-map-d')); O.api('/organizer/me').catch(function () {}); return; }
      msg.textContent = '';
      if (err && err.status === 404) { msg.appendChild(el('p', { text: 'Activitatea nu are o hartă de locuri publicată.' })); return; }
      var retry = el('button', { class: 'btn btn-ghost oi-sm', type: 'button', text: 'Reîncearcă' });
      retry.addEventListener('click', loadMap);
      msg.appendChild(el('p', { text: 'Nu am putut încărca harta.' }));
      msg.appendChild(retry);
    });
  }
  function showMap() {
    $('oi-map-msg').hidden = true;
    $('oi-map').hidden = false;
    renderMap();
    bindPanZoom();
    fit();
  }
  function countMap() { $('oi-map-count').textContent = F.count(draft.length, 'loc', 'locuri'); }

  function svg(tag, attrs, kids) {
    var n = document.createElementNS(SVGNS, tag);
    Object.keys(attrs || {}).forEach(function (k) { if (attrs[k] != null) n.setAttribute(k, String(attrs[k])); });
    (kids || []).forEach(function (c) { if (c != null && c !== '') n.appendChild(typeof c === 'string' ? document.createTextNode(c) : c); });
    return n;
  }
  function num(v) { var n = Number(v); return isFinite(n) ? n : 0; }
  function color(c, fallback) { c = String(c || '').trim(); return /^#[0-9a-f]{3,8}$/i.test(c) || /^rgba?\([\d\s.,%]+\)$/i.test(c) ? c : fallback; }
  function renderMap() {
    var d = seating, host = $('oi-map'), cw = num(d.canvas && d.canvas.width) || 1000, ch = num(d.canvas && d.canvas.height) || 800;
    seatInfo = {};
    host.textContent = '';
    var s = svg('svg', { viewBox: '0 0 ' + cw + ' ' + ch, width: cw, height: ch, role: 'group', 'aria-label': 'Harta locurilor' });
    d.sections.forEach(function (sec) {
      if (!sec) return;
      var rot = num(sec.rotation), sx = num(sec.x), sy = num(sec.y);
      var g = svg('g', rot ? { transform: 'rotate(' + rot + ' ' + (sx + num(sec.width) / 2) + ' ' + (sy + num(sec.height) / 2) + ')' } : null);
      if (sec.section_type === 'icon') iconSection(g, sec);
      else if (sec.section_type === 'decorative') decoSection(g, sec);
      else if (Array.isArray(sec.rows)) seatSection(g, sec, sx, sy);
      s.appendChild(g);
    });
    host.appendChild(s);
    paintAll();
  }
  function seatSection(g, sec, sx, sy) {
    var meta = sec.metadata || {}, size = parseInt(meta.seat_size, 10) || 15, r = size / 2, fs = Math.round(r * 0.85 * 10) / 10;
    var xs = [], gap = r * 3, found = false;
    sec.rows.forEach(function (row) {
      if (!row || !Array.isArray(row.seats)) return;
      row.seats.forEach(function (st) { xs.push(num(st.x)); });
      if (!found && row.seats.length >= 2) {
        var sorted = row.seats.map(function (st) { return num(st.x); }).sort(function (a, b) { return a - b; });
        gap = Math.abs(sorted[1] - sorted[0]) || gap;
        found = true;
      }
    });
    var minX = xs.length ? Math.min.apply(null, xs) : 0, maxX = xs.length ? Math.max.apply(null, xs) : 0;
    var lx = sx + minX - gap, rx = sx + maxX + gap, rl = Math.max(10, Math.round(fs * 1.1 * 10) / 10), labels = meta.auto_show_row_labels !== false;
    sec.rows.forEach(function (row) {
      if (!row || !Array.isArray(row.seats) || !row.seats.length) return;
      var rowLabel = F.flat(row.label);
      if (labels && rowLabel) {
        var ly = sy + num(row.seats[0].y) + r * 0.4;
        g.appendChild(svg('text', { x: lx, y: ly, 'text-anchor': 'end', 'font-size': rl, class: 'oi-row-l', 'aria-hidden': 'true' }, [rowLabel]));
        g.appendChild(svg('text', { x: rx, y: ly, 'text-anchor': 'start', 'font-size': rl, class: 'oi-row-l', 'aria-hidden': 'true' }, [rowLabel]));
      }
      row.seats.forEach(function (st) {
        var uid = F.flat(st && st.seat_uid);
        if (!uid) return;
        var x = sx + num(st.x), y = sy + num(st.y), status = st.status || 'available';
        var info = { seat_uid: uid, section_name: F.flat(sec.name) || null, row_label: rowLabel || null, seat_label: F.flat(st.label) || null, status: status, type: F.flat(st.ticket_type_name) };
        seatInfo[uid] = info;
        var label = seatRef(info) + (info.type ? ' · ' + info.type : '');
        var c = svg('circle', { cx: x, cy: y, r: r, class: 'oi-seat', 'data-uid': uid, role: 'checkbox', 'aria-checked': 'false', 'aria-label': label });
        if (status === 'available') c.setAttribute('tabindex', '0'); else c.setAttribute('aria-disabled', 'true');
        c.appendChild(svg('title', null, [label + (status === 'available' ? '' : ' (indisponibil)')]));
        g.appendChild(c);
        if (status === 'available' && info.seat_label) g.appendChild(svg('text', { x: x, y: y + r * 0.35, 'text-anchor': 'middle', 'font-size': fs, class: 'oi-seat-n', 'aria-hidden': 'true' }, [info.seat_label]));
      });
    });
  }
  var SHAPES = { path: 1, circle: 1, rect: 1, ellipse: 1, polygon: 1, polyline: 1, line: 1, g: 1 };
  var SHAPE_ATTRS = ['d', 'cx', 'cy', 'r', 'rx', 'ry', 'x', 'y', 'width', 'height', 'points', 'x1', 'y1', 'x2', 'y2', 'transform', 'fill-rule', 'clip-rule', 'stroke-width', 'stroke-linecap', 'stroke-linejoin'];
  /** The venue's icon (a path or a whole SVG from the seating designer), rebuilt from plain shapes only. */
  function iconShapes(raw, fill) {
    raw = String(raw || '').trim();
    if (!raw) return null;
    var box = svg('svg', { viewBox: '0 0 24 24' }), g = svg('g', { fill: fill });
    box.appendChild(g);
    if (raw.indexOf('<svg') === -1) {
      if (!/^[MmLlHhVvCcSsQqTtAaZz0-9\s.,eE+-]+$/.test(raw)) return null;
      g.appendChild(svg('path', { d: raw }));
      return box;
    }
    var doc;
    try { doc = new DOMParser().parseFromString(raw, 'image/svg+xml'); } catch (e) { return null; }
    var src = doc && doc.documentElement;
    if (!src || String(src.nodeName).toLowerCase() !== 'svg' || doc.getElementsByTagName('parsererror').length) return null;
    var vb = String(src.getAttribute('viewBox') || '0 0 512 512');
    if (/^[\d\s.,-]+$/.test(vb)) box.setAttribute('viewBox', vb);
    (function copy(from, to) {
      [].forEach.call(from.childNodes, function (n) {
        var name = n.nodeType === 1 ? String(n.nodeName).toLowerCase() : '';
        if (!SHAPES[name]) return;
        var c = document.createElementNS(SVGNS, name);
        SHAPE_ATTRS.forEach(function (a) { var v = n.getAttribute(a); if (v != null && /^[\w\s.,#%()+-]*$/.test(v)) c.setAttribute(a, v); });
        copy(n, c);
        to.appendChild(c);
      });
    })(src, g);
    return g.childNodes.length ? box : null;
  }
  function iconSection(g, sec) {
    var meta = sec.metadata || {}, size = num(meta.icon_size) || 40, r = size / 2, x = num(sec.x), y = num(sec.y);
    g.appendChild(svg('circle', { cx: x + r, cy: y + r, r: r, fill: color(meta.background_color || sec.color_hex, '#3B82F6') }));
    var inner = size * 0.6, shapes = iconShapes(sec.icon_svg, color(meta.icon_color, '#FFFFFF'));
    if (shapes) {
      shapes.setAttribute('x', x + (size - inner) / 2);
      shapes.setAttribute('y', y + (size - inner) / 2);
      shapes.setAttribute('width', inner);
      shapes.setAttribute('height', inner);
      g.appendChild(shapes);
    }
    var label = F.flat(sec.icon_label || sec.name);
    if (label) g.appendChild(svg('text', { x: x + r, y: y + size + 12, 'text-anchor': 'middle', 'font-size': 10, 'font-weight': 500, fill: '#1F2937', 'aria-hidden': 'true' }, [label]));
  }
  function decoSection(g, sec) {
    var meta = sec.metadata || {}, shape = meta.shape || 'polygon', fill = color(sec.background_color || sec.color_hex, '#10B981'), x = num(sec.x), y = num(sec.y);
    var opacity = Math.min(1, Math.max(0, parseFloat(meta.opacity) || 0.3));
    if (shape === 'polygon' && Array.isArray(meta.points)) {
      var pts = [];
      for (var i = 0; i + 1 < meta.points.length; i += 2) pts.push((num(meta.points[i]) - x) + ',' + (num(meta.points[i + 1]) - y));
      var wrap = svg('g', { transform: 'translate(' + x + ',' + y + ')' });
      wrap.appendChild(svg('polygon', { points: pts.join(' '), fill: fill, opacity: opacity, stroke: fill, 'stroke-width': 1 }));
      var name = F.flat(meta.label || sec.name);
      if (name) wrap.appendChild(svg('text', { x: 10, y: 20, 'font-size': 12, fill: '#1F2937', opacity: 0.8, 'aria-hidden': 'true' }, [name]));
      g.appendChild(wrap);
    } else if (shape === 'text') {
      var fs = parseInt(meta.fontSize, 10) || 16, family = /^[\w\s,'"-]+$/.test(String(meta.fontFamily || '')) ? meta.fontFamily : 'Arial';
      var weight = /^(normal|bold|bolder|lighter|[1-9]00)$/.test(String(meta.fontWeight || '')) ? meta.fontWeight : 'normal';
      g.appendChild(svg('text', { x: x, y: num(sec.height) > 0 ? y + num(sec.height) / 2 : y + fs, 'dominant-baseline': 'central', 'font-size': fs, 'font-family': family, 'font-weight': weight, fill: fill, 'aria-hidden': 'true' }, [F.flat(meta.text || sec.name) || 'Text']));
    } else if (shape === 'line') {
      var lp = Array.isArray(meta.points) ? meta.points : [0, 0, 100, 0];
      g.appendChild(svg('line', { x1: x + num(lp[0]), y1: y + num(lp[1]), x2: x + (lp[2] != null ? num(lp[2]) : 100), y2: y + num(lp[3]), stroke: color(meta.strokeColor, fill), 'stroke-width': parseInt(meta.strokeWidth, 10) || 2, 'stroke-linecap': 'round' }));
    }
  }
  function paintSeat(c) {
    var uid = c.getAttribute('data-uid'), info = seatInfo[uid] || {}, off = info.status !== 'available', mine = !off && draft.some(function (s) { return s.seat_uid === uid; });
    c.classList.toggle('is-off', off);
    c.classList.toggle('is-mine', mine);
    c.setAttribute('aria-checked', String(mine));
  }
  function paintAll() { qsa('#oi-map .oi-seat').forEach(paintSeat); }
  function toggleSeat(c) {
    var uid = c.getAttribute('data-uid'), info = seatInfo[uid];
    if (!info || info.status !== 'available') return;
    var at = -1;
    draft.forEach(function (s, i) { if (s.seat_uid === uid) at = i; });
    if (at > -1) draft.splice(at, 1);
    else {
      if (draft.length >= MAX) { showErr('oi-map-err', 'Poți alege cel mult 50 de locuri într-o serie.'); return; }
      draft.push({ seat_uid: uid, section_name: info.section_name, row_label: info.row_label, seat_label: info.seat_label });
    }
    showErr('oi-map-err', '');
    paintSeat(c);
    countMap();
  }
  $('oi-map').addEventListener('click', function (e) { var c = e.target && e.target.closest ? e.target.closest('.oi-seat') : null; if (c) toggleSeat(c); });
  $('oi-map').addEventListener('keydown', function (e) {
    var c = e.target && e.target.closest ? e.target.closest('.oi-seat') : null;
    if (c && (e.key === 'Enter' || e.key === ' ')) { e.preventDefault(); toggleSeat(c); }
  });
  $('oi-map-clear').addEventListener('click', function () { draft = []; paintAll(); countMap(); showErr('oi-map-err', ''); });
  $('oi-map-ok').addEventListener('click', function () {
    if (!draft.length) { showErr('oi-map-err', 'Alege cel puțin un loc înainte de a confirma.'); return; }
    picked = sortSeats(draft.slice());
    pickedSeatingId = seating && seating.event_seating_id;
    closeDialog($('oi-map-d'));
    showErr('oi-seats-err', '');
    drawChips();
  });

  /* pan + zoom: mouse drag and wheel, one-finger pan and pinch; a drag never toggles the seat under it */
  function applyView() {
    $('oi-map').style.transform = 'translate(' + view.x + 'px,' + view.y + 'px) scale(' + view.zoom + ')';
    $('oi-zoom-l').textContent = Math.round(view.zoom * 100) + '%';
  }
  function canvasSize() { var c = (seating && seating.canvas) || {}; return { w: num(c.width) || 1000, h: num(c.height) || 800 }; }
  function fit() {
    var box = $('oi-map-body').getBoundingClientRect(), c = canvasSize();
    if (!box.width || !box.height) return;
    var z = Math.min((box.width - 24) / c.w, (box.height - 24) / c.h);
    view.min = Math.min(0.4, z);
    view.zoom = Math.max(view.min, Math.min(view.max, z));
    view.x = (box.width - c.w * view.zoom) / 2;
    view.y = (box.height - c.h * view.zoom) / 2;
    applyView();
  }
  function zoomAt(delta, fx, fy) {
    var box = $('oi-map-body').getBoundingClientRect();
    if (typeof fx !== 'number') { fx = box.width / 2; fy = box.height / 2; }
    var next = Math.max(view.min, Math.min(view.max, view.zoom + delta));
    if (next === view.zoom) return;
    var k = next / view.zoom;
    view.x = fx - (fx - view.x) * k;
    view.y = fy - (fy - view.y) * k;
    view.zoom = next;
    applyView();
  }
  function bindPanZoom() {
    if (panBound) return;
    panBound = true;
    var body = $('oi-map-body'), host = $('oi-map'), drag = null, moved = false, pinch = 0, pinchZoom = 1, touch = null;
    $('oi-zoom-in').addEventListener('click', function () { zoomAt(0.2); });
    $('oi-zoom-out').addEventListener('click', function () { zoomAt(-0.2); });
    $('oi-zoom-fit').addEventListener('click', fit);
    body.addEventListener('wheel', function (e) {
      if (e.target.closest && e.target.closest('.oi-zoom')) return;
      e.preventDefault();
      var r = body.getBoundingClientRect();
      zoomAt(e.deltaY > 0 ? -0.15 : 0.15, e.clientX - r.left, e.clientY - r.top);
    }, { passive: false });
    body.addEventListener('mousedown', function (e) {
      if (e.button !== 0 || (e.target.closest && e.target.closest('.oi-zoom'))) return;
      drag = { x: e.clientX, y: e.clientY, vx: view.x, vy: view.y };
      moved = false;
    });
    window.addEventListener('mousemove', function (e) {
      if (!drag) return;
      var dx = e.clientX - drag.x, dy = e.clientY - drag.y;
      if (!moved && (Math.abs(dx) > 3 || Math.abs(dy) > 3)) { moved = true; host.classList.add('is-dragging'); }
      if (!moved) return;
      view.x = drag.vx + dx;
      view.y = drag.vy + dy;
      applyView();
    });
    window.addEventListener('mouseup', function () { if (drag) { drag = null; host.classList.remove('is-dragging'); } });
    body.addEventListener('click', function (e) { if (moved) { e.stopPropagation(); e.preventDefault(); moved = false; } }, true);
    body.addEventListener('touchstart', function (e) {
      if (e.touches.length === 2) {
        pinch = Math.hypot(e.touches[0].clientX - e.touches[1].clientX, e.touches[0].clientY - e.touches[1].clientY);
        pinchZoom = view.zoom;
        touch = null;
        e.preventDefault();
      } else if (e.touches.length === 1) {
        touch = { x: e.touches[0].clientX, y: e.touches[0].clientY, vx: view.x, vy: view.y, moved: false };
      }
    }, { passive: false });
    body.addEventListener('touchmove', function (e) {
      if (e.touches.length === 2 && pinch > 0) {
        e.preventDefault();
        var dist = Math.hypot(e.touches[0].clientX - e.touches[1].clientX, e.touches[0].clientY - e.touches[1].clientY), r = body.getBoundingClientRect();
        var target = Math.max(view.min, Math.min(view.max, pinchZoom * dist / pinch));
        zoomAt(target - view.zoom, (e.touches[0].clientX + e.touches[1].clientX) / 2 - r.left, (e.touches[0].clientY + e.touches[1].clientY) / 2 - r.top);
      } else if (e.touches.length === 1 && touch) {
        var dx = e.touches[0].clientX - touch.x, dy = e.touches[0].clientY - touch.y;
        if (!touch.moved && (Math.abs(dx) > 4 || Math.abs(dy) > 4)) touch.moved = true;
        if (touch.moved) { e.preventDefault(); view.x = touch.vx + dx; view.y = touch.vy + dy; applyView(); }
      }
    }, { passive: false });
    body.addEventListener('touchend', function (e) {
      if (e.touches.length < 2) pinch = 0;
      if (e.touches.length === 0 && touch) {
        if (touch.moved) {
          var swallow = function (ev2) { ev2.stopPropagation(); ev2.preventDefault(); body.removeEventListener('click', swallow, true); };
          body.addEventListener('click', swallow, true);
          setTimeout(function () { body.removeEventListener('click', swallow, true); }, 400);
        }
        touch = null;
      }
    });
  }

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
  qsa('dialog', root).forEach(function (d) {
    d.addEventListener('click', function (e) { if (!d.hasAttribute('data-busy') && e.target.closest && e.target.closest('[data-close]')) closeDialog(d); });
    d.addEventListener('cancel', function (e) { if (d.hasAttribute('data-busy')) e.preventDefault(); });
    d.addEventListener('close', function () {
      if (d.id === 'oi-confirm-d') confirmRun = null;
      if (qsa('dialog', root).some(function (x) { return x.open; })) return;
      var back = opener && document.contains(opener) ? opener : (openerKey && root.querySelector('[data-focus="' + openerKey + '"]')) || root.querySelector('#oi-batches button') || $('oi-next');
      opener = null;
      openerKey = null;
      if (back && typeof back.focus === 'function' && !back.disabled && back.getClientRects().length) back.focus();
    });
  });

  O.ready.then(function (ok) {
    if (!ok) return;
    if (EVENT) loadEvent(); else choose(null);
  });
})();
