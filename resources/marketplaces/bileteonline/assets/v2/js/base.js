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
  var sentinel = $('hdr-sentinel');
  if (hdr && sentinel && 'IntersectionObserver' in window) {
    var io = null;
    var watchHeader = function () {
      if (io) io.disconnect();
      var h = hdr.offsetHeight;
      io = new IntersectionObserver(function (entries) {
        var e = entries[0];
        hdr.classList.toggle('is-solid', !e.isIntersecting && e.boundingClientRect.top < h + 1);
      }, { rootMargin: '-' + h + 'px 0px 0px 0px', threshold: 0 });
      io.observe(sentinel);
    };
    watchHeader();
    desktopMQ.addEventListener('change', watchHeader);
  }

  /* ---------- mobile menu ---------- */
  var menu = $('menu'), menuBtn = $('menu-btn');
  function toggleMenu(open) {
    menu.classList.toggle('is-open', open);
    hdr.classList.toggle('menu-open', open);
    menuBtn.setAttribute('aria-expanded', String(open));
    menuBtn.setAttribute('aria-label', open ? 'Închide meniul' : 'Deschide meniul');
    menuBtn.querySelector('use').setAttribute('href', open ? '#i-x' : '#i-list');
    // smooth scrolling (homepage, desktop) pauses while the menu is open
    if (window.v2Lenis) { if (open) window.v2Lenis.stop(); else window.v2Lenis.start(); }
  }
  if (hdr && menu && menuBtn) {
    menuBtn.addEventListener('click', function () { toggleMenu(!menu.classList.contains('is-open')); });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && menu.classList.contains('is-open')) toggleMenu(false);
    });
  }

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
      btn.textContent = 'Se trimite…';
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
          btn.disabled = false;
          btn.innerHTML = label;
        }
      }).catch(function (err) {
        say(false, err && err.fromServer && err.message ? err.message : form.getAttribute('data-err'));
        btn.disabled = false;
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
