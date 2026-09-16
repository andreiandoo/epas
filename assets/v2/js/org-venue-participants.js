/* bilete.online v2: the venue's participants (/organizator/locatie/participanti), ported from Ambilet.
   Every filter is sent to the server — the period, the search, the status, the check-in, the ticket types and the
   visit day — so the four figures always describe the same set as the list. A ticket can be checked in by hand when
   its code cannot be scanned; the answer is idempotent, so pressing twice is safe. Runs inside the organizer shell
   (window.BO_ORG); text from the API is always written as text. */
(function () {
  'use strict';
  var O = window.BO_ORG, root = document.getElementById('ve');
  if (!O || !root) return;
  var F = O.fmt, el = O.el;
  var $ = function (id) { return document.getElementById(id); };
  var qsa = function (sel) { return Array.prototype.slice.call(root.querySelectorAll(sel)); };
  var PER = 100;
  var STATUS = { valid: ['Valid', 'is-ok'], used: ['Folosit', 'is-muted'], cancelled: ['Anulat', 'is-bad'], refunded: ['Restituit', 'is-wait'] };
  var CAT = { access: 'Acces', parking: 'Parcare', rental: 'Închiriere', activity: 'Activitate', extra: 'Extra', package: 'Pachet' };
  var events = [], eventId = null, range = '30', from = null, to = null;
  var rows = [], page = 1, lastPage = 1, total = 0, typesReady = false, loading = false, seq = 0, timer = null;

  function txt(v) { return F.flat(v).trim(); }
  function day(v) { var d = F.dateOf(v); return d ? F.date(d, { day: 'numeric', month: 'short', year: 'numeric' }) : '—'; }
  function stamp(v) { var d = F.dateOf(v); return d ? F.date(d, { day: '2-digit', month: '2-digit', year: '2-digit', hour: '2-digit', minute: '2-digit' }) : '—'; }
  function ymd(d) { return F.ymd(d); }
  function show(id, on) { var e = $(id); if (e) e.hidden = !on; }

  function selectedTypes() {
    return qsa('#vp-types-list input[type="checkbox"]:checked').map(function (c) { return c.value; });
  }
  function filters() {
    return {
      search: $('vp-q').value.trim(),
      status: $('vp-status').value,
      checkin: $('vp-checkin').value,
      ticket_type_id: selectedTypes().join(','),
      visit_from: $('vp-visit-from').value,
      visit_to: $('vp-visit-to').value,
    };
  }
  function anyFilter() {
    var f = filters();
    return !!(f.search || f.status || f.checkin || f.ticket_type_id || f.visit_from || f.visit_to);
  }
  function setRange(value) {
    range = value;
    qsa('.ve-range').forEach(function (b) { b.setAttribute('aria-pressed', String(b.getAttribute('data-range') === value)); });
    show('vp-custom', value === 'custom');
    if (value === 'custom') return;
    var end = new Date(), start = new Date(Date.now() - parseInt(value, 10) * 86400000);
    from = ymd(start);
    to = ymd(end);
    load(true);
  }

  function load(reset) {
    if (loading || !eventId) return;
    loading = true;
    var my = ++seq;
    if (reset) { page = 1; rows = []; }
    else { $('vp-more').disabled = true; }
    var f = filters(), q = ['per_page=' + PER, 'page=' + page];
    if (from) q.push('from=' + from);
    if (to) q.push('to=' + to);
    Object.keys(f).forEach(function (k) { if (f[k]) q.push(k + '=' + encodeURIComponent(f[k])); });
    if (reset) {
      var body = $('vp-rows');
      body.textContent = '';
      body.appendChild(el('tr', null, el('td', { colspan: 8, class: 've-state', text: 'Se încarcă…' })));
    }
    O.api('/organizer/events/' + eventId + '/leisure/participants?' + q.join('&')).then(function (r) {
      if (my !== seq) return;
      var d = (r && r.data) || {}, stats = d.stats || {}, meta = d.meta || {};
      $('vp-s-total').textContent = F.num(F.toNum(stats.total));
      $('vp-s-checked').textContent = F.num(F.toNum(stats.checked_in));
      $('vp-s-rate').textContent = F.pct(F.toNum(stats.rate), 1);
      $('vp-s-noshow').textContent = F.num(F.toNum(stats.no_show));
      $('vp-period').textContent = 'Plătite între ' + day(d.from) + ' și ' + day(d.to) + '.';
      if (!typesReady && Array.isArray(d.ticket_types)) drawTypes(d.ticket_types);
      page = F.toNum(meta.current_page) || page;
      lastPage = F.toNum(meta.last_page) || 1;
      total = F.toNum(meta.total);
      rows = rows.concat(Array.isArray(d.rows) ? d.rows : []);
      draw();
    }, function (err) {
      if (my !== seq) return;
      if (err && err.status === 401) return;
      var body = $('vp-rows');
      body.textContent = '';
      body.appendChild(el('tr', null, el('td', { colspan: 8, class: 've-state', text: 'Nu am putut încărca participanții.' })));
      $('vp-count').textContent = '';
      show('vp-more', false);
    }).then(function () {
      loading = false;
      $('vp-more').disabled = false;
    });
  }
  function drawTypes(list) {
    typesReady = true;
    var box = $('vp-types-list'), order = { access: 1, parking: 2, activity: 3, rental: 4, extra: 5, package: 6 };
    box.textContent = '';
    list.slice().sort(function (a, b) {
      return (order[a.service_category] || 9) - (order[b.service_category] || 9) || txt(a.name).localeCompare(txt(b.name));
    }).forEach(function (t) {
      var input = el('input', { type: 'checkbox', value: String(t.id) });
      input.addEventListener('change', function () { typesLabel(); reset(); });
      box.appendChild(el('label', { class: 'po-check' }, [input, el('span', null, [
        document.createTextNode(txt(t.name) + ' '),
        el('span', { class: 'org-tag is-muted', text: CAT[t.service_category] || txt(t.service_category) }),
      ])]));
    });
    if (!list.length) box.appendChild(el('p', { class: 've-state', text: 'Niciun tip de bilet.' }));
    typesLabel();
  }
  function typesLabel() {
    var n = selectedTypes().length;
    $('vp-types-label').textContent = n ? F.count(n, 'tip ales', 'tipuri alese') : 'Toate tipurile';
  }
  function draw() {
    var body = $('vp-rows');
    body.textContent = '';
    if (!rows.length) {
      body.appendChild(el('tr', null, el('td', { colspan: 8, class: 've-state', text: anyFilter() ? 'Niciun participant pentru filtrele alese.' : 'Niciun participant în perioada aleasă.' })));
      $('vp-count').textContent = '';
      show('vp-more', false);
      return;
    }
    rows.forEach(function (r) { body.appendChild(row(r)); });
    $('vp-count').textContent = 'Afișați ' + F.num(rows.length) + ' din ' + F.count(total || rows.length, 'participant', 'participanți') + '.';
    show('vp-more', page < lastPage);
  }
  function row(r) {
    var st = STATUS[r.status] || [txt(r.status) || '—', 'is-muted'];
    var tr = el('tr', null, [
      el('td', null, [el('b', { class: 've-mono', text: txt(r.order_number) || '—' }), el('small', { class: 've-sub', text: stamp(r.order_paid_at) })]),
      el('td', { class: 've-mono', text: txt(r.code) || txt(r.barcode) || '—' }),
      el('td', null, [el('b', { text: txt(r.customer_name) || '—' }), el('small', { class: 've-sub', text: txt(r.customer_email) })]),
      el('td', null, txt(r.vehicle_plate) ? el('span', { class: 've-plate', text: txt(r.vehicle_plate) }) : document.createTextNode('—')),
      el('td', null, [el('span', { text: txt(r.ticket_type) || '—' }), el('small', { class: 've-sub' }, el('span', { class: 'org-tag is-muted', text: CAT[r.service_category] || txt(r.service_category) }))]),
      el('td', { text: day(r.visit_date) }),
      el('td', null, el('span', { class: 'org-tag ' + st[1], text: st[0] })),
    ]);
    tr.appendChild(checkinCell(r));
    return tr;
  }
  function checkinCell(r) {
    var td = el('td');
    if (r.checked_in_at) {
      td.appendChild(el('span', { class: 've-checkin', text: '✓ ' + stamp(r.checked_in_at) }));
      return td;
    }
    if (['cancelled', 'refunded'].indexOf(r.status) > -1) {
      td.appendChild(document.createTextNode('—'));
      return td;
    }
    var btn = el('button', { class: 've-checkin-btn', type: 'button' });
    btn.appendChild(O.icon('check-circle'));
    btn.appendChild(document.createTextNode('Check-in manual'));
    btn.addEventListener('click', function () { checkIn(r, btn, td); });
    td.appendChild(btn);
    return td;
  }
  function checkIn(r, btn, td) {
    btn.disabled = true;
    O.api('/organizer/events/' + eventId + '/leisure/tickets/' + F.toNum(r.id) + '/manual-checkin', { method: 'POST', body: {} }).then(function (res) {
      var at = (res && res.data && res.data.checked_in_at) || new Date().toISOString();
      r.checked_in_at = at;
      if (r.status === 'valid') r.status = 'used';
      td.textContent = '';
      td.appendChild(el('span', { class: 've-checkin', text: '✓ ' + stamp(at) }));
      O.flash((res && res.data && res.data.was_already_checked_in) ? 'Biletul era deja validat.' : 'Check-in făcut pentru ' + (txt(r.code) || 'bilet') + '.');
      var checked = F.toNum($('vp-s-checked').textContent.replace(/\D/g, '')) + 1;
      $('vp-s-checked').textContent = F.num(checked);
    }, function (err) {
      btn.disabled = false;
      O.flash((err && err.message) || 'Nu am putut face check-in.', true);
    });
  }
  function reset() {
    page = 1;
    load(true);
    $('vp-reset').hidden = !anyFilter();
  }
  function exportCsv() {
    if (!rows.length) { O.flash('Nu e nimic de exportat.', true); return; }
    var head = ['Comanda', 'Platita la', 'Cod bilet', 'Nume', 'Email', 'Nr. inmatriculare', 'Tip bilet', 'Categorie', 'Societate', 'Data vizitei', 'Status', 'Check-in'];
    var cell = function (v) { return '"' + String(v == null ? '' : v).replace(/"/g, '""') + '"'; };
    var lines = [head.map(cell).join(',')].concat(rows.map(function (r) {
      return [r.order_number, r.order_paid_at, r.code || r.barcode, r.customer_name, r.customer_email, r.vehicle_plate,
        txt(r.ticket_type), r.service_category, r.issuing_company, r.visit_date, r.status, r.checked_in_at].map(cell).join(',');
    }));
    var blob = new Blob(['﻿' + lines.join('\r\n')], { type: 'text/csv;charset=utf-8' });
    var a = document.createElement('a'), href = URL.createObjectURL(blob);
    a.href = href;
    a.download = 'participanti-' + (from || ymd(new Date())) + '.csv';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    setTimeout(function () { URL.revokeObjectURL(href); }, 4000);
    O.flash('Lista a fost descărcată.');
  }

  /* =================== wiring =================== */
  qsa('.ve-range').forEach(function (b) { b.addEventListener('click', function () { setRange(b.getAttribute('data-range')); }); });
  $('vp-apply').addEventListener('click', function () {
    var f = $('vp-from').value, t = $('vp-to').value;
    if (!f || !t) { O.flash('Alege ambele date.', true); return; }
    if (f > t) { O.flash('Data de început e după cea de sfârșit.', true); return; }
    from = f;
    to = t;
    load(true);
  });
  $('vp-q').addEventListener('input', function () { clearTimeout(timer); timer = setTimeout(reset, 300); });
  ['vp-status', 'vp-checkin', 'vp-visit-from', 'vp-visit-to'].forEach(function (id) { $(id).addEventListener('change', reset); });
  $('vp-reset').addEventListener('click', function () {
    $('vp-q').value = '';
    $('vp-status').value = '';
    $('vp-checkin').value = '';
    $('vp-visit-from').value = '';
    $('vp-visit-to').value = '';
    qsa('#vp-types-list input[type="checkbox"]').forEach(function (c) { c.checked = false; });
    typesLabel();
    reset();
  });
  $('vp-more').addEventListener('click', function () { page++; load(false); });
  $('vp-csv').addEventListener('click', exportCsv);
  $('ve-retry').addEventListener('click', function () { show('ve-failed', false); start(); });
  $('ve-event').addEventListener('change', function () {
    eventId = F.toNum(this.value);
    typesReady = false;
    load(true);
  });

  function loadEvents() {
    return O.api('/organizer/events?per_page=50&page=1').then(function (r) {
      var list = Array.isArray(r && r.data) ? r.data : (r && r.data && r.data.items) || [];
      events = list.filter(function (e) { return e && e.id != null; });
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
      setRange('30');
    }, function (err) {
      if (err && err.status === 401) return;
      show('ve-failed', true);
    });
  }
  O.ready.then(function (ok) { if (ok) start(); });
})();
