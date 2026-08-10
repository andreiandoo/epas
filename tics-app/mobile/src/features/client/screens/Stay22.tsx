/* =========================================================
   CAZARE & CUM AJUNG.

   Ecranul din prototip (S.stay22, client-app.html:769) desena o harta cu grid
   si o lista de hoteluri inventate. Ca demo isi facea treaba; ca ecran real,
   nu: nimeni nu poate rezerva o camera care nu exista, iar „Direcții" nu ducea
   nicaieri.

   Acum are doua parti, si fiecare functioneaza pe cont propriu:

   1. CUM AJUNG — adresa reala a locatiei si butoane catre aplicatiile de harti
      de pe telefon. Nu depinde de niciun serviciu extern si merge intotdeauna.

   2. CAZARE — harta Stay22, serviciul dupa care ecranul era numit de la
      inceput. Cere un cod de afiliat (`VITE_STAY22_AID`). Fara el sectiunea
      LIPSESTE cu totul, in loc sa arate hoteluri false: o lista de cazari
      inventate langa o sala reala e o minciuna despre orasul acela, iar cineva
      chiar ar incerca sa rezerve.
   ========================================================= */
import { Ic, sx } from '../../../design/sx';
import { I, VEN } from '../../../mock/prototype';
import { CatalogLoading, MissingContent, SafeTop } from '../kit';
import { useNav } from '../nav';
import { useClient } from '../../../store/client';
import { useCatalogVenue } from '../catalogData';

type Ev = Record<string, any>;

/** Codul de afiliat Stay22. Fara el, partea de cazare nu se randeaza. */
const STAY22_AID = import.meta.env.VITE_STAY22_AID as string | undefined;

/**
 * Linkul catre harti.
 *
 * Coordonatele bat adresa cand le avem: un nume de sala scris usor diferit
 * poate nimeri alt oras, pe cand o pereche lat/lng nu poate fi inteleasa
 * gresit. Se deschide in aplicatia nativa de pe telefon (Capacitor
 * intercepteaza `_blank`).
 */
function mapsUrl(v: { name: string; addr: string; city: string; lat?: number | null; lng?: number | null }) {
  if (typeof v.lat === 'number' && typeof v.lng === 'number') {
    return `https://www.google.com/maps/dir/?api=1&destination=${v.lat},${v.lng}`;
  }

  const q = [v.name, v.addr, v.city].filter(Boolean).join(', ');

  return `https://www.google.com/maps/dir/?api=1&destination=${encodeURIComponent(q)}`;
}

function wazeUrl(v: { name: string; addr: string; city: string; lat?: number | null; lng?: number | null }) {
  if (typeof v.lat === 'number' && typeof v.lng === 'number') {
    return `https://waze.com/ul?ll=${v.lat},${v.lng}&navigate=yes`;
  }

  return `https://waze.com/ul?q=${encodeURIComponent([v.name, v.addr, v.city].filter(Boolean).join(', '))}`;
}

