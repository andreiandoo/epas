/* =========================================================
   AUTH — port 1:1 din client-app.html:
     S.splash    (533) + INIT.splash (auto-avans la 2200ms, tap = skip)
     S.onboarding(557) + INIT.onboarding (cautare in lista de preferinte)
     S.login     (576) · S.register (589) · S.forgot (604)

   INTEGRARE cu §3: prototipul de client duce direct in Acasa dupa login,
   pentru ca nu stie de conturi de organizator. Aici, "Autentificare" apeleaza
   session.login(), care decide singur: >1 proprietate -> pasul "Alege contul",
   ==1 -> intrare directa. Vizualul ramane cel din prototip.
   ========================================================= */
import { useEffect, useRef, useState } from 'react';
import { Ic, Raw, cn, sx } from '../../../design/sx';
import { I, OBALL, PEMO, PREFGROUPS, facebook, google, txMark } from '../../../mock/prototype';
import { SafeTop } from '../kit';
import { useClient } from '../../../store/client';
import { useSession } from '../../../store/session';
import type { IdentityKind } from '../../../api/client';

type Ev = Record<string, any>;

/** In demo, forma emailului decide ce proprietati are contul (ca in LoginScreen). */
const identityFor = (mail: string): IdentityKind => {
  const m = mail.trim().toLowerCase();
  if (m.startsWith('client')) return 'clientonly';
  if (m.startsWith('operator') || m.startsWith('org')) return 'orgonly';
  return 'multi';
};

/* =========================================================
   S.splash + INIT.splash
   ========================================================= */
export function Splash({ onDone }: { onDone: () => void }) {
  useEffect(() => {
    const reduced = matchMedia('(prefers-reduced-motion: reduce)').matches;
    const t = setTimeout(onDone, reduced ? 600 : 2200);
    return () => clearTimeout(t);
  }, [onDone]);

  return (
    <div className="splash" onClick={onDone}>
      <div className="gridfx" />
      <div className="mesh" />
      <span className="spark" style={sx('top:20%;left:18%')}>✦</span>
      <span className="spark" style={sx('top:26%;right:16%;animation-delay:.7s')}>✧</span>
      <span className="spark" style={sx('bottom:28%;left:24%;animation-delay:1.2s')}>✦</span>
      <span className="spark" style={sx('bottom:22%;right:22%;animation-delay:1.8s')}>✧</span>
      <div className="core">
        <div className="mark">
          <Raw html={txMark('#fff', 52)} />
        </div>
        <div className="wm">tixello</div>
        <div className="tl">Evenimente, experiențe & cashless.</div>
      </div>
      <div className="prog">
        <i />
      </div>
    </div>
  );
}

/* =========================================================
   S.onboarding + INIT.onboarding
   ========================================================= */
