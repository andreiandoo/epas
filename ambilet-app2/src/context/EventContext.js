import React, { createContext, useContext, useState, useCallback, useEffect, useRef } from 'react';
import { getEvents, getEvent } from '../api/events';
import { getParticipants } from '../api/participants';
import { categorizeEvent, groupEventsByCategory } from '../utils/eventCategories';
import { createReverbConnection } from '../api/reverb';
import { useAuth } from './AuthContext';
import { pickString } from '../utils/pickString';

const EventContext = createContext(null);

export function EventProvider({ children }) {
  const { user } = useAuth();
  const [events, setEvents] = useState([]);
  const [selectedEvent, setSelectedEvent] = useState(null);
  const [eventStats, setEventStats] = useState(null);
  const [ticketTypes, setTicketTypes] = useState([]);
  const [allTicketTypes, setAllTicketTypes] = useState([]);
  const [isLoadingEvents, setIsLoadingEvents] = useState(false);
  const [isLoadingStats, setIsLoadingStats] = useState(false);
  const [eventCommission, setEventCommission] = useState(null);

  // When the active organizer changes (switch-organizer), clear all cached
  // event data so the next fetch loads the new organizer's events.
  const previousOrganizerId = useRef(null);
  useEffect(() => {
    if (!user?.id) {
      previousOrganizerId.current = null;
      return;
    }
    if (previousOrganizerId.current && previousOrganizerId.current !== user.id) {
      setEvents([]);
      setSelectedEvent(null);
      setEventStats(null);
      setTicketTypes([]);
      setAllTicketTypes([]);
      setEventCommission(null);
    }
    previousOrganizerId.current = user.id;
  }, [user?.id]);

  const isReportsOnlyMode = selectedEvent?.timeCategory === 'past';

  const fetchEvents = useCallback(async () => {
    setIsLoadingEvents(true);
    try {
      // Two parallel calls so drafts NEVER crowd out real events from the
      // 100-row page. Postgres sorts `event_date DESC NULLS FIRST` by default,
      // so a single unfiltered fetch can return only drafts (which usually
      // have no date) when the organizer has 100+ drafts. Fetching published
      // and drafts on separate paginators guarantees we have a slice of each.
      const [publishedRes, draftsRes] = await Promise.all([
        getEvents({ published_only: true, per_page: 100 }),
        getEvents({ status: 'draft', per_page: 50 }).catch(() => null),
      ]);
      const unwrap = (res) => {
        if (!res || !res.success || !res.data) return [];
        return Array.isArray(res.data) ? res.data : (res.data.events || res.events || []);
      };
      const merged = [...unwrap(publishedRes), ...unwrap(draftsRes)];
      // Dedupe by id in case an event appears in both slices (shouldn't
      // happen — published_only vs status=draft are mutually exclusive —
      // but cheap safety net).
      const seen = new Set();
      const list = merged.filter(e => {
        if (seen.has(e.id)) return false;
        seen.add(e.id);
        return true;
      });
      if (list.length > 0 || publishedRes?.success || draftsRes?.success) {
        const enriched = list.map(e => ({
          ...e,
          timeCategory: categorizeEvent(e),
        }));
        setEvents(enriched);

        // Auto-select: live > today > future > past > first.
        // Also warm the cache for the SECOND most-likely event so
        // switching from the picker is instant on the common case
        // ("live event + one soon after"), before that switch happens.
        if (!selectedEvent) {
          const live = enriched.find(e => e.timeCategory === 'live');
          const today = enriched.find(e => e.timeCategory === 'today');
          const future = enriched.find(e => e.timeCategory === 'future');
          const past = enriched.find(e => e.timeCategory === 'past');
          const primary = live || today || future || past || enriched[0];
          selectEvent(primary);
          const runnersUp = [live, today, future].filter(Boolean).filter(e => e.id !== primary?.id);
          if (runnersUp[0]) {
            // Fire-and-forget — no await, no state changes, just fills the cache.
            setTimeout(() => { try { fetchEventData(runnersUp[0].id); } catch {} }, 800);
          }
        }
      }
    } catch (e) {
      console.error('Failed to fetch events:', e);
    }
    setIsLoadingEvents(false);
  }, []);

  // Per-event cache — dedupe fetches within a 30s window. Reverb push
  // events (order.confirmed) call `invalidateEventCache` to force fresh
  // reads immediately after a real state change. `refreshStats` also
  // bypasses the cache so pull-to-refresh always hits the API.
  const eventCacheRef = useRef(new Map()); // eventId → { lastFetchedAt, inflight }
  const CACHE_TTL_MS = 30_000;

  const invalidateEventCache = useCallback((eventId) => {
    if (eventId === undefined) {
      eventCacheRef.current.clear();
    } else {
      eventCacheRef.current.delete(eventId);
    }
  }, []);

  const [lastSyncAt, setLastSyncAt] = useState(null);

  const COLOR_PALETTE = ['#8B5CF6', '#F59E0B', '#10B981', '#06B6D4', '#EF4444', '#EC4899'];
  const enrichTicketType = (t, i) => ({
    ...t,
    // Ticket-type `name` on Ambilet legacy events is a translatable JSON
    // ({en, ro}) that leaks through some backend endpoints unresolved.
    // Coerce to a string here so every downstream <Text>{tt.name}</Text>
    // (cart, picker, dashboard breakdown) is safe.
    name: pickString(t.name, `Bilet ${i + 1}`),
    color: t.color || COLOR_PALETTE[i % COLOR_PALETTE.length],
    available: t.available ?? (t.quantity != null && t.quantity_sold != null ? t.quantity - t.quantity_sold : 0),
    checked_in: t.checked_in ?? 0,
  });

  const applyEventPayload = useCallback((participantsData, eventResponse) => {
    const rawStats = participantsData?.data?.stats
      || participantsData?.stats
      || participantsData?.meta?.stats
      || participantsData?.meta
      || {};
    const eventData = eventResponse?.data?.event || eventResponse?.data || {};

    setEventStats({
      total: rawStats.total ?? 0,
      checked_in: rawStats.checked_in ?? 0,
      not_checked_in: rawStats.not_checked_in ?? 0,
      check_in_rate: rawStats.check_in_rate ?? 0,
      online_count: rawStats.online_count ?? 0,
      door_count: rawStats.door_count ?? 0,
      // Coerce ticket-type name to string per row — backend selects the raw
      // (translatable) ticket_types.name column so it can arrive as {en, ro}.
      by_source_and_type: {
        online: (rawStats.by_source_and_type?.online || []).map(r => ({ ...r, name: pickString(r.name, 'Bilet') })),
        door: (rawStats.by_source_and_type?.door || []).map(r => ({ ...r, name: pickString(r.name, 'Bilet') })),
      },
      hourly_distribution: rawStats.hourly_distribution ?? [],
      peak_hour: rawStats.peak_hour ?? null,
      total_sold: eventData.tickets_sold ?? rawStats.total ?? 0,
      revenue: eventData.revenue ?? rawStats.revenue ?? 0,
      capacity: rawStats.capacity ?? eventData.capacity ?? 0,
    });

    setEventCommission({
      rate: eventData.effective_commission_rate || eventData.commission_rate || 0,
      mode: eventData.commission_mode || 'included',
      useFixed: eventData.use_fixed_commission || false,
    });

    const allTypes = eventData.ticket_types || [];
    setAllTicketTypes(allTypes.map(enrichTicketType));
    const isTestType = (t) => t?.meta?.is_test === true;
    const posTypes = allTypes.filter(t => t.is_entry_ticket || isTestType(t));
    setTicketTypes(posTypes.length > 0 ? posTypes.map(enrichTicketType) : []);

    setLastSyncAt(Date.now());
  }, []);

  // Single load path: parallel fetch of participants + event details,
  // with 30s cache dedupe and in-flight coalescing (rapid tab switches
  // do NOT trigger duplicate requests).
  const fetchEventData = useCallback(async (eventId, { force = false } = {}) => {
    if (!eventId) return;
    const cache = eventCacheRef.current.get(eventId);
    const now = Date.now();

    if (!force && cache?.lastFetchedAt && now - cache.lastFetchedAt < CACHE_TTL_MS) {
      return; // fresh in cache, skip
    }
    if (cache?.inflight) {
      return cache.inflight; // dedupe: one operator switching fast fires 1 request
    }

    setIsLoadingStats(true);
    const inflight = (async () => {
      try {
        const [participants, eventResponse] = await Promise.all([
          getParticipants(eventId, { per_page: 1 }),
          getEvent(eventId),
        ]);
        applyEventPayload(participants, eventResponse);
        eventCacheRef.current.set(eventId, { lastFetchedAt: Date.now(), inflight: null });
      } catch (e) {
        console.error('Failed to fetch event data:', e);
        eventCacheRef.current.set(eventId, { lastFetchedAt: cache?.lastFetchedAt || 0, inflight: null });
      } finally {
        setIsLoadingStats(false);
      }
    })();

    eventCacheRef.current.set(eventId, { lastFetchedAt: cache?.lastFetchedAt || 0, inflight });
    return inflight;
  }, [applyEventPayload]);

  const selectEvent = useCallback(async (event) => {
    if (!event) return;
    setSelectedEvent(event);
    fetchEventData(event.id);
  }, [fetchEventData]);

  // Backwards-compat wrappers — a few call sites still reference these.
  const fetchEventStats = useCallback((eventId) => fetchEventData(eventId, { force: true }), [fetchEventData]);
  const fetchTicketTypes = useCallback((eventId) => fetchEventData(eventId, { force: true }), [fetchEventData]);

  const incrementCheckedIn = useCallback(() => {
    setEventStats(prev => {
      if (!prev) return prev;
      const newCheckedIn = (prev.checked_in || 0) + 1;
      const total = prev.total || 0;
      return {
        ...prev,
        checked_in: newCheckedIn,
        not_checked_in: Math.max(0, total - newCheckedIn),
        check_in_rate: total > 0 ? (newCheckedIn / total) * 100 : 0,
      };
    });
  }, []);

  // Pull-to-refresh entry point. Debounced to 500ms so a compulsive
  // operator can't hammer the API on a laggy connection.
  const lastRefreshAtRef = useRef(0);
  const refreshStats = useCallback(async () => {
    if (!selectedEvent) return;
    const now = Date.now();
    if (now - lastRefreshAtRef.current < 500) return;
    lastRefreshAtRef.current = now;
    invalidateEventCache(selectedEvent.id);
    return fetchEventData(selectedEvent.id, { force: true });
  }, [selectedEvent, fetchEventData, invalidateEventCache]);

  // Reverb push handler needs to bust cache before refetching.
  const refreshTicketTypes = useCallback(() => {
    if (!selectedEvent) return;
    invalidateEventCache(selectedEvent.id);
    return fetchEventData(selectedEvent.id, { force: true });
  }, [selectedEvent, fetchEventData, invalidateEventCache]);

  // ── Real-time push: Reverb subscription per selected event ────────
  // One WebSocket per AuthProvider lifetime; we attach / detach a
  // listener as selectedEvent changes. When backend dispatches
  // OrderConfirmed on event.{id}.sales, we refresh stats + ticket
  // types immediately — no 30 s polling lag.
  const reverbRef = useRef(null);

  useEffect(() => {
    const realtime = user?.realtime;
    if (!realtime?.enabled) return;
    if (!reverbRef.current) {
      reverbRef.current = createReverbConnection(realtime);
    }
    return () => {
      // We keep the connection across event switches — only close on
      // user change (handled by the cleanup of the outer effect that
      // recreates this when user.id changes).
    };
  }, [user?.realtime?.enabled, user?.realtime?.app_key, user?.realtime?.host]);

  // Tear down on logout / account switch.
  useEffect(() => {
    return () => {
      if (reverbRef.current) {
        reverbRef.current.close();
        reverbRef.current = null;
      }
    };
  }, [user?.id]);

  // Per-event subscription.
  useEffect(() => {
    if (!selectedEvent?.id || !reverbRef.current) return;
    const channel = `event.${selectedEvent.id}.sales`;
    const unsub = reverbRef.current.subscribe(channel, 'order.confirmed', () => {
      // A sale landed somewhere — bust the 30s cache and pull fresh
      // numbers right now (single request via fetchEventData).
      invalidateEventCache(selectedEvent.id);
      fetchEventData(selectedEvent.id, { force: true });
    });
    return () => { try { unsub(); } catch {} };
  }, [selectedEvent?.id]);

  const groupedEvents = groupEventsByCategory(events);

  return (
    <EventContext.Provider value={{
      events,
      groupedEvents,
      selectedEvent,
      eventStats,
      ticketTypes,
      allTicketTypes,
      isLoadingEvents,
      isLoadingStats,
      isReportsOnlyMode,
      eventCommission,
      lastSyncAt,
      fetchEvents,
      selectEvent,
      refreshStats,
      refreshTicketTypes,
      incrementCheckedIn,
    }}>
      {children}
    </EventContext.Provider>
  );
}

export function useEvent() {
  const context = useContext(EventContext);
  if (!context) throw new Error('useEvent must be used within EventProvider');
  return context;
}
