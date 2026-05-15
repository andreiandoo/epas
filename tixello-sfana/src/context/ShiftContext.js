import React, { createContext, useContext, useState, useCallback } from 'react';
import { fetchActiveShift, fetchOrganizerEvents } from '../api/leisure';

const ShiftContext = createContext(null);

export function ShiftProvider({ children }) {
  const [activeShift, setActiveShift] = useState(null);
  const [activeEvent, setActiveEvent] = useState(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);

  /**
   * Reîncarcă shift-ul activ + event-ul leisure asociat.
   * Apelat după login + periodic (la fiecare 5 min) ca rolul să fie sincronizat
   * cu turnetele setate de manager.
   */
  const refresh = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const [shiftRes, eventsRes] = await Promise.all([
        fetchActiveShift().catch(() => ({ shifts: [] })),
        fetchOrganizerEvents(),
      ]);
      const shifts = shiftRes?.data?.shifts || shiftRes?.shifts || [];
      const events = eventsRes?.data || [];
      const leisure = events.find(e => (e.display_template || 'standard') === 'leisure_venue');

      setActiveShift(shifts[0] || null); // primul shift activ
      setActiveEvent(leisure || null);
    } catch (e) {
      console.warn('[ShiftContext] refresh failed', e);
      setError(e?.message || 'Eroare');
    } finally {
      setLoading(false);
    }
  }, []);

  const clear = useCallback(() => {
    setActiveShift(null);
    setActiveEvent(null);
    setError(null);
  }, []);

  return (
    <ShiftContext.Provider value={{ activeShift, activeEvent, loading, error, refresh, clear }}>
      {children}
    </ShiftContext.Provider>
  );
}

export function useShift() {
  const ctx = useContext(ShiftContext);
  if (!ctx) throw new Error('useShift must be used within ShiftProvider');
  return ctx;
}
