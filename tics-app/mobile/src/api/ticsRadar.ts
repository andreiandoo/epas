/* =========================================================
   RADAR — sursa reala de date: TICS Radar (app.tics.ro).

   Radarul din prototip compara pretul aceluiasi eveniment pe mai multe
   platforme de ticketing. Exact asta agrega si app.tics.ro, deci ecranele
   Radar / Oferte / Calendar se leaga direct la el.

   API-ul public (verificat live):
     GET /api/public-stats
         -> {platforms, liveEvents, artists, venues, cities}
     GET /api/v1/events?page=&per_page=&city=&event_type=&q=
         -> {data:[...], meta:{current_page,last_page,total}}
     GET /api/v1/events/{id}
         -> {data:{..., platforms:[{platform_name, url, tickets:[...]}]}}

   TREI LIMITARI ale API-ului, si cum le tratam:

   1. per_page e plafonat la 50 (peste asta raspunde cu redirect, nu JSON).
   2. NU exista filtru de data si nici sortare configurabila: lista vine
      mereu crescator dupa `starts_at`, incepand din 2018. Primele pagini
      sunt istorie. Gasim inceputul viitorului printr-o cautare binara pe
      index (log2(6000) ~ 13 cereri de cate un element), rezultat pe care il
      tinem in cache pe zi — vezi upcomingOffset().
   3. Lista NU include platformele/preturile; alea vin doar din detaliu.
      Deci hidratam separat, in paralel, doar cardurile pe care le afisam.

   Ca peste tot in aplicatie, daca sursa cade ramanem cu datasetul
   prototipului, sa nu ajungem cu ecrane goale.
   ========================================================= */
import { TICS, galFor } from '../mock/prototype';

export const RADAR_ROOT = import.meta.env.VITE_TICS_RADAR ?? 'https://app.tics.ro';

/** maximul acceptat de API; peste el raspunsul nu mai e JSON */
const PER_PAGE = 50;
/** plafon de siguranta la paginare, ca sa nu plimbam sute de cereri */
const MAX_PAGES = 12;
/** luna e mai lunga decat o lista: august 2026 are ~700 de evenimente */
const MONTH_MAX_PAGES = 26;

/* ---------- forma bruta din API ---------- */
type ApiRadarEvent = {
  id: number;
  reference: string;
  title: string;
  event_type: string | null;
  genre: string | null;
  starts_at: string | null;
  ends_at: string | null;
  city: string | null;
  venue: string | null;
  artist_name: string | null;
  poster_url: string | null;
  status: string;
  platforms?: {
    platform_name: string;
    platform_slug: string;
    url: string;
    tickets: { name: string; price: string; total_price: string; currency: string; availability: string }[];
  }[];
};

type ListResponse = { data: ApiRadarEvent[]; meta: { current_page: number; last_page: number; total: number } };
type DetailResponse = { data: ApiRadarEvent };

/* ---------- forma pe care o consuma ecranele (cea din prototip) ---------- */
export type RadarOffer = [platform: string, price: number, stock: string];

export type RadarItem = {
  id: string;
  s: string;
  cat: string;
  city: string;
  venName: string;
  addr: string;
  day: string;
  mon: string;
  time: string;
  tone: string;
  g: string;
  stock: string;
  rat: string;
  desc: string;
  artists: string[];
  gallery: string[];
  offers: RadarOffer[];
  sc?: string;
  poster?: string | null;
  /** true daca vine din TICS Radar, false daca e din datasetul prototipului */
  live?: boolean;
  /** linkurile de cumparare, in aceeasi ordine ca `offers` */
  urls?: Record<string, string>;
};

/** Datasetul prototipului e generat din HTML si netipat — il citim prin unknown. */
export const PROTO_RADAR = TICS as unknown as Record<string, RadarItem>;

const MONTHS_RO = ['Ian', 'Feb', 'Mar', 'Apr', 'Mai', 'Iun', 'Iul', 'Aug', 'Sep', 'Oct', 'Noi', 'Dec'];

