/* bilete.online v2: city × intent landing. On a single page of results: the city filter (global pages whose results
   span several cities) and the sort, both over the server-rendered cards. */
(function () {
  'use strict';
  var grid = document.getElementById('it-grid');
  if (!grid) return;

  var cards = [].slice.call(grid.querySelectorAll('.xp'));
  var sort = document.getElementById('it-sort');
  var count = document.getElementById('it-count');
  var chips = [].slice.call(document.querySelectorAll('.it-cities [data-city]'));
  var city = 'all';
  var total = cards.length;

  /* Romanian counting, as v2_num(): 1 rezultat, 5 rezultate, 20 de rezultate */
  function num(n, one, many) {
    if (n === 1) return '1 ' + one;
    var rem = n % 100;
    return n + ' ' + (n >= 20 && !(rem >= 1 && rem <= 19) ? 'de ' : '') + many;
  }
  function value(card, key) {
    var raw = card.getAttribute('data-' + key);
    return raw === '' || raw === null ? null : Number(raw);
  }
  // unknown prices and durations go last whatever the order
  function by(key) {
    return function (a, b) {
      var x = value(a, key), y = value(b, key);
      if (key === 'dur') { x = x || null; y = y || null; }
      if (x === null && y === null) return value(a, 'order') - value(b, 'order');
      if (x === null) return 1;
      if (y === null) return -1;
      return x - y || value(a, 'order') - value(b, 'order');
    };
  }

  function apply() {
    var shown = 0;
    cards.forEach(function (card) {
      var hit = city === 'all' || card.getAttribute('data-city') === city;
      card.hidden = !hit;
      if (hit) shown++;
    });
    chips.forEach(function (chip) { chip.setAttribute('aria-pressed', String(chip.getAttribute('data-city') === city)); });
    var key = sort ? sort.value : 'recommended';
    var order = cards.slice().sort(key === 'priceAsc' ? by('price') : key === 'duration' ? by('dur') : by('order'));
    order.forEach(function (card) { grid.appendChild(card); });
    if (count && chips.length) count.textContent = city === 'all' ? num(total, 'rezultat', 'rezultate') : shown + ' din ' + num(total, 'rezultat', 'rezultate');
  }

  chips.forEach(function (chip) {
    chip.addEventListener('click', function () {
      city = chip.getAttribute('data-city');
      apply();
    });
  });
  if (sort) sort.addEventListener('change', apply);
  if (sort && sort.value !== 'recommended') apply(); // a choice the browser kept after a reload
})();
