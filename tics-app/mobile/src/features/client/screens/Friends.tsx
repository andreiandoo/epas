/* =========================================================
   PRIETENI.

   Ecran nou — prototipul n-are aşa ceva; are doar „Invită prieteni", care era
   despre afiliere (cod + comision), nu despre un graf social. Limbajul vizual e
   însă tot al lui: aceleaşi `.card`, `.listitem`, `.chip`, ca ecranul să nu pară
   lipit din altă aplicaţie.

   Trei zone, în ordinea în care contează:
     1. CERERILE primite — sunt singurele care aşteaptă o decizie;
     2. CODUL propriu — singura cale de descoperire (nu există căutare de
        oameni, nici aici, nici pe server);
     3. PRIETENII, cu acţiunile pe fiecare.

   Invitaţiile trimise unor oameni fără cont apar separat: altfel ai crede că
   n-ai trimis nimic şi ai trimite iar.
   ========================================================= */
import { useCallback, useEffect, useState } from 'react';
import { Ic, sx } from '../../../design/sx';
import { I } from '../../../mock/prototype';
import { BottomNav, TopBar } from '../kit';
import { useNav } from '../nav';
import { useClient } from '../../../store/client';
import {
  blockFriend,
  fetchFriends,
  inviteFriendByEmail,
  redeemInviteCode,
  removeFriend,
  respondToRequest,
  reportAccount,
  type FriendCard,
  type FriendsState,
  type ReportReason,
} from '../../../api/friends';

/** Iniţialele, când nu există poză. */
const initials = (name: string) =>
  name
    .split(/\s+/)
    .slice(0, 2)
    .map((w) => w[0]?.toUpperCase() ?? '')
    .join('') || '?';

function Avatar({ person, size = 42 }: { person: FriendCard; size?: number }) {
  return (
    <div
      style={{
        width: size,
        height: size,
        borderRadius: size * 0.34,
        flex: 'none',
        display: 'grid',
        placeItems: 'center',
        fontWeight: 600,
        fontSize: size * 0.34,
        color: '#fff',
        background: person.avatar
          ? `url('${person.avatar}') center/cover, #14101f`
          : 'linear-gradient(135deg,var(--indigo),var(--indigo-4))',
      }}
    >
      {person.avatar ? '' : initials(person.name)}
    </div>
  );
}

