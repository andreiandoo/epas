/* =========================================================
   STAY22 — port 1:1 al lui S.stay22() (client-app.html, linia 769),
   inclusiv stayFilter() (355) si panStay() (1235).

   Harta e o suprafata cu grid care se translateaza ca sa centreze pin-ul
   selectat; lista de jos e sincronizata cu pin-ul si invers.
   ========================================================= */
import { useMemo } from 'react';
import { Ic, cn, sx } from '../../../design/sx';
import { I, STAY, STAYSORTS, STAYTYPES, VEN } from '../../../mock/prototype';
import { SafeTop } from '../kit';
import { useNav } from '../nav';
import { useClient } from '../../../store/client';

type Ev = Record<string, any>;
type Stay = { n: string; type: string; r: string; dkm: number; p: number; x: number; y: number; tone: string };

/** stayFilter() din prototip */
function stayFilter(f: { type: string; sort: string; maxPrice: number }): Stay[] {
  let l = (STAY as Stay[]).filter((s) => f.type === 'Toate' || s.type === f.type).filter((s) => s.p <= f.maxPrice);
  const so = f.sort;
  l = l
    .slice()
    .sort((a, b) => (so === 'price' ? a.p - b.p : so === 'rating' ? parseFloat(b.r) - parseFloat(a.r) : a.dkm - b.dkm));
  return l;
}

/** panStay() din prototip */
const panStay = (list: Stay[], i: number) => {
  const s = list[i];
  return s ? `translate(${(50 - s.x).toFixed(2)}%,${(50 - s.y).toFixed(2)}%)` : 'translate(0%,0%)';
};

const icoFor = (t: string) => (t === 'Hotel' ? '🏨' : t === 'Apartament' ? '🏢' : '🛏');

