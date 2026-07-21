// useOfflineTicketSync — hook DEFAULT-ON pentru cache local de bilete Sf. Ana.
//
// Scop: internetul cade rar pe locatia fizica a tabletei; cand cade, KioskScreen
// trebuie sa poata VALIDA bilete offline. Rezolvare: descarcam biletele evenimentului
// in AsyncStorage la mount + refresh incremental la fiecare 60s.
//
// Design NON-BREAKING:
//  - Tot codul offline e wrapped in try/catch. Orice esec nu afecteaza fluxul online.
//  - Hook-ul e OPT-IN: KioskScreen il apeleaza doar cand vrea offline support.
//  - Foloseste doar dependinte deja existente (@react-native-async-storage/async-storage).
//    NU adauga netinfo — detectam offline prin timeout la request-ul de scanare.
//
// API returnat:
//  {
//    cachedCount,           // numar bilete in cache local
//    lastSyncAt,            // ISO datetime al ultimului sync reusit
//    isSyncing,             // true in timpul unui sync
//    syncError,             // ultimul mesaj de eroare (afisare optionala)
//    lookupTicket(code),    // async, cauta biletul in cache local; null daca lipseste
//    markTicketCheckedIn(code, at),  // marcheaza local + adauga in pending queue
//    getPendingScans(),     // returneaza scanurile care asteapta sync la server
//    flushPendingScans(sendFn), // trimite queue-ul la server via fn(code) → Promise
//    forceRefresh(),        // trigger manual sync
//  }
//
// Format cache:
//   AsyncStorage['sfana_ticket_cache_v1_{eventId}'] = {
//     tickets: { [code]: ticketData, [barcode]: ticketData, ... },
//     lastSyncAt: '2026-07-11T15:30:00.000Z',
//     count: 152
//   }
//   AsyncStorage['sfana_pending_scans_v1_{eventId}'] = [
//     { code, scanned_at, attempt_count }
//   ]

import { useState, useEffect, useCallback, useRef } from 'react';
import AsyncStorage from '@react-native-async-storage/async-storage';
import { fetchEventParticipants } from '../api/leisure';

const CACHE_PREFIX = 'sfana_ticket_cache_v1_';
const PENDING_PREFIX = 'sfana_pending_scans_v1_';
const SYNC_INTERVAL_MS = 60_000; // 60 secunde per spec user

