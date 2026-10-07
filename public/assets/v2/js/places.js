/* Viaqui destination pages: the region list and the region map highlight each other, and a bubble on the map says what
   the region holds (its attractions and cities). The page works without this. */
(function () {
  'use strict';
  [].forEach.call(document.querySelectorAll('[data-vrmap]'), function (box) {
    var fig = box.querySelector('.v-rfig'), bub = box.querySelector('[data-vrbub]');
    function bubble(shape) {
      if (!bub || !fig) return;
      if (!shape) { bub.hidden = true; return; }
      bub.firstElementChild.textContent = shape.getAttribute('data-name') || '';
      bub.lastElementChild.textContent = shape.getAttribute('data-says') || '';
      bub.hidden = false;
      // above the middle of the shape, kept inside the figure
      var r = shape.getBoundingClientRect(), f = fig.getBoundingClientRect(), half = bub.offsetWidth / 2;
      var x = Math.max(half, Math.min(f.width - half, r.left + r.width / 2 - f.left));
      var y = r.top + r.height / 2 - f.top;
      bub.style.left = x + 'px';
      bub.style.top = Math.max(bub.offsetHeight + 8, y) + 'px';
    }
    function light(slug, on) {
      [].forEach.call(box.querySelectorAll('[data-r]'), function (el) {
        if (el.getAttribute('data-r') === slug) el.classList.toggle('is-hot', on);
      });
      bubble(on ? box.querySelector('svg [data-r="' + slug + '"]') : null);
    }
    ['mouseover', 'focusin'].forEach(function (type) {
      box.addEventListener(type, function (e) { var el = e.target.closest('[data-r]'); if (el) light(el.getAttribute('data-r'), true); });
    });
    ['mouseout', 'focusout'].forEach(function (type) {
      box.addEventListener(type, function (e) { var el = e.target.closest('[data-r]'); if (el) light(el.getAttribute('data-r'), false); });
    });
  });
})();
