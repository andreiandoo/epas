/* =========================================================
   Swipe orizontal peste feed-ul de shorts.

   Feed-ul deruleaza VERTICAL cu scroll-snap, deci gestul orizontal trebuie
   recunoscut fara sa atinga derularea: nu apelam niciodata preventDefault si
   nu ascultam `touchmove` in mod activ — doar comparam punctul de plecare cu
   cel de sosire.

   Doua conditii, ambele necesare:
     - deplasare orizontala peste prag (un tap tremurat nu e un swipe);
     - orizontala clar dominanta fata de verticala, altfel o derulare usor
       oblica ar declansa navigarea si utilizatorul ar sari din ecran vrand
       doar sa treaca la short-ul urmator.

   Gestul se anuleaza si daca degetul zaboveste: peste `MAX_MS` e mai probabil
   o derulare cu ezitare decat o intentie de navigare.
   ========================================================= */
import { useRef, type TouchEvent } from 'react';

/** Cat trebuie sa parcurga degetul pe orizontala ca sa conteze. */
const MIN_DX = 60;

/** De cate ori trebuie sa fie orizontala mai mare decat verticala. */
const DOMINANCE = 1.6;

/** Peste atat, gestul nu mai e citit ca swipe. */
const MAX_MS = 700;

export type SwipeHandlers = {
  onTouchStart: (e: TouchEvent) => void;
  onTouchEnd: (e: TouchEvent) => void;
};

/**
 * @param onRight  degetul merge de la stanga la dreapta — conventional „inapoi"
 * @param onLeft   degetul merge de la dreapta la stanga — „intra in detaliu"
 */
export function useHorizontalSwipe(onRight?: () => void, onLeft?: () => void): SwipeHandlers {
  const start = useRef<{ x: number; y: number; t: number } | null>(null);

  return {
    onTouchStart: (e) => {
      // Cu doua degete pe ecran e un pinch, nu un swipe.
      if (e.touches.length !== 1) {
        start.current = null;

        return;
      }

      const t = e.touches[0];
      start.current = { x: t.clientX, y: t.clientY, t: performance.now() };
    },

    onTouchEnd: (e) => {
      const from = start.current;
      start.current = null;
      if (!from) return;

      const t = e.changedTouches[0];
      if (!t) return;

      if (performance.now() - from.t > MAX_MS) return;

      const dx = t.clientX - from.x;
      const dy = t.clientY - from.y;

      if (Math.abs(dx) < MIN_DX) return;
      if (Math.abs(dx) < Math.abs(dy) * DOMINANCE) return;

      if (dx > 0) onRight?.();
      else onLeft?.();
    },
  };
}
