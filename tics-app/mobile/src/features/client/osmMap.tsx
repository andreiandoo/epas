/* =========================================================
   HARTA OpenStreetMap (Leaflet)

   Inainte, „Lângă tine" desena o harta schematica: pozitii reale, dar fara
   strazi. Cererea a fost explicita — harta adevarata, pe care sa te poti
   plimba, si care sa se populeze pe masura ce o plimbi. Asta face fisierul
   asta, si il folosesc doua ecrane: Acasa (multe evenimente, incarcate pe
   dreptunghiul vizibil) si fisa locatiei (un singur pin, fara incarcare).

   DE STIUT DESPRE DALE
   Dalele vin de la tile.openstreetmap.org, gratuit si fara cheie. Politica lor
   de utilizare interzice traficul „greu" de la aplicatii; la volumul de acum e
   in regula, dar cand aplicatia creste, se schimba `TILE_URL` cu un furnizor
   platit (MapTiler, Stadia, Thunderforest) si NIMIC ALTCEVA — de aceea URL-ul
   si atributia stau intr-o singura constanta, nu imprastiate prin ecrane.
   Atributia OSM e obligatorie si e randata mereu, nu optional.

   DE CE `import()` LA CERERE
   Leaflet + CSS-ul lui inseamna ~45 KB gzip. Ecranul de pornire nu trebuie sa
   le astepte ca sa se afiseze: se incarca dupa montare, iar pana atunci se
   vede un schelet. Harta e a treia sectiune din ecran, nimeni n-o vede in
   prima secunda.
   ========================================================= */
import { useEffect, useRef, useState } from 'react';
import type { Map as LeafletMap, Marker } from 'leaflet';
import { sx } from '../../design/sx';
import type { MapEvent } from '../../api/catalog';

const TILE_URL = 'https://tile.openstreetmap.org/{z}/{x}/{y}.png';
const TILE_ATTRIB = '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>';

export type Bounds = { north: number; south: number; east: number; west: number };

type Props = {
  center: { lat: number; lng: number };
  zoom?: number;
  height?: number;
  /** Evenimentele de desenat. Se redeseneaza doar ce s-a schimbat. */
  events?: MapEvent[];
  /** Punctul „tu esti aici" (cerc mic, distinct de pinuri). */
  showMe?: boolean;
  /** Chemat la fiecare oprire a plimbarii, cu dreptunghiul vizibil. */
  onBoundsChange?: (b: Bounds) => void;
  onPick?: (e: MapEvent) => void;
  /** Pin unic, fara interactiune — pentru fisa unei locatii. */
  pin?: { lat: number; lng: number; label?: string } | null;
  interactive?: boolean;
};

/** Pinul: acelasi marcaj pentru toate hartile, ca sa nu divergem in doua stiluri. */
function pinHtml(poster: string | null, count = 1): string {
  const face = poster
    ? `background-image:url('${poster.replace(/'/g, '')}');background-size:cover;background-position:center`
    : 'background:linear-gradient(135deg,#6d5bf5,#8b7cf6)';

  const badge =
    count > 1
      ? `<span style="position:absolute;right:-5px;top:-5px;min-width:18px;height:18px;padding:0 4px;border-radius:9px;background:#6d5bf5;color:#fff;font-size:10px;font-weight:700;line-height:18px;text-align:center;border:2px solid #0c0a16;box-sizing:content-box">${count}</span>`
      : '';

  return `<span style="position:relative;display:block;width:30px;height:30px;border-radius:50%;border:2px solid #fff;box-shadow:0 3px 10px rgba(0,0,0,.45);${face}">${badge}</span>`;
}

/**
 * Evenimentele de la aceeasi adresa, adunate intr-un singur pin.
 *
 * Fara asta, Bucurestiul arata ca un teanc de 60 de bulinuce peste centru: se
 * vedea doar ultima, iar celelalte 59 erau imposibil de atins. Se grupeaza pe
 * coordonata rotunjita la ~11 m — adica exact salile care chiar sunt in
 * acelasi loc, plus toate cele carora le-am pus centrul orasului pentru ca
 * n-aveau coordonate proprii.
 */
type PinGroup = { key: string; lat: number; lng: number; items: MapEvent[] };

function groupPins(events: MapEvent[]): PinGroup[] {
  const by = new Map<string, PinGroup>();

  for (const e of events) {
    const key = `${e.lat.toFixed(4)},${e.lng.toFixed(4)}`;
    const slot = by.get(key);

    if (slot) slot.items.push(e);
    else by.set(key, { key, lat: e.lat, lng: e.lng, items: [e] });
  }

  return [...by.values()];
}

