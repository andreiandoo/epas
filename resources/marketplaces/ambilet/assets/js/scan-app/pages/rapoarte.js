/* =============================================================================
 * Scan App — pages/rapoarte.js (Reports)
 * -----------------------------------------------------------------------------
 * Read-only analytics view. Mirrors tixello-app/src/screens/ReportsScreen.js:
 *   - hero (event name + dates)
 *   - 4 top stats (check-in rate, sold, entered, revenue)
 *   - per-ticket-type performance bars (sold vs available + check-in %)
 *   - recent activity (from AppContext.recentScans / recentSales)
 *   - export participants CSV (link to existing export endpoint)
 *   - past-event picker sheet
 * ============================================================================= */
(function () {
  'use strict';

  function $(id) { return document.getElementById(id); }
  function escapeHtml(s) {
    if (s == null) return '';
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');
  }
  function pad(n) { return String(n).padStart(2,'0'); }
  function formatLei(amount) {
    var n = Number(amount) || 0;
    return n.toLocaleString('ro-RO', { maximumFractionDigits: 2 }) + ' lei';
  }
  function formatDateTime(value) {
    try {
      var d = new Date(value);
      if (isNaN(d.getTime())) return '—';
      return pad(d.getDate()) + '.' + pad(d.getMonth()+1) + '.' + d.getFullYear() + ' · ' + pad(d.getHours()) + ':' + pad(d.getMinutes());
    } catch (e) { return '—'; }
  }

  var dom = {};
  function collectDom() {
    dom.heroName        = $('scanapp-report-event-name');
    dom.heroMeta        = $('scanapp-report-event-meta');
    dom.statRate        = $('scanapp-report-rate');
    dom.statRateBar     = $('scanapp-report-rate-bar');
    dom.statSold        = $('scanapp-report-sold');
    dom.statSoldHint    = $('scanapp-report-sold-hint');
    dom.statEntered     = $('scanapp-report-entered');
    dom.statEnteredHint = $('scanapp-report-entered-hint');
    dom.statRevenue     = $('scanapp-report-revenue');
    dom.typeBars        = $('scanapp-report-types');
    dom.activity        = $('scanapp-report-activity');
    dom.exportBtn       = $('scanapp-report-export');
    dom.pickerBtn       = $('scanapp-report-pick-event');
    dom.eventSheet      = $('scanapp-event-sheet');
    dom.eventSheetBody  = $('scanapp-event-sheet-body');
    dom.eventSheetClose = $('scanapp-event-sheet-close');
  }

  // ── Render helpers ───────────────────────────────────────────────────────
  function renderHero(event) {
    if (!event) {
      dom.heroName.textContent = 'Selectează un eveniment';
      dom.heroMeta.textContent = '—';
      return;
    }
    dom.heroName.textContent = event.name || event.title || 'Eveniment';
    var meta = '';
    if (event.starts_at) meta = formatDateTime(event.starts_at);
    if (event.venue_name) meta += (meta ? ' · ' : '') + event.venue_name;
    dom.heroMeta.textContent = meta || '—';
  }

  function renderStats(stats) {
    if (!stats) return;
    var entered = stats.checked_in || 0;
    var total   = stats.total || 0;
    var sold    = stats.total_sold != null ? stats.total_sold : total;
    var capacity= stats.capacity || 0;
    var revenue = Number(stats.revenue || 0);
    var rate    = total > 0 ? (entered / total) * 100 : 0;

    dom.statRate.textContent = rate.toFixed(0) + '%';
    dom.statRateBar.style.width = Math.min(100, rate).toFixed(1) + '%';
    dom.statSold.textContent = sold.toLocaleString('ro-RO');
    dom.statSoldHint.textContent = capacity > 0 ? ('din ' + capacity.toLocaleString('ro-RO')) : 'capacitate necunoscută';
    dom.statEntered.textContent = entered.toLocaleString('ro-RO');
    dom.statEnteredHint.textContent = Math.max(0, total - entered).toLocaleString('ro-RO') + ' de scanat';
    dom.statRevenue.textContent = formatLei(revenue);
  }

  function renderTypeBars() {
    var types = EventContext.getState().allTicketTypes || [];
    if (!types.length) {
      dom.typeBars.innerHTML = '<div class="scanapp-card scanapp-card--placeholder"><p class="scanapp-card__text">Nu există date pentru tipurile de bilete.</p></div>';
      return;
    }
    dom.typeBars.innerHTML = types.map(function (t) {
      var sold = t.quantity_sold != null ? t.quantity_sold : 0;
      var ci   = t.checked_in || 0;
      var cap  = t.quantity != null ? t.quantity : (sold + (t.available || 0));
      var soldPct = cap > 0 ? (sold / cap) * 100 : 0;
      var ciPct   = sold > 0 ? (ci / sold) * 100 : 0;
      var color = t.color || '#8B5CF6';
      return '' +
        '<div class="scanapp-report-bar">' +
          '<div class="scanapp-report-bar__head">' +
            '<div class="scanapp-report-bar__name">' + escapeHtml(t.name || 'Tip bilet') + '</div>' +
            '<div class="scanapp-report-bar__value">' + sold + ' / ' + cap + '</div>' +
          '</div>' +
          '<div class="scanapp-report-bar__track">' +
            '<div class="scanapp-report-bar__fill" style="width:' + soldPct.toFixed(1) + '%; background:' + escapeHtml(color) + ';"></div>' +
          '</div>' +
          '<div class="scanapp-report-bar__head" style="margin-top:6px;">' +
            '<div class="scanapp-report-bar__value">Check-in</div>' +
            '<div class="scanapp-report-bar__value">' + ci + ' / ' + sold + ' (' + ciPct.toFixed(0) + '%)</div>' +
          '</div>' +
          '<div class="scanapp-report-bar__track">' +
            '<div class="scanapp-report-bar__fill" style="width:' + ciPct.toFixed(1) + '%; background:var(--scanapp-success);"></div>' +
          '</div>' +
        '</div>';
    }).join('');
  }

  function renderActivity() {
    var ev = EventContext.getState().selectedEvent;
    if (!ev) return;
    var scans = AppContext.getRecentScans(ev.id).slice(0, 5);
    var sales = AppContext.getRecentSales(ev.id).slice(0, 5);
    if (!scans.length && !sales.length) {
      dom.activity.innerHTML = '<p class="scanapp-card__text scanapp-card__text--muted">Nicio activitate înregistrată pe acest dispozitiv.</p>';
      return;
    }
    var html = '';
    if (scans.length) {
      html += '<div class="scanapp-card__title" style="font-size:13px;">Ultimele scanări</div>';
      html += scans.map(function (s) {
        var d = new Date(s.time);
        var when = pad(d.getHours()) + ':' + pad(d.getMinutes());
        var color = s.valid ? 'var(--scanapp-success)' : 'var(--scanapp-warning)';
        return '<div style="display:flex; justify-content:space-between; padding:6px 0; font-size:12.5px; border-bottom:1px solid var(--scanapp-border);">' +
                 '<span style="color:' + color + ';">' + (s.valid ? '✓' : '⚠') + ' ' + escapeHtml(s.customerName || s.code) + '</span>' +
                 '<span style="color:var(--scanapp-text-ter);">' + when + '</span>' +
               '</div>';
      }).join('');
    }
    if (sales.length) {
      html += '<div class="scanapp-card__title" style="font-size:13px; margin-top:12px;">Ultimele vânzări</div>';
      html += sales.map(function (s) {
        var d = new Date(s.time);
        var when = pad(d.getHours()) + ':' + pad(d.getMinutes());
        var desc = (s.items || []).map(function (i) { return i.quantity + 'x ' + i.name; }).join(', ');
        return '<div style="display:flex; justify-content:space-between; padding:6px 0; font-size:12.5px; border-bottom:1px solid var(--scanapp-border);">' +
                 '<span>' + (s.paymentMethod === 'cash' ? '💵' : '💳') + ' ' + escapeHtml(desc || 'Vânzare') + '</span>' +
                 '<span style="color:var(--scanapp-text);"><b>' + escapeHtml(formatLei(s.total)) + '</b> · <span style="color:var(--scanapp-text-ter);">' + when + '</span></span>' +
               '</div>';
      }).join('');
    }
    dom.activity.innerHTML = html;
  }

  // ── Event picker ─────────────────────────────────────────────────────────
  function openPicker() {
    var s = EventContext.getState();
    var groups = s.groupedEvents || { live:[], today:[], future:[], past:[] };
    var html = '';
    var labels = {
      live:   'Live acum',
      today:  'Azi',
      future: 'Viitoare',
      past:   'Trecute'
    };
    ['live','today','future','past'].forEach(function (key) {
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
    if (!html) html = '<div class="scanapp-sheet__empty">Nu există evenimente disponibile.</div>';
    dom.eventSheetBody.innerHTML = html;
    dom.eventSheetBody.querySelectorAll('[data-event-id]').forEach(function (row) {
      row.addEventListener('click', function () {
        var id = row.getAttribute('data-event-id');
        var ev = (EventContext.getState().events || []).find(function (e) { return String(e.id) === String(id); });
        if (ev) {
          EventContext.selectEvent(ev);
          closePicker();
        }
      });
    });
    dom.eventSheet.classList.add('scanapp-sheet-backdrop--open');
  }
  function closePicker() {
    dom.eventSheet.classList.remove('scanapp-sheet-backdrop--open');
  }

  // ── Export ───────────────────────────────────────────────────────────────
  function exportCsv() {
    var ev = EventContext.getState().selectedEvent;
    if (!ev) { ScanApp.toast('Selectează un eveniment.', 'warning'); return; }
    ScanApp.toast('Pornesc exportul…', 'success');
    AmbiletAPI.get('/organizer/events/' + ev.id + '/participants/export').then(function (resp) {
      var url = (resp && resp.data && (resp.data.url || resp.data.download_url)) || null;
      if (url) {
        window.open(url, '_blank', 'noopener');
        return;
      }
      // If the endpoint returned CSV inline, attempt to read it.
      if (typeof resp === 'string') {
        triggerCsvDownload('participanti-' + ev.id + '.csv', resp);
        return;
      }
      ScanApp.toast('Exportul a fost solicitat. Vei primi un email când e gata.', 'success');
    }).catch(function (err) {
      console.error('[rapoarte] export error:', err);
      ScanApp.toast('Exportul nu s-a putut realiza acum.', 'danger');
    });
  }

  function triggerCsvDownload(filename, csv) {
    var blob = new Blob([csv], { type: 'text/csv;charset=utf-8' });
    var a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = filename;
    document.body.appendChild(a); a.click();
    setTimeout(function () { document.body.removeChild(a); URL.revokeObjectURL(a.href); }, 0);
  }

  // ── Wire up ──────────────────────────────────────────────────────────────
  function init() {
    collectDom();
    if (!dom.heroName) return;

    dom.exportBtn.addEventListener('click', exportCsv);
    dom.pickerBtn.addEventListener('click', openPicker);
    dom.eventSheetClose.addEventListener('click', closePicker);
    dom.eventSheet.addEventListener('click', function (e) { if (e.target === dom.eventSheet) closePicker(); });

    if (window.EventContext) {
      EventContext.subscribe('event-selected', function (e) {
        renderHero(e.detail.event);
        renderTypeBars();
        renderActivity();
      });
      EventContext.subscribe('stats-updated', function (e) {
        renderStats(e.detail.stats);
      });
      EventContext.subscribe('ticket-types-updated', function () {
        renderTypeBars();
      });

      var s = EventContext.getState();
      if (s.selectedEvent) { renderHero(s.selectedEvent); renderTypeBars(); renderActivity(); }
      if (s.eventStats)    renderStats(s.eventStats);
    }
    if (window.AppContext) {
      AppContext.subscribe('scan-added', renderActivity);
      AppContext.subscribe('sale-added', renderActivity);
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
