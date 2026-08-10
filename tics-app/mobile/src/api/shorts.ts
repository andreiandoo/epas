/* =========================================================
   Strat de date SHORTS — API-ul real din EPAS (docs/plans/shorts.md §5).

   Endpointuri:
     GET  /api/tenant-client/shorts?feed=&cursor=&limit=
     GET  /api/tenant-client/shorts/{id}
     GET  /api/tenant-client/events/{slug}/shorts
     POST /api/tenant-client/shorts/events           (batched, accepta guest)
     POST /api/marketplace-client/customer/shorts/{id}/like
     POST /api/marketplace-client/customer/shorts/{id}/save

   Citirile sunt publice: feed-ul se poate parcurge inainte de login. Cand
   exista token, payload-ul vine imbogatit cu starea `viewer` (liked/saved).

   Ca in tenantClient.ts: daca sursa e goala sau indisponibila, apelantul
   decide fallback-ul — aici nu inventam date.
   ========================================================= */
import { API_ROOT } from './tenantClient';

/* ---------- forma din API (docs/plans/shorts.md §5) ---------- */
export type ShortSource = 'upload' | 'youtube' | 'tiktok' | 'instagram' | 'facebook';

export type ShortFeedSegment = 'for_you' | 'featured' | 'nearby' | 'following' | 'event' | 'artist';

export type ShortEventType =
  | 'impression'
  | 'view'
  | 'complete'
  | 'like'
  | 'unlike'
  | 'save'
  | 'unsave'
  | 'share'
  | 'cta_click'
  | 'skip';

export type ApiShort = {
  id: number;
  source: ShortSource;
  feed: ShortFeedSegment | null;
  playback: { hls_url: string | null; poster_url: string | null; blurhash: string | null };
  embed_html: string | null;
  source_url: string | null;
  duration: number | null;
  aspect: string;
  title: string | null;
  caption: string | null;
  hashtags: string[];
  language: string | null;
  music_credit: string | null;
  owner: { type: string | null; id: number; slug: string | null; name: string | null } | null;
  event: { id: number; slug: string | null; title: string | null; date: string | null } | null;
  cta: {
    type: 'buy_tickets' | 'open_event' | 'open_artist' | 'external';
    label: string | null;
    url: string | null;
    ticket_type_id: number | null;
    promo_code: string | null;
    /** Momentul deschiderii vanzarii, cand biletele nu sunt inca la vanzare (D2). */
    on_sale_at: string | null;
    pending: boolean;
  } | null;
  /** ['flashing','alcohol',...] — avertismente de continut (D7/D10). */
  content_flags: string[];
  stats: { likes: number; views: number; shares: number };
  /** `reminded` vine de la server, deci butonul „Amintește-mi" isi aminteste
   *  intre sesiuni si intre dispozitive, nu doar cat tine ecranul (D2). */
  viewer: { liked: boolean; saved: boolean; reminded?: boolean };
  /**
   * Prezent DOAR pe plasarile platite (D3). `label` vine de la server si
   * difera intre un eveniment boostat si o reclama de brand — nu il inlocui
   * cu un text hardcodat in client si nu il ascunde: dezvaluirea e obligatia
   * legala, iar serverul e singurul care stie ce fel de plasare e.
   */
  promoted?: {
    id: number;
    label: string;
    objective: 'event' | 'artist' | 'brand' | 'house';
    advertiser: string | null;
  } | null;
};

/**
 * Hint-uri de redare decise pe server de guardrail-ul de cost (D8).
 *
 * Cand consumul Bunny se apropie de plafon, platforma scade calitatea pentru
 * toata lumea — decizia ajunge pe telefon prin feed, fara release de app.
 * Se combina cu preferinta locala: oricare dintre ele porneste economia.
 */
export type ShortsPlaybackHints = {
  data_saver: boolean;
  max_height: number;
  prefetch_count: number;
};

export type ShortsPage = {
  feed: ShortFeedSegment;
  items: ApiShort[];
  playback?: ShortsPlaybackHints | null;
  next_cursor: string | null;
};

/** Un eveniment de telemetrie, exact cum il asteapta POST shorts/events. */
export type ShortTelemetryEvent = {
  short_id: number;
  type: ShortEventType;
  watch_ms?: number;
  watch_ratio?: number;
  feed?: ShortFeedSegment | null;
  meta?: Record<string, unknown>;
};

/**
 * Tokenul de `MarketplaceCustomer`.
 *
 * TODO(owner): app-ul inca ruleaza pe identitate mock (`api/client.ts` are
 * USE_MOCK=true), deci nimeni nu apeleaza inca asta si feed-ul merge ca guest —
 * corect prin design: citirile sunt publice, doar like/save cer cont. Cand
 * apare loginul real de client, cheama setShortsToken() in acelasi loc in care
 * se cheama setToken() din api/client.ts.
 */
let authToken: string | null = null;
export const setShortsToken = (token: string | null) => {
  authToken = token;
};

