/* =========================================================
   PORTOFEL — port 1:1 al lui S.wallet() (client-app.html, linia 974),
   plus S.topup() si S.payqr() + INIT.payqr, care fac parte din acelasi flux.
   ========================================================= */
import { Ic, sx } from '../../../design/sx';
import { I, TX, lei } from '../../../mock/prototype';
import { BottomNav, TopBar } from '../kit';
import { useNav } from '../nav';
import { useClient } from '../../../store/client';
import { Qr } from '../qr';

/* ---------- S.wallet ---------- */
export function Wallet() {
  const { go, back, tab, stack } = useNav();
  const balance = useClient((s) => s.balance);
  const points = useClient((s) => s.points);
  const sback = () => (stack.length > 1 ? back() : tab('home'));

  return (
    <div className="grid" style={sx('min-height:100%;padding-bottom:6px')}>
      <div className="stickytop">
        <div className="hrow">
          <div className="row" style={sx('gap:12px;min-width:0')}>
            <div className="icon-btn" onClick={sback}>
              <Ic svg={I.back} />
            </div>
            <div>
              <div className="eyebrow">Cashless</div>
              <h1 className="h1" style={sx('font-size:23px;margin-top:2px')}>
                Portofel
              </h1>
            </div>
          </div>
          <div className="icon-btn" onClick={() => go('notif')}>
            <Ic svg={I.bell} />
          </div>
        </div>
      </div>

      <div className="pad" style={sx('margin-top:14px')}>
        <div className="walletcard fade-up">
          <div
            style={sx('position:absolute;right:-24px;top:-24px;width:150px;height:150px;border-radius:50%;background:rgba(255,255,255,.06)')}
          />
          <div className="between">
            <div>
              <div style={sx('font-size:11px;opacity:.72;font-weight:500')}>Sold disponibil</div>
              <div style={sx('font-size:32px;font-weight:600;letter-spacing:-.02em;margin-top:3px')}>
                {lei(balance)} <span style={sx('font-size:16px;opacity:.7')}>lei</span>
              </div>
            </div>
            <div style={sx('width:38px;height:28px;border-radius:7px;background:linear-gradient(135deg,#f4d27a,#c99a3e)')} />
          </div>
          <div className="between" style={sx('margin-top:20px')}>
            <div>
              <div style={sx('font-size:10.5px;opacity:.66')}>Brățară · Nordvale Festival</div>
              <div style={sx('font-size:12.5px;font-weight:500;letter-spacing:1px;margin-top:1px')}>NV ·· 8842 ·· 7A</div>
            </div>
            <div style={sx('width:40px;height:40px;border-radius:11px;background:#fff;display:grid;place-items:center;color:#141020')}>
              <Ic svg={I.qr} />
            </div>
          </div>
        </div>
      </div>

      <div className="pad" style={sx('margin-top:14px')}>
        <div className="row" style={sx('gap:11px')}>
          <button className="cta" onClick={() => go('topup')} style={sx('flex:1;padding:14px')}>
            <Ic svg={I.plus} /> Încarcă
          </button>
          <button className="cta ghost" onClick={() => go('payqr')} style={sx('flex:1;padding:14px')}>
            <Ic svg={I.qr} /> Plătește
          </button>
        </div>
      </div>

      <div className="pad" style={sx('margin-top:14px')}>
        <div
          className="card"
          style={sx('padding:14px;display:flex;align-items:center;gap:13px;background:linear-gradient(135deg,var(--amber-soft),var(--surface-solid))')}
        >
          <div style={sx('width:44px;height:44px;border-radius:13px;background:var(--amber);display:grid;place-items:center;color:#111')}>
            <Ic svg={I.star} />
          </div>
          <div style={sx('flex:1')}>
            <div style={sx('font-weight:600;font-size:14px')}>{points} puncte Tixello</div>
            <div className="muted" style={sx('font-size:11.5px')}>
              Convertești în reduceri la bilete
            </div>
          </div>
          <button className="chip ind on" style={sx('padding:8px 14px')} onClick={() => go('points')}>
            Folosește
          </button>
        </div>
      </div>

      <div className="between pad" style={sx('margin-top:20px')}>
        <div className="h2" style={sx('font-size:15px')}>
          Tranzacții
        </div>
        <span className="muted" style={sx('font-size:12px;font-weight:500')}>
          Toate
        </span>
      </div>

      <div className="pad" style={sx('margin-top:8px')}>
        <div className="card" style={sx('padding:4px 14px')}>
          {(TX as [string, string, string, number][]).map((t) => (
            <div className="txrow" key={t[0] + t[1]}>
              <div
                style={{
                  width: 40,
                  height: 40,
                  borderRadius: 13,
                  display: 'grid',
                  placeItems: 'center',
                  flex: 'none',
                  background: t[3] ? 'var(--green-soft)' : 'var(--indigo-soft)',
                  color: t[3] ? 'var(--green-2)' : 'var(--indigo-2)',
                }}
              >
                <Ic svg={t[3] ? I.plus : I.wallet} />
              </div>
              <div style={sx('flex:1')}>
                <div style={sx('font-weight:500;font-size:13.5px')}>{t[0]}</div>
                <div className="muted" style={sx('font-size:11.5px')}>
                  {t[1]}
                </div>
              </div>
              <div
                className="tnum"
                style={{ fontWeight: 600, fontSize: '13.5px', color: t[3] ? 'var(--green-2)' : 'var(--ink)' }}
              >
                {t[2]} lei
              </div>
            </div>
          ))}
        </div>
      </div>

      <BottomNav active="wallet" />
    </div>
  );
}

