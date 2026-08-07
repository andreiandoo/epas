/* =========================================================
   Hook-uri pentru ecranele de cont (Bilete, Portofel, Profil).

   Regula, ca la Radar: daca exista un cont real conectat, aratam datele lui;
   altfel ramanem pe datasetul prototipului, ca aplicatia sa fie navigabila si
   fara credentiale. `live` spune ecranului din ce sursa se uita.
   ========================================================= */
import { useEffect, useState } from 'react';
import { MYTIX } from '../../mock/prototype';
import {
  fetchNotifications,
  fetchPaymentMethods,
  fetchReviews,
  fetchStats,
  fetchTickets,
  getCustomer,
  isLoggedIn,
  type AccountStats,
  type ApiNotification,
  type ApiPaymentMethod,
  type ApiReview,
  type ApiTicket,
} from '../../api/customer';

/* ---------- forma pe care o consuma ecranul de Bilete (cea din prototip) --- */
export type Pass = { name: string; code: string; checkedIn?: string };
export type TicketGroup = {
  /** id-ul evenimentului (sau al comenzii, cand evenimentul lipseste) */
  ev: string;
  title: string;
  venue: string;
  date: string;
  time: string;
  passes: Pass[];
  seat: string;
  cat: string;
  upcoming: boolean;
  live: boolean;
};

const MONTHS_RO = ['ian', 'feb', 'mar', 'apr', 'mai', 'iun', 'iul', 'aug', 'sep', 'oct', 'noi', 'dec'];

function fmtDate(iso: string | null): string {
  if (!iso) return '';
  const d = new Date(iso);
  if (Number.isNaN(d.getTime())) return '';
  return `${d.getUTCDate()} ${MONTHS_RO[d.getUTCMonth()]}`;
}

/**
 * API-ul da bilete individuale; ecranul afiseaza CARDURI PE EVENIMENT, cu
 * biletele dedesubt (asa e in prototip). Gruparea se face pe eveniment, iar
 * cand evenimentul lipseste (bilete fara meta) cade pe comanda.
 */
export function groupTickets(list: ApiTicket[]): TicketGroup[] {
  const byKey = new Map<string, TicketGroup>();

  for (const t of list) {
    const key = String(t.event_id ?? `order-${t.order_id ?? 'x'}`);
    let g = byKey.get(key);
    if (!g) {
      g = {
        ev: key,
        title: t.event ?? 'Bilet',
        venue: t.venue ?? '',
        date: fmtDate(t.date),
        time: (t.time ?? '').slice(0, 5),
        passes: [],
        seat: '',
        cat: t.type ?? '',
        upcoming: t.is_upcoming,
        live: true,
      };
      byKey.set(key, g);
    }
    g.passes.push({ name: t.type ?? 'Bilet', code: t.code });
    if (t.seat_label) g.seat = g.seat ? `${g.seat}, ${t.seat_label}` : t.seat_label;
  }

  const groups = [...byKey.values()];
  for (const g of groups) if (!g.seat) g.seat = '—';
  // intai ce urmeaza, apoi trecutul
  return groups.sort((a, b) => Number(b.upcoming) - Number(a.upcoming));
}

/** Datasetul prototipului, adus la aceeasi forma. */
function protoGroups(): TicketGroup[] {
  type ProtoTicket = { ev: string; passes: Pass[]; seat: string; cat: string; date?: string; slot?: string };
  return (MYTIX as unknown as ProtoTicket[]).map((t) => ({
    ev: t.ev,
    title: '',
    venue: '',
    date: t.date ?? '',
    time: '',
    passes: t.passes,
    seat: t.seat,
    cat: t.cat,
    upcoming: true,
    live: false,
  }));
}

export function useTickets() {
  const [groups, setGroups] = useState<TicketGroup[]>(protoGroups);
  const [live, setLive] = useState(false);
  const [loading, setLoading] = useState(isLoggedIn());

  useEffect(() => {
    if (!isLoggedIn()) return;
    let alive = true;
    fetchTickets()
      .then((list) => {
        if (!alive || !list) return;
        setGroups(groupTickets(list));
        setLive(true);
      })
      .finally(() => alive && setLoading(false));
    return () => {
      alive = false;
    };
  }, []);

  return { groups, live, loading };
}

export function useAccountStats() {
  const [stats, setStats] = useState<AccountStats | null>(null);

  useEffect(() => {
    if (!isLoggedIn()) return;
    let alive = true;
    fetchStats().then((s) => alive && s && setStats(s));
    return () => {
      alive = false;
    };
  }, []);

  return stats;
}

/** Cine e conectat; null cand rulam pe contul demo. */
export function useCustomer() {
  const [c] = useState(getCustomer);
  return c;
}

export const customerName = (c: ReturnType<typeof getCustomer>) =>
  c ? c.name || [c.first_name, c.last_name].filter(Boolean).join(' ') || c.email : null;

export const initialsOf = (name: string) =>
  name
    .split(/\s+/)
    .slice(0, 2)
    .map((w) => w[0]?.toUpperCase() ?? '')
    .join('') || 'AP';

export function usePaymentMethods() {
  const [cards, setCards] = useState<ApiPaymentMethod[] | null>(null);
  useEffect(() => {
    if (!isLoggedIn()) return;
    let alive = true;
    fetchPaymentMethods().then((c) => alive && c && setCards(c));
    return () => {
      alive = false;
    };
  }, []);
  return cards;
}

export function useNotifications() {
  const [list, setList] = useState<ApiNotification[] | null>(null);
  useEffect(() => {
    if (!isLoggedIn()) return;
    let alive = true;
    fetchNotifications().then((n) => alive && n && setList(n));
    return () => {
      alive = false;
    };
  }, []);
  return list;
}

export function useReviews() {
  const [list, setList] = useState<ApiReview[] | null>(null);
  useEffect(() => {
    if (!isLoggedIn()) return;
    let alive = true;
    fetchReviews().then((r) => alive && r && setList(r));
    return () => {
      alive = false;
    };
  }, []);
  return list;
}
