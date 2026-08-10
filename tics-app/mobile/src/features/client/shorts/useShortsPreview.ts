/* =========================================================
   Cateva postere din feed, pentru zona „Pe val" de pe Exploreaza.

   Bannerul era un gradient fix cu un text: arata ca un buton de reclama, nu ca
   o intrare catre continut. Cu postere reale, se vede DIN CE intri — si se
   schimba singur pe masura ce apar short-uri noi.

   Cererea e mica (cateva randuri) si se retine cat traieste aplicatia: e un
   ornament de pe un ecran de lista, n-are voie sa coste o cerere de fiecare
   data cand utilizatorul revine pe Exploreaza.
   ========================================================= */
import { useEffect, useState } from 'react';
import { fetchShortsFeed } from '../../../api/shorts';

export type ShortsPreview = { posters: string[]; count: number };

let cached: ShortsPreview | null = null;
let inFlight: Promise<ShortsPreview | null> | null = null;

async function load(): Promise<ShortsPreview | null> {
  try {
    const page = await fetchShortsFeed({ feed: 'for_you', limit: 8 });
    const posters = page.items
      .map((s) => s.playback.poster_url)
      .filter((p): p is string => typeof p === 'string' && p !== '')
      .slice(0, 3);

    // Fara postere nu avem ce arata; apelantul ramane pe varianta simpla.
    if (posters.length === 0) return null;

    cached = { posters, count: page.items.length };

    return cached;
  } catch {
    return null;
  }
}

export function useShortsPreview(): ShortsPreview | null {
  const [preview, setPreview] = useState<ShortsPreview | null>(cached);

  useEffect(() => {
    if (cached) return;

    let alive = true;
    inFlight = inFlight ?? load();
    void inFlight.then((p) => {
      inFlight = null;
      if (alive && p) setPreview(p);
    });

    return () => {
      alive = false;
    };
  }, []);

  return preview;
}
