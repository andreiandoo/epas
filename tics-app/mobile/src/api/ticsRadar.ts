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
      sunt istorie. Gasim inceputul viitorului printr-o cautare binara peste
      pagini (~7 cereri), cu rezultatul tinut in cache pe zi — vezi
      upcomingOffset().
   3. Lista NU include platformele/preturile; alea vin doar din detaliu.
      Deci hidratam separat, in paralel, doar cardurile pe care le afisam.

   Ca peste tot in aplicatie, daca sursa cade ramanem cu datasetul
   prototipului, sa nu ajungem cu ecrane goale.
   ========================================================= */
import { TICS } from '../mock/prototype';
import { feedCategories, feedCities, feedList, feedMonth } from './radarFeed';

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

/** O categorie de bilet a unei platforme — asa cum e listata pe app.tics.ro. */
export type RadarTicket = { name: string; price: number; stock: string };

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
  /** toate categoriile de bilet, per platforma; cheia e numele platformei */
  tickets: Record<string, RadarTicket[]>;
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
  kids: { cat: 'Copii', sc: 'theatre', g: '🧸', tone: 'linear-gradient(150deg,#7e22ce,#f0abfc)' },
  'food-drink': { cat: 'Food & drink', sc: 'wine', g: '🍷', tone: 'linear-gradient(150deg,#4c1d95,#b45309)' },
  charity: { cat: 'Caritate', sc: 'city', g: '💜', tone: 'linear-gradient(150deg,#3b0764,#a78bfa)' },
  workshop: { cat: 'Workshop', sc: 'city', g: '🛠', tone: 'linear-gradient(150deg,#1e3a5f,#3b82f6)' },
  musical: { cat: 'Musical', sc: 'theatre', g: '🎼', tone: 'linear-gradient(150deg,#4c1d95,#ec4899)' },
  conference: { cat: 'Conferințe', sc: 'city', g: '🎧', tone: 'linear-gradient(150deg,#0f4c4a,#0e7490)' },
  circus: { cat: 'Circ', sc: 'festival', g: '🎪', tone: 'linear-gradient(150deg,#b45309,#f59e0b)' },
  wellness: { cat: 'Wellness', sc: 'nature', g: '🧘', tone: 'linear-gradient(150deg,#065f46,#22c55e)' },
  classical: { cat: 'Clasic', sc: 'theatre', g: '🎻', tone: 'linear-gradient(150deg,#312e81,#818cf8)' },
  other: { cat: 'Altele', sc: 'city', g: '🎟', tone: 'linear-gradient(150deg,#2a2440,#4c1d95)' },
};

const typeInfo = (t: string | null) => TYPE_MAP[(t ?? '').toLowerCase()] ?? TYPE_MAP.other;

/**
 * Categoriile din "Alege un vibe" -> `event_type`-ul TICS.
 *
 * ATENTIE la proportii, masurate pe date reale (esantion de 200 de
 * evenimente viitoare): `event_type` e 'other' in ~70% din cazuri, iar
 * `genre` e null in ~78%. Un filtru pe tip ascunde deci majoritatea
 * catalogului — de aia "Toate" ramane implicit peste tot.
 * Tipuri intalnite: other, theater, concert, festival, kids, sport,
 * standup, food-drink, party, dance, charity.
 */
export const CAT_TO_TYPE: Record<string, string> = {
  Concerte: 'concert',
  Teatru: 'theater',
  Festival: 'festival',
  'Stand-up': 'standup',
  Petrecere: 'party',
  Sport: 'sport',
  Copii: 'kids',
  Film: 'other',
  Experiențe: 'food-drink',
};

/** Tipurile oferite in filtrul de tip, cu eticheta lor. */
export const TYPE_OPTIONS: [value: string, label: string][] = [
  ['', 'Toate tipurile'],
  ['concert', 'Concerte'],
  ['theater', 'Teatru'],
  ['festival', 'Festivaluri'],
  ['standup', 'Stand-up'],
  ['party', 'Petreceri'],
  ['dance', 'Dance'],
  ['sport', 'Sport'],
  ['kids', 'Copii'],
  ['food-drink', 'Food & drink'],
  ['other', 'Altele'],
];

