/* =========================================================
   Golirea automată a cozii.

   Fara asta, scanurile se adunau local si nu pleca nimeni sa le trimita —
   motorul avea `flushAll`, dar nimeni nu-l chema. La o poarta, asta inseamna
   ca reconcilierea nu se intampla niciodata.

   Trei declansatoare, fiindca niciunul singur nu ajunge:
     - revenirea online (evenimentul `online`)
     - revenirea aplicatiei in prim-plan (telefonul a stat in buzunar)
     - un ritm de siguranta, pentru cazul in care conexiunea revine fara ca
       browserul sa anunte (se intampla pe mobil mai des decat ar trebui)
   ========================================================= */
import { useEffect, useRef } from 'react';
import { flushAll, pendingCount, type Poster, type SyncReport } from './sync';

const HEARTBEAT_MS = 60_000;

export function useAutoSync(post: Poster, onReport?: (r: SyncReport) => void) {
  /* Tinem callback-ul intr-un ref: altfel fiecare randare a parintelui ar
     rearma listenerele si ritmul. */
  const cb = useRef(onReport);
  cb.current = onReport;

  const posting = useRef(post);
  posting.current = post;

  useEffect(() => {
    let stopped = false;
    let running = false;

    const flush = async (reason: string) => {
      if (stopped || running) return;
      if (typeof navigator !== 'undefined' && navigator.onLine === false) return;
      // nu deschidem o cerere degeaba cand n-avem ce trimite
      if ((await pendingCount()) === 0) return;

      running = true;
      try {
        const r = await flushAll(posting.current);
        if (!stopped && (r.sent || r.failed || r.corrections.length)) {
          cb.current?.(r);
        }
      } catch {
        /* o sincronizare esuata nu trebuie sa opreasca aplicatia */
      } finally {
        running = false;
        void reason;
      }
    };

    const onOnline = () => void flush('online');
    const onVisible = () => {
      if (document.visibilityState === 'visible') void flush('foreground');
    };

    window.addEventListener('online', onOnline);
    document.addEventListener('visibilitychange', onVisible);
    const beat = setInterval(() => void flush('heartbeat'), HEARTBEAT_MS);

    // o incercare la montare, ca sa nu asteptam primul declansator
    void flush('mount');

    return () => {
      stopped = true;
      window.removeEventListener('online', onOnline);
      document.removeEventListener('visibilitychange', onVisible);
      clearInterval(beat);
    };
  }, []);
}
