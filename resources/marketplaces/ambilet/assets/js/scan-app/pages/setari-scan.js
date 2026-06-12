/* =============================================================================
 * Scan App — pages/setari-scan.js (Settings)
 * -----------------------------------------------------------------------------
 * Mirrors SettingsScreen.js: account display, scanner toggles, hardware
 * (read-only placeholders for NFC/POS hardware which are unsupported on web),
 * admin section gate, install banners (Android APK redirect / iOS A2HS),
 * logout.
 * ============================================================================= */
(function () {
  'use strict';

  function $(id) { return document.getElementById(id); }

  var SETTINGS = ['vibrationFeedback', 'soundEffects', 'autoConfirmValid'];

  function reflectToggle(setting) {
    var el = document.querySelector('[data-setting="' + setting + '"]');
    if (!el) return;
    var on = AppContext.get(setting) === true;
    el.setAttribute('aria-checked', on ? 'true' : 'false');
  }

  function reflectAllToggles() { SETTINGS.forEach(reflectToggle); }

  function bindToggles() {
    SETTINGS.forEach(function (setting) {
      var el = document.querySelector('[data-setting="' + setting + '"]');
      if (!el) return;
      el.addEventListener('click', function () {
        AppContext.set(setting, !(AppContext.get(setting) === true));
      });
      el.addEventListener('keydown', function (e) {
        if (e.key === ' ' || e.key === 'Enter') {
          e.preventDefault();
          AppContext.set(setting, !(AppContext.get(setting) === true));
        }
      });
    });
    AppContext.subscribe('settings-change', function (e) {
      reflectToggle(e.detail.key);
    });
  }

  function renderAccount() {
    var nameEl = $('scanapp-account-name');
    var roleEl = $('scanapp-account-role');
    var tm = ScanAuth.getTeamMember();
    var org = ScanAuth.getOrganizer();
    var displayName, displayRole;
    if (tm) {
      displayName = tm.name || tm.email || 'Membru echipă';
      displayRole = (tm.role || 'staff') + ' · ' + (org.name || '—');
    } else {
      displayName = (org && org.public_name) || (org && org.name) || 'Organizator';
      displayRole = 'Owner · ' + ((org && org.name) || '—');
    }
    if (nameEl) nameEl.textContent = displayName;
    if (roleEl) roleEl.textContent = displayRole;

    var admin = $('scanapp-admin-section');
    if (admin) admin.hidden = !ScanAuth.isAdmin();
  }

  function renderInstallBanners() {
    var ua = navigator.userAgent || '';
    var isAndroid = /Android/i.test(ua);
    var isIOS = /iPad|iPhone|iPod/.test(ua) && !window.MSStream;
    var inStandalone = (window.matchMedia && window.matchMedia('(display-mode: standalone)').matches) ||
                       (window.navigator && window.navigator.standalone === true);
    var a = $('scanapp-android-banner');
    var b = $('scanapp-ios-banner');
    if (a) a.hidden = !isAndroid;
    if (b) b.hidden = !(isIOS && !inStandalone);
  }

  function renderVersion() {
    var v = $('scanapp-version');
    if (v && window.SCAN_APP) v.textContent = window.SCAN_APP.version || '—';
  }

  function bindLogout() {
    var btn = $('scanapp-logout-btn');
    if (!btn) return;
    btn.addEventListener('click', function () {
      ScanAuth.logout();
    });
  }

  function init() {
    renderAccount();
    reflectAllToggles();
    bindToggles();
    renderInstallBanners();
    renderVersion();
    bindLogout();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
