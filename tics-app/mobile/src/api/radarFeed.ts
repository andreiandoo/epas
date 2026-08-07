/* =========================================================
   FEED RADAR — o singura cerere pentru tot ce se intampla.

   DE CE EXISTA
   `/api/v1/events` nu da preturi in lista; ele vin doar din detaliu, cate o
   cerere per eveniment. Ca sa umplem un ecran faceam zeci de cereri si intram
   in limitarea de rata (~60/min) — de aici listele scurte ("Teatru: 22
   evenimente" dar doar 2 afisate) si secundele de asteptare.

   Pagina /events de pe app.tics.ro isi embed-uieste insa tot listingul:
   ~1000 de evenimente pentru urmatoarele 3 saptamani, fiecare cu pretul cel
   mai mic, platforma, posterul si categoria. N-are CORS, deci o citim printr-un
   proxy al nostru (public/tics-app/radar.php) care o si pune in cache.

   Rezultat: UN request, apoi tot filtrarea e locala si instantanee.

   Ce NU acopera feed-ul, si de ce ramane si calea veche:
   - doar 3 saptamani inainte (calendarul umbla prin luni oricat de departe);
   - o singura platforma per eveniment (comparatia completa vine din detaliu);
   - daca proxy-ul nu raspunde, ecranele cad automat pe API-ul public.
   ========================================================= */
import type { RadarItem, RadarOffer } from './ticsRadar';

export const FEED_URL =
  import.meta.env.VITE_RADAR_FEED ?? 'https://core.tixello.com/tics-app/radar.php';

/* ---------- forma servita de proxy ---------- */
type FeedEvent = {
  id: number;
  cat: string;
  label: string;
  color: string;
  t: string;
  city: string;
  ven: string;
  genre: string | null;
  plat: string;
  price: number | null;
  save: number;
  sold: boolean;
  /** cate zile de azi incolo */
  days: number;
  date: string;
  wknd: boolean;
  img: string | null;
};

export type FeedCategory = { key: string; label: string; color: string };
type FeedCity = { name: string; n: number };
type Feed = { fetched: string; events: FeedEvent[]; cats: FeedCategory[]; cities: FeedCity[] };

const MONTHS_RO = ['Ian', 'Feb', 'Mar', 'Apr', 'Mai', 'Iun', 'Iul', 'Aug', 'Sep', 'Oct', 'Noi', 'Dec'];

/** categoria TICS -> scena procedurala si emoji, pentru cardurile fara poster */
const CAT_LOOK: Record<string, { sc: string; g: string }> = {
  concerte: { sc: 'concert', g: '🎤' },
  teatru: { sc: 'theatre', g: '🎭' },
  festival: { sc: 'festival', g: '🎪' },
  standup: { sc: 'standup', g: '🎙' },
  sport: { sc: 'city', g: '🏟' },
  classical: { sc: 'theatre', g: '🎻' },
  dance: { sc: 'party', g: '🩰' },
  musical: { sc: 'theatre', g: '🎼' },
  party: { sc: 'party', g: '🪩' },
  exhibition: { sc: 'city', g: '🖼' },
  conference: { sc: 'city', g: '🎧' },
  workshop: { sc: 'city', g: '🛠' },
  film: { sc: 'theatre', g: '🎬' },
  kids: { sc: 'theatre', g: '🧸' },
  'food-drink': { sc: 'wine', g: '🍷' },
  fair: { sc: 'festival', g: '🎠' },
  outdoor: { sc: 'nature', g: '🥾' },
  circus: { sc: 'festival', g: '🎪' },
  wellness: { sc: 'nature', g: '🧘' },
  charity: { sc: 'city', g: '💜' },
  seasonal: { sc: 'city', g: '🎄' },
  arta: { sc: 'city', g: '🎟' },
};

const look = (cat: string) => CAT_LOOK[cat] ?? CAT_LOOK.arta;

