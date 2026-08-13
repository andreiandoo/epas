/* =========================================================
   API-ul de ORGANIZATOR al aplicației tics (/api/app/org/*).

   Aplicatia nu vorbeste direct cu lumea partenerului si NU are cheia lui de
   API: o cheie compilata in APK se poate extrage din fisier. Serverul e cel
   care tine cheile si ruteaza spre lumea in care traieste organizatorul.

   Tokenul de sesiune e al contului tics, nu al partenerului.
   ========================================================= */
import { anchorFromResponse } from '../offline/clock';
import type { CachedTicket } from '../offline/db';
import type { Poster } from '../offline/sync';

export const APP_API = import.meta.env.VITE_APP_API ?? 'https://core.tixello.com/api/app';

const TOKEN_LS = 'tixello.app.token.v1';

let token: string | null = null;

export function getAppToken(): string | null {
  if (token) return token;
  try {
    token = localStorage.getItem(TOKEN_LS);
  } catch {
    token = null;
  }
  return token;
}

export function setAppToken(t: string | null): void {
  token = t;
  try {
    if (t) localStorage.setItem(TOKEN_LS, t);
    else localStorage.removeItem(TOKEN_LS);
  } catch {
    /* fara persistenta, sesiunea tine cat aplicatia */
  }
}

type Envelope<T> = { success: boolean; data?: T; error?: string; acks?: unknown };

async function call<T>(path: string, init?: RequestInit): Promise<Envelope<T> | null> {
  const ctrl = new AbortController();
  const t = setTimeout(() => ctrl.abort(), 15000);
  try {
    const res = await fetch(`${APP_API}${path}`, {
      ...init,
      signal: ctrl.signal,
      headers: {
        Accept: 'application/json',
        ...(init?.body ? { 'Content-Type': 'application/json' } : null),
        ...(getAppToken() ? { Authorization: `Bearer ${getAppToken()}` } : null),
        ...init?.headers,
      },
    });

    /* Fiecare raspuns e o ocazie sa reancoram ceasul. La o poarta, telefonul
       poate sta ore offline; cu cat ancora e mai proaspata, cu atat ordonarea
       scanurilor e mai buna. */
    anchorFromResponse(res);

    if (res.status === 401) {
      setAppToken(null);
      return null;
    }
    return (await res.json()) as Envelope<T>;
  } catch {
    return null;
  } finally {
    clearTimeout(t);
  }
}

/* ---------- conectarea contului de organizator ---------- */

export async function connectOrganizer(
  marketplaceClientId: number,
  email: string,
  password: string,
): Promise<{ ok: boolean; error?: string }> {
  const r = await call<{ organizer: { id: number; name: string } }>('/org/connect', {
    method: 'POST',
    body: JSON.stringify({ marketplace_client_id: marketplaceClientId, email, password }),
  });
  if (!r) return { ok: false, error: 'Nu am putut contacta serverul.' };
  return r.success ? { ok: true } : { ok: false, error: r.error ?? 'Conectare eșuată.' };
}

/* ---------- evenimente ---------- */

export type OrgEvent = { id: string; title: string; date: string | null; time: string | null; status: string };

export async function fetchOrgEvents(): Promise<OrgEvent[] | null> {
  const r = await call<OrgEvent[]>('/org/events');
  return r?.success ? (r.data ?? []) : null;
}

/* ---------- inventarul pentru scanare offline ---------- */

export async function fetchOrgTickets(eventId: string): Promise<CachedTicket[] | null> {
  const r = await call<CachedTicket[]>(`/org/events/${encodeURIComponent(eventId)}/tickets`);
  return r?.success ? (r.data ?? []) : null;
}

/* ---------- trimiterea scanurilor ---------- */

/**
 * `Poster` pentru coada offline. Motorul nu stie de HTTP; primeste functia
 * asta, ca sa poata fi testat cu una falsa.
 */
export const scanPoster: Poster = async (batch) => {
  const ctrl = new AbortController();
  const t = setTimeout(() => ctrl.abort(), 20000);
  try {
    const res = await fetch(`${APP_API}/org/scans`, {
      method: 'POST',
      signal: ctrl.signal,
      headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        ...(getAppToken() ? { Authorization: `Bearer ${getAppToken()}` } : null),
      },
      body: JSON.stringify({ scans: batch }),
    });

    if (!res.ok) return { ok: false, response: res };

    const json = (await res.json()) as { success: boolean; acks?: { id: string; result?: string; error?: string }[] };
    return {
      ok: !!json.success,
      acks: json.acks as never,
      response: res,
    };
  } catch {
    // fara retea: coada pastreaza tot si reincearca mai tarziu
    return { ok: false };
  } finally {
    clearTimeout(t);
  }
};

/* =========================================================
   Personalul de la poartă

   Serverul deleaga catre acelasi controller ca aplicatia partenerului, deci
   sunt ACELEASI randuri si aceleasi reguli — nu o lista paralela.
   Dezactivarea e simetrica: cine dispare aici dispare si acolo.
   ========================================================= */
