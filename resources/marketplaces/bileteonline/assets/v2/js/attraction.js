/* bilete.online v2: attraction page. Photo gallery (cover first, then the attraction's gallery). */
(function () {
  'use strict';
  var root = document.documentElement;
  var $ = function (id) { return document.getElementById(id); };
  var data = {};
  try { data = JSON.parse(($('v2-data') || {}).textContent || '{}'); } catch (e) {}
  var lb = $('lb'), gallery = Array.isArray(data.gallery) ? data.gallery : [];
  if (!lb || !gallery.length) return;

  var img = $('lb-img'), title = $('lb-title'), count = $('lb-count'), at = 0, opener = null;
  function show(i) {
    at = (i + gallery.length) % gallery.length;
    img.src = gallery[at].src;
    img.alt = gallery[at].alt || '';
    title.textContent = gallery[at].alt || '';
    count.textContent = (at + 1) + ' / ' + gallery.length;
  }
  function close() {
    lb.hidden = true;
    root.classList.remove('lb-lock');
    if (opener) opener.focus();
  }
  document.querySelectorAll('[data-gallery]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      opener = btn;
      show(parseInt(btn.getAttribute('data-gallery'), 10) || 0);
      lb.hidden = false;
      root.classList.add('lb-lock');
      lb.querySelector('[data-lb="close"]').focus();
    });
  });
  lb.addEventListener('click', function (e) {
    var t = e.target.closest('[data-lb]');
    if (t) {
      var act = t.getAttribute('data-lb');
      if (act === 'close') close();
      else show(at + (act === 'next' ? 1 : -1));
    } else if (e.target === lb || e.target.classList.contains('lb-fig')) {
      close();
    }
  });
  lb.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') { e.preventDefault(); close(); return; }
    if (e.key === 'ArrowRight' && gallery.length > 1) { show(at + 1); return; }
    if (e.key === 'ArrowLeft' && gallery.length > 1) { show(at - 1); return; }
    if (e.key !== 'Tab') return;
    var f = [].slice.call(lb.querySelectorAll('button:not([disabled])')).filter(function (el) { return el.offsetParent !== null; });
    if (!f.length) return;
    if (e.shiftKey && document.activeElement === f[0]) { e.preventDefault(); f[f.length - 1].focus(); }
    else if (!e.shiftKey && document.activeElement === f[f.length - 1]) { e.preventDefault(); f[0].focus(); }
  });
})();