/** Genurile cele mai frecvente. Filtrul e client-side: API-ul ignora `genre`. */
export const GENRE_OPTIONS: [value: string, label: string][] = [
  ['', 'Toate genurile'],
  ['electronic', 'Electronic'],
  ['pop', 'Pop'],
  ['rock', 'Rock'],
  ['manele', 'Manele'],
  ['classical', 'Clasic'],
  ['jazz', 'Jazz'],
  ['folk', 'Folk'],
  ['hip-hop', 'Hip-hop'],
];

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
function toOffers(e: ApiRadarEvent): {
  offers: RadarOffer[];
  urls: Record<string, string>;
  tickets: Record<string, RadarTicket[]>;
} {
  const offers: RadarOffer[] = [];
  const urls: Record<string, string> = {};
  const tickets: Record<string, RadarTicket[]> = {};

  for (const p of e.platforms ?? []) {
    const rows = (p.tickets ?? [])
      .map((t) => ({
        name: (t.name ?? '').trim() || 'Bilet',
        price: Number(t.total_price ?? t.price),
        av: (t.availability ?? '').toLowerCase(),
      }))
      .filter((t) => Number.isFinite(t.price) && t.price > 0);
    if (!rows.length) continue;

    const cheapest = rows.reduce((a, b) => (b.price < a.price ? b : a));
    offers.push([p.platform_name, Math.round(cheapest.price), STOCK_RO[cheapest.av] ?? 'Verifică']);
    if (p.url) urls[p.platform_name] = p.url;

    /* Toate categoriile, nu doar cea mai ieftina: pe app.tics.ro se pot
       desfasura, iar pretul de start singur nu spune ce cumperi. Sortate
       crescator, ca randul de sus sa fie chiar cel din capul listei. */
    tickets[p.platform_name] = rows
      .map((t) => ({ name: t.name, price: Math.round(t.price), stock: STOCK_RO[t.av] ?? 'Verifică' }))
      .sort((a, b) => a.price - b.price);
  }

  return { offers: offers.sort((a, b) => a[1] - b[1]), urls, tickets };
}

export function normalizeRadar(e: ApiRadarEvent): RadarItem {
  const info = typeInfo(e.event_type);
  const p = parts(e.starts_at);
  const { offers, urls, tickets } = toOffers(e);

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
    /* API-ul da un singur nume, si acela adesea gol — nu o lista de artisti.
       Il aratam cand exista, in loc sa lasam sectiunea goala. */
    artists: e.artist_name ? [e.artist_name] : [],
    gallery: [],
    offers,
    tickets,
    sc: info.sc,
    poster: e.poster_url ?? null,
    live: true,
    urls,
  };

  /* TICS Radar da un singur poster per eveniment. Nu-l completam cu scene
     procedurale: o "galerie" din poza reala + trei desene ar fi o minciuna
     vizuala. Ecranul ascunde sectiunea cand nu are ce arata. */
  item.gallery = item.poster ? [`url('${item.poster}') center/cover, #14101f`] : [];
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
   injumatatire prima PAGINA care incepe in viitor, apoi pozitia exacta in
   pagina de dinaintea ei. Rezultatul se tine pe zi in localStorage, deci in
   practica se face o singura data pe zi, per set de filtre.
   ========================================================= */
const todayStamp = () => new Date().toISOString().slice(0, 10);

/* Retinem si `total`: e util pentru diagnosticare si tine cheia de cache
   auto-descriptiva. Offsetul e valabil o zi. */
function cacheGet(key: string): { offset: number; total: number } | null {
  try {
    const raw = localStorage.getItem(key);
    if (!raw) return null;
    const v = JSON.parse(raw) as { stamp: string; offset: number; total?: number };
    return v.stamp === todayStamp() ? { offset: v.offset, total: v.total ?? 0 } : null;
  } catch {
    return null;
  }
}

function cacheSet(key: string, offset: number, total: number) {
  try {
    localStorage.setItem(key, JSON.stringify({ stamp: todayStamp(), offset, total }));
  } catch {
    /* fara cache, doar mai lent */
  }
}

