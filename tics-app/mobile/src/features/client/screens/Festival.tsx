/* =========================================================
   FESTIVAL — port 1:1 al lui S.festival() (client-app.html, linia 1117).
   Sectiuni: hero cu video, badge-uri, cardul de portofel cashless, Despre,
   artisti (ART + lineup), galerie cu lightbox, bilete & abonamente, servicii
   & inchirieri, programul pe zile si scene, dock cu CTA.
   ========================================================= */
import { Ic, cn, sx } from '../../../design/sx';
import { ART, FEST, FSTAGES, I, lei } from '../../../mock/prototype';
import { DBar } from '../kit';
import { useNav } from '../nav';
import { useClient } from '../../../store/client';
import { useLightbox } from '../lightbox';

type Ev = Record<string, any>;

export function Festival() {
  const { go, tab } = useNav();
  const lb = useLightbox();
  const showToast = useClient((s) => s.showToast);
  const balance = useClient((s) => s.balance);
  const fDay = useClient((s) => s.fDay);
  const fStage = useClient((s) => s.fStage);
  const f = FEST as Ev;
  const stages = FSTAGES as Ev[];
  const s = stages[fStage];
  const set = s.day[fDay] as [string, string][] | undefined;

  return (
    <div style={sx('min-height:100%;background:var(--bg);padding-bottom:2px')}>
      <DBar
        title={f.name || 'Festival'}
        right={
          <>
            <div className="icon-btn glass">
              <Ic svg={I.share} />
            </div>
            <div className="icon-btn glass" onClick={() => showToast('Salvat')}>
              <Ic svg={I.save} />
            </div>
          </>
        }
      />

      <div className="poster" style={{ background: f.tone, height: 280, borderRadius: '0 0 30px 30px' }}>
        <div style={sx('position:absolute;inset:0;background:linear-gradient(180deg,rgba(0,0,0,.28),transparent 30%,rgba(11,9,18,.96))')} />
        {f.video ? (
          <div style={sx('position:absolute;left:50%;top:118px;transform:translateX(-50%)')}>
            <div style={sx('width:56px;height:56px;border-radius:50%;background:rgba(255,255,255,.2);border:1px solid rgba(255,255,255,.4);backdrop-filter:blur(8px);display:grid;place-items:center;color:#fff')}>
              <Ic svg={I.play} />
            </div>
          </div>
        ) : null}
        <div style={sx('position:absolute;left:20px;right:20px;bottom:20px')}>
          <span className="badge" style={sx('background:rgba(255,255,255,.16);backdrop-filter:blur(8px);color:#fff')}>
            Festival · 4 zile · ⭐ {f.rat}
          </span>
          <div style={sx('font-size:24px;font-weight:600;margin-top:9px;letter-spacing:-.03em')}>{f.t}</div>
          <div style={sx('font-size:12.5px;opacity:.82;margin-top:3px')}>{f.d} · Wonderland, Cluj · cashless</div>
        </div>
      </div>

      <div className="pad" style={sx('margin-top:16px')}>
        <div className="row" style={sx('gap:8px;flex-wrap:wrap')}>
          {['🎪 40+ artiști', '🏕️ Camping', '💳 Cashless', '🍔 Food court'].map((b) => (
            <span key={b} className="badge" style={sx('background:var(--surface-2);border:1px solid var(--line);color:var(--ink-2)')}>
              {b}
            </span>
          ))}
        </div>

        <div
          className="card"
          onClick={() => tab('wallet')}
          style={sx('margin-top:14px;cursor:pointer;background:linear-gradient(135deg,var(--indigo-3),#2a1065);border:1px solid var(--line-2);color:#fff;padding:15px 16px;display:flex;align-items:center;gap:13px')}
        >
          <div style={sx('width:44px;height:44px;border-radius:13px;background:rgba(255,255,255,.18);display:grid;place-items:center')}>
            <Ic svg={I.wallet} />
          </div>
          <div style={sx('flex:1')}>
            <div style={sx('font-weight:600;font-size:14px')}>Portofel cashless</div>
            <div style={sx('font-size:11.5px;opacity:.82')}>Sold {lei(balance)} lei · plătește cu brățara</div>
          </div>
          <Ic svg={I.arrow} />
        </div>

        <div className="h2" style={sx('margin-top:22px;font-size:15px')}>
          Despre festival
        </div>
        <p style={sx('color:var(--ink-2);font-size:13.5px;line-height:1.62;margin-top:8px')}>{f.desc}</p>

        <div className="h2" style={sx('margin-top:22px;font-size:15px')}>
          Artiști
        </div>
        <div className="scroll-x" style={sx('margin-top:11px;padding:0')}>
          {(f.artists as string[]).map((id) => {
            const a = (ART as Record<string, Ev>)[id];
            if (!a) return null;
            return (
              <div key={id} onClick={() => go('artist', { id })} style={sx('min-width:90px;text-align:center;cursor:pointer')}>
                <div style={{ width: 72, height: 72, borderRadius: 24, margin: '0 auto', background: a.tone, display: 'grid', placeItems: 'center', fontSize: '28px' }}>
                  {a.g}
                </div>
                <div style={sx('font-weight:500;font-size:12.5px;margin-top:7px')}>{a.name}</div>
                <div style={sx('font-size:10.5px;color:var(--muted)')}>{a.role}</div>
              </div>
            );
          })}
          {(f.lineup as [string, string][]).slice(2).map((l) => (
            <div key={l[0]} style={sx('min-width:90px;text-align:center')}>
              <div style={sx('width:72px;height:72px;border-radius:24px;margin:0 auto;background:var(--surface-3);display:grid;place-items:center;font-size:26px')}>
                🎤
              </div>
              <div style={sx('font-weight:500;font-size:12.5px;margin-top:7px')}>{l[0]}</div>
              <div style={sx('font-size:10.5px;color:var(--muted)')}>{l[1]}</div>
            </div>
          ))}
        </div>

        <div className="between" style={sx('margin-top:22px')}>
          <div className="h2" style={sx('font-size:15px')}>
            Galerie
          </div>
          <span className="muted" style={sx('font-size:11px;font-weight:600')}>
            atinge pentru mărire
          </span>
        </div>
        <div className="scroll-x" style={sx('margin-top:11px;padding:0')}>
          {(f.gallery as string[]).map((g, i) => (
            <div
              key={i}
              onClick={() => lb.open(f.gallery, i, '🎪')}
              style={{ minWidth: 150, height: 108, borderRadius: 16, background: g, boxShadow: 'var(--sh)', cursor: 'pointer', position: 'relative' }}
            >
              <span style={sx('position:absolute;right:8px;bottom:8px;width:26px;height:26px;border-radius:50%;background:rgba(10,7,20,.5);backdrop-filter:blur(6px);display:grid;place-items:center;color:#fff')}>
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.4" strokeLinecap="round">
                  <path d="M15 3h6v6M9 21H3v-6M21 3l-7 7M3 21l7-7" />
                </svg>
              </span>
            </div>
          ))}
          <div
            onClick={() => lb.open(f.gallery, 0, '🎪')}
            style={{ minWidth: 150, height: 108, borderRadius: 16, background: f.tone, display: 'grid', placeItems: 'center', color: '#fff', cursor: 'pointer' }}
          >
            <div style={sx('width:44px;height:44px;border-radius:50%;background:rgba(255,255,255,.22);display:grid;place-items:center')}>
              <Ic svg={I.play} />
            </div>
          </div>
        </div>
      </div>

      <div className="pad" style={sx('margin-top:22px')}>
        <div className="between">
          <div className="h2" style={sx('font-size:15px')}>
            Bilete & abonamente
          </div>
          <span className="pts" style={sx('font-size:10.5px')}>
            <Ic svg={I.star} /> +puncte
          </span>
        </div>
        <div style={sx('margin-top:11px;display:flex;flex-direction:column;gap:10px')}>
          {(f.tt as Ev[]).map((t, i) => (
            <div
              key={t.n}
              className="card"
              onClick={() => showToast('Adăugat: ' + t.n)}
              style={
                i === 1
                  ? { padding: 13, cursor: 'pointer', border: '1.5px solid var(--indigo-line)', background: 'var(--indigo-soft)' }
                  : { padding: 13, cursor: 'pointer' }
              }
            >
              <div className="between">
                <div style={sx('flex:1')}>
                  <div className="row" style={sx('gap:7px')}>
                    <div style={sx('font-weight:600;font-size:14px')}>{t.n}</div>
                    {t.old ? (
                      <span className="badge" style={sx('background:rgba(240,97,109,.16);color:#f0616d')}>
                        -{Math.round((1 - t.p / t.old) * 100)}%
                      </span>
                    ) : null}
                    {i === 1 ? (
                      <span className="badge" style={sx('background:var(--indigo);color:#fff')}>
                        Popular
                      </span>
                    ) : null}
                  </div>
                  <div className="muted" style={sx('font-size:11.5px;margin-top:4px;line-height:1.4')}>
                    {t.desc}
                  </div>
                  <div className="row" style={sx('gap:7px;margin-top:7px')}>
                    <span style={sx('font-weight:600;color:var(--indigo-2);font-size:15px')}>{t.p} lei</span>
                    {t.old ? (
                      <span className="muted" style={sx('text-decoration:line-through;font-size:12px')}>
                        {t.old} lei
                      </span>
                    ) : null}
                  </div>
                </div>
                <div
                  className="icon-btn"
                  style={sx('width:34px;height:34px;background:linear-gradient(135deg,var(--indigo),var(--indigo-3));color:#fff;border:0;align-self:center')}
                >
                  <Ic svg={I.plus} />
                </div>
              </div>
            </div>
          ))}
        </div>
      </div>

      <div className="pad" style={sx('margin-top:22px')}>
        <div className="h2" style={sx('font-size:15px')}>
          Servicii & închirieri
        </div>
        <div className="muted" style={sx('font-size:11.5px;margin-top:2px')}>
          Camping, parcare, glamping & locker — le adaugi la checkout
        </div>
        <div style={sx('margin-top:11px;display:flex;flex-direction:column;gap:10px')}>
          {(f.rentals as Ev[]).map((r) => (
            <div
              key={r.n}
              className="card"
              onClick={() => showToast('Adăugat: ' + r.n)}
              style={sx('padding:12px;display:flex;align-items:center;gap:12px;cursor:pointer')}
            >
              <div style={sx('width:42px;height:42px;border-radius:12px;background:var(--surface-3);display:grid;place-items:center;font-size:19px')}>
                {r.ic}
              </div>
              <div style={sx('flex:1')}>
                <div style={sx('font-weight:600;font-size:13.5px')}>{r.n}</div>
                <div className="muted" style={sx('font-size:11.5px')}>
                  {r.d} · <b style={sx('color:var(--indigo-2)')}>{r.p} lei</b>
                  {r.period ? ' /perioadă' : ''}
                </div>
              </div>
              <Ic svg={I.arrow} />
            </div>
          ))}
        </div>
      </div>

      <div className="pad" style={sx('margin-top:22px')}>
        <div className="h2" style={sx('font-size:15px;margin-bottom:2px')}>
          Program pe zile & scene
        </div>
        <div className="scroll-x" style={sx('margin-top:10px;padding:0')}>
          {(f.days as string[]).map((d) => (
            <button key={d} className={cn('chip', d === fDay && 'ind on')} onClick={() => useClient.setState({ fDay: d })}>
              {d}
            </button>
          ))}
        </div>
        <div className="scroll-x" style={sx('margin-top:10px;padding:0')}>
          {stages.map((st, i) => (
            <button
              key={st.n}
              className={cn('chip', i === fStage && 'on')}
              onClick={() => useClient.setState({ fStage: i })}
              style={i === fStage ? { background: st.c, borderColor: st.c, color: '#fff' } : undefined}
            >
              {st.n}
            </button>
          ))}
        </div>

        <div className="card" style={{ marginTop: 12, padding: 14, borderLeft: `3px solid ${s.c}` }}>
          <div className="between">
            <div style={sx('font-weight:600;font-size:15px')}>{s.n}</div>
            <span className="badge" style={{ background: s.c + '22', color: s.c }}>
              {fDay}
            </span>
          </div>
          <div className="muted" style={sx('font-size:12px;margin-top:5px;line-height:1.45')}>
            {s.desc}
          </div>
          <div style={sx('margin-top:12px;display:flex;flex-direction:column;gap:9px')}>
            {set ? (
              set.map((l) => (
                <div key={l[0]} className="row" style={sx('gap:11px')}>
                  <div style={{ width: 38, height: 38, borderRadius: 11, background: s.c + '22', display: 'grid', placeItems: 'center', fontSize: '16px' }}>
                    🎧
                  </div>
                  <div style={sx('flex:1')}>
                    <div style={sx('font-weight:600;font-size:13.5px')}>{l[0]}</div>
                  </div>
                  <span style={{ fontSize: '12px', fontWeight: 600, color: s.c }}>{l[1]}</span>
                </div>
              ))
            ) : (
              <div className="muted" style={sx('font-size:12px')}>
                Program neanunțat pentru această zi.
              </div>
            )}
          </div>
        </div>
      </div>

      <div className="dock">
        <button className="cta" onClick={() => showToast('Abonament adăugat')}>
          Cumpără abonament · de la {f.from} lei <Ic svg={I.arrow} />
        </button>
      </div>
    </div>
  );
}
