/* =========================================================
   PROFIL — port 1:1 al lui S.profile() (client-app.html, linia 987).

   SINGURA ADAUGARE fata de prototip: randul "Comută la Organizator".
   Prototipul de client e o aplicatie de sine statatoare si nu stie de conturi
   de organizator, dar §3 din ghid cere explicit un punct de comutare in Profil
   ("buton «Schimbă contul» în Profil"). E randat cu clasa .listitem a
   prototipului, deci arata nativ, si apare DOAR daca emailul are si
   proprietati de organizator.
   ========================================================= */
import { useEffect, useState } from 'react';
import { Ic, sx } from '../../../design/sx';
import { AFF, I } from '../../../mock/prototype';
import { BottomNav } from '../kit';
import { useNav } from '../nav';
import { useClient } from '../../../store/client';
import { useSession } from '../../../store/session';
import { applyPendingUpdate, checkForUpdate, getOtaState, onOtaChange, type OtaState } from '../../../ota';

/** [emoji, eticheta, actiune] — exact lista din prototip. */
const MENU: [string, string, string][] = [
  ['🎟', 'Biletele mele', 'tab:tickets'],
  ['💳', 'Portofel & carduri', 'tab:wallet'],
  ['⭐', 'Puncte & recompense', 'go:points'],
  ['📝', 'Recenziile mele', 'go:reviews'],
  ['🎯', 'Preferințele mele', 'prefsEdit'],
  ['❤️', 'Salvate', 'go:saved'],
  ['🔔', 'Notificări', 'go:notif'],
  ['⚙️', 'Setări cont', 'go:settings'],
  ['↩︎', 'Deconectare', 'logout'],
];

/**
 * Rand de actualizari — NU exista in prototip, dar e necesar operational:
 * fara el nu se poate vedea ce versiune de continut ruleaza pe telefon si nu
 * se poate forta verificarea. Randat cu .listitem, ca sa arate nativ.
 */
function UpdateRow() {
  const showToast = useClient((s) => s.showToast);
  const [ota, setOta] = useState<OtaState>(getOtaState);
  const [busy, setBusy] = useState(false);

  useEffect(() => onOtaChange(setOta), []);

  const label = ota.pending
    ? `Versiunea ${ota.pending} e gata — atinge pentru repornire`
    : ota.downloading
      ? `Se descarcă… ${Math.round(ota.progress)}%`
      : busy
        ? 'Se verifică…'
        : 'Verifică actualizările';

  const sub = `conținut ${ota.current === 'builtin' ? 'inclus în APK' : ota.current}${ota.native ? ` · aplicație ${ota.native}` : ''}`;

  return (
    <div
      className="listitem"
      style={sx('cursor:pointer')}
      onClick={async () => {
        if (ota.pending) return void applyPendingUpdate();
        setBusy(true);
        showToast(await checkForUpdate());
        setBusy(false);
      }}
    >
      <div
        style={sx('width:38px;height:38px;border-radius:12px;background:var(--surface-3);display:grid;place-items:center;font-size:17px')}
      >
        {ota.pending ? '🔄' : '⬇️'}
      </div>
      <div style={sx('flex:1;min-width:0')}>
        <div style={sx('font-weight:500;font-size:14px')}>{label}</div>
        <div className="muted" style={sx('font-size:11px;margin-top:2px')}>
          {sub}
        </div>
      </div>
      <span className="muted">
        <Ic svg={I.arrow} />
      </span>
    </div>
  );
}

