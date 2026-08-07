/* =========================================================
   SUB-ECRANELE DE PROFIL — port 1:1 din client-app.html:
     S.points    (993)  puncte & recompense
     S.invite   (1004)  afiliere: cod, invitatii, retea
     S.saved    (1017)  evenimente salvate
     S.prefsEdit(1035)  editarea intereselor
     S.reviews  (1099)  recenziile mele + de recenzat
     S.review   (1105)  formularul de recenzie (stele, taburi)
     S.notif    (1141)  notificari
   ========================================================= */
import { useState } from 'react';
import { Ic, Raw, cn, sx } from '../../../design/sx';
import {
  AFF,
  ART,
  ATTENDED,
  EV,
  I,
  MYREVIEWS,
  NOTI,
  PEMO,
  PREFGROUPS,
  REWARDS,
  VEN,
  money,
  poster,
} from '../../../mock/prototype';
import { BottomNav, SetHead, TopBar } from '../kit';
import { useNav } from '../nav';
import { useClient } from '../../../store/client';

type Ev = Record<string, any>;
const evOf = (id: string) => (EV as Record<string, Ev>)[id];

/* =========================================================
   S.points
   ========================================================= */
export function Points() {
  const { go } = useNav();
  const cur = useClient((s) => s.points);
  const showToast = useClient((s) => s.showToast);
  const next = 2000;
  const pct = Math.min(100, Math.round((cur / next) * 100));

  const earn: [string, string, string][] = [
    ['🎟', '1 leu cheltuit', '+1 punct'],
    ['🤝', 'Un prieten se înscrie cu codul tău', '+250 pct'],
    ['⭐', 'Lași o recenzie', '+50 pct'],
    ['🎂', 'De ziua ta', '+500 pct'],
  ];

  return (
    <div className="grid" style={sx('min-height:100%')}>
      <TopBar>
        <div className="row" style={sx('gap:12px')}>
          <div className="icon-btn" onClick={() => go('profile')}>
            <Ic svg={I.back} />
          </div>
          <div className="h2">Puncte & recompense</div>
        </div>
        <div className="icon-btn" onClick={() => go('invite')}>
          🤝
        </div>
      </TopBar>

      <div className="pad" style={sx('margin-top:14px')}>
        <div className="walletcard fade-up" style={sx('background:linear-gradient(140deg,#3a2a08,#b7791f 135%)')}>
          <div style={sx('position:absolute;right:-24px;top:-24px;width:150px;height:150px;border-radius:50%;background:rgba(255,255,255,.08)')} />
          <div className="between">
            <div>
              <div style={sx('font-size:11px;opacity:.8;font-weight:500')}>Puncte Tixello</div>
              <div style={sx('font-size:34px;font-weight:600;margin-top:2px')}>
                {cur} <span style={sx('font-size:15px;opacity:.7')}>pct</span>
              </div>
            </div>
            <div style={sx('width:44px;height:44px;border-radius:13px;background:rgba(255,255,255,.2);display:grid;place-items:center')}>
              <Ic svg={I.star} />
            </div>
          </div>
          <div style={sx('margin-top:18px')}>
            <div className="between" style={sx('font-size:11px;opacity:.85;margin-bottom:6px')}>
              <span>Nivel Silver</span>
              <span>{next - cur} pct până la Gold</span>
            </div>
            <div style={sx('height:7px;border-radius:9px;background:rgba(255,255,255,.22)')}>
              <div style={{ height: '100%', width: `${pct}%`, background: '#fff', borderRadius: 9 }} />
            </div>
          </div>
        </div>
      </div>

      <div className="pad" style={sx('margin-top:16px')}>
        <div className="h2" style={sx('font-size:15px;margin-bottom:10px')}>
          Cum câștigi puncte
        </div>
        <div style={sx('display:flex;flex-direction:column;gap:10px')}>
          {earn.map((r) => (
            <div key={r[1]} className="listitem">
              <div style={sx('width:38px;height:38px;border-radius:12px;background:var(--surface-3);display:grid;place-items:center;font-size:17px')}>
                {r[0]}
              </div>
              <div style={sx('flex:1;font-weight:500;font-size:13.5px')}>{r[1]}</div>
              <span className="pts">{r[2]}</span>
            </div>
          ))}
        </div>
      </div>

      <div className="pad" style={sx('margin-top:20px')}>
        <div className="h2" style={sx('font-size:15px;margin-bottom:10px')}>
          Recompense disponibile
        </div>
        <div style={sx('display:flex;flex-direction:column;gap:10px')}>
          {(REWARDS as [string, string, string, string][]).map((r) => {
            const can = r[3] === 'ok';
            return (
              <div
                key={r[1]}
                className="card"
                style={{ padding: 13, display: 'flex', alignItems: 'center', gap: 12, opacity: can ? undefined : 0.55 }}
              >
                <div style={sx('width:44px;height:44px;border-radius:13px;background:var(--surface-3);display:grid;place-items:center;font-size:20px')}>
                  {r[0]}
                </div>
                <div style={sx('flex:1')}>
                  <div style={sx('font-weight:600;font-size:13.5px')}>{r[1]}</div>
                  <div className="pts" style={sx('margin-top:4px')}>
                    <Ic svg={I.star} /> {r[2].replace('-', ' pct')}
                  </div>
                </div>
                <button
                  className={cn('chip', can && 'ind on')}
                  style={sx('padding:8px 14px')}
                  onClick={() => can && showToast('Recompensă activată')}
                >
                  {can ? 'Folosește' : '🔒'}
                </button>
              </div>
            );
          })}
        </div>
      </div>
      <div style={sx('height:10px')} />
      <BottomNav active="profile" />
    </div>
  );
}

