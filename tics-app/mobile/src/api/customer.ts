/* =========================================================
   CONT CLIENT — API-ul real de cont din EPAS.

   Endpointuri (verificate live pe core.tixello.com):
     POST /api/tenant-client/auth/login?hostname=<domeniu>
          {email, password} -> {success, data:{token, customer}}
     GET  /api/tenant-client/account/stats?tenant=<id>      (Bearer)
     GET  /api/tenant-client/account/tickets?tenant=<id>    (Bearer)
     GET  /api/tenant-client/account/orders?tenant=<id>     (Bearer)
     GET  /api/tenant-client/account/favorites|notifications|reviews
     GET  /api/tenant-client/account/payment-methods|gift-cards
     POST /api/tenant-client/account/profile

   ATENTIE la doua conventii DIFERITE de rezolvare a tenantului, in acelasi
   prefix: autentificarea il cauta dupa DOMENIU (`?hostname=` sau antetul
   X-Tenant-Domain — vezi AuthController::resolveTenant), iar restul contului
   dupa ID (`?tenant=` — trait-ul ResolvesTenant). Trimitem ambele peste tot,
   ca sa nu conteze pe care nimereste ruta.

   Tokenul e un CustomerToken cu viata de 30 de zile; il tinem in localStorage,
   ca sesiunea sa supravietuiasca repornirii WebView-ului.
   ========================================================= */
export const API_ROOT = import.meta.env.VITE_API_BASE ?? 'https://core.tixello.com/api';
export const TENANT_ID = Number(import.meta.env.VITE_TENANT_ID ?? 17);
export const TENANT_HOST = import.meta.env.VITE_TENANT_HOST ?? 'teatru.tixello.ro';

const TOKEN_LS = 'tixello.customer.token.v1';

export type Customer = {
  id: number;
  name?: string | null;
  first_name?: string | null;
  last_name?: string | null;
  email: string;
  phone?: string | null;
  /** URL absolut catre poza de profil, sau null. */
  avatar?: string | null;
};

let token: string | null = null;
let customer: Customer | null = null;

export function getToken(): string | null {
  if (token) return token;
  try {
    const raw = localStorage.getItem(TOKEN_LS);
    if (!raw) return null;
    const v = JSON.parse(raw) as { token: string; customer: Customer | null };
    token = v.token;
    customer = v.customer;
    return token;
  } catch {
    return null;
  }
}

export const getCustomer = (): Customer | null => {
  getToken();
  return customer;
};

/**
 * `remember` decide daca sesiunea supravietuieste repornirii.
 *
 * Bifa „Ține-mă minte" chiar face ceva acum: nebifata, tokenul ramane doar in
 * memorie si dispare cand se inchide aplicatia. Pe un telefon imprumutat, asta
 * e diferenta dintre a te deconecta si a lasa contul deschis.
 */
function setSession(t: string | null, c: Customer | null, remember = true) {
  token = t;
  customer = c;
  try {
    if (t && remember) localStorage.setItem(TOKEN_LS, JSON.stringify({ token: t, customer: c }));
    else localStorage.removeItem(TOKEN_LS);
  } catch {
    /* fara persistenta, doar se pierde la repornire */
  }
}

export const clearCustomer = () => setSession(null, null);
export const isLoggedIn = () => !!getToken();

/* ---------- transport ---------- */
const qs = (extra: Record<string, string> = {}) =>
  new URLSearchParams({ tenant: String(TENANT_ID), hostname: TENANT_HOST, ...extra }).toString();

