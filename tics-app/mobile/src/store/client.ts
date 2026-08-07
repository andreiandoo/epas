/* =========================================================
   Starea clientului — oglindeste obiectul `ST` din client-app.html
   (§12.3: "replica structura de stare si fluxurile").
   Valorile initiale sunt copiate din prototip, ca ecranele sa porneasca
   in exact aceeasi stare ca macheta.
   ========================================================= */
import { create } from 'zustand';
import { ST as PROTO_ST } from '../mock/prototype';

type ProtoSt = {
  prefs: string[];
  prefsSel: string[];
  ev: string;
  seats: string[];
  obStep: number;
  cart: { protect: boolean; cultural: boolean; discount: number; nameOnTicket: boolean };
  balance: number;
  points: number;
  stayPin: number;
  expDate: number;
  expDay: number;
  addons: Record<string, boolean>;
  calDay: number;
  fStage: number;
  fDay: string;
  saved: string[];
  revRating: number;
  revTab: number;
  rateStars: number;
  cards: { brand: string; last: string; exp: string; grad: string; primary: boolean }[];
};

const seed = PROTO_ST as unknown as ProtoSt;

/** ST.catF din prototip — filtrele ecranului de categorie. */
export type CatFilters = { sort: 'rec' | 'price' | 'rating'; maxPrice: number; city: string; seated: boolean };
const CAT_DEFAULTS: CatFilters = { sort: 'rec', maxPrice: 500, city: '', seated: false };

/* ---------- Radar (date reale din app.tics.ro) ---------- */

/** Chip-urile din bara de filtre a Radarului. */
export type RadarFilters = {
  when: 'all' | 'today' | 'weekend';
  /** pragul chip-ului "Sub 100 lei"; 0 = fara prag */
  maxPrice: number;
  /** chip-ul "Aproape sold-out" */
  scarce: boolean;
};
const RADAR_DEFAULTS: RadarFilters = { when: 'all', maxPrice: 0, scarce: false };

/** Filtrele calendarului: oras / tip / gen. */
export type CalFilters = { city: string; type: string; genre: string };
const CAL_DEFAULTS: CalFilters = { city: '', type: '', genre: '' };

type ClientState = ProtoSt & {
  /** contoarele de bilete de pe ecranul de tipuri (ST._ttCounts din prototip) */
  ttCounts: Record<string, number[]>;
  /** filtrele de categorie + categoria pentru care sunt valabile (ST._catFor) */
  catF: CatFilters;
  catFor: string | null;
  /** orasul ales din antetul de pe Acasa; '' = toata tara */
  city: string;
  setCity: (city: string) => void;
  /** filtrele ecranului Radar */
  radarF: RadarFilters;
  setRadarF: (patch: Partial<RadarFilters>) => void;
  resetRadarF: () => void;
  /** filtrele calendarului */
  calF: CalFilters;
  setCalF: (patch: Partial<CalFilters>) => void;
  /** ST.stayF — filtrele hartii Stay22 */
  stayF: { type: string; sort: 'dist' | 'price' | 'rating'; maxPrice: number };
  stayFltOpen: boolean;
  setStayF: (patch: Partial<ClientState['stayF']>) => void;
  setStayPin: (i: number) => void;
  toggleStayFlt: () => void;
  resetStayF: () => void;
  setCat: (cat: string) => void;
  setCatF: (patch: Partial<CatFilters>) => void;
  resetCatF: () => void;
  toast: string | null;

  toggleSaved: (id: string) => void;
  isSaved: (id: string) => boolean;
  setEv: (id: string) => void;
  toggleSeat: (id: string) => void;
  setTtCount: (evId: string, idx: number, delta: number, len: number) => void;
  togglePref: (p: string) => void;
  setObStep: (n: number) => void;
  cardPrimary: (i: number) => void;
  cardDel: (i: number) => void;
  setRateStars: (n: number) => void;
  toggleAddon: (key: string) => void;
  setCart: (patch: Partial<ProtoSt['cart']>) => void;
  showToast: (m: string) => void;
};

let toastTimer: ReturnType<typeof setTimeout> | null = null;

