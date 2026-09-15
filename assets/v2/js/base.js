/* bilete.online v2: behaviour shared by every page (header, mega menu, mobile menu, tabs, rails,
   reveal on enter, brand-line drawing) and the cookie consent. Page scripts load after it. */
(function () {
  'use strict';
  var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var canHover = window.matchMedia('(hover: hover) and (pointer: fine)');
  var desktopMQ = window.matchMedia('(min-width: 1024px)');
  var $ = function (id) { return document.getElementById(id); };
  var hdr = $('hdr');

  /* ---------- header: solid once the content reaches it (pages with a hero; the others render it solid) ---------- */
  // Checked on scroll, not with an IntersectionObserver: on a hero taller than the screen the sentinel starts below
  // the fold, and an anchor jump straight past it (below the fold -> above the header) is no change in intersection,
  // so the observer never fired and the header stayed transparent over the content.
  var sentinel = $('hdr-sentinel');
  if (hdr && sentinel) {
    var solidQueued = false;
    var checkSolid = function () {
      solidQueued = false;
      hdr.classList.toggle('is-solid', sentinel.getBoundingClientRect().top < hdr.offsetHeight + 1);
    };
    window.addEventListener('scroll', function () {
      if (!solidQueued) { solidQueued = true; window.requestAnimationFrame(checkSolid); }
    }, { passive: true });
    window.addEventListener('resize', checkSolid);
    checkSolid();
  }

  /* ---------- mobile menu ----------
     Opens as a circle growing from the menu button, the sections (Explorează, Activități, Inspirație) slide in as
     panels with a back button, categories unfold their types, and the search suggests cities, categories and guides
     from #mm-data while typing. Escape steps back, then closes; focus stays inside while it is open. */
  var menu = $('menu'), menuBtn = $('menu-btn');
  if (hdr && menu && menuBtn && menu.querySelector('.mm-sheet')) (function () {
    var sheet = menu.querySelector('.mm-sheet'), root = $('mm-root'), current = root;
    var q = $('mm-q'), sug = $('mm-suggest'), closeTimer = null, lastFocus = null, active = -1;
    var OPEN_MS = reduce ? 0 : 620, CLOSE_MS = reduce ? 0 : 440, PANEL_MS = reduce ? 0 : 540;
    var data = null;
    try { data = JSON.parse(($('mm-data') || {}).textContent || 'null'); } catch (x) { data = null; }

    function isOpen() { return menu.classList.contains('is-open'); }
    function place() {
      var b = menuBtn.getBoundingClientRect(), x = b.left + b.width / 2;
      sheet.style.setProperty('--mx', Math.round(x) + 'px');
      sheet.style.setProperty('--mr', Math.ceil(Math.hypot(Math.max(x, window.innerWidth - x), window.innerHeight)) + 'px');
    }
    function setOpen(open, opts) {
      opts = opts || {};
      if (open === isOpen()) return;
      clearTimeout(closeTimer);
      hdr.classList.toggle('menu-open', open);
      menuBtn.setAttribute('aria-expanded', String(open));
      menuBtn.setAttribute('aria-label', open ? 'Închide meniul' : 'Deschide meniul');
      menuBtn.querySelector('use').setAttribute('href', open ? '#i-x' : '#i-list');
      document.documentElement.classList.toggle('mm-lock', open);
      // smooth scrolling (homepage, desktop) pauses while the menu is open
      if (window.v2Lenis) { if (open) window.v2Lenis.stop(); else window.v2Lenis.start(); }
      if (open) {
        lastFocus = document.activeElement;
        menu.classList.remove('is-closing');
        resetPanels();
        menu.hidden = false;
        place();
        void sheet.offsetWidth;
        menu.classList.add('is-open');
        if (opts.search) q.focus({ preventScroll: true }); else sheet.focus({ preventScroll: true });
      } else {
        hideSuggest();
        menu.classList.add('is-closing');
        menu.classList.remove('is-open');
        closeTimer = setTimeout(function () { menu.hidden = true; menu.classList.remove('is-closing'); }, CLOSE_MS);
        if (opts.restore !== false) (lastFocus && document.contains(lastFocus) && lastFocus !== document.body ? lastFocus : menuBtn).focus({ preventScroll: true });
      }
    }

    /* panels */
    function afterMove(el, fn) {
      if (!PANEL_MS) { fn(); return; }
      var done = false;
      var go = function (e) {
        if (done || (e && (e.target !== el || e.propertyName !== 'transform'))) return;
        done = true;
        el.removeEventListener('transitionend', go);
        fn();
      };
      el.addEventListener('transitionend', go);
      setTimeout(go, PANEL_MS + 80);
    }
    function show(panel, back) {
      if (!panel || panel === current) return;
      var from = current;
      current = panel;
      hideSuggest();
      panel.hidden = false;
      panel.classList.remove('is-in');
      panel.classList.add(back ? 'is-left' : 'is-right');
      if (!back) panel.scrollTop = 0;
      void panel.offsetWidth;
      panel.classList.remove('is-left', 'is-right');
      if (!back) panel.classList.add('is-in');
      from.classList.add(back ? 'is-right' : 'is-left');
      afterMove(from, function () {
        if (from === current) return;
        from.hidden = true;
        from.classList.remove('is-left', 'is-right', 'is-in');
      });
      var target = back ? root.querySelector('[data-mm-go="' + from.id + '"]') : panel.querySelector('.mm-back');
      if (target) target.focus({ preventScroll: true });
    }
    function resetPanels() {
      [].forEach.call(menu.querySelectorAll('.mm-panel'), function (p) {
        p.hidden = p !== root;
        p.classList.remove('is-left', 'is-right', 'is-in');
      });
      current = root;
    }

    menuBtn.addEventListener('click', function () { setOpen(!isOpen()); });
    [].forEach.call(document.querySelectorAll('[data-mm-search]'), function (b) {
      b.addEventListener('click', function () { if (isOpen()) q.focus(); else setOpen(true, { search: true }); });
    });
    menu.addEventListener('click', function (e) {
      var t = e.target.nodeType === 1 ? e.target : e.target.parentElement;
      if (!t) return;
      var go = t.closest('[data-mm-go]'), tg = t.closest('.mm-cat-tg');
      if (t.closest('[data-mm-close]')) { setOpen(false); return; }
      if (go) { show($(go.getAttribute('data-mm-go'))); return; }
      if (t.closest('[data-mm-back]')) { show(root, true); return; }
      if (tg) {
        var on = tg.getAttribute('aria-expanded') !== 'true', sub = $(tg.getAttribute('aria-controls'));
        tg.setAttribute('aria-expanded', String(on));
        if (sub) sub.classList.toggle('is-open', on);
        return;
      }
    });
    document.addEventListener('keydown', function (e) {
      if (!isOpen()) return;
      if (e.key === 'Escape') {
        e.preventDefault();
        if (!sug.hidden) hideSuggest();
        else if (current !== root) show(root, true);
        else setOpen(false);
        return;
      }
      if (e.key !== 'Tab') return;
      var list = [menuBtn].concat([].slice.call(menu.querySelectorAll('.mm-searchbar input, .mm-searchbar button, .mm-searchbar a[href]')), [].slice.call(current.querySelectorAll('a[href], button')))
        .filter(function (el) { return el.getClientRects().length && getComputedStyle(el).visibility !== 'hidden'; });
      var i = list.indexOf(document.activeElement);
      if (i < 0 || (e.shiftKey && i === 0) || (!e.shiftKey && i === list.length - 1)) {
        e.preventDefault();
        list[e.shiftKey ? (i <= 0 ? list.length - 1 : i - 1) : (i < 0 || i === list.length - 1 ? 0 : i + 1)].focus();
      }
    });
    desktopMQ.addEventListener('change', function (m) { if (m.matches && isOpen()) setOpen(false, { restore: false }); });

    /* search suggestions */
    function fold(s) { return String(s || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase(); }
    function hideSuggest() {
      sug.hidden = true;
      sug.textContent = '';
      q.setAttribute('aria-expanded', 'false');
      q.removeAttribute('aria-activedescendant');
      active = -1;
    }
    function icon(name) {
      var svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg'), use = document.createElementNS('http://www.w3.org/2000/svg', 'use');
      svg.setAttribute('class', 'ic');
      svg.setAttribute('aria-hidden', 'true');
      use.setAttribute('href', '#i-' + name);
      svg.appendChild(use);
      return svg;
    }
    function option(id, href, ic, label, needle, meta, extra) {
      var a = document.createElement('a'), b = document.createElement('b'), f = fold(label), at = needle ? f.indexOf(needle) : -1;
      a.className = 'mm-sg' + (extra || '');
      a.href = href;
      a.id = id;
      a.setAttribute('role', 'option');
      a.setAttribute('aria-selected', 'false');
      a.appendChild(icon(ic));
      if (at > -1 && f.length === label.length) {
        var m = document.createElement('mark');
        m.textContent = label.slice(at, at + needle.length);
        b.appendChild(document.createTextNode(label.slice(0, at)));
        b.appendChild(m);
        b.appendChild(document.createTextNode(label.slice(at + needle.length)));
      } else {
        b.textContent = label;
      }
      a.appendChild(b);
      if (meta) { var s = document.createElement('small'); s.textContent = meta; a.appendChild(s); }
      return a;
    }
    function hits(list, needle, max) {
      var first = [], rest = [];
      for (var i = 0; i < list.length && first.length < max; i++) {
        var f = fold(list[i][0]), at = f.indexOf(needle);
        if (at === 0 || (at > 0 && /[\s\-(]/.test(f.charAt(at - 1)))) first.push(list[i]);
        else if (at > 0 && rest.length < max) rest.push(list[i]);
      }
      return first.concat(rest).slice(0, max);
    }
    function suggest() {
      var text = q.value.trim(), needle = fold(text);
      if (!data || needle.length < 2) { hideSuggest(); return; }
      sug.textContent = '';
      var n = 0;
      [['Orașe', 'map-pin', data.c || [], 5], ['Categorii', 'squares-four', data.k || [], 3], ['Ghiduri', 'sun', data.g || [], 2]].forEach(function (g) {
        var found = hits(g[2], needle, g[3]);
        if (!found.length) return;
        var k = document.createElement('p');
        k.className = 'mm-sg-k';
        k.textContent = g[0];
        sug.appendChild(k);
        found.forEach(function (it) {
          var href = it[it.length - 1];
          if (typeof href !== 'string' || !/^\/(?!\/)/.test(href)) return;
          sug.appendChild(option('mm-sg-' + n++, href, g[1], String(it[0]), needle, it.length > 2 ? it[1] : ''));
        });
      });
      sug.appendChild(option('mm-sg-' + n++, '/cauta?q=' + encodeURIComponent(text), 'magnifying-glass', 'Caută „' + text + '”', '', '', ' mm-sg-all'));
      sug.hidden = false;
      q.setAttribute('aria-expanded', 'true');
      active = -1;
    }
    function move(step) {
      var opts = [].slice.call(sug.querySelectorAll('.mm-sg'));
      if (!opts.length) return;
      if (opts[active]) opts[active].setAttribute('aria-selected', 'false');
      active = (active + step + opts.length) % opts.length;
      opts[active].setAttribute('aria-selected', 'true');
      opts[active].scrollIntoView({ block: 'nearest' });
      q.setAttribute('aria-activedescendant', opts[active].id);
    }
    q.addEventListener('input', suggest);
    q.addEventListener('focus', function () { if (q.value.trim().length > 1 && sug.hidden) suggest(); });
    q.addEventListener('keydown', function (e) {
      if (e.key === 'ArrowDown' || e.key === 'ArrowUp') { if (sug.hidden) suggest(); e.preventDefault(); move(e.key === 'ArrowDown' ? 1 : -1); }
      else if (e.key === 'Enter' && active > -1) {
        var opt = sug.querySelectorAll('.mm-sg')[active];
        if (opt) { e.preventDefault(); window.location.href = opt.href; }
      }
    });
  })();

  /* ---------- tabs: [data-tabs] role=tablist, panels via aria-controls ---------- */
  function bindTabs(list) {
    var tabs = [].slice.call(list.querySelectorAll('[role="tab"]'));
    var vertical = list.getAttribute('aria-orientation') === 'vertical';
    function select(tab, focus) {
      tabs.forEach(function (t) {
        var on = t === tab;
        t.setAttribute('aria-selected', String(on));
        t.tabIndex = on ? 0 : -1;
        var panel = $(t.getAttribute('aria-controls'));
        if (panel) panel.hidden = !on;
      });
      if (focus) tab.focus();
    }
    tabs.forEach(function (t, i) {
      t.addEventListener('click', function () { select(t); });
      if (list.hasAttribute('data-hover')) {
        t.addEventListener('mouseenter', function () { if (canHover.matches) select(t); });
      }
      t.addEventListener('keydown', function (e) {
        var next = vertical ? 'ArrowDown' : 'ArrowRight', prev = vertical ? 'ArrowUp' : 'ArrowLeft';
        var j = e.key === next ? i + 1 : e.key === prev ? i - 1 : e.key === 'Home' ? 0 : e.key === 'End' ? tabs.length - 1 : null;
        if (j === null) return;
        e.preventDefault();
        select(tabs[(j + tabs.length) % tabs.length], true);
      });
    });
  }
  document.querySelectorAll('[data-tabs]').forEach(bindTabs);

  /* ---------- mega menu ---------- */
  var megaBtns = hdr ? [].slice.call(hdr.querySelectorAll('[data-mega]')) : [];
  var openId = null, openTimer = null, closeTimer = null;
  function setMega(id, focusFirst) {
    megaBtns.forEach(function (b) {
      var bid = b.getAttribute('data-mega'), on = bid === id;
      b.setAttribute('aria-expanded', String(on));
      $('mega-' + bid).hidden = !on;
    });
    openId = id;
    hdr.classList.toggle('mega-open', !!id);
    if (id && focusFirst) {
      var first = $('mega-' + id).querySelector('[role="tab"][aria-selected="true"], a, button');
      if (first) first.focus();
    }
  }
  if (megaBtns.length) {
    megaBtns.forEach(function (b) {
      var id = b.getAttribute('data-mega');
      b.addEventListener('click', function () {
        clearTimeout(openTimer);
        setMega(openId === id ? null : id);
      });
      b.addEventListener('keydown', function (e) {
        if (e.key === 'ArrowDown') { e.preventDefault(); setMega(id, true); }
      });
      b.addEventListener('mouseenter', function () {
        if (!canHover.matches) return;
        clearTimeout(closeTimer);
        clearTimeout(openTimer);
        openTimer = setTimeout(function () { setMega(id); }, openId ? 0 : 110);
      });
      b.addEventListener('mouseleave', function () { clearTimeout(openTimer); });
    });
    hdr.querySelectorAll('.mnav-link, .hdr-tools a, .hdr-tools button, .brand, .hdr-search').forEach(function (el) {
      el.addEventListener('mouseenter', function () { if (canHover.matches && openId) setMega(null); });
    });
    hdr.addEventListener('mouseleave', function () {
      if (!canHover.matches || !openId) return;
      closeTimer = setTimeout(function () { setMega(null); }, 240);
    });
    hdr.addEventListener('mouseenter', function () { clearTimeout(closeTimer); });
    hdr.addEventListener('focusout', function (e) {
      if (openId && e.relatedTarget && !hdr.contains(e.relatedTarget)) setMega(null);
    });
    document.addEventListener('keydown', function (e) {
      if (e.key !== 'Escape' || !openId) return;
      var btn = hdr.querySelector('[data-mega="' + openId + '"]');
      setMega(null);
      if (btn) btn.focus();
    });
    document.addEventListener('pointerdown', function (e) {
      if (openId && !hdr.contains(e.target)) setMega(null);
    });
    desktopMQ.addEventListener('change', function (m) { if (!m.matches) setMega(null); });
  }

  /* ---------- decorative lines drawn once on enter ---------- */
  var drawEls = [].slice.call(document.querySelectorAll('.draw-clip'));
  if (reduce || !('IntersectionObserver' in window)) {
    drawEls.forEach(function (el) { el.classList.add('is-drawn'); });
  } else if (drawEls.length) {
    // Observe the container: a line clipped to nothing reports no intersection itself.
    var drawIO = new IntersectionObserver(function (entries) {
      entries.forEach(function (en) {
        if (!en.isIntersecting) return;
        drawEls.forEach(function (el) { if (el.parentElement === en.target) el.classList.add('is-drawn'); });
        drawIO.unobserve(en.target);
      });
    }, { threshold: 0.15 });
    drawEls.forEach(function (el) { drawIO.observe(el.parentElement); });
  }

  /* ---------- reveal on enter ----------
     Content is visible by default. Only groups that start below the fold are hidden,
     then cascade in once when they enter the viewport. */
  if (!reduce && 'IntersectionObserver' in window) {
    var revealIO = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (!entry.isIntersecting) return;
        [].forEach.call(entry.target.children, function (child) { child.classList.add('is-in'); });
        revealIO.unobserve(entry.target);
      });
    }, { rootMargin: '0px 0px -10% 0px', threshold: 0.08 });
    document.querySelectorAll('[data-reveal]').forEach(function (group) {
      if (group.getBoundingClientRect().top < window.innerHeight) return;
      [].forEach.call(group.children, function (child, i) {
        child.style.setProperty('--i', i);
        child.classList.add('will-reveal');
      });
      revealIO.observe(group);
    });
  }

  /* ---------- language menu (the site is in Romanian) ---------- */
  var langBtn = $('lang-btn'), langMenu = $('lang-menu');
  if (langBtn && langMenu) {
    var setLang = function (open) { langMenu.hidden = !open; langBtn.setAttribute('aria-expanded', String(open)); };
    langBtn.addEventListener('click', function (e) { e.stopPropagation(); setLang(langMenu.hidden); });
    document.addEventListener('click', function (e) { if (!langMenu.hidden && !langMenu.contains(e.target)) setLang(false); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !langMenu.hidden) { setLang(false); langBtn.focus(); } });
  }

  /* ---------- rails with prev/next ---------- */
  document.querySelectorAll('.rail-btns').forEach(function (group) {
    var rail = $(group.getAttribute('data-for'));
    if (!rail) return;
    var prev = group.querySelector('[data-dir="-1"]'), next = group.querySelector('[data-dir="1"]');
    function step() {
      var item = rail.querySelector('li');
      var gap = parseFloat(getComputedStyle(rail).columnGap) || 0;
      return item ? item.getBoundingClientRect().width + gap : 320;
    }
    function update() {
      prev.disabled = rail.scrollLeft < 4;
      next.disabled = rail.scrollLeft + rail.clientWidth > rail.scrollWidth - 4;
    }
    [prev, next].forEach(function (b) {
      b.addEventListener('click', function () {
        rail.scrollBy({ left: +b.getAttribute('data-dir') * step(), behavior: reduce ? 'auto' : 'smooth' });
      });
    });
    var ticking = false;
    rail.addEventListener('scroll', function () {
      if (ticking) return;
      ticking = true;
      requestAnimationFrame(function () { ticking = false; update(); });
    }, { passive: true });
    window.addEventListener('resize', update);
    update();
    // data-drag: the mouse can drag the rail sideways; a drag never opens the card under the pointer
    if (rail.hasAttribute('data-drag')) {
      var drag = null, dragged = false;
      rail.addEventListener('pointerdown', function (e) {
        if (e.pointerType !== 'mouse' || e.button !== 0) return;
        drag = { x: e.clientX, left: rail.scrollLeft, id: e.pointerId };
        dragged = false;
      });
      rail.addEventListener('pointermove', function (e) {
        if (!drag || e.pointerId !== drag.id) return;
        var dx = e.clientX - drag.x;
        if (!dragged && Math.abs(dx) < 6) return;
        if (!dragged) { dragged = true; rail.classList.add('is-dragging'); }
        rail.scrollLeft = drag.left - dx;
      });
      var endDrag = function (e) {
        if (!drag || (e && e.pointerId !== drag.id)) return;
        drag = null;
        rail.classList.remove('is-dragging');
      };
      rail.addEventListener('pointercancel', endDrag);
      window.addEventListener('pointerup', endDrag);
      rail.addEventListener('click', function (e) { if (dragged) { e.preventDefault(); e.stopPropagation(); dragged = false; } }, true);
      rail.addEventListener('dragstart', function (e) { e.preventDefault(); });
    }
  });
})();

