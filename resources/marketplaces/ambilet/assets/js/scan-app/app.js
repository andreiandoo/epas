/* =============================================================================
 * Scan App — global init
 * -----------------------------------------------------------------------------
 * Loaded on every /organizator/scan/* page. Wires up:
 *   - auth guard (server-side gate is the primary; this is a JS-side double-check)
 *   - header event-name binding via EventContext subscription
 *   - global event-picker sheet (opened by header tap on any page)
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

  // ── Helpers ──────────────────────────────────────────────────────────────
  function pad(n) { return String(n).padStart(2, '0'); }
  function formatDateTime(value) {
    try {
      var d = new Date(value);
      if (isNaN(d.getTime())) return '—';
      return pad(d.getDate()) + '.' + pad(d.getMonth() + 1) + '.' + d.getFullYear() + ' · ' + pad(d.getHours()) + ':' + pad(d.getMinutes());
    } catch (e) { return '—'; }
  }
  function escapeHtml(s) {
    if (s == null) return '';
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');
  }

  // ── Header bindings ──────────────────────────────────────────────────────
  function formatEventMeta(event) {
    if (!event) return '—';
    var label = '';
    var dt = event.starts_at || event.start_date;
    if (dt) {
      label = formatDateTime(dt);
    }
    if (event.venue_name) label += (label ? ' · ' : '') + event.venue_name;
    return label || '—';
  }

  function renderHeader(event) {
    // The Header.js port doesn't surface the event name in the top bar.
    // The EventSelector strip below the topbar does — render it here.
    renderEventSelector(event);
  }

  function renderEventSelector(event) {
    var nameEl   = document.getElementById('scanapp-es-name');
    var metaEl   = document.getElementById('scanapp-es-meta');
    var badge    = document.getElementById('scanapp-es-badge');
    var badgeTxt = document.getElementById('scanapp-es-badge-text');
    if (!nameEl) return;
    if (!event) {
      nameEl.textContent = 'Niciun eveniment selectat';
      metaEl.textContent = 'Apasă pentru a alege un eveniment';
      if (badge) badge.hidden = true;
      return;
    }

    // Format short date prefix ("15 IUN") + event name
    var months = ['Ian','Feb','Mar','Apr','Mai','Iun','Iul','Aug','Sep','Oct','Noi','Dec'];
    var datePrefix = '';
    var dateSource = event.start_date || event.event_date || event.starts_at;
    if (dateSource) {
      try {
        var d = new Date(dateSource);
        if (!isNaN(d.getTime())) datePrefix = d.getDate() + ' ' + months[d.getMonth()];
      } catch (e) {}
    }
    var title = event.name || event.title || 'Eveniment';
    if (datePrefix) {
      nameEl.innerHTML = '<span class="scanapp-event-selector__date-prefix">' + escapeHtml(datePrefix) + '</span>' + escapeHtml(title);
    } else {
      nameEl.textContent = title;
    }

    // Venue · city
    var venue = event.venue_name || (event.venue && event.venue.name) || '';
    var city  = event.venue_city || (event.venue && event.venue.city) || '';
    var meta  = '';
    if (venue) meta = venue;
    if (city && city !== venue) meta += (meta ? ', ' : '') + city;
    metaEl.textContent = meta || '—';

    // Status badge
    if (badge && badgeTxt) {
      badge.hidden = false;
      var cat = event.timeCategory || 'future';
      var labels = { live: 'LIVE', today: 'AZI', future: 'Viitor', past: 'Încheiat' };
      badgeTxt.textContent = labels[cat] || 'Viitor';
      badge.className = 'scanapp-event-selector__badge scanapp-event-selector__badge--' + cat;
    }
  }

  function escapeHtml(s) {
    if (s == null) return '';
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');
  }

  function renderStatusPill() {
    var pill = document.getElementById('scanapp-status-pill');
    var txt  = document.getElementById('scanapp-status-text');
    if (!pill || !txt) return;
    var on = (typeof AppContext !== 'undefined' && AppContext.isOnline) ? AppContext.isOnline() : navigator.onLine !== false;
    pill.classList.toggle('scanapp-status-pill--online',  on);
    pill.classList.toggle('scanapp-status-pill--offline', !on);
    txt.textContent = on ? 'Live' : 'Offline';
  }

  // ── Global event picker sheet (created on demand, reused on all pages) ──
  var pickerSheet = null;

  function buildPickerSheet() {
    if (pickerSheet) return pickerSheet;
    var el = document.createElement('div');
    el.className = 'scanapp-sheet-backdrop';
    el.id = 'scanapp-global-event-sheet';
    el.setAttribute('role', 'dialog');
    el.setAttribute('aria-modal', 'true');
    el.innerHTML =
      '<div class="scanapp-sheet">' +
        '<div class="scanapp-sheet__handle"></div>' +
        '<h2 class="scanapp-sheet__title">Alege un eveniment</h2>' +
        '<div id="scanapp-global-event-sheet-body"></div>' +
        '<hr class="scanapp-divider">' +
        '<button type="button" class="scanapp-btn scanapp-btn--block" id="scanapp-global-event-sheet-close">Închide</button>' +
      '</div>';
    document.body.appendChild(el);
    el.querySelector('#scanapp-global-event-sheet-close').addEventListener('click', closePicker);
    el.addEventListener('click', function (e) { if (e.target === el) closePicker(); });
    pickerSheet = el;
    return el;
  }

  function renderPickerContents() {
    if (typeof EventContext === 'undefined') return;
    var s = EventContext.getState();
    var groups = s.groupedEvents || { live: [], today: [], future: [], past: [] };
    var labels = { live: 'Live acum', today: 'Azi', future: 'Viitoare', past: 'Trecute' };
    var html = '';
    ['live', 'today', 'future', 'past'].forEach(function (key) {
      var list = groups[key] || [];
      if (!list.length) return;
      html += '<div class="scanapp-section-title" style="margin-top:8px;">' + labels[key] + '</div>';
      html += list.map(function (e) {
        var meta = e.starts_at ? formatDateTime(e.starts_at) : '';
        if (e.venue_name) meta += (meta ? ' · ' : '') + e.venue_name;
        var isActive = (s.selectedEvent && Number(s.selectedEvent.id) === Number(e.id));
        return '<div class="scanapp-sheet__row" data-event-id="' + escapeHtml(e.id) + '" style="cursor:pointer;">' +
                 '<div class="scanapp-sheet__row-body">' +
                   '<div class="scanapp-sheet__row-name">' + escapeHtml(e.name || e.title || 'Eveniment') + (isActive ? ' ✓' : '') + '</div>' +
                   '<div class="scanapp-sheet__row-sub">' + escapeHtml(meta || '—') + '</div>' +
                 '</div>' +
               '</div>';
      }).join('');
    });
    if (!html) {
      var state = EventContext.getState();
      html = state.isLoadingEvents
        ? '<div class="scanapp-sheet__empty">Se încarcă evenimentele…</div>'
        : '<div class="scanapp-sheet__empty">Nu există evenimente disponibile.</div>';
    }
    var body = document.getElementById('scanapp-global-event-sheet-body');
    if (body) {
      body.innerHTML = html;
      body.querySelectorAll('[data-event-id]').forEach(function (row) {
        row.addEventListener('click', function () {
          var id = row.getAttribute('data-event-id');
          var ev = (EventContext.getState().events || []).find(function (e) { return String(e.id) === String(id); });
          if (ev) {
            EventContext.selectEvent(ev);
            closePicker();
          }
        });
      });
    }
  }

  function openPicker() {
    var el = buildPickerSheet();
    renderPickerContents();
    el.classList.add('scanapp-sheet-backdrop--open');
  }
  function closePicker() {
    if (pickerSheet) pickerSheet.classList.remove('scanapp-sheet-backdrop--open');
  }

  function bindHeader() {
    // EventSelector strip — opens the global picker on tap.
    var selectorBar = document.getElementById('scanapp-event-selector-bar');
    if (selectorBar) selectorBar.addEventListener('click', openPicker);

    // Refresh button (uses circular-arrow icon to clearly signal action).
    var refresh = document.getElementById('scanapp-refresh');
    if (refresh) {
      refresh.addEventListener('click', function () {
        if (typeof EventContext === 'undefined') return;
        var svg = refresh.querySelector('svg');
        if (svg) { svg.style.transition = 'transform 600ms'; svg.style.transform = 'rotate(-360deg)'; }
        Promise.resolve(EventContext.refreshAll && EventContext.refreshAll())
          .then(function () {
            toast('Datele au fost reîncărcate.', 'success');
          })
          .catch(function () {
            toast('Nu am putut reîncărca datele.', 'danger');
          })
          .finally(function () {
            setTimeout(function () { if (svg) svg.style.transform = ''; }, 700);
          });
      });
    }

    // Live status pill updates on online/offline events.
    renderStatusPill();
    if (typeof AppContext !== 'undefined' && AppContext.subscribe) {
      AppContext.subscribe('online-change', renderStatusPill);
    }
    window.addEventListener('online',  renderStatusPill);
    window.addEventListener('offline', renderStatusPill);
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
      renderPickerContents();
    });
    EventContext.subscribe('events-loaded', function () {
      renderPickerContents();
      // If hero/header is currently empty (no selectedEvent yet rendered),
      // re-pull state and force a header refresh.
      var s = EventContext.getState();
      if (s.selectedEvent) renderHeader(s.selectedEvent);
    });

    // SWR-aware bootstrap. event-context.js hydrated `state` synchronously
    // from localStorage during its IIFE, so page scripts already see last-
    // visit data. We now fire BOTH endpoints in parallel to revalidate:
    //   - fetchEvents() refreshes the events list
    //   - refreshAll() refreshes the selected event's stats + ticket types
    // Cold visits (no cache) skip the refreshAll() shortcut because
    // selectedEvent isn't set yet — fetchEvents() will pick the initial
    // event and chain into refreshAll() itself.
    var hadCache = !!EventContext.getState().selectedEvent;

    // Render the cached state immediately so the UI doesn't flash empty
    // while the revalidate is in flight.
    if (hadCache) {
      var snap = EventContext.getState();
      renderHeader(snap.selectedEvent);
      renderPickerContents();
    }

    if (hadCache && typeof EventContext.refreshAll === 'function') {
      // Parallel revalidate: events list + stats fire at the same time.
      Promise.all([EventContext.fetchEvents(), EventContext.refreshAll()])
        .catch(function () { /* errors already logged inside */ });
    } else {
      EventContext.fetchEvents();
    }
  }

  // Expose a small public API for page scripts.
  window.ScanApp = window.ScanApp || {};
  window.ScanApp.toast          = toast;
  window.ScanApp.authGuard      = authGuard;
  window.ScanApp.renderHeader   = renderHeader;
  window.ScanApp.openEventPicker= openPicker;

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bootstrap);
  } else {
    bootstrap();
  }
})();