/** event_type-ul TICS -> categoria + scena + emoji ale prototipului */
const TYPE_MAP: Record<string, { cat: string; sc: string; g: string; tone: string }> = {
  concert: { cat: 'Concerte', sc: 'concert', g: '🎤', tone: 'linear-gradient(150deg,#4c1d95,#a78bfa)' },
  festival: { cat: 'Festival', sc: 'festival', g: '🎪', tone: 'linear-gradient(150deg,#1e1b4b,#7c3aed)' },
  theatre: { cat: 'Teatru', sc: 'theatre', g: '🎭', tone: 'linear-gradient(150deg,#3b0764,#7e22ce)' },
  theater: { cat: 'Teatru', sc: 'theatre', g: '🎭', tone: 'linear-gradient(150deg,#3b0764,#7e22ce)' },
  standup: { cat: 'Stand-up', sc: 'standup', g: '🎙', tone: 'linear-gradient(150deg,#4c1d95,#c084fc)' },
  comedy: { cat: 'Stand-up', sc: 'standup', g: '🎙', tone: 'linear-gradient(150deg,#4c1d95,#c084fc)' },
  dance: { cat: 'Petrecere', sc: 'party', g: '🕺', tone: 'linear-gradient(150deg,#6d28d9,#2a1065)' },
  party: { cat: 'Petrecere', sc: 'party', g: '🪩', tone: 'linear-gradient(150deg,#6d28d9,#2a1065)' },
  sport: { cat: 'Sport', sc: 'city', g: '🏟', tone: 'linear-gradient(150deg,#0f4c4a,#12b3a6)' },
  film: { cat: 'Film', sc: 'theatre', g: '🎬', tone: 'linear-gradient(150deg,#312e81,#6366f1)' },
  other: { cat: 'Altele', sc: 'city', g: '🎟', tone: 'linear-gradient(150deg,#2a2440,#4c1d95)' },
};

const typeInfo = (t: string | null) => TYPE_MAP[(t ?? '').toLowerCase()] ?? TYPE_MAP.other;

const STOCK_RO: Record<string, string> = {
  available: 'Disponibil',
  limited: 'Puține',
  last: 'Ultimele',
  sold_out: 'Sold out',
  soldout: 'Sold out',
  unknown: 'Verifică',
};

/**
 * `starts_at` vine ca ISO cu Z, dar orele sunt cele locale ale evenimentului
 * (19:00 inseamna 19:00 in sala, nu 19:00 UTC). Citim cu getterele UTC ca sa
 * afisam exact ce e stocat, fara sa mutam ora dupa fusul telefonului.
 */
function parts(iso: string | null) {
  if (!iso) return { day: '', mon: '', time: '', date: null as Date | null };
  const d = new Date(iso);
  if (Number.isNaN(d.getTime())) return { day: '', mon: '', time: '', date: null };
  const hh = d.getUTCHours();
  const mm = d.getUTCMinutes();
  return {
    day: String(d.getUTCDate()).padStart(2, '0'),
    mon: MONTHS_RO[d.getUTCMonth()],
    // 00:00 inseamna "ora nestiuta" in datele TICS, nu miezul noptii
    time: hh === 0 && mm === 0 ? '' : `${String(hh).padStart(2, '0')}:${String(mm).padStart(2, '0')}`,
    date: d,
  };
}

/** Cel mai mic pret pe platforma, cu eticheta de stoc a biletului respectiv. */
function toOffers(e: ApiRadarEvent): { offers: RadarOffer[]; urls: Record<string, string> } {
  const offers: RadarOffer[] = [];
  const urls: Record<string, string> = {};

  for (const p of e.platforms ?? []) {
    const tickets = (p.tickets ?? [])
      .map((t) => ({ price: Number(t.total_price ?? t.price), av: (t.availability ?? '').toLowerCase() }))
      .filter((t) => Number.isFinite(t.price) && t.price > 0);
    if (!tickets.length) continue;

    const cheapest = tickets.reduce((a, b) => (b.price < a.price ? b : a));
    offers.push([p.platform_name, Math.round(cheapest.price), STOCK_RO[cheapest.av] ?? 'Verifică']);
    if (p.url) urls[p.platform_name] = p.url;
  }

  return { offers: offers.sort((a, b) => a[1] - b[1]), urls };
}

export function normalizeRadar(e: ApiRadarEvent): RadarItem {
  const info = typeInfo(e.event_type);
  const p = parts(e.starts_at);
  const { offers, urls } = toOffers(e);

  const item: RadarItem = {
    id: String(e.id),
    s: e.title,
    cat: info.cat,
    city: e.city && e.city !== 'Necunoscut' ? e.city : '',
    venName: e.venue ?? '',
    addr: '',
    day: p.day,
    mon: p.mon,
    time: p.time,
    tone: info.tone,
    g: info.g,
    stock: offers[0]?.[2] ?? 'Verifică',
    rat: '—',
    desc: '',
    artists: [],
    gallery: [],
    offers,
    sc: info.sc,
    poster: e.poster_url ?? null,
    live: true,
    urls,
  };

  item.gallery = item.poster ? [`url('${item.poster}') center/cover, #14101f`, ...galFor(item)] : galFor(item);
  return item;
}

