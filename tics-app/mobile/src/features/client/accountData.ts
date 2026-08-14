/* =========================================================
   Hook-uri pentru ecranele de cont (Bilete, Portofel, Profil).

   Regula, ca la Radar: daca exista un cont real conectat, aratam datele lui;
   altfel ramanem pe datasetul prototipului, ca aplicatia sa fie navigabila si
   fara credentiale. `live` spune ecranului din ce sursa se uita.
   ========================================================= */
import { useEffect, useState } from 'react';
import { MYTIX } from '../../mock/prototype';
import {
  fetchFavorites,
  fetchGiftCards,
  fetchOrders,
  fetchNotifications,
  fetchPaymentMethods,
  removePaymentMethod,
  setDefaultPaymentMethod,
  fetchReviews,
  fetchStats,
  fetchTickets,
  getCustomer,
  isLoggedIn,
  toggleFavorite,
  type AccountStats,
  type ApiNotification,
  type ApiPaymentMethod,
  type ApiReview,
  type ApiTicket,
  type BillingInfo,
  type FavoriteType,
  type Favorites,
  type ReviewStats,
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

  /* Ziua de azi, ca sa stim ce e „viitor". Serverul NU trimite un `is_upcoming`
     — il calculam noi din data evenimentului. */
  const today = new Date();
  today.setHours(0, 0, 0, 0);

  for (const t of list) {
    /* Gruparea se face pe SLUG-ul evenimentului: raspunsul nu poarta id-ul lui.
       Fara slug, biletul isi tine propriul rand — mai bine un card in plus decat
       doua evenimente diferite amestecate sub acelasi titlu. */
    const key = t.event_slug ?? `ticket-${t.id}`;
    const when = t.date ? new Date(t.date) : null;

    let g = byKey.get(key);
    if (!g) {
      g = {
        ev: key,
        title: t.event_name ?? 'Bilet',
        venue: t.venue ?? '',
        date: fmtDate(t.date),
        time: when && !Number.isNaN(when.getTime()) && when.getUTCHours() + when.getUTCMinutes() > 0
          ? `${String(when.getUTCHours()).padStart(2, '0')}:${String(when.getUTCMinutes()).padStart(2, '0')}`
          : '',
        passes: [],
        seat: '',
        cat: t.ticket_type ?? '',
        upcoming: when ? when >= today : true,
        live: true,
      };
      byKey.set(key, g);
    }
    g.passes.push({ name: t.ticket_type ?? 'Bilet', code: t.code });
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
  /* Cu sesiune reala pornim GOL, nu cu biletele prototipului: altfel primul
     cadru arata bilete care nu sunt ale nimanui, iar dupa o secunda sar. */
  const [groups, setGroups] = useState<TicketGroup[]>(isLoggedIn() ? [] : protoGroups);
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
/**
 * Clientul autentificat.
 *
 * `useState(getCustomer)` retinea valoarea de la PRIMA randare si nu se mai
 * uita niciodata inapoi — deci poza de profil incarcata intr-un ecran nu
 * aparea in celelalte pana la repornirea aplicatiei. Se citeste la fiecare
 * montare si la revenirea in aplicatie.
 */
export function useCustomer() {
  const [c, setC] = useState(getCustomer);

  useEffect(() => {
    setC(getCustomer());

    const onFocus = () => setC(getCustomer());
    window.addEventListener('focus', onFocus);

    return () => window.removeEventListener('focus', onFocus);
  }, []);

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
  const [billing, setBilling] = useState<BillingInfo | null>(null);
  const [loading, setLoading] = useState(isLoggedIn());

  const load = () =>
    fetchPaymentMethods().then((r) => {
      if (!r) return;
      setCards(r.cards);
      setBilling(r.billing);
    });

  useEffect(() => {
    if (!isLoggedIn()) return;
    let alive = true;
    fetchPaymentMethods()
      .then((r) => {
        if (!alive || !r) return;
        setCards(r.cards);
        setBilling(r.billing);
      })
      .finally(() => alive && setLoading(false));
    return () => {
      alive = false;
    };
  }, []);

  /* Dupa „principal" reincarcam de pe server: schimbarea atinge TOATE randurile
     (celelalte devin nepricipale), deci o corectie locala ar putea rata unul. */
  const makeDefault = async (id: number) => {
    if (await setDefaultPaymentMethod(id)) await load();
  };

  const remove = async (id: number) => {
    if (await removePaymentMethod(id)) setCards((cur) => (cur ?? []).filter((c) => c.id !== id));
  };

  return { cards, billing, loading, live: cards !== null, makeDefault, remove, reload: load };
}

export function useNotifications() {
  const [list, setList] = useState<ApiNotification[] | null>(null);
  /* `loading` porneste ADEVARAT cand exista sesiune: fara el, ecranul nu poate
     face diferenta intre „inca astept raspunsul" si „nu am nimic", asa ca
     afisa datele prototipului pana venea raspunsul — o secunda de continut
     inventat, care apoi se schimba sub ochii omului. */
  const [loading, setLoading] = useState(isLoggedIn());

  useEffect(() => {
    if (!isLoggedIn()) return;
    let alive = true;
    fetchNotifications()
      .then((n) => alive && n && setList(n))
      .finally(() => alive && setLoading(false));
    return () => {
      alive = false;
    };
  }, []);

  return { list, loading };
}

/**
 * Recenziile vin impreuna cu un sumar (`stats`) calculat pe server — total,
 * publicate, in asteptare, medie. Il pastram asa cum e: recalculat in aplicatie
 * ar da alte cifre, pentru ca lista poate fi paginata iar media serverului tine
 * cont doar de cele publicate.
 */
export function useReviews() {
  const [list, setList] = useState<ApiReview[] | null>(null);
  const [stats, setStats] = useState<ReviewStats | null>(null);
  const [loading, setLoading] = useState(isLoggedIn());

  useEffect(() => {
    if (!isLoggedIn()) return;
    let alive = true;
    fetchReviews()
      .then((r) => {
        if (!alive || !r) return;
        setList(r.items);
        setStats(r.stats);
      })
      .finally(() => alive && setLoading(false));
    return () => {
      alive = false;
    };
  }, []);

  return { list, stats, loading, live: list !== null };
}

/**
 * Favoritele sosesc grupate pe tip, iar peste id-uri e intins `meta`.
 * Serverul cunoaste DOAR evenimente si artisti — nu exista favorite pe sali,
 * desi prototipul avea un tab pentru ele. Tabul acela ramane gol pana cand
 * exista si in API, ca sa nu inventam o functie care n-are unde sa salveze.
 */
export type SavedItem = {
  id: number;
  kind: FavoriteType;
  itemId: number;
  title: string;
  sub: string;
  img: string | null;
};

const asText = (v: unknown): string => (typeof v === 'string' ? v : '');

const GROUPS: ReadonlyArray<[keyof Favorites, FavoriteType]> = [
  ['events', 'event'],
  ['artists', 'artist'],
];

function flattenFavorites(f: Favorites): SavedItem[] {
  const out: SavedItem[] = [];
  for (const [group, kind] of GROUPS) {
    for (const it of f[group] ?? []) {
      out.push({
        id: it.id,
        kind,
        itemId: it.item_id,
        // `meta` e liber ca forma, deci incercam cheile uzuale, in ordine
        title: asText(it.title) || asText(it.name) || `#${it.item_id}`,
        sub: asText(it.venue) || asText(it.date) || asText(it.city) || asText(it.subtitle),
        img: asText(it.image) || asText(it.poster) || asText(it.cover) || null,
      });
    }
  }
  return out;
}

export function useFavorites() {
  const [items, setItems] = useState<SavedItem[] | null>(null);
  const [loading, setLoading] = useState(isLoggedIn());

  useEffect(() => {
    if (!isLoggedIn()) return;
    let alive = true;
    fetchFavorites()
      .then((f) => alive && f && setItems(flattenFavorites(f)))
      .finally(() => alive && setLoading(false));
    return () => {
      alive = false;
    };
  }, []);

  /** Scoate din favorite si actualizeaza lista pe loc, fara sa reincarce tot. */
  const remove = async (it: SavedItem) => {
    const favorited = await toggleFavorite(it.kind, it.itemId);
    // null = cererea a esuat; lasam randul pe ecran, ca sa nu mintim utilizatorul
    if (favorited === false) setItems((cur) => (cur ?? []).filter((x) => x.id !== it.id));
  };

  return { items, loading, live: items !== null, remove };
}


/* ---------- portofel ---------- */

export type WalletTx = { title: string; when: string; amount: string; credit: boolean };

/**
 * Ce arata Portofelul cand exista un cont real.
 *
 * `balance` e suma cardurilor cadou active — singurul sold care exista chiar in
 * sistem. NU exista un portofel cashless pe server, deci nici nu inventam unul:
 * fara carduri cadou, soldul e zero si asta e adevarat.
 *
 * Tranzactiile sunt comenzile clientului. Toate sunt debite: o comanda inseamna
 * bani cheltuiti. Reincarcarile ar aparea aici cand va exista un portofel real.
 */
export function useWallet() {
  const [tx, setTx] = useState<WalletTx[] | null>(null);
  const [balance, setBalance] = useState<number | null>(null);

  useEffect(() => {
    if (!isLoggedIn()) return;
    let alive = true;

    void fetchOrders().then((orders) => {
      if (!alive || !orders) return;

      setTx(
        orders.map((o) => ({
          title: o.order_number ? `Comanda ${o.order_number}` : `Comanda #${o.id}`,
          /* `date` vine deja formatata de server („08 Aug 2026"); n-o mai
             reformatam, ca sa nu ajungem cu doua formate diferite in acelasi
             ecran cand serverul isi schimba locale-ul. */
          when: o.date ?? '',
          amount: `-${String(o.total ?? '0').replace('.', ',')}`,
          credit: false,
        })),
      );
    });

    void fetchGiftCards().then((cards) => {
      if (!alive || !cards) return;

      setBalance(cards.reduce((sum, c) => sum + (Number(c.balance) || 0), 0));
    });

    return () => {
      alive = false;
    };
  }, []);

  return { tx, balance, live: tx !== null || balance !== null };
}
