/* bilete.online v2: organizer balance (/organizator/sold). The account figures and every activity's balance come
   from /organizer/finance (core computes them live from the sales breakdown); the minimum payout from
   /organizer/balance; payout requests go to /organizer/payouts. Address: ?event= (opens and shows one activity),
   ?stare=active|incheiate, ?q=. Runs inside the organizer shell (window.BO_ORG); text from the API is always text. */
(function () {
  'use strict';
  var O = window.BO_ORG, root = document.getElementById('of');
  if (!O || !root) return;
  var F = O.fmt, el = O.el, icon = O.icon;
  var $ = function (id) { return document.getElementById(id); };
  function qsa(sel, ctx) { return [].slice.call((ctx || document).querySelectorAll(sel)); }
  var RECENT_DAYS = 90; // own requests (pending / rejected / cancelled) older than this are not shown under an activity
  var STATUS = {
    pending: ['În verificare', 'is-wait'], approved: ['Aprobată', 'is-info'], processing: ['În procesare', 'is-info'],
    completed: ['Plătită', 'is-ok'], rejected: ['Respinsă', 'is-bad'], cancelled: ['Anulată', 'is-muted'],
  };

  var S = { stare: '', q: '', event: '' };
  var data = null, events = [], minPayout = 100, accounts = null, accountsReq = null, open = {}, tabs = {}, bd = '', seq = 0, searchTimer = 0, payTarget = null, cancelTarget = null, opener = null;

  /* =================== HELPERS =================== */
  function norm(s) { return String(s == null ? '' : s).normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase(); }
  function num(v) { return F.toNum(v); }
  function money(v) { return F.money(v); }
  function naiveDay(v) { var m = /^(\d{4}-\d{2}-\d{2})/.exec(String(v || '')); return m ? m[1] : ''; }
  function day(v) { var d = F.dateOf(naiveDay(v)); return d ? F.date(d, { day: 'numeric', month: 'short', year: 'numeric' }) : ''; }
  function stamp(iso) { var d = F.dateOf(iso); return d ? F.date(d, { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' }) : '—'; }
  function hm(t) { var m = /^(\d{1,2}):(\d{2})/.exec(String(t || '')); return m ? (m[1].length < 2 ? '0' : '') + m[1] + ':' + m[2] : ''; }
  function evTitle(e) { return F.flat(e && e.title) || 'Activitatea #' + (e && e.id); }
  function evMeta(e) {
    var d = day(e.starts_at), t = hm(e.start_time);
    return [d ? d + (t ? ', ' + t : '') : '', F.flat(e.venue_name), F.flat(e.venue_city)].filter(Boolean).join(' · ');
  }
  function evById(id) { return events.filter(function (e) { return String(e.id) === String(id); })[0] || null; }
  function isOnTop(e) { return e.commission_mode === 'added_on_top' || e.commission_mode === 'on_top'; }
  function recent(p) { var d = F.dateOf(p.created_at); return !!d && Date.now() - d.getTime() < RECENT_DAYS * 864e5; }
  /** The payouts of an activity: in progress and paid (full lists) plus the organizer's own recent requests. */
  function payoutsOf(id) {
    var seen = {}, list = [];
    function add(p) { if (p && String(p.event_id) === String(id) && !seen[p.id]) { seen[p.id] = true; list.push(p); } }
    (data.payouts_pending || []).forEach(add);
    (data.payouts_completed || []).forEach(add);
    (data.payouts || []).forEach(function (p) { if (/^(pending|rejected|cancelled)$/.test(p.status) && recent(p)) add(p); });
    return list.sort(function (a, b) { return String(b.created_at || '') < String(a.created_at || '') ? -1 : 1; });
  }
  function ownPending(id) { return payoutsOf(id).filter(function (p) { return p.status === 'pending'; })[0] || null; }

  /* =================== ADDRESS =================== */
  function readUrl() {
    var p = new URLSearchParams(location.search);
    S.stare = p.get('stare') === 'active' || p.get('stare') === 'incheiate' ? p.get('stare') : '';
    S.q = (p.get('q') || '').slice(0, 100);
    S.event = /^\d+$/.test(p.get('event') || '') ? p.get('event') : '';
  }
  function writeUrl() {
    var p = new URLSearchParams();
    if (S.event) p.set('event', S.event);
    if (S.stare) p.set('stare', S.stare);
    if (S.q) p.set('q', S.q);
    var url = location.pathname + (p.toString() ? '?' + p.toString() : '') + location.hash;
    if (url !== location.pathname + location.search + location.hash) history.replaceState(null, '', url);
  }

  /* =================== LOAD =================== */
  function load(keepView) {
    var my = ++seq;
    O.api('/organizer/balance', { quiet: true }).then(function (r) {
      var m = r && r.data && r.data.payout_settings && num(r.data.payout_settings.minimum_amount);
      if (m > 0) { minPayout = m; if (my === seq && data) renderList(); }
    }, function () {});
    loadAccounts(false);
    return O.api('/organizer/finance').then(function (r) {
      if (my !== seq) return;
      data = (r && r.data) || {};
      events = Array.isArray(data.events) ? data.events : [];
      renderCards();
      qsa('.of-bd-t', root).forEach(function (b) { b.disabled = false; });
      if (bd) renderBreakdown(bd, false);
      if (!keepView && S.event && evById(S.event)) { open[S.event] = true; tabs[S.event] = 'payments'; }
      renderList();
      if (!keepView && S.event) {
        var card = root.querySelector('.of-ev[data-id="' + S.event + '"]');
        if (card) { card.classList.add('is-target'); card.scrollIntoView({ block: 'start' }); }
      }
    }).catch(function (err) {
      if (my !== seq || (err && err.status === 401)) return;
      ['sales', 'available', 'pending', 'paid'].forEach(function (k) { $('of-c-' + k).textContent = '—'; });
      $('of-list').textContent = '';
      var box = $('of-empty');
      box.textContent = '';
      box.classList.add('is-error');
      box.appendChild(el('span', { class: 'org-empty-ic' }, icon('warning-circle')));
      box.appendChild(el('b', { text: 'Nu am putut încărca soldul' }));
      box.appendChild(el('p', { text: 'Verifică conexiunea și încearcă din nou.' }));
      var retry = el('button', { class: 'btn btn-primary', type: 'button', text: 'Reîncearcă' });
      retry.addEventListener('click', function () { box.hidden = true; box.classList.remove('is-error'); skeleton(); load(false); });
      box.appendChild(el('div', { class: 'of-empty-cta' }, retry));
      box.hidden = false;
    });
  }
  function skeleton() {
    var list = $('of-list');
    list.textContent = '';
    for (var i = 0; i < 3; i++) list.appendChild(el('li', { class: 'of-ev is-skel', 'aria-hidden': 'true' }, el('span', { class: 'org-skel of-sk-card' })));
  }
  function loadAccounts(force) {
    if (accountsReq && !force) return accountsReq;
    accountsReq = O.api('/organizer/bank-accounts', { quiet: true }).then(function (r) {
      var d = r && r.data, list = d && (Array.isArray(d.accounts) ? d.accounts : Array.isArray(d) ? d : []);
      accounts = list || [];
      renderNotice();
      return accounts;
    }, function () { accountsReq = null; accounts = null; throw new Error('accounts'); });
    return accountsReq;
  }
  function renderNotice() {
    var box = $('of-notice');
    if (accounts && !accounts.length) {
      var t = $('of-notice-t');
      t.textContent = '';
      t.appendChild(document.createTextNode('Ca să poți cere plata banilor, adaugă un cont bancar. '));
      t.appendChild(el('a', { href: '/organizator/setari#bank', text: 'Adaugă un cont bancar' }));
      box.hidden = false;
    } else box.hidden = true;
  }

  /* =================== CARDS + BREAKDOWN =================== */
  function renderCards() {
    var avail = num(data.available_balance), pending = num(data.pending_balance), paid = num(data.total_paid_out);
    var sales = data.total_sales != null ? num(data.total_sales) : avail + pending + paid;
    $('of-c-sales').textContent = money(sales);
    $('of-c-pending').textContent = money(pending);
    $('of-c-paid').textContent = money(paid);
    var a = $('of-c-available'), ap = $('of-c-available-p');
    a.classList.toggle('is-neg', avail < -0.005);
    ap.classList.toggle('is-neg', avail < -0.005);
    if (avail < -0.005) {
      a.textContent = '− ' + money(Math.abs(avail));
      ap.textContent = 'De regularizat: s-a decontat mai mult decât valoarea biletelor rămase valide (rambursări ulterioare).';
    } else {
      a.textContent = money(avail);
      ap.textContent = 'Bani din bilete pentru care nu s-a emis încă un decont.';
    }
  }
  var BD = {
    sales: ['Total vânzări, pe activități', 'Venitul tău din fiecare activitate, după reduceri.'],
    available: ['Sold disponibil, pe activități', 'Bani din bilete pentru care nu s-a emis încă un decont.'],
    pending: ['În procesare, pe deconturi', 'Deconturi aprobate, în curs de plată către tine.'],
    paid: ['Total încasat, pe deconturi', 'Deconturi deja plătite către tine.'],
  };
  function renderBreakdown(which, focus) {
    bd = which;
    qsa('.of-bd-t', root).forEach(function (b) { b.setAttribute('aria-expanded', String(b.getAttribute('data-bd') === which)); });
    var panel = $('of-bd');
    panel.hidden = !which;
    if (!which) return;
    $('of-bd-h').textContent = BD[which][0];
    $('of-bd-p').textContent = BD[which][1];
    var list = $('of-bd-list'), total = $('of-bd-total'), rows = [];
    list.textContent = '';
    function line(name, sub, value, neg) {
      rows.push(el('li', null, [el('span', { class: 'of-bd-name' }, [el('b', { text: name, title: name }), sub ? el('span', { text: sub }) : null]), el('span', { class: 'of-bd-v' + (neg ? ' is-neg' : ''), text: value })]));
    }
    var sum = 0;
    if (which === 'sales' || which === 'available') {
      var field = which === 'sales' ? 'net_revenue' : 'available_balance';
      events.filter(function (e) { return num(e[field]) > 0.005; }).sort(function (a, b) { return num(b[field]) - num(a[field]); })
        .forEach(function (e) { sum += num(e[field]); line(evTitle(e), evMeta(e), money(e[field])); });
      if (which === 'available') {
        events.filter(function (e) { return num(e.available_balance_signed) < -0.005; }).forEach(function (e) {
          sum += num(e.available_balance_signed);
          line(evTitle(e), 'de regularizat · ' + evMeta(e), '− ' + money(Math.abs(num(e.available_balance_signed))), true);
        });
      }
    } else {
      var src = which === 'pending' ? data.payouts_pending : data.payouts_completed;
      (Array.isArray(src) ? src : []).forEach(function (p) {
        var e = p.event_id ? evById(p.event_id) : null;
        var name = e ? evTitle(e) : p.event_id ? (F.flat(p.event_title) || 'Activitatea #' + p.event_id) : 'Decont pentru mai multe activități';
        var ref = F.flat(p.decont_series) || F.flat(p.reference) || '#' + p.id;
        sum += num(p.amount);
        line(name, ref + ' · ' + stamp(p.completed_at || p.created_at), money(p.amount));
      });
    }
    if (!rows.length) {
      list.appendChild(el('li', { class: 'of-bd-empty', text: which === 'pending' ? 'Niciun decont în curs de plată.' : which === 'paid' ? 'Niciun decont plătit încă.' : which === 'sales' ? 'Nicio activitate cu vânzări.' : 'Nicio activitate cu sold disponibil.' }));
      total.hidden = true;
    } else {
      rows.forEach(function (r) { list.appendChild(r); });
      total.textContent = '';
      total.appendChild(el('span', { text: 'Total' }));
      total.appendChild(el('span', { text: (sum < -0.005 ? '− ' : '') + money(Math.abs(sum)) }));
      total.hidden = rows.length < 2;
    }
    if (focus) { panel.scrollIntoView({ block: 'nearest' }); $('of-bd-h').focus({ preventScroll: true }); }
  }
  qsa('.of-bd-t', root).forEach(function (b) {
    b.addEventListener('click', function () {
      var k = b.getAttribute('data-bd');
      renderBreakdown(bd === k ? '' : k, bd !== k);
      if (!bd) b.focus();
    });
  });
  $('of-bd-x').addEventListener('click', function () {
    var was = bd;
    renderBreakdown('', false);
    var t = root.querySelector('.of-bd-t[data-bd="' + was + '"]');
    if (t) t.focus();
  });

  /* =================== ACTIVITIES =================== */
  function sorted() {
    var q = norm(S.q.trim());
    return events.filter(function (e) {
      if (S.stare === 'active' && e.is_past) return false;
      if (S.stare === 'incheiate' && !e.is_past) return false;
      return !q || norm([evTitle(e), e.venue_name, e.venue_city].join(' ')).indexOf(q) > -1;
    }).sort(function (a, b) {
      if (!!a.is_past !== !!b.is_past) return a.is_past ? 1 : -1;
      var x = String(a.starts_at || ''), y = String(b.starts_at || '');
      return a.is_past ? (x > y ? -1 : x < y ? 1 : 0) : (x < y ? -1 : x > y ? 1 : 0);
    });
  }
  function renderCounts() {
    var q = norm(S.q.trim()), base = events.filter(function (e) { return !q || norm([evTitle(e), e.venue_name, e.venue_city].join(' ')).indexOf(q) > -1; });
    var n = { '': base.length, active: base.filter(function (e) { return !e.is_past; }).length, incheiate: base.filter(function (e) { return e.is_past; }).length };
    qsa('.of-seg-b', root).forEach(function (b) {
      b.setAttribute('aria-pressed', String(b.getAttribute('data-stare') === S.stare));
      b.querySelector('b').textContent = F.num(n[b.getAttribute('data-stare')]);
    });
  }
  function renderList() {
    writeUrl();
    renderCounts();
    var list = $('of-list'), box = $('of-empty'), shown = sorted();
    list.textContent = '';
    box.hidden = true;
    box.classList.remove('is-error');
    $('of-live').textContent = F.count(shown.length, 'activitate', 'activități') + '.';
    if (!shown.length) {
      box.textContent = '';
      box.appendChild(el('span', { class: 'org-empty-ic' }, icon('wallet')));
      if (!events.length) {
        box.appendChild(el('b', { text: 'Nu ai încă activități' }));
        box.appendChild(el('p', { text: 'Soldul apare aici după ce creezi o activitate și vinzi primele bilete.' }));
        box.appendChild(el('div', { class: 'of-empty-cta' }, el('a', { class: 'btn btn-primary', href: '/organizator/activities?action=create', text: 'Creează o activitate' })));
      } else {
        box.appendChild(el('b', { text: S.q ? 'Nicio activitate nu se potrivește căutării' : S.stare === 'active' ? 'Nicio activitate activă' : 'Nicio activitate încheiată' }));
        var all = el('button', { class: 'btn btn-ghost', type: 'button', text: 'Arată toate activitățile' });
        all.addEventListener('click', function () { S.stare = ''; S.q = ''; $('of-q').value = ''; renderList(); });
        box.appendChild(el('div', { class: 'of-empty-cta' }, all));
      }
      box.hidden = false;
      return;
    }
    shown.forEach(function (e) { list.appendChild(cardFor(e)); });
  }
  function cardFor(e) {
    var id = String(e.id), net = num(e.net_revenue), paid = num(e.total_paid_out), pending = num(e.pending_payout), avail = num(e.available_balance);
    var signed = e.available_balance_signed != null ? num(e.available_balance_signed) : avail;
    var pPaid = net > 0 ? Math.max(0, Math.min(100, paid / net * 100)) : 0, pPend = net > 0 ? Math.max(0, Math.min(100 - pPaid, pending / net * 100)) : 0;
    var own = ownPending(id), isOpen = !!open[id];
    var media = el('span', { class: 'of-media', 'aria-hidden': 'true' });
    if (e.image && /^https?:\/\//.test(String(e.image))) { // core's storage URL for the poster
      var img = el('img', { src: e.image, alt: '', loading: 'lazy' });
      img.addEventListener('error', function () { media.textContent = ''; media.appendChild(icon('calendar-blank')); });
      media.appendChild(img);
    } else media.appendChild(icon('calendar-blank'));
    var paidBar = el('span', { class: 'is-paid' }), pendBar = el('span', { class: 'is-pending' });
    paidBar.style.width = pPaid.toFixed(2) + '%';
    pendBar.style.width = pPend.toFixed(2) + '%';
    var comm = num(e.commission_amount), rate = e.commission_rate != null ? F.pct(e.commission_rate, 2) : '';
    var figs = el('dl', { class: 'of-figs' }, [
      fig('Venituri brute', money(e.gross_revenue), ''),
      isOnTop(e) ? fig('Comision' + (rate ? ' ' + rate : ''), money(comm), '', 'plătit de client, peste preț') : fig('Comision' + (rate ? ' ' + rate : ''), '− ' + money(comm), 'is-minus'),
      fig('Reduceri acordate', num(e.discount_amount) > 0.005 ? '− ' + money(e.discount_amount) : money(0), num(e.discount_amount) > 0.005 ? 'is-minus' : ''),
      fig('Venituri nete', money(net), 'is-net'),
      fig('Retras', money(paid), ''),
      fig('În procesare', money(pending), ''),
    ]);
    var li = el('li', { class: 'of-ev' + (e.is_past ? ' is-past' : ''), 'data-id': id }, [
      el('div', { class: 'of-ev-main' }, [
        media,
        el('div', { class: 'of-ev-t' }, [
          el('h3', { class: 'of-ev-h', text: evTitle(e) }),
          evMeta(e) ? el('p', { class: 'of-ev-meta', text: evMeta(e) }) : null,
          el('p', { class: 'of-ev-tags' }, [
            el('span', { class: 'org-tag ' + (e.is_past ? 'is-muted' : 'is-ok'), text: e.is_past ? 'Încheiată' : 'Activă' }),
            el('span', { text: F.count(e.tickets_sold, 'bilet vândut', 'bilete vândute') }),
          ]),
        ]),
        el('div', { class: 'of-ev-side' }, [el('p', { class: 'of-ev-k', text: 'Sold disponibil' }), el('p', { class: 'of-ev-avail' + (avail > 0.005 ? '' : ' is-zero'), text: money(avail) }), payoutAction(e, avail, pending, own)]),
        el('div', { class: 'of-progress' }, [
          el('div', { class: 'of-bar', role: 'img', 'aria-label': 'Primit ' + F.pct(pPaid, 0) + ', în procesare ' + F.pct(pPend, 0) + ' din venituri' }, [paidBar, pendBar]),
          el('p', null, ['Ai primit ', el('b', { text: money(paid) }), ' din ', el('b', { text: money(net) }), pending > 0.005 ? el('span', { class: 'is-pending-t', text: ' · ' + money(pending) + ' în procesare' }) : null]),
          signed < -0.005 ? el('p', { class: 'of-owed', text: 'De regularizat: ' + money(Math.abs(signed)) + '. S-a decontat mai mult decât valoarea biletelor rămase valide (rambursări ulterioare).' }) : null,
        ]),
        figs,
      ]),
      el('button', { class: 'of-toggle', type: 'button', 'aria-expanded': String(isOpen), 'aria-controls': 'of-det-' + id, 'data-toggle': id }, [isOpen ? 'Ascunde detaliile' : 'Plăți, tranzacții și reduceri', icon('caret-down')]),
      el('div', { class: 'of-details', id: 'of-det-' + id, hidden: !isOpen }, isOpen ? details(e) : null),
    ]);
    return li;
  }
  function fig(label, value, cls, sub) {
    return el('div', { class: 'of-fig' + (cls ? ' ' + cls : '') }, [el('dt', { text: label }), el('dd', null, [value, sub ? el('small', { text: sub }) : null])]);
  }
  function payoutAction(e, avail, pending, own) {
    if (!e.is_past) return el('p', { class: 'of-why', text: 'Plata se poate cere după încheierea activității.' });
    if (own) return el('p', { class: 'of-why is-wait', text: 'Cerere de plată în verificare: ' + money(own.amount) + '.' });
    if (pending > 0.005 && avail < minPayout) return el('p', { class: 'of-why is-wait', text: 'Plată în procesare.' });
    if (avail < minPayout) return el('p', { class: 'of-why', text: avail > 0.005 ? 'Suma minimă pentru plată este ' + money(minPayout) + '.' : 'Fără sold disponibil.' });
    return el('button', { class: 'btn btn-primary', type: 'button', 'data-pay': String(e.id) }, 'Solicită plata');
  }

  /* details: payments, transactions, discounts */
  function details(e) {
    var id = String(e.id), tab = tabs[id] || 'payments';
    var pays = payoutsOf(id), txs = (data.transactions || []).filter(function (t) { return String(t.event_id) === id && t.type !== 'payout' && t.type !== 'payout_reversal'; });
    var discounts = Array.isArray(e.discount_orders) ? e.discount_orders : [];
    var defs = [['payments', 'Plăți', pays.length], ['transactions', 'Tranzacții', txs.length], ['discounts', 'Reduceri', discounts.length]];
    var tablist = el('div', { class: 'of-tabs', role: 'tablist', 'aria-label': 'Detalii pentru ' + evTitle(e) }, defs.map(function (d) {
      return el('button', { class: 'of-tab', type: 'button', role: 'tab', id: 'of-tab-' + id + '-' + d[0], 'aria-selected': String(tab === d[0]), 'aria-controls': 'of-pane-' + id, tabindex: tab === d[0] ? '0' : '-1', 'data-tab': d[0], 'data-ev': id }, [d[1], el('b', { text: F.num(d[2]) })]);
    }));
    var pane = el('div', { class: 'of-pane', id: 'of-pane-' + id, role: 'tabpanel', 'aria-labelledby': 'of-tab-' + id + '-' + tab });
    if (tab === 'payments') pane.appendChild(paymentsPane(pays));
    else if (tab === 'transactions') pane.appendChild(transactionsPane(txs));
    else pane.appendChild(discountsPane(discounts));
    return [tablist, pane];
  }
  function paymentsPane(pays) {
    if (!pays.length) return el('p', { class: 'of-pane-empty', text: 'Aici apar deconturile acestei activități: cererile tale, cele aprobate, aflate în curs de plată și cele deja plătite. Momentan nu există niciunul.' });
    return el('div', { class: 'of-table-wrap' }, el('table', { class: 'of-table' }, [
      el('thead', null, el('tr', null, ['Decont', 'Valoare', 'Status', 'Generat', 'Plătit', ''].map(function (h, i) { return el('th', { scope: 'col', class: i === 1 ? 'is-num' : null }, h ? h : el('span', { class: 'sr', text: 'Acțiuni' })); }))),
      el('tbody', null, pays.map(function (p) {
        var st = STATUS[p.status] || [p.status, 'is-muted'];
        var ref = F.flat(p.decont_series) || F.flat(p.reference) || '#' + p.id;
        return el('tr', null, [
          el('td', { class: 'c-first', 'data-label': 'Decont' }, [el('b', { text: ref }), p.decont_series && p.reference ? el('span', { class: 'of-sub', text: F.flat(p.reference) }) : null]),
          el('td', { class: 'is-num', 'data-label': 'Valoare' }, el('b', { text: money(p.amount) })),
          el('td', { 'data-label': 'Status' }, [el('span', { class: 'org-tag ' + st[1], text: st[0] }), p.status === 'rejected' && p.rejection_reason ? el('span', { class: 'of-reason', text: 'Motiv: ' + F.flat(p.rejection_reason) }) : null]),
          el('td', { 'data-label': 'Generat' }, stamp(p.created_at)),
          el('td', { 'data-label': 'Plătit' }, p.status === 'completed' ? stamp(p.completed_at) : '—'),
          el('td', null, p.status === 'pending' ? el('button', { class: 'of-act', type: 'button', 'data-cancel': String(p.id) }, 'Anulează cererea') : null),
        ]);
      })),
    ]));
  }
  function transactionsPane(txs) {
    var note = el('p', { class: 'of-pane-note', text: 'Aici apar vânzările și rambursările activității, din ultimele 20 de mișcări ale contului tău; cele mai vechi nu apar aici.' });
    if (!txs.length) return el('div', null, [el('p', { class: 'of-pane-empty', text: 'Nicio vânzare sau rambursare printre ultimele mișcări ale contului.' }), note]);
    return el('div', null, [el('ul', { class: 'of-tx' }, txs.map(function (t) {
      var kind = t.type === 'sale' ? '' : t.type === 'refund' ? ' is-refund' : ' is-other', v = num(t.amount);
      return el('li', null, [
        el('span', { class: 'of-tx-ic' + kind, 'aria-hidden': 'true' }, icon(t.type === 'sale' ? 'plus' : t.type === 'refund' ? 'arrow-left' : 'receipt')),
        el('span', { class: 'of-tx-t' }, [el('b', { text: F.flat(t.description) || F.flat(t.type_label) || t.type }), el('span', { class: 'of-sub', text: stamp(t.date || t.created_at) })]),
        el('span', { class: 'of-tx-v' + (v < 0 ? ' is-neg' : ''), text: (v >= 0 ? '+' : '−') + money(Math.abs(v)) }),
      ]);
    })), note]);
  }
  function discountsPane(list) {
    if (!list.length) return el('p', { class: 'of-pane-empty', text: 'Aici apar comenzile în care s-a folosit un cod de reducere. La această activitate nu s-a aplicat nicio reducere.' });
    return el('div', { class: 'of-table-wrap' }, el('table', { class: 'of-table' }, [
      el('thead', null, el('tr', null, ['Comandă', 'Cod reducere', 'Bilete', 'Reducere', 'Data'].map(function (h, i) { return el('th', { scope: 'col', class: i === 2 || i === 3 ? 'is-num' : null, text: h }); }))),
      el('tbody', null, list.map(function (d) {
        return el('tr', null, [
          el('td', { class: 'c-first', 'data-label': 'Comandă' }, el('b', { text: F.flat(d.order_number) || '#' + d.order_id })),
          el('td', { 'data-label': 'Cod reducere' }, d.code ? el('span', { class: 'of-code', text: F.flat(d.code) }) : el('span', { class: 'of-sub', text: 'reducere manuală' })),
          el('td', { class: 'is-num', 'data-label': 'Bilete' }, F.num(d.tickets)),
          el('td', { class: 'is-num', 'data-label': 'Reducere' }, [el('b', { text: '− ' + money(d.allocated_discount) }), d.is_shared ? el('span', { class: 'of-sub', text: 'din ' + money(d.order_discount) + ' pe comandă, restul la alte activități' }) : null]),
          el('td', { 'data-label': 'Data' }, d.date ? stamp(d.date) : '—'),
        ]);
      })),
    ]));
  }
  function rerenderCard(id, focusSel) {
    var e = evById(id), old = root.querySelector('.of-ev[data-id="' + id + '"]');
    if (!e || !old) return;
    var fresh = cardFor(e);
    if (old.classList.contains('is-target')) fresh.classList.add('is-target');
    old.replaceWith(fresh);
    if (focusSel) { var f = fresh.querySelector(focusSel); if (f) f.focus(); }
  }
  $('of-list').addEventListener('click', function (ev) {
    var t = ev.target.closest('[data-toggle]');
    if (t) {
      var id = t.getAttribute('data-toggle');
      open[id] = !open[id];
      S.event = open[id] ? id : (S.event === id ? '' : S.event);
      writeUrl();
      rerenderCard(id, '[data-toggle]');
      return;
    }
    var tab = ev.target.closest('[data-tab]');
    if (tab) { tabs[tab.getAttribute('data-ev')] = tab.getAttribute('data-tab'); rerenderCard(tab.getAttribute('data-ev'), '[data-tab="' + tab.getAttribute('data-tab') + '"]'); return; }
    var pay = ev.target.closest('[data-pay]');
    if (pay) { openPay(evById(pay.getAttribute('data-pay')), pay); return; }
    var cancel = ev.target.closest('[data-cancel]');
    if (cancel) openCancel(cancel.getAttribute('data-cancel'), cancel);
  });
  $('of-list').addEventListener('keydown', function (ev) { // arrow keys move between the tabs of an activity
    var tab = ev.target.closest('[role="tab"]');
    if (!tab || ['ArrowLeft', 'ArrowRight', 'Home', 'End'].indexOf(ev.key) < 0) return;
    ev.preventDefault();
    var all = qsa('[role="tab"]', tab.parentNode), i = all.indexOf(tab);
    var next = ev.key === 'Home' ? all[0] : ev.key === 'End' ? all[all.length - 1] : all[(i + (ev.key === 'ArrowRight' ? 1 : -1) + all.length) % all.length];
    tabs[tab.getAttribute('data-ev')] = next.getAttribute('data-tab');
    rerenderCard(tab.getAttribute('data-ev'), '[data-tab="' + next.getAttribute('data-tab') + '"]');
  });
  qsa('.of-seg-b', root).forEach(function (b) {
    b.addEventListener('click', function () { S.stare = b.getAttribute('data-stare'); renderList(); });
  });
  $('of-q').addEventListener('input', function () {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(function () { S.q = $('of-q').value.trim().slice(0, 100); renderList(); }, 150);
  });

  /* =================== DIALOGS =================== */
  function openDialog(d, from) { opener = from || document.activeElement; if (typeof d.showModal === 'function') d.showModal(); else d.setAttribute('open', ''); }
  function closeDialog(d) { if (typeof d.close === 'function') d.close(); else d.removeAttribute('open'); }
  [$('of-pay-d'), $('of-cancel-d')].forEach(function (d) {
    d.addEventListener('close', function () { if (opener && document.contains(opener)) opener.focus(); opener = null; });
    d.addEventListener('click', function (e) { if (e.target === d || e.target.closest('[data-close]')) closeDialog(d); });
  });
  function fieldError(id, msg) {
    var err = $(id + '-err'), input = $(id);
    err.textContent = msg || '';
    err.hidden = !msg;
    if (msg) input.setAttribute('aria-invalid', 'true'); else input.removeAttribute('aria-invalid');
  }
  function formError(nodes) {
    var box = $('of-pay-err');
    box.textContent = '';
    if (!nodes) { box.hidden = true; return; }
    [].concat(nodes).forEach(function (n) { box.appendChild(typeof n === 'string' ? document.createTextNode(n) : n); });
    box.hidden = false;
  }
  function round2(v) { return Math.round(v * 100) / 100; }
  function parseAmount(v) {
    var s = String(v || '').trim().replace(/\s+/g, '').replace(/lei$/i, '');
    if (/^\d{1,3}(\.\d{3})+(,\d{1,2})?$/.test(s)) s = s.replace(/\./g, ''); // 1.234,50
    s = s.replace(',', '.');
    return /^\d+(\.\d{1,2})?$/.test(s) ? parseFloat(s) : NaN;
  }
  function fillAccounts() {
    var sel = $('of-pay-account');
    sel.textContent = '';
    if (!accounts) { sel.appendChild(el('option', { value: '', text: 'Nu am putut încărca conturile' })); return; }
    if (!accounts.length) { sel.appendChild(el('option', { value: '', text: 'Nu ai conturi bancare adăugate' })); return; }
    sel.appendChild(el('option', { value: '', text: 'Selectează contul' }));
    accounts.forEach(function (a) {
      var iban = String(a.iban || a.account_number || '').replace(/\s+/g, '');
      var label = [F.flat(a.bank || a.bank_name) || 'Cont bancar', iban ? '•••• ' + iban.slice(-4) : '', F.flat(a.holder || a.account_holder)].filter(Boolean).join(' · ') + (a.is_primary ? ' (principal)' : '');
      sel.appendChild(el('option', { value: String(a.id), text: label }));
    });
    var primary = accounts.filter(function (a) { return a.is_primary; })[0] || (accounts.length === 1 ? accounts[0] : null);
    if (primary) sel.value = String(primary.id);
  }
  function openPay(e, from) {
    if (!e) return;
    payTarget = e;
    var avail = round2(num(e.available_balance));
    $('of-pay-name').textContent = evTitle(e);
    $('of-pay-avail').textContent = money(avail);
    $('of-pay-amount').value = '';
    $('of-pay-hint').textContent = 'Minimum ' + money(minPayout) + ', maximum ' + money(avail) + '.';
    $('of-pay-notes').value = '';
    $('of-pay-notes-n').textContent = '0 / 500';
    fieldError('of-pay-amount', '');
    fieldError('of-pay-account', '');
    formError(null);
    fillAccounts();
    $('of-pay-go').disabled = false;
    openDialog($('of-pay-d'), from);
    $('of-pay-amount').focus();
    if (!accounts) loadAccounts(true).then(fillAccounts, fillAccounts);
  }
  $('of-pay-all').addEventListener('click', function () {
    if (!payTarget) return;
    $('of-pay-amount').value = String(round2(num(payTarget.available_balance))).replace('.', ',');
    fieldError('of-pay-amount', '');
    $('of-pay-amount').focus();
  });
  $('of-pay-amount').addEventListener('input', function () { if (!$('of-pay-amount-err').hidden) fieldError('of-pay-amount', ''); });
  $('of-pay-account').addEventListener('change', function () { fieldError('of-pay-account', ''); });
  $('of-pay-notes').addEventListener('input', function () { $('of-pay-notes-n').textContent = this.value.length + ' / 500'; });
  $('of-pay-form').addEventListener('submit', function (ev) {
    ev.preventDefault();
    var go = $('of-pay-go');
    if (!payTarget || go.getAttribute('aria-busy') === 'true') return;
    var avail = round2(num(payTarget.available_balance)), amount = parseAmount($('of-pay-amount').value), account = $('of-pay-account').value, bad = false;
    formError(null);
    if (!(amount > 0)) { fieldError('of-pay-amount', 'Scrie suma, de exemplu 250 sau 250,50.'); bad = true; }
    else if (amount < minPayout) { fieldError('of-pay-amount', 'Suma minimă este ' + money(minPayout) + '.'); bad = true; }
    else if (amount > avail) { fieldError('of-pay-amount', 'Suma depășește soldul disponibil (' + money(avail) + ').'); bad = true; }
    if (!account) {
      fieldError('of-pay-account', accounts && !accounts.length ? 'Adaugă un cont bancar în Setări, apoi revino.' : 'Alege contul în care primești banii.');
      if (!bad) $('of-pay-account').focus();
      bad = true;
    }
    if (bad) { if (!$('of-pay-amount-err').hidden) $('of-pay-amount').focus(); return; }
    var label = go.querySelector('[data-label]'), notes = $('of-pay-notes').value.trim(), e = payTarget;
    go.setAttribute('aria-busy', 'true');
    label.textContent = 'Se trimite…';
    O.api('/organizer/payouts', { method: 'POST', body: { amount: amount, event_id: e.id, bank_account_id: +account, notes: notes || null } }).then(function () {
      opener = null;
      closeDialog($('of-pay-d'));
      O.flash('Cererea de plată de ' + money(amount) + ' pentru „' + evTitle(e) + '” a fost trimisă. Te anunțăm pe email când e aprobată.');
      open[String(e.id)] = true;
      tabs[String(e.id)] = 'payments';
      S.event = String(e.id);
      return load(true).then(function () { var c = root.querySelector('.of-ev[data-id="' + e.id + '"] [data-toggle]'); if (c) c.focus(); });
    }).catch(function (err) {
      if (err && err.status === 401) return;
      var m = String((err && err.message) || ''), errors = (err && err.errors) || (err && err.data && err.data.errors) || null;
      if (err && err.status === 403) {
        formError([m && /[ăâîșț]/i.test(m) ? m + ' ' : 'Trebuie să semnezi contractul înainte de a putea retrage bani. ', el('a', { href: '/organizator/setari#contract', text: 'Semnează contractul' })]);
      } else if (err && err.status === 422 && errors) {
        if (errors.amount) fieldError('of-pay-amount', 'Suma nu este validă.');
        if (errors.bank_account_id) fieldError('of-pay-account', 'Contul bancar nu este valid.');
        if (errors.notes) formError('Notele pot avea cel mult 500 de caractere.');
        if (errors.event_id) formError('Activitatea nu mai există.');
      } else if (err && err.status === 400 && /sold insuficient/i.test(m)) {
        formError('Soldul disponibil nu mai acoperă suma cerută. Reîncarcă pagina și încearcă o sumă mai mică.');
      } else if (err && err.status === 400 && /[ăâîșț]/i.test(m)) { // core's own Romanian answer: a request already waiting, the minimum, the bank account
        formError(/cont bancar/i.test(m) ? [m + ' ', el('a', { href: '/organizator/setari#bank', text: 'Deschide Setări' })] : m);
      } else {
        formError('Nu am putut trimite cererea de plată. Încearcă din nou.');
      }
    }).then(function () {
      go.removeAttribute('aria-busy');
      label.textContent = 'Solicită plata';
    });
  });

  function openCancel(payoutId, from) {
    var p = null;
    (data.payouts || []).forEach(function (x) { if (String(x.id) === String(payoutId)) p = x; });
    if (!p) return;
    cancelTarget = p;
    var e = p.event_id ? evById(p.event_id) : null;
    $('of-cancel-p').textContent = 'Cererea ' + (F.flat(p.reference) || '#' + p.id) + ' de ' + money(p.amount) + (e ? ' pentru „' + evTitle(e) + '”' : '') + ' se anulează, iar suma rămâne în soldul disponibil. Poți trimite o cerere nouă oricând.';
    openDialog($('of-cancel-d'), from);
    $('of-cancel-d').querySelector('[data-close]').focus();
  }
  $('of-cancel-ok').addEventListener('click', function () {
    var b = this, p = cancelTarget;
    if (!p || b.getAttribute('aria-busy') === 'true') return;
    b.setAttribute('aria-busy', 'true');
    O.api('/organizer/payouts/' + encodeURIComponent(p.id), { method: 'DELETE' }).then(function () {
      opener = null;
      closeDialog($('of-cancel-d'));
      O.flash('Cererea de plată a fost anulată.');
    }).catch(function (err) {
      if (err && err.status === 401) return;
      opener = null;
      closeDialog($('of-cancel-d'));
      var m = String((err && err.message) || '');
      O.flash(/cannot be cancelled/i.test(m) ? 'Cererea nu mai poate fi anulată: echipa bilete.online a preluat-o deja.' : /not found/i.test(m) ? 'Cererea nu mai există.' : 'Nu am putut anula cererea. Încearcă din nou.', true);
    }).then(function () {
      b.removeAttribute('aria-busy');
      var id = p.event_id ? String(p.event_id) : '';
      return load(true).then(function () { var c = id && root.querySelector('.of-ev[data-id="' + id + '"] [data-toggle]'); if (c) c.focus(); });
    });
  });

  /* =================== START =================== */
  O.ready.then(function (ok) {
    if (!ok) return;
    readUrl();
    $('of-q').value = S.q;
    load(false);
  });
})();
