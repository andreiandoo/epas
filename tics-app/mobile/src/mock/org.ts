/* =========================================================
   Date mock ORGANIZATOR — portate 1:1 din obiectele CTX / LEISURE /
   STAFF / GATES / NOTIFS / GUESTS / EMERGENCY din organizer-app.html.
   Forma respecta §13 (mapare EPAS) ca sa poata fi inlocuite direct
   cu raspunsurile tenant API fara a schimba UI-ul.
   ========================================================= */
import type { IconName } from '../design/icons/Icon';

export type VerticalKey =
  | 'organizer'
  | 'theater'
  | 'philharmonic'
  | 'agency'
  | 'venuearena'
  | 'festival'
  | 'leisure';

export type TicketType = {
  /**
   * id-ul REAL din EPAS. Lipseste in datasetul demo — de aia POS-ul refuza
   * vanzarea cat timp ruleaza pe el: fara id nu se poate scrie o comanda.
   */
  id?: number;
  /** nume */
  n: string;
  /** pret */
  p: number;
  /** cantitate totala */
  q: number;
  /** vandute */
  s: number;
  /** check-in */
  ci: number;
  /** culoare stripe */
  c: string;
};

export type OrgContext = {
  label: string;
  org: string;
  event: string;
  venue: string;
  city: string;
  date: string;
  sold: number;
  cap: number;
  checkedin: number;
  revenue: number;
  online: number;
  door: number;
  tt: TicketType[];
  seated: boolean;
  tagline: string;
};

export const CTX: Record<Exclude<VerticalKey, 'leisure'>, OrgContext> = {
  organizer: {
    label: 'Organizator',
    org: 'Nord Events',
    event: 'Wild Experience Fest 2026',
    venue: 'Arenele Romane',
    city: 'București',
    date: '14.07.2026 · 20:00',
    sold: 1486,
    cap: 1800,
    checkedin: 1248,
    revenue: 128450,
    online: 1086,
    door: 400,
    tt: [
      { n: 'Abonament General', p: 150, q: 900, s: 842, ci: 842, c: '#9A1B22' },
      { n: 'Acces 1 zi', p: 90, q: 700, s: 590, ci: 436, c: '#D97706' },
      { n: 'VIP', p: 350, q: 120, s: 54, ci: 52, c: '#0E7490' },
      { n: 'Early Bird', p: 70, q: 80, s: 0, ci: 0, c: '#16A34A' },
    ],
    seated: false,
    tagline: 'Festival · concert live',
  },
  theater: {
    label: 'Teatru',
    org: 'Teatrul Național',
    event: 'O scrisoare pierdută',
    venue: 'Sala Mare',
    city: 'București',
    date: '18.07.2026 · 19:00',
    sold: 392,
    cap: 540,
    checkedin: 301,
    revenue: 47040,
    online: 286,
    door: 106,
    tt: [
      { n: 'Parter rând 1–8', p: 160, q: 180, s: 172, ci: 150, c: '#7C3AA6' },
      { n: 'Parter rând 9–16', p: 120, q: 180, s: 140, ci: 96, c: '#D97706' },
      { n: 'Balcon', p: 90, q: 120, s: 72, ci: 52, c: '#0E7490' },
      { n: 'Lojă (protocol)', p: 220, q: 60, s: 8, ci: 3, c: '#16A34A' },
    ],
    seated: true,
    tagline: 'Stagiune · loc pe scaun',
  },
  philharmonic: {
    label: 'Filarmonică',
    org: 'Filarmonica G. Enescu',
    event: 'Simfonia a 9-a · Beethoven',
    venue: 'Ateneul Român',
    city: 'București',
    date: '20.07.2026 · 19:30',
    sold: 648,
    cap: 780,
    checkedin: 512,
    revenue: 71280,
    online: 503,
    door: 145,
    tt: [
      { n: 'Abonament stagiune', p: 140, q: 420, s: 398, ci: 340, c: '#2456C7' },
      { n: 'Parter', p: 120, q: 200, s: 180, ci: 132, c: '#D97706' },
      { n: 'Balcon I', p: 80, q: 100, s: 62, ci: 38, c: '#0E7490' },
      { n: 'Studenți', p: 35, q: 60, s: 8, ci: 2, c: '#16A34A' },
    ],
    seated: true,
    tagline: 'Concert simfonic · abonament',
  },
  agency: {
    label: 'Agenție artiști',
    org: 'Roton Music',
    event: 'Turneu Național · Cluj',
    venue: 'BT Arena',
    city: 'Cluj-Napoca',
    date: '22.07.2026 · 20:30',
    sold: 2140,
    cap: 2600,
    checkedin: 1890,
    revenue: 214000,
    online: 1712,
    door: 428,
    tt: [
      { n: 'Golden Circle', p: 220, q: 400, s: 400, ci: 380, c: '#6D28D9' },
      { n: 'Tribună A', p: 120, q: 1200, s: 1080, ci: 940, c: '#D97706' },
      { n: 'Tribună B', p: 90, q: 900, s: 640, ci: 560, c: '#0E7490' },
      { n: 'Meet & Greet', p: 450, q: 100, s: 20, ci: 10, c: '#16A34A' },
    ],
    seated: false,
    tagline: 'Turneu · management artist',
  },
  venuearena: {
    label: 'Venue',
    org: 'Cluj Arena',
    event: 'UNTOLD Warm-up',
    venue: 'Cluj Arena',
    city: 'Cluj-Napoca',
    date: '24.07.2026 · 18:00',
    sold: 3820,
    cap: 4500,
    checkedin: 3110,
    revenue: 344000,
    online: 2980,
    door: 840,
    tt: [
      { n: 'General Access', p: 90, q: 3000, s: 2740, ci: 2280, c: '#0E7490' },
      { n: 'Tribună acoperită', p: 150, q: 1000, s: 920, ci: 740, c: '#D97706' },
      { n: 'Sky Box', p: 600, q: 120, s: 60, ci: 40, c: '#9A1B22' },
      { n: 'Copii <12', p: 0, q: 300, s: 100, ci: 50, c: '#16A34A' },
    ],
    seated: false,
    tagline: 'Locație mare · stadion',
  },
  festival: {
    label: 'Festival',
    org: 'Neversea',
    event: 'Neversea 2026 · Ziua 2',
    venue: 'Plaja Modern',
    city: 'Constanța',
    date: '26.07.2026 · 16:00',
    sold: 12480,
    cap: 15000,
    checkedin: 9860,
    revenue: 1248000,
    online: 10120,
    door: 2360,
    tt: [
      { n: 'General Pass', p: 180, q: 9000, s: 8200, ci: 6400, c: '#C0187A' },
      { n: 'VIP Pass', p: 420, q: 3000, s: 2680, ci: 2100, c: '#D97706' },
      { n: 'Cashless Top-up', p: 0, q: 9999, s: 0, ci: 0, c: '#0E7490' },
      { n: 'Wristband ridicat', p: 0, q: 15000, s: 1600, ci: 1360, c: '#16A34A' },
    ],
    seated: false,
    tagline: 'Festival · cashless · wristband',
  },
};

