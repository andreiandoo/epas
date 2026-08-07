/* =========================================================
   Strat de date CLIENT — EPAS tenant-client API (real), cu fallback
   pe datasetul prototipului.

   De ce fallback si nu doar API: tenantii de demo au inca putine evenimente
   (tenant 17 = 1 eveniment, tenant 18 = 0). Un ecran gol nu ne spune nimic
   despre design. Asa aplicatia e EFECTIV cablata la EPAS, dar ramane plina
   cand sursa e goala sau indisponibila.

   Endpointuri reale folosite (verificate live pe core.tixello.com):
     GET /api/tenant-client/events?tenant={id}&limit=&offset=&search=&category=
     GET /api/tenant-client/events/{slug}?tenant={id}
     GET /api/tenant-client/categories?tenant={id}
     GET /api/tenant-client/artists?tenant={id}
   Tenantul se rezolva din query (?tenant= sau ?hostname=) — vezi
   app/Http/Controllers/Api/Concerns/ResolvesTenant.php.
   ========================================================= */
import { EV, VEN, bgv, galFor, scByCat } from '../mock/prototype';

export const API_ROOT = import.meta.env.VITE_API_BASE ?? 'https://core.tixello.com/api';
/** Tenantul demo folosit pana la login real (are evenimente publicate). */
export const DEMO_TENANT = Number(import.meta.env.VITE_TENANT_ID ?? 17);

/* ---------- forma pe care o consuma ecranele (cea din prototip) ---------- */
export type UiEvent = {
  id: string;
  t: string;
  s: string;
  type: 'event' | 'experience';
  cat: string;
  city: string;
  ven: string;
  d: string;
  mon: string;
  day: string;
  time: string;
  from: number;
  tone: string;
  g: string;
  rat: string;
  by: string;
  sc?: string;
  seatmap?: boolean;
  gallery?: string[];
  tt: { n: string; desc: string; p: number; old?: number; pts: number; seat: boolean }[];
  /** true daca vine din EPAS, false daca e din datasetul prototipului */
  live?: boolean;
  slug?: string;
  poster?: string | null;
};

/* ---------- forma bruta din API ---------- */
type ApiEvent = {
  id: number;
  title: string;
  slug: string;
  start_date?: string | null;
  start_time?: string | null;
  price_from?: number | null;
  venue?: { id: number; name: string; city: string } | null;
  poster_url?: string | null;
  hero_image_url?: string | null;
  short_description?: string | null;
  event_types?: { id: number; name: string }[];
  event_genres?: { id: number; name: string }[];
  artists?: { id: number; name: string }[];
  ticket_types?: {
    id: number;
    name: string;
    description?: string | null;
    price: number;
    sale_price?: number | null;
    currency?: string;
    available?: number;
  }[];
};

const MONTHS_RO = ['Ian', 'Feb', 'Mar', 'Apr', 'Mai', 'Iun', 'Iul', 'Aug', 'Sep', 'Oct', 'Noi', 'Dec'];

/** Categoriile EPAS -> categoriile de scena ale prototipului. */
const CAT_TO_SCENE: Record<string, string> = {
  Concerte: 'concert',
  Concert: 'concert',
  Teatru: 'theatre',
  Festival: 'festival',
  'Stand-up': 'standup',
  Petrecere: 'party',
  Experiențe: 'nature',
};

/**
 * Normalizeaza un eveniment EPAS in forma prototipului, ca ecranele sa nu stie
 * de unde vin datele. Imaginile: daca EPAS are poster/hero real il folosim;
 * altfel cade pe scena SVG procedurala a prototipului, ca sa nu ramana card gol.
 */