/** Sesiune anonima, ca telemetria de guest sa poata fi deduplicata pe device. */
const SESSION_KEY = 'tixello.shorts.session';
function sessionId(): string {
  try {
    const existing = localStorage.getItem(SESSION_KEY);
    if (existing) return existing;
    const fresh = `s-${Date.now().toString(36)}-${Math.random().toString(36).slice(2, 10)}`;
    localStorage.setItem(SESSION_KEY, fresh);
    return fresh;
  } catch {
    // Storage blocat (modul privat / WebView restrictiv) — sesiune volatila.
    return `s-${Date.now().toString(36)}`;
  }
}

async function request<T>(path: string, init?: RequestInit): Promise<T> {
  const res = await fetch(API_ROOT + path, {
    ...init,
    headers: {
      /* `Content-Type` DOAR cand chiar trimitem ceva: pe un GET ar transforma
         cererea intr-una „non-simpla" si ar adauga un preflight OPTIONS inutil
         inaintea fiecarei pagini de feed. */
      ...(init?.body ? { 'Content-Type': 'application/json' } : null),
      Accept: 'application/json',
      ...(authToken ? { Authorization: `Bearer ${authToken}` } : null),
      ...init?.headers,
    },
  });

  if (!res.ok) throw new Error(`${res.status} ${res.statusText}`);

  return (await res.json()) as T;
}

type Envelope<T> = { success: boolean; data: T };

/* ---------- citiri ---------- */

export async function fetchShortsFeed(opts: {
  feed?: ShortFeedSegment;
  cursor?: string | null;
  limit?: number;
  tenant?: number;
  /** Pentru segmentul „nearby" — bate orasul din profil. */
  city?: string;
  signal?: AbortSignal;
} = {}): Promise<ShortsPage> {
  const params = new URLSearchParams();
  if (opts.feed) params.set('feed', opts.feed);
  if (opts.cursor) params.set('cursor', opts.cursor);
  if (opts.limit) params.set('limit', String(opts.limit));
  if (opts.tenant) params.set('tenant', String(opts.tenant));
  if (opts.city) params.set('city', opts.city);

  // Fara asta, frequency cap-ul reclamelor nu se poate aplica vizitatorilor
  // nelogati — adica exact publicului majoritar al feed-ului, care ar vedea
  // aceeasi reclama la fiecare pagina pana se termina bugetul (D3).
  params.set('session_id', sessionId());

  const query = params.toString();
  const body = await request<Envelope<ShortsPage>>(`/tenant-client/shorts${query ? `?${query}` : ''}`, {
    signal: opts.signal,
  });

  return body.data;
}

export async function fetchShort(id: number): Promise<ApiShort> {
  const body = await request<Envelope<ApiShort>>(`/tenant-client/shorts/${id}`);
  return body.data;
}

export async function fetchEventShorts(slug: string, cursor?: string | null): Promise<ShortsPage> {
  const query = cursor ? `?cursor=${encodeURIComponent(cursor)}` : '';
  const body = await request<Envelope<ShortsPage>>(`/tenant-client/events/${encodeURIComponent(slug)}/shorts${query}`);
  return body.data;
}

/* ---------- telemetrie ---------- */

/**
 * Trimite un lot de evenimente. Fire-and-forget prin design: telemetria nu
 * trebuie sa poata strica redarea, deci esecurile sunt inghitite.
 *
 * Foloseste sendBeacon cand pagina moare (ultimul lot la inchiderea app-ului),
 * altfel fetch normal.
 */
export async function sendShortEvents(events: ShortTelemetryEvent[], useBeacon = false): Promise<void> {
  if (events.length === 0) return;

  const payload = JSON.stringify({ session_id: sessionId(), events });
  const url = `${API_ROOT}/tenant-client/shorts/events`;

  if (useBeacon && typeof navigator !== 'undefined' && typeof navigator.sendBeacon === 'function') {
    try {
      navigator.sendBeacon(url, new Blob([payload], { type: 'application/json' }));
      return;
    } catch {
      // Cade pe fetch mai jos.
    }
  }

  try {
    await fetch(url, {
      method: 'POST',
      keepalive: true,
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        ...(authToken ? { Authorization: `Bearer ${authToken}` } : null),
      },
      body: payload,
    });
  } catch {
    // Telemetria nu are voie sa arunce in fata utilizatorului.
  }
}

/* ---------- interactiuni (necesita token) ---------- */

export type ToggleResult = { short_id: number; active: boolean; count: number };

export async function toggleShortLike(id: number, feed?: ShortFeedSegment | null): Promise<ToggleResult> {
  const body = await request<Envelope<ToggleResult>>(`/marketplace-client/customer/shorts/${id}/like`, {
    method: 'POST',
    body: JSON.stringify({ feed }),
  });

  return body.data;
}

export async function toggleShortSave(id: number, feed?: ShortFeedSegment | null): Promise<ToggleResult> {
  const body = await request<Envelope<ToggleResult>>(`/marketplace-client/customer/shorts/${id}/save`, {
    method: 'POST',
    body: JSON.stringify({ feed }),
  });

  return body.data;
}

