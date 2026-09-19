/* bilete.online v2: organizer activity report (/organizator/report/{id}). Figures, sales over time and ticket types from
   /organizer/events/{id}/analytics?period=all; goals, campaigns and orders (refunded + latest) load on their own, so one
   missing part never blanks the report. Charts are drawn here as SVG. Runs inside the organizer shell (window.BO_ORG);
   text from the API is always written as text. */
(function () {
  'use strict';
  var O = window.BO_ORG, root = document.getElementById('or');
  if (!O || !root) return;
  var F = O.fmt, el = O.el, icon = O.icon, SVGNS = 'http://www.w3.org/2000/svg';
  var $ = function (id) { return document.getElementById(id); };
  var ID = /^\d+$/.test(root.getAttribute('data-event') || '') ? root.getAttribute('data-event') : '';
  var COLORS = ['#1B7F4E', '#F2A900', '#2D6CCD', '#E4572E', '#7A5AC8', '#0E8C8C', '#8E958F', '#C2185B'];
  var SOURCES = { Direct: 'Direct', Organic: 'Alte site-uri și căutări', Facebook: 'Facebook', Google: 'Google', Instagram: 'Instagram', TikTok: 'TikTok', Email: 'Email' };
  var ORDER_STATUS = { refunded: ['Rambursată', 'is-info'], partially_refunded: ['Parțial rambursată', 'is-wait'] };
  var data = null, chart = null, active = -1, parts = null, geo = null, redrawTimer = 0;
  try { geo = new Intl.DisplayNames(['ro'], { type: 'region' }); } catch (e) { geo = null; }

  /* =================== HELPERS =================== */
  function num(v) { return F.toNum(v); }
  function money(v) { return F.money(v); }
  function cap(s) { return s ? s.charAt(0).toUpperCase() + s.slice(1) : s; }
  function naiveDay(v) { var m = /^(\d{4}-\d{2}-\d{2})/.exec(String(v || '')); return m ? m[1] : ''; }
  function naiveTime(v) { var m = /T(\d{2}):(\d{2})/.exec(String(v || '')); return m && m[1] + m[2] !== '0000' ? m[1] + ':' + m[2] : ''; }
  function dayLabel(ymd, opts) { var d = F.dateOf(ymd); return d ? F.date(d, opts || { day: 'numeric', month: 'long', year: 'numeric' }) : ''; }
  function stamp(iso) { var d = F.dateOf(iso); return d ? F.date(d, { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' }) : ''; }
  function svgNode(tag, attrs, txt) {
    var n = document.createElementNS(SVGNS, tag);
    Object.keys(attrs).forEach(function (k) { n.setAttribute(k, attrs[k]); });
    if (txt != null) n.textContent = txt;
    return n;
  }
  function empty(box, text, link) {
    box.textContent = '';
    box.appendChild(el('p', { class: 'or-empty-p' }, [text, link ? ' ' : null, link || null]));
  }
  function slug(s) { return String(s || '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 60) || 'activitate'; }
  var compact = new Intl.NumberFormat('ro-RO', { notation: 'compact', maximumFractionDigits: 1 });
  function niceNum(x) {
    if (!(x > 0)) return 1;
    var e = Math.pow(10, Math.floor(Math.log10(x))), f = x / e;
    return (f <= 1 ? 1 : f <= 2 ? 2 : f <= 2.5 ? 2.5 : f <= 5 ? 5 : 10) * e;
  }

  /* =================== LOAD =================== */
  function start() {
    if (!ID) {
      showNone('Alege o activitate', 'Raportul se deschide pentru o activitate anume, din lista activităților tale.');
      return;
    }
    $('or-all-sales').href = '/organizator/vanzari?event=' + ID;
    O.api('/organizer/events/' + ID + '/analytics?period=all').then(function (r) {
      data = (r && r.data) || {};
      if (!data.event && r && r.event) data.event = r.event;
      renderHead();
      renderStats();
      prepareChart();
      renderTypes();
      renderTraffic();
      renderLocations();
      renderFinance();
      $('or-print').disabled = false;
      $('or-pdf').disabled = false;
      $('or-generated').textContent = 'Raport generat la ' + stamp(new Date().toISOString()) + '.';
    }).catch(function (err) {
      if (err && err.status === 401) return;
      if (err && err.status === 404) { showNone('Activitatea nu a fost găsită', 'Poate a fost ștearsă sau nu este în contul tău.'); return; }
      showNone('Nu am putut încărca raportul', 'Verifică conexiunea și încearcă din nou.', true);
    });
    O.api('/organizer/events/' + ID + '/goals', { quiet: true }).then(renderGoals, function () { empty($('or-goals'), 'Nu am putut încărca obiectivele.'); });
    O.api('/organizer/events/' + ID + '/milestones', { quiet: true }).then(renderCampaigns, function () { empty($('or-camps'), 'Nu am putut încărca campaniile.'); });
    loadRefunds();
    O.api('/organizer/orders?event_id=' + ID + '&status=completed&per_page=5&sort_by=created_at&sort_dir=desc', { quiet: true }).then(renderOrders, function () { empty($('or-orders'), 'Nu am putut încărca ultimele comenzi.'); });
  }
  function showNone(title, text, retry) {
    $('or-body').hidden = true;
    var box = $('or-none');
    box.textContent = '';
    box.classList.toggle('is-error', !!retry);
    box.appendChild(el('span', { class: 'org-empty-ic' }, icon(retry ? 'warning-circle' : 'file-text')));
    box.appendChild(el('b', { text: title }));
    box.appendChild(el('p', { text: text }));
    var cta = retry ? el('button', { class: 'btn btn-primary', type: 'button', text: 'Reîncearcă' }) : el('a', { class: 'btn btn-primary', href: '/organizator/activities', text: 'Vezi activitățile' });
    if (retry) cta.addEventListener('click', function () { location.reload(); });
    box.appendChild(el('div', { class: 'or-empty-cta' }, cta));
    box.hidden = false;
  }

  /* =================== HEAD + FIGURES =================== */
  function renderHead() {
    var e = data.event || {}, title = F.flat(e.title) || 'Activitatea #' + ID;
    $('or-title').textContent = title;
    document.title = 'Raport: ' + title + ' — bilete.online';
    var info = $('or-info'), day = naiveDay(e.starts_at || e.date), time = naiveTime(e.starts_at || e.date), bits = [];
    info.textContent = '';
    if (day) bits.push(dayLabel(day, { weekday: 'short', day: 'numeric', month: 'long', year: 'numeric' }) + (time ? ', ' + time : ''));
    [F.flat(e.venue), F.flat(e.venue_city)].forEach(function (v) { if (v) bits.push(v); });
    if (bits.length) info.appendChild(el('span', { text: bits.join(' · ') }));
    var du = e.days_until != null ? num(e.days_until) : null;
    if (e.is_cancelled) info.appendChild(el('span', { class: 'org-tag is-bad', text: 'Anulată' }));
    else if (du !== null) info.appendChild(el('span', { class: 'org-tag ' + (du < 0 ? 'is-muted' : 'is-ok'), text: du < 0 ? 'Încheiată' : du === 0 ? 'Azi' : du === 1 ? 'Mâine' : 'Peste ' + F.count(du, 'zi', 'zile') }));
    if (e.is_sold_out) info.appendChild(el('span', { class: 'org-tag is-wait', text: 'Sold out' }));
  }
  function setStat(key, value, sub) { $('or-s-' + key).textContent = value; $('or-s-' + key + '-p').textContent = sub || ''; }
  function renderStats() {
    var o = data.overview || {}, sold = num(o.tickets_sold), capacity = num(o.capacity), views = num(o.page_views);
    setStat('revenue', money(o.net_revenue != null ? o.net_revenue : o.total_revenue), 'Ce îți revine din bilete, după comision și reduceri.');
    setStat('tickets', F.num(sold), [capacity > 0 ? 'din ' + F.count(capacity, 'loc', 'locuri') : '', num(o.tickets_today) > 0 ? F.num(o.tickets_today) + ' azi' : ''].filter(Boolean).join(' · ') || 'Online, la ușă și invitații.');
    setStat('views', F.num(views), 'Vizualizări ale paginii activității.');
    if (views > 0) setStat('conversion', F.pct(o.conversion_rate, 1), F.count(sold, 'bilet', 'bilete') + ' la ' + F.count(views, 'vizualizare', 'vizualizări') + '.');
    else setStat('conversion', '—', 'Nu avem vizualizări măsurate.');
  }

  /* =================== SALES CHART =================== */
  function prepareChart() {
    var c = data.chart || {}, dates = Array.isArray(c.raw_dates) ? c.raw_dates.slice() : [];
    var rev = dates.map(function (_, i) { return num((c.revenue || [])[i]); }), tix = dates.map(function (_, i) { return num((c.tickets || [])[i]); });
    var e = data.event || {}, end = naiveDay(e.ends_at || e.starts_at);
    if (end && dates.length) { // after the activity there is nothing left to sell: stop the chart at its last day
      var cut = dates.findIndex(function (d) { return d > end; });
      if (cut > 0) { dates = dates.slice(0, cut); rev = rev.slice(0, cut); tix = tix.slice(0, cut); }
    }
    chart = dates.length ? { dates: dates, rev: rev, tix: tix } : null;
    var p = $('or-chart-p');
    p.textContent = chart ? (dates.length > 1 ? dayLabel(dates[0], { day: 'numeric', month: 'short', year: 'numeric' }) + ' – ' + dayLabel(dates[dates.length - 1], { day: 'numeric', month: 'short', year: 'numeric' }) : dayLabel(dates[0])) : '';
    drawChart();
    renderTotals();
  }
  function renderTotals() {
    var box = $('or-totals');
    box.textContent = '';
    if (!chart) return;
    var revSum = chart.rev.reduce(function (s, v) { return s + v; }, 0), tixSum = chart.tix.reduce(function (s, v) { return s + v; }, 0);
    var saleDays = chart.tix.filter(Boolean).length, best = -1;
    chart.rev.forEach(function (v, i) { if (v > 0 && (best < 0 || v > chart.rev[best])) best = i; });
    [['Venit net în grafic', money(revSum)], ['Bilete în grafic', F.num(tixSum)], ['Zile cu vânzări', F.num(saleDays)], ['Cea mai bună zi', best < 0 ? '—' : dayLabel(chart.dates[best], { day: 'numeric', month: 'short' }) + ' · ' + money(chart.rev[best])]]
      .forEach(function (t) { box.appendChild(el('div', null, [el('dt', { text: t[0] }), el('dd', { text: t[1] })])); });
    $('or-plot').setAttribute('aria-label', 'Grafic vânzări, ' + $('or-chart-p').textContent + ': venit net ' + money(revSum) + ', ' + F.count(tixSum, 'bilet vândut', 'bilete vândute') + '. Folosește săgețile pentru fiecare zi.');
  }
  function drawChart() {
    var plot = $('or-plot'), old = plot.querySelector('svg');
    if (old) old.remove();
    parts = null;
    $('or-plot-msg').hidden = true;
    if (!chart) { $('or-plot-msg').hidden = false; return; }
    var W = Math.max(260, Math.floor(plot.clientWidth)), narrow = W < 560, H = narrow ? 220 : 280, n = chart.dates.length;
    var padL = narrow ? 42 : 58, padR = narrow ? 30 : 40, padT = 14, padB = 34, iw = W - padL - padR, ih = H - padT - padB;
    var rMax = niceNum(Math.max.apply(null, chart.rev.concat([0])) / 4) * 4 || 4, tMax = Math.max(1, Math.ceil(niceNum(Math.max.apply(null, chart.tix.concat([0])) / 4))) * 4;
    var y = function (v) { return padT + ih * (1 - v / rMax); }, yt = function (v) { return padT + ih * (1 - v / tMax); };
    var bw = iw / n, barW = Math.max(1, Math.min(26, bw * 0.62));
    var svg = svgNode('svg', { class: 'or-svg', width: W, height: H, viewBox: '0 0 ' + W + ' ' + H, 'aria-hidden': 'true', focusable: 'false' });
    for (var i = 0; i <= 4; i++) {
      var yy = Math.round(y(rMax * i / 4)) + 0.5;
      svg.appendChild(svgNode('line', { class: 'or-gl' + (i === 0 ? ' is-zero' : ''), x1: padL, x2: W - padR, y1: yy, y2: yy }));
      svg.appendChild(svgNode('text', { class: 'or-ax', x: padL - 8, y: yy + 4, 'text-anchor': 'end' }, compact.format(rMax * i / 4)));
      svg.appendChild(svgNode('text', { class: 'or-ax', x: W - padR + 8, y: yy + 4, 'text-anchor': 'start' }, F.num(tMax * i / 4)));
    }
    var band = svgNode('rect', { class: 'or-band', x: padL, y: padT, width: bw, height: ih, visibility: 'hidden' });
    svg.appendChild(band);
    var bars = chart.rev.map(function (v, k) {
      if (!(v > 0)) return null;
      var b = svgNode('rect', { class: 'or-bar', x: (padL + k * bw + (bw - barW) / 2).toFixed(2), y: y(v).toFixed(2), width: barW.toFixed(2), height: Math.max(1, padT + ih - y(v)).toFixed(2), rx: Math.min(4, barW / 3).toFixed(2) });
      svg.appendChild(b);
      return b;
    });
    var pts = chart.tix.map(function (v, k) { return [padL + (k + 0.5) * bw, yt(v)]; });
    if (chart.tix.some(Boolean) && n > 1) {
      var line = pts.map(function (p, k) { return (k ? 'L' : 'M') + p[0].toFixed(1) + ' ' + p[1].toFixed(1); }).join('');
      svg.appendChild(svgNode('path', { class: 'or-area', d: line + 'L' + pts[n - 1][0].toFixed(1) + ' ' + (padT + ih) + 'L' + pts[0][0].toFixed(1) + ' ' + (padT + ih) + 'Z' }));
      svg.appendChild(svgNode('path', { class: 'or-line', d: line }));
    }
    var slots = Math.min(n, narrow ? 4 : 7), seen = {};
    for (var s = 0; s < slots; s++) {
      var idx = slots === 1 ? 0 : Math.round(s * (n - 1) / (slots - 1));
      if (seen[idx]) continue;
      seen[idx] = true;
      var anchor = slots === 1 ? 'middle' : s === 0 ? 'start' : s === slots - 1 ? 'end' : 'middle';
      var lx = anchor === 'start' ? padL : anchor === 'end' ? W - padR : pts[idx][0];
      svg.appendChild(svgNode('text', { class: 'or-ax', x: lx.toFixed(1), y: H - 6, 'text-anchor': anchor }, dayLabel(chart.dates[idx], { day: 'numeric', month: 'short' })));
    }
    var dot = svgNode('circle', { class: 'or-pt', cx: 0, cy: 0, r: 4.5, visibility: 'hidden' });
    svg.appendChild(dot);
    var hit = svgNode('rect', { class: 'or-hit', x: padL, y: 0, width: iw, height: H });
    svg.appendChild(hit);
    plot.insertBefore(svg, plot.firstChild);
    parts = { band: band, dot: dot, bars: bars, pts: pts, bw: bw, padL: padL, W: W };
    $('or-plot-msg').hidden = chart.rev.some(Boolean) || chart.tix.some(Boolean);
    if (active >= 0 && active < n) show(active, false); else hide();
  }
  function show(i, announce) {
    if (!chart || !parts || i < 0 || i >= chart.dates.length) return;
    active = i;
    parts.band.setAttribute('x', (parts.padL + i * parts.bw).toFixed(2));
    parts.band.setAttribute('visibility', 'visible');
    parts.bars.forEach(function (b, k) { if (b) b.classList.toggle('is-on', k === i); });
    parts.dot.setAttribute('cx', parts.pts[i][0].toFixed(1));
    parts.dot.setAttribute('cy', parts.pts[i][1].toFixed(1));
    parts.dot.setAttribute('visibility', 'visible');
    var title = cap(dayLabel(chart.dates[i], { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' })), tip = $('or-tip');
    tip.textContent = '';
    tip.appendChild(el('b', { text: title }));
    tip.appendChild(el('dl', null, [el('dt', { text: 'Venit net' }), el('dd', { text: money(chart.rev[i]) }), el('dt', { text: 'Bilete' }), el('dd', { text: F.num(chart.tix[i]) })]));
    tip.hidden = false;
    var cx = parts.pts[i][0], tw = tip.offsetWidth, left = cx + 16;
    if (left + tw > parts.W - 2) left = cx - 16 - tw;
    tip.style.left = Math.max(0, Math.min(left, parts.W - tw)) + 'px';
    if (announce) $('or-plot-live').textContent = title + ': venit net ' + money(chart.rev[i]) + ', ' + F.count(chart.tix[i], 'bilet', 'bilete') + '.';
  }
  function hide() {
    active = -1;
    if (parts) {
      parts.band.setAttribute('visibility', 'hidden');
      parts.dot.setAttribute('visibility', 'hidden');
      parts.bars.forEach(function (b) { if (b) b.classList.remove('is-on'); });
    }
    $('or-tip').hidden = true;
  }
  function indexAt(clientX) {
    var svg = $('or-plot').querySelector('svg');
    if (!svg || !parts) return -1;
    var r = svg.getBoundingClientRect(), x = (clientX - r.left) * (parts.W / r.width);
    return Math.max(0, Math.min(chart.dates.length - 1, Math.floor((x - parts.padL) / parts.bw)));
  }
  var plotEl = $('or-plot');
  plotEl.addEventListener('pointermove', function (e) { if (chart && e.target.classList && e.target.classList.contains('or-hit')) show(indexAt(e.clientX), false); });
  plotEl.addEventListener('pointerleave', function () { if (document.activeElement !== plotEl) hide(); });
  plotEl.addEventListener('blur', hide);
  plotEl.addEventListener('keydown', function (e) {
    if (!chart) return;
    var n = chart.dates.length, i = active;
    if (e.key === 'ArrowRight') i = i < 0 ? 0 : Math.min(n - 1, i + 1);
    else if (e.key === 'ArrowLeft') i = i < 0 ? n - 1 : Math.max(0, i - 1);
    else if (e.key === 'Home') i = 0;
    else if (e.key === 'End') i = n - 1;
    else if (e.key === 'Escape') { hide(); return; }
    else return;
    e.preventDefault();
    show(i, true);
  });
  window.addEventListener('resize', function () {
    clearTimeout(redrawTimer);
    redrawTimer = setTimeout(function () { if (chart && $('or-plot').querySelector('svg') && Math.floor($('or-plot').clientWidth) !== (parts && parts.W)) drawChart(); }, 150);
  });

  /* =================== TICKET TYPES =================== */
  function typesSorted() {
    var list = Array.isArray(data.ticket_performance) ? data.ticket_performance.slice() : [];
    return list.sort(function (a, b) { return num(b.sold) - num(a.sold); });
  }
  function renderTypes() {
    var list = typesSorted(), body = $('or-types'), foot = $('or-types-foot');
    body.textContent = '';
    foot.textContent = '';
    renderDonut(list);
    if (!list.length) { body.appendChild(el('tr', null, el('td', { colspan: '5', class: 'or-empty-p', text: 'Activitatea nu are tipuri de bilete.' }))); return; }
    var totalRev = list.reduce(function (s, t) { return s + num(t.revenue); }, 0), totalSold = 0;
    list.forEach(function (t, i) {
      var rev = num(t.revenue), pct = totalRev > 0 ? rev / totalRev * 100 : 0, color = COLORS[i % COLORS.length], capq = num(t.capacity);
      totalSold += num(t.sold);
      var type = el('div', { class: 'or-type' }, [el('i', { 'aria-hidden': 'true' }), el('b', null, [F.flat(t.name) || 'Bilet', t.is_invitation ? el('small', { text: 'titlu gratuit (invitație)' }) : t.is_entry_ticket ? el('small', { text: 'încasat de operator' }) : null])]);
      type.querySelector('i').style.setProperty('--c', color);
      var share = el('div', { class: 'or-share' }, [el('span', { 'aria-hidden': 'true' }, el('i')), el('b', { text: F.pct(pct, 0) })]);
      share.querySelector('i').style.width = pct.toFixed(2) + '%';
      share.querySelector('i').style.setProperty('--c', color);
      body.appendChild(el('tr', null, [
        el('td', null, type),
        el('td', { class: 'is-num', text: money(t.price) }),
        el('td', { class: 'is-num' }, [F.num(t.sold), capq > 0 ? el('span', { class: 'or-sub', text: 'din ' + F.num(capq) }) : null]),
        el('td', { class: 'is-num', text: money(rev) }),
        el('td', { class: 'is-share' }, share),
      ]));
    });
    foot.appendChild(el('tr', null, [el('td', { text: 'Total' }), el('td'), el('td', { class: 'is-num', text: F.num(totalSold) }), el('td', { class: 'is-num', text: money(totalRev) }), el('td', { class: 'is-share' })]));
  }
  function renderDonut(list) {
    var box = $('or-donut'), legend = $('or-donut-legend'), sold = list.filter(function (t) { return num(t.sold) > 0; });
    box.textContent = '';
    legend.textContent = '';
    var total = sold.reduce(function (s, t) { return s + num(t.sold); }, 0);
    if (!total) { box.appendChild(el('p', { class: 'or-empty-p', text: 'Niciun bilet vândut încă.' })); return; }
    var R = 70, C = 2 * Math.PI * R, offset = 0;
    var svg = svgNode('svg', { viewBox: '0 0 200 200', role: 'img', 'aria-label': 'Distribuția biletelor: ' + sold.map(function (t) { return F.flat(t.name) + ' ' + F.num(t.sold); }).join(', ') });
    svg.appendChild(svgNode('circle', { cx: 100, cy: 100, r: R, fill: 'none', stroke: '#EFEFEB', 'stroke-width': 26 }));
    sold.forEach(function (t) {
      var idx = list.indexOf(t), share = num(t.sold) / total, len = C * share;
      svg.appendChild(svgNode('circle', { cx: 100, cy: 100, r: R, fill: 'none', stroke: COLORS[idx % COLORS.length], 'stroke-width': 26, 'stroke-dasharray': Math.max(0, len - (sold.length > 1 ? 1.5 : 0)).toFixed(2) + ' ' + C.toFixed(2), 'stroke-dashoffset': (-offset).toFixed(2), transform: 'rotate(-90 100 100)' }));
      offset += len;
      var li = el('li', null, [el('i', { 'aria-hidden': 'true' }), el('span', { text: F.flat(t.name) || 'Bilet' }), el('b', null, [F.num(t.sold), el('small', { text: F.pct(share * 100, 0) })])]);
      li.querySelector('i').style.setProperty('--c', COLORS[idx % COLORS.length]);
      legend.appendChild(li);
    });
    box.appendChild(svg);
    box.appendChild(el('div', { class: 'or-donut-c', 'aria-hidden': 'true' }, [el('b', { text: F.num(total) }), el('span', { text: total === 1 ? 'bilet' : 'bilete' })]));
  }

  /* =================== GOALS + CAMPAIGNS =================== */
  function renderGoals(r) {
    var box = $('or-goals'), d = r && r.data, goals = d && (Array.isArray(d.goals) ? d.goals : Array.isArray(d) ? d : []);
    box.textContent = '';
    if (!goals || !goals.length) { empty(box, 'Niciun obiectiv setat.', el('a', { href: '/organizator/analytics/' + ID, text: 'Setează obiective' })); return; }
    goals.forEach(function (g) {
      var p = Math.max(0, Math.min(100, num(g.progress_percent))), done = !!g.is_achieved || p >= 100;
      var bar = el('i', { class: p >= 75 || done ? '' : p >= 50 ? 'is-mid' : 'is-low' });
      bar.style.width = p.toFixed(1) + '%';
      var status = done ? ['Obiectiv atins' + (g.achieved_at ? ' pe ' + dayLabel(naiveDay(g.achieved_at)) : '') + '.', 'is-done']
        : g.is_overdue ? ['Termenul a trecut' + (g.deadline ? ' (' + dayLabel(g.deadline) + ')' : '') + '.', 'is-late']
        : [F.pct(p, 1) + ' completat' + (g.deadline ? ' · termen ' + dayLabel(g.deadline) + (num(g.days_remaining) > 0 ? ', ' + F.count(g.days_remaining, 'zi rămasă', 'zile rămase') : '') : '') + '.', ''];
      box.appendChild(el('article', { class: 'or-goal' + (done ? ' is-done' : '') }, [
        el('div', { class: 'or-goal-top' }, [el('b', { text: F.flat(g.name) || F.flat(g.type_label) || 'Obiectiv' }), g.type_label ? el('span', { class: 'org-tag is-muted', text: F.flat(g.type_label) }) : null]),
        el('p', { class: 'or-goal-v' }, [el('strong', { text: F.flat(g.formatted_current) || F.num(g.current_value) }), el('span', { text: ' / ' + (F.flat(g.formatted_target) || F.num(g.target_value)) })]),
        el('div', { class: 'or-meter', role: 'progressbar', 'aria-valuemin': '0', 'aria-valuemax': '100', 'aria-valuenow': String(Math.round(p)), 'aria-label': 'Progres ' + (F.flat(g.name) || 'obiectiv') }, bar),
        el('p', { class: 'or-goal-s ' + status[1], text: status[0] }),
      ]));
    });
  }
  function renderCampaigns(r) {
    var box = $('or-camps'), d = r && r.data, list = d && (Array.isArray(d.milestones) ? d.milestones : Array.isArray(d) ? d : []);
    box.textContent = '';
    if (!list || !list.length) { empty(box, 'Nicio campanie înregistrată.', el('a', { href: '/organizator/analytics/' + ID, text: 'Adaugă o campanie' })); return; }
    list.forEach(function (c) {
      var budget = num(c.budget), revenue = num(c.attributed_revenue), roi = budget > 0 ? Math.round((revenue - budget) / budget * 100) : null;
      var dates = [c.start_date ? dayLabel(c.start_date, { day: 'numeric', month: 'short', year: 'numeric' }) : '', c.end_date ? dayLabel(c.end_date, { day: 'numeric', month: 'short', year: 'numeric' }) : ''].filter(Boolean).join(' – ');
      box.appendChild(el('article', { class: 'or-camp' }, [
        el('div', { class: 'or-camp-top' }, [
          el('div', null, [el('b', { text: F.flat(c.title) || 'Campanie' }), el('span', { class: 'or-sub', text: [F.flat(c.type_label), dates].filter(Boolean).join(' · ') })]),
          roi !== null ? el('span', { class: 'org-tag ' + (roi >= 0 ? 'is-ok' : 'is-bad'), text: (roi >= 0 ? '+' : '') + F.num(roi) + '% ROI' }) : null,
        ]),
        el('dl', { class: 'or-camp-figs' }, [
          el('div', null, [el('dt', { text: 'Buget' }), el('dd', { text: money(budget) })]),
          el('div', null, [el('dt', { text: 'Venituri' }), el('dd', { class: 'is-net', text: money(revenue) })]),
          el('div', null, [el('dt', { text: 'Conversii' }), el('dd', { text: F.num(c.conversions) })]),
        ]),
      ]));
    });
  }

  /* =================== TRAFFIC + LOCATIONS =================== */
  function renderTraffic() {
    var box = $('or-traffic'), list = (Array.isArray(data.traffic_sources) ? data.traffic_sources : []).filter(function (s) { return num(s.visitors) > 0; });
    box.textContent = '';
    if (!list.length) { box.appendChild(el('li', { class: 'or-empty-p', text: 'Nu există date despre trafic.' })); return; }
    list.sort(function (a, b) { return num(b.visitors) - num(a.visitors); });
    var total = list.reduce(function (s, x) { return s + num(x.visitors); }, 0);
    list.forEach(function (s) {
      var pct = total ? num(s.visitors) / total * 100 : 0, bar = el('i');
      bar.style.width = pct.toFixed(2) + '%';
      box.appendChild(el('li', null, [
        el('b', { text: SOURCES[s.source] || F.flat(s.source) || 'Direct' }),
        el('span', { class: 'or-bar-v' }, [F.count(s.visitors, 'vizitator', 'vizitatori'), el('small', { text: F.pct(pct, 0) })]),
        el('div', { class: 'or-meter', 'aria-hidden': 'true' }, bar),
        num(s.revenue) > 0 ? el('span', { class: 'or-sub', text: 'Vânzări atribuite: ' + money(s.revenue) }) : null,
      ]));
    });
  }
  function renderLocations() {
    var box = $('or-locations'), list = Array.isArray(data.top_locations) ? data.top_locations.slice(0, 8) : [];
    box.textContent = '';
    if (!list.length) { box.appendChild(el('li', { class: 'or-empty-p', text: 'Nu există date despre locații.' })); return; }
    list.forEach(function (l, i) {
      var code = String(l.country_code || l.country || '').toUpperCase(), country = '';
      try { country = geo && /^[A-Z]{2}$/.test(code) ? geo.of(code) : code; } catch (e) { country = code; }
      var city = F.flat(l.city);
      box.appendChild(el('li', null, [
        el('span', { class: 'or-rank-n', text: String(i + 1) }),
        el('span', null, [el('b', { text: city && city !== 'Unknown' ? city : country || 'Necunoscut' }), city && city !== 'Unknown' && country ? el('small', { text: country }) : null]),
        el('span', { class: 'or-rank-v', text: F.num(l.visitors || l.count) }),
      ]));
    });
  }

  /* =================== REFUNDS + ORDERS =================== */
  function orderRow(o, refund) {
    var st = ORDER_STATUS[o.status];
    return el('li', null, [
      el('div', null, [
        el('b', { text: F.flat(o.customer) || 'Client' }),
        el('span', { class: 'or-sub', text: [o.order_number, stamp(o.created_at), refund ? '' : F.count(o.tickets_count, 'bilet', 'bilete')].filter(Boolean).join(' · ') }),
        refund && st ? el('span', { class: 'org-tag ' + st[1], text: st[0] }) : null,
      ]),
      el('span', { class: 'or-row-v' + (refund ? ' is-refund' : ''), text: (refund ? '−' : '') + money(refund ? o.total : (o.net_total != null ? o.net_total : o.total)) }),
    ]);
  }
  function loadRefunds() {
    var base = '/organizer/orders?event_id=' + ID + '&per_page=50&sort_by=created_at&sort_dir=desc&status=';
    Promise.all([
      O.api(base + 'refunded', { quiet: true }).then(function (r) { return r; }, function () { return null; }),
      O.api(base + 'partially_refunded', { quiet: true }).then(function (r) { return r; }, function () { return null; }),
    ]).then(function (res) {
      var box = $('or-refunds'), rows = [];
      box.textContent = '';
      if (!res[0] && !res[1]) { empty(box, 'Nu am putut încărca rambursările.'); return; }
      res.forEach(function (r) { if (r && Array.isArray(r.data)) rows = rows.concat(r.data); });
      rows.sort(function (a, b) { return String(b.created_at || '') < String(a.created_at || '') ? -1 : 1; });
      if (!rows.length) { box.appendChild(el('li', { class: 'or-empty-p', text: 'Nicio rambursare.' })); return; }
      rows.forEach(function (o) { box.appendChild(orderRow(o, true)); });
      var more = res.reduce(function (s, r) { return s + (r ? Math.max(0, num(O.metaOf(r).total) - (Array.isArray(r.data) ? r.data.length : 0)) : 0); }, 0);
      if (more > 0) box.appendChild(el('li', { class: 'or-note', text: 'Și încă ' + F.count(more, 'comandă rambursată', 'comenzi rambursate') + ', în Vânzări.' }));
    });
  }
  function renderOrders(r) {
    var box = $('or-orders'), rows = r && Array.isArray(r.data) ? r.data : [];
    box.textContent = '';
    if (!rows.length) { box.appendChild(el('li', { class: 'or-empty-p', text: 'Nicio comandă finalizată încă.' })); return; }
    rows.forEach(function (o) { box.appendChild(orderRow(o, false)); });
  }

  /* =================== FINANCIAL SUMMARY =================== */
  function renderFinance() {
    var o = data.overview || {}, rate = o.commission_rate != null ? F.pct(o.commission_rate, 2) : '', onTop = o.commission_mode === 'added_on_top' || o.commission_mode === 'on_top';
    $('or-f-gross').textContent = money(o.gross_revenue != null ? o.gross_revenue : o.total_revenue);
    $('or-f-refunds').textContent = '− ' + money(o.refunds_total);
    $('or-refunds-total').textContent = num(o.refunds_total) > 0 ? '− ' + money(o.refunds_total) : money(0);
    $('or-f-comm-l').textContent = 'Comision platformă' + (rate ? ' (' + rate + (o.use_fixed_commission ? ' fix' : '') + ')' : '');
    var comm = $('or-f-comm');
    comm.textContent = '';
    if (onTop) { comm.appendChild(document.createTextNode(money(o.commission_amount))); comm.appendChild(el('small', { text: 'plătit de client, peste preț' })); }
    else comm.textContent = '− ' + money(o.commission_amount);
    $('or-f-net').textContent = money(o.net_revenue != null ? o.net_revenue : o.total_revenue);
    $('or-fin-p').textContent = 'Veniturile brute și nete socotesc biletele valide: comenzile rambursate nu mai sunt incluse în ele, iar suma lor apare separat la Rambursări.' + (onTop ? ' Comisionul a fost plătit de clienți peste prețul biletului, deci nu se scade din venitul tău.' : '');
  }

  /* =================== PRINT + PDF =================== */
  $('or-print').addEventListener('click', function () { hide(); window.print(); });
  $('or-pdf').addEventListener('click', function () {
    var btn = this;
    if (!ID || btn.getAttribute('aria-busy') === 'true') return;
    var token = typeof BileteOnlineAuth !== 'undefined' && BileteOnlineAuth.getToken ? BileteOnlineAuth.getToken() : null;
    if (!token) { O.flash('Sesiunea a expirat. Autentifică-te din nou.', true); return; }
    var label = btn.querySelector('[data-label]'), base = (window.BILETEONLINE && window.BILETEONLINE.apiUrl) || '/api/proxy.php', name = 'raport-' + slug($('or-title').textContent) + '-' + F.ymd() + '.pdf';
    btn.setAttribute('aria-busy', 'true');
    label.textContent = 'Se generează…';
    fetch(base + '?action=organizer.event.report.export&event_id=' + encodeURIComponent(ID), { headers: { Authorization: 'Bearer ' + token, Accept: 'application/pdf' } }).then(function (res) {
      if (!res.ok) { var e = new Error('pdf'); e.status = res.status; throw e; }
      return res.blob();
    }).then(function (blob) {
      return blob.slice(0, 5).text().then(function (head) {
        // only core's PDF is saved; a refused token gets its sign-in page instead (200, the proxy follows the redirect)
        if (head !== '%PDF-') { var e = new Error('pdf'); e.html = true; throw e; }
        var url = URL.createObjectURL(blob), a = el('a', { href: url, download: name, hidden: true });
        document.body.appendChild(a);
        a.click();
        setTimeout(function () { URL.revokeObjectURL(url); a.remove(); }, 1500);
        O.flash('Raportul PDF a fost descărcat.');
      });
    }).catch(function (err) {
      function failed() { O.flash('Nu am putut genera raportul PDF. Încearcă din nou.', true); }
      function signOut() {
        O.flash('Sesiunea a expirat. Te trimitem la autentificare.', true);
        setTimeout(function () { O.api('/organizer/me').catch(function () {}); }, 1500);
      }
      if (err && err.status === 401) return signOut();
      if (err && err.status === 404) { O.flash('Activitatea nu a fost găsită.', true); return; }
      if (err && err.html) return O.api('/organizer/me', { quiet: true }).then(failed, function (e) { if (e && e.status === 401) signOut(); else failed(); });
      failed();
    }).then(function () {
      btn.removeAttribute('aria-busy');
      label.textContent = 'Export PDF';
    });
  });

  O.ready.then(function (ok) { if (ok) start(); });
})();
