/* =========================================================
   CATALOG — fisele publice de eveniment, artist si locatie.

     GET /api/tenant-client/catalog/events/{id|slug}
     GET /api/tenant-client/catalog/artists/{id|slug}
     GET /api/tenant-client/catalog/venues/{id|slug}

   Citiri publice, fara cont: exact ca feed-ul de shorts, care e si sursa
   principala de trafic spre ecranele astea.

   Nu se inventeaza date: cand raspunsul lipseste, apelantul decide ce arata.
   ========================================================= */
import { API_ROOT } from './tenantClient';

export type CatalogVenueRef = {
  id: number;
  slug: string | null;
  name: string | null;
  city: string | null;
  address: string | null;
};

/** Forma scurta, folosita in listele de pe fisa artistului si a locatiei. */
export type CatalogEventBrief = {
  id: number;
  slug: string | null;
  title: string | null;
  subtitle: string | null;
  date: string | null;
  day: string | null;
  month: string | null;
  date_label: string | null;
  time: string | null;
  city: string | null;
  venue: CatalogVenueRef | null;
  poster: string | null;
  category: string | null;
  price_from: number | null;
  is_cancelled: boolean;
  is_postponed: boolean;
};

export type CatalogTicketType = {
  id: number;
  name: string | null;
  description: string | null;
  price: number | null;
  /** Pretul intreg, cand exista o reducere activa; altfel null. */
  full_price: number | null;
  /** Ce include biletul („acces zona VIP", „include o bautura"...). */
  perks: string[];
  /** DA/NU, nu numar: stocul ramas e informatie comerciala a organizatorului. */
  available: boolean;
};

/**
 * Cine ia comisionul si cum.
 *
 * `included` — deja in pretul afisat, cumparatorul nu vede nicio linie in plus.
 * `added_on_top` — se adauga la total, ca linie separata.
 */
export type CatalogPricing = {
  source: 'marketplace' | 'tenant';
  mode: 'included' | 'added_on_top';
  /** procent */
  rate: number;
};

export type CatalogArtistRef = {
  id: number;
  slug: string | null;
  name: string | null;
  role: string | null;
  image: string | null;
};

export type CatalogEvent = CatalogEventBrief & {
  hero: string | null;
  gallery: string[];
  organizer: string | null;
  short_description: string | null;
  description: string | null;
  terms: string | null;
  pricing: CatalogPricing;
  ticket_types: CatalogTicketType[];
  artists: CatalogArtistRef[];
};

/** O piesa din topul Spotify al artistului. */
export type CatalogTrack = {
  id: string;
  name: string;
  album: string;
  image: string | null;
  /** deja formatata „3:41" */
  duration: string | null;
  /** 30 de secunde de proba; poate lipsi (drepturi teritoriale) */
  preview: string | null;
  url: string | null;
};

export type CatalogArtist = {
  id: number;
  slug: string | null;
  name: string | null;
  role: string | null;
  bio: string | null;
  city: string | null;
  country: string | null;
  image: string | null;
  cover: string | null;
  links: Partial<Record<'website' | 'facebook' | 'instagram' | 'tiktok' | 'youtube' | 'spotify', string>>;
  followers: Partial<Record<'facebook' | 'instagram' | 'tiktok' | 'youtube' | 'spotify', number>>;
  events: CatalogEventBrief[];
  /** Gol cand artistul n-are `spotify_id` sau cand Spotify n-a raspuns. */
  top_tracks?: CatalogTrack[];
};

/**
 * O recenzie Google, exact cum o trimite serverul.
 *
 * Campurile erau declarate `author` si `time: string` — nu asa se cheama si nu
 * asta sunt. Payload-ul real are `author_name`, `profile_photo_url`,
 * `relative_time_description` si `time` ca UNIX seconds. Cu numele gresite,
 * orice randare ar fi afisat casute goale.
 */
export type CatalogVenueReview = {
  author_name?: string;
  profile_photo_url?: string;
  author_url?: string;
  rating?: number;
  text?: string;
  /** UNIX, in secunde. */
  time?: number;
  /** „acum 3 luni" — deja tradus de Google in limba ceruta. */
  relative_time_description?: string;
};

export type CatalogVenue = {
  id: number;
  slug: string | null;
  name: string | null;
  city: string | null;
  address: string | null;
  country: string | null;
  capacity: number | null;
  description: string | null;
  image: string | null;
  portrait: string | null;
  gallery: string[];
  lat: number | null;
  lng: number | null;
  /** Pinul e centrul orasului, nu adresa salii — se spune in interfata. */
  location_approx?: boolean;
  rating: number | null;
  review_count: number | null;
  reviews: CatalogVenueReview[];
  events: CatalogEventBrief[];
};

