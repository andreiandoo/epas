/* bilete.online v2: organizer billing (/organizator/facturare). Invoices from /organizer/invoices (status filter, pages),
   payouts from /organizer/payouts (status filter), billing details from /organizer/billing-info, the detail window for an
   invoice (printed or saved as PDF from the browser: core has no invoice PDF route) or a payout, and the CSV export
   fetched with the session token in a header and checked before saving. Runs inside the organizer shell
   (window.BO_ORG); text from the API is always written as text. */
(function () {
  'use strict';
  var O = window.BO_ORG, root = document.getElementById('ob');
  if (!O || !root) return;
  var F = O.fmt, el = O.el, icon = O.icon;
  var $ = function (id) { return document.getElementById(id); };
  function qsa(sel) { return [].slice.call(root.querySelectorAll(sel)); }

  var PER = 10;
  var INV = { paid: ['Plătită', 'is-ok'], pending: ['În așteptare', 'is-wait'], overdue: ['Restantă', 'is-bad'], cancelled: ['Anulată', 'is-muted'], refunded: ['Stornată', 'is-muted'] };
  var PAY = { pending: ['În așteptare', 'is-wait'], approved: ['Aprobat', 'is-info'], processing: ['În procesare', 'is-info'], completed: ['Finalizat', 'is-ok'], rejected: ['Respins', 'is-bad'], cancelled: ['Anulat', 'is-muted'] };
  var inv = { filter: 'all', page: 1, total: 0, rows: [], seq: 0 }, pay = { filter: 'all', rows: null, seq: 0 }, opener = null, dSeq = 0;

  function txt(v) { return F.flat(v).trim(); }
  function tag(map, s) { var t = map[s] || [txt(s) || '—', 'is-muted']; return el('span', { class: 'org-tag ' + t[1], text: t[0] }); }
  function day(v) { var d = F.dateOf(v); return d ? F.date(d, { day: 'numeric', month: 'short', year: 'numeric' }) : '—'; }
  function money(v) { return F.money(F.toNum(v)); }
  function state(id, kind, retry) {
    var box = $(id);
    box.textContent = '';
    box.hidden = !kind;
    if (kind === 'loading') box.textContent = 'Se încarcă…';
    else if (kind === 'error') {
      var b = el('button', { type: 'button', text: 'Reîncearcă' });
      b.addEventListener('click', retry);
      box.appendChild(el('b', { text: 'Nu am putut încărca lista' }));
      box.appendChild(document.createTextNode('Verifică conexiunea și încearcă din nou.'));
      box.appendChild(el('br'));
      box.appendChild(b);
    } else if (kind) { box.appendChild(el('b', { text: kind[0] })); box.appendChild(document.createTextNode(kind[1])); }
  }
  function busyBtn(btn, on, text) {
    var l = btn.querySelector('[data-label]');
    if (on) { btn.setAttribute('aria-busy', 'true'); if (l) { btn.setAttribute('data-idle', l.textContent); l.textContent = text; } }
    else { btn.removeAttribute('aria-busy'); if (l && btn.hasAttribute('data-idle')) l.textContent = btn.getAttribute('data-idle'); btn.removeAttribute('data-idle'); }
  }
  function pill(ic, text, label, onClick) {
    var b = el('button', { class: 'ob-pill', type: 'button', 'aria-label': label }, [icon(ic), text]);
    b.addEventListener('click', function () { onClick(b); });
    return b;
  }

  /* =================== TABS + FILTERS =================== */
  function setSection(s, focus) {
    ['invoices', 'payouts'].forEach(function (k) {
      var on = k === s, t = $('ob-tab-' + k);
      t.setAttribute('aria-selected', String(on));
      t.tabIndex = on ? 0 : -1;
      $('ob-' + k).hidden = !on;
    });
    if (s === 'payouts' && !pay.rows) loadPayouts();
    if (focus) $('ob-tab-' + s).focus();
    try { history.replaceState(null, '', location.pathname + location.search + (s === 'payouts' ? '#deconturi' : '')); } catch (e) {}
  }
  ['invoices', 'payouts'].forEach(function (k, i, all) {
    var t = $('ob-tab-' + k);
    t.addEventListener('click', function () { setSection(k); });
    t.addEventListener('keydown', function (e) {
      if (e.key !== 'ArrowRight' && e.key !== 'ArrowLeft') return;
      e.preventDefault();
      setSection(all[(i + 1) % 2], true);
    });
  });
  function segment(attr, onPick) {
    qsa('[data-' + attr + ']').forEach(function (b) {
      b.addEventListener('click', function () {
        qsa('[data-' + attr + ']').forEach(function (x) { x.setAttribute('aria-pressed', String(x === b)); });
        onPick(b.getAttribute('data-' + attr));
      });
    });
  }
  segment('inv', function (v) { inv.filter = v; inv.page = 1; loadInvoices(); });
  segment('pay', function (v) { pay.filter = v; drawPayouts(); });

  /* =================== INVOICES =================== */
  function loadInvoices() {
    var my = ++inv.seq;
    $('ob-inv-rows').textContent = '';
    state('ob-inv-state', 'loading');
    O.api('/organizer/invoices?page=' + inv.page + '&per_page=' + PER + (inv.filter !== 'all' ? '&status=' + inv.filter : '')).then(function (r) {
      if (my !== inv.seq) return;
      var d = (r && r.data) || {};
      inv.rows = Array.isArray(d.invoices) ? d.invoices : [];
      inv.total = F.toNum(d.total);
      drawInvoices();
    }, function (err) {
      if (my !== inv.seq || (err && err.status === 401)) return;
      $('ob-inv-pages').hidden = true;
      state('ob-inv-state', 'error', loadInvoices);
    });
  }
  function drawInvoices() {
    var body = $('ob-inv-rows'), pages = Math.max(1, Math.ceil(inv.total / PER)), start = inv.total ? (inv.page - 1) * PER + 1 : 0;
    body.textContent = '';
    inv.rows.forEach(function (x) {
      var id = String(x.id), num = txt(x.number) || '#' + id;
      body.appendChild(el('tr', null, [
        el('td', null, el('span', { class: 'ob-num', text: num })),
        el('td', { class: 'ob-muted', text: day(x.date) }),
        el('td', { class: 'ob-md', text: txt(x.description) || '—' }),
        el('td', { class: 'ob-amount', text: money(x.amount) }),
        el('td', null, tag(INV, x.status)),
        el('td', null, el('div', { class: 'ob-acts' }, [
          pill('file-text', 'Vezi', 'Vezi factura ' + num, function (b) { openInvoice(id, b, false); }),
          pill('download-simple', 'PDF', 'Printează sau salvează PDF factura ' + num, function (b) { openInvoice(id, b, true); }),
        ])),
      ]));
    });
    state('ob-inv-state', inv.rows.length ? null : inv.filter === 'all' ? ['Nicio factură', 'Nu ai nicio factură încă.'] : ['Nicio factură', 'Nicio factură cu acest status.']);
    $('ob-inv-info').textContent = 'Afișare ' + start + '-' + Math.min(inv.page * PER, inv.total) + ' din ' + F.count(inv.total, 'factură', 'facturi');
    $('ob-inv-pages').hidden = pages <= 1;
    $('ob-inv-page').textContent = 'Pagina ' + inv.page + ' din ' + pages;
    $('ob-inv-prev').disabled = inv.page <= 1;
    $('ob-inv-next').disabled = inv.page >= pages;
  }
  $('ob-inv-prev').addEventListener('click', function () { if (inv.page > 1) { inv.page--; loadInvoices(); } });
  $('ob-inv-next').addEventListener('click', function () { inv.page++; loadInvoices(); });

  /* =================== PAYOUTS =================== */
  function loadPayouts() {
    var my = ++pay.seq;
    state('ob-pay-state', 'loading');
    O.api('/organizer/payouts?per_page=50').then(function (r) {
      if (my !== pay.seq) return;
      var d = r && r.data;
      pay.rows = Array.isArray(d) ? d : d && Array.isArray(d.data) ? d.data : d && Array.isArray(d.payouts) ? d.payouts : [];
      drawPayouts();
    }, function (err) {
      if (my !== pay.seq || (err && err.status === 401)) return;
      pay.rows = null;
      state('ob-pay-state', 'error', loadPayouts);
    });
  }
  function drawPayouts() {
    if (!pay.rows) return;
    var body = $('ob-pay-rows'), list = pay.rows.filter(function (p) {
      return pay.filter === 'completed' ? p.status === 'completed' : pay.filter === 'pending' ? ['pending', 'approved', 'processing'].indexOf(p.status) > -1 : true;
    });
    body.textContent = '';
    list.forEach(function (p) {
      var ref = txt(p.reference) || '#' + p.id;
      body.appendChild(el('tr', null, [
        el('td', null, el('span', { class: 'ob-num', text: ref })),
        el('td', { class: 'ob-muted', text: day(p.created_at) }),
        el('td', { text: txt(p.event_title) || txt(p.event && (p.event.title || p.event.name)) || '—' }),
        el('td', { class: 'ob-amount', text: money(p.amount) }),
        el('td', null, tag(PAY, p.status)),
        el('td', null, el('div', { class: 'ob-acts' }, pill('file-text', 'Vezi', 'Vezi decontul ' + ref, function (b) { openPayout(p, b); }))),
      ]));
    });
    $('ob-pay-info').textContent = pay.rows.length ? F.count(list.length, 'decont', 'deconturi') + (pay.filter === 'all' ? '' : ' cu acest status') + '.' : '';
    state('ob-pay-state', list.length ? null : ['Niciun decont', pay.rows.length ? 'Niciun decont cu acest status.' : 'Nu ai niciun decont încă.']);
  }

  /* =================== BILLING DETAILS =================== */
  O.ready.then(function (ok) {
    if (!ok) return;
    O.api('/organizer/billing-info', { quiet: true }).then(function (r) {
      var b = (r && r.data) || {};
      [['company', b.company_name], ['cui', b.cui], ['reg', b.reg_number], ['address', b.address], ['email', b.email]].forEach(function (x) { $('ob-b-' + x[0]).textContent = txt(x[1]) || '—'; });
    }, function () {});
    setSection(location.hash === '#deconturi' ? 'payouts' : 'invoices');
    loadInvoices();
  });

  /* =================== DETAIL WINDOW =================== */
  function openDialog(from, title) {
    var d = $('ob-d');
    opener = from;
    $('ob-d-h').textContent = title;
    $('ob-d-p').textContent = '—';
    $('ob-print').hidden = true;
    $('ob-d-body').textContent = '';
    $('ob-d-body').appendChild(el('p', { class: 'ob-state', text: 'Se încarcă…' }));
    if (!d.open) d.showModal();
    d.querySelector('.ob-x').focus();
    return ++dSeq;
  }
  function failDialog(my, err) {
    if (my !== dSeq || (err && err.status === 401)) return;
    $('ob-d-body').textContent = '';
    $('ob-d-body').appendChild(el('p', { class: 'ob-note is-bad', text: err && err.status === 404 ? 'Nu mai găsim acest document.' : 'Nu am putut încărca detaliile. Încearcă din nou.' }));
  }
  function line(label, value) { return value ? el('p', { text: label ? label + ': ' + value : value }) : null; }
  function openInvoice(id, from, print) {
    var my = openDialog(from, 'Detalii factură');
    O.api('/organizer/invoices/' + encodeURIComponent(id)).then(function (r) {
      if (my !== dSeq) return;
      var x = (r && r.data) || {}, iss = x.issuer || {}, cli = x.client || {}, body = $('ob-d-body');
      $('ob-d-p').textContent = txt(x.number) || '#' + id;
      body.textContent = '';
      body.appendChild(el('div', { class: 'ob-parties' }, [
        el('div', { class: 'ob-party' }, [el('h3', { text: 'Emitent' }), el('b', { text: txt(iss.name) || 'bilete.online' }), line('CUI', txt(iss.cui)), line('Reg. Com.', txt(iss.reg_com)), line('', txt(iss.address)), iss.iban ? line(txt(iss.bank_name) || 'IBAN', txt(iss.iban)) : null, line('', txt(iss.email))]),
        el('div', { class: 'ob-party' }, [el('h3', { text: 'Client' }), el('b', { text: txt(cli.name) || '—' }), line('', txt(cli.address)), line('CUI', txt(cli.cui))]),
      ]));
      var items = Array.isArray(x.items) ? x.items : [];
      var foot = [el('tr', null, [el('td', { colspan: 3, text: 'Subtotal' }), el('td', { text: money(x.subtotal) })])];
      if (F.toNum(x.vat)) foot.push(el('tr', null, [el('td', { colspan: 3, text: 'TVA (' + F.num(F.toNum(x.vat_rate)) + '%)' }), el('td', { text: money(x.vat) })]));
      foot.push(el('tr', { class: 'is-total' }, [el('td', { colspan: 3, text: 'Total' }), el('td', { text: money(x.total) })]));
      body.appendChild(el('div', { class: 'ob-table-wrap' }, el('table', { class: 'ob-lines' }, [
        el('thead', null, el('tr', null, ['Descriere', 'Cant.', 'Preț', 'Total'].map(function (h) { return el('th', { scope: 'col', text: h }); }))),
        el('tbody', null, items.length ? items.map(function (it) { return el('tr', null, [el('td', { text: txt(it.description) || '—' }), el('td', { text: F.num(F.toNum(it.quantity)) }), el('td', { text: money(it.price) }), el('td', { text: money(it.total) })]); }) : el('tr', null, el('td', { colspan: 4, text: 'Factura nu are linii detaliate.' }))),
        el('tfoot', null, foot),
      ])));
      body.appendChild(el('div', { class: 'ob-d-meta' }, [el('span', null, ['Status: ', tag(INV, x.status)]), el('span', { text: 'Emisă: ' + day(x.date) + ' · Scadentă: ' + day(x.due_date) })]));
      $('ob-print').hidden = false;
      if (print) setTimeout(function () { if (my === dSeq) window.print(); }, 60);
    }, function (err) { failDialog(my, err); });
  }
  function openPayout(p, from) {
    var my = openDialog(from, 'Detalii decont');
    O.api('/organizer/payouts/' + encodeURIComponent(p.id)).then(function (r) {
      if (my !== dSeq) return;
      var x = (r && r.data && (r.data.payout || r.data)) || p, ref = txt(x.reference) || '#' + x.id, body = $('ob-d-body');
      $('ob-d-p').textContent = 'Decont ' + ref;
      body.textContent = '';
      var rows = [['Referință', ref], ['Dată', day(x.created_at)], ['Activitate', txt(x.event_title) || '—'], ['Valoare', money(x.amount)], ['Status', (PAY[x.status] || [txt(x.status)])[0]], ['Cont bancar', txt(x.account) || txt(x.payout_method && x.payout_method.iban) || '—']];
      if (x.period_start) rows.push(['Perioadă', day(x.period_start) + ' — ' + day(x.period_end)]);
      body.appendChild(el('dl', { class: 'ob-dl' }, rows.map(function (rw) { return el('div', null, [el('dt', { text: rw[0] }), el('dd', { text: rw[1] })]); })));
      if (txt(x.rejection_reason)) body.appendChild(el('p', { class: 'ob-note is-bad', text: 'Motiv respingere: ' + txt(x.rejection_reason) }));
      if (txt(x.notes)) body.appendChild(el('p', { class: 'ob-note', text: 'Note: ' + txt(x.notes) }));
    }, function (err) { failDialog(my, err); });
  }
  $('ob-print').addEventListener('click', function () { window.print(); });
  $('ob-d').addEventListener('click', function (e) { if (e.target.closest('[data-close]')) $('ob-d').close(); });
  $('ob-d').addEventListener('close', function () { dSeq++; if (opener && document.contains(opener)) opener.focus(); opener = null; });

  /* =================== EXPORT =================== */
  $('ob-export').addEventListener('click', function () {
    var btn = this, token = typeof BileteOnlineAuth !== 'undefined' && BileteOnlineAuth.getToken ? BileteOnlineAuth.getToken() : null;
    if (btn.getAttribute('aria-busy') === 'true') return;
    if (!token) { O.flash('Sesiunea a expirat. Autentifică-te din nou.', true); return; }
    var base = (window.BILETEONLINE && window.BILETEONLINE.apiUrl) || '/api/proxy.php', name = 'facturi-' + F.ymd() + '.csv';
    busyBtn(btn, true, 'Se exportă…');
    fetch(base + '?action=organizer.invoices.export&status=' + encodeURIComponent(inv.filter), { headers: { Authorization: 'Bearer ' + token, Accept: 'text/csv' } }).then(function (res) {
      if (!res.ok) { var e = new Error('export'); e.status = res.status; throw e; }
      var m = /filename="?([^";]+)"?/i.exec(res.headers.get('content-disposition') || '');
      if (m) name = m[1];
      return res.blob();
    }).then(function (blob) {
      return blob.slice(0, 5).arrayBuffer().then(function (buf) {
        var head = String.fromCharCode.apply(null, new Uint8Array(buf));
        // an expired session gets the sign-in page (status 200): never save it as the CSV
        if (!blob.size || head.charAt(0) === '<' || head.charAt(3) === '<') { var e = new Error('html'); e.html = true; throw e; }
        var href = URL.createObjectURL(blob), a = el('a', { href: href, download: name, hidden: true });
        document.body.appendChild(a);
        a.click();
        setTimeout(function () { URL.revokeObjectURL(href); a.remove(); }, 1500);
        O.flash('Lista de facturi a fost exportată.');
      });
    }).catch(function (err) {
      if (err && (err.status === 401 || err.html)) { O.flash('Sesiunea a expirat. Te trimitem la autentificare.', true); setTimeout(function () { O.api('/organizer/me').catch(function () {}); }, 1500); return; }
      O.flash('Nu am putut exporta facturile. Încearcă din nou.', true);
    }).then(function () { busyBtn(btn, false); });
  });
})();
