/* =========================================================
   LANGA TINE — evenimentele din jur, pe harta OpenStreetMap

   Sectiunea exista si in prototip, in „Explorează", dar era desenata: doua
   puncte fixe pe un gradient si acelasi festival, la orice ora si in orice
   oras.

   Harta e acum una reala (Leaflet + OSM, vezi osmMap.tsx) si se poate plimba:
   la fiecare oprire cere evenimentele din dreptunghiul vizibil, deci apar pini
   noi pe masura ce te muti. Lista de sub harta ramane cea „langa tine", pe
   raza de 100 km, ordonata dupa distanta — harta raspunde la „unde", lista la
   „cat de aproape".
   ========================================================= */
import { useCallback, useEffect, useRef, useState } from 'react';
import { Ic, sx } from '../../design/sx';
import { I } from '../../mock/prototype';
import {
  fetchEventsInBounds,
  fetchNearbyEvents,
  type MapEvent,
  type NearbyResult,
} from '../../api/catalog';
import { OsmMap, type Bounds } from './osmMap';
import { useNav } from './nav';

type Located = { lat: number; lng: number } | { city: string } | null;

/**
 * Pozitia utilizatorului. Se incearca GPS-ul, dar NU se asteapta dupa el:
 * pe Android, permisiunea de locatie lipseste din build-ul curent, deci
 * `navigator.geolocation` esueaza imediat. Orasul ales in aplicatie e
 * raspunsul de rezerva si acopera cazul obisnuit.
 */
function useWhereAmI(city: string): Located {
  const [gps, setGps] = useState<{ lat: number; lng: number } | null>(null);
  const [tried, setTried] = useState(false);

  useEffect(() => {
    if (!navigator.geolocation) {
      setTried(true);

      return;
    }

    let alive = true;
    navigator.geolocation.getCurrentPosition(
      (pos) => {
        if (!alive) return;
        setGps({ lat: pos.coords.latitude, lng: pos.coords.longitude });
        setTried(true);
      },
      () => {
        if (alive) setTried(true);
      },
      { timeout: 6000, maximumAge: 10 * 60 * 1000, enableHighAccuracy: false },
    );

    return () => {
      alive = false;
    };
  }, []);

  if (gps) return gps;
  if (!tried) return null;

  return city ? { city } : null;
}

