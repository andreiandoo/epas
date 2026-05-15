import { useState, useCallback } from 'react';
import AsyncStorage from '@react-native-async-storage/async-storage';
import { getParticipants } from '../api/participants';
import { useApp } from '../context/AppContext';

const CACHE_PREFIX = 'offline_tickets_';

export function useOfflineCache() {
  const [isCaching, setIsCaching] = useState(false);
  const { setCachedTickets } = useApp();

  const cacheEventTickets = useCallback(async (eventId) => {
    setIsCaching(true);
    try {
      let allParticipants = [];
      let page = 1;
      let hasMore = true;

      while (hasMore) {
        const data = await getParticipants(eventId, { per_page: 200, page });
        if (data.success && data.data) {
          allParticipants = [...allParticipants, ...data.data];
          hasMore = page < (data.meta?.last_page || 1);
          page++;
        } else {
          hasMore = false;
        }
      }

      // Store as lookup map by ticket code/barcode
      const ticketMap = {};
      allParticipants.forEach(p => {
        if (p.ticket_code) ticketMap[p.ticket_code] = p;
        if (p.barcode) ticketMap[p.barcode] = p;
        if (p.control_code) ticketMap[p.control_code] = p;
      });

      await AsyncStorage.setItem(
        `${CACHE_PREFIX}${eventId}`,
        JSON.stringify(ticketMap)
      );
      await AsyncStorage.setItem(
        `${CACHE_PREFIX}${eventId}_count`,
        String(allParticipants.length)
      );

      setCachedTickets(allParticipants.length);
      setIsCaching(false);
      return allParticipants.length;
    } catch (e) {
      console.error('Failed to cache tickets:', e);
      setIsCaching(false);
      return 0;
    }
  }, [setCachedTickets]);

  const lookupOfflineTicket = useCallback(async (eventId, code) => {
    try {
      const cached = await AsyncStorage.getItem(`${CACHE_PREFIX}${eventId}`);
      if (!cached) return null;
      const ticketMap = JSON.parse(cached);
      return ticketMap[code] || null;
    } catch (e) {
      return null;
    }
  }, []);

  const getCachedCount = useCallback(async (eventId) => {
    try {
      const count = await AsyncStorage.getItem(`${CACHE_PREFIX}${eventId}_count`);
      return count ? parseInt(count) : 0;
    } catch (e) {
      return 0;
    }
  }, []);

  const clearCache = useCallback(async (eventId) => {
    try {
      await AsyncStorage.removeItem(`${CACHE_PREFIX}${eventId}`);
      await AsyncStorage.removeItem(`${CACHE_PREFIX}${eventId}_count`);
      setCachedTickets(0);
    } catch (e) {
      // Ignore
    }
  }, [setCachedTickets]);

  return {
    isCaching,
    cacheEventTickets,
    lookupOfflineTicket,
    getCachedCount,
    clearCache,
  };
}
