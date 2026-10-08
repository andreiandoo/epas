/* viaqui.com v2: region page. The hero search filters the region's cities in the by-county index (diacritics
   don't matter), hides the counties it empties, and when nothing matches offers the same search in every region. */
(function () {
  'use strict';
  var $ = function (id) { return document.getElementById(id); };
  var input = $('rg-q'), list = $('rg-counties');
  if (!input || !list) return;

  var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var counties = [].slice.call(list.querySelectorAll('.rg-county'));
  var items = [].slice.call(list.querySelectorAll('li[data-q]'));
  var count = $('rg-count'), none = $('rg-none'), status = $('rg-status'), elsewhere = $('rg-elsewhere');
  var total = items.length;

  function norm(s) {
    return String(s || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/\s+/g, ' ').trim();
  }
  var index = items.map(function (li) { return norm(li.getAttribute('data-q')); });

  function apply() {
    var q = norm(input.value), shown = 0;
    items.forEach(function (li, i) {
      var hit = !q || index[i].indexOf(q) !== -1;
      li.hidden = !hit;
      if (hit) shown++;
    });
    counties.forEach(function (county) {
      county.hidden = !county.querySelector('li[data-q]:not([hidden])');
    });
    count.textContent = VQ.t('{shown} of {total} cities', { shown: shown, total: total });
    none.hidden = shown > 0;
    elsewhere.href = VQ.url('/cities') + (q ? '?q=' + encodeURIComponent(input.value.trim()) : '');
    status.textContent = q ? (shown ? VQ.t('{found} in the list below', { found: VQ.n(shown, 'city found', 'cities found') }) : VQ.t('No city with this name in the region.')) : '';
  }

  input.addEventListener('input', apply);
  $('rg-form').addEventListener('submit', function (e) {
    e.preventDefault();
    apply();
    var target = $('toate-orasele');
    target.scrollIntoView({ behavior: reduce ? 'auto' : 'smooth', block: 'start' });
    target.focus({ preventScroll: true });
  });
  $('rg-reset').addEventListener('click', function () {
    input.value = '';
    apply();
    input.focus();
  });

  apply(); // a value the browser kept after a reload
})();