/* ---------- header state and newsletter forms ----------
   The cart count and the signed-in customer come from the same localStorage keys as assets/js/cart.js and
   assets/js/auth.js. Newsletter forms (form[data-newsletter="source"]) post to the same proxy action as the
   old pages; data-ok / data-err hold the messages, data-keep locks the form after a successful sign-up. */
(function () {
  'use strict';
  function stored(key) {
    try { return JSON.parse(localStorage.getItem(key)); } catch (e) { return null; }
  }

  var badge = document.querySelector('[data-cart-count]');
  function renderCart() {
    var cart = stored('bileteonline_cart'), n = 0;
    if (cart && Array.isArray(cart.items)) {
      cart.items.forEach(function (it) { n += parseInt(it && it.quantity, 10) || 0; });
    }
    badge.textContent = n > 99 ? '99+' : String(n);
    badge.hidden = n < 1;
    badge.parentElement.setAttribute('aria-label', n > 0 ? 'Coșul de cumpărături (' + n + ')' : 'Coșul de cumpărături');
  }
  if (badge) {
    renderCart();
    window.addEventListener('storage', function (e) { if (e.key === 'bileteonline_cart') renderCart(); });
    window.addEventListener('bileteonline:cart:update', renderCart);
  }

  var acct = document.querySelector('[data-account]');
  var userType = null, token = null;
  try {
    userType = localStorage.getItem('bileteonline_user_type');
    token = localStorage.getItem('bileteonline_customer_token');
  } catch (e) {}
  var user = acct && token && (!userType || userType === 'customer') ? stored('bileteonline_customer_data') : null;
  if (user && typeof user === 'object') {
    var full = ((user.first_name || '') + ' ' + (user.last_name || '')).trim() || user.name || '';
    var initials = full.split(/\s+/).filter(Boolean).map(function (s) { return s.charAt(0); }).join('').slice(0, 2).toUpperCase()
      || String(user.email || '').charAt(0).toUpperCase();
    var box = acct.querySelector('.acct-ini');
    if (initials && box) {
      box.textContent = initials;
      box.hidden = false;
      acct.classList.add('is-user');
      acct.setAttribute('aria-label', 'Contul tău' + (full ? ', ' + full : ''));
    }
  }

  [].forEach.call(document.querySelectorAll('form[data-newsletter]'), function (form) {
    var btn = form.querySelector('button[type="submit"]'), msg = document.getElementById(form.getAttribute('data-msg'));
    var label = btn ? btn.innerHTML : '', busy = false;
    function say(ok, text) {
      if (!msg) return;
      msg.textContent = text;
      msg.classList.toggle('is-ok', ok);
      msg.classList.toggle('is-err', !ok);
      msg.hidden = false;
    }
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      var email = form.elements.email;
      if (busy || !btn || !email || email.disabled) return;
      if (!email.checkValidity()) { email.reportValidity(); return; }
      busy = true;
      btn.disabled = true;
      if (btn.hasAttribute('data-icon-only')) btn.setAttribute('aria-busy', 'true'); else btn.textContent = 'Se trimite…';
      var payload = { email: email.value.trim(), source: form.getAttribute('data-newsletter') };
      if (form.elements.city && form.elements.city.value) payload.city = form.elements.city.value;
      fetch('/api/proxy.php?action=newsletter.subscribe', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
        body: JSON.stringify(payload)
      }).then(function (r) {
        return r.json().catch(function () { return {}; }).then(function (d) {
          if (r.ok && d.success !== false) return;
          var err = new Error((d && d.message) || '');
          err.fromServer = true;
          throw err;
        });
      }).then(function () {
        say(true, form.getAttribute('data-ok'));
        if (form.hasAttribute('data-keep')) {
          email.disabled = true;
          btn.innerHTML = '<svg class="ic" aria-hidden="true"><use href="#i-check"/></svg>Gata';
        } else {
          form.reset();
          btn.disabled = false; btn.removeAttribute('aria-busy');
          btn.innerHTML = label;
        }
      }).catch(function (err) {
        say(false, err && err.fromServer && err.message ? err.message : form.getAttribute('data-err'));
        btn.disabled = false; btn.removeAttribute('aria-busy');
        btn.innerHTML = label;
      }).then(function () { busy = false; });
    });
  });
})();

