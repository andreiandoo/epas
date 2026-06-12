/* =============================================================================
 * Scan App — pages/porti.js (Gate manager)
 * -----------------------------------------------------------------------------
 * CRUD UI over /organizer/venues/{venueId}/gates. Venue is resolved from the
 * currently selected event's venue_id.
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
    dom.list        = $('scanapp-gates-list');
    dom.addBtn      = $('scanapp-gate-add');
    dom.sheet       = $('scanapp-gate-sheet');
    dom.title       = $('scanapp-gate-sheet-title');
    dom.nameInput   = $('scanapp-gate-name');
    dom.capInput    = $('scanapp-gate-capacity');
    dom.cancelBtn   = $('scanapp-gate-cancel');
    dom.saveBtn     = $('scanapp-gate-save');
    dom.deleteBtn   = $('scanapp-gate-delete');
  }

  var currentVenueId = null;
  var gates = [];
  var editingGate = null;

  function getVenueId() {
    var ev = EventContext.getState().selectedEvent;
    if (!ev) return null;
    return ev.venue_id || (ev.venue && ev.venue.id) || null;
  }

  function loadGates() {
    var venueId = getVenueId();
    if (!venueId) {
      currentVenueId = null;
      dom.list.innerHTML = '<div class="scanapp-card scanapp-card--placeholder"><p class="scanapp-card__text">Selectează un eveniment cu o locație configurată pentru a vedea porțile.</p></div>';
      dom.addBtn.disabled = true;
      return;
    }
    currentVenueId = venueId;
    dom.addBtn.disabled = false;
    dom.list.innerHTML = '<div class="scanapp-card scanapp-card--placeholder"><p class="scanapp-card__text">Se încarcă porțile…</p></div>';
    AmbiletAPI.get('/organizer/venues/' + venueId + '/gates').then(function (resp) {
      var data = (resp && resp.data) || resp || [];
      gates = Array.isArray(data) ? data : (data.gates || data.items || []);
      renderGates();
    }).catch(function (err) {
      console.error('[porti] load failed:', err);
      dom.list.innerHTML = '<div class="scanapp-card scanapp-card--placeholder"><p class="scanapp-card__text">Nu am putut încărca porțile. Reîncearcă mai târziu.</p></div>';
    });
  }

  function renderGates() {
    if (!gates.length) {
      dom.list.innerHTML = '<div class="scanapp-card scanapp-card--placeholder"><p class="scanapp-card__text">Nu există porți configurate. Adaugă prima poartă.</p></div>';
      return;
    }
    dom.list.innerHTML = gates.map(function (g) {
      var cap = g.capacity || g.max_capacity || null;
      return '<div class="scanapp-sheet__row" data-gate-id="' + escapeHtml(g.id) + '" style="cursor:pointer; padding: 14px 12px; background: var(--scanapp-surface); border: 1px solid var(--scanapp-border); border-radius: 12px; margin-bottom: 8px;">' +
               '<div class="scanapp-sheet__row-body">' +
                 '<div class="scanapp-sheet__row-name">' + escapeHtml(g.name || 'Poartă') + '</div>' +
                 '<div class="scanapp-sheet__row-sub">' + (cap ? ('Capacitate: ' + cap) : 'Fără limită capacitate') + '</div>' +
               '</div>' +
               '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color: var(--scanapp-text-ter); flex-shrink: 0;"><polyline points="9 18 15 12 9 6"/></svg>' +
             '</div>';
    }).join('');
    dom.list.querySelectorAll('[data-gate-id]').forEach(function (row) {
      row.addEventListener('click', function () {
        var id = row.getAttribute('data-gate-id');
        var g = gates.find(function (x) { return String(x.id) === String(id); });
        if (g) openSheet(g);
      });
    });
  }

  function openSheet(gate) {
    editingGate = gate || null;
    dom.title.textContent = gate ? 'Editează poartă' : 'Poartă nouă';
    dom.nameInput.value = gate ? (gate.name || '') : '';
    dom.capInput.value  = gate ? (gate.capacity || gate.max_capacity || '') : '';
    dom.deleteBtn.hidden = !gate;
    dom.sheet.classList.add('scanapp-sheet-backdrop--open');
    setTimeout(function () { dom.nameInput.focus(); }, 50);
  }
  function closeSheet() {
    dom.sheet.classList.remove('scanapp-sheet-backdrop--open');
    editingGate = null;
  }

  function saveGate() {
    if (!currentVenueId) return;
    var name = (dom.nameInput.value || '').trim();
    if (!name) { ScanApp.toast('Numele porții este obligatoriu.', 'warning'); return; }
    var capacity = parseInt(dom.capInput.value, 10);
    var payload = { name: name };
    if (!isNaN(capacity) && capacity > 0) payload.capacity = capacity;

    var p;
    if (editingGate) {
      p = AmbiletAPI.put('/organizer/venues/' + currentVenueId + '/gates/' + editingGate.id, payload);
    } else {
      p = AmbiletAPI.post('/organizer/venues/' + currentVenueId + '/gates', payload);
    }
    p.then(function () {
      closeSheet();
      loadGates();
      ScanApp.toast(editingGate ? 'Poarta a fost actualizată.' : 'Poarta a fost creată.', 'success');
    }).catch(function (err) {
      console.error('[porti] save failed:', err);
      ScanApp.toast('Salvarea a eșuat: ' + ((err && err.message) || 'eroare necunoscută.'), 'danger');
    });
  }

  function deleteGate() {
    if (!editingGate || !currentVenueId) return;
    if (!confirm('Ștergi poarta "' + (editingGate.name || '') + '"?')) return;
    AmbiletAPI.delete('/organizer/venues/' + currentVenueId + '/gates/' + editingGate.id).then(function () {
      closeSheet();
      loadGates();
      ScanApp.toast('Poarta a fost ștearsă.', 'success');
    }).catch(function (err) {
      console.error('[porti] delete failed:', err);
      ScanApp.toast('Ștergerea a eșuat.', 'danger');
    });
  }

  function init() {
    collectDom();
    if (!dom.list) return;
    if (!ScanAuth.isAdmin()) {
      dom.list.innerHTML = '<div class="scanapp-card scanapp-card--placeholder"><p class="scanapp-card__text">Doar administratorii pot configura porțile.</p></div>';
      dom.addBtn.disabled = true;
      return;
    }
    dom.addBtn.addEventListener('click', function () { openSheet(null); });
    dom.cancelBtn.addEventListener('click', closeSheet);
    dom.saveBtn.addEventListener('click', saveGate);
    dom.deleteBtn.addEventListener('click', deleteGate);
    dom.sheet.addEventListener('click', function (e) { if (e.target === dom.sheet) closeSheet(); });

    if (window.EventContext) {
      EventContext.subscribe('event-selected', loadGates);
      var s = EventContext.getState();
      if (s.selectedEvent) loadGates();
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
