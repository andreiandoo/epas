/* bilete.online v2: cookie policy. Shows what the visitor allows right now, on each category card and in one line under
   the hero buttons, and follows the settings dialog as it saves (base.js owns the banner, the dialog and the stored
   choice). A visible banner means there is no valid choice yet, so the version check stays in one place, base.js. */
(function () {
  'use strict';
  var status = document.getElementById('cp-status');
  if (!status) return;

  var KEY = 'bo_cookie_consent_v1';
  var MONTHS = ['ian.', 'feb.', 'mar.', 'apr.', 'mai', 'iun.', 'iul.', 'aug.', 'sept.', 'oct.', 'nov.', 'dec.'];
  var NAMES = { analytics: 'analytics', personalization: 'personalizare', marketing: 'marketing' };

  function saved() {
    var banner = document.getElementById('cc-banner');
    if (banner && !banner.hidden) return null;
    try {
      var s = JSON.parse(localStorage.getItem(KEY));
      return s && s.consent ? s : null;
    } catch (e) { return null; }
  }

  function render(s) {
    Object.keys(NAMES).forEach(function (k) {
      var pill = document.querySelector('[data-cp-state="' + k + '"]');
      if (!pill) return;
      var on = !!(s && s.consent && s.consent[k]);
      pill.textContent = on ? 'Activ' : 'Oprit';
      pill.setAttribute('data-on', String(on));
    });
    if (!s || !s.consent) {
      status.textContent = 'Nu ai ales încă ce cookies permiți. Până atunci rulează doar cele esențiale.';
      return;
    }
    var allowed = Object.keys(NAMES).filter(function (k) { return s.consent[k]; }).map(function (k) { return NAMES[k]; });
    var d = s.savedAt ? new Date(s.savedAt) : null;
    var when = d && !isNaN(d) ? ' din ' + d.getDate() + ' ' + MONTHS[d.getMonth()] + ' ' + d.getFullYear() : '';
    var what;
    if (allowed.length === 3) what = 'toate categoriile sunt permise.';
    else if (!allowed.length) what = 'doar cookies esențiale.';
    else what = 'esențiale, plus ' + allowed.join(' și ') + '.';
    status.textContent = 'Alegerea ta' + when + ': ' + what;
  }

  window.addEventListener('bo-cookie-consent-updated', function (e) { render(e.detail); });
  // base.js decides on load whether the banner shows; read after it has run
  if (document.readyState === 'complete') render(saved());
  else window.addEventListener('load', function () { render(saved()); });
})();
