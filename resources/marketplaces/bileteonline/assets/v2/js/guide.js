/* bilete.online v2: single guide. The reading-progress line, the contents (folding on phones, the section in view
   marked), copying the link (with feedback) and the phone's own share sheet where there is one; the recommendations
   rail is base.js. */
(function () {
  'use strict';
  var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  /* ---------- reading progress: how far through the text ---------- */
  var bar = document.getElementById('gd-progress'), prose = document.getElementById('gd-prose');
  if (bar && prose) {
    var ticking = false;
    var measure = function () {
      ticking = false;
      var r = prose.getBoundingClientRect(), span = r.height - window.innerHeight * 0.6;
      var done = span > 0 ? Math.min(1, Math.max(0, (window.innerHeight * 0.4 - r.top) / span)) : (r.top < window.innerHeight ? 1 : 0);
      bar.style.transform = 'scaleX(' + done.toFixed(4) + ')';
    };
    var onScroll = function () { if (!ticking) { ticking = true; requestAnimationFrame(measure); } };
    window.addEventListener('scroll', onScroll, { passive: true });
    window.addEventListener('resize', onScroll);
    measure();
  }

  /* ---------- contents ---------- */
  var toggle = document.getElementById('gd-toc-toggle');
  var links = [].slice.call(document.querySelectorAll('[data-toc]'));
  if (toggle) {
    toggle.addEventListener('click', function () {
      toggle.setAttribute('aria-expanded', String(toggle.getAttribute('aria-expanded') !== 'true'));
    });
  }
  if (links.length) {
    var wide = window.matchMedia('(min-width: 1100px)');
    var byId = {}, headings = [];
    links.forEach(function (a) {
      var h = document.getElementById(a.getAttribute('data-toc'));
      if (h) { byId[h.id] = a; headings.push(h); }
    });
    // the heading in view, and its section when it is a sub-heading
    var mark = function (id) {
      var parent = id && byId[id] ? byId[id].getAttribute('data-toc-parent') : null;
      links.forEach(function (a) {
        var key = a.getAttribute('data-toc');
        if (key === id || key === parent) a.setAttribute('aria-current', 'true');
        else a.removeAttribute('aria-current');
      });
    };
    // the section in view: the last heading above the upper third of the screen
    var current = function () {
      var line = window.innerHeight * 0.3, id = null;
      headings.forEach(function (h) { if (h.getBoundingClientRect().top <= line) id = h.id; });
      mark(id);
    };
    var queued = false;
    window.addEventListener('scroll', function () {
      if (queued) return;
      queued = true;
      requestAnimationFrame(function () { queued = false; current(); });
    }, { passive: true });
    current();
    // a jump keeps the address (#section) and, on phones, folds the contents away
    links.forEach(function (a) {
      a.addEventListener('click', function (e) {
        var h = document.getElementById(a.getAttribute('data-toc'));
        if (!h) return;
        e.preventDefault();
        if (toggle && !wide.matches) toggle.setAttribute('aria-expanded', 'false');
        h.scrollIntoView({ behavior: reduce ? 'auto' : 'smooth', block: 'start' });
        try { window.history.replaceState(null, '', '#' + h.id); } catch (err) {}
        h.setAttribute('tabindex', '-1');
        h.focus({ preventScroll: true });
      });
    });
  }

  var status = document.getElementById('gd-copy-status');

  function copyText(text) {
    if (navigator.clipboard && window.isSecureContext) {
      return navigator.clipboard.writeText(text).then(function () { return true; }, function () { return legacyCopy(text); });
    }
    return Promise.resolve(legacyCopy(text));
  }
  function legacyCopy(text) {
    var area = document.createElement('textarea');
    area.value = text;
    area.setAttribute('readonly', '');
    area.style.position = 'fixed';
    area.style.opacity = '0';
    document.body.appendChild(area);
    area.select();
    var ok = false;
    try { ok = document.execCommand('copy'); } catch (e) {}
    area.remove();
    return ok;
  }

  var copy = document.querySelector('[data-copy]');
  if (copy) {
    var label = copy.textContent, timer = null;
    copy.addEventListener('click', function () {
      copyText(copy.getAttribute('data-copy')).then(function (ok) {
        copy.textContent = ok ? 'Copiat ✓' : 'Nu s-a putut copia';
        if (status) status.textContent = ok ? 'Linkul ghidului a fost copiat.' : 'Linkul nu a putut fi copiat.';
        clearTimeout(timer);
        timer = setTimeout(function () { copy.textContent = label; }, 2500);
      });
    });
  }

  var native = document.querySelector('[data-native-share]');
  if (native && navigator.share) {
    native.hidden = false;
    native.addEventListener('click', function () {
      navigator.share({ title: native.getAttribute('data-title'), url: native.getAttribute('data-url') }).catch(function () {});
    });
  }
})();
