/* bilete.online v2: region page. The hero search filters the region's cities in the by-county index (diacritics
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
  /* Romanian counting, as v2_num(): 1 oraș, 5 orașe, 20 de orașe */
  function num(n, one, many) {
    if (n === 1) return '1 ' + one;
    var rem = n % 100;
    return n + ' ' + (n >= 20 && !(rem >= 1 && rem <= 19) ? 'de ' : '') + many;
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
    count.textContent = shown + ' din ' + total + ' orașe';
    none.hidden = shown > 0;
    elsewhere.href = '/orase' + (q ? '?q=' + encodeURIComponent(input.value.trim()) : '');
    status.textContent = q ? (shown ? num(shown, 'oraș găsit', 'orașe găsite') + ' în lista de mai jos' : 'Niciun oraș cu acest nume în regiune.') : '';
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