/* =========================================================
   S.invite
   ========================================================= */
export function Invite() {
  const { back } = useNav();
  const showToast = useClient((s) => s.showToast);
  const aff = AFF as Ev;
  const [email, setEmail] = useState('');

  return (
    <div className="grid" style={sx('min-height:100%')}>
      <TopBar>
        <div className="row" style={sx('gap:12px')}>
          <div className="icon-btn" onClick={back}>
            <Ic svg={I.back} />
          </div>
          <div className="h2">Invită prieteni</div>
        </div>
        <div className="icon-btn">
          <Ic svg={I.info} />
        </div>
      </TopBar>

      <div className="pad" style={sx('margin-top:14px')}>
        <div
          className="card"
          style={sx('padding:20px;text-align:center;background:linear-gradient(160deg,var(--indigo-3),#2a1065);border:1px solid var(--line-2);color:#fff;position:relative;overflow:hidden')}
        >
          <div style={sx('font-size:18px;font-weight:600')}>Invită-ți prietenii 🎉</div>
          <div style={sx('font-size:12.5px;opacity:.85;margin-top:6px;line-height:1.5')}>
            Prietenul primește <b>10 lei</b> la prima comandă.
            <br />
            Iar rețeaua ta Tixello crește cu fiecare invitație.
          </div>
          <div
            style={sx('margin-top:16px;background:rgba(255,255,255,.14);border:1px dashed rgba(255,255,255,.4);border-radius:14px;padding:14px;display:flex;align-items:center;gap:12px')}
          >
            <div style={sx('flex:1;text-align:left')}>
              <div style={sx('font-size:10px;opacity:.75;font-weight:600')}>CODUL TĂU</div>
              <div style={sx('font-size:20px;font-weight:600;letter-spacing:1px')}>{aff.code}</div>
            </div>
            <button
              className="circ"
              onClick={() => showToast('Cod copiat: ' + aff.code)}
              style={sx('position:static;width:40px;height:40px;background:#fff;color:#141020;border-color:#fff')}
              aria-label="Copiază codul"
            >
              <Ic svg={I.copy} />
            </button>
          </div>
        </div>
      </div>

      <div className="pad" style={sx('margin-top:16px')}>
        <div className="label" style={sx('margin-bottom:8px')}>
          Trimite invitația pe email
        </div>
        <div className="field">
          <Ic svg={I.mail} />
          <input value={email} onChange={(e) => setEmail(e.target.value)} placeholder="email@prieten.ro" inputMode="email" />
        </div>
        <button
          className="cta"
          style={sx('margin-top:12px')}
          onClick={() => showToast(email ? 'Invitație trimisă la ' + email : 'Completează un email')}
        >
          <Ic svg={I.send} /> Trimite invitația
        </button>
      </div>

      <div className="pad" style={sx('margin-top:16px')}>
        <div className="row" style={sx('gap:10px')}>
          {[
            [aff.invited, 'Invitații trimise'],
            [aff.friends.length, 'Au acceptat'],
          ].map((s) => (
            <div key={String(s[1])} className="card" style={sx('flex:1;text-align:center;padding:15px')}>
              <div style={sx('font-size:22px;font-weight:600')}>{s[0]}</div>
              <div className="muted" style={sx('font-size:10px;font-weight:500;text-transform:uppercase;margin-top:3px')}>
                {s[1]}
              </div>
            </div>
          ))}
        </div>
      </div>

      <div className="pad" style={sx('margin-top:20px')}>
        <div className="h2" style={sx('font-size:15px;margin-bottom:10px')}>
          Prietenii tăi
        </div>
        <div style={sx('display:flex;flex-direction:column;gap:10px')}>
          {(aff.friends as [string, string, string][]).map((f) => (
            <div key={f[0]} className="listitem">
              <div style={sx('width:40px;height:40px;border-radius:13px;background:linear-gradient(135deg,var(--indigo-2),var(--indigo-4));display:grid;place-items:center;color:#fff;font-weight:600;font-size:12px')}>
                {f[1]}
              </div>
              <div style={sx('flex:1')}>
                <div style={sx('font-weight:600;font-size:13.5px')}>{f[0]}</div>
                <div className="muted" style={sx('font-size:11px')}>
                  {f[2]}
                </div>
              </div>
              <span className="badge" style={sx('background:var(--green-soft);color:var(--green-2)')}>
                prieten
              </span>
            </div>
          ))}
        </div>
      </div>

      <div className="pad" style={sx('margin-top:18px')}>
        <div className="between" style={sx('margin-bottom:10px')}>
          <div className="h2" style={sx('font-size:15px')}>
            Prietenii prietenilor
          </div>
          <span className="muted" style={sx('font-size:11px;font-weight:600')}>
            rețeaua ta extinsă
          </span>
        </div>
        <div style={sx('display:flex;flex-direction:column;gap:10px')}>
          {(aff.fof as [string, string, string][]).map((f) => (
            <div key={f[0]} className="listitem" style={sx('opacity:.9')}>
              <div style={sx('width:38px;height:38px;border-radius:12px;background:var(--surface-3);display:grid;place-items:center;color:var(--muted);font-weight:600;font-size:12px')}>
                {f[1]}
              </div>
              <div style={sx('flex:1')}>
                <div style={sx('font-weight:500;font-size:13px')}>{f[0]}</div>
                <div className="muted" style={sx('font-size:11px')}>
                  {f[2]}
                </div>
              </div>
            </div>
          ))}
        </div>
        <div className="muted" style={sx('font-size:11px;text-align:center;margin-top:12px;line-height:1.5')}>
          Când adaugi beneficiari la checkout sau cineva folosește codul tău, îi păstrăm în rețeaua ta. 💜
        </div>
      </div>
      <div style={sx('height:14px')} />
      <BottomNav active="profile" />
    </div>
  );
}

