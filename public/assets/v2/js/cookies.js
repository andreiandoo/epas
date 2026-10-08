/* viaqui.com v2: cookie policy. Shows what the visitor allows right now, on each category card and in one line under
   the hero buttons, and follows the settings dialog as it saves (base.js owns the banner, the dialog and the stored
   choice). A visible banner means there is no valid choice yet, so the version check stays in one place, base.js. */
(function () {
  'use strict';
  var status = document.getElementById('cp-status');
  if (!status) return;

  var KEY = 'bo_cookie_consent_v1';
  var NAMES = { analytics: 1, personalization: 1, marketing: 1 };

  /* One whole sentence for each combination of the three optional categories (analytics, personalization, marketing). */
  function allowedText(c) {
    var a = !!c.analytics, p = !!c.personalization, m = !!c.marketing;
    if (a && p && m) return VQ.t('All categories are allowed.');
    if (a && p) return VQ.t('Allowed: essential, plus analytics and personalisation.');
    if (a && m) return VQ.t('Allowed: essential, plus analytics and marketing.');
    if (p && m) return VQ.t('Allowed: essential, plus personalisation and marketing.');
    if (a) return VQ.t('Allowed: essential, plus analytics.');
    if (p) return VQ.t('Allowed: essential, plus personalisation.');
    if (m) return VQ.t('Allowed: essential, plus marketing.');
    return VQ.t('Allowed: essential cookies only.');
  }

  function dateText(d) {
    try {
      return d.toLocaleDateString(VQ.locale === 'en' ? 'en-GB' : VQ.locale, { day: 'numeric', month: 'short', year: 'numeric' });
    } catch (e) { return d.toISOString().slice(0, 10); }
  }

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
      pill.textContent = on ? VQ.t('On') : VQ.t('Off');
      pill.setAttribute('data-on', String(on));
    });
    if (!s || !s.consent) {
      status.textContent = VQ.t('You have not yet chosen which cookies you allow. Until then only the essential ones run.');
      return;
    }
    var d = s.savedAt ? new Date(s.savedAt) : null;
    var when = d && !isNaN(d) ? VQ.t('Your choice, saved on {date}.', { date: dateText(d) }) : VQ.t('Your choice is saved.');
    status.textContent = when + ' ' + allowedText(s.consent);
  }

  window.addEventListener('bo-cookie-consent-updated', function (e) { render(e.detail); });
  // base.js decides on load whether the banner shows; read after it has run
  if (document.readyState === 'complete') render(saved());
  else window.addEventListener('load', function () { render(saved()); });
})();