/* ---------- S.topup ---------- */
const AMOUNTS = ['50', '100', '200', '300', '500', 'Alta'];

export function Topup() {
  const { back } = useNav();
  const balance = useClient((s) => s.balance);
  const showToast = useClient((s) => s.showToast);

  return (
    <div className="grid" style={sx('min-height:100%')}>
      <TopBar>
        <div className="icon-btn" onClick={back}>
          <Ic svg={I.back} />
        </div>
        <div className="h2">Încarcă portofelul</div>
        <div style={sx('width:42px')} />
      </TopBar>

      <div className="pad" style={sx('margin-top:16px')}>
        <div className="muted" style={sx('font-size:12.5px;font-weight:500;text-align:center')}>
          Sold curent
        </div>
        <div style={sx('text-align:center;font-size:34px;font-weight:600;margin-top:2px')}>{lei(balance)} lei</div>

        <div className="h2" style={sx('font-size:14px;margin-top:22px;margin-bottom:11px')}>
          Alege suma
        </div>
        <div style={sx('display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px')}>
          {AMOUNTS.map((v, i) => (
            <button
              key={v}
              className={`chip ${i === 2 ? 'ind on' : ''}`}
              style={sx('padding:16px 0;justify-content:center;font-size:15px;border-radius:16px')}
            >
              {v === 'Alta' ? v : v + ' lei'}
            </button>
          ))}
        </div>

        <div className="card" style={sx('margin-top:18px;padding:13px;display:flex;align-items:center;gap:12px')}>
          <div
            style={sx('width:44px;height:30px;border-radius:7px;background:#1a1f71;color:#fff;display:grid;place-items:center;font-weight:600;font-size:11px;font-style:italic')}
          >
            VISA
          </div>
          <div style={sx('flex:1')}>
            <div style={sx('font-weight:500;font-size:13px')}>•••• 8756</div>
            <div className="muted" style={sx('font-size:11px')}>
              Card primar
            </div>
          </div>
          <span className="muted">Schimbă ›</span>
        </div>
      </div>

      <div className="dock">
        <button
          className="cta"
          onClick={() => {
            back();
            showToast('Portofel încărcat cu 200 lei');
          }}
        >
          Încarcă 200 lei
        </button>
      </div>
    </div>
  );
}

/* ---------- S.payqr + INIT.payqr ---------- */
export function PayQr() {
  const { back } = useNav();
  const balance = useClient((s) => s.balance);

  return (
    <div style={sx('min-height:100%;background:var(--bg);display:flex;flex-direction:column')}>
      <TopBar>
        <div className="icon-btn" onClick={back}>
          <Ic svg={I.back} />
        </div>
        <div className="h2">Plătește cashless</div>
        <div className="icon-btn">
          <Ic svg={I.info} />
        </div>
      </TopBar>

      <div
        style={sx('flex:1;display:flex;flex-direction:column;align-items:center;justify-content:center;padding:26px;gap:18px')}
      >
        <div style={sx('text-align:center')}>
          <div className="muted" style={sx('font-size:12px;font-weight:500')}>
            Sold disponibil
          </div>
          <div style={sx('font-size:26px;font-weight:600')}>{lei(balance)} lei</div>
        </div>
        <div className="qr-wrap">
          <Qr seed={23} size={220} style={{ width: 220, height: 220 }} />
        </div>
        <p className="muted" style={sx('text-align:center;font-size:13px;max-width:26ch')}>
          Arată codul la orice bar sau food-truck din festival. Se scanează și se scade din sold instant.
        </p>
        <div className="row" style={sx('gap:8px')}>
          <span className="badge" style={sx('background:var(--surface-2)')}>
            Brățară activă
          </span>
          <span className="badge" style={sx('background:var(--surface-2)')}>
            Reîmprospătare 30s
          </span>
        </div>
      </div>
    </div>
  );
}
