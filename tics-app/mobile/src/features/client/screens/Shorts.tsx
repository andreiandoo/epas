/* =========================================================
   PE VAL (shorts) — port 1:1 al lui S.shorts() + buildFeed() + shortItem()
   (client-app.html, liniile 1177-1210).

   Feed vertical cu snap, trei tipuri de card: eveniment (video / galerie /
   foto), artist si locatie. Ordinea e recalculata la fiecare intrare, cu
   scoruri influentate de preferintele utilizatorului — exact ca buildFeed().
   ========================================================= */
import { useMemo, useState } from 'react';
import { Ic, Raw, cn, sx } from '../../../design/sx';
import { ART, EV, I, VEN, bgv, galFor, money, poster } from '../../../mock/prototype';
import { useNav } from '../nav';
import { useClient } from '../../../store/client';

type Ev = Record<string, any>;

/** kfmt() din prototip */
const kfmt = (n: number) => (n >= 1000 ? (n / 1000).toFixed(1).replace(/\.0$/, '') + 'k' : '' + n);

type FeedItem =
  | { t: 'event'; ev: Ev; media: 'video' | 'gallery' | 'image'; likes: string; s: number }
  | { t: 'artist'; a: Ev; evc: number; likes: string; s: number }
  | { t: 'venue'; v: Ev; evc: number; likes: string; s: number };

/** buildFeed() din prototip */
function buildFeed(prefs: string[]): FeedItem[] {
  const items: FeedItem[] = [];

  Object.values(EV as Record<string, Ev>).forEach((ev) => {
    let s = Math.random() * 0.9;
    if (prefs.includes(ev.cat)) s += 1.5;
    if (prefs.includes(ev.city)) s += 0.9;
    if (ev.type === 'experience' && prefs.includes('Experiențe')) s += 1.1;
    const media: 'video' | 'gallery' | 'image' = ev.video
      ? Math.random() < 0.62
        ? 'video'
        : 'gallery'
      : Math.random() < 0.5
        ? 'gallery'
        : 'image';
    items.push({ t: 'event', ev, media, likes: kfmt(900 + Math.floor(Math.random() * 30000)), s: s + 1.4 });
  });

  Object.values(ART as Record<string, Ev>).forEach((a) => {
    let s = Math.random() * 0.9;
    const evs = Object.values(EV as Record<string, Ev>).filter((e) => e.artists.includes(a.id));
    if (evs.some((e) => prefs.includes(e.cat))) s += 1.1;
    items.push({ t: 'artist', a, evc: evs.length, likes: a.fol, s: s + 0.5 });
  });

  Object.values(VEN as Record<string, Ev>).forEach((v) => {
    let s = Math.random() * 0.8;
    if (prefs.includes(v.city)) s += 1;
    const evc = Object.values(EV as Record<string, Ev>).filter((e) => e.ven === v.id).length;
    items.push({ t: 'venue', v, evc, likes: kfmt(400 + Math.floor(Math.random() * 8000)), s: s + 0.2 });
  });

  items.sort((a, b) => b.s - a.s);
  return items;
}

/** shTop(icon) */
function ShTop({ icon }: { icon: string }) {
  const { back } = useNav();
  return (
    <div className="shtop">
      <div className="icon-btn glass" onClick={back}>
        <Ic svg={I.back} />
      </div>
      <div className="row" style={sx('gap:6px;color:#fff;font-weight:600;font-size:14px')}>
        <Ic svg={I.wave} /> Pe val
      </div>
      <div className="icon-btn glass">
        <Ic svg={icon} />
      </div>
    </div>
  );
}

function Act({ children, label, onClick }: { children: React.ReactNode; label?: string; onClick?: () => void }) {
  return (
    <div className="act" onClick={onClick}>
      <div className="b">{children}</div>
      {label ? <span>{label}</span> : null}
    </div>
  );
}

