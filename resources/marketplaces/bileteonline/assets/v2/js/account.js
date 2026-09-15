/* bilete.online v2: customer account shell (/cont/*). Fills in the user card from the session, keeps the section
   badges (pages call BO_ACCOUNT.setBadges), wires both logout buttons, and brings the current section into view in the
   phone section bar. Exposes window.BO_ACCOUNT for the page scripts. */
(function () {
  'use strict';
  var root = document.querySelector('.acc');
  if (!root) return;

  function auth() { return typeof BileteOnlineAuth !== 'undefined' ? BileteOnlineAuth : null; }
  function isCustomer() {
    var a = auth();
    try {
      if (!a || !a.isLoggedIn()) return false;
      var type = a.getUserType();
      return !type || type === 'customer';
    } catch (e) { return false; }
  }
  function cachedUser() {
    var a = auth();
    try { return (a && a.getCustomerData && a.getCustomerData()) || null; } catch (e) { return null; }
  }

  function setUser(u) {
    if (!u) return;
    var full = ((u.first_name || '') + ' ' + (u.last_name || '')).trim() || u.name || '';
    var label = full || u.email || 'Client';
    var initials = label.split(/\s+/).filter(Boolean).map(function (s) { return s.charAt(0); }).join('').slice(0, 2).toUpperCase() || '?';
    [].forEach.call(root.querySelectorAll('[data-acc-initials]'), function (el) { el.textContent = initials; });
    [].forEach.call(root.querySelectorAll('[data-acc-name]'), function (el) { el.textContent = label; });
    [].forEach.call(root.querySelectorAll('[data-acc-email]'), function (el) { el.textContent = u.email || '—'; });
  }

  var fmt = new Intl.NumberFormat('ro-RO');
  function setBadges(badges) {
    Object.keys(badges || {}).forEach(function (key) {
      var n = Number(badges[key]);
      [].forEach.call(root.querySelectorAll('[data-acc-badge="' + key + '"]'), function (el) {
        var show = !isNaN(n) && n > 0;
        el.textContent = show ? fmt.format(n) : '';
        el.hidden = !show;
      });
    });
  }

  if (isCustomer()) setUser(cachedUser());
  window.addEventListener('bileteonline:auth:login', function (e) { if (e.detail && e.detail.type === 'customer') setUser(e.detail.user); });
  window.addEventListener('bileteonline:customer:loaded', function (e) { setUser(e.detail); });

  [].forEach.call(root.querySelectorAll('[data-acc-logout]'), function (btn) {
    btn.hidden = !isCustomer(); // nothing to sign out of on the login prompt
    btn.addEventListener('click', function () {
      [].forEach.call(root.querySelectorAll('[data-acc-logout]'), function (b) { b.disabled = true; });
      var label = btn.querySelector('span');
      if (label) label.textContent = 'Se deconectează…';
      var a = auth();
      if (a && typeof a.logoutCustomer === 'function') a.logoutCustomer(); // clears the session and goes to /
      else window.location.href = '/';
    });
  });

  // phone section bar: start with the current section visible
  var current = root.querySelector('.acc-mnav [aria-current="page"]');
  var track = root.querySelector('.acc-mnav-track');
  if (current && track && track.scrollWidth > track.clientWidth) {
    track.scrollLeft = Math.max(0, current.offsetLeft - (track.clientWidth - current.offsetWidth) / 2);
  }

  window.BO_ACCOUNT = { isCustomer: isCustomer, cachedUser: cachedUser, setUser: setUser, setBadges: setBadges };
})();