type Envelope<T> = { success: boolean; data: T };

async function get<T>(path: string, signal?: AbortSignal): Promise<T | null> {
  try {
    const res = await fetch(API_ROOT + path, {
      signal,
      // fara Content-Type pe GET: ar adauga un preflight OPTIONS degeaba
      headers: { Accept: 'application/json' },
    });

    if (!res.ok) return null;

    const body = (await res.json()) as Envelope<T>;

    return body?.success ? body.data : null;
  } catch {
    return null;
  }
}

const key = (v: string | number) => encodeURIComponent(String(v));

export const fetchCatalogEvent = (id: string | number, signal?: AbortSignal) =>
  get<CatalogEvent>(`/tenant-client/catalog/events/${key(id)}`, signal);

/**
 * Evenimentele NOASTRE (tenanti + marketplace-uri), pentru ecranele de
 * descoperire. Radarul TICS ramane o sursa secundara: acolo doar comparam
 * preturi, aici chiar vindem bilet.
 */
export const fetchCatalogEvents = (
  opts: { city?: string; limit?: number; category?: string } = {},
  signal?: AbortSignal,
) => {
  const params = new URLSearchParams();
  if (opts.city) params.set('city', opts.city);
  if (opts.limit) params.set('limit', String(opts.limit));
  if (opts.category) params.set('category', opts.category);

  const qs = params.toString();

  return get<CatalogEventBrief[]>(`/tenant-client/catalog/events${qs ? `?${qs}` : ''}`, signal);
};

/** Un eveniment care are un loc pe harta. */
export type MapEvent = CatalogEventBrief & { lat: number; lng: number };

/** Acelasi, dar cerut relativ la un punct — deci si cu distanta. */
export type NearbyEvent = MapEvent & { distance_km: number };

export type NearbyResult = {
  center: { lat: number; lng: number };
  radius_km: number;
  events: NearbyEvent[];
};

/**
 * Evenimentele din jurul unui punct, deja ordonate dupa distanta de server.
 * Fara coordonate, centrul se deduce din numele orasului — asa functia merge
 * si cand utilizatorul refuza (sau nu i se cere) permisiunea de locatie.
 */
export const fetchNearbyEvents = (
  at: { lat: number; lng: number } | { city: string },
  opts: { radius?: number; limit?: number } = {},
  signal?: AbortSignal,
) => {
  const params = new URLSearchParams();
  if ('lat' in at) {
    params.set('lat', String(at.lat));
    params.set('lng', String(at.lng));
  } else {
    params.set('city', at.city);
  }
  params.set('radius', String(opts.radius ?? 100));
  params.set('limit', String(opts.limit ?? 20));

  return get<NearbyResult>(`/tenant-client/catalog/events/nearby?${params.toString()}`, signal);
};

export type BoundsResult = { events: MapEvent[]; too_wide: boolean };

/** Evenimentele din dreptunghiul vizibil pe harta. */
export const fetchEventsInBounds = (
  b: { north: number; south: number; east: number; west: number },
  signal?: AbortSignal,
) => {
  const params = new URLSearchParams({
    north: b.north.toFixed(5),
    south: b.south.toFixed(5),
    east: b.east.toFixed(5),
    west: b.west.toFixed(5),
  });

  return get<BoundsResult>(`/tenant-client/catalog/events/in-bounds?${params.toString()}`, signal);
};

export type CatalogSearch = {
  events: CatalogEventBrief[];
  artists: { id: number; slug: string | null; name: string | null; role: string | null; image: string | null }[];
  venues: { id: number; slug: string | null; name: string | null; city: string | null; image: string | null }[];
};

/** Cautare in catalogul propriu. Radarul se cauta separat, in client. */
export const searchCatalog = (q: string, signal?: AbortSignal) =>
  get<CatalogSearch>(`/tenant-client/catalog/search?q=${encodeURIComponent(q)}`, signal);

export const fetchCatalogArtist = (id: string | number, signal?: AbortSignal) =>
  get<CatalogArtist>(`/tenant-client/catalog/artists/${key(id)}`, signal);

export const fetchCatalogVenue = (id: string | number, signal?: AbortSignal) =>
  get<CatalogVenue>(`/tenant-client/catalog/venues/${key(id)}`, signal);

/**
 * Cauta o locatie dupa nume — pentru evenimentele din Radar, care tin sala doar
 * ca text. Intoarce null cand nu exista una sigura; nu ghicim.
 */
export const lookupCatalogVenue = (name: string, city?: string) => {
  const params = new URLSearchParams({ name });
  if (city) params.set('city', city);

  return get<{ id: number; slug: string | null }>(`/tenant-client/catalog/venues-lookup?${params.toString()}`);
};