/** Ziua de azi la miezul noptii, in aceeasi conventie ca restul modulului. */
const todayUtc = () => {
  const n = new Date();
  return Date.UTC(n.getFullYear(), n.getMonth(), n.getDate());
};

/** Data reala a evenimentului: feed-ul da distanta in zile, nu data. */
export const feedDate = (e: FeedEvent) => todayUtc() + e.days * 86400000;

const STOCK = (e: FeedEvent) => (e.sold ? 'Sold out' : e.save > 0 ? 'Puține' : 'Disponibil');

/** Feed -> forma pe care o consuma ecranele (aceeasi ca datasetul prototipului). */
export function fromFeed(e: FeedEvent): RadarItem {
  const d = new Date(feedDate(e));
  const l = look(e.cat);
  const offers: RadarOffer[] = e.price ? [[e.plat || 'TICS', Math.round(e.price), STOCK(e)]] : [];

  return {
    id: String(e.id),
    s: e.t,
    cat: e.label,
    city: e.city && e.city !== 'Necunoscut' ? e.city : '',
    venName: e.ven,
    addr: '',
    day: String(d.getUTCDate()).padStart(2, '0'),
    mon: MONTHS_RO[d.getUTCMonth()],
    time: '',
    tone: `linear-gradient(150deg, ${e.color}, #1a1428)`,
    g: l.g,
    stock: STOCK(e),
    rat: '—',
    desc: '',
    artists: [],
    gallery: e.img ? [`url('${e.img}') center/cover, #14101f`] : [],
    offers,
    sc: l.sc,
    poster: e.img,
    live: true,
  };
}

/* ---------- aducere, o singura data pe sesiune ---------- */
const LS_KEY = 'tics.radar.feed.v1';
const LS_TTL = 15 * 60 * 1000; // proxy-ul cacheaza 10 min; noi ceva mai mult

let inflight: Promise<Feed | null> | null = null;
let memory: Feed | null = null;

async function load(): Promise<Feed | null> {
  try {
    const ctrl = new AbortController();
    const t = setTimeout(() => ctrl.abort(), 15000);
    const res = await fetch(FEED_URL, { signal: ctrl.signal, headers: { Accept: 'application/json' } });
    clearTimeout(t);
    if (!res.ok) return null;
    const feed = (await res.json()) as Feed;
    if (!feed?.events?.length) return null;
    try {
      localStorage.setItem(LS_KEY, JSON.stringify({ at: Date.now(), feed }));
    } catch {
      /* fara cache local, doar se readuce mai des */
    }
    return feed;
  } catch {
    return null;
  }
}

export async function getFeed(): Promise<Feed | null> {
  if (memory) return memory;

  try {
    const raw = localStorage.getItem(LS_KEY);
    if (raw) {
      const v = JSON.parse(raw) as { at: number; feed: Feed };
      if (Date.now() - v.at < LS_TTL && v.feed?.events?.length) {
        memory = v.feed;
        return memory;
      }
    }
  } catch {
    /* cache stricat: reincarcam */
  }

  if (!inflight) {
    inflight = load().finally(() => {
      inflight = null;
    });
  }
  memory = await inflight;
  return memory;
}

/* ---------- interogari, toate LOCALE ---------- */
export type FeedQuery = {
  limit?: number;
  offset?: number;
  city?: string;
  /** cheia de categorie TICS ('teatru', 'concerte'...) */
  cat?: string;
  genre?: string;
  search?: string;
  when?: 'all' | 'today' | 'weekend';
  /** o zi anume, timestamp UTC la miezul noptii */
  day?: number;
  maxPrice?: number;
  scarce?: boolean;
};

