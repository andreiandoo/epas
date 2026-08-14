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
import { useEffect, useState } from 'react';
import { Ic, Raw, cn, sx } from '../../../design/sx';
import {
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
import { BottomNav, ListSkeleton, SetHead, TopBar } from '../kit';
import { useNav } from '../nav';
import { useClient } from '../../../store/client';
import { useFavorites, useNotifications, useReviews, useTickets, type SavedItem } from '../accountData';
import { fetchFriends, inviteFriendByEmail, type FriendsState } from '../../../api/friends';
import { ConnectTics } from '../connectTics';
import { getAppToken } from '../../../api/orgApp';

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
              <div style={sx('font-size:11px;opacity:.8;font-weight:500')}>Puncte tics</div>
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

  /* Ecranul rula PE DATELE DEMO: cod „ANDREI2X", 12 invitati, o lista de
     prieteni inventata si o sectiune „Prietenii prietenilor" care nu are
     corespondent in API. Un cod de invitatie fals e activ daunator — il dai
     mai departe si nu merge pentru nimeni. Acum: ori datele contului tau, ori
     cererea de a lega contul. Nimic intre. */
  const [real, setReal] = useState<FriendsState | null>(null);
  const [tick, setTick] = useState(0);
  const [email, setEmail] = useState('');
  const [sending, setSending] = useState(false);
  const linked = !!getAppToken();

  useEffect(() => {
    if (!linked) return;

    let alive = true;
    void fetchFriends().then((r) => {
      if (alive && r.ok) setReal(r.data);
    });

    return () => {
      alive = false;
    };
  }, [tick, linked]);

  const send = () => {
    if (!email.trim()) {
      showToast('Completează un email');

      return;
    }

    setSending(true);
    void inviteFriendByEmail(email.trim()).then((r) => {
      setSending(false);
      showToast(r.ok ? 'Invitație trimisă la ' + email.trim() : r.message);
      if (r.ok) {
        setEmail('');
        setTick((n) => n + 1);
      }
    });
  };

  const share = async () => {
    if (!real) return;

    const text = 'Hai pe tics! Codul meu de invitație: ' + real.invite_code;

    try {
      if (navigator.share) {
        await navigator.share({ title: 'tics', text, url: real.invite_url });

        return;
      }

      await navigator.clipboard.writeText(text + '\n' + real.invite_url);
      showToast('Link copiat');
    } catch {
      /* foaia de share inchisa de utilizator — nu e eroare */
    }
  };

  return (
    <div className="grid" style={sx('min-height:100%')}>
      <TopBar>
        <div className="row" style={sx('gap:12px')}>
          <div className="icon-btn" onClick={back}>
            <Ic svg={I.back} />
          </div>
          <div className="h2">Invită prieteni</div>
        </div>
        <div style={sx('width:42px')} />
      </TopBar>

      {!linked ? <ConnectTics what="Invitațiile" onDone={() => setTick((n) => n + 1)} /> : null}

      {linked && !real ? (
        <div className="pad" style={sx('margin-top:16px;display:flex;flex-direction:column;gap:10px')}>
          <div className="sk" style={sx('height:180px;border-radius:20px')} />
          <div className="sk" style={sx('height:64px;border-radius:18px')} />
        </div>
      ) : null}

      {real ? (
        <>
          <div className="pad" style={sx('margin-top:14px')}>
            <div
              className="card"
              style={sx('padding:20px;text-align:center;background:linear-gradient(160deg,var(--indigo-3),#2a1065);border:1px solid var(--line-2);color:#fff;position:relative;overflow:hidden')}
            >
              <div style={sx('font-size:18px;font-weight:600')}>Invită-ți prietenii 🎉</div>
              <div style={sx('font-size:12.5px;opacity:.85;margin-top:6px;line-height:1.5')}>
                Vedeți împreună la ce evenimente mergeți și cumpărați unii pentru alții.
              </div>
              <div
                style={sx('margin-top:16px;background:rgba(255,255,255,.14);border:1px dashed rgba(255,255,255,.4);border-radius:14px;padding:14px;display:flex;align-items:center;gap:12px')}
              >
                <div style={sx('flex:1;text-align:left')}>
                  <div style={sx('font-size:10px;opacity:.75;font-weight:600')}>CODUL TĂU</div>
                  <div style={sx('font-size:20px;font-weight:600;letter-spacing:1px')}>{real.invite_code}</div>
                </div>
                <button
                  className="circ"
                  onClick={() => {
                    void navigator.clipboard?.writeText(real.invite_code);
                    showToast('Cod copiat: ' + real.invite_code);
                  }}
                  style={sx('position:static;width:40px;height:40px;background:#fff;color:#141020;border-color:#fff')}
                  aria-label="Copiază codul"
                >
                  <Ic svg={I.copy} />
                </button>
              </div>
              <button className="cta" style={sx('margin-top:13px;background:#fff;color:#141020')} onClick={() => void share()}>
                <Ic svg={I.send} /> Trimite invitația
              </button>
            </div>
          </div>

          <div className="pad" style={sx('margin-top:16px')}>
            <div className="label" style={sx('margin-bottom:8px')}>
              Trimite invitația pe email
            </div>
            <div className="field">
              <Ic svg={I.mail} />
              <input
                value={email}
                onChange={(e) => setEmail(e.target.value)}
                placeholder="email@prieten.ro"
                inputMode="email"
                onKeyDown={(e) => {
                  if (e.key === 'Enter') send();
                }}
              />
            </div>
            <button className="cta" style={sx('margin-top:12px')} disabled={sending} onClick={send}>
              <Ic svg={I.send} /> {sending ? 'Se trimite…' : 'Trimite invitația'}
            </button>
          </div>

          <div className="pad" style={sx('margin-top:16px')}>
            <div className="row" style={sx('gap:10px')}>
              {([
                [real.invited.length, 'În așteptare'],
                [real.friends.length, 'Au acceptat'],
              ] as [number, string][]).map((st) => (
                <div key={st[1]} className="card" style={sx('flex:1;text-align:center;padding:15px')}>
                  <div style={sx('font-size:22px;font-weight:600')}>{st[0]}</div>
                  <div className="muted" style={sx('font-size:10px;font-weight:500;text-transform:uppercase;margin-top:3px')}>
                    {st[1]}
                  </div>
                </div>
              ))}
            </div>
          </div>

          {real.friends.length ? (
            <div className="pad" style={sx('margin-top:20px')}>
              <div className="h2" style={sx('font-size:15px;margin-bottom:10px')}>
                Prietenii tăi
              </div>
              <div style={sx('display:flex;flex-direction:column;gap:10px')}>
                {real.friends.map((f) => (
                  <div key={f.id} className="listitem">
                    <div style={sx('width:40px;height:40px;border-radius:13px;background:linear-gradient(135deg,var(--indigo-2),var(--indigo-4));display:grid;place-items:center;color:#fff;font-weight:600;font-size:12px')}>
                      {f.name.split(' ').map((w) => w[0]).slice(0, 2).join('').toUpperCase()}
                    </div>
                    <div style={sx('flex:1;min-width:0')}>
                      <div style={sx('font-weight:600;font-size:13.5px')}>{f.name}</div>
                    </div>
                    <span className="badge" style={sx('background:var(--green-soft);color:var(--green-2)')}>
                      prieten
                    </span>
                  </div>
                ))}
              </div>
            </div>
          ) : null}

          {/* Invitatiile trimise si neacceptate inca. Aici era „Prietenii
              prietenilor" — o retea extinsa pe care serverul n-o calculeaza si
              n-o expune nicaieri; erau trei nume scrise in prototip. */}
          {real.invited.length ? (
            <div className="pad" style={sx('margin-top:20px')}>
              <div className="h2" style={sx('font-size:15px;margin-bottom:10px')}>
                Invitații trimise
              </div>
              <div style={sx('display:flex;flex-direction:column;gap:10px')}>
                {real.invited.map((iv) => (
                  <div key={iv.email} className="listitem" style={sx('opacity:.9')}>
                    <div style={sx('width:38px;height:38px;border-radius:12px;background:var(--surface-3);display:grid;place-items:center;color:var(--muted)')}>
                      <Ic svg={I.mail} />
                    </div>
                    <div style={sx('flex:1;min-width:0')}>
                      <div style={sx('font-weight:500;font-size:13px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis')}>
                        {iv.email}
                      </div>
                    </div>
                    <span className="badge">așteaptă</span>
                  </div>
                ))}
              </div>
            </div>
          ) : null}

          {!real.friends.length && !real.invited.length ? (
            <div className="pad" style={sx('margin-top:20px')}>
              <div className="muted" style={sx('font-size:12px;text-align:center;line-height:1.55')}>
                Încă n-ai invitat pe nimeni. Trimite codul de mai sus — când cineva îl folosește, apare aici. 💜
              </div>
            </div>
          ) : null}
        </>
      ) : null}

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
  const savedRadar = useClient((s) => s.savedRadar);
  const toggleSavedRadar = useClient((s) => s.toggleSavedRadar);
  const favs = useFavorites();

  /* Salvatele vin din doua surse: datasetul local (rezolvabil dupa id) si TICS
     Radar (pastrat ca obiect, fiindca n-are corespondent local). Le aducem la
     aceeasi forma, ca randul sa nu stie de unde vine evenimentul. */
  type Row = {
    id: string;
    s: string;
    city: string;
    d: string;
    type: string;
    from: number;
    poster?: string | null;
    radar: boolean;
    /** favorit din contul real: scoaterea se face pe server, nu local */
    fav?: SavedItem;
  };

  const localList: Row[] = saved
    .map((id): Row | null => {
      const r = savedRadar[id];
      if (r) {
        return {
          id: r.id,
          s: r.s,
          city: r.city,
          d: `${r.day} ${r.mon}`.trim(),
          type: 'event',
          from: r.offers[0]?.[1] ?? 0,
          poster: r.poster,
          radar: true,
        };
      }
      const e = evOf(id) as Record<string, unknown> | undefined;
      if (!e) return null;
      return {
        id: e.id as string,
        s: e.s as string,
        city: e.city as string,
        d: e.d as string,
        type: (e.type as string) ?? 'event',
        from: (e.from as number) ?? 0,
        poster: null,
        radar: false,
      };
    })
    .filter((x): x is Row => x !== null);

  /* Cu un cont conectat, sursa de adevar e serverul: favoritele traiesc acolo
     si trebuie sa se vada la fel pe orice dispozitiv. Salvarile locale raman
     doar pentru sesiunea fara cont. */
  const list: Row[] = favs.items
    ? favs.items.map((f) => ({
        id: String(f.itemId),
        s: f.title,
        city: f.sub,
        d: '',
        type: f.kind === 'artist' ? 'artist' : 'event',
        from: 0,
        poster: f.img,
        radar: false,
        fav: f,
      }))
    : localList;

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

      {favs.loading ? <ListSkeleton rows={3} height={96} /> : null}

      {!favs.loading && list.length ? (
        <div className="pad" style={sx('margin-top:14px;display:flex;flex-direction:column;gap:12px')}>
          {list.map((ev) => (
            <div key={ev.id} className="card" style={sx('overflow:hidden;display:flex;gap:12px;padding:11px')}>
              <div
                onClick={() => go(ev.radar ? 'ticsoffers' : ev.type === 'artist' ? 'artist' : 'event', { id: ev.id })}
                style={sx('display:flex;gap:12px;flex:1;cursor:pointer;min-width:0')}
              >
                {ev.poster ? (
                  <div
                    style={{
                      width: 74,
                      height: 74,
                      borderRadius: 16,
                      flex: 'none',
                      background: `url('${ev.poster}') center/cover, #14101f`,
                    }}
                  />
                ) : (
                  <Raw html={poster(ev, '', 'width:74px;height:74px;border-radius:16px;flex:none', { tag: 1 })} />
                )}
                <div style={sx('flex:1;min-width:0')}>
                  <div style={sx('font-weight:600;font-size:14px')}>{ev.s}</div>
                  <div className="row muted" style={sx('gap:5px;font-size:11.5px;margin-top:3px')}>
                    <Ic svg={I.pin} /> {[ev.city, ev.d].filter(Boolean).join(' · ')}
                  </div>
                  {/* pretul apare doar cand il stim; favoritele din cont nu-l poarta */}
                  {ev.from ? (
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
                  ) : null}
                </div>
              </div>
              <button
                className="icon-btn"
                onClick={() =>
                  ev.fav ? favs.remove(ev.fav) : ev.radar ? toggleSavedRadar(savedRadar[ev.id]) : toggleSaved(ev.id)
                }
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
  const { list, stats, loading } = useReviews();
  const { groups, live: ticketsLive } = useTickets();

  /* „De recenzat" nu are endpoint propriu, dar se poate deduce: evenimentele
     cu bilet care au trecut deja si pentru care nu exista inca o recenzie.
     Potrivirea se face pe TITLU, fiindca recenziile intorc numele
     evenimentului, nu id-ul lui — daca API-ul incepe sa dea `event_id`,
     inlocuieste comparatia de mai jos cu una pe id. */
  const reviewed = new Set((list ?? []).map((r) => (r.event ?? '').trim().toLowerCase()));
  const toReview: { ev: string; name: string; when: string; poster: Ev }[] = ticketsLive
    ? groups
        .filter((g) => !g.upcoming && !reviewed.has(g.title.trim().toLowerCase()))
        .map((g) => ({ ev: g.ev, name: g.title, when: g.date, poster: { g: '★' } }))
    : (ATTENDED as { ev: string; when: string; reviewed: boolean }[])
        .filter((a) => !a.reviewed)
        .map((a) => ({ ev: a.ev, name: evOf(a.ev).s, when: a.when, poster: evOf(a.ev) }));

  /* Cele doua surse au campuri diferite (prototipul are `target`/`txt` si un
     eveniment rezolvabil dupa id; API-ul da titlu de eveniment ca text si o
     stare de moderare). Le aducem la un rand comun, ca marcajul de mai jos sa
     ramana cel din prototip. */
  type Row = { key: string; ev: Ev; name: string; sub: string; rating: number; txt: string; pending: boolean };

  const rows: Row[] = list
    ? list.map((r): Row => ({
        key: String(r.id),
        // fara imagine in API: posterul cade pe scena implicita, ca in prototip
        ev: { g: '★' },
        name: r.event ?? 'Eveniment',
        sub: r.created_at ? new Date(r.created_at).toLocaleDateString('ro-RO') : '',
        rating: r.rating,
        // titlul recenziei, cand exista, e primul rand al textului
        txt: [r.title, r.body].filter(Boolean).join(' — '),
        pending: r.status !== 'published',
      }))
    : (MYREVIEWS as Ev[]).map((r, i): Row => {
        const ev = evOf(r.ev);
        return {
          key: `proto-${i}`,
          ev,
          name: ev.s,
          sub: `${r.target} · ${r.when}`,
          rating: r.rating,
          txt: r.txt,
          pending: false,
        };
      });

  return (
    <div className="grid" style={sx('min-height:100%')}>
      <TopBar>
        <div className="row" style={sx('gap:12px')}>
          <div className="icon-btn" onClick={back}>
            <Ic svg={I.back} />
          </div>
          <div>
            <div className="h2">Recenziile mele</div>
            {stats ? (
              <div className="muted" style={sx('font-size:11.5px')}>
                {stats.total} recenzii · medie {stats.avg.toFixed(1)}
                {stats.pending ? ` · ${stats.pending} în așteptare` : ''}
              </div>
            ) : null}
          </div>
        </div>
        <div style={sx('width:42px')} />
      </TopBar>

      {toReview.length ? (
        <div className="pad" style={sx('margin-top:14px')}>
          <div className="label" style={sx('margin-bottom:9px')}>
            De recenzat · ai participat
          </div>
          <div style={sx('display:flex;flex-direction:column;gap:11px')}>
            {toReview.map((a) => (
              <div key={a.ev} className="card" style={sx('padding:13px')}>
                <div className="row" style={sx('gap:12px')}>
                  <Raw html={poster(a.poster, '', 'width:52px;height:52px;border-radius:14px;flex:none', undefined)} />
                  <div style={sx('flex:1')}>
                    <div style={sx('font-weight:600;font-size:13.5px')}>{a.name}</div>
                    <div className="muted" style={sx('font-size:11.5px')}>
                      Ai participat · {a.when}
                    </div>
                  </div>
                </div>
                <button className="cta" onClick={() => go('review', { id: a.ev })} style={sx('margin-top:11px;padding:12px')}>
                  <Ic svg={I.star} /> Lasă o recenzie
                </button>
              </div>
            ))}
          </div>
        </div>
      ) : null}

      <div className="pad" style={sx('margin-top:18px')}>
        <div className="label" style={sx('margin-bottom:9px')}>
          Recenziile tale
        </div>
        <div style={sx('display:flex;flex-direction:column;gap:11px')}>
          {loading ? <ListSkeleton rows={3} height={104} /> : null}
          {loading ? null : rows.map((r) => (
            <div key={r.key} className="card" style={sx('padding:14px')}>
              <div className="between">
                <div className="row" style={sx('gap:11px')}>
                  <Raw html={poster(r.ev, '', 'width:44px;height:44px;border-radius:12px;flex:none', undefined)} />
                  <div>
                    <div style={sx('font-weight:600;font-size:13.5px')}>{r.name}</div>
                    <div className="muted" style={sx('font-size:10.5px')}>
                      {r.sub}
                      {r.pending ? ' · în așteptare' : ''}
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
          ))}
          {!rows.length ? (
            <div className="muted" style={sx('font-size:12.5px;text-align:center;padding:18px 0')}>
              Nu ai scris nicio recenzie încă.
            </div>
          ) : null}
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
  const { list: live, loading } = useNotifications();

  /* Notificarile reale, aduse la forma tuplului din prototip:
     [emoji, titlu, text, cand, necitit]. */
  type Row = [string, string, string, string, number];
  const rows: Row[] = live
    ? live.map((n): Row => [
        n.type === 'order' ? '🎟' : n.type === 'event' ? '📅' : '🔔',
        n.title ?? 'Notificare',
        n.body ?? n.message ?? '',
        n.created_at ? new Date(n.created_at).toLocaleDateString('ro-RO') : '',
        n.read ? 0 : 1,
      ])
    : (NOTI as Row[]);

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
        {loading ? <ListSkeleton rows={4} height={72} /> : null}
        {loading ? null : rows.map((n, i) => (
          <div
            key={`${n[1]}-${i}`}
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
        {!rows.length ? (
          <div className="muted" style={sx('font-size:12.5px;text-align:center;padding:20px 0')}>
            Nicio notificare.
          </div>
        ) : null}
      </div>
      <BottomNav active="" />
    </div>
  );
}
