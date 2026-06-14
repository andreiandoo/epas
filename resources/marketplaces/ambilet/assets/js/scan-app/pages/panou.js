/* =============================================================================
 * Scan App — pages/panou.js (Dashboard)
 * -----------------------------------------------------------------------------
 * Pixel-perfect port of tixello-app/src/screens/DashboardScreen.js.
 * Renders dual modes (admin vs scanner) based on ScanAuth.getUserRole().
 * ============================================================================= */
(function () {
  'use strict';

  function $(id) { return document.getElementById(id); }
  function escapeHtml(s) {
    if (s == null) return '';
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');
  }
  function pad(n) { return String(n).padStart(2, '0'); }
  function formatLei(amount) {
    var n = Number(amount) || 0;
    return n.toLocaleString('ro-RO', { maximumFractionDigits: 2 }) + ' lei';
  }

  // ── Event header: date row + countdown + name + venue ────────────────────
  function renderEventHeader(event) {
    var dateEl     = $('scanapp-event-date');
    var countdown  = $('scanapp-countdown');
    var countdownT = $('scanapp-countdown-text');
    var nameEl     = $('scanapp-event-name-hero');
    var venueEl    = $('scanapp-event-venue-hero');

    if (!event) {
      dateEl.textContent = 'Selectează un eveniment';
      countdown.hidden = true;
      nameEl.textContent = '—';
      venueEl.textContent = '—';
      return;
    }

    var name = event.name || event.title || 'Eveniment';
    nameEl.textContent = name;

    var venueParts = [];
    if (event.venue_name) venueParts.push(event.venue_name);
    if (event.venue_city) venueParts.push(event.venue_city);
    venueEl.textContent = venueParts.join(' · ') || '—';

    if (event.starts_at) {
      try {
        var d = new Date(event.starts_at);
        if (!isNaN(d.getTime())) {
          var months = ['IAN','FEB','MAR','APR','MAI','IUN','IUL','AUG','SEP','OCT','NOI','DEC'];
          var weekdays = ['DUMINICĂ','LUNI','MARȚI','MIERCURI','JOI','VINERI','SÂMBĂTĂ'];
          var dateStr = weekdays[d.getDay()] + ', ' + d.getDate() + ' ' + months[d.getMonth()] + ' ' + d.getFullYear() + ' · ' + pad(d.getHours()) + ':' + pad(d.getMinutes());
          dateEl.textContent = dateStr;

          // Countdown badge
          var now = new Date();
          var msDiff = d.getTime() - now.getTime();
          if (msDiff > 0) {
            var days = Math.floor(msDiff / 86400000);
            var hours = Math.floor((msDiff % 86400000) / 3600000);
            countdown.hidden = false;
            if (days >= 1) countdownT.textContent = 'În ' + days + ' ' + (days === 1 ? 'zi' : 'zile');
            else if (hours >= 1) countdownT.textContent = 'În ' + hours + 'h';
            else countdownT.textContent = 'Curând';
          } else {
            var hoursPast = Math.floor(-msDiff / 3600000);
            if (hoursPast < 12) {
              countdown.hidden = false;
              countdownT.textContent = 'LIVE ACUM';
            } else {
              countdown.hidden = true;
            }
          }
        }
      } catch (e) {
        countdown.hidden = true;
      }
    } else {
      countdown.hidden = true;
    }
  }

  // ── Live stats render ────────────────────────────────────────────────────
  function renderStats(stats) {
    if (!stats) return;
    var entered    = Number(stats.checked_in) || 0;
    var total      = Number(stats.total) || 0;
    var sold       = stats.total_sold != null ? Number(stats.total_sold) : total;
    var revenue    = Number(stats.revenue) || 0;
    var capacity   = Number(stats.capacity) || 0;
    var remaining  = sold - entered; // mobile: totalSold > 0 ? totalSold - checkedIn : 0
    if (remaining < 0) remaining = 0;
    var rate       = total > 0 ? (entered / total) * 100 : 0;
    var capacityPct= capacity > 0 ? (sold / capacity) * 100 : 0;
    var barWidth   = Math.min(rate, 100);

    $('scanapp-stat-entered').textContent     = entered.toLocaleString('ro-RO');
    $('scanapp-stat-total').textContent       = sold.toLocaleString('ro-RO');
    $('scanapp-checkedin-pct').textContent    = rate.toFixed(1) + '%';
    $('scanapp-entered-bar').style.width      = barWidth.toFixed(1) + '%';

    $('scanapp-stat-sold').textContent        = sold.toLocaleString('ro-RO');
    $('scanapp-stat-revenue').textContent     = formatLei(revenue);
    $('scanapp-stat-remaining').textContent   = remaining.toLocaleString('ro-RO');
    $('scanapp-stat-capacity').textContent    = capacityPct.toFixed(1) + '%';

    // Reports-only mirror
    $('scanapp-ro-sold').textContent     = sold.toLocaleString('ro-RO');
    $('scanapp-ro-checked').textContent  = entered.toLocaleString('ro-RO');
    $('scanapp-ro-revenue').textContent  = formatLei(revenue);
    $('scanapp-ro-rate').textContent     = rate.toFixed(1) + '%';
  }

  // ── Reports-only mode toggle ─────────────────────────────────────────────
  function reflectReportsOnly() {
    var pastMode = EventContext.isReportsOnlyMode();
    $('scanapp-reports-banner').hidden = !pastMode;
    $('scanapp-reports-grid').hidden   = !pastMode;
    $('scanapp-live-stats').hidden     = pastMode;
  }

  // ── Recent activity list (formatted exactly like mobile) ─────────────────
  function renderRecentActivity() {
    var host = $('scanapp-recent-activity');
    if (!host) return;
    var ev = EventContext.getState().selectedEvent;
    if (!ev) return;
    var scans = AppContext.getRecentScans(ev.id).slice(0, 10);

    if (!scans.length) {
      host.innerHTML =
        '<div class="scanapp-recent-empty">' +
          '<svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color: var(--scanapp-text-quaternary);"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>' +
          '<div class="scanapp-recent-empty__text">Nicio activitate recentă</div>' +
        '</div>';
      return;
    }

    host.innerHTML = scans.map(function (s) {
      var ok = s.valid === true && s.duplicate !== true;
      var dotCls = ok ? 'scanapp-activity-dot--green' : 'scanapp-activity-dot--red';
      var iconColor = ok ? 'var(--scanapp-green)' : 'var(--scanapp-red)';
      var iconPath = ok
        ? '<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>'
        : '<circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/>';
      var name = s.customerName || s.code || 'Unknown';
      var when = '';
      try {
        var d = new Date(s.time);
        when = pad(d.getHours()) + ':' + pad(d.getMinutes());
      } catch (e) {}
      var meta = (s.ticketType || 'Bilet') + ' · ' + (when || 'acum');
      return '<div class="scanapp-activity-item">' +
               '<div class="scanapp-activity-dot ' + dotCls + '"></div>' +
               '<div class="scanapp-activity-content">' +
                 '<div class="scanapp-activity-name">' + escapeHtml(name) + '</div>' +
                 '<div class="scanapp-activity-meta">' + escapeHtml(meta) + '</div>' +
               '</div>' +
               '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="' + iconColor + '" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' + iconPath + '</svg>' +
             '</div>';
    }).join('');
  }

  // ── Scanner view (staff) — turnover + my stats + actions ─────────────────
  var durationTimer = null;
  function formatDuration(startMs) {
    if (!startMs) return '—';
    var ms = Date.now() - Number(startMs);
    if (ms < 0) return '—';
    var s = Math.floor(ms / 1000);
    var h = Math.floor(s / 3600); s = s - h * 3600;
    var m = Math.floor(s / 60);
    return (h > 0 ? h + 'h ' : '') + m + 'min';
  }
  function renderShift(shift) {
    if (!shift) shift = AppContext.getShift();
    $('scanapp-shift-cash').textContent     = formatLei(shift.cashTurnover);
    $('scanapp-shift-card').textContent     = formatLei(shift.cardTurnover);
    $('scanapp-shift-scans').textContent    = String(shift.myScans || 0);
    $('scanapp-shift-sales').textContent    = String(shift.mySales || 0);
    $('scanapp-shift-duration').textContent = shift.shiftStartTime ? formatDuration(shift.shiftStartTime) : '—';

    if (shift.shiftStartTime && !durationTimer) {
      durationTimer = setInterval(function () {
        $('scanapp-shift-duration').textContent = formatDuration(shift.shiftStartTime);
      }, 30000);
    } else if (!shift.shiftStartTime && durationTimer) {
      clearInterval(durationTimer); durationTimer = null;
    }
  }

  // ── Sheet helpers ────────────────────────────────────────────────────────
  function openSheet(title, bodyHtml) {
    $('scanapp-sheet-title').textContent = title;
    $('scanapp-sheet-body').innerHTML = bodyHtml;
    $('scanapp-sheet').classList.add('scanapp-sheet-backdrop--open');
  }
  function closeSheet() {
    $('scanapp-sheet').classList.remove('scanapp-sheet-backdrop--open');
  }

  function renderTicketTypeRow(t, mode) {
    var color = t.color || '#8B5CF6';
    var qty   = t.quantity_sold != null ? t.quantity_sold : (t.sold || 0);
    var avail = t.available != null ? t.available : ((t.quantity || 0) - qty);
    var ci    = t.checked_in || 0;
    var name  = t.name || 'Tip bilet';
    var price = Number(t.price || 0);

    var valueLine, subLine;
    if (mode === 'entered') {
      valueLine = ci + ' / ' + (qty || 0);
      subLine = qty > 0 ? Math.round((ci / qty) * 100) + '% intrați' : '—';
    } else if (mode === 'sold') {
      valueLine = qty.toLocaleString('ro-RO');
      subLine = formatLei(price) + ' · ' + (avail > 0 ? avail : 0) + ' disponibile';
    } else if (mode === 'revenue') {
      valueLine = formatLei(qty * price);
      subLine = qty + ' × ' + formatLei(price);
    } else {
      valueLine = avail.toLocaleString('ro-RO');
      subLine = qty + ' vândute';
    }

    return '<div class="scanapp-sheet__row">' +
             '<div class="scanapp-sheet__row-color" style="background:' + color + ';"></div>' +
             '<div class="scanapp-sheet__row-body">' +
               '<div class="scanapp-sheet__row-name">' + escapeHtml(name) + '</div>' +
               '<div class="scanapp-sheet__row-sub">' + escapeHtml(subLine) + '</div>' +
             '</div>' +
             '<div class="scanapp-sheet__row-value">' + escapeHtml(valueLine) + '</div>' +
           '</div>';
  }

  function openTicketTypesModal(mode) {
    var types = EventContext.getState().allTicketTypes || [];
    var titles = {
      entered: 'Defalcare check-in',
      sold: 'Defalcare bilete vândute',
      revenue: 'Defalcare venituri',
      remaining: 'Bilete rămase'
    };
    if (!types.length) {
      openSheet(titles[mode] || 'Defalcare', '<div class="scanapp-sheet__empty">Nu există tipuri de bilete pentru acest eveniment.</div>');
      return;
    }
    var html = types.map(function (t) { return renderTicketTypeRow(t, mode); }).join('');

    // Append totals row matching mobile's modal footer.
    var totalCount = types.reduce(function (n, t) { return n + (t.quantity_sold || 0); }, 0);
    var totalRevenue = types.reduce(function (n, t) { return n + ((t.quantity_sold || 0) * Number(t.price || 0)); }, 0);
    html += '<div class="scanapp-sheet__row" style="border-top: 1px solid var(--scanapp-border-medium); margin-top: 8px; padding-top: 14px;">' +
              '<div class="scanapp-sheet__row-body"><div class="scanapp-sheet__row-name">Total</div></div>' +
              '<div class="scanapp-sheet__row-value">' +
                (mode === 'revenue' ? formatLei(totalRevenue) : (totalCount.toLocaleString('ro-RO') + ' bilete')) +
              '</div>' +
            '</div>';

    openSheet(titles[mode] || 'Defalcare', html);
  }

  function openShiftSummaryModal() {
    var shift = AppContext.getShift();
    var html = '';
    html += '<div class="scanapp-sheet__row"><div class="scanapp-sheet__row-color" style="background: var(--scanapp-green);"></div>' +
            '<div class="scanapp-sheet__row-body"><div class="scanapp-sheet__row-name">Numerar de predat</div>' +
            '<div class="scanapp-sheet__row-sub">cash încasat în tură</div></div>' +
            '<div class="scanapp-sheet__row-value" style="color: var(--scanapp-green);">' + escapeHtml(formatLei(shift.cashTurnover)) + '</div></div>';
    html += '<div class="scanapp-sheet__row"><div class="scanapp-sheet__row-color" style="background: var(--scanapp-cyan);"></div>' +
            '<div class="scanapp-sheet__row-body"><div class="scanapp-sheet__row-name">Card</div>' +
            '<div class="scanapp-sheet__row-sub">venituri prin card POS</div></div>' +
            '<div class="scanapp-sheet__row-value" style="color: var(--scanapp-cyan);">' + escapeHtml(formatLei(shift.cardTurnover)) + '</div></div>';
    html += '<div class="scanapp-sheet__row"><div class="scanapp-sheet__row-color" style="background: var(--scanapp-purple);"></div>' +
            '<div class="scanapp-sheet__row-body"><div class="scanapp-sheet__row-name">Scanări</div>' +
            '<div class="scanapp-sheet__row-sub">check-in reușite</div></div>' +
            '<div class="scanapp-sheet__row-value">' + (shift.myScans || 0) + '</div></div>';
    html += '<div class="scanapp-sheet__row"><div class="scanapp-sheet__row-color" style="background: var(--scanapp-amber);"></div>' +
            '<div class="scanapp-sheet__row-body"><div class="scanapp-sheet__row-name">Vânzări</div>' +
            '<div class="scanapp-sheet__row-sub">comenzi finalizate</div></div>' +
            '<div class="scanapp-sheet__row-value">' + (shift.mySales || 0) + '</div></div>';
    if (shift.shiftStartTime) {
      html += '<div class="scanapp-sheet__row"><div class="scanapp-sheet__row-body">' +
              '<div class="scanapp-sheet__row-name">Durată tură</div></div>' +
              '<div class="scanapp-sheet__row-value">' + escapeHtml(formatDuration(shift.shiftStartTime)) + '</div></div>';
    }
    html += '<button type="button" class="scanapp-btn scanapp-btn--danger scanapp-btn--block" id="scanapp-confirm-close-shift" style="margin-top: 16px;">Confirmă închiderea</button>';
    openSheet('Sumar tură', html);
    var btn = $('scanapp-confirm-close-shift');
    if (btn) btn.addEventListener('click', function () {
      AppContext.endShift();
      closeSheet();
      ScanApp.toast('Tura a fost închisă.', 'success');
      reflectMode();
    });
  }

  // ── Mode switching ───────────────────────────────────────────────────────
  function reflectMode() {
    var role = ScanAuth.getUserRole();
    var isStaffOnly = role === 'staff' || role === 'manager';
    $('scanapp-admin-view').hidden    = isStaffOnly;
    $('scanapp-scanner-view').hidden  = !isStaffOnly;
    if (isStaffOnly) renderShift();
    else reflectReportsOnly();
  }

  function bindActions() {
    document.querySelectorAll('[data-modal]').forEach(function (el) {
      el.addEventListener('click', function () {
        var m = el.getAttribute('data-modal');
        if (m === 'entered')   openTicketTypesModal('entered');
        if (m === 'sold')      openTicketTypesModal('sold');
        if (m === 'revenue')   openTicketTypesModal('revenue');
        if (m === 'remaining') openTicketTypesModal('remaining');
      });
    });
    $('scanapp-sheet-close').addEventListener('click', closeSheet);
    $('scanapp-sheet').addEventListener('click', function (e) {
      if (e.target === $('scanapp-sheet')) closeSheet();
    });
    var a = $('scanapp-action-close-shift');
    if (a) a.addEventListener('click', openShiftSummaryModal);
    var b = $('scanapp-scanner-close-shift');
    if (b) b.addEventListener('click', openShiftSummaryModal);
  }

  // ── Init ─────────────────────────────────────────────────────────────────
  function init() {
    if (!$('scanapp-event-header')) return;
    reflectMode();
    bindActions();

    if (window.EventContext) {
      EventContext.subscribe('event-selected', function (e) {
        renderEventHeader(e.detail.event);
        reflectReportsOnly();
        renderRecentActivity();
      });
      EventContext.subscribe('stats-updated', function (e) {
        renderStats(e.detail.stats);
      });
      var s = EventContext.getState();
      if (s.selectedEvent) {
        renderEventHeader(s.selectedEvent);
        reflectReportsOnly();
      }
      if (s.eventStats) renderStats(s.eventStats);
      renderRecentActivity();
    }

    if (window.AppContext) {
      AppContext.subscribe('scan-added', function () { renderShift(); renderRecentActivity(); });
      AppContext.subscribe('sale-added', function () { renderShift(); });
      AppContext.subscribe('shift-start', function () { renderShift(); reflectMode(); });
      AppContext.subscribe('shift-end',   function () { renderShift(); reflectMode(); });
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
