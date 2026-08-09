/* =========================================================
   COADA DE SINCRONIZARE

   Scanurile se scriu MEREU local, deci poarta nu depinde de retea. Coada le
   trimite cand exista internet.

   Trei lucruri fac diferenta intre o coada care merge si una care strica date:

   1. IDEMPOTENȚĂ — fiecare scan are un `id` generat pe dispozitiv si il trimite
      la fiecare incercare. Daca raspunsul se pierde pe drum si retrimitem,
      serverul recunoaste id-ul si nu numara de doua ori.
   2. NU stergem la trimitere — marcam 'sent'. Un scan sters e o intrare
      pierduta pe care n-o mai poate reconstitui nimeni.
   3. Esecul nu blocheaza coada — un scan care da eroare de validare se
      marcheaza 'failed' si ramane vizibil, in loc sa reincerce la infinit si
      sa tina in loc tot ce e in spate.
   ========================================================= */
import { anchorFromResponse } from './clock';
import { pendingScans, updateScans, type LocalScan, type ScanResult } from './db';

/** Cate scanuri trimitem intr-o cerere. */
const BATCH = 50;
/** Peste atatea incercari esuate, scanul se marcheaza 'failed' si se lasa. */
const MAX_ATTEMPTS = 5;

export type SyncReport = {
  sent: number;
  failed: number;
  /** verdicte ale serverului care difera de decizia locala */
  corrections: { id: string; local: ScanResult; server: ScanResult }[];
  /** true daca a mai ramas ceva de trimis (lot partial) */
  more: boolean;
};

type ServerAck = { id: string; result?: ScanResult; error?: string };

/** Ce trimite aplicatia. Serverul are nevoie de ora normalizata SI de cea bruta. */
const payloadOf = (s: LocalScan) => ({
  id: s.id,
  code: s.code,
  event_id: s.eventId,
  device_id: s.deviceId,
  gate_id: s.gateId,
  scanned_at: new Date(s.at).toISOString(),
  device_at: new Date(s.deviceAt).toISOString(),
  seq: s.seq,
  skew_ms: s.skewMs,
  clock_trusted: s.trusted,
  local_result: s.result,
});

export type Poster = (batch: ReturnType<typeof payloadOf>[]) => Promise<{
  ok: boolean;
  acks?: ServerAck[];
  /** raspunsul brut, ca sa putem ancora ceasul din antetul `Date` */
  response?: { headers: { get(name: string): string | null } };
}>;

let running = false;

/**
 * Trimite un lot. `post` e injectat, ca motorul sa fie testabil si sa nu
 * depinda de forma finala a API-ului de organizator.
 */
export async function flushOnce(post: Poster): Promise<SyncReport> {
  const empty: SyncReport = { sent: 0, failed: 0, corrections: [], more: false };
  if (running) return empty;
  running = true;

  try {
    const all = await pendingScans();
    if (!all.length) return empty;

    // ordine cronologica: serverul vede scanurile in ordinea in care s-au produs
    const batch = [...all].sort((a, b) => a.at - b.at).slice(0, BATCH);
    const res = await post(batch.map(payloadOf));

    if (res.response) anchorFromResponse(res.response);

    if (!res.ok) {
      // esec de transport: crestem incercarile, dar NU marcam 'failed' —
      // lipsa retelei nu e vina scanului
      await updateScans(
        batch.map((s) => ({
          id: s.id,
          changes: {
            attempts: s.attempts + 1,
            ...(s.attempts + 1 >= MAX_ATTEMPTS ? { sync: 'failed' as const } : null),
          },
        })),
      );
      return { sent: 0, failed: 0, corrections: [], more: all.length > 0 };
    }

    const acks = new Map((res.acks ?? []).map((a) => [a.id, a]));
    const corrections: SyncReport['corrections'] = [];
    const patch: { id: string; changes: Partial<LocalScan> }[] = [];
    let sent = 0;
    let failed = 0;

    for (const s of batch) {
      const ack = acks.get(s.id);
      if (!ack) {
        // serverul n-a confirmat acest scan: ramane in coada
        patch.push({ id: s.id, changes: { attempts: s.attempts + 1 } });
        continue;
      }
      if (ack.error) {
        failed++;
        patch.push({ id: s.id, changes: { sync: 'failed', attempts: s.attempts + 1 } });
        continue;
      }
      sent++;
      const changes: Partial<LocalScan> = { sync: 'sent', attempts: s.attempts + 1 };
      if (ack.result && ack.result !== s.result) {
        changes.serverResult = ack.result;
        corrections.push({ id: s.id, local: s.result, server: ack.result });
      }
      patch.push({ id: s.id, changes });
    }

    await updateScans(patch);
    return { sent, failed, corrections, more: all.length > batch.length };
  } finally {
    running = false;
  }
}

/** Goleste coada, lot dupa lot, pana nu mai ramane nimic de trimis. */
export async function flushAll(post: Poster, maxBatches = 20): Promise<SyncReport> {
  const total: SyncReport = { sent: 0, failed: 0, corrections: [], more: false };
  for (let i = 0; i < maxBatches; i++) {
    const r = await flushOnce(post);
    total.sent += r.sent;
    total.failed += r.failed;
    total.corrections.push(...r.corrections);
    total.more = r.more;
    if (!r.more || (r.sent === 0 && r.failed === 0)) break;
  }
  return total;
}

/** Cate scanuri asteapta — pentru insigna „N în așteptare" din Setări. */
export const pendingCount = async (): Promise<number> => (await pendingScans()).length;
