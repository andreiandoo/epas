/* bilete.online v2: the venue's live dashboard (/organizator/locatie/live), ported from Ambilet's leisure dashboard.
   Live figures and the activity stream refresh every 20 seconds together with the register; the comparison every
   minute; the forecast once. The thirty-day chart is drawn here as SVG, like the other v2 charts — no chart library.
   Runs inside the organizer shell (window.BO_ORG); text from the API is always written as text. */
(function () {
  'use strict';
  var O = window.BO_ORG, root = document.getElementById('ve');
  if (!O || !root) return;
  var F = O.fmt, el = O.el;
  var $ = function (id) { return document.getElementById(id); };
  var SVGNS = 'http://www.w3.org/2000/svg';
  var events = [], eventId = null, liveTimer = null, compareTimer = null, chartRows = [], seq = 0;

  function txt(v) { return F.flat(v).trim(); }
  function lei(v) { return F.money(F.toNum(v)); }
  function num(v) { return F.num(Math.round(F.toNum(v))); }
  function hhmm(v) { var d = F.dateOf(v); return d ? F.date(d, { hour: '2-digit', minute: '2-digit' }) : '—'; }
  function ymd(d) { return F.ymd(d); }
  function svg(tag, attrs) {
    var n = document.createElementNS(SVGNS, tag);
    Object.keys(attrs || {}).forEach(function (k) { n.setAttribute(k, attrs[k]); });
    return n;
  }
  function show(id, on) { var e = $(id); if (e) e.hidden = !on; }

  /* =================== live figures + stream =================== */
  function loadLive() {
    var my = seq;
    return O.api('/organizer/events/' + eventId + '/leisure/dashboard/live', { quiet: true }).then(function (r) {
      if (my !== seq) return;
      var d = (r && r.data) || {}, s = d.stats || {};
      $('ve-refresh').textContent = hhmm(d.now || new Date());
      $('ve-k-sold').textContent = num(s.sold_today);
      $('ve-k-scanned').textContent = num(s.scanned_today);
      $('ve-k-revenue').textContent = lei(s.revenue_today);
      $('ve-k-orders').textContent = num(s.orders_today);
      $('ve-k-occupancy').textContent = num(s.occupancy);
      var hint = $('ve-k-sold-hint'), visitors = s.visitors_today == null ? null : F.toNum(s.visitors_today);
      if (hint) {
        var different = visitors != null && visitors !== F.toNum(s.sold_today);
        hint.textContent = different ? num(visitors) + ' bilete scanabile' : '';
        hint.hidden = !different;
      }
      drawStream(Array.isArray(d.stream) ? d.stream : []);
    }, function () {});
  }
  function drawStream(list) {
    var box = $('ve-stream');
    box.textContent = '';
    if (!list.length) {
      box.appendChild(el('li', { class: 've-state', text: 'Nicio activitate în ultima oră.' }));
      return;
    }
    var ICON = { sale: ['coins', ''], scan: ['check-circle', 'is-scan'], staff_scan: ['users-three', 'is-staff'] };
    list.slice(0, 20).forEach(function (a) {
      var ic = ICON[a.type] || ['clock', ''];
      var mark = el('span', { class: 've-st-ic ' + ic[1] });
      mark.appendChild(O.icon(ic[0]));
      box.appendChild(el('li', null, [
        mark,
        el('span', { class: 've-st-t' }, [el('b', { text: txt(a.label) }), el('small', { text: txt(a.detail) })]),
        el('span', { class: 've-st-at', text: hhmm(a.at) }),
      ]));
    });
  }

  /* =================== register =================== */
  function loadCashier() {
    return O.api('/organizer/events/' + eventId + '/leisure/cashier/current', { quiet: true }).then(function (r) {
      var s = (r && r.data && r.data.session) || null, box = $('ve-cash');
      box.classList.toggle('is-open', !!s);
      $('ve-cash-label').textContent = s ? 'Casa este deschisă' : 'Casa este închisă';
      $('ve-cash-since').textContent = s ? 'de la ' + hhmm(s.opened_at) + (txt(s.opened_label) ? ' · ' + txt(s.opened_label) : '') : 'Deschide casa din POS ca să poți vinde.';
      $('ve-cash-cta').textContent = '';
      $('ve-cash-cta').appendChild(O.icon(s ? 'door-open' : 'scan'));
      $('ve-cash-cta').appendChild(document.createTextNode(s ? 'Gestionează casa' : 'Deschide casa'));
      show('ve-cash-nums', !!s);
      if (s) {
        var live = s.live || {};
        $('ve-cash-cash').textContent = lei(live.cash);
        $('ve-cash-card').textContent = lei(live.card);
        $('ve-cash-total').textContent = lei(live.total);
        $('ve-cash-orders').textContent = num(live.orders);
      }
    }, function () {});
  }

  /* =================== people =================== */
  function loadPeople() {
    return O.api('/organizer/events/' + eventId + '/leisure/participants', { quiet: true }).then(function (r) {
      var st = (r && r.data && r.data.stats) || {};
      $('ve-part-total').textContent = num(st.total);
      $('ve-part-checked').textContent = num(st.checked_in);
      $('ve-part-rate').textContent = F.pct(F.toNum(st.rate), 0);
    }, function () {});
  }

  /* =================== weather =================== */
  function loadWeather() {
    return O.api('/organizer/events/' + eventId + '/leisure/weather', { quiet: true }).then(function (r) {
      var d = (r && r.data) || {}, forecast = d.forecast, days = forecast && Array.isArray(forecast.days) ? forecast.days : [];
      if (!days.length) { show('ve-weather', false); return; }
      var venue = d.venue || {};
      $('ve-weather-venue').textContent = [txt(venue.name) || 'Locație', txt(venue.city)].filter(Boolean).join(' · ');
      var box = $('ve-weather-days');
      box.textContent = '';
      var names = ['dum', 'lun', 'mar', 'mie', 'joi', 'vin', 'sâm'];
      days.forEach(function (day, i) {
        var date = F.dateOf(day.date), label = i === 0 ? 'Azi' : i === 1 ? 'Mâine' : (date ? names[date.getDay()] : '');
        var card = el('li', { class: 've-day' }, [
          el('span', { class: 've-day-k', text: label }),
          el('span', { class: 've-day-d', text: date ? F.date(date, { day: '2-digit', month: '2-digit' }) : txt(day.date) }),
          el('span', { class: 've-day-ic', text: txt(day.icon), title: txt(day.label) }),
          el('span', { class: 've-day-t' }, [
            document.createTextNode(day.temp_max == null ? '—' : Math.round(F.toNum(day.temp_max)) + '°'),
            el('span', { text: ' / ' + (day.temp_min == null ? '—' : Math.round(F.toNum(day.temp_min)) + '°') }),
          ]),
        ]);
        if (F.toNum(day.precip_mm) > 0) card.appendChild(el('span', { class: 've-day-x', text: F.num(F.toNum(day.precip_mm)) + ' mm' }));
        if (day.uv_max != null) card.appendChild(el('span', { class: 've-day-uv', text: 'UV ' + num(day.uv_max) }));
        box.appendChild(card);
      });
      show('ve-weather', true);
    }, function () { show('ve-weather', false); });
  }

  /* =================== comparison =================== */
  function loadCompare() {
    return O.api('/organizer/events/' + eventId + '/leisure/dashboard/compare', { quiet: true }).then(function (r) {
      var d = (r && r.data) || {}, snaps = d.snapshots || {}, body = $('ve-compare-body');
      var metrics = [['revenue', 'Încasări', lei], ['orders', 'Comenzi', num], ['tickets_sold', 'Bilete vândute', num], ['checkins', 'Check-in-uri', num]];
      var cols = ['yesterday', 'last_week', 'last_month', 'last_year'];
      if (d.cutoff_time) $('ve-compare-sub').textContent = 'Comparație corectă: fiecare zi e numărată până la ora ' + txt(d.cutoff_time) + ', aceeași oră ca azi.';
      body.textContent = '';
      metrics.forEach(function (m) {
        var today = (snaps.today || {})[m[0]];
        var row = el('tr', null, [
          el('td', { class: 've-metric', text: m[1] }),
          el('td', { class: 've-right' }, el('b', { class: 've-val', text: m[2](today) })),
        ]);
        cols.forEach(function (k) {
          var snap = snaps[k] || {}, val = snap[m[0]], delta = (snap.delta_vs_today || {})[m[0]];
          var cell = el('td', { class: 've-right' });
          if (val == null) { cell.appendChild(el('span', { class: 've-val', text: '—' })); }
          else {
            cell.appendChild(el('span', { class: 've-val', text: m[2](val) }));
            if (delta != null) {
              var n = F.toNum(delta), tone = n > 0 ? 'is-up' : n < 0 ? 'is-down' : 'is-flat';
              cell.appendChild(el('span', { class: 've-delta ' + tone, text: (n > 0 ? '+' : '') + F.num(n) + '%' }));
            }
          }
          row.appendChild(cell);
        });
        body.appendChild(row);
      });
    }, function () {
      var body = $('ve-compare-body');
      body.textContent = '';
      body.appendChild(el('tr', null, el('td', { colspan: 6, class: 've-state', text: 'Nu am putut încărca comparația.' })));
    });
  }

  /* =================== thirty days =================== */
  function loadChart() {
    var to = new Date(), from = new Date();
    from.setDate(from.getDate() - 29);
    return O.api('/organizer/events/' + eventId + '/leisure/sales-timeline?from=' + ymd(from) + '&to=' + ymd(to) + '&group_by=day', { quiet: true }).then(function (r) {
      chartRows = ((r && r.data && r.data.rows) || []).filter(function (x) { return x && x.date; });
      drawChart();
    }, function () { chartRows = []; drawChart(); });
  }
  function drawChart() {
    var box = $('ve-chart');
    box.textContent = '';
    var rows = chartRows;
    var any = rows.some(function (x) { return F.toNum(x.revenue) > 0 || F.toNum(x.tickets) > 0 || F.toNum(x.visitors) > 0; });
    show('ve-chart-empty', !any);
    if (!any) return;
    var W = Math.max(320, box.clientWidth || 640), H = 260, padL = 46, padR = 12, padT = 14, padB = 26;
    var iw = W - padL - padR, ih = H - padT - padB;
    var revs = rows.map(function (x) { return F.toNum(x.revenue); });
    var vis = rows.map(function (x) { return x.visitors != null ? F.toNum(x.visitors) : F.toNum(x.tickets); });
    var maxRev = Math.max.apply(null, revs.concat([1])), maxVis = Math.max.apply(null, vis.concat([1]));
    var n = rows.length, step = iw / Math.max(1, n);
    var node = svg('svg', { viewBox: '0 0 ' + W + ' ' + H, role: 'img', 'aria-label': 'Încasări și vizitatori pe zi, ultimele 30 de zile' });

    [0, 0.5, 1].forEach(function (t) {
      var y = padT + ih * t;
      node.appendChild(svg('line', { class: 've-grid-line', x1: padL, x2: W - padR, y1: y.toFixed(1), y2: y.toFixed(1) }));
      var label = svg('text', { class: 've-axis', x: 4, y: (y + 3).toFixed(1) });
      label.textContent = F.num(Math.round(maxRev * (1 - t)));
      node.appendChild(label);
    });
    rows.forEach(function (row, i) {
      var h = (vis[i] / maxVis) * ih * 0.55, x = padL + i * step + step * 0.18, w = Math.max(2, step * 0.64);
      if (h > 0) node.appendChild(svg('rect', { class: 've-bar', x: x.toFixed(1), y: (padT + ih - h).toFixed(1), width: w.toFixed(1), height: h.toFixed(1), rx: 2 }));
    });
    var pts = rows.map(function (row, i) { return [padL + i * step + step / 2, padT + ih - (revs[i] / maxRev) * ih]; });
    var line = pts.map(function (p, i) { return (i ? 'L' : 'M') + p[0].toFixed(1) + ' ' + p[1].toFixed(1); }).join('');
    if (pts.length > 1) {
      node.appendChild(svg('path', { class: 've-area', d: line + 'L' + pts[pts.length - 1][0].toFixed(1) + ' ' + (padT + ih) + 'L' + pts[0][0].toFixed(1) + ' ' + (padT + ih) + 'Z' }));
      node.appendChild(svg('path', { class: 've-line', d: line }));
    }
    [0, Math.floor(n / 2), n - 1].forEach(function (i) {
      if (i < 0 || i >= n) return;
      var d = F.dateOf(rows[i].date), t = svg('text', { class: 've-axis', x: (padL + i * step + step / 2).toFixed(1), y: H - 6, 'text-anchor': 'middle' });
      t.textContent = d ? F.date(d, { day: 'numeric', month: 'short' }) : txt(rows[i].date);
      node.appendChild(t);
    });
    box.appendChild(node);
  }
  var redraw = null;
  window.addEventListener('resize', function () {
    clearTimeout(redraw);
    redraw = setTimeout(function () { if (chartRows.length) drawChart(); }, 200);
  });

  /* =================== activities =================== */
  function loadEvents() {
    return O.api('/organizer/events?per_page=50&page=1').then(function (r) {
      var rows = Array.isArray(r && r.data) ? r.data : (r && r.data && r.data.items) || [];
      events = rows.filter(function (e) { return e && e.id != null; });
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
  function pick(venue) {
    eventId = F.toNum(venue.id);
    seq++;
    var name = txt(venue.title || venue.name);
    if (name) $('ve-title').textContent = name;
    var slug = txt(venue.slug);
    var link = $('ve-public');
    if (slug) {
      link.href = O.safeHref('/activitate/' + slug, '/');
      link.hidden = false;
    } else link.hidden = true;
    stop();
    Promise.all([loadLive(), loadCashier(), loadPeople(), loadChart(), loadWeather(), loadCompare()]);
    liveTimer = setInterval(function () { loadLive(); loadCashier(); }, 20000);
    compareTimer = setInterval(loadCompare, 60000);
  }
  function stop() {
    if (liveTimer) { clearInterval(liveTimer); liveTimer = null; }
    if (compareTimer) { clearInterval(compareTimer); compareTimer = null; }
  }
  $('ve-event').addEventListener('change', function () {
    var v = events.filter(function (e) { return String(e.id) === this.value; }.bind(this))[0];
    if (v) pick(v);
  });
  $('ve-retry').addEventListener('click', function () { show('ve-failed', false); start(); });
  document.addEventListener('visibilitychange', function () {
    if (document.hidden) stop();
    else if (eventId) { loadLive(); loadCashier(); liveTimer = setInterval(function () { loadLive(); loadCashier(); }, 20000); compareTimer = setInterval(loadCompare, 60000); }
  });

  function start() {
    show('ve-main', false);
    show('ve-none', false);
    loadEvents().then(function (venues) {
      if (!venues.length) { show('ve-none', true); return; }
      var sel = $('ve-event');
      sel.textContent = '';
      venues.forEach(function (e) { sel.appendChild(new Option(txt(e.title || e.name) || 'Locație #' + e.id, String(e.id))); });
      sel.disabled = venues.length < 2;
      show('ve-main', true);
      pick(venues[0]);
    }, function (err) {
      if (err && err.status === 401) return;
      show('ve-failed', true);
    });
  }
  O.ready.then(function (ok) { if (ok) start(); });
})();
