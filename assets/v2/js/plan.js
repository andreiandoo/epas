/* bilete.online v2: the trip planner on /plan.
 *
 * Give it a place, a number of days, what you care about and a pace; it returns a day-by-day
 * itinerary built out of the same attraction dataset the map uses, then lets you move it around.
 *
 * It is deterministic on purpose. No model writes these itineraries: the planner filters the
 * catalogue by area and type, scores what is left, groups it into days that each stay in one
 * area, and orders every day so the driving is short. That means it can never claim something
 * about a place that is not in the catalogue -- and the catalogue has no opening hours, so the
 * durations are per-type estimates and the page says so.
 *
 * Your edits are protected: a stop you moved, added or pinned is kept exactly where it is when
 * the rest of the plan is regenerated, and a stop you removed is never suggested again.
 *
 * Nothing is stored on a server. The whole plan lives in the URL hash and in localStorage, so a
 * link is a plan.
 */
(function () {
  'use strict';

  var root = document.getElementById('pl-plan');
  var startBox = document.getElementById('pl-start');
  if (!root || !startBox || !window.EPMap) return;

  var CFG = {};
  try { CFG = (JSON.parse((document.getElementById('v2-data') || {}).textContent || '{}')).plan || {}; } catch (e) {}
  if (!CFG.dataUrl) return;

  var STORE = 'bo_plan_v1';
  var DAY_START = 9 * 60;          // itineraries start at 09:00
  var FILL_TO = 0.85;              // leave the last sixth of the day unplanned, on purpose
  var RADIUS_CITY = [45, 60, 75, 90, 100, 110, 120];   // km around a city, by number of days

  /* ---------------------------------------------------------------- utils */

  function el(tag, cls, text) {
    var n = document.createElement(tag);
    if (cls) n.className = cls;
    if (text !== undefined && text !== null) n.textContent = text;
    return n;
  }
  function icon(name, cls) {
    var s = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
    var u = document.createElementNS('http://www.w3.org/2000/svg', 'use');
    s.setAttribute('class', cls || 'ic');
    s.setAttribute('aria-hidden', 'true');
    u.setAttribute('href', '#i-' + name);
    s.appendChild(u);
    return s;
  }
  function fold(s) {
    return (s || '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();
  }
  function nf(n) { return new Intl.NumberFormat('ro-RO').format(Math.round(n)); }
  function hm(min) {
    min = Math.round(min || 0);
    var h = Math.floor(min / 60), m = min % 60;
    return h ? (h + ' h' + (m ? ' ' + m + ' min' : '')) : (m + ' min');
  }
  function clock(min) {
    var h = Math.floor(min / 60) % 24, m = Math.round(min) % 60;
    return (h < 10 ? '0' : '') + h + ':' + (m < 10 ? '0' : '') + m;
  }
  function km(aLat, aLng, bLat, bLng) {
    var r = Math.PI / 180, dLat = (bLat - aLat) * r, dLng = (bLng - aLng) * r;
    var x = Math.sin(dLat / 2) * Math.sin(dLat / 2) +
      Math.cos(aLat * r) * Math.cos(bLat * r) * Math.sin(dLng / 2) * Math.sin(dLng / 2);
    return 12742 * Math.asin(Math.min(1, Math.sqrt(x)));
  }
  /* Straight-line distance is not a drive: a detour factor and a floor make it an honest estimate. */
  function travelMin(distKm) {
    var t = (distKm * (CFG.travel.detour || 1.35)) / (CFG.travel.kmh || 55) * 60;
    return distKm < 0.3 ? 0 : Math.max(CFG.travel.min_leg || 5, Math.round(t));
  }
  function travelKm(distKm) { return Math.round(distKm * (CFG.travel.detour || 1.35) * 10) / 10; }
  function duration(typeSlug) {
    return CFG.durations[typeSlug] || CFG.durations.default || 45;
  }
  function dateLabel(iso, offset) {
    if (!iso) return 'Ziua ' + (offset + 1);
    var d = new Date(iso + 'T12:00:00');
    if (isNaN(d)) return 'Ziua ' + (offset + 1);
    d.setDate(d.getDate() + offset);
    return d.toLocaleDateString('ro-RO', { weekday: 'long', day: 'numeric', month: 'long' });
  }

  /* ---------------------------------------------------------------- data */

  var D = null;                    // the pin dataset, as loaded
  var bySlug = {};                 // slug -> index
  var cityCentre = {};             // city slug -> [lat, lng], averaged from its attractions
  var regionOf = {};               // point index -> region name

  function loadData() {
    return fetch(CFG.dataUrl, { credentials: 'omit' }).then(function (r) {
      if (!r.ok) throw new Error('map data ' + r.status);
      return r.json();
    }).then(function (raw) {
      var f = {};
      (raw.fields || []).forEach(function (name, i) { f[name] = i; });
      D = { f: f, rows: raw.points || [], types: raw.types || [], cities: raw.cities || [], zones: raw.zones || [], flags: raw.flags || { image: 1, activities: 4 } };

      var sums = {};
      for (var i = 0; i < D.rows.length; i++) {
        var r = D.rows[i];
        bySlug[r[f.slug]] = i;
        var ci = r[f.city];
        if (ci >= 0) {
          var slug = D.cities[ci][0];
          if (!sums[slug]) sums[slug] = [0, 0, 0];
          sums[slug][0] += r[f.lat_e5] / 1e5;
          sums[slug][1] += r[f.lng_e5] / 1e5;
          sums[slug][2]++;
        }
        var zi = r[f.zone];
        if (zi >= 0) regionOf[i] = D.zones[zi][1] || '';
      }
      Object.keys(sums).forEach(function (s) {
        cityCentre[s] = [sums[s][0] / sums[s][2], sums[s][1] / sums[s][2]];
      });
      return D;
    });
  }

  /** The shape EPMap's route mode and the itinerary rows both read. */
  function stopRow(i, legKm, legMin) {
    var f = D.f, r = D.rows[i];
    var t = r[f.type] >= 0 ? D.types[r[f.type]] : null;
    var c = r[f.city] >= 0 ? D.cities[r[f.city]] : null;
    return [
      r[f.slug], r[f.name], c ? c[1] : '', c ? c[0] : '', c ? c[2] : '',
      t ? t[1] : '', t ? t[2] : '', r[f.lat_e5] / 1e5, r[f.lng_e5] / 1e5, r[f.img] || '',
      legKm || 0, legMin || 0
    ];
  }

  /* ---------------------------------------------------------------- the plan */

  var plan = null;

  function blankPlan() {
    return { where: null, days: 2, from: '', interests: [], pace: 'normal', stops: [], locked: {}, removed: {}, custom: {} };
  }

  function wantedTypes() {
    if (!plan.interests.length) return null;
    var set = {};
    plan.interests.forEach(function (key) {
      (CFG.interests[key] ? CFG.interests[key][2] : []).forEach(function (t) { set[t] = 1; });
    });
    return set;
  }

  /**
   * Everything that could go in the plan: inside the area, of a type that was asked for, and not
   * something the traveller already threw out. Scored so the day builder has an opinion about what
   * is worth the detour.
   */
  function candidates() {
    var f = D.f, want = wantedTypes(), out = [];
    var centre = plan.where.lat != null ? [plan.where.lat, plan.where.lng] : null;
    var radius = plan.where.kind === 'city' ? (RADIUS_CITY[Math.min(plan.days, RADIUS_CITY.length) - 1]) : 0;

    for (var i = 0; i < D.rows.length; i++) {
      var r = D.rows[i];
      if (plan.removed[r[f.slug]]) continue;

      var tSlug = r[f.type] >= 0 ? D.types[r[f.type]][0] : '';
      if (want && !want[tSlug]) continue;

      var lat = r[f.lat_e5] / 1e5, lng = r[f.lng_e5] / 1e5, d = 0;
      if (plan.where.kind === 'region') {
        if (regionOf[i] !== plan.where.key) continue;
        if (centre) d = km(centre[0], centre[1], lat, lng);
      } else {
        d = km(centre[0], centre[1], lat, lng);
        if (d > radius) continue;
      }

      var flags = r[f.flags];
      var score = 0;
      if (flags & D.flags.image) score += 2.5;          // a photo means somebody curated the row
      if (flags & (D.flags.activities || 4)) score += 4; // and a bookable place is worth the stop
      score += (duration(tSlug) >= 60 ? 1.5 : 0);        // places you actually spend time in
      score -= d / 40;                                   // near beats far, gently
      out.push({ i: i, lat: lat, lng: lng, type: tSlug, score: score, d: d });
    }
    out.sort(function (a, b) { return b.score - a.score; });
    return out;
  }

  /** Nearest-neighbour ordering from the first stop: short driving, in the order you would drive it. */
  function orderDay(list) {
    if (list.length < 3) return list;
    var rest = list.slice(1), out = [list[0]], cur = list[0];
    while (rest.length) {
      var best = 0, bestD = Infinity;
      for (var i = 0; i < rest.length; i++) {
        var d = km(cur.lat, cur.lng, rest[i].lat, rest[i].lng);
        if (d < bestD) { bestD = d; best = i; }
      }
      cur = rest.splice(best, 1)[0];
      out.push(cur);
    }
    return out;
  }

  /**
   * Build the days. Stops the traveller locked stay in the day they are in; the rest of each day is
   * filled from the candidate pool, nearest-and-best-first, until the day's budget is nearly full.
   */
  function generate() {
    var budget = (CFG.paces[plan.pace] ? CFG.paces[plan.pace][1] : 420) * FILL_TO;
    var pool = candidates();
    var used = {};
    var days = [];

    // Locked stops first, in their own day, so a regenerate cannot move them.
    for (var d = 0; d < plan.days; d++) {
      var keep = [];
      (plan.stops[d] || []).forEach(function (slug) {
        if (!plan.locked[slug]) return;
        var i = bySlug[slug];
        if (i === undefined || used[i]) return;
        used[i] = 1;
        keep.push({ i: i, lat: D.rows[i][D.f.lat_e5] / 1e5, lng: D.rows[i][D.f.lng_e5] / 1e5, type: D.rows[i][D.f.type] >= 0 ? D.types[D.rows[i][D.f.type]][0] : '', score: 99 });
      });
      days.push(keep);
    }
    pool = pool.filter(function (c) { return !used[c.i]; });

    for (var day = 0; day < plan.days; day++) {
      var list = days[day];
      var spent = 0;
      list.forEach(function (s, n) {
        spent += duration(s.type) + (n ? travelMin(km(list[n - 1].lat, list[n - 1].lng, s.lat, s.lng)) : 0);
      });

      // Seed an empty day with the best thing left, so each day starts somewhere worth going.
      if (!list.length) {
        var seed = pool.shift();
        if (!seed) break;
        list.push(seed);
        used[seed.i] = 1;
        spent += duration(seed.type);
      }

      // Then add whatever is cheap to reach and still good, until the day is nearly full.
      for (;;) {
        var last = list[list.length - 1];
        var pick = -1, pickCost = Infinity, pickMin = 0;
        for (var k = 0; k < pool.length; k++) {
          var c = pool[k];
          if (used[c.i]) continue;
          var tm = travelMin(km(last.lat, last.lng, c.lat, c.lng));
          if (tm > 75) continue;                       // another day's business
          var need = tm + duration(c.type);
          if (spent + need > budget) continue;
          var cost = tm - c.score * 6;                 // a better place is worth a longer hop
          if (cost < pickCost) { pickCost = cost; pick = k; pickMin = need; }
        }
        if (pick < 0) break;
        var chosen = pool.splice(pick, 1)[0];
        used[chosen.i] = 1;
        list.push(chosen);
        spent += pickMin;
      }

      days[day] = orderDay(list);
      pool = pool.filter(function (c) { return !used[c.i]; });
    }

    plan.stops = days.map(function (list) {
      return list.map(function (s) { return D.rows[s.i][D.f.slug]; });
    });
  }

  /* ---------------------------------------------------------------- derived view */

  /** One day as rows with travel and clock times, plus its totals. */
  function dayView(dayIndex) {
    var slugs = plan.stops[dayIndex] || [];
    var rows = [], t = DAY_START, totalKm = 0, visit = 0, travel = 0;
    for (var n = 0; n < slugs.length; n++) {
      var i = bySlug[slugs[n]];
      if (i === undefined) continue;
      var lat = D.rows[i][D.f.lat_e5] / 1e5, lng = D.rows[i][D.f.lng_e5] / 1e5;
      var legKm = 0, legMin = 0;
      if (rows.length) {
        var prev = rows[rows.length - 1];
        var raw = km(prev.lat, prev.lng, lat, lng);
        legKm = travelKm(raw);
        legMin = travelMin(raw);
        totalKm += legKm;
        travel += legMin;
        t += legMin;
      }
      var tSlug = D.rows[i][D.f.type] >= 0 ? D.types[D.rows[i][D.f.type]][0] : '';
      var dur = plan.custom[slugs[n]] || duration(tSlug);
      rows.push({
        slug: slugs[n], i: i, lat: lat, lng: lng, type: tSlug,
        start: t, dur: dur, legKm: legKm, legMin: legMin,
        row: stopRow(i, legKm, legMin)
      });
      visit += dur;
      t += dur;
    }
    return { rows: rows, km: totalKm, visit: visit, travel: travel, end: t };
  }

  /* ---------------------------------------------------------------- storage + url */

  function encode() {
    var compact = {
      w: plan.where, d: plan.days, f: plan.from, i: plan.interests, p: plan.pace,
      s: plan.stops, l: Object.keys(plan.locked), r: Object.keys(plan.removed), c: plan.custom
    };
    try {
      return btoa(unescape(encodeURIComponent(JSON.stringify(compact)))).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
    } catch (e) { return ''; }
  }
  function decode(str) {
    try {
      var b = str.replace(/-/g, '+').replace(/_/g, '/');
      var o = JSON.parse(decodeURIComponent(escape(atob(b))));
      var p = blankPlan();
      p.where = o.w; p.days = o.d || 2; p.from = o.f || ''; p.interests = o.i || [];
      p.pace = o.p || 'normal'; p.stops = o.s || []; p.custom = o.c || {};
      (o.l || []).forEach(function (s) { p.locked[s] = 1; });
      (o.r || []).forEach(function (s) { p.removed[s] = 1; });
      return p.where ? p : null;
    } catch (e) { return null; }
  }
  function save() {
    var code = encode();
    if (!code) return;
    try { localStorage.setItem(STORE, code); } catch (e) {}
    history.replaceState(null, '', location.pathname + '#p=' + code);
  }
  function restore() {
    var m = /[#&]p=([A-Za-z0-9_-]+)/.exec(location.hash);
    if (m) return decode(m[1]);
    try { return decode(localStorage.getItem(STORE) || ''); } catch (e) { return null; }
  }

  /* ---------------------------------------------------------------- rendering */

  var ui = {
    bar: document.getElementById('pl-bar'),
    list: document.getElementById('pl-days-list'),
    note: document.getElementById('pl-map-note')
  };
  var activeDay = 0;

  function render() {
    startBox.hidden = true;
    root.hidden = false;
    renderBar();
    renderDays();
    syncMap();
    save();
  }

  function renderBar() {
    ui.bar.textContent = '';
    var left = el('div', 'pl-bar-main');
    left.appendChild(el('h2', 'pl-bar-h', plan.where.label));

    var totals = plan.stops.reduce(function (acc, _, d) {
      var v = dayView(d);
      acc.stops += v.rows.length; acc.km += v.km; acc.min += v.visit + v.travel;
      return acc;
    }, { stops: 0, km: 0, min: 0 });

    var meta = el('p', 'pl-bar-meta');
    meta.appendChild(el('span', '', plan.days + (plan.days === 1 ? ' zi' : ' zile')));
    meta.appendChild(el('span', '', totals.stops + (totals.stops === 1 ? ' oprire' : ' opriri')));
    meta.appendChild(el('span', '', nf(totals.km) + ' km'));
    meta.appendChild(el('span', '', (CFG.paces[plan.pace] || ['Normal'])[0]));
    left.appendChild(meta);
    ui.bar.appendChild(left);

    var acts = el('div', 'pl-bar-acts');
    acts.appendChild(btn('arrow-right', 'Regenerează', function () {
      generate();
      render();
    }, 'pl-btn'));
    acts.appendChild(btn('link', 'Copiază link', function (b) {
      save();
      var done = function () {
        b.classList.add('is-done');
        b.querySelector('span').textContent = 'Copiat';
        setTimeout(function () { b.classList.remove('is-done'); b.querySelector('span').textContent = 'Copiază link'; }, 2000);
      };
      if (navigator.clipboard) navigator.clipboard.writeText(location.href).then(done, done);
      else done();
    }, 'pl-btn'));
    acts.appendChild(btn('printer', 'Tipărește', function () { window.print(); }, 'pl-btn'));
    acts.appendChild(btn('gear-six', 'Schimbă', function () {
      root.hidden = true;
      startBox.hidden = false;
      startBox.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }, 'pl-btn'));
    ui.bar.appendChild(acts);
  }

  function btn(ic, label, fn, cls) {
    var b = el('button', cls || 'pl-btn');
    b.type = 'button';
    b.appendChild(icon(ic));
    b.appendChild(el('span', '', label));
    b.addEventListener('click', function () { fn(b); });
    return b;
  }

  function renderDays() {
    ui.list.textContent = '';
    for (var d = 0; d < plan.days; d++) ui.list.appendChild(dayCard(d));
  }

  function dayCard(d) {
    var view = dayView(d);
    var box = el('section', 'pl-day' + (d === activeDay ? ' is-active' : ''));
    box.dataset.day = String(d);

    var head = el('header', 'pl-day-head');
    var hl = el('div');
    hl.appendChild(el('h3', 'pl-day-h', dateLabel(plan.from, d)));
    var sub = el('p', 'pl-day-sub');
    sub.textContent = view.rows.length
      ? view.rows.length + (view.rows.length === 1 ? ' oprire · ' : ' opriri · ') + nf(view.km) + ' km · ' +
        clock(DAY_START) + '–' + clock(view.end) + ' · ' + hm(view.travel) + ' pe drum'
      : 'Zi liberă — adaugă ceva sau regenerează.';
    hl.appendChild(sub);
    head.appendChild(hl);

    var show = el('button', 'pl-day-show');
    show.type = 'button';
    show.appendChild(icon('map-pin'));
    show.appendChild(el('span', '', d === activeDay ? 'Pe hartă' : 'Vezi pe hartă'));
    show.addEventListener('click', function () { activeDay = d; renderDays(); syncMap(); });
    head.appendChild(show);
    box.appendChild(head);

    var ol = el('ol', 'pl-stops');
    view.rows.forEach(function (r, n) { ol.appendChild(stopItem(d, n, r, view.rows.length)); });
    box.appendChild(ol);

    var foot = el('div', 'pl-day-foot');
    if (view.rows.length) {
      var free = (CFG.paces[plan.pace] ? CFG.paces[plan.pace][1] : 420) - (view.visit + view.travel);
      foot.appendChild(el('p', 'pl-free', free > 20
        ? 'Rămân ' + hm(free) + ' liberi — loc pentru masă, cafea sau ce apare pe drum.'
        : 'Ziua e plină.'));
    }
    foot.appendChild(addControl(d));
    box.appendChild(foot);
    return box;
  }

  function stopItem(d, n, r, total) {
    var li = el('li', 'pl-stop');
    var num = el('span', 'pl-stop-num', String(n + 1));
    num.setAttribute('aria-hidden', 'true');
    li.appendChild(num);

    var card = el('div', 'pl-stop-card' + (plan.locked[r.slug] ? ' is-locked' : ''));

    if (n > 0) {
      var leg = el('p', 'pl-leg');
      leg.appendChild(icon('arrow-right'));
      leg.appendChild(document.createTextNode(nf(r.legKm) + ' km · ' + hm(r.legMin) + ' de mers'));
      card.appendChild(leg);
    }

    var body = el('div', 'pl-stop-body');
    var media = el('a', 'pl-stop-media');
    media.href = '/atractie/' + r.slug;
    media.target = '_blank';
    media.rel = 'noopener';
    if (r.row[9]) {
      var img = el('img');
      img.src = r.row[9];
      img.alt = '';
      img.loading = 'lazy';
      img.decoding = 'async';
      media.appendChild(img);
    } else {
      var ph = el('span', '', r.row[6] || '📍');
      ph.setAttribute('aria-hidden', 'true');
      media.appendChild(ph);
    }
    body.appendChild(media);

    var text = el('div', 'pl-stop-text');
    var time = el('p', 'pl-stop-time', clock(r.start) + '–' + clock(r.start + r.dur));
    text.appendChild(time);
    var title = el('a', 'pl-stop-title', r.row[1]);
    title.href = '/atractie/' + r.slug;
    title.target = '_blank';
    title.rel = 'noopener';
    text.appendChild(title);
    var meta = el('p', 'pl-stop-meta');
    meta.appendChild(document.createTextNode([r.row[5], r.row[2]].filter(Boolean).join(' · ')));
    text.appendChild(meta);

    var durWrap = el('label', 'pl-dur');
    durWrap.appendChild(el('span', 'sr', 'Cât stai la ' + r.row[1]));
    var sel = el('select');
    [20, 30, 45, 60, 90, 120, 180].forEach(function (m) {
      var o = el('option', '', hm(m));
      o.value = String(m);
      if (m === r.dur) o.selected = true;
      sel.appendChild(o);
    });
    if ([20, 30, 45, 60, 90, 120, 180].indexOf(r.dur) === -1) {
      var o2 = el('option', '', hm(r.dur));
      o2.value = String(r.dur);
      o2.selected = true;
      sel.appendChild(o2);
    }
    sel.addEventListener('change', function () {
      plan.custom[r.slug] = parseInt(sel.value, 10);
      plan.locked[r.slug] = 1;
      renderDays();
      save();
    });
    durWrap.appendChild(sel);
    durWrap.appendChild(el('small', '', 'estimat'));
    text.appendChild(durWrap);
    body.appendChild(text);
    card.appendChild(body);

    var acts = el('div', 'pl-stop-acts');
    acts.appendChild(iconBtn('arrow-left', 'Mai devreme', n === 0, function () { move(d, n, -1); }, 'up'));
    acts.appendChild(iconBtn('arrow-right', 'Mai târziu', n === total - 1, function () { move(d, n, 1); }, 'down'));
    if (plan.days > 1) {
      var mv = el('select', 'pl-move');
      mv.setAttribute('aria-label', 'Mută în altă zi');
      var head = el('option', '', 'Ziua…');
      head.value = '';
      mv.appendChild(head);
      for (var k = 0; k < plan.days; k++) {
        if (k === d) continue;
        var o3 = el('option', '', 'Ziua ' + (k + 1));
        o3.value = String(k);
        mv.appendChild(o3);
      }
      mv.addEventListener('change', function () {
        if (mv.value === '') return;
        moveToDay(d, n, parseInt(mv.value, 10));
      });
      acts.appendChild(mv);
    }
    acts.appendChild(iconBtn('x', 'Scoate din plan', false, function () { remove(d, n); }, 'del'));
    card.appendChild(acts);

    li.appendChild(card);
    return li;
  }

  function iconBtn(ic, label, disabled, fn, cls) {
    var b = el('button', 'pl-ib pl-ib-' + cls);
    b.type = 'button';
    b.disabled = !!disabled;
    b.title = label;
    b.appendChild(icon(ic));
    b.appendChild(el('span', 'sr', label));
    b.addEventListener('click', fn);
    return b;
  }

  /* ---------- editing: every change locks what it touched ---------- */

  function lockDay(d) {
    (plan.stops[d] || []).forEach(function (s) { plan.locked[s] = 1; });
  }
  function move(d, n, dir) {
    var list = plan.stops[d];
    var to = n + dir;
    if (to < 0 || to >= list.length) return;
    var x = list[n];
    list[n] = list[to];
    list[to] = x;
    lockDay(d);
    renderDays();
    syncMap();
    save();
  }
  function moveToDay(from, n, to) {
    var slug = plan.stops[from].splice(n, 1)[0];
    plan.stops[to].push(slug);
    plan.locked[slug] = 1;
    lockDay(to);
    activeDay = to;
    renderDays();
    syncMap();
    save();
  }
  function remove(d, n) {
    var slug = plan.stops[d].splice(n, 1)[0];
    plan.removed[slug] = 1;
    delete plan.locked[slug];
    renderDays();
    syncMap();
    save();
  }
  function add(d, slug) {
    if (!bySlug.hasOwnProperty(slug)) return;
    for (var k = 0; k < plan.stops.length; k++) {
      var at = plan.stops[k].indexOf(slug);
      if (at !== -1) plan.stops[k].splice(at, 1);
    }
    delete plan.removed[slug];
    plan.stops[d].push(slug);
    plan.locked[slug] = 1;
    activeDay = d;
    renderDays();
    syncMap();
    save();
  }

  /** Search the catalogue and drop the result straight into this day. */
  function addControl(d) {
    var wrap = el('div', 'pl-add');
    var inp = el('input');
    inp.type = 'search';
    inp.placeholder = 'Adaugă o oprire în ziua asta…';
    inp.setAttribute('aria-label', 'Caută o atracție de adăugat');
    inp.autocomplete = 'off';
    var list = el('ul', 'pl-add-list');
    list.hidden = true;

    var search = function () {
      var q = fold(inp.value.trim());
      list.textContent = '';
      if (q.length < 2) { list.hidden = true; return; }
      var f = D.f, hits = 0;
      for (var i = 0; i < D.rows.length && hits < 6; i++) {
        var name = D.rows[i][f.name];
        if (fold(name).indexOf(q) === -1) continue;
        hits++;
        var c = D.rows[i][f.city] >= 0 ? D.cities[D.rows[i][f.city]][1] : '';
        var li = el('li');
        var b = el('button', 'pl-add-hit');
        b.type = 'button';
        b.appendChild(el('b', '', name));
        if (c) b.appendChild(el('small', '', c));
        b.addEventListener('click', (function (slug) {
          return function () { inp.value = ''; list.hidden = true; add(d, slug); };
        })(D.rows[i][f.slug]));
        li.appendChild(b);
        list.appendChild(li);
      }
      list.hidden = !hits;
    };
    var t;
    inp.addEventListener('input', function () { clearTimeout(t); t = setTimeout(search, 140); });
    inp.addEventListener('blur', function () { setTimeout(function () { list.hidden = true; }, 180); });
    wrap.appendChild(inp);
    wrap.appendChild(list);
    return wrap;
  }

  /* ---------------------------------------------------------------- the map */

  function syncMap() {
    var inst = window.EPMap.instance;
    if (!inst) return;
    var view = dayView(activeDay);
    inst.boot().then(function () {
      inst.setRoute(view.rows.map(function (r) { return r.row; }), '');
      if (ui.note) {
        ui.note.textContent = view.rows.length
          ? 'Ziua ' + (activeDay + 1) + ': ' + view.rows.length + (view.rows.length === 1 ? ' oprire, ' : ' opriri, ') +
            nf(view.km) + ' km. Distanțele și timpii sunt estimări, nu drum calculat pe șosea.'
          : 'Ziua ' + (activeDay + 1) + ' e goală.';
      }
    });
  }

  /* ---------------------------------------------------------------- the start form */

  var form = document.getElementById('pl-form');
  var whereInput = document.getElementById('pl-where');
  var sugg = document.getElementById('pl-sugg');
  var chosen = null;

  /**
   * Everywhere you can start from. Once the dataset is in, that is every city that actually has an
   * attraction -- all ~1,100 of them, not just the forty the page could afford to print. Until
   * then, the handful the server sent, so the field is never dead.
   */
  function places() {
    var out = [];
    (CFG.regions || []).forEach(function (r) {
      out.push({ kind: 'region', key: r[0], label: r[0], count: r[1], hint: 'regiune' });
    });
    if (D) {
      var counts = {};
      for (var i = 0; i < D.rows.length; i++) {
        var ci = D.rows[i][D.f.city];
        if (ci >= 0) counts[ci] = (counts[ci] || 0) + 1;
      }
      Object.keys(counts).forEach(function (ci) {
        var c = D.cities[ci];
        if (c && c[0]) out.push({ kind: 'city', key: c[0], label: c[1], count: counts[ci], hint: c[2] ? 'oraș · ' + c[2] : 'oraș' });
      });
      out.sort(function (a, b) { return b.count - a.count; });
    } else {
      (CFG.cities || []).forEach(function (c) {
        out.push({ kind: 'city', key: c[0], label: c[1], count: c[2], hint: 'oraș' });
      });
    }
    return out;
  }
  var ALL_PLACES = null;

  function suggest() {
    if (!ALL_PLACES) ALL_PLACES = places();
    var q = fold(whereInput.value.trim());
    sugg.textContent = '';
    if (!q) { sugg.hidden = true; return; }
    var hits = ALL_PLACES.filter(function (p) { return fold(p.label).indexOf(q) === 0; })
      .concat(ALL_PLACES.filter(function (p) { return fold(p.label).indexOf(q) > 0; }))
      .slice(0, 7);
    hits.forEach(function (p) {
      var li = el('li');
      li.setAttribute('role', 'option');
      var b = el('button', 'pl-sugg-hit');
      b.type = 'button';
      b.appendChild(el('b', '', p.label));
      b.appendChild(el('small', '', p.hint + ' · ' + nf(p.count) + ' atracții'));
      b.addEventListener('click', function () { pick(p); });
      li.appendChild(b);
      sugg.appendChild(li);
    });
    if (!hits.length) {
      // Silence would read as broken. Several big cities (Brașov, Sibiu, Constanța, Iași) have no
      // attraction in the catalogue yet, and saying so is better than an empty box.
      var li = el('li', 'pl-sugg-empty');
      li.textContent = 'Nu avem încă atracții catalogate acolo. Încearcă alt oraș sau o regiune.';
      sugg.appendChild(li);
    }
    sugg.hidden = false;
  }
  function pick(p) {
    chosen = p;
    whereInput.value = p.label;
    sugg.hidden = true;
  }

  function resolveWhere(p) {
    if (p.kind === 'city') {
      var c = cityCentre[p.key];
      return { kind: 'city', key: p.key, label: p.label, lat: c ? c[0] : null, lng: c ? c[1] : null };
    }
    // A region's centre is the average of its own attractions.
    var sLat = 0, sLng = 0, n = 0;
    for (var i = 0; i < D.rows.length; i++) {
      if (regionOf[i] !== p.key) continue;
      sLat += D.rows[i][D.f.lat_e5] / 1e5;
      sLng += D.rows[i][D.f.lng_e5] / 1e5;
      n++;
    }
    return { kind: 'region', key: p.key, label: p.label, lat: n ? sLat / n : null, lng: n ? sLng / n : null };
  }

  function readForm() {
    plan = plan || blankPlan();
    plan.days = Math.max(1, Math.min(7, parseInt(document.getElementById('pl-days').value, 10) || 2));
    plan.from = document.getElementById('pl-from').value || '';
    plan.interests = [].slice.call(document.querySelectorAll('#pl-interests [aria-pressed="true"]'))
      .map(function (b) { return b.dataset.interest; });
    var pace = document.querySelector('#pl-pace [aria-pressed="true"]');
    plan.pace = pace ? pace.dataset.pace : 'normal';
    plan.stops = [];
    for (var i = 0; i < plan.days; i++) plan.stops.push([]);
  }

  function start(place) {
    plan = blankPlan();
    readForm();
    plan.where = resolveWhere(place);
    if (plan.where.lat == null) return;
    generate();
    activeDay = 0;
    render();
    root.scrollIntoView({ behavior: 'smooth', block: 'start' });
  }

  /* ---------------------------------------------------------------- wiring */

  whereInput.addEventListener('input', function () { chosen = null; suggest(); });
  whereInput.addEventListener('focus', suggest);
  document.addEventListener('click', function (e) {
    if (!sugg.contains(e.target) && e.target !== whereInput) sugg.hidden = true;
  });

  document.getElementById('pl-interests').addEventListener('click', function (e) {
    var b = e.target.closest('[data-interest]');
    if (!b) return;
    b.setAttribute('aria-pressed', b.getAttribute('aria-pressed') === 'true' ? 'false' : 'true');
  });
  document.getElementById('pl-pace').addEventListener('click', function (e) {
    var b = e.target.closest('[data-pace]');
    if (!b) return;
    [].forEach.call(this.querySelectorAll('[data-pace]'), function (x) {
      x.setAttribute('aria-pressed', String(x === b));
    });
  });
  [].forEach.call(document.querySelectorAll('[data-days]'), function (b) {
    b.addEventListener('click', function () {
      var inp = document.getElementById('pl-days');
      inp.value = Math.max(1, Math.min(7, (parseInt(inp.value, 10) || 2) + parseInt(b.dataset.days, 10)));
    });
  });
  [].forEach.call(document.querySelectorAll('[data-start-city]'), function (b) {
    b.addEventListener('click', function () {
      var slug = b.dataset.startCity;
      var p = (ALL_PLACES || (ALL_PLACES = places())).filter(function (x) { return x.kind === 'city' && x.key === slug; })[0];
      if (!p) return;
      pick(p);
      form.dispatchEvent(new Event('submit', { cancelable: true }));
    });
  });

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    if (!D) return;
    if (!ALL_PLACES) ALL_PLACES = places();
    var p = chosen;
    if (!p) {
      var q = fold(whereInput.value.trim());
      p = ALL_PLACES.filter(function (x) { return fold(x.label) === q; })[0] ||
        ALL_PLACES.filter(function (x) { return q && fold(x.label).indexOf(q) === 0; })[0];
    }
    if (!p) {
      whereInput.focus();
      whereInput.setAttribute('aria-invalid', 'true');
      return;
    }
    whereInput.removeAttribute('aria-invalid');
    start(p);
  });

  /* ---------------------------------------------------------------- boot */

  loadData().then(function () {
    ALL_PLACES = places();          // now every city, not just the forty the page printed
    var saved = restore();
    if (saved && saved.stops && saved.stops.length) {
      plan = saved;
      activeDay = 0;
      render();
    }
  }).catch(function (err) {
    if (window.console) console.warn('[plan]', err);
  });
})();
