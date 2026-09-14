/* bilete.online v2: city page. Rotating category cards in the hero, photo gallery, a section nav that follows
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

  /* ---------- GetYourGuide: the widget script loads only when its block nears the viewport ---------- */
  var gyg = $('gyg-mount');
  if (gyg) {
    var loaded = false;
    var load = function () {
      if (loaded) return;
      loaded = true;
      var s = document.createElement('script');
      s.async = true;
      s.defer = true;
      s.src = 'https://widget.getyourguide.com/dist/pa.umd.production.min.js';
      document.body.appendChild(s);
    };
    if ('IntersectionObserver' in window) {
      var gio = new IntersectionObserver(function (es) {
        if (es.some(function (x) { return x.isIntersecting; })) { load(); gio.disconnect(); }
      }, { rootMargin: '600px' });
      gio.observe(gyg);
    } else {
      load();
    }
  }
})();