/* ---------- cookie consent ----------
   Same storage key, version and `bo-cookie-consent-updated` event as includes/cookie-consent.php and the
   consent-mode snippet in the <head>, so a choice holds on every page. */
(function () {
  'use strict';
  var KEY = 'bo_cookie_consent_v1', VERSION = '2026-05-26';
  var banner = document.getElementById('cc-banner'), dialog = document.getElementById('cc-dialog');
  if (!banner || !dialog) return;
  var toggles = [].slice.call(dialog.querySelectorAll('input[data-cc]'));
  var lastFocus = null;

  function read() {
    try {
      var s = JSON.parse(localStorage.getItem(KEY));
      return s && s.version === VERSION && s.consent ? s : null;
    } catch (e) { return null; }
  }
  function persist(consent, source) {
    var payload = {
      version: VERSION,
      source: source,
      consent: { essential: true, analytics: !!consent.analytics, personalization: !!consent.personalization, marketing: !!consent.marketing },
      savedAt: new Date().toISOString()
    };
    try { localStorage.setItem(KEY, JSON.stringify(payload)); } catch (e) {}
    banner.hidden = true;
    closeDialog(false);
    window.dispatchEvent(new CustomEvent('bo-cookie-consent-updated', { detail: payload }));
  }
  function openDialog() {
    var saved = read();
    lastFocus = document.activeElement;
    toggles.forEach(function (t) { t.checked = !!(saved && saved.consent[t.getAttribute('data-cc')]); });
    banner.hidden = true;
    dialog.hidden = false;
    (toggles[0] || dialog.querySelector('button')).focus();
  }
  function closeDialog(showBannerAgain) {
    if (dialog.hidden) return;
    dialog.hidden = true;
    if (showBannerAgain && !read()) banner.hidden = false;
    if (lastFocus && lastFocus.focus) lastFocus.focus();
  }

  if (!read()) banner.hidden = false;

  document.addEventListener('click', function (e) {
    var b = e.target.closest ? e.target.closest('[data-cc-action]') : null;
    if (!b) return;
    var action = b.getAttribute('data-cc-action');
    if (action === 'accept') persist({ analytics: true, personalization: true, marketing: true }, 'accept_all');
    else if (action === 'reject') persist({}, 'reject_optional');
    else if (action === 'save') {
      var c = {};
      toggles.forEach(function (t) { c[t.getAttribute('data-cc')] = t.checked; });
      persist(c, 'save_preferences');
    }
    else if (action === 'open') openDialog();
    else if (action === 'close') closeDialog(true);
  });
  window.addEventListener('bo-open-cookie-preferences', openDialog);
  dialog.addEventListener('click', function (e) { if (e.target === dialog) closeDialog(true); });
  dialog.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') { e.stopPropagation(); closeDialog(true); return; }
    if (e.key !== 'Tab') return;
    var f = [].slice.call(dialog.querySelectorAll('button, input:not([disabled]), a[href]'));
    var first = f[0], last = f[f.length - 1];
    if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
    else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
  });
})();