/* =========================================================
   S.saved
   ========================================================= */
export function Saved() {
  const { go, back, tab } = useNav();
  const saved = useClient((s) => s.saved);
  const toggleSaved = useClient((s) => s.toggleSaved);
  const list = saved.map((id) => evOf(id)).filter(Boolean);

  return (
    <div className="grid" style={sx('min-height:100%')}>
      <TopBar>
        <div className="row" style={sx('gap:12px')}>
          <div className="icon-btn" onClick={back}>
            <Ic svg={I.back} />
          </div>
          <div>
            <div className="h2">Salvate</div>
            <div className="muted" style={sx('font-size:11.5px')}>
              {list.length} evenimente & experiențe
            </div>
          </div>
        </div>
        <div className="icon-btn" onClick={() => go('search')}>
          <Ic svg={I.search} />
        </div>
      </TopBar>

      {list.length ? (
        <div className="pad" style={sx('margin-top:14px;display:flex;flex-direction:column;gap:12px')}>
          {list.map((ev) => (
            <div key={ev.id} className="card" style={sx('overflow:hidden;display:flex;gap:12px;padding:11px')}>
              <div
                onClick={() => go('event', { id: ev.id })}
                style={sx('display:flex;gap:12px;flex:1;cursor:pointer;min-width:0')}
              >
                <Raw html={poster(ev, '', 'width:74px;height:74px;border-radius:16px;flex:none', { tag: 1 })} />
                <div style={sx('flex:1;min-width:0')}>
                  <div style={sx('font-weight:600;font-size:14px')}>{ev.s}</div>
                  <div className="row muted" style={sx('gap:5px;font-size:11.5px;margin-top:3px')}>
                    <Ic svg={I.pin} /> {ev.city} · {ev.d}
                  </div>
                  <div
                    style={{
                      fontWeight: 600,
                      color: ev.type === 'experience' ? 'var(--green-2)' : 'var(--indigo-2)',
                      fontSize: '13.5px',
                      marginTop: '6px',
                    }}
                  >
                    de la {money(ev.from)} lei
                  </div>
                </div>
              </div>
              <button
                className="icon-btn"
                onClick={() => toggleSaved(ev.id)}
                style={sx('align-self:flex-start;color:var(--red)')}
              >
                <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
                  <path d="M12 21s-8-5-8-11a4.5 4.5 0 0 1 8-2.9A4.5 4.5 0 0 1 20 10c0 6-8 11-8 11z" />
                </svg>
              </button>
            </div>
          ))}
        </div>
      ) : (
        <div className="pad" style={sx('margin-top:60px;text-align:center')}>
          <div style={sx('font-size:46px;opacity:.5')}>🤍</div>
          <div style={sx('font-weight:600;font-size:15px;margin-top:10px')}>Nimic salvat încă</div>
          <div className="muted" style={sx('font-size:12.5px;margin-top:4px')}>
            Apasă ♥ la un eveniment ca să-l găsești aici.
          </div>
          <button className="cta" style={sx('width:auto;padding:12px 22px;margin:18px auto 0')} onClick={() => tab('explore')}>
            Explorează <Ic svg={I.arrow} />
          </button>
        </div>
      )}
      <div style={sx('height:10px')} />
      <BottomNav active="profile" />
    </div>
  );
}

