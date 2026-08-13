/* =========================================================
   LANGA TINE — evenimentele din raza de 100 km, pe harta

   Sectiunea exista si in prototip, in „Explorează", dar era desenata: doua
   puncte fixe pe un gradient si acelasi festival, la orice ora si in orice
   oras. Acum vine din `/catalog/events/nearby`, cu distanta calculata pe
   server.

   DE CE O HARTA DESENATA DE NOI, si nu una cu strazi
   O harta cu dale (Leaflet + OpenStreetMap, Mapbox, MapTiler) inseamna ori o
   politica de utilizare pe care un app comercial n-o respecta, ori o cheie de
   API si o factura pe afisari. Aici nu ai nevoie sa vezi strazi: intrebarea e
   „ce se intampla aproape si CAT de aproape". Pozitiile sunt reale, proiectate
   din lat/lng, iar cercurile spun distanta mai clar decat ar face-o un fundal
   de harta. Daca se ia o cheie de dale, fundalul se schimba fara sa se atinga
   restul.
   ========================================================= */
import { useEffect, useState } from 'react';
import { Ic, sx } from '../../design/sx';
import { I } from '../../mock/prototype';
import { fetchNearbyEvents, type NearbyEvent, type NearbyResult } from '../../api/catalog';
import { useNav } from './nav';

/** Cate km inseamna un grad, ca sa proiectam lat/lng in kilometri plani. */
const KM_PER_DEG = 111.045;

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

/** Proiectie plana in jurul centrului: la 100 km, curbura e sub un pixel. */
function project(center: { lat: number; lng: number }, p: { lat: number; lng: number }) {
  const x = (p.lng - center.lng) * KM_PER_DEG * Math.cos((center.lat * Math.PI) / 180);
  const y = (center.lat - p.lat) * KM_PER_DEG;

  return { x, y };
}

function Map({ data, onPick }: { data: NearbyResult; onPick: (e: NearbyEvent) => void }) {
  /* Scara se ia dupa cel mai departat eveniment AFISAT, nu dupa raza ceruta:
     daca tot ce ai la 100 km e la 12 km, o harta scalata la 100 km ar
     ingramadi totul intr-un punct in mijloc. Minim 8 km, ca doua evenimente
     din acelasi oras sa nu se suprapuna complet. */
  const far = Math.max(8, ...data.events.map((e) => e.distance_km));
  const rings = [far / 3, (far * 2) / 3, far];

  return (
    <div
      style={sx('position:relative;height:210px;border-radius:20px;overflow:hidden;background:radial-gradient(120% 120% at 50% 50%,#171331,#0c0a16);border:1px solid var(--line-2)')}
    >
      <div style={sx('position:absolute;inset:0;background-image:var(--grid);background-size:26px 26px;opacity:.45')} />

      {/* cercurile de distanta */}
      {rings.map((km, i) => {
        const size = ((i + 1) / rings.length) * 92;

        return (
          <div
            key={km}
            style={{
              position: 'absolute',
              left: '50%',
              top: '50%',
              width: `${size}%`,
              height: `${size * (210 / 210)}%`,
              transform: 'translate(-50%,-50%)',
              borderRadius: '50%',
              border: '1px dashed rgba(139,124,246,.28)',
              pointerEvents: 'none',
            }}
          />
        );
      })}
      <div
        className="muted"
        style={sx('position:absolute;left:50%;top:calc(50% - 96px);transform:translateX(-50%);font-size:9.5px;font-weight:600;letter-spacing:.06em')}
      >
        {Math.round(far)} KM
      </div>

      {/* evenimentele */}
      {data.events.map((e) => {
        const p = project(data.center, e);
        /* 46% din latime/inaltime pentru cercul exterior: restul e marginea in
           care incape pastila cu numele, ca sa nu iasa din card. */
        const left = 50 + (p.x / far) * 42;
        const top = 50 + (p.y / far) * 42;

        return (
          <button
            key={e.id}
            onClick={() => onPick(e)}
            aria-label={`${e.title ?? 'Eveniment'} · ${e.distance_km} km`}
            style={{
              position: 'absolute',
              left: `${Math.max(4, Math.min(96, left))}%`,
              top: `${Math.max(6, Math.min(94, top))}%`,
              transform: 'translate(-50%,-50%)',
              width: 30,
              height: 30,
              borderRadius: '50%',
              border: '2px solid rgba(255,255,255,.9)',
              background: e.poster ? `url('${e.poster}') center/cover` : 'var(--indigo)',
              boxShadow: 'var(--sh-p)',
              padding: 0,
              cursor: 'pointer',
            }}
          />
        );
      })}

      {/* tu */}
      <div
        style={sx('position:absolute;left:50%;top:50%;transform:translate(-50%,-50%);width:14px;height:14px;border-radius:50%;background:var(--cyan);border:3px solid #0c0a16;box-shadow:0 0 0 6px rgba(45,214,238,.18)')}
      />
    </div>
  );
}

export function NearYou({ city }: { city: string }) {
  const { go } = useNav();
  const at = useWhereAmI(city);
  const [data, setData] = useState<NearbyResult | null>(null);
  const [state, setState] = useState<'loading' | 'ready' | 'empty' | 'nowhere'>('loading');
  const [open, setOpen] = useState(false);

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
          <Map data={data} onPick={(e) => go('event', { id: String(e.id) })} />

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
