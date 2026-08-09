/* =========================================================
   CEASUL DE POARTĂ

   Reconcilierea scanurilor offline se face pe „primul câștigă". Ordonarea
   dupa ora raportata de telefon NU e sigura, din doua motive:

   1. Un dispozitiv offline cateva ore deriva; doua porti pot fi decalate cu
      minute intre ele fara sa stie nimeni.
   2. Ceasul de perete poate fi schimbat manual. Cineva care il da inapoi isi
      face scanul „primul" la reconciliere. Ordonarea devine manipulabila.

   Solutia: la ultimul moment ONLINE retinem ora serverului si o ancoram de
   ceasul MONOTON al dispozitivului (performance.now(), care numara de la
   pornirea paginii si nu se schimba cand utilizatorul umbla la ora). De acolo
   incolo, ora unui scan = ancora + timp monoton scurs.

   Pastram MEREU ambele valori:
     - `at`     ora normalizata, cu care se ordoneaza
     - `deviceAt` ora bruta a telefonului, pentru dispute si diagnostic
     - `seq`    contor monoton per dispozitiv, ca sa putem ordona doua scanuri
                de pe acelasi telefon care cad in aceeasi milisecunda
   ========================================================= */

const LS_KEY = 'tixello.clock.anchor.v1';

type Anchor = {
  /** ora serverului la momentul ancorarii, in ms epoch */
  serverMs: number;
  /** ora telefonului in acelasi moment, in ms epoch */
  deviceMs: number;
  /** valoarea ceasului monoton in acelasi moment */
  monoMs: number;
  /** identificatorul rularii: performance.now() reporneste la relansare */
  runId: string;
};

let anchor: Anchor | null = null;
let seq = 0;

/** Identificator de rulare — se schimba la fiecare pornire a aplicatiei. */
const RUN_ID = `${Date.now().toString(36)}-${Math.floor(Math.random() * 1e6).toString(36)}`;

const mono = (): number =>
  typeof performance !== 'undefined' && typeof performance.now === 'function' ? performance.now() : Date.now();

function load(): Anchor | null {
  if (anchor) return anchor;
  try {
    const raw = localStorage.getItem(LS_KEY);
    if (!raw) return null;
    anchor = JSON.parse(raw) as Anchor;
    return anchor;
  } catch {
    return null;
  }
}

function persist(a: Anchor) {
  anchor = a;
  try {
    localStorage.setItem(LS_KEY, JSON.stringify(a));
  } catch {
    /* fara persistenta ramanem pe ancora din memorie */
  }
}

/**
 * Se apeleaza ori de cate ori vorbim cu serverul si stim ora lui
 * (antetul `Date` al raspunsului sau un camp explicit).
 */
export function anchorToServer(serverMs: number): void {
  persist({ serverMs, deviceMs: Date.now(), monoMs: mono(), runId: RUN_ID });
}

/** Ancoreaza din antetul `Date` al unui raspuns HTTP, daca exista. */
export function anchorFromResponse(res: { headers: { get(name: string): string | null } }): void {
  const h = res.headers.get('date');
  if (!h) return;
  const t = Date.parse(h);
  if (Number.isFinite(t)) anchorToServer(t);
}

export type Stamp = {
  /** ora normalizata (ancora + timp monoton scurs), ms epoch */
  at: number;
  /** ora bruta a telefonului, ms epoch */
  deviceAt: number;
  /** contor monoton per rulare */
  seq: number;
  /** cat de departe e ceasul telefonului de cel al serverului, ms */
  skewMs: number;
  /** false cand n-am vorbit niciodata cu serverul: `at` e doar ceasul local */
  trusted: boolean;
};

/**
 * Ora unui eveniment local, normalizata.
 *
 * Cand ancora e din ALTA rulare a aplicatiei, ceasul monoton a repornit si nu
 * mai putem masura timpul scurs cu el. Atunci cadem pe derapajul cunoscut:
 * `Date.now() - skew`. E mai slab decat varianta monotona (un ceas schimbat
 * intre timp trece nedetectat), dar tot mai bun decat ora bruta — si marcam
 * asta prin `trusted`.
 */
export function stamp(): Stamp {
  const deviceAt = Date.now();
  seq += 1;
  const a = load();

  if (!a) {
    return { at: deviceAt, deviceAt, seq, skewMs: 0, trusted: false };
  }

  const skewMs = a.deviceMs - a.serverMs;

  if (a.runId === RUN_ID) {
    // aceeasi rulare: timpul scurs se masoara monoton, deci e de incredere
    return { at: Math.round(a.serverMs + (mono() - a.monoMs)), deviceAt, seq, skewMs, trusted: true };
  }

  // alta rulare: corectam doar cu derapajul stiut
  return { at: deviceAt - skewMs, deviceAt, seq, skewMs, trusted: false };
}

/** Ora serverului, estimata acum. Pentru afisaje, nu pentru ordonare. */
export const serverNow = (): number => stamp().at;

/** Ordoneaza doua scanuri: intai ora normalizata, apoi dispozitiv + contor. */
export function compareStamps(
  a: { at: number; deviceId: string; seq: number },
  b: { at: number; deviceId: string; seq: number },
): number {
  if (a.at !== b.at) return a.at - b.at;
  // acelasi moment: pe acelasi dispozitiv decide contorul, altfel id-ul, ca
  // ordinea sa fie stabila si reproductibila pe server si pe telefon
  if (a.deviceId === b.deviceId) return a.seq - b.seq;
  return a.deviceId < b.deviceId ? -1 : 1;
}

/** Sterge ancora — folosit la deconectare si in teste. */
export function resetClock(): void {
  anchor = null;
  seq = 0;
  try {
    localStorage.removeItem(LS_KEY);
  } catch {
    /* nimic de curatat */
  }
}

/** Doar pentru teste: forteaza o ancora dintr-o alta rulare. */
export function __setAnchorForTest(a: Partial<Anchor> & { serverMs: number; deviceMs: number }): void {
  persist({ monoMs: mono(), runId: a.runId ?? 'alta-rulare', ...a });
}

export const __RUN_ID = RUN_ID;
