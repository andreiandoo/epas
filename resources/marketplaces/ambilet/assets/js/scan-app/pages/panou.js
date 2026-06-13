/* =============================================================================
 * Scan App — pages/panou.js (Dashboard)
 * -----------------------------------------------------------------------------
 * Dual-mode dashboard:
 *   - admin/owner: stat cards (Intrați / Sold / Venituri / Rămase), quick action
 *     grid (Scan, Sell, Guest list, Staff), close shift button, modals for
 *     deeper breakdowns.
 *   - staff/manager: personal shift turnover + scans/sales counts + shortcuts.
 *
 * Mirrors the dual layout from tixello-app/src/screens/DashboardScreen.js.
 *
 * Stats come from EventContext (auto-polled every 30s + Reverb push later).
 * Shift state comes from AppContext (in-memory + sessionStorage).
 *
 * Modals are bottom-sheet style, all reusing #scanapp-sheet.
 * ============================================================================= */
(function () {
  'use strict';

  function $(id) { return document.getElementById(id); }

  var dom = {};
  function collectDom() {
    dom.hero          = $('scanapp-hero');
    dom.heroName      = $('scanapp-hero-name');
    dom.heroMeta      = $('scanapp-hero-meta');
    dom.adminView     = $('scanapp-admin-view');
    dom.scannerView   = $('scanapp-scanner-view');
    dom.statEntered   = $('scanapp-stat-entered');
    dom.statEnteredHint = $('scanapp-stat-entered-hint');
    dom.statSold      = $('scanapp-stat-sold');
    dom.statRevenue   = $('scanapp-stat-revenue');
    dom.statRemaining = $('scanapp-stat-remaining');
    dom.enteredBar    = $('scanapp-entered-bar');
    dom.shiftCash     = $('scanapp-shift-cash');
    dom.shiftCard     = $('scanapp-shift-card');
    dom.shiftScans    = $('scanapp-shift-scans');
    dom.shiftSales    = $('scanapp-shift-sales');
    dom.shiftStart    = $('scanapp-shift-start');
    dom.shiftDuration = $('scanapp-shift-duration');
    dom.sheet         = $('scanapp-sheet');
    dom.sheetTitle    = $('scanapp-sheet-title');
    dom.sheetBody     = $('scanapp-sheet-body');
    dom.sheetClose    = $('scanapp-sheet-close');
  }

  // ── Format helpers ───────────────────────────────────────────────────────
  function formatLei(amount) {
    var n = Number(amount) || 0;
    return n.toLocaleString('ro-RO', { maximumFractionDigits: 2 }) + ' lei';
  }
  function pad(n) { return String(n).padStart(2, '0'); }
  function formatDateTime(value) {
    try {
      var d = new Date(value);
      if (isNaN(d.getTime())) return '—';
      return pad(d.getDate()) + '.' + pad(d.getMonth() + 1) + '.' + d.getFullYear() + ' · ' + pad(d.getHours()) + ':' + pad(d.getMinutes());
    } catch (e) { return '—'; }
  }
  function formatTime(value) {
    try {
      var d = new Date(value);
      if (isNaN(d.getTime())) return '—';
      return pad(d.getHours()) + ':' + pad(d.getMinutes());
    } catch (e) { return '—'; }
  }
  function formatDuration(startMs) {
    if (!startMs) return '—';
    var ms = Date.now() - Number(startMs);
    if (ms < 0) return '—';
    var s = Math.floor(ms / 1000);
    var h = Math.floor(s / 3600); s = s - h * 3600;
    var m = Math.floor(s / 60);
    return (h > 0 ? h + 'h ' : '') + m + 'min';
  }
  function escapeHtml(s) {
    if (s == null) return '';
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');
  }

  // ── Hero renderer ────────────────────────────────────────────────────────
  function renderHero(event) {
    if (!event) {
      dom.heroName.textContent = 'Selectează un eveniment';
      dom.heroMeta.textContent = '—';
      return;
    }
    dom.heroName.textContent = event.name || event.title || 'Eveniment';
    var meta = '';
    if (event.starts_at) {
      meta = formatDateTime(event.starts_at);
    } else if (event.start_date) {
      meta = event.start_date + (event.start_time ? ' · ' + event.start_time : '');
    }
    if (event.venue_name) meta += (meta ? ' · ' : '') + event.venue_name;
    if (event.timeCategory) {
      var labels = { live: 'LIVE', today: 'AZI', future: 'VIITOR', past: 'TRECUT' };
      meta = '[' + (labels[event.timeCategory] || event.timeCategory.toUpperCase()) + '] ' + meta;
    }
    dom.heroMeta.textContent = meta || '—';
  }

  // ── Stats renderer ───────────────────────────────────────────────────────
  function renderStats(stats) {
    if (!stats) return;
    var entered = stats.checked_in || 0;
    var total   = stats.total       || 0;
    var sold    = stats.total_sold  != null ? stats.total_sold : total;
    var revenue = Number(stats.revenue || 0);
    var capacity= stats.capacity    || 0;
    var remaining = Math.max(0, (capacity > 0 ? capacity : total) - sold);
    var rate    = total > 0 ? (entered / total) * 100 : 0;

    dom.statEntered.textContent = entered.toLocaleString('ro-RO');
    dom.statEnteredHint.textContent = 'din ' + total.toLocaleString('ro-RO');
    dom.statSold.textContent = sold.toLocaleString('ro-RO');
    dom.statRevenue.textContent = formatLei(revenue);
    dom.statRemaining.textContent = remaining.toLocaleString('ro-RO');
    dom.enteredBar.style.width = Math.min(100, rate).toFixed(1) + '%';
  }

  // ── Shift renderer (for scanner view) ────────────────────────────────────
  var durationTimer = null;
  function renderShift(shift) {
    if (!shift) shift = AppContext.getShift();
    dom.shiftCash.textContent  = formatLei(shift.cashTurnover);
    dom.shiftCard.textContent  = formatLei(shift.cardTurnover);
    dom.shiftScans.textContent = String(shift.myScans || 0);
    dom.shiftSales.textContent = String(shift.mySales || 0);
    dom.shiftStart.textContent = shift.shiftStartTime ? formatTime(shift.shiftStartTime) : '—';
    dom.shiftDuration.textContent = shift.shiftStartTime ? formatDuration(shift.shiftStartTime) : '—';

    if (shift.shiftStartTime && !durationTimer) {
      durationTimer = setInterval(function () {
        dom.shiftDuration.textContent = formatDuration(shift.shiftStartTime);
      }, 30000);
    } else if (!shift.shiftStartTime && durationTimer) {
      clearInterval(durationTimer); durationTimer = null;
    }
  }

  // ── Sheet helpers ────────────────────────────────────────────────────────
  function openSheet(title, bodyHtml) {
    dom.sheetTitle.textContent = title;
    dom.sheetBody.innerHTML = bodyHtml;
    dom.sheet.classList.add('scanapp-sheet-backdrop--open');
  }
  function closeSheet() {
    dom.sheet.classList.remove('scanapp-sheet-backdrop--open');
  }

  // ── Modals ───────────────────────────────────────────────────────────────
  function renderTicketTypeRow(t, mode) {
    var color = t.color || '#8B5CF6';
    var qty   = t.quantity_sold != null ? t.quantity_sold : (t.sold || 0);
    var avail = t.available != null ? t.available : ((t.quantity || 0) - qty);
    var ci    = t.checked_in || 0;
    var name  = t.name || 'Tip bilet';

    var valueLine, subLine;
    if (mode === 'entered') {
      valueLine = ci + ' / ' + (qty || 0);
      subLine = qty > 0 ? Math.round((ci / qty) * 100) + '% intrați' : '—';
    } else if (mode === 'sold') {
      valueLine = qty.toLocaleString('ro-RO');
      subLine = (avail > 0 ? avail : 0) + ' disponibile';
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
    var s = EventContext.getState();
    var types = s.allTicketTypes || [];
    var titles = { entered: 'Defalcare check-in', sold: 'Defalcare bilete vândute', remaining: 'Bilete rămase' };
    if (!types.length) {
      openSheet(titles[mode] || 'Defalcare', '<div class="scanapp-sheet__empty">Nu există tipuri de bilete pentru acest eveniment.</div>');
      return;
    }
    var html = types.map(function (t) { return renderTicketTypeRow(t, mode); }).join('');
    openSheet(titles[mode] || 'Defalcare', html);
  }

  function openRevenueModal() {
    var s = EventContext.getState();
    var ev = s.selectedEvent;
    if (!ev) { openSheet('Venituri', '<div class="scanapp-sheet__empty">Selectează un eveniment.</div>'); return; }

    openSheet('Venituri — încărcare…', '<div class="scanapp-sheet__empty">Se încarcă defalcarea…</div>');

    var paths = ['/organizer/events/' + ev.id + '/sales-breakdown', '/events/' + ev.id + '/sales-breakdown'];
    function tryNext(i) {
      if (i >= paths.length) return Promise.reject(new Error('No sales-breakdown endpoint responded'));
      return AmbiletAPI.get(paths[i]).catch(function () { return tryNext(i + 1); });
    }
    tryNext(0)
      .then(function (resp) {
        var d = (resp && resp.data) || resp || {};
        var online = Number(d.online_revenue || d.online || 0);
        var pos    = Number(d.pos_revenue    || d.pos    || 0);
        var total  = Number(d.total_revenue  || d.total  || (online + pos));
        var html = '' +
          '<div class="scanapp-sheet__row"><div class="scanapp-sheet__row-color" style="background:#10B981;"></div>' +
          '<div class="scanapp-sheet__row-body"><div class="scanapp-sheet__row-name">Vânzări online</div>' +
          '<div class="scanapp-sheet__row-sub">prin site / widget</div></div>' +
          '<div class="scanapp-sheet__row-value">' + escapeHtml(formatLei(online)) + '</div></div>' +
          '<div class="scanapp-sheet__row"><div class="scanapp-sheet__row-color" style="background:#06B6D4;"></div>' +
          '<div class="scanapp-sheet__row-body"><div class="scanapp-sheet__row-name">Vânzări POS</div>' +
          '<div class="scanapp-sheet__row-sub">la fața locului (app + web)</div></div>' +
          '<div class="scanapp-sheet__row-value">' + escapeHtml(formatLei(pos)) + '</div></div>' +
          '<div class="scanapp-sheet__row"><div class="scanapp-sheet__row-color" style="background:#8B5CF6;"></div>' +
          '<div class="scanapp-sheet__row-body"><div class="scanapp-sheet__row-name">Total</div>' +
          '<div class="scanapp-sheet__row-sub">brut</div></div>' +
          '<div class="scanapp-sheet__row-value">' + escapeHtml(formatLei(total)) + '</div></div>';
        openSheet('Venituri — online vs POS', html);
      })
      .catch(function () {
        // Fallback: use the aggregate revenue from EventContext.
        var s = EventContext.getState();
        var revenue = Number((s.eventStats && s.eventStats.revenue) || 0);
        var html = '<div class="scanapp-sheet__row">' +
                     '<div class="scanapp-sheet__row-color" style="background:#8B5CF6;"></div>' +
                     '<div class="scanapp-sheet__row-body">' +
                       '<div class="scanapp-sheet__row-name">Venituri totale</div>' +
                       '<div class="scanapp-sheet__row-sub">defalcarea online vs POS nu e disponibilă pentru acest eveniment</div>' +
                     '</div>' +
                     '<div class="scanapp-sheet__row-value">' + escapeHtml(formatLei(revenue)) + '</div>' +
                   '</div>';
        openSheet('Venituri', html);
      });
  }

  function openShiftSummaryModal() {
    var shift = AppContext.getShift();
    var ev = EventContext.getState().selectedEvent;
    var recentScans = ev ? AppContext.getRecentScans(ev.id).slice(0, 10) : [];
    var recentSales = ev ? AppContext.getRecentSales(ev.id).slice(0, 10) : [];

    var html = '';
    html += '<div class="scanapp-sheet__row"><div class="scanapp-sheet__row-color" style="background:#10B981;"></div>' +
            '<div class="scanapp-sheet__row-body"><div class="scanapp-sheet__row-name">Numerar de predat</div>' +
            '<div class="scanapp-sheet__row-sub">cash din tură</div></div>' +
            '<div class="scanapp-sheet__row-value">' + escapeHtml(formatLei(shift.cashTurnover)) + '</div></div>';
    html += '<div class="scanapp-sheet__row"><div class="scanapp-sheet__row-color" style="background:#06B6D4;"></div>' +
            '<div class="scanapp-sheet__row-body"><div class="scanapp-sheet__row-name">Card</div>' +
            '<div class="scanapp-sheet__row-sub">venituri prin card</div></div>' +
            '<div class="scanapp-sheet__row-value">' + escapeHtml(formatLei(shift.cardTurnover)) + '</div></div>';
    html += '<div class="scanapp-sheet__row"><div class="scanapp-sheet__row-color" style="background:#8B5CF6;"></div>' +
            '<div class="scanapp-sheet__row-body"><div class="scanapp-sheet__row-name">Scanări reușite</div>' +
            '<div class="scanapp-sheet__row-sub">check-in OK</div></div>' +
            '<div class="scanapp-sheet__row-value">' + (shift.myScans || 0) + '</div></div>';
    html += '<div class="scanapp-sheet__row"><div class="scanapp-sheet__row-color" style="background:#F59E0B;"></div>' +
            '<div class="scanapp-sheet__row-body"><div class="scanapp-sheet__row-name">Vânzări</div>' +
            '<div class="scanapp-sheet__row-sub">comenzi finalizate</div></div>' +
            '<div class="scanapp-sheet__row-value">' + (shift.mySales || 0) + '</div></div>';

    if (shift.shiftStartTime) {
      html += '<div class="scanapp-sheet__row"><div class="scanapp-sheet__row-body">' +
              '<div class="scanapp-sheet__row-name">Durată tură</div>' +
              '<div class="scanapp-sheet__row-sub">început ' + formatTime(shift.shiftStartTime) + '</div></div>' +
              '<div class="scanapp-sheet__row-value">' + escapeHtml(formatDuration(shift.shiftStartTime)) + '</div></div>';
    }

    html += '<div class="scanapp-section-title" style="margin-top:14px;">Confirmă închiderea</div>';
    html += '<button type="button" class="scanapp-btn scanapp-btn--danger scanapp-btn--block" id="scanapp-confirm-close-shift">Închide tura</button>';

    openSheet('Sumar tură', html);
    var confirm = $('scanapp-confirm-close-shift');
    if (confirm) confirm.addEventListener('click', function () {
      AppContext.endShift();
      closeSheet();
      ScanApp.toast('Tura a fost închisă.', 'success');
      reflectMode();
    });
  }

  // ── Mode switching (admin vs scanner view) ───────────────────────────────
  function reflectMode() {
    var role = ScanAuth.getUserRole();
    var isStaff = role === 'staff' || role === 'manager';
    dom.adminView.hidden   = isStaff;
    dom.scannerView.hidden = !isStaff;
    if (isStaff) renderShift();
  }

  // ── Wire up ──────────────────────────────────────────────────────────────
  function bindActions() {
    document.querySelectorAll('[data-modal]').forEach(function (el) {
      el.addEventListener('click', function () {
        var modal = el.getAttribute('data-modal');
        if (modal === 'entered')   openTicketTypesModal('entered');
        if (modal === 'sold')      openTicketTypesModal('sold');
        if (modal === 'remaining') openTicketTypesModal('remaining');
        if (modal === 'revenue')   openRevenueModal();
      });
    });

    dom.sheetClose.addEventListener('click', closeSheet);
    dom.sheet.addEventListener('click', function (e) { if (e.target === dom.sheet) closeSheet(); });

    var closeShiftAdmin = $('scanapp-action-close-shift');
    if (closeShiftAdmin) closeShiftAdmin.addEventListener('click', openShiftSummaryModal);
    var closeShiftScanner = $('scanapp-scanner-close-shift');
    if (closeShiftScanner) closeShiftScanner.addEventListener('click', openShiftSummaryModal);
  }

  function init() {
    collectDom();
    if (!dom.hero) return;
    reflectMode();
    bindActions();

    if (window.EventContext) {
      EventContext.subscribe('event-selected', function (e) {
        renderHero(e.detail.event);
      });
      EventContext.subscribe('stats-updated', function (e) {
        renderStats(e.detail.stats);
      });
      // Initial
      var s = EventContext.getState();
      if (s.selectedEvent) renderHero(s.selectedEvent);
      if (s.eventStats)    renderStats(s.eventStats);
    }

    if (window.AppContext) {
      AppContext.subscribe('scan-added', function () { renderShift(); });
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
