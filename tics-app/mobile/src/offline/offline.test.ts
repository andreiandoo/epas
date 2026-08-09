/* =========================================================
   Teste pentru motorul offline.

   Sunt scrise pentru lucrurile pe care NU le pot verifica la o poartă
   adevărată: ordonarea scanurilor cand ceasurile mint, dedupe-ul local,
   reconcilierea intre doua porti si comportamentul cozii cand reteaua pica.
   ========================================================= */
import 'fake-indexeddb/auto';
import { beforeEach, describe, expect, it } from 'vitest';

import { __setAnchorForTest, anchorToServer, compareStamps, resetClock, stamp } from './clock';
import { putTickets, pendingScans, wipe, type CachedTicket, type LocalScan } from './db';
import { __setDeviceId, duplicatesFrom, scanCode } from './scanEngine';
import { flushAll, type Poster } from './sync';

const EV = 'ev-1';
const tickets = (n: number): CachedTicket[] =>
  Array.from({ length: n }, (_, i) => ({ code: `T-${i + 1}`, eventId: EV, status: 'valid' }));

const scan = (over: Partial<LocalScan>): LocalScan => ({
  id: 'x',
  code: 'T-1',
  eventId: EV,
  deviceId: 'A',
  gateId: null,
  at: 0,
  deviceAt: 0,
  seq: 0,
  skewMs: 0,
  trusted: true,
  result: 'valid',
  sync: 'pending',
  attempts: 0,
  ...over,
});

beforeEach(async () => {
  resetClock();
  localStorage.clear();
  await wipe();
  __setDeviceId('DEV-A');
});

describe('ceasul', () => {
  it('fara ancora, marcheaza ora ca neverificata', () => {
    const s = stamp();
    expect(s.trusted).toBe(false);
    expect(s.skewMs).toBe(0);
  });

  it('dupa ancorare, ora normalizata e a serverului, nu a telefonului', () => {
    // telefonul e cu 10 minute inainte fata de server
    const serverNow = Date.now() - 600_000;
    anchorToServer(serverNow);

    const s = stamp();
    expect(s.trusted).toBe(true);
    expect(s.skewMs).toBeGreaterThan(590_000);
    // ora normalizata trebuie sa fie langa cea a serverului, nu langa Date.now()
    expect(Math.abs(s.at - serverNow)).toBeLessThan(5_000);
    expect(Math.abs(s.at - s.deviceAt)).toBeGreaterThan(590_000);
  });

  it('ancora dintr-o alta rulare corecteaza derapajul, dar nu se declara de incredere', () => {
    const dev = Date.now();
    __setAnchorForTest({ serverMs: dev - 300_000, deviceMs: dev, runId: 'rulare-veche' });

    const s = stamp();
    expect(s.trusted).toBe(false);
    // tot corectam cu derapajul stiut
    expect(Math.abs(s.at - (dev - 300_000))).toBeLessThan(5_000);
  });

  it('ordoneaza stabil doua scanuri din aceeasi milisecunda', () => {
    const a = { at: 1000, deviceId: 'A', seq: 2 };
    const b = { at: 1000, deviceId: 'A', seq: 1 };
    expect(compareStamps(a, b)).toBeGreaterThan(0); // seq mai mic castiga

    const c = { at: 1000, deviceId: 'B', seq: 1 };
    // dispozitive diferite: ordine stabila, aceeasi pe server si pe telefon
    expect(Math.sign(compareStamps(b, c))).toBe(-1);
    expect(Math.sign(compareStamps(c, b))).toBe(1);
  });
});

describe('scanarea la poarta', () => {
  beforeEach(async () => {
    await putTickets(EV, tickets(3));
    anchorToServer(Date.now());
  });

  it('accepta un bilet valid', async () => {
    const r = await scanCode('T-1', { eventId: EV });
    expect(r.result).toBe('valid');
  });

  it('respinge al doilea scan al aceluiasi bilet, pe acelasi dispozitiv', async () => {
    await scanCode('T-1', { eventId: EV });
    const r = await scanCode('T-1', { eventId: EV });
    expect(r.result).toBe('duplicate');
    expect(r.firstScanAt).toBeTypeOf('number');
  });

  it('recunoaste un bilet de la alt eveniment', async () => {
    await putTickets('ev-2', [{ code: 'X-9', eventId: 'ev-2', status: 'valid' }]);
    const r = await scanCode('X-9', { eventId: EV });
    expect(r.result).toBe('wrong-event');
  });

  it('cod necunoscut', async () => {
    const r = await scanCode('NU-EXISTA', { eventId: EV });
    expect(r.result).toBe('unknown');
  });

  it('pastreaza fiecare scan in coada, inclusiv pe cele respinse', async () => {
    await scanCode('T-1', { eventId: EV });
    await scanCode('T-1', { eventId: EV });
    await scanCode('NU-EXISTA', { eventId: EV });
    expect((await pendingScans()).length).toBe(3);
  });
});

