/* bilete.online v2: help center filter. Every question is already in the page; this narrows the list by category and by
   search (diacritics ignored, every word must match), keeps the counts and the empty state right, and mirrors the state
   in the URL (?q=&categorie=) so a filtered view can be shared. Hero search and chips bring the list into view. */
(function () {
  'use strict';
  var $ = function (id) { return document.getElementById(id); };
  var list = $('hp-list');
  if (!list) return;

  var LABELS = {};
  try { LABELS = JSON.parse($('v2-data').textContent).categories || {}; } catch (e) {}
  var items = [].slice.call(list.querySelectorAll('.hp-item'));
  var search = $('hp-search'), clear = $('hp-clear'), foundT = $('hp-found-t'), jump = $('hp-jump');
  var title = $('hp-cat-title'), count = $('hp-count'), empty = $('hp-empty');
  var catButtons = [].slice.call(document.querySelectorAll('[data-hp-cat]'));
  var chips = [].slice.call(document.querySelectorAll('[data-hp-chip]'));
  var TOTAL = items.length, active = 'all', timer = null;

  function norm(s) {
    return String(s || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '')
      .replace(/[şș]/g, 's').replace(/[ţț]/g, 't').replace(/\s+/g, ' ').trim();
  }
  var blobs = items.map(function (el) { return norm(el.textContent); });

  function plural(n) { return n === 1 ? '1 întrebare' : n + (n !== 0 && (n % 100 === 0 || n % 100 >= 20) ? ' de întrebări' : ' întrebări'); }

  function writeUrl() {
    try {
      var url = new URL(window.location.href);
      var q = search.value.trim();
      if (q) url.searchParams.set('q', q); else url.searchParams.delete('q');
      if (active !== 'all') url.searchParams.set('categorie', active); else url.searchParams.delete('categorie');
      history.replaceState(null, '', url.pathname + url.search + url.hash);
    } catch (e) {}
  }

  function apply() {
    var words = norm(search.value).split(' ').filter(Boolean), shown = 0;
    items.forEach(function (el, i) {
      var ok = (active === 'all' || el.getAttribute('data-cat') === active) && words.every(function (w) { return blobs[i].indexOf(w) !== -1; });
      el.hidden = !ok;
      if (ok) shown++;
    });
    catButtons.forEach(function (b) { b.setAttribute('aria-pressed', String(b.getAttribute('data-hp-cat') === active)); });
    chips.forEach(function (b) { b.setAttribute('aria-pressed', String(b.getAttribute('data-hp-chip') === active)); });
    title.textContent = LABELS[active] || 'Toate';
    count.textContent = shown + ' din ' + TOTAL + ' întrebări';
    empty.hidden = shown > 0;
    clear.hidden = !search.value;
    if (words.length) {
      foundT.textContent = shown ? plural(shown) + (shown === 1 ? ' găsită' : ' găsite') : 'Nicio întrebare găsită';
      jump.hidden = false;
    } else {
      foundT.textContent = '';
      jump.hidden = true;
    }
    writeUrl();
  }

  function showList() {
    var top = $('intrebari').getBoundingClientRect().top;
    if (top > window.innerHeight * 0.6 || top < 0) $('intrebari').scrollIntoView({ behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth', block: 'start' });
  }

  search.addEventListener('input', function () {
    clearTimeout(timer);
    timer = setTimeout(apply, 120);
  });
  $('hp-search-form').addEventListener('submit', function (e) {
    e.preventDefault();
    clearTimeout(timer);
    apply();
    showList();
  });
  clear.addEventListener('click', function () {
    search.value = '';
    apply();
    search.focus();
  });
  jump.addEventListener('click', function (e) {
    e.preventDefault();
    showList();
  });
  catButtons.forEach(function (b) {
    b.addEventListener('click', function () {
      active = b.getAttribute('data-hp-cat');
      apply();
    });
  });
  chips.forEach(function (b) {
    b.addEventListener('click', function () {
      active = b.getAttribute('data-hp-chip') === active ? 'all' : b.getAttribute('data-hp-chip');
      apply();
      showList();
    });
  });
  $('hp-reset').addEventListener('click', function () {
    search.value = '';
    active = 'all';
    apply();
    title.focus();
  });

  // start from the URL: /ajutor?q=retur&categorie=refunds
  try {
    var params = new URLSearchParams(window.location.search);
    if (params.get('q')) search.value = params.get('q').slice(0, 80);
    if (LABELS[params.get('categorie')]) active = params.get('categorie');
  } catch (e) {}
  if (search.value || active !== 'all') apply();
})();