export const CTX_ORDER: (keyof typeof CTX)[] = [
  'organizer',
  'theater',
  'philharmonic',
  'agency',
  'venuearena',
  'festival',
];

/* ---------- leisure / venue-owner (navigatorul distinct) ---------- */
export type LeisureEvent = {
  id: number;
  title: string;
  org: string;
  date: string;
  sold: number;
  cap: number;
  ci: number;
  live?: boolean;
  badge?: string | null;
  past?: boolean;
};

export const LEISURE = {
  venue: 'Delta Adventure Park',
  city: 'Tulcea',
  events: [
    {
      id: 1,
      title: 'Acces zilnic · bilete + rentals',
      org: 'Delta Adventure Park',
      date: 'Azi · deschis 09:00–20:00',
      sold: 214,
      cap: 600,
      ci: 186,
      live: true,
    },
    { id: 2, title: 'Tur cu barca — apus', org: 'Delta Boat Tours', date: '14 iul. 2026 · 18:30', sold: 48, cap: 60, ci: 0, badge: null },
    {
      id: 3,
      title: 'Aventura în copaci (weekend)',
      org: 'Delta Adventure Park',
      date: '20 iul. 2026 · 10:00',
      sold: 132,
      cap: 200,
      ci: 0,
      badge: 'Reprogramat',
    },
    { id: 4, title: 'Noaptea liliecilor (ghidat)', org: 'ONG Natura', date: '6 iul. 2026 · 21:00', sold: 60, cap: 60, ci: 58, badge: null, past: true },
  ] as LeisureEvent[],
};

/* ---------- echipa / porti / notificari / invitati ---------- */
export type StaffMember = {
  id: number;
  nm: string;
  ini: string;
  av: 'red' | 'blue' | 'amber' | 'purple';
  email: string;
  role: 'admin' | 'manager' | 'staff';
  roleL: string;
  gate: string | null;
  status: 'active' | 'pending';
  scans: number;
  sales: number;
};

export const STAFF: StaffMember[] = [
  { id: 1, nm: 'Mihai Coman', ini: 'MC', av: 'red', email: 'mihai@tixello.ro', role: 'admin', roleL: 'Administrator', gate: 'Poarta 1', status: 'active', scans: 412, sales: 0 },
  { id: 2, nm: 'Alexandra Dinu', ini: 'AD', av: 'blue', email: 'alexandra@tixello.ro', role: 'manager', roleL: 'Manager', gate: 'Poarta 1', status: 'active', scans: 0, sales: 36 },
  { id: 3, nm: 'Ioan Barbu', ini: 'IB', av: 'amber', email: 'ioan@tixello.ro', role: 'staff', roleL: 'Staff', gate: 'Poarta 2', status: 'active', scans: 247, sales: 0 },
  { id: 4, nm: 'Raluca Bălan', ini: 'RB', av: 'purple', email: 'raluca@tixello.ro', role: 'staff', roleL: 'Staff', gate: null, status: 'pending', scans: 0, sales: 0 },
];

