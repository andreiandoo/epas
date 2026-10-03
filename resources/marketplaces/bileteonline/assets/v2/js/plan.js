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
 *
 * On screen the plan is one map and one list. The map never leaves: on a phone it holds the top of
 * the screen and the list scrolls under it, on a desk they sit side by side. Whatever the list has
 * under its heading is what the map shows — the day, then the stop and its neighbours, then the
 * town of the night — so nobody has to ask for the map. The same map takes the accommodation list
 * when it is asked for, from the button at its foot.
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
  var PRESETS = CFG.stops || [];                        // your own stops, ready made: label, icon key, minutes
  var MINUTES = CFG.minutes || [15, 30, 45, 60, 90, 120, 180, 240];
  var PARTY = CFG.party || {};                          // how many travel, and how many fit in a room
  var STAY = CFG.stay22 || {};                          // the accommodation embed, minus dates and places
  var WIDE = '(min-width: 1024px)';                     // where the map stands beside the list instead of above it
  var MODES = CFG.modes || {};                          // how you travel: speeds, routing profile, what the plan leans to
  var MODE_COLOR = { car: '#1E5B48', moto: '#C8322B', bike: '#2D6CCD' };
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
  /* Place icons (includes/v2/product-icons.php, printed by the header as #i-pi-<key>): an attraction type's by its
     slug, the rest by key. An emoji saved by an older version of the page is read as the key it stood for. */
  var TYPE_ICON = { 'castel-palat': 'castle', 'muzeu': 'museum', 'monument': 'columns', 'biserica-manastire': 'church', 'parc-gradina': 'park',
    'piata-centru-vechi': 'city', 'cladire-istorica': 'house', 'punct-panoramic': 'binoculars', 'lac-natura': 'waves', 'teatru-opera': 'theatre' };
  var OLD_EMOJI = { '\uD83C\uDF7D\uFE0F': 'fork', '\uD83C\uDF7D': 'fork', '\u2615': 'coffee', '\uD83D\uDE0C': 'armchair', '\uD83D\uDEB6': 'walk',
    '\uD83D\uDECD\uFE0F': 'shopping', '\uD83D\uDECD': 'shopping', '\uD83C\uDFE8': 'bed', '\uD83D\uDD51': 'clock', '\uD83C\uDF9F\uFE0F': 'ticket',
    '\u2728': 'sparkle', '\uD83D\uDCCD': 'pin' };
  function placeKey(k, fallback) {
    if (k && OLD_EMOJI[k]) k = OLD_EMOJI[k];
    return typeof k === 'string' && /^[a-z]{1,16}$/.test(k) && document.getElementById('i-pi-' + k) ? k : (fallback || 'pin');
  }
  function placeIcon(k, fallback, cls) { return icon('pi-' + placeKey(k, fallback), cls || 'ic-em'); }
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
  function modeKey() { return (plan && MODES[plan.mode]) ? plan.mode : 'car'; }
  function modeDef() { return (MODES[modeKey()] || [])[2] || {}; }
  /** How the way you travel tilts the types: a rider stops for a view, not for a street of old houses. */
  function modeWeight(typeSlug) { var w = modeDef().weights || {}; return w[typeSlug] || 0; }
  function estMin(distKm) {
    var m = modeDef();
    var t = (distKm * (m.detour || CFG.travel.detour || 1.35)) / (m.kmh || CFG.travel.kmh || 55) * 60;
    return distKm < 0.3 ? 0 : Math.max(CFG.travel.min_leg || 5, Math.round(t));
  }
  function estKm(distKm) { return Math.round(distKm * (modeDef().detour || CFG.travel.detour || 1.35) * 10) / 10; }
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
      dur: x.minutes || 30, img: '', type: '', emoji: placeKey(x.emoji, 'clock'), typeSlug: '',
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
        emoji: b[0] === 'location' ? 'ticket' : 'sparkle', bookable: true, typeSlug: '',
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
      emoji: t ? (TYPE_ICON[t[0]] || 'pin') : 'pin', typeSlug: t ? t[0] : '',
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
      origin: null, where: null, back: null, days: 2, from: '', mode: 'car',
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
    var radius = plan.where.kind === 'city' ? RADIUS_CITY[Math.min(plan.days, RADIUS_CITY.length) - 1] * (modeDef().radius || 1) : 0;

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
      score += companyWeight(tSlug) + modeWeight(tSlug);
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
          if (tm > (modeDef().leg_max || 75)) continue;
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
        fetch('/api/route.php?c=' + encodeURIComponent(key) + (modeDef().profile === 'bike' ? '&p=bike' : ''), { credentials: 'omit' })
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
    if (n.maxprice > 0) { q.push('max=' + n.maxprice); q.push('priceper=nightly'); q.push('maxprice=' + n.maxprice); }
    q.push('currency=' + encodeURIComponent(STAY.currency || 'RON'));
    q.push('maincolor=' + encodeURIComponent(STAY.maincolor || '1E5B48'));
    q.push('markertype=' + encodeURIComponent(STAY.markertype || 'circle'));
    q.push('zoom=' + (STAY.zoom || 12));
    q.push('ljs=ro');
    q.push('hidebrandlogo=true');
    var prov = (STAY.providers || {})[stayProv];
    if (prov && prov[1]) q.push(prov[1]);
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
    if (n.maxprice > 0) { q.push('max=' + n.maxprice); q.push('priceper=nightly'); q.push('maxprice=' + n.maxprice); }
    q.push('currency=' + encodeURIComponent(STAY.currency || 'RON'));
    var prov = (STAY.providers || {})[stayProv];
    return ((prov && prov[2]) || STAY.link || 'https://www.stay22.com/allez/booking') + '?' + q.join('&');
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
    if (plan.mode && plan.mode !== 'car') compact.m = plan.mode;
    if (plan.name) compact.e = plan.name;
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
      p.mode = MODES[o.m] ? o.m : 'car';      // a link made before the modes existed is a car plan
      if (typeof o.e === 'string') p.name = o.e.slice(0, 80);
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
          'SUMMARY:' + icsEscape(e.name)
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
        'SUMMARY:' + icsEscape('Cazare în ' + nt.name),
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
    return 'https://www.google.com/maps/dir/?api=1&travelmode=' + (modeDef().gmaps || 'driving') +
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
        title: (plan.name || ('Plan ' + plan.where.label)) + ' · ' + plan.days + (plan.days === 1 ? ' zi' : ' zile'),
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
    ui.bar.appendChild(panel);
    if (ui.scroller) ui.scroller.scrollTop = 0;

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
    paneStay: document.getElementById('pl-pane-stay'),
    scroller: document.getElementById('plx-list'),
    map: document.getElementById('plx-map'),
    top: document.getElementById('plx-top'),
    title: document.getElementById('plx-t'),
    sub: document.getElementById('plx-s'),
    badge: document.getElementById('plx-mode'),
    rail: document.getElementById('plx-days'),
    menu: document.getElementById('plx-menu'),
    menuBtn: document.getElementById('plx-menu-b'),
    stayBtn: document.getElementById('plx-stay-btn'),
    stayBox: document.getElementById('plx-stay'),
    stayX: document.getElementById('plx-stay-x'),
    prof: document.getElementById('plx-prof'),
    grab: document.getElementById('plx-grab'),
    endActs: document.getElementById('plx-end-acts'),
    stayOut: document.getElementById('plx-stay-out'),
    hdr: document.getElementById('hdr')
  };
  var openStop = null;       // the stop whose card is open, by id
  var activeDay = 0;
  var mapTab = 'route';      // what the map area is showing: the route, or the accommodation list
  var stayNight = -1;        // the night the accommodation view is on, -1 for none yet
  var stayProv = Object.keys((CFG.stay22 || {}).providers || {})[0] || '';      // which source the list is showing
  var focusId = null;        // whose handle to put the focus back on after the next render

  /** Says out loud what just moved, for whoever is not looking at the screen. */
  function announce(msg) { if (ui.live) ui.live.textContent = msg; }

  /**
   * A road answer arriving is the one redraw nobody asked for, so it waits while a menu or the
   * little form is open rather than pulling them out from under the reader.
   */
  var stale = false;
  function refresh() {
    if (menu || composer || swap) { stale = true; return; }
    stale = false;
    renderBar();
    renderDays();
    syncMap();
    syncStay();
  }

  function render() {
    composer = null;          // a fresh plan never opens with half a form on the screen
    swap = null;              // nor with somebody else's list of stand-ins
    stale = false;
    startBox.hidden = true;
    root.hidden = false;
    root.setAttribute('data-mode', modeKey());
    document.documentElement.classList.add('plx-open');
    if (ui.hdr) ui.hdr.classList.add('is-solid');
    closeMenuX();
    closeStay(true);          // a fresh plan opens on its own map, not on somebody else's night
    stayNight = -1;
    if (ui.paneStay) { ui.paneStay.textContent = ''; ui.paneStay.dataset.stayUrl = ''; }
    focusKey = null;
    overview = true;
    renderBar();
    renderDays();
    if (ui.scroller) ui.scroller.scrollTop = 0;
    var inst = window.EPMap.instance;
    if (inst && inst.resize) inst.resize();
    syncMap();
    syncStay();
    save();
    fetchRoads();
  }
  /** Back to the form. The plan stays where it is — in the link and in the browser. */
  function leave() {
    closeMenuX();
    closeStay(true);
    root.hidden = true;
    startBox.hidden = false;
    document.documentElement.classList.remove('plx-open');
    window.scrollTo(0, 0);
    window.dispatchEvent(new Event('resize'));      // the header works out again whether it sits on the dark band
  }
  function closeMenuX() {
    if (!ui.menu) return;
    ui.menu.hidden = true;
    if (ui.menuBtn) ui.menuBtn.setAttribute('aria-expanded', 'false');
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
    var totals = { stops: 0, km: 0, min: 0, cost: 0, routed: 0 };
    for (var d = 0; d < plan.days; d++) {
      var v = dayView(d);
      totals.stops += v.rows.filter(function (r) { return r.role === 'stop'; }).length;
      totals.km += v.km;
      totals.min += v.visit + v.travel;
      totals.cost += v.cost;
      if (v.routed) totals.routed++;
    }

    // Over the map: what the plan is, in two lines.
    ui.title.textContent = plan.name || ((plan.origin ? plan.origin.label + ' → ' : '') + plan.where.label);
    ui.sub.textContent = [
      plan.days + (plan.days === 1 ? ' zi' : ' zile'),
      totals.stops + (totals.stops === 1 ? ' oprire' : ' opriri'),
      nf(totals.km) + ' km' + (totals.routed === plan.days ? ' pe șosea' : ' (estimat)'),
      (CFG.paces[plan.pace] || ['Normal'])[0]
    ].join(' · ');
    ui.badge.textContent = '';
    ui.badge.appendChild(icon((MODES[modeKey()] || [])[1] || 'pi-car'));
    ui.badge.title = (MODES[modeKey()] || ['Mașină'])[0];

    // Everything you can do to the plan as a whole: behind the three dots, and again where the list ends.
    var fill = function (host, cls, shut) {
      if (!host) return;
      host.textContent = '';
      var item = function (ic, label, fn) { var b = btn(ic, label, fn, cls); host.appendChild(b); return b; };
      item('link', 'Copiază link', function (b) {
        save();
        var done = function () {
          b.classList.add('is-done');
          b.querySelector('span').textContent = 'Copiat';
          setTimeout(function () { b.classList.remove('is-done'); b.querySelector('span').textContent = 'Copiază link'; shut(); }, 1300);
        };
        if (navigator.clipboard) navigator.clipboard.writeText(location.href).then(done, done);
        else done();
      });
      item('pl-regen', 'Regenerează', function () { shut(); generate(); render(); });
      item('gear-six', 'Schimbă datele', leave);
      item('calendar-blank', 'Calendar (.ics)', function () { shut(); downloadIcs(); });
      item('printer', 'Tipărește', function () { shut(); window.print(); });
      item('user-circle', 'Salvează în cont', savePlan);
      if (token()) item('list', 'Planurile mele', function (b) { shut(); openSaved(b); });
    };
    fill(ui.menu, 'plx-menu-i', closeMenuX);
    fill(ui.endActs, 'pl-btn', function () {});

    // At the head of the list: what it costs and where it sleeps.
    ui.bar.textContent = '';
    var box = el('div', 'pl-bar-in');
    if (totals.cost > 0) box.appendChild(el('span', 'pl-bar-sell', 'de la ' + lei(totals.cost) + ' bilete'));
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
      var nem = el('span', '');
      nem.appendChild(placeIcon('moon'));
      nem.setAttribute('aria-hidden', 'true');
      nb.appendChild(nem);
      nb.appendChild(document.createTextNode(slept
        ? slept + (slept === 1 ? ' noapte' : ' nopți') + ' · ' + towns.join(', ')
        : 'Nicio noapte pe traseu'));
      nb.addEventListener('click', jumpToNight);
      box.appendChild(nb);
    }
    if (box.firstChild) ui.bar.appendChild(box);

    // The days, as buttons on the map: where you are, and a way to jump.
    ui.rail.textContent = '';
    var all = el('button', 'plx-day', 'Tot traseul');
    all.type = 'button';
    all.dataset.day = '-1';
    ui.rail.appendChild(all);
    for (var k = 0; k < plan.days; k++) {
      var pb = el('button', 'plx-day', 'Ziua ' + (k + 1));
      pb.type = 'button';
      pb.dataset.day = String(k);
      var dt = dateOffset(k);
      if (dt) pb.appendChild(el('small', '', dt.toLocaleDateString('ro-RO', { weekday: 'short', day: 'numeric', month: 'short' })));
      ui.rail.appendChild(pb);
    }
    paintRail();
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
    if (swap && swap.focus) {
      swap.focus = false;
      var sp = ui.list.querySelector('.pl-swap');
      if (sp) {
        var sf = sp.querySelector('.pl-swap-hit') || sp.querySelector('input');
        if (sf) sf.focus();
        sp.scrollIntoView({ block: 'nearest' });
      }
    }
    markFocus();      // the nodes are new; the one the map is on gets its mark back
  }

  function dayCard(d) {
    var view = dayView(d);
    var stops = view.rows.filter(function (r) { return r.role === 'stop'; });
    var box = el('section', 'pl-day' + (d === activeDay ? ' is-active' : ''));
    box.dataset.day = String(d);

    var head = el('header', 'pl-day-head'), tip = null;
    var hl = el('div', 'pl-day-headings');
    var num = el('span', 'pl-day-n');
    num.appendChild(el('small', '', 'ziua'));
    num.appendChild(el('b', '', String(d + 1)));
    num.setAttribute('aria-hidden', 'true');
    head.appendChild(num);
    hl.appendChild(el('h3', 'pl-day-h', dateLabel(plan.from, d)));
    var sub = el('p', 'pl-day-sub');
    sub.textContent = stops.length
      ? stops.length + (stops.length === 1 ? ' oprire · ' : ' opriri · ') + nf(view.km) + ' km' +
        (view.routed ? ' pe șosea' : (view.pending ? ' (se calculează…)' : ' (estimat)')) + ' · ' +
        clock(DAY_START) + '–' + clock(view.end) + ' · ' + hm(view.travel) + ' pe drum'
      : 'Zi liberă — adaugă ceva sau regenerează.';
    hl.appendChild(sub);
    if (d === 0 && stops.length > 1) {
      tip = el('p', 'pl-day-tip',
        'Trage de numărul din stânga ca să muți o oprire, în zi sau în altă zi, iar cu + pui o oprire de-a ta între două locuri. Harta urmărește oprirea la care ai ajuns cu lista.');
    }
    head.appendChild(hl);

    var acts = el('div', 'pl-day-acts');
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
    if (tip) box.appendChild(tip);      // under the heading, not in it: the heading stays one line when it sticks

    var ol = el('ol', 'pl-stops');
    var n = 0;
    view.rows.forEach(function (r) {
      if (r.role === 'stop') {
        if (!r.e.own) n++;                       // your own stops take time, not a number on the map
        ol.appendChild(insertSlot(d, r.pos, r)); // the gap above this stop: the drive to it, and room for a stop of your own
        ol.appendChild(stopItem(d, r, n));
        // what could take this stop's place opens right under it, so it is never in doubt which one goes
        if (swap && swap.day === d && swap.pos === r.pos && swap.id === r.e.id) ol.appendChild(swapItem());
      } else {
        var edge = edgeItem(r);
        edge.dataset.day = String(d);
        edge.dataset.role = r.role;
        ol.appendChild(edge);
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

  /**
   * A stop is one line when closed — what, where, when, for how long — and opens on a tap into
   * everything that can be done to it. Only one is open at a time; the open one is also the one the
   * map is on.
   */
  function stopItem(d, r, n) {
    var e = r.e, open = openStop === e.id;
    var li = el('li', 'pl-stop' + (e.own ? ' is-own' : '') + (open ? ' is-open' : ''));
    li.dataset.day = String(d);
    li.dataset.pos = String(r.pos);
    li.dataset.id = e.id;
    li.appendChild(gripFor(d, r, n));

    var card = el('div', 'pl-stop-card' + (plan.locked[e.id] ? ' is-locked' : '') + (e.bookable ? ' is-sell' : '') +
      (swap && swap.day === d && swap.pos === r.pos ? ' is-swapping' : ''));

    var main = el('button', 'pl-stop-main');
    main.type = 'button';
    main.setAttribute('aria-expanded', String(open));
    var media = el('span', 'pl-stop-media');
    if (e.img) {
      var img = el('img');
      img.src = thumb(e.img, 160, 200);
      img.alt = '';
      img.loading = 'lazy';
      img.decoding = 'async';
      media.appendChild(img);
    } else {
      media.appendChild(placeIcon(e.emoji, 'pin', 'ic'));
    }
    main.appendChild(media);

    var text = el('span', 'pl-stop-text');
    text.appendChild(el('span', 'pl-stop-title', e.name));
    var meta = el('span', 'pl-stop-meta', e.own ? ownMeta(e) : [e.type, e.city].filter(Boolean).join(' · '));
    text.appendChild(meta);
    var when = el('span', 'pl-stop-when');
    when.appendChild(el('em', '', clock(r.start) + '–' + clock(r.start + r.dur)));
    when.appendChild(el('i', '', hm(r.dur)));
    if (e.bookable || e.price > 0) {
      var tag = el('span', 'pl-sell');
      tag.appendChild(icon('ticket'));
      tag.appendChild(document.createTextNode(e.price > 0 ? 'de la ' + lei(e.price) : 'are bilete'));
      when.appendChild(tag);
    }
    text.appendChild(when);
    main.appendChild(text);
    main.appendChild(icon('caret-down', 'ic pl-stop-car'));
    main.addEventListener('click', function () { toggleStop(e.id); });
    card.appendChild(main);

    // ---- what opens under it
    var more = el('div', 'pl-stop-more');
    var clip = el('div', '');
    var inn = el('div', 'pl-stop-in');
    // What the place says about itself. It is asked for when the card first opens (the pin dataset
    // carries no descriptions), and a place with nothing of its own to say shows no text at all.
    var note = el('p', 'pl-stop-note');
    note.dataset.about = e.id;
    if (e.own) note.textContent = ownMeta(e) + '.';
    else if (aboutCache[e.id]) note.textContent = aboutCache[e.id];
    else note.hidden = true;
    inn.appendChild(note);
    if (e.approx) inn.appendChild(el('p', 'pl-stop-note is-small', 'Poziția pe hartă e aproximativă.'));
    if (open) about(e);

    var act = function (ic, label, fn, cls) {
      var b = el('button', 'pl-ab' + (cls ? ' ' + cls : ''));
      b.type = 'button';
      if (ic) b.appendChild(ic === 'trash' ? glyph('trash') : icon(ic));
      b.appendChild(el('span', '', label));
      b.addEventListener('click', fn);
      return b;
    };
    var setDur = function (by) {
      var m = Math.max(10, Math.min(360, r.dur + by));
      if (m === r.dur) return;
      if (e.own && plan.extra[e.id]) plan.extra[e.id].minutes = m;
      else plan.custom[e.id] = m;
      plan.locked[e.id] = 1;
      announce(e.name + ': ' + hm(m) + '.');
      after();
    };
    var row = el('div', 'pl-acts');
    var durs = el('span', 'pl-durs');
    durs.setAttribute('role', 'group');
    durs.setAttribute('aria-label', 'Cât stai la ' + e.name);
    var less = el('button', '', '−');
    less.type = 'button';
    less.setAttribute('aria-label', 'Cu 15 minute mai puțin');
    less.addEventListener('click', function () { setDur(-15); });
    var plus = el('button', '', '+');
    plus.type = 'button';
    plus.setAttribute('aria-label', 'Cu 15 minute mai mult');
    plus.addEventListener('click', function () { setDur(15); });
    durs.appendChild(less);
    durs.appendChild(el('output', '', hm(r.dur)));
    durs.appendChild(plus);
    var tip = el('button', 'pl-tip');
    tip.type = 'button';
    tip.setAttribute('data-tip', e.own
      ? 'Durata e cea pe care o alegi tu.'
      : 'Durata e o estimare pe tip de obiectiv. Verifică programul înainte de drum.');
    tip.title = tip.getAttribute('data-tip');
    tip.setAttribute('aria-label', 'Despre durată');
    tip.setAttribute('aria-expanded', 'false');
    tip.appendChild(icon('info'));
    var tipText = el('p', 'pl-stop-note is-small', tip.getAttribute('data-tip'));
    tipText.hidden = true;
    tip.addEventListener('click', function () { tipText.hidden = !tipText.hidden; tip.setAttribute('aria-expanded', String(!tipText.hidden)); });
    row.appendChild(tip);
    row.appendChild(durs);
    var count = (plan.stops[d] || []).length;
    var up = act('arrow-right', 'Mai devreme', function () {
      if (!relocate(d, r.pos, d, r.pos - 1)) return;
      focusId = e.id;
      after();
    }, 'is-sq is-up');
    up.disabled = r.pos === 0;
    var down = act('arrow-right', 'Mai târziu', function () {
      if (!relocate(d, r.pos, d, r.pos + 2)) return;
      focusId = e.id;
      after();
    }, 'is-sq is-down');
    down.disabled = r.pos >= count - 1;
    row.appendChild(up);
    row.appendChild(down);
    inn.appendChild(row);
    inn.appendChild(tipText);

    var row2 = el('div', 'pl-acts');
    // A place out of the catalogue can be traded for another one nearby. A stop of your own is
    // not swapped but renamed: those are your words, and the catalogue has no opinion on them.
    if (!e.own) row2.appendChild(act('pl-regen', 'Înlocuiește', function () { openSwap(d, r.pos); }));
    if (e.own && plan.extra[e.id]) {
      row2.appendChild(act('', 'Redenumește', function () {
        var now = plan.extra[e.id].name || '';
        var next = window.prompt('Cum se numește oprirea?', now);
        if (next === null) return;
        next = next.replace(/\s+/g, ' ').trim().slice(0, 60);
        if (!next || next === now) return;
        plan.extra[e.id].name = next;
        focusId = e.id;
        announce('Oprirea se numește acum ' + next + '.');
        after();
      }));
    }
    if (plan.days > 1) {
      var mv = el('label', 'pl-ab pl-move');
      mv.appendChild(icon('calendar-blank'));
      var ms = el('select');
      ms.setAttribute('aria-label', 'Mută ' + e.name + ' în altă zi');
      var m0 = el('option', '', 'Mută în ziua…');
      m0.value = '';
      ms.appendChild(m0);
      for (var k = 0; k < plan.days; k++) {
        if (k === d) continue;
        var dk = dateOffset(k);
        var mo = el('option', '', 'Ziua ' + (k + 1) + (dk ? ' · ' + dk.toLocaleDateString('ro-RO', { weekday: 'short', day: 'numeric', month: 'short' }) : ''));
        mo.value = String(k);
        ms.appendChild(mo);
      }
      ms.addEventListener('change', function () {
        var to = parseInt(ms.value, 10);
        if (isNaN(to) || !relocate(d, r.pos, to, (plan.stops[to] || []).length)) return;
        focusId = e.id;
        announce(e.name + ' a trecut în ziua ' + (to + 1) + '.');
        after();
      });
      mv.appendChild(ms);
      row2.appendChild(mv);
    }
    row2.appendChild(act('trash', 'Scoate', function () { remove(d, r.pos); }, 'is-danger'));
    if (e.href) {
      var go = el('a', 'pl-ab' + (e.bookable || e.price > 0 ? ' is-go' : ''));
      go.href = e.href;
      go.target = '_blank';
      go.rel = 'noopener';
      go.appendChild(icon(e.bookable || e.price > 0 ? 'ticket' : 'arrow-right'));
      go.appendChild(el('span', '', e.bookable || e.price > 0 ? 'Vezi bilete' : 'Detalii'));
      row2.appendChild(go);
    }
    inn.appendChild(row2);
    clip.appendChild(inn);
    more.appendChild(clip);
    card.appendChild(more);

    li.appendChild(card);
    return li;
  }
  var aboutCache = {};       // stop id -> its description; '' once we know there is none
  function about(e) {
    if (!e || e.own) return;
    var show = function () {
      [].forEach.call(ui.list.querySelectorAll('.pl-stop-note[data-about]'), function (p) {
        if (p.dataset.about !== e.id) return;
        p.textContent = aboutCache[e.id] || '';
        p.hidden = !aboutCache[e.id];
      });
    };
    if (aboutCache[e.id] !== undefined) { show(); return; }
    aboutCache[e.id] = '';
    fetch('/api/place.php?id=' + encodeURIComponent(e.id) + (e.kind === 'location' ? '&kind=location' : ''), { credentials: 'omit' })
      .then(function (x) { return x.json(); })
      .then(function (j) { aboutCache[e.id] = (j && j.ok && j.text) ? String(j.text) : ''; show(); })
      .catch(function () {});
  }

  /** Opens one card and closes the rest, without redrawing the list; the map goes to the one that opened. */
  function toggleStop(id) {
    openStop = openStop === id ? null : id;
    if (openStop) about(entry(openStop));
    var hit = null;
    [].forEach.call(ui.list.querySelectorAll('.pl-stop'), function (li) {
      var on = li.dataset.id === openStop;
      setCls(li, 'is-open', on);
      var m = li.querySelector('.pl-stop-main');
      if (m) m.setAttribute('aria-expanded', String(on));
      if (on) hit = li;
    });
    if (!hit) return;
    setFocus(hit);
    setTimeout(function () {
      var box = hit.getBoundingClientRect(), view = ui.scroller.getBoundingClientRect();
      if (box.bottom > view.bottom - 8) ui.scroller.scrollBy({ top: box.bottom - view.bottom + 16, behavior: 'smooth' });
    }, 380);
  }

  /** The number is the handle: drag it, or take the stop with Enter and move it with the arrows. */
  function gripFor(d, r, n) {
    var e = r.e;
    var b = el('button', 'pl-grip');
    b.type = 'button';
    b.title = 'Trage ca să muți oprirea';
    var face = el('span', 'pl-grip-n', e.own ? null : String(n));
    if (e.own) face.appendChild(placeIcon(e.emoji, 'clock'));
    face.setAttribute('aria-hidden', 'true');
    b.appendChild(face);
    b.appendChild(glyph('grip', 'ic pl-grip-ic'));
    b.appendChild(el('span', 'sr', 'Mută ' + e.name + '. Trage cu mausul, sau apasă Enter și folosește săgețile.'));
    b.addEventListener('pointerdown', function (ev) { dragStart(ev, stopLi(b)); });
    b.addEventListener('keydown', function (ev) { gripKey(ev, stopLi(b)); });
    return b;
  }
  function stopLi(node) { return node.closest('.pl-stop'); }

  var menu = null;
  function closeMenu() {
    if (!menu) return;
    menu = null;
    if (stale) refresh();
  }

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
  function after() { stale = false; closeMenu(); if (swap && !swapOk()) swap = null; tidy(); renderBar(); renderDays(); syncMap(); syncStay(); save(); fetchRoads(); }

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
    var edge = 84, box = ui.scroller.getBoundingClientRect();
    scrollBy = y < box.top + edge ? -Math.ceil((box.top + edge - y) / 5)
      : (y > box.bottom - edge ? Math.ceil((y - (box.bottom - edge)) / 5) : 0);
    if (scrollBy && !scroller) {
      scroller = setInterval(function () {
        if (!drag) return;
        ui.scroller.scrollTop += scrollBy;
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
      var em = el('span', '');
      em.appendChild(placeIcon(e && e.emoji));
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
    if (ui.menu && !ui.menu.hidden) { closeMenuX(); if (ui.menuBtn) ui.menuBtn.focus(); return; }
    if (mapTab === 'stay') { closeStay(); return; }
    if (swap && !grab) closeSwap();
  });

  /* ---------- a stop of your own: the little form that makes one ---------- */

  var composer = null;

  /**
   * The gap between two stops: hovering it, or reaching it with the keyboard, offers a + that puts a stop of your own
   * exactly there. It gets out of the way while something is being dragged, where the drop line already says enough.
   */
  function insertSlot(d, pos, r) {
    var li = el('li', 'pl-ins');
    li.dataset.pos = String(pos);
    var b = el('button', 'pl-ins-btn');
    b.type = 'button';
    b.title = 'Adaugă o oprire de-a ta aici';
    b.appendChild(icon('plus'));
    b.appendChild(el('span', 'sr', 'Adaugă o oprire de-a ta aici, între opriri'));
    b.addEventListener('click', function () { openComposer(d, pos); });
    li.appendChild(b);
    // the drive to the stop below, where there is one
    if (r && (r.legKm > 0 || r.legMin > 0)) {
      var leg = el('span', 'pl-ins-leg');
      leg.appendChild(icon((MODES[modeKey()] || [])[1] || 'pi-car'));
      leg.appendChild(document.createTextNode(hm(r.legMin) + ' · ' + km1(r.legKm) + ' km'));
      li.appendChild(leg);
    } else if (r && r.off) {
      li.appendChild(el('span', 'pl-ins-leg is-off', 'fără drum în plus'));
    }
    return li;
  }

  function openComposer(d, pos) {
    var first = PRESETS[0] || ['Pauză', 'clock', 30];
    swap = null;              // one panel at a time inside a day
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
      var em = el('span', '');
      em.appendChild(placeIcon(p[1], 'clock'));
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
        emoji: placeKey(c.emoji, 'clock'),
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

  /**
   * Adding to a day. Putting the cursor in the box is enough: it offers places near where the day
   * already is, judged the way the plan was — the interests declared, the company, how you travel —
   * and never something the plan already holds. Typing turns it into a search of both catalogues.
   */
  function addControl(d) {
    var wrap = el('div', 'pl-add');
    var inp = el('input');
    inp.type = 'search';
    inp.placeholder = 'Adaugă o atracție, o experiență sau o locație…';
    inp.setAttribute('aria-label', 'Adaugă ceva în ziua ' + (d + 1) + ': alege din sugestii sau caută');
    inp.autocomplete = 'off';
    var list = el('ul', 'pl-add-list');
    list.hidden = true;
    var pick = function (id) { inp.value = ''; list.hidden = true; add(d, id); };

    var suggest = function () {
      list.textContent = '';
      var from = lastPlaced(d) || firstPlaced(d) || (plan.where ? { lat: plan.where.lat, lng: plan.where.lng } : null);
      var rows = from ? nearSuggestions(from, SWAP.near_km || 60, wantedTypes()) : [];
      if (from && !rows.length) rows = nearSuggestions(from, SWAP.far_km || 120, null);
      rows.sort(function (x, y) { return x.d - y.d; });
      rows = rows.slice(0, 6);
      if (!rows.length) { list.hidden = true; return; }
      list.appendChild(el('li', 'pl-add-h', 'Din apropiere, pe gustul tău. Sau scrie ce cauți.'));
      rows.forEach(function (c) { list.appendChild(swapOption(c, pick)); });
      list.hidden = false;
    };
    var search = function () {
      list.textContent = '';
      var hits = searchCatalogue(inp.value, 8);
      hits.forEach(function (h) {
        var li = el('li');
        var b = el('button', 'pl-add-hit');
        b.type = 'button';
        b.appendChild(el('b', '', h.name));
        b.appendChild(el('small', '', (h.book ? 'se rezervă · ' : '') + (h.city || '')));
        b.addEventListener('click', function () { pick(h.id); });
        li.appendChild(b);
        list.appendChild(li);
      });
      if (!hits.length) list.appendChild(el('li', 'pl-add-h', 'Nu am găsit nimic cu numele ăsta în catalog.'));
      list.hidden = false;
    };
    var run = function () { if (inp.value.trim().length < 2) suggest(); else search(); };
    inp.addEventListener('focus', run);
    inp.addEventListener('input', debounce(run, 140));
    inp.addEventListener('blur', function () { setTimeout(function () { list.hidden = true; }, 200); });
    wrap.appendChild(inp);
    wrap.appendChild(list);
    return wrap;
  }

  /* ---------- swapping a stop for another place nearby ----------
   *
   * The generator chose this stop; the traveller should not have to argue with the catalogue to
   * change it. «Înlocuiește» opens, under the stop itself, a ready-made short list of places that
   * could stand in for it. Which places get on the list is judged the same way the whole plan was:
   * how close they are to the stop that is going, then the interests that were declared and the
   * company that is travelling — so a family with children is not handed another monastery. What
   * the plan already holds is never offered twice; what was thrown out earlier may come back, and
   * says so. The order on screen is the map's — nearest first — because that is what a swap asks.
   *
   * Nothing here costs the page anything at load: it is the dataset already in memory, measured.
   */

  var swap = null;                                  // { day, pos, id, query, focus } while open
  var SWAP = CFG.swap || {};
  function swapCount() { return Math.max(3, Math.min(12, SWAP.count || 8)); }
  function swapKmPoint() { return SWAP.km_per_point || 7; }

  /** Whether the stop the open panel is about is still where the panel thinks it is. */
  function swapOk() {
    return !!(swap && plan.stops[swap.day] && plan.stops[swap.day][swap.pos] === swap.id);
  }
  /** Every catalogue stop the plan holds, on any day: none of them is an alternative to itself. */
  function inPlan() {
    var seen = {};
    (plan.stops || []).forEach(function (day) {
      (day || []).forEach(function (id) { if (!isOwn(id)) seen[id] = 1; });
    });
    return seen;
  }

  /**
   * What could take this stop's place, inside `reach` kilometres of it. `want` is the interest
   * filter, exactly the one the generator applied when it built the plan — pass null to drop it.
   * Distance is the spine of the score — an alternative is only one if the day still holds together
   * around it — and the plan's own weights sit on top: the company you keep, a photo to recognise
   * the place by, a ticket you can actually buy. Something you removed earlier is allowed back, one
   * step down the list.
   */
  function swapSuggestions(id, reach, want) {
    var from = entry(id);
    if (!from || typeof from.lat !== 'number') return [];
    return nearSuggestions(from, reach, want);
  }
  /** The same judgement around any point: what a day could take on, near where it already is. */
  function nearSuggestions(from, reach, want) {
    var have = inPlan(), per = swapKmPoint();
    var f = D.f, out = [];

    for (var i = 0; i < D.rows.length; i++) {
      var r = D.rows[i], slug = r[f.slug];
      if (have[slug]) continue;
      var tSlug = r[f.type] >= 0 ? D.types[r[f.type]][0] : '';
      if (want && !want[tSlug]) continue;
      var lat = r[f.lat_e5] / 1e5, lng = r[f.lng_e5] / 1e5;
      var d = km(from.lat, from.lng, lat, lng);
      if (d > reach) continue;
      var flags = r[f.flags], s = 0;
      if (flags & D.flags.image) s += 1.5;
      if (flags & (D.flags.activities || 4)) s += 4;
      s += companyWeight(tSlug) + modeWeight(tSlug);      // who travels, and how, tilts the types; it never rules one out
      if (plan.removed[slug]) s -= 1.5;
      s -= d / per;
      out.push({ id: slug, d: d, score: s });
    }

    (CFG.bookables || []).forEach(function (b) {
      var bid = 'b:' + b[1];
      if (have[bid]) return;
      var bd = km(from.lat, from.lng, b[6], b[7]);
      if (bd > reach) return;
      out.push({ id: bid, d: bd, score: 5 - bd / per - (plan.removed[bid] ? 1.5 : 0) });
    });

    out.sort(function (a, b) { return b.score - a.score; });
    return out.slice(0, swapCount());
  }

  /**
   * The list as it is read: the weights choose which places are worth offering, the map decides the
   * order they are read in. Two ways out of an empty circle rather than an empty panel — widen it
   * once, and only then let go of the interest filter, saying so when that happens. The distance is
   * printed on every row either way, so nothing about the reach has to be taken on trust.
   */
  function swapList(id) {
    var near = SWAP.near_km || 60, far = SWAP.far_km || 120, want = wantedTypes();
    var rows = swapSuggestions(id, near, want), loose = false;
    if (!rows.length) rows = swapSuggestions(id, far, want);
    if (!rows.length && want) {
      loose = true;
      rows = swapSuggestions(id, near, null);
      if (!rows.length) rows = swapSuggestions(id, far, null);
    }
    rows.sort(function (a, b) { return a.d - b.d; });
    return { rows: rows, loose: loose };
  }

  function openSwap(d, pos) {
    var id = (plan.stops[d] || [])[pos];
    if (!id || isOwn(id)) return;
    composer = null;
    swap = { day: d, pos: pos, id: id, query: '', focus: true };
    activeDay = d;
    announce('Caut ce ar putea lua locul lui ' + stopName(id) + '.');
    after();
  }
  function closeSwap(quiet) {
    if (!swap) return;
    var id = swap.id;
    swap = null;
    focusId = id;
    if (!quiet) announce('Am renunțat. ' + stopName(id) + ' rămâne în plan, neschimbată.');
    after();
  }

  /**
   * The swap itself: the new place lands on the position the old one held, the day keeps its order,
   * and both decisions are written down — the newcomer is locked so a regeneration leaves it alone,
   * the one that went is marked removed so it does not walk back in on its own.
   */
  function applySwap(newId) {
    if (!swap || !swapOk()) { closeSwap(true); return; }
    var d = swap.day, oldId = swap.id, oldName = stopName(oldId);
    var ne = entry(newId);
    if (!ne || newId === oldId) { closeSwap(true); return; }

    // The search reaches the whole catalogue, so it can land on something the plan already holds
    // somewhere else. It moves here rather than appearing twice.
    for (var k = 0; k < plan.stops.length; k++) {
      var at = plan.stops[k].indexOf(newId);
      if (at !== -1) plan.stops[k].splice(at, 1);
    }
    var pos = plan.stops[d].indexOf(oldId);
    if (pos === -1) { closeSwap(true); return; }
    plan.stops[d].splice(pos, 1, newId);

    plan.removed[oldId] = 1;
    delete plan.locked[oldId];
    delete plan.custom[oldId];
    delete plan.removed[newId];
    plan.locked[newId] = 1;
    if (grab && grab.id === oldId) grab = null;
    activeDay = d;
    swap = null;
    focusId = newId;
    announce(ne.name + ' ia locul lui ' + oldName + ', pe aceeași poziție în ziua ' + (d + 1) +
      '. Am recalculat orele și kilometrii.');
    after();
  }

  /** One place on the list: its photo, what it is, how far it is and how long it takes. */
  function swapOption(c, onPick) {
    var li = el('li');
    var e = entry(c.id);
    if (!e) return li;
    var b = el('button', 'pl-swap-hit');
    b.type = 'button';

    var media = el('span', 'pl-swap-media');
    if (e.img) {
      var img = el('img');
      img.src = thumb(e.img, 120, 120);
      img.alt = '';
      img.loading = 'lazy';
      img.decoding = 'async';
      media.appendChild(img);
    } else {
      var ph = el('span', '');
      ph.appendChild(placeIcon(e.emoji));
      ph.setAttribute('aria-hidden', 'true');
      media.appendChild(ph);
    }
    b.appendChild(media);

    var text = el('span', 'pl-swap-text');
    text.appendChild(el('b', '', e.name));
    text.appendChild(el('span', 'pl-swap-meta', [e.type, e.city].filter(Boolean).join(' · ')));

    var line = el('span', 'pl-swap-line');
    var away = estKm(c.d);      // corrected for real roads, like every other distance on the page
    line.appendChild(el('span', 'pl-swap-km', away < 0.1 ? 'la câțiva pași' : 'la ' + km1(away) + ' km'));
    line.appendChild(el('span', 'pl-swap-dur', hm(e.dur)));
    if (e.bookable || e.price > 0) {
      var tag = el('span', 'pl-sell');
      tag.appendChild(icon('ticket'));
      tag.appendChild(document.createTextNode(e.price > 0 ? 'de la ' + lei(e.price) : 'are bilete'));
      line.appendChild(tag);
    }
    if (plan.removed[c.id]) line.appendChild(el('span', 'pl-swap-back', 'ai scos-o mai devreme'));
    text.appendChild(line);
    b.appendChild(text);

    b.addEventListener('click', function () { (onPick || applySwap)(c.id); });
    li.appendChild(b);
    return li;
  }

  /** The panel, in the day, under the stop it is about — a sibling of the form that makes a stop. */
  function swapItem() {
    var s = swap;
    var e = entry(s.id);
    var li = el('li', 'pl-swap');
    var box = el('div', 'pl-swap-in');
    li.appendChild(box);
    if (!e) return li;

    box.appendChild(el('p', 'pl-swap-h', 'În locul lui ' + e.name));
    var bits = [e.type || 'Oprire'];
    if (e.city) bits.push(e.city);
    box.appendChild(el('p', 'pl-swap-sub', bits.join(' · ') + ' · ' + hm(plan.custom[s.id] || e.dur) +
      ' în plan. Alege din apropiere, sau caută tu altceva.'));

    var res = swapList(s.id);
    if (res.loose) {
      box.appendChild(el('p', 'pl-swap-loose',
        'Prin apropiere nu e nimic din ce ai bifat că te interesează, așa că îți arăt ce mai este pe-acolo.'));
    }
    if (res.rows.length) {
      var ul = el('ul', 'pl-swap-list');
      res.rows.forEach(function (c) { ul.appendChild(swapOption(c)); });
      box.appendChild(ul);
    } else {
      box.appendChild(el('p', 'pl-swap-empty',
        'Nu am în catalog niciun alt loc prin apropierea ei. Caută tu mai jos — merge oriunde în țară.'));
    }

    var find = el('div', 'pl-swap-find');
    var inp = el('input');
    inp.type = 'search';
    inp.autocomplete = 'off';
    inp.placeholder = 'Sau caută altceva: o atracție, o experiență, o locație…';
    inp.setAttribute('aria-label', 'Caută altceva în locul opririi ' + e.name);
    inp.value = s.query || '';
    var hits = el('ul', 'pl-swap-hits');
    hits.hidden = true;
    inp.addEventListener('input', debounce(function () {
      s.query = inp.value;
      hits.textContent = '';
      var have = inPlan();
      var found = searchCatalogue(inp.value, 8).filter(function (h) { return h.id !== s.id; });
      found.forEach(function (h) {
        var row = el('li');
        var hb = el('button', 'pl-add-hit');
        hb.type = 'button';
        hb.appendChild(el('b', '', h.name));
        hb.appendChild(el('small', '', (h.book ? 'se rezervă · ' : '') + (h.city || '') +
          (have[h.id] ? ' · e deja în plan, se mută aici' : '')));
        hb.addEventListener('click', function () { applySwap(h.id); });
        row.appendChild(hb);
        hits.appendChild(row);
      });
      hits.hidden = !found.length;
    }, 140));
    find.appendChild(inp);
    find.appendChild(hits);
    box.appendChild(find);

    var acts = el('div', 'pl-swap-acts');
    var no = el('button', 'pl-btn pl-swap-no', 'Renunță');
    no.type = 'button';
    no.addEventListener('click', function () { closeSwap(); });
    acts.appendChild(no);
    box.appendChild(acts);
    return li;
  }

  /* ---------------------------------------------------------------- nights on the page
   *
   * A night card sits between two days and carries three decisions: see what there is, sleep
   * somewhere else, or do not sleep on the road at all. The list of places to sleep is Stay22's,
   * in an iframe built only when somebody asks for it, and it takes the place of our own map — in
   * the same frame — until the traveller sends it back.
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
    var em = el('span', 'pl-night-em');
    em.appendChild(placeIcon('moon'));
    em.setAttribute('aria-hidden', 'true');
    h.appendChild(em);
    var ht = el('span', 'pl-night-t');
    ht.appendChild(el('b', '', n && !n.skip ? 'Noaptea în ' + n.name : nightTitle(i)));
    ht.appendChild(el('small', '', n && !n.skip
      ? (dateOffset(i) ? nightRange(i) : 'între ziua ' + (i + 1) + ' și ziua ' + (i + 2)) + ' · ' + partyLine(n.rooms)
      : 'între ziua ' + (i + 1) + ' și ziua ' + (i + 2)));
    h.appendChild(ht);
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
      wrap.appendChild(nightBudget(i, n));
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

    }
    return wrap;
  }

  /** How much the night may cost at most. The accommodation list filters on it; "Orice preț" shows everything. */
  function nightBudget(i, n) {
    var lab = el('label', 'pl-night-rooms');
    lab.appendChild(el('span', '', 'Buget'));
    var sel = el('select');
    var any = el('option', '', 'Orice preț');
    any.value = '0';
    if (!n.maxprice) any.selected = true;
    sel.appendChild(any);
    var budgets = CFG.budgets && CFG.budgets.length ? CFG.budgets.slice() : [200, 300, 500, 700, 1000];
    if (n.maxprice > 0 && budgets.indexOf(n.maxprice) === -1) budgets.push(n.maxprice);
    budgets.sort(function (x, y) { return x - y; }).forEach(function (v) {
      var o = el('option', '', 'până în ' + nf(v) + ' lei');
      o.value = String(v);
      if (v === n.maxprice) o.selected = true;
      sel.appendChild(o);
    });
    sel.addEventListener('change', function () {
      setNight(i, { maxprice: clampInt(sel.value, 0, 100000, 0) });
      afterNight(i);
    });
    lab.appendChild(sel);
    return lab;
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
  function syncStay() {
    if (ui.paneStay && mapTab === 'stay') {
      stayInto(ui.paneStay, stayNight);
      if (ui.stayOut && stayNight >= 0) ui.stayOut.href = stayLink(stayNight);
    }
    stayButton();
  }
  /** The night the reader is at: the one under the heading, else the one that follows the day on screen. */
  function currentNight() {
    if (!nightCount()) return -1;
    var m = /^n:(\d+)$/.exec(focusKey || '');
    var i = m ? +m[1] : Math.min(activeDay, nightCount() - 1);
    var n = nightOf(i);
    return (n && !n.skip) ? i : firstOpenNight();
  }
  /** The button at the foot of the map. It names the town once the list has reached a night. */
  function stayButton() {
    if (!ui.stayBtn) return;
    var i = plan ? currentNight() : -1;
    ui.stayBtn.hidden = i < 0 || mapTab === 'stay';
    if (i < 0) return;
    var atNight = /^n:/.test(focusKey || '') && !overview, n = nightOf(i);
    ui.stayBtn.querySelector('span').textContent = (atNight && n) ? 'Arată cazări în ' + n.name : 'Arată cazări disponibile';
    setCls(ui.stayBtn, 'is-hot', atNight);
  }
  /**
   * The accommodation list takes the map's place, in the map's own frame, and hands it back. On a
   * phone the frame grows first: a list of hotels needs more room than a route does.
   */
  function openStay(i) {
    if (i < 0 || !ui.stayBox) return;
    stayNight = i;
    mapTab = 'stay';
    ui.stayBox.hidden = false;
    setCls(root, 'is-stay', true);
    stayInto(ui.paneStay, i);
    if (ui.stayOut) ui.stayOut.href = stayLink(i);
    stayButton();
    if (!wide()) snapMap(0.74);
    var n = nightOf(i);
    announce(n ? 'Am deschis cazările din ' + n.name + ', în locul hărții.' : 'Am deschis cazările.');
    if (ui.stayX) ui.stayX.focus();
  }
  function closeStay(quiet) {
    if (!ui.stayBox || (mapTab !== 'stay' && ui.stayBox.hidden)) return;
    mapTab = 'route';
    ui.stayBox.hidden = true;
    setCls(root, 'is-stay', false);
    stayButton();
    if (!wide()) snapMap(0.46);
    else setTimeout(function () { var inst = window.EPMap.instance; if (inst && inst.resize) inst.resize(); applyFocus(); }, 60);
    if (!quiet) { announce('Harta traseului e la loc.'); if (ui.stayBtn && !ui.stayBtn.hidden) ui.stayBtn.focus(); }
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
      'Opțiunile de cazare vin de la Booking, Expedia, Vrbo ș.a. Dacă alegi o cazare din cele propuse, website-ul va înregistra un comision.'));
    host.appendChild(box);
  }
  function nightRange(i) {
    var a = dateOffset(i), b = dateOffset(i + 1);
    if (!a || !b) return 'o noapte';
    var f = { day: 'numeric', month: 'short' };
    return a.toLocaleDateString('ro-RO', f) + ' → ' + b.toLocaleDateString('ro-RO', f);
  }

  if (ui.stayBtn) ui.stayBtn.addEventListener('click', function () { openStay(currentNight()); });
  if (ui.stayX) ui.stayX.addEventListener('click', function () { closeStay(); });
  // The embed shows one source at a time; the switch rebuilds it for another (the URL changes, so stayInto reloads).
  var provBox = document.getElementById('plx-stay-prov');
  if (provBox) provBox.addEventListener('click', function (ev) {
    var b = ev.target.closest('[data-prov]');
    if (!b || !(STAY.providers || {})[b.dataset.prov]) return;
    stayProv = b.dataset.prov;
    [].forEach.call(provBox.querySelectorAll('[data-prov]'), function (x) { x.setAttribute('aria-pressed', String(x === b)); });
    syncStay();
    announce('Arăt cazările de la ' + b.textContent.trim() + '.');
  });

  /* ---------------------------------------------------------------- the map */

  var mapIds = [];           // the stops that have a pin, in pin order: index -> stop id
  var pinWired = false;

  function syncMap() {
    var inst = window.EPMap.instance;
    if (!inst) return;
    if (!pinWired && inst.onPin) {
      pinWired = true;
      // A pin is a way into the list: the stop comes up under the heading and the map stays put.
      inst.onPin(function (i) {
        var id = mapIds[i];
        var node = id ? ui.list.querySelector('.pl-stop[data-id="' + id.replace(/"/g, '') + '"]') : null;
        if (node) scrollToNode(node, true);
      });
    }
    var view = dayView(activeDay);
    var r = road(activeDay);
    // Only real places get a pin. A stop of your own is time: whatever driving it carried is folded
    // into the next pin, so the kilometres on the map still add up.
    var rows = [], carry = { km: 0, min: 0 };
    mapIds = [];
    view.rows.forEach(function (x) {
      if (x.role !== 'stop') return;
      if (x.e.own) { carry.km += x.legKm; carry.min += x.legMin; return; }
      rows.push(mapRow(x.e, x.legKm + carry.km, x.legMin + carry.min));
      mapIds.push(x.e.id);
      carry.km = 0;
      carry.min = 0;
    });
    if (inst.setColor) inst.setColor(MODE_COLOR[modeKey()] || MODE_COLOR.car);
    inst.setRoute(rows, r ? r.geometry : '');
    // The other days stay on the map, faint: the day on screen is a part of a trip, not all of it.
    if (inst.setGhosts) {
      var ghosts = [];
      for (var d = 0; d < plan.days; d++) {
        if (d === activeDay) continue;
        var rd = road(d);
        if (rd && rd.geometry) { ghosts.push(rd.geometry); continue; }
        var pts = roadPoints(d).map(function (p) { return [p.lat, p.lng]; });
        if (pts.length > 1) ghosts.push(pts);
      }
      inst.setGhosts(ghosts);
    }
    if (ui.note) {
      ui.note.textContent = rows.length
        ? (view.routed
          ? 'Kilometrii și timpii de mers sunt calculați pe șosea (OpenStreetMap), fără trafic și fără opriri.'
          : (view.pending ? 'Se calculează drumul pe șosea…' : 'Drumul nu a putut fi calculat: distanțele sunt estimate din linia dreaptă.'))
        : (view.rows.some(function (x) { return x.role === 'stop'; })
          ? 'Ziua ' + (activeDay + 1) + ' are doar opriri de-ale tale; pe hartă ajung locurile din catalog.'
          : 'Ziua ' + (activeDay + 1) + ' e goală.');
    }
    applyFocus();
    profile();
  }

  /* ---------------------------------------------------------------- the list drives the map
   *
   * Under the list's heading there is always one thing: the day's departure, a stop, the return, or
   * a night. That thing is what the map shows. Reaching another day redraws the route; reaching a
   * stop frames it with its neighbours; reaching a night frames the town and names it on the
   * accommodation button. At the very top of the list the map shows the whole trip.
   */

  var focusKey = null;       // 's:<stop id>', 'e:<day>:<role>' or 'n:<night>'
  var overview = true;       // the whole trip, not one place
  var holdUntil = 0;         // while a jump is scrolling, the list does not get a say

  function focusNodes() { return ui.list.querySelectorAll('.pl-edge, .pl-stop, .pl-night'); }
  function keyOfNode(node) {
    if (node.classList.contains('pl-stop')) return 's:' + node.dataset.id;
    if (node.classList.contains('pl-night')) return 'n:' + node.dataset.night;
    return 'e:' + node.dataset.day + ':' + node.dataset.role;
  }
  function dayOfNode(node) {
    return parseInt(node.classList.contains('pl-night') ? node.dataset.night : node.dataset.day, 10);
  }
  function markFocus() {
    [].forEach.call(ui.list.querySelectorAll('.is-focus'), function (x) { x.classList.remove('is-focus'); });
    if (overview || !focusKey) return;
    var nodes = focusNodes();
    for (var i = 0; i < nodes.length; i++) {
      if (keyOfNode(nodes[i]) === focusKey) { nodes[i].classList.add('is-focus'); break; }
    }
  }
  function paintRail() {
    if (!ui.rail) return;
    var on = overview ? -1 : activeDay;
    [].forEach.call(ui.rail.children, function (b) {
      var is = parseInt(b.dataset.day, 10) === on;
      b.setAttribute('aria-pressed', String(is));
      if (is && !overview && ui.rail.scrollTo) ui.rail.scrollTo({ left: Math.max(0, b.offsetLeft - 70), behavior: 'smooth' });
    });
  }
  function paintActiveDay() {
    [].forEach.call(ui.list.querySelectorAll('.pl-day'), function (c) {
      setCls(c, 'is-active', parseInt(c.dataset.day, 10) === activeDay);
    });
  }
  var applyFocusSoon = debounce(function () { applyFocus(); }, 130);

  function setFocus(node) {
    if (!node) {
      if (overview) return;
      overview = true;
      focusKey = null;
    } else {
      var key = keyOfNode(node);
      if (!overview && key === focusKey) return;
      overview = false;
      focusKey = key;
      var d = dayOfNode(node);
      if (d !== activeDay && d >= 0 && d < plan.days) {
        activeDay = d;
        paintActiveDay();
        markFocus();
        paintRail();
        stayButton();
        syncMap();              // a new day is a new route; it frames the focus itself when it is drawn
        return;
      }
    }
    markFocus();
    paintRail();
    stayButton();
    profile();
    applyFocusSoon();
  }

  /** What the map has to keep clear: the title and the days above, the button and the profile below. */
  function mapPad() {
    var top = ui.top ? ui.top.getBoundingClientRect().height + 16 : 60;
    return { t: top, r: 46, b: (ui.prof && !ui.prof.hidden ? 160 : 78), l: 46 };
  }
  /** A stop is shown with the one before and the one after — unless one of them is a long drive away. */
  function around(at) {
    var here = entry(mapIds[at]), out = [at];
    if (!here) return out;
    var p = at > 0 ? entry(mapIds[at - 1]) : null, q = at < mapIds.length - 1 ? entry(mapIds[at + 1]) : null;
    var dp = p ? km(p.lat, p.lng, here.lat, here.lng) : -1, dq = q ? km(here.lat, here.lng, q.lat, q.lng) : -1;
    if (p && !(dp > 40 && dq >= 0 && dp > dq * 6)) out.push(at - 1);
    if (q && !(dq > 40 && dp >= 0 && dq > dp * 6)) out.push(at + 1);
    return out;
  }
  function applyFocus() {
    var inst = window.EPMap.instance;
    if (!inst || !inst.focusRoute || !plan || root.hidden || mapTab === 'stay') return;
    var o = { pad: mapPad() };
    if (overview || !focusKey) { inst.fitRoute({ pad: o.pad, all: true }); return; }
    var m = /^s:(.+)$/.exec(focusKey);
    if (m) {
      var at = mapIds.indexOf(m[1]);
      if (at !== -1) { inst.focusRoute(at, around(at), o); return; }
      // A stop of your own has no pin: the map stays with the last real place before it.
      var ids = plan.stops[activeDay] || [], pos = ids.indexOf(m[1]), k = -1;
      for (var q = pos - 1; q >= 0 && k === -1; q--) k = mapIds.indexOf(ids[q]);
      if (k !== -1) inst.focusRoute(-1, [k], o); else inst.fitRoute(o);
      return;
    }
    m = /^n:(\d+)$/.exec(focusKey);
    if (m) {
      var n = nightOf(+m[1]);
      if (n) { inst.fitPoints([[n.lat, n.lng]], { pad: o.pad, maxZoom: 12 }); return; }
    }
    inst.fitRoute(o);
  }

  function onListScroll() {
    if (!plan || root.hidden || drag || Date.now() < holdUntil) return;
    if (ui.scroller.scrollTop < 8) { setFocus(null); return; }
    var line = ui.scroller.getBoundingClientRect().top + 104, nodes = focusNodes(), best = null;
    for (var i = 0; i < nodes.length; i++) {
      if (nodes[i].getBoundingClientRect().top <= line) best = nodes[i]; else break;
    }
    setFocus(best || nodes[0] || null);
  }
  function scrollToNode(node, flash) {
    var y = node.getBoundingClientRect().top - ui.scroller.getBoundingClientRect().top + ui.scroller.scrollTop - 78;
    ui.scroller.scrollTo({ top: Math.max(10, y), behavior: 'smooth' });
    if (flash) {
      node.classList.remove('is-flash');
      void node.offsetWidth;
      node.classList.add('is-flash');
    }
  }
  if (ui.scroller) {
    var scrollTick = 0;
    ui.scroller.addEventListener('scroll', function () {
      if (scrollTick) return;
      scrollTick = requestAnimationFrame(function () { scrollTick = 0; onListScroll(); });
    }, { passive: true });
  }
  if (ui.rail) {
    ui.rail.addEventListener('click', function (ev) {
      var b = ev.target.closest('.plx-day');
      if (!b || !plan) return;
      var d = parseInt(b.dataset.day, 10);
      holdUntil = Date.now() + 900;
      if (d < 0) {
        ui.scroller.scrollTo({ top: 0, behavior: 'smooth' });
        setFocus(null);
        return;
      }
      var card = ui.list.querySelector('.pl-day[data-day="' + d + '"]');
      if (!card) return;
      var y = card.getBoundingClientRect().top - ui.scroller.getBoundingClientRect().top + ui.scroller.scrollTop - 4;
      ui.scroller.scrollTo({ top: Math.max(10, y), behavior: 'smooth' });
      setFocus(card.querySelector('.pl-edge, .pl-stop'));
    });
  }
  if (ui.menuBtn) {
    ui.menuBtn.addEventListener('click', function (ev) {
      ev.stopPropagation();
      ui.menu.hidden = !ui.menu.hidden;
      ui.menuBtn.setAttribute('aria-expanded', String(!ui.menu.hidden));
      if (!ui.menu.hidden) { var f = ui.menu.querySelector('button'); if (f) f.focus(); }
    });
    document.addEventListener('click', function (ev) {
      if (!ui.menu.hidden && !ui.menu.contains(ev.target) && ev.target !== ui.menuBtn) closeMenuX();
    });
  }
  var backBtn = document.getElementById('plx-back');
  if (backBtn) backBtn.addEventListener('click', leave);
  [].forEach.call(document.querySelectorAll('[data-plx]'), function (b) {
    b.addEventListener('click', function () {
      var inst = window.EPMap.instance, k = b.dataset.plx;
      if (k === 'size') { snapMap(mapShare() > 0.55 ? 0.3 : 0.72); return; }
      if (!inst) return;
      if (k === 'in' && inst.zoomBy) inst.zoomBy(1);
      else if (k === 'out' && inst.zoomBy) inst.zoomBy(-1);
      else if (k === 'fit') applyFocus();
    });
  });

  /* ---------- how much of the screen the map takes, on a phone ---------- */

  var SNAPS = [0.3, 0.46, 0.72];
  function mapShare() {
    var all = root.getBoundingClientRect().height;
    return all ? ui.map.getBoundingClientRect().height / all : 0.46;
  }
  function snapMap(share) {
    root.classList.add('is-snap');
    root.style.setProperty('--plx-map', (share * 100).toFixed(1) + '%');
    setTimeout(function () {
      root.classList.remove('is-snap');
      var inst = window.EPMap.instance;
      if (inst && inst.resize) inst.resize();
      applyFocus();
    }, 440);
  }
  if (ui.grab) {
    var pulling = false, pulled = false, pullTick = 0;
    ui.grab.addEventListener('pointerdown', function (ev) {
      pulling = true;
      pulled = false;
      ui.grab.setPointerCapture(ev.pointerId);
      root.classList.remove('is-snap');
    });
    ui.grab.addEventListener('pointermove', function (ev) {
      if (!pulling) return;
      pulled = true;
      var box = root.getBoundingClientRect();
      var share = Math.max(0.2, Math.min(0.8, (ev.clientY - box.top + 10) / box.height));
      root.style.setProperty('--plx-map', (share * 100).toFixed(1) + '%');
      if (!pullTick) pullTick = requestAnimationFrame(function () {
        pullTick = 0;
        var inst = window.EPMap.instance;
        if (inst && inst.resize) inst.resize();
      });
    });
    var pullEnd = function () {
      if (!pulling) return;
      pulling = false;
      var now = mapShare();
      if (!pulled) { snapMap(now > 0.55 ? 0.3 : 0.72); return; }
      snapMap(SNAPS.reduce(function (x, y) { return Math.abs(y - now) < Math.abs(x - now) ? y : x; }));
    };
    ui.grab.addEventListener('pointerup', pullEnd);
    ui.grab.addEventListener('pointercancel', pullEnd);
    ui.grab.addEventListener('keydown', function (ev) {
      if (ev.key !== 'ArrowUp' && ev.key !== 'ArrowDown') return;
      ev.preventDefault();
      var now = mapShare(), at = 0;
      SNAPS.forEach(function (s, i) { if (Math.abs(s - now) < Math.abs(SNAPS[at] - now)) at = i; });
      snapMap(SNAPS[Math.max(0, Math.min(SNAPS.length - 1, at + (ev.key === 'ArrowDown' ? 1 : -1)))]);
    });
  }
  window.addEventListener('resize', debounce(function () {
    var inst = window.EPMap.instance;
    if (plan && !root.hidden && inst && inst.resize) { inst.resize(); applyFocus(); }
  }, 200));

  /* ---------------------------------------------------------------- the climb
   *
   * On two wheels the day is its profile as much as its map. Once the road for the day is known,
   * its line is sampled and /api/profile.php gives the heights — once per distinct day, cached
   * there. Dragging along the profile moves a marker along the road.
   */

  var profCache = {};
  function decodeLine(str) {
    var out = [], i = 0, lat = 0, lng = 0;
    while (i < str.length) {
      for (var k = 0; k < 2; k++) {
        var res = 0, shift = 0, b;
        do { b = str.charCodeAt(i++) - 63; res |= (b & 31) << shift; shift += 5; } while (b >= 32);
        var d = (res & 1) ? ~(res >> 1) : (res >> 1);
        if (k === 0) lat += d; else lng += d;
      }
      out.push([lat / 1e5, lng / 1e5]);
    }
    return out;
  }
  function sampleLine(pts, count) {
    var cum = [0], i;
    for (i = 1; i < pts.length; i++) cum.push(cum[i - 1] + km(pts[i - 1][0], pts[i - 1][1], pts[i][0], pts[i][1]));
    var total = cum[cum.length - 1], out = [], j = 0;
    for (var s = 0; s < count; s++) {
      var at = total * s / (count - 1);
      while (j < pts.length - 2 && cum[j + 1] < at) j++;
      var span = cum[j + 1] - cum[j], f = span > 0 ? (at - cum[j]) / span : 0;
      out.push([pts[j][0] + (pts[j + 1][0] - pts[j][0]) * f, pts[j][1] + (pts[j + 1][1] - pts[j][1]) * f, at]);
    }
    return out;
  }
  function climb(z) {
    var up = 0;
    for (var i = 1; i < z.length; i++) if (z[i] > z[i - 1]) up += z[i] - z[i - 1];
    return up;
  }
  var profNow = null;
  function profile() {
    if (!ui.prof) return;
    var was = ui.prof.hidden;
    var hide = function () {
      ui.prof.hidden = true;
      profNow = null;
      var inst = window.EPMap.instance;
      if (inst && inst.setDot) inst.setDot(null);
      if (!was) applyFocusSoon();
    };
    if (!plan || modeKey() === 'car' || overview) { hide(); return; }
    var r = road(activeDay);
    if (!r || !r.geometry) { hide(); return; }
    var key = dayKey(activeDay), hit = profCache[key];
    if (!hit) {
      var pts = decodeLine(r.geometry);
      if (pts.length < 2) { hide(); return; }
      var s = sampleLine(pts, 64);
      profCache[key] = 'pending';
      fetch('/api/profile.php?c=' + encodeURIComponent(s.map(function (p) { return p[0].toFixed(4) + ',' + p[1].toFixed(4); }).join(';')), { credentials: 'omit' })
        .then(function (x) { return x.json(); })
        .then(function (j) {
          profCache[key] = (j && j.ok && j.z && j.z.length === s.length) ? { z: j.z, pts: s, km: s[s.length - 1][2] } : 'failed';
          profile();
        })
        .catch(function () { profCache[key] = 'failed'; });
      hide();
      return;
    }
    if (hit === 'pending' || hit === 'failed') { hide(); return; }

    var z = hit.z, lo = Math.min.apply(null, z), hi = Math.max.apply(null, z), n2 = z.length;
    var line = z.map(function (v, i) {
      return (i / (n2 - 1) * 300).toFixed(1) + ',' + (42 - (v - lo) / Math.max(hi - lo, 1) * 38).toFixed(1);
    });
    ui.prof.querySelector('.plx-prof-line').setAttribute('d', 'M' + line.join('L'));
    ui.prof.querySelector('.plx-prof-area').setAttribute('d', 'M0,44L' + line.join('L') + 'L300,44Z');
    profNow = hit;
    profLabel(-1);
    ui.prof.hidden = false;
    if (was) applyFocusSoon();
  }
  function profLabel(i) {
    if (!profNow) return;
    var z = profNow.z, a = ui.prof.querySelector('.plx-prof-a'), b = ui.prof.querySelector('.plx-prof-b');
    var mark = ui.prof.querySelector('.plx-prof-x');
    if (i < 0) {
      a.textContent = 'Profilul zilei · urcare ' + nf(climb(z)) + ' m';
      b.textContent = 'max ' + nf(Math.max.apply(null, z)) + ' m';
      mark.style.display = 'none';
      return;
    }
    a.textContent = 'km ' + Math.round(profNow.pts[i][2]);
    b.textContent = nf(z[i]) + ' m';
    var x = i / (z.length - 1) * 300;
    mark.setAttribute('x1', x);
    mark.setAttribute('x2', x);
    mark.style.display = '';
  }
  if (ui.prof) {
    var scrubbing = false;
    var scrub = function (ev) {
      if (!profNow) return;
      var box = ui.prof.querySelector('svg').getBoundingClientRect(), last = profNow.z.length - 1;
      var i = Math.max(0, Math.min(last, Math.round((ev.clientX - box.left) / box.width * last)));
      profLabel(i);
      var inst = window.EPMap.instance;
      if (inst && inst.setDot) inst.setDot(profNow.pts[i][0], profNow.pts[i][1]);
    };
    var scrubEnd = function () {
      scrubbing = false;
      profLabel(-1);
      var inst = window.EPMap.instance;
      if (inst && inst.setDot) inst.setDot(null);
    };
    ui.prof.addEventListener('pointerdown', function (ev) { scrubbing = true; ui.prof.setPointerCapture(ev.pointerId); scrub(ev); });
    ui.prof.addEventListener('pointermove', function (ev) { if (scrubbing || ev.pointerType === 'mouse') scrub(ev); });
    ui.prof.addEventListener('pointerup', scrubEnd);
    ui.prof.addEventListener('pointercancel', scrubEnd);
    ui.prof.addEventListener('pointerleave', function () { if (!scrubbing) scrubEnd(); });
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
    var how = document.querySelector('#pl-mode [aria-pressed="true"]');
    plan.mode = (how && MODES[how.dataset.mode]) ? how.dataset.mode : 'car';
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
  /* How you travel: one of three, and the form takes its colour. */
  function setMode(key) {
    if (!MODES[key]) key = 'car';
    var box = document.getElementById('pl-mode');
    if (!box) return;
    [].forEach.call(box.querySelectorAll('[data-mode]'), function (b, i) {
      var on = b.dataset.mode === key;
      b.setAttribute('aria-pressed', String(on));
      if (on) box.style.setProperty('--i', String(i));
    });
    startBox.setAttribute('data-mode', key);
  }
  (function () {
    var box = document.getElementById('pl-mode');
    if (box) box.addEventListener('click', function (e) {
      var b = e.target.closest('[data-mode]');
      if (b) setMode(b.dataset.mode);
    });
    var tg = document.getElementById('pl-back-toggle'), row = document.getElementById('pl-back-row');
    if (tg && row) tg.addEventListener('click', function () {
      row.hidden = !row.hidden;
      tg.setAttribute('aria-expanded', String(!row.hidden));
      tg.textContent = row.hidden ? 'Mă întorc în alt loc' : 'Mă întorc de unde am plecat';
      if (row.hidden) { fields.back.input.value = ''; fields.back.picked = null; } else fields.back.input.focus();
    });
    // One line that says what the folded preferences hold, so they do not have to be opened to be read.
    var sum = document.getElementById('pl-prefs-sum');
    var say = function () {
      if (!sum) return;
      var who = [].map.call(document.querySelectorAll('#pl-company [aria-pressed="true"]'), function (b) { return b.textContent.trim(); });
      var what = document.querySelectorAll('#pl-interests [aria-pressed="true"]').length;
      var pace = document.querySelector('#pl-pace [aria-pressed="true"] b');
      sum.textContent = [who.length ? who.join(', ') : 'oricine', what ? what + (what === 1 ? ' interes' : ' interese') : 'de toate',
        'ritm ' + (pace ? pace.textContent.toLowerCase() : 'normal')].join(' · ');
    };
    var prefs = document.getElementById('pl-prefs');
    if (prefs) prefs.addEventListener('click', function () { setTimeout(say, 0); });
    say();
  })();

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
  });

  /** A plan that came back from a link or from storage shows its party in the form again. */
  function fillParty() {
    setMode(modeKey());
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

  /**
   * /plan?drum=<slug>: one of the roads on /trasee, opened as a one-day plan — its two ends as
   * departure and arrival, the catalogue's places beside it as stops. From there it is a plan like
   * any other: stops can be added, moved and taken out, and the link carries it.
   */
  function roadPlan(slug) {
    var rd = (CFG.roads || {})[slug];
    if (!rd) return null;
    var p = blankPlan();
    p.mode = MODES[rd.mode] ? rd.mode : 'car';
    p.name = rd.title + ' · ' + rd.from + ' → ' + rd.to;      // a road has a name; a generated plan is named by its ends
    p.days = 1;
    p.origin = { kind: 'point', key: '', label: rd.from, lat: rd.a[0], lng: rd.a[1] };
    p.back = { kind: 'point', key: '', label: rd.to, lat: rd.b[0], lng: rd.b[1] };
    p.where = { kind: 'city', key: '', label: rd.title, lat: +((rd.a[0] + rd.b[0]) / 2).toFixed(5), lng: +((rd.a[1] + rd.b[1]) / 2).toFixed(5) };
    p.stops = [(rd.stops || []).filter(function (id) { return bySlug[id] !== undefined; })];
    p.stops[0].forEach(function (id) { p.locked[id] = 1; });
    return p;
  }

  /**
   * /plan?traseu=<slug>: one of the editorial routes, opened as a plan — its stops in their order,
   * split evenly over the days the route was written for, leaving from the town of the first stop
   * and ending in the town of the last. Every stop is locked, so regenerating keeps the route.
   */
  function routePlan(slug) {
    var rt = (CFG.routes || {})[slug];
    if (!rt) return null;
    var ids = (rt.stops || []).filter(function (id) { return bySlug[id] !== undefined; });
    if (!ids.length) return null;
    var p = blankPlan();
    p.name = rt.title;
    p.days = Math.max(1, Math.min(ids.length, rt.days || 1));
    p.origin = { kind: 'point', key: '', label: rt.from, lat: rt.a[0], lng: rt.a[1] };
    p.back = { kind: 'point', key: '', label: rt.to, lat: rt.b[0], lng: rt.b[1] };
    p.where = { kind: 'city', key: '', label: rt.title, lat: +((rt.a[0] + rt.b[0]) / 2).toFixed(5), lng: +((rt.a[1] + rt.b[1]) / 2).toFixed(5) };
    var per = Math.ceil(ids.length / p.days);
    p.stops = [];
    for (var d = 0; d < p.days; d++) p.stops.push(ids.slice(d * per, (d + 1) * per));
    ids.forEach(function (id) { p.locked[id] = 1; });
    return p;
  }

  loadData().then(function () {
    ALL_PLACES = places();
    var query = new URLSearchParams(location.search);
    var fromRoad = roadPlan(query.get('drum') || '') || routePlan(query.get('traseu') || '');
    if (fromRoad) {
      plan = fromRoad;
      fillParty();
      activeDay = 0;
      render();
      return;
    }
    if (MODES[query.get('mod')]) setMode(query.get('mod'));
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