export function NearYou({ city }: { city: string }) {
  const { go } = useNav();
  const at = useWhereAmI(city);
  const [data, setData] = useState<NearbyResult | null>(null);
  const [state, setState] = useState<'loading' | 'ready' | 'empty' | 'nowhere'>('loading');
  const [open, setOpen] = useState(false);

  /* Pinurile de pe harta sunt ALTCEVA decat lista: lista e „langa tine",
     pinurile sunt „ce se vede acum pe ecran". Cand plimbi harta spre alt
     oras, pinurile se schimba, dar lista de dedesubt ramane a ta. */
  const [pins, setPins] = useState<MapEvent[]>([]);
  const [tooWide, setTooWide] = useState(false);
  const boundsAbort = useRef<AbortController | null>(null);
  const boundsTimer = useRef<ReturnType<typeof setTimeout> | null>(null);

  useEffect(() => {
    if (!at) {
      /* Nici GPS, nici oras ales — nu avem de unde sti unde e „langa tine". */
      setState('nowhere');

      return;
    }

    const ac = new AbortController();
    setState('loading');

    void fetchNearbyEvents(at, { radius: 100, limit: 20 }, ac.signal).then((r) => {
      if (ac.signal.aborted) return;

      /* `events` se verifica, nu se presupune: sectiunea deseneaza pozitii
         calculate din raspuns, iar un raspuns de alta forma (proxy, cache
         vechi, ruta prinsa de alt handler) ar arunca in mijlocul randarii si
         ar lua cu el tot ecranul de pornire. */
      const list = Array.isArray(r?.events) ? r.events : [];

      if (!r || !list.length) {
        setData(null);
        setState(r ? 'empty' : 'nowhere');

        return;
      }

      setData({ ...r, events: list });
      setState('ready');
    });

    return () => ac.abort();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [at && 'lat' in at ? `${at.lat},${at.lng}` : (at?.city ?? '')]);

  /* Plimbatul hartii cere evenimente noi — dar nu la fiecare cadru de
     miscare: Leaflet trage `moveend` si dupa o glisare de doi pixeli, iar o
     cerere pe fiecare ar insemna zeci pe secunda. 350 ms de liniste inseamna
     „s-a oprit din mutat". */
  const onBounds = useCallback((b: Bounds) => {
    if (boundsTimer.current) clearTimeout(boundsTimer.current);

    boundsTimer.current = setTimeout(() => {
      boundsAbort.current?.abort();
      boundsAbort.current = new AbortController();
      const ac = boundsAbort.current;

      void fetchEventsInBounds(b, ac.signal).then((r) => {
        if (ac.signal.aborted || !r) return;
        setTooWide(!!r.too_wide);
        setPins(Array.isArray(r.events) ? r.events : []);
      });
    }, 350);
  }, []);

  useEffect(
    () => () => {
      if (boundsTimer.current) clearTimeout(boundsTimer.current);
      boundsAbort.current?.abort();
    },
    [],
  );

  /* Fara loc si fara rezultate, sectiunea nu se afiseaza deloc: un card gol pe
     ecranul de pornire e zgomot, nu informatie. */
  if (state === 'nowhere' || state === 'empty') return null;

  return (
    <div className="pad sec tight">
      <div className="between" style={sx('margin-bottom:10px')}>
        <div className="row" style={sx('gap:9px')}>
          <div
            style={sx('width:30px;height:30px;border-radius:10px;background:var(--indigo-soft);color:var(--indigo-2);display:grid;place-items:center')}
          >
            <Ic svg={I.pin} />
          </div>
          <div>
            <div style={sx('font-weight:600;font-size:14.5px')}>Lângă tine</div>
            <div className="muted" style={sx('font-size:11px')}>
              {data ? `${data.events.length} pe 100 km` : 'Caut în jurul tău…'}
            </div>
          </div>
        </div>
        {data && data.events.length > 8 ? (
          <button className="chip" onClick={() => setOpen((v) => !v)} style={sx('padding:6px 12px;font-size:11.5px')}>
            {open ? 'Ascunde' : 'Vezi tot'}
          </button>
        ) : null}
      </div>

      {state === 'loading' || !data ? (
        <div className="sk" style={sx('height:210px;border-radius:20px')} />
      ) : (
        <>
          <OsmMap
            center={data.center}
            zoom={9}
            height={240}
            events={pins}
            showMe
            onBoundsChange={onBounds}
            onPick={(e) => go('event', { id: String(e.id) })}
          />
          {tooWide ? (
            <div className="muted" style={sx('font-size:11px;margin-top:7px;text-align:center')}>
              Apropie harta ca să vezi evenimentele.
            </div>
          ) : null}

          {/* Sub harta: acelasi set, citibil. Pastila spune distanta, ca sa
              poti alege fara sa ghicesti care punct de pe harta e care. */}
          {open ? (
            <div style={sx('margin-top:11px;display:flex;flex-direction:column;gap:9px')}>
              {data.events.map((e) => (
                <div
                  key={e.id}
                  className="listitem"
                  onClick={() => go('event', { id: String(e.id) })}
                  style={sx('cursor:pointer')}
                >
                  <div
                    style={{
                      width: 44,
                      height: 44,
                      borderRadius: 14,
                      flex: 'none',
                      background: e.poster ? `url('${e.poster}') center/cover` : 'var(--surface-3)',
                    }}
                  />
                  <div style={sx('flex:1;min-width:0')}>
                    <div style={sx('font-weight:600;font-size:13.5px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis')}>
                      {e.title ?? 'Eveniment'}
                    </div>
                    <div className="muted" style={sx('font-size:11.5px;margin-top:2px')}>
                      {[e.date_label, e.venue?.name, e.city].filter(Boolean).join(' · ')}
                    </div>
                  </div>
                  <span className="chip-mini">{e.distance_km} km</span>
                </div>
              ))}
            </div>
          ) : (
            <div className="scroll-x" style={sx('margin-top:11px;padding:2px 0')}>
              {data.events.slice(0, 8).map((e) => (
                <button
                  key={e.id}
                  className="chip"
                  onClick={() => go('event', { id: String(e.id) })}
                  style={sx('padding:8px 12px;font-size:11.5px;white-space:nowrap;max-width:210px;overflow:hidden;text-overflow:ellipsis')}
                >
                  <b style={sx('color:var(--indigo-2)')}>{e.distance_km} km</b> · {e.title ?? 'Eveniment'}
                </button>
              ))}
            </div>
          )}
        </>
      )}
    </div>
  );
}
