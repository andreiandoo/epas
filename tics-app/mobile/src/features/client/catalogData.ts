/* =========================================================
   Fisele reale de eveniment / artist / locatie, aduse la forma prototipului.

   DE CE ADAPTOR SI NU ECRANE NOI
   Ecranele sunt porturi 1:1 din client-app.html si citesc inregistrari cu
   cheile scurte de acolo (`s`, `t`, `d`, `from`, `tone`, `ven`...). Rescrise ca
   sa consume forma API-ului, ar fi trebuit refacute integral si ar fi divergat
   de prototip la prima modificare. Adaptorul pastreaza un singur mod de
   randare: ecranul nu stie daca inregistrarea vine din catalogul real sau din
   datasetul demo.

   Ce NU are corespondent real (prietenii care au bilete, extra-optiunile,
   recenziile de eveniment) se marcheaza ca absent, nu se umple cu date demo:
   un ecran care arata „3 prieteni au bilete" pentru un eveniment real ar fi o
   minciuna, nu un substitut.
   ========================================================= */
import { useEffect, useState } from 'react';
import type { UiEvent } from '../../api/tenantClient';
import {
  fetchCatalogArtist,
  fetchCatalogEvent,
  fetchCatalogEvents,
  fetchCatalogVenue,
  type CatalogArtist,
  type CatalogEvent,
  type CatalogEventBrief,
  type CatalogVenue,
} from '../../api/catalog';

type Rec = Record<string, any>;

/** Gradientele prototipului tin loc de culoare cand nu avem imagine. */
const TONES = [
  'linear-gradient(150deg,#4c1d95,#8b5cf6)',
  'linear-gradient(150deg,#241a44,#6d28d9)',
  'linear-gradient(150deg,#2a2150,#7c3aed)',
  'linear-gradient(150deg,#3a2c66,#8b5cf6)',
  'linear-gradient(150deg,#0f4c4a,#12b3a6)',
];

/** Stabil pe id, ca acelasi eveniment sa nu-si schimbe culoarea intre ecrane. */
const toneFor = (id: number | string) => {
  const n = typeof id === 'number' ? id : [...String(id)].reduce((a, c) => a + c.charCodeAt(0), 0);

  return TONES[Math.abs(n) % TONES.length];
};

/** O imagine reala bate gradientul; `bgv()` din prototip asteapta un `background`. */
const bgFor = (image: string | null | undefined, id: number | string) =>
  image ? `url('${image}') center/cover, #14101f` : toneFor(id);

/* ---------- eveniment ---------- */

export type EventRecord = {
  ev: Rec;
  venue: Rec | null;
  artists: Rec[];
  /** Categoriile de bilet reale; goale cand evenimentul n-are niciuna activa. */
  tickets: CatalogEvent['ticket_types'];
  description: string | null;
  terms: string | null;
};

export function toEventRecord(e: CatalogEvent): EventRecord {
  const venue = e.venue
    ? {
        id: String(e.venue.id),
        name: e.venue.name ?? '',
        city: e.venue.city ?? '',
        addr: e.venue.address ?? '',
        cap: '—',
        tone: toneFor(e.venue.id),
      }
    : null;

  return {
    ev: {
      id: String(e.id),
      t: e.title ?? '',
      s: e.title ?? '',
      type: 'event',
      cat: e.category ?? 'Eveniment',
      city: e.city ?? '',
      ven: venue?.id ?? '',
      d: e.date_label ?? '',
      mon: e.month ?? '',
      day: e.day ?? '',
      time: e.time ?? '',
      from: e.price_from ?? 0,
      tone: toneFor(e.id),
      // `g` e emoji-ul decorativ din prototip; cu poster real nu se mai vede
      g: '🎫',
      rat: null,
      by: e.organizer ?? '',
      poster: e.poster,
      artists: e.artists.map((a) => String(a.id)),
      gallery: e.gallery.length ? e.gallery.map((g) => `url('${g}') center/cover, #14101f`) : [],
      video: false,
      // fara corespondent real — sectiunile lor se ascund
      friends: [],
      seatmap: false,
      tt: e.ticket_types.map((t) => ({
        n: t.name ?? 'Bilet',
        desc: t.description ?? '',
        p: t.price ?? 0,
        pts: Math.round(t.price ?? 0),
        seat: false,
        sold: !t.available,
      })),
      _bg: bgFor(e.poster ?? e.hero, e.id),
      _live: true,
    },
    venue,
    artists: e.artists.map((a) => ({
      id: String(a.id),
      name: a.name ?? '',
      role: a.role ?? '',
      g: '🎤',
      tone: toneFor(a.id),
      fol: '',
      bio: '',
      _bg: bgFor(a.image, a.id),
      _live: true,
    })),
    tickets: e.ticket_types,
    description: e.description ?? e.short_description,
    terms: e.terms,
  };
}