export function Friends() {
  const { go, back } = useNav();
  const showToast = useClient((s) => s.showToast);

  const [state, setState] = useState<FriendsState | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);
  const [code, setCode] = useState('');
  const [email, setEmail] = useState('');

  const load = useCallback(async () => {
    const r = await fetchFriends();
    if (r.ok) {
      setState(r.data);
      setError(null);
    } else {
      setError(r.message);
    }
  }, []);

  useEffect(() => {
    void load();
  }, [load]);

  /* Fiecare acţiune reîncarcă lista de la server, nu ajustează starea locală:
     o acceptare schimbă trei liste deodată (cereri, prieteni, trimise), iar o
     corecţie locală ar rata una şi ar arăta un ecran care nu mai corespunde. */
  const run = async (action: () => Promise<{ ok: boolean; message?: string }>, okText?: string) => {
    setBusy(true);
    const r = await action();
    setBusy(false);

    if (!r.ok) {
      showToast(r.message ?? 'Nu a mers.');

      return;
    }

    if (okText) showToast(okText);
    void load();
  };

  const share = async () => {
    if (!state) return;

    const text = `Hai pe Tics! Codul meu de invitație: ${state.invite_code}`;

    try {
      if (navigator.share) {
        await navigator.share({ title: 'Tics', text, url: state.invite_url });

        return;
      }

      await navigator.clipboard.writeText(`${text}\n${state.invite_url}`);
      showToast('Link copiat');
    } catch {
      /* utilizatorul a închis foaia de share — nu e o eroare */
    }
  };

  return (
    <div className="grid" style={sx('min-height:100%')}>
      <TopBar>
        <div className="row" style={sx('gap:12px')}>
          <div className="icon-btn" onClick={back}>
            <Ic svg={I.back} />
          </div>
          <div>
            <div className="h2">Prietenii mei</div>
            {state ? (
              <div className="muted" style={sx('font-size:11.5px')}>
                {state.friends.length} prieteni
                {state.requests.length ? ` · ${state.requests.length} cereri` : ''}
              </div>
            ) : null}
          </div>
        </div>
        <div style={sx('width:42px')} />
      </TopBar>

      {error ? (
        <div className="pad" style={sx('margin-top:40px;text-align:center')}>
          <div style={sx('font-size:40px;opacity:.5')}>🤝</div>
          <div className="muted" style={sx('font-size:12.5px;margin-top:10px;line-height:1.5')}>{error}</div>
        </div>
      ) : null}

      {!state && !error ? (
        <div className="pad" style={sx('margin-top:16px;display:flex;flex-direction:column;gap:10px')}>
          <div className="sk" style={sx('height:64px;border-radius:18px')} />
          <div className="sk" style={sx('height:64px;border-radius:18px')} />
        </div>
      ) : null}

      {state ? (
        <>
          {/* ---------- cereri primite ---------- */}
          {state.requests.length ? (
            <div className="pad" style={sx('margin-top:14px')}>
              <div className="label" style={sx('margin-bottom:9px')}>
                Cereri de prietenie
              </div>
              <div style={sx('display:flex;flex-direction:column;gap:10px')}>
                {state.requests.map((r) => (
                  <div key={r.id} className="card" style={sx('padding:12px')}>
                    <div className="row" style={sx('gap:12px')}>
                      <Avatar person={r.account} />
                      <div style={sx('flex:1;min-width:0')}>
                        <div style={sx('font-weight:600;font-size:13.5px')}>{r.account.name}</div>
                        <div className="muted" style={sx('font-size:11px')}>
                          {r.source === 'beneficiary' ? 'V-ați întâlnit la o comandă' : 'Prin cod de invitație'}
                        </div>
                      </div>
                    </div>
                    <div className="row" style={sx('gap:10px;margin-top:11px')}>
                      <button
                        className="cta"
                        disabled={busy}
                        style={sx('padding:11px')}
                        onClick={() => void run(() => respondToRequest(r.id, true), 'Sunteți prieteni')}
                      >
                        Acceptă
                      </button>
                      <button
                        className="cta ghost"
                        disabled={busy}
                        style={sx('padding:11px')}
                        onClick={() => void run(() => respondToRequest(r.id, false))}
                      >
                        Refuză
                      </button>
                    </div>
                  </div>
                ))}
              </div>
            </div>
          ) : null}

          {/* ---------- codul propriu ---------- */}
          <div className="pad" style={sx('margin-top:18px')}>
            <div
              className="card"
              style={sx('padding:16px;background:linear-gradient(135deg,var(--indigo-soft),var(--surface-solid));border-color:var(--indigo-line)')}
            >
              <div style={sx('font-weight:600;font-size:14px')}>Codul tău de invitație</div>
              <div className="muted" style={sx('font-size:11.5px;margin-top:3px;line-height:1.45')}>
                Prietenii se adaugă doar cu codul sau linkul tău — nu există căutare după nume.
              </div>
              <div
                style={sx('font-size:26px;font-weight:700;letter-spacing:5px;margin-top:12px;color:var(--indigo-2);font-variant-numeric:tabular-nums')}
              >
                {state.invite_code}
              </div>
              <div className="row" style={sx('gap:10px;margin-top:12px')}>
                <button className="cta" style={sx('padding:11px')} onClick={() => void share()}>
                  <Ic svg={I.share} /> Trimite
                </button>
                <button
                  className="cta ghost"
                  style={sx('padding:11px')}
                  onClick={() => {
                    void navigator.clipboard?.writeText(state.invite_code);
                    showToast('Cod copiat');
                  }}
                >
                  Copiază
                </button>
              </div>
            </div>
          </div>

          {/* ---------- adaugă pe cineva ---------- */}
          <div className="pad" style={sx('margin-top:18px')}>
            <div className="label" style={sx('margin-bottom:9px')}>
              Adaugă un prieten
            </div>
            <div className="field">
              <input
                value={code}
                placeholder="Codul lui de invitație"
                autoCapitalize="characters"
                onChange={(e) => setCode(e.target.value.toUpperCase())}
              />
              <button
                className="chip ind on"
                disabled={busy || code.trim().length < 4}
                style={sx('padding:6px 12px')}
                onClick={() =>
                  void run(() => redeemInviteCode(code), 'Cerere trimisă').then(() => setCode(''))
                }
              >
                Trimite
              </button>
            </div>

            <div className="muted" style={sx('font-size:11.5px;margin-top:14px')}>
              Sau invită pe email pe cineva care nu are încă Tics:
            </div>
            <div className="field" style={sx('margin-top:8px')}>
              <input
                value={email}
                type="email"
                placeholder="email@exemplu.ro"
                onChange={(e) => setEmail(e.target.value)}
              />
              <button
                className="chip"
                disabled={busy || !email.includes('@')}
                style={sx('padding:6px 12px')}
                onClick={() =>
                  void run(() => inviteFriendByEmail(email), 'Invitație trimisă').then(() => setEmail(''))
                }
              >
                Invită
              </button>
            </div>
          </div>

          {/* ---------- invitaţii în aşteptare ---------- */}
          {state.invited.length ? (
            <div className="pad" style={sx('margin-top:18px')}>
              <div className="label" style={sx('margin-bottom:9px')}>
                Invitații trimise
              </div>
              <div className="card" style={sx('padding:4px 14px')}>
                {state.invited.map((inv, i) => (
                  <div
                    key={inv.email}
                    className="between"
                    style={{ padding: '11px 0', borderBottom: i < state.invited.length - 1 ? '1px solid var(--line)' : undefined }}
                  >
                    <div style={sx('min-width:0')}>
                      <div style={sx('font-size:13px;font-weight:500')}>{inv.name || inv.email}</div>
                      {inv.name ? (
                        <div className="muted" style={sx('font-size:11px')}>
                          {inv.email}
                        </div>
                      ) : null}
                    </div>
                    <span className="muted" style={sx('font-size:11px')}>
                      așteaptă
                    </span>
                  </div>
                ))}
              </div>
            </div>
          ) : null}

          {/* ---------- cereri trimise ---------- */}
          {state.sent.length ? (
            <div className="pad" style={sx('margin-top:18px')}>
              <div className="label" style={sx('margin-bottom:9px')}>
                Cereri trimise
              </div>
              <div style={sx('display:flex;flex-direction:column;gap:9px')}>
                {state.sent.map((r) => (
                  <div key={r.id} className="listitem">
                    <Avatar person={r.account} size={38} />
                    <div style={sx('flex:1;min-width:0;font-weight:500;font-size:13.5px')}>{r.account.name}</div>
                    <span className="muted" style={sx('font-size:11px')}>
                      așteaptă răspuns
                    </span>
                  </div>
                ))}
              </div>
            </div>
          ) : null}

          {/* ---------- prieteni ---------- */}
          <div className="pad" style={sx('margin-top:18px')}>
            <div className="label" style={sx('margin-bottom:9px')}>
              Prieteni
            </div>
            {state.friends.length ? (
              <div style={sx('display:flex;flex-direction:column;gap:9px')}>
                {state.friends.map((f) => (
                  <div
                    key={f.id}
                    className="listitem"
                    onClick={() => go('friend', { id: String(f.id) })}
                    style={sx('cursor:pointer')}
                  >
                    <Avatar person={f} size={38} />
                    <div style={sx('flex:1;min-width:0;font-weight:500;font-size:13.5px')}>{f.name}</div>
                    <span className="muted">
                      <Ic svg={I.arrow} />
                    </span>
                  </div>
                ))}
              </div>
            ) : (
              <div className="muted" style={sx('font-size:12.5px;padding:8px 0;line-height:1.5')}>
                Încă niciun prieten. Trimite-i cuiva codul tău.
              </div>
            )}
          </div>
        </>
      ) : null}

      <div style={sx('height:8px')} />
      <BottomNav active="profile" />
    </div>
  );
}