export type StaffMemberApi = {
  id: number;
  name: string;
  email: string;
  role: 'admin' | 'manager' | 'staff';
  status: 'pending' | 'active' | 'inactive';
  gate_id?: number | null;
  permissions?: string[] | null;
};

export async function fetchStaff(): Promise<StaffMemberApi[] | null> {
  const r = await call<StaffMemberApi[] | { members?: StaffMemberApi[] }>('/org/staff');
  if (!r?.success) return null;
  const d = r.data as StaffMemberApi[] | { members?: StaffMemberApi[] } | undefined;
  return Array.isArray(d) ? d : (d?.members ?? []);
}

export async function inviteStaff(payload: {
  name?: string;
  email: string;
  password: string;
  role: 'admin' | 'manager' | 'staff';
  gate_id?: number | null;
  event_ids?: number[];
  send_welcome_email?: boolean;
}): Promise<{ ok: boolean; error?: string }> {
  const r = await call('/org/staff', { method: 'POST', body: JSON.stringify(payload) });
  if (!r) return { ok: false, error: 'Nu am putut contacta serverul.' };
  return r.success ? { ok: true } : { ok: false, error: r.error ?? 'Adăugare eșuată.' };
}

export async function updateStaff(payload: {
  member_id: number;
  role?: 'admin' | 'manager' | 'staff';
  gate_id?: number | null;
  event_ids?: number[];
}): Promise<boolean> {
  const r = await call('/org/staff/update', { method: 'POST', body: JSON.stringify(payload) });
  return !!r?.success;
}

export async function removeStaff(memberId: number): Promise<boolean> {
  const r = await call('/org/staff/remove', { method: 'POST', body: JSON.stringify({ member_id: memberId }) });
  return !!r?.success;
}

/* =========================================================
   Vânzarea la ușă

   Aplicatia trimite DOAR ce tip de bilet si cate bucati — pretul il calculeaza
   serverul. Un pret venit de la client poate fi modificat de oricine
   intercepteaza cererea.

   `sale_id` e generat pe dispozitiv si retrimis identic la fiecare incercare:
   casierul apasa din nou cand reteaua intarzie, iar fara el omul ar plati o
   data si ar primi doua comenzi.
   ========================================================= */
export type PosSaleResult = {
  order_id: number;
  order_number: string;
  total: number;
  currency: string;
  tickets: { code: string; price: number }[];
};

const newSaleId = () =>
  typeof crypto !== 'undefined' && 'randomUUID' in crypto
    ? crypto.randomUUID()
    : `sale-${Date.now().toString(36)}-${Math.floor(Math.random() * 1e9).toString(36)}`;

export async function posSale(payload: {
  eventId: number;
  items: { ticket_type_id: number; qty: number }[];
  paymentMethod: 'cash' | 'card' | 'nfc';
  customer?: { email?: string; name?: string; phone?: string };
  /** se trimite acelasi la reincercare; generat automat daca lipseste */
  saleId?: string;
}): Promise<{ ok: boolean; sale?: PosSaleResult; error?: string }> {
  const r = await call<PosSaleResult>('/org/sale', {
    method: 'POST',
    body: JSON.stringify({
      sale_id: payload.saleId ?? newSaleId(),
      event_id: payload.eventId,
      items: payload.items,
      payment_method: payload.paymentMethod,
      customer: payload.customer ?? {},
    }),
  });

  if (!r) return { ok: false, error: 'Nu am putut contacta serverul.' };
  return r.success ? { ok: true, sale: r.data } : { ok: false, error: r.error ?? 'Vânzare eșuată.' };
}

/**
 * Autentificare in contul tics al aplicatiei (`/api/app/auth/login`).
 *
 * DE CE EXISTA DOUA AUTENTIFICARI
 * Ecranele de cont (bilete, portofel, comenzi) merg pe contul de CLIENT al
 * platformei; prietenii si cumpararea merg pe contul TICS, care traverseaza
 * toate marketplace-urile. Sunt doua sisteme reale, nu o scapare de design.
 *
 * Utilizatorul nu trebuie insa sa se autentifice de doua ori cu aceleasi date.
 * Dupa un login de client reusit incercam, tacut, si contul tics cu ACELEASI
 * credentiale. Daca exista, ecranul de prieteni functioneaza imediat; daca nu,
 * nu se intampla nimic si nu apare nicio eroare — n-ar avea ce sa faca omul cu
 * ea in acel moment.
 */
export async function appLogin(email: string, password: string): Promise<boolean> {
  try {
    const res = await fetch(`${APP_API}/auth/login`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
      body: JSON.stringify({ email, password }),
    });

    if (!res.ok) return false;

    const body = (await res.json()) as { success?: boolean; data?: { token?: string } };

    if (!body?.success || !body.data?.token) return false;

    setAppToken(body.data.token);

    return true;
  } catch {
    return false;
  }
}