export function Stay22({ id }: { id?: string }) {
  const { back } = useNav();
  const showToast = useClient((s) => s.showToast);
  const f = useClient((s) => s.stayF);
  const setStayF = useClient((s) => s.setStayF);
  const resetStayF = useClient((s) => s.resetStayF);
  const stayPin = useClient((s) => s.stayPin);
  const setStayPin = useClient((s) => s.setStayPin);
  const fltOpen = useClient((s) => s.stayFltOpen);
  const toggleFlt = useClient((s) => s.toggleStayFlt);

  const v = ((VEN as Record<string, Ev>)[id || ''] || (VEN as Record<string, Ev>).arena) as Ev;
  const list = useMemo(() => stayFilter(f), [f]);
  const pin = stayPin >= list.length ? 0 : stayPin;
  const active = (f.type !== 'Toate' ? 1 : 0) + (f.maxPrice < 500 ? 1 : 0);

  return (
    <div style={sx('min-height:100%;background:var(--bg);display:flex;flex-direction:column;height:100%')}>
      <div style={sx('position:relative;flex:1;min-height:0;overflow:hidden;background:#0d0b16')}>
        <div style={sx('position:absolute;inset:0;background-image:var(--grid);background-size:18px 18px;opacity:.5')} />
        <div style={sx('position:absolute;inset:0;background:radial-gradient(circle at 50% 50%, rgba(139,92,246,.15), transparent 62%)')} />

        <div
          className="mapinner"
          style={{
            position: 'absolute',
            inset: 0,
            zIndex: 2,
            transition: 'transform .55s cubic-bezier(.3,.85,.3,1)',
            transform: panStay(list, pin),
          }}
        >
          <div style={sx('position:absolute;left:50%;top:52%;transform:translate(-50%,-50%);text-align:center')}>
            <div style={sx('width:18px;height:18px;border-radius:50%;background:var(--indigo);border:3px solid #fff;box-shadow:var(--sh-p);margin:0 auto')} />
            <div style={sx('font-size:10px;font-weight:600;color:#fff;background:rgba(0,0,0,.55);padding:3px 8px;border-radius:999px;margin-top:6px')}>
              {v.name}
            </div>
          </div>
          {list.map((s, i) => (
            <div
              key={s.n}
              className={cn('mappin', i === pin && 'on')}
              style={{ left: `${s.x}%`, top: `${s.y}%` }}
              onClick={() => setStayPin(i)}
            >
              <div className="bub">{s.p} lei</div>
            </div>
          ))}
        </div>

        <div style={sx('position:absolute;left:50%;top:50%;transform:translate(-50%,-50%);z-index:3;pointer-events:none;width:66px;height:66px;border-radius:50%;border:1.5px dashed rgba(255,255,255,.28)')} />

        <SafeTop />
        <div className="between pad" style={sx('position:relative;padding-top:4px;z-index:5')}>
          <div className="icon-btn glass" onClick={back}>
            <Ic svg={I.back} />
          </div>
          <div className="icon-btn glass" onClick={toggleFlt} style={sx('position:relative')}>
            <Ic svg={I.slider} />
            {active ? (
              <span style={sx('position:absolute;top:-4px;right:-4px;min-width:16px;height:16px;padding:0 4px;border-radius:9px;background:var(--indigo);color:#fff;font-size:10px;font-weight:700;display:grid;place-items:center')}>
                {active}
              </span>
            ) : null}
          </div>
        </div>

        {fltOpen ? (
          <div style={sx('position:absolute;left:12px;right:12px;top:64px;z-index:8')}>
            <div className="card" style={sx('padding:15px;background:rgba(18,15,30,.96);backdrop-filter:blur(16px)')}>
              <div className="between">
                <span className="label" style={sx('margin:0')}>
                  Tip cazare
                </span>
                <button className="chip" onClick={resetStayF} style={sx('padding:5px 11px;font-size:11px')}>
                  Resetează
                </button>
              </div>
              <div className="scroll-x" style={sx('padding:8px 0 2px;margin:0 -4px')}>
                {(STAYTYPES as string[]).map((t) => (
                  <button key={t} className={cn('chip', f.type === t && 'ind on')} onClick={() => setStayF({ type: t })}>
                    {t}
                  </button>
                ))}
              </div>
              <div className="label" style={sx('margin:12px 0 8px')}>
                Sortează după
              </div>
              <div className="scroll-x" style={sx('padding:0 0 2px;margin:0 -4px')}>
                {(STAYSORTS as [string, string][]).map((s) => (
                  <button
                    key={s[0]}
                    className={cn('chip', f.sort === s[0] && 'ind on')}
                    onClick={() => setStayF({ sort: s[0] as 'dist' | 'price' | 'rating' })}
                  >
                    {s[1]}
                  </button>
                ))}
              </div>
              <div className="between" style={sx('margin-top:14px')}>
                <span className="label" style={sx('margin:0')}>
                  Preț maxim / noapte
                </span>
                <span style={sx('font-weight:600;font-size:13px;color:var(--indigo-2)')}>
                  {f.maxPrice >= 500 ? 'oricât' : f.maxPrice + ' lei'}
                </span>
              </div>
              <input
                type="range"
                min={100}
                max={500}
                step={20}
                value={f.maxPrice}
                onChange={(e) => setStayF({ maxPrice: +e.target.value })}
                style={sx('width:100%;margin-top:8px;accent-color:var(--indigo)')}
              />
              <button className="cta" onClick={toggleFlt} style={sx('margin-top:14px;padding:12px')}>
                Vezi {list.length} cazări
              </button>
            </div>
          </div>
        ) : null}

        <div style={sx('position:absolute;bottom:10px;left:16px;font-size:10px;color:var(--faint);font-weight:500;z-index:5')}>
          powered by Stay22
        </div>
      </div>

      <div
        style={sx('background:var(--bg);border-radius:24px 24px 0 0;margin-top:-18px;position:relative;box-shadow:0 -20px 40px -20px rgba(0,0,0,.6);padding-bottom:14px')}
      >
        <div style={sx('width:40px;height:4px;border-radius:9px;background:var(--line-2);margin:10px auto 6px')} />
        <div className="between pad">
          <div className="h2" style={sx('font-size:15px')}>
            Cazare lângă {v.name}
          </div>
          <span className="muted" style={sx('font-size:11.5px;font-weight:500')}>
            {list.length} {list.length === 1 ? 'opțiune' : 'opțiuni'}
          </span>
        </div>

        {list.length ? (
          <div className="scroll-x" style={sx('margin-top:10px')}>
            {list.map((s, i) => (
              <div
                key={s.n}
                className="card"
                onClick={() => setStayPin(i)}
                style={{
                  minWidth: 230,
                  padding: 11,
                  display: 'flex',
                  gap: 11,
                  cursor: 'pointer',
                  borderColor: i === pin ? 'var(--indigo)' : 'var(--line)',
                }}
              >
                <div
                  style={{
                    width: 54,
                    height: 54,
                    borderRadius: 14,
                    background: s.tone,
                    flex: 'none',
                    display: 'grid',
                    placeItems: 'center',
                    color: '#fff',
                    fontSize: '20px',
                  }}
                >
                  {icoFor(s.type)}
                </div>
                <div style={sx('flex:1;min-width:0')}>
                  <div style={sx('font-weight:600;font-size:13px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis')}>
                    {s.n}
                  </div>
                  <div className="row" style={sx('gap:8px;margin-top:3px;font-size:11px')}>
                    <span style={sx('color:var(--amber);font-weight:500')}>★ {s.r}</span>
                    <span className="muted">{s.dkm} km</span>
                    <span className="badge" style={sx('padding:1px 7px;font-size:9.5px;background:var(--surface-3);color:var(--muted)')}>
                      {s.type}
                    </span>
                  </div>
                  <div style={sx('font-weight:600;color:var(--indigo-2);font-size:13px;margin-top:5px')}>
                    {s.p} lei<span className="muted" style={sx('font-weight:600;font-size:10.5px')}> /noapte</span>
                  </div>
                </div>
              </div>
            ))}
          </div>
        ) : (
          <div className="pad" style={sx('text-align:center;padding-top:18px')}>
            <div className="muted" style={sx('font-size:12.5px')}>
              Nicio cazare pentru filtrele alese.
            </div>
          </div>
        )}

        <div className="pad" style={sx('margin-top:12px')}>
          <button className="cta green" onClick={() => showToast('Deschid Stay22')}>
            Rezervă prin Stay22 <Ic svg={I.ext} />
          </button>
        </div>
      </div>
    </div>
  );
}