export function Shorts() {
  const { go } = useNav();
  const prefsSel = useClient((s) => s.prefsSel);
  const toggleSaved = useClient((s) => s.toggleSaved);
  const showToast = useClient((s) => s.showToast);
  /* recalculat la fiecare intrare, ca in prototip */
  const feed = useMemo(() => buildFeed(prefsSel), [prefsSel]);
  const [liked, setLiked] = useState<Record<number, boolean>>({});

  const like = (i: number) => {
    setLiked((l) => ({ ...l, [i]: !l[i] }));
    showToast(liked[i] ? 'Scos de la favorite' : '💜');
  };

  return (
    <div className="shorts">
      {feed.map((it, idx) => {
        if (it.t === 'artist') {
          const a = it.a;
          return (
            <div className="short" key={`a${a.id}`}>
              <div className="media kb" style={{ background: bgv(a) }} />
              <div className="grad" />
              <ShTop icon={I.user} />
              <span className="mtag">🎤 Artist</span>
              <div className="info">
                <div
                  style={{
                    width: 64,
                    height: 64,
                    borderRadius: 22,
                    background: a.tone,
                    display: 'grid',
                    placeItems: 'center',
                    fontSize: '29px',
                    boxShadow: 'var(--sh-p)',
                    border: '2px solid rgba(255,255,255,.25)',
                  }}
                >
                  {a.g}
                </div>
                <span className="gpill solid" style={sx('margin-top:12px')}>
                  Artist · {a.role}
                </span>
                <div style={sx('font-size:26px;font-weight:700;letter-spacing:-.03em;margin-top:10px;line-height:1.05;text-shadow:0 2px 18px rgba(0,0,0,.6)')}>
                  {a.name}
                </div>
                <div className="cmeta" style={sx('margin-top:8px')}>
                  <span className="i">
                    <Ic svg={I.user} />
                    <span>{a.fol} urmăritori</span>
                  </span>
                  <span className="i">
                    <Ic svg={I.cal} />
                    <span>{it.evc} evenimente</span>
                  </span>
                </div>
                <button className="shcta" onClick={() => go('artist', { id: a.id })} style={sx('margin-top:14px')}>
                  <Ic svg={I.user} /> Vezi artist
                </button>
              </div>
              <div className="rail">
                <Act label={a.fol} onClick={() => like(idx)}>
                  <Ic svg={I.heartO} />
                </Act>
                <Act>
                  <Ic svg={I.share} />
                </Act>
                <div className="act" onClick={() => go('artist', { id: a.id })}>
                  <div
                    className="b"
                    style={{ padding: 0, overflow: 'hidden', border: '2px solid rgba(255,255,255,.5)', background: a.tone, fontSize: '19px' }}
                  >
                    {a.g}
                  </div>
                </div>
              </div>
            </div>
          );
        }

        if (it.t === 'venue') {
          const v = it.v;
          return (
            <div className="short" key={`v${v.id}`}>
              <div className="media kb" style={{ background: bgv(v) }} />
              <div className="grad" />
              <ShTop icon={I.pin} />
              <span className="mtag">📍 Locație</span>
              <div className="info">
                <span className="gpill" style={sx('background:rgba(45,214,238,.9);border-color:transparent;color:#052a30')}>
                  📍 Locație
                </span>
                <div style={sx('font-size:26px;font-weight:700;letter-spacing:-.03em;margin-top:11px;line-height:1.05;text-shadow:0 2px 18px rgba(0,0,0,.6)')}>
                  {v.name}
                </div>
                <div className="cmeta" style={sx('margin-top:8px')}>
                  <span className="i">
                    <Ic svg={I.pin} />
                    <span>{v.city}</span>
                  </span>
                  <span className="i">
                    <Ic svg={I.user} />
                    <span>{v.cap} locuri</span>
                  </span>
                  <span className="i">
                    <Ic svg={I.cal} />
                    <span>{it.evc} ev.</span>
                  </span>
                </div>
                <button className="shcta" onClick={() => go('venue', { id: v.id })} style={sx('margin-top:14px')}>
                  <Ic svg={I.pin} /> Vezi locația
                </button>
              </div>
              <div className="rail">
                <Act label={it.likes} onClick={() => like(idx)}>
                  <Ic svg={I.heartO} />
                </Act>
                <Act>
                  <Ic svg={I.share} />
                </Act>
                <div className="act" onClick={() => go('venue', { id: v.id })}>
                  <div
                    className="b"
                    style={{ padding: 0, overflow: 'hidden', border: '2px solid rgba(255,255,255,.5)', background: v.tone }}
                  />
                </div>
              </div>
            </div>
          );
        }

        const ev = it.ev;
        const v = (VEN as Record<string, Ev>)[ev.ven];
        const gal: string[] = ev.gallery || galFor(ev);
        const m = it.media;
        return (
          <div className="short" key={`e${ev.id}`}>
            {m === 'gallery' ? (
              <div className="gal">
                {gal.map((g, j) => (
                  <div className="g" key={j} style={{ background: g }} />
                ))}
              </div>
            ) : (
              <div className="media kb" style={{ background: bgv(ev) }} />
            )}
            <div className="grad" />
            <ShTop icon={m === 'video' ? I.play : m === 'gallery' ? I.layers : I.search} />
            {m === 'video' ? (
              <>
                <div className="vbar">
                  <i />
                </div>
                <span className="mtag">
                  <Ic svg={I.play} /> Video
                </span>
              </>
            ) : m === 'gallery' ? (
              <>
                <div className="gdots">
                  {gal.map((_, j) => (
                    <span key={j} className={j === 0 ? 'on' : ''} />
                  ))}
                </div>
                <span className="mtag">
                  <Ic svg={I.layers} /> {gal.length} foto
                </span>
              </>
            ) : (
              <span className="mtag">📷 Foto</span>
            )}

            <div className="info">
              <span className={cn('gpill', ev.type === 'experience' ? 'gsolid' : 'solid')}>
                {ev.type === 'experience' ? '⛰ Experiență' : ev.cat}
              </span>
              <div style={sx('font-size:26px;font-weight:700;letter-spacing:-.03em;margin-top:11px;line-height:1.05;text-shadow:0 2px 18px rgba(0,0,0,.6)')}>
                {ev.s}
              </div>
              <div className="cmeta" style={sx('margin-top:8px')}>
                <span className="i">
                  <Ic svg={I.cal} />
                  <span>{ev.d}</span>
                </span>
                <span className="i">
                  <Ic svg={I.pin} />
                  <span>{v.name}</span>
                </span>
              </div>
              <button className="shcta" onClick={() => go('event', { id: ev.id })} style={sx('margin-top:14px')}>
                <Ic svg={I.ticket} /> de la {money(ev.from)} lei
              </button>
            </div>

            <div className="rail">
              <Act label={it.likes} onClick={() => like(idx)}>
                <Ic svg={I.heartO} />
              </Act>
              <Act onClick={() => toggleSaved(ev.id)}>
                <Ic svg={I.save} />
              </Act>
              <Act>
                <Ic svg={I.share} />
              </Act>
              <div className="act" onClick={() => go('event', { id: ev.id })}>
                <div className="b" style={{ padding: 0, overflow: 'hidden', border: '2px solid rgba(255,255,255,.5)' }}>
                  <Raw html={poster(ev, '', 'width:100%;height:100%', undefined)} />
                </div>
              </div>
            </div>
          </div>
        );
      })}
    </div>
  );
}
