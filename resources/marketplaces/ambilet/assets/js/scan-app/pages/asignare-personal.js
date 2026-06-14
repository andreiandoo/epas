/* =============================================================================
 * Scan App — pages/asignare-personal.js (Staff Assignment)
 * -----------------------------------------------------------------------------
 * Maps team members to gates via GET /organizer/team + POST /organizer/team/update.
 * ============================================================================= */
(function () {
  'use strict';

  function $(id) { return document.getElementById(id); }
  function escapeHtml(s) {
    if (s == null) return '';
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');
  }

  var dom = {};
  function collectDom() {
    dom.list           = $('scanapp-staff-list');
    dom.sheet          = $('scanapp-staff-sheet');
    dom.sheetTitle     = $('scanapp-staff-sheet-title');
    dom.sheetMember    = $('scanapp-staff-sheet-member');
    dom.sheetGates     = $('scanapp-staff-sheet-gates');
    dom.sheetClose     = $('scanapp-staff-sheet-close');
  }

  var members = [];
  var gates = [];
  var editingMember = null;

  function getVenueId() {
    var ev = EventContext.getState().selectedEvent;
    if (!ev) return null;
    return ev.venue_id || (ev.venue && ev.venue.id) || null;
  }

  function loadAll() {
    var venueId = getVenueId();
    dom.list.innerHTML = '<div class="scanapp-card scanapp-card--placeholder"><p class="scanapp-card__text">Se încarcă echipa…</p></div>';
    var promises = [ScanAPI.get('/organizer/team')];
    if (venueId) promises.push(ScanAPI.get('/organizer/venues/' + venueId + '/gates'));
    Promise.all(promises).then(function (results) {
      var teamResp = results[0];
      var gatesResp = results[1];
      var teamData = (teamResp && teamResp.data) || teamResp || [];
      members = Array.isArray(teamData) ? teamData : (teamData.team || teamData.members || teamData.items || []);
      var gatesData = (gatesResp && gatesResp.data) || gatesResp || [];
      var rawGates = Array.isArray(gatesData) ? gatesData : (gatesData.gates || gatesData.items || []);
      // Defensive client-side filter: keep only gates tied to the CURRENT event's
      // venue. The backend endpoint URL already scopes by venueId, but for
      // some organizers we've seen gates from other venues bleeding in
      // (cross-venue contamination in legacy data). This guarantees the
      // operator never sees a gate from a different location.
      if (venueId) {
        gates = rawGates.filter(function (g) {
          var gv = g.venue_id != null ? g.venue_id : (g.venue && g.venue.id);
          return gv == null || Number(gv) === Number(venueId);
        });
      } else {
        gates = rawGates;
      }
      renderMembers();
    }).catch(function (err) {
      console.error('[asignare] load failed:', err);
      dom.list.innerHTML = '<div class="scanapp-card scanapp-card--placeholder"><p class="scanapp-card__text">Nu am putut încărca echipa. Reîncearcă mai târziu.</p></div>';
    });
  }

  function gateName(gateId) {
    var g = gates.find(function (x) { return Number(x.id) === Number(gateId); });
    return g ? (g.name || ('#' + g.id)) : null;
  }

  function renderMembers() {
    if (!members.length) {
      dom.list.innerHTML = '<div class="scanapp-card scanapp-card--placeholder"><p class="scanapp-card__text">Nu există membri în echipă.</p></div>';
      return;
    }
    dom.list.innerHTML = members.map(function (m) {
      var name = m.name || m.email || 'Membru';
      var role = m.role || 'staff';
      var gid = m.assigned_gate || m.gate_id || null;
      var gn = gid ? gateName(gid) : null;
      var sub = role + (gn ? ' · poartă: ' + gn : ' · fără poartă asignată');
      return '<div class="scanapp-sheet__row" data-member-id="' + escapeHtml(m.id) + '" style="cursor:pointer; padding: 14px 12px; background: var(--scanapp-surface); border: 1px solid var(--scanapp-border); border-radius: 12px; margin-bottom: 8px;">' +
               '<div class="scanapp-sheet__row-body">' +
                 '<div class="scanapp-sheet__row-name">' + escapeHtml(name) + '</div>' +
                 '<div class="scanapp-sheet__row-sub">' + escapeHtml(sub) + '</div>' +
               '</div>' +
               '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color: var(--scanapp-text-ter); flex-shrink: 0;"><polyline points="9 18 15 12 9 6"/></svg>' +
             '</div>';
    }).join('');
    dom.list.querySelectorAll('[data-member-id]').forEach(function (row) {
      row.addEventListener('click', function () {
        var id = row.getAttribute('data-member-id');
        var m = members.find(function (x) { return String(x.id) === String(id); });
        if (m) openSheet(m);
      });
    });
  }

  function openSheet(member) {
    editingMember = member;
    dom.sheetTitle.textContent = 'Asignează poartă';
    dom.sheetMember.textContent = (member.name || member.email || 'Membru') + ' · ' + (member.role || 'staff');
    var currentGate = member.assigned_gate || member.gate_id || null;

    var html = '<div class="scanapp-sheet__row" data-gate-pick="" style="cursor:pointer;">' +
                 '<div class="scanapp-sheet__row-body"><div class="scanapp-sheet__row-name">Fără poartă</div>' +
                 '<div class="scanapp-sheet__row-sub">' + (currentGate ? '' : '✓ selectat') + '</div></div>' +
               '</div>';
    html += gates.map(function (g) {
      var sel = Number(currentGate) === Number(g.id);
      return '<div class="scanapp-sheet__row" data-gate-pick="' + escapeHtml(g.id) + '" style="cursor:pointer;">' +
               '<div class="scanapp-sheet__row-body"><div class="scanapp-sheet__row-name">' + escapeHtml(g.name || 'Poartă') + (sel ? ' ✓' : '') + '</div>' +
               '<div class="scanapp-sheet__row-sub">' + ((g.capacity || g.max_capacity) ? ('Capacitate: ' + (g.capacity || g.max_capacity)) : 'Fără limită capacitate') + '</div></div>' +
             '</div>';
    }).join('');

    dom.sheetGates.innerHTML = html;
    dom.sheetGates.querySelectorAll('[data-gate-pick]').forEach(function (row) {
      row.addEventListener('click', function () {
        var gateId = row.getAttribute('data-gate-pick');
        assignGate(gateId || null);
      });
    });
    dom.sheet.classList.add('scanapp-sheet-backdrop--open');
  }
  function closeSheet() {
    dom.sheet.classList.remove('scanapp-sheet-backdrop--open');
    editingMember = null;
  }

  function assignGate(gateId) {
    if (!editingMember) return;
    var payload = {
      member_id:  editingMember.id,
      id:         editingMember.id,
      assigned_gate: gateId ? Number(gateId) : null,
      gate_id:    gateId ? Number(gateId) : null
    };
    ScanAPI.post('/organizer/team/update', payload).then(function () {
      editingMember.assigned_gate = gateId ? Number(gateId) : null;
      editingMember.gate_id = gateId ? Number(gateId) : null;
      closeSheet();
      renderMembers();
      ScanApp.toast('Asignare salvată.', 'success');
    }).catch(function (err) {
      console.error('[asignare] update failed:', err);
      ScanApp.toast('Salvarea a eșuat.', 'danger');
    });
  }

  function init() {
    collectDom();
    if (!dom.list) return;
    if (!ScanAuth.isAdmin()) {
      dom.list.innerHTML = '<div class="scanapp-card scanapp-card--placeholder"><p class="scanapp-card__text">Doar administratorii pot asigna personal la porți.</p></div>';
      return;
    }
    dom.sheetClose.addEventListener('click', closeSheet);
    dom.sheet.addEventListener('click', function (e) { if (e.target === dom.sheet) closeSheet(); });

    if (window.EventContext) {
      EventContext.subscribe('event-selected', loadAll);
      var s = EventContext.getState();
      if (s.selectedEvent) loadAll();
      else loadAll(); // try anyway — team endpoint might still return all members
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
