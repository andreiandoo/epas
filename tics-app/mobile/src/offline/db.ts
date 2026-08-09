/* =========================================================
   STOCARE LOCALĂ pentru scanarea offline.

   DE CE IndexedDB si nu SQLite: planul spunea SQLite, dar pentru volumele
   reale (un eveniment mare inseamna zeci de mii de bilete) IndexedDB e
   suficient, e persistent in WebView-ul Capacitor peste reporniri, si nu cere
   un plugin nativ — deci nu adauga o cale noua de esec la build si merge
   identic in browser, unde il putem si testa.
   Interfata de mai jos e insa un DRIVER: daca vreodata volumul sau nevoia de
   interogari cere SQLite, se schimba implementarea, nu apelantii.

   Trei magazii:
     tickets  — inventarul descarcat pentru scanare fara internet
     scans    — scanurile facute local, cu ora normalizata (vezi clock.ts)
     meta     — starea sincronizarii
   ========================================================= */

export type CachedTicket = {
  /** codul de pe bilet — cheia dupa care se cauta la scanare */
  code: string;
  eventId: string;
  /** 'valid' | 'used' | 'void' — starea la momentul descarcarii */
  status: string;
  type?: string | null;
  seat?: string | null;
  holder?: string | null;
  /** cand a fost deja folosit inainte de descarcare (ISO) */
  usedAt?: string | null;
};

export type LocalScan = {
  /** id local, unic pe dispozitiv: nu depinde de server */
  id: string;
  code: string;
  eventId: string;
  deviceId: string;
  gateId?: string | null;
  /** ora normalizata — cu asta se ordoneaza la reconciliere */
  at: number;
  /** ora bruta a telefonului, pentru dispute */
  deviceAt: number;
  seq: number;
  skewMs: number;
  trusted: boolean;
  /** ce a decis dispozitivul pe loc */
  result: ScanResult;
  /** 'pending' | 'sent' | 'failed' */
  sync: 'pending' | 'sent' | 'failed';
  attempts: number;
  /** verdictul serverului dupa reconciliere, cand difera de cel local */
  serverResult?: ScanResult | null;
};

export type ScanResult = 'valid' | 'duplicate' | 'unknown' | 'void' | 'wrong-event';

const DB_NAME = 'tixello-offline';
const DB_VERSION = 1;

type Store = 'tickets' | 'scans' | 'meta';

let dbp: Promise<IDBDatabase> | null = null;

function open(): Promise<IDBDatabase> {
  if (dbp) return dbp;
  dbp = new Promise((resolve, reject) => {
    const req = indexedDB.open(DB_NAME, DB_VERSION);
    req.onupgradeneeded = () => {
      const db = req.result;
      if (!db.objectStoreNames.contains('tickets')) {
        const s = db.createObjectStore('tickets', { keyPath: 'code' });
        s.createIndex('eventId', 'eventId');
      }
      if (!db.objectStoreNames.contains('scans')) {
        const s = db.createObjectStore('scans', { keyPath: 'id' });
        s.createIndex('sync', 'sync');
        s.createIndex('code', 'code');
      }
      if (!db.objectStoreNames.contains('meta')) db.createObjectStore('meta');
    };
    req.onsuccess = () => resolve(req.result);
    req.onerror = () => reject(req.error);
  });
  return dbp;
}

function tx<T>(store: Store, mode: IDBTransactionMode, fn: (s: IDBObjectStore) => IDBRequest<T>): Promise<T> {
  return open().then(
    (db) =>
      new Promise<T>((resolve, reject) => {
        const t = db.transaction(store, mode);
        const req = fn(t.objectStore(store));
        req.onsuccess = () => resolve(req.result);
        req.onerror = () => reject(req.error);
      }),
  );
}

/* ---------- inventar ---------- */

/** Inlocuieste inventarul unui eveniment. Se apeleaza la „descarca biletele". */
export async function putTickets(eventId: string, tickets: CachedTicket[]): Promise<number> {
  const db = await open();
  return new Promise((resolve, reject) => {
    const t = db.transaction('tickets', 'readwrite');
    const s = t.objectStore('tickets');
    // curatam ce era pentru evenimentul asta, ca sa nu ramana bilete anulate
    const del = s.index('eventId').openKeyCursor(IDBKeyRange.only(eventId));
    del.onsuccess = () => {
      const cur = del.result;
      if (cur) {
        s.delete(cur.primaryKey);
        cur.continue();
        return;
      }
      for (const tk of tickets) s.put(tk);
    };
    t.oncomplete = () => resolve(tickets.length);
    t.onerror = () => reject(t.error);
  });
}

export const getTicket = (code: string) => tx<CachedTicket | undefined>('tickets', 'readonly', (s) => s.get(code));

export async function countTickets(eventId?: string): Promise<number> {
  if (!eventId) return tx<number>('tickets', 'readonly', (s) => s.count());
  return tx<number>('tickets', 'readonly', (s) => s.index('eventId').count(IDBKeyRange.only(eventId)));
}

export const markTicketUsed = async (code: string, at: number) => {
  const t = await getTicket(code);
  if (!t) return;
  await tx('tickets', 'readwrite', (s) => s.put({ ...t, status: 'used', usedAt: new Date(at).toISOString() }));
};

/* ---------- scanuri ---------- */

export const putScan = (scan: LocalScan) => tx('scans', 'readwrite', (s) => s.put(scan));

export const allScans = () => tx<LocalScan[]>('scans', 'readonly', (s) => s.getAll());

export const pendingScans = () =>
  tx<LocalScan[]>('scans', 'readonly', (s) => s.index('sync').getAll(IDBKeyRange.only('pending')));

export const scansForCode = (code: string) =>
  tx<LocalScan[]>('scans', 'readonly', (s) => s.index('code').getAll(IDBKeyRange.only(code)));

export async function updateScans(patch: { id: string; changes: Partial<LocalScan> }[]): Promise<void> {
  const db = await open();
  return new Promise((resolve, reject) => {
    const t = db.transaction('scans', 'readwrite');
    const s = t.objectStore('scans');
    for (const p of patch) {
      const g = s.get(p.id);
      g.onsuccess = () => {
        const cur = g.result as LocalScan | undefined;
        if (cur) s.put({ ...cur, ...p.changes });
      };
    }
    t.oncomplete = () => resolve();
    t.onerror = () => reject(t.error);
  });
}

/* ---------- meta ---------- */

export const getMeta = <T>(key: string) => tx<T | undefined>('meta', 'readonly', (s) => s.get(key));
export const setMeta = (key: string, value: unknown) => tx('meta', 'readwrite', (s) => s.put(value, key));

/** Sterge tot — la deconectare sau la schimbarea evenimentului. */
export async function wipe(): Promise<void> {
  const db = await open();
  return new Promise((resolve, reject) => {
    const t = db.transaction(['tickets', 'scans', 'meta'], 'readwrite');
    t.objectStore('tickets').clear();
    t.objectStore('scans').clear();
    t.objectStore('meta').clear();
    t.oncomplete = () => resolve();
    t.onerror = () => reject(t.error);
  });
}

/** Doar pentru teste: inchide si uita conexiunea. */
export function __resetDb(): void {
  dbp = null;
}