/* ---------- artist ---------- */

export function toArtistRecord(a: CatalogArtist): Rec {
  const followers = Object.values(a.followers).filter((n): n is number => typeof n === 'number');
  const best = followers.length ? Math.max(...followers) : null;

  return {
    id: String(a.id),
    name: a.name ?? '',
    role: a.role ?? '',
    g: '🎤',
    tone: toneFor(a.id),
    fol: best !== null ? compact(best) : '',
    bio: a.bio ?? '',
    city: a.city ?? '',
    _bg: bgFor(a.image ?? a.cover, a.id),
    _links: a.links,
    _live: true,
  };
}

/* ---------- locatie ---------- */

export function toVenueRecord(v: CatalogVenue): Rec {
  return {
    id: String(v.id),
    name: v.name ?? '',
    city: v.city ?? '',
    addr: v.address ?? '',
    cap: v.capacity ? exact(v.capacity) : '—',
    tone: toneFor(v.id),
    _bg: bgFor(v.portrait ?? v.image, v.id),
    /* Coordonatele merg mai departe catre „Cum ajung": o pereche lat/lng nu
       poate fi inteleasa gresit, spre deosebire de un nume de sala. */
    _lat: v.lat,
    _lng: v.lng,
    _rating: v.rating,
    _reviewCount: v.review_count,
    _desc: v.description,
    _live: true,
  };
}

/**
 * Prototipul foloseste DOUA formate diferite, si nu din intamplare:
 *   - urmaritori: „82M", „2.4M" — ordinul de marime conteaza, cifra exacta nu;
 *   - capacitate: „30.000", „820" — aici numarul exact chiar inseamna ceva.
 * De aceea sunt doua functii, nu una cu un parametru.
 */
export function compact(n: number): string {
  if (n >= 1_000_000) return `${(n / 1_000_000).toFixed(1).replace(/\.0$/, '')}M`;
  if (n >= 1_000) return `${(n / 1_000).toFixed(1).replace(/\.0$/, '')}k`;

  return String(n);
}

export const exact = (n: number): string => n.toLocaleString('ro-RO');

/**
 * Evenimentele listate pe fisele de artist / locatie si pe ecranele de
 * descoperire, in forma pe care o consuma cardurile din prototip.
 */
export function toEventBrief(e: CatalogEventBrief): UiEvent {
  return {
    id: String(e.id),
    s: e.title ?? '',
    t: e.title ?? '',
    type: 'event',
    cat: 'Eveniment',
    city: e.city ?? e.venue?.city ?? '',
    d: e.date_label ?? '',
    mon: e.month ?? '',
    day: e.day ?? '',
    time: e.time ?? '',
    from: e.price_from ?? 0,
    tone: toneFor(e.id),
    g: '🎫',
    /* `ven` tine NUMELE, nu id-ul: cardurile cauta intai in VEN-ul
       prototipului si cad pe valoarea bruta, deci un id numeric s-ar fi
       afisat ca atare sub numele salii. */
    ven: e.venue?.name ?? '',
    _venueId: e.venue ? String(e.venue.id) : '',
    /* `poster` e cheia pe care o stie deja `eventBackground()`. */
    poster: e.poster,
    _bg: bgFor(e.poster, e.id),
    _live: true,
  } as unknown as UiEvent;
}

/* ---------- hook-uri ---------- */

/**
 * Rezultatul unei incarcari de fisa.
 *
 * `missing` inseamna „serverul spune ca nu exista", spre deosebire de `loading`
 * si de „nu s-a cerut" — ecranul trebuie sa poata face diferenta intre „inca
 * astept" si „chiar nu e nimic aici".
 */
export type Loaded<T> = { data: T | null; loading: boolean; missing: boolean };

