/* =========================================================
   Hook-uri pentru ecranele Radar, peste api/ticsRadar.

   Regula, ca peste tot: ecranul porneste cu datasetul prototipului, ca sa
   arate corect din prima cadra, si il inlocuieste cand raspunde app.tics.ro.
   Daca sursa cade, ramane pe prototip — niciun ecran gol.
   ========================================================= */
import { useEffect, useState } from 'react';
import {
  fetchRadarEvent,
  fetchRadarList,
  fetchRadarMonth,
  fetchRadarStats,
  PROTO_RADAR,
  withOffers,
  type MonthData,
  type RadarItem,
  type RadarStats,
} from '../../api/ticsRadar';

const protoItems = (): RadarItem[] => Object.values(PROTO_RADAR).map((t) => ({ ...t, live: false }));

export function useRadarList(limit = 6) {
  const [items, setItems] = useState<RadarItem[]>(() => {
    const p = protoItems();
    // prototipul are 3 evenimente, ecranul afiseaza 6 — le repetam ca in prototip
    return [...p, ...p].slice(0, limit);
  });
  const [source, setSource] = useState<'tics' | 'prototype'>('prototype');
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    let alive = true;
    fetchRadarList({ limit })
      .then((r) => {
        if (!alive || r.source !== 'tics') return;
        setItems(r.items);
        setSource('tics');
      })
      .finally(() => alive && setLoading(false));
    return () => {
      alive = false;
    };
  }, [limit]);

  return { items, source, loading };
}

export function useRadarStats() {
  const [stats, setStats] = useState<RadarStats | null>(null);
  useEffect(() => {
    let alive = true;
    fetchRadarStats().then((s) => alive && s && setStats(s));
    return () => {
      alive = false;
    };
  }, []);
  return stats;
}

export function useRadarEvent(id?: string) {
  const fallback = PROTO_RADAR[id ?? ''] ?? PROTO_RADAR.smiley;
  const [item, setItem] = useState<RadarItem>(fallback);

  useEffect(() => {
    let alive = true;
    setItem(fallback);
    if (!id) return;
    // fara nicio oferta ecranul n-are ce compara — ramanem pe ce aveam
    fetchRadarEvent(id).then((r) => alive && r?.offers.length && setItem(r));
    return () => {
      alive = false;
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [id]);

  return item;
}

export function useRadarMonth(year: number, month: number) {
  const [data, setData] = useState<MonthData | null>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    let alive = true;
    setLoading(true);
    setData(null);
    fetchRadarMonth(year, month)
      .then((d) => alive && setData(d))
      .finally(() => alive && setLoading(false));
    return () => {
      alive = false;
    };
  }, [year, month]);

  return { data, loading };
}

/**
 * Evenimentele unei zile, cu preturile aduse la cerere. Lista lunii vine fara
 * platforme (API-ul le da doar in detaliu), asa ca hidratam doar ziua deschisa
 * — cateva cereri, nu cateva sute.
 */
export function useRadarDay(items: RadarItem[]) {
  const [full, setFull] = useState<RadarItem[]>(items);

  const key = items.map((i) => i.id).join(',');
  useEffect(() => {
    let alive = true;
    setFull(items);
    if (!items.length) return;
    withOffers(items).then((r) => alive && setFull(r));
    return () => {
      alive = false;
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [key]);

  return full;
}

/** 3450 -> "3.4k"; sub 1000 ramane cifra exacta. */
export const fmtK = (n: number) => (n >= 1000 ? `${(n / 1000).toFixed(1).replace('.0', '')}k` : String(n));