/* ---------- invite links: https://<site>/?ref=<code> ----------
   Core builds referral links this way. The code used to be caught by auth.js, which the v2 pages don't load, so nobody
   who arrived through an invite was credited. Check the code once (the public validate action also counts the click),
   keep it where BileteOnlineAuth.registerCustomer() looks for it (it is sent as referral_code and cleared after
   sign-up), and take ?ref out of the address bar. Visitors already signed in have nothing to be credited for. */
(function () {
  'use strict';
  var KEY = 'bileteonline_referral_code', INFO = 'bileteonline_referral_info', code = '';
  try { code = (new URLSearchParams(window.location.search).get('ref') || '').trim(); } catch (e) { return; }
  if (!code) return;
  function clean() {
    try {
      var url = new URL(window.location.href);
      url.searchParams.delete('ref');
      history.replaceState(history.state, '', url.pathname + url.search + url.hash);
    } catch (e) {}
  }
  try {
    var signedIn = localStorage.getItem('bileteonline_customer_token') || localStorage.getItem('bileteonline_organizer_token');
    if (!/^[A-Za-z0-9_-]{3,20}$/.test(code) || signedIn || localStorage.getItem(KEY) === code) { clean(); return; }
  } catch (e) { clean(); return; }
  fetch('/api/proxy.php?action=customer.referrals.validate&code=' + encodeURIComponent(code), { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
    .then(function (r) { return r.ok ? r.json() : null; })
    .then(function (res) {
      if (!(res && res.success && res.data && res.data.valid)) return;
      try {
        localStorage.setItem(KEY, code);
        localStorage.setItem(INFO, JSON.stringify(res.data));
      } catch (e) {}
    })
    .catch(function () {})
    .then(clean);
})();