/* =========================================================
   Profilul unui prieten.

   Deliberat sărac: nume şi poză. Serverul nici nu trimite mai mult — email,
   telefon şi istoric n-au de ce să treacă de la un prieten la altul fără o
   decizie explicită. Când vor exista lucruri de arătat (evenimente comune,
   artişti urmăriţi), aici e locul lor.
   ========================================================= */
const REPORT_REASONS: [ReportReason, string][] = [
  ['spam', 'Trimite spam sau reclame'],
  ['harassment', 'Mă hărțuiește'],
  ['fake_profile', 'Profil fals / impostor'],
  ['inappropriate', 'Conținut nepotrivit'],
  ['other', 'Altceva'],
];

export function FriendProfile({ id }: { id?: string }) {
  const { back } = useNav();
  const showToast = useClient((s) => s.showToast);
  const [reporting, setReporting] = useState(false);
  const [reason, setReason] = useState<ReportReason | ''>('');
  const [note, setNote] = useState('');

  const [person, setPerson] = useState<FriendCard | null>(null);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    if (!id) return;

    let alive = true;
    void fetchFriendProfileSafe(Number(id)).then((r) => {
      if (!alive) return;
      if (r.ok) setPerson(r.data);
      else setError(r.message);
    });

    return () => {
      alive = false;
    };
  }, [id]);

  const act = async (action: () => Promise<{ ok: boolean; message?: string }>, okText: string) => {
    const r = await action();
    showToast(r.ok ? okText : (r.message ?? 'Nu a mers.'));
    if (r.ok) back();
  };

  return (
    <div className="grid" style={sx('min-height:100%')}>
      <TopBar>
        <div className="row" style={sx('gap:12px')}>
          <div className="icon-btn" onClick={back}>
            <Ic svg={I.back} />
          </div>
          <div className="h2">Profil</div>
        </div>
        <div style={sx('width:42px')} />
      </TopBar>

      {error ? (
        <div className="pad" style={sx('margin-top:50px;text-align:center')}>
          <div className="muted" style={sx('font-size:12.5px;line-height:1.5')}>{error}</div>
        </div>
      ) : null}

      {person ? (
        <>
          <div className="pad" style={sx('margin-top:22px;text-align:center')}>
            <div style={sx('display:flex;justify-content:center')}>
              <Avatar person={person} size={96} />
            </div>
            <div style={sx('font-weight:600;font-size:20px;margin-top:12px')}>{person.name}</div>
            <div className="muted" style={sx('font-size:12px;margin-top:3px')}>
              Prieten pe Tics
            </div>
          </div>

          <div className="pad" style={sx('margin-top:24px;display:flex;flex-direction:column;gap:10px')}>
            <button
              className="cta ghost"
              onClick={() => void act(() => removeFriend(person.id), 'Șters din prieteni')}
              style={sx('padding:12px')}
            >
              Șterge din prieteni
            </button>
            <button
              className="cta ghost"
              onClick={() => void act(() => blockFriend(person.id), 'Blocat')}
              style={sx('padding:12px;color:var(--red);border-color:rgba(240,97,109,.3)')}
            >
              Blochează
            </button>
            <button
              className="cta ghost"
              onClick={() => setReporting(true)}
              style={sx('padding:12px;color:var(--red);border-color:rgba(240,97,109,.3)')}
            >
              Raportează
            </button>
          </div>
        </>
      ) : null}

      {/* RAPORTAREA. Blocarea rezolva problema unui singur om — nu-l mai vezi.
          Raportarea e pentru cand problema priveste pe toata lumea si trebuie sa
          ajunga la cineva care poate lua o masura. Motivele sunt o lista scurta
          si inchisa: un camp liber ar fi produs „nu-mi place" si n-ar fi putut
          fi triat de nimeni. */}
      {reporting && person ? (
        <div
          onClick={() => setReporting(false)}
          style={sx('position:fixed;inset:0;z-index:60;background:rgba(4,3,9,.6);backdrop-filter:blur(3px);display:flex;align-items:flex-end')}
        >
          <div
            onClick={(e) => e.stopPropagation()}
            style={{
              width: '100%',
              background: 'var(--bg)',
              borderRadius: '24px 24px 0 0',
              border: '1px solid var(--line)',
              borderBottom: 0,
              paddingBottom: 'calc(16px + var(--safe-bottom, 0px))',
            }}
          >
            <div style={sx('width:40px;height:4px;border-radius:9px;background:var(--line-2);margin:10px auto 6px')} />
            <div className="pad">
              <div className="h2" style={sx('font-size:15px')}>
                Raportează {person.name}
              </div>
              <div className="muted" style={sx('font-size:11.5px;margin-top:4px;line-height:1.45')}>
                Contul va fi și blocat, ca să nu te mai poată contacta până verificăm.
              </div>

              <div style={sx('display:flex;flex-direction:column;gap:8px;margin-top:14px')}>
                {REPORT_REASONS.map(([value, label]) => (
                  <button
                    key={value}
                    className={reason === value ? 'chip ind on' : 'chip'}
                    onClick={() => setReason(value)}
                    style={sx('justify-content:flex-start;padding:12px 14px')}
                  >
                    {label}
                  </button>
                ))}
              </div>

              {reason === 'other' ? (
                <div className="field" style={sx('margin-top:12px')}>
                  <input
                    value={note}
                    placeholder="Spune-ne pe scurt ce s-a întâmplat"
                    onChange={(e) => setNote(e.target.value)}
                  />
                </div>
              ) : null}

              <button
                className="cta"
                disabled={!reason || (reason === 'other' && note.trim().length < 3)}
                style={sx('margin-top:14px')}
                onClick={() =>
                  void act(() => reportAccount(person.id, reason as ReportReason, note || undefined), 'Raport trimis')
                }
              >
                Trimite raportul
              </button>
            </div>
          </div>
        </div>
      ) : null}

      <div style={sx('height:10px')} />
      <BottomNav active="profile" />
    </div>
  );
}

/** Import separat, ca `Friends` să nu tragă profilul şi invers. */
async function fetchFriendProfileSafe(id: number) {
  const { fetchFriendProfile } = await import('../../../api/friends');

  return fetchFriendProfile(id);
}