export function Onboarding({ onDone }: { onDone: () => void }) {
  const obStep = useClient((s) => s.obStep);
  const setObStep = useClient((s) => s.setObStep);
  const prefsSel = useClient((s) => s.prefsSel);
  const togglePref = useClient((s) => s.togglePref);
  const [q, setQ] = useState('');
  const [dir, setDir] = useState<'fwd' | 'back'>('fwd');

  const all = OBALL as Ev[];
  const i = Math.min(obStep, all.length - 1);
  const o = all[i];
  const g = o.pref ? ((PREFGROUPS as Ev[])[o.group] as Ev) : null;
  const last = all.length - 1;
  const pemo = PEMO as Record<string, string>;

  /** obSelCount(group) din prototip */
  const selCount = g ? (g.o as string[]).filter((p) => prefsSel.includes(p)).length : 0;

  const next = () => {
    setQ('');
    setDir('fwd');
    if (i === last) onDone();
    else setObStep(i + 1);
  };
  const back = () => {
    setQ('');
    setDir('back');
    setObStep(Math.max(0, i - 1));
  };

  const v = q.trim().toLowerCase();

  return (
    <div className="ob grid">
      <SafeTop />
      <div className="between pad" style={sx('padding-top:4px')}>
        <div className="row" style={sx('gap:6px;font-weight:600;font-size:16px;color:var(--ink)')}>
          <Raw html={txMark('var(--indigo-2)', 18)} /> tixello
        </div>
        <button className="chip" onClick={onDone} style={sx('border:0;background:transparent;color:var(--muted)')}>
          Sari peste
        </button>
      </div>

      <div className="seg">
        {all.map((_, k) => (
          <i key={k} className={k <= i ? 'on' : ''} />
        ))}
      </div>

      <div
        className={cn('body', dir === 'back' ? 'obb' : 'obf')}
        style={o.pref ? sx('justify-content:flex-start;overflow-y:auto;text-align:center;gap:0;padding-top:18px') : undefined}
      >
        {o.pref && g ? (
          <div style={sx('width:100%')}>
            <div style={sx('width:64px;height:64px;border-radius:20px;margin:0 auto;background:var(--indigo-soft);border:1px solid var(--indigo-line);display:grid;place-items:center;font-size:30px')}>
              {g.ic}
            </div>
            <h2 style={sx('margin:14px auto 0')}>{g.h}</h2>
            <p style={sx('margin:8px auto 0')}>{g.p}</p>
            <div className="label" style={sx('text-align:left;margin:20px 2px 9px')}>
              {g.t}
              {selCount ? (
                <>
                  {' · '}
                  <span style={sx('color:var(--indigo-2)')}>{selCount} alese</span>
                </>
              ) : null}
            </div>
            {g.search ? (
              <div className="field" style={sx('margin-bottom:12px')}>
                <Ic svg={I.search} />
                <input value={q} onChange={(e) => setQ(e.target.value)} placeholder={g.search} autoComplete="off" />
              </div>
            ) : null}
            <div className="prefwrap" style={sx('justify-content:flex-start;max-width:none;gap:10px')}>
              {(g.o as string[])
                .filter((p) => !v || p.toLowerCase().includes(v))
                .map((p) => (
                  <button key={p} className={cn('pref', prefsSel.includes(p) && 'on')} onClick={() => togglePref(p)}>
                    {pemo[p] ? pemo[p] + ' ' : ''}
                    {p}
                  </button>
                ))}
            </div>
            <div className="muted" style={sx('font-size:11px;margin-top:16px')}>
              🔒 Le poți schimba oricând din profil.
            </div>
            <div style={sx('height:10px')} />
          </div>
        ) : (
          <>
            <Raw html={o.art} />
            <h2>{o.h}</h2>
            <p>{o.p}</p>
          </>
        )}
      </div>

      <div className="foot">
        <button className="cta" onClick={next}>
          {i === last ? 'Începe — sunt gata 🎉' : 'Continuă'} <Ic svg={I.arrow} />
        </button>
        {o.pref ? (
          <button className="cta ghost" onClick={back} style={sx('padding:13px')}>
            Înapoi
          </button>
        ) : null}
      </div>
    </div>
  );
}

/* ---------- iconita de lacat, folosita la parola (inline in prototip) ---------- */
const LockIcon = () => (
  <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="var(--muted)" strokeWidth="2" strokeLinecap="round">
    <rect x="4" y="11" width="16" height="10" rx="2" />
    <path d="M8 11V7a4 4 0 0 1 8 0v4" />
  </svg>
);

/* =========================================================
   S.login
   ========================================================= */
