/* Viaqui destination pages: the region list and the region map highlight each other. The page works without this. */
(function () {
  'use strict';
  [].forEach.call(document.querySelectorAll('[data-vrmap]'), function (box) {
    var tip = box.querySelector('[data-vrtip]'), rest = tip ? tip.textContent : '';
    function light(slug, on) {
      [].forEach.call(box.querySelectorAll('[data-r]'), function (el) {
        if (el.getAttribute('data-r') === slug) el.classList.toggle('is-hot', on);
      });
      if (tip) {
        var shape = on ? box.querySelector('svg [data-r="' + slug + '"]') : null;
        tip.textContent = shape ? shape.getAttribute('data-name') : rest;
      }
    }
    ['mouseover', 'focusin'].forEach(function (type) {
      box.addEventListener(type, function (e) { var el = e.target.closest('[data-r]'); if (el) light(el.getAttribute('data-r'), true); });
    });
    ['mouseout', 'focusout'].forEach(function (type) {
      box.addEventListener(type, function (e) { var el = e.target.closest('[data-r]'); if (el) light(el.getAttribute('data-r'), false); });
    });
  });
})();
