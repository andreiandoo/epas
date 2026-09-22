/**
 * bilete.online v2: the map explorer — the filter surface docked to the top of the map on /harta
 * and on the /harta/{slug} landings (markup in includes/v2/map-page.php).
 *
 * The page renders every type, region, county and city as an ordinary link, so the content is
 * there for a crawler and for anyone without JavaScript. This file turns those same links into
 * map filters: a plain click filters in place, a middle click or a ctrl-click still opens the
 * page behind the link. It also drives the picks rail at the bottom of the page.
 */
(function () {
  'use strict';

  /* ------------------------------------------------------------------ explorer */
  var root = document.querySelector('[data-mpx-root]');

  // Built at runtime: the combining-mark range is easier to trust as code points than as source.
  var MARKS = new RegExp('[' + String.fromCharCode(0x300) + '-' + String.fromCharCode(0x36f) + ']', 'g');
  function fold(s) {
    return (s || '').normalize('NFD').replace(MARKS, '').toLowerCase();
  }


  function nf(n) {
    return String(n).replace(/\B(?=(\d{3})+(?!\d))/g, '.');
  }

  if (root) initExplorer(root);

  function initExplorer(root) {
    var base = {};
    try { base = JSON.parse(root.getAttribute('data-mpx-base') || '{}'); } catch (e) {}
    // The baseline is whatever the map settles on after it boots (a preset is a list of types
    // the page does not know), so "Resetează" only shows once something really changed.
    var baseKey = null;

    var tabs = [].slice.call(root.querySelectorAll('[data-mpx-tab]'));
    var panels = {};
    [].forEach.call(root.querySelectorAll('[data-mpx-panel]'), function (p) {
      panels[p.getAttribute('data-mpx-panel')] = p;
    });
    var countEl = root.querySelector('[data-mpx-count]');
    var resetEl = root.querySelector('[data-mpx-reset]');
    var opened = '';

    function key(types, region, zone, city) {
      return (types || []).slice().sort().join(',') + '|' + region + '|' + zone + '|' + city;
    }

    function show(id) {
      opened = id;
      tabs.forEach(function (t) {
        var on = t.getAttribute('data-mpx-tab') === opened;
        t.setAttribute('aria-expanded', String(on));
        t.classList.toggle('is-open', on);
      });
      Object.keys(panels).forEach(function (k) { panels[k].hidden = k !== opened; });
    }

    tabs.forEach(function (t) {
      t.addEventListener('click', function () {
        var id = t.getAttribute('data-mpx-tab');
        show(opened === id ? '' : id);
      });
    });

    // Esc closes, and a click anywhere outside the explorer does too.
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && opened) { show(''); }
    });
    document.addEventListener('click', function (e) {
      if (opened && !root.contains(e.target)) show('');
    });

    function map() {
      var m = window.EPMap && window.EPMap.instance;
      return (m && m.setFilter) ? m : null;
    }

    /* --------------------------------------------------------- choosing */
    root.addEventListener('click', function (e) {
      var a = e.target.closest ? e.target.closest('[data-mpx-set]') : null;
      if (!a || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
      var m = map();
      if (!m) return;                       // no map (yet): let the link do its job
      e.preventDefault();

      var kind = a.getAttribute('data-mpx-set');
      var k = a.getAttribute('data-mpx-key');
      var st = m.getState();

      if (kind === 'preset') {
        m.setFilter(k === 'all' ? { types: [] } : { preset: k });
        return;
      }
      if (kind === 'type') {
        var list = st.types.slice();
        var i = list.indexOf(k);
        if (i >= 0) list.splice(i, 1); else list.push(k);
        m.setFilter({ types: list });
        return;
      }
      // One place at a time: a region, a county and a city cannot all be true at once.
      var patch = { region: '', zone: '', city: '' };
      if (st[kind] !== k) patch[kind] = k;
      m.setFilter(patch);
      show('');
      nudgeToMap();
    });

    if (resetEl) {
      resetEl.addEventListener('click', function () {
        var m = map();
        if (!m) return;
        m.setFilter({
          types: base.preset ? undefined : (base.types || []),
          preset: base.preset || undefined,
          region: base.region || '',
          zone: base.zone || '',
          city: base.city || '',
          q: ''
        });
        show('');
      });
    }

    /* --------------------------------------------------------- filtering a long list */
    [].forEach.call(root.querySelectorAll('[data-mpx-find]'), function (input) {
      var panel = panels[input.getAttribute('data-mpx-find')];
      if (!panel) return;
      var items = [].slice.call(panel.querySelectorAll('li'));
      var empty = panel.querySelector('[data-mpx-empty]');
      input.addEventListener('input', function () {
        var q = fold(input.value.trim());
        var hits = 0;
        items.forEach(function (li) {
          var a = li.firstElementChild;
          var on = !q || fold(a && a.getAttribute('data-mpx-label')).indexOf(q) >= 0;
          li.hidden = !on;
          if (on) hits++;
        });
        if (empty) empty.hidden = hits > 0;
      });
    });

    /* --------------------------------------------------------- reflecting the map */
    function paint(st) {
      if (countEl) {
        var n = st.visible;
        countEl.firstElementChild.textContent = nf(n);
        countEl.lastElementChild.textContent = n === 1 ? 'loc pe hartă' : 'locuri pe hartă';
      }
      var chosen = {};
      [].forEach.call(root.querySelectorAll('[data-mpx-set]'), function (a) {
        var kind = a.getAttribute('data-mpx-set');
        if (kind === 'preset') return;
        var k = a.getAttribute('data-mpx-key');
        var on = kind === 'type' ? st.types.indexOf(k) >= 0 : st[kind] === k;
        a.classList.toggle('is-on', on);
        a.setAttribute('aria-pressed', String(on));
        if (on) (chosen[kind] = chosen[kind] || []).push(a.getAttribute('data-mpx-label'));
      });

      // Each trigger says what is chosen on it, so nothing has to be opened to be read.
      tabs.forEach(function (t) {
        var panel = panels[t.getAttribute('data-mpx-tab')];
        var kind = panel ? panel.getAttribute('data-mpx-kind') : '';
        var picked = chosen[kind] || [];
        var sub = t.querySelector('[data-mpx-tabsub]');
        var total = panel ? panel.querySelectorAll('[data-mpx-set]:not([data-mpx-set="preset"])').length : 0;
        if (!sub) return;
        if (!picked.length) {
          sub.textContent = (kind === 'type' && st.types.length === 0) ? 'toate tipurile' : t.getAttribute('data-mpx-sub');
          t.classList.remove('is-active');
        } else {
          sub.textContent = picked.length === 1 ? picked[0]
            : (kind === 'type' ? picked.length + ' din ' + total + ' alese' : picked.length + ' alese');
          t.classList.add('is-active');
        }
      });

      var now = key(st.types, st.region, st.zone, st.city);
      if (baseKey === null && st.total > 0) baseKey = now;
      if (resetEl) resetEl.hidden = baseKey === null || now === baseKey;
    }

    var tries = 0;
    (function bind() {
      var m = map();
      if (!m || !m.onChange) {
        if (tries++ < 120) setTimeout(bind, 100);
        return;
      }
      m.onChange(paint);
      paint(m.getState());
    })();
  }

  /** Bring the map back into view after a filter chosen from a panel below the fold. */
  function nudgeToMap() {
    var frame = document.querySelector('.mp-frame');
    if (!frame) return;
    var r = frame.getBoundingClientRect();
    if (r.top < 0 || r.top > window.innerHeight * 0.55) {
      window.scrollTo({ top: window.scrollY + r.top - 90, behavior: 'smooth' });
    }
  }

  /* ------------------------------------------------------------------ the picks rail */
  var rail = document.querySelector('[data-mpr]');
  if (rail) {
    var arrows = document.querySelector('[data-mpr-arrows]');
    var prev = document.querySelector('[data-mpr-prev]');
    var next = document.querySelector('[data-mpr-next]');

    // Press and drag, wheel, or the arrows. Without this a mouse has nothing to grab and the
    // thirty-six cards past the right edge may as well not be there.
    if (window.EPHDrag) {
      window.EPHDrag(rail, {
        onSync: function (st) {
          if (arrows) arrows.hidden = !st.over;
          if (prev) prev.disabled = st.atStart;
          if (next) next.disabled = st.atEnd;
        }
      });
    }
    var nudge = function (dir) {
      if (rail.epScrollBy) rail.epScrollBy(dir);
      else rail.scrollBy({ left: dir * Math.round(rail.clientWidth * 0.8), behavior: 'smooth' });
    };
    if (prev) prev.addEventListener('click', function () { nudge(-1); });
    if (next) next.addEventListener('click', function () { nudge(1); });
  }
})();
