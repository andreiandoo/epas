/* =============================================================================
 * Scan App — EventContext (events list, selected event, stats, ticket types)
 * -----------------------------------------------------------------------------
 * Mirror of the mobile app's EventContext from tixello-app/src/context/EventContext.js.
 *
 * Holds:
 *   - events             [...]          (from GET /organizer/events)
 *   - selectedEvent      {...}|null     (persisted localStorage: scanapp_selected_event_id)
 *   - eventStats         {total, checked_in, not_checked_in, check_in_rate, total_sold, revenue, capacity}
 *   - ticketTypes        [...]          (only is_entry_ticket=true — what the POS shows)
 *   - allTicketTypes     [...]          (every ticket type — used by reports/breakdowns)
 *   - eventCommission    {rate, mode}
 *   - isLoadingEvents    boolean
 *   - isLoadingStats     boolean
 *   - groupedEvents      {live:[], today:[], future:[], past:[]} (categorized)
 *
 * Behaviour mirrored from mobile:
 *   - categorizeEvent() reproduces tixello-app/src/utils/eventCategories.js EXACTLY,
 *     including the recent server-side fix for in-progress range events (event.event_date
 *     set to NOW makes diffDays=0).
 *   - 30s polling of stats once an event is selected (stops if tab is hidden, resumes on
 *     visibilitychange).
 *   - Public API: fetchEvents / selectEvent / refreshStats / refreshTicketTypes /
 *     incrementCheckedIn / subscribe.
 *
 * Real-time Reverb hookup is added in Etapa 3 (scanner) — leaves a TODO marker
 * below so the integration point is obvious.
 * ============================================================================= */
