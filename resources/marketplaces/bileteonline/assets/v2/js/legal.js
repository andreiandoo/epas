/* bilete.online v2: legal pages (/termeni, /confidentialitate). The table of contents is open beside the text on wide
   screens and folded above it on phones, where picking an entry folds it again; the entry of the section being read is
   marked (aria-current). Without this script the contents stay folded and still open with a tap. */
(function () {
  'use strict';
  var toc = document.getElementById('lg-toc');
  if (!toc) return;
  var wide = window.matchMedia('(min-width: 1024px)');
  function fit() { toc.open = wide.matches; }
  fit();
  if (wide.addEventListener) wide.addEventListener('change', fit);
  else if (wide.addListener) wide.addListener(fit);
  toc.addEventListener('click', function (e) {
    if (wide.matches && e.target.closest('summary')) e.preventDefault(); // stays open beside the text
    else if (!wide.matches && e.target.closest('a')) toc.open = false;
  });

  var links = [].slice.call(toc.querySelectorAll('a[href^="#"]'));
  if (!links.length || !('IntersectionObserver' in window)) return;
  var ids = links.map(function (a) { return a.getAttribute('href').slice(1); });
  var inView = {};
  var io = new IntersectionObserver(function (entries) {
    entries.forEach(function (en) { inView[en.target.id] = en.isIntersecting; });
    var current = null;
    for (var i = 0; i < ids.length; i++) if (inView[ids[i]]) { current = ids[i]; break; }
    if (!current) return;
    links.forEach(function (a, i) {
      if (ids[i] === current) a.setAttribute('aria-current', 'true');
      else a.removeAttribute('aria-current');
    });
  }, { rootMargin: '-20% 0px -65% 0px' });
  ids.forEach(function (id) { var s = document.getElementById(id); if (s) io.observe(s); });
})();
