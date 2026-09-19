/* bilete.online v2: operator help centre (/organizator/help). Every question is already in the page; the search narrows
   them while typing (diacritics ignored, every word must match), opens the ones it finds, hides the groups left empty,
   says how many it found and shows a message when nothing matches. Escape or the × clears it. The topic cards jump to
   their group and move the focus there. Markup in organizer/help.php. */
(function () {
  'use strict';
  var $ = function (id) { return document.getElementById(id); };
  var root = $('ohp');
  if (!root) return;
  var q = $('ohp-q'), clear = $('ohp-clear'), found = $('ohp-found'), empty = $('ohp-empty');
  var items = [].slice.call(root.querySelectorAll('.ohp-q'));
  var secs = [].slice.call(root.querySelectorAll('.ohp-sec'));
  var timer = 0;

  function norm(s) {
    return String(s || '').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '')
      .replace(/[şș]/g, 's').replace(/[ţț]/g, 't').replace(/\s+/g, ' ').trim();
  }
  var blobs = items.map(function (d) { return norm(d.textContent); });
  function plural(n) { return n === 1 ? '1 întrebare găsită' : n + (n % 100 === 0 || n % 100 >= 20 ? ' de întrebări găsite' : ' întrebări găsite'); }

  function apply() {
    var words = norm(q.value).split(' ').filter(Boolean), shown = 0;
    items.forEach(function (d, i) {
      var ok = words.every(function (w) { return blobs[i].indexOf(w) !== -1; });
      d.hidden = !ok;
      if (ok) shown++;
      if (words.length && ok && !d.open) { d.open = true; d.setAttribute('data-auto', ''); }
      else if (!words.length && d.hasAttribute('data-auto')) { d.open = false; d.removeAttribute('data-auto'); }
    });
    secs.forEach(function (s) { s.hidden = !s.querySelector('.ohp-q:not([hidden])'); });
    empty.hidden = shown > 0;
    clear.hidden = !q.value;
    found.textContent = words.length ? (shown ? plural(shown) + '.' : 'Nicio întrebare găsită.') : '';
  }

  q.addEventListener('input', function () { clearTimeout(timer); timer = setTimeout(apply, 120); });
  q.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && q.value) { e.preventDefault(); e.stopPropagation(); q.value = ''; apply(); }
  });
  $('ohp-search').addEventListener('submit', function (e) {
    e.preventDefault();
    clearTimeout(timer);
    apply();
    var first = root.querySelector('.ohp-q:not([hidden]) summary');
    if (first && q.value.trim()) first.scrollIntoView({ block: 'center', behavior: reduced() ? 'auto' : 'smooth' });
  });
  clear.addEventListener('click', function () { q.value = ''; apply(); q.focus(); });
  $('ohp-reset').addEventListener('click', function () { q.value = ''; apply(); q.focus(); });

  function reduced() { return window.matchMedia('(prefers-reduced-motion: reduce)').matches; }
  /* topic cards: jump to the group and put the focus on it, so Tab continues from there */
  [].forEach.call(root.querySelectorAll('.ohp-topic'), function (a) {
    a.addEventListener('click', function (e) {
      var t = document.getElementById((a.getAttribute('href') || '').slice(1));
      if (!t) return;
      e.preventDefault();
      if (t.hidden) { q.value = ''; apply(); }
      t.scrollIntoView({ block: 'start', behavior: reduced() ? 'auto' : 'smooth' });
      t.focus({ preventScroll: true });
      try { history.replaceState(null, '', '#' + t.id); } catch (err) {}
    });
  });
})();
