/* bilete.online v2: homepage behaviour (hero arch and motion, search, day strip, panels, tiles,
   gift card). Needs base.js, which handles the header, menus, tabs, rails and consent. */
(function () {
  'use strict';
  var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var desktopMQ = window.matchMedia('(min-width: 1024px)');
  var $ = function (id) { return document.getElementById(id); };
  var lenis = null;

  var DATA = {};
  try { DATA = JSON.parse(document.getElementById('v2-data').textContent); } catch (e) {}
  // Loaded only on desktop with motion allowed: phones never download scroll libraries.
  var LIBS = DATA.libs || [];

  // Search suggestions rendered by the page: [label, detail, link].
  var CITIES = DATA.cities || [], ATTRACTIONS = DATA.attractions || [], CATEGORIES = DATA.categories || [];

  var fold = function (s) { return s.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase(); };
  var esc = function (s) { return s.replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); };
  function highlight(text, q) {
    if (!q) return esc(text);
    var i = fold(text).indexOf(fold(q));
    if (i < 0) return esc(text);
    return esc(text.slice(0, i)) + '<mark>' + esc(text.slice(i, i + q.length)) + '</mark>' + esc(text.slice(i + q.length));
  }

  /* ---------- line: draws itself once (CSS clip-path transition) ---------- */
  var line = $('hero-line');
  requestAnimationFrame(function () { requestAnimationFrame(function () { line.classList.add('is-drawn'); }); });

  /* ---------- popovers (Când, Cu cine) ---------- */
  var openPop = null;
  function closePop(returnFocus) {
    if (!openPop) return;
    openPop.pop.hidden = true;
    openPop.btn.setAttribute('aria-expanded', 'false');
    openPop.field.classList.remove('is-open');
    if (returnFocus) openPop.btn.focus();
    openPop = null;
  }
  function bindPop(btnId, popId) {
    var btn = $(btnId), pop = $(popId), field = btn.closest('.sf');
    btn.addEventListener('click', function () {
      var wasOpen = openPop && openPop.pop === pop;
      closePop(false);
      closeSuggest();
      if (wasOpen) return;
      pop.hidden = false;
      btn.setAttribute('aria-expanded', 'true');
      field.classList.add('is-open');
      openPop = { btn: btn, pop: pop, field: field };
      var first = pop.querySelector('[aria-pressed="true"], button, input');
      if (first) first.focus();
    });
  }
  bindPop('when-btn', 'when-pop');
  bindPop('who-btn', 'who-pop');
  document.addEventListener('pointerdown', function (e) {
    if (openPop && !openPop.field.contains(e.target)) closePop(false);
    if (!$('q').closest('.sf').contains(e.target)) closeSuggest();
  });
  document.addEventListener('keydown', function (e) {
    if (e.key !== 'Escape') return;
    if (openPop) { closePop(true); return; }
    if (!$('q-list').hidden) closeSuggest();
  });

  /* Când */
  var fmt = new Intl.DateTimeFormat('ro-RO', { day: 'numeric', month: 'short' });
  var today = new Date();
  function addDays(d, n) { var x = new Date(d); x.setDate(x.getDate() + n); return x; }
  function iso(d) { return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0'); }
  var sat = addDays(today, (6 - today.getDay() + 7) % 7);
  document.querySelectorAll('#when-pop [data-day]').forEach(function (el) {
    var v = el.getAttribute('data-day');
    el.textContent = v === 'weekend' ? fmt.format(sat) + ' - ' + fmt.format(addDays(sat, 1)) : fmt.format(addDays(today, +v));
  });
  $('when-date').min = iso(today);
  function setWhen(label, value) {
    $('when-val').textContent = label;
    $('when-input').value = value;
  }
  document.querySelectorAll('#when-pop .opt').forEach(function (opt) {
    opt.addEventListener('click', function () {
      document.querySelectorAll('#when-pop .opt').forEach(function (o) { o.setAttribute('aria-pressed', String(o === opt)); });
      var w = opt.getAttribute('data-when');
      var value = w === 'azi' ? iso(today) : w === 'maine' ? iso(addDays(today, 1)) : w === 'weekend' ? iso(sat) : '';
      setWhen(opt.firstChild.textContent.trim(), value);
      $('when-date').value = '';
      closePop(true);
    });
  });
  $('when-date').addEventListener('change', function () {
    if (!this.value) return;
    var d = new Date(this.value + 'T12:00:00');
    document.querySelectorAll('#when-pop .opt').forEach(function (o) { o.setAttribute('aria-pressed', 'false'); });
    setWhen(fmt.format(d), this.value);
  });

  /* Cu cine */
  var chips = document.querySelectorAll('#who-pop .chip');
  function syncWho() {
    var picked = [].filter.call(chips, function (c) { return c.getAttribute('aria-pressed') === 'true'; });
    $('who-val').textContent = picked.length ? picked.map(function (c) { return c.textContent; }).join(', ') : 'Oricine';
    $('who-input').value = picked.map(function (c) { return c.getAttribute('data-who'); }).join(',');
  }
  chips.forEach(function (c) {
    c.addEventListener('click', function () { c.setAttribute('aria-pressed', String(c.getAttribute('aria-pressed') !== 'true')); syncWho(); });
  });
  $('who-clear').addEventListener('click', function () { chips.forEach(function (c) { c.setAttribute('aria-pressed', 'false'); }); syncWho(); });
  $('who-done').addEventListener('click', function () { closePop(true); });

  /* ---------- „Unde sau ce” combobox ---------- */
  var q = $('q'), list = $('q-list'), active = -1, options = [];
  function renderSuggest() {
    var term = q.value.trim(), f = fold(term);
    var match = function (s) { return !f || fold(s).indexOf(f) !== -1; };
    var groups = [
      { title: 'Orașe', icon: 'map-pin', items: CITIES.filter(function (c) { return match(c[0]); }).slice(0, term ? 4 : 3) },
      { title: 'Atracții', icon: 'castle-turret', items: ATTRACTIONS.filter(function (a) { return match(a[0]) || match(a[1]); }).slice(0, term ? 5 : 3) },
      { title: 'Categorii', icon: 'ticket', items: CATEGORIES.filter(function (c) { return match(c[0]); }).slice(0, term ? 3 : 2) }
    ];
    var html = '', n = 0;
    groups.forEach(function (g) {
      if (!g.items.length) return;
      html += '<div class="sg-group" role="presentation">' + g.title + '</div>';
      g.items.forEach(function (it) {
        html += '<div class="sg-opt" role="option" id="sg-' + n + '" aria-selected="false" data-value="' + esc(it[0]) + '" data-href="' + esc(it[2] || '') + '">' +
          '<span class="sg-ic" aria-hidden="true"><svg class="ic"><use href="#i-' + g.icon + '"/></svg></span>' +
          '<span><b>' + highlight(it[0], term) + '</b><small>' + esc(it[1] || '') + '</small></span></div>';
        n++;
      });
    });
    list.innerHTML = html || '<div class="sg-empty">Nicio potrivire. Apasă Caută ca să cauți „' + esc(term) + '” în toate experiențele.</div>';
    options = [].slice.call(list.querySelectorAll('.sg-opt'));
    active = -1;
    q.removeAttribute('aria-activedescendant');
  }
  function openSuggest() {
    closePop(false);
    renderSuggest();
    list.hidden = false;
    q.setAttribute('aria-expanded', 'true');
  }
  function closeSuggest() {
    if (list.hidden) return;
    list.hidden = true;
    q.setAttribute('aria-expanded', 'false');
    q.removeAttribute('aria-activedescendant');
  }
  function setActive(i) {
    if (!options.length) return;
    active = (i + options.length) % options.length;
    options.forEach(function (o, k) { o.setAttribute('aria-selected', String(k === active)); });
    q.setAttribute('aria-activedescendant', options[active].id);
    options[active].scrollIntoView({ block: 'nearest' });
  }
  function choose(opt) {
    var href = opt.getAttribute('data-href');
    if (href) { window.location.href = href; return; }
    q.value = opt.getAttribute('data-value');
    closeSuggest();
  }
  q.addEventListener('focus', openSuggest);
  q.addEventListener('input', openSuggest);
  q.addEventListener('keydown', function (e) {
    if (e.key === 'ArrowDown') { e.preventDefault(); if (list.hidden) openSuggest(); setActive(active + 1); }
    else if (e.key === 'ArrowUp') { e.preventDefault(); setActive(active - 1); }
    else if (e.key === 'Enter' && !list.hidden && active > -1) { e.preventDefault(); choose(options[active]); }
    else if (e.key === 'Tab') closeSuggest();
  });
  list.addEventListener('pointerdown', function (e) {
    var opt = e.target.closest('.sg-opt');
    if (opt) { e.preventDefault(); choose(opt); }
  });

  /* ---------- arch geometry ---------- */
  var hero = $('hero'), slot = $('arch-slot'), media = $('hero-media'), img = $('hero-img'),
      copy = $('hero-copy'), feature = $('hero-feature');
  var geo = null;
  function measure() {
    var h = hero.getBoundingClientRect(), s = slot.getBoundingClientRect();
    geo = { W: h.width, H: h.height, L: s.left - h.left, T: s.top - h.top, SW: s.width, SH: s.height };
  }
  function render(p) {
    var W = geo.W, H = geo.H, L = geo.L, T = geo.T, SW = geo.SW, SH = geo.SH, k = 1 - p;
    var top = T * k, left = L * k, right = Math.max(0, W - L - SW) * k, bottom = Math.max(0, H - T - SH) * k;
    var r = ((W - left - right) / 2) * k;
    media.style.clipPath = 'inset(' + top.toFixed(1) + 'px ' + right.toFixed(1) + 'px ' + bottom.toFixed(1) + 'px ' + left.toFixed(1) + 'px round ' + r.toFixed(1) + 'px ' + r.toFixed(1) + 'px 0px 0px)';
    var s0 = Math.max(SW / W, SH / H), s = s0 + (1 - s0) * p;
    var dx = (L + SW / 2 - W / 2) * k, dy = (T + SH / 2 - H / 2) * k;
    img.style.transform = 'translate3d(' + dx.toFixed(1) + 'px,' + dy.toFixed(1) + 'px,0) scale(' + s.toFixed(4) + ')';
  }
  function clearDesktop() {
    media.style.clipPath = '';
    img.style.transform = '';
    media.style.removeProperty('--scrim');
    feature.removeAttribute('style');
  }
  function placeStatic() {
    measure(); render(0);
    media.style.setProperty('--scrim', '1');
    feature.style.left = (geo.L + 16) + 'px';
    feature.style.bottom = '16px';
    feature.style.width = (geo.SW - 32) + 'px';
  }

  var staticOn = false;
  function staticMode(on) {
    if (on === staticOn) { if (on) placeStatic(); return; }
    staticOn = on;
    if (on) {
      hero.classList.remove('is-animated');
      placeStatic();
      window.addEventListener('resize', placeStatic);
    } else {
      window.removeEventListener('resize', placeStatic);
      clearDesktop();
    }
  }

  function loadAll(srcs) {
    return Promise.all(srcs.map(function (src) {
      return new Promise(function (resolve, reject) {
        var s = document.createElement('script');
        s.src = src; s.async = false; s.onload = resolve; s.onerror = reject;
        document.head.appendChild(s);
      });
    }));
  }

  var motionRequested = false;
  function startMotion() {
    if (motionRequested) return;
    motionRequested = true;
    // Paint the precise arch before the libraries arrive, so nothing jumps when they do.
    hero.classList.add('is-animated');
    measure(); render(0);
    loadAll(LIBS).then(initMotion, function () {
      motionRequested = false;
      staticMode(desktopMQ.matches);
    });
  }

  function initMotion() {
    var gsap = window.gsap, ST = window.ScrollTrigger;
    if (!gsap || !ST || !window.Lenis) { staticMode(desktopMQ.matches); return; }
    gsap.registerPlugin(ST);
    gsap.matchMedia().add('(min-width: 1024px)', function () {
      staticMode(false);
      lenis = new Lenis({ autoRaf: false, lerp: 0.12 });
      window.v2Lenis = lenis; // base.js pauses it while the mobile menu is open
      lenis.on('scroll', ST.update);
      var raf = function (t) { lenis.raf(t * 1000); };
      gsap.ticker.add(raf);
      gsap.ticker.lagSmoothing(0);

      hero.classList.add('is-animated');
      var proxy = { p: 0 };
      measure(); render(0);
      var tl = gsap.timeline({
        defaults: { ease: 'none' },
        scrollTrigger: {
          trigger: hero, start: 'top top', end: '+=115%', pin: true, scrub: true, anticipatePin: 1, invalidateOnRefresh: true,
          onRefresh: function () { measure(); render(proxy.p); }
        }
      });
      tl.to(proxy, { p: 1, duration: 1, ease: 'power1.inOut', onUpdate: function () { render(proxy.p); } }, 0)
        .to(copy, { autoAlpha: 0, y: -36, duration: 0.38 }, 0)
        .to(line, { autoAlpha: 0, duration: 0.3 }, 0)
        .to(media, { '--scrim': 1, duration: 0.3 }, 0.62)
        .fromTo(feature, { autoAlpha: 0, y: 18 }, { autoAlpha: 1, y: 0, duration: 0.22 }, 0.78);

      // The featured card leaves before the page content slides under the header.
      gsap.to('.hf-card', {
        autoAlpha: 0, y: -12, ease: 'none',
        scrollTrigger: { trigger: '#main', start: 'top 92%', end: 'top 52%', scrub: true }
      });

      return function () {
        gsap.ticker.remove(raf);
        lenis.destroy();
        lenis = null;
        window.v2Lenis = null;
        hero.classList.remove('is-animated');
        clearDesktop();
      };
    });
  }

  function applyLayout() {
    if (!desktopMQ.matches) { staticMode(false); return; }
    if (reduce) { staticMode(true); return; }
    startMotion();
  }
  applyLayout();
  desktopMQ.addEventListener('change', applyLayout);
})();

