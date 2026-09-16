/* bilete.online v2: the venue's settings (/organizator/locatie/setari), ported from Ambilet's leisure.php.
   Four independent parts, each loaded on its own: the ticket types with their issuing company (from the venue config),
   the sales per issuing company over a period, the two issuing companies (saved one at a time, only the fields core
   accepts) and the access gates of the venue. Runs inside the organizer shell (window.BO_ORG); text from the API is
   always written as text. */
(function () {
  'use strict';
  var O = window.BO_ORG, root = document.getElementById('ve');
  if (!O || !root) return;
  var F = O.fmt, el = O.el;
  var $ = function (id) { return document.getElementById(id); };
  var CAT = { access: 'Acces', parking: 'Parcare', rental: 'Închiriere', activity: 'Activitate', extra: 'Alt produs', package: 'Pachet' };
  var GATE = { entry: 'Intrare', exit: 'Ieșire', vip: 'VIP', pos: 'Casă / POS' };
  var FIELDS = ['name', 'tax_id', 'registration', 'address', 'city', 'county', 'zip', 'bank_name', 'iban', 'invoice_series', 'next_invoice_number', 'vat_payer', 'vat_rate'];
  var events = [], eventId = null, venueId = null, issuerNames = {}, gates = [], editGate = null, delArmed = false, lastFocus = null, repSeq = 0;

  function txt(v) { return F.flat(v).trim(); }
  function show(id, on) { var e = $(id); if (e) e.hidden = !on; }
  function day(v) { var d = F.dateOf(v); return d ? F.date(d, { day: 'numeric', month: 'short', year: 'numeric' }) : '—'; }
  function amount(v, cur) {
    var c = String(cur || 'RON').toUpperCase(), lei = F.money(F.toNum(v));
    return c === 'RON' || c === 'LEI' ? lei : lei.replace(/ lei$/, '') + ' ' + c;
  }
  function state(bodyId, cols, text) {
    var body = $(bodyId);
    body.textContent = '';
    body.appendChild(el('tr', null, el('td', { colspan: cols, class: 've-state', text: text })));
  }
  function issuerLabel(key) {
    return txt(issuerNames[key]) || (key === 'secondary' ? 'Societatea secundară' : 'Societatea principală');
  }

  /* =================== ticket types =================== */
  function loadConfig() {
    state('vx-types', 5, 'Se încarcă…');
    return O.api('/organizer/events/' + eventId + '/leisure/config').then(function (r) {
      var d = (r && r.data) || {}, iss = d.issuers || {};
      issuerNames = { primary: iss.primary && iss.primary.name, secondary: iss.secondary && iss.secondary.name };
      var types = Array.isArray(d.ticket_types) ? d.ticket_types : [], body = $('vx-types');
      body.textContent = '';
      if (!types.length) { state('vx-types', 5, 'Locația nu are încă tipuri de bilete.'); return; }
      types.forEach(function (t) {
        var company = t.issuing_company === 'secondary' ? 'secondary' : 'primary';
        body.appendChild(el('tr', null, [
          el('td', null, el('b', { text: txt(t.name) || '—' })),
          el('td', { text: CAT[t.service_category] || txt(t.service_category) || '—' }),
          el('td', { class: 've-r', text: t.daily_capacity ? F.num(F.toNum(t.daily_capacity)) : '—' }),
          el('td', null, [el('span', { class: 'org-tag ' + (company === 'secondary' ? 'is-info' : 'is-ok'), text: company === 'secondary' ? 'Secundară' : 'Principală' }), document.createTextNode(' ' + issuerLabel(company))]),
          el('td', null, el('span', { class: 'org-tag ' + (t.is_active === false ? 'is-muted' : 'is-ok'), text: t.is_active === false ? 'Oprit' : 'Activ' })),
        ]));
      });
    }, function (err) {
      if (err && err.status === 401) return;
      state('vx-types', 5, 'Nu am putut încărca tipurile de bilete.');
    });
  }

  /* =================== sales per issuing company =================== */
  function loadReport() {
    var f = $('vx-from').value, t = $('vx-to').value, box = $('vx-rep'), my = ++repSeq;
    if (f && t && f > t) { O.flash('Data de început e după cea de sfârșit.', true); return; }
    box.textContent = '';
    box.appendChild(el('p', { class: 've-state', text: 'Se încarcă…' }));
    O.api('/organizer/events/' + eventId + '/leisure/reports/by-issuer?from=' + f + '&to=' + t).then(function (r) {
      if (my !== repSeq) return;
      var d = (r && r.data) || {}, rows = Array.isArray(d.rows) ? d.rows : [];
      $('vx-rep-period').textContent = 'Plătite între ' + day(d.from || f) + ' și ' + day(d.to || t) + '.';
      box.textContent = '';
      if (!rows.length || rows.every(function (x) { return !F.toNum(x.tickets_count); })) {
        box.appendChild(el('p', { class: 've-state', text: 'Nicio vânzare în perioada aleasă.' }));
        return;
      }
      rows.forEach(function (row) {
        var key = row.company === 'secondary' ? 'secondary' : 'primary', iss = row.issuer || {};
        var card = el('article', { class: 've-issuer' + (key === 'secondary' ? ' is-second' : '') }, [
          el('p', { class: 've-issuer-k' }, [O.icon('buildings'), document.createTextNode(key === 'secondary' ? 'Societatea secundară' : 'Societatea principală')]),
          el('h3', { class: 've-issuer-n', text: txt(iss.name) || issuerLabel(key) }),
          el('p', { class: 've-issuer-sum' }, [el('b', { text: amount(row.subtotal, d.currency) })]),
          el('p', { class: 've-sub ve-issuer-facts', text: [txt(iss.tax_id) ? 'CUI ' + txt(iss.tax_id) : '', F.count(F.toNum(row.tickets_count), 'bilet', 'bilete'), F.count(F.toNum(row.orders_count), 'comandă', 'comenzi')].filter(Boolean).join(' · ') }),
        ]);
        var cats = Object.keys(row.by_category || {});
        if (cats.length) {
          var list = el('ul', { class: 've-issuer-pay' });
          cats.forEach(function (c) {
            var info = row.by_category[c] || {};
            list.appendChild(el('li', null, [el('span', { text: CAT[c] || c }), el('span', { class: 've-sub', text: F.num(F.toNum(info.count)) }), el('b', { text: amount(info.subtotal, d.currency) })]));
          });
          card.appendChild(list);
        }
        box.appendChild(card);
      });
      box.classList.toggle('is-two', box.children.length > 1);
    }, function (err) {
      if (my !== repSeq) return;
      if (err && err.status === 401) return;
      box.textContent = '';
      box.appendChild(el('p', { class: 've-state', text: 'Nu am putut încărca vânzările pe societate.' }));
    });
  }

  /* =================== issuing companies =================== */
  function form(key) { return root.querySelector('[data-form="' + key + '"]'); }
  function input(key, field) { return form(key).querySelector('[data-f="' + field + '"]'); }
  function loadIssuers() {
    show('vx-iss', false);
    show('vx-iss-state', true);
    $('vx-iss-state').textContent = 'Se încarcă…';
    return O.api('/organizer/events/' + eventId + '/leisure/issuers').then(function (r) {
      var d = (r && r.data) || {};
      fill('primary', d.primary || {});
      fill('secondary', d.secondary || {});
      $('vx-sec-on').checked = !!d.has_secondary_issuer;
      secondaryState();
      show('vx-iss', true);
      show('vx-iss-state', false);
    }, function (err) {
      if (err && err.status === 401) return;
      $('vx-iss-state').textContent = 'Nu am putut încărca societățile emitente.';
    });
  }
  function fill(key, data) {
    FIELDS.forEach(function (field) {
      var i = input(key, field);
      if (!i) return;
      if (i.type === 'checkbox') i.checked = !!data[field];
      else if (field === 'next_invoice_number') { i.value = ''; i.placeholder = String(F.toNum(data.next_invoice_number) || 1); }
      else i.value = data[field] == null ? '' : String(data[field]);
    });
    var last = F.toNum(data.last_invoice_number), hint = form(key).querySelector('[data-next-hint]');
    hint.textContent = last > 0 ? 'Ultima emisă: ' + F.num(last) + '. Gol = continuă de la ' + F.num(last + 1) + '.' : 'Gol = începe de la 1.';
    vatState(key);
  }
  function vatState(key) {
    form(key).querySelector('[data-vat-rate]').hidden = !input(key, 'vat_payer').checked;
  }
  function secondaryState() {
    var on = $('vx-sec-on').checked;
    form('secondary').querySelectorAll('input').forEach(function (i) { i.disabled = !on; });
    form('secondary').classList.toggle('is-off', !on);
  }
  function collect(key) {
    var out = {};
    FIELDS.forEach(function (field) {
      var i = input(key, field), v;
      if (!i) return;
      if (i.type === 'checkbox') { out[field] = !!i.checked; return; }
      v = i.value.trim();
      if (field === 'next_invoice_number') { if (v !== '') out[field] = parseInt(v, 10); return; }
      if (field === 'vat_rate') { out[field] = v === '' ? null : parseFloat(v); return; }
      if (field === 'iban') v = v.replace(/\s+/g, '').toUpperCase();
      if (field === 'invoice_series') v = v.toUpperCase();
      out[field] = v === '' ? null : v;
    });
    if (!out.vat_payer) delete out.vat_rate;
    return out;
  }
  function saveIssuer(key, btn) {
    var fields = collect(key), sec = key === 'secondary';
    if (sec) fields.has_secondary_issuer = $('vx-sec-on').checked;
    var active = !sec || fields.has_secondary_issuer;
    if (active && (!fields.name || !fields.tax_id)) { O.flash('Scrie denumirea și CUI-ul societății.', true); return; }
    if (fields.next_invoice_number !== undefined && !(fields.next_invoice_number >= 1 && fields.next_invoice_number <= 9999999)) { O.flash('Numărul de factură trebuie să fie între 1 și 9.999.999.', true); return; }
    if (fields.vat_rate != null && !(fields.vat_rate >= 0 && fields.vat_rate <= 100)) { O.flash('Cota TVA trebuie să fie între 0 și 100.', true); return; }
    if (fields.iban && !/^[A-Z]{2}[0-9A-Z]{13,32}$/.test(fields.iban)) { O.flash('IBAN-ul nu pare corect.', true); return; }
    btn.disabled = true;
    O.api('/organizer/events/' + eventId + '/leisure/issuers', { method: 'PUT', body: { company: key, fields: fields } }).then(function () {
      O.flash(sec ? 'Societatea secundară a fost salvată.' : 'Societatea principală a fost salvată.');
      loadIssuers();
      loadConfig();
    }, function (err) {
      var first = err && err.errors && Object.keys(err.errors)[0];
      O.flash((first && err.errors[first] && err.errors[first][0]) || (err && err.message) || 'Nu am putut salva societatea.', true);
    }).then(function () { btn.disabled = false; });
  }

  /* =================== gates =================== */
  function loadGates() {
    var list = $('vx-gates');
    list.textContent = '';
    var ev = events.filter(function (e) { return F.toNum(e.id) === eventId; })[0] || {};
    venueId = F.toNum(ev.venue_id) || null;
    $('vx-gate-add').disabled = !venueId;
    if (!venueId) {
      list.appendChild(el('li', { class: 've-state', text: 'Activitatea nu are încă o locație fizică. Alege locația în detaliile activității, apoi poți adăuga porți.' }));
      return Promise.resolve();
    }
    list.appendChild(el('li', { class: 've-state', text: 'Se încarcă…' }));
    return O.api('/organizer/venues/' + venueId + '/gates').then(function (r) {
      var d = (r && r.data) || {}, v = d.venue || {};
      gates = Array.isArray(d.gates) ? d.gates : [];
      $('vx-venue-line').textContent = 'Punctele pe unde se scanează biletele' + (txt(v.name) ? ' la ' + txt(v.name) : '') + (txt(v.city) ? ', ' + txt(v.city) : '') + '.';
      drawGates();
    }, function (err) {
      if (err && err.status === 401) return;
      list.textContent = '';
      list.appendChild(el('li', { class: 've-state', text: 'Nu am putut încărca porțile.' }));
    });
  }
  function drawGates() {
    var list = $('vx-gates');
    list.textContent = '';
    if (!gates.length) { list.appendChild(el('li', { class: 've-state', text: 'Nicio poartă încă. Adaug-o pe prima.' })); return; }
    gates.forEach(function (g) {
      var edit = el('button', { class: 'btn btn-ghost', type: 'button' });
      edit.appendChild(O.icon('pencil-simple'));
      edit.appendChild(document.createTextNode('Schimbă'));
      edit.addEventListener('click', function () { openGate(g); });
      list.appendChild(el('li', { class: 've-gate' + (g.is_active === false ? ' is-off' : '') }, [
        el('span', { class: 've-gate-ic' }, O.icon(g.type === 'pos' ? 'coins' : 'door-open')),
        el('span', { class: 've-gate-t' }, [el('b', { text: txt(g.name) || '—' }), el('small', { class: 've-sub', text: [GATE[g.type] || txt(g.type), txt(g.location)].filter(Boolean).join(' · ') })]),
        el('span', { class: 'org-tag ' + (g.is_active === false ? 'is-muted' : 'is-ok'), text: g.is_active === false ? 'Oprită' : 'În funcțiune' }),
        edit,
      ]));
    });
  }
  function openGate(g) {
    editGate = g || null;
    delArmed = false;
    lastFocus = document.activeElement;
    $('vx-modal-h').textContent = g ? 'Schimbă poarta' : 'Adaugă o poartă';
    $('vx-g-name').value = g ? txt(g.name) : '';
    $('vx-g-type').value = g && GATE[g.type] ? g.type : 'entry';
    $('vx-g-loc').value = g ? txt(g.location) : '';
    $('vx-g-active').checked = !g || g.is_active !== false;
    show('vx-g-active-wrap', !!g);
    show('vx-g-del', !!g);
    $('vx-g-del').lastChild.textContent = 'Șterge poarta';
    show('vx-modal', true);
    $('vx-g-name').focus();
  }
  function closeGate() {
    show('vx-modal', false);
    editGate = null;
    if (lastFocus && lastFocus.focus) lastFocus.focus();
  }
  function saveGate() {
    var body = { name: $('vx-g-name').value.trim(), type: $('vx-g-type').value, location: $('vx-g-loc').value.trim() || null };
    if (!body.name) { O.flash('Scrie numele porții.', true); $('vx-g-name').focus(); return; }
    if (editGate) body.is_active = $('vx-g-active').checked;
    var btn = $('vx-g-save'), base = '/organizer/venues/' + venueId + '/gates';
    btn.disabled = true;
    var req = editGate ? O.api(base + '/' + F.toNum(editGate.id), { method: 'PUT', body: body }) : O.api(base, { method: 'POST', body: body });
    req.then(function () {
      O.flash(editGate ? 'Poarta a fost schimbată.' : 'Poarta a fost adăugată.');
      closeGate();
      loadGates();
    }, function (err) {
      O.flash((err && err.message) || 'Nu am putut salva poarta.', true);
    }).then(function () { btn.disabled = false; });
  }
  function deleteGate() {
    if (!editGate) return;
    var btn = $('vx-g-del');
    if (!delArmed) { delArmed = true; btn.lastChild.textContent = 'Apasă din nou ca să ștergi'; return; }
    btn.disabled = true;
    O.api('/organizer/venues/' + venueId + '/gates/' + F.toNum(editGate.id), { method: 'DELETE' }).then(function () {
      O.flash('Poarta a fost ștearsă.');
      closeGate();
      loadGates();
    }, function (err) {
      O.flash((err && err.message) || 'Nu am putut șterge poarta.', true);
    }).then(function () { btn.disabled = false; });
  }

  /* =================== wiring =================== */
  $('vx-apply').addEventListener('click', loadReport);
  ['primary', 'secondary'].forEach(function (key) {
    input(key, 'vat_payer').addEventListener('change', function () { vatState(key); });
    root.querySelector('[data-save="' + key + '"]').addEventListener('click', function () { saveIssuer(key, this); });
  });
  $('vx-sec-on').addEventListener('change', secondaryState);
  $('vx-gate-add').addEventListener('click', function () { openGate(null); });
  $('vx-g-cancel').addEventListener('click', closeGate);
  $('vx-g-save').addEventListener('click', saveGate);
  $('vx-g-del').addEventListener('click', deleteGate);
  $('vx-modal').addEventListener('click', function (ev) { if (ev.target === $('vx-modal')) closeGate(); });
  document.addEventListener('keydown', function (ev) { if (ev.key === 'Escape' && !$('vx-modal').hidden) closeGate(); });
  $('ve-retry').addEventListener('click', function () { show('ve-failed', false); start(); });
  $('ve-event').addEventListener('change', function () { eventId = F.toNum(this.value); loadAll(); });

  function loadAll() {
    loadConfig().then(loadReport);
    loadIssuers();
    loadGates();
  }
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
      $('vx-from').value = F.ymd(new Date(Date.now() - 30 * 86400000));
      $('vx-to').value = F.ymd(new Date());
      show('ve-main', true);
      loadAll();
    }, function (err) {
      if (err && err.status === 401) return;
      show('ve-failed', true);
    });
  }
  O.ready.then(function (ok) { if (ok) start(); });
})();