export type Gate = {
  id: number;
  nm: string;
  loc: string;
  type: 'entry' | 'vip' | 'pos' | 'exit';
  typeL: string;
  chip: string;
  icon: IconName;
  active: boolean;
};

export const GATES: Gate[] = [
  { id: 1, nm: 'Intrare Principală', loc: 'Nord', type: 'entry', typeL: 'Intrare', chip: 'chip-green', icon: 'in', active: true },
  { id: 2, nm: 'Acces VIP', loc: 'Est', type: 'vip', typeL: 'VIP', chip: 'chip-amber', icon: 'star', active: true },
  { id: 3, nm: 'Casă POS', loc: 'Vest', type: 'pos', typeL: 'POS', chip: 'chip-cyan', icon: 'card', active: true },
  { id: 4, nm: 'Ieșire', loc: 'Sud', type: 'exit', typeL: 'Ieșire', chip: 'chip-red', icon: 'door', active: false },
];

export type Notif = { type: 'alert' | 'success' | 'info'; msg: string; time: string; unread: boolean };

export const NOTIFS: Notif[] = [
  { type: 'alert', msg: 'Capacitate la 90% pentru poarta VIP', time: 'acum 2 min', unread: true },
  { type: 'success', msg: 'Ioan Barbu și-a început tura', time: 'acum 12 min', unread: true },
  { type: 'alert', msg: 'Urgență Medicală — raportat de Raluca B. (Staff)', time: 'acum 18 min', unread: true },
  { type: 'info', msg: 'Sincronizare offline finalizată — 12 vânzări', time: 'acum 30 min', unread: false },
];

export const GUESTS = [
  { nm: 'Andrei Popescu', ini: 'AP', type: 'VIP', tc: 'purple', checked: false },
  { nm: 'Maria Ionescu', ini: 'MI', type: 'Artist', tc: 'amber', checked: true },
  { nm: 'Radu Georgescu', ini: 'RG', type: 'Presă', tc: 'cyan', checked: false },
  { nm: 'Elena Vasile', ini: 'EV', type: 'Invitat', tc: 'staff', checked: false },
  { nm: 'Cristian Munteanu', ini: 'CM', type: 'VIP', tc: 'purple', checked: true },
];

export const EMERGENCY = [
  { id: 'medical', l: 'Urgență Medicală', sev: 'high' },
  { id: 'fire', l: 'Incendiu / Evacuare', sev: 'high' },
  { id: 'security', l: 'Problemă de Securitate', sev: 'high' },
  { id: 'tech', l: 'Problemă Tehnică', sev: 'med' },
  { id: 'crowd', l: 'Control Mulțime', sev: 'med' },
  { id: 'equip', l: 'Defecțiune Echipament', sev: 'med' },
  { id: 'weather', l: 'Alertă Meteo', sev: 'low' },
  { id: 'other', l: 'Altele', sev: 'low' },
] as const;

export const HOURLY = [16, 26, 44, 56, 72, 80, 50, 26];
export const HOURLABELS = ['15', '16', '17', '18', '19', '20', '21', '22'];

/* ---------- stari de scanare (§6.2) ---------- */
export type ScanState = 'idle' | 'valid' | 'duplicate' | 'invalid' | 'banned';

export type ScanResult = {
  cls: 'green' | 'amber' | 'danger';
  icon: IconName;
  title: string;
  sub: string;
  nm?: string;
  tt?: string;
  seat?: string;
  at?: string;
  msg?: string;
};

export const SCAN_RESULTS: Record<Exclude<ScanState, 'idle'>, ScanResult> = {
  valid: {
    cls: 'green',
    icon: 'check',
    title: 'ACCES APROBAT',
    sub: 'Bilet valid · Poarta 1',
    nm: 'Andrei Popescu',
    tt: 'Abonament General · #12587',
    seat: 'Secțiune A · Rând 4 · Loc 12',
  },
  duplicate: {
    cls: 'amber',
    icon: 'alert',
    title: 'RE-INTRARE BLOCATĂ',
    sub: 'Deja scanat · anti-passback',
    nm: 'Maria Ionescu',
    tt: 'Acces 1 zi · #12588',
    at: 'Scanat la Poarta 2 · acum 12 min',
  },
  invalid: {
    cls: 'danger',
    icon: 'x',
    title: 'BILET INVALID',
    sub: 'Cod negăsit',
    msg: 'Codul scanat nu există pentru acest eveniment.',
  },
  banned: {
    cls: 'danger',
    icon: 'alert',
    title: 'BILET BLOCAT',
    sub: 'Pe lista neagră',
    nm: 'Cod #4821',
    tt: 'Blocat de admin · motiv: contestație plată',
    msg: 'Refuză accesul și anunță supervizorul.',
  },
};

/** Ciclul de simulare din prototip: valid → duplicate → banned → invalid */
export const SCAN_CYCLE: Exclude<ScanState, 'idle'>[] = ['valid', 'duplicate', 'banned', 'invalid'];