/* ---------- fetch cu timeout, ca UI-ul sa nu atarne ---------- */
const sleep = (ms: number) => new Promise((r) => setTimeout(r, ms));

/**
 * O scanare de luna inseamna ~20 de cereri la rand, iar app.tics.ro
 * limiteaza (429). Reincercam o data, cu pauza — altfel raman gauri in date
 * si, mai rau, le-am cacheda ca si cum ar fi complete.
 */
async function get<T>(path: string, ms = 8000, retries = 1): Promise<T | null> {
  for (let attempt = 0; ; attempt++) {
    const ctrl = new AbortController();
    const t = setTimeout(() => ctrl.abort(), ms);
    try {
      const res = await fetch(`${RADAR_ROOT}${path}`, { signal: ctrl.signal, headers: { Accept: 'application/json' } });
      if (res.ok) return (await res.json()) as T;
      if (res.status === 429 && attempt < retries) {
        clearTimeout(t);
        await sleep(1200 * (attempt + 1));
        continue;
      }
      return null;
    } catch {
      if (attempt < retries) {
        clearTimeout(t);
        await sleep(400);
        continue;
      }
      return null;
    } finally {
      clearTimeout(t);
    }
  }
}

const qs = (o: Record<string, string | number | undefined>) => {
  const u = new URLSearchParams();
  for (const [k, v] of Object.entries(o)) if (v !== undefined && v !== '') u.set(k, String(v));
  return u.toString();
};

/* =========================================================
   Unde incepe viitorul

   Lista e crescatoare dupa data si fara filtru de data, deci cautam prin
   injumatatire primul index cu `starts_at >= azi`. Cu per_page=1 fiecare
   sondaj costa un element, iar rezultatul il tinem pe zi in localStorage:
   in practica se face o singura data pe zi, per set de filtre.
   ========================================================= */
const todayStamp = () => new Date().toISOString().slice(0, 10);

function cacheGet(key: string): number | null {
  try {
    const raw = localStorage.getItem(key);
    if (!raw) return null;
    const v = JSON.parse(raw) as { stamp: string; offset: number };
    return v.stamp === todayStamp() ? v.offset : null;
  } catch {
    return null;
  }
}

function cacheSet(key: string, offset: number) {
  try {
    localStorage.setItem(key, JSON.stringify({ stamp: todayStamp(), offset }));
  } catch {
    /* fara cache, doar mai lent */
  }
}

/** Prima secunda a zilei de azi, in aceeasi conventie ca `parts()`. */
function todayUtc() {
  const n = new Date();
  return Date.UTC(n.getFullYear(), n.getMonth(), n.getDate());
}

/* Doua cautari pe aceeasi lista (ziua de azi pentru Radar, intai de luna
   pentru Calendar) ating multi indecsi comuni — le tinem, ca sa nu platim
   aceeasi cerere de doua ori intr-un minut, cu limitarea de rata a API-ului. */
const probeCache = new Map<string, ListResponse | null>();

async function probe(filters: Record<string, string | undefined>, page: number) {
  const k = `${qs(filters)}#${page}`;
  if (probeCache.has(k)) return probeCache.get(k)!;
  const r = await get<ListResponse>(`/api/v1/events?${qs({ ...filters, per_page: 1, page })}`);
  if (r) probeCache.set(k, r);
  return r;
}

const isFuture = (e: ApiRadarEvent, from: number) => {
  const d = e.starts_at ? new Date(e.starts_at) : null;
  return !!d && !Number.isNaN(d.getTime()) && d.getTime() >= from;
};

/** Indexul (0-based) primului eveniment care nu a trecut inca, sau null. */
async function upcomingOffset(filters: Record<string, string | undefined>, from = todayUtc()): Promise<number | null> {
  // `from` INTRA in cheie: calendarul cauta inceputul unei luni, lista cauta
  // ziua de azi — acelasi set de filtre, indecsi complet diferiti.
  const key = `tics.radar.offset:${from}:${qs(filters)}`;
  const cached = cacheGet(key);
  if (cached !== null) return cached;

  const first = await probe(filters, 1);
  const total = first?.meta?.total ?? 0;
  if (!total || !first?.data?.length) return null;
  if (isFuture(first.data[0], from)) {
    cacheSet(key, 0);
    return 0;
  }

  let lo = 0;
  let hi = total - 1;
  let ans = total; // total = nimic in viitor
  while (lo <= hi) {
    const mid = (lo + hi) >> 1;
    const r = await probe(filters, mid + 1);
    if (!r?.data?.length) {
      hi = mid - 1;
      continue;
    }
    if (isFuture(r.data[0], from)) {
      ans = mid;
      hi = mid - 1;
    } else {
      lo = mid + 1;
    }
  }

  if (ans >= total) return null;
  cacheSet(key, ans);
  return ans;
}

