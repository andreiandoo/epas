/* bilete.online v2: the interactive attractions map.
 *
 *   EPMap.mount(container, config) -> instance { open, close, setTypes, destroy }
 *
 * One engine, several hosts: a full-screen dialog on /atractii, an inline map on /harta, and
 * pre-filtered mini maps elsewhere. The host only supplies a container and a config; every bit
 * of UI below (search, type chips, list, card, bottom sheet) is built here, so there is a single
 * place to fix a map bug.
 *
 * Data is the static pin dataset written by bin/build-map-data.php: columnar rows whose layout is
 * described by its own `fields` array, so the offsets are never hard-coded here. ~7.3k pins arrive
 * in one immutable, gzipped download and every filter, search and sort after that is local --
 * no request per pan, and the search the marketplace API does not offer works anyway.
 *
 * Leaflet + markercluster load from cdnjs on first open, never on page load. Tiles are CARTO's
 * with the site key, falling back to OpenStreetMap when CARTO refuses them (see the CARTO_API_KEY
 * note in includes/config.php).
 *
 * Accessibility: the list beside the map is the keyboard and screen-reader path to every pin, so
 * markers are deliberately not focusable -- 3k tab stops would be worse than none.
 */
(function () {
  'use strict';

  var LEAFLET = 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/';
  var CLUSTER = 'https://cdnjs.cloudflare.com/ajax/libs/leaflet.markercluster/1.5.3/';
  var RO = { lat: 45.94, lng: 24.97, zoom: 7 };
  /* "Populare" hides the 3.9k churches and monasteries: they are 54% of the dataset and they bury
     everything else. One list, easy to retune. */
  var POPULAR_EXCLUDE = ['biserica-manastire'];
  var LIST_PAGE = 40;
  var THEME_KEY = 'bo_map_theme';

  var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var libs = null;

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
  function nf(n) { return new Intl.NumberFormat('ro-RO').format(n); }
  /* Romanian counting, same rule as v2_num() in PHP. */
  function count(n, one, many) {
    if (n === 1) return '1 ' + one;
    var rem = n % 100;
    return nf(n) + ' ' + (n >= 20 && !(rem >= 1 && rem <= 19) ? 'de ' : '') + many;
  }
  function fold(s) {
    return (s || '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();
  }
  function debounce(fn, ms) {
    var t;
    return function () {
      var self = this, args = arguments;
      clearTimeout(t);
      t = setTimeout(function () { fn.apply(self, args); }, ms);
    };
  }
  function km(aLat, aLng, bLat, bLng) {
    var r = Math.PI / 180, dLat = (bLat - aLat) * r, dLng = (bLng - aLng) * r;
    var x = Math.sin(dLat / 2) * Math.sin(dLat / 2) +
      Math.cos(aLat * r) * Math.cos(bLat * r) * Math.sin(dLng / 2) * Math.sin(dLng / 2);
    return 12742 * Math.asin(Math.min(1, Math.sqrt(x)));
  }
  function dist(d) { return d < 1 ? Math.round(d * 1000) + ' m' : d.toFixed(d < 10 ? 1 : 0).replace('.', ',') + ' km'; }

  function loadCss(href) {
    var link = document.createElement('link');
    link.rel = 'stylesheet';
    link.href = href;
    document.head.appendChild(link);
  }
  function loadJs(src) {
    return new Promise(function (resolve, reject) {
      var s = document.createElement('script');
      s.src = src;
      s.async = true;
      s.onload = resolve;
      s.onerror = function () { reject(new Error(src)); };
      document.head.appendChild(s);
    });
  }
  /* Leaflet first, then markercluster: the plugin attaches itself to window.L. */
  function loadLibs() {
    if (libs) return libs;
    libs = (window.L && window.L.map ? Promise.resolve() : (loadCss(LEAFLET + 'leaflet.min.css'), loadJs(LEAFLET + 'leaflet.min.js')))
      .then(function () {
        if (!window.L || !window.L.map) throw new Error('leaflet');
        if (window.L.markerClusterGroup) return;
        loadCss(CLUSTER + 'MarkerCluster.min.css');
        return loadJs(CLUSTER + 'leaflet.markercluster.min.js');
      })
      .then(function () { return window.L; });
    return libs;
  }

  /* ---------------------------------------------------------------- data */

  var dataCache = {};
  function loadData(url) {
    if (dataCache[url]) return dataCache[url];
    dataCache[url] = fetch(url, { credentials: 'omit' })
      .then(function (r) {
        if (!r.ok) throw new Error('map data ' + r.status);
        return r.json();
      })
      .then(function (raw) {
        var f = {};
        (raw.fields || []).forEach(function (name, i) { f[name] = i; });
        var rows = raw.points || [];
        var types = raw.types || [];
        var cities = raw.cities || [];
        var zones = raw.zones || [];
        var flagBits = raw.flags || { image: 1, featured: 2, activities: 4 };

        /* One folded haystack per pin (name + city), built once so typing stays instant. */
        var hay = new Array(rows.length);
        var lat = new Float64Array(rows.length);
        var lng = new Float64Array(rows.length);
        for (var i = 0; i < rows.length; i++) {
          var r = rows[i];
          var cityName = r[f.city] >= 0 && cities[r[f.city]] ? cities[r[f.city]][1] : '';
          hay[i] = fold(r[f.name] + ' ' + cityName);
          lat[i] = r[f.lat_e5] / 100000;
          lng[i] = r[f.lng_e5] / 100000;
        }
        return { f: f, rows: rows, types: types, cities: cities, zones: zones, flags: flagBits, hay: hay, lat: lat, lng: lng };
      });
    return dataCache[url];
  }

  /* ---------------------------------------------------------------- instance */

  function mount(container, cfg) {
    cfg = cfg || {};
    if (!container) return null;

    var D = null;                      // dataset once loaded
    var map = null, cluster = null, tiles = null, usingOsm = false;
    var markers = [];                  // lazily created, one per pin index
    var visible = [];                  // pin indices passing the filters
    var inView = [];                   // subset currently on screen, sorted by distance
    var listShown = LIST_PAGE;
    var selected = -1;
    var me = null;                     // [lat, lng] from geolocation
    var opened = false, booting = false, lastFocus = null;
    var theme = cfg.theme || readTheme() || 'light';

    var state = {
      q: '',
      // empty = every type; the host can preselect (the page's own Tip filter carries over)
      types: (cfg.types && cfg.types.length) ? cfg.types.slice() : [],
      preset: cfg.preset || 'popular',
      city: cfg.city || '',
      zone: cfg.zone || '',
      photo: false,
      ticket: false
    };

    function readTheme() {
      try { return localStorage.getItem(THEME_KEY); } catch (e) { return null; }
    }
    function saveTheme(v) {
      try { localStorage.setItem(THEME_KEY, v); } catch (e) {}
    }

    /* ---------- shell ---------- */

    var ui = {};
    container.classList.add('epm');
    container.classList.add(cfg.dialog ? 'epm-dialog' : 'epm-inline');
    if (theme === 'dark') container.classList.add('is-dark');
    if (cfg.dialog) {
      container.setAttribute('role', 'dialog');
      container.setAttribute('aria-modal', 'true');
      container.setAttribute('aria-label', cfg.title || 'Harta atracțiilor');
      container.hidden = true;
    }

    function buildShell() {
      var bar = el('header', 'epm-bar');
      var main = el('div', 'epm-bar-main');

      if (cfg.title) main.appendChild(el('h2', 'epm-title', cfg.title));

      var search = el('div', 'epm-search');
      search.appendChild(icon('magnifying-glass'));
      ui.input = el('input');
      ui.input.type = 'search';
      ui.input.placeholder = 'Caută o atracție sau un oraș';
      ui.input.setAttribute('aria-label', 'Caută pe hartă');
      ui.input.autocomplete = 'off';
      search.appendChild(ui.input);
      ui.clear = el('button', 'epm-search-clear');
      ui.clear.type = 'button';
      ui.clear.hidden = true;
      ui.clear.appendChild(icon('x'));
      ui.clear.appendChild(el('span', 'sr', 'Șterge căutarea'));
      search.appendChild(ui.clear);
      main.appendChild(search);

      ui.locate = tool('target', 'Lângă mine');
      main.appendChild(ui.locate);

      ui.theme = tool('sun', 'Aspect');
      ui.theme.classList.add('epm-tool-icon');
      ui.theme.querySelector('.epm-tool-label').classList.add('sr');
      main.appendChild(ui.theme);

      if (cfg.dialog) {
        ui.close = tool('x', 'Închide harta');
        ui.close.classList.add('epm-tool-icon');
        ui.close.querySelector('.epm-tool-label').classList.add('sr');
        main.appendChild(ui.close);
      }
      bar.appendChild(main);

      ui.chips = el('div', 'epm-chips');
      ui.chips.setAttribute('role', 'group');
      ui.chips.setAttribute('aria-label', 'Tip de atracție');
      bar.appendChild(ui.chips);

      ui.meta = el('p', 'epm-meta');
      ui.meta.setAttribute('aria-live', 'polite');
      bar.appendChild(ui.meta);
      container.appendChild(bar);

      var body = el('div', 'epm-body');

      ui.side = el('aside', 'epm-side');
      ui.side.setAttribute('aria-label', 'Atracțiile din zona afișată');
      ui.side.setAttribute('data-snap', 'half');
      ui.grab = el('button', 'epm-sheet-grab');
      ui.grab.type = 'button';
      ui.grab.appendChild(el('span', '', 'Trage pentru a mări sau micșora lista'));
      ui.side.appendChild(ui.grab);
      var head = el('div', 'epm-side-head');
      ui.inview = el('span', '', 'Se încarcă…');
      head.appendChild(ui.inview);
      ui.all = el('button', 'link-btn', 'Arată toate');
      ui.all.type = 'button';
      head.appendChild(ui.all);
      ui.side.appendChild(head);
      ui.list = el('ul', 'epm-list');
      ui.side.appendChild(ui.list);
      body.appendChild(ui.side);

      ui.canvas = el('div', 'epm-map');
      ui.loading = el('div', 'epm-loading', 'Se încarcă harta…');
      ui.canvas.appendChild(ui.loading);
      body.appendChild(ui.canvas);

      ui.card = el('div', 'epm-card');
      ui.card.hidden = true;
      body.appendChild(ui.card);

      container.appendChild(body);
      wire();
    }

    function tool(ic, label) {
      var b = el('button', 'epm-tool');
      b.type = 'button';
      b.appendChild(icon(ic));
      b.appendChild(el('span', 'epm-tool-label', label));
      b.setAttribute('aria-label', label);
      return b;
    }

    /* ---------- filters ---------- */

    function typeSlugs() {
      return D.types.map(function (t) { return t[0]; });
    }
    function presetTypes(name) {
      if (name === 'all') return [];
      return typeSlugs().filter(function (s) { return POPULAR_EXCLUDE.indexOf(s) === -1; });
    }
    function isPreset(name) {
      var want = presetTypes(name).slice().sort().join(',');
      var have = state.types.slice().sort().join(',');
      return name === 'all' ? state.types.length === 0 : want === have;
    }

    function buildChips() {
      ui.chips.textContent = '';
      ['popular', 'all'].forEach(function (p) {
        var b = el('button', 'epm-chip epm-chip-preset');
        b.type = 'button';
        b.dataset.preset = p;
        b.textContent = p === 'popular' ? 'Populare' : 'Toate';
        ui.chips.appendChild(b);
      });
      ui.chips.appendChild(el('span', 'epm-chips-sep'));

      D.types.forEach(function (t) {
        if (!t[4]) return;                                    // no pins of this type, no chip
        var b = el('button', 'epm-chip');
        b.type = 'button';
        b.dataset.type = t[0];
        if (t[2]) {
          var e = el('span', 'epm-chip-emoji', t[2]);
          e.setAttribute('aria-hidden', 'true');
          b.appendChild(e);
        }
        b.appendChild(document.createTextNode(t[1]));
        b.appendChild(el('b', '', nf(t[4])));
        ui.chips.appendChild(b);
      });

      /* Only offer a toggle that can actually change the result. */
      var anyPhoto = false, anyTicket = false;
      for (var i = 0; i < D.rows.length && !(anyPhoto && anyTicket); i++) {
        var fl = D.rows[i][D.f.flags];
        if (fl & D.flags.image) anyPhoto = true;
        if (fl & D.flags.activities) anyTicket = true;
      }
      if (anyPhoto || anyTicket) {
        ui.chips.appendChild(el('span', 'epm-chips-sep'));
        if (anyTicket) ui.chips.appendChild(flagChip('ticket', 'ticket', 'Cu bilete'));
        if (anyPhoto) ui.chips.appendChild(flagChip('photo', 'star', 'Cu poză'));
      }
      paintChips();
    }
    function flagChip(key, ic, label) {
      var b = el('button', 'epm-chip');
      b.type = 'button';
      b.dataset.flag = key;
      b.appendChild(icon(ic));
      b.appendChild(document.createTextNode(label));
      return b;
    }
    function paintChips() {
      [].forEach.call(ui.chips.children, function (b) {
        if (b.dataset.preset) b.setAttribute('aria-pressed', String(isPreset(b.dataset.preset)));
        else if (b.dataset.type) b.setAttribute('aria-pressed', String(state.types.indexOf(b.dataset.type) !== -1));
        else if (b.dataset.flag) b.setAttribute('aria-pressed', String(!!state[b.dataset.flag]));
      });
    }

    function applyFilters() {
      var f = D.f, rows = D.rows, q = fold(state.q.trim());
      var wantTypes = null;
      if (state.types.length) {
        wantTypes = {};
        state.types.forEach(function (s) { wantTypes[s] = 1; });
      }
      var cityIdx = -1, zoneIdx = -1;
      if (state.city) {
        for (var ci = 0; ci < D.cities.length; ci++) if (D.cities[ci][0] === state.city) { cityIdx = ci; break; }
      }
      if (state.zone) {
        var wantZone = fold(state.zone);
        for (var zi = 0; zi < D.zones.length; zi++) {
          if (fold(D.zones[zi][0]) === wantZone || fold(D.zones[zi][1] || '') === wantZone) { zoneIdx = zi; break; }
        }
      }

      var out = [];
      for (var i = 0; i < rows.length; i++) {
        var r = rows[i];
        if (wantTypes) {
          var t = r[f.type];
          if (t < 0 || !wantTypes[D.types[t][0]]) continue;
        }
        if (cityIdx >= 0 && r[f.city] !== cityIdx) continue;
        if (zoneIdx >= 0 && r[f.zone] !== zoneIdx) continue;
        if (state.photo && !(r[f.flags] & D.flags.image)) continue;
        if (state.ticket && !(r[f.flags] & D.flags.activities)) continue;
        if (q && D.hay[i].indexOf(q) === -1) continue;
        out.push(i);
      }
      visible = out;
    }

    /* ---------- markers ---------- */

    function markerFor(i) {
      if (markers[i]) return markers[i];
      var L = window.L, r = D.rows[i], f = D.f;
      var t = r[f.type] >= 0 ? D.types[r[f.type]] : null;
      var pin = el('span', 'epm-pin' + ((r[f.flags] & D.flags.activities) ? ' has-ticket' : ''), t && t[2] ? t[2] : '•');
      pin.setAttribute('data-i', String(i));
      var m = L.marker([D.lat[i], D.lng[i]], {
        keyboard: false,
        title: r[f.name],
        icon: L.divIcon({ className: 'epm-pin-wrap', html: pin.outerHTML, iconSize: [30, 30], iconAnchor: [15, 15] })
      });
      m.epIndex = i;
      m.on('click', function () { select(i, true); });
      markers[i] = m;
      return m;
    }

    function drawMarkers() {
      if (!cluster) return;
      busy(true);
      /* Let the overlay paint before the (blocking) bulk insert of a few thousand markers. */
      requestAnimationFrame(function () {
        cluster.clearLayers();
        var batch = new Array(visible.length);
        for (var i = 0; i < visible.length; i++) batch[i] = markerFor(visible[i]);
        cluster.addLayers(batch);
        busy(false);
        syncView();
      });
    }
    function busy(on) {
      ui.loading.hidden = !on;
      if (on) ui.loading.textContent = 'Se actualizează harta…';
    }

    /* ---------- map ---------- */

    function tileUrl() {
      var base = 'https://{s}.basemaps.cartocdn.com/' + (theme === 'dark' ? 'dark_all' : 'light_all') + '/{z}/{x}/{y}{r}.png';
      return base + (cfg.cartoKey ? '?key=' + encodeURIComponent(cfg.cartoKey) : '');
    }
    function addTiles() {
      var L = window.L;
      usingOsm = false;
      tiles = L.tileLayer(tileUrl(), {
        subdomains: 'abcd',
        maxZoom: 19,
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> &copy; <a href="https://carto.com/attributions">CARTO</a>'
      });
      /* CARTO answers 403 for a domain that is not on the key -- fall back once, quietly. */
      tiles.on('tileerror', function () {
        if (usingOsm) return;
        usingOsm = true;
        map.removeLayer(tiles);
        tiles = L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
          maxZoom: 19,
          attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
        }).addTo(map);
      });
      tiles.addTo(map);
    }

    function buildMap() {
      var L = window.L;
      map = L.map(ui.canvas, {
        zoomControl: true,
        scrollWheelZoom: true,
        attributionControl: true,
        preferCanvas: true
      }).setView([RO.lat, RO.lng], RO.zoom);
      addTiles();

      cluster = L.markerClusterGroup({
        chunkedLoading: true,
        showCoverageOnHover: false,
        removeOutsideVisibleBounds: true,
        spiderfyOnMaxZoom: true,
        animate: !reduceMotion,
        maxClusterRadius: function (zoom) { return zoom > 12 ? 40 : 70; },
        iconCreateFunction: function (c) {
          var n = c.getChildCount();
          var size = n < 10 ? 38 : n < 100 ? 46 : 56;
          var tier = n < 10 ? '' : n < 100 ? ' s2' : ' s3';
          return L.divIcon({
            className: 'epm-pin-wrap',
            html: '<span class="epm-cluster' + tier + '" style="width:' + size + 'px;height:' + size + 'px">' + nf(n) + '</span>',
            iconSize: [size, size]
          });
        }
      });
      map.addLayer(cluster);
      map.on('moveend', syncView);
      map.on('click', function () { select(-1); });
    }

    function fitVisible() {
      if (!map) return;
      if (!visible.length) return;
      var pts = [];
      for (var i = 0; i < visible.length; i++) pts.push([D.lat[visible[i]], D.lng[visible[i]]]);
      map.invalidateSize();
      if (pts.length === 1) map.setView(pts[0], 13);
      else map.fitBounds(pts, { padding: [50, 50], maxZoom: 13, animate: !reduceMotion });
    }

    /* ---------- list ---------- */

    function syncView() {
      if (!map || !D) return;
      var b = map.getBounds(), c = map.getCenter();
      var origin = me || [c.lat, c.lng];
      var rows = [];
      for (var i = 0; i < visible.length; i++) {
        var p = visible[i];
        if (!b.contains([D.lat[p], D.lng[p]])) continue;
        rows.push([p, km(origin[0], origin[1], D.lat[p], D.lng[p])]);
      }
      rows.sort(function (a, z) { return a[1] - z[1]; });
      inView = rows;
      listShown = LIST_PAGE;
      renderList();
      renderMeta();
      pushUrl();
    }

    function renderList() {
      ui.list.textContent = '';
      ui.inview.textContent = inView.length
        ? count(inView.length, 'atracție în zona afișată', 'atracții în zona afișată')
        : 'Nicio atracție în zona afișată';

      if (!inView.length) {
        var empty = el('li', 'epm-empty');
        empty.appendChild(el('b', '', visible.length ? 'Zona asta e goală' : 'Niciun rezultat'));
        empty.appendChild(el('p', '', visible.length
          ? 'Depărtează harta sau apasă „Arată toate”.'
          : 'Încearcă alt tip de atracție sau alt cuvânt în căutare.'));
        ui.list.appendChild(empty);
        return;
      }

      var frag = document.createDocumentFragment();
      var n = Math.min(listShown, inView.length);
      for (var i = 0; i < n; i++) frag.appendChild(row(inView[i][0], inView[i][1]));
      ui.list.appendChild(frag);

      if (inView.length > n) {
        var li = el('li');
        var more = el('button', 'btn btn-light epm-more', 'Încă ' + nf(Math.min(LIST_PAGE, inView.length - n)) + ' din zonă');
        more.type = 'button';
        more.addEventListener('click', function () { listShown += LIST_PAGE; renderList(); });
        li.appendChild(more);
        ui.list.appendChild(li);
      }
    }

    function row(i, d) {
      var r = D.rows[i], f = D.f;
      var t = r[f.type] >= 0 ? D.types[r[f.type]] : null;
      var city = r[f.city] >= 0 ? D.cities[r[f.city]][1] : '';
      var li = el('li');
      var b = el('button', 'epm-row');
      b.type = 'button';
      b.dataset.i = String(i);
      if (i === selected) b.setAttribute('aria-current', 'true');

      var media = el('span', 'epm-row-media');
      if (r[f.img]) {
        var img = el('img');
        img.src = r[f.img];
        img.alt = '';
        img.loading = 'lazy';
        img.decoding = 'async';
        img.width = 56;
        img.height = 56;
        media.appendChild(img);
      } else {
        var ph = el('span', '', t && t[2] ? t[2] : '📍');
        ph.setAttribute('aria-hidden', 'true');
        media.appendChild(ph);
      }
      b.appendChild(media);

      var body = el('span', 'epm-row-body');
      body.appendChild(el('span', 'epm-row-title', r[f.name]));
      var meta = el('span', 'epm-row-meta');
      var bits = [];
      if (t) bits.push(t[1]);
      if (city) bits.push(city);
      meta.appendChild(document.createTextNode(bits.join(' · ')));
      if (isFinite(d)) {
        meta.appendChild(document.createTextNode(' · '));
        meta.appendChild(el('span', 'epm-row-dist', dist(d)));
      }
      body.appendChild(meta);
      b.appendChild(body);
      li.appendChild(b);
      return li;
    }

    function renderMeta() {
      ui.meta.textContent = '';
      ui.meta.appendChild(el('span', '', count(visible.length, 'atracție pe hartă', 'atracții pe hartă')));
      if (visible.length !== D.rows.length) {
        var reset = el('button', 'link-btn', 'Șterge filtrele');
        reset.type = 'button';
        reset.addEventListener('click', function () {
          state.q = '';
          state.types = [];
          state.photo = false;
          state.ticket = false;
          ui.input.value = '';
          ui.clear.hidden = true;
          refresh(true);
        });
        ui.meta.appendChild(reset);
      }
    }

    /* ---------- selection ---------- */

    function select(i, pan) {
      selected = i;
      [].forEach.call(ui.list.querySelectorAll('.epm-row'), function (b) {
        b.toggleAttribute('aria-current', Number(b.dataset.i) === i);
      });
      [].forEach.call(ui.canvas.querySelectorAll('.epm-pin'), function (p) {
        p.classList.toggle('is-on', Number(p.getAttribute('data-i')) === i);
      });
      if (i < 0) { ui.card.hidden = true; return; }
      renderCard(i);
      if (pan === true && map) map.panTo([D.lat[i], D.lng[i]], { animate: !reduceMotion });
    }

    function renderCard(i) {
      var r = D.rows[i], f = D.f;
      var t = r[f.type] >= 0 ? D.types[r[f.type]] : null;
      var city = r[f.city] >= 0 ? D.cities[r[f.city]][1] : '';
      var zone = r[f.zone] >= 0 ? D.zones[r[f.zone]] : null;
      var href = (cfg.base || '/atractie/') + r[f.slug];

      ui.card.textContent = '';
      var media = el('a', 'epm-card-media');
      media.href = href;
      if (r[f.img]) {
        var img = el('img');
        img.src = r[f.img];
        img.alt = '';
        img.loading = 'lazy';
        img.decoding = 'async';
        media.appendChild(img);
      } else {
        var ph = el('span', '', t && t[2] ? t[2] : '📍');
        ph.setAttribute('aria-hidden', 'true');
        media.appendChild(ph);
      }
      ui.card.appendChild(media);

      var close = el('button', 'epm-card-close');
      close.type = 'button';
      close.appendChild(icon('x'));
      close.appendChild(el('span', 'sr', 'Închide'));
      close.addEventListener('click', function () { select(-1); });
      ui.card.appendChild(close);

      var body = el('div', 'epm-card-body');
      if (t) body.appendChild(el('p', 'epm-card-kicker', t[1]));
      body.appendChild(el('h3', 'epm-card-title', r[f.name]));

      var meta = el('p', 'epm-card-meta');
      if (city || zone) {
        var where = el('span');
        where.appendChild(icon('map-pin'));
        where.appendChild(document.createTextNode([city, zone ? zone[0] : ''].filter(Boolean).join(', ')));
        meta.appendChild(where);
      }
      if (me) {
        var dd = el('span');
        dd.appendChild(icon('target'));
        dd.appendChild(document.createTextNode(dist(km(me[0], me[1], D.lat[i], D.lng[i])) + ' de tine'));
        meta.appendChild(dd);
      }
      if (meta.childNodes.length) body.appendChild(meta);

      var actions = el('div', 'epm-card-actions');
      var go = el('a', 'btn btn-primary', 'Vezi atracția');
      go.href = href;
      actions.appendChild(go);
      var nav = el('a', 'btn btn-light', 'Navighează');
      nav.href = 'https://www.google.com/maps/dir/?api=1&destination=' + D.lat[i] + ',' + D.lng[i];
      nav.target = '_blank';
      nav.rel = 'noopener';
      actions.appendChild(nav);
      body.appendChild(actions);

      ui.card.appendChild(body);
      ui.card.hidden = false;
    }

    /* ---------- url ---------- */

    var pushUrl = debounce(function () {
      if (!cfg.urlState || !map) return;
      var p = new URLSearchParams(window.location.search);
      if (state.types.length && !isPreset('all')) p.set('tip', state.types.join(',')); else p.delete('tip');
      if (state.q.trim()) p.set('q', state.q.trim()); else p.delete('q');
      if (cfg.dialog) p.set('harta', '1');
      var c = map.getCenter();
      var hash = '#' + map.getZoom() + '/' + c.lat.toFixed(4) + '/' + c.lng.toFixed(4);
      var qs = p.toString();
      history.replaceState(null, '', window.location.pathname + (qs ? '?' + qs : '') + hash);
    }, 400);

    function readUrl() {
      var p = new URLSearchParams(window.location.search);
      var tip = p.get('tip');
      if (tip) {
        var valid = typeSlugs();
        state.types = tip.split(',').filter(function (s) { return valid.indexOf(s) !== -1; });
      }
      var q = p.get('q');
      if (q) { state.q = q; ui.input.value = q; ui.clear.hidden = false; }

      var m = /^#(\d{1,2})\/(-?\d+(?:\.\d+)?)\/(-?\d+(?:\.\d+)?)$/.exec(window.location.hash);
      return m ? { zoom: +m[1], lat: +m[2], lng: +m[3] } : null;
    }

    /* ---------- events ---------- */

    function wire() {
      ui.input.addEventListener('input', debounce(function () {
        state.q = ui.input.value;
        ui.clear.hidden = !state.q;
        refresh(false);
      }, 160));
      ui.clear.addEventListener('click', function () {
        ui.input.value = '';
        state.q = '';
        ui.clear.hidden = true;
        ui.input.focus();
        refresh(false);
      });

      ui.chips.addEventListener('click', function (e) {
        var b = e.target.closest('button');
        if (!b) return;
        if (b.dataset.preset) {
          state.types = presetTypes(b.dataset.preset);
        } else if (b.dataset.type) {
          var i = state.types.indexOf(b.dataset.type);
          /* First click off "Toate" means "only this one", which is what people expect. */
          if (!state.types.length) state.types = [b.dataset.type];
          else if (i === -1) state.types.push(b.dataset.type);
          else state.types.splice(i, 1);
        } else if (b.dataset.flag) {
          state[b.dataset.flag] = !state[b.dataset.flag];
        } else return;
        refresh(false);
      });

      ui.list.addEventListener('click', function (e) {
        var b = e.target.closest('.epm-row');
        if (b) select(Number(b.dataset.i), true);
      });
      ui.list.addEventListener('mouseover', function (e) {
        var b = e.target.closest('.epm-row');
        hot(b ? Number(b.dataset.i) : -1);
      });
      ui.list.addEventListener('mouseleave', function () { hot(-1); });

      ui.all.addEventListener('click', function () { fitVisible(); });
      ui.locate.addEventListener('click', locate);
      ui.theme.addEventListener('click', function () {
        theme = theme === 'dark' ? 'light' : 'dark';
        saveTheme(theme);
        container.classList.toggle('is-dark', theme === 'dark');
        if (map && tiles && !usingOsm) { map.removeLayer(tiles); addTiles(); }
      });
      if (ui.close) ui.close.addEventListener('click', close);

      container.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && cfg.dialog && opened) { e.stopPropagation(); close(); }
        if (e.key === 'Tab' && cfg.dialog && opened) trapTab(e);
      });

      sheet();
    }

    function hot(i) {
      [].forEach.call(ui.canvas.querySelectorAll('.epm-pin'), function (p) {
        p.classList.toggle('is-hot', Number(p.getAttribute('data-i')) === i);
      });
    }

    /* Bottom sheet: tap the handle to cycle, drag it to resize, snap on release. */
    function sheet() {
      var snaps = ['peek', 'half', 'full'];
      var start = null;
      ui.grab.addEventListener('click', function () {
        var i = snaps.indexOf(ui.side.getAttribute('data-snap') || 'half');
        ui.side.setAttribute('data-snap', snaps[(i + 1) % snaps.length]);
      });
      ui.grab.addEventListener('pointerdown', function (e) {
        start = { y: e.clientY, h: ui.side.getBoundingClientRect().height };
        ui.grab.setPointerCapture(e.pointerId);
      });
      ui.grab.addEventListener('pointermove', function (e) {
        if (!start) return;
        var h = Math.max(120, Math.min(window.innerHeight * 0.9, start.h + (start.y - e.clientY)));
        ui.side.style.setProperty('--epm-sheet', h + 'px');
      });
      ui.grab.addEventListener('pointerup', function (e) {
        if (!start) return;
        var h = ui.side.getBoundingClientRect().height, vh = window.innerHeight;
        var moved = Math.abs(start.y - e.clientY) > 6;
        start = null;
        ui.side.style.removeProperty('--epm-sheet');
        if (!moved) return;                      // a tap: let the click handler cycle instead
        ui.side.setAttribute('data-snap', h < vh * 0.28 ? 'peek' : h < vh * 0.66 ? 'half' : 'full');
      });
    }

    function locate() {
      if (!navigator.geolocation) return;
      ui.locate.classList.add('is-busy');
      navigator.geolocation.getCurrentPosition(function (pos) {
        ui.locate.classList.remove('is-busy');
        me = [pos.coords.latitude, pos.coords.longitude];
        if (map) map.setView(me, 11, { animate: !reduceMotion });
        syncView();
      }, function () {
        ui.locate.classList.remove('is-busy');
        ui.inview.textContent = 'Nu am putut afla unde ești.';
      }, { enableHighAccuracy: false, timeout: 8000, maximumAge: 300000 });
    }

    function trapTab(e) {
      var f = container.querySelectorAll('a[href], button:not([disabled]), input, [tabindex]:not([tabindex="-1"])');
      if (!f.length) return;
      var first = f[0], last = f[f.length - 1];
      if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
      else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
    }

    /* ---------- lifecycle ---------- */

    function refresh(fit) {
      applyFilters();
      paintChips();
      renderMeta();
      if (selected >= 0 && visible.indexOf(selected) === -1) select(-1);
      drawMarkers();
      if (fit) fitVisible();
    }

    function boot() {
      if (D || booting) return Promise.resolve(D);
      booting = true;
      buildShell();
      ui.loading.hidden = false;
      return Promise.all([loadLibs(), loadData(cfg.dataUrl)])
        .then(function (res) {
          D = res[1];
          if (!state.types.length && state.preset === 'popular') state.types = presetTypes('popular');
          buildChips();
          var view = cfg.urlState ? readUrl() : null;
          buildMap();
          applyFilters();
          paintChips();
          if (view) map.setView([view.lat, view.lng], view.zoom);
          else if (state.city || state.zone || state.q) fitVisible();
          drawMarkers();
          ui.loading.hidden = true;
          booting = false;
          if (typeof cfg.onReady === 'function') cfg.onReady(api);
          return D;
        })
        .catch(function (err) {
          booting = false;
          ui.loading.hidden = false;
          ui.loading.textContent = 'Harta nu a putut fi încărcată. Reîncarcă pagina.';
          if (window.console) console.warn('[EPMap]', err);
        });
    }

    function open() {
      if (opened) return;
      opened = true;
      lastFocus = document.activeElement;
      container.hidden = false;
      document.documentElement.classList.add('epm-locked');
      boot().then(function () {
        if (map) map.invalidateSize();
        if (ui.input) ui.input.focus();
      });
    }
    function close() {
      if (!opened) return;
      opened = false;
      container.hidden = true;
      document.documentElement.classList.remove('epm-locked');
      if (cfg.urlState) {
        var p = new URLSearchParams(window.location.search);
        p.delete('harta');
        var qs = p.toString();
        history.replaceState(null, '', window.location.pathname + (qs ? '?' + qs : ''));
      }
      if (lastFocus && lastFocus.focus) lastFocus.focus();
    }

    var api = {
      open: open,
      close: close,
      boot: boot,
      setTypes: function (list) { state.types = list || []; if (D) refresh(true); },
      isOpen: function () { return opened; }
    };

    if (!cfg.dialog) boot();
    return api;
  }

  /* ---------------------------------------------------------------- auto-mount
   * A page only has to print an empty <div data-epm-root data-epm-config='{...}'> and, for the
   * dialog flavour, any number of [data-epm-open] buttons. ?harta=1 reopens a shared link. */
  function auto() {
    var host = document.querySelector('[data-epm-root]');
    if (!host) return;
    var cfg = {};
    try { cfg = JSON.parse(host.getAttribute('data-epm-config') || '{}'); } catch (e) {}

    var inst = mount(host, cfg);
    window.EPMap.instance = inst;

    [].forEach.call(document.querySelectorAll('[data-epm-open]'), function (b) {
      b.addEventListener('click', function (e) {
        e.preventDefault();
        inst.open();
      });
    });

    if (cfg.dialog && new URLSearchParams(window.location.search).get('harta') === '1') inst.open();
  }

  window.EPMap = { mount: mount };

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', auto);
  else auto();
})();