export const useClient = create<ClientState>((set, get) => ({
  prefs: [...seed.prefs],
  prefsSel: [...seed.prefsSel],
  ev: seed.ev,
  seats: [...seed.seats],
  obStep: seed.obStep,
  cart: { ...seed.cart },
  balance: seed.balance,
  points: seed.points,
  stayPin: seed.stayPin,
  expDate: seed.expDate,
  expDay: seed.expDay,
  addons: { ...seed.addons },
  calDay: seed.calDay,
  fStage: seed.fStage,
  fDay: seed.fDay,
  saved: [...seed.saved],
  revRating: seed.revRating,
  revTab: seed.revTab,
  rateStars: seed.rateStars,
  cards: seed.cards ? seed.cards.map((c) => ({ ...c })) : [],

  ttCounts: {},
  catF: { ...CAT_DEFAULTS },
  catFor: null,
  city: '',
  radarF: { ...RADAR_DEFAULTS },
  calF: { ...CAL_DEFAULTS },
  stayF: { type: 'Toate', sort: 'dist', maxPrice: 500 },
  stayFltOpen: false,
  toast: null,

  setStayF: (patch) => set((s) => ({ stayF: { ...s.stayF, ...patch } })),
  setStayPin: (stayPin) => set({ stayPin }),
  toggleStayFlt: () => set((s) => ({ stayFltOpen: !s.stayFltOpen })),
  resetStayF: () => set({ stayF: { type: 'Toate', sort: 'dist', maxPrice: 500 } }),

  /** Prototip: filtrele se reseteaza cand se schimba categoria. */
  setCat: (cat) =>
    set((s) => (s.catFor === cat ? {} : { catFor: cat, catF: { ...CAT_DEFAULTS } })),
  setCatF: (patch) => set((s) => ({ catF: { ...s.catF, ...patch } })),
  resetCatF: () => set({ catF: { ...CAT_DEFAULTS } }),

  setCity: (city) => set({ city }),
  setRadarF: (patch) => set((s) => ({ radarF: { ...s.radarF, ...patch } })),
  resetRadarF: () => set({ radarF: { ...RADAR_DEFAULTS } }),
  setCalF: (patch) => set((s) => ({ calF: { ...s.calF, ...patch } })),

  toggleSaved: (id) =>
    set((s) => ({ saved: s.saved.includes(id) ? s.saved.filter((x) => x !== id) : [...s.saved, id] })),
  isSaved: (id) => get().saved.includes(id),
  setEv: (ev) => set({ ev }),

  toggleSeat: (id) =>
    set((s) => ({ seats: s.seats.includes(id) ? s.seats.filter((x) => x !== id) : [...s.seats, id] })),

  /** Prototip: primul tip de bilet porneste cu 1, restul cu 0. */
  setTtCount: (evId, idx, delta, len) =>
    set((s) => {
      const cur = s.ttCounts[evId] ?? Array.from({ length: len }, (_, i) => (i === 0 ? 1 : 0));
      const next = [...cur];
      next[idx] = Math.max(0, (next[idx] || 0) + delta);
      return { ttCounts: { ...s.ttCounts, [evId]: next } };
    }),

  togglePref: (p) =>
    set((s) => ({ prefsSel: s.prefsSel.includes(p) ? s.prefsSel.filter((x) => x !== p) : [...s.prefsSel, p] })),
  setObStep: (obStep) => set({ obStep }),
  cardPrimary: (i) => set((s) => ({ cards: s.cards.map((c, k) => ({ ...c, primary: k === i })) })),
  cardDel: (i) => set((s) => ({ cards: s.cards.filter((_, k) => k !== i) })),
  setRateStars: (rateStars) => set({ rateStars }),
  toggleAddon: (key) => set((s) => ({ addons: { ...s.addons, [key]: !s.addons[key] } })),
  setCart: (patch) => set((s) => ({ cart: { ...s.cart, ...patch } })),

  showToast: (toast) => {
    set({ toast });
    if (toastTimer) clearTimeout(toastTimer);
    toastTimer = setTimeout(() => set({ toast: null }), 2200);
  },
}));

/** Contoarele curente pentru un eveniment, cu valorile implicite din prototip. */
export const ttCountsFor = (evId: string, len: number) =>
  useClient.getState().ttCounts[evId] ?? Array.from({ length: len }, (_, i) => (i === 0 ? 1 : 0));
