/* bilete.online v2: organizer service orders (/organizator/servicii/comenzi). Every page of /organizer/services/orders,
   the figures from /organizer/services/stats, search (number or activity, without diacritics), status and type filters,
   20 per page; each order opens its detail. Runs inside the organizer shell (window.BO_ORG); text from the API is always
   written as text. */
(function () {
  'use strict';
  var O = window.BO_ORG, root = document.getElementById('sq');
  if (!O || !root) return;
  var F = O.fmt, el = O.el;
  var $ = function (id) { return document.getElementById(id); };
  var PER = 20;
  var TYPES = { featuring: 'Promovare', email: 'Email marketing', tracking: 'Ad tracking', campaign: 'Creare campanie' };
  var STATUS = { draft: ['Draft', 'is-muted'], pending_payment: ['Așteaptă plata', 'is-wait'], processing: ['În procesare', 'is-wait'], active: ['Activ', 'is-ok'], completed: ['Finalizat', 'is-muted'], cancelled: ['Anulat', 'is-bad'], refunded: ['Rambursat', 'is-bad'] };
  var all = null, page = 1;

  function txt(v) { return F.flat(v).trim(); }
  function norm(s) { return String(s == null ? '' : s).normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase(); }
  function day(v) { var d = F.dateOf(v); return d ? F.date(d, { day: 'numeric', month: 'short', year: 'numeric' }) : ''; }
  function stamp(v) { var d = F.dateOf(v); return d ? F.date(d, { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' }) : '—'; }
  function money(v, cur) { var m = F.money(F.toNum(v)); return cur && !/lei|ron/i.test(cur) ? F.num(F.toNum(v)) + ' ' + cur : m; }

  function load() {
    var got = [];
    function next(n) {
      return O.api('/organizer/services/orders?per_page=100&page=' + n).then(function (r) {
        var d = r && r.data, rows = Array.isArray(d) ? d : d && Array.isArray(d.data) ? d.data : [];
        got = got.concat(rows);
        var last = F.toNum(O.metaOf(r).last_page) || F.toNum(d && d.last_page);
        if (last > n && n < 10) return next(n + 1);
      });
    }
    next(1).then(function () {
      all = got.filter(function (o) { return o && o.id != null; });
      $('sq-s-total').textContent = F.num(all.length);
      $('sq-s-pending').textContent = F.num(all.filter(function (o) { return o.status === 'pending_payment' || o.status === 'processing'; }).length);
      draw();
    }, function (err) {
      if (err && err.status === 401) return;
      ['total', 'pending'].forEach(function (k) { $('sq-s-' + k).textContent = '—'; });
      var body = $('sq-rows'), retry = el('button', { class: 'sq-pill', type: 'button', text: 'Reîncearcă' });
      retry.addEventListener('click', function () { body.textContent = ''; body.appendChild(el('tr', null, el('td', { colspan: 7, class: 'sq-state', text: 'Se încarcă…' }))); load(); });
      body.textContent = '';
      body.appendChild(el('tr', null, el('td', { colspan: 7, class: 'sq-state' }, ['Nu am putut încărca comenzile. ', retry])));
      $('sq-info').textContent = '';
    });
  }
  function shown() {
    var q = norm($('sq-q').value.trim()), st = $('sq-status').value, ty = $('sq-type').value;
    return all.filter(function (o) {
      return (!q || norm(txt(o.order_number) + ' ' + txt(o.event_name)).indexOf(q) > -1) && (!st || o.status === st) && (!ty || o.type === ty);
    });
  }
  function draw() {
    if (!all) return;
    var list = shown(), pages = Math.max(1, Math.ceil(list.length / PER)), body = $('sq-rows');
    if (page > pages) page = pages;
    var from = (page - 1) * PER, rows = list.slice(from, from + PER);
    body.textContent = '';
    if (!rows.length) body.appendChild(el('tr', null, el('td', { colspan: 7, class: 'sq-state', text: all.length ? 'Nicio comandă nu se potrivește filtrelor.' : 'Nu există comenzi' })));
    rows.forEach(function (o) {
      var id = txt(o.id), st = STATUS[o.status] || [txt(o.status_label) || txt(o.status) || '—', 'is-muted'], paid = o.payment_status === 'paid';
      var start = day(o.service_start_date), end = day(o.service_end_date), href = /^[\w-]+$/.test(id) ? '/organizator/services/' + id : null;
      body.appendChild(el('tr', null, [
        el('td', null, el('div', { class: 'sq-num' }, [href ? el('a', { href: href, text: txt(o.order_number) || 'Comandă' }) : el('b', { text: txt(o.order_number) || 'Comandă' }), el('small', { class: paid ? 'is-paid' : '', text: paid ? 'Plătit' : 'Neplătit' })])),
        el('td', null, el('span', { class: 'org-tag', text: TYPES[o.type] || txt(o.type_label) || txt(o.type) || '—' })),
        el('td', { text: txt(o.event_name) || '—' }),
        el('td', { class: 'sq-muted', text: start && end ? start + ' - ' + end : '—' }),
        el('td', { class: 'sq-right sq-amount', text: money(o.total, txt(o.currency)) }),
        el('td', null, el('span', { class: 'org-tag ' + st[1], text: st[0] })),
        el('td', { class: 'sq-right sq-muted', text: stamp(o.created_at) }),
      ]));
    });
    $('sq-info').textContent = list.length ? 'Afișare ' + (from + 1) + '-' + Math.min(from + PER, list.length) + ' din ' + F.count(list.length, 'comandă', 'comenzi') : 'Nu există comenzi';
    $('sq-prev').disabled = page <= 1;
    $('sq-next').disabled = page >= pages;
  }
  var timer = null;
  $('sq-q').addEventListener('input', function () { clearTimeout(timer); timer = setTimeout(function () { page = 1; draw(); }, 200); });
  ['sq-status', 'sq-type'].forEach(function (id) { $(id).addEventListener('change', function () { page = 1; draw(); }); });
  $('sq-prev').addEventListener('click', function () { if (page > 1) { page--; draw(); } });
  $('sq-next').addEventListener('click', function () { page++; draw(); });

  O.ready.then(function (ok) {
    if (!ok) return;
    load();
    O.api('/organizer/services/stats', { quiet: true }).then(function (r) {
      var d = (r && r.data) || {};
      $('sq-s-active').textContent = F.num(F.toNum(d.active_count));
      $('sq-s-spent').textContent = F.money(F.toNum(d.total_spent));
    }, function () { $('sq-s-active').textContent = '—'; $('sq-s-spent').textContent = '—'; });
  });
})();