async function call<T>(path: string, init?: RequestInit & { auth?: boolean }): Promise<T | null> {
  const ctrl = new AbortController();
  const t = setTimeout(() => ctrl.abort(), 12000);
  try {
    const res = await fetch(`${API_ROOT}${path}${path.includes('?') ? '&' : '?'}${qs()}`, {
      ...init,
      signal: ctrl.signal,
      headers: {
        Accept: 'application/json',
        'X-Tenant-Domain': TENANT_HOST,
        /* FormData isi pune singur antetul, cu tot cu separator; scris de noi,
           ar rupe corpul cererii. */
        ...(init?.body && !(init.body instanceof FormData) ? { 'Content-Type': 'application/json' } : null),
        ...(init?.auth !== false && getToken() ? { Authorization: `Bearer ${getToken()}` } : null),
        ...init?.headers,
      },
    });
    // token expirat sau revocat: iesim curat, ca ecranele sa cada pe demo
    if (res.status === 401) {
      clearCustomer();
      return null;
    }
    if (!res.ok) return null;
    return (await res.json()) as T;
  } catch {
    return null;
  } finally {
    clearTimeout(t);
  }
}

/* ---------- autentificare ---------- */
type LoginResponse = { success: boolean; data?: { token: string; customer: Customer } };

/** Intoarce clientul la succes, null daca datele-s gresite sau API-ul tace. */
export async function customerLogin(email: string, password: string, remember = true): Promise<Customer | null> {
  const r = await call<LoginResponse>('/tenant-client/auth/login', {
    method: 'POST',
    body: JSON.stringify({ email, password }),
    auth: false,
  });
  if (!r?.success || !r.data?.token) return null;
  setSession(r.data.token, r.data.customer ?? null, remember);

  return r.data.customer ?? null;
}

export async function customerLogout(): Promise<void> {
  await call('/tenant-client/auth/logout', { method: 'POST' });
  clearCustomer();
}

/* ---------- date de cont ---------- */
export type AccountStats = {
  upcoming_tickets: number;
  active_subscriptions: number;
  favorites: number;
  points: number;
  total_spent: number;
  orders_count: number;
};

export type ApiTicket = {
  code: string;
  type: string | null;
  seat_label: string | null;
  event_id: number | null;
  event: string | null;
  venue: string | null;
  date: string | null;
  time: string | null;
  is_upcoming: boolean;
  order_id: number | null;
  is_subscription: boolean;
};

type Wrapped<T> = { success: boolean; data: T };

export const fetchStats = () =>
  call<Wrapped<AccountStats>>('/tenant-client/account/stats').then((r) => r?.data ?? null);

export const fetchTickets = () =>
  call<Wrapped<ApiTicket[]>>('/tenant-client/account/tickets').then((r) => r?.data ?? null);

export type ApiOrder = {
  id: number;
  reference?: string | null;
  status: string;
  total?: number;
  created_at?: string | null;
  tickets_count?: number;
};

export const fetchOrders = () =>
  call<Wrapped<ApiOrder[]>>('/tenant-client/account/orders').then((r) => r?.data ?? null);

export type ApiPaymentMethod = {
  id: number;
  brand: string | null;
  last4: string | null;
  /** deja formatat de server ca „MM/YY" */
  exp: string | null;
  holder: string | null;
  is_default: boolean;
};

/** Datele de facturare vin alaturi de carduri, la radacina raspunsului. */
export type BillingInfo = { name: string | null; email: string | null; phone: string | null };

export const fetchPaymentMethods = () =>
  call<Wrapped<ApiPaymentMethod[]> & { billing?: BillingInfo }>('/tenant-client/account/payment-methods').then((r) =>
    r?.success ? { cards: r.data ?? [], billing: r.billing ?? null } : null,
  );

export const setDefaultPaymentMethod = (id: number) =>
  call<{ success: boolean }>(`/tenant-client/account/payment-methods/${id}/default`, { method: 'POST' }).then(
    (r) => r?.success === true,
  );

export const removePaymentMethod = (id: number) =>
  call<{ success: boolean }>(`/tenant-client/account/payment-methods/${id}`, { method: 'DELETE' }).then(
    (r) => r?.success === true,
  );

/**
 * ATENTIE: serverul NU tokenizeaza cardul la un procesator — pastreaza doar
 * brandul, ultimele 4 cifre si expirarea, cu un `token` fictiv. Lista e utila
 * ca preferinta a clientului, dar cu ea NU se poate plati. Plata reala vine
 * odata cu integrarea Stripe.
 */
