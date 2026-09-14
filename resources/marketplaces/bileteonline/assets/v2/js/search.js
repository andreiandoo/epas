/* bilete.online v2: /cauta. Filters fold away on phones; picking a date under "Alte date" applies it right away. */
(function () {
  'use strict';
  var toggle = document.querySelector('.sr-filter-toggle'), body = document.getElementById('sr-filter-body');
  if (toggle && body) {
    toggle.addEventListener('click', function () {
      var open = toggle.getAttribute('aria-expanded') !== 'true';
      toggle.setAttribute('aria-expanded', String(open));
      body.classList.toggle('is-open', open);
    });
  }
  var date = document.getElementById('sr-date');
  if (date && date.form) {
    date.addEventListener('change', function () { if (date.value) date.form.submit(); });
  }
})();
