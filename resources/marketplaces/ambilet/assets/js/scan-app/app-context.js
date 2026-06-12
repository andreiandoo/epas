/* =============================================================================
 * Scan App — AppContext (settings, shift, recent activity)
 * -----------------------------------------------------------------------------
 * Mirror of the mobile app's AppContext from tixello-app/src/context/AppContext.js.
 *
 * Holds:
 *   - settings        (persisted localStorage):
 *       vibrationFeedback / soundEffects / autoConfirmValid / offlineMode
 *   - shift           (persisted sessionStorage so a reload mid-shift keeps stats):
 *       shiftStartTime / cashTurnover / cardTurnover / myScans / mySales / isShiftPaused
 *   - recentScans     (localStorage per event, max 50)
 *   - recentSales     (localStorage per event, max 20)
 *   - isOnline        (live navigator.onLine)
 *
 * Pub/sub via a tiny EventTarget so multiple pages/components can subscribe to
 * the same store without React/Vue overhead.
 *
 * Usage:
 *   AppContext.get('vibrationFeedback')
 *   AppContext.set('vibrationFeedback', false)
 *   AppContext.startShift()
 *   AppContext.endShift()
 *   AppContext.addScan({ code, valid, ticketType, time })
 *   AppContext.addSale({ id, total, paymentMethod, items, time })
 *   AppContext.subscribe('change', (e) => { console.log(e.detail.key, e.detail.value); })
 * ============================================================================= */
(function () {
  'use strict';

  var SETTINGS_KEY = 'scanapp_settings_v1';
  var SHIFT_KEY    = 'scanapp_shift_v1';

  function readJSON(storage, key, fallback) {
    try {
      var raw = storage.getItem(key);
      if (!raw) return fallback;
      return JSON.parse(raw);
    } catch (e) {
      return fallback;
    }
  }

  function writeJSON(storage, key, value) {
    try { storage.setItem(key, JSON.stringify(value)); } catch (e) { /* quota / safari private */ }
  }

  var defaultSettings = {
    vibrationFeedback: true,
    soundEffects:      true,
    autoConfirmValid:  false,
    offlineMode:       false
  };

  var defaultShift = {
    shiftStartTime: null,
    cashTurnover:   0,
    cardTurnover:   0,
    myScans:        0,
    mySales:        0,
    isShiftPaused:  false
  };

  function loadSettings() {
    var stored = readJSON(localStorage, SETTINGS_KEY, {});
    return Object.assign({}, defaultSettings, stored || {});
  }

  function loadShift() {
    var stored = readJSON(sessionStorage, SHIFT_KEY, {});
    return Object.assign({}, defaultShift, stored || {});
  }

  function recentScansKey(eventId) { return 'scanapp_scans_' + eventId; }
  function recentSalesKey(eventId) { return 'scanapp_sales_' + eventId; }

  var emitter = new EventTarget();

  function emit(type, detail) {
    emitter.dispatchEvent(new CustomEvent(type, { detail: detail }));
  }

  // ── In-memory state ──────────────────────────────────────────────────────
  var state = {
    settings: loadSettings(),
    shift:    loadShift(),
    isOnline: navigator.onLine !== false
  };

  // Online/offline detection
  window.addEventListener('online',  function () {
    state.isOnline = true;
    emit('online-change', { isOnline: true });
  });
  window.addEventListener('offline', function () {
    state.isOnline = false;
    emit('online-change', { isOnline: false });
  });

  // ── Public API ───────────────────────────────────────────────────────────
  var AppContext = {
    // Settings
    getSettings: function () { return Object.assign({}, state.settings); },
    get: function (key) { return state.settings[key]; },
    set: function (key, value) {
      if (!(key in defaultSettings)) {
        console.warn('[AppContext] Unknown setting:', key);
        return;
      }
      state.settings[key] = value;
      writeJSON(localStorage, SETTINGS_KEY, state.settings);
      emit('settings-change', { key: key, value: value, settings: state.settings });
      emit('change',          { key: key, value: value, settings: state.settings });
    },

    // Shift
    getShift: function () { return Object.assign({}, state.shift); },
    startShift: function () {
      state.shift = Object.assign({}, defaultShift, { shiftStartTime: Date.now() });
      writeJSON(sessionStorage, SHIFT_KEY, state.shift);
      emit('shift-start', state.shift);
    },
    endShift: function () {
      var snapshot = Object.assign({}, state.shift);
      state.shift = Object.assign({}, defaultShift);
      try { sessionStorage.removeItem(SHIFT_KEY); } catch (e) {}
      emit('shift-end', snapshot);
    },
    pauseShift: function () {
      state.shift.isShiftPaused = true;
      writeJSON(sessionStorage, SHIFT_KEY, state.shift);
      emit('shift-pause', state.shift);
    },
    resumeShift: function () {
      state.shift.isShiftPaused = false;
      writeJSON(sessionStorage, SHIFT_KEY, state.shift);
      emit('shift-resume', state.shift);
    },

    // Recent activity (per event)
    getRecentScans: function (eventId) {
      if (!eventId) return [];
      return readJSON(localStorage, recentScansKey(eventId), []) || [];
    },
    getRecentSales: function (eventId) {
      if (!eventId) return [];
      return readJSON(localStorage, recentSalesKey(eventId), []) || [];
    },

    /**
     * @param {string|number} eventId
     * @param {{code:string, valid:boolean, ticketType?:string, time?:number, customerName?:string}} scan
     */
    addScan: function (eventId, scan) {
      if (!eventId || !scan) return;
      var entry = Object.assign({}, scan, { time: scan.time || Date.now() });
      var list = AppContext.getRecentScans(eventId);
      list.unshift(entry);
      if (list.length > 50) list.length = 50;
      writeJSON(localStorage, recentScansKey(eventId), list);
      if (entry.valid) {
        state.shift.myScans = (state.shift.myScans || 0) + 1;
        writeJSON(sessionStorage, SHIFT_KEY, state.shift);
      }
      emit('scan-added', { eventId: eventId, scan: entry, shift: state.shift });
    },

    /**
     * @param {string|number} eventId
     * @param {{id:string|number, total:number, paymentMethod:string, items:Array, time?:number}} sale
     */
    addSale: function (eventId, sale) {
      if (!eventId || !sale) return;
      var entry = Object.assign({}, sale, { time: sale.time || Date.now() });
      var list = AppContext.getRecentSales(eventId);
      list.unshift(entry);
      if (list.length > 20) list.length = 20;
      writeJSON(localStorage, recentSalesKey(eventId), list);

      // Update turnover
      var amount = Number(entry.total) || 0;
      if (entry.paymentMethod === 'cash') state.shift.cashTurnover = (state.shift.cashTurnover || 0) + amount;
      else if (entry.paymentMethod === 'card') state.shift.cardTurnover = (state.shift.cardTurnover || 0) + amount;
      state.shift.mySales = (state.shift.mySales || 0) + 1;
      writeJSON(sessionStorage, SHIFT_KEY, state.shift);
      emit('sale-added', { eventId: eventId, sale: entry, shift: state.shift });
    },

    isOnline: function () { return state.isOnline; },

    // Pub/sub
    subscribe: function (event, handler) {
      emitter.addEventListener(event, handler);
      return function unsubscribe() { emitter.removeEventListener(event, handler); };
    }
  };

  window.AppContext = AppContext;
})();
