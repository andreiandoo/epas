/* =========================================================
   Date mock CLIENT — portate din EV / VEN / ART / TICS / MYTIX
   din client-app.html. Subsetul necesar scheletului navigabil;
   se completeaza in Faza 1 pe masura ce portam ecranele §5.
   ========================================================= */

export type Venue = { id: string; name: string; city: string; addr: string; cap: string; tone: string };

export const VEN: Record<string, Venue> = {
  arena: { id: 'arena', name: 'Cluj Arena', city: 'Cluj-Napoca', addr: 'Aleea Stadionului 2', cap: '30.000', tone: 'linear-gradient(150deg,#241a44,#6d28d9)' },
  sala: { id: 'sala', name: 'Sala Mare · TN Cluj', city: 'Cluj-Napoca', addr: 'P-ța Ștefan cel Mare 24', cap: '820', tone: 'linear-gradient(150deg,#2a2150,#7c3aed)' },
  zeppelin: { id: 'zeppelin', name: 'Zeppelin Feld', city: 'Nuremberg', addr: 'Bayern, DE', cap: '40.000', tone: 'linear-gradient(150deg,#3a2c66,#8b5cf6)' },
  turda: { id: 'turda', name: 'Salina Turda', city: 'Turda', addr: 'Aleea Durgăului 7', cap: '—', tone: 'linear-gradient(150deg,#0f4c4a,#12b3a6)' },
};

export type ClientTicketType = { n: string; desc: string; p: number; old?: number; pts: number; seat: boolean };

export type ClientEvent = {
  id: string;
  t: string;
  s: string;
  type: 'event' | 'experience';
  cat: string;
  city: string;
  ven: string;
  d: string;
  mon: string;
  day: string;
  time: string;
  from: number;
  tone: string;
  g: string;
  rat: string;
  by: string;
  seatmap: boolean;
  tt: ClientTicketType[];
};

