import React, { createContext, useContext, useState, useCallback, useEffect, useRef } from 'react';
import AsyncStorage from '@react-native-async-storage/async-storage';
import { getParticipants } from '../api/participants';

const AppContext = createContext(null);

export function AppProvider({ children }) {
  // Scanner settings
  const [vibrationFeedback, setVibrationFeedback] = useState(true);
  const [soundEffects, setSoundEffects] = useState(true);
  const [autoConfirmValid, setAutoConfirmValid] = useState(false);
  const [offlineMode, setOfflineMode] = useState(false);

  // Shift state
  const [shiftStartTime, setShiftStartTime] = useState(null);
  const [isShiftPaused, setIsShiftPaused] = useState(false);
  const [cashTurnover, setCashTurnover] = useState(0);
  const [cardTurnover, setCardTurnover] = useState(0);
  const [myScans, setMyScans] = useState(0);
  const [mySales, setMySales] = useState(0);

  // Recent scans (local)
  const [recentScans, setRecentScans] = useState([]);
  const [recentSales, setRecentSales] = useState([]);

  // Notifications
  const [notifications, setNotifications] = useState([]);

  // Connection status
  const [isOnline, setIsOnline] = useState(true);

  // Cached tickets count
  const [cachedTickets, setCachedTickets] = useState(0);

  // Load settings from storage
  useEffect(() => {
    loadSettings();
  }, []);

  const loadSettings = async () => {
    try {
      const settings = await AsyncStorage.getItem('app_settings');
      if (settings) {
        const parsed = JSON.parse(settings);
        setVibrationFeedback(parsed.vibrationFeedback ?? true);
        setSoundEffects(parsed.soundEffects ?? true);
        setAutoConfirmValid(parsed.autoConfirmValid ?? false);
        setOfflineMode(parsed.offlineMode ?? false);
      }
    } catch (e) {
      // Use defaults
    }
  };

  const saveSettings = async (updates) => {
    try {
      const current = {
        vibrationFeedback,
        soundEffects,
        autoConfirmValid,
        offlineMode,
        ...updates,
      };
      await AsyncStorage.setItem('app_settings', JSON.stringify(current));
    } catch (e) {
      // Ignore
    }
  };

  const toggleVibration = () => {
    const newVal = !vibrationFeedback;
    setVibrationFeedback(newVal);
    saveSettings({ vibrationFeedback: newVal });
  };

  const toggleSound = () => {
    const newVal = !soundEffects;
    setSoundEffects(newVal);
    saveSettings({ soundEffects: newVal });
  };

  const toggleAutoConfirm = () => {
    const newVal = !autoConfirmValid;
    setAutoConfirmValid(newVal);
    saveSettings({ autoConfirmValid: newVal });
  };

  const toggleOfflineMode = () => {
    const newVal = !offlineMode;
    setOfflineMode(newVal);
    saveSettings({ offlineMode: newVal });
  };

  // Shift management
  const startShift = () => {
    setShiftStartTime(new Date());
    setIsShiftPaused(false);
    setCashTurnover(0);
    setCardTurnover(0);
    setMyScans(0);
    setMySales(0);
    setRecentScans([]);
    setRecentSales([]);
  };

  const endShift = () => {
    setShiftStartTime(null);
    setIsShiftPaused(false);
  };

  const addScan = (scan) => {
    if (!shiftStartTime) {
      setShiftStartTime(Date.now());
    }
    setRecentScans(prev => [scan, ...prev].slice(0, 20));
    setMyScans(prev => prev + 1);
  };

  const addSale = (sale) => {
    if (!shiftStartTime) {
      setShiftStartTime(Date.now());
    }
    setRecentSales(prev => [sale, ...prev].slice(0, 20));
    setMySales(prev => prev + 1);
    if (sale.method === 'cash') {
      setCashTurnover(prev => prev + sale.total);
    } else {
      setCardTurnover(prev => prev + sale.total);
    }
  };

  const addNotification = (notification) => {
    setNotifications(prev => [{
      id: Date.now(),
      ...notification,
      time: 'Just now',
      unread: true,
    }, ...prev]);
  };

  const markAllRead = () => {
    setNotifications(prev => prev.map(n => ({ ...n, unread: false })));
  };
 
 // Offline: download all participants for offline check-in
  const downloadParticipantsForOffline = useCallback(async (eventId) => {
    if (!eventId) return;
    try {
      let allParticipants = [];
      let page = 1;
      let hasMore = true;
      while (hasMore) {
        const response = await getParticipants(eventId, { per_page: 200, page });
        const participants = response.data || [];
        allParticipants = [...allParticipants, ...participants];
        const meta = response.meta || {};
        hasMore = meta.current_page < meta.last_page;
        page++;
      }
      await AsyncStorage.setItem(
        `offline_participants_${eventId}`,
        JSON.stringify(allParticipants)
      );
      setCachedTickets(allParticipants.length);
    } catch (e) {
      console.error("Failed to cache participants:", e);
    }
  }, []);
  // Offline: check in a ticket from cached data
  const offlineCheckIn = useCallback(async (eventId, ticketCode) => {
    try {
      const stored = await AsyncStorage.getItem(`offline_participants_${eventId}`);
      if (!stored) return { success: false, message: "Nu există date offline" };
      const participants = JSON.parse(stored);
      const match = participants.find(p =>
        p.barcode === ticketCode || p.code === ticketCode || p.ticket_code === ticketCode
      );
      if (!match) return { success: false, message: "Bilet negăsit sau cod invalid" };
      if (match.checked_in_at || match.status === "checked_in") {
        return { success: false, message: "Acest bilet a fost deja scanat", type: "duplicate" };
      }
      // Mark as checked in locally
      const updated = participants.map(p =>
        p === match ? { ...p, checked_in_at: new Date().toISOString(), status: "checked_in" } : p
      );
      await AsyncStorage.setItem(`offline_participants_${eventId}`, JSON.stringify(updated));
      // Queue for sync
      const queue = JSON.parse(await AsyncStorage.getItem("offline_checkin_queue") || "[]");
      queue.push({ eventId, ticketCode, timestamp: Date.now() });
      await AsyncStorage.setItem("offline_checkin_queue", JSON.stringify(queue));
      setCachedTickets(updated.length);
      return { success: true, data: match };
    } catch (e) {
      return { success: false, message: "Eroare la scanarea offline" };
    }
  }, []);

  return (
    <AppContext.Provider value={{
      // Settings
      vibrationFeedback,
      soundEffects,
      autoConfirmValid,
      offlineMode,
      toggleVibration,
      toggleSound,
      toggleAutoConfirm,
      toggleOfflineMode,

      // Shift
      shiftStartTime,
      isShiftPaused,
      setIsShiftPaused,
      cashTurnover,
      cardTurnover,
      myScans,
      mySales,
      startShift,
      endShift,

      // Activity
      recentScans,
      recentSales,
      addScan,
      addSale,

      // Notifications
      notifications,
      addNotification,
      markAllRead,

      // Connection
      isOnline,
      setIsOnline,

      // Cache
      cachedTickets,
      setCachedTickets,

      // Offline
      downloadParticipantsForOffline,
      offlineCheckIn,
    }}>
      {children}
    </AppContext.Provider>
  );
}

export function useApp() {
  const context = useContext(AppContext);
  if (!context) throw new Error('useApp must be used within AppProvider');
  return context;
}
