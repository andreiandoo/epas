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
  var PRESETS = CFG.stops || [];                        // your own stops, ready made: label, emoji, minutes
  var MINUTES = CFG.minutes || [15, 30, 45, 60, 90, 120, 180, 240];
  var PARTY = CFG.party || {};                          // how many travel, and how many fit in a room
  var STAY = CFG.stay22 || {};                          // the accommodation embed, minus dates and places
  var WIDE = '(min-width: 1100px)';                     // where the map column becomes a column
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
  /* Three shapes the shared sprite does not carry, drawn on the same 256 grid as the rest. */
  var GLYPH = {
    trash: 'M216,48H176V40a24,24,0,0,0-24-24H104A24,24,0,0,0,80,40v8H40a8,8,0,0,0,0,16h8V208a16,16,0,0,0,16,16H192a16,16,0,0,0,16-16V64h8a8,8,0,0,0,0-16ZM96,40a8,8,0,0,1,8-8h48a8,8,0,0,1,8,8v8H96Zm96,168H64V64H192ZM112,104v64a8,8,0,0,1-16,0V104a8,8,0,0,1,16,0Zm48,0v64a8,8,0,0,1-16,0V104a8,8,0,0,1,16,0Z',
    grip: 'M108,60A16,16,0,1,1,92,44,16,16,0,0,1,108,60Zm56-16a16,16,0,1,0,16,16A16,16,0,0,0,164,44ZM92,112a16,16,0,1,0,16,16A16,16,0,0,0,92,112Zm72,0a16,16,0,1,0,16,16A16,16,0,0,0,164,112ZM92,180a16,16,0,1,0,16,16A16,16,0,0,0,92,180Zm72,0a16,16,0,1,0,16,16A16,16,0,0,0,164,180Z',
    dots: 'M128,96a16,16,0,1,0-16-16A16,16,0,0,0,128,96Zm0,16a16,16,0,1,0,16,16A16,16,0,0,0,128,112Zm0,64a16,16,0,1,0,16,16A16,16,0,0,0,128,176Z'
  };
  function glyph(name, cls) {
    var s = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
    var p = document.createElementNS('http://www.w3.org/2000/svg', 'path');
    s.setAttribute('class', cls || 'ic');
    s.setAttribute('viewBox', '0 0 256 256');
    s.setAttribute('aria-hidden', 'true');
    p.setAttribute('fill', 'currentColor');
    p.setAttribute('d', GLYPH[name]);
    s.appendChild(p);
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
  var cityCount = {};   // how much of the catalogue a city holds — a proxy for "a town you can sleep in"
  var cityCounty = {};
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
        cityCount[s] = sums[s][2];
      });
      D.cities.forEach(function (c) { if (c && c[0]) cityCounty[c[0]] = c[2] || ''; });

      // [kind, slug, title, city, citySlug, county, lat, lng, approx, priceCents, minutes, img, category]
      (CFG.bookables || []).forEach(function (b) { BOOK[b[1]] = b; });
      return D;
    });
  }

  /**
   * One uniform stop, whatever it is underneath. 'b:' marks something bookable (an experience or a
   * leisure location) so the rest of the planner never has to care which catalogue it came from.
   */
  /* -------- stops of your own --------
   *
   * A stop the traveller writes themselves — a meal, a coffee, a break, a hotel check-in — lives in
   * plan.extra under an id that starts with 'x:', so entry() can tell it from a catalogue slug and
   * the rest of the planner never has to care. Three ways to place it:
   *
   *   at:'prev'  it borrows the place of whatever it follows: time in the day, no driving;
   *   at:'none'  no place at all: time in the day, nothing on the map, nothing on the road;
   *   at:'fix'   a real point picked from the catalogue, with lat/lng — this one does move the road.
   *
   * Only 'fix' is a waypoint. The other two are skipped by the router, by the map and by the leg
   * arithmetic, and keep nothing but their minutes.
   */
  var OWNSEQ = 0;
  function isOwn(id) { return typeof id === 'string' && id.indexOf('x:') === 0; }
  function ownId() {
    var id;
    do { OWNSEQ++; id = 'x:' + Date.now().toString(36) + OWNSEQ.toString(36); } while (plan.extra[id]);
    return id;
  }
  function ownEntry(id) {
    var x = plan && plan.extra ? plan.extra[id] : null;
    if (!x) return null;
    var fixed = x.at === 'fix' && typeof x.lat === 'number' && typeof x.lng === 'number';
    return {
      id: id, kind: 'own', slug: id, name: x.name || 'Oprire', city: '', citySlug: '', county: '',
      lat: fixed ? x.lat : null, lng: fixed ? x.lng : null, approx: false, price: 0,
      dur: x.minutes || 30, img: '', type: '', emoji: x.emoji || '🕑', typeSlug: '',
      bookable: false, href: '', own: true, at: x.at || 'none', place: x.place || ''
    };
  }
  function ownMeta(e) {
    if (e.at === 'fix') return 'Oprirea ta · ' + (e.place || 'alt loc');
    if (e.at === 'prev') return 'Oprirea ta · la oprirea dinainte';
    return 'Oprirea ta · fără loc anume';
  }

  function entry(id) {
    if (isOwn(id)) return ownEntry(id);
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
      interests: [], company: [], pace: 'normal', party: partyDefault(),
      stops: [], locked: {}, removed: {}, custom: {}, extra: {}, nights: {}, token: ''
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

  /** Your own stops, dropped back behind the stop each one was following. */
  function putBack(list, own) {
    if (!own.length) return list;
    var out = list.slice();
    own.forEach(function (o) {
      var at = o.after ? out.length : 0;      // the stop it followed is gone: it waits at the end
      if (o.after) {
        for (var i = 0; i < out.length; i++) {
          if (out[i].id === o.after) { at = i + 1; break; }
        }
      }
      while (at < out.length && isOwn(out[at].id)) at++;       // keep two of them in the order given
      out.splice(at, 0, { id: o.id, lat: null, lng: null, type: '', dur: o.dur, score: 99 });
    });
    return out;
  }

  function generate() {
    var budget = dayBudget() * FILL_TO;
    var pool = candidates();
    var used = {};
    var days = [];

    var mine = [];    // per day: the stops you wrote yourself, and what each of them followed

    for (var d = 0; d < plan.days; d++) {
      var keep = [], own = [], behind = null;
      (plan.stops[d] || []).forEach(function (id) {
        if (isOwn(id)) {
          // Never thrown away by a regeneration, and it comes back behind whatever it was following
          // — whether or not that stop survived the shuffle.
          var x = entry(id);
          if (x) own.push({ id: id, after: behind, dur: x.dur });
          return;
        }
        behind = id;
        if (!plan.locked[id] || used[id]) return;
        var e = entry(id);
        if (!e) return;
        used[id] = 1;
        keep.push({ id: id, lat: e.lat, lng: e.lng, type: e.typeSlug || '', dur: e.dur, score: 99 });
      });
      days.push(keep);
      mine.push(own);
    }
    pool = pool.filter(function (c) { return !used[c.id]; });

    // Day one leaves from the departure point, so the first hop is a real hop.
    var anchor = plan.origin ? [plan.origin.lat, plan.origin.lng] : null;

    for (var day = 0; day < plan.days; day++) {
      var list = days[day];
      var spent = 0;
      mine[day].forEach(function (o) { spent += o.dur; });     // your own stops eat into the day too
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

      days[day] = putBack(orderDay(list), mine[day]);
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
    var last = pts.length ? pts[0] : null;      // the last point that is really on the road
    (plan.stops[dayIndex] || []).forEach(function (id, pos) {
      var e = entry(id);
      if (!e) return;
      if (e.own && e.lat === null) {
        // Time, not a waypoint: 'prev' borrows the place it follows so the calendar still has a
        // location, 'none' has none at all. Neither adds a leg and neither goes to the router.
        var borrow = e.at === 'prev' ? last : null;
        pts.push({
          role: 'stop', entry: e, pos: pos, off: true,
          lat: borrow ? borrow.lat : null, lng: borrow ? borrow.lng : null
        });
        return;
      }
      pts.push({ role: 'stop', entry: e, pos: pos, lat: e.lat, lng: e.lng });
      last = pts[pts.length - 1];
    });
    if (dayIndex === plan.days - 1) {
      var end = plan.back || plan.origin;
      if (end) pts.push({ role: 'back', name: end.label, lat: end.lat, lng: end.lng });
    }
    return pts;
  }
  /** Only what is driven: a stop without a place of its own is time, not a waypoint. */
  function roadPoints(dayIndex) {
    return dayPoints(dayIndex).filter(function (p) { return !p.off && typeof p.lat === 'number'; });
  }
  function dayKey(dayIndex) {
    return roadPoints(dayIndex).map(function (p) { return p.lat.toFixed(5) + ',' + p.lng.toFixed(5); }).join(';');
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
            refresh();
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
    var prev = null, leg = -1;      // the last point on the road, and its index in the routed legs

    for (var n = 0; n < pts.length; n++) {
      var p = pts[n];
      var legKm = 0, legMin = 0;
      if (!p.off) {
        leg++;
        if (leg > 0 && prev) {
          if (r && r.legs && r.legs[leg]) {
            legKm = r.legs[leg][0];
            legMin = r.legs[leg][1];
          } else {
            var raw = km(prev.lat, prev.lng, p.lat, p.lng);
            legKm = estKm(raw);
            legMin = estMin(raw);
          }
          totalKm += legKm;
          travel += legMin;
          t += legMin;
        }
        prev = p;
      }
      if (p.role !== 'stop') {
        rows.push({ role: p.role, name: p.name, lat: p.lat, lng: p.lng, start: t, dur: 0, legKm: legKm, legMin: legMin });
        continue;
      }
      var e = p.entry;
      var dur = plan.custom[e.id] || e.dur;
      rows.push({
        role: 'stop', e: e, id: e.id, pos: p.pos, off: !!p.off,
        lat: p.lat, lng: p.lng, start: t, dur: dur, legKm: legKm, legMin: legMin
      });
      visit += dur;
      cost += e.price || 0;
      t += dur;
    }
    return { rows: rows, km: totalKm, visit: visit, travel: travel, end: t, cost: cost, routed: !!r, pending: roadPending(dayIndex) };
  }

  /* ---------------------------------------------------------------- the nights
   *
   * A plan of n days has n-1 nights: on the last day you drive home, so nothing is offered for it.
   * A night is not a stop — it sits between two day cards and carries its own suggestion: the town
   * that keeps the evening short without making tomorrow morning long.
   *
   * The town is computed, never stored, unless the traveller picks another one or says they are not
   * sleeping there; only that difference rides in the link. Everything else — the dates, how many of
   * you there are, how many rooms — falls out of the plan itself.
   */

  function partyDefault() {
    return { adults: PARTY.adults || 2, children: PARTY.children || 0 };
  }
  function clampInt(v, lo, hi, dflt) {
    v = parseInt(v, 10);
    if (isNaN(v)) return dflt;
    return Math.max(lo, Math.min(hi, v));
  }
  function party() {
    var p = (plan && plan.party) || {};
    return {
      adults: clampInt(p.adults, 1, PARTY.adults_max || 12, PARTY.adults || 2),
      children: clampInt(p.children, 0, PARTY.children_max || 10, PARTY.children || 0)
    };
  }
  /** Two adults to a room, children with them: a starting point, not a rule. */
  function defaultRooms() {
    return Math.max(1, Math.min(PARTY.rooms_max || 8, Math.ceil(party().adults / (PARTY.per_room || 2))));
  }
  function partyLine(rooms) {
    var p = party();
    var out = p.adults + (p.adults === 1 ? ' adult' : ' adulți');
    if (p.children > 0) out += ' · ' + p.children + (p.children === 1 ? ' copil' : ' copii');
    return out + ' · ' + rooms + (rooms === 1 ? ' cameră' : ' camere');
  }
  function nightCount() { return Math.max(0, plan.days - 1); }

  /** The last place of a day that is really on the map, and the first one of the next. */
  function lastPlaced(d) {
    var ids = plan.stops[d] || [];
    for (var k = ids.length - 1; k >= 0; k--) {
      var e = entry(ids[k]);
      if (e && typeof e.lat === 'number') return { lat: e.lat, lng: e.lng, name: e.name };
    }
    return null;
  }
  function firstPlaced(d) {
    var ids = plan.stops[d] || [];
    for (var k = 0; k < ids.length; k++) {
      var e = entry(ids[k]);
      if (e && typeof e.lat === 'number') return { lat: e.lat, lng: e.lng, name: e.name };
    }
    return null;
  }
  function nightAnchors(i) {
    var a = lastPlaced(i), b = firstPlaced(i + 1);
    var centre = a || b || (plan.where ? { lat: plan.where.lat, lng: plan.where.lng, name: plan.where.label } : null);
    return { a: a, b: b, centre: centre };
  }

  /**
   * The town we suggest: a short drive tonight, a shorter one tomorrow morning, and big enough to
   * have somewhere to sleep. Tomorrow weighs a little more than tonight — an hour before breakfast
   * costs more than an hour after dinner. How much of the catalogue a town holds is the only thing
   * we know about it, so it stands in for "there are beds here"; the traveller can name another.
   */
  function nightBest(i) {
    if (!D || !plan.where) return null;
    var an = nightAnchors(i);
    var from = an.a || an.centre;
    if (!from) return null;
    // First the driving: from tonight's last stop to the town, and from the town to tomorrow's first stop (the
    // morning weighs a little more, because that is the drive you make before coffee).
    var towns = [], bestDrive = Infinity;
    for (var k = 0; k < D.cities.length; k++) {
      var c = D.cities[k];
      if (!c || !c[0] || !cityCentre[c[0]]) continue;
      var lat = cityCentre[c[0]][0], lng = cityCentre[c[0]][1];
      var drive = km(from.lat, from.lng, lat, lng);
      if (an.b) drive += 1.35 * km(lat, lng, an.b.lat, an.b.lng);
      towns.push({ slug: c[0], name: c[1], county: c[2] || '', lat: lat, lng: lng, drive: drive, size: cityCount[c[0]] || 0 });
      if (drive < bestDrive) bestDrive = drive;
    }
    if (!towns.length) return null;
    // Then, among the towns that cost about the same to reach, the one you are most likely to find a bed in. The
    // catalogue counts attractions, not hotels, but a village with three of them is rarely where you sleep.
    var cut = bestDrive + 18, best = null;
    for (var j = 0; j < towns.length; j++) {
      var t = towns[j];
      if (t.drive > cut) continue;
      if (!best || t.size > best.size || (t.size === best.size && t.drive < best.drive)) best = t;
    }
    return best;
  }

  /**
   * A night as it stands: the computed suggestion, with whatever the traveller said on top of it.
   * Null when the catalogue has no town to offer at all.
   */
  function nightOf(i) {
    if (i < 0 || i >= nightCount()) return null;
    var ov = (plan.nights && plan.nights[i]) || {};
    var c = (ov.city && typeof ov.lat === 'number')
      ? { slug: ov.city, name: ov.name || ov.city, county: ov.county || '', lat: ov.lat, lng: ov.lng }
      : nightBest(i);
    if (!c) return null;
    var an = nightAnchors(i);
    var kA = an.a ? estKm(km(an.a.lat, an.a.lng, c.lat, c.lng)) : -1;
    var kB = an.b ? estKm(km(c.lat, c.lng, an.b.lat, an.b.lng)) : -1;
    return {
      i: i, city: c.slug, name: c.name, county: c.county, lat: c.lat, lng: c.lng,
      kA: kA, kB: kB, toName: an.b ? an.b.name : '', hasFrom: !!an.a,
      far: kA > 60 || kB > 60, chosen: !!ov.city, skip: !!ov.skip,
      rooms: clampInt(ov.rooms, 1, PARTY.rooms_max || 8, defaultRooms()),
      maxprice: clampInt(ov.maxprice, 0, 100000, 0)
    };
  }
  /** Writes only what the traveller actually decided; a key put back to its default disappears. */
  function setNight(i, patch) {
    if (!plan.nights) plan.nights = {};
    var cur = plan.nights[i] || {};
    Object.keys(patch).forEach(function (k) {
      var v = patch[k];
      if (v === null || v === false || v === undefined || v === '') delete cur[k];
      else cur[k] = v;
    });
    if (Object.keys(cur).length) plan.nights[i] = cur;
    else delete plan.nights[i];
  }

  /** The plan's start date shifted by so many days, or null when no date was given. */
  function dateOffset(offset) {
    if (!plan.from) return null;
    var d = new Date(plan.from + 'T12:00:00');
    if (isNaN(d)) return null;
    d.setDate(d.getDate() + offset);
    return d;
  }
  function isoOffset(offset) {
    var d = dateOffset(offset);
    if (!d) {
      d = new Date();
      d.setHours(12, 0, 0, 0);
      d.setDate(d.getDate() + offset);
    }
    var p = function (n) { return (n < 10 ? '0' : '') + n; };
    return d.getFullYear() + '-' + p(d.getMonth() + 1) + '-' + p(d.getDate());
  }
  function nightTitle(i) {
    var d = dateOffset(i);
    return d
      ? 'Noaptea de ' + d.toLocaleDateString('ro-RO', { weekday: 'long', day: 'numeric', month: 'short' })
      : 'Noaptea dintre ziua ' + (i + 1) + ' și ziua ' + (i + 2);
  }

  /**
   * The Stay22 map for one night. Everything fixed about it — the affiliate id, our green, the
   * currency — comes from PLAN_STAY22 in includes/v2/plan-config.php, so nothing about the account
   * lives in this file. Built only when asked: the embed is third party and never loads on its own.
   */
  function stayAddress(n) {
    return n.name + (n.county ? ', ' + n.county : '') + ', România';
  }
  function stayUrl(i) {
    var n = nightOf(i);
    if (!n) return '';
    var p = party();
    var q = [
      'aid=' + encodeURIComponent(STAY.aid || ''),
      'lat=' + (+n.lat).toFixed(5),
      'lng=' + (+n.lng).toFixed(5),
      'address=' + encodeURIComponent(stayAddress(n)),
      'checkin=' + isoOffset(i),
      'checkout=' + isoOffset(i + 1),
      'adults=' + p.adults
    ];
    if (p.children > 0) q.push('children=' + p.children);
    q.push('rooms=' + n.rooms);
    if (n.maxprice > 0) q.push('maxprice=' + n.maxprice);
    q.push('currency=' + encodeURIComponent(STAY.currency || 'RON'));
    q.push('maincolor=' + encodeURIComponent(STAY.maincolor || '1E5B48'));
    q.push('markertype=' + encodeURIComponent(STAY.markertype || 'circle'));
    q.push('zoom=' + (STAY.zoom || 12));
    return (STAY.embed || 'https://www.stay22.com/embed/gm') + '?' + q.join('&');
  }
  /** The same night as a plain page on Stay22, for when the iframe does not come up. */
  function stayLink(i) {
    var n = nightOf(i);
    if (!n) return '';
    var p = party();
    var q = [
      'aid=' + encodeURIComponent(STAY.aid || ''),
      'address=' + encodeURIComponent(stayAddress(n)),
      'checkin=' + isoOffset(i),
      'checkout=' + isoOffset(i + 1),
      'adults=' + p.adults
    ];
    if (p.children > 0) q.push('children=' + p.children);
    q.push('rooms=' + n.rooms);
    if (n.maxprice > 0) q.push('maxprice=' + n.maxprice);
    q.push('currency=' + encodeURIComponent(STAY.currency || 'RON'));
    return (STAY.link || 'https://www.stay22.com/allez/booking') + '?' + q.join('&');
  }

  /* ---------------------------------------------------------------- storage + url */

  /**
   * The nights in the link: only what the traveller decided differently from what we computed, so a
   * plan nobody argued with carries nothing at all. k = not sleeping here, c/a/g/m/u = another town,
   * r = another number of rooms.
   */
  function nightsCompact() {
    var out = {}, any = false;
    for (var i = 0; i < nightCount(); i++) {
      var ov = (plan.nights || {})[i];
      if (!ov) continue;
      var o = {};
      if (ov.skip) o.k = 1;
      if (ov.city && typeof ov.lat === 'number') {
        var def = nightBest(i);
        if (!def || def.slug !== ov.city) {
          o.c = ov.city;
          o.a = ov.lat;
          o.g = ov.lng;
          o.m = ov.name || ov.city;
          if (ov.county) o.u = ov.county;
        }
      }
      if (ov.rooms && ov.rooms !== defaultRooms()) o.r = ov.rooms;
      if (ov.maxprice > 0) o.p = ov.maxprice;
      if (Object.keys(o).length) { out[i] = o; any = true; }
    }
    return any ? out : null;
  }
  function nightsExpand(raw) {
    var out = {};
    if (!raw || typeof raw !== 'object') return out;
    Object.keys(raw).forEach(function (k) {
      var v = raw[k] || {}, o = {}, i = parseInt(k, 10);
      if (isNaN(i) || i < 0) return;
      if (v.k) o.skip = true;
      if (v.c && typeof v.a === 'number' && typeof v.g === 'number') {
        o.city = String(v.c);
        o.lat = v.a;
        o.lng = v.g;
        o.name = String(v.m || v.c);
        o.county = v.u ? String(v.u) : '';
      }
      if (v.r) o.rooms = clampInt(v.r, 1, PARTY.rooms_max || 8, defaultRooms());
      if (v.p) o.maxprice = clampInt(v.p, 0, 100000, 0);
      if (Object.keys(o).length) out[i] = o;
    });
    return out;
  }

  function encode() {
    var compact = {
      o: plan.origin, w: plan.where, b: plan.back, d: plan.days, f: plan.from,
      i: plan.interests, g: plan.company, p: plan.pace, s: plan.stops,
      l: Object.keys(plan.locked), r: Object.keys(plan.removed), c: plan.custom, t: plan.token || ''
    };
    // Only when there is something to say: a plan without stops of your own stays as short as it was.
    if (plan.extra && Object.keys(plan.extra).length) compact.x = plan.extra;
    var pty = party(), dft = partyDefault();
    if (pty.adults !== dft.adults || pty.children !== dft.children) compact.y = [pty.adults, pty.children];
    var nts = nightsCompact();
    if (nts) compact.n = nts;
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
      // Links and saved plans made before stops of your own existed simply have none of them.
      p.extra = (o.x && typeof o.x === 'object') ? o.x : {};
      // Same for the nights and for who travels: an older link says nothing, so it gets the defaults.
      p.party = {
        adults: clampInt(o.y && o.y[0], 1, PARTY.adults_max || 12, PARTY.adults || 2),
        children: clampInt(o.y && o.y[1], 0, PARTY.children_max || 10, PARTY.children || 0)
      };
      p.nights = nightsExpand(o.n);
      (o.l || []).forEach(function (s) { p.locked[s] = 1; });
      (o.r || []).forEach(function (s) { p.removed[s] = 1; });
      p.stops = p.stops.map(function (day) {
        return (day || []).filter(function (id) { return !isOwn(id) || p.extra[id]; });
      });
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
  /** A date with no hour behind it: what an all-day entry wants. */
  function icsDate(dayIndex) {
    var base = plan.from ? new Date(plan.from + 'T00:00:00') : new Date();
    if (isNaN(base)) base = new Date();
    base.setHours(0, 0, 0, 0);
    base.setDate(base.getDate() + dayIndex);
    var p = function (n) { return (n < 10 ? '0' : '') + n; };
    return base.getFullYear() + p(base.getMonth() + 1) + p(base.getDate());
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
        var e = r.e;
        var where = [e.city, e.county].filter(Boolean).join(', ');
        lines.push(
          'BEGIN:VEVENT',
          'UID:' + r.id.replace(':', '-') + '-d' + d + '@bilete.online',
          'DTSTAMP:' + stamp,
          'DTSTART:' + icsTime(d, r.start),
          'DTEND:' + icsTime(d, r.start + r.dur),
          'SUMMARY:' + icsEscape(e.own && e.emoji ? e.emoji + ' ' + e.name : e.name)
        );
        // A stop of your own may have no place at all: it keeps its hour and loses the geography.
        if (where) lines.push('LOCATION:' + icsEscape(where));
        if (typeof r.lat === 'number' && typeof r.lng === 'number') lines.push('GEO:' + r.lat + ';' + r.lng);
        if (e.href) lines.push('URL:https://bilete.online' + e.href);
        lines.push('DESCRIPTION:' + icsEscape(e.own
          ? ownMeta(e) + '. Durata e cea pusă de tine.'
          : (e.type ? e.type + '. ' : '') + 'Durata e o estimare. https://bilete.online' + e.href));
        lines.push('END:VEVENT');
      });
    }
    // One all-day entry per night, with the town — the last day has none: you drive home.
    for (var nd = 0; nd < nightCount(); nd++) {
      var nt = nightOf(nd);
      if (!nt || nt.skip) continue;
      lines.push(
        'BEGIN:VEVENT',
        'UID:noapte-' + nd + '-' + nt.city + '@bilete.online',
        'DTSTAMP:' + stamp,
        'DTSTART;VALUE=DATE:' + icsDate(nd),
        'DTEND;VALUE=DATE:' + icsDate(nd + 1),
        'SUMMARY:' + icsEscape('🌙 Cazare în ' + nt.name),
        'LOCATION:' + icsEscape([nt.name, nt.county].filter(Boolean).join(', ')),
        'GEO:' + (+nt.lat).toFixed(5) + ';' + (+nt.lng).toFixed(5),
        'DESCRIPTION:' + icsEscape('Noaptea propusă de planificator: ' + partyLine(nt.rooms) +
          '. Lista de cazări se deschide din plan, pe https://bilete.online/plan'),
        'END:VEVENT'
      );
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

  /**
   * The day handed to Google Maps: departure, what is driven, arrival. Stops of your own that are
   * pure time have no coordinates and no business in a driving link, so they are left out; their
   * minutes stay in the plan on this page.
   */
  function gmapsUrl(dayIndex) {
    var pts = roadPoints(dayIndex);
    if (pts.length < 2) return '';
    var ll = function (p) { return p.lat + ',' + p.lng; };
    var mid = pts.slice(1, -1);
    if (mid.length > 8) mid = mid.slice(0, 8);      // the URL API takes nine waypoints, no more
    return 'https://www.google.com/maps/dir/?api=1&travelmode=driving' +
      '&origin=' + encodeURIComponent(ll(pts[0])) +
      '&destination=' + encodeURIComponent(ll(pts[pts.length - 1])) +
      (mid.length ? '&waypoints=' + encodeURIComponent(mid.map(ll).join('|')) : '');
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
    note: document.getElementById('pl-map-note'),
    live: document.getElementById('pl-live'),
    tabRoute: document.getElementById('pl-tab-route'),
    tabStay: document.getElementById('pl-tab-stay'),
    paneRoute: document.getElementById('pl-pane-route'),
    paneStay: document.getElementById('pl-pane-stay')
  };
  var activeDay = 0;
  var mapTab = 'route';      // which of the two views the map column is showing
  var stayNight = -1;        // the night the accommodation view is on, -1 for none yet
  var sheet = null;          // the full-height sheet, on a phone
  var focusId = null;        // whose handle to put the focus back on after the next render

  /** Says out loud what just moved, for whoever is not looking at the screen. */
  function announce(msg) { if (ui.live) ui.live.textContent = msg; }

  /**
   * A road answer arriving is the one redraw nobody asked for, so it waits while a menu or the
   * little form is open rather than pulling them out from under the reader.
   */
  var stale = false;
  function refresh() {
    if (menu || composer) { stale = true; return; }
    stale = false;
    renderBar();
    renderDays();
    syncMap();
    syncStay();
  }

  function render() {
    composer = null;          // a fresh plan never opens with half a form on the screen
    stale = false;
    startBox.hidden = true;
    root.hidden = false;
    closeSheet();
    stayNight = -1;
    if (ui.paneStay) { ui.paneStay.textContent = ''; ui.paneStay.dataset.stayUrl = ''; }
    showTab('route');          // a fresh plan opens on its own map, not on somebody else's night
    renderBar();
    renderDays();
    syncMap();
    syncStay();
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
    // the band runs the width of the page; its contents keep the page's own column
    var bar = el('div', 'wrap pl-bar-in');
    ui.bar.appendChild(bar);
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

    // One line for the nights, and a way into the first one nobody has looked at yet.
    if (nightCount() > 0) {
      var towns = [], seenTown = {}, slept = 0;
      for (var ni = 0; ni < nightCount(); ni++) {
        var nn = nightOf(ni);
        if (!nn || nn.skip) continue;
        slept++;
        if (seenTown[nn.name]) continue;
        seenTown[nn.name] = 1;
        towns.push(nn.name);
      }
      var nb = el('button', 'pl-bar-nights');
      nb.type = 'button';
      var nem = el('span', '', '🌙');
      nem.setAttribute('aria-hidden', 'true');
      nb.appendChild(nem);
      nb.appendChild(document.createTextNode(slept
        ? slept + (slept === 1 ? ' noapte' : ' nopți') + ' · ' + towns.join(', ')
        : 'Nicio noapte pe traseu'));
      nb.addEventListener('click', jumpToNight);
      left.appendChild(nb);
    }
    bar.appendChild(left);

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
    bar.appendChild(acts);
  }

  function renderDays() {
    // Rendering throws the old nodes away, so a stop held with the keyboard has to be handed back
    // its handle — and so does one whose handle simply had the focus when the router answered.
    var held = document.activeElement;
    var heldId = held && held.classList && held.classList.contains('pl-grip') && held.closest('.pl-stop')
      ? held.closest('.pl-stop').dataset.id : null;

    ui.list.textContent = '';
    for (var d = 0; d < plan.days; d++) {
      ui.list.appendChild(dayCard(d));
      // No night after the last day: that is the day you drive home.
      if (d < plan.days - 1) ui.list.appendChild(nightCard(d));
    }

    var want = grab ? grab.id : (focusId || heldId);
    focusId = null;
    if (want) {
      var li = ui.list.querySelector('.pl-stop[data-id="' + want + '"]');
      if (li) {
        if (grab) li.classList.add('is-grabbed');
        var g = li.querySelector('.pl-grip');
        if (g) g.focus();
        li.scrollIntoView({ block: 'nearest' });
      } else if (grab) {
        grab = null;
      }
    }
    if (composer && composer.focus) {
      composer.focus = false;
      var f = ui.list.querySelector('.pl-new input');
      if (f) { f.focus(); f.select(); }
      var nb = ui.list.querySelector('.pl-new');
      if (nb) nb.scrollIntoView({ block: 'nearest' });
    }
  }

  function dayCard(d) {
    var view = dayView(d);
    var stops = view.rows.filter(function (r) { return r.role === 'stop'; });
    var box = el('section', 'pl-day' + (d === activeDay ? ' is-active' : ''));
    box.dataset.day = String(d);

    var head = el('header', 'pl-day-head');
    var hl = el('div', 'pl-day-headings');
    hl.appendChild(el('h3', 'pl-day-h', dateLabel(plan.from, d)));
    var sub = el('p', 'pl-day-sub');
    sub.textContent = stops.length
      ? stops.length + (stops.length === 1 ? ' oprire · ' : ' opriri · ') + nf(view.km) + ' km' +
        (view.routed ? ' pe șosea' : (view.pending ? ' (se calculează…)' : ' (estimat)')) + ' · ' +
        clock(DAY_START) + '–' + clock(view.end) + ' · ' + hm(view.travel) + ' pe drum'
      : 'Zi liberă — adaugă ceva sau regenerează.';
    hl.appendChild(sub);
    if (d === 0 && stops.length > 1) {
      hl.appendChild(el('p', 'pl-day-tip',
        'Trage de bulina din stânga ca să muți o oprire, în zi sau în altă zi. Cu tastatura: Enter pe bulină, apoi săgețile.'));
    }
    head.appendChild(hl);

    var acts = el('div', 'pl-day-acts');
    var show = el('button', 'pl-day-act pl-day-show');
    show.type = 'button';
    show.appendChild(icon('map-pin'));
    show.appendChild(el('span', '', d === activeDay ? 'Pe hartă' : 'Vezi pe hartă'));
    show.addEventListener('click', function () { activeDay = d; renderDays(); syncMap(); });
    acts.appendChild(show);

    var mk = el('button', 'pl-day-act');
    mk.type = 'button';
    mk.appendChild(icon('plus'));
    mk.appendChild(el('span', '', 'Oprire de-a ta'));
    mk.addEventListener('click', function () { openComposer(d, (plan.stops[d] || []).length); });
    acts.appendChild(mk);

    var gm = gmapsUrl(d);
    if (gm) {
      var ga = el('a', 'pl-day-act');
      ga.href = gm;
      ga.target = '_blank';
      ga.rel = 'noopener';
      ga.appendChild(icon('map-pin'));
      ga.appendChild(el('span', '', 'Google Maps'));
      acts.appendChild(ga);
    }
    head.appendChild(acts);
    box.appendChild(head);

    var ol = el('ol', 'pl-stops');
    var n = 0;
    view.rows.forEach(function (r) {
      if (r.role === 'stop') {
        if (!r.e.own) n++;                       // your own stops take time, not a number on the map
        ol.appendChild(insertSlot(d, r.pos));    // the gap above this stop can take a new one
        ol.appendChild(stopItem(d, r, n));
      } else {
        ol.appendChild(edgeItem(r));
      }
    });
    if (stops.length) ol.appendChild(insertSlot(d, stops.length));
    if (composer && composer.day === d) {
      var at = ol.querySelector('.pl-stop[data-pos="' + composer.pos + '"]');
      ol.insertBefore(composerItem(), at || null);
    }
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

  function stopItem(d, r, n) {
    var e = r.e;
    var li = el('li', 'pl-stop' + (e.own ? ' is-own' : ''));
    li.dataset.day = String(d);
    li.dataset.pos = String(r.pos);
    li.dataset.id = e.id;
    li.appendChild(gripFor(d, r, n));

    var card = el('div', 'pl-stop-card' + (plan.locked[e.id] ? ' is-locked' : '') + (e.bookable ? ' is-sell' : ''));

    // One row across the top of the card: the drive on the left, what you can do to the stop on the
    // right. The bin sits in the corner without having to be lifted out of the flow.
    var top = el('div', 'pl-stop-top');
    if (r.legKm > 0 || r.legMin > 0) {
      var leg = el('p', 'pl-leg');
      leg.appendChild(icon('arrow-right'));
      leg.appendChild(document.createTextNode(km1(r.legKm) + ' km · ' + hm(r.legMin) + ' de mers'));
      top.appendChild(leg);
    } else if (r.off) {
      top.appendChild(el('p', 'pl-leg pl-leg-off', 'fără drum în plus'));
    } else {
      top.appendChild(el('span', 'pl-leg-none'));
    }
    top.appendChild(toolsFor(d, r));
    card.appendChild(top);

    var body = el('div', 'pl-stop-body');
    var media = el(e.href ? 'a' : 'div', 'pl-stop-media');
    if (e.href) {
      media.href = e.href;
      media.target = '_blank';
      media.rel = 'noopener';
    }
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
    var title;
    if (e.href) {
      title = el('a', 'pl-stop-title', e.name);
      title.href = e.href;
      title.target = '_blank';
      title.rel = 'noopener';
    } else {
      title = el('p', 'pl-stop-title', e.name);
    }
    text.appendChild(title);

    var meta = el('p', 'pl-stop-meta');
    meta.appendChild(document.createTextNode(e.own ? ownMeta(e) : [e.type, e.city].filter(Boolean).join(' · ')));
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
    var opts = MINUTES.slice();
    if (opts.indexOf(r.dur) === -1) opts.push(r.dur);
    opts.sort(function (a, b) { return a - b; }).forEach(function (m) {
      var o = el('option', '', hm(m));
      o.value = String(m);
      if (m === r.dur) o.selected = true;
      sel.appendChild(o);
    });
    sel.addEventListener('change', function () {
      var m = parseInt(sel.value, 10) || 30;
      if (e.own && plan.extra[e.id]) plan.extra[e.id].minutes = m;
      else plan.custom[e.id] = m;
      plan.locked[e.id] = 1;
      after();
    });
    durWrap.appendChild(sel);
    durWrap.appendChild(el('small', '', e.own ? 'cât zici tu' : 'estimat'));
    text.appendChild(durWrap);
    body.appendChild(text);
    card.appendChild(body);

    li.appendChild(card);
    return li;
  }

  /** The number is the handle: drag it, or take the stop with Enter and move it with the arrows. */
  function gripFor(d, r, n) {
    var e = r.e;
    var b = el('button', 'pl-grip');
    b.type = 'button';
    b.title = 'Trage ca să muți oprirea';
    var face = el('span', 'pl-grip-n', e.own ? (e.emoji || '🕑') : String(n));
    face.setAttribute('aria-hidden', 'true');
    b.appendChild(face);
    b.appendChild(glyph('grip', 'ic pl-grip-ic'));
    b.appendChild(el('span', 'sr', 'Mută ' + e.name + '. Trage cu mausul, sau apasă Enter și folosește săgețile.'));
    b.addEventListener('pointerdown', function (ev) { dragStart(ev, stopLi(b)); });
    b.addEventListener('keydown', function (ev) { gripKey(ev, stopLi(b)); });
    return b;
  }
  function stopLi(node) { return node.closest('.pl-stop'); }

  /** Top right of the card: everything you can do to the stop that is not its duration. */
  function toolsFor(d, r) {
    var e = r.e;
    var box = el('div', 'pl-stop-tools');

    var more = el('button', 'pl-ic-btn pl-more');
    more.type = 'button';
    more.title = 'Mai multe';
    more.setAttribute('aria-haspopup', 'true');
    more.setAttribute('aria-expanded', 'false');
    more.appendChild(glyph('dots'));
    more.appendChild(el('span', 'sr', 'Mai multe pentru ' + e.name));
    more.addEventListener('click', function (ev) { ev.stopPropagation(); toggleMenu(more, d, r); });
    box.appendChild(more);

    var del = el('button', 'pl-ic-btn pl-del');
    del.type = 'button';
    del.title = 'Scoate din plan';
    del.appendChild(glyph('trash'));
    del.appendChild(el('span', 'sr', 'Scoate ' + e.name + ' din plan'));
    del.addEventListener('click', function () { remove(d, r.pos); });
    box.appendChild(del);
    return box;
  }

  var menu = null;
  function closeMenu() {
    if (!menu) return;
    menu.btn.setAttribute('aria-expanded', 'false');
    if (menu.box.parentNode) menu.box.parentNode.removeChild(menu.box);
    menu = null;
    if (stale) refresh();
  }
  function toggleMenu(btn, d, r) {
    var same = menu && menu.btn === btn;
    closeMenu();
    if (same) return;
    var box = el('div', 'pl-menu');
    var add = el('button', 'pl-menu-i', 'Adaugă o oprire după');
    add.type = 'button';
    add.addEventListener('click', function () { closeMenu(); openComposer(d, r.pos + 1); });
    box.appendChild(add);
    // an own stop can be renamed later: it is the user's own words, not a catalogue name
    if (r.e.own && plan.extra[r.e.id]) {
      var ren = el('button', 'pl-menu-i', 'Redenumește');
      ren.type = 'button';
      ren.addEventListener('click', function () {
        closeMenu();
        var now = plan.extra[r.e.id].name || '';
        var next = window.prompt('Cum se numește oprirea?', now);
        if (next === null) return;
        next = next.replace(/\s+/g, ' ').trim().slice(0, 60);
        if (!next || next === now) return;
        plan.extra[r.e.id].name = next;
        focusId = r.e.id;
        announce('Oprirea se numește acum ' + next + '.');
        after();
      });
      box.appendChild(ren);
    }
    for (var k = 0; k < plan.days; k++) {
      (function (k) {
        if (k === d) return;
        var b = el('button', 'pl-menu-i', 'Mută în ziua ' + (k + 1));
        b.type = 'button';
        b.addEventListener('click', function () {
          closeMenu();
          if (!relocate(d, r.pos, k, (plan.stops[k] || []).length)) return;
          focusId = r.e.id;
          announce(r.e.name + ' a trecut în ziua ' + (k + 1) + '.');
          after();
        });
        box.appendChild(b);
      })(k);
    }
    btn.setAttribute('aria-expanded', 'true');
    btn.closest('.pl-stop-card').appendChild(box);
    menu = { btn: btn, box: box };
    var first = box.querySelector('button');
    if (first) first.focus();
  }
  document.addEventListener('click', function (ev) {
    if (menu && !menu.box.contains(ev.target) && ev.target !== menu.btn) closeMenu();
  });

  /* ---------- editing: every change locks what it touched ---------- */

  function lockDay(d) { (plan.stops[d] || []).forEach(function (s) { plan.locked[s] = 1; }); }
  /** Definitions nothing points at any more have no business travelling in the link. */
  function tidy() {
    var seen = {};
    (plan.stops || []).forEach(function (day) { (day || []).forEach(function (id) { seen[id] = 1; }); });
    Object.keys(plan.extra).forEach(function (id) { if (!seen[id]) delete plan.extra[id]; });
    if (!plan.nights) plan.nights = {};
    Object.keys(plan.nights).forEach(function (k) {
      if (+k >= nightCount()) delete plan.nights[k];
    });
  }
  function after() { stale = false; closeMenu(); tidy(); renderBar(); renderDays(); syncMap(); syncStay(); save(); fetchRoads(); }

  /**
   * One move for all of them — the arrows are gone, so dragging, the ⋮ menu and the keyboard all
   * come through here. `toPos` is where the stop should land in the day as it looks right now.
   */
  function relocate(fromDay, fromPos, toDay, toPos) {
    if (!plan.stops[fromDay] || !plan.stops[toDay]) return false;
    if (fromDay === toDay && (toPos === fromPos || toPos === fromPos + 1)) return false;
    var id = plan.stops[fromDay].splice(fromPos, 1)[0];
    if (id === undefined) return false;
    if (fromDay === toDay && toPos > fromPos) toPos--;
    toPos = Math.max(0, Math.min(plan.stops[toDay].length, toPos));
    plan.stops[toDay].splice(toPos, 0, id);
    plan.locked[id] = 1;
    lockDay(fromDay);
    lockDay(toDay);
    if (fromDay !== toDay) activeDay = toDay;
    return true;
  }
  function remove(d, pos) {
    var id = plan.stops[d].splice(pos, 1)[0];
    if (id === undefined) return;
    var e = entry(id);
    if (isOwn(id)) delete plan.extra[id];
    else plan.removed[id] = 1;
    delete plan.locked[id];
    delete plan.custom[id];
    if (grab && grab.id === id) grab = null;
    announce((e ? e.name : 'Oprirea') + ' nu mai e în plan.');
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
  /** A stop of your own, dropped in at `pos`. It is locked from the start: regenerating spares it. */
  function addOwn(d, pos, def) {
    var id = ownId();
    plan.extra[id] = def;
    plan.locked[id] = 1;
    pos = Math.max(0, Math.min((plan.stops[d] || []).length, pos));
    plan.stops[d].splice(pos, 0, id);
    activeDay = d;
    focusId = id;
    announce('Am adăugat ' + def.name + ' în ziua ' + (d + 1) + '.');
    after();
  }

  /* ---------- moving a stop: the same three steps by hand, by finger and by keyboard ---------- */

  var drag = null;      // a drag in progress
  var grab = null;      // a stop picked up with the keyboard
  var mark = null;      // the line that shows where it would land
  var ghost = null;     // the little label that follows the pointer
  var scroller = null;  // the timer that scrolls the page near the edges
  var scrollBy = 0;

  function stopName(id) { var e = entry(id); return e ? e.name : 'Oprirea'; }
  function dayStops(d) { return plan.stops[d] || []; }

  function showMark(t) {
    if (!mark) {
      mark = el('div', 'pl-drop');
      mark.setAttribute('aria-hidden', 'true');
    }
    if (!mark.parentNode) document.body.appendChild(mark);
    var items = t.items, box, top;
    if (t.at < items.length) {
      box = items[t.at].getBoundingClientRect();
      top = box.top - 6;
    } else if (items.length) {
      box = items[items.length - 1].getBoundingClientRect();
      top = box.bottom + 3;
    } else {
      box = t.list.getBoundingClientRect();
      top = box.top;
    }
    mark.style.top = Math.round(top) + 'px';
    mark.style.left = Math.round(box.left) + 'px';
    mark.style.width = Math.round(box.width) + 'px';
  }
  function hideMark() { if (mark && mark.parentNode) mark.parentNode.removeChild(mark); }

  /** Which day's list the pointer is over, and how far down it. */
  function dropAt(x, y) {
    var lists = ui.list.querySelectorAll('.pl-stops');
    var best = null, bestGap = Infinity;
    for (var i = 0; i < lists.length; i++) {
      var b = lists[i].getBoundingClientRect();
      var gap = (y < b.top ? b.top - y : (y > b.bottom ? y - b.bottom : 0)) +
        (x < b.left ? b.left - x : (x > b.right ? x - b.right : 0));
      if (gap < bestGap) { bestGap = gap; best = lists[i]; }
    }
    if (!best || bestGap > 160) return null;
    var items = best.querySelectorAll('.pl-stop');
    var at = 0;
    for (var k = 0; k < items.length; k++) {
      var r = items[k].getBoundingClientRect();
      if (y > r.top + r.height / 2) at = k + 1;
    }
    var day = parseInt(best.parentNode.dataset.day, 10);
    var pos = at < items.length
      ? parseInt(items[at].dataset.pos, 10)
      : (items.length ? parseInt(items[items.length - 1].dataset.pos, 10) + 1 : 0);
    return { day: day, list: best, items: items, at: at, pos: pos };
  }

  function edgeScroll(y) {
    var edge = 96;
    var top = (window.innerHeight || 0);
    scrollBy = y < edge ? -Math.ceil((edge - y) / 5) : (y > top - edge ? Math.ceil((y - (top - edge)) / 5) : 0);
    if (scrollBy && !scroller) {
      scroller = setInterval(function () {
        if (!drag) return;
        window.scrollBy(0, scrollBy);
        drag.target = dropAt(drag.x, drag.y);
        if (drag.target) showMark(drag.target); else hideMark();
      }, 16);
    }
    if (!scrollBy && scroller) { clearInterval(scroller); scroller = null; }
  }

  function dragStart(ev, item) {
    if (!item || (ev.button !== undefined && ev.button > 0)) return;
    ev.preventDefault();      // no text selection behind the drag, and no stray focus on the handle
    closeMenu();
    drag = {
      item: item, day: +item.dataset.day, pos: +item.dataset.pos, id: item.dataset.id,
      x: ev.clientX, y: ev.clientY, x0: ev.clientX, y0: ev.clientY, moved: false, target: null
    };
  }
  function dragMove(ev) {
    if (!drag) return;
    drag.x = ev.clientX;
    drag.y = ev.clientY;
    if (!drag.moved) {
      if (Math.abs(drag.x - drag.x0) + Math.abs(drag.y - drag.y0) < 6) return;
      drag.moved = true;
      drag.item.classList.add('is-dragging');
      document.body.classList.add('pl-dragging');
      var e = entry(drag.id);
      ghost = el('div', 'pl-ghost');
      var em = el('span', '', e && e.emoji ? e.emoji : '📍');
      em.setAttribute('aria-hidden', 'true');
      ghost.appendChild(em);
      ghost.appendChild(el('span', '', e ? e.name : ''));
      document.body.appendChild(ghost);
    }
    ghost.style.left = drag.x + 'px';
    ghost.style.top = drag.y + 'px';
    drag.target = dropAt(drag.x, drag.y);
    if (drag.target) showMark(drag.target); else hideMark();
    edgeScroll(drag.y);
  }
  function dragEnd(cancel) {
    if (!drag) return;
    var d = drag;
    drag = null;
    if (scroller) { clearInterval(scroller); scroller = null; }
    hideMark();
    if (ghost && ghost.parentNode) ghost.parentNode.removeChild(ghost);
    ghost = null;
    d.item.classList.remove('is-dragging');
    document.body.classList.remove('pl-dragging');
    if (cancel || !d.moved || !d.target) return;
    if (!relocate(d.day, d.pos, d.target.day, d.target.pos)) return;
    focusId = d.id;
    announce(stopName(d.id) + ' a ajuns în ziua ' + (d.target.day + 1) + '.');
    after();
  }
  window.addEventListener('pointermove', dragMove);
  window.addEventListener('pointerup', function () { dragEnd(false); });
  window.addEventListener('pointercancel', function () { dragEnd(true); });

  /* ---------- the same, from the keyboard ---------- */

  function grabbed(item) {
    grab = { id: item.dataset.id, day: +item.dataset.day, pos: +item.dataset.pos };
    grab.day0 = grab.day;
    grab.pos0 = grab.pos;
    item.classList.add('is-grabbed');
    announce('Ai luat ' + stopName(grab.id) +
      '. Săgeți sus și jos ca s-o muți în zi, stânga și dreapta ca s-o treci în altă zi, Enter ca s-o lași acolo, Escape ca să renunți.');
  }
  function grabSay() {
    announce(stopName(grab.id) + ': poziția ' + (grab.pos + 1) + ' din ' + dayStops(grab.day).length +
      ', ziua ' + (grab.day + 1) + '.');
  }
  function grabStep(dir) {
    var to = grab.pos + dir;
    if (to < 0 || to >= dayStops(grab.day).length) { announce('Nu mai e loc în direcția asta.'); return; }
    if (!relocate(grab.day, grab.pos, grab.day, dir > 0 ? to + 1 : to)) return;
    grab.pos = to;
    after();
    grabSay();
  }
  function grabDay(dir) {
    var to = grab.day + dir;
    if (to < 0 || to >= plan.days) { announce('Nu mai e nicio zi în direcția asta.'); return; }
    if (!relocate(grab.day, grab.pos, to, dayStops(to).length)) return;
    grab.day = to;
    grab.pos = dayStops(to).length - 1;
    after();
    grabSay();
  }
  function grabDrop() {
    var was = grab;
    grab = null;
    focusId = was.id;
    announce(stopName(was.id) + ' rămâne pe poziția ' + (was.pos + 1) + ' din ziua ' + (was.day + 1) + '.');
    after();
  }
  function grabCancel() {
    var was = grab;
    grab = null;
    focusId = was.id;
    var id = plan.stops[was.day].splice(was.pos, 1)[0];
    if (id !== undefined) {
      plan.stops[was.day0].splice(Math.min(was.pos0, plan.stops[was.day0].length), 0, id);
      activeDay = was.day0;
    }
    announce('Am anulat mutarea. ' + stopName(was.id) + ' e unde era.');
    after();
  }
  function gripKey(ev, item) {
    var k = ev.key;
    if (k === ' ' || k === 'Spacebar' || k === 'Enter') {
      ev.preventDefault();
      if (!grab) grabbed(item);
      else if (grab.id === item.dataset.id) grabDrop();
      return;
    }
    if (!grab || grab.id !== item.dataset.id) return;
    if (k === 'ArrowUp') { ev.preventDefault(); grabStep(-1); }
    else if (k === 'ArrowDown') { ev.preventDefault(); grabStep(1); }
    else if (k === 'ArrowLeft') { ev.preventDefault(); grabDay(-1); }
    else if (k === 'ArrowRight') { ev.preventDefault(); grabDay(1); }
    else if (k === 'Escape') { ev.preventDefault(); grabCancel(); }
  }
  document.addEventListener('keydown', function (ev) {
    if (ev.key !== 'Escape') return;
    if (drag) { dragEnd(true); return; }
    if (sheet) { closeSheet(); return; }
    if (menu) { var b = menu.btn; closeMenu(); b.focus(); }
  });

  /* ---------- a stop of your own: the little form that makes one ---------- */

  var composer = null;

  /**
   * The gap between two stops: hovering it, or reaching it with the keyboard, offers a + that puts a stop of your own
   * exactly there. It gets out of the way while something is being dragged, where the drop line already says enough.
   */
  function insertSlot(d, pos) {
    var li = el('li', 'pl-ins');
    li.dataset.pos = String(pos);
    var b = el('button', 'pl-ins-btn');
    b.type = 'button';
    b.title = 'Adaugă o oprire aici';
    b.appendChild(icon('plus'));
    b.appendChild(el('span', 'sr', 'Adaugă o oprire aici, între opriri'));
    b.addEventListener('click', function () { openComposer(d, pos); });
    li.appendChild(b);
    return li;
  }

  function openComposer(d, pos) {
    var first = PRESETS[0] || ['Pauză', '🕑', 30];
    composer = {
      day: d, pos: pos, name: first[0], emoji: first[1], minutes: first[2],
      at: 'prev', place: '', lat: null, lng: null, focus: true
    };
    after();
  }
  function closeComposer() {
    composer = null;
    after();
  }

  function composerItem() {
    var c = composer;
    var li = el('li', 'pl-new');
    var box = el('div', 'pl-new-in');
    box.appendChild(el('p', 'pl-new-h', 'O oprire de-a ta'));

    var name = el('input');
    var dur = el('select');
    var pick = el('div', 'pl-new-pick');
    var find = el('input');
    var msg = el('p', 'pl-new-msg');
    msg.hidden = true;

    var presets = el('div', 'pl-new-presets');
    PRESETS.forEach(function (p) {
      var b = el('button', 'pl-chip pl-chip-sm');
      b.type = 'button';
      b.setAttribute('aria-pressed', c.name === p[0] ? 'true' : 'false');
      var em = el('span', '', p[1]);
      em.setAttribute('aria-hidden', 'true');
      b.appendChild(em);
      b.appendChild(document.createTextNode(p[0]));
      b.addEventListener('click', function () {
        c.name = p[0];
        c.emoji = p[1];
        c.minutes = p[2];
        name.value = p[0];
        dur.value = String(p[2]);
        [].forEach.call(presets.children, function (x) { x.setAttribute('aria-pressed', String(x === b)); });
      });
      presets.appendChild(b);
    });
    box.appendChild(presets);

    var row = el('div', 'pl-new-row');
    var nameLab = el('label', 'pl-new-f');
    nameLab.appendChild(el('span', '', 'Ce faci?'));
    name.type = 'text';
    name.value = c.name;
    name.maxLength = 60;
    name.placeholder = 'Masă, cafea, o plimbare…';
    name.addEventListener('input', function () { c.name = name.value; });
    nameLab.appendChild(name);
    row.appendChild(nameLab);

    var durLab = el('label', 'pl-new-f pl-new-f-dur');
    durLab.appendChild(el('span', '', 'Cât ține?'));
    MINUTES.forEach(function (m) {
      var o = el('option', '', hm(m));
      o.value = String(m);
      if (m === c.minutes) o.selected = true;
      dur.appendChild(o);
    });
    dur.addEventListener('change', function () { c.minutes = parseInt(dur.value, 10) || 30; });
    durLab.appendChild(dur);
    row.appendChild(durLab);
    box.appendChild(row);

    var fs = el('fieldset', 'pl-new-where');
    fs.appendChild(el('legend', '', 'Unde o pun?'));
    var group = 'pl-at-' + Math.random().toString(36).slice(2, 8);
    [
      ['prev', 'La oprirea dinainte', 'Nu adaugă drum, doar timp.'],
      ['none', 'Fără loc anume', 'Timp în zi, fără punct pe hartă.'],
      ['fix', 'Alt loc', 'Alegi un loc din catalog și drumul se recalculează.']
    ].forEach(function (o) {
      var lab = el('label', 'pl-radio');
      var rd = el('input');
      rd.type = 'radio';
      rd.name = group;
      rd.value = o[0];
      rd.checked = c.at === o[0];
      rd.addEventListener('change', function () {
        if (!rd.checked) return;
        c.at = o[0];
        pick.hidden = o[0] !== 'fix';
        if (o[0] === 'fix') find.focus();
      });
      lab.appendChild(rd);
      var t = el('span', 'pl-radio-t');
      t.appendChild(el('b', '', o[1]));
      t.appendChild(el('small', '', o[2]));
      lab.appendChild(t);
      fs.appendChild(lab);
    });
    box.appendChild(fs);

    pick.hidden = c.at !== 'fix';
    find.type = 'search';
    find.autocomplete = 'off';
    find.placeholder = 'Caută locul…';
    find.setAttribute('aria-label', 'Caută locul opririi');
    find.value = c.query || c.place || '';
    var hits = el('ul', 'pl-add-list');
    hits.hidden = true;
    find.addEventListener('input', debounce(function () {
      c.query = find.value;
      c.lat = null;
      c.lng = null;
      c.place = '';
      hits.textContent = '';
      var found = searchCatalogue(find.value, 6);
      found.forEach(function (h) {
        var row2 = el('li');
        var b = el('button', 'pl-add-hit');
        b.type = 'button';
        b.appendChild(el('b', '', h.name));
        b.appendChild(el('small', '', (h.book ? 'se rezervă · ' : '') + (h.city || '')));
        b.addEventListener('click', function () {
          var e = entry(h.id);
          if (!e) return;
          c.lat = e.lat;
          c.lng = e.lng;
          c.place = e.name;
          c.query = e.name;
          find.value = e.name;
          hits.hidden = true;
          msg.hidden = true;
        });
        row2.appendChild(b);
        hits.appendChild(row2);
      });
      hits.hidden = !found.length;
    }, 140));
    pick.appendChild(find);
    pick.appendChild(hits);
    box.appendChild(pick);
    box.appendChild(msg);

    var acts = el('div', 'pl-new-acts');
    var ok = el('button', 'btn btn-primary pl-new-ok', 'Adaugă oprirea');
    ok.type = 'button';
    ok.addEventListener('click', function () {
      if (c.at === 'fix' && typeof c.lat !== 'number') {
        msg.textContent = 'Alege întâi locul din listă.';
        msg.hidden = false;
        find.focus();
        return;
      }
      var def = {
        name: (c.name || '').trim() || 'Oprire',
        minutes: c.minutes || 30,
        emoji: c.emoji || '🕑',
        at: c.at,
        lat: c.at === 'fix' ? c.lat : null,
        lng: c.at === 'fix' ? c.lng : null,
        place: c.at === 'fix' ? c.place : ''
      };
      var day = c.day, at = c.pos;
      composer = null;
      addOwn(day, at, def);
    });
    var no = el('button', 'pl-btn pl-new-no', 'Renunță');
    no.type = 'button';
    no.addEventListener('click', function () { closeComposer(); });
    acts.appendChild(ok);
    acts.appendChild(no);
    box.appendChild(acts);

    li.appendChild(box);
    return li;
  }

  /** Both catalogues at once: what can be booked first, then the attractions. */
  function searchCatalogue(q, limit) {
    var out = [];
    q = fold((q || '').trim());
    if (q.length < 2) return out;
    (CFG.bookables || []).forEach(function (b) {
      if (out.length < Math.min(4, limit) && fold(b[2]).indexOf(q) !== -1) {
        out.push({ id: 'b:' + b[1], name: b[2], city: b[3], book: true });
      }
    });
    var f = D.f;
    for (var i = 0; i < D.rows.length && out.length < limit; i++) {
      var name = D.rows[i][f.name];
      if (fold(name).indexOf(q) === -1) continue;
      var c = D.rows[i][f.city] >= 0 ? D.cities[D.rows[i][f.city]][1] : '';
      out.push({ id: D.rows[i][f.slug], name: name, city: c, book: false });
    }
    return out;
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
      list.textContent = '';
      var hits = searchCatalogue(inp.value, 8);
      hits.forEach(function (h) {
        var li = el('li');
        var b = el('button', 'pl-add-hit');
        b.type = 'button';
        b.appendChild(el('b', '', h.name));
        b.appendChild(el('small', '', (h.book ? 'se rezervă · ' : '') + (h.city || '')));
        b.addEventListener('click', function () { inp.value = ''; list.hidden = true; add(d, h.id); });
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

  /* ---------------------------------------------------------------- nights on the page
   *
   * A night card sits between two days and carries three decisions: see what there is, sleep
   * somewhere else, or do not sleep on the road at all. The list of places to sleep is Stay22's,
   * in an iframe built only when somebody asks for it — on a wide screen in the map column, beside
   * our own map, and on a phone as a sheet over the plan.
   */

  function setCls(node, name, on) { if (node) node.classList[on ? 'add' : 'remove'](name); }
  function wide() { return !!(window.matchMedia && window.matchMedia(WIDE).matches); }

  function nightBtn(ic, label, fn, cls) {
    var b = el('button', 'pl-day-act' + (cls ? ' ' + cls : ''));
    b.type = 'button';
    b.appendChild(icon(ic));
    b.appendChild(el('span', '', label));
    b.addEventListener('click', function () { fn(b); });
    return b;
  }
  /** Rendering throws the card away, so the night that was just changed gets its focus back. */
  function afterNight(i) {
    after();
    var card = ui.list.querySelector('.pl-night[data-night="' + i + '"]');
    if (card) card.focus();
  }
  function jumpToNight() {
    var want = -1;
    for (var i = 0; i < nightCount(); i++) {
      if (!(plan.nights || {})[i]) { want = i; break; }     // nobody has said anything about this one
    }
    if (want < 0) want = 0;
    var card = ui.list.querySelector('.pl-night[data-night="' + want + '"]');
    if (!card) return;
    card.scrollIntoView({ behavior: 'smooth', block: 'center' });
    card.focus({ preventScroll: true });
  }

  function nightCard(i) {
    var n = nightOf(i);
    var box = el('section', 'pl-night');
    box.dataset.night = String(i);
    box.tabIndex = -1;

    var h = el('h3', 'pl-night-h');
    var em = el('span', 'pl-night-em', '🌙');
    em.setAttribute('aria-hidden', 'true');
    h.appendChild(em);
    h.appendChild(document.createTextNode(nightTitle(i)));
    box.appendChild(h);

    if (n && n.skip) {
      setCls(box, 'is-skipped', true);
      box.appendChild(el('p', 'pl-night-p', 'Ai spus că nu dormi pe traseu în noaptea asta.'));
      var undo = el('div', 'pl-night-acts');
      undo.appendChild(nightBtn('plus', 'Pun cazarea la loc', function () {
        setNight(i, { skip: false });
        announce('Noaptea a revenit în plan.');
        afterNight(i);
      }));
      box.appendChild(undo);
      return box;
    }

    if (!n) {
      box.appendChild(el('p', 'pl-night-p',
        'Nu am în catalog un oraș pe care să ți-l propun pentru noaptea asta. Alege tu unul.'));
    } else {
      setCls(box, 'is-far', n.far);
      box.appendChild(nightLine(n));
    }
    var pick = nightPicker(i);
    box.appendChild(nightActs(i, n, pick));
    box.appendChild(pick);
    return box;
  }

  /** What we can honestly say about the town: the two drives it sits between, and nothing else. */
  function nightLine(n) {
    var p = el('p', 'pl-night-p');
    var town = el('b', '', n.name);
    if (n.far) {
      p.appendChild(document.createTextNode('Prin zonă nu e niciun oraș din catalog aproape. Cel mai apropiat e '));
      p.appendChild(town);
      if (n.hasFrom) p.appendChild(document.createTextNode(', la ' + km1(n.kA) + ' km de ultima oprire'));
      if (n.toName) {
        p.appendChild(document.createTextNode((n.hasFrom ? ' și la ' : ', la ') + km1(n.kB) +
          ' km de ' + n.toName + ', unde pornești mâine.'));
      } else {
        p.appendChild(document.createTextNode('.'));
      }
      p.appendChild(document.createTextNode(' Dacă știi ceva mai aproape, alege altă localitate.'));
      return p;
    }
    p.appendChild(document.createTextNode('Îți propun să dormi în '));
    p.appendChild(town);
    if (n.hasFrom && n.toName) {
      p.appendChild(document.createTextNode(' — ultima oprire e la ' + km1(n.kA) +
        ' km, iar mâine pornești spre ' + n.toName + ', la ' + km1(n.kB) + ' km.'));
    } else if (n.hasFrom) {
      p.appendChild(document.createTextNode(' — ultima oprire e la ' + km1(n.kA) + ' km.'));
    } else if (n.toName) {
      p.appendChild(document.createTextNode(' — mâine pornești spre ' + n.toName + ', la ' + km1(n.kB) + ' km.'));
    } else {
      p.appendChild(document.createTextNode('.'));
    }
    return p;
  }

  function nightActs(i, n, pick) {
    var wrap = el('div', 'pl-night-acts');
    if (n) wrap.appendChild(nightBtn('buildings', 'Vezi cazări', function () { openStay(i); }, 'is-primary'));

    var other = nightBtn('magnifying-glass', n ? 'Altă localitate' : 'Alege localitatea', function (b) {
      var show = pick.hidden;
      pick.hidden = !show;
      b.setAttribute('aria-expanded', String(show));
      if (show) {
        var f = pick.querySelector('input');
        if (f) f.focus();
      }
    });
    other.setAttribute('aria-expanded', 'false');
    wrap.appendChild(other);

    wrap.appendChild(nightBtn('x', 'Nu dorm aici', function () {
      setNight(i, { skip: true });
      announce('Am scos noaptea din plan.');
      afterNight(i);
    }));

    if (n) {
      var rl = el('label', 'pl-night-rooms');
      rl.appendChild(el('span', '', 'Camere'));
      var sel = el('select');
      for (var r = 1; r <= (PARTY.rooms_max || 8); r++) {
        var o = el('option', '', String(r));
        o.value = String(r);
        if (r === n.rooms) o.selected = true;
        sel.appendChild(o);
      }
      sel.addEventListener('change', function () {
        setNight(i, { rooms: clampInt(sel.value, 1, PARTY.rooms_max || 8, defaultRooms()) });
        afterNight(i);
      });
      rl.appendChild(sel);
      wrap.appendChild(rl);

      // How much the night may cost at most — Stay22 filters on it, so an empty choice means "show me everything".
      var bl = el('label', 'pl-night-rooms');
      bl.appendChild(el('span', '', 'Maxim pe noapte'));
      var bsel = el('select');
      var b0 = el('option', '', 'fără limită');
      b0.value = '0';
      if (!n.maxprice) b0.selected = true;
      bsel.appendChild(b0);
      var budgets = CFG.budgets && CFG.budgets.length ? CFG.budgets : [200, 300, 500, 700, 1000];
      if (n.maxprice > 0 && budgets.indexOf(n.maxprice) === -1) budgets = budgets.concat([n.maxprice]);
      budgets.slice().sort(function (a, b) { return a - b; }).forEach(function (v) {
        var o = el('option', '', nf(v) + ' lei');
        o.value = String(v);
        if (v === n.maxprice) o.selected = true;
        bsel.appendChild(o);
      });
      bsel.addEventListener('change', function () {
        setNight(i, { maxprice: clampInt(bsel.value, 0, 100000, 0) });
        afterNight(i);
      });
      bl.appendChild(bsel);
      wrap.appendChild(bl);
    }
    return wrap;
  }

  /** The same city list the start form searches, so "another town" means the same thing twice. */
  function nightCityHits(q, limit) {
    if (!D) return [];
    if (!ALL_PLACES) ALL_PLACES = places();
    q = fold((q || '').trim());
    if (q.length < 2) return [];
    var pool = ALL_PLACES.filter(function (x) { return x.kind === 'city'; });
    return pool.filter(function (x) { return fold(x.label).indexOf(q) === 0; })
      .concat(pool.filter(function (x) { return fold(x.label).indexOf(q) > 0; }))
      .slice(0, limit || 7);
  }

  function nightPicker(i) {
    var wrap = el('div', 'pl-night-pick');
    wrap.hidden = true;
    var inp = el('input');
    inp.type = 'search';
    inp.autocomplete = 'off';
    inp.placeholder = 'Caută orașul în care dormi…';
    inp.setAttribute('aria-label', 'Caută orașul în care dormi');
    var hits = el('ul', 'pl-night-hits');
    hits.hidden = true;
    inp.addEventListener('input', debounce(function () {
      hits.textContent = '';
      var found = nightCityHits(inp.value, 7);
      found.forEach(function (pl) {
        var li = el('li');
        var b = el('button', 'pl-add-hit');
        b.type = 'button';
        b.appendChild(el('b', '', pl.label));
        b.appendChild(el('small', '', pl.hint + ' · ' + nf(pl.count) + ' atracții'));
        b.addEventListener('click', function () {
          var r = resolvePlace(pl);
          if (!r) return;
          setNight(i, {
            city: pl.key, lat: r.lat, lng: r.lng, name: pl.label,
            county: cityCounty[pl.key] || '', skip: false
          });
          announce('Noaptea se mută în ' + pl.label + '.');
          afterNight(i);
        });
        li.appendChild(b);
        hits.appendChild(li);
      });
      hits.hidden = !found.length;
    }, 140));
    wrap.appendChild(inp);
    wrap.appendChild(hits);
    return wrap;
  }

  /* ---------- the accommodation view ---------- */

  function firstOpenNight() {
    for (var i = 0; i < nightCount(); i++) {
      var n = nightOf(i);
      if (n && !n.skip) return i;
    }
    return -1;
  }
  function showTab(name) {
    if (!ui.paneStay) return;
    if (name === 'stay' && (stayNight < 0 || stayNight >= nightCount())) stayNight = firstOpenNight();
    mapTab = name;
    setCls(ui.tabRoute, 'is-on', name === 'route');
    setCls(ui.tabStay, 'is-on', name === 'stay');
    if (ui.tabRoute) ui.tabRoute.setAttribute('aria-selected', String(name === 'route'));
    if (ui.tabStay) ui.tabStay.setAttribute('aria-selected', String(name === 'stay'));
    // Not unmounted, only out of sight: the map keeps its size, so Leaflet has nothing to recover.
    setCls(ui.paneRoute, 'is-off', name !== 'route');
    setCls(ui.paneStay, 'is-off', name !== 'stay');
    syncStay();
  }
  function syncStay() {
    if (ui.paneStay && mapTab === 'stay') stayInto(ui.paneStay, stayNight);
    if (sheet) stayInto(sheet.body, sheet.night);
  }
  function openStay(i) {
    stayNight = i;
    var n = nightOf(i);
    if (wide()) {
      showTab('stay');
      var col = document.querySelector('.pl-map-col');
      if (col && col.scrollIntoView) col.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
      announce(n ? 'Am deschis cazările din ' + n.name + ' în coloana din dreapta.' : 'Am deschis cazările.');
    } else {
      openSheet(i);
    }
  }

  /**
   * Builds the embed for one night, and only then. The same night twice in a row is left alone, so
   * a redraw does not reload the iframe under the reader; anything that changes the URL — the town,
   * the dates, who travels, the rooms — does rebuild it.
   */
  function stayInto(host, i) {
    var n = i >= 0 ? nightOf(i) : null;
    if (!n || n.skip) {
      if (host.dataset.stayUrl === '-') return;
      host.textContent = '';
      host.dataset.stayUrl = '-';
      host.appendChild(el('p', 'pl-stay-empty', nightCount()
        ? 'Alege o noapte din plan și îți deschid aici cazările din orașul ei.'
        : 'Planul are o singură zi, deci nicio noapte pe drum: te întorci în aceeași zi.'));
      return;
    }
    var url = stayUrl(i);
    if (host.dataset.stayUrl === url) return;
    host.textContent = '';
    host.dataset.stayUrl = url;

    var box = el('div', 'pl-stay');
    box.appendChild(el('p', 'pl-stay-h', 'Cazare în ' + n.name));
    box.appendChild(el('p', 'pl-stay-sub', nightRange(i) + ' · ' + partyLine(n.rooms)
      + (n.maxprice > 0 ? ' · maxim ' + nf(n.maxprice) + ' lei' : '')));

    var frame = el('div', 'pl-stay-frame');
    var skel = el('div', 'pl-stay-skel');
    var spin = el('span', 'pl-stay-spin');
    spin.setAttribute('aria-hidden', 'true');
    skel.appendChild(spin);
    skel.appendChild(el('span', '', 'Se încarcă lista de cazări…'));
    var fr = el('iframe');
    fr.title = 'Cazări în ' + n.name + ', pe Stay22';
    fr.loading = 'lazy';
    fr.referrerPolicy = 'origin';
    fr.setAttribute('allowtransparency', 'true');
    var arrived = false;
    fr.addEventListener('load', function () { arrived = true; skel.hidden = true; });
    setTimeout(function () {
      if (arrived || !skel.parentNode) return;
      skel.textContent = '';
      skel.appendChild(el('span', '', 'Lista nu a pornit. Deschide-o pe Stay22, din link-ul de mai jos.'));
    }, 12000);
    fr.src = url;
    frame.appendChild(fr);
    frame.appendChild(skel);
    box.appendChild(frame);

    var foot = el('div', 'pl-stay-foot');
    var out = el('a', 'pl-stay-out');
    out.href = stayLink(i);
    out.target = '_blank';
    out.rel = 'noopener nofollow sponsored';
    out.appendChild(document.createTextNode('Deschide lista pe Stay22'));
    out.appendChild(icon('arrow-right'));
    foot.appendChild(out);
    box.appendChild(foot);

    box.appendChild(el('p', 'pl-stay-note', STAY.note ||
      'Cazările vin de la Stay22. Dacă rezervi, primim un comision — prețul tău nu crește.'));
    host.appendChild(box);
  }
  function nightRange(i) {
    var a = dateOffset(i), b = dateOffset(i + 1);
    if (!a || !b) return 'o noapte';
    var f = { day: 'numeric', month: 'short' };
    return a.toLocaleDateString('ro-RO', f) + ' → ' + b.toLocaleDateString('ro-RO', f);
  }

  /* ---------- on a phone: a sheet over the plan, not a column beside it ---------- */

  function openSheet(i) {
    closeSheet();
    var back = document.activeElement;
    var box = el('div', 'pl-sheet');
    var head = el('div', 'pl-sheet-head');
    head.appendChild(el('p', 'pl-stay-h', 'Cazare · ' + nightTitle(i)));
    var x = el('button', 'pl-sheet-x');
    x.type = 'button';
    x.appendChild(icon('x'));
    x.appendChild(el('span', 'sr', 'Închide cazările'));
    x.addEventListener('click', function () { closeSheet(); });
    head.appendChild(x);
    var body = el('div', 'pl-sheet-body');
    box.appendChild(head);
    box.appendChild(body);
    document.body.appendChild(box);
    document.documentElement.classList.add('pl-sheet-open');
    sheet = { box: box, body: body, night: i, back: back };
    stayInto(body, i);
    x.focus();
  }
  function closeSheet() {
    if (!sheet) return;
    var was = sheet;
    sheet = null;
    document.documentElement.classList.remove('pl-sheet-open');
    if (was.box.parentNode) was.box.parentNode.removeChild(was.box);
    if (was.back && was.back.focus) was.back.focus();
  }

  if (ui.tabRoute) ui.tabRoute.addEventListener('click', function () { showTab('route'); });
  if (ui.tabStay) ui.tabStay.addEventListener('click', function () { showTab('stay'); });

  /* ---------------------------------------------------------------- the map */

  function syncMap() {
    var inst = window.EPMap.instance;
    if (!inst) return;
    var view = dayView(activeDay);
    var r = road(activeDay);
    // Only real places get a pin. A stop of your own is time: whatever driving it carried is folded
    // into the next pin, so the kilometres on the map still add up.
    var rows = [], carry = { km: 0, min: 0 };
    view.rows.forEach(function (x) {
      if (x.role !== 'stop') return;
      if (x.e.own) { carry.km += x.legKm; carry.min += x.legMin; return; }
      rows.push(mapRow(x.e, x.legKm + carry.km, x.legMin + carry.min));
      carry.km = 0;
      carry.min = 0;
    });
    inst.setRoute(rows, r ? r.geometry : '');
    if (ui.note) {
      ui.note.textContent = rows.length
        ? 'Ziua ' + (activeDay + 1) + ': ' + rows.length + (rows.length === 1 ? ' oprire, ' : ' opriri, ') +
          nf(view.km) + ' km ' + (view.routed
            ? 'pe șosea, cu drumul desenat pe hartă.'
            : (view.pending ? '— se calculează drumul…' : '(estimat; drumul nu a putut fi calculat).'))
        : (view.rows.some(function (x) { return x.role === 'stop'; })
          ? 'Ziua ' + (activeDay + 1) + ': doar opriri de-ale tale — pe hartă ajung locurile din catalog.'
          : 'Ziua ' + (activeDay + 1) + ' e goală.');
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
    var ad = document.getElementById('pl-adults'), ch = document.getElementById('pl-children');
    plan.party = {
      adults: clampInt(ad ? ad.value : null, 1, PARTY.adults_max || 12, PARTY.adults || 2),
      children: clampInt(ch ? ch.value : null, 0, PARTY.children_max || 10, PARTY.children || 0)
    };
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
  [].forEach.call(document.querySelectorAll('[data-party]'), function (b) {
    b.addEventListener('click', function () {
      var inp = document.getElementById('pl-' + b.dataset.party);
      if (!inp) return;
      var lo = parseInt(inp.min, 10) || 0, hi = parseInt(inp.max, 10) || 12;
      inp.value = String(clampInt((parseInt(inp.value, 10) || lo) + (parseInt(b.dataset.step, 10) || 0), lo, hi, lo));
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

  /** A plan that came back from a link or from storage shows its party in the form again. */
  function fillParty() {
    var p = party();
    var ad = document.getElementById('pl-adults'), ch = document.getElementById('pl-children');
    if (ad) ad.value = String(p.adults);
    if (ch) ch.value = String(p.children);
  }

  /** Whatever the catalogue no longer knows about is dropped, so positions stay honest. */
  function prune() {
    plan.stops = (plan.stops || []).map(function (day) {
      return (day || []).filter(function (id) { return !!entry(id); });
    });
    while (plan.stops.length < plan.days) plan.stops.push([]);
    if (!plan.party) plan.party = partyDefault();
    if (!plan.nights) plan.nights = {};
    Object.keys(plan.nights).forEach(function (k) {
      if (+k >= nightCount()) delete plan.nights[k];
    });
  }

  /* ---------------------------------------------------------------- boot */

  loadData().then(function () {
    ALL_PLACES = places();
    var saved = restore();
    if (saved && saved.stops && saved.stops.length) {
      plan = saved;
      prune();
      fillParty();
      activeDay = 0;
      render();
    }
  }).catch(function (err) {
    if (window.console) console.warn('[plan]', err);
  });
})();
