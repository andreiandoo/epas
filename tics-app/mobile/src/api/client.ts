/* =========================================================
   Client API — deocamdata MOCK (§13 nu e inca expus in EPAS).
   Contractul e cel din api/types.ts; cand tenant API-ul e gata,
   se comuta USE_MOCK pe false si se implementeaza request().
   Aplicatia trebuie sa ramana rulabila in permanenta.
   ========================================================= */
import type { LoginResponse, PropertiesResponse, Property } from './types';

export const API_BASE = import.meta.env.VITE_API_BASE ?? 'https://core.tixello.com/api';
export const USE_MOCK = true;

let authToken: string | null = null;
export const setToken = (t: string | null) => {
  authToken = t;
};

async function request<T>(path: string, init?: RequestInit): Promise<T> {
  const res = await fetch(API_BASE + path, {
    ...init,
    headers: {
      'Content-Type': 'application/json',
      Accept: 'application/json',
      ...(authToken ? { Authorization: `Bearer ${authToken}` } : null),
      ...init?.headers,
    },
  });
  if (!res.ok) throw new Error(`${res.status} ${res.statusText}`);
  return (await res.json()) as T;
}

/* =========================================================
   identityProps() — modelul de referinta din §3.
   Un email → una sau mai multe "proprietati". Aici, contul demo
   are 4: client + 3 organizatori (standard, festival, leisure).
   ========================================================= */
const DEMO_PROPERTIES: Property[] = [
  {
    kind: 'client',
    key: 'client',
    name: 'Andrei Popescu',
    sub: 'Cont client tics',
    email: 'andrei@tics.ro',
    icon: 'user',
    av: 'purple',
  },
  {
    kind: 'org',
    key: 'organizer',
    orgId: 101,
    account: 'organizer',
    vertical: 'organizer',
    ctx: 'organizer',
    name: 'Nord Events',
    sub: 'Organizator · concerte',
    role: 'admin',
    icon: 'star',
    av: 'red',
  },
  {
    kind: 'org',
    key: 'festival',
    orgId: 102,
    account: 'organizer',
    vertical: 'festival',
    ctx: 'festival',
    name: 'Neversea',
    sub: 'Festival · cashless',
    role: 'manager',
    icon: 'ticket',
    av: 'amber',
  },
  {
    kind: 'org',
    key: 'venue',
    orgId: 103,
    account: 'venue',
    vertical: 'leisure',
    ctx: 'organizer',
    name: 'Delta Adventure Park',
    sub: 'Leisure · venue-owner',
    role: 'admin',
    icon: 'boat',
    av: 'blue',
  },
];

/** Variantele de identitate pe care le poate avea un email (pentru test). */
export type IdentityKind = 'multi' | 'orgonly' | 'clientonly';

export function identityProps(kind: IdentityKind = 'multi'): Property[] {
  if (kind === 'clientonly') return DEMO_PROPERTIES.filter((p) => p.kind === 'client');
  if (kind === 'orgonly') return DEMO_PROPERTIES.filter((p) => p.kind === 'org');
  return DEMO_PROPERTIES;
}

/* ---------- endpointuri ---------- */
export const api = {
  /** POST /auth/login */
  async login(email: string, _password: string): Promise<LoginResponse> {
    if (USE_MOCK) {
      await delay(450);
      return { token: 'demo-token', user: { id: 1, name: 'Andrei Popescu', email } };
    }
    return request<LoginResponse>('/auth/login', {
      method: 'POST',
      body: JSON.stringify({ email, password: _password }),
    });
  },

  /** GET /me/properties */
  async properties(kind: IdentityKind = 'multi'): Promise<PropertiesResponse> {
    if (USE_MOCK) {
      await delay(150);
      return { properties: identityProps(kind) };
    }
    return request<PropertiesResponse>('/me/properties');
  },
};

const delay = (ms: number) => new Promise((r) => setTimeout(r, ms));