export function normalizeEvent(e: ApiEvent): UiEvent {
  const cat = e.event_types?.[0]?.name ?? 'Concerte';
  const sc = CAT_TO_SCENE[cat] ?? 'concert';
  const date = e.start_date ? new Date(e.start_date) : null;
  const day = date ? String(date.getDate()) : '';
  const mon = date ? MONTHS_RO[date.getMonth()] : '';

  const base: UiEvent = {
    id: e.slug,
    slug: e.slug,
    t: e.title,
    s: e.title,
    type: cat === 'Experiențe' ? 'experience' : 'event',
    cat,
    city: e.venue?.city ?? '',
    ven: e.venue?.name ?? '',
    d: date ? `${day} ${mon}` : '',
    mon,
    day,
    time: (e.start_time ?? '').slice(0, 5),
    from: Number(e.price_from ?? 0),
    tone: 'linear-gradient(150deg,#4c1d95,#8b5cf6)',
    g: '🎤',
    rat: '—',
    by: '',
    sc,
    seatmap: false,
    live: true,
    poster: e.poster_url ?? e.hero_image_url ?? null,
    tt: (e.ticket_types ?? []).map((t) => ({
      n: t.name,
      desc: t.description ?? '',
      p: Number(t.sale_price ?? t.price),
      old: t.sale_price ? Number(t.price) : undefined,
      pts: Number(t.sale_price ?? t.price),
      seat: false,
    })),
  };

  base.gallery = galFor(base);
  return base;
}

/** Fundalul unui card: poster real din EPAS daca exista, altfel scena SVG. */
export function eventBackground(ev: UiEvent): string {
  if (ev.poster) return `url('${ev.poster}') center/cover, #14101f`;
  return bgv(ev);
}

/* ---------- datasetul prototipului, in aceeasi forma ---------- */
const protoEvents = (): UiEvent[] =>
  Object.values(EV).map((e: Record<string, unknown>) => ({
    ...(e as unknown as UiEvent),
    ven: (VEN as Record<string, { name: string }>)[e.ven as string]?.name ?? '',
    live: false,
    sc: (e.sc as string) ?? scByCat(e),
  }));

/* ---------- fetch cu timeout, ca UI-ul sa nu atarne ---------- */
async function get<T>(path: string, ms = 6000): Promise<T | null> {
  const ctrl = new AbortController();
  const t = setTimeout(() => ctrl.abort(), ms);
  try {
    const res = await fetch(`${API_ROOT}${path}`, {
      signal: ctrl.signal,
      headers: { Accept: 'application/json' },
    });
    if (!res.ok) return null;
    return (await res.json()) as T;
  } catch {
    return null;
  } finally {
    clearTimeout(t);
  }
}

type EventsResponse = { success: boolean; data: { events: ApiEvent[]; meta?: { total: number } } };
type EventResponse = { success: boolean; data: { event: ApiEvent } };

/**
 * Lista de evenimente. Intoarce intotdeauna ceva randabil:
 * evenimentele reale ale tenantului, completate cu cele din prototip cand
 * sursa e goala sau indisponibila.
 */
export async function fetchEvents(opts: { tenant?: number; limit?: number; category?: string; search?: string } = {}) {
  const tenant = opts.tenant ?? DEMO_TENANT;
  const qs = new URLSearchParams({ tenant: String(tenant), limit: String(opts.limit ?? 24) });
  if (opts.category) qs.set('category', opts.category);
  if (opts.search) qs.set('search', opts.search);

  const res = await get<EventsResponse>(`/tenant-client/events?${qs.toString()}`);
  const live = (res?.data?.events ?? []).map(normalizeEvent);
  const proto = protoEvents();

  return {
    live,
    /** ce se afiseaza: reale intai, apoi cele din prototip ca sa nu fie gol */
    items: [...live, ...proto],
    source: live.length ? ('epas' as const) : ('prototype' as const),
  };
}

/** Detaliu de eveniment; cade pe prototip daca slug-ul nu e din EPAS. */
export async function fetchEvent(slug: string, tenant = DEMO_TENANT): Promise<UiEvent | null> {
  const fromProto = protoEvents().find((e) => e.id === slug);
  if (fromProto) return fromProto;

  const res = await get<EventResponse>(`/tenant-client/events/${encodeURIComponent(slug)}?tenant=${tenant}`);
  return res?.data?.event ? normalizeEvent(res.data.event) : null;
}

type CategoriesResponse = { success: boolean; data: { categories: { id: number; name: string; slug: string }[] } };

/** Categoriile tenantului; cade pe cele din prototip. */
export async function fetchCategories(tenant = DEMO_TENANT): Promise<string[]> {
  const res = await get<CategoriesResponse>(`/tenant-client/categories?tenant=${tenant}`);
  const live = (res?.data?.categories ?? []).map((c) => c.name);
  if (live.length) return live;
  return ['Concerte', 'Experiențe', 'Teatru', 'Festival', 'Stand-up', 'Petrecere'];
}
