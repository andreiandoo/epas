import React, { createContext, useContext, useState, useCallback, useEffect, useRef } from 'react';
import AsyncStorage from '@react-native-async-storage/async-storage';

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
    setRecentScans(prev => [scan, ...prev].slice(0, 20));
    setMyScans(prev => prev + 1);
  };

  const addSale = (sale) => {
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
