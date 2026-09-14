/* bilete.online v2: cities catalog. Search (diacritics don't matter) and the region filter over the server-rendered
   city cards, live suggestions under the hero search, and the state kept in the URL (?q=, ?regiune=). */
(function () {
  'use strict';
  var $ = function (id) { return document.getElementById(id); };
  var input = $('ct-q'), grid = $('ct-grid');
  if (!input || !grid) return;

  var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var cards = [].slice.call(grid.querySelectorAll('.ct-card'));
  var buttons = [].slice.call(document.querySelectorAll('.ct-regions [data-region]'));
  var title = $('ct-title'), count = $('ct-count'), none = $('ct-none'), status = $('ct-status');
  var quick = $('ct-quick'), suggest = $('ct-suggest');
  var more = $('ct-more');
  var total = cards.length, region = 'all', expanded = false;

  function norm(s) {
    return String(s || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/\s+/g, ' ').trim();
  }
  /* Romanian counting, as v2_num(): 1 oraș, 5 orașe, 20 de orașe */
  function num(n, one, many) {
    if (n === 1) return '1 ' + one;
    var rem = n % 100;
    return n + ' ' + (n >= 20 && !(rem >= 1 && rem <= 19) ? 'de ' : '') + many;
  }

  var index = cards.map(function (card) {
    var link = card.querySelector('.ct-top');
    return {
      q: norm(card.getAttribute('data-q')),
      region: card.getAttribute('data-region-key'),
      name: card.querySelector('h3').textContent,
      // the search also matches the county, so a suggestion says where it is ("Bran · jud. Brașov")
      where: card.getAttribute('data-county') ? 'jud. ' + card.getAttribute('data-county') : card.querySelector('.ct-over small').textContent,
      href: link.getAttribute('href'),
    };
  });

  function sync() {
    var params = new URLSearchParams(window.location.search);
    var q = input.value.trim();
    if (q) params.set('q', q); else params.delete('q');
    if (region !== 'all') params.set('regiune', region); else params.delete('regiune');
    var search = params.toString();
    window.history.replaceState(null, '', window.location.pathname + (search ? '?' + search : '') + window.location.hash);
  }

  function apply(fromUser) {
    var q = norm(input.value), shown = 0, found = [];
    cards.forEach(function (card, i) {
      var hit = !q || index[i].q.indexOf(q) !== -1;
      if (hit && q) found.push(index[i]);
      var visible = hit && (region === 'all' || index[i].region === region);
      card.hidden = !visible;
      if (visible) shown++;
    });

    var active = buttons.filter(function (b) { return b.getAttribute('data-region') === region; })[0] || buttons[0];
    buttons.forEach(function (b) { b.setAttribute('aria-pressed', String(b === active)); });
    title.textContent = active.getAttribute('data-label');
    count.textContent = shown + ' din ' + total + ' orașe';
    none.hidden = shown > 0;
    // a search or a region always shows every match; otherwise the first 12 until "show all"
    var filtering = !!q || region !== 'all';
    grid.classList.toggle('is-all', expanded || filtering);
    if (more) more.hidden = expanded || filtering;

    // hero: what the search finds (in any region) while typing, the popular cities otherwise
    quick.hidden = !!q;
    suggest.hidden = !q || !found.length;
    suggest.textContent = '';
    found.slice(0, 6).forEach(function (city) {
      var li = document.createElement('li'), a = document.createElement('a'), small = document.createElement('small');
      a.href = city.href;
      a.textContent = city.name;
      small.textContent = city.where;
      a.appendChild(small);
      li.appendChild(a);
      suggest.appendChild(li);
    });
    status.textContent = q ? (found.length ? num(found.length, 'oraș găsit', 'orașe găsite') : 'Niciun oraș găsit.') : '';

    if (fromUser) sync();
  }

  input.addEventListener('input', function () { apply(true); });
  $('ct-form').addEventListener('submit', function (e) {
    e.preventDefault();
    apply(true);
    $('lista').scrollIntoView({ behavior: reduce ? 'auto' : 'smooth', block: 'start' });
    title.focus({ preventScroll: true });
  });
  buttons.forEach(function (b) {
    b.addEventListener('click', function () {
      region = b.getAttribute('data-region');
      apply(true);
    });
  });
  if (more) {
    more.addEventListener('click', function () {
      expanded = true;
      apply(false);
      var next = grid.querySelector('.ct-card.is-extra .ct-top');
      if (next) next.focus({ preventScroll: true }); // keyboard users continue at the first city that appeared
    });
  }
  $('ct-reset').addEventListener('click', function () {
    input.value = '';
    region = 'all';
    apply(true);
    buttons[0].focus();
  });

  // state from the URL (a shared or reloaded link)
  var params = new URLSearchParams(window.location.search);
  var wanted = params.get('regiune');
  if (wanted && buttons.some(function (b) { return b.getAttribute('data-region') === wanted; })) region = wanted;
  if (params.get('q') && !input.value) input.value = params.get('q');
  apply(false);
})();
