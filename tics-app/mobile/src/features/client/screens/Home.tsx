/* =========================================================
   ACASA — port 1:1 al lui S.home() din client-app.html (linia 658).
   Structura, clasele si sirurile de stil sunt copiate verbatim.
   Sectiuni, in ordinea din prototip:
     header locatie + clopotel · searchbar · rail de categorii ("Pe val" +
     CATS) · "Pentru tine" (AI) · "Evenimente" (card mare + rail) ·
     "Experiente" (banda verde) · card festival · "Radar" · bottom nav
   ========================================================= */
import { Ic, sx } from '../../../design/sx';
import { CATS, EV, FEST, I } from '../../../mock/prototype';
import type { UiEvent } from '../../../api/tenantClient';
import { eventBackground } from '../../../api/tenantClient';
import { EvMini, ExpCard, FeaturedCard, RadarCard } from '../cards';
import { BottomNav, SafeTop, SecH } from '../kit';
import { useNav } from '../nav';
import { useRadarList } from '../radarData';

/* EV vine dintr-un dump verbatim (@ts-nocheck), deci fara index signature. */
const ev = (id: string) => (EV as Record<string, unknown>)[id] as UiEvent;

export function Home() {
  const { go } = useNav();
  const { items: radar } = useRadarList(3);

  const f = ev('coldplay');
  const forYou = ['coldplay', 'celestial', 'swan'].map(ev);
  const events = ['coldplay', 'swan', 'celestial'].map(ev);
  const exps = ['salina', 'atv', 'wine'].map(ev);

  return (
    <div className="grid" style={sx('min-height:100%;padding-bottom:6px')}>
      <div className="stickytop">
        <SafeTop />
        <div className="hdr" style={sx('padding:4px 20px 11px')}>
          <div className="row" style={sx('gap:11px')}>
            <div className="avatar">AP</div>
            <div>
              <div className="loc-l">
                <Ic svg={I.pin} /> Locația ta
              </div>
              <div className="loc-v">
                Cluj-Napoca{' '}
                <svg
                  width="13"
                  height="13"
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="var(--muted)"
                  strokeWidth="2.6"
                  strokeLinecap="round"
                >
                  <path d="M6 9l6 6 6-6" />
                </svg>
              </div>
            </div>
          </div>
          <div className="icon-btn" onClick={() => go('notif')} style={sx('position:relative')}>
            <Ic svg={I.bell} />
            <span
              style={sx(
                'position:absolute;top:9px;right:10px;width:8px;height:8px;border-radius:50%;background:var(--indigo);border:2px solid var(--bg)',
              )}
            />
          </div>
        </div>
      </div>

      <div className="pad" style={sx('margin-top:14px')}>
        <div className="searchbar" onClick={() => go('search')}>
          <span className="muted">
            <Ic svg={I.search} />
          </span>
          <span className="ph">Caută evenimente, artiști…</span>
          <span style={sx('color:var(--indigo-2)')}>
            <Ic svg={I.slider} />
          </span>
        </div>
      </div>

      <div className="rail" style={sx('margin-top:15px;padding-bottom:2px')}>
        <button
          className="chip"
          onClick={() => go('shorts')}
          style={sx(
            'background:linear-gradient(120deg,#0e7490,#7c3aed,#ec4899);color:#fff;border-color:transparent;font-weight:600',
          )}
        >
          <Ic svg={I.wave} /> Pe val
        </button>
        {(CATS as [string, string][]).map((c, i) => (
          <button
            key={c[0]}
            className={`chip ind ${i === 0 ? 'on' : ''}`}
            onClick={() => {
              if (i === 0) return;
              if (c[0] === 'Festival') go('festival');
              else go('category', { id: c[0] });
            }}
          >
            {c[1]} {c[0]}
          </button>
        ))}
      </div>

      <div className="sec">
        <SecH icon="🤖" icbg="var(--indigo-soft)" iccol="var(--indigo-2)" title="Pentru tine" sub="Alese de AI pe gusturile tale" />
        <div className="rail">
          {forYou.map((e) => (
            <EvMini key={e.id} ev={e} />
          ))}
        </div>
      </div>

      <div className="sec">
        <SecH
          icon={I.ticket}
          icbg="var(--indigo-soft)"
          iccol="var(--indigo-2)"
          title="Evenimente"
          sub="Concerte · teatru · festivaluri"
          more={['Vezi tot', () => go('category', { id: 'Concerte' })]}
        />
        <FeaturedCard ev={f} />
        <div className="rail" style={sx('margin-top:13px')}>
          {events.slice(1).map((e) => (
            <EvMini key={e.id} ev={e} />
          ))}
        </div>
      </div>

      <div
        className="sec"
        style={sx(
          'padding:18px 0 20px;background:linear-gradient(180deg,rgba(34,197,94,.07),transparent 70%);border-top:1px solid var(--green-line)',
        )}
      >
        <SecH
          icon="⛰"
          icbg="var(--green-soft)"
          iccol="var(--green-2)"
          title="Experiențe"
          sub="Tururi · aventuri — alegi data ta"
          more={['Vezi tot', () => go('category', { id: 'Experiențe' })]}
        />
        <div className="rail">
          {exps.map((e) => (
            <ExpCard key={e.id} ev={e} />
          ))}
        </div>
      </div>

      <div className="pad sec tight">
        <div className="mcard" onClick={() => go('festival')}>
          <div className="cover" style={{ background: eventBackground(FEST as unknown as UiEvent), height: 172 }}>
            <span className="em">🎪</span>
            <div className="scrim" />
            <div className="top">
              <span className="gpill" style={sx('background:rgba(255,255,255,.2)')}>
                ● În desfășurare
              </span>
              <span className="gpill amber">
                <Ic svg={I.star} />
                {FEST.rat}
              </span>
            </div>
            <div className="btm">
              <div className="ctitle" style={sx('font-size:20px')}>
                {FEST.t}
              </div>
              <div className="cmeta">
                <span className="i">
                  <Ic svg={I.cal} />
                  <span>{FEST.d}</span>
                </span>
                <span className="i">
                  <Ic svg={I.pin} />
                  <span>Wonderland, Cluj</span>
                </span>
              </div>
              <div className="row" style={sx('gap:7px;margin-top:11px')}>
                <span className="gpill">🎪 Lineup 40+</span>
                <span className="gpill">💳 Cashless</span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div className="sec">
        <SecH
          icon={I.layers}
          icbg="var(--cyan-soft)"
          iccol="var(--cyan)"
          title="Radar"
          sub="Cel mai bun preț din toată piața"
          more={['Vezi tot', () => go('ticslist')]}
        />
        <div className="rail">
          {/* aceleasi date reale ca ecranul Radar (app.tics.ro), doar trei */}
          {radar.map((t) => (
            <RadarCard key={t.id} t={t} />
          ))}
        </div>
      </div>

      <BottomNav active="home" />
    </div>
  );
}