describe('reconciliere intre doua porti', () => {
  it('primul castiga dupa ora normalizata, nu dupa cea a telefonului', () => {
    // Poarta B are ceasul dat inapoi cu 10 minute: dupa ora BRUTA ar parea
    // prima, desi a scanat a doua.
    const a = scan({ id: 'a', deviceId: 'A', at: 1_000_000, deviceAt: 1_000_000 });
    const b = scan({ id: 'b', deviceId: 'B', at: 1_000_500, deviceAt: 400_000 });

    const [dup] = duplicatesFrom([b, a]);
    expect(dup.winner.id).toBe('a');
    expect(dup.losers.map((x) => x.id)).toEqual(['b']);
  });

  it('nu raporteaza nimic cand biletul a fost scanat o singura data', () => {
    expect(duplicatesFrom([scan({ id: 'a' })])).toEqual([]);
  });

  it('ignora scanurile respinse — doar cele acceptate produc duplicate', () => {
    const a = scan({ id: 'a', at: 10 });
    const b = scan({ id: 'b', at: 20, result: 'duplicate' });
    expect(duplicatesFrom([a, b])).toEqual([]);
  });

  it('grupeaza pe cod si ordoneaza pierzatorii', () => {
    const s = [
      scan({ id: '3', at: 300, deviceId: 'C' }),
      scan({ id: '1', at: 100, deviceId: 'A' }),
      scan({ id: '2', at: 200, deviceId: 'B' }),
    ];
    const [dup] = duplicatesFrom(s);
    expect(dup.winner.id).toBe('1');
    expect(dup.losers.map((x) => x.id)).toEqual(['2', '3']);
  });
});

describe('coada de sincronizare', () => {
  beforeEach(async () => {
    await putTickets(EV, tickets(3));
    anchorToServer(Date.now());
  });

  it('trimite si marcheaza, fara sa stearga', async () => {
    await scanCode('T-1', { eventId: EV });
    await scanCode('T-2', { eventId: EV });

    const post: Poster = async (batch) => ({ ok: true, acks: batch.map((b) => ({ id: b.id })) });
    const r = await flushAll(post);

    expect(r.sent).toBe(2);
    expect((await pendingScans()).length).toBe(0);
  });

  it('reteaua picata nu pierde nimic: scanurile raman in coada', async () => {
    await scanCode('T-1', { eventId: EV });
    const r = await flushAll(async () => ({ ok: false }), 2);
    expect(r.sent).toBe(0);
    expect((await pendingScans()).length).toBe(1);
  });

  it('retrimite acelasi id, ca serverul sa poata ignora duplicatele', async () => {
    await scanCode('T-1', { eventId: EV });
    const seen: string[] = [];
    await flushAll(async (b) => {
      seen.push(...b.map((x) => x.id));
      return { ok: false };
    }, 1);
    await flushAll(async (b) => {
      seen.push(...b.map((x) => x.id));
      return { ok: true, acks: b.map((x) => ({ id: x.id })) };
    }, 1);
    expect(seen[0]).toBe(seen[1]);
  });

  it('raporteaza cand serverul contrazice decizia locala', async () => {
    await scanCode('T-1', { eventId: EV });
    const post: Poster = async (batch) => ({
      ok: true,
      acks: batch.map((b) => ({ id: b.id, result: 'duplicate' as const })),
    });
    const r = await flushAll(post);
    expect(r.corrections).toHaveLength(1);
    expect(r.corrections[0]).toMatchObject({ local: 'valid', server: 'duplicate' });
  });

  it('un scan respins de server nu blocheaza restul cozii', async () => {
    await scanCode('T-1', { eventId: EV });
    await scanCode('T-2', { eventId: EV });
    const post: Poster = async (batch) => ({
      ok: true,
      acks: batch.map((b, i) => (i === 0 ? { id: b.id, error: 'invalid' } : { id: b.id })),
    });
    const r = await flushAll(post);
    expect(r.failed).toBe(1);
    expect(r.sent).toBe(1);
    expect((await pendingScans()).length).toBe(0);
  });

  it('trimite in ordine cronologica', async () => {
    await scanCode('T-1', { eventId: EV });
    await scanCode('T-2', { eventId: EV });
    await scanCode('T-3', { eventId: EV });
    let order: string[] = [];
    await flushAll(async (b) => {
      order = b.map((x) => x.code);
      return { ok: true, acks: b.map((x) => ({ id: x.id })) };
    });
    expect(order).toEqual(['T-1', 'T-2', 'T-3']);
  });
});