(function () {
  'use strict';
  var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var canHover = window.matchMedia('(hover: hover) and (pointer: fine)');
  var $ = function (id) { return document.getElementById(id); };

  /* ---------- Ce faci în zilele următoare ---------- */
  // Each card carries its real available dates for the next ten days (ISO, from the API).
  // The strip is below the fold: set it up when the main thread is idle, not during load.
  var idle = window.requestIdleCallback || function (fn) { return setTimeout(fn, 200); };
  var xpGrid = $('xp-grid');
  if (xpGrid) idle(function () {
    var strip = $('daystrip');
    var cards = [].slice.call(xpGrid.querySelectorAll('.xp'));
    var tiles = strip ? [].slice.call(strip.querySelectorAll('.day[data-date]')) : [];
    var dateInput = $('day-date'), moreTile = dateInput ? dateInput.closest('.day') : null;
    var catTabs = [].slice.call(document.querySelectorAll('#days-tabs [role="tab"]'));
    var state = { date: null, cat: 'all', sort: 'pop' };
    var now = new Date(); now.setHours(12, 0, 0, 0);
    var DOW = ['dum', 'lun', 'mar', 'mie', 'joi', 'vin', 'sâm'];
    var longDay = new Intl.DateTimeFormat('ro-RO', { weekday: 'long', day: 'numeric', month: 'short' });
    var shortDate = new Intl.DateTimeFormat('ro-RO', { day: 'numeric', month: 'short' });
    var plural = function (n, one, many) { return n === 1 ? '1 ' + one : n + ' ' + (n >= 20 && (n % 100 < 1 || n % 100 > 19) ? 'de ' : '') + many; };
    var parse = function (iso) { return new Date(iso + 'T12:00:00'); };
    var isoOf = function (d) { return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0'); };
    var todayIso = isoOf(now);
    var dayDiff = function (iso) { return Math.round((parse(iso) - now) / 864e5); };
    var datesOf = function (card) { var v = card.getAttribute('data-dates'); return v ? v.split(',') : []; };
    var openOn = function (card, iso) { return datesOf(card).indexOf(iso) !== -1; };
    var matchesCat = function (card) { return state.cat === 'all' || card.getAttribute('data-cat') === state.cat; };
    var label = function (iso) { var diff = dayDiff(iso); return diff === 0 ? 'azi' : diff === 1 ? 'mâine' : longDay.format(parse(iso)); };

    // The page can come from the cache: relabel the days against the visitor's own date.
    tiles.forEach(function (t) {
      var iso = t.getAttribute('data-date'), diff = dayDiff(iso);
      t.querySelector('.day-dow').textContent = diff === 0 ? 'Azi' : diff === 1 ? 'Mâine' : DOW[parse(iso).getDay()];
      var sr = t.querySelector('.sr');
      if (!sr) { sr = document.createElement('span'); sr.className = 'sr'; t.appendChild(sr); }
      sr.textContent = ', ' + longDay.format(parse(iso));
    });
    if (dateInput) dateInput.min = todayIso;

    function render() {
      var list = cards.filter(function (c) { return matchesCat(c) && (!state.date || openOn(c, state.date)); });
      list.sort(function (a, b) {
        if (state.sort === 'price') return (+a.dataset.price || 1e9) - (+b.dataset.price || 1e9);
        if (state.sort === 'rating') return b.dataset.rating - a.dataset.rating;
        return a.dataset.pop - b.dataset.pop;
      });
      cards.forEach(function (c) { c.hidden = true; });
      list.slice(0, 8).forEach(function (c) { c.hidden = false; xpGrid.appendChild(c); });
      list.forEach(function (c) {
        var t = c.querySelector('.xp-avail-t');
        var next = state.date || datesOf(c).filter(function (x) { return x >= todayIso; })[0];
        t.textContent = next ? 'Disponibil ' + label(next) : 'Verifică disponibilitatea';
      });
      var priced = list.filter(function (c) { return +c.dataset.price > 0; });
      var min = priced.reduce(function (m, c) { return Math.min(m, +c.dataset.price); }, Infinity);
      $('days-count').innerHTML = '<b>' + plural(list.length, 'opțiune', 'opțiuni') + '</b>' + (priced.length ? ' <span>de la ' + min + ' lei</span>' : '');
      $('days-empty').hidden = list.length > 0;

      if (!tiles.length) return;
      var counts = tiles.map(function (t) {
        var iso = t.getAttribute('data-date');
        return cards.filter(function (c) { return matchesCat(c) && openOn(c, iso); }).length;
      });
      var max = Math.max.apply(null, counts.concat([1]));
      tiles.forEach(function (t, i) {
        var n = counts[i];
        t.querySelector('.day-opt').textContent = plural(n, 'opțiune', 'opțiuni');
        t.style.setProperty('--fill', (n / max).toFixed(2));
        t.style.setProperty('--c', n / max < 0.5 ? 'var(--yellow)' : 'var(--online)');
        t.setAttribute('aria-disabled', String(n === 0));
      });
    }

    tiles.forEach(function (t) {
      t.addEventListener('click', function () {
        if (t.getAttribute('aria-disabled') === 'true') return;
        var on = t.getAttribute('aria-pressed') !== 'true';
        tiles.forEach(function (x) { x.setAttribute('aria-pressed', 'false'); });
        if (moreTile) { moreTile.classList.remove('is-picked'); moreTile.querySelector('.day-opt').textContent = 'Alte date'; }
        t.setAttribute('aria-pressed', String(on));
        state.date = on ? t.getAttribute('data-date') : null;
        render();
      });
    });
    if (dateInput) dateInput.addEventListener('change', function () {
      if (!dateInput.value) return;
      tiles.forEach(function (x) { x.setAttribute('aria-pressed', 'false'); });
      moreTile.classList.add('is-picked');
      moreTile.querySelector('.day-opt').textContent = shortDate.format(parse(dateInput.value));
      state.date = dateInput.value;
      render();
    });
    catTabs.forEach(function (t, i) {
      t.addEventListener('click', function () {
        catTabs.forEach(function (x) { x.setAttribute('aria-selected', String(x === t)); x.tabIndex = x === t ? 0 : -1; });
        state.cat = t.getAttribute('data-cat');
        render();
      });
      t.addEventListener('keydown', function (e) {
        var j = e.key === 'ArrowRight' ? i + 1 : e.key === 'ArrowLeft' ? i - 1 : null;
        if (j === null) return;
        e.preventDefault();
        var target = catTabs[(j + catTabs.length) % catTabs.length];
        target.focus();
        target.click();
      });
    });
    $('days-sort').addEventListener('change', function () { state.sort = this.value; render(); });
    render();
  }, { timeout: 2000 });

  /* ---------- Recomandat pentru: one panel open at a time ---------- */
  var wideMQ = window.matchMedia('(min-width: 1024px)');
  document.querySelectorAll('.who').forEach(function (who) {
    var panels = [].slice.call(who.querySelectorAll('.wp'));
    var hoverTimer = null;
    function open(p) {
      panels.forEach(function (x) {
        var on = x === p;
        x.classList.toggle('is-open', on);
        x.querySelector('.wp-tab').setAttribute('aria-expanded', String(on));
      });
    }
    panels.forEach(function (p) {
      // The whole closed panel is a pointer target; keyboard users get the heading button.
      p.addEventListener('click', function () { if (!p.classList.contains('is-open')) open(p); });
      p.addEventListener('mouseenter', function () {
        if (!canHover.matches || !wideMQ.matches) return;
        clearTimeout(hoverTimer);
        hoverTimer = setTimeout(function () { open(p); }, 220);
      });
      p.addEventListener('mouseleave', function () { clearTimeout(hoverTimer); });
    });
  });

  /* ---------- De ce bilete.online: each tile plays its short sequence once, when it is in view ---------- */
  var btTiles = [].slice.call(document.querySelectorAll('.bt'));
  var thousands = function (n) { return String(n).replace(/\B(?=(\d{3})+(?!\d))/g, '.'); };
  function playTile(tile) {
    tile.classList.add('is-play');
    if (reduce) return;
    tile.querySelectorAll('[data-count]').forEach(function (el) {
      var to = +el.getAttribute('data-count'), t0 = null;
      var step = function (t) {
        if (t0 === null) t0 = t;
        var k = Math.min(1, (t - t0) / 1400);
        el.textContent = thousands(Math.round(to * (1 - Math.pow(1 - k, 3))));
        if (k < 1) requestAnimationFrame(step);
      };
      requestAnimationFrame(step);
    });
  }
  if (reduce || !('IntersectionObserver' in window)) {
    btTiles.forEach(playTile);
  } else {
    // counters start from zero; the real figures stay in the HTML for no-JS and crawlers
    document.querySelectorAll('.bt [data-count]').forEach(function (el) { el.textContent = '0'; });
    var btIO = new IntersectionObserver(function (entries) {
      entries.forEach(function (en) {
        if (!en.isIntersecting) return;
        btIO.unobserve(en.target);
        playTile(en.target);
      });
    }, { threshold: 0.35 });
    btTiles.forEach(function (t) { btIO.observe(t); });
  }

  /* ---------- gift card ---------- */
  var card = $('giftcard');
  if (card) {
    var amount = $('gc-amount'), custom = $('amount-custom'), customWrap = custom.closest('.amount-custom');
    document.querySelectorAll('input[name="gift-amount"]').forEach(function (r) {
      r.addEventListener('change', function () {
        if (!r.checked) return;
        var isCustom = r.value === 'custom';
        customWrap.classList.toggle('is-on', isCustom);
        amount.textContent = isCustom ? (parseInt(custom.value, 10) || 0) : r.value;
      });
    });
    custom.addEventListener('input', function () {
      var v = Math.max(0, Math.min(5000, parseInt(custom.value || '0', 10) || 0));
      amount.textContent = v;
    });
    if (!reduce && canHover.matches) {
      card.addEventListener('pointerenter', function () { card.classList.add('is-tilting'); });
      card.addEventListener('pointermove', function (e) {
        var r = card.getBoundingClientRect(), x = (e.clientX - r.left) / r.width, y = (e.clientY - r.top) / r.height;
        card.style.transform = 'rotateY(' + ((x - 0.5) * 12).toFixed(2) + 'deg) rotateX(' + ((0.5 - y) * 10).toFixed(2) + 'deg)';
        card.style.setProperty('--gx', (x * 100).toFixed(1) + '%');
        card.style.setProperty('--gy', (y * 100).toFixed(1) + '%');
      });
      card.addEventListener('pointerleave', function () {
        card.classList.remove('is-tilting');
        card.style.transform = '';
      });
    }
  }
})();