/** Prima secunda a zilei de azi, in aceeasi conventie ca `parts()`. */
function todayUtc() {
  const n = new Date();
  return Date.UTC(n.getFullYear(), n.getMonth(), n.getDate());
}

/* Paginile deja aduse. Cautarea de mai jos si listarea propriu-zisa ating
   aceleasi pagini, iar API-ul limiteaza la ~60 de cereri pe minut — deci
   fiecare pagina se aduce o singura data pe sesiune. */
const pageCache = new Map<string, ListResponse>();

async function getPage(filters: Record<string, string | undefined>, page: number) {
  const k = `${qs(filters)}#${page}`;
  const hit = pageCache.get(k);
  if (hit) return hit;
  const r = await get<ListResponse>(`/api/v1/events?${qs({ ...filters, per_page: PER_PAGE, page })}`);
  if (r) pageCache.set(k, r);
  return r;
}

const isFuture = (e: ApiRadarEvent, from: number) => {
  const d = e.starts_at ? new Date(e.starts_at) : null;
  return !!d && !Number.isNaN(d.getTime()) && d.getTime() >= from;
};

/**
 * Indexul (0-based) primului eveniment care nu a trecut inca, sau null.
 *
 * Cautam peste PAGINI de cate 50, nu peste elemente: ~121 de pagini in loc de
 * ~6000 de pozitii inseamna log2(121) ~ 7 cereri in loc de 13, iar paginile
 * atinse raman in cache si le refoloseste chiar listarea de dupa.
 *
 * (Am incercat si o cautare pornita dintr-o estimare proportionala, plecand de
 * la lista nefiltrata. Masurat pe date reale nu se plateste: 12 -> 9 cereri
 * pentru Bucuresti, dar 10 -> 11 pentru concerte, fiindca subseturile nu sunt
 * distribuite in timp la fel ca intregul.)
 */
async function upcomingOffset(filters: Record<string, string | undefined>, from = todayUtc()): Promise<number | null> {
  // `from` INTRA in cheie: calendarul cauta inceputul unei luni, lista cauta
  // ziua de azi — acelasi set de filtre, indecsi complet diferiti.
  const key = `tics.radar.offset:${from}:${qs(filters)}`;
  const cached = cacheGet(key);
  if (cached) return cached.offset;

  const first = await getPage(filters, 1);
  if (!first?.data?.length) return null;
  const total = first.meta.total;
  const last = first.meta.last_page;

  const inFirst = first.data.findIndex((e) => isFuture(e, from));
  if (inFirst >= 0) {
    cacheSet(key, inFirst, total);
    return inFirst;
  }

  /* prima pagina care INCEPE in viitor; granita e atunci in pagina de dinaintea ei */
  let lo = 2;
  let hi = last;
  let firstFuturePage = last + 1;
  while (lo <= hi) {
    const mid = (lo + hi) >> 1;
    const r = await getPage(filters, mid);
    if (!r?.data?.length) {
      hi = mid - 1;
      continue;
    }
    if (isFuture(r.data[0], from)) {
      firstFuturePage = mid;
      hi = mid - 1;
    } else {
      lo = mid + 1;
    }
  }
  if (firstFuturePage > last) return null;

  const target = Math.max(1, firstFuturePage - 1);
  const r = await getPage(filters, target);
  const i = r?.data?.findIndex((e) => isFuture(e, from)) ?? -1;
  const ans = i >= 0 ? (target - 1) * PER_PAGE + i : (firstFuturePage - 1) * PER_PAGE;

  if (ans >= total) return null;
  cacheSet(key, ans, total);
  return ans;
}

/**
 * Evenimente de la un index incolo, paginand cat e nevoie.
 * `until` opreste scanarea cand am trecut de o data (filtrele "Azi"/"Weekend",
 * pe care API-ul nu le poate face singur).
 * `keep` filtreaza client-side ce API-ul nu stie sa filtreze (ex. `genre`).
 */
