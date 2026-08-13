/* =========================================================
   Partea sociala a unui eveniment: cine dintre prieteni merge, si daca eu sunt
   vizibil acolo.

   Hook separat de `catalogData`, desi tot despre un eveniment e vorba: astea
   cer cont Tics si se schimba des (cineva tocmai a cumparat), pe cand fisa
   evenimentului e publica si se cacheaza. Amestecate, ori am fi cachea-t ceva
   ce trebuie proaspat, ori am fi cerut din nou ceva ce nu se schimba.
   ========================================================= */
import { useCallback, useEffect, useState } from 'react';
import { fetchEventFriends, setFriendsVisibility, type EventFriends } from '../../api/friends';

export function useEventFriends(eventId: number | null) {
  const [data, setData] = useState<EventFriends | null>(null);

  const load = useCallback(async () => {
    if (!eventId || !Number.isFinite(eventId)) return;

    const r = await fetchEventFriends(eventId);
    /* Esecul e tacut prin design: fara cont Tics raspunsul e o eroare, iar
       un eveniment nu trebuie sa arate un bloc de eroare pentru o functie
       optionala. */
    if (r.ok) setData(r.data);
  }, [eventId]);

  useEffect(() => {
    void load();
  }, [load]);

  /* Optimist, cu revenire la esec: comutatorul trebuie sa raspunda instant,
     dar n-are voie sa ramana pe o stare pe care serverul n-a acceptat-o. */
  const toggle = useCallback(async () => {
    if (!eventId || !data) return;

    const next = !data.visible;
    setData({ ...data, visible: next });

    const r = await setFriendsVisibility(next, eventId);

    if (!r.ok) setData({ ...data, visible: !next });
  }, [eventId, data]);

  return { data, toggle, reload: load };
}