/** Evenimente de la un index incolo, paginand cat e nevoie. */
async function fromOffset(filters: Record<string, string | undefined>, offset: number, want: number) {
  const out: ApiRadarEvent[] = [];
  let page = Math.floor(offset / PER_PAGE) + 1;
  let skip = offset % PER_PAGE;

  for (let i = 0; i < MAX_PAGES && out.length < want; i++) {
    const r = await get<ListResponse>(`/api/v1/events?${qs({ ...filters, per_page: PER_PAGE, page })}`);
    if (!r?.data?.length) break;
    out.push(...r.data.slice(skip));
    skip = 0;
    if (page >= r.meta.last_page) break;
    page++;
  }
  return out.slice(0, want);
}

/* ---------- hidratare: platformele vin doar din detaliu ---------- */
const detailCache = new Map<string, RadarItem>();

export async function fetchRadarEvent(id: string): Promise<RadarItem | null> {
  const hit = detailCache.get(id);
  if (hit) return hit;

  const proto = PROTO_RADAR[id];
  if (proto) return { ...proto, live: false };

  const r = await get<DetailResponse>(`/api/v1/events/${encodeURIComponent(id)}`);
  if (!r?.data) return null;
  const item = normalizeRadar(r.data);
  detailCache.set(id, item);
  return item;
}

/** Cereri in paralel, dar in valuri, ca sa nu deschidem 20 de conexiuni. */
async function hydrate(events: ApiRadarEvent[], concurrency = 6): Promise<RadarItem[]> {
  const out: RadarItem[] = [];
  for (let i = 0; i < events.length; i += concurrency) {
    const wave = events.slice(i, i + concurrency);
    const done = await Promise.all(
      wave.map(async (e) => {
        const cached = detailCache.get(String(e.id));
        if (cached) return cached;
        const r = await get<DetailResponse>(`/api/v1/events/${e.id}`);
        const item = normalizeRadar(r?.data ?? e);
        detailCache.set(item.id, item);
        return item;
      })
    );
    out.push(...done);
  }
  return out;
}

/**
 * Completeaza cu platformele/preturile care lipsesc din payload-ul de lista.
 * Folosita pentru listele scurte (ziua din calendar), nu pentru toata luna.
 */
export async function withOffers(items: RadarItem[], concurrency = 4): Promise<RadarItem[]> {
  const out: RadarItem[] = [];
  for (let i = 0; i < items.length; i += concurrency) {
    const wave = items.slice(i, i + concurrency);
    out.push(
      ...(await Promise.all(
        wave.map(async (it) => {
          if (it.offers.length || !it.live) return it;
          const cached = detailCache.get(it.id);
          if (cached) return cached;
          const r = await get<DetailResponse>(`/api/v1/events/${it.id}`);
          if (!r?.data) return it;
          const full = normalizeRadar(r.data);
          detailCache.set(full.id, full);
          return full;
        })
      ))
    );
  }
  return out;
}

/* ---------- datasetul prototipului, ca plasa de siguranta ---------- */
const protoItems = (): RadarItem[] =>
  Object.values(PROTO_RADAR).map((t) => ({ ...t, live: false }));

/* =========================================================
   API-ul folosit de ecrane
   ========================================================= */

export type RadarStats = { platforms: number; liveEvents: number; artists: number; venues: number; cities: number };

export async function fetchRadarStats(): Promise<RadarStats | null> {
  return get<RadarStats>('/api/public-stats');
}

/**
 * Lista Radar: evenimente viitoare, cu preturile de pe fiecare platforma.
 * Cele cu mai multe platforme urca primele — comparatia de pret e tot rostul
 * ecranului, iar un eveniment cu o singura oferta nu are ce compara.
 */
