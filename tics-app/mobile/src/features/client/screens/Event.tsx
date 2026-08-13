/* =========================================================
   PAGINA DE EVENIMENT — port 1:1 al lui S.event() (client-app.html, linia 742).

   Sectiuni, in ordinea din prototip:
     dbar peste hero (share + salvare) · poster 388px cu scrim si buton de
     video · data + locatie · prietenii care au bilete · artisti · Despre ·
     [experiente] produse & servicii · galerie cu lightbox · recenzii ·
     locatie · card Stay22 · dock cu pretul si CTA-ul
   ========================================================= */
import { useEffect } from 'react';
import { Ic, cn, sx } from '../../../design/sx';
import { ADDONS, ART, ATTENDED, EV, EVREVIEWS, I, VEN, bgv, money } from '../../../mock/prototype';
import { CatalogLoading, DBar, MissingContent } from '../kit';
import { useCatalogEvent } from '../catalogData';
import { useEventFriends } from '../friendsData';
import { useNav } from '../nav';
import { useClient } from '../../../store/client';
import { useLightbox } from '../lightbox';

type Ev = Record<string, any>;

export function Event({ id }: { id?: string }) {
  const { go } = useNav();
  const lb = useLightbox();
  const evId = useClient((s) => s.ev);
  const setEv = useClient((s) => s.setEv);
  const saved = useClient((s) => s.saved);
  const toggleSaved = useClient((s) => s.toggleSaved);

  const key = id || evId;
  const demo = (EV as Record<string, Ev>)[key];

  /* Datasetul prototipului acopera doar cateva id-uri inventate. Restul —
     tot ce vine din feed-ul de shorts sau din Radar — se cere de la catalogul
     real. Se interogheaza doar cand nu exista corespondent demo, ca fluxurile
     de prezentare sa ramana instant si offline. */
  const live = useCatalogEvent(demo ? undefined : key);

  useEffect(() => {
    if (demo && demo.id !== evId) setEv(demo.id);
    else if (live.data && key !== evId) setEv(key);
  }, [demo, live.data, key, evId, setEv]);

  /* Partea sociala: cine dintre prieteni merge + propria vizibilitate.
     Se cheama INAINTE de orice `return` conditionat — un hook chemat dupa o
     iesire timpurie se executa doar pe unele randari, iar React opreste tot
     componenta. Se vedea imediat: ecranul evenimentului ramanea alb.
     `demo ? null : key` in loc de `ev.id`: `ev` inca nu exista aici, iar
     evenimentele din prototip n-au corespondent pe server. */
  const social = useEventFriends(demo ? null : Number(key));

  if (!demo && live.loading) return <CatalogLoading title="Eveniment" />;
  if (!demo && !live.data) return <MissingContent what="Evenimentul" />;

  const ev = demo ?? (live.data as { ev: Ev }).ev;
  const isLive = !demo;

  const v = (isLive ? live.data?.venue : null) ?? (VEN as Record<string, Ev>)[ev.ven] ?? null;
  const liveArtists = isLive ? (live.data?.artists ?? []) : null;
  const attended = (ATTENDED as { ev: string }[]).some((a) => a.ev === ev.id);
  const addons = (ADDONS as Record<string, Ev[]>)[ev.id];
  const isExp = ev.type === 'experience';
  const isSaved = saved.includes(ev.id);

  const heartSvg = (
    <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
      <path d="M12 21s-8-5-8-11a4.5 4.5 0 0 1 8-2.9A4.5 4.5 0 0 1 20 10c0 6-8 11-8 11z" />
    </svg>
  );

  return (
    <div style={sx('min-height:100%;background:var(--bg);padding-bottom:2px')}>
      <DBar
        title={ev.t}
        right={
          <>
            <div className="icon-btn glass">
              <Ic svg={I.share} />
            </div>
            <div
              className="icon-btn glass"
              onClick={() => toggleSaved(ev.id)}
              style={isSaved ? { color: 'var(--red)' } : undefined}
            >
              {isSaved ? heartSvg : <Ic svg={I.save} />}
            </div>
          </>
        }
      />

      {/* Inaltimea: 80% din ecran cand avem AFISUL real, 388px cand nu avem.

          Afisele de eveniment sunt verticale (2:3 sau mai inguste). La 388px,
          `cover` taia din ele mai mult de jumatate — vedeai o banda din mijloc
          si nu-ti puteai da seama ce e pe afis. La 80vh, un 2:3 intra aproape
          intreg. Pentru evenimentele din Radar, care n-au imagine la noi si
          primesc un degrade, 80vh ar fi insemnat un ecran intreg de culoare —
          deci raman la inaltimea din prototip. */}
      <div
        className="poster"
        style={{
          background: ev._bg ?? bgv(ev),
          height: ev.poster ? 'min(65vh, 620px)' : 388,
          borderRadius: '0 0 30px 30px',
        }}
      >
        <div
          style={sx('position:absolute;inset:0;background:linear-gradient(180deg,rgba(0,0,0,.3),transparent 26%,rgba(11,9,18,.97))')}
        />
        {ev.video ? (
          <div style={sx('position:absolute;left:50%;top:150px;transform:translateX(-50%)')}>
            <div
              style={sx('width:58px;height:58px;border-radius:50%;background:rgba(255,255,255,.2);border:1px solid rgba(255,255,255,.4);backdrop-filter:blur(8px);display:grid;place-items:center;color:#fff')}
            >
              <Ic svg={I.play} />
            </div>
          </div>
        ) : null}
        <div style={sx('position:absolute;left:20px;right:20px;bottom:22px')}>
          <span
            className="badge"
            style={sx('background:rgba(255,255,255,.16);backdrop-filter:blur(8px);color:#fff')}
          >
            {isExp ? 'Experiență' : ev.cat}
            {ev.rat ? ` · ⭐ ${ev.rat}` : ''}
          </span>
          <div
            style={sx('font-size:23px;font-weight:600;letter-spacing:-.03em;margin-top:10px;line-height:1.12;text-wrap:balance')}
          >
            {ev.t}
          </div>
        </div>
      </div>

      <div className="pad" style={sx('margin-top:16px')}>
        <div className="row" style={sx('gap:22px')}>
          <div className="row" style={sx('gap:9px')}>
            <div className="icon-btn" style={sx('width:38px;height:38px')}>
              <Ic svg={I.cal} />
            </div>
            <div>
              <div style={sx('font-size:12.5px;font-weight:500')}>{ev.d}</div>
              <div style={sx('font-size:11px;color:var(--muted)')}>Ora {ev.time}</div>
            </div>
          </div>
          {v ? (
            <div className="row" style={sx('gap:9px')} onClick={() => go('venue', { id: v.id })}>
              <div className="icon-btn" style={sx('width:38px;height:38px')}>
                <Ic svg={I.pin} />
              </div>
              <div>
                <div style={sx('font-size:12.5px;font-weight:500')}>{v.name}</div>
                <div style={sx('font-size:11px;color:var(--indigo-2)')}>Vezi locația ›</div>
              </div>
            </div>
          ) : null}
        </div>

        {/* PRIETENII care merg — date reale, nu iniţialele inventate din
            prototip. Apar doar cei care au ales să se ştie: serverul filtrează
            după regula lor generală şi după excepţia pe acest eveniment, iar
            aplicaţia nu are cum să vadă pe altcineva.

            Sub ele, propriul comutator: e locul firesc să decizi „aici vreau
            să se ştie", pentru că exact aici te gândeşti la evenimentul ăsta. */}
        {social.data && social.data.count > 0 ? (
          <div className="listitem" style={sx('margin-top:18px')}>
            <div className="row" style={sx('margin-right:2px')}>
              {social.data.friends.slice(0, 4).map((fr, i) => (
                <div
                  key={fr.id}
                  title={fr.name}
                  style={{
                    width: 30,
                    height: 30,
                    borderRadius: '50%',
                    background: fr.avatar
                      ? `url('${fr.avatar}') center/cover, #14101f`
                      : 'linear-gradient(135deg,var(--indigo-2),var(--indigo-4))',
                    display: 'grid',
                    placeItems: 'center',
                    fontSize: '10px',
                    fontWeight: 600,
                    color: '#fff',
                    border: '2px solid var(--surface-solid)',
                    marginLeft: i ? -10 : 0,
                  }}
                >
                  {fr.avatar
                    ? ''
                    : fr.name
                        .split(/\s+/)
                        .slice(0, 2)
                        .map((w) => w[0]?.toUpperCase() ?? '')
                        .join('')}
                </div>
              ))}
            </div>
            <div style={sx('flex:1;font-size:12.5px;font-weight:600;color:var(--ink-2)')}>
              {social.data.count === 1 ? (
                <>
                  <b>{social.data.friends[0]?.name}</b> are bilet
                </>
              ) : (
                <>
                  <b>{social.data.count} prieteni</b> au deja bilete
                </>
              )}
            </div>
          </div>
        ) : null}

        {social.data ? (
          <div
            className="listitem"
            onClick={() => void social.toggle()}
            style={sx('margin-top:10px;cursor:pointer')}
          >
            <div
              style={sx('width:38px;height:38px;border-radius:12px;background:var(--indigo-soft);color:var(--indigo-2);display:grid;place-items:center;font-size:17px')}
            >
              👥
            </div>
            <div style={sx('flex:1;min-width:0')}>
              <div style={sx('font-weight:500;font-size:13.5px')}>Arată prietenilor că merg</div>
              <div className="muted" style={sx('font-size:11px;margin-top:1px')}>
                Doar la acest eveniment
              </div>
            </div>
            <div className={cn('toggle', social.data.visible && 'on')} />
          </div>
        ) : null}

        {ev.artists?.length ? (
          <>
            <div className="h2" style={sx('margin-top:22px;font-size:15px')}>
              Artiști
            </div>
            <div className="scroll-x" style={sx('margin-top:11px;padding:0')}>
              {(liveArtists ?? (ev.artists as string[]).map((aid) => (ART as Record<string, Ev>)[aid])).map((a: Ev) => {
                const aid = a.id;
                return (
                  <div
                    key={aid}
                    onClick={() => go('artist', { id: aid })}
                    style={sx('min-width:90px;text-align:center;cursor:pointer')}
                  >
                    <div
                      style={{
                        width: 72,
                        height: 72,
                        borderRadius: 24,
                        margin: '0 auto',
                        background: a._bg ?? a.tone,
                        display: 'grid',
                        placeItems: 'center',
                        fontSize: '28px',
                      }}
                    >
                      {a._bg ? '' : a.g}
                    </div>
                    <div style={sx('font-weight:500;font-size:12.5px;margin-top:7px')}>{a.name}</div>
                    <div style={sx('font-size:10.5px;color:var(--muted)')}>{a.role}</div>
                  </div>
                );
              })}
            </div>
          </>
        ) : null}

        <div className="h2" style={sx('margin-top:22px;font-size:15px')}>
          Despre
        </div>
        {/* Descrierea reala cand exista. Textul generic de mai jos e din
            prototip si ramane doar pentru evenimentele demo — pus sub un
            eveniment real, ar fi o descriere inventata. */}
        <p style={sx('color:var(--ink-2);font-size:13.5px;line-height:1.62;margin-top:8px;white-space:pre-line')}>
          {isLive
            ? (live.data?.description ??
              'Organizatorul nu a adăugat încă o descriere pentru acest eveniment.')
            : isExp
              ? 'O experiență de neuitat, cu ghizi dedicați și tot ce ai nevoie inclus. Alegi data care ți se potrivește și adaugi serviciile dorite.'
              : 'Un spectacol care îmbină energie, atmosferă și un show de neuitat. Fie că ești fan de mult timp sau descoperi acum, aceasta e experiența ta.'}
        </p>

        {isExp && addons ? (
          <>
            <div className="between" style={sx('margin-top:24px')}>
              <div className="h2" style={sx('font-size:15px')}>
                Produse & servicii
              </div>
              <span className="muted" style={sx('font-size:11px;font-weight:600')}>
                le adaugi la bilet
              </span>
            </div>
            <div style={sx('margin-top:11px;display:flex;flex-direction:column;gap:10px')}>
              {addons.map((a) => (
                <div
                  key={a.n}
                  className="listitem"
                  onClick={() => go('tickettypes')}
                  style={sx('cursor:pointer;padding:12px')}
                >
                  <div className="iconbadge" style={sx('background:var(--surface-3)')}>
                    {a.ic}
                  </div>
                  <div style={sx('flex:1;min-width:0')}>
                    <div style={sx('font-weight:600;font-size:13.5px')}>{a.n}</div>
                    <div className="metaline" style={sx('margin-top:3px')}>
                      <span>{a.d}</span>
                      {a.period ? (
                        <span className="chip-mini" style={sx('padding:2px 7px')}>
                          pe perioadă
                        </span>
                      ) : null}
                    </div>
                  </div>
                  <div style={sx('text-align:right;flex:none')}>
                    <div style={sx('font-weight:700;color:var(--indigo-2);font-size:14px')}>{a.p} lei</div>
                    <div className="muted" style={sx('font-size:9.5px;font-weight:600')}>
                      adaugă +
                    </div>
                  </div>
                </div>
              ))}
            </div>
          </>
        ) : null}

        <div className="between" style={sx('margin-top:24px')} hidden={!ev.gallery.length}>
          <div className="h2" style={sx('font-size:15px')}>
            Galerie
          </div>
          <span className="muted" style={sx('font-size:11px;font-weight:600')}>
            {ev.gallery.length}
            {ev.video ? '+video' : ''} · atinge pentru mărire
          </span>
        </div>
        <div className="scroll-x gallery" style={sx('margin-top:11px;padding:0')}>
          {(ev.gallery as string[]).map((g, i) => (
            <div
              key={i}
              className="galtile"
              onClick={() => lb.open(ev.gallery, i, ev.g)}
              style={{
                minWidth: 150,
                height: 108,
                borderRadius: 16,
                background: g,
                boxShadow: 'var(--sh)',
                cursor: 'pointer',
                position: 'relative',
              }}
            >
              <span
                style={sx('position:absolute;right:8px;bottom:8px;width:26px;height:26px;border-radius:50%;background:rgba(10,7,20,.5);backdrop-filter:blur(6px);display:grid;place-items:center;color:#fff')}
              >
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.4" strokeLinecap="round">
                  <path d="M15 3h6v6M9 21H3v-6M21 3l-7 7M3 21l7-7" />
                </svg>
              </span>
            </div>
          ))}
          {ev.video ? (
            <div
              className="galtile"
              onClick={() => lb.open(ev.gallery, 0, ev.g)}
              style={{
                minWidth: 150,
                height: 108,
                borderRadius: 16,
                background: ev.tone,
                display: 'grid',
                placeItems: 'center',
                color: '#fff',
                cursor: 'pointer',
              }}
            >
              <div style={sx('width:44px;height:44px;border-radius:50%;background:rgba(255,255,255,.22);display:grid;place-items:center')}>
                <Ic svg={I.play} />
              </div>
            </div>
          ) : null}
        </div>

        {/* Recenziile din prototip sunt un dataset fix. Pe un eveniment real ar
            fi pareri inventate despre el, deci sectiunea dispare pana cand
            exista un endpoint de recenzii pe eveniment. */}
        <div className="between" style={sx('margin-top:24px')} hidden={isLive}>
          <div className="h2" style={sx('font-size:15px')}>
            Recenzii
          </div>
          <span className="row" style={sx('gap:5px;color:var(--amber);font-size:12.5px;font-weight:600')}>
            <Ic svg={I.star} /> {ev.rat} · {(EVREVIEWS as unknown[]).length} recenzii
          </span>
        </div>
        <div className="scroll-x" style={sx('margin-top:11px;padding:0')} hidden={isLive}>
          {(EVREVIEWS as [string, string, number, string, string][]).map((r) => (
            <div key={r[0] + r[4]} className="card" style={sx('min-width:252px;padding:13px')}>
              <div className="row" style={sx('gap:9px')}>
                <div
                  style={sx('width:34px;height:34px;border-radius:50%;background:linear-gradient(135deg,var(--indigo-2),var(--indigo-4));display:grid;place-items:center;color:#fff;font-weight:600;font-size:11px')}
                >
                  {r[1]}
                </div>
                <div style={sx('flex:1')}>
                  <div style={sx('font-weight:600;font-size:12.5px')}>{r[0]}</div>
                  <div style={sx('color:var(--amber);font-size:10px;letter-spacing:1px')}>
                    {'★'.repeat(r[2])}
                    {'☆'.repeat(5 - r[2])}
                  </div>
                </div>
                <span className="muted" style={sx('font-size:9.5px')}>
                  {r[4]}
                </span>
              </div>
              <p className="muted" style={sx('font-size:12px;line-height:1.5;margin-top:9px')}>
                {r[3]}
              </p>
            </div>
          ))}
        </div>

        {attended ? (
          <button className="cta ghost" onClick={() => go('review', { id: ev.id })} style={sx('margin-top:13px;padding:12px')}>
            <Ic svg={I.star} /> Lasă o recenzie
          </button>
        ) : (
          <div className="listitem" style={sx('margin-top:13px;background:var(--surface-2);border-style:dashed')}>
            <span style={sx('font-size:16px')}>🎟️</span>
            <div className="muted" style={sx('font-size:11.5px;flex:1')}>
              Poți lăsa o recenzie după ce participi la {isExp ? 'experiență' : 'eveniment'}.
            </div>
          </div>
        )}

        <div className="h2" style={sx('margin-top:24px;font-size:15px')} hidden={!v}>
          Locație
        </div>
        <div
          className="listitem"
          hidden={!v}
          onClick={() => v && go('venue', { id: v.id })}
          style={sx('margin-top:11px;cursor:pointer;padding:12px')}
        >
          <div className="iconbadge" style={{ background: v?.tone }}>
            <Ic svg={I.pin} />
          </div>
          <div style={sx('flex:1;min-width:0')}>
            <div style={sx('font-weight:600;font-size:13.5px')}>{v?.name}</div>
            <div className="metaline" style={sx('margin-top:3px')}>
              {v?.addr} · {v?.city}
            </div>
          </div>
          <span className="muted">
            <Ic svg={I.arrow} />
          </span>
        </div>
        <div
          className="listitem"
          hidden={!v}
          onClick={() => v && go('stay22', { id: v.id })}
          style={sx('background:linear-gradient(135deg,#0f766e,#12b3a6);border:0;margin-top:11px;color:#fff;cursor:pointer;padding:12px')}
        >
          <div className="iconbadge" style={sx('background:rgba(255,255,255,.18)')}>
            <Ic svg={I.bed} />
          </div>
          <div style={sx('flex:1')}>
            <div style={sx('font-weight:600;font-size:13.5px')}>Cazare & cum ajung</div>
            <div style={sx('font-size:11.5px;opacity:.85')}>Hartă cu hoteluri lângă {v?.name}</div>
          </div>
          <Ic svg={I.arrow} />
        </div>
      </div>

      <div className="dock">
        <div className="row" style={sx('gap:14px;align-items:center')}>
          <div>
            <div
              style={sx('font-size:10px;color:var(--muted);font-weight:600;letter-spacing:.06em;text-transform:uppercase')}
            >
              Preț de la
            </div>
            <div style={sx('font-weight:700;font-size:20px;margin-top:1px')}>
              {ev.from ? (
                <>
                  {money(ev.from)} <span style={sx('font-size:13px;color:var(--muted);font-weight:600')}>lei</span>
                </>
              ) : (
                <span style={sx('font-size:15px;color:var(--muted);font-weight:600')}>—</span>
              )}
            </div>
          </div>
          <button className="cta" onClick={() => go(isExp ? 'expdate' : 'tickettypes')} style={sx('flex:1')}>
            {isExp ? 'Alege data' : 'Alege bilete'} <Ic svg={I.arrow} />
          </button>
        </div>
      </div>
    </div>
  );
}