export function Login({ onForgot, onRegister }: { onForgot: () => void; onRegister: () => void }) {
  const login = useSession((s) => s.login);
  const [email, setEmail] = useState('andrei@tixello.ro');
  const [pass, setPass] = useState('password');

  /* §3: nu mergem direct in Acasa — session.login() alege chooser vs intrare directa */
  const submit = () => login(identityFor(email));

  return (
    <div style={sx('min-height:100%;background:var(--bg);padding-bottom:26px')}>
      <div className="login-top">
        <div className="gridfx" />
        <div className="mesh" />
        <SafeTop />
        <div style={sx('position:relative;padding:20px 24px 0')}>
          <div style={sx('width:50px;height:50px;border-radius:16px;background:rgba(255,255,255,.16);border:1px solid rgba(255,255,255,.3);display:grid;place-items:center;backdrop-filter:blur(8px)')}>
            <Raw html={txMark('#fff', 28)} />
          </div>
          <div style={sx('font-size:25px;font-weight:600;letter-spacing:-.03em;margin-top:14px')}>Bine ai revenit 👋</div>
          <div style={sx('font-size:13.5px;color:rgba(255,255,255,.82);margin-top:4px')}>
            Intră în cont și continuă distracția.
          </div>
        </div>
      </div>

      <div className="login-sheet stagger">
        <div className="row" style={sx('gap:11px')}>
          <button className="sbtn" onClick={submit}>
            <Raw html={google} /> Google
          </button>
          <button className="sbtn" onClick={submit}>
            <Raw html={facebook} /> Facebook
          </button>
        </div>
        <div className="divider">
          <span />
          sau cu email
          <span />
        </div>
        <div>
          <div className="label">Email</div>
          <div className="field">
            <Ic svg={I.user} />
            <input value={email} onChange={(e) => setEmail(e.target.value)} placeholder="nume@email.ro" />
          </div>
        </div>
        <div style={sx('margin-top:12px')}>
          <div className="label">Parolă</div>
          <div className="field">
            <LockIcon />
            <input type="password" value={pass} onChange={(e) => setPass(e.target.value)} placeholder="••••••••" />
          </div>
        </div>
        <div className="between" style={sx('margin:12px 2px 0;font-size:12.5px;font-weight:500')}>
          <span className="muted">Ține-mă minte</span>
          <span style={sx('color:var(--indigo-2);cursor:pointer')} onClick={onForgot}>
            Ai uitat parola?
          </span>
        </div>
        <button className="cta" onClick={submit} style={sx('margin-top:16px')}>
          Autentificare
        </button>
      </div>

      <div style={sx('text-align:center;font-size:13px;font-weight:500;color:var(--muted);margin-top:16px')}>
        Nu ai cont?{' '}
        <span style={sx('color:var(--indigo-2);cursor:pointer;font-weight:600')} onClick={onRegister}>
          Creează cont
        </span>
      </div>
    </div>
  );
}

/* =========================================================
   S.register
   ========================================================= */
export function Register({ onBack }: { onBack: () => void }) {
  const login = useSession((s) => s.login);
  const [email, setEmail] = useState('');
  const submit = () => login(identityFor(email || 'andrei@tixello.ro'));

  return (
    <div style={sx('min-height:100%;background:var(--bg);padding-bottom:26px')}>
      {/* Hero aliniat cu Login: butonul de back sta exact unde sta patratul cu
          logo pe Login (padding:20px 24px 0, 50x50). Fara "tixello" in mijloc —
          in prototip acolo era nevoie de un reper, pentru ca bara de status
          simulata ocupa randul de deasupra. */}
      <div className="login-top" style={sx('height:224px')}>
        <div className="gridfx" />
        <div className="mesh" />
        <SafeTop />
        <div style={sx('position:relative;padding:20px 24px 0')}>
          <div
            className="icon-btn glass"
            onClick={onBack}
            style={sx('width:50px;height:50px;border-radius:16px')}
          >
            <Ic svg={I.back} />
          </div>
          <div style={sx('font-size:25px;font-weight:600;letter-spacing:-.03em;margin-top:14px')}>
            Creează-ți contul 🎉
          </div>
          <div style={sx('font-size:13px;color:rgba(255,255,255,.82);margin-top:5px')}>
            Un cont, toate biletele și portofelul cashless.
          </div>
        </div>
      </div>

      <div className="login-sheet stagger">
        <div className="row" style={sx('gap:11px')}>
          <button className="sbtn" onClick={submit}>
            <Raw html={google} /> Google
          </button>
          <button className="sbtn" onClick={submit}>
            <Raw html={facebook} /> Facebook
          </button>
        </div>
        <div className="divider">
          <span />
          sau cu email
          <span />
        </div>
        <div>
          <div className="label">Nume complet</div>
          <div className="field">
            <Ic svg={I.user} />
            <input placeholder="Andrei Popescu" />
          </div>
        </div>
        <div style={sx('margin-top:12px')}>
          <div className="label">Email</div>
          <div className="field">
            <Ic svg={I.mail} />
            <input value={email} onChange={(e) => setEmail(e.target.value)} placeholder="nume@email.ro" inputMode="email" />
          </div>
        </div>
        <div style={sx('margin-top:12px')}>
          <div className="label">Telefon</div>
          <div className="field">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="var(--muted)" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
              <path d="M4 4h4l2 5-3 2a12 12 0 0 0 6 6l2-3 5 2v4a2 2 0 0 1-2 2A17 17 0 0 1 2 6a2 2 0 0 1 2-2" />
            </svg>
            <input placeholder="07xx xxx xxx" inputMode="tel" />
          </div>
        </div>
        <div style={sx('margin-top:12px')}>
          <div className="label">Parolă</div>
          <div className="field">
            <LockIcon />
            <input type="password" placeholder="Minim 8 caractere" />
          </div>
          <div className="muted" style={sx('font-size:10.5px;margin-top:6px')}>
            Folosește litere, cifre și un simbol pentru un cont sigur.
          </div>
        </div>
        <label className="row" style={sx('gap:9px;margin-top:14px;cursor:pointer')}>
          <span className="toggle on" style={sx('pointer-events:none')} />
          <span className="muted" style={sx('font-size:11.5px;flex:1')}>
            Sunt de acord cu Termenii și Politica de confidențialitate.
          </span>
        </label>
        <button className="cta" onClick={submit} style={sx('margin-top:16px')}>
          Creează cont <Ic svg={I.arrow} />
        </button>
      </div>

      <div style={sx('text-align:center;font-size:13px;font-weight:500;color:var(--muted);margin-top:16px')}>
        Ai deja cont?{' '}
        <span style={sx('color:var(--indigo-2);cursor:pointer;font-weight:600')} onClick={onBack}>
          Autentifică-te
        </span>
      </div>
    </div>
  );
}