/* =========================================================
   S.prefsEdit
   ========================================================= */
export function PrefsEdit() {
  const { back } = useNav();
  const prefsSel = useClient((s) => s.prefsSel);
  const togglePref = useClient((s) => s.togglePref);
  const showToast = useClient((s) => s.showToast);
  const pemo = PEMO as Record<string, string>;

  return (
    <div className="grid" style={sx('min-height:100%')}>
      <SetHead title="Preferințele mele" sub={`${prefsSel.length} interese active`} />
      <div className="pad" style={sx('margin-top:12px')}>
        <div className="muted" style={sx('font-size:12px;line-height:1.5')}>
          Ajustează-ți interesele — le folosim ca să-ți recomandăm exact ce ți se potrivește. 💜
        </div>
      </div>

      {(PREFGROUPS as Ev[]).map((g) => (
        <div key={g.t} className="pad" style={sx('margin-top:18px')}>
          <div className="row" style={sx('gap:8px;margin-bottom:10px')}>
            <span style={sx('font-size:16px')}>{g.ic}</span>
            <span className="h2" style={sx('font-size:14.5px')}>
              {g.t}
            </span>
          </div>
          <div className="prefwrap" style={sx('justify-content:flex-start;max-width:none;gap:9px')}>
            {(g.o as string[]).map((p) => (
              <button key={p} className={cn('pref', prefsSel.includes(p) && 'on')} onClick={() => togglePref(p)}>
                {pemo[p] ? pemo[p] + ' ' : ''}
                {p}
              </button>
            ))}
          </div>
        </div>
      ))}

      <div style={sx('height:14px')} />
      <div className="pad" style={sx('position:sticky;bottom:12px;margin-top:auto')}>
        <button
          className="cta"
          onClick={() => {
            showToast('Preferințe salvate');
            back();
          }}
        >
          Salvează preferințele
        </button>
      </div>
      <div style={sx('height:8px')} />
    </div>
  );
}

/* =========================================================
   S.reviews
   ========================================================= */
