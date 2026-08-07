/* =========================================================
   CLIENT — carduri image-forward, portate 1:1 din client-app.html:
     evMini()    linia 626   tile portret, imaginea domina
     expCard()   linia 629   tile de experienta, identitate verde
     radarCard() linia 637   card de revanzare, cu banda de pret
     evRow()     linia 643   rand orizontal, imagine la stanga

   Clasele si sirurile de stil sunt COPIATE, nu rescrise: sx() parseaza
   exact sirul din prototip. Fundalul vine din bgv() — scenele SVG
   procedurale — sau din posterul real EPAS, cand exista.
   ========================================================= */
import { Ic, cn, sx } from '../../design/sx';
import { I, VEN, money } from '../../mock/prototype';
import { eventBackground, type UiEvent } from '../../api/tenantClient';
import type { RadarItem as TicsRadarItem } from '../../api/ticsRadar';
import { useClient } from '../../store/client';
import { useNav } from './nav';

/* ---------- evMini(ev, st) ---------- */
export function EvMini({ ev, st }: { ev: UiEvent; st?: string }) {
  const { go } = useNav();
  return (
    <div className="mcard" onClick={() => go('event', { id: ev.id })} style={sx(st || 'min-width:212px')}>
      <div className="cover" style={{ background: eventBackground(ev), height: 268 }}>
        <span className="em">{ev.g}</span>
        <div className="scrim" />
        <div className="top">
          <span className={cn('gpill', ev.type === 'experience' ? 'gsolid' : 'solid')}>
            {ev.type === 'experience' ? '⛰ Experiență' : ev.cat}
          </span>
          <span className="gpill amber">
            <Ic svg={I.star} />
            {ev.rat}
          </span>
        </div>
        <div className="btm">
          <div className="ctitle">{ev.s}</div>
          <div className="cmeta">
            <span className="i">
              <Ic svg={I.cal} />
              <span>{ev.d}</span>
            </span>
            <span className="i">
              <Ic svg={I.pin} />
              <span>{ev.city}</span>
            </span>
          </div>
          <div className="cprice" style={sx('margin-top:9px')}>
            <small>de la</small>
            {money(ev.from)} lei
          </div>
        </div>
      </div>
    </div>
  );
}

/* ---------- expCard(ev) ---------- */
export function ExpCard({ ev }: { ev: UiEvent }) {
  const { go } = useNav();
  return (
    <div
      className="mcard"
      onClick={() => go('event', { id: ev.id })}
      style={sx('min-width:236px;border:1px solid var(--green-line)')}
    >
      <div className="cover" style={{ background: eventBackground(ev), height: 232 }}>
        <span className="em">{ev.g}</span>
        <div className="scrim" />
        <div className="top">
          <span className="gpill gsolid">⛰ Experiență</span>
          <span className="gpill amber">
            <Ic svg={I.star} />
            {ev.rat}
          </span>
        </div>
        <div className="btm">
          <div className="ctitle">{ev.s}</div>
          <div className="cmeta">
            <span className="i">
              <Ic svg={I.pin} />
              <span>{ev.city}</span>
            </span>
            <span className="i">
              <Ic svg={I.clock} />
              <span>{ev.time}</span>
            </span>
          </div>
          <div className="between" style={sx('margin-top:9px')}>
            <span className="cprice">
              <small>de la</small>
              {money(ev.from)} lei
            </span>
            <span className="gpill" style={sx('background:rgba(34,197,94,.92);border-color:transparent')}>
              <Ic svg={I.cal} /> alegi data
            </span>
          </div>
        </div>
      </div>
    </div>
  );
}

/* ---------- radarCard(t, st) ---------- */
const MPCOL = ['#7c3aed', '#0e7490', '#b45309', '#be185d'];

/* Forma e cea din api/ticsRadar (sursa reala) — datasetul prototipului o
   respecta si el, deci cardul randeaza la fel din oricare din ele. */
type RadarItem = Pick<TicsRadarItem, 'id' | 's' | 'city' | 'day' | 'mon' | 'g' | 'rat' | 'offers'> &
  Partial<Pick<TicsRadarItem, 'tone' | 'sc' | 'poster'>>;

export function RadarCard({ t, st }: { t: RadarItem; st?: string }) {
  const { go } = useNav();
  const ch = Math.min(...t.offers.map((o) => o[1]));
  const med = Math.round(ch * 1.28);
  const save = Math.round((1 - ch / med) * 100);

  return (
    <div
      className="mcard radar"
      onClick={() => go('ticsoffers', { id: t.id })}
      style={sx(st || 'min-width:270px')}
    >
      <div className="cover" style={{ background: eventBackground(t as unknown as UiEvent), height: 156 }}>
        <span className="em">{t.g}</span>
        <div className="scrim" />
        <div className="top">
          <span className="gpill" style={sx('background:rgba(4,3,9,.5)')}>
            <span className="livedot" />
            LIVE · {t.offers.length} oferte
          </span>
          <span className="gpill amber">
            <Ic svg={I.star} />
            {t.rat}
          </span>
        </div>
        <div className="btm">
          <div className="ctitle" style={sx('font-size:17px')}>
            {t.s}
          </div>
          <div className="cmeta">
            <span className="i">
              <Ic svg={I.pin} />
              <span>{t.city}</span>
            </span>
            <span className="i">
              <Ic svg={I.cal} />
              <span>
                {t.day} {t.mon}
              </span>
            </span>
          </div>
        </div>
      </div>
      <div className="rprice">
        <div className="avstack">
          {t.offers.slice(0, 3).map((o, k) => (
            <i key={o[0]} style={{ background: MPCOL[k] }}>
              {o[0][0].toUpperCase()}
            </i>
          ))}
        </div>
        <div style={sx('flex:1;min-width:0')}>
          <div className="lbl">cel mai bun preț</div>
          <div className="amt">
            {ch}
            <small> lei</small>
          </div>
        </div>
        <span className="gpill gsolid" style={sx('font-size:12px')}>
          ↓{save}%
        </span>
      </div>
    </div>
  );
}