export async function fetchRadarList(
  opts: { limit?: number; city?: string; type?: string; search?: string } = {}
): Promise<{ items: RadarItem[]; source: 'tics' | 'prototype' }> {
  const limit = opts.limit ?? 6;
  const filters = { city: opts.city, event_type: opts.type, q: opts.search };

  // cautarea are propriul set de rezultate, deja restrans: nu mai sarim in viitor
  const offset = opts.search ? 0 : await upcomingOffset(filters);
  if (offset === null) return { items: protoItems(), source: 'prototype' };

  /* Luam ceva mai multe decat afisam, ca sa avem din ce alege mai jos — dar
     nu mult mai multe: fiecare candidat costa o cerere de detaliu, iar
     app.tics.ro limiteaza la ~60 pe minut. */
  const raw = await fromOffset(filters, offset, Math.min(limit * 2, 12));
  if (!raw.length) return { items: protoItems(), source: 'prototype' };

  const hydrated = await hydrate(raw);
  const usable = hydrated.filter((i) => i.offers.length > 0);
  if (!usable.length) return { items: protoItems(), source: 'prototype' };

  usable.sort((a, b) => b.offers.length - a.offers.length);
  return { items: usable.slice(0, limit), source: 'tics' };
}

/* =========================================================
   Calendar — cate evenimente sunt in fiecare zi a lunii

   Paginam de la prima zi a lunii pana trecem de ultima. Cu ~50 pe pagina si
   plafonul MAX_PAGES acoperim ~600 de evenimente pe luna; daca luna e mai
   plina, `capped` spune ca numaratoarea e partiala, ca ecranul sa nu afirme
   un total pe care nu l-a vazut.
   ========================================================= */
export type MonthData = {
  counts: Record<number, number>;
  byDay: Record<number, RadarItem[]>;
  total: number;
  capped: boolean;
};

const monthCache = new Map<string, MonthData>();

export async function fetchRadarMonth(year: number, month: number): Promise<MonthData | null> {
  const key = `${year}-${month}`;
  const hit = monthCache.get(key);
  if (hit) return hit;

  /* O luna costa pana la 20 de cereri, deci o tinem si peste reporniri —
     o zi, cat sa prindem evenimentele adaugate intre timp. */
  const lsKey = `tics.radar.month.v2:${key}`;
  try {
    const raw = localStorage.getItem(lsKey);
    if (raw) {
      const v = JSON.parse(raw) as { stamp: string; data: MonthData };
      if (v.stamp === todayStamp()) {
        monthCache.set(key, v.data);
        return v.data;
      }
    }
  } catch {
    /* cache stricat: il ignoram si reincarcam */
  }

  const start = Date.UTC(year, month, 1);
  const end = Date.UTC(year, month + 1, 1);
  const offset = await upcomingOffset({}, start);
  if (offset === null) return null;

  const counts: Record<number, number> = {};
  const byDay: Record<number, RadarItem[]> = {};
  let total = 0;
  let page = Math.floor(offset / PER_PAGE) + 1;
  let skip = offset % PER_PAGE;
  let capped = true;
  /* O pagina cazuta (429 chiar si dupa reincercare) inseamna date cu gauri:
     le aratam, dar NU le salvam ca si cum ar fi luna completa. */
  let incomplete = false;

  for (let i = 0; i < MONTH_MAX_PAGES; i++) {
    const r = await get<ListResponse>(`/api/v1/events?${qs({ per_page: PER_PAGE, page })}`);
    if (!r?.data?.length) {
      if (r === null) incomplete = true;
      else capped = false;
      break;
    }

    let passedMonth = false;
    for (const e of r.data.slice(skip)) {
      const d = e.starts_at ? new Date(e.starts_at) : null;
      if (!d || Number.isNaN(d.getTime())) continue;
      const t = d.getTime();
      if (t >= end) {
        passedMonth = true;
        break;
      }
      if (t < start) continue;

      const day = d.getUTCDate();
      counts[day] = (counts[day] ?? 0) + 1;
      total++;
      // pastram doar cateva pe zi: ecranul afiseaza o lista scurta
      if ((byDay[day] ??= []).length < 4) byDay[day].push(normalizeRadar(e));
    }

    skip = 0;
    if (passedMonth || page >= r.meta.last_page) {
      capped = false;
      break;
    }
    page++;
    // ritmam scanarea ca sa nu intram in limitarea de rata a API-ului
    await sleep(150);
  }

  const data: MonthData = { counts, byDay, total, capped: capped || incomplete };
  monthCache.set(key, data);
  if (!incomplete) {
    try {
      localStorage.setItem(lsKey, JSON.stringify({ stamp: todayStamp(), data }));
    } catch {
      /* localStorage plin: mergem doar cu cache-ul din memorie */
    }
  }
  return data;
}
