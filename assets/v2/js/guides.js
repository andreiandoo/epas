/* bilete.online v2: guides index. Search (diacritics don't matter) and the topic filter over the server-rendered guide
   cards, the hero topic chips, and the state kept in the URL (?q=, ?topic=). */
(function () {
  'use strict';
  var $ = function (id) { return document.getElementById(id); };
  var input = $('ct-q'), grid = $('gd-grid');
  if (!input || !grid) return;

  var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var cards = [].slice.call(grid.querySelectorAll('.ct-card'));
  var buttons = [].slice.call(document.querySelectorAll('.ct-regions [data-topic]'));
  var chips = [].slice.call(document.querySelectorAll('[data-topic-chip]'));
  var title = $('ct-title'), count = $('ct-count'), none = $('ct-none'), status = $('ct-status');
  var total = cards.length, topic = 'all';

  function norm(s) {
    return String(s || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/\s+/g, ' ').trim();
  }
  /* Romanian counting, as v2_num(): 1 ghid, 5 ghiduri, 20 de ghiduri */
  function num(n, one, many) {
    if (n === 1) return '1 ' + one;
    var rem = n % 100;
    return n + ' ' + (n >= 20 && !(rem >= 1 && rem <= 19) ? 'de ' : '') + many;
  }
  var index = cards.map(function (card) {
    return { q: norm(card.getAttribute('data-q')), topic: card.getAttribute('data-topic-key') };
  });
  var known = function (key) { return buttons.some(function (b) { return b.getAttribute('data-topic') === key; }); };

  function apply(fromUser) {
    var q = norm(input.value), shown = 0;
    cards.forEach(function (card, i) {
      var visible = (topic === 'all' || index[i].topic === topic) && (!q || index[i].q.indexOf(q) !== -1);
      card.hidden = !visible;
      if (visible) shown++;
    });
    var active = buttons.filter(function (b) { return b.getAttribute('data-topic') === topic; })[0] || buttons[0];
    buttons.forEach(function (b) { b.setAttribute('aria-pressed', String(b === active)); });
    chips.forEach(function (c) { c.setAttribute('aria-pressed', String(c.getAttribute('data-topic-chip') === topic)); });
    title.textContent = active.getAttribute('data-label');
    count.textContent = shown + ' din ' + total + ' ghiduri';
    none.hidden = shown > 0;
    status.textContent = q ? (shown ? num(shown, 'ghid găsit', 'ghiduri găsite') : 'Niciun ghid găsit.') : '';
    if (fromUser) {
      var params = new URLSearchParams(window.location.search);
      if (input.value.trim()) params.set('q', input.value.trim()); else params.delete('q');
      if (topic !== 'all') params.set('topic', topic); else params.delete('topic');
      var search = params.toString();
      window.history.replaceState(null, '', window.location.pathname + (search ? '?' + search : '') + window.location.hash);
    }
  }
  function toList() {
    $('lista').scrollIntoView({ behavior: reduce ? 'auto' : 'smooth', block: 'start' });
    title.focus({ preventScroll: true });
  }

  input.addEventListener('input', function () { apply(true); });
  $('ct-form').addEventListener('submit', function (e) {
    e.preventDefault();
    apply(true);
    toList();
  });
  buttons.forEach(function (b) {
    b.addEventListener('click', function () {
      topic = b.getAttribute('data-topic');
      apply(true);
    });
  });
  chips.forEach(function (c) {
    c.addEventListener('click', function () {
      var key = c.getAttribute('data-topic-chip');
      topic = topic === key ? 'all' : key; // a second press clears the topic
      apply(true);
      if (topic !== 'all') toList();
    });
  });
  $('ct-reset').addEventListener('click', function () {
    input.value = '';
    topic = 'all';
    apply(true);
    buttons[0].focus();
  });

  var params = new URLSearchParams(window.location.search);
  if (params.get('topic') && known(params.get('topic'))) topic = params.get('topic');
  if (params.get('q') && !input.value) input.value = params.get('q');
  apply(false);
})();
