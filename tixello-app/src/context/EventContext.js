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
      if (data.meta?.stats) {
        setEventStats(data.meta.stats);
      }
    } catch (e) {
      console.error('Failed to fetch event stats:', e);
    }
    setIsLoadingStats(false);
  }, []);

  const fetchTicketTypes = useCallback(async (eventId) => {
    try {
      const data = await getEvent(eventId);
      if (data.success && data.data?.ticket_types) {
        const colors = ['#8B5CF6', '#F59E0B', '#10B981', '#06B6D4', '#EF4444', '#EC4899'];
        setTicketTypes(data.data.ticket_types.map((t, i) => ({
          ...t,
          color: colors[i % colors.length],
          available: t.quota_available ?? t.quota - (t.quota_sold || 0),
        })));
      }
    } catch (e) {
      console.error('Failed to fetch ticket types:', e);
    }
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
      fetchEvents,
      selectEvent,
      refreshStats,
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
