/* =========================================================
   Telemetrie batched pentru feed-ul de shorts.

   Scopul: un scroll rapid prin 30 de short-uri nu trebuie sa insemne 90 de
   cereri HTTP. Evenimentele se acumuleaza intr-o coada si pleaca:
     - la fiecare FLUSH_MS,
     - cand coada atinge MAX_QUEUE,
     - cand app-ul trece in fundal (visibilitychange / pagehide),
     - la demontare.

   Ultimul lot pleaca pe sendBeacon, altfel s-ar pierde exact evenimentele
   care conteaza cel mai mult (watch_ratio la iesire).
   ========================================================= */
import { useCallback, useEffect, useRef } from 'react';
import { sendShortEvents, type ShortTelemetryEvent } from '../../../api/shorts';

const FLUSH_MS = 5000;
const MAX_QUEUE = 25;

export function useShortTelemetry() {
  const queue = useRef<ShortTelemetryEvent[]>([]);
  const timer = useRef<ReturnType<typeof setInterval> | null>(null);

  const flush = useCallback((useBeacon = false) => {
    if (queue.current.length === 0) return;

    const batch = queue.current;
    queue.current = [];
    void sendShortEvents(batch, useBeacon);
  }, []);

  const track = useCallback(
    (event: ShortTelemetryEvent) => {
      queue.current.push(event);
      if (queue.current.length >= MAX_QUEUE) flush();
    },
    [flush],
  );

  useEffect(() => {
    timer.current = setInterval(() => flush(), FLUSH_MS);

    // Trecerea in fundal e cel mai probabil moment de "moarte" a paginii
    // intr-un WebView — golim coada cu beacon ca sa nu pierdem lotul.
    const onHide = () => {
      if (document.visibilityState === 'hidden') flush(true);
    };
    const onPageHide = () => flush(true);

    document.addEventListener('visibilitychange', onHide);
    window.addEventListener('pagehide', onPageHide);

    return () => {
      document.removeEventListener('visibilitychange', onHide);
      window.removeEventListener('pagehide', onPageHide);
      if (timer.current) clearInterval(timer.current);
      flush(true);
    };
  }, [flush]);

  return { track, flush };
}
