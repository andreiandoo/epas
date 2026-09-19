/* bilete.online v2: the operator's bookings (/organizator/rezervari). The last 30 days on top, then two views: one
   day (per product and start time, seats left, "nu s-a prezentat" for a paid visitor of a past or current day) and the
   full list by visit date with filters, pages and a CSV export (downloaded with the session token, since the proxy
   needs it). Uses window.BO_AM (org-am.js) inside the organizer shell; text from the API is written as text. */
(function () {
  'use strict';
  var O = window.BO_ORG, A = window.BO_AM, root = document.getElementById('am-bk');
  if (!O || !A || !root) return;
  var el = O.el, F = O.fmt;
  var $ = function (id) { return document.getElementById(id); };
  var STATUS = {
    pending: ['În așteptare', 'is-wait'], paid: ['Plătită', 'is-ok'], confirmed: ['Confirmată', 'is-ok'], checked_in: ['Validată', 'is-ok'],
    no_show: ['Nu s-a prezentat', 'is-bad'], cancelled: ['Anulată', 'is-muted'], expired: ['Expirată', 'is-muted'],
  };
  var today = F.ymd(new Date());
  var day = today, page = 1, daySeq = 0, allSeq = 0; // a late answer for an older question is dropped

  function lei(v) { return F.money(v || 0); }
  function addDays(ymd, n) { var d = new Date(ymd + 'T12:00:00'); d.setDate(d.getDate() + n); return F.ymd(d); }
  function loc() { return $('am-bk-loc').value || ''; }
  function qs(o) { return Object.keys(o).filter(function (k) { return o[k] !== '' && o[k] != null; }).map(function (k) { return encodeURIComponent(k) + '=' + encodeURIComponent(o[k]); }).join('&'); }
  function tag(status) { var s = STATUS[status] || [status, 'is-muted']; return el('span', { class: 'org-tag ' + s[1], text: s[0] }); }
  function dayLabel(ymd) { return F.date(new Date(ymd + 'T12:00:00'), { weekday: 'long', day: 'numeric', month: 'long' }); }

  /* ---------- summary ---------- */
  function kpi(label, value) { return el('div', { class: 'am-kpi' }, [el('small', { text: label }), el('b', { text: value })]); }
  function loadSummary() {
    var box = $('am-bk-kpis');
    A.api('/summary?' + qs({ location_id: loc() })).then(function (r) {
      var d = (r && r.data) || {}, t = d.totals || {}, a = d.arrivals_7_days || {};
      box.textContent = '';
      [kpi('Rezervări, 30 de zile', F.num(t.bookings || 0)), kpi('Persoane', F.num(t.persons || 0)), kpi('Vânzări', lei(t.value)),
        kpi('Îți rămân', lei(t.net)), kpi('Sosiri azi / 7 zile', F.num(a.today || 0) + ' / ' + F.num(a.persons || 0))].forEach(function (n) { box.appendChild(n); });
    }, function (err) {
      if (err && err.status === 401) return;
      box.textContent = '';
    });
  }

  /* ---------- one day ---------- */
  function bookingLine(b, isDayView) {
    var who = b.customer || {};
    var bits = [b.quantity + ' × ' + (b.variant || b.title), b.confirmation_code, who.phone, b.vehicle_plate ? 'mașina ' + b.vehicle_plate : null, b.package ? 'din „' + b.package + '”' : null].filter(Boolean);
    var tools = el('div', { class: 'am-bk-tools' }, [tag(b.status)]);
    if (isDayView && (b.status === 'paid' || b.status === 'confirmed') && b.date <= today) {
      var ns = A.button(null, 'Nu s-a prezentat', 'btn btn-ghost');
      ns.addEventListener('click', function () {
        ns.disabled = true;
        A.api('/bookings/' + b.id + '/no-show', { method: 'POST', body: {} }).then(function (r) {
          O.flash((r && r.message) || 'Marcată.');
          loadDay();
        }, function (err) { ns.disabled = false; O.flash(A.errText(err, 'Nu am putut marca rezervarea.'), true); });
      });
      tools.appendChild(ns);
    }
    return el('div', { class: 'am-bk' }, [
      el('div', null, [el('b', { text: who.name || 'Client' }), el('small', { text: bits.join(' · ') }),
        b.attendees && b.attendees.length > 1 ? el('small', { text: 'Pe bilete: ' + b.attendees.join(', ') }) : null,
        b.checked_in ? el('small', { text: 'Validate: ' + b.checked_in + ' din ' + b.tickets }) : null]),
      tools,
    ]);
  }
  function loadDay() {
    $('am-day').value = day;
    var body = $('am-day-body');
    body.textContent = '';
    body.appendChild(el('p', { class: 've-state', text: 'Se încarcă…' }));
    var seq = ++daySeq;
    A.api('/bookings/day?' + qs({ date: day, location_id: loc() })).then(function (r) {
      if (seq !== daySeq) return;
      var d = (r && r.data) || {};
      body.textContent = '';
      $('am-day-total').textContent = cap(dayLabel(day)) + ': ' + (d.persons || 0) + (d.persons === 1 ? ' persoană' : ' persoane');
      if (!(d.products || []).length) {
        body.appendChild(el('p', { class: 've-state', text: 'Nicio rezervare în această zi.' }));
        return;
      }
      d.products.forEach(function (p) {
        var cap_ = p.capacity ? ' · mai sunt ' + p.capacity.left + ' din ' + p.capacity.total : '';
        var card = el('div', { class: 'am-prod-day' }, [el('div', { class: 'am-prod-day-head' }, [
          el('h3', { text: p.title }),
          el('small', { text: [p.location, p.persons + ' pers.', p.checked_in ? p.checked_in + ' validate' : null].filter(Boolean).join(' · ') + cap_ }),
        ])]);
        (p.groups || []).forEach(function (g) {
          var slot = el('div', { class: 'am-slot' }, [el('p', { class: 'am-slot-k' }, [
            document.createTextNode(g.time === 'toată ziua' ? 'Toată ziua' : g.time),
            el('small', { text: ' · ' + g.persons + ' pers.' + (g.left != null ? ' · ' + g.left + ' locuri libere' : '') }),
          ])]);
          (g.bookings || []).forEach(function (b) { slot.appendChild(bookingLine(b, true)); });
          card.appendChild(slot);
        });
        body.appendChild(card);
      });
    }, function (err) {
      if (seq !== daySeq || (err && err.status === 401)) return;
      body.textContent = '';
      body.appendChild(el('p', { class: 've-state', text: A.errText(err, 'Nu am putut încărca ziua.') }));
    });
  }
  function cap(s) { s = String(s || ''); return s.charAt(0).toUpperCase() + s.slice(1); }

  /* ---------- the list ---------- */
  function listParams() {
    return { from: $('am-all-from').value, to: $('am-all-to').value, product_id: $('am-all-prod').value, status: $('am-all-status').value, q: $('am-all-q').value.trim(), location_id: loc() };
  }
  function loadAll() {
    var rows = $('am-all-rows');
    rows.textContent = '';
    rows.appendChild(el('tr', null, [el('td', { colspan: 5, class: 've-state', text: 'Se încarcă…' })]));
    var seq = ++allSeq;
    A.api('/bookings?' + qs(Object.assign(listParams(), { page: page, per_page: 50 }))).then(function (r) {
      if (seq !== allSeq) return;
      var d = (r && r.data) || {}, list = d.bookings || [], pg = d.pagination || {};
      rows.textContent = '';
      if (!list.length) rows.appendChild(el('tr', null, [el('td', { colspan: 5, class: 've-state', text: 'Nicio rezervare pentru filtrele alese.' })]));
      list.forEach(function (b) {
        var who = b.customer || {};
        rows.appendChild(el('tr', null, [
          el('td', null, [el('b', { text: b.date_label || b.date }), el('small', { text: b.time_label || '' })]),
          el('td', null, [el('b', { text: b.title }), el('small', { text: [b.quantity + ' × ' + (b.variant || ''), b.location, b.vehicle_plate].filter(Boolean).join(' · ') })]),
          el('td', null, [el('b', { text: who.name || '' }), el('small', { text: [who.email, who.phone, b.confirmation_code].filter(Boolean).join(' · ') })]),
          el('td', { text: lei(b.value) }),
          el('td', null, [tag(b.status)]),
        ]));
      });
      var last = pg.last_page || 1;
      $('am-all-pager').hidden = last <= 1;
      $('am-all-page').textContent = 'Pagina ' + (pg.current_page || 1) + ' din ' + last + ' · ' + (pg.total || 0) + ' rezervări';
      $('am-all-prev').disabled = page <= 1;
      $('am-all-next').disabled = page >= last;
    }, function (err) {
      if (seq !== allSeq || (err && err.status === 401)) return;
      rows.textContent = '';
      rows.appendChild(el('tr', null, [el('td', { colspan: 5, class: 've-state', text: A.errText(err, 'Nu am putut încărca rezervările.') })]));
    });
  }
  function exportCsv() {
    var token = typeof BileteOnlineAuth !== 'undefined' ? BileteOnlineAuth.getToken() : null;
    var p = listParams();
    var url = '/api/proxy.php?action=organizer.am.bookings.export&' + qs({ from: p.from, to: p.to, product_id: p.product_id, location_id: p.location_id });
    var b = $('am-all-export');
    b.disabled = true;
    fetch(url, { headers: token ? { Authorization: 'Bearer ' + token } : {} }).then(function (r) {
      if (!r.ok) throw new Error('HTTP ' + r.status);
      return r.blob();
    }).then(function (blob) {
      var a = el('a', { href: URL.createObjectURL(blob), download: 'rezervari-' + (p.from || today) + '-' + (p.to || today) + '.csv' });
      document.body.appendChild(a);
      a.click();
      setTimeout(function () { URL.revokeObjectURL(a.href); a.remove(); }, 1000);
    }, function () { O.flash('Nu am putut descărca exportul.', true); }).then(function () { b.disabled = false; });
  }

  /* ---------- wiring ---------- */
  function tab(which) {
    var dayOn = which === 'day';
    $('am-tab-day').setAttribute('aria-selected', String(dayOn));
    $('am-tab-all').setAttribute('aria-selected', String(!dayOn));
    $('am-bk-day').hidden = !dayOn;
    $('am-bk-all').hidden = dayOn;
    if (dayOn) loadDay(); else loadAll();
  }
  $('am-tab-day').addEventListener('click', function () { tab('day'); });
  $('am-tab-all').addEventListener('click', function () { tab('all'); });
  $('am-day-prev').addEventListener('click', function () { day = addDays(day, -1); loadDay(); });
  $('am-day-next').addEventListener('click', function () { day = addDays(day, 1); loadDay(); });
  $('am-day-today').addEventListener('click', function () { day = today; loadDay(); });
  $('am-day').addEventListener('change', function () { if (/^\d{4}-\d{2}-\d{2}$/.test($('am-day').value)) { day = $('am-day').value; loadDay(); } });
  $('am-all-form').addEventListener('submit', function (e) { e.preventDefault(); page = 1; loadAll(); });
  $('am-all-prev').addEventListener('click', function () { if (page > 1) { page--; loadAll(); } });
  $('am-all-next').addEventListener('click', function () { page++; loadAll(); });
  $('am-all-export').addEventListener('click', exportCsv);
  $('am-bk-loc').addEventListener('change', function () {
    loadSummary();
    if ($('am-bk-day').hidden) { page = 1; loadAll(); } else loadDay();
  });

  O.ready.then(function (ok) {
    if (!ok) return;
    $('am-all-from').value = today;
    $('am-all-to').value = addDays(today, 30);
    Promise.all([A.api('/locations'), A.api('/products')]).then(function (res) {
      var ls = (res[0] && res[0].data && res[0].data.locations) || [], ps = (res[1] && res[1].data && res[1].data.products) || [];
      ls.forEach(function (l) { $('am-bk-loc').appendChild(el('option', { value: String(l.id), text: l.name || ('Locația ' + l.id) })); });
      ps.forEach(function (p) { $('am-all-prod').appendChild(el('option', { value: String(p.id), text: p.title || ('Produsul ' + p.id) })); });
    }, function () {});
    loadSummary();
    loadDay();
  });
})();
