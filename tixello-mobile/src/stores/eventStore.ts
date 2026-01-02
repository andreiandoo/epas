import { create } from 'zustand';
import { Event, TicketType, LiveStats, GatePerformance } from '../types';

interface EventState {
  events: Event[];
  selectedEvent: Event | null;
  ticketTypes: TicketType[];
  liveStats: LiveStats | null;
  gatePerformance: GatePerformance[];
  isLoading: boolean;
  error: string | null;

  // Actions
  setEvents: (events: Event[]) => void;
  setSelectedEvent: (event: Event | null) => void;
  setTicketTypes: (types: TicketType[]) => void;
  setLiveStats: (stats: LiveStats | null) => void;
  setGatePerformance: (performance: GatePerformance[]) => void;
  setLoading: (loading: boolean) => void;
  setError: (error: string | null) => void;
  refreshSelectedEvent: () => void;
}

export const useEventStore = create<EventState>((set, get) => ({
  events: [],
  selectedEvent: null,
  ticketTypes: [],
  liveStats: null,
  gatePerformance: [],
  isLoading: false,
  error: null,

  setEvents: (events) => set({ events }),

  setSelectedEvent: (selectedEvent) => set({ selectedEvent }),

  setTicketTypes: (ticketTypes) => set({ ticketTypes }),

  setLiveStats: (liveStats) => set({ liveStats }),

  setGatePerformance: (gatePerformance) => set({ gatePerformance }),

  setLoading: (isLoading) => set({ isLoading }),

  setError: (error) => set({ error }),

  refreshSelectedEvent: () => {
    const { events, selectedEvent } = get();
    if (selectedEvent) {
      const updated = events.find(e => e.id === selectedEvent.id);
      if (updated) {
        set({ selectedEvent: updated });
      }
    }
  },
}));
