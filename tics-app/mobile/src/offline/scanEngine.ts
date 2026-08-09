/* =========================================================
   MOTORUL DE SCANARE

   Doua decizii DISTINCTE, si e important sa nu fie confundate:

   1. LA POARTĂ, offline — decizia e locala si instantanee: biletul e in
      inventarul meu? l-am mai scanat EU? Atat poate sti un dispozitiv fara
      internet.

   2. LA RECONCILIERE, dupa sincronizare — se compara scanurile de pe toate
      dispozitivele si „primul castiga" (vezi clock.ts pentru de ce ordonarea
      are nevoie de ora normalizata).

   Consecinta care trebuie asumata: doua porti offline nu se pot opri una pe
   alta. Al doilea om a intrat deja. Rezultatul reconcilierii e o ALERTA
   pentru organizator, nu o usa inchisa — vezi `duplicatesFrom`.
   ========================================================= */
import { compareStamps, stamp } from './clock';
import {
  getTicket,
  markTicketUsed,
  putScan,
  scansForCode,
  type LocalScan,
  type ScanResult,
} from './db';

export type ScanOutcome = {
  result: ScanResult;
  scan: LocalScan;
  /** biletul din inventar, cand exista */
  ticket?: { type?: string | null; seat?: string | null; holder?: string | null };
  /** cand result = 'duplicate': cand a fost scanat prima data pe ACEST dispozitiv */
  firstScanAt?: number;
};

let deviceId = '';

/** Id stabil de dispozitiv — intra in ordonarea scanurilor si in audit. */
export function getDeviceId(): string {
  if (deviceId) return deviceId;
  try {
    const k = 'tixello.deviceId.v1';
    let v = localStorage.getItem(k);
    if (!v) {
      v =
        typeof crypto !== 'undefined' && 'randomUUID' in crypto
          ? crypto.randomUUID()
          : `dev-${Date.now().toString(36)}-${Math.floor(Math.random() * 1e9).toString(36)}`;
      localStorage.setItem(k, v);
    }
    deviceId = v;
  } catch {
    deviceId = `dev-${Math.floor(Math.random() * 1e9).toString(36)}`;
  }
  return deviceId;
}

const newId = () =>
  typeof crypto !== 'undefined' && 'randomUUID' in crypto
    ? crypto.randomUUID()
    : `scan-${Date.now().toString(36)}-${Math.floor(Math.random() * 1e9).toString(36)}`;

/**
 * Scaneaza un cod. Functioneaza identic online si offline: scrie mereu local,
 * iar sincronizarea e treaba cozii. Asa poarta nu depinde de retea.
 */
export async function scanCode(code: string, opts: { eventId: string; gateId?: string | null }): Promise<ScanOutcome> {
  const s = stamp();
  const ticket = await getTicket(code);

  let result: ScanResult;
  let firstScanAt: number | undefined;

  if (!ticket) {
    result = 'unknown';
  } else if (ticket.eventId !== opts.eventId) {
    // bilet real, dar pentru alt eveniment — greseala frecventa la festivaluri
    result = 'wrong-event';
  } else if (ticket.status === 'void') {
    result = 'void';
  } else if (ticket.status === 'used') {
    // deja folosit inainte de descarcarea inventarului
    result = 'duplicate';
    firstScanAt = ticket.usedAt ? Date.parse(ticket.usedAt) : undefined;
  } else {
    // l-am scanat chiar eu, mai devreme, fara sa fi apucat sa sincronizez?
    const mine = await scansForCode(code);
    const accepted = mine.filter((x) => x.result === 'valid');
    if (accepted.length) {
      result = 'duplicate';
      firstScanAt = accepted.sort((a, b) => a.at - b.at)[0].at;
    } else {
      result = 'valid';
    }
  }

  const scan: LocalScan = {
    id: newId(),
    code,
    eventId: opts.eventId,
    deviceId: getDeviceId(),
    gateId: opts.gateId ?? null,
    at: s.at,
    deviceAt: s.deviceAt,
    seq: s.seq,
    skewMs: s.skewMs,
    trusted: s.trusted,
    result,
    sync: 'pending',
    attempts: 0,
  };

  await putScan(scan);
  // marcam local biletul, ca urmatoarea scanare pe acelasi dispozitiv sa fie
  // respinsa instant, fara sa mai caute prin scanuri
  if (result === 'valid') await markTicketUsed(code, s.at);

  return { result, scan, ticket, firstScanAt };
}

/* =========================================================
   Reconciliere
   ========================================================= */

export type Duplicate = {
  code: string;
  /** scanul care castiga — primul, dupa ora normalizata */
  winner: LocalScan;
  /** scanurile pierzatoare, in ordine */
  losers: LocalScan[];
};

/**
 * Grupeaza scanurile acceptate pe cod si intoarce cazurile in care acelasi
 * bilet a fost validat de mai multe ori. Ordonarea foloseste `compareStamps`,
 * deci e stabila si pe server, si pe telefon.
 *
 * Functia e PURA — primeste scanuri, intoarce duplicate. Asa poate fi testata
 * fara baza de date si rulata identic pe ambele parti.
 */
export function duplicatesFrom(scans: LocalScan[]): Duplicate[] {
  const byCode = new Map<string, LocalScan[]>();
  for (const s of scans) {
    if (s.result !== 'valid') continue;
    const list = byCode.get(s.code);
    if (list) list.push(s);
    else byCode.set(s.code, [s]);
  }

  const out: Duplicate[] = [];
  for (const [code, list] of byCode) {
    if (list.length < 2) continue;
    const ordered = [...list].sort(compareStamps);
    out.push({ code, winner: ordered[0], losers: ordered.slice(1) });
  }
  // cele mai recente probleme, primele
  return out.sort((a, b) => b.winner.at - a.winner.at);
}

/** Doar pentru teste. */
export function __setDeviceId(id: string): void {
  deviceId = id;
}