/* =========================================================
   S.forgot
   ========================================================= */
export function Forgot({ onBack }: { onBack: () => void }) {
  const showToast = useClient((s) => s.showToast);
  const [email, setEmail] = useState('andrei@tixello.ro');
  const ref = useRef<HTMLInputElement>(null);

  return (
    <div style={sx('min-height:100%;background:var(--bg);padding-bottom:26px')}>
      {/* Acelasi hero ca la Inregistrare: back-ul in pozitia logo-ului de pe Login. */}
      <div className="login-top" style={sx('height:180px')}>
        <div className="gridfx" />
        <div className="mesh" />
        <SafeTop />
        <div style={sx('position:relative;padding:20px 24px 0')}>
          <div
            className="icon-btn glass"
            onClick={onBack}
            style={sx('width:50px;height:50px;border-radius:16px')}
          >
            <Ic svg={I.back} />
          </div>
          <div style={sx('font-size:24px;font-weight:600;letter-spacing:-.03em;margin-top:14px')}>
            Ți-ai uitat parola? 🔑
          </div>
          <div style={sx('font-size:13px;color:rgba(255,255,255,.82);margin-top:4px')}>
            Îți trimitem un link de resetare pe email.
          </div>
        </div>
      </div>

      <div className="login-sheet stagger">
        <div className="listitem" style={sx('background:var(--indigo-soft);border-color:var(--indigo-line)')}>
          <div className="iconbadge" style={sx('width:40px;height:40px;background:var(--indigo-soft);color:var(--indigo-2)')}>
            <Ic svg={I.mail} />
          </div>
          <div className="muted" style={sx('font-size:11.5px;flex:1')}>
            Scrie adresa contului tău și verifică-ți inboxul (și folderul Spam).
          </div>
        </div>
        <div style={sx('margin-top:16px')}>
          <div className="label">Email</div>
          <div className="field">
            <Ic svg={I.mail} />
            <input ref={ref} value={email} onChange={(e) => setEmail(e.target.value)} placeholder="nume@email.ro" inputMode="email" />
          </div>
        </div>
        <button
          className="cta"
          style={sx('margin-top:16px')}
          onClick={() => {
            showToast('Link trimis pe ' + email);
            onBack();
          }}
        >
          Trimite linkul <Ic svg={I.arrow} />
        </button>
        <button className="cta ghost" onClick={onBack} style={sx('margin-top:11px')}>
          Înapoi la autentificare
        </button>
      </div>
    </div>
  );
}