export function OsmMap({
  center,
  zoom = 10,
  height = 240,
  events = [],
  showMe = false,
  onBoundsChange,
  onPick,
  pin = null,
  interactive = true,
}: Props) {
  const host = useRef<HTMLDivElement | null>(null);
  const map = useRef<LeafletMap | null>(null);
  const markers = useRef(new Map<string, Marker>());
  const [ready, setReady] = useState(false);

  /* Callback-urile prin ref: harta se construieste O SINGURA DATA, iar un
     handler prins la construire ar ramane pe primul `onBoundsChange` primit —
     adica ar chema mereu versiunea veche, cu starea veche. */
  const cbBounds = useRef(onBoundsChange);
  const cbPick = useRef(onPick);
  cbBounds.current = onBoundsChange;
  cbPick.current = onPick;

  useEffect(() => {
    let alive = true;
    let cleanup: (() => void) | undefined;

    void (async () => {
      const [L] = await Promise.all([import('leaflet'), import('leaflet/dist/leaflet.css')]);

      if (!alive || !host.current || map.current) return;

      const m = L.map(host.current, {
        center: [center.lat, center.lng],
        zoom,
        zoomControl: false,
        attributionControl: true,
        // pe telefon, glisarea cu un deget e felul normal de a misca harta
        dragging: interactive,
        scrollWheelZoom: interactive,
        doubleClickZoom: interactive,
        touchZoom: interactive,
        keyboard: interactive,
      });

      L.tileLayer(TILE_URL, { maxZoom: 19, attribution: TILE_ATTRIB }).addTo(m);

      if (showMe) {
        L.circleMarker([center.lat, center.lng], {
          radius: 7,
          color: '#0c0a16',
          weight: 3,
          fillColor: '#2dd6ee',
          fillOpacity: 1,
        }).addTo(m);
      }

      if (pin) {
        L.marker([pin.lat, pin.lng], {
          icon: L.divIcon({ html: pinHtml(null), className: '', iconSize: [30, 30], iconAnchor: [15, 15] }),
          title: pin.label ?? '',
        }).addTo(m);
      }

      const emit = () => {
        const b = m.getBounds();
        cbBounds.current?.({
          north: b.getNorth(),
          south: b.getSouth(),
          east: b.getEast(),
          west: b.getWest(),
        });
      };

      m.on('moveend', emit);
      m.on('zoomend', emit);

      map.current = m;
      setReady(true);
      emit();

      cleanup = () => {
        m.off();
        m.remove();
        map.current = null;
        markers.current.clear();
      };
    })();

    return () => {
      alive = false;
      cleanup?.();
    };
    // constructie unica: centrul se muta prin `setView` mai jos, nu prin remontare
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  /* Pinurile: se adauga cele noi si se scot cele iesite din vedere. Nu se
     sterge tot si nu se redeseneaza: la fiecare glisare, pinurile ar clipi. */
  useEffect(() => {
    const m = map.current;
    if (!m || !ready) return;

    let cancelled = false;

    void import('leaflet').then((L) => {
      if (cancelled || !map.current) return;

      const groups = groupPins(events);
      const seen = new Set<string>();

      for (const g of groups) {
        seen.add(g.key);
        if (markers.current.has(g.key)) continue;

        const first = g.items[0];
        const label =
          g.items.length > 1
            ? `${g.items.length} evenimente aici`
            : (first.title ?? 'Eveniment');

        const marker = L.marker([g.lat, g.lng], {
          icon: L.divIcon({
            html: pinHtml(first.poster, g.items.length),
            className: '',
            iconSize: [30, 30],
            iconAnchor: [15, 15],
          }),
          title: label,
          alt: label,
        }).addTo(m);

        marker.on('click', () => {
          /* Un pin cu mai multe evenimente nu poate „deschide evenimentul" —
             ar insemna sa alegem noi unul din 12. Apropie harta in schimb:
             salile vecine se despart si fiecare isi capata pinul. Cand chiar
             sunt la aceeasi adresa, raman impreuna si asta e adevarul. */
          if (g.items.length > 1) {
            m.setView([g.lat, g.lng], Math.min(m.getZoom() + 3, 18));

            return;
          }

          cbPick.current?.(first);
        });

        markers.current.set(g.key, marker);
      }

      for (const [key, marker] of markers.current) {
        if (!seen.has(key)) {
          marker.remove();
          markers.current.delete(key);
        }
      }
    });

    return () => {
      cancelled = true;
    };
  }, [events, ready]);

  return (
    /* `isolation:isolate` + `z-index:0` inchid straturile Leaflet intr-un
       context propriu. Leaflet isi pune panourile la z-index 400 si controalele
       la 1000, iar bara de jos sta la 30: fara contextul asta, harta trecea
       PESTE bara, iar la derulare bara disparea sub ea. */
    <div
      style={sx(
        `position:relative;z-index:0;isolation:isolate;height:${height}px;border-radius:20px;overflow:hidden;border:1px solid var(--line-2);background:#0c0a16`,
      )}
    >
      <div ref={host} style={sx('position:absolute;inset:0')} />
      {!ready ? <div className="sk" style={sx('position:absolute;inset:0')} /> : null}
    </div>
  );
}
