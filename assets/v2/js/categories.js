/* bilete.online v2: categories catalog. The hero search filters the server-rendered category cards (diacritics don't
   matter; names, descriptions and subcategories all count) and keeps ?q= in the URL. */
(function () {
  'use strict';
  var $ = function (id) { return document.getElementById(id); };
  var input = $('ct-q'), grid = $('cg-grid');
  if (!input || !grid) return;

  var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var cards = [].slice.call(grid.querySelectorAll('.cg-card'));
  var count = $('ct-count'), none = $('ct-none'), status = $('ct-status'), title = $('ct-title');
  var total = cards.length;

  function norm(s) {
    return String(s || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/\s+/g, ' ').trim();
  }
  /* Romanian counting, as v2_num(): 1 categorie, 5 categorii, 20 de categorii */
  function num(n, one, many) {
    if (n === 1) return '1 ' + one;
    var rem = n % 100;
    return n + ' ' + (n >= 20 && !(rem >= 1 && rem <= 19) ? 'de ' : '') + many;
  }
  var index = cards.map(function (card) { return norm(card.getAttribute('data-q')); });

  function apply(fromUser) {
    var q = norm(input.value), shown = 0;
    cards.forEach(function (card, i) {
      var hit = !q || index[i].indexOf(q) !== -1;
      card.hidden = !hit;
      if (hit) shown++;
    });
    count.textContent = shown + ' din ' + total + ' categorii afișate';
    none.hidden = shown > 0;
    status.textContent = q ? (shown ? num(shown, 'categorie găsită', 'categorii găsite') : 'Nicio categorie găsită.') : '';
    if (fromUser) {
      var params = new URLSearchParams(window.location.search);
      if (input.value.trim()) params.set('q', input.value.trim()); else params.delete('q');
      var search = params.toString();
      window.history.replaceState(null, '', window.location.pathname + (search ? '?' + search : '') + window.location.hash);
    }
  }

  input.addEventListener('input', function () { apply(true); });
  $('ct-form').addEventListener('submit', function (e) {
    e.preventDefault();
    apply(true);
    $('lista').scrollIntoView({ behavior: reduce ? 'auto' : 'smooth', block: 'start' });
    title.focus({ preventScroll: true });
  });
  $('ct-reset').addEventListener('click', function () {
    input.value = '';
    apply(true);
    title.focus();
  });

  var wanted = new URLSearchParams(window.location.search).get('q');
  if (wanted && !input.value) input.value = wanted;
  apply(false);
})();
