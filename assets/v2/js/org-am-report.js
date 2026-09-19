/* bilete.online v2: the operator's sales report (/organizator/raport). A period (presets or dates, up to a year) and a
   location; the totals, the days (bars + table) and the products from /organizer/activities-module/summary (by payment
   date, online and desk sales together); the bookings of the period as CSV from .../bookings/export (downloaded with
   the session token, the proxy needs it). Uses window.BO_AM inside the organizer shell. */
(function () {
  'use strict';
  var O = window.BO_ORG, A = window.BO_AM, root = document.getElementById('am-rep');
  if (!O || !A || !root) return;
  var el = O.el, F = O.fmt;
  var $ = function (id) { return document.getElementById(id); };
  var today = F.ymd(new Date()), seq = 0;

  function addDays(ymd, n) { var d = new Date(ymd + 'T12:00:00'); d.setDate(d.getDate() + n); return F.ymd(d); }
  function monthStart(ymd, back) { var d = new Date(ymd.slice(0, 7) + '-01T12:00:00'); d.setMonth(d.getMonth() - (back || 0)); return F.ymd(d); }
  function monthEnd(ymd) { var d = new Date(ymd.slice(0, 7) + '-01T12:00:00'); d.setMonth(d.getMonth() + 1); d.setDate(0); return F.ymd(d); }
  function qs(o) { return Object.keys(o).filter(function (k) { return o[k] !== '' && o[k] != null; }).map(function (k) { return encodeURIComponent(k) + '=' + encodeURIComponent(o[k]); }).join('&'); }
  function dayLabel(ymd) { return F.date(new Date(ymd + 'T12:00:00'), { weekday: 'short', day: 'numeric', month: 'short' }); }
  function kpi(label, value) { return el('div', { class: 'am-kpi' }, [el('small', { text: label }), el('b', { text: value })]); }

  var PRESETS = {
    today: function () { return [today, today]; },
    7: function () { return [addDays(today, -6), today]; },
    30: function () { return [addDays(today, -29), today]; },
    month: function () { return [monthStart(today), today]; },
    'last-month': function () { var s = monthStart(today, 1); return [s, monthEnd(s)]; },
    year: function () { return [today.slice(0, 4) + '-01-01', today]; },
  };
  function setRange(r, preset) {
    $('rep-from').value = r[0];
    $('rep-to').value = r[1];
    [].forEach.call(document.querySelectorAll('#rep-presets .fchip'), function (b) { b.setAttribute('aria-current', String(b.getAttribute('data-preset') === preset)); });
  }

  function load() {
    var from = $('rep-from').value || today, to = $('rep-to').value || today;
    if (to < from) { var t = from; from = to; to = t; setRange([from, to]); }
    var mine = ++seq, kp = $('rep-kpis');
    kp.textContent = '';
    kp.appendChild(el('p', { class: 've-state', text: 'Se încarcă…' }));
    A.api('/summary?' + qs({ from: from, to: to, location_id: $('rep-loc').value })).then(function (r) {
      if (mine !== seq) return;
      draw((r && r.data) || {});
    }, function (e) {
      if (mine !== seq || (e && e.status === 401)) return;
      kp.textContent = '';
      kp.appendChild(el('p', { class: 've-state', text: A.errText(e, 'Nu am putut încărca raportul.') }));
    });
  }
  function draw(d) {
    var t = d.totals || {}, kp = $('rep-kpis');
    kp.textContent = '';
    [kpi('Rezervări', F.num(t.bookings || 0)), kpi('Persoane', F.num(t.persons || 0)), kpi('Vânzări', F.money(t.value || 0)),
      kpi('Comision bilete.online', F.money(t.commission || 0)), kpi('Îți rămân', F.money(t.net || 0))].forEach(function (n) { kp.appendChild(n); });

    // every day of the period, the empty ones too
    var byDay = {};
    (d.by_day || []).forEach(function (x) { byDay[x.date] = x; });
    var days = [], cur = d.from || $('rep-from').value, end = d.to || $('rep-to').value;
    while (cur <= end && days.length < 400) { days.push(byDay[cur] || { date: cur, bookings: 0, value: 0, net: 0 }); cur = addDays(cur, 1); }
    var max = days.reduce(function (m, x) { return Math.max(m, x.value || 0); }, 0);
    var bars = $('rep-bars');
    bars.textContent = '';
    bars.hidden = days.length < 2;
    days.forEach(function (x) {
      bars.appendChild(el('span', { class: 'rep-bar' + (x.value ? '' : ' is-zero'), style: 'height:' + (max ? Math.max(2, Math.round((x.value || 0) / max * 100)) : 2) + '%', title: dayLabel(x.date) + ': ' + F.money(x.value || 0) }));
    });
    $('rep-days-p').textContent = F.date(new Date(d.from + 'T12:00:00'), { day: 'numeric', month: 'long', year: 'numeric' }) + ' – ' + F.date(new Date(d.to + 'T12:00:00'), { day: 'numeric', month: 'long', year: 'numeric' });
    var tb = $('rep-days');
    tb.textContent = '';
    var withSales = days.filter(function (x) { return x.bookings; }).reverse();
    if (!withSales.length) tb.appendChild(el('tr', null, [el('td', { colspan: 4, class: 've-state', text: 'Nicio vânzare în perioada aleasă.' })]));
    withSales.forEach(function (x) {
      tb.appendChild(el('tr', null, [el('td', { text: dayLabel(x.date) }), el('td', { text: F.num(x.bookings) }), el('td', { text: F.money(x.value) }), el('td', { text: F.money(x.net) })]));
    });

    var pb = $('rep-products');
    pb.textContent = '';
    if (!(d.by_product || []).length) pb.appendChild(el('tr', null, [el('td', { colspan: 5, class: 've-state', text: 'Nimic vândut în perioada aleasă.' })]));
    (d.by_product || []).forEach(function (x) {
      pb.appendChild(el('tr', null, [el('td', { text: x.title }), el('td', { text: F.num(x.bookings) }), el('td', { text: F.num(x.persons) }), el('td', { text: F.money(x.value) }), el('td', { text: F.money(x.net) })]));
    });
  }

  function exportCsv() {
    var token = typeof BileteOnlineAuth !== 'undefined' ? BileteOnlineAuth.getToken() : null;
    var from = $('rep-from').value, to = $('rep-to').value, b = $('rep-export');
    b.disabled = true;
    fetch('/api/proxy.php?action=organizer.am.bookings.export&' + qs({ from: from, to: to, location_id: $('rep-loc').value }), { headers: token ? { Authorization: 'Bearer ' + token } : {} })
      .then(function (r) { if (!r.ok) throw new Error('HTTP ' + r.status); return r.blob(); })
      .then(function (blob) {
        var a = el('a', { href: URL.createObjectURL(blob), download: 'rezervari-' + from + '-' + to + '.csv' });
        document.body.appendChild(a);
        a.click();
        setTimeout(function () { URL.revokeObjectURL(a.href); a.remove(); }, 1000);
      }, function () { O.flash('Nu am putut descărca exportul.', true); })
      .then(function () { b.disabled = false; });
  }

  $('rep-presets').addEventListener('click', function (e) {
    var b = e.target.closest('[data-preset]');
    if (!b) return;
    setRange(PRESETS[b.getAttribute('data-preset')](), b.getAttribute('data-preset'));
    load();
  });
  $('rep-form').addEventListener('submit', function (e) { e.preventDefault(); setRange([$('rep-from').value, $('rep-to').value], null); load(); });
  $('rep-loc').addEventListener('change', load);
  $('rep-export').addEventListener('click', exportCsv);

  O.ready.then(function (ok) {
    if (!ok) return;
    setRange(PRESETS[30](), '30');
    A.api('/locations').then(function (r) {
      ((r && r.data && r.data.locations) || []).forEach(function (l) { $('rep-loc').appendChild(el('option', { value: String(l.id), text: l.name || ('Locația ' + l.id) })); });
    }, function () {});
    load();
  });
})();
