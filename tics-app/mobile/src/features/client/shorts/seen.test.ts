/* =========================================================
   Teste pentru rotatia din „Pe val".

   Ce se verifica aici nu se poate verifica prin ecran: ordinea in care ajung
   short-urile depinde de ce ai vazut ACUM CATEVA ZILE, iar un test de interfata
   ar trebui sa astepte zile ca sa vada diferenta.
   ========================================================= */
import { beforeEach, describe, expect, it } from 'vitest';

import { freshCount, markSeen, rotateBySeen, seenAt } from './seen';

type S = { id: number };

const page = (...ids: number[]): S[] => ids.map((id) => ({ id }));
const ids = (list: S[]) => list.map((s) => s.id);

describe('rotatia dupa ce ai vazut', () => {
  beforeEach(() => {
    localStorage.clear();
  });

  it('lasa pagina neatinsa cand nimic nu a fost vazut', () => {
    expect(ids(rotateBySeen(page(1, 2, 3)))).toEqual([1, 2, 3]);
  });

  it('trimite la coada ce ai vazut deja', () => {
    markSeen(2);

    expect(ids(rotateBySeen(page(1, 2, 3)))).toEqual([1, 3, 2]);
  });

  it('pastreaza ordinea serverului intre cele nevazute', () => {
    markSeen(1);

    expect(ids(rotateBySeen(page(1, 2, 3, 4)))).toEqual([2, 3, 4, 1]);
  });

  it('cand ai vazut tot, primul e cel vazut cel mai demult', () => {
    markSeen(1, 3_000);
    markSeen(2, 1_000);
    markSeen(3, 2_000);

    expect(ids(rotateBySeen(page(1, 2, 3)))).toEqual([2, 3, 1]);
  });

  it('numara cate sunt noi — de asta depinde saritul primei pagini', () => {
    markSeen(1);
    markSeen(3);

    expect(freshCount(page(1, 2, 3))).toBe(1);
    expect(freshCount(page(1, 3))).toBe(0);
  });

  it('tine minte peste „reporniri" (acelasi localStorage, alt import)', () => {
    markSeen(42, 12345);

    expect(seenAt(42)).toBe(12345);
    expect(seenAt(43)).toBe(0);
  });

  it('un localStorage stricat nu opreste feedul', () => {
    localStorage.setItem('tics.shorts.seen.v1', 'nu-e-json');

    expect(ids(rotateBySeen(page(1, 2)))).toEqual([1, 2]);
    expect(() => markSeen(1)).not.toThrow();
  });

  it('nu creste la nesfarsit: pastreaza cele mai recente 800', () => {
    for (let i = 0; i < 850; i++) markSeen(i, 1000 + i);

    const kept = Object.keys(JSON.parse(localStorage.getItem('tics.shorts.seen.v1') ?? '{}'));

    expect(kept.length).toBe(800);
    // cele mai vechi au fost aruncate, cele mai noi au ramas
    expect(seenAt(849)).toBe(1849);
    expect(seenAt(0)).toBe(0);
  });
});
