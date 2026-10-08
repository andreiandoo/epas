/* viaqui.com v2: the venue's orders (/organizator/locatie/comenzi), ported from Ambilet.
   The period, the search, the source and the status filter on the server, page by page. Opening a row loads that
   order's tickets once. Deleting an order is irreversible, so it asks for a written reason, which the core keeps in
   the deletion history shown at the bottom of the page. Runs inside the organizer shell (window.BO_ORG); text from
   the API is always written as text. */
(function () {
  'use strict';
  var O = window.BO_ORG, root = document.getElementById('ve');
  if (!O || !root) return;
  var F = O.fmt, el = O.el;
  var $ = function (id) { return document.getElementById(id); };
  var qsa = function (sel) { return Array.prototype.slice.call(root.querySelectorAll(sel)); };
  var PER = 30, H_PER = 20;
  var STATUS = {
    paid: [VQ.t('Paid'), 'is-ok'], completed: [VQ.t('Completed'), 'is-ok'], pending: [VQ.t('Pending'), 'is-wait'],
    refunded: [VQ.t('Refunded'), 'is-muted'], cancelled: [VQ.t('Cancelled'), 'is-bad'],
    valid: [VQ.t('Valid'), 'is-ok'], used: [VQ.t('Used'), 'is-muted'],
  };
  var PAY = { cash: [VQ.t('Cash'), 'coins'], card: [VQ.t('Card'), 'credit-card'], invoice: [VQ.t('Link by email'), 'file-text'], online: [VQ.t('Online'), 'globe-simple'] };
  var CAT = { access: VQ.t('Access'), parking: VQ.t('Parking'), rental: VQ.t('Rental'), activity: VQ.t('Activity'), extra: VQ.t('Extra'), package: VQ.t('Package') };
  var eventId = null, range = '30', from = null, to = null, page = 1, lastPage = 1, loading = false, seq = 0, timer = null;
  var hPage = 1, hLast = 1, hLoaded = false, hTimer = null, hSeq = 0;
  var deleting = null, lastFocus = null;

  function txt(v) { return F.flat(v).trim(); }
  function day(v) { var d = F.dateOf(v); return d ? F.date(d, { day: 'numeric', month: 'short', year: 'numeric' }) : '—'; }
  function stamp(v) { var d = F.dateOf(v); return d ? F.date(d, { day: '2-digit', month: '2-digit', year: '2-digit', hour: '2-digit', minute: '2-digit' }) : '—'; }
  function show(id, on) { var e = $(id); if (e) e.hidden = !on; }
  function histOpen() { return $('vo-hist-btn').getAttribute('aria-expanded') === 'true'; }
  function tag(list) { var s = STATUS[list] || [txt(list) || '—', 'is-muted']; return el('span', { class: 'org-tag ' + s[1], text: s[0] }); }
  /** The amount in the currency the order carries; without one, the shell's own money format. */
  function amount(v, cur) {
    var c = txt(cur).toUpperCase();
    if (c && window.BileteOnlineUtils && typeof BileteOnlineUtils.formatCurrency === 'function') return BileteOnlineUtils.formatCurrency(F.toNum(v), c);
    return F.money(F.toNum(v));
  }
  function money(v, cur) {
    return el('span', { class: 've-total', text: amount(v, cur) });
  }
  function state(bodyId, cols, text) {
    var body = $(bodyId);
    body.textContent = '';
    body.appendChild(el('tr', null, el('td', { colspan: cols, class: 've-state', text: text })));
  }

  /* =================== the orders =================== */
  function filters() {
    return { search: $('vo-q').value.trim(), source: $('vo-source').value, status: $('vo-status').value };
  }
  function anyFilter() { var f = filters(); return !!(f.search || f.source || f.status); }
  function setRange(value) {
    range = value;
    qsa('.ve-range').forEach(function (b) { b.setAttribute('aria-pressed', String(b.getAttribute('data-range') === value)); });
    show('vo-custom', value === 'custom');
    if (value === 'custom') return;
    from = F.ymd(new Date(Date.now() - parseInt(value, 10) * 86400000));
    to = F.ymd(new Date());
    load(1);
  }
  function load(toPage) {
    if (loading || !eventId) return;
    loading = true;
    var my = ++seq;
    page = toPage;
    var f = filters(), q = ['per_page=' + PER, 'page=' + page];
    if (from) q.push('from=' + from);
    if (to) q.push('to=' + to);
    Object.keys(f).forEach(function (k) { if (f[k]) q.push(k + '=' + encodeURIComponent(f[k])); });
    state('vo-rows', 11, VQ.t('Loading…'));
    O.api('/organizer/events/' + eventId + '/leisure/orders?' + q.join('&')).then(function (r) {
      if (my !== seq) return;
      var d = (r && r.data) || {}, pg = d.pagination || {}, rows = Array.isArray(d.orders) ? d.orders : [];
      lastPage = F.toNum(pg.last_page) || 1;
      page = F.toNum(pg.current_page) || page;
      $('vo-period').textContent = VQ.t('Paid between {from} and {to}.', { from: day(d.from), to: day(d.to) });
      draw(rows, F.toNum(pg.total));
      $('vo-reset').hidden = !anyFilter();
    }, function (err) {
      if (my !== seq) return;
      if (err && err.status === 401) return;
      state('vo-rows', 11, VQ.t('We could not load the orders.'));
      $('vo-count').textContent = '';
      $('vo-prev').disabled = true;
      $('vo-next').disabled = true;
    }).then(function () { loading = false; });
  }
  function draw(rows, total) {
    var body = $('vo-rows');
    body.textContent = '';
    if (!rows.length) {
      state('vo-rows', 11, anyFilter() ? VQ.t('No orders for the chosen filters.') : VQ.t('No orders in the chosen period.'));
      $('vo-count').textContent = '';
      $('vo-prev').disabled = true;
      $('vo-next').disabled = true;
      return;
    }
    rows.forEach(function (o) {
      body.appendChild(orderRow(o));
      body.appendChild(detailRow(o));
    });
    $('vo-count').textContent = VQ.t('{count} · page {page} of {pages}', { count: VQ.n(total || rows.length, 'order', 'orders'), page: F.num(page), pages: F.num(lastPage) });
    $('vo-prev').disabled = page <= 1;
    $('vo-next').disabled = page >= lastPage;
  }
  function orderRow(o) {
    var id = F.toNum(o.id);
    var caret = el('button', { class: 've-caret', type: 'button', 'aria-label': VQ.t('See the order\'s tickets') });
    caret.appendChild(O.icon('caret-down'));
    var pay = PAY[o.payment_method];
    var payCell = el('td');
    if (pay) {
      payCell.appendChild(O.icon(pay[1]));
      payCell.appendChild(document.createTextNode(' ' + pay[0]));
    } else {
      payCell.appendChild(document.createTextNode(txt(o.payment_method) || '—'));
    }
    var del = el('button', { class: 've-del-btn', type: 'button' });
    del.appendChild(O.icon('trash'));
    del.appendChild(document.createTextNode(VQ.t('Delete')));
    del.addEventListener('click', function (ev) { ev.stopPropagation(); openDelete(o); });

    var tr = el('tr', { class: 've-orow', 'aria-expanded': 'false', 'data-order': String(id) }, [
      el('td', null, caret),
      el('td', { class: 've-mono', text: txt(o.order_number) || '—' }),
      el('td', { text: stamp(o.paid_at) }),
      el('td', null, [el('b', { text: txt(o.customer_name) || '—' }), el('small', { class: 've-sub', text: txt(o.customer_email) })]),
      el('td', null, el('span', { class: 'org-tag ' + (o.source === 'pos' ? 'is-wait' : 'is-info'), text: o.source === 'pos' ? VQ.t('At the register') : VQ.t('On the site') })),
      payCell,
      el('td', { text: txt(o.operator_name) || '—' }),
      el('td', { class: 've-r', text: F.num(F.toNum(o.tickets_count)) }),
      el('td', { class: 've-r' }, money(o.total, o.currency)),
      el('td', null, tag(o.status)),
      el('td', null, del),
    ]);
    tr.addEventListener('click', function () { toggle(id); });
    return tr;
  }
  function detailRow(o) {
    var id = F.toNum(o.id);
    return el('tr', { class: 've-drow', 'data-detail': String(id), hidden: true }, el('td', { colspan: 11 },
      el('div', { class: 've-dbox', id: 'vo-d-' + id }, el('p', { class: 've-state', text: VQ.t('Loading tickets…') }))));
  }
  function toggle(id) {
    var row = root.querySelector('.ve-orow[data-order="' + id + '"]');
    var det = root.querySelector('.ve-drow[data-detail="' + id + '"]');
    if (!row || !det) return;
    var open = row.getAttribute('aria-expanded') === 'true';
    row.setAttribute('aria-expanded', String(!open));
    det.hidden = open;
    if (open) return;
    var box = $('vo-d-' + id);
    if (box.getAttribute('data-loaded')) return;
    box.setAttribute('data-loaded', '1');
    O.api('/organizer/events/' + eventId + '/leisure/orders/' + id).then(function (r) {
      drawTickets(box, (r && r.data) || {});
    }, function () {
      box.removeAttribute('data-loaded');
      box.textContent = '';
      box.appendChild(el('p', { class: 've-state', text: VQ.t('We could not load the order\'s tickets.') }));
    });
  }
  function drawTickets(box, d) {
    var tickets = Array.isArray(d.tickets) ? d.tickets : [];
    box.textContent = '';
    if (!tickets.length) {
      box.appendChild(el('p', { class: 've-state', text: VQ.t('This order has no tickets issued.') }));
      return;
    }
    box.appendChild(el('p', { text: VQ.n(tickets.length, 'ticket issued', 'tickets issued') }));
    var head = el('tr', null, [VQ.t('Code'), VQ.t('Ticket type'), VQ.t('Category'), VQ.t('Price'), VQ.t('Visit date'), VQ.t('Status'), VQ.t('Check-in')].map(function (h, i) {
      return el('th', { scope: 'col', class: i === 3 ? 've-r' : '', text: h });
    }));
    var body = el('tbody');
    tickets.forEach(function (t) {
      var type = el('td', null, el('span', { text: txt(t.ticket_type) || '—' }));
      if (t.from_package) type.appendChild(el('small', { class: 've-sub', text: VQ.t('from a package') }));
      if (t.is_umbrella) type.appendChild(el('small', { class: 've-sub', text: VQ.t('package') }));
      body.appendChild(el('tr', null, [
        el('td', { class: 've-mono', text: txt(t.code) || '—' }),
        type,
        el('td', { text: CAT[t.service_category] || txt(t.service_category) || '—' }),
        el('td', { class: 've-r', text: amount(t.price, d.currency) }),
        el('td', { text: t.visit_date ? day(t.visit_date) : '—' }),
        el('td', null, tag(t.status)),
        el('td', null, t.checked_in_at ? el('span', { class: 've-checkin', text: '✓ ' + stamp(t.checked_in_at) }) : document.createTextNode(VQ.t('Not checked in'))),
      ]));
    });
    box.appendChild(el('table', { class: 've-tt' }, [el('thead', null, head), body]));
    if (txt(d.customer_phone)) {
      box.appendChild(el('p', { class: 've-note-line' }, [document.createTextNode(VQ.t('Customer phone: ')), el('b', { text: txt(d.customer_phone) })]));
    }
  }

  /* =================== deleting an order =================== */
  function openDelete(o) {
    deleting = o;
    lastFocus = document.activeElement;
    $('vo-del-nr').textContent = txt(o.order_number) || ('#' + F.toNum(o.id));
    var sum = $('vo-del-sum');
    sum.textContent = '';
    [[VQ.t('Customer'), txt(o.customer_name) || '—'], [VQ.t('Total'), amount(o.total, o.currency)],
      [VQ.t('Tickets'), F.num(F.toNum(o.tickets_count))], [VQ.t('Source'), o.source === 'pos' ? VQ.t('At the register') : VQ.t('On the site')]].forEach(function (pair) {
      sum.appendChild(el('div', null, [el('dt', { text: pair[0] }), el('dd', { text: pair[1] })]));
    });
    $('vo-del-note').value = '';
    show('vo-modal', true);
    $('vo-del-note').focus();
  }
  function closeDelete() {
    show('vo-modal', false);
    deleting = null;
    if (lastFocus && lastFocus.focus) lastFocus.focus();
  }
  function confirmDelete() {
    if (!deleting) return;
    var note = $('vo-del-note').value.trim();
    if (note.length < 3) {
      O.flash(VQ.t('Write the reason for deleting, at least 3 characters.'), true);
      $('vo-del-note').focus();
      return;
    }
    var btn = $('vo-del-ok'), o = deleting;
    btn.disabled = true;
    O.api('/organizer/events/' + eventId + '/leisure/orders/' + F.toNum(o.id), { method: 'DELETE', body: { note: note } }).then(function (r) {
      var d = (r && r.data) || {};
      closeDelete();
      O.flash(d.cashier_snapshot_regenerated
        ? VQ.t('Order {number} was deleted. The register session report was recalculated.', { number: txt(o.order_number) || '' })
        : VQ.t('Order {number} was deleted.', { number: txt(o.order_number) || '' }));
      hLoaded = false;
      if (histOpen()) loadHistory(1);
      load(page);
    }, function (err) {
      O.flash((err && err.message) || VQ.t('We could not delete the order.'), true);
    }).then(function () { btn.disabled = false; });
  }

  /* =================== the deletion history =================== */
  function loadHistory(toPage) {
    if (!eventId) return;
    var my = ++hSeq;
    hPage = toPage;
    var q = ['per_page=' + H_PER, 'page=' + hPage];
    var s = $('vo-hq').value.trim(), hf = $('vo-hfrom').value, ht = $('vo-hto').value;
    if (s) q.push('search=' + encodeURIComponent(s));
    if (hf) q.push('from=' + hf);
    if (ht) q.push('to=' + ht);
    state('vo-hrows', 7, VQ.t('Loading…'));
    O.api('/organizer/events/' + eventId + '/leisure/orders/deletion-history?' + q.join('&'), { quiet: true }).then(function (r) {
      if (my !== hSeq) return;
      hLoaded = true;
      var d = (r && r.data) || {}, pg = d.pagination || {}, items = Array.isArray(d.items) ? d.items : [];
      hLast = F.toNum(pg.last_page) || 1;
      hPage = F.toNum(pg.current_page) || hPage;
      var body = $('vo-hrows');
      body.textContent = '';
      if (!items.length) {
        state('vo-hrows', 7, s ? VQ.t('No matching deletions.') : VQ.t('No orders deleted in the chosen period.'));
        $('vo-hcount').textContent = '';
        $('vo-hprev').disabled = true;
        $('vo-hnext').disabled = true;
        return;
      }
      items.forEach(function (l) {
        body.appendChild(el('tr', null, [
          el('td', null, [el('b', { class: 've-mono', text: txt(l.order_number) || '—' }),
            el('small', { class: 've-sub', text: (l.order_source === 'pos' ? VQ.t('At the register') : VQ.t('On the site')) + (l.cashier_snapshot_regenerated ? ' · ' + VQ.t('report recalculated') : '') })]),
          el('td', { text: stamp(l.deleted_at) }),
          el('td', null, [el('span', { text: txt(l.deleted_by_name) || '—' }), el('small', { class: 've-sub', text: l.deleted_by_type === 'team_member' ? VQ.t('Team member') : VQ.t('Operator') })]),
          el('td', null, [el('span', { text: txt(l.customer_name) || '—' }), el('small', { class: 've-sub', text: txt(l.customer_email) })]),
          el('td', { class: 've-r', text: F.num(F.toNum(l.tickets_count)) }),
          el('td', { class: 've-r' }, money(l.order_total, l.order_currency)),
          el('td', { class: 've-hist-note', text: txt(l.note) || '—' }),
        ]));
      });
      $('vo-hcount').textContent = VQ.t('{count} · page {page} of {pages}', { count: VQ.n(F.toNum(pg.total) || items.length, 'deletion', 'deletions'), page: F.num(hPage), pages: F.num(hLast) });
      $('vo-hprev').disabled = hPage <= 1;
      $('vo-hnext').disabled = hPage >= hLast;
    }, function () {
      if (my !== hSeq) return;
      state('vo-hrows', 7, VQ.t('We could not load the deletion history.'));
    });
  }

  /* =================== wiring =================== */
  qsa('.ve-range').forEach(function (b) { b.addEventListener('click', function () { setRange(b.getAttribute('data-range')); }); });
  $('vo-apply').addEventListener('click', function () {
    var f = $('vo-from').value, t = $('vo-to').value;
    if (!f || !t) { O.flash(VQ.t('Choose both dates.'), true); return; }
    if (f > t) { O.flash(VQ.t('The start date is after the end date.'), true); return; }
    from = f;
    to = t;
    load(1);
  });
  $('vo-q').addEventListener('input', function () { clearTimeout(timer); timer = setTimeout(function () { load(1); }, 300); });
  $('vo-source').addEventListener('change', function () { load(1); });
  $('vo-status').addEventListener('change', function () { load(1); });
  $('vo-reset').addEventListener('click', function () {
    $('vo-q').value = '';
    $('vo-source').value = '';
    $('vo-status').value = '';
    load(1);
  });
  $('vo-prev').addEventListener('click', function () { if (page > 1) load(page - 1); });
  $('vo-next').addEventListener('click', function () { if (page < lastPage) load(page + 1); });
  $('vo-del-cancel').addEventListener('click', closeDelete);
  $('vo-del-ok').addEventListener('click', confirmDelete);
  $('vo-modal').addEventListener('click', function (ev) { if (ev.target === $('vo-modal')) closeDelete(); });
  document.addEventListener('keydown', function (ev) { if (ev.key === 'Escape' && !$('vo-modal').hidden) closeDelete(); });
  $('vo-hist-btn').addEventListener('click', function () {
    var open = !histOpen();
    this.setAttribute('aria-expanded', String(open));
    this.firstChild.nodeValue = open ? VQ.t('Hide history') : VQ.t('Show history');
    show('vo-hist-body', open);
    if (open && !hLoaded) loadHistory(1);
  });
  $('vo-hq').addEventListener('input', function () { clearTimeout(hTimer); hTimer = setTimeout(function () { loadHistory(1); }, 300); });
  $('vo-hfrom').addEventListener('change', function () { loadHistory(1); });
  $('vo-hto').addEventListener('change', function () { loadHistory(1); });
  $('vo-hprev').addEventListener('click', function () { if (hPage > 1) loadHistory(hPage - 1); });
  $('vo-hnext').addEventListener('click', function () { if (hPage < hLast) loadHistory(hPage + 1); });
  $('ve-retry').addEventListener('click', function () { show('ve-failed', false); start(); });
  $('ve-event').addEventListener('change', function () {
    eventId = F.toNum(this.value);
    hLoaded = false;
    load(1);
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
      venues.forEach(function (e) { sel.appendChild(new Option(txt(e.title || e.name) || VQ.t('Venue #{id}', { id: e.id }), String(e.id))); });
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