async function fromOffset(
  filters: Record<string, string | undefined>,
  offset: number,
  want: number,
  opts: { until?: number; keep?: (e: ApiRadarEvent) => boolean; maxPages?: number } = {}
) {
  const out: ApiRadarEvent[] = [];
  let page = Math.floor(offset / PER_PAGE) + 1;
  let skip = offset % PER_PAGE;

  for (let i = 0; i < (opts.maxPages ?? MAX_PAGES) && out.length < want; i++) {
    const r = await getPage(filters, page);
    if (!r?.data?.length) break;

    let past = false;
    for (const e of r.data.slice(skip)) {
      if (opts.until !== undefined) {
        const t = e.starts_at ? new Date(e.starts_at).getTime() : NaN;
        if (Number.isFinite(t) && t >= opts.until) {
          past = true;
          break;
        }
      }
      if (opts.keep && !opts.keep(e)) continue;
      out.push(e);
      if (out.length >= want) break;
    }

    skip = 0;
    if (past || page >= r.meta.last_page) break;
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

/**
 * Cereri in paralel, dar in valuri, ca sa nu deschidem 20 de conexiuni.
 *
 * ATENTIE la cache: daca cererea de detaliu esueaza (429), evenimentul ajunge
 * fara oferte. NU-l punem in cache in cazul asta — altfel o singura limitare
 * de rata il lasa gol pana la repornirea aplicatiei, si ecranul il arunca
 * (filtrele pastreaza doar evenimentele cu oferte).
 */
async function hydrate(events: ApiRadarEvent[], concurrency = 5): Promise<RadarItem[]> {
  const out: RadarItem[] = [];
  for (let i = 0; i < events.length; i += concurrency) {
    const wave = events.slice(i, i + concurrency);
    const done = await Promise.all(
      wave.map(async (e) => {
        const cached = detailCache.get(String(e.id));
        if (cached) return cached;
        const r = await get<DetailResponse>(`/api/v1/events/${e.id}`);
        const item = normalizeRadar(r?.data ?? e);
        if (r?.data) detailCache.set(item.id, item);
        return item;
      })
    );
    out.push(...done);
    if (i + concurrency < events.length) await sleep(120);
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
          if (!r?.data) return it; // cerere cazuta: pastram ce aveam, fara sa cachem un gol
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

/** Filtrele ecranului Radar. `when` acopera chip-urile Azi / Weekend. */
export type RadarQuery = {
  limit?: number;
  city?: string;
  type?: string;
  /** cheia de categorie a feed-ului ('teatru', 'concerte'...) */
  catKey?: string;
  genre?: string;
  search?: string;
  when?: 'all' | 'today' | 'weekend';
  /** o zi anume, ca timestamp UTC la miezul noptii (din Calendar) */
  day?: number;
  /** cate rezultate sa sara peste — pentru "Incarca mai multe" */
  offset?: number;
  /** pretul cel mai mic sa fie sub pragul asta (chip-ul "Sub 100 lei") */
  maxPrice?: number;
  /** doar stoc pe terminate (chip-ul "Aproape sold-out") */
  scarce?: boolean;
};

/** Intervalul [de la, pana la) pentru chip-urile de data si pentru ziua din calendar. */
function whenRange(when: RadarQuery['when'], day?: number): { from: number; until?: number } {
  if (day !== undefined) return { from: day, until: day + 86400000 };
  const from = todayUtc();
  if (when === 'today') return { from, until: from + 86400000 };
  if (when === 'weekend') {
    const n = new Date(from);
    // 6 = sambata; daca azi e deja weekend, weekendul incepe azi
    const dow = n.getUTCDay();
    const toSat = dow === 6 || dow === 0 ? 0 : 6 - dow;
    const start = from + toSat * 86400000;
    // sambata + duminica; daca suntem duminica, doar ziua curenta
    const days = dow === 0 ? 1 : 2;
    return { from: start, until: start + days * 86400000 };
  }
  return { from };
}

const SCARCE = new Set(['Puține', 'Ultimele', 'Sold out']);

/**
 * Lista Radar: evenimente viitoare, cu preturile de pe fiecare platforma.
 * Cele cu mai multe platforme urca primele — comparatia de pret e tot rostul
 * ecranului, iar un eveniment cu o singura oferta nu are ce compara.
 *
 * `city` si `type` merg prin API. `genre`, pretul si stocul NU pot: primul e
 * ignorat de API, celelalte doua exista abia dupa hidratare. Pe alea le
 * filtram aici si, daca nu ies destule, mai tragem candidati.
 */
export async function fetchRadarList(
  opts: RadarQuery = {}
): Promise<{ items: RadarItem[]; source: 'tics' | 'prototype'; hasMore: boolean }> {
  const limit = opts.limit ?? 6;
  const filters = { city: opts.city, event_type: opts.type, q: opts.search };
  const { from, until } = whenRange(opts.when, opts.day);
  const needsPost = !!opts.maxPrice || !!opts.scarce;
  const skip = opts.offset ?? 0;

  /* Plasa de siguranta (datasetul prototipului) se intinde DOAR cand nu s-a
     cerut niciun filtru. Daca utilizatorul a filtrat si nu iese nimic,
     raspunsul corect e "nimic gasit", nu trei evenimente inventate. */
  const unfiltered =
    !opts.city && !opts.type && !opts.genre && !opts.search && !opts.maxPrice && !opts.scarce &&
    !opts.catKey && opts.day === undefined && !opts.offset && (!opts.when || opts.when === 'all');
  const empty = () => ({
    items: unfiltered ? protoItems() : [],
    source: unfiltered ? ('prototype' as const) : ('tics' as const),
    hasMore: false,
  });

  /* Feed-ul acopera urmatoarele 3 saptamani intr-o singura cerere, deci
     raspunde instant. Cade pe API doar cand nu e disponibil. */
  const viaFeed = await feedList({
    limit,
    offset: skip,
    city: opts.city,
    cat: opts.catKey,
    genre: opts.genre,
    search: opts.search,
    when: opts.when,
    day: opts.day,
    maxPrice: opts.maxPrice,
    scarce: opts.scarce,
  });
  if (viaFeed) {
    if (!viaFeed.items.length) return empty();
    return { items: viaFeed.items, source: 'tics', hasMore: skip + viaFeed.items.length < viaFeed.total };
  }

  // cautarea are propriul set de rezultate, deja restrans: nu mai sarim in viitor
  const offset = opts.search ? 0 : await upcomingOffset(filters, from);
  if (offset === null) return empty();

  /* Luam ceva mai multe decat afisam, ca sa avem din ce alege mai jos — dar
     nu mult mai multe: fiecare candidat costa o cerere de detaliu, iar
     app.tics.ro limiteaza la ~60 pe minut. Cand filtram si dupa pret/stoc,
     rata de pastrare scade, deci cerem mai multi candidati. */
  /* `want` TREBUIE sa creasca odata cu limita, altfel "Incarca mai multe" nu
     aduce nimic: plafonul fix taia candidatii inainte sa ajunga la ecran. */
  const want = Math.min(skip + limit * (needsPost ? 3 : 2), 72);
  const raw = await fromOffset(filters, offset, want, {
    until,
    keep: opts.genre ? (e) => (e.genre ?? '').toLowerCase() === opts.genre!.toLowerCase() : undefined,
  });
  if (!raw.length) return empty();

  const hydrated = await hydrate(raw);
  let usable = hydrated.filter((i) => i.offers.length > 0);
  if (opts.maxPrice) usable = usable.filter((i) => i.offers[0][1] <= opts.maxPrice!);
  if (opts.scarce) usable = usable.filter((i) => i.offers.some((o) => SCARCE.has(o[2])));
  if (!usable.length) return empty();

  usable.sort((a, b) => b.offers.length - a.offers.length);
  /* "Mai sunt?" NU se poate deduce din cate carduri ies pe ecran: multe
     candidaturi cad la hidratare (fara oferte) sau la filtrele de pret/stoc,
     deci lista afisata e aproape mereu mai scurta decat ceruta. Semnalul bun e
     daca am consumat toata cota de candidati. */
  return { items: usable.slice(skip, skip + limit), source: 'tics', hasMore: raw.length >= want };
}

/* =========================================================
   Orasele in care chiar se intampla ceva

   API-ul n-are endpoint de orase (`/api/public-stats` da doar numarul lor),
   asa ca le deducem din evenimentele viitoare si le ordonam dupa cate au.
   Rezultatul se tine pe zi — nu e o lista care se schimba de la o ora la alta.
   ========================================================= */
/* Orasele SI categoriile se deduc din acelasi esantion de evenimente
   viitoare. Inainte faceau doua scanari separate (5 + 12 pagini) la
   deschiderea aplicatiei — suficient cat sa intram in limitarea de rata. */
let samplePromise: Promise<ApiRadarEvent[]> | null = null;

async function upcomingSample(): Promise<ApiRadarEvent[]> {
  if (samplePromise) return samplePromise;
  samplePromise = (async () => {
    const offset = await upcomingOffset({});
    if (offset === null) return [];
    return fromOffset({}, offset, 600, { maxPages: 12 });
  })();
  const r = await samplePromise;
  if (!r.length) samplePromise = null; // esec: lasam sa se reincerce
  return r;
}

export const CITY_FALLBACK = [
  'București', 'Cluj-Napoca', 'Timișoara', 'Iași', 'Brașov',
  'Constanța', 'Sibiu', 'Oradea', 'Craiova', 'Galați',
];

export async function fetchRadarCities(): Promise<string[]> {
  const viaFeed = await feedCities();
  if (viaFeed?.length) return viaFeed;

  const lsKey = 'tics.radar.cities.v1';
  try {
    const raw = localStorage.getItem(lsKey);
    if (raw) {
      const v = JSON.parse(raw) as { stamp: string; cities: string[] };
      if (v.stamp === todayStamp() && v.cities.length) return v.cities;
    }
  } catch {
    /* cache stricat: reincarcam */
  }

  const raw = await upcomingSample();
  if (!raw.length) return CITY_FALLBACK;

  const counts = new Map<string, number>();
  for (const e of raw) {
    const c = e.city;
    if (!c || c === 'Necunoscut') continue;
    counts.set(c, (counts.get(c) ?? 0) + 1);
  }
  if (!counts.size) return CITY_FALLBACK;

  const cities = [...counts.entries()].sort((a, b) => b[1] - a[1]).map(([c]) => c);
  try {
    localStorage.setItem(lsKey, JSON.stringify({ stamp: todayStamp(), cities }));
  } catch {
    /* fara cache, doar mai lent */
  }
  return cities;
}

/* =========================================================
   Categoriile reale, deduse din date

   API-ul n-are endpoint de categorii, iar site-ul nu-si expune lista. Le
   deducem din `event_type`-urile evenimentelor viitoare — 17 distincte
   masurate, dominate de 'other'. Iese si o lista de exemple per categorie,
   pe care ecranul Exploreaza le foloseste ca imagini de card (inainte punea
   evenimente din prototip).
   ========================================================= */
export type RadarCategory = {
  type: string;
  cat: string;
  g: string;
  count: number;
  samples: RadarItem[];
  /** culoarea oficiala a categoriei, cand vine din feed */
  color?: string;
};

const CAT_LS = 'tics.radar.cats.v1';

export async function fetchRadarCategories(): Promise<RadarCategory[]> {
  const viaFeed = await feedCategories();
  if (viaFeed?.length) {
    return viaFeed.map((c) => ({ type: c.key, cat: c.label, g: c.samples[0]?.g ?? '🎟', count: c.count, samples: c.samples, color: c.color }));
  }

  try {
    const raw = localStorage.getItem(CAT_LS);
    if (raw) {
      const v = JSON.parse(raw) as { stamp: string; cats: RadarCategory[] };
      if (v.stamp === todayStamp() && v.cats.length) return v.cats;
    }
  } catch {
    /* cache stricat: reincarcam */
  }

  const raw = await upcomingSample();
  if (!raw.length) return [];

  const byType = new Map<string, { count: number; samples: ApiRadarEvent[] }>();
  for (const e of raw) {
    const t = (e.event_type ?? 'other').toLowerCase();
    const slot = byType.get(t) ?? { count: 0, samples: [] };
    slot.count++;
    // pastram doar exemple cu poster: cardul de categorie e o imagine
    // preferam exemple cu poster, dar NU excludem categoriile fara: altfel
    // dispareau tipurile rare si ramaneau 12 din 17
    if (e.poster_url && slot.samples.length < 4) slot.samples.push(e);
    else if (slot.samples.length < 2) slot.samples.push(e);
    byType.set(t, slot);
  }

  const cats: RadarCategory[] = [...byType.entries()]
    .map(([type, v]) => {
      const info = typeInfo(type);
      return { type, cat: info.cat, g: info.g, count: v.count, samples: v.samples.map(normalizeRadar) };
    })
    /* 'other' e cel mai numeros (~70%), dar "Altele" e o categorie proasta de
       pus prima — o coboram la final si lasam categoriile reale in fata. */
    .sort((a, b) => (a.type === 'other' ? 1 : b.type === 'other' ? -1 : b.count - a.count));

  try {
    localStorage.setItem(CAT_LS, JSON.stringify({ stamp: todayStamp(), cats }));
  } catch {
    /* fara cache, doar mai lent */
  }
  return cats;
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

/** Filtrele calendarului. `genre` se aplica local — API-ul il ignora. */
export type MonthQuery = { city?: string; type?: string; genre?: string };

export async function fetchRadarMonth(
  year: number,
  month: number,
  q: MonthQuery = {}
): Promise<MonthData | null> {
  const filters = { city: q.city, event_type: q.type };
  const key = `${year}-${month}#${qs(filters)}#${q.genre ?? ''}`;
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

  /* Feed-ul acopera 3 saptamani; pentru luna curenta raspunde instant. */
  if (!q.city && !q.type && !q.genre) {
    const viaFeed = await feedMonth(year, month);
    if (viaFeed) {
      monthCache.set(key, viaFeed);
      return viaFeed;
    }
  }

  const start = Date.UTC(year, month, 1);
  const end = Date.UTC(year, month + 1, 1);
  const offset = await upcomingOffset(filters, start);
  if (offset === null) return { counts: {}, byDay: {}, total: 0, capped: false };
  const genre = q.genre?.toLowerCase();

  const counts: Record<number, number> = {};
  const byDay: Record<number, RadarItem[]> = {};
  let total = 0;
  const firstPage = Math.floor(offset / PER_PAGE) + 1;
  const firstSkip = offset % PER_PAGE;
  let capped = true;
  /* O pagina cazuta (429 chiar si dupa reincercare) inseamna date cu gauri:
     le aratam, dar NU le salvam ca si cum ar fi luna completa. */
  let incomplete = false;
  let done = false;

  /* Paginile se aduc in VALURI PARALELE, nu una cate una: o luna are ~15-26 de
     pagini, iar secvential inseamna 5-6 secunde de ecran gol. In valuri de 4
     scade la ~1.5s si tot ramanem sub limita de rata. */
  const WAVE = 4;
  for (let w = 0; w < Math.ceil(MONTH_MAX_PAGES / WAVE) && !done; w++) {
    const pages = Array.from({ length: WAVE }, (_, k) => firstPage + w * WAVE + k);
    const results = await Promise.all(pages.map((p) => getPage(filters, p)));

    for (let k = 0; k < results.length; k++) {
      const r = results[k];
      if (!r?.data?.length) {
        if (r === null) incomplete = true;
        else capped = false;
        done = true;
        break;
      }

      const slice = w === 0 && k === 0 ? r.data.slice(firstSkip) : r.data;
      for (const e of slice) {
        const d = e.starts_at ? new Date(e.starts_at) : null;
        if (!d || Number.isNaN(d.getTime())) continue;
        const t = d.getTime();
        if (t >= end) {
          capped = false;
          done = true;
          break;
        }
        if (t < start) continue;
        if (genre && (e.genre ?? '').toLowerCase() !== genre) continue;

        const day = d.getUTCDate();
        counts[day] = (counts[day] ?? 0) + 1;
        total++;
        // pastram doar cateva pe zi: ecranul afiseaza o lista scurta
        if ((byDay[day] ??= []).length < 4) byDay[day].push(normalizeRadar(e));
      }
      if (done) break;
      if (pages[k] >= r.meta.last_page) {
        capped = false;
        done = true;
        break;
      }
    }
    if (!done) await sleep(120);
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
