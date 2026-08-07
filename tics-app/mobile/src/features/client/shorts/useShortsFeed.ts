/* =========================================================
   Feed cursor pentru shorts.

   Contractul de paginare vine din API (keyset pe featured/published_at/id),
   deci aici nu se face nicio deduplicare "de siguranta" — daca ar aparea
   duplicate, ar fi un bug de server care trebuie sa se vada, nu sa fie mascat.

   `prefetchFrom` incarca pagina urmatoare cu cateva ecrane inainte de capat,
   ca scroll-ul sa nu se opreasca niciodata pe o rotita.
   ========================================================= */
import { useCallback, useEffect, useRef, useState } from 'react';
import { fetchShortsFeed, type ApiShort, type ShortFeedSegment } from '../../../api/shorts';

/** Cu cate short-uri inainte de capat cerem pagina urmatoare. */
const PREFETCH_THRESHOLD = 3;

export type ShortsFeedState = {
  items: ApiShort[];
  loading: boolean;
  /** Prima incarcare a esuat sau a venit goala — apelantul poate cadea pe fallback. */
  unavailable: boolean;
  error: string | null;
  loadMore: () => void;
  /** Anunta indexul curent; declanseaza prefetch-ul cand se apropie de capat. */
  onIndexChange: (index: number) => void;
  /** Actualizeaza un short in loc (dupa un toggle de like/save). */
  patch: (id: number, changes: Partial<ApiShort>) => void;
  reload: () => void;
};

export function useShortsFeed(feed: ShortFeedSegment = 'for_you', limit = 10): ShortsFeedState {
  const [items, setItems] = useState<ApiShort[]>([]);
  const [loading, setLoading] = useState(true);
  const [unavailable, setUnavailable] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const cursor = useRef<string | null>(null);
  const exhausted = useRef(false);
  const inFlight = useRef(false);
  const firstLoadDone = useRef(false);
  const abort = useRef<AbortController | null>(null);

  const load = useCallback(async () => {
    if (inFlight.current || exhausted.current) return;

    inFlight.current = true;
    setLoading(true);
    abort.current?.abort();
    abort.current = new AbortController();

    try {
      const page = await fetchShortsFeed({
        feed,
        cursor: cursor.current,
        limit,
        signal: abort.current.signal,
      });

      cursor.current = page.next_cursor;
      if (!page.next_cursor) exhausted.current = true;

      setItems((prev) => [...prev, ...page.items]);
      setError(null);

      if (!firstLoadDone.current) {
        firstLoadDone.current = true;
        setUnavailable(page.items.length === 0);
      }
    } catch (e) {
      if ((e as Error)?.name === 'AbortError') return;

      setError((e as Error)?.message ?? 'unknown');
      if (!firstLoadDone.current) {
        firstLoadDone.current = true;
        setUnavailable(true);
      }
    } finally {
      inFlight.current = false;
      setLoading(false);
    }
  }, [feed, limit]);

  const reload = useCallback(() => {
    cursor.current = null;
    exhausted.current = false;
    firstLoadDone.current = false;
    setItems([]);
    setUnavailable(false);
    void load();
  }, [load]);

  useEffect(() => {
    void load();

    return () => abort.current?.abort();
    // `load` e stabil pe (feed, limit) — reincarcam doar cand se schimba segmentul.
  }, [load]);

  const onIndexChange = useCallback(
    (index: number) => {
      if (index >= items.length - PREFETCH_THRESHOLD) void load();
    },
    [items.length, load],
  );

  const patch = useCallback((id: number, changes: Partial<ApiShort>) => {
    setItems((prev) => prev.map((item) => (item.id === id ? { ...item, ...changes } : item)));
  }, []);

  return {
    items,
    loading,
    unavailable,
    error,
    loadMore: () => void load(),
    onIndexChange,
    patch,
    reload,
  };
}
