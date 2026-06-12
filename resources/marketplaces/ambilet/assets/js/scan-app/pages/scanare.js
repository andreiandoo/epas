/* =============================================================================
 * Scan App — pages/scanare.js
 * -----------------------------------------------------------------------------
 * Controller for /organizator/scan/scanare. Wires the scanner to the check-in
 * API, renders result cards, and bridges manual entry. Mirrors the logic of
 * tixello-app/src/screens/CheckInScreen.js.
 * ============================================================================= */
(function () {
  'use strict';

  // ── DOM refs ─────────────────────────────────────────────────────────────
  var dom = {};
  function $(id) { return document.getElementById(id); }

  function collectDom() {
    dom.video           = $('scanapp-video');
    dom.scannerHost     = $('scanapp-scanner-host');
    dom.placeholder     = $('scanapp-scanner-placeholder');
    dom.placeholderText = $('scanapp-scanner-placeholder-text');
    dom.btnCamera       = $('scanapp-btn-camera');
    dom.btnManual       = $('scanapp-btn-manual');
    dom.result          = $('scanapp-result');
    dom.resultIcon      = $('scanapp-result-icon');
    dom.resultTitle     = $('scanapp-result-title');
    dom.resultSubtitle  = $('scanapp-result-subtitle');
    dom.resultDetails   = $('scanapp-result-details');
    dom.resultNext      = $('scanapp-result-next');
    dom.manualModal     = $('scanapp-manual-modal');
    dom.manualInput     = $('scanapp-manual-input');
    dom.manualCancel    = $('scanapp-manual-cancel');
    dom.manualSubmit    = $('scanapp-manual-submit');
    dom.reportsOnly     = $('scanapp-reports-only');
    dom.statPills       = $('scanapp-stat-pills');
    dom.statSpm         = $('scanapp-stat-spm');
    dom.statCheckedIn   = $('scanapp-stat-checkedin');
    dom.statRate        = $('scanapp-stat-rate');
  }

  // ── Scanner state ────────────────────────────────────────────────────────
  var scanner = null;
  var cameraActive = false;
  var scanTimestamps = []; // for scans/min rolling window

  // ── Result rendering ─────────────────────────────────────────────────────
  var KIND_TO_CLASS = {
    success: 'scanapp-result--success',
    warning: 'scanapp-result--warning',
    danger:  'scanapp-result--danger'
  };

  var ICONS = {
    success: '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>',
    warning: '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>',
    danger:  '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>'
  };

  function showResult(kind, title, subtitle, details) {
    if (!dom.result) return;
    dom.result.classList.remove('scanapp-result--success', 'scanapp-result--warning', 'scanapp-result--danger');
    dom.result.classList.add('scanapp-result--show');
    if (KIND_TO_CLASS[kind]) dom.result.classList.add(KIND_TO_CLASS[kind]);
    dom.resultIcon.innerHTML  = ICONS[kind] || '';
    dom.resultTitle.textContent    = title || '—';
    dom.resultSubtitle.textContent = subtitle || '';
    dom.resultDetails.innerHTML = '';
    (details || []).forEach(function (d) {
      var row = document.createElement('div');
      row.className = 'scanapp-result__detail-row';
      row.innerHTML = '<div class="scanapp-result__detail-label">' + escapeHtml(d.label) + '</div>' +
                      '<div class="scanapp-result__detail-value">' + escapeHtml(d.value || '—') + '</div>';
      dom.resultDetails.appendChild(row);
    });

    // Auto-advance for success when autoConfirmValid is on.
    var auto = (window.AppContext && AppContext.get('autoConfirmValid')) === true;
    if (kind === 'success' && auto) {
      setTimeout(hideResult, 1500);
    }
  }

  function hideResult() {
    if (!dom.result) return;
    dom.result.classList.remove('scanapp-result--show');
  }

  function escapeHtml(s) {
    if (s == null) return '';
    return String(s)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
  }

  // ── Check-in API ─────────────────────────────────────────────────────────
  function performCheckIn(code) {
    if (!code) return Promise.resolve(null);
    if (typeof AmbiletAPI === 'undefined' || !AmbiletAPI.post) {
      showResult('danger', 'Eroare conexiune', 'AmbiletAPI nu este disponibilă.');
      return Promise.resolve(null);
    }

    var event = (window.EventContext && EventContext.getState().selectedEvent) || null;
    var eventId = event ? event.id : null;

    return AmbiletAPI.post('/organizer/participants/checkin', {
      ticket_code: code,
      event_id: eventId
    }).then(function (resp) {
      handleCheckInResponse(resp, code);
      return resp;
    }).catch(function (err) {
      console.error('[scanare] check-in error:', err);
      handleCheckInError(err, code);
      throw err;
    });
  }

  function handleCheckInResponse(resp, code) {
    var data = (resp && resp.data) || resp || {};
    var participant = data.participant || data.ticket || data;
    var ticketStatus = (participant && (participant.status || participant.ticket_status)) || null;
    var alreadyCheckedIn = !!(data.already_checked_in
      || ticketStatus === 'used'
      || ticketStatus === 'checked_in'
      || data.duplicate);

    // Build common detail rows
    var name = participant.customer_name || participant.name || participant.attendee_name
            || (participant.customer && participant.customer.name) || null;
    var ticketType = participant.ticket_type_name || participant.ticket_type
            || (participant.ticket_type && participant.ticket_type.name) || null;
    var orderNumber = participant.order_number || participant.order_id || null;

    var details = [];
    if (name)        details.push({ label: 'Nume',        value: name });
    if (ticketType)  details.push({ label: 'Tip bilet',   value: ticketType });
    if (orderNumber) details.push({ label: 'Comandă',     value: '#' + orderNumber });
    details.push({ label: 'Cod', value: code });

    if (resp && resp.success === false && !alreadyCheckedIn) {
      showResult('danger', 'Bilet invalid', resp.message || 'Codul nu a fost recunoscut.', details);
      if ((AppContext.get('soundEffects') !== false)) ScanScanner.feedbackError();
      if ((AppContext.get('vibrationFeedback') !== false)) ScanScanner.vibratePattern([0, 200, 100, 200, 100, 200]);
      return;
    }

    if (alreadyCheckedIn) {
      var prev = participant.checked_in_at || data.checked_in_at;
      if (prev) details.unshift({ label: 'Scanat anterior', value: formatDateTime(prev) });
      showResult('warning', 'Deja scanat', resp.message || 'Acest bilet a fost deja folosit.', details);
      if ((AppContext.get('soundEffects') !== false)) ScanScanner.feedbackWarning();
      if ((AppContext.get('vibrationFeedback') !== false)) ScanScanner.vibratePattern([0, 100, 100, 100]);
      if (window.AppContext && EventContext.getState().selectedEvent) {
        AppContext.addScan(EventContext.getState().selectedEvent.id, {
          code: code, valid: false, duplicate: true, ticketType: ticketType, customerName: name
        });
      }
      return;
    }

    // Success
    showResult('success', 'Bilet valid', name || 'Check-in reușit', details);
    pulseScanTimestamp();
    if (window.EventContext) EventContext.incrementCheckedIn();
    if (window.AppContext && EventContext.getState().selectedEvent) {
      AppContext.addScan(EventContext.getState().selectedEvent.id, {
        code: code, valid: true, ticketType: ticketType, customerName: name
      });
    }
  }

  function handleCheckInError(err, code) {
    var msg = (err && err.message) || 'Eroare de rețea.';
    var status = err && err.status;
    var kind = 'danger';
    var title = 'Eroare';
    if (status === 404) { title = 'Bilet inexistent'; msg = 'Codul nu există în sistem.'; }
    else if (status === 403) { title = 'Acces refuzat'; msg = 'Nu ai permisiunea de check-in pe acest eveniment.'; }
    else if (!navigator.onLine) { title = 'Fără conexiune'; msg = 'Reconectează-te și reîncearcă.'; kind = 'warning'; }
    showResult(kind, title, msg, [{ label: 'Cod', value: code }]);
    if ((AppContext.get('soundEffects') !== false)) ScanScanner.feedbackError();
    if ((AppContext.get('vibrationFeedback') !== false)) ScanScanner.vibratePattern([0, 200, 100, 200, 100, 200]);
  }

  function formatDateTime(value) {
    try {
      var d = new Date(value);
      if (isNaN(d.getTime())) return String(value);
      var dd = String(d.getDate()).padStart(2, '0');
      var mm = String(d.getMonth() + 1).padStart(2, '0');
      var yy = d.getFullYear();
      var hh = String(d.getHours()).padStart(2, '0');
      var mi = String(d.getMinutes()).padStart(2, '0');
      return dd + '.' + mm + '.' + yy + ' ' + hh + ':' + mi;
    } catch (e) { return String(value); }
  }

  // ── Live stat pills ──────────────────────────────────────────────────────
  function pulseScanTimestamp() {
    var now = Date.now();
    scanTimestamps.push(now);
    var cutoff = now - 60000;
    while (scanTimestamps.length && scanTimestamps[0] < cutoff) scanTimestamps.shift();
    dom.statSpm.textContent = String(scanTimestamps.length);
  }

  function renderStatPills(stats) {
    if (!stats) return;
    dom.statCheckedIn.textContent = String(stats.checked_in || 0);
    var rate = stats.check_in_rate || 0;
    dom.statRate.textContent = Math.round(rate) + '%';
  }

  // ── Manual entry sheet ───────────────────────────────────────────────────
  function openManual() {
    dom.manualInput.value = '';
    dom.manualModal.classList.add('scanapp-modal--open');
    setTimeout(function () { dom.manualInput.focus(); }, 50);
  }
  function closeManual() { dom.manualModal.classList.remove('scanapp-modal--open'); }
  function submitManual() {
    var code = (dom.manualInput.value || '').trim();
    if (!code) return;
    closeManual();
    performCheckIn(code);
  }

  // ── Camera lifecycle ─────────────────────────────────────────────────────
  function startCamera() {
    if (!window.ScanScanner) {
      ScanApp.toast('Scanner-ul nu s-a încărcat.', 'danger');
      return;
    }
    if (!scanner) {
      scanner = ScanScanner.create({
        videoEl: dom.video,
        onScan: function (s) { performCheckIn(s.code); },
        onError: function (e) {
          if (dom.placeholderText) dom.placeholderText.textContent = e.message || 'Eroare cameră.';
          dom.placeholder.style.display = 'flex';
          ScanApp.toast(e.message || 'Eroare cameră.', 'danger');
        },
        onPermission: function (p) {
          if (p.granted) dom.placeholder.style.display = 'none';
        }
      });
    }
    scanner.start();
    cameraActive = true;
    dom.btnCamera.textContent = 'Oprește camera';
  }

  function stopCamera() {
    if (scanner) scanner.stop();
    cameraActive = false;
    dom.btnCamera.textContent = 'Pornește camera';
    dom.placeholder.style.display = 'flex';
  }

  function toggleCamera() {
    if (cameraActive) stopCamera();
    else              startCamera();
  }

  // ── Reports-only mode + event change ─────────────────────────────────────
  function reflectReportsOnlyMode() {
    var on = window.EventContext && EventContext.isReportsOnlyMode();
    dom.reportsOnly.hidden = !on;
    dom.scannerHost.style.display = on ? 'none' : '';
    document.querySelector('.scanapp-scanner-controls').style.display = on ? 'none' : '';
    if (on && cameraActive) stopCamera();
  }

  // ── Wire up ──────────────────────────────────────────────────────────────
  function init() {
    collectDom();
    if (!dom.video) return;

    dom.btnCamera.addEventListener('click', toggleCamera);
    dom.btnManual.addEventListener('click', openManual);
    dom.resultNext.addEventListener('click', hideResult);
    dom.manualCancel.addEventListener('click', closeManual);
    dom.manualSubmit.addEventListener('click', submitManual);
    dom.manualInput.addEventListener('keydown', function (e) {
      if (e.key === 'Enter') submitManual();
    });
    dom.manualModal.addEventListener('click', function (e) {
      if (e.target === dom.manualModal) closeManual();
    });

    if (window.EventContext) {
      EventContext.subscribe('event-selected', function () {
        reflectReportsOnlyMode();
        if (scanner) scanner.clearDedup();
      });
      EventContext.subscribe('stats-updated', function (e) {
        renderStatPills(e.detail.stats);
        dom.statPills.hidden = false;
      });
      // Initial check (events may already have been loaded by app.js)
      reflectReportsOnlyMode();
      var s = EventContext.getState();
      if (s.eventStats) {
        renderStatPills(s.eventStats);
        dom.statPills.hidden = false;
      }
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