export const addPaymentMethod = (number: string, holder?: string, exp?: string) =>
  call<Wrapped<{ id: number }>>('/tenant-client/account/payment-methods', {
    method: 'POST',
    body: JSON.stringify({ number, holder, exp }),
  }).then((r) => (r?.success ? (r.data?.id ?? null) : null));

/* ---------- favorite ---------- */

/** Serverul accepta DOAR aceste doua tipuri (vezi validarea din toggleFavorite). */
export type FavoriteType = 'event' | 'artist';

/** Peste id-uri se intinde `meta`, care e liber ca forma — de aici Record-ul. */
export type ApiFavorite = { id: number; item_type: FavoriteType; item_id: number } & Record<string, unknown>;

/** Raspunsul are exact aceste doua chei; nu exista grup pentru sali. */
export type Favorites = { events: ApiFavorite[]; artists: ApiFavorite[] };

export const fetchFavorites = () =>
  call<Wrapped<Favorites>>('/tenant-client/account/favorites').then((r) => (r?.success ? (r.data ?? null) : null));

/**
 * Intoarce noua stare, sau null daca cererea a esuat.
 * ATENTIE: aici `favorited` sta la RADACINA raspunsului, nu sub `data` ca la
 * restul endpointurilor din acest controller.
 */
export const toggleFavorite = (itemType: FavoriteType, itemId: number, meta?: Record<string, unknown>) =>
  call<{ success: boolean; favorited: boolean }>('/tenant-client/account/favorites/toggle', {
    method: 'POST',
    body: JSON.stringify({ item_type: itemType, item_id: itemId, ...(meta ? { meta } : null) }),
  }).then((r) => (r?.success ? r.favorited : null));

export type ApiNotification = {
  id?: number | string;
  title?: string | null;
  body?: string | null;
  message?: string | null;
  created_at?: string | null;
  read?: boolean;
  type?: string | null;
};

export const fetchNotifications = () =>
  call<Wrapped<ApiNotification[]>>('/tenant-client/account/notifications').then((r) => r?.data ?? null);

export type ApiReview = {
  id: number;
  event: string | null;
  rating: number;
  title: string | null;
  body: string | null;
  /** 'published' | 'pending' | ... */
  status: string;
  created_at: string | null;
};

export type ReviewStats = { total: number; published: number; pending: number; avg: number };

/** Raspunsul are si `stats`, in afara lui `data` — le intoarcem impreuna. */
export const fetchReviews = () =>
  call<Wrapped<ApiReview[]> & { stats?: ReviewStats }>('/tenant-client/account/reviews').then((r) =>
    r?.success ? { items: r.data ?? [], stats: r.stats ?? null } : null,
  );

export type ApiGiftCard = { id?: number; code?: string; balance?: number; currency?: string };

export const fetchGiftCards = () =>
  call<Wrapped<ApiGiftCard[]>>('/tenant-client/account/gift-cards').then((r) => r?.data ?? null);

/**
 * Poza de profil.
 *
 * Se trimite ca `multipart/form-data`, deci NU se pune `Content-Type` de mana:
 * antetul trebuie sa contina si separatorul (`boundary`), pe care doar browserul
 * il stie. Scris manual, serverul ar primi un corp pe care nu-l poate desface.
 */
export async function uploadAvatar(file: File): Promise<string | null> {
  const body = new FormData();
  body.append('avatar', file);

  const r = await call<Wrapped<{ avatar: string | null }>>('/tenant-client/account/avatar', {
    method: 'POST',
    body,
  });

  return r?.success ? (r.data?.avatar ?? null) : null;
}

export const removeAvatar = () =>
  call<Wrapped<{ avatar: null }>>('/tenant-client/account/avatar', { method: 'DELETE' }).then(
    (r) => r?.success === true,
  );

export const updateProfile = (payload: Record<string, unknown>) =>
  call<Wrapped<Customer>>('/tenant-client/account/profile', {
    method: 'POST',
    body: JSON.stringify(payload),
  }).then((r) => r?.data ?? null);
