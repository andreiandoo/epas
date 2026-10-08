/* viaqui.com v2: city page. Rotating category cards in the hero, photo gallery, a section nav that follows
   the scroll, filter menus and the phone filter sheet, the GetYourGuide widget loaded near the viewport. */
(function () {
  'use strict';
  var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var root = document.documentElement;
  var $ = function (id) { return document.getElementById(id); };
  var data = {};
  try { data = JSON.parse(($('v2-data') || {}).textContent || '{}'); } catch (e) {}

  function trapTab(e, box) {
    if (e.key !== 'Tab') return;
    var f = [].slice.call(box.querySelectorAll('a[href], button:not([disabled])')).filter(function (el) { return el.offsetParent !== null; });
    if (!f.length) return;
    var first = f[0], last = f[f.length - 1];
    if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
    else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
  }

  /* ---------- hero: one category card at a time swings along the arch, pauses on hover or focus ---------- */
  var orbit = document.querySelector('[data-orbit]');
  var cards = orbit ? [].slice.call(orbit.querySelectorAll('.oc')) : [];
  if (cards.length > 1 && !reduce) {
    var cur = 0, hold = false, inView = true;
    cards.forEach(function (c) {
      c.addEventListener('pointerenter', function () { hold = true; });
      c.addEventListener('pointerleave', function () { hold = false; });
    });
    orbit.addEventListener('focusin', function () { hold = true; });
    orbit.addEventListener('focusout', function () { hold = false; });
    if ('IntersectionObserver' in window) {
      new IntersectionObserver(function (es) { inView = es[0].isIntersecting; }).observe(orbit.parentElement);
    }
    setInterval(function () {
      if (hold || !inView || document.hidden) return;
      var out = cards[cur];
      out.classList.remove('is-on');
      out.classList.add('is-out');
      setTimeout(function () { out.classList.remove('is-out'); }, 950);
      cur = (cur + 1) % cards.length;
      cards[cur].classList.add('is-on');
    }, 3000);
  }

  /* ---------- gallery lightbox ---------- */
  var lb = $('lb'), gallery = Array.isArray(data.gallery) ? data.gallery : [];
  if (lb && gallery.length) {
    var lbImg = $('lb-img'), lbTitle = $('lb-title'), lbCount = $('lb-count'), at = 0, opener = null;
    var show = function (i) {
      at = (i + gallery.length) % gallery.length;
      lbImg.src = gallery[at].src;
      lbImg.alt = gallery[at].alt || '';
      lbTitle.textContent = gallery[at].alt || '';
      lbCount.textContent = (at + 1) + ' / ' + gallery.length;
    };
    var closeLb = function () {
      lb.hidden = true;
      root.classList.remove('lb-lock');
      if (opener) opener.focus();
    };
    document.querySelectorAll('[data-gallery]').forEach(function (b) {
      b.addEventListener('click', function () {
        opener = b;
        show(parseInt(b.getAttribute('data-gallery'), 10) || 0);
        lb.hidden = false;
        root.classList.add('lb-lock');
        lb.querySelector('[data-lb="close"]').focus();
      });
    });
    lb.addEventListener('click', function (e) {
      var b = e.target.closest('[data-lb]');
      if (b) {
        var act = b.getAttribute('data-lb');
        if (act === 'close') closeLb();
        else show(at + (act === 'next' ? 1 : -1));
      } else if (e.target === lb || e.target.classList.contains('lb-fig')) {
        closeLb();
      }
    });
    lb.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') { e.preventDefault(); closeLb(); }
      else if (e.key === 'ArrowRight' && gallery.length > 1) show(at + 1);
      else if (e.key === 'ArrowLeft' && gallery.length > 1) show(at - 1);
      else trapTab(e, lb);
    });
  }

  /* ---------- section nav: marks the section being read and keeps its link in view ---------- */
  var navIn = document.querySelector('.cnav-in');
  if (navIn && 'IntersectionObserver' in window) {
    var links = [].slice.call(navIn.querySelectorAll('a[href^="#"]'));
    var linkFor = {};
    var targets = links.map(function (a) {
      var t = $(a.getAttribute('href').slice(1));
      if (t) linkFor[t.id] = a;
      return t;
    }).filter(Boolean);
    var setCurrent = function (a) {
      links.forEach(function (l) { if (l === a) l.setAttribute('aria-current', 'true'); else l.removeAttribute('aria-current'); });
      if (a.offsetLeft < navIn.scrollLeft || a.offsetLeft + a.offsetWidth > navIn.scrollLeft + navIn.clientWidth) {
        navIn.scrollTo({ left: Math.max(0, a.offsetLeft - 24), behavior: reduce ? 'auto' : 'smooth' });
      }
    };
    var top = (($('hdr') || {}).offsetHeight || 64) + navIn.parentElement.offsetHeight;
    var spy = new IntersectionObserver(function (entries) {
      entries.forEach(function (en) { if (en.isIntersecting && linkFor[en.target.id]) setCurrent(linkFor[en.target.id]); });
    }, { rootMargin: '-' + (top + 8) + 'px 0px -60% 0px', threshold: 0 });
    targets.forEach(function (t) { spy.observe(t); });
  }

  /* ---------- filter menus: one open at a time, closed on outside click or Escape ---------- */
  var menus = [].slice.call(document.querySelectorAll('.dd'));
  if (menus.length) {
    menus.forEach(function (d) {
      d.addEventListener('toggle', function () {
        if (d.open) menus.forEach(function (o) { if (o !== d) o.open = false; });
      });
    });
    document.addEventListener('click', function (e) {
      menus.forEach(function (d) { if (d.open && !d.contains(e.target)) d.open = false; });
    });
    document.addEventListener('keydown', function (e) {
      if (e.key !== 'Escape') return;
      menus.forEach(function (d) {
        if (!d.open) return;
        d.open = false;
        d.querySelector('summary').focus();
      });
    });
  }

  /* ---------- phone filter sheet ---------- */
  var sheet = $('cl-sheet'), sheetBtn = document.querySelector('[data-sheet-open]');
  if (sheet && sheetBtn) {
    var closeSheet = function () {
      sheet.classList.remove('is-open');
      root.classList.remove('sheet-lock');
      sheetBtn.setAttribute('aria-expanded', 'false');
      sheetBtn.focus();
    };
    sheetBtn.addEventListener('click', function () {
      sheet.classList.add('is-open');
      root.classList.add('sheet-lock');
      sheetBtn.setAttribute('aria-expanded', 'true');
      sheet.querySelector('[data-sheet-close]').focus();
    });
    sheet.addEventListener('click', function (e) {
      if (e.target === sheet || e.target.closest('[data-sheet-close]')) closeSheet();
    });
    sheet.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') { e.preventDefault(); closeSheet(); }
      else trapTab(e, sheet);
    });
  }

  /* ---------- listing ----------
     The next page of cards arrives as the visitor nears the end of the grid: that is more.js, asked for by the
     data-more attributes of the grid in city.php. What stays here is the entrance of the cards of the first page. */
  var listing = $('things-to-do');
  var grid = listing && listing.querySelector('.xp-grid');
  // base.js hides a group that starts below the fold and shows it once 8% of it is on screen. A stacked column of
  // 24 cards on a phone is taller than twelve screens, so 8% of it never fits and the cards would stay hidden:
  // here they come in as soon as any of the grid is on screen.
  if (grid && !reduce && 'IntersectionObserver' in window) {
    var gridIn = new IntersectionObserver(function (entries) {
      if (!entries.some(function (en) { return en.isIntersecting; })) return;
      [].forEach.call(grid.children, function (c) { if (c.classList.contains('will-reveal')) c.classList.add('is-in'); });
      gridIn.disconnect();
    }, { rootMargin: '0px 0px -10% 0px' });
    gridIn.observe(grid);
  }

  /* ---------- GetYourGuide: the widget script loads only when its block nears the viewport ---------- */
  var gyg = $('gyg-mount');
  if (gyg) {
    var loaded = false;
    var marketingAllowed = function () {
      try {
        var c = JSON.parse(localStorage.getItem('bo_cookie_consent_v1'));
        return !!(c && c.consent && c.consent.marketing);
      } catch (e) { return false; }
    };
    window.addEventListener('bo-cookie-consent-updated', function () { if (seen) load(); });
    var seen = false;
    var load = function () {
      if (loaded || !marketingAllowed()) return;      // a partner's widget: only with the visitor's consent
      loaded = true;
      var s = document.createElement('script');
      s.async = true;
      s.defer = true;
      s.src = 'https://widget.getyourguide.com/dist/pa.umd.production.min.js';
      document.body.appendChild(s);
    };
    if ('IntersectionObserver' in window) {
      var gio = new IntersectionObserver(function (es) {
        if (es.some(function (x) { return x.isIntersecting; })) { seen = true; load(); gio.disconnect(); }
      }, { rootMargin: '600px' });
      gio.observe(gyg);
    } else {
      load();
    }
  }
  /* The card rails (attractions, nearby cities): a mouse can press and drag them, as a finger does. */
  if (window.EPHDrag) {
    [].forEach.call(document.querySelectorAll('.rail, .ncities'), function (rail) {
      window.EPHDrag(rail, { wheel: false });
      // the browser's own drag of a link or a photo would take the gesture away
      rail.addEventListener('dragstart', function (e) { e.preventDefault(); });
    });
  }
})();