export function Reviews() {
  const { go, back } = useNav();
  const toReview = (ATTENDED as { ev: string; when: string; reviewed: boolean }[]).filter((a) => !a.reviewed);

  return (
    <div className="grid" style={sx('min-height:100%')}>
      <TopBar>
        <div className="row" style={sx('gap:12px')}>
          <div className="icon-btn" onClick={back}>
            <Ic svg={I.back} />
          </div>
          <div className="h2">Recenziile mele</div>
        </div>
        <div style={sx('width:42px')} />
      </TopBar>

      {toReview.length ? (
        <div className="pad" style={sx('margin-top:14px')}>
          <div className="label" style={sx('margin-bottom:9px')}>
            De recenzat · ai participat
          </div>
          <div style={sx('display:flex;flex-direction:column;gap:11px')}>
            {toReview.map((a) => {
              const ev = evOf(a.ev);
              return (
                <div key={a.ev} className="card" style={sx('padding:13px')}>
                  <div className="row" style={sx('gap:12px')}>
                    <Raw html={poster(ev, '', 'width:52px;height:52px;border-radius:14px;flex:none', undefined)} />
                    <div style={sx('flex:1')}>
                      <div style={sx('font-weight:600;font-size:13.5px')}>{ev.s}</div>
                      <div className="muted" style={sx('font-size:11.5px')}>
                        Ai participat · {a.when}
                      </div>
                    </div>
                  </div>
                  <button className="cta" onClick={() => go('review', { id: a.ev })} style={sx('margin-top:11px;padding:12px')}>
                    <Ic svg={I.star} /> Lasă o recenzie
                  </button>
                </div>
              );
            })}
          </div>
        </div>
      ) : null}

      <div className="pad" style={sx('margin-top:18px')}>
        <div className="label" style={sx('margin-bottom:9px')}>
          Recenziile tale
        </div>
        <div style={sx('display:flex;flex-direction:column;gap:11px')}>
          {(MYREVIEWS as Ev[]).map((r, i) => {
            const ev = evOf(r.ev);
            return (
              <div key={i} className="card" style={sx('padding:14px')}>
                <div className="between">
                  <div className="row" style={sx('gap:11px')}>
                    <Raw html={poster(ev, '', 'width:44px;height:44px;border-radius:12px;flex:none', undefined)} />
                    <div>
                      <div style={sx('font-weight:600;font-size:13.5px')}>{ev.s}</div>
                      <div className="muted" style={sx('font-size:10.5px')}>
                        {r.target} · {r.when}
                      </div>
                    </div>
                  </div>
                  <div style={sx('color:var(--amber);font-size:12px;letter-spacing:1px')}>
                    {'★'.repeat(r.rating)}
                    {'☆'.repeat(5 - r.rating)}
                  </div>
                </div>
                <p className="muted" style={sx('font-size:12.5px;line-height:1.5;margin-top:10px')}>
                  {r.txt}
                </p>
              </div>
            );
          })}
        </div>
      </div>
      <div style={sx('height:12px')} />
      <BottomNav active="profile" />
    </div>
  );
}

/* =========================================================
   S.review
   ========================================================= */
const RATING_LABELS = ['', 'Slab', 'Ok', 'Bun', 'Foarte bun', 'Excelent!'];