export function Stay22({ id }: { id?: string }) {
  const { back } = useNav();
  const showToast = useClient((s) => s.showToast);

  const demo = (VEN as Record<string, Ev>)[id || ''] as Ev | undefined;
  const live = useCatalogVenue(demo ? undefined : id);

  if (!demo && live.loading) return <CatalogLoading title="Cazare" />;
  if (!demo && !live.data) return <MissingContent what="Locația" />;

  const raw = demo ?? (live.data as { rec: Ev }).rec;
  const v = {
    name: raw.name ?? '',
    addr: raw.addr ?? '',
    city: raw.city ?? '',
    lat: raw._lat ?? null,
    lng: raw._lng ?? null,
  };

  const fullAddress = [v.addr, v.city].filter(Boolean).join(', ');

  const open = (url: string) => window.open(url, '_blank', 'noopener');

  const copyAddress = async () => {
    try {
      await navigator.clipboard.writeText([v.name, fullAddress].filter(Boolean).join(', '));
      showToast('Adresă copiată');
    } catch {
      // clipboard blocat — nu e o eroare pe care sa o vada utilizatorul
    }
  };

  /* Adresa cautata de Stay22. Cu coordonate e fara echivoc; altfel cade pe
     text, care e tot ce stim. */
  const stayQuery =
    typeof v.lat === 'number' && typeof v.lng === 'number'
      ? `${v.lat},${v.lng}`
      : [v.name, fullAddress].filter(Boolean).join(', ');

  return (
    <div style={sx('min-height:100%;background:var(--bg);padding-bottom:26px')}>
      <SafeTop />

      <div className="pad" style={sx('padding-top:6px')}>
        <div className="row" style={sx('gap:12px')}>
          <div className="icon-btn" onClick={back}>
            <Ic svg={I.back} />
          </div>
          <div style={sx('min-width:0')}>
            <div className="h2">Cazare & cum ajung</div>
            <div className="muted" style={sx('font-size:11.5px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis')}>
              {v.name}
            </div>
          </div>
        </div>
      </div>

      {/* ---------- cum ajung ---------- */}
      <div className="pad" style={sx('margin-top:16px')}>
        <div className="card" style={sx('padding:14px')}>
          <div className="row" style={sx('gap:11px;align-items:flex-start')}>
            <div className="iconbadge" style={sx('background:var(--indigo-soft);color:var(--indigo-2)')}>
              <Ic svg={I.pin} />
            </div>
            <div style={sx('flex:1;min-width:0')}>
              <div style={sx('font-weight:600;font-size:13.5px')}>{v.name}</div>
              <div className="muted" style={sx('font-size:12px;margin-top:3px;line-height:1.45')}>
                {fullAddress || 'Adresă neanunțată'}
              </div>
            </div>
            <button className="chip" onClick={copyAddress} style={sx('padding:7px 11px;flex:none')}>
              Copiază
            </button>
          </div>

          <div className="row" style={sx('gap:10px;margin-top:13px')}>
            <button className="cta" onClick={() => open(mapsUrl(v))} style={sx('padding:12px')}>
              <Ic svg={I.car} /> Direcții
            </button>
            <button className="cta ghost" onClick={() => open(wazeUrl(v))} style={sx('padding:12px')}>
              Waze
            </button>
          </div>
        </div>
      </div>

      {/* ---------- cazare ---------- */}
      {STAY22_AID ? (
        <>
          <div className="pad" style={sx('margin-top:20px')}>
            <div className="between">
              <div className="h2" style={sx('font-size:15px')}>
                Cazare în apropiere
              </div>
              <span className="muted" style={sx('font-size:10.5px;font-weight:600')}>
                prin Stay22
              </span>
            </div>
            <div className="muted" style={sx('font-size:11.5px;margin-top:4px;line-height:1.45')}>
              Prețuri live de la mai multe site-uri de rezervări, pe hartă în jurul locației.
            </div>
          </div>

          <div className="pad" style={sx('margin-top:12px')}>
            <div style={sx('border-radius:20px;overflow:hidden;box-shadow:var(--sh);background:#0d0b16')}>
              <iframe
                title="Cazare în apropiere"
                src={`https://www.stay22.com/embed/gm?aid=${encodeURIComponent(STAY22_AID)}&address=${encodeURIComponent(stayQuery)}&maincolor=7C3AED`}
                style={{ width: '100%', height: 460, border: 0, display: 'block' }}
                loading="lazy"
              />
            </div>
            {/* Obligatoriu, nu decorativ: linkurile de rezervare sunt afiliate. */}
            <div className="muted" style={sx('font-size:10px;text-align:center;margin-top:8px;line-height:1.5')}>
              Rezervările se fac pe site-urile partenere. Tixello poate primi un comision, fără cost suplimentar pentru tine.
            </div>
          </div>
        </>
      ) : null}
    </div>
  );
}
