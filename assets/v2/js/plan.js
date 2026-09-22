/* bilete.online v2: the trip planner on /plan.
 *
 * Where you leave from, where you are going, for how long, with whom and at what pace; it returns
 * a day-by-day itinerary built out of the attraction catalogue and out of whatever can actually be
 * booked on the marketplace, then lets you move it around.
 *
 * It is deterministic on purpose. No model writes these itineraries: the planner filters the
 * catalogue by area and type, scores what is left, groups it into days that each stay in one area,
 * and orders every day so the driving is short. It can never claim something about a place that is
 * not in the catalogue. Two things it is honest about rather than pretending otherwise: visit
 * durations are per-type estimates (the catalogue has no opening hours), and "who you travel with"
 * is a weighting of types, not a label on each place.
 *
 * Distances are real. Each day is sent once to /api/route.php, which routes it on OpenStreetMap
 * data server-side and caches the answer, so the community routing service sees one call per
 * distinct day rather than one per visitor. Until an answer arrives, or if it never does, the page
 * shows a straight-line estimate and says which it is.
 *
 * Nothing is stored on a server unless you ask: the plan lives in the URL hash and in localStorage,
 * so a link is a plan.
 */
(function () {
  'use strict';

  var root = document.getElementById('pl-plan');
  var startBox = document.getElementById('pl-start');
  if (!root || !startBox || !window.EPMap) return;

  var CFG = {};
  try { CFG = (JSON.parse((document.getElementById('v2-data') || {}).textContent || '{}')).plan || {}; } catch (e) {}
  if (!CFG.dataUrl) return;

  var STORE = 'bo_plan_v2';
  var DAY_START = 9 * 60;
  var FILL_TO = 0.85;                                   // leave the last sixth of the day free
  var RADIUS_CITY = [45, 60, 75, 90, 100, 110, 120];    // km around a city, by number of days
  var CRLF = String.fromCharCode(13, 10);
  var COMBINING = new RegExp('[' + String.fromCharCode(0x300) + '-' + String.fromCharCode(0x36f) + ']', 'g');

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
  function fold(s) { return (s || '').normalize('NFD').replace(COMBINING, '').toLowerCase(); }
  /**
   * Catalogue covers are served at upload size — a 56px row does not need a megabyte. /api/img.php
   * resizes and caches; it passes anything that is not ours straight back, so this is safe to call
   * on any absolute URL.
   */
  function thumb(url, w, h) {
    if (!url || url.indexOf('http') !== 0) return url || '';
    return '/api/img.php?u=' + encodeURIComponent(url) + '&w=' + w + (h ? '&h=' + h : '');
  }

  function nf(n) { return new Intl.NumberFormat('ro-RO').format(Math.round(n)); }
  function km1(n) { return (Math.round(n * 10) / 10).toString().replace('.', ','); }
  function lei(cents) { return nf(Math.round(cents / 100)) + ' lei'; }
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
  function estMin(distKm) {
    var t = (distKm * (CFG.travel.detour || 1.35)) / (CFG.travel.kmh || 55) * 60;
    return distKm < 0.3 ? 0 : Math.max(CFG.travel.min_leg || 5, Math.round(t));
  }
  function estKm(distKm) { return Math.round(distKm * (CFG.travel.detour || 1.35) * 10) / 10; }
  function duration(typeSlug) { return CFG.durations[typeSlug] || CFG.durations.default || 45; }
  function dateLabel(iso, offset) {
    if (!iso) return 'Ziua ' + (offset + 1);
    var d = new Date(iso + 'T12:00:00');
    if (isNaN(d)) return 'Ziua ' + (offset + 1);
    d.setDate(d.getDate() + offset);
    return d.toLocaleDateString('ro-RO', { weekday: 'long', day: 'numeric', month: 'long' });
  }
  function debounce(fn, ms) {
    var t;
    return function () {
      var self = this, args = arguments;
      clearTimeout(t);
      t = setTimeout(function () { fn.apply(self, args); }, ms);
    };
  }

  /* ---------------------------------------------------------------- data */

  var D = null;
  var bySlug = {};
  var cityCentre = {};
  var regionOf = {};
  var BOOK = {};      // slug -> bookable row

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

      // [kind, slug, title, city, citySlug, county, lat, lng, approx, priceCents, minutes, img, category]
      (CFG.bookables || []).forEach(function (b) { BOOK[b[1]] = b; });
      return D;
    });
  }

  /**
   * One uniform stop, whatever it is underneath. 'b:' marks something bookable (an experience or a
   * leisure location) so the rest of the planner never has to care which catalogue it came from.
   */
  function entry(id) {
    if (id.indexOf('b:') === 0) {
      var b = BOOK[id.slice(2)];
      if (!b) return null;
      return {
        id: id, kind: b[0], slug: b[1], name: b[2], city: b[3], citySlug: b[4], county: b[5],
        lat: b[6], lng: b[7], approx: !!b[8], price: b[9] || 0, dur: b[10] || 90,
        img: b[11] || '', type: b[12] || (b[0] === 'location' ? 'Locație' : 'Experiență'),
        emoji: b[0] === 'location' ? '🎟️' : '✨', bookable: true, typeSlug: '',
        href: (b[0] === 'location' ? '/locatie/' : '/experienta/') + b[1]
      };
    }
    var i = bySlug[id];
    if (i === undefined) return null;
    var f = D.f, r = D.rows[i];
    var t = r[f.type] >= 0 ? D.types[r[f.type]] : null;
    var c = r[f.city] >= 0 ? D.cities[r[f.city]] : null;
    return {
      id: id, kind: 'attraction', slug: id, name: r[f.name], city: c ? c[1] : '', citySlug: c ? c[0] : '',
      county: c ? c[2] : '', lat: r[f.lat_e5] / 1e5, lng: r[f.lng_e5] / 1e5, approx: false,
      price: 0, dur: duration(t ? t[0] : ''), img: r[f.img] || '', type: t ? t[1] : '',
      emoji: t ? t[2] : '📍', typeSlug: t ? t[0] : '',
      bookable: !!(r[f.flags] & (D.flags.activities || 4)), href: '/atractie/' + id
    };
  }

  /** The row shape EPMap's route mode reads. */
  function mapRow(e, legKm, legMin) {
    return [e.slug, e.name, e.city, e.citySlug, e.county, e.type, e.emoji,
      e.lat, e.lng, e.img, legKm || 0, legMin || 0, e.bookable ? 4 : 0];
  }

  /* ---------------------------------------------------------------- the plan */

  var plan = null;

  function blankPlan() {
    return {
      origin: null, where: null, back: null, days: 2, from: '',
      interests: [], company: [], pace: 'normal',
      stops: [], locked: {}, removed: {}, custom: {}, token: ''
    };
  }

  function wantedTypes() {
    if (!plan.interests.length) return null;
    var set = {};
    plan.interests.forEach(function (key) {
      (CFG.interests[key] ? CFG.interests[key][2] : []).forEach(function (t) { set[t] = 1; });
    });
    return set;
  }
  /** Company weights, summed over everyone you are travelling with. */
  function companyWeight(typeSlug) {
    var w = 0;
    plan.company.forEach(function (key) {
      var def = CFG.company[key];
      if (def && def[3] && def[3][typeSlug] !== undefined) w += def[3][typeSlug];
    });
    return w;
  }
  function dayBudget() {
    var base = (CFG.paces[plan.pace] ? CFG.paces[plan.pace][1] : 420);
    var scale = 1;
    plan.company.forEach(function (key) {
      var def = CFG.company[key];
      if (def && def[2]) scale = Math.min(scale, def[2]);   // the shortest day wins
    });
    return base * scale;
  }

  function candidates() {
    var f = D.f, want = wantedTypes(), out = [];
    var centre = [plan.where.lat, plan.where.lng];
    var radius = plan.where.kind === 'city' ? RADIUS_CITY[Math.min(plan.days, RADIUS_CITY.length) - 1] : 0;

    for (var i = 0; i < D.rows.length; i++) {
      var r = D.rows[i];
      var slug = r[f.slug];
      if (plan.removed[slug]) continue;

      var tSlug = r[f.type] >= 0 ? D.types[r[f.type]][0] : '';
      if (want && !want[tSlug]) continue;

      var lat = r[f.lat_e5] / 1e5, lng = r[f.lng_e5] / 1e5;
      var d = km(centre[0], centre[1], lat, lng);
      if (plan.where.kind === 'region') {
        if (regionOf[i] !== plan.where.key) continue;
      } else if (d > radius) {
        continue;
      }

      var flags = r[f.flags];
      var score = 0;
      if (flags & D.flags.image) score += 2.5;
      if (flags & (D.flags.activities || 4)) score += 4;
      if (duration(tSlug) >= 60) score += 1.5;
      score += companyWeight(tSlug);
      score -= d / 40;
      out.push({ id: slug, lat: lat, lng: lng, type: tSlug, dur: duration(tSlug), score: score });
    }

    // Anything bookable inside the area goes in with a firm push: it is the part of a plan a
    // visitor can act on, and there is very little of it.
    (CFG.bookables || []).forEach(function (b) {
      var id = 'b:' + b[1];
      if (plan.removed[id]) return;
      var d = km(centre[0], centre[1], b[6], b[7]);
      if (d > (radius || 120)) return;
      out.push({ id: id, lat: b[6], lng: b[7], type: '', dur: b[10] || 90, score: 7 - d / 40 });
    });

    out.sort(function (a, b) { return b.score - a.score; });
    return out;
  }

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

  function generate() {
    var budget = dayBudget() * FILL_TO;
    var pool = candidates();
    var used = {};
    var days = [];

    for (var d = 0; d < plan.days; d++) {
      var keep = [];
      (plan.stops[d] || []).forEach(function (id) {
        if (!plan.locked[id] || used[id]) return;
        var e = entry(id);
        if (!e) return;
        used[id] = 1;
        keep.push({ id: id, lat: e.lat, lng: e.lng, type: e.typeSlug || '', dur: e.dur, score: 99 });
      });
      days.push(keep);
    }
    pool = pool.filter(function (c) { return !used[c.id]; });

    // Day one leaves from the departure point, so the first hop is a real hop.
    var anchor = plan.origin ? [plan.origin.lat, plan.origin.lng] : null;

    for (var day = 0; day < plan.days; day++) {
      var list = days[day];
      var spent = 0;
      list.forEach(function (s, n) {
        var from = n ? [list[n - 1].lat, list[n - 1].lng] : (day === 0 ? anchor : null);
        spent += s.dur + (from ? estMin(km(from[0], from[1], s.lat, s.lng)) : 0);
      });

      if (!list.length) {
        var seed = pool.shift();
        if (!seed) break;
        list.push(seed);
        used[seed.id] = 1;
        var from0 = day === 0 ? anchor : null;
        spent += seed.dur + (from0 ? estMin(km(from0[0], from0[1], seed.lat, seed.lng)) : 0);
      }

      for (;;) {
        var last = list[list.length - 1];
        var pick = -1, pickCost = Infinity, pickNeed = 0;
        for (var k = 0; k < pool.length; k++) {
          var c = pool[k];
          if (used[c.id]) continue;
          var tm = estMin(km(last.lat, last.lng, c.lat, c.lng));
          if (tm > 75) continue;
          var need = tm + c.dur;
          if (spent + need > budget) continue;
          var cost = tm - c.score * 6;
          if (cost < pickCost) { pickCost = cost; pick = k; pickNeed = need; }
        }
        if (pick < 0) break;
        var chosen = pool.splice(pick, 1)[0];
        used[chosen.id] = 1;
        list.push(chosen);
        spent += pickNeed;
      }

      days[day] = orderDay(list);
      pool = pool.filter(function (c) { return !used[c.id]; });
    }

    plan.stops = days.map(function (list) { return list.map(function (s) { return s.id; }); });
    roadCache = {};
  }

  /* ---------------------------------------------------------------- road distances */

  var roadCache = {};        // day key -> answer, or 'pending' / 'failed'

  /** Every point of a day, departure and return included — what actually gets driven. */
  function dayPoints(dayIndex) {
    var pts = [];
    if (dayIndex === 0 && plan.origin) {
      pts.push({ role: 'origin', name: plan.origin.label, lat: plan.origin.lat, lng: plan.origin.lng });
    }
    (plan.stops[dayIndex] || []).forEach(function (id) {
      var e = entry(id);
      if (e) pts.push({ role: 'stop', entry: e, lat: e.lat, lng: e.lng });
    });
    if (dayIndex === plan.days - 1) {
      var end = plan.back || plan.origin;
      if (end) pts.push({ role: 'back', name: end.label, lat: end.lat, lng: end.lng });
    }
    return pts;
  }
  function dayKey(dayIndex) {
    return dayPoints(dayIndex).map(function (p) { return p.lat.toFixed(5) + ',' + p.lng.toFixed(5); }).join(';');
  }

  /** Ask the server for the real road, once per distinct day. */
  function fetchRoads() {
    for (var d = 0; d < plan.days; d++) {
      (function () {
        var key = dayKey(d);
        if (!key || key.indexOf(';') === -1) return;   // fewer than two points: nothing to route
        if (roadCache[key]) return;
        roadCache[key] = 'pending';
        fetch('/api/route.php?c=' + encodeURIComponent(key), { credentials: 'omit' })
          .then(function (r) { return r.json(); })
          .then(function (j) {
            roadCache[key] = (j && j.ok) ? j : 'failed';
            renderBar();
            renderDays();
            syncMap();
          })
          .catch(function () { roadCache[key] = 'failed'; });
      })();
    }
  }
  function road(dayIndex) {
    var v = roadCache[dayKey(dayIndex)];
    return (v && v !== 'pending' && v !== 'failed') ? v : null;
  }
  function roadPending(dayIndex) { return roadCache[dayKey(dayIndex)] === 'pending'; }

  /* ---------------------------------------------------------------- derived view */

  /** A day as rows with clock times, using the routed legs once they arrive. */
  function dayView(dayIndex) {
    var pts = dayPoints(dayIndex);
    var r = road(dayIndex);
    var rows = [], t = DAY_START, totalKm = 0, visit = 0, travel = 0, cost = 0;

    for (var n = 0; n < pts.length; n++) {
      var p = pts[n];
      var legKm = 0, legMin = 0;
      if (n > 0) {
        if (r && r.legs && r.legs[n]) {
          legKm = r.legs[n][0];
          legMin = r.legs[n][1];
        } else {
          var raw = km(pts[n - 1].lat, pts[n - 1].lng, p.lat, p.lng);
          legKm = estKm(raw);
          legMin = estMin(raw);
        }
        totalKm += legKm;
        travel += legMin;
        t += legMin;
      }
      if (p.role !== 'stop') {
        rows.push({ role: p.role, name: p.name, lat: p.lat, lng: p.lng, start: t, dur: 0, legKm: legKm, legMin: legMin });
        continue;
      }
      var e = p.entry;
      var dur = plan.custom[e.id] || e.dur;
      rows.push({ role: 'stop', e: e, id: e.id, lat: e.lat, lng: e.lng, start: t, dur: dur, legKm: legKm, legMin: legMin });
      visit += dur;
      cost += e.price || 0;
      t += dur;
    }
    return { rows: rows, km: totalKm, visit: visit, travel: travel, end: t, cost: cost, routed: !!r, pending: roadPending(dayIndex) };
  }

  /* ---------------------------------------------------------------- storage + url */

  function encode() {
    var compact = {
      o: plan.origin, w: plan.where, b: plan.back, d: plan.days, f: plan.from,
      i: plan.interests, g: plan.company, p: plan.pace, s: plan.stops,
      l: Object.keys(plan.locked), r: Object.keys(plan.removed), c: plan.custom, t: plan.token || ''
    };
    try {
      return btoa(unescape(encodeURIComponent(JSON.stringify(compact)))).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
    } catch (e) { return ''; }
  }
  function decode(str) {
    try {
      var o = JSON.parse(decodeURIComponent(escape(atob(str.replace(/-/g, '+').replace(/_/g, '/')))));
      var p = blankPlan();
      p.origin = o.o || null; p.where = o.w; p.back = o.b || null;
      p.days = o.d || 2; p.from = o.f || ''; p.interests = o.i || []; p.company = o.g || [];
      p.pace = o.p || 'normal'; p.stops = o.s || []; p.custom = o.c || {}; p.token = o.t || '';
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

  /* ---------------------------------------------------------------- calendar */

  function icsTime(dayIndex, minutes) {
    var base = plan.from ? new Date(plan.from + 'T00:00:00') : new Date();
    base.setHours(0, 0, 0, 0);
    base.setDate(base.getDate() + dayIndex);
    base.setMinutes(minutes);
    var p = function (n) { return (n < 10 ? '0' : '') + n; };
    return base.getFullYear() + p(base.getMonth() + 1) + p(base.getDate()) + 'T' +
      p(base.getHours()) + p(base.getMinutes()) + '00';
  }
  function icsEscape(v) {
    var B = String.fromCharCode(92);
    return String(v || '')
      .replace(new RegExp('([,;' + B + B + '])', 'g'), B + '$1')
      .replace(/\r?\n/g, B + 'n');
  }
  function downloadIcs() {
    var lines = ['BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//bilete.online//Planificator//RO', 'CALSCALE:GREGORIAN'];
    var stamp = icsTime(0, 0);
    for (var d = 0; d < plan.days; d++) {
      dayView(d).rows.forEach(function (r) {
        if (r.role !== 'stop') return;
        lines.push(
          'BEGIN:VEVENT',
          'UID:' + r.id.replace(':', '-') + '-d' + d + '@bilete.online',
          'DTSTAMP:' + stamp,
          'DTSTART:' + icsTime(d, r.start),
          'DTEND:' + icsTime(d, r.start + r.dur),
          'SUMMARY:' + icsEscape(r.e.name),
          'LOCATION:' + icsEscape([r.e.city, r.e.county].filter(Boolean).join(', ')),
          'GEO:' + r.lat + ';' + r.lng,
          'URL:https://bilete.online' + r.e.href,
          'DESCRIPTION:' + icsEscape((r.e.type ? r.e.type + '. ' : '') + 'Durata e o estimare. https://bilete.online' + r.e.href),
          'END:VEVENT'
        );
      });
    }
    lines.push('END:VCALENDAR');

    var blob = new Blob([lines.join(CRLF)], { type: 'text/calendar;charset=utf-8' });
    var a = el('a');
    a.href = URL.createObjectURL(blob);
    a.download = 'plan-' + fold(plan.where.label).replace(/[^a-z0-9]+/g, '-') + '.ics';
    document.body.appendChild(a);
    a.click();
    setTimeout(function () { URL.revokeObjectURL(a.href); a.remove(); }, 1000);
  }

  /* ---------------------------------------------------------------- the account */

  function token() {
    try { return localStorage.getItem('bileteonline_customer_token') || ''; } catch (e) { return ''; }
  }
  function api(action, options) {
    var t = token();
    if (!t) return Promise.reject(new Error('anonim'));
    options = options || {};
    return fetch('/api/proxy.php?action=' + action + (options.query || ''), {
      method: options.method || 'GET',
      headers: { Accept: 'application/json', 'Content-Type': 'application/json', Authorization: 'Bearer ' + t },
      body: options.body ? JSON.stringify(options.body) : undefined,
      credentials: 'same-origin'
    }).then(function (r) {
      return r.json().then(function (j) {
        if (!r.ok || j.success === false) throw new Error(j.message || ('HTTP ' + r.status));
        return j.data || j;
      });
    });
  }
  function savePlan(b) {
    var label = b.querySelector('span');
    var total = plan.stops.reduce(function (n, day) { return n + day.length; }, 0);
    label.textContent = 'Se salvează…';
    api('customer.plan.save', {
      method: 'POST',
      body: {
        token: plan.token || null,
        title: 'Plan ' + plan.where.label + ' · ' + plan.days + (plan.days === 1 ? ' zi' : ' zile'),
        place: plan.where.label, days: plan.days, stops: total,
        starts_on: plan.from || null, payload: { v: 2, code: encode() }
      }
    }).then(function (d) {
      plan.token = (d.plan || {}).token || plan.token;
      save();
      b.classList.add('is-done');
      label.textContent = 'Salvat în cont';
      setTimeout(function () { b.classList.remove('is-done'); label.textContent = 'Salvează în cont'; }, 2500);
    }).catch(function (err) {
      label.textContent = err.message === 'anonim' ? 'Intră în cont' : 'N-a mers';
      setTimeout(function () { label.textContent = 'Salvează în cont'; }, 2500);
      if (err.message === 'anonim') window.location.href = '/login?redirect=' + encodeURIComponent(location.pathname + location.hash);
    });
  }
  function openSaved(b) {
    var panel = document.getElementById('pl-saved');
    if (panel) { panel.remove(); return; }
    panel = el('div', 'pl-saved');
    panel.id = 'pl-saved';
    panel.appendChild(el('p', 'pl-saved-h', 'Se încarcă…'));
    b.parentNode.parentNode.appendChild(panel);

    api('customer.plans').then(function (d) {
      panel.textContent = '';
      var rows = d.plans || [];
      panel.appendChild(el('p', 'pl-saved-h', rows.length ? 'Planurile tale' : 'N-ai niciun plan salvat încă.'));
      if (!rows.length) return;
      var ul = el('ul', 'pl-saved-list');
      rows.forEach(function (row) {
        var li = el('li');
        var open = el('button', 'pl-saved-open');
        open.type = 'button';
        open.appendChild(el('b', '', row.title));
        open.appendChild(el('small', '', (row.stops || 0) + ' opriri · actualizat ' + String(row.updated_at || '').slice(0, 10)));
        open.addEventListener('click', function () {
          api('customer.plan', { query: '&token=' + encodeURIComponent(row.token) }).then(function (r) {
            var loaded = decode(((r.plan || {}).payload || {}).code || '');
            if (!loaded) return;
            plan = loaded;
            plan.token = row.token;
            activeDay = 0;
            roadCache = {};
            panel.remove();
            render();
          });
        });
        li.appendChild(open);
        var del = el('button', 'pl-ib pl-ib-del');
        del.type = 'button';
        del.title = 'Șterge planul';
        del.appendChild(icon('x'));
        del.appendChild(el('span', 'sr', 'Șterge planul'));
        del.addEventListener('click', function () {
          api('customer.plan.delete', { method: 'DELETE', query: '&token=' + encodeURIComponent(row.token) })
            .then(function () { li.remove(); });
        });
        li.appendChild(del);
        ul.appendChild(li);
      });
      panel.appendChild(ul);
    }).catch(function (err) {
      panel.textContent = '';
      panel.appendChild(el('p', 'pl-saved-h', err.message === 'anonim' ? 'Intră în cont ca să-ți vezi planurile.' : 'Nu am putut încărca planurile.'));
    });
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
    fetchRoads();
  }

  function btn(ic, label, fn, cls) {
    var b = el('button', cls || 'pl-btn');
    b.type = 'button';
    b.appendChild(icon(ic));
    b.appendChild(el('span', '', label));
    b.addEventListener('click', function () { fn(b); });
    return b;
  }

  function renderBar() {
    ui.bar.textContent = '';
    var left = el('div', 'pl-bar-main');
    left.appendChild(el('h2', 'pl-bar-h', (plan.origin ? plan.origin.label + ' → ' : '') + plan.where.label));

    var totals = { stops: 0, km: 0, min: 0, cost: 0, routed: 0 };
    for (var d = 0; d < plan.days; d++) {
      var v = dayView(d);
      totals.stops += v.rows.filter(function (r) { return r.role === 'stop'; }).length;
      totals.km += v.km;
      totals.min += v.visit + v.travel;
      totals.cost += v.cost;
      if (v.routed) totals.routed++;
    }

    var meta = el('p', 'pl-bar-meta');
    meta.appendChild(el('span', '', plan.days + (plan.days === 1 ? ' zi' : ' zile')));
    meta.appendChild(el('span', '', totals.stops + (totals.stops === 1 ? ' oprire' : ' opriri')));
    meta.appendChild(el('span', '', nf(totals.km) + ' km' + (totals.routed === plan.days ? ' pe șosea' : ' (estimat)')));
    meta.appendChild(el('span', '', (CFG.paces[plan.pace] || ['Normal'])[0]));
    if (totals.cost > 0) meta.appendChild(el('span', 'pl-bar-sell', 'de la ' + lei(totals.cost) + ' bilete'));
    left.appendChild(meta);
    ui.bar.appendChild(left);

    var acts = el('div', 'pl-bar-acts');
    acts.appendChild(btn('arrow-right', 'Regenerează', function () { generate(); render(); }));
    acts.appendChild(btn('link', 'Copiază link', function (b) {
      save();
      var done = function () {
        b.classList.add('is-done');
        b.querySelector('span').textContent = 'Copiat';
        setTimeout(function () { b.classList.remove('is-done'); b.querySelector('span').textContent = 'Copiază link'; }, 2000);
      };
      if (navigator.clipboard) navigator.clipboard.writeText(location.href).then(done, done);
      else done();
    }));
    acts.appendChild(btn('calendar-blank', 'Calendar (.ics)', function () { downloadIcs(); }));
    acts.appendChild(btn('printer', 'Tipărește', function () { window.print(); }));
    acts.appendChild(btn('user-circle', 'Salvează în cont', savePlan));
    if (token()) acts.appendChild(btn('list', 'Planurile mele', openSaved));
    acts.appendChild(btn('gear-six', 'Schimbă', function () {
      root.hidden = true;
      startBox.hidden = false;
      startBox.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }));
    ui.bar.appendChild(acts);
  }

  function renderDays() {
    ui.list.textContent = '';
    for (var d = 0; d < plan.days; d++) ui.list.appendChild(dayCard(d));
  }

  function dayCard(d) {
    var view = dayView(d);
    var stops = view.rows.filter(function (r) { return r.role === 'stop'; });
    var box = el('section', 'pl-day' + (d === activeDay ? ' is-active' : ''));
    box.dataset.day = String(d);

    var head = el('header', 'pl-day-head');
    var hl = el('div');
    hl.appendChild(el('h3', 'pl-day-h', dateLabel(plan.from, d)));
    var sub = el('p', 'pl-day-sub');
    sub.textContent = stops.length
      ? stops.length + (stops.length === 1 ? ' oprire · ' : ' opriri · ') + nf(view.km) + ' km' +
        (view.routed ? ' pe șosea' : (view.pending ? ' (se calculează…)' : ' (estimat)')) + ' · ' +
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
    var n = 0;
    view.rows.forEach(function (r) {
      if (r.role === 'stop') {
        ol.appendChild(stopItem(d, n, r, stops.length));
        n++;
      } else {
        ol.appendChild(edgeItem(r));
      }
    });
    box.appendChild(ol);

    var foot = el('div', 'pl-day-foot');
    if (stops.length) {
      var free = dayBudget() - (view.visit + view.travel);
      foot.appendChild(el('p', 'pl-free', free > 20
        ? 'Rămân ' + hm(free) + ' liberi — loc pentru masă, cafea sau ce apare pe drum.'
        : 'Ziua e plină.'));
    }
    foot.appendChild(addControl(d));
    box.appendChild(foot);
    return box;
  }

  /** The departure and the return: not stops, but they are on the road and in the time. */
  function edgeItem(r) {
    var li = el('li', 'pl-edge');
    var dot = el('span', 'pl-edge-dot');
    dot.appendChild(icon(r.role === 'origin' ? 'map-pin' : 'check'));
    li.appendChild(dot);
    var txt = el('div', 'pl-edge-text');
    txt.appendChild(el('b', '', (r.role === 'origin' ? 'Plecare din ' : 'Întoarcere la ') + r.name));
    txt.appendChild(el('span', '', r.role === 'origin'
      ? 'Pornire la ' + clock(r.start)
      : km1(r.legKm) + ' km · ' + hm(r.legMin) + ' · ajungi pe la ' + clock(r.start)));
    li.appendChild(txt);
    return li;
  }

  function stopItem(d, n, r, total) {
    var e = r.e;
    var li = el('li', 'pl-stop');
    var num = el('span', 'pl-stop-num', String(n + 1));
    num.setAttribute('aria-hidden', 'true');
    li.appendChild(num);

    var card = el('div', 'pl-stop-card' + (plan.locked[e.id] ? ' is-locked' : '') + (e.bookable ? ' is-sell' : ''));

    if (r.legKm > 0 || r.legMin > 0) {
      var leg = el('p', 'pl-leg');
      leg.appendChild(icon('arrow-right'));
      leg.appendChild(document.createTextNode(km1(r.legKm) + ' km · ' + hm(r.legMin) + ' de mers'));
      card.appendChild(leg);
    }

    var body = el('div', 'pl-stop-body');
    var media = el('a', 'pl-stop-media');
    media.href = e.href;
    media.target = '_blank';
    media.rel = 'noopener';
    if (e.img) {
      var img = el('img');
      img.src = thumb(e.img, 240, 240);
      img.alt = '';
      img.loading = 'lazy';
      img.decoding = 'async';
      media.appendChild(img);
    } else {
      var ph = el('span', '', e.emoji || '📍');
      ph.setAttribute('aria-hidden', 'true');
      media.appendChild(ph);
    }
    body.appendChild(media);

    var text = el('div', 'pl-stop-text');
    text.appendChild(el('p', 'pl-stop-time', clock(r.start) + '–' + clock(r.start + r.dur)));
    var title = el('a', 'pl-stop-title', e.name);
    title.href = e.href;
    title.target = '_blank';
    title.rel = 'noopener';
    text.appendChild(title);

    var meta = el('p', 'pl-stop-meta');
    meta.appendChild(document.createTextNode([e.type, e.city].filter(Boolean).join(' · ')));
    if (e.approx) meta.appendChild(el('span', 'pl-approx', 'poziție aproximativă'));
    if (e.bookable || e.price > 0) {
      var tag = el('span', 'pl-sell');
      tag.appendChild(icon('ticket'));
      tag.appendChild(document.createTextNode(e.price > 0 ? 'de la ' + lei(e.price) : 'are bilete'));
      meta.appendChild(tag);
    }
    text.appendChild(meta);

    var durWrap = el('label', 'pl-dur');
    durWrap.appendChild(el('span', 'sr', 'Cât stai la ' + e.name));
    var sel = el('select');
    var opts = [20, 30, 45, 60, 90, 120, 180, 240];
    if (opts.indexOf(r.dur) === -1) opts.push(r.dur);
    opts.sort(function (a, b) { return a - b; }).forEach(function (m) {
      var o = el('option', '', hm(m));
      o.value = String(m);
      if (m === r.dur) o.selected = true;
      sel.appendChild(o);
    });
    sel.addEventListener('change', function () {
      plan.custom[e.id] = parseInt(sel.value, 10);
      plan.locked[e.id] = 1;
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
      var h = el('option', '', 'Mută în…');
      h.value = '';
      mv.appendChild(h);
      for (var k = 0; k < plan.days; k++) {
        if (k === d) continue;
        var o3 = el('option', '', 'Ziua ' + (k + 1));
        o3.value = String(k);
        mv.appendChild(o3);
      }
      mv.addEventListener('change', function () {
        if (mv.value !== '') moveToDay(d, n, parseInt(mv.value, 10));
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

  function lockDay(d) { (plan.stops[d] || []).forEach(function (s) { plan.locked[s] = 1; }); }
  function after() { renderBar(); renderDays(); syncMap(); save(); fetchRoads(); }

  function move(d, n, dir) {
    var list = plan.stops[d], to = n + dir;
    if (to < 0 || to >= list.length) return;
    var x = list[n];
    list[n] = list[to];
    list[to] = x;
    lockDay(d);
    after();
  }
  function moveToDay(from, n, to) {
    var id = plan.stops[from].splice(n, 1)[0];
    plan.stops[to].push(id);
    plan.locked[id] = 1;
    lockDay(to);
    activeDay = to;
    after();
  }
  function remove(d, n) {
    var id = plan.stops[d].splice(n, 1)[0];
    plan.removed[id] = 1;
    delete plan.locked[id];
    after();
  }
  function add(d, id) {
    for (var k = 0; k < plan.stops.length; k++) {
      var at = plan.stops[k].indexOf(id);
      if (at !== -1) plan.stops[k].splice(at, 1);
    }
    delete plan.removed[id];
    plan.stops[d].push(id);
    plan.locked[id] = 1;
    activeDay = d;
    after();
  }

  /** Search both catalogues and drop the result straight into this day. */
  function addControl(d) {
    var wrap = el('div', 'pl-add');
    var inp = el('input');
    inp.type = 'search';
    inp.placeholder = 'Adaugă o atracție, o experiență sau o locație…';
    inp.setAttribute('aria-label', 'Caută ceva de adăugat');
    inp.autocomplete = 'off';
    var list = el('ul', 'pl-add-list');
    list.hidden = true;

    var search = function () {
      var q = fold(inp.value.trim());
      list.textContent = '';
      if (q.length < 2) { list.hidden = true; return; }
      var hits = [];
      (CFG.bookables || []).forEach(function (b) {
        if (hits.length < 4 && fold(b[2]).indexOf(q) !== -1) hits.push(['b:' + b[1], b[2], b[3], true]);
      });
      var f = D.f;
      for (var i = 0; i < D.rows.length && hits.length < 8; i++) {
        var name = D.rows[i][f.name];
        if (fold(name).indexOf(q) === -1) continue;
        var c = D.rows[i][f.city] >= 0 ? D.cities[D.rows[i][f.city]][1] : '';
        hits.push([D.rows[i][f.slug], name, c, false]);
      }
      hits.forEach(function (h) {
        var li = el('li');
        var b = el('button', 'pl-add-hit');
        b.type = 'button';
        b.appendChild(el('b', '', h[1]));
        b.appendChild(el('small', '', (h[3] ? 'se rezervă · ' : '') + (h[2] || '')));
        b.addEventListener('click', function () { inp.value = ''; list.hidden = true; add(d, h[0]); });
        li.appendChild(b);
        list.appendChild(li);
      });
      list.hidden = !hits.length;
    };
    inp.addEventListener('input', debounce(search, 140));
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
    var r = road(activeDay);
    var rows = view.rows.filter(function (x) { return x.role === 'stop'; })
      .map(function (x) { return mapRow(x.e, x.legKm, x.legMin); });
    inst.setRoute(rows, r ? r.geometry : '');
    if (ui.note) {
      ui.note.textContent = rows.length
        ? 'Ziua ' + (activeDay + 1) + ': ' + rows.length + (rows.length === 1 ? ' oprire, ' : ' opriri, ') +
          nf(view.km) + ' km ' + (view.routed
            ? 'pe șosea, cu drumul desenat pe hartă.'
            : (view.pending ? '— se calculează drumul…' : '(estimat; drumul nu a putut fi calculat).'))
        : 'Ziua ' + (activeDay + 1) + ' e goală.';
    }
  }

  /* ---------------------------------------------------------------- the start form */

  var form = document.getElementById('pl-form');
  var fields = {
    origin: { input: document.getElementById('pl-from-place'), sugg: document.getElementById('pl-sugg-from'), cities: true, picked: null },
    where:  { input: document.getElementById('pl-where'), sugg: document.getElementById('pl-sugg'), cities: false, picked: null },
    back:   { input: document.getElementById('pl-back'), sugg: document.getElementById('pl-sugg-back'), cities: true, picked: null }
  };
  var ALL_PLACES = null;

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

  function suggest(name) {
    var fld = fields[name];
    if (!fld || !fld.input) return;
    if (!ALL_PLACES) ALL_PLACES = places();
    var q = fold(fld.input.value.trim());
    fld.sugg.textContent = '';
    if (!q) { fld.sugg.hidden = true; return; }

    var pool = fld.cities ? ALL_PLACES.filter(function (p) { return p.kind === 'city'; }) : ALL_PLACES;
    var hits = pool.filter(function (p) { return fold(p.label).indexOf(q) === 0; })
      .concat(pool.filter(function (p) { return fold(p.label).indexOf(q) > 0; }))
      .slice(0, 7);

    hits.forEach(function (p) {
      var li = el('li');
      li.setAttribute('role', 'option');
      var b = el('button', 'pl-sugg-hit');
      b.type = 'button';
      b.appendChild(el('b', '', p.label));
      b.appendChild(el('small', '', p.hint + ' · ' + nf(p.count) + ' atracții'));
      b.addEventListener('click', function () {
        fld.picked = p;
        fld.input.value = p.label;
        fld.sugg.hidden = true;
        fld.input.removeAttribute('aria-invalid');
      });
      li.appendChild(b);
      fld.sugg.appendChild(li);
    });
    if (!hits.length) {
      var li2 = el('li', 'pl-sugg-empty');
      li2.textContent = 'Nu avem încă atracții catalogate acolo. Încearcă alt oraș sau o regiune.';
      fld.sugg.appendChild(li2);
    }
    fld.sugg.hidden = false;
  }

  function resolvePlace(p) {
    if (!p) return null;
    if (p.kind === 'city') {
      var c = cityCentre[p.key];
      return c ? { kind: 'city', key: p.key, label: p.label, lat: +c[0].toFixed(5), lng: +c[1].toFixed(5) } : null;
    }
    var sLat = 0, sLng = 0, n = 0;
    for (var i = 0; i < D.rows.length; i++) {
      if (regionOf[i] !== p.key) continue;
      sLat += D.rows[i][D.f.lat_e5] / 1e5;
      sLng += D.rows[i][D.f.lng_e5] / 1e5;
      n++;
    }
    return n ? { kind: 'region', key: p.key, label: p.label, lat: +(sLat / n).toFixed(5), lng: +(sLng / n).toFixed(5) } : null;
  }

  function resolveField(name) {
    var fld = fields[name];
    if (!fld || !fld.input) return null;
    if (fld.picked) return fld.picked;
    var q = fold(fld.input.value.trim());
    if (!q) return null;
    if (!ALL_PLACES) ALL_PLACES = places();
    var pool = fld.cities ? ALL_PLACES.filter(function (p) { return p.kind === 'city'; }) : ALL_PLACES;
    return pool.filter(function (x) { return fold(x.label) === q; })[0] ||
      pool.filter(function (x) { return fold(x.label).indexOf(q) === 0; })[0] || null;
  }

  function readForm() {
    plan.days = Math.max(1, Math.min(7, parseInt(document.getElementById('pl-days').value, 10) || 2));
    plan.from = document.getElementById('pl-from').value || '';
    plan.interests = [].slice.call(document.querySelectorAll('#pl-interests [aria-pressed="true"]'))
      .map(function (b) { return b.dataset.interest; });
    plan.company = [].slice.call(document.querySelectorAll('#pl-company [aria-pressed="true"]'))
      .map(function (b) { return b.dataset.company; });
    var pace = document.querySelector('#pl-pace [aria-pressed="true"]');
    plan.pace = pace ? pace.dataset.pace : 'normal';
    plan.stops = [];
    for (var i = 0; i < plan.days; i++) plan.stops.push([]);
  }

  /* ---------------------------------------------------------------- wiring */

  Object.keys(fields).forEach(function (name) {
    var fld = fields[name];
    if (!fld.input) return;
    fld.input.addEventListener('input', function () { fld.picked = null; suggest(name); });
    fld.input.addEventListener('focus', function () { suggest(name); });
    document.addEventListener('click', function (e) {
      if (!fld.sugg.contains(e.target) && e.target !== fld.input) fld.sugg.hidden = true;
    });
  });

  ['pl-interests', 'pl-company'].forEach(function (id) {
    var box = document.getElementById(id);
    if (!box) return;
    box.addEventListener('click', function (e) {
      var b = e.target.closest('[data-interest],[data-company]');
      if (!b) return;
      b.setAttribute('aria-pressed', b.getAttribute('aria-pressed') === 'true' ? 'false' : 'true');
    });
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
      if (!ALL_PLACES) ALL_PLACES = places();
      var p = ALL_PLACES.filter(function (x) { return x.kind === 'city' && x.key === b.dataset.startCity; })[0];
      if (!p) return;
      fields.where.picked = p;
      fields.where.input.value = p.label;
      fields.where.sugg.hidden = true;
      if (!fields.origin.input.value.trim()) {
        fields.origin.input.focus();
        fields.origin.input.scrollIntoView({ behavior: 'smooth', block: 'center' });
      } else {
        form.dispatchEvent(new Event('submit', { cancelable: true }));
      }
    });
  });

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    if (!D) return;

    var originPick = resolveField('origin');
    var wherePick = resolveField('where');
    if (!originPick) {
      fields.origin.input.setAttribute('aria-invalid', 'true');
      fields.origin.input.focus();
      return;
    }
    if (!wherePick) {
      fields.where.input.setAttribute('aria-invalid', 'true');
      fields.where.input.focus();
      return;
    }
    fields.origin.input.removeAttribute('aria-invalid');
    fields.where.input.removeAttribute('aria-invalid');

    plan = blankPlan();
    readForm();
    plan.origin = resolvePlace(originPick);
    plan.where = resolvePlace(wherePick);
    plan.back = resolvePlace(resolveField('back'));
    if (!plan.origin || !plan.where) return;

    generate();
    activeDay = 0;
    render();
    root.scrollIntoView({ behavior: 'smooth', block: 'start' });
  });

  /* ---------------------------------------------------------------- boot */

  loadData().then(function () {
    ALL_PLACES = places();
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
