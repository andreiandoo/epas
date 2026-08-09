/* =========================================================
   Legatura dintre motorul offline si ecrane.

   Motorul (clock/db/scanEngine/sync) nu stie nimic despre React si e testat
   separat. Aici traiesc doar starea vizibila si efectele.
   ========================================================= */
import { useCallback, useEffect, useState } from 'react';
import { countTickets, allScans, putTickets, type CachedTicket } from './db';
import { duplicatesFrom, scanCode, type Duplicate, type ScanOutcome } from './scanEngine';
import { flushAll, pendingCount, type Poster, type SyncReport } from './sync';

export type OfflineState = {
  /** cate bilete sunt in cache-ul local */
  cached: number;
  /** cate scanuri asteapta trimiterea */
  pending: number;
  /** bilete validate de mai multe ori, descoperite dupa sincronizare */
  duplicates: Duplicate[];
};

const EMPTY: OfflineState = { cached: 0, pending: 0, duplicates: [] };

export function useOffline(eventId?: string) {
  const [state, setState] = useState<OfflineState>(EMPTY);
  const [busy, setBusy] = useState(false);

  const refresh = useCallback(async () => {
    try {
      const [cached, pending, scans] = await Promise.all([countTickets(eventId), pendingCount(), allScans()]);
      setState({ cached, pending, duplicates: duplicatesFrom(scans) });
    } catch {
      // IndexedDB indisponibil (mod privat, cota plina): ramanem pe zero,
      // aplicatia trebuie sa functioneze si fara cache local
      setState(EMPTY);
    }
  }, [eventId]);

  useEffect(() => {
    void refresh();
  }, [refresh]);

  /** Descarca inventarul pentru scanare fara internet. */
  const download = useCallback(
    async (load: () => Promise<CachedTicket[]>) => {
      if (!eventId) return 0;
      setBusy(true);
      try {
        const n = await putTickets(eventId, await load());
        await refresh();
        return n;
      } finally {
        setBusy(false);
      }
    },
    [eventId, refresh],
  );

  const scan = useCallback(
    async (code: string, gateId?: string | null): Promise<ScanOutcome | null> => {
      if (!eventId) return null;
      const out = await scanCode(code, { eventId, gateId });
      await refresh();
      return out;
    },
    [eventId, refresh],
  );

  const sync = useCallback(
    async (post: Poster): Promise<SyncReport> => {
      setBusy(true);
      try {
        const r = await flushAll(post);
        await refresh();
        return r;
      } finally {
        setBusy(false);
      }
    },
    [refresh],
  );

  return { ...state, busy, refresh, download, scan, sync };
}