(function () {
  'use strict';

  var SELECTED_EVENT_KEY = 'scanapp_selected_event_id_v1';

  var emitter = new EventTarget();
  function emit(type, detail) {
    emitter.dispatchEvent(new CustomEvent(type, { detail: detail }));
  }

  var state = {
    events:           [],
    groupedEvents:    { live: [], today: [], future: [], past: [] },
    selectedEvent:    null,
    eventStats:       null,
    ticketTypes:      [],
    allTicketTypes:   [],
    eventCommission:  { rate: 0, mode: 'included', useFixed: false },
    isLoadingEvents:  false,
    isLoadingStats:   false
  };

  var statsPollTimer = null;
  var STATS_POLL_INTERVAL_MS = 30000;

  // ── Helpers ──────────────────────────────────────────────────────────────
  /**
   * Replicates tixello-app/src/utils/eventCategories.js → categorizeEvent().
   * Returns one of: 'live' | 'today' | 'future' | 'past'.
   */
  function categorizeEvent(event) {
    var now = new Date();
    var eventDate = new Date(event.event_date || event.starts_at);
    var today = new Date(now.getFullYear(), now.getMonth(), now.getDate());
    var eventDay = new Date(eventDate.getFullYear(), eventDate.getMonth(), eventDate.getDate());

    if (event.status === 'ended' || event.status === 'cancelled') return 'past';

    var diffMs = eventDay.getTime() - today.getTime();
    var diffDays = diffMs / (1000 * 60 * 60 * 24);

    if (diffDays === 0) {
      var hoursDiff = (eventDate.getTime() - now.getTime()) / (1000 * 60 * 60);
      if (hoursDiff <= 0 && hoursDiff > -12) return 'live';
      return 'today';
    }
    if (diffDays < 0) return 'past';
    return 'future';
  }

  function groupEventsByCategory(events) {
    var groups = { live: [], today: [], future: [], past: [] };
    events.forEach(function (event) {
      var category = event.timeCategory || categorizeEvent(event);
      if (groups[category]) {
        groups[category].push(Object.assign({}, event, { timeCategory: category }));
      }
    });
    return groups;
  }

  function persistSelectedEventId(id) {
    try {
      if (id == null) localStorage.removeItem(SELECTED_EVENT_KEY);
      else            localStorage.setItem(SELECTED_EVENT_KEY, String(id));
    } catch (e) {}
  }

  function loadPersistedSelectedEventId() {
    try {
      var raw = localStorage.getItem(SELECTED_EVENT_KEY);
      return raw ? Number(raw) : null;
    } catch (e) { return null; }
  }

  // ── API calls ────────────────────────────────────────────────────────────
  function apiGet(path, params) {
    if (typeof ScanAPI === 'undefined' || !ScanAPI.get) {
      return Promise.reject(new Error('ScanAPI not available'));
    }
    return ScanAPI.get(path, params || {});
  }

  function unwrapList(resp) {
    // Mobile-side accepts either `data: [...]` or `data: { events: [...] }`.
    var data = resp && resp.data;
    if (Array.isArray(data)) return data;
    if (data && Array.isArray(data.events)) return data.events;
    if (resp && Array.isArray(resp.events)) return resp.events;
    return [];
  }

  function unwrapEvent(resp) {
    var data = resp && resp.data;
    if (data && data.event) return data.event;
    if (data && data.id)    return data;
    if (resp && resp.event) return resp.event;
    return data || null;
  }

  // ── Public API ───────────────────────────────────────────────────────────
  var EventContext = {
    getState: function () { return Object.assign({}, state); },

    isReportsOnlyMode: function () {
      return !!(state.selectedEvent && state.selectedEvent.timeCategory === 'past');
    },

    fetchEvents: function () {
      state.isLoadingEvents = true;
      emit('loading-events', { loading: true });

      return apiGet('/organizer/events', { per_page: 100, sort: 'event_date', order: 'desc', published_only: true })
        .then(function (resp) {
          var list = unwrapList(resp);
          var enriched = list.map(function (e) {
            return Object.assign({}, e, { timeCategory: categorizeEvent(e) });
          });
          state.events = enriched;
          state.groupedEvents = groupEventsByCategory(enriched);

          // Resolve initial selectedEvent: persisted → first live → today → future → past → first.
          if (!state.selectedEvent) {
            var persistedId = loadPersistedSelectedEventId();
            var fromPersist = persistedId
              ? enriched.find(function (e) { return Number(e.id) === Number(persistedId); })
              : null;
            var byPriority = enriched.find(function (e) { return e.timeCategory === 'live';   })
                          || enriched.find(function (e) { return e.timeCategory === 'today';  })
                          || enriched.find(function (e) { return e.timeCategory === 'future'; })
                          || enriched.find(function (e) { return e.timeCategory === 'past';   })
                          || enriched[0]
                          || null;
            var pick = fromPersist || byPriority;
            if (pick) EventContext.selectEvent(pick, { skipPersist: false });
          }

          emit('events-loaded', { events: enriched, grouped: state.groupedEvents });
        })
        .catch(function (e) {
          console.error('[EventContext] fetchEvents failed:', e);
          emit('events-error', { error: e });
        })
        .finally(function () {
          state.isLoadingEvents = false;
          emit('loading-events', { loading: false });
        });
    },

    selectEvent: function (event, opts) {
      if (!event) return Promise.resolve(null);
      opts = opts || {};
      state.selectedEvent = event;
      if (!opts.skipPersist) persistSelectedEventId(event.id);
      emit('event-selected', { event: event });

      // Auto-fetch stats + ticket types in ONE batch (one /events/{id} call
      // instead of two).
      return EventContext.refreshAll().then(function () {
        EventContext._startStatsPolling();
      });
    },

    /**
     * One batched fetch — runs participants + event details in parallel and
     * dispatches both stats-updated and ticket-types-updated events from a
     * single network round-trip.
     *
     * Older callers (refreshStats / refreshTicketTypes) delegate to this.
     */
    refreshAll: function () {
      if (!state.selectedEvent) return Promise.resolve(null);
      var eventId = state.selectedEvent.id;
      state.isLoadingStats = true;

      return Promise.all([
        apiGet('/organizer/events/' + eventId + '/participants', { per_page: 1 }),
        apiGet('/organizer/events/' + eventId)
      ]).then(function (results) {
        var partResp = results[0];
        var eventResp = results[1];

        // Stats
        var rawStats = (partResp && partResp.data && partResp.data.stats)
                    || (partResp && partResp.stats)
                    || (partResp && partResp.meta && partResp.meta.stats)
                    || {};
        var eventData = unwrapEvent(eventResp) || {};
        state.eventStats = {
          total:           rawStats.total           != null ? rawStats.total           : 0,
          checked_in:      rawStats.checked_in      != null ? rawStats.checked_in      : 0,
          not_checked_in:  rawStats.not_checked_in  != null ? rawStats.not_checked_in  : 0,
          check_in_rate:   rawStats.check_in_rate   != null ? rawStats.check_in_rate   : 0,
          total_sold:      eventData.tickets_sold   != null ? eventData.tickets_sold   : (rawStats.total || 0),
          revenue:         eventData.revenue        != null ? eventData.revenue        : (rawStats.revenue || 0),
          capacity:        eventData.capacity       != null ? eventData.capacity       : 0
        };

        // Ticket types + commission (same event payload — no second fetch)
        state.eventCommission = {
          rate:     eventData.effective_commission_rate || eventData.commission_rate || 0,
          mode:     eventData.commission_mode || 'included',
          useFixed: !!eventData.use_fixed_commission
        };
        var palette = ['#8B5CF6', '#F59E0B', '#10B981', '#06B6D4', '#EF4444', '#EC4899'];
        var allTypes = eventData.ticket_types || [];
        var enrich = function (t, i) {
          var available = (t.available != null)
            ? t.available
            : ((t.quantity != null && t.quantity_sold != null) ? (t.quantity - t.quantity_sold) : 0);
          return Object.assign({}, t, {
            color:      t.color || palette[i % palette.length],
            available:  available,
            checked_in: t.checked_in != null ? t.checked_in : 0
          });
        };
        state.allTicketTypes = allTypes.map(enrich);
        state.ticketTypes    = allTypes.filter(function (t) { return t.is_entry_ticket; }).map(enrich);

        emit('stats-updated',         { stats: state.eventStats });
        emit('ticket-types-updated',  { ticketTypes: state.ticketTypes, allTicketTypes: state.allTicketTypes, commission: state.eventCommission });
      }).catch(function (e) {
        console.error('[EventContext] refreshAll failed:', e);
      }).finally(function () {
        state.isLoadingStats = false;
      });
    },

    refreshStats:       function () { return EventContext.refreshAll(); },
    refreshTicketTypes: function () { return EventContext.refreshAll(); },

    incrementCheckedIn: function () {
      if (!state.eventStats) return;
      var s = state.eventStats;
      var newCheckedIn = (s.checked_in || 0) + 1;
      var total = s.total || 0;
      state.eventStats = Object.assign({}, s, {
        checked_in:      newCheckedIn,
        not_checked_in:  Math.max(0, total - newCheckedIn),
        check_in_rate:   total > 0 ? (newCheckedIn / total) * 100 : 0
      });
      emit('stats-updated', { stats: state.eventStats });
    },

    // Polling — pauses on hidden tab, resumes on focus.
    _startStatsPolling: function () {
      EventContext._stopStatsPolling();
      statsPollTimer = setInterval(function () {
        if (document.hidden) return;
        EventContext.refreshStats();
      }, STATS_POLL_INTERVAL_MS);
    },
    _stopStatsPolling: function () {
      if (statsPollTimer) { clearInterval(statsPollTimer); statsPollTimer = null; }
    },

    subscribe: function (event, handler) {
      emitter.addEventListener(event, handler);
      return function unsubscribe() { emitter.removeEventListener(event, handler); };
    }

    // TODO Etapa 3: Reverb realtime sub `event.{id}.sales` → on `order.confirmed`
    //   refreshStats() + refreshTicketTypes(). Hook lands in reverb-client.js.
  };

  // When tab becomes visible again, refresh once immediately (catch up).
  document.addEventListener('visibilitychange', function () {
    if (!document.hidden && state.selectedEvent) {
      EventContext.refreshStats();
    }
  });

  window.EventContext = EventContext;
})();