/* Flights: "Flying from". The select of large European cities becomes a field that finds any city or airport
   (Travelpayouts' public place search); the choice is remembered for the next city page. Without this script, or
   when the place search does not answer, the select keeps working. */
(function () {
  'use strict';
  var form = document.getElementById('fl-form');
  var select = document.getElementById('fl-from');
  if (!form || !select || !window.fetch) return;
  var KEY = 'vq_flight_from';
  var to = form.getAttribute('data-to') || '';
  var tpl = form.getAttribute('data-tpl') || '';
  var anyUrl = form.getAttribute('data-any') || '';
  var T = window.VQ ? VQ.t : function (s) { return s; };
  var locale = (window.VQ && VQ.locale) || 'en';

  var box = select.parentNode;
  var hidden = document.createElement('input');
  hidden.type = 'hidden'; hidden.name = 'u'; hidden.value = anyUrl;
  var input = document.createElement('input');
  input.type = 'text'; input.id = 'fl-from'; input.className = 'fl-from-in';
  input.setAttribute('role', 'combobox'); input.setAttribute('aria-autocomplete', 'list');
  input.setAttribute('aria-expanded', 'false'); input.setAttribute('aria-controls', 'fl-from-list');
  input.setAttribute('autocomplete', 'off'); input.setAttribute('spellcheck', 'false');
  input.placeholder = T('City or airport');
  var list = document.createElement('ul');
  list.id = 'fl-from-list'; list.className = 'fl-from-list'; list.setAttribute('role', 'listbox'); list.hidden = true;

  // what the select offered: the suggestions before anything is typed
  var starters = [].map.call(select.options, function (o) {
    return o.getAttribute('data-code') ? { code: o.getAttribute('data-code'), name: o.textContent, sub: '' } : null;
  }).filter(Boolean);

  select.removeAttribute('name'); select.removeAttribute('id'); select.hidden = true;
  box.appendChild(input); box.appendChild(list); form.appendChild(hidden);

  var items = [], active = -1, chosen = null, timer = null, seq = 0;

  function urlFor(code) { return tpl.replace('__FROM__', encodeURIComponent(code)); }
  function close() { list.hidden = true; input.setAttribute('aria-expanded', 'false'); input.removeAttribute('aria-activedescendant'); active = -1; }
  function choose(place, keep) {
    chosen = place;
    input.value = place ? place.name : '';
    hidden.value = place ? urlFor(place.code) : anyUrl;
    close();
    if (keep !== false) {
      try {
        if (place) localStorage.setItem(KEY, JSON.stringify({ code: place.code, name: place.name }));
        else localStorage.removeItem(KEY);
      } catch (e) {}
    }
  }
  function render(rows) {
    items = rows.filter(function (r) { return r.code && r.code !== to; }).slice(0, 7);
    list.textContent = '';
    items.forEach(function (r, i) {
      var li = document.createElement('li');
      li.id = 'fl-from-o' + i; li.setAttribute('role', 'option'); li.setAttribute('aria-selected', 'false');
      var b = document.createElement('b'); b.textContent = r.name;
      var code = document.createElement('span'); code.className = 'fl-from-code'; code.textContent = r.code;
      li.appendChild(b);
      if (r.sub) { var s = document.createElement('small'); s.textContent = r.sub; li.appendChild(s); }
      li.appendChild(code);
      li.addEventListener('mousedown', function (e) { e.preventDefault(); choose(r); });
      list.appendChild(li);
    });
    list.hidden = !items.length;
    input.setAttribute('aria-expanded', items.length ? 'true' : 'false');
    active = -1;
  }
  function mark(i) {
    if (!items.length) return;
    active = (i + items.length) % items.length;
    [].forEach.call(list.children, function (li, n) { li.setAttribute('aria-selected', n === active ? 'true' : 'false'); });
    input.setAttribute('aria-activedescendant', 'fl-from-o' + active);
    list.children[active].scrollIntoView({ block: 'nearest' });
  }
  function search(term) {
    var mine = ++seq;
    fetch('https://autocomplete.travelpayouts.com/places2?locale=' + encodeURIComponent(locale) + '&types[]=city&types[]=airport&term=' + encodeURIComponent(term))
      .then(function (r) { return r.ok ? r.json() : []; })
      .then(function (rows) {
        if (mine !== seq || document.activeElement !== input) return;
        render((rows || []).map(function (r) {
          return { code: r.code, name: r.name, sub: r.type === 'airport' ? [r.city_name, r.country_name].filter(Boolean).join(', ') : (r.country_name || '') };
        }));
      })
      .catch(function () {
        if (mine === seq) render(starters.filter(function (s) { return s.name.toLowerCase().indexOf(term.toLowerCase()) === 0; }));
      });
  }

  input.addEventListener('focus', function () { if (!input.value) render(starters); else input.select(); });
  input.addEventListener('input', function () {
    chosen = null; hidden.value = anyUrl;
    var term = input.value.trim();
    clearTimeout(timer);
    if (term.length < 2) { seq++; render(term ? [] : starters); return; }
    timer = setTimeout(function () { search(term); }, 180);
  });
  input.addEventListener('keydown', function (e) {
    if (e.key === 'ArrowDown') { e.preventDefault(); if (list.hidden) render(items.length ? items : starters); mark(active + 1); }
    else if (e.key === 'ArrowUp') { e.preventDefault(); mark(active - 1); }
    else if (e.key === 'Enter' && !list.hidden && items.length) { e.preventDefault(); choose(items[active < 0 ? 0 : active]); }
    else if (e.key === 'Escape' && !list.hidden) { e.preventDefault(); close(); }
  });
  input.addEventListener('blur', function () {
    // typed but not chosen: take the first suggestion, or search from anywhere
    if (!chosen && input.value.trim() && items.length) choose(items[0]);
    else if (!chosen) { input.value = ''; hidden.value = anyUrl; close(); }
    else close();
  });

  try {
    var saved = JSON.parse(localStorage.getItem(KEY) || 'null');
    if (saved && /^[A-Z]{3}$/.test(saved.code || '') && saved.code !== to && typeof saved.name === 'string') {
      choose({ code: saved.code, name: saved.name.slice(0, 60), sub: '' }, false);
    }
  } catch (e) {}
})();
