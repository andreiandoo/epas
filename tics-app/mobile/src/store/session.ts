/* =========================================================
   Store de sesiune — replica structura de stare `S` din
   organizer-app.html (§12.3: "replica structura de stare si fluxurile").
   Un singur store pentru: identitate, shell activ, verticala, rol,
   tema, tura, online/offline, cos POS, stare de scanare, modale.
   ========================================================= */
import { create } from 'zustand';
import type { OrgProperty, Property } from '../api/types';
import { identityProps, type IdentityKind } from '../api/client';
import { SCAN_CYCLE, type ScanState } from '../mock/org';

export type AppMode = 'chooser' | 'client' | 'organizer';
export type AppTheme = 'dark' | 'light' | 'lowlight';
export type OrgTab = 'Dashboard' | 'CheckIn' | 'Sales' | 'Reports' | 'Settings';
export type VenueTab = 'VenueEvents' | 'CheckIn' | 'Sales' | 'Settings';
export type ClientTab = 'Acasa' | 'Explore' | 'Tickets' | 'Wallet' | 'Profile';
export type SaleStep = 'select' | 'cart' | 'success';
export type VenueScreen = 'list' | 'detail' | 'ticket';

export type SettingsFlags = {
  vibr: boolean;
  sound: boolean;
  autoconf: boolean;
  bt: boolean;
  nfc: boolean;
  offline: boolean;
  printer: boolean;
};

type SessionState = {
  /* ---- auth & identitate ---- */
  authed: boolean;
  loginIdentity: IdentityKind;
  properties: Property[];
  activeProp: Property | null;

  /* ---- shell ---- */
  mode: AppMode;
  /** 'organizer' = navigator standard, 'venue' = navigator leisure (§7) */
  account: 'organizer' | 'venue';
  ctx: OrgProperty['ctx'];
  role: 'admin' | 'manager' | 'staff';
  tab: OrgTab | VenueTab;
  clientTab: ClientTab;
  venueScreen: VenueScreen;
  venueEventId: number;

  /* ---- chrome ---- */
  appTheme: AppTheme;
  online: boolean;
  shift: boolean;
  shiftPaused: boolean;
  set: SettingsFlags;
  modal: string | null;
  modalArg: string | null;
  toast: string | null;

  /* ---- POS & scanare ---- */
  cart: Record<number, number>;
  sale: SaleStep;
  scan: ScanState;
  scanIdx: number;
  flash: string | null;
  onlineSeg: 'online' | 'door' | null;

  /* ---- actiuni ---- */
  login: (identity?: IdentityKind) => void;
  logout: () => void;
  applyProp: (p: Property) => void;
  switchMode: () => void;
  goChooser: () => void;

  go: (tab: OrgTab | VenueTab) => void;
  clientGo: (tab: ClientTab) => void;
  setTheme: (t: AppTheme) => void;
  setRole: (r: 'admin' | 'manager' | 'staff') => void;
  toggleSet: (k: keyof SettingsFlags) => void;
  toggleOnline: () => void;
  toggleShiftPause: () => void;

  openModal: (name: string, arg?: string) => void;
  closeModal: () => void;
  showToast: (msg: string) => void;

  addCart: (i: number) => void;
  subCart: (i: number) => void;
  clearCart: () => void;
  setSale: (s: SaleStep) => void;

  doScan: () => void;
  clearScan: () => void;
  clearFlash: () => void;

  venueOpen: (id: number) => void;
  venueBack: () => void;
  venueTicket: () => void;
};

let toastTimer: ReturnType<typeof setTimeout> | null = null;

