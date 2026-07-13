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

          // Countdown badge — synced with the authoritative timeCategory
          // (which respects is_cancelled / is_postponed / past dates) so we
          // never end up displaying both 'LIVE ACUM' and the
          // 'eveniment s-a încheiat' banner on the same page.
          var cat = event.timeCategory;
          if (cat === 'past') {
            countdown.hidden = false;
            countdownT.textContent = 'ÎNCHEIAT';
            countdown.style.background = 'var(--scanapp-amber-bg)';
            countdown.style.borderColor = 'var(--scanapp-amber-border)';
            countdown.style.color = 'var(--scanapp-amber)';
          } else if (cat === 'live') {
            countdown.hidden = false;
            countdownT.textContent = 'LIVE ACUM';
            countdown.style.background = 'var(--scanapp-green-bg)';
            countdown.style.borderColor = 'var(--scanapp-green-border)';
            countdown.style.color = 'var(--scanapp-green)';
          } else if (cat === 'today') {
            countdown.hidden = false;
            countdownT.textContent = 'AZI';
            countdown.style.background = 'var(--scanapp-purple-bg)';
            countdown.style.borderColor = 'var(--scanapp-purple-border)';
            countdown.style.color = 'var(--scanapp-purple)';
          } else if (cat === 'future') {
            var now = new Date();
            var msDiff = d.getTime() - now.getTime();
            var days = Math.floor(msDiff / 86400000);
            var hours = Math.floor((msDiff % 86400000) / 3600000);
            countdown.hidden = false;
            if (days >= 1) countdownT.textContent = 'ÎN ' + days + ' ' + (days === 1 ? 'ZI' : 'ZILE');
            else if (hours >= 1) countdownT.textContent = 'ÎN ' + hours + 'H';
            else countdownT.textContent = 'CURÂND';
            countdown.style.background = 'var(--scanapp-purple-bg)';
            countdown.style.borderColor = 'var(--scanapp-purple-border)';
            countdown.style.color = 'var(--scanapp-purple)';
          } else {
            countdown.hidden = true;
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

    // Capacitate section — visible only when the event carries a
    // capacity value (unlimited events keep it hidden entirely so we
    // don't render a 0/0 progress bar).
    var capSection = $('scanapp-capacity-section');
    if (capSection) {
      if (capacity > 0) {
        capSection.hidden = false;
        var capPct = Math.min(100, capacityPct);
        $('scanapp-cap-pct').textContent   = Math.round(capPct) + '%';
        $('scanapp-cap-fill').style.width  = capPct.toFixed(1) + '%';
        $('scanapp-cap-sold').textContent  = sold.toLocaleString('ro-RO');
        $('scanapp-cap-total').textContent = capacity.toLocaleString('ro-RO');
      } else {
        capSection.hidden = true;
      }
    }

    // Online vs. la ușă — hidden until we have at least one sale from
    // one of the two channels; keeps the dashboard tight on brand-new
    // events before the first ticket goes through.
    renderOnlineDoor(stats);
  }

  // ── Online vs. la ușă split + click-to-expand per ticket type ────────────
  //
  // State kept on a module local so a re-render (e.g. Reverb push) doesn't
  // collapse the expansion the user was just looking at. Toggles on click:
  //   click 'online' → shows online-only ticket types
  //   click 'door'   → shows door-only ticket types
  //   click same segment again → collapses
  var _odExpanded = null; // 'online' | 'door' | null
  var _odSourceData = { online: [], door: [] };

  function renderOnlineDoor(stats) {
    var section = $('scanapp-online-door-section');
    if (!section) return;
    var online = Number(stats.online_count) || 0;
    var door   = Number(stats.door_count)   || 0;
    var sum    = online + door;
    if (sum === 0) {
      section.hidden = true;
      _odExpanded = null;
      $('scanapp-od-expand').hidden = true;
      return;
    }
    section.hidden = false;
    _odSourceData = (stats.by_source_and_type && typeof stats.by_source_and_type === 'object')
      ? stats.by_source_and_type
      : { online: [], door: [] };
    if (!Array.isArray(_odSourceData.online)) _odSourceData.online = [];
    if (!Array.isArray(_odSourceData.door))   _odSourceData.door   = [];

    var onlinePct = Math.round((online / sum) * 100);
    var doorPct   = Math.round((door   / sum) * 100);

    var onSeg = $('scanapp-od-online');
    var drSeg = $('scanapp-od-door');
    onSeg.hidden = online === 0;
    drSeg.hidden = door === 0;
    // flex-grow proportional to raw counts so a 90/10 split reads
    // visually as 90/10 without needing extra math on the min-widths.
    onSeg.style.flexGrow = String(online);
    drSeg.style.flexGrow = String(door);
    $('scanapp-online-cnt').textContent = online.toLocaleString('ro-RO');
    $('scanapp-online-pct').textContent = onlinePct;
    $('scanapp-door-cnt').textContent   = door.toLocaleString('ro-RO');
    $('scanapp-door-pct').textContent   = doorPct;

    renderOdExpansion();
  }

  function renderOdExpansion() {
    var box    = $('scanapp-od-expand');
    var title  = $('scanapp-od-expand-title');
    var list   = $('scanapp-od-expand-list');
    var onSeg  = $('scanapp-od-online');
    var drSeg  = $('scanapp-od-door');
    if (onSeg) onSeg.classList.toggle('is-active', _odExpanded === 'online');
    if (drSeg) drSeg.classList.toggle('is-active', _odExpanded === 'door');

    if (!_odExpanded) { box.hidden = true; return; }
    box.hidden = false;
    title.textContent = 'Tipuri de bilete — ' + (_odExpanded === 'online' ? 'online' : 'la ușă');

    var rows = _odSourceData[_odExpanded] || [];
    if (!rows.length) {
      list.innerHTML = '<div class="scanapp-od-empty">Niciun bilet încă pe acest canal.</div>';
      return;
    }
    var maxSold = rows.reduce(function (m, r) { return Math.max(m, Number(r.sold_count) || 0); }, 0);
    var defaultColor = _odExpanded === 'online' ? '#A51C30' : '#F5C7CE';
    list.innerHTML = rows.map(function (r) {
      var sold = Number(r.sold_count) || 0;
      var w = maxSold > 0 ? Math.max(4, (sold / maxSold) * 100) : 0;
      var color = r.color || defaultColor;
      var name = String(r.name || '').replace(/[<>&"]/g, function (ch) {
        return ({ '<': '&lt;', '>': '&gt;', '&': '&amp;', '"': '&quot;' })[ch];
      });
      return ''
        + '<div class="scanapp-od-row">'
        +   '<div class="scanapp-od-row__head">'
        +     '<span class="scanapp-od-row__label">' + name + '</span>'
        +     '<span class="scanapp-od-row__value">' + sold.toLocaleString('ro-RO') + '</span>'
        +   '</div>'
        +   '<div class="scanapp-od-row__bar-bg">'
        +     '<div class="scanapp-od-row__bar-fill" style="width:' + w.toFixed(1) + '%; background:' + color + ';"></div>'
        +   '</div>'
        + '</div>';
    }).join('');
  }

  function bindOnlineDoorToggles() {
    var onSeg = $('scanapp-od-online');
    var drSeg = $('scanapp-od-door');
    if (onSeg && !onSeg._boundOd) {
      onSeg.addEventListener('click', function () {
        _odExpanded = _odExpanded === 'online' ? null : 'online';
        renderOdExpansion();
      });
      onSeg._boundOd = true;
    }
    if (drSeg && !drSeg._boundOd) {
      drSeg.addEventListener('click', function () {
        _odExpanded = _odExpanded === 'door' ? null : 'door';
        renderOdExpansion();
      });
      drSeg._boundOd = true;
    }
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
    bindOnlineDoorToggles();

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