function match(e: FeedEvent, q: FeedQuery): boolean {
  if (q.city && e.city !== q.city) return false;
  if (q.cat && e.cat !== q.cat) return false;
  if (q.genre && (e.genre ?? '').toLowerCase() !== q.genre.toLowerCase()) return false;
  if (q.search && !e.t.toLowerCase().includes(q.search.toLowerCase())) return false;
  if (q.day !== undefined && feedDate(e) !== q.day) return false;
  if (q.when === 'today' && e.days !== 0) return false;
  if (q.when === 'weekend' && !e.wknd) return false;
  if (q.maxPrice && (e.price === null || e.price > q.maxPrice)) return false;
  if (q.scarce && !(e.sold || e.save > 0)) return false;
  return true;
}

/**
 * Lista filtrata. Sortare: intai cele cu economie reala fata de piata (asta e
 * rostul Radarului), apoi dupa cat de aproape sunt.
 */
export async function feedList(q: FeedQuery = {}): Promise<{ items: RadarItem[]; total: number } | null> {
  const feed = await getFeed();
  if (!feed) return null;

  const hits = feed.events.filter((e) => e.price !== null && match(e, q));
  hits.sort((a, b) => b.save - a.save || a.days - b.days);

  const off = q.offset ?? 0;
  const lim = q.limit ?? 12;
  return { items: hits.slice(off, off + lim).map(fromFeed), total: hits.length };
}

/** Categoriile OFICIALE (22), cu cate evenimente are fiecare in feed. */
export async function feedCategories(): Promise<
  { key: string; label: string; color: string; count: number; samples: RadarItem[] }[] | null
> {
  const feed = await getFeed();
  if (!feed?.cats?.length) return null;

  const byCat = new Map<string, FeedEvent[]>();
  for (const e of feed.events) {
    (byCat.get(e.cat) ?? byCat.set(e.cat, []).get(e.cat)!).push(e);
  }

  return feed.cats
    .map((c) => {
      const list = byCat.get(c.key) ?? [];
      // preferam exemple cu poster: cardul de categorie e in primul rand o imagine
      const withImg = list.filter((e) => e.img);
      const samples = (withImg.length ? withImg : list).slice(0, 4).map(fromFeed);
      return { key: c.key, label: c.label, color: c.color, count: list.length, samples };
    })
    /* Le aratam pe TOATE cele 22 oficiale, ca pe site — chiar daca unele n-au
       evenimente in urmatoarele 3 saptamani; contorul spune cinstit cate sunt.
       Ordine: cele cu evenimente, dupa cate au; apoi "Altele" (cea mai
       numeroasa, dar proasta de pus prima); la final cele goale. */
    .sort((a, b) => {
      const rank = (c: { key: string; count: number }) => (c.count === 0 ? 2 : c.key === 'arta' ? 1 : 0);
      return rank(a) - rank(b) || b.count - a.count;
    });
}

/** Orasele, dupa cate evenimente au. */
export async function feedCities(): Promise<string[] | null> {
  const feed = await getFeed();
  if (!feed?.cities?.length) return null;
  return feed.cities
    .filter((c) => c.name && c.name !== 'Necunoscut')
    .sort((a, b) => b.n - a.n)
    .map((c) => c.name);
}

/** Cate evenimente sunt in fiecare zi a lunii — cat acopera feed-ul. */
export async function feedMonth(year: number, month: number) {
  const feed = await getFeed();
  if (!feed) return null;

  const start = Date.UTC(year, month, 1);
  const end = Date.UTC(year, month + 1, 1);
  const counts: Record<number, number> = {};
  const byDay: Record<number, RadarItem[]> = {};
  let total = 0;
  let covered = false;

  for (const e of feed.events) {
    const t = feedDate(e);
    if (t < start || t >= end) continue;
    covered = true;
    const day = new Date(t).getUTCDate();
    counts[day] = (counts[day] ?? 0) + 1;
    total++;
    if ((byDay[day] ??= []).length < 4) byDay[day].push(fromFeed(e));
  }

  // feed-ul tine doar 3 saptamani: pentru luni mai indepartate nu are ce spune
  return covered ? { counts, byDay, total, capped: false } : null;
}
