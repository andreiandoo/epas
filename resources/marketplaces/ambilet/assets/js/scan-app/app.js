/* =============================================================================
 * Scan App — global init
 * -----------------------------------------------------------------------------
 * Loaded on every /organizator/scan/* page. Wires up:
 *   - auth guard (server-side gate is the primary; this is a JS-side double-check)
 *   - header event-name binding via EventContext subscription
 *   - bootstrap fetchEvents() once auth is OK
 *   - toast helper used by page-specific scripts
 *
 * Dependencies (loaded BEFORE this file via _layout_end.php):
 *   - /assets/js/auth.js       (AmbiletAuth — existing main panel)
 *   - /assets/js/api.js        (AmbiletAPI — existing main panel)
 *   - /assets/js/scan-app/auth.js          → ScanAuth
 *   - /assets/js/scan-app/app-context.js   → AppContext
 *   - /assets/js/scan-app/event-context.js → EventContext
 * ============================================================================= */
(function () {
  'use strict';

  // ── Auth guard (defense in depth — server-side _layout.php already redirects) ──
  function authGuard() {
    if (typeof ScanAuth === 'undefined' || !ScanAuth.isLoggedIn()) {
      var rt = encodeURIComponent(location.pathname + location.search);
      location.replace('/organizator/login?redirect=' + rt);
      return false;
    }
    return true;
  }

  // ── Toast helper ─────────────────────────────────────────────────────────
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

  // ── Header bindings ──────────────────────────────────────────────────────
  function formatEventMeta(event) {
    if (!event) return '—';
    var label = '';
    var dt = event.starts_at || event.start_date;
    if (dt) {
      try {
        var d = new Date(dt);
        if (!isNaN(d.getTime())) {
          var dd = String(d.getDate()).padStart(2, '0');
          var mm = String(d.getMonth() + 1).padStart(2, '0');
          var yy = d.getFullYear();
          var hh = String(d.getHours()).padStart(2, '0');
          var mi = String(d.getMinutes()).padStart(2, '0');
          label = dd + '.' + mm + '.' + yy + ' · ' + hh + ':' + mi;
        }
      } catch (e) {}
    }
    if (event.venue_name) label += (label ? ' · ' : '') + event.venue_name;
    return label || '—';
  }

  function renderHeader(event) {
    var nameEl = document.getElementById('scanapp-event-name');
    var metaEl = document.getElementById('scanapp-event-meta');
    if (nameEl) nameEl.textContent = event ? (event.name || event.title || 'Eveniment') : 'Selectează un eveniment';
    if (metaEl) metaEl.textContent = formatEventMeta(event);
  }

  function bindHeader() {
    var picker = document.getElementById('scanapp-event-picker');
    if (picker) {
      picker.addEventListener('click', function () {
        // Etapa 7 replaces this stub with the real picker page (modal or drawer).
        toast('Selectorul de evenimente urmează în Etapa 7', 'warning');
      });
    }
    var notif = document.getElementById('scanapp-notif');
    if (notif) {
      notif.addEventListener('click', function () {
        toast('Notificările urmează în Etapa 7', 'warning');
      });
    }
  }

  // ── Bootstrap ────────────────────────────────────────────────────────────
  function bootstrap() {
    if (!authGuard()) return;
    bindHeader();

    if (typeof EventContext === 'undefined') {
      console.error('[scan-app] EventContext not loaded');
      return;
    }

    // Render header on selection change.
    EventContext.subscribe('event-selected', function (e) {
      renderHeader(e.detail.event);
    });

    // Initial events fetch (fills events list + auto-selects first available).
    EventContext.fetchEvents();
  }

  // Expose a small public API for page scripts.
  window.ScanApp = window.ScanApp || {};
  window.ScanApp.toast       = toast;
  window.ScanApp.authGuard   = authGuard;
  window.ScanApp.renderHeader = renderHeader;

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bootstrap);
  } else {
    bootstrap();
  }
})();
