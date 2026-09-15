/* bilete.online v2: operators catalog (/operatori). Search (diacritics don't matter), city and "verified" filters over the
   server-rendered operator cards (the hero's quick buttons drive the same "verified" filter), the first 12 cards then
   "show all", and the state kept in the URL (?q=, ?oras=, ?verificati=1). */
(function () {
  'use strict';
  var $ = function (id) { return document.getElementById(id); };
  var grid = $('os-grid'), input = $('os-q');
  if (!grid || !input) return;

  var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var cards = [].slice.call(grid.querySelectorAll('.os-card'));
  var citySel = $('os-city'), verified = $('os-verified'), quick = [].slice.call(document.querySelectorAll('.os-quick [data-quick]'));
  var title = $('os-title'), count = $('os-count'), none = $('os-none'), status = $('os-status'), more = $('os-more');
  var total = cards.length, expanded = false;

  function norm(s) {
    return String(s || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/\s+/g, ' ').trim();
  }
  /* Romanian counting, as v2_num(): 1 operator, 5 operatori, 20 de operatori */
  function num(n, one, many) {
    if (n === 1) return '1 ' + one;
    var rem = n % 100;
    return n + ' ' + (n >= 20 && !(rem >= 1 && rem <= 19) ? 'de ' : '') + many;
  }

  var index = cards.map(function (card) {
    return {
      q: norm(card.getAttribute('data-q')),
      cities: (card.getAttribute('data-cities') || '').split(' ').filter(Boolean),
      verified: card.getAttribute('data-verified') === '1'
    };
  });

  function sync() {
    var params = new URLSearchParams(window.location.search), q = input.value.trim();
    if (q) params.set('q', q); else params.delete('q');
    if (citySel.value !== 'all') params.set('oras', citySel.value); else params.delete('oras');
    if (verified.checked) params.set('verificati', '1'); else params.delete('verificati');
    var search = params.toString();
    window.history.replaceState(null, '', window.location.pathname + (search ? '?' + search : '') + window.location.hash);
  }

  function apply(fromUser) {
    var q = norm(input.value), city = citySel.value, onlyVerified = verified.checked, shown = 0, found = 0;
    cards.forEach(function (card, i) {
      var it = index[i], hit = !q || it.q.indexOf(q) !== -1;
      var visible = hit && (city === 'all' || it.cities.indexOf(city) !== -1) && (!onlyVerified || it.verified);
      card.hidden = !visible;
      if (visible) shown++;
      if (hit && q) found++;
    });
    quick.forEach(function (b) { b.setAttribute('aria-pressed', String((b.getAttribute('data-quick') === 'verified') === onlyVerified)); });
    title.textContent = onlyVerified ? 'Operatori verificați' : (city !== 'all' ? 'Operatori în ' + citySel.options[citySel.selectedIndex].text : 'Operatori parteneri');
    count.textContent = shown + ' din ' + total + ' operatori';
    none.hidden = shown > 0;
    // a search or a filter always shows every match; otherwise the first 12 until "show all"
    var filtering = !!q || city !== 'all' || onlyVerified;
    grid.classList.toggle('is-all', expanded || filtering);
    if (more) more.hidden = expanded || filtering;
    status.textContent = q ? (found ? num(found, 'operator găsit', 'operatori găsiți') : 'Niciun operator găsit.') : '';
    if (fromUser) sync();
  }

  input.addEventListener('input', function () { apply(true); });
  $('os-form').addEventListener('submit', function (e) {
    e.preventDefault();
    apply(true);
    $('lista').scrollIntoView({ behavior: reduce ? 'auto' : 'smooth', block: 'start' });
    title.focus({ preventScroll: true });
  });
  citySel.addEventListener('change', function () { apply(true); });
  verified.addEventListener('change', function () { apply(true); });
  quick.forEach(function (b) {
    b.addEventListener('click', function () {
      var onlyVerified = b.getAttribute('data-quick') === 'verified';
      verified.checked = onlyVerified;
      if (!onlyVerified) citySel.value = 'all'; // "Toți operatorii" clears the filters, as before
      apply(true);
    });
  });
  if (more) {
    more.addEventListener('click', function () {
      expanded = true;
      apply(false);
      var next = grid.querySelector('.os-card.is-extra .ct-top');
      if (next) next.focus({ preventScroll: true }); // keyboard users continue at the first operator that appeared
    });
  }
  $('os-reset').addEventListener('click', function () {
    input.value = '';
    citySel.value = 'all';
    verified.checked = false;
    apply(true);
    input.focus();
  });

  // state from the URL (a shared or reloaded link)
  var params = new URLSearchParams(window.location.search);
  var wantedCity = params.get('oras');
  if (wantedCity && [].some.call(citySel.options, function (o) { return o.value === wantedCity; })) citySel.value = wantedCity;
  if (params.get('verificati') === '1') verified.checked = true;
  if (params.get('q') && !input.value) input.value = params.get('q');
  apply(false);
})();
