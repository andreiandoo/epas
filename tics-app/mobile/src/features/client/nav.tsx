/* =========================================================
   CLIENT — navigator de stiva, portat din prototip.

   Prototipul tine `stack=[{id,data}]` si randeaza cu paint(dir), aplicand
   clasele .enter-forward/.exit-forward/.enter-back/.exit-back (definite in
   client.css) pentru tranzitii. Reproducem exact acelasi model: ecranul nou
   se monteaza peste cel vechi, iar cel vechi se demonteaza dupa 420ms —
   durata tranzitiei din CSS-ul prototipului.

   `tabScreens` = ecranele care NU se stivuiesc intre ele (bottom nav-ul le
   inlocuieste, nu le adauga), exact ca functia tab() din prototip.
   ========================================================= */
import { createContext, useCallback, useContext, useMemo, useRef, useState, type ReactNode } from 'react';

export type Frame = { id: string; data?: Record<string, unknown>; key: number };

/** Ecranele de tab: tab() le inlocuieste in varf, nu le stivuieste. */
/* Ecranele care se inlocuiesc intre ele in bara de jos. „ticslist" (Radar) a
   intrat aici odata cu mutarea lui in bara: fara asta, un tap pe Radar ar fi
   stivuit ecrane la nesfarsit in loc sa comute intre taburi. */
export const TAB_SCREENS = ['home', 'explore', 'ticslist', 'shorts', 'tickets', 'wallet', 'profile'];

/** Durata tranzitiei din client.css (.screen { transition: ... .4s }). */
const EXIT_MS = 420;

/**
 * Cat era derulat fiecare cadru, dupa cheia lui.
 *
 * Ecranele se DEMONTEAZA cand navighezi mai departe (asa functioneaza stiva
 * prototipului), deci pozitia de scroll se pierde: te intorci din Setari in
 * Profil si esti aruncat in capul paginii. Cheia cadrului supravietuieste
 * insa in stiva, asa ca o folosim ca sa punem scroll-ul inapoi la remontare.
 */
const scrollMemory = new Map<number, number>();
export const rememberScroll = (key: number, top: number) => scrollMemory.set(key, top);
export const recallScroll = (key: number) => scrollMemory.get(key) ?? 0;

type NavApi = {
  stack: Frame[];
  /** cadrul care iese, cat timp dureaza animatia */
  leaving: { frame: Frame; dir: 'forward' | 'back' } | null;
  top: Frame;
  go: (id: string, data?: Record<string, unknown>) => void;
  back: () => void;
  reset: (id: string, data?: Record<string, unknown>) => void;
  tab: (id: string) => void;
  dir: 'forward' | 'back';
};

const Ctx = createContext<NavApi | null>(null);

export function useNav() {
  const api = useContext(Ctx);
  if (!api) throw new Error('useNav folosit in afara <NavProvider>');
  return api;
}

export function NavProvider({ initial = 'home', children }: { initial?: string; children: ReactNode }) {
  const seq = useRef(1);
  const [stack, setStack] = useState<Frame[]>([{ id: initial, key: 0 }]);
  const [leaving, setLeaving] = useState<NavApi['leaving']>(null);
  const [dir, setDir] = useState<'forward' | 'back'>('forward');
  const timer = useRef<ReturnType<typeof setTimeout> | null>(null);

  const scheduleExit = useCallback((frame: Frame, direction: 'forward' | 'back') => {
    setLeaving({ frame, dir: direction });
    if (timer.current) clearTimeout(timer.current);
    timer.current = setTimeout(() => setLeaving(null), EXIT_MS);
  }, []);

  const go = useCallback(
    (id: string, data?: Record<string, unknown>) => {
      setDir('forward');
      setStack((s) => {
        scheduleExit(s[s.length - 1], 'forward');
        return [...s, { id, data, key: seq.current++ }];
      });
    },
    [scheduleExit],
  );

  const back = useCallback(() => {
    setDir('back');
    setStack((s) => {
      if (s.length <= 1) return s;
      scheduleExit(s[s.length - 1], 'back');
      return s.slice(0, -1);
    });
  }, [scheduleExit]);

  const reset = useCallback(
    (id: string, data?: Record<string, unknown>) => {
      setDir('forward');
      setStack((s) => {
        scheduleExit(s[s.length - 1], 'forward');
        return [{ id, data, key: seq.current++ }];
      });
    },
    [scheduleExit],
  );

  /**
   * tab(): daca ecranul cerut e DEJA in stiva ne intoarcem la el, ca sa nu-l
   * dublam si ca sa-si pastreze pozitia de scroll (Profil -> Portofel -> tab
   * Profil trebuie sa aduca acelasi Profil, nu unul nou, derulat sus).
   * Altfel: pe un ecran de tab il inlocuim; pe orice altceva resetam stiva.
   */
  const tab = useCallback(
    (id: string) => {
      setStack((s) => {
        const cur = s[s.length - 1];
        if (cur.id === id) return s;

        const at = s.findIndex((f) => f.id === id);
        if (at >= 0) {
          setDir('back');
          scheduleExit(cur, 'back');
          return s.slice(0, at + 1);
        }

        setDir('forward');
        scheduleExit(cur, 'forward');
        if (TAB_SCREENS.includes(cur.id)) {
          return [...s.slice(0, -1), { id, key: seq.current++ }];
        }
        return [{ id, key: seq.current++ }];
      });
    },
    [scheduleExit],
  );

  const api = useMemo<NavApi>(
    () => ({ stack, leaving, top: stack[stack.length - 1], go, back, reset, tab, dir }),
    [stack, leaving, go, back, reset, tab, dir],
  );

  return <Ctx.Provider value={api}>{children}</Ctx.Provider>;
}