export function useOfflineTicketSync(eventId, { enabled = true } = {}) {
  const [cachedCount, setCachedCount] = useState(0);
  const [lastSyncAt, setLastSyncAt] = useState(null);
  const [isSyncing, setIsSyncing] = useState(false);
  const [syncError, setSyncError] = useState(null);
  const intervalRef = useRef(null);
  const cacheRef = useRef({ tickets: {}, lastSyncAt: null }); // in-memory oglinda a AsyncStorage

  // Cheile AsyncStorage sunt derivate din eventId. Cand eventId lipseste, hook-ul e no-op.
  const cacheKey = eventId ? `${CACHE_PREFIX}${eventId}` : null;
  const pendingKey = eventId ? `${PENDING_PREFIX}${eventId}` : null;

  // ─── Load cache existent din AsyncStorage la mount ──────────────────
  useEffect(() => {
    if (!enabled || !cacheKey) return;
    (async () => {
      try {
        const raw = await AsyncStorage.getItem(cacheKey);
        if (raw) {
          const parsed = JSON.parse(raw);
          if (parsed && typeof parsed === 'object') {
            cacheRef.current = {
              tickets: parsed.tickets || {},
              lastSyncAt: parsed.lastSyncAt || null,
            };
            setCachedCount(parsed.count || Object.keys(parsed.tickets || {}).length);
            setLastSyncAt(parsed.lastSyncAt || null);
          }
        }
      } catch (e) {
        // Silent — cache invalid, se va reface la primul sync
        console.warn('[offline-sync] load cache failed:', e?.message);
      }
    })();
  }, [cacheKey, enabled]);

  // ─── Sync worker: fetch bilete + salvare AsyncStorage ────────────────
  // Foloseste `since` = lastSyncAt pentru sync incremental (payload mic).
  // Prima rulare (lastSyncAt=null) → full sync (paginat).
  const runSync = useCallback(async () => {
    if (!enabled || !cacheKey) return;
    setIsSyncing(true);
    setSyncError(null);
    try {
      const since = cacheRef.current.lastSyncAt;
      // Sync incremental daca avem lastSyncAt (< 1 KB payload), full altfel
      const now = new Date().toISOString();
      let page = 1;
      let hasMore = true;
      const newTicketMap = { ...cacheRef.current.tickets };
      let updated = 0;

      while (hasMore) {
        const res = await fetchEventParticipants(eventId, { since, page, perPage: 500 });
        const items = res?.data || [];
        for (const t of items) {
          // Indexare dupa TOATE identificatorii posibili (code + barcode + control_code)
          // ca lookup-ul sa functioneze indiferent ce scaneaza operatorul.
          if (t.code) newTicketMap[t.code] = t;
          if (t.barcode) newTicketMap[t.barcode] = t;
          if (t.control_code) newTicketMap[t.control_code] = t;
          updated++;
        }
        const meta = res?.meta || {};
        hasMore = meta.current_page && meta.last_page && meta.current_page < meta.last_page;
        page++;
        if (page > 20) break; // safety cap 10k bilete
      }

      cacheRef.current = { tickets: newTicketMap, lastSyncAt: now };
      const count = Object.keys(newTicketMap).length;
      await AsyncStorage.setItem(cacheKey, JSON.stringify({
        tickets: newTicketMap,
        lastSyncAt: now,
        count,
      }));
      setCachedCount(count);
      setLastSyncAt(now);
      setIsSyncing(false);
      return { updated, total: count };
    } catch (e) {
      console.warn('[offline-sync] sync failed:', e?.message);
      setSyncError(e?.message || 'Sync failed');
      setIsSyncing(false);
      return null;
    }
  }, [enabled, cacheKey, eventId]);

  // ─── Start polling: sync la mount + la fiecare 60s ───────────────────
  useEffect(() => {
    if (!enabled || !cacheKey) return;
    // Un sync imediat la mount (initial cache dupa AsyncStorage load)
    runSync();
    intervalRef.current = setInterval(runSync, SYNC_INTERVAL_MS);
    return () => {
      if (intervalRef.current) clearInterval(intervalRef.current);
      intervalRef.current = null;
    };
  }, [enabled, cacheKey, runSync]);

  // ─── Lookup: cauta un cod in cache local ─────────────────────────────
  // Returneaza obiectul biletului daca exista, null altfel.
  const lookupTicket = useCallback(async (code) => {
    if (!code) return null;
    try {
      // In-memory cache pentru viteza (evita read AsyncStorage la fiecare scanare)
      const inMem = cacheRef.current.tickets[code];
      if (inMem) return inMem;
      // Fallback disk (in caz ca in-mem nu s-a incarcat inca)
      if (!cacheKey) return null;
      const raw = await AsyncStorage.getItem(cacheKey);
      if (!raw) return null;
      const parsed = JSON.parse(raw);
      return parsed?.tickets?.[code] || null;
    } catch (e) {
      return null;
    }
  }, [cacheKey]);

  // ─── Pending scans queue: adaugam scanurile offline pentru sync ulterior ──
  const markTicketCheckedIn = useCallback(async (code, scannedAt = null) => {
    if (!code || !pendingKey) return;
    try {
      const raw = await AsyncStorage.getItem(pendingKey);
      const queue = raw ? JSON.parse(raw) : [];
      // Dedupe: daca acelasi cod deja e in queue, nu adaugam de doua ori
      if (queue.find((q) => q.code === code)) return;
      queue.push({
        code,
        scanned_at: scannedAt || new Date().toISOString(),
        attempt_count: 0,
      });
      await AsyncStorage.setItem(pendingKey, JSON.stringify(queue));
      // Actualizam si in-memory ca lookup-ul urmator sa returneze biletul marcat
      const t = cacheRef.current.tickets[code];
      if (t) {
        cacheRef.current.tickets[code] = { ...t, checked_in_at: scannedAt || new Date().toISOString(), _offline_scan: true };
      }
    } catch (e) {
      console.warn('[offline-sync] markTicketCheckedIn failed:', e?.message);
    }
  }, [pendingKey]);

  const getPendingScans = useCallback(async () => {
    if (!pendingKey) return [];
    try {
      const raw = await AsyncStorage.getItem(pendingKey);
      return raw ? JSON.parse(raw) : [];
    } catch (e) {
      return [];
    }
  }, [pendingKey]);

  // Flush pending: incearca sa trimita fiecare scanare la server via sendFn.
  // sendFn(code) trebuie sa returneze o Promise care se rezolva cu true/false.
  const flushPendingScans = useCallback(async (sendFn) => {
    if (!pendingKey || typeof sendFn !== 'function') return { sent: 0, failed: 0 };
    try {
      const raw = await AsyncStorage.getItem(pendingKey);
      const queue = raw ? JSON.parse(raw) : [];
      if (queue.length === 0) return { sent: 0, failed: 0 };
      let sent = 0;
      let failed = 0;
      const remaining = [];
      for (const item of queue) {
        try {
          const ok = await sendFn(item.code);
          if (ok) sent++;
          else {
            item.attempt_count = (item.attempt_count || 0) + 1;
            if (item.attempt_count < 10) remaining.push(item);
            failed++;
          }
        } catch (_e) {
          item.attempt_count = (item.attempt_count || 0) + 1;
          if (item.attempt_count < 10) remaining.push(item);
          failed++;
        }
      }
      await AsyncStorage.setItem(pendingKey, JSON.stringify(remaining));
      return { sent, failed };
    } catch (e) {
      return { sent: 0, failed: 0 };
    }
  }, [pendingKey]);

  return {
    cachedCount,
    lastSyncAt,
    isSyncing,
    syncError,
    lookupTicket,
    markTicketCheckedIn,
    getPendingScans,
    flushPendingScans,
    forceRefresh: runSync,
  };
}