/* ---------- evRow(ev) ---------- */
export function EvRow({ ev }: { ev: UiEvent }) {
  const { go } = useNav();
  const venueName = (VEN as Record<string, { name: string }>)[ev.ven]?.name ?? ev.ven;
  return (
    <div
      className="mcard"
      onClick={() => go('event', { id: ev.id })}
      style={sx('display:flex;align-items:stretch;border-radius:20px')}
    >
      <div className="cover" style={{ background: eventBackground(ev), width: 116, flex: 'none' }}>
        <span className="em" style={sx('font-size:58px;bottom:-8px;right:-6px')}>
          {ev.g}
        </span>
        <div className="scrim" style={sx('background:linear-gradient(90deg,rgba(4,3,9,.05),rgba(4,3,9,.35))')} />
        <span className="gpill" style={sx('position:absolute;top:9px;left:9px;padding:4px 8px')}>
          {ev.day ? `${ev.day} ${ev.mon}` : ev.d}
        </span>
      </div>
      <div style={sx('flex:1;min-width:0;padding:12px 14px;display:flex;flex-direction:column;justify-content:center')}>
        <div className="row" style={sx('gap:7px')}>
          <span className="chip-mini">{ev.type === 'experience' ? '⛰ Experiență' : ev.cat}</span>
          <span
            className="row"
            style={sx('gap:3px;color:var(--amber);font-size:11px;font-weight:600;margin-left:auto')}
          >
            <Ic svg={I.star} />
            {ev.rat}
          </span>
        </div>
        <div style={sx('font-weight:600;font-size:14.5px;margin-top:7px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis')}>
          {ev.s}
        </div>
        <div className="metaline" style={sx('margin-top:5px')}>
          <Ic svg={I.pin} />
          <span>{venueName}</span>
          <span className="dot" />
          <Ic svg={I.clock} />
          <span>{ev.time}</span>
        </div>
        <div
          style={{
            fontWeight: 700,
            color: ev.type === 'experience' ? 'var(--green-2)' : 'var(--indigo-2)',
            fontSize: '14px',
            marginTop: '8px',
          }}
        >
          de la {money(ev.from)} lei
        </div>
      </div>
    </div>
  );
}

/* ---------- cardul mare, „featured", de pe Acasa ---------- */
export function FeaturedCard({ ev }: { ev: UiEvent }) {
  const { go } = useNav();
  const saved = useClient((s) => s.saved.includes(ev.id));
  const toggleSaved = useClient((s) => s.toggleSaved);

  return (
    <div className="pad">
      <div className="mcard fade-up" onClick={() => go('event', { id: ev.id })}>
        <div className="cover" style={{ background: eventBackground(ev), height: 224 }}>
          <span className="em">{ev.g}</span>
          <div className="scrim" />
          <div className="top">
            <span className="gpill solid">{ev.cat}</span>
            <span
              className="circ"
              onClick={(e) => {
                e.stopPropagation();
                toggleSaved(ev.id);
              }}
            >
              {saved ? (
                <svg width={16} height={16} viewBox="0 0 24 24" fill="currentColor">
                  <path d="M12 21s-8-5-8-11a4.5 4.5 0 0 1 8-2.9A4.5 4.5 0 0 1 20 10c0 6-8 11-8 11z" />
                </svg>
              ) : (
                <Ic svg={I.save} />
              )}
            </span>
          </div>
          <div className="btm">
            <div className="ctitle" style={sx('font-size:21px')}>
              {ev.s}
            </div>
            <div className="cmeta">
              <span className="i">
                <Ic svg={I.cal} />
                <span>{ev.d}</span>
              </span>
              <span className="i">
                <Ic svg={I.clock} />
                <span>{ev.time}</span>
              </span>
              <span className="i">
                <Ic svg={I.pin} />
                <span>{ev.city}</span>
              </span>
            </div>
            <div className="between" style={sx('margin-top:12px')}>
              <span className="cprice" style={sx('font-size:18px')}>
                <small>de la</small>
                {money(ev.from)} lei
              </span>
              <span className="gpill" style={sx('background:#fff;color:#141020;border-color:#fff')}>
                Vezi bilete <Ic svg={I.arrow} />
              </span>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
