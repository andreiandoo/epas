/* =========================================================
   Starea clientului — oglindeste obiectul `ST` din client-app.html
   (§12.3: "replica structura de stare si fluxurile").
   Valorile initiale sunt copiate din prototip, ca ecranele sa porneasca
   in exact aceeasi stare ca macheta.
   ========================================================= */
import { create } from 'zustand';
import { ST as PROTO_ST } from '../mock/prototype';
import type { RadarItem } from '../api/ticsRadar';

/* Salvatele din Radar supravietuiesc repornirii; cele din datasetul local sunt
   oricum rezolvabile dupa id. */
const SAVED_LS = 'tixello.savedRadar.v1';

function loadSavedRadar(): Record<string, RadarItem> {
  try {
    return JSON.parse(localStorage.getItem(SAVED_LS) || '{}') as Record<string, RadarItem>;
  } catch {
    return {};
  }
}

function saveSavedRadar(v: Record<string, RadarItem>) {
  try {
    localStorage.setItem(SAVED_LS, JSON.stringify(v));
  } catch {
    /* fara persistenta, doar se pierd la repornire */
  }
}

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
/** Preferintele de continut, pastrate intre porniri.
    Sunt o alegere pe care utilizatorul o face o data si care hraneste
    recomandarile; resetate la fiecare pornire, ecranul de preferinte devine un
    formular fara efect. */
const PREFS_LS = 'tixello.prefs';
const readPrefs = (seedPrefs: string[]): string[] => {
  try {
    const raw = localStorage.getItem(PREFS_LS);
    if (!raw) return [...seedPrefs];

    const parsed = JSON.parse(raw);

    return Array.isArray(parsed) ? parsed.filter((x): x is string => typeof x === 'string') : [...seedPrefs];
  } catch {
    return [...seedPrefs];
  }
};
const writePrefs = (v: string[]) => {
  try {
    localStorage.setItem(PREFS_LS, JSON.stringify(v));
  } catch {
    /* fara persistenta, raman pe sesiunea curenta */
  }
};

/** Orasul ales, pastrat intre porniri. */
const CITY_LS = 'tixello.city';
const CITIES_LS = 'tics.radar.cities';

/** Orasele din Radar, pastrate intre porniri ca orice alt filtru. */
const readCities = (): string[] => {
  try {
    const raw = localStorage.getItem(CITIES_LS);
    const v = raw ? (JSON.parse(raw) as unknown) : null;

    return Array.isArray(v) ? v.filter((x): x is string => typeof x === 'string') : [];
  } catch {
    return [];
  }
};

const writeCities = (list: string[]): void => {
  try {
    localStorage.setItem(CITIES_LS, JSON.stringify(list));
  } catch {
    /* stocare blocata: filtrul ramane doar pe sesiunea curenta */
  }
};
const readCity = (): string => {
  try {
    return localStorage.getItem(CITY_LS) ?? '';
  } catch {
    return '';
  }
};

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
  /** din panoul complet de filtre */
  type: string;
  genre: string;
};
const RADAR_DEFAULTS: RadarFilters = { when: 'all', maxPrice: 0, scarce: false, type: '', genre: '' };

/** Filtrele calendarului: oras / tip / gen. */
export type CalFilters = { city: string; type: string; genre: string };
const CAL_DEFAULTS: CalFilters = { city: '', type: '', genre: '' };

type ClientState = ProtoSt & {
  /** contoarele de bilete de pe ecranul de tipuri (ST._ttCounts din prototip) */
  ttCounts: Record<string, number[]>;
  /** filtrele de categorie + categoria pentru care sunt valabile (ST._catFor) */
  catF: CatFilters;
  catFor: string | null;
  /** evenimentele salvate care vin din TICS Radar (nu-s in datasetul local) */
  savedRadar: Record<string, RadarItem>;
  toggleSavedRadar: (item: RadarItem) => void;
  /** orasul ales din antetul de pe Acasa; '' = toata tara */
  city: string;
  setCity: (city: string) => void;
  /**
   * Orasele alese in Radar. Gol = toata tara.
   *
   * Separat de `city`: acolo e UN oras, folosit de „Lângă tine" si de listele
   * de pe Acasa, unde un centru unic chiar are sens. Radarul e ecranul de
   * cautat, iar acolo ai des nevoie de doua-trei orase deodata (locuiesti
   * intre ele, sau cauti un turneu).
   */
  cities: string[];
  setCities: (cities: string[]) => void;
  toggleCity: (city: string) => void;
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
  prefsSel: readPrefs(seed.prefsSel),
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
  savedRadar: loadSavedRadar(),
  city: readCity(),
  cities: readCities(),
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

  /* Evenimentele de Radar nu exista in datasetul local, deci `saved` (care
     tine doar id-uri) n-ar avea ce rezolva in ecranul Salvate. Pastram si
     obiectul, ca sa poata fi afisat dupa repornire. */
  toggleSavedRadar: (item) =>
    set((s) => {
      const has = !!s.savedRadar[item.id];
      const next = { ...s.savedRadar };
      if (has) delete next[item.id];
      else next[item.id] = item;
      saveSavedRadar(next);
      return {
        savedRadar: next,
        saved: has ? s.saved.filter((x) => x !== item.id) : [...s.saved, item.id],
      };
    }),

  setCities: (cities) => {
    writeCities(cities);
    set({ cities });
  },

  toggleCity: (city) => {
    const cur = get().cities;
    const next = cur.includes(city) ? cur.filter((c) => c !== city) : [...cur, city];
    writeCities(next);
    set({ cities: next });
  },

  setCity: (city) => {
    /* Orasul se retine intre porniri: e o alegere pe care utilizatorul o face
       o data si o asteapta acolo data viitoare, nu un filtru de sesiune. */
    try {
      localStorage.setItem(CITY_LS, city);
    } catch {
      /* fara persistenta, ramane doar pe sesiunea curenta */
    }
    set({ city });
  },
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
    set((s) => {
      const next = s.prefsSel.includes(p) ? s.prefsSel.filter((x) => x !== p) : [...s.prefsSel, p];
      writePrefs(next);

      return { prefsSel: next };
    }),
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