export const EV: Record<string, ClientEvent> = {
  coldplay: {
    id: 'coldplay', t: 'Coldplay — Music of the Spheres', s: 'Coldplay', type: 'event', cat: 'Concerte', city: 'Cluj-Napoca', ven: 'arena',
    d: '19 Apr', mon: 'Apr', day: '19', time: '20:00', from: 175, tone: 'linear-gradient(150deg,#4c1d95,#8b5cf6)', g: '🎤', rat: '4.9', by: 'Live Nation', seatmap: true,
    tt: [
      { n: 'Fan Pit', desc: 'Cea mai apropiată zonă de scenă, în picioare.', p: 350, pts: 350, seat: false },
      { n: 'Categoria I', desc: 'Loc pe scaun, tribună centrală.', p: 250, old: 290, pts: 250, seat: true },
      { n: 'Categoria II', desc: 'Loc pe scaun, tribună laterală.', p: 175, pts: 175, seat: true },
      { n: 'Elev/Student', desc: 'Necesită legitimație validă la intrare.', p: 120, pts: 120, seat: false },
    ],
  },
  celestial: {
    id: 'celestial', t: 'Celestial Echo: The Horizon', s: 'Celestial Echo', type: 'event', cat: 'Concerte', city: 'Nuremberg', ven: 'zeppelin',
    d: '21 Oct', mon: 'Oct', day: '21', time: '19:00', from: 56, tone: 'linear-gradient(150deg,#3a2c66,#a78bfa)', g: '✨', rat: '4.7', by: 'Zeppelin Live', seatmap: false,
    tt: [
      { n: 'General Access', desc: 'Acces general, în picioare.', p: 56, pts: 56, seat: false },
      { n: 'Golden Circle', desc: 'Zonă premium lângă scenă.', p: 110, old: 130, pts: 110, seat: false },
    ],
  },
  swan: {
    id: 'swan', t: 'Lacul Lebedelor', s: 'Lacul Lebedelor', type: 'event', cat: 'Teatru', city: 'Cluj', ven: 'sala',
    d: '18 Apr', mon: 'Apr', day: '18', time: '19:00', from: 80, tone: 'linear-gradient(150deg,#2a2150,#7c3aed)', g: '🩰', rat: '4.9', by: 'Teatrul Național', seatmap: true,
    tt: [
      { n: 'Parter', desc: 'Rândurile 1–10, vizibilitate optimă.', p: 120, pts: 120, seat: true },
      { n: 'Balcon', desc: 'Etaj, vedere de ansamblu.', p: 80, pts: 80, seat: true },
    ],
  },
  salina: {
    id: 'salina', t: 'Salina Turda — Tur & Agrement', s: 'Salina Turda', type: 'experience', cat: 'Experiențe', city: 'Turda', ven: 'turda',
    d: 'Zilnic', mon: '', day: '', time: '09–17', from: 35, tone: 'linear-gradient(150deg,#0f4c4a,#12b3a6)', g: '⛰', rat: '4.8', by: 'Experiență Tics', seatmap: false,
    tt: [
      { n: 'Bilet Adult', desc: 'Acces salină + roată panoramică + barcă pe lac subteran.', p: 50, pts: 50, seat: false },
      { n: 'Bilet Copil', desc: '5–14 ani. Sub 5 ani gratuit.', p: 35, old: 40, pts: 35, seat: false },
      { n: 'Family Pass', desc: '2 adulți + 2 copii.', p: 140, old: 170, pts: 140, seat: false },
    ],
  },
  atv: {
    id: 'atv', t: 'ATV Adventure în Apuseni', s: 'ATV Adventure', type: 'experience', cat: 'Experiențe', city: 'Apuseni', ven: 'turda',
    d: 'Weekend', mon: '', day: '', time: '2h', from: 180, tone: 'linear-gradient(150deg,#7c3a12,#d97706)', g: '🏍', rat: '4.9', by: 'Experiență Tics', seatmap: false,
    tt: [
      { n: 'ATV Single', desc: 'Un ATV, o persoană, ghid inclus.', p: 220, pts: 220, seat: false },
      { n: 'ATV Duo', desc: 'Un ATV, două persoane.', p: 180, pts: 180, seat: false },
    ],
  },
  wine: {
    id: 'wine', t: 'Wine Tasting la Jidvei', s: 'Wine Tasting', type: 'experience', cat: 'Experiențe', city: 'Alba', ven: 'turda',
    d: 'Vineri', mon: '', day: '', time: '3h', from: 90, tone: 'linear-gradient(150deg,#5b1f3a,#a83e6a)', g: '🍷', rat: '4.7', by: 'Experiență Tics', seatmap: false,
    tt: [
      { n: 'Degustare 5 vinuri', desc: 'Cu platou de brânzeturi.', p: 120, pts: 120, seat: false },
      { n: 'Degustare 3 vinuri', desc: 'Introducere ghidată.', p: 90, pts: 90, seat: false },
    ],
  },
  neversea: {
    id: 'neversea', t: 'Neversea 2026', s: 'Neversea 2026', type: 'event', cat: 'Festival', city: 'Constanța', ven: 'arena',
    d: '26 Iul', mon: 'Iul', day: '26', time: '16:00', from: 180, tone: 'linear-gradient(150deg,#3a0d29,#C0187A)', g: '🎪', rat: '4.9', by: 'Neversea', seatmap: false,
    tt: [
      { n: 'General Pass', desc: 'Acces general 4 zile.', p: 180, pts: 180, seat: false },
      { n: 'VIP Pass', desc: 'Zone VIP, fast-track, lounge.', p: 420, pts: 420, seat: false },
    ],
  },
};

export type MyPass = { name: string; code: string; checkedIn?: string };
export type MyTicket = { ev: string; passes: MyPass[]; seat: string; cat: string; date?: string; slot?: string; people?: string };

export const MYTIX: MyTicket[] = [
  {
    ev: 'coldplay',
    passes: [
      { name: 'Andrei Popescu', code: 'TIX-CP-8841' },
      { name: 'Maria Ionescu', code: 'TIX-CP-8842', checkedIn: '19 Apr · 22:14 · Poarta A' },
    ],
    seat: 'B2, B3',
    cat: 'Categoria I',
  },
  { ev: 'salina', passes: [{ name: 'Andrei Popescu', code: 'TIX-SL-2240' }], seat: '—', cat: 'Bilet Adult', date: '9 Aug', slot: '09:00–17:00', people: '1 adult' },
];

/** Tranzactii portofel cashless (§5.7) */
export const WALLET_TX = [
  { icon: 'cash' as const, label: 'Reîncărcare card', time: 'acum 2 h', amount: '+200 lei', positive: true },
  { icon: 'card' as const, label: 'Bar · 2× bere', time: 'acum 1 h', amount: '−24 lei', positive: false },
  { icon: 'card' as const, label: 'Food truck · burger', time: 'acum 40 min', amount: '−32 lei', positive: false },
];

export const CLIENT_PROFILE = {
  name: 'Andrei Popescu',
  email: 'andrei@tics.ro',
  initials: 'AP',
  city: 'Cluj-Napoca',
  points: 1240,
  balance: 162.0,
  wristband: '#NV-2026-08841 · Neversea',
  activeTickets: 2,
};

export const CATEGORIES = ['Concerte', 'Festival', 'Teatru', 'Experiențe', 'Sport', 'Stand-up'];
