/* =============================================================================
 * Scan App — pages/guest-list.js
 * -----------------------------------------------------------------------------
 * Lists participants for the selected event, mirrors the mobile app's
 * GuestListModal: filterable (all/checked/missing), searchable, with toggle
 * check-in on tap. Endpoint: GET /organizer/events/{id}/participants.
 * ============================================================================= */
(function () {
  'use strict';

  function $(id) { return document.getElementById(id); }
  function $$(sel, root) { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); }
  function escapeHtml(s) {
    if (s == null) return '';
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');
  }

  var dom = {};
  function collectDom() {
    dom.total       = $('scanapp-guest-total');
    dom.checked     = $('scanapp-guest-checked');
    dom.missing     = $('scanapp-guest-missing');
    dom.filters     = $('scanapp-guest-filters');
    dom.search      = $('scanapp-guest-search');
    dom.list        = $('scanapp-guest-list');
  }

  var participants = [];
  var filter = 'all';
  var query = '';
  var loading = false;

  function loadParticipants() {
    var ev = EventContext.getState().selectedEvent;
    if (!ev) {
      dom.list.innerHTML = '<div class="scanapp-card scanapp-card--placeholder"><p class="scanapp-card__text">Selectează un eveniment pentru a vedea lista.</p></div>';
      return;
    }
    loading = true;
    dom.list.innerHTML = '<div class="scanapp-card scanapp-card--placeholder"><p class="scanapp-card__text">Se încarcă lista de invitați…</p></div>';
    AmbiletAPI.get('/organizer/events/' + ev.id + '/participants', { per_page: 500 })
      .then(function (resp) {
        var data = (resp && resp.data) || resp || {};
        participants = data.participants || data.items || data.data || (Array.isArray(data) ? data : []);
        if (!Array.isArray(participants)) participants = [];
        renderStats();
        render();
      })
      .catch(function (err) {
        console.error('[guest-list] load failed:', err);
        dom.list.innerHTML = '<div class="scanapp-card scanapp-card--placeholder"><p class="scanapp-card__text">Nu am putut încărca lista. Reîncearcă mai târziu.</p></div>';
      })
      .finally(function () { loading = false; });
  }

  function renderStats() {
    var total = participants.length;
    var checked = participants.filter(isCheckedIn).length;
    dom.total.textContent = total.toLocaleString('ro-RO');
    dom.checked.textContent = checked.toLocaleString('ro-RO');
    dom.missing.textContent = Math.max(0, total - checked).toLocaleString('ro-RO');
  }

  function isCheckedIn(p) {
    return !!(p.checked_in_at || p.checked_in || p.status === 'used' || p.ticket_status === 'used');
  }

  function nameOf(p) {
    return p.customer_name || p.name || p.attendee_name
      || ((p.customer && (p.customer.name || ((p.customer.first_name || '') + ' ' + (p.customer.last_name || '')).trim())) || '')
      || '—';
  }
  function emailOf(p) {
    return (p.customer && p.customer.email) || p.customer_email || p.email || '';
  }
  function ticketCodeOf(p) {
    return p.ticket_code || p.code || (p.ticket && (p.ticket.code || p.ticket.barcode)) || '';
  }
  function ticketTypeNameOf(p) {
    return p.ticket_type_name || p.ticket_type || (p.ticket_type_obj && p.ticket_type_obj.name) || '';
  }

  function applyFilter(list) {
    if (filter === 'all') return list;
    if (filter === 'checked') return list.filter(isCheckedIn);
    if (filter === 'missing') return list.filter(function (p) { return !isCheckedIn(p); });
    return list;
  }

  function applySearch(list) {
    if (!query) return list;
    var q = query.toLowerCase();
    return list.filter(function (p) {
      return nameOf(p).toLowerCase().indexOf(q) !== -1
          || emailOf(p).toLowerCase().indexOf(q) !== -1
          || ticketCodeOf(p).toLowerCase().indexOf(q) !== -1;
    });
  }

  function render() {
    var list = applySearch(applyFilter(participants));
    if (!list.length) {
      dom.list.innerHTML = '<div class="scanapp-card scanapp-card--placeholder"><p class="scanapp-card__text">Nicio potrivire.</p></div>';
      return;
    }
    // Hard cap visible rows for perf (most events have hundreds; rendering
    // 10k rows freezes the main thread).
    var cap = 200;
    var capped = list.slice(0, cap);
    var rows = capped.map(function (p) {
      var name = nameOf(p);
      var sub = [ticketTypeNameOf(p), emailOf(p), ticketCodeOf(p)].filter(Boolean).join(' · ');
      var inChip = isCheckedIn(p)
        ? '<span style="font-size:11px; padding:3px 8px; border-radius:8px; background:rgba(16,185,129,0.15); color:var(--scanapp-success);">✓ intrat</span>'
        : '<span style="font-size:11px; padding:3px 8px; border-radius:8px; background:rgba(255,255,255,0.05); color:var(--scanapp-text-ter);">neintrat</span>';
      return '<div class="scanapp-sheet__row" data-code="' + escapeHtml(ticketCodeOf(p)) + '" style="padding:10px 12px; background:var(--scanapp-surface); border:1px solid var(--scanapp-border); border-radius:12px; margin-bottom:8px;">' +
               '<div class="scanapp-sheet__row-body">' +
                 '<div class="scanapp-sheet__row-name">' + escapeHtml(name) + '</div>' +
                 '<div class="scanapp-sheet__row-sub">' + escapeHtml(sub || '—') + '</div>' +
               '</div>' +
               '<div>' + inChip + '</div>' +
             '</div>';
    }).join('');
    if (list.length > cap) {
      rows += '<div class="scanapp-sheet__empty">… afișați primii ' + cap + ' din ' + list.length + '. Folosește căutarea pentru a restrânge.</div>';
    }
    dom.list.innerHTML = rows;
  }

  function bindFilters() {
    dom.filters.querySelectorAll('[data-filter]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        filter = btn.getAttribute('data-filter');
        dom.filters.querySelectorAll('[data-filter]').forEach(function (b) {
          b.classList.toggle('scanapp-stat-pill--active', b === btn);
        });
        render();
      });
    });
  }

  var searchTimer = null;
  function bindSearch() {
    dom.search.addEventListener('input', function () {
      query = (dom.search.value || '').trim();
      clearTimeout(searchTimer);
      searchTimer = setTimeout(render, 120);
    });
  }

  function init() {
    collectDom();
    if (!dom.list) return;
    bindFilters();
    bindSearch();
    EventContext.subscribe('event-selected', loadParticipants);
    var s = EventContext.getState();
    if (s.selectedEvent) loadParticipants();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