export function Profile() {
  const { go, tab } = useNav();
  const points = useClient((s) => s.points);
  const { properties, switchMode, goChooser, logout } = useSession();
  const hasOrg = properties.some((p) => p.kind === 'org');

  const run = (action: string) => {
    if (action === 'logout') return logout();
    if (action === 'prefsEdit') return go('prefsEdit');
    const [kind, id] = action.split(':');
    if (kind === 'tab') return tab(id);
    if (kind === 'go') return go(id);
  };

  const stats: [string | number, string][] = [
    ['12', 'Bilete'],
    [points, 'Puncte'],
    ['162', 'Sold lei'],
  ];

  return (
    <div className="grid" style={sx('min-height:100%;padding-bottom:6px')}>
      <div style={sx('display:flex;flex-direction:column;align-items:center;padding:14px 20px 20px')}>
        <div
          style={sx('width:86px;height:86px;border-radius:28px;background:linear-gradient(135deg,var(--indigo),var(--indigo-4));display:grid;place-items:center;color:#fff;font-size:28px;font-weight:600;box-shadow:var(--sh-p)')}
        >
          AP
        </div>
        <div style={sx('font-weight:600;font-size:19px;margin-top:12px')}>Andrei Popescu</div>
        <div className="muted" style={sx('font-size:13px')}>
          andrei@tixello.ro
        </div>
        <div className="row" style={sx('gap:10px;margin-top:16px')}>
          {stats.map((s) => (
            <div key={s[1]} className="card" style={sx('text-align:center;min-width:84px;padding:12px')}>
              <div style={sx('font-size:18px;font-weight:600')}>{s[0]}</div>
              <div
                className="muted"
                style={sx('font-size:10px;font-weight:500;text-transform:uppercase;margin-top:2px')}
              >
                {s[1]}
              </div>
            </div>
          ))}
        </div>
      </div>

      <div className="pad">
        <div
          className="card"
          onClick={() => go('invite')}
          style={sx('cursor:pointer;padding:14px;display:flex;align-items:center;gap:13px;background:linear-gradient(135deg,var(--green-soft),var(--surface-solid));border-color:var(--green-line)')}
        >
          <div
            style={sx('width:44px;height:44px;border-radius:13px;background:var(--green-soft);color:var(--green-2);display:grid;place-items:center;font-size:20px')}
          >
            🤝
          </div>
          <div style={sx('flex:1')}>
            <div style={sx('font-weight:600;font-size:14px')}>Invită prieteni, ia puncte</div>
            <div className="muted" style={sx('font-size:11.5px')}>
              Codul tău <b style={sx('color:var(--green-2)')}>{AFF.code}</b> · {AFF.invited} invitați
            </div>
          </div>
          <Ic svg={I.arrow} />
        </div>
      </div>

      <div className="pad" style={sx('margin-top:10px;display:flex;flex-direction:column;gap:10px')}>
        {/* §3 — punct de comutare catre contul de organizator (nu exista in prototip) */}
        {hasOrg ? (
          <>
            <div className="listitem" onClick={switchMode} style={sx('cursor:pointer')}>
              {/* badge cu emoji, ca toate celelalte randuri din lista prototipului */}
              <div
                style={sx('width:38px;height:38px;border-radius:12px;background:var(--surface-3);display:grid;place-items:center;font-size:17px')}
              >
                🎛
              </div>
              <div style={sx('flex:1;font-weight:500;font-size:14px')}>Comută la Organizator</div>
              <span className="muted">
                <Ic svg={I.arrow} />
              </span>
            </div>
            <div className="listitem" onClick={goChooser} style={sx('cursor:pointer')}>
              <div
                style={sx('width:38px;height:38px;border-radius:12px;background:var(--surface-3);display:grid;place-items:center;font-size:17px')}
              >
                🔀
              </div>
              <div style={sx('flex:1;font-weight:500;font-size:14px')}>Schimbă contul</div>
              <span className="muted">
                <Ic svg={I.arrow} />
              </span>
            </div>
          </>
        ) : null}

        <UpdateRow />

        {MENU.map((m) => (
          <div key={m[1]} className="listitem" onClick={() => run(m[2])} style={sx('cursor:pointer')}>
            <div
              style={sx('width:38px;height:38px;border-radius:12px;background:var(--surface-3);display:grid;place-items:center;font-size:17px')}
            >
              {m[0]}
            </div>
            <div style={sx('flex:1;font-weight:500;font-size:14px')}>{m[1]}</div>
            <span className="muted">
              <Ic svg={I.arrow} />
            </span>
          </div>
        ))}
      </div>

      <BottomNav active="profile" />
    </div>
  );
}
