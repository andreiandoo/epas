import React, { createContext, useContext, useState, useCallback, useEffect } from 'react';
import { getEvents, getEvent } from '../api/events';
import { getParticipants } from '../api/participants';
import { categorizeEvent, groupEventsByCategory } from '../utils/eventCategories';

const EventContext = createContext(null);

export function EventProvider({ children }) {
  const [events, setEvents] = useState([]);
  const [selectedEvent, setSelectedEvent] = useState(null);
  const [eventStats, setEventStats] = useState(null);
  const [ticketTypes, setTicketTypes] = useState([]);
  const [isLoadingEvents, setIsLoadingEvents] = useState(false);
  const [isLoadingStats, setIsLoadingStats] = useState(false);
  const [eventCommission, setEventCommission] = useState(null);

  const isReportsOnlyMode = selectedEvent?.timeCategory === 'past';

  const fetchEvents = useCallback(async () => {
    setIsLoadingEvents(true);
    try {
      const data = await getEvents({ per_page: 100 });
      if (data.success && data.data) {
        const enriched = data.data.map(e => ({
          ...e,
          timeCategory: categorizeEvent(e),
        }));
        setEvents(enriched);

        // Auto-select: live > today > past > first
        if (!selectedEvent) {
          const live = enriched.find(e => e.timeCategory === 'live');
          const today = enriched.find(e => e.timeCategory === 'today');
          const past = enriched.find(e => e.timeCategory === 'past');
          selectEvent(live || today || past || enriched[0]);
        }
      }
    } catch (e) {
      console.error('Failed to fetch events:', e);
    }
    setIsLoadingEvents(false);
  }, []);

  const selectEvent = useCallback(async (event) => {
    if (!event) return;
    setSelectedEvent(event);
    fetchEventStats(event.id);
    fetchTicketTypes(event.id);
  }, []);

  const fetchEventStats = useCallback(async (eventId) => {
    setIsLoadingStats(true);
    try {
      const data = await getParticipants(eventId, { per_page: 1 });
      // API returns { data: { participants: [], stats: { total, checked_in, not_checked_in, check_in_rate } } }
      const rawStats = data.data?.stats || data.stats || data.meta?.stats || data.meta || {};
      // Also get event-level data for revenue/capacity/tickets_sold
      const eventResponse = await getEvent(eventId);
      const eventData = eventResponse.data?.event || eventResponse.data || {};
      setEventStats({
        total: rawStats.total ?? 0,
        checked_in: rawStats.checked_in ?? 0,
        not_checked_in: rawStats.not_checked_in ?? 0,
        check_in_rate: rawStats.check_in_rate ?? 0,
        // Event-level stats
        total_sold: eventData.tickets_sold ?? rawStats.total ?? 0,
        revenue: eventData.revenue ?? rawStats.revenue ?? 0,
        capacity: eventData.capacity ?? 0,
      });
    } catch (e) {
      console.error('Failed to fetch event stats:', e);
    }
    setIsLoadingStats(false);
  }, []);

  const fetchTicketTypes = useCallback(async (eventId) => {
    try {
      const response = await getEvent(eventId);
      // Handle { data: { event: { ticket_types: [] } } } and { data: { ticket_types: [] } }
      const event = response.data?.event || response.data || response;
      setEventCommission({
        rate: event.effective_commission_rate || event.commission_rate || 0,
        mode: event.commission_mode || 'included',
        useFixed: event.use_fixed_commission || false,
      });
      const types = event.ticket_types || [];
      if (types.length > 0) {
        const colorPalette = ['#8B5CF6', '#F59E0B', '#10B981', '#06B6D4', '#EF4444', '#EC4899'];
        setTicketTypes(types.map((t, i) => ({
          ...t,
          color: colorPalette[i % colorPalette.length],
          available: t.available ?? (t.quantity != null && t.quantity_sold != null ? t.quantity - t.quantity_sold : 0),
        })));
      } else {
        setTicketTypes([]);
      }
    } catch (e) {
      console.error('Failed to fetch ticket types:', e);
      setTicketTypes([]);
    }
  }, []);

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

  const refreshStats = useCallback(() => {
    if (selectedEvent) {
      fetchEventStats(selectedEvent.id);
    }
  }, [selectedEvent]);

  const groupedEvents = groupEventsByCategory(events);

  return (
    <EventContext.Provider value={{
      events,
      groupedEvents,
      selectedEvent,
      eventStats,
      ticketTypes,
      isLoadingEvents,
      isLoadingStats,
      isReportsOnlyMode,
      eventCommission,
      fetchEvents,
      selectEvent,
      refreshStats,
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
