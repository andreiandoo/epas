/* =========================================================
   CE AI VAZUT DEJA IN „PE VAL"

   Feed-ul serverului e ordonat determinist (keyset pe featured/published_at/id),
   deci raspunde identic la fiecare deschidere: aceleasi short-uri, in aceeasi
   ordine, oricat le-ai fi derulat ieri. Pentru un feed de descoperire asta e
   cel mai rau lucru posibil — a doua oara nu mai are ce sa-ti arate.

   Solutia sta in client, nu pe server, si intentionat: „vazut" e o proprietate
   a TELEFONULUI acestuia si a acestui om, iar tinuta pe server ar cere cont
   (feed-ul e public) si un tabel care creste cu fiecare derulare a fiecarui
   vizitator.

   Regula: cele nevazute intai, in ordinea serverului; cele vazute la coada.
   Nu se sterge nimic din feed — un short vazut ramane accesibil, doar ca dupa
   tot ce e nou. Cand ai vazut chiar tot, feedul redevine cel obisnuit, ordonat
   dupa cat de demult l-ai vazut.
   ========================================================= */

const LS = 'tics.shorts.seen.v1';

/** Cate id-uri tinem minte. La ~40 de octeti fiecare, 800 inseamna ~32 KB. */
const CAP = 800;

type SeenMap = Record<string, number>;

function read(): SeenMap {
  try {
    const raw = localStorage.getItem(LS);
    if (!raw) return {};

    const v = JSON.parse(raw) as unknown;

    return v && typeof v === 'object' && !Array.isArray(v) ? (v as SeenMap) : {};
  } catch {
    return {};
  }
}

function write(map: SeenMap): void {
  try {
    const entries = Object.entries(map);

    /* Peste plafon, se arunca cele mai VECHI vizionari. Daca ai vazut ceva
       acum opt luni, e la fel de bun ca nou. */
    const kept =
      entries.length <= CAP
        ? entries
        : entries.sort((a, b) => b[1] - a[1]).slice(0, CAP);

    localStorage.setItem(LS, JSON.stringify(Object.fromEntries(kept)));
  } catch {
    /* stocare plina sau blocata: rotatia se pierde, feedul ramane functional */
  }
}

/** Momentul ultimei vizionari, sau 0. */
export function seenAt(id: number): number {
  return read()[String(id)] ?? 0;
}

export function markSeen(id: number, when = Date.now()): void {
  const map = read();
  map[String(id)] = when;
  write(map);
}

/**
 * Rearanjeaza o pagina: nevazutele in ordinea primita, vazutele dupa ele,
 * cea mai veche vizionare prima.
 *
 * Se aplica pe FIECARE pagina in parte, nu pe lista intreaga: paginile deja
 * afisate nu se ating niciodata, altfel ecranul ar sari sub degetul omului in
 * timp ce deruleaza.
 */
export function rotateBySeen<T extends { id: number }>(page: T[]): T[] {
  const map = read();
  const fresh: T[] = [];
  const old: Array<{ item: T; at: number }> = [];

  for (const item of page) {
    const at = map[String(item.id)];
    if (at) old.push({ item, at });
    else fresh.push(item);
  }

  old.sort((a, b) => a.at - b.at);

  return [...fresh, ...old.map((o) => o.item)];
}

/** Cate din pagina sunt noi pentru omul asta. */
export function freshCount<T extends { id: number }>(page: T[]): number {
  const map = read();

  return page.filter((i) => !map[String(i.id)]).length;
}
