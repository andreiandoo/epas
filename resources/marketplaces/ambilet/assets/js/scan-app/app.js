/* =============================================================================
 * Scan App — global init
 * -----------------------------------------------------------------------------
 * Loaded on every /organizator/scan/* page. Etapa 1: just wire up auth check
 * fallback (server-side gate already redirects in _layout.php) and a toast
 * helper used by page-specific scripts later.
 *
 * EventContext, AppContext, scanner, etc. land in Etapa 2+.
 * ============================================================================= */
(function () {
  'use strict';

  // Guard: re-check auth once AmbiletAuth is on window. Server-side _layout.php
  // already did a synchronous redirect, but on slow networks or if auth.js
  // hasn't loaded yet, that gate is a no-op — repeat here defensively.
  function authGuard() {
    if (typeof AmbiletAuth === 'undefined') {
      // auth.js failed to load — degrade gracefully to login
      console.error('[scan-app] AmbiletAuth not loaded');
      return false;
    }
    if (!AmbiletAuth.isLoggedIn || !AmbiletAuth.isLoggedIn()) {
      var rt = encodeURIComponent(location.pathname + location.search);
      location.replace('/organizator/login?redirect=' + rt);
      return false;
    }
    return true;
  }

  function toast(message, kind) {
    var host = document.getElementById('scanapp-toasts');
    if (!host) return;
    var el = document.createElement('div');
    el.className = 'scanapp-toast' + (kind ? ' scanapp-toast--' + kind : '');
    el.textContent = message;
    host.appendChild(el);
    setTimeout(function () {
      el.style.opacity = '0';
      el.style.transform = 'translateY(8px)';
      el.style.transition = 'opacity 180ms, transform 180ms';
      setTimeout(function () { el.remove(); }, 220);
    }, 3200);
  }

  // Minimal event-picker stub: opens /organizator/scan/evenimente when ready.
  // Etapa 7 replaces this with the real picker.
  function bindHeader() {
    var picker = document.getElementById('scanapp-event-picker');
    if (picker) {
      picker.addEventListener('click', function () {
        toast('Selectorul de evenimente urmează în Etapa 2+', 'warning');
      });
    }
    var notif = document.getElementById('scanapp-notif');
    if (notif) {
      notif.addEventListener('click', function () {
        toast('Notificările urmează în Etapa 7', 'warning');
      });
    }
  }

  // Expose a small API for page scripts.
  window.ScanApp = window.ScanApp || {};
  window.ScanApp.toast = toast;
  window.ScanApp.authGuard = authGuard;

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () {
      if (authGuard()) bindHeader();
    });
  } else {
    if (authGuard()) bindHeader();
  }
})();