export const useSession = create<SessionState>((set, get) => ({
  authed: false,
  loginIdentity: 'multi',
  properties: [],
  activeProp: null,

  mode: 'chooser',
  account: 'organizer',
  ctx: 'organizer',
  role: 'admin',
  tab: 'Dashboard',
  clientTab: 'Acasa',
  venueScreen: 'list',
  venueEventId: 1,

  appTheme: 'dark',
  online: true,
  shift: true,
  shiftPaused: false,
  set: { vibr: true, sound: true, autoconf: false, bt: true, nfc: true, offline: false, printer: true },
  modal: null,
  modalArg: null,
  toast: null,

  cart: {},
  sale: 'select',
  scan: 'idle',
  scanIdx: 0,
  flash: null,
  onlineSeg: null,

  /* ---- login: >1 proprietate → chooser; ==1 → intrare directa (§3) ---- */
  login: (identity) => {
    const kind = identity ?? get().loginIdentity;
    const props = identityProps(kind);
    set({ authed: true, loginIdentity: kind, properties: props });
    if (props.length > 1) set({ mode: 'chooser', modal: null });
    else get().applyProp(props[0]);
  },

  logout: () => set({ authed: false, mode: 'chooser', activeProp: null, modal: null, toast: null }),

  applyProp: (p) => {
    if (p.kind === 'client') {
      set({ mode: 'client', clientTab: 'Acasa', activeProp: p, modal: null });
    } else {
      set({
        mode: 'organizer',
        account: p.account,
        ctx: p.ctx,
        role: p.role,
        tab: p.account === 'venue' ? 'VenueEvents' : 'Dashboard',
        venueScreen: 'list',
        sale: 'select',
        cart: {},
        scan: 'idle',
        activeProp: p,
        modal: null,
      });
    }
  },

  /** client ↔ prima proprietate de organizator (sau invers) */
  switchMode: () => {
    const { mode, properties, applyProp, showToast } = get();
    if (mode === 'client') {
      const org = properties.find((p) => p.kind === 'org');
      if (!org) return showToast('Acest email nu are cont de organizator');
      applyProp(org);
    } else {
      const client = properties.find((p) => p.kind === 'client');
      if (!client) return showToast('Acest email nu are cont de client');
      applyProp(client);
    }
  },

  goChooser: () => set({ mode: 'chooser', modal: null }),

  go: (tab) => {
    const { account, role, showToast } = get();
    if (account !== 'venue' && tab === 'Reports' && role !== 'admin') {
      return showToast('Rapoartele sunt disponibile doar pentru Admin');
    }
    set({ tab, sale: 'select' });
  },
  clientGo: (clientTab) => set({ clientTab }),
  setTheme: (appTheme) => set({ appTheme }),
  setRole: (role) => set((s) => ({ role, tab: role === 'staff' && s.tab === 'Reports' ? 'Dashboard' : s.tab })),
  toggleSet: (k) => set((s) => ({ set: { ...s.set, [k]: !s.set[k] } })),
  toggleOnline: () => set((s) => ({ online: !s.online })),
  toggleShiftPause: () => set((s) => ({ shiftPaused: !s.shiftPaused })),

  openModal: (modal, modalArg) => set({ modal, modalArg: modalArg ?? null }),
  closeModal: () => set({ modal: null, modalArg: null }),

  showToast: (toast) => {
    set({ toast });
    if (toastTimer) clearTimeout(toastTimer);
    toastTimer = setTimeout(() => set({ toast: null }), 2200);
  },

  addCart: (i) => set((s) => ({ cart: { ...s.cart, [i]: (s.cart[i] || 0) + 1 } })),
  subCart: (i) =>
    set((s) => {
      const next = { ...s.cart };
      if (!next[i]) return {};
      next[i] -= 1;
      if (!next[i]) delete next[i];
      return { cart: next };
    }),
  clearCart: () => set({ cart: {} }),
  setSale: (sale) => set({ sale }),

  /** Simuleaza ciclul de rezultate din prototip: valid → duplicate → banned → invalid */
  doScan: () => {
    const { shiftPaused, scanIdx } = get();
    if (shiftPaused) return;
    const next = SCAN_CYCLE[scanIdx % SCAN_CYCLE.length];
    set({ scan: next, scanIdx: scanIdx + 1, flash: next });
  },
  clearScan: () => set({ scan: 'idle' }),
  clearFlash: () => set({ flash: null }),

  venueOpen: (venueEventId) => set({ venueEventId, venueScreen: 'detail' }),
  venueBack: () => set((s) => ({ venueScreen: s.venueScreen === 'ticket' ? 'detail' : 'list' })),
  venueTicket: () => set({ venueScreen: 'ticket' }),
}));

/* ---------- selectori derivati ---------- */
export const isAdminRole = (r: string) => r === 'admin' || r === 'manager';
