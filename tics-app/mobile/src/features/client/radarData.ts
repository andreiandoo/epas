/* =========================================================
   Hook-uri pentru ecranele Radar, peste api/ticsRadar.

   Regula, ca peste tot: ecranul porneste cu datasetul prototipului, ca sa
   arate corect din prima cadra, si il inlocuieste cand raspunde app.tics.ro.
   Daca sursa cade, ramane pe prototip — niciun ecran gol.
   ========================================================= */
import { useEffect, useState } from 'react';
import type { UiEvent } from '../../api/tenantClient';
import {
  CITY_FALLBACK,
  fetchRadarCategories,
  fetchRadarCities,
  fetchRadarEvent,
  fetchRadarList,
  fetchRadarMonth,
  fetchRadarStats,
  PROTO_RADAR,
  withOffers,
  type MonthData,
  type MonthQuery,
  type RadarCategory,
  type RadarItem,
  type RadarQuery,
  type RadarStats,
} from '../../api/ticsRadar';


/**
 * Lista Radar. `q` poate contine oras, tip, gen, interval si praguri —
 * vezi RadarQuery. Se refetch-uieste ori de cate ori se schimba filtrele.
 */
export function useRadarList(q: RadarQuery = {}) {
  const limit = q.limit ?? 6;
  /* Pornim GOL, nu cu datasetul prototipului: pana raspundea API-ul se vedeau
     ~1 secunda evenimente demo, care apoi sareau. Ecranul arata schelete cat
     timp `loading` e true. Prototipul ramane doar ca raspuns final, cand
     sursa nefiltrata nu da nimic. */
  const [items, setItems] = useState<RadarItem[]>([]);
  const [source, setSource] = useState<'tics' | 'prototype'>('tics');
  const [hasMore, setHasMore] = useState(false);
  const [loading, setLoading] = useState(true);

  /* Cheia serializeaza filtrele: obiectul e recreat la fiecare randare, deci
     nu poate sta direct in lista de dependinte fara bucla infinita. */
  const key = JSON.stringify([limit, q.city, q.type, q.catKey, q.genre, q.search, q.when, q.maxPrice, q.scarce, q.day, q.offset]);

  useEffect(() => {
    let alive = true;
    setLoading(true);
    fetchRadarList({ ...q, limit })
      .then((r) => {
        if (!alive) return;
        setItems(r.items);
        setSource(r.source);
        setHasMore(r.hasMore);
      })
      .finally(() => alive && setLoading(false));
    return () => {
      alive = false;
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [key]);

  return { items, source, loading, hasMore };
}

/** Categoriile reale, cu exemple din care ecranul isi ia imaginile de card. */
export function useRadarCategories(city?: string, cities?: string[]) {
  const [cats, setCats] = useState<RadarCategory[]>([]);
  /* Cheia din sir, nu tabloul: un tablou nou la fiecare randare ar reporni
     efectul la nesfarsit. */
  const key = (cities ?? []).join('|');

  useEffect(() => {
    let alive = true;
    fetchRadarCategories(city, key ? key.split('|') : undefined).then((c) => alive && setCats(c));
    return () => {
      alive = false;
    };
  }, [city, key]);

  return cats;
}

/**
 * Orasele in care chiar se intampla ceva, dupa cate evenimente au.
 * Pornim de la o lista fixa, ca selectorul sa nu apara gol: lista reala cere
 * cautarea binara + cateva pagini si poate intarzia cateva secunde.
 */
export function useRadarCities() {
  const [cities, setCities] = useState<string[]>(CITY_FALLBACK);
  useEffect(() => {
    let alive = true;
    fetchRadarCities().then((c) => alive && setCities(c));
    return () => {
      alive = false;
    };
  }, []);
  return cities;
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

export function useRadarMonth(year: number, month: number, q: MonthQuery = {}) {
  const [data, setData] = useState<MonthData | null>(null);
  const [loading, setLoading] = useState(true);
  const key = JSON.stringify([year, month, q.city, q.type, q.genre]);

  useEffect(() => {
    let alive = true;
    setLoading(true);
    setData(null);
    fetchRadarMonth(year, month, q)
      .then((d) => alive && setData(d))
      .finally(() => alive && setLoading(false));
    return () => {
      alive = false;
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [key]);

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


/**
 * Un eveniment din Radar, in forma pe care o consuma cardurile de pe Acasa.
 *
 * Cardurile sunt porturi din prototip si citesc cheile scurte de acolo
 * (`s`, `d`, `from`, `ven`...). Radarul are alte nume si un pret care sta in
 * lista de oferte, nu intr-un camp — de aici traducerea.
 *
 * `ven` primeste NUMELE salii, nu un id: cardurile cauta intai in VEN-ul
 * prototipului si cad pe valoarea bruta, deci un id ar fi ajuns pe ecran.
 */
export function radarToUi(r: RadarItem): UiEvent {
  return {
    id: r.id,
    s: r.s,
    t: r.s,
    type: r.cat === 'Experiențe' ? 'experience' : 'event',
    cat: r.cat,
    city: r.city,
    ven: r.venName,
    d: [r.day, r.mon].filter(Boolean).join(' '),
    day: r.day,
    mon: r.mon,
    time: r.time,
    // pretul de start e cea mai ieftina oferta gasita de Radar
    from: r.offers[0]?.[1] ?? 0,
    tone: r.tone,
    g: r.g,
    rat: r.rat,
    sc: r.sc,
    poster: r.poster,
    live: true,
    // Radarul agrega preturi, nu galerii; sectiunile care le cer se ascund
    gallery: [],
    artists: [],
    /* Marcheaza provenienta: cardurile trebuie sa duca la ecranul de oferte
       (`ticsoffers`), nu la fisa unui eveniment din catalogul nostru — sunt
       lumi diferite, cu id-uri diferite. */
    radar: true,
  } as unknown as UiEvent;
}