export const isAuthenticated = () => authToken !== null;

/* ---------- val 1: share, remind, streak ---------- */

export type ShareResult = {
  share_id: number;
  token: string;
  url: string;
  card_url: string | null;
  deep_link: string;
  points?: { points: number; awarded: boolean };
};

/**
 * Cere serverului un link de share. Serverul minteste tokenul si baga codul de
 * referral al utilizatorului in URL (D1) — clientul nu construieste linkul de
 * atribuire singur, ca sa nu existe doua reguli pentru acelasi lucru.
 */
export async function shareShort(
  id: number,
  channel?: string,
  feed?: ShortFeedSegment | null,
): Promise<ShareResult | null> {
  try {
    const body = await request<Envelope<ShareResult>>(`/marketplace-client/customer/shorts/${id}/share`, {
      method: 'POST',
      body: JSON.stringify({ channel, feed }),
    });

    return body.data;
  } catch {
    return null;
  }
}

/** „Amintește-mi cand intra biletele" (D2). */
export async function remindMe(id: number): Promise<boolean> {
  try {
    await request(`/marketplace-client/customer/shorts/${id}/remind`, { method: 'POST' });

    return true;
  } catch {
    return false;
  }
}

export async function forgetReminder(id: number): Promise<boolean> {
  try {
    await request(`/marketplace-client/customer/shorts/${id}/remind`, { method: 'DELETE' });

    return true;
  } catch {
    return false;
  }
}

export type StreakResult = { points: number; streak: number; awarded: boolean };

/**
 * Anunta prima vizionare credibila din sesiune. Serverul decide daca e prima
 * din zi — ziua e o notiune de server, nu de ceas local (D11).
 */
export async function recordDailyWatch(): Promise<StreakResult | null> {
  try {
    const body = await request<Envelope<StreakResult>>('/marketplace-client/customer/shorts/watched', {
      method: 'POST',
    });

    return body.data;
  } catch {
    return null;
  }
}

export type StreakState = {
  current_streak: number;
  longest_streak: number;
  total_points: number;
  last_watch_date: string | null;
};

export async function fetchStreak(): Promise<StreakState | null> {
  try {
    const body = await request<Envelope<StreakState>>('/marketplace-client/customer/shorts/streak');

    return body.data;
  } catch {
    return null;
  }
}

/* ---------- val 2: CTA shoppable (B1) ---------- */

export type CtaClickResult = {
  short_id: number;
  /** Campania taxata, cand tap-ul a venit dintr-un slot platit (D3). */
  promotion_id: number | null;
  checkout: {
    event_id: number | null;
    ticket_type_id: number | null;
    promo_code: string | null;
    source_short_id: number;
    source_feed: string | null;
  };
};

/**
 * Raporteaza tap-ul pe CTA si primeste inapoi oferta de onorat la checkout.
 *
 * NU trece prin coada de telemetrie: e ultimul lucru pe care il face clientul
 * inainte sa plece din feed spre checkout, iar un flush la 5 secunde ar prinde
 * ecranul deja inchis. Public — tap-ul se intampla la fel de des inainte de
 * login ca dupa.
 *
 * `promotionId` se trimite doar cand short-ul a venit dintr-un slot platit: e
 * exact `short.promoted.id` din feed. Serverul refuza sa taxeze un id care nu
 * apartine short-ului, deci un short atins organic nu costa pe nimeni nimic.
 */
export async function reportCtaClick(
  id: number,
  feed?: ShortFeedSegment | null,
  promotionId?: number | null,
): Promise<CtaClickResult | null> {
  try {
    const body = await request<Envelope<CtaClickResult>>(`/tenant-client/shorts/${id}/cta-click`, {
      method: 'POST',
      body: JSON.stringify({ feed, promotion_id: promotionId ?? null }),
    });

    return body.data;
  } catch {
    // Nu blocam navigarea spre checkout pentru o problema de raportare.
    return null;
  }
}

/* ---------- val 2: graful de urmarire (B2) ---------- */

export type FollowableType = 'artist' | 'tenant' | 'venue';

export type FollowItem = {
  id: number;
  type: FollowableType | null;
  followable_id: number;
  name: string | null;
  slug: string | null;
  followed_at: string | null;
};

export async function fetchFollows(): Promise<FollowItem[]> {
  try {
    const body = await request<Envelope<{ items: FollowItem[] }>>('/marketplace-client/customer/follows');

    return body.data.items;
  } catch {
    return [];
  }
}

/**
 * Toggle de urmarire. Serverul decide starea finala si o intoarce — clientul nu
 * o deduce, ca doua tap-uri rapide sa nu poata desincroniza butonul.
 */
export async function toggleFollow(
  type: FollowableType,
  id: number,
): Promise<{ following: boolean } | null> {
  try {
    const body = await request<Envelope<{ following: boolean }>>('/marketplace-client/customer/follows', {
      method: 'POST',
      body: JSON.stringify({ type, id }),
    });

    return body.data;
  } catch {
    return null;
  }
}
