/* =========================================================
   Bara de jos, montata O SINGURA DATA in carcasa aplicatiei.

   Pana acum fiecare ecran isi randa propria bara. La schimbarea ecranului,
   vechea bara pleca odata cu ecranul vechi si aparea alta odata cu cel nou —
   asa ca elementul care ar trebui sa fie punctul fix al aplicatiei era exact
   cel care clipea la fiecare navigare.

   Ecranele nu mai randeaza bara; o CER. `<BottomNav active="…" />` doar anunta
   „aici se vede bara, cu tabul asta activ", iar carcasa o deseneaza. Ecranele
   raman neschimbate ca scriere, deci nu s-a atins niciunul dintre porturile
   1:1 din prototip.

   MARCA DE PROPRIETATE (`token`) rezolva suprapunerea: la navigare, ecranul
   nou se monteaza INAINTE ca cel vechi sa se demonteze (cel vechi mai traieste
   cat tine animatia de iesire). Fara marca, demontarea celui vechi ar sterge
   cererea celui nou si bara ar disparea exact dupa ce a aparut. Asa, fiecare
   ecran sterge doar propria cerere, si numai daca mai e a lui.
   ========================================================= */
import { useSyncExternalStore } from 'react';

export type BottomNavState = { visible: boolean; active: string };

let state: BottomNavState = { visible: false, active: '' };
let owner = 0;
let seq = 0;

const listeners = new Set<() => void>();
const emit = () => listeners.forEach((l) => l());

const subscribe = (l: () => void) => {
  listeners.add(l);

  return () => listeners.delete(l);
};

/** Un identificator nou pentru ecranul care cere bara. */
export const claimToken = () => ++seq;

export function requestBottomNav(token: number, active: string): void {
  if (state.visible && state.active === active && owner === token) return;

  owner = token;
  state = { visible: true, active };
  emit();
}

/** Elibereaza, dar numai daca cererea curenta chiar apartine acestui ecran. */
export function releaseBottomNav(token: number): void {
  if (owner !== token) return;

  state = { visible: false, active: '' };
  emit();
}

export const useBottomNavState = (): BottomNavState =>
  useSyncExternalStore(subscribe, () => state, () => state);
