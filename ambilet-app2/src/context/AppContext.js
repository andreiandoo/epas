import React, { createContext, useContext, useState, useCallback, useEffect, useRef } from 'react';
import AsyncStorage from '@react-native-async-storage/async-storage';
import { Vibration } from 'react-native';
import { getParticipants, checkinByBarcode } from '../api/participants';
import { apiPost } from '../api/client';
import {
  loadQueue as loadSalesQueue,
  enqueueOfflineSale,
  removeFromQueue as removeSaleFromQueue,
  markRetried as markSaleRetried,
  countQueued as countQueuedSales,
} from '../services/offlineSalesQueue';

// expo-av is optional — we lazy-require so the module is a silent no-op
// if the native side isn't linked (dev without prebuild).
let Audio = null;
try {
  Audio = require('expo-av').Audio;
} catch (e) {
  Audio = null;
}

const AppContext = createContext(null);

export function AppProvider({ children }) {
  // Scanner settings
  const [vibrationFeedback, setVibrationFeedback] = useState(true);
  const [soundEffects, setSoundEffects] = useState(true);
  const [autoConfirmValid, setAutoConfirmValid] = useState(false);
  const [offlineMode, setOfflineMode] = useState(false);

  // Auto-logout after N minutes of inactivity. Default 5min. Configurable
  // in Settings (5/10/15/30 minutes, or off).
  const [autoLogoutMinutes, setAutoLogoutMinutes] = useState(5);

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

  // Offline-scanned tickets still waiting for server sync. Persisted in
  // AsyncStorage under `offline_checkin_queue`; we mirror the count in
  // state so the header badge re-renders when the queue changes without
  // having to poll AsyncStorage.
  const [pendingOfflineCount, setPendingOfflineCount] = useState(0);

  // Offline sales queue — same idea but for POS sales made while offline.
  // Count exposed for the header badge (aggregated with scan queue).
  const [pendingOfflineSalesCount, setPendingOfflineSalesCount] = useState(0);
  const refreshPendingSalesCount = useCallback(async () => {
    setPendingOfflineSalesCount(await countQueuedSales());
  }, []);
  useEffect(() => { refreshPendingSalesCount(); }, [refreshPendingSalesCount]);

  const refreshPendingOfflineCount = useCallback(async () => {
    try {
      const raw = await AsyncStorage.getItem('offline_checkin_queue');
      const queue = raw ? JSON.parse(raw) : [];
      setPendingOfflineCount(Array.isArray(queue) ? queue.length : 0);
    } catch (e) {
      setPendingOfflineCount(0);
    }
  }, []);

  useEffect(() => { refreshPendingOfflineCount(); }, [refreshPendingOfflineCount]);

  // Emergency contacts (phone numbers dialed from the notifications panel).
  // Kept local to the device — each operator/venue configures their own.
  const [emergencyContacts, setEmergencyContacts] = useState({
    medical: '',
    tehnica: '',
    paza: '',
  });

  // Load settings from storage
  useEffect(() => {
    loadSettings();
    loadEmergencyContacts();
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
        // Guard: 0 disables, valid values in [5,10,15,30].
        const al = parsed.autoLogoutMinutes;
        setAutoLogoutMinutes(typeof al === 'number' && al >= 0 ? al : 5);
      }
    } catch (e) {
      // Use defaults
    }
  };

  const loadEmergencyContacts = async () => {
    try {
      const raw = await AsyncStorage.getItem('emergency_contacts');
      if (raw) {
        const parsed = JSON.parse(raw);
        setEmergencyContacts({
          medical: parsed.medical || '',
          tehnica: parsed.tehnica || '',
          paza: parsed.paza || '',
        });
      }
    } catch (e) {
      // Ignore — keep defaults
    }
  };

  const updateEmergencyContact = useCallback((key, value) => {
    setEmergencyContacts(prev => {
      const next = { ...prev, [key]: value };
      AsyncStorage.setItem('emergency_contacts', JSON.stringify(next)).catch(() => {});
      return next;
    });
  }, []);

  const saveSettings = async (updates) => {
    try {
      const current = {
        vibrationFeedback,
        soundEffects,
        autoConfirmValid,
        offlineMode,
        autoLogoutMinutes,
        ...updates,
      };
      await AsyncStorage.setItem('app_settings', JSON.stringify(current));
    } catch (e) {
      // Ignore
    }
  };

  const changeAutoLogoutMinutes = useCallback((minutes) => {
    setAutoLogoutMinutes(minutes);
    saveSettings({ autoLogoutMinutes: minutes });
  }, [saveSettings]);

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

  // Force-set the auto-confirm flag (used by the "future event auto-uncheck"
  // rule and the today-event prompt CTA). Persists like toggleAutoConfirm.
  const setAutoConfirm = (value) => {
    const next = !!value;
    setAutoConfirmValid(next);
    saveSettings({ autoConfirmValid: next });
  };

  const [isDownloadingOffline, setIsDownloadingOffline] = useState(false);

  const toggleOfflineMode = async (eventId) => {
    const newVal = !offlineMode;
    setOfflineMode(newVal);
    saveSettings({ offlineMode: newVal });
    if (newVal && eventId) {
      setIsDownloadingOffline(true);
      await downloadParticipantsForOffline(eventId);
      setIsDownloadingOffline(false);
    }
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

  const addScan = useCallback((scan) => {
    if (!shiftStartTime) {
      setShiftStartTime(Date.now());
    }
    setRecentScans(prev => {
      const updated = [scan, ...prev].slice(0, 50);
      // Persist scan history per event
      if (scan.eventId) {
        AsyncStorage.setItem(`scan_history_${scan.eventId}`, JSON.stringify(updated)).catch(() => {});
      }
      return updated;
    });
    setMyScans(prev => prev + 1);
  }, [shiftStartTime]);

  const loadScanHistory = useCallback(async (eventId) => {
    if (!eventId) return;
    try {
      const stored = await AsyncStorage.getItem(`scan_history_${eventId}`);
      if (stored) {
        const parsed = JSON.parse(stored);
        setRecentScans(parsed);
        // Restore myScans count from persisted history
        setMyScans(parsed.length);
      } else {
        setRecentScans([]);
      }
    } catch (e) {
      setRecentScans([]);
    }
  }, []);

  const addSale = useCallback((sale) => {
    if (!shiftStartTime) {
      setShiftStartTime(Date.now());
    }
    setRecentSales(prev => {
      const updated = [sale, ...prev].slice(0, 20);
      // Persist sale history per event
      if (sale.eventId) {
        AsyncStorage.setItem(`sale_history_${sale.eventId}`, JSON.stringify(updated)).catch(() => {});
      }
      return updated;
    });
    setMySales(prev => prev + 1);
    if (sale.method === 'cash') {
      setCashTurnover(prev => prev + sale.total);
    } else {
      setCardTurnover(prev => prev + sale.total);
    }
  }, [shiftStartTime]);

  const loadSaleHistory = useCallback(async (eventId) => {
    if (!eventId) return;
    try {
      const stored = await AsyncStorage.getItem(`sale_history_${eventId}`);
      if (stored) {
        const parsed = JSON.parse(stored);
        setRecentSales(parsed);
        setMySales(parsed.length);
        // Restore turnover from persisted sales
        let cash = 0, card = 0;
        parsed.forEach(s => {
          if (s.method === 'cash') cash += s.total || 0;
          else card += s.total || 0;
        });
        setCashTurnover(cash);
        setCardTurnover(card);
      } else {
        setRecentSales([]);
        setMySales(0);
        setCashTurnover(0);
        setCardTurnover(0);
      }
    } catch (e) {
      setRecentSales([]);
    }
  }, []);

  // Lazy-load a single Audio.Sound instance for notification chimes so we
  // don't recreate it on every incoming notification (would spike memory).
  // Falls back silently if expo-av isn't linked (dev without prebuild).
  const notificationSoundRef = useRef(null);
  const loadNotificationSound = useCallback(async () => {
    if (!Audio || notificationSoundRef.current) return notificationSoundRef.current;
    try {
      const { sound } = await Audio.Sound.createAsync(
        require('../../assets/sounds/warning.mp3'),
        { volume: 0.9 },
      );
      notificationSoundRef.current = sound;
      return sound;
    } catch (e) {
      return null;
    }
  }, []);
  useEffect(() => {
    return () => {
      // Release audio when the provider unmounts (logout / hot reload)
      try { notificationSoundRef.current?.unloadAsync(); } catch {}
      notificationSoundRef.current = null;
    };
  }, []);
  const playNotificationSound = useCallback(async () => {
    // Respect the operator's Scanner "Efecte Sonore" toggle — that
    // preference already governs every other in-app sound cue.
    if (!soundEffects) return;
    const sound = await loadNotificationSound();
    if (!sound) return;
    try { await sound.replayAsync(); } catch {}
  }, [soundEffects, loadNotificationSound]);

  const addNotification = (notification) => {
    setNotifications(prev => [{
      id: Date.now(),
      ...notification,
      time: 'Just now',
      unread: true,
    }, ...prev]);
    // Fire-and-forget audio + haptic. Alert-type notifications get a
    // stronger vibration pattern; info/success stay subtle so a normal
    // "sale confirmed" doesn't feel emergency-loud.
    playNotificationSound();
    if (vibrationFeedback) {
      const heavy = notification?.type === 'alert';
      try { Vibration.vibrate(heavy ? [0, 120, 80, 120] : 60); } catch {}
    }
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
  // Offline: ensure offline data exists when offline mode is already enabled
  const ensureOfflineData = useCallback(async (eventId) => {
    if (!offlineMode || !eventId) return;
    try {
      const stored = await AsyncStorage.getItem(`offline_participants_${eventId}`);
      if (!stored || JSON.parse(stored).length === 0) {
        setIsDownloadingOffline(true);
        await downloadParticipantsForOffline(eventId);
        setIsDownloadingOffline(false);
      } else {
        setCachedTickets(JSON.parse(stored).length);
      }
    } catch (e) {
      console.error('Failed to ensure offline data:', e);
    }
  }, [offlineMode, downloadParticipantsForOffline]);

  // Best-effort replay of everything queued while offline. Called manually
  // (Settings → sync button) or automatically when isOnline flips true.
  // We tolerate duplicates and unknown barcodes — anything that comes back
  // is considered "processed" and drops out of the queue so the badge
  // doesn't stay stuck forever.
  const syncingRef = useRef(false);
  const flushOfflineQueue = useCallback(async () => {
    if (syncingRef.current) return;
    syncingRef.current = true;
    try {
      const raw = await AsyncStorage.getItem('offline_checkin_queue');
      const queue = raw ? JSON.parse(raw) : [];
      if (!Array.isArray(queue) || queue.length === 0) {
        setPendingOfflineCount(0);
        return;
      }
      const remaining = [];
      for (const item of queue) {
        try {
          await checkinByBarcode(item.eventId, item.ticketCode);
        } catch (e) {
          // Backend already knew about this scan (409 duplicate) or the ticket
          // was deleted — both are "done from our side". Only a real network
          // failure would keep the item in the queue for the next attempt.
          const status = e?.status || e?.response?.status;
          if (!status || status >= 500) {
            remaining.push(item);
          }
        }
      }
      await AsyncStorage.setItem('offline_checkin_queue', JSON.stringify(remaining));
      setPendingOfflineCount(remaining.length);
    } catch (e) {
      // fall through — will retry on next online transition
    } finally {
      syncingRef.current = false;
    }
  }, []);

  // Enqueue an offline sale — SalesScreen calls this when isOnline is
  // false. Returns the entry (with idempotency_key) so the caller can
  // still fire a local success animation and history record.
  const enqueueOfflineSaleAction = useCallback(async ({ eventId, payload }) => {
    const entry = await enqueueOfflineSale({ eventId, payload });
    await refreshPendingSalesCount();
    return entry;
  }, [refreshPendingSalesCount]);

  // Flush queued sales. Backend uses `idempotency_key` from meta to
  // dedupe — repeated retries won't duplicate tickets even if the
  // response is lost mid-flight. Removes entries on 2xx or on 4xx that
  // aren't clearly "try again" (e.g. 422 validation = never going to
  // succeed, remove; 5xx or network = keep for next attempt).
  const salesSyncingRef = useRef(false);
  const flushOfflineSalesQueue = useCallback(async () => {
    if (salesSyncingRef.current) return;
    salesSyncingRef.current = true;
    try {
      const queue = await loadSalesQueue();
      for (const entry of queue) {
        try {
          await apiPost('/orders', {
            ...entry.payload,
            idempotency_key: entry.idempotency_key,
          });
          await removeSaleFromQueue(entry.idempotency_key);
        } catch (e) {
          const status = e?.status;
          if (status && status >= 400 && status < 500 && status !== 429) {
            // Permanent error — server refuses. Drop from queue and log.
            await removeSaleFromQueue(entry.idempotency_key);
          } else {
            await markSaleRetried(entry.idempotency_key, e?.message || 'network');
          }
        }
      }
    } finally {
      salesSyncingRef.current = false;
      await refreshPendingSalesCount();
    }
  }, [refreshPendingSalesCount]);

  // Trigger a flush whenever the app comes back online (see setIsOnline
  // caller). Also runs once on boot in case the previous session died
  // mid-sync with items still queued.
  useEffect(() => {
    if (isOnline) {
      flushOfflineQueue();
      flushOfflineSalesQueue();
    }
  }, [isOnline, flushOfflineQueue, flushOfflineSalesQueue]);

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
      setPendingOfflineCount(queue.length);
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
      autoLogoutMinutes,
      toggleVibration,
      toggleSound,
      toggleAutoConfirm,
      setAutoConfirm,
      toggleOfflineMode,
      changeAutoLogoutMinutes,

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
      loadScanHistory,
      loadSaleHistory,

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
      isDownloadingOffline,

      // Offline
      downloadParticipantsForOffline,
      offlineCheckIn,
      ensureOfflineData,
      pendingOfflineCount,
      flushOfflineQueue,
      pendingOfflineSalesCount,
      enqueueOfflineSale: enqueueOfflineSaleAction,
      flushOfflineSalesQueue,

      // Emergency contacts
      emergencyContacts,
      updateEmergencyContact,
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
