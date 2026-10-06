/* viaqui.com v2: the stop list of a route or a road page (/trasee/{slug}).
 *
 * A stop is one line when closed and opens on a tap into what the place says about itself and the
 * way to its page — the same card the planner uses. The description is asked for when the card
 * first opens (/api/place.php); a place with nothing of its own to say shows only the link.
 */
(function () {
  'use strict';

  var list = document.querySelector('.rp-stops');
  if (!list) return;
  var known = {};

  function fill(li, text) {
    var p = li.querySelector('.rp-about');
    if (!p) return;
    p.textContent = text || '';
    p.hidden = !text;
  }
  function about(li) {
    var id = li.getAttribute('data-place');
    if (!id) return;
    if (known[id] !== undefined) { fill(li, known[id]); return; }
    known[id] = '';
    fetch('/api/place.php?id=' + encodeURIComponent(id), { credentials: 'omit' })
      .then(function (r) { return r.json(); })
      .then(function (j) { known[id] = (j && j.ok && j.text) ? String(j.text) : ''; fill(li, known[id]); })
      .catch(function () {});
  }

  list.addEventListener('click', function (ev) {
    var main = ev.target.closest('.rp-main');
    if (!main) return;
    var li = main.closest('.rp-stop');
    var open = !li.classList.contains('is-open');
    [].forEach.call(list.querySelectorAll('.rp-stop.is-open'), function (x) {
      x.classList.remove('is-open');
      x.querySelector('.rp-main').setAttribute('aria-expanded', 'false');
    });
    if (!open) return;
    li.classList.add('is-open');
    main.setAttribute('aria-expanded', 'true');
    about(li);
  });
})();