/**
 * Fisele deja aduse, pastrate cat traieste aplicatia.
 *
 * Nu e o optimizare de dragul vitezei: „Alege bilete" e un ecran separat, care
 * are nevoie de ACELASI eveniment. Fara cache ar reinterogat serverul si ar
 * afisa o clipa un ecran gol dupa ce utilizatorul tocmai se uita la datele lui;
 * mai rau, ar putea prinde alt raspuns decat ecranul din spate.
 */
const cache = new Map<string, unknown>();

/**
 * Fisa unui eveniment deja adusa, citita SINCRON.
 *
 * Cosul si celelalte ecrane de cumparare nu sunt locul unde se asteapta o
 * cerere de retea: utilizatorul tocmai a apasat „Continua". Ele citeau doar
 * datasetul prototipului, deci pe un eveniment real primeau `undefined` si
 * ecranul ramanea negru — aplicatia parea blocata.
 */
export const cachedEvent = (id: string | undefined): EventRecord | null =>
  (id ? (cache.get(`event:${id}`) as EventRecord | undefined) : undefined) ?? null;

function useCatalog<A, T>(
  id: string | undefined,
  fetcher: (id: string, signal: AbortSignal) => Promise<A | null>,
  adapt: (raw: A) => T,
  ns: string,
): Loaded<T> {
  const cached = id ? (cache.get(`${ns}:${id}`) as T | undefined) : undefined;

  const [state, setState] = useState<Loaded<T>>(() =>
    cached !== undefined
      ? { data: cached, loading: false, missing: false }
      : { data: null, loading: !!id, missing: false },
  );

  useEffect(() => {
    if (!id) {
      setState({ data: null, loading: false, missing: false });

      return;
    }

    const hit = cache.get(`${ns}:${id}`) as T | undefined;
    if (hit !== undefined) {
      setState({ data: hit, loading: false, missing: false });

      return;
    }

    const ctrl = new AbortController();
    let alive = true;
    setState({ data: null, loading: true, missing: false });

    fetcher(id, ctrl.signal).then((raw) => {
      if (!alive) return;

      if (!raw) {
        // Esecurile NU se cacheaza: o pana de retea n-are voie sa marcheze
        // permanent un eveniment ca inexistent.
        setState({ data: null, loading: false, missing: true });

        return;
      }

      const adapted = adapt(raw);
      cache.set(`${ns}:${id}`, adapted);
      setState({ data: adapted, loading: false, missing: false });
    });

    return () => {
      alive = false;
      ctrl.abort();
    };
    // adapt/fetcher sunt stabile (module-level)
  }, [id, ns]);

  return state;
}

/**
 * Evenimentele proprii, in forma pe care o consuma cardurile.
 *
 * Se reincarca la schimbarea orasului. Esecul NU e o eroare de ecran: ecranul
 * cade pe Radar, care oricum e a doua sursa — deci lista goala e un rezultat
 * valid, nu o exceptie.
 */
export function useCatalogEvents(opts: { city?: string; limit?: number } = {}) {
  const { city, limit } = opts;
  const [items, setItems] = useState<UiEvent[]>([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    const ctrl = new AbortController();
    let alive = true;
    setLoading(true);

    void fetchCatalogEvents({ city, limit }, ctrl.signal).then((list) => {
      if (!alive) return;
      /* `Array.isArray`, nu doar `?? []`: un raspuns malformat (proxy care
         intoarce alt JSON, endpoint schimbat) ar fi ajuns direct in `.map` si
         ar fi doborat ecranul de pornire. */
      setItems(Array.isArray(list) ? list.map(toEventBrief) : []);
      setLoading(false);
    });

    return () => {
      alive = false;
      ctrl.abort();
    };
  }, [city, limit]);

  return { items, loading };
}

const adaptArtist = (a: CatalogArtist) => ({ rec: toArtistRecord(a), events: a.events.map(toEventBrief) });
const adaptVenue = (v: CatalogVenue) => ({
  rec: toVenueRecord(v),
  events: v.events.map(toEventBrief),
  reviews: v.reviews,
});

export const useCatalogEvent = (id?: string) =>
  useCatalog(id, (k, signal) => fetchCatalogEvent(k, signal), toEventRecord, 'event');

export const useCatalogArtist = (id?: string) =>
  useCatalog(id, (k, signal) => fetchCatalogArtist(k, signal), adaptArtist, 'artist');

export const useCatalogVenue = (id?: string) =>
  useCatalog(id, (k, signal) => fetchCatalogVenue(k, signal), adaptVenue, 'venue');