export function Review({ id }: { id?: string }) {
  const { back } = useNav();
  const showToast = useClient((s) => s.showToast);
  const revRating = useClient((s) => s.revRating);
  const revTab = useClient((s) => s.revTab);
  const ev = evOf(id || 'coldplay') || evOf('coldplay');
  const v = (VEN as Record<string, Ev>)[ev.ven];
  const tabs: [string, string][] = [
    ['Eveniment', ev.s],
    ['Artist', ev.artists && ev.artists[0] ? (ART as Record<string, Ev>)[ev.artists[0]].name : 'Artist'],
    ['Locație', v.name],
  ];
  const ti = revTab || 0;

  return (
    <div className="grid" style={sx('min-height:100%')}>
      <TopBar>
        <div className="row" style={sx('gap:12px')}>
          <div className="icon-btn" onClick={back}>
            <Ic svg={I.back} />
          </div>
          <div className="h2">Lasă o recenzie</div>
        </div>
        <div style={sx('width:42px')} />
      </TopBar>

      <div className="pad" style={sx('margin-top:14px')}>
        <div className="card" style={sx('padding:13px;display:flex;gap:12px;align-items:center')}>
          <Raw html={poster(ev, '', 'width:52px;height:52px;border-radius:14px;flex:none', undefined)} />
          <div style={sx('flex:1')}>
            <div style={sx('font-weight:600;font-size:14px')}>{ev.s}</div>
            <div className="muted" style={sx('font-size:11.5px')}>
              {ev.d} · {v.name}
            </div>
          </div>
        </div>
      </div>

      <div className="pad" style={sx('margin-top:14px')}>
        <div className="label" style={sx('margin-bottom:8px')}>
          Ce evaluezi?
        </div>
        <div className="scroll-x" style={sx('padding:0')}>
          {tabs.map((t, i) => (
            <button
              key={t[0]}
              className={cn('chip', i === ti && 'ind on')}
              onClick={() => useClient.setState({ revTab: i })}
            >
              {['🎫', '🎤', '📍'][i]} {t[0]}
            </button>
          ))}
        </div>
      </div>

      <div className="pad" style={sx('margin-top:18px;text-align:center')}>
        <div className="muted" style={sx('font-size:12.5px')}>
          Cât de mult ți-a plăcut <b style={sx('color:var(--ink)')}>{tabs[ti][1]}</b>?
        </div>
        <div className="row" style={sx('justify-content:center;gap:8px;margin-top:14px')}>
          {[1, 2, 3, 4, 5].map((n) => (
            <button
              key={n}
              onClick={() => useClient.setState({ revRating: n })}
              style={{
                background: 'none',
                border: 0,
                cursor: 'pointer',
                fontSize: '38px',
                lineHeight: 1,
                color: n <= revRating ? 'var(--amber)' : 'var(--surface-3)',
              }}
            >
              ★
            </button>
          ))}
        </div>
        <div className="muted" style={sx('font-size:12px;margin-top:8px;height:16px')}>
          {RATING_LABELS[revRating] || 'Atinge o stea'}
        </div>
      </div>

      <div className="pad" style={sx('margin-top:12px')}>
        <div className="label" style={sx('margin-bottom:7px')}>
          Spune-ne mai multe (opțional)
        </div>
        <div className="field" style={sx('align-items:flex-start')}>
          <textarea
            rows={4}
            placeholder="Ce ți-a plăcut? Ce ar putea fi mai bun?"
            style={sx('border:0;outline:0;font:inherit;font-size:14px;font-weight:500;color:var(--ink);background:transparent;width:100%;resize:none')}
          />
        </div>
      </div>

      <div className="pad" style={sx('margin-top:12px')}>
        <div className="pts" style={sx('font-size:11.5px')}>
          <Ic svg={I.star} /> Primești +50 puncte pentru recenzie
        </div>
      </div>

      <div className="dock">
        <button
          className="cta"
          onClick={() => {
            useClient.setState((s) => ({ points: s.points + 50 }));
            showToast('Mulțumim pentru recenzie! +50 puncte');
            back();
          }}
        >
          Trimite recenzia <Ic svg={I.arrow} />
        </button>
      </div>
    </div>
  );
}

/* =========================================================
   S.notif
   ========================================================= */
export function Notif() {
  const { back } = useNav();
  return (
    <div className="grid" style={sx('min-height:100%')}>
      <TopBar>
        <div className="row" style={sx('gap:12px')}>
          <div className="icon-btn" onClick={back}>
            <Ic svg={I.back} />
          </div>
          <div className="h2">Notificări</div>
        </div>
        <span style={sx('color:var(--indigo-2);font-size:12.5px;font-weight:500')}>Citește tot</span>
      </TopBar>

      <div className="pad" style={sx('margin-top:14px;display:flex;flex-direction:column;gap:10px')}>
        {(NOTI as [string, string, string, string, number][]).map((n) => (
          <div
            key={n[1]}
            className="listitem"
            style={n[4] ? { background: 'var(--indigo-soft)', borderColor: 'var(--indigo-line)' } : undefined}
          >
            <div style={sx('width:42px;height:42px;border-radius:13px;background:var(--surface-3);display:grid;place-items:center;font-size:19px')}>
              {n[0]}
            </div>
            <div style={sx('flex:1')}>
              <div style={sx('font-weight:600;font-size:13.5px')}>{n[1]}</div>
              <div className="muted" style={sx('font-size:11.5px;margin-top:1px')}>
                {n[2]}
              </div>
              <div style={sx('font-size:10.5px;color:var(--faint);margin-top:3px')}>{n[3]}</div>
            </div>
            {n[4] ? <span style={sx('width:8px;height:8px;border-radius:50%;background:var(--indigo)')} /> : null}
          </div>
        ))}
      </div>
      <BottomNav active="" />
    </div>
  );
}
