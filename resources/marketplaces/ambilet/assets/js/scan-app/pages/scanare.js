/* =============================================================================
 * Scan App — pages/scanare.js (Check-In)
 * -----------------------------------------------------------------------------
 * Pixel-perfect port of tixello-app/src/screens/CheckInScreen.js layout:
 *   - 280x280 scanner frame with 4 corner brackets + animated purple scan line
 *   - Frame border + glow color change on scan result (purple→green/amber/red)
 *   - Result card: color-coded with 52x52 icon circle, ACCES APROBAT / DEJA
 *     SCANAT / BILET INVALID titles, name + ticket type details
 *   - Big purple "Începe Scanarea" button + "Cod Manual" secondary link
 *   - 3 stat pills: scanări/min · așteptare · intrați
 *   - Recent scans list with 32x32 status icon
 * ============================================================================= */
(function () {
  'use strict';

  function $(id) { return document.getElementById(id); }
  function escapeHtml(s) {
    if (s == null) return '';
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');
  }
  function pad(n) { return String(n).padStart(2, '0'); }

  var dom = {};
  function collectDom() {
    dom.reportsOnly      = $('scanapp-reports-only');
    dom.main             = $('scanapp-checkin-main');
    dom.video            = $('scanapp-video');
    dom.frame            = $('scanapp-scanner-frame');
    dom.scanLine         = $('scanapp-scan-line');
    dom.placeholder      = $('scanapp-scanner-placeholder');
    dom.placeholderText  = $('scanapp-scanner-placeholder-text');
    dom.btnCamera        = $('scanapp-btn-camera');
    dom.btnCameraText    = $('scanapp-btn-camera-text');
    dom.btnManual        = $('scanapp-btn-manual');
    dom.result           = $('scanapp-result');
    dom.manualModal      = $('scanapp-manual-modal');
    dom.manualInput      = $('scanapp-manual-input');
    dom.manualCancel     = $('scanapp-manual-cancel');
    dom.manualSubmit     = $('scanapp-manual-submit');
    dom.statSpm          = $('scanapp-stat-spm');
    dom.statWait         = $('scanapp-stat-wait');
    dom.statCheckedIn    = $('scanapp-stat-checkedin');
    dom.recentSection    = $('scanapp-recent-scans-section');
    dom.recentList       = $('scanapp-recent-scans-list');
  }

  var scanner = null;
  var cameraActive = false;
  var scanTimestamps = [];

  function setFrameColor(state) {
    dom.frame.classList.remove(
      'scanapp-scanner-frame--scanning',
      'scanapp-scanner-frame--valid',
      'scanapp-scanner-frame--invalid',
      'scanapp-scanner-frame--duplicate'
    );
    if (state) dom.frame.classList.add('scanapp-scanner-frame--' + state);
  }
  function setVideoVisible(on) {
    if (dom.video) dom.video.style.display = on ? 'block' : 'none';
    if (dom.placeholder) dom.placeholder.style.display = on ? 'none' : 'flex';
    if (dom.scanLine) dom.scanLine.hidden = !on;
  }

  // ── Result card rendering (mobile CheckInScreen.js renderResultCard) ─────
  var resultTimer = null;
  function showResult(type, data) {
    if (resultTimer) { clearTimeout(resultTimer); resultTimer = null; }

    var classes = { valid: 'scanapp-result--success', duplicate: 'scanapp-result--warning', invalid: 'scanapp-result--danger' };
    var titles  = { valid: 'ACCES APROBAT', duplicate: 'DEJA SCANAT', invalid: 'BILET INVALID' };
    var icons   = {
      valid:     '<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg>',
      duplicate: '<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>',
      invalid:   '<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6L6 18M6 6l12 12"/></svg>'
    };

    setFrameColor(type === 'valid' ? 'valid' : type === 'duplicate' ? 'duplicate' : 'invalid');

    var html = '<div class="scanapp-result__inner">' +
                 '<div class="scanapp-result__icon-circle">' + icons[type] + '</div>' +
                 '<div class="scanapp-result__text">' +
                   '<div class="scanapp-result__title">' + titles[type] + '</div>';
    if (type === 'valid') {
      html += '<div class="scanapp-result__name">' + escapeHtml(data.name || '—') + '</div>';
      if (data.ticketType) html += '<div class="scanapp-result__detail">' + escapeHtml(data.ticketType) + '</div>';
      if (data.seatInfo) html += '<div class="scanapp-result__detail" style="margin-top: 2px;">' + escapeHtml(data.seatInfo) + '</div>';
    } else if (type === 'duplicate') {
      if (data.name && data.name !== 'N/A') html += '<div class="scanapp-result__name">' + escapeHtml(data.name) + '</div>';
      var prev = data.checkedInAt ? 'Scanat: ' + data.checkedInAt : 'Bilet folosit anterior';
      html += '<div class="scanapp-result__detail" style="color: var(--scanapp-amber);">' + escapeHtml(prev) + '</div>';
    } else {
      html += '<div class="scanapp-result__detail" style="color: var(--scanapp-red);">' + escapeHtml(data.message || 'Bilet nerecunoscut') + '</div>';
    }
    html += '</div></div>';

    var autoConfirm = (window.AppContext && AppContext.get('autoConfirmValid')) === true;
    if (type === 'valid' && !autoConfirm) {
      html += '<button type="button" class="scanapp-result__next-btn" id="scanapp-result-next">Scanează Următorul</button>';
    }

    dom.result.className = 'scanapp-result scanapp-result--show ' + classes[type];
    dom.result.innerHTML = html;
    var next = $('scanapp-result-next');
    if (next) next.addEventListener('click', hideResult);
    if (type === 'valid' && autoConfirm) resultTimer = setTimeout(hideResult, 1500);
  }

  function hideResult() {
    if (dom.result) {
      dom.result.classList.remove('scanapp-result--show');
      dom.result.innerHTML = '';
    }
    setFrameColor(cameraActive ? 'scanning' : null);
  }

  // ── Check-in API ─────────────────────────────────────────────────────────
  function performCheckIn(code) {
    if (!code) return;
    if (typeof ScanAPI === 'undefined') { showResult('invalid', { message: 'API indisponibil' }); return; }
    var ev = EventContext.getState().selectedEvent;
    return ScanAPI.post('/organizer/participants/checkin', {
      ticket_code: code,
      event_id: ev ? ev.id : null
    }).then(function (resp) { handleCheckInResponse(resp, code); })
      .catch(function (err) {
        showResult('invalid', { message: (err && err.message) || 'Eroare de rețea' });
        ScanScanner.feedbackError();
        ScanScanner.vibratePattern([0, 200, 100, 200, 100, 200]);
      });
  }

  function handleCheckInResponse(resp, code) {
    var data = (resp && resp.data) || resp || {};
    var participant = data.participant || data.ticket || data;
    var ticketStatus = (participant && (participant.status || participant.ticket_status)) || null;
    var alreadyCheckedIn = !!(data.already_checked_in
      || ticketStatus === 'used' || ticketStatus === 'checked_in' || data.duplicate);

    var name = participant.customer_name || participant.name || participant.attendee_name
      || (participant.customer && participant.customer.name) || null;
    var ticketType = participant.ticket_type_name || participant.ticket_type
      || (participant.ticket_type && participant.ticket_type.name) || null;

    if (resp && resp.success === false && !alreadyCheckedIn) {
      showResult('invalid', { message: resp.message || 'Cod necunoscut', code: code });
      ScanScanner.feedbackError();
      ScanScanner.vibratePattern([0, 200, 100, 200, 100, 200]);
      if (window.AppContext && EventContext.getState().selectedEvent) {
        AppContext.addScan(EventContext.getState().selectedEvent.id, { code: code, valid: false, ticketType: ticketType, customerName: name });
      }
      return;
    }
    if (alreadyCheckedIn) {
      showResult('duplicate', { name: name, ticketType: ticketType, checkedInAt: participant.checked_in_at || data.checked_in_at || null });
      ScanScanner.feedbackWarning();
      ScanScanner.vibratePattern([0, 100, 100, 100]);
      if (window.AppContext && EventContext.getState().selectedEvent) {
        AppContext.addScan(EventContext.getState().selectedEvent.id, { code: code, valid: false, duplicate: true, ticketType: ticketType, customerName: name });
      }
      return;
    }
    showResult('valid', { name: name, ticketType: ticketType });
    pulseScanTimestamp();
    if (window.EventContext) EventContext.incrementCheckedIn();
    if (window.AppContext && EventContext.getState().selectedEvent) {
      AppContext.addScan(EventContext.getState().selectedEvent.id, { code: code, valid: true, ticketType: ticketType, customerName: name });
    }
  }

  function pulseScanTimestamp() {
    var now = Date.now();
    scanTimestamps.push(now);
    var cutoff = now - 60000;
    while (scanTimestamps.length && scanTimestamps[0] < cutoff) scanTimestamps.shift();
    dom.statSpm.textContent = String(scanTimestamps.length);
  }
  function renderLiveStats(stats) {
    if (!stats) return;
    dom.statCheckedIn.textContent = String(stats.checked_in || 0);
    dom.statWait.textContent = '0s';
  }

  function renderRecentScans() {
    var ev = EventContext.getState().selectedEvent;
    if (!ev || !dom.recentList) return;
    var list = AppContext.getRecentScans(ev.id).slice(0, 10);
    if (!list.length) { dom.recentSection.hidden = true; return; }
    dom.recentSection.hidden = false;
    dom.recentList.innerHTML = list.map(function (s) {
      var type = s.valid === true ? 'valid' : (s.duplicate === true ? 'duplicate' : 'invalid');
      var iconPath = type === 'valid'
        ? '<path d="M20 6L9 17l-5-5"/>'
        : type === 'duplicate'
          ? '<path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>'
          : '<path d="M18 6L6 18M6 6l12 12"/>';
      var time = '';
      try { var d = new Date(s.time); time = pad(d.getHours()) + ':' + pad(d.getMinutes()); } catch (e) {}
      var name = s.customerName || s.code || 'Cod ' + (s.code || '');
      var ticketType = s.ticketType || 'Bilet';
      return '<div class="scanapp-recent-scan">' +
               '<div class="scanapp-recent-scan__status scanapp-recent-scan__status--' + type + '">' +
                 '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">' + iconPath + '</svg>' +
               '</div>' +
               '<div class="scanapp-recent-scan__text">' +
                 '<div class="scanapp-recent-scan__name">' + escapeHtml(ticketType) + '</div>' +
                 '<div class="scanapp-recent-scan__type">' + escapeHtml(name) + ' · ' + escapeHtml(s.code || '') + '</div>' +
               '</div>' +
               '<div class="scanapp-recent-scan__time">' + escapeHtml(time) + '</div>' +
             '</div>';
    }).join('');
  }

  function openManual() {
    dom.manualInput.value = '';
    dom.manualModal.classList.add('scanapp-modal-overlay--open');
    setTimeout(function () { dom.manualInput.focus(); }, 50);
  }
  function closeManual() { dom.manualModal.classList.remove('scanapp-modal-overlay--open'); }
  function submitManual() {
    var code = (dom.manualInput.value || '').trim();
    if (!code) return;
    closeManual();
    performCheckIn(code);
  }

  function startCamera() {
    if (!window.ScanScanner) { ScanApp.toast('Scanner-ul nu s-a încărcat.', 'danger'); return; }
    if (!scanner) {
      scanner = ScanScanner.create({
        videoEl: dom.video,
        onScan: function (s) { performCheckIn(s.code); },
        onError: function (e) {
          dom.placeholderText.textContent = e.message || 'Eroare cameră.';
          setVideoVisible(false);
          ScanApp.toast(e.message || 'Eroare cameră.', 'danger');
        },
        onPermission: function (p) { if (p.granted) setVideoVisible(true); }
      });
    }
    scanner.start();
    cameraActive = true;
    dom.btnCameraText.textContent = 'Oprește Camera';
    dom.btnCamera.classList.add('scanapp-scan-button--scanning');
    setFrameColor('scanning');
  }
  function stopCamera() {
    if (scanner) scanner.stop();
    cameraActive = false;
    dom.btnCameraText.textContent = 'Începe Scanarea';
    dom.btnCamera.classList.remove('scanapp-scan-button--scanning');
    setVideoVisible(false);
    setFrameColor(null);
  }
  function toggleCamera() { cameraActive ? stopCamera() : startCamera(); }

  function reflectReportsOnly() {
    var on = window.EventContext && EventContext.isReportsOnlyMode();
    dom.reportsOnly.hidden = !on;
    dom.main.hidden = on;
    if (on && cameraActive) stopCamera();
  }

  function init() {
    collectDom();
    if (!dom.frame) return;
    setVideoVisible(false);

    dom.btnCamera.addEventListener('click', toggleCamera);
    dom.btnManual.addEventListener('click', openManual);
    dom.manualCancel.addEventListener('click', closeManual);
    dom.manualSubmit.addEventListener('click', submitManual);
    dom.placeholder.addEventListener('click', toggleCamera);
    dom.manualInput.addEventListener('keydown', function (e) { if (e.key === 'Enter') submitManual(); });
    dom.manualModal.addEventListener('click', function (e) { if (e.target === dom.manualModal) closeManual(); });

    if (window.EventContext) {
      EventContext.subscribe('event-selected', function () {
        reflectReportsOnly();
        renderRecentScans();
        if (scanner) scanner.clearDedup();
      });
      EventContext.subscribe('stats-updated', function (e) { renderLiveStats(e.detail.stats); });
      reflectReportsOnly();
      var s = EventContext.getState();
      if (s.eventStats) renderLiveStats(s.eventStats);
      renderRecentScans();
    }
    if (window.AppContext) AppContext.subscribe('scan-added', renderRecentScans);
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
})();
