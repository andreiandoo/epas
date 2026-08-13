/* =========================================================
   DESCOPERIRE — port 1:1 din client-app.html:
     S.category (714) lista filtrata pe categorie, cu bara de filtre si panou
     S.search   (729) cautare cu istoric, trending si rezultate live
     S.artist   (830) profil de artist: social, top 10, videoclipuri, evenimente
     S.venue    (849) profil de locatie: statistici, harta, evenimente
   ========================================================= */
import { useEffect, useMemo, useRef, useState } from 'react';
import { Ic, cn, sx } from '../../../design/sx';
import { ART, ARTX, EV, I, SOCIC, SOCLIST, SORTLBL, VEN, bgv } from '../../../mock/prototype';
import type { UiEvent } from '../../../api/tenantClient';
import { EvRow } from '../cards';
import { BottomNav, BackTitle, CatalogLoading, DBar, MissingContent, SafeTop, SecH, TopBar } from '../kit';
import { useCatalogArtist, useCatalogEvents, useCatalogSearch, useCatalogVenue } from '../catalogData';
import { radarToUi, useRadarList } from '../radarData';
import { CAT_TO_FEED } from '../../../api/ticsRadar';
import { CityTag } from '../cityTag';
import { useNav } from '../nav';
import { useClient } from '../../../store/client';

type Ev = Record<string, any>;
const allEvents = () => Object.values(EV as Record<string, Ev>);

/* =========================================================
   S.category + INIT.category
   ========================================================= */
export function Category({ id }: { id?: string }) {
  const { go } = useNav();
  const cat = id || 'Concerte';
  const setCat = useClient((s) => s.setCat);
  const f = useClient((s) => s.catF);
  const setCatF = useClient((s) => s.setCatF);
  const resetCatF = useClient((s) => s.resetCatF);
  const [panelOpen, setPanelOpen] = useState(false);
  /** valoarea live a slider-ului, inainte de 'change' (ca in INIT.category) */
  const [priceLive, setPriceLive] = useState(f.maxPrice);

  useEffect(() => {
    setCat(cat);
  }, [cat, setCat]);
  useEffect(() => setPriceLive(f.maxPrice), [f.maxPrice]);

  /* Aceleasi doua surse ca pe Acasa: ale noastre intai, Radarul dupa.
     Categoria se cere SERVERULUI, nu se filtreaza in client — taxonomia scrie
     „Concert", „Concerte", „Concert live", iar o comparatie de siruri egale
     rata aproape tot si ecranul ramanea gol. */
  const mine = useCatalogEvents({ limit: 40, category: cat === 'Toate' ? undefined : cat });
  const { items: radarItems, loading: radarLoading } = useRadarList({
    limit: 24,
    /* `CAT_TO_FEED`, nu `CAT_TO_TYPE`: feed-ul si API-ul TICS folosesc
       vocabulare diferite („concerte" vs „concert"). Cu cel gresit, filtrul nu
       potrivea nimic si categoria aparea goala. */
    catKey: cat === 'Toate' ? undefined : CAT_TO_FEED[cat],
  });

  const loadingCat = mine.loading || radarLoading;

  const base = useMemo(() => {
    const ours = mine.items;
    const seen = new Set(ours.map((e) => String(e.s).toLowerCase()));
    const fromRadar = radarItems.filter((r) => !seen.has(r.s.toLowerCase())).map(radarToUi);

    /* NU se mai cade pe datasetul prototipului. O categorie fara evenimente
       reale arata acum goala, nu plina cu spectacole inventate: un ecran de
       „Concerte" care listeaza Coldplay la Cluj pe date fixe e mai rau decat
       unul gol — pare ca aplicatia are stoc si trimite omul intr-o fundatura. */
    return [...ours, ...fromRadar];
  }, [mine.items, radarItems]);
  const cities = useMemo(() => [...new Set(base.map((e) => e.city))], [base]);

  let list = base
    .filter((e) => e.from <= f.maxPrice)
    .filter((e) => !f.city || e.city === f.city)
    .filter((e) => !f.seated || e.seatmap);
  if (f.sort === 'price') list = [...list].sort((a, b) => a.from - b.from);
  else if (f.sort === 'rating') list = [...list].sort((a, b) => parseFloat(b.rat) - parseFloat(a.rat));

  const active = (f.city ? 1 : 0) + (f.seated ? 1 : 0) + (f.maxPrice < 500 ? 1 : 0);
  const nextSort = () => setCatF({ sort: f.sort === 'rec' ? 'price' : f.sort === 'price' ? 'rating' : 'rec' });

  return (
    <div className="grid" style={sx('min-height:100%')}>
      <TopBar
        below={
          <div className="filterbar" style={sx('padding-bottom:12px')}>
            <div className={cn('flt', active > 0 && 'on')} onClick={() => setPanelOpen((v) => !v)}>
              <Ic svg={I.slider} /> Filtre{active ? ' · ' + active : ''}
            </div>
            <div className="flt on" onClick={nextSort}>
              Sortează: {(SORTLBL as Record<string, string>)[f.sort]}
            </div>
            <div className={cn('flt', f.seated && 'on')} onClick={() => setCatF({ seated: !f.seated })}>
              Cu locuri
            </div>
            {cities.map((c) => (
              <div
                key={c}
                className={cn('flt', f.city === c && 'on')}
                onClick={() => setCatF({ city: f.city === c ? '' : c })}
              >
                {c}
              </div>
            ))}
          </div>
        }
      >
        <BackTitle
          title={cat}
          sub={`${list.length} din ${base.length} rezultate`}
          right={
            <div className="row" style={sx('gap:8px')}>
              <CityTag />
              <div className="icon-btn" onClick={() => go('search')}>
                <Ic svg={I.search} />
              </div>
            </div>
          }
        />
      </TopBar>

      {panelOpen ? (
        <div className="pad" style={sx('margin-top:12px')}>
          <div className="card" style={sx('padding:15px')}>
            <div className="between">
              <span className="label" style={sx('margin:0')}>
                Preț maxim
              </span>
              <span style={sx('font-weight:600;font-size:13px;color:var(--indigo-2)')}>
                {priceLive >= 500 ? 'oricât' : priceLive + ' lei'}
              </span>
            </div>
            <input
              type="range"
              min={20}
              max={500}
              step={10}
              value={priceLive}
              onChange={(e) => setPriceLive(+e.target.value)}
              onMouseUp={() => setCatF({ maxPrice: priceLive })}
              onTouchEnd={() => setCatF({ maxPrice: priceLive })}
              style={sx('width:100%;margin-top:8px;accent-color:var(--indigo)')}
            />
            <div className="between" style={sx('margin-top:14px')}>
              <span className="label" style={sx('margin:0')}>
                Doar cu locuri pe scaun
              </span>
              <div className={cn('toggle', f.seated && 'on')} onClick={() => setCatF({ seated: !f.seated })} />
            </div>
            <div className="row" style={sx('gap:10px;margin-top:16px')}>
              <button className="cta ghost" style={sx('padding:12px')} onClick={resetCatF}>
                Resetează
              </button>
              <button className="cta" style={sx('padding:12px')} onClick={() => setPanelOpen(false)}>
                Vezi {list.length} rezultate
              </button>
            </div>
          </div>
        </div>
      ) : null}

      {loadingCat && !list.length ? (
        <div className="pad" style={sx('margin-top:14px;display:flex;flex-direction:column;gap:11px')}>
          <div className="sk" style={sx('height:96px;border-radius:20px')} />
          <div className="sk" style={sx('height:96px;border-radius:20px')} />
        </div>
      ) : null}

      {!loadingCat && !list.length ? (
        <div className="pad" style={sx('margin-top:50px;text-align:center')}>
          <div style={sx('font-size:40px;opacity:.5')}>🔍</div>
          <div style={sx('font-weight:600;font-size:15px;margin-top:10px')}>Nimic în „{cat}" deocamdată</div>
          <div className="muted" style={sx('font-size:12.5px;margin-top:6px;line-height:1.5')}>
            {base.length
              ? 'Filtrele active nu lasă niciun rezultat. Încearcă să le lărgești.'
              : 'Încă nu sunt evenimente anunțate în această categorie.'}
          </div>
        </div>
      ) : null}

      {list.length ? (
        <div className="pad" style={sx('margin-top:16px;display:flex;flex-direction:column;gap:13px')}>
          {list.map((ev) => (
            <EvRow key={ev.id} ev={ev as UiEvent} />
          ))}
        </div>
      ) : (
        <div className="pad" style={sx('margin-top:52px;text-align:center')}>
          <div style={sx('font-size:46px;opacity:.5')}>🔎</div>
          <div style={sx('font-weight:600;font-size:15px;margin-top:12px')}>Niciun rezultat</div>
          <div className="muted" style={sx('font-size:12.5px;margin-top:4px')}>
            Încearcă să relaxezi filtrele
          </div>
          <button
            className="cta ghost"
            style={sx('width:auto;padding:11px 20px;margin:18px auto 0')}
            onClick={resetCatF}
          >
            Resetează filtrele
          </button>
        </div>
      )}
      <div style={sx('height:8px')} />
      <BottomNav active="" />
    </div>
  );
}

/* =========================================================
   S.search + INIT.search
   ========================================================= */
const RECENT = ['Coldplay', 'Salina Turda', 'Nordvale', 'Teatru'];
const TRENDING = ['Nordvale Festival 2026', 'Coldplay', 'Salina Turda', 'ATV Adventure', 'Smiley Live'];

export function Search() {
  const { back, go } = useNav();
  const [q, setQ] = useState('');
  const input = useRef<HTMLInputElement>(null);

  useEffect(() => {
    const t = setTimeout(() => input.current?.focus(), 350);
    return () => clearTimeout(t);
  }, []);

  const v = q.trim();

  /* Trei surse, in ordinea in care conteaza:
       1. catalogul propriu (evenimente, artisti, locatii) — de aici se cumpara;
       2. Radarul TICS — comparatie de preturi pe alte platforme;
       3. datasetul prototipului, DOAR cand primele doua n-au nimic si nici nu
          mai asteptam un raspuns; altfel ecranul ar parea gol o clipa.
     Cautarea din prototip filtra doar datasetul demo, deci orice termen real
     intorcea fie nimic, fie primele trei evenimente inventate — mai rau decat
     un raspuns gol, pentru ca parea un rezultat. */
  const mine = useCatalogSearch(v);
  const { items: radarHits, loading: radarLoading } = useRadarList({ limit: 10, search: v.length > 1 ? v : undefined });

  const catalogEvents = mine.data?.events ?? [];
  const catalogKeys = new Set(catalogEvents.map((e) => String(e.s).toLowerCase()));
  const radarEvents = v.length > 1 ? radarHits.filter((r) => !catalogKeys.has(r.s.toLowerCase())).map(radarToUi) : [];

  const results: UiEvent[] = [...catalogEvents, ...radarEvents];
  const artists = mine.data?.artists ?? [];
  const venues = mine.data?.venues ?? [];
  const searching = mine.loading || radarLoading;
  const empty = v.length > 1 && !searching && !results.length && !artists.length && !venues.length;

  return (
    <div className="grid" style={sx('min-height:100%')}>
      <SafeTop />
      <div className="row pad" style={sx('padding-top:8px;gap:11px')}>
        <div className="icon-btn" onClick={back}>
          <Ic svg={I.back} />
        </div>
        <div className="field" style={sx('flex:1')}>
          <span className="muted">
            <Ic svg={I.search} />
          </span>
          <input
            ref={input}
            value={q}
            onChange={(e) => setQ(e.target.value)}
            placeholder="Caută evenimente, artiști…"
          />
          <span style={sx('color:var(--indigo-2)')}>
            <Ic svg={I.slider} />
          </span>
        </div>
      </div>

      {!v ? (
        <div className="pad" style={sx('margin-top:18px')}>
          <div className="between">
            <div className="h2" style={sx('font-size:14px')}>
              Căutări recente
            </div>
            <span className="muted" style={sx('font-size:12px;font-weight:500')}>
              Șterge
            </span>
          </div>
          <div className="scroll-x" style={sx('padding:11px 0 0;margin:0')}>
            {RECENT.map((t) => (
              <button key={t} className="chip" onClick={() => setQ(t)}>
                {t} ✕
              </button>
            ))}
          </div>
          <div className="h2" style={sx('font-size:14px;margin-top:22px;margin-bottom:11px')}>
            În trend 🔥
          </div>
          <div style={sx('display:flex;flex-direction:column;gap:9px')}>
            {TRENDING.map((t, i) => (
              <div key={t} className="row" onClick={() => setQ(t)} style={sx('gap:12px;cursor:pointer')}>
                <span
                  style={{
                    width: 26,
                    textAlign: 'center',
                    fontWeight: 600,
                    color: i < 3 ? 'var(--indigo-2)' : 'var(--faint)',
                  }}
                >
                  {i + 1}
                </span>
                <div style={sx('flex:1;font-weight:500;font-size:14px')}>{t}</div>
                <span className="muted">
                  <Ic svg={I.search} />
                </span>
              </div>
            ))}
          </div>
        </div>
      ) : (
        <div className="pad" style={sx('margin-top:14px')}>
          <div className="scroll-x" style={sx('padding:0 0 12px;margin:0')}>
            <button className="chip ind on">Toate</button>
            <button className="chip">Evenimente</button>
            <button className="chip">Experiențe</button>
            <button className="chip">Artiști</button>
          </div>
          {artists.length ? (
            <>
              <div className="h2" style={sx('font-size:14px;margin-bottom:10px')}>
                Artiști
              </div>
              <div className="scroll-x" style={sx('padding:0 0 14px;margin:0')}>
                {artists.map((a) => (
                  <div
                    key={a.id}
                    onClick={() => go('artist', { id: a.id })}
                    style={sx('min-width:88px;text-align:center;cursor:pointer')}
                  >
                    <div style={{ width: 68, height: 68, borderRadius: 22, margin: '0 auto', background: a.bg }} />
                    <div style={sx('font-weight:500;font-size:12.5px;margin-top:7px')}>{a.name}</div>
                    <div style={sx('font-size:10.5px;color:var(--muted)')}>{a.role}</div>
                  </div>
                ))}
              </div>
            </>
          ) : null}

          {venues.length ? (
            <>
              <div className="h2" style={sx('font-size:14px;margin-bottom:10px')}>
                Locații
              </div>
              <div style={sx('display:flex;flex-direction:column;gap:9px;margin-bottom:16px')}>
                {venues.map((ven) => (
                  <div
                    key={ven.id}
                    className="listitem"
                    onClick={() => go('venue', { id: ven.id })}
                    style={sx('cursor:pointer;padding:10px')}
                  >
                    <div style={{ width: 40, height: 40, borderRadius: 13, flex: 'none', background: ven.bg }} />
                    <div style={sx('flex:1;min-width:0')}>
                      <div style={sx('font-weight:600;font-size:13.5px')}>{ven.name}</div>
                      <div className="muted" style={sx('font-size:11.5px')}>
                        {ven.city}
                      </div>
                    </div>
                    <span className="muted">
                      <Ic svg={I.arrow} />
                    </span>
                  </div>
                ))}
              </div>
            </>
          ) : null}

          {results.length ? (
            <div className="between" style={sx('margin-bottom:10px')}>
              <div className="h2" style={sx('font-size:14px')}>
                Evenimente
              </div>
              <CityTag />
            </div>
          ) : null}
          <div style={sx('display:flex;flex-direction:column;gap:11px')}>
            {results.map((ev) => (
              <EvRow key={`${ev.id}-${ev.s}`} ev={ev as UiEvent} />
            ))}
          </div>

          {searching && !results.length ? (
            <div style={sx('display:flex;flex-direction:column;gap:10px')}>
              <div className="sk" style={sx('height:86px;border-radius:20px')} />
              <div className="sk" style={sx('height:86px;border-radius:20px')} />
            </div>
          ) : null}

          {empty ? (
            <div className="muted" style={sx('font-size:12.5px;text-align:center;padding:26px 0;line-height:1.5')}>
              Nimic pentru „{v}".
              <br />
              Încearcă numele artistului, al locației sau al orașului.
            </div>
          ) : null}
        </div>
      )}
    </div>
  );
}

/* =========================================================
   S.artist
   ========================================================= */
export function Artist({ id }: { id?: string }) {
  const showToast = useClient((s) => s.showToast);
  /* Fara id ramane exemplul din prototip (asa intra din ecranele demo), dar un
     id NECUNOSCUT vine de la un short real si n-are ce arata aici — mai bine
     spunem asta decat sa afisam alt artist. */
  const demo = (ART as Record<string, Ev>)[id || 'coldplay'] as Ev | undefined;

  /* Fara id ramane exemplul din prototip (asa intra din ecranele demo). Un id
     necunoscut vine de la un short sau de la un eveniment real, deci se cere
     din catalog. */
  const live = useCatalogArtist(demo ? undefined : id);

  if (!demo && live.loading) return <CatalogLoading title="Artist" />;
  if (!demo && !live.data) return <MissingContent what="Artistul" />;

  const a = demo ?? (live.data as { rec: Ev }).rec;
  const isLive = !demo;

  /* ARTX tine melodii, clipuri si statistici sociale detaliate — un dataset
     fix al prototipului. Pentru un artist real n-avem asa ceva in API, iar
     imprumutate de la Coldplay ar fi pur si simplu false. Sectiunile care
     depind de el se ascund. */
  const x = (isLive ? undefined : (ARTX as Record<string, Ev>)[a.id]) as Ev | undefined;
  const upEv: Ev[] = isLive
    ? (live.data?.events ?? [])
    : (() => {
        const evs = allEvents().filter((e) => e.artists.includes(a.id));

        return evs.length ? evs : [(EV as Record<string, Ev>).coldplay];
      })();
  const socList = SOCLIST as [string, string][];
  const socIc = SOCIC as Record<string, string>;

  return (
    <div style={sx('min-height:100%;background:var(--bg)')}>
      <DBar
        title={a.name}
        right={
          <div className="icon-btn glass">
            <Ic svg={I.share} />
          </div>
        }
      />
      <div className="poster" style={{ background: a._bg ?? bgv(a), height: 228 }}>
        <div style={sx('position:absolute;inset:0;background:linear-gradient(180deg,rgba(0,0,0,.4),transparent 42%,var(--bg))')} />
        <div style={sx('position:absolute;left:0;right:0;bottom:-42px;text-align:center')}>
          <div
            style={{
              width: 104,
              height: 104,
              borderRadius: 34,
              margin: '0 auto',
              background: a._bg ?? a.tone,
              border: '4px solid var(--bg)',
              display: 'grid',
              placeItems: 'center',
              fontSize: '44px',
              boxShadow: 'var(--sh-p)',
            }}
          >
            {a._bg ? '' : a.g}
          </div>
        </div>
      </div>

      <div style={sx('text-align:center;margin-top:52px')}>
        <div style={sx('font-weight:600;font-size:22px;letter-spacing:-.02em')}>{a.name}</div>
        <div className="muted" style={sx('font-size:12.5px;margin-top:3px')}>
          {[x?.sub ?? a.role, a.fol ? `${a.fol} urmăritori` : null].filter(Boolean).join(' · ')}
        </div>
        <div className="row" style={sx('justify-content:center;gap:9px;margin-top:15px')}>
          <button className="cta" onClick={() => showToast('Urmărești ' + a.name)} style={sx('width:auto;padding:11px 28px')}>
            Urmărește
          </button>
          <button className="icon-btn">
            <Ic svg={I.bell} />
          </button>
        </div>
        {/* Doar retelele cu link real. Un rand de pictograme din care jumatate
            nu duc nicaieri e mai rau decat trei care duc. */}
        <div className="row" style={sx('justify-content:center;gap:10px;margin-top:15px')}>
          {socList
            .filter((s) => !isLive || a._links?.[s[0]])
            .map((s) => (
              <button
                key={s[1]}
                className="socbtn"
                onClick={() => (a._links?.[s[0]] ? window.open(a._links[s[0]], '_blank', 'noopener') : showToast(s[1]))}
              >
                <Ic svg={socIc[s[0]]} />
              </button>
            ))}
        </div>
      </div>

      <div className="scroll-x" style={sx('margin-top:18px')} hidden={!x}>
        {socList.map((s) => (
          <div key={s[1]} className="statpill">
            <Ic svg={socIc[s[0]]} />
            <div>
              <div style={sx('font-weight:700;font-size:14px;font-variant-numeric:tabular-nums')}>{x?.soc[s[0]]}</div>
              <div
                className="muted"
                style={sx('font-size:9px;font-weight:600;text-transform:uppercase;letter-spacing:.04em')}
              >
                {s[1]}
              </div>
            </div>
          </div>
        ))}
      </div>

      <div className="pad" style={sx('margin-top:22px')}>
        <div className="h2" style={sx('font-size:15px')}>
          Despre
        </div>
        <p className="muted" style={sx('font-size:13.5px;line-height:1.62;margin-top:8px')}>
          {a.bio || x?.bio || 'Artistul nu are încă o descriere.'}
        </p>
      </div>

      {/* Melodiile si clipurile vin din ARTX, un dataset fix al prototipului.
          Pentru un artist real n-avem asa ceva in API, iar imprumutate de la
          alt artist ar fi pur si simplu false — deci sectiunile dispar. */}
      <div className="pad" style={sx('margin-top:22px')} hidden={!x}>
        <div className="between">
          <div className="h2" style={sx('font-size:15px')}>
            Top 10 melodii
          </div>
          <span className="row" style={sx('gap:5px;color:#1DB954;font-size:11.5px;font-weight:600')}>
            <Ic svg={socIc.spotify} /> Spotify
          </span>
        </div>
        <div className="card" style={sx('margin-top:11px;padding:2px 14px')}>
          {((x?.songs ?? []) as [string, string][]).map((s, i) => (
            <div
              key={s[0]}
              className="between"
              onClick={() => showToast('▶ ' + s[0])}
              style={{
                padding: '11px 0',
                borderBottom: i < (x?.songs.length ?? 0) - 1 ? '1px solid var(--line)' : undefined,
                cursor: 'pointer',
              }}
            >
              <div className="row" style={sx('gap:13px;min-width:0')}>
                <span
                  style={{
                    width: 16,
                    textAlign: 'center',
                    fontWeight: 700,
                    color: i < 3 ? 'var(--green-2)' : 'var(--faint)',
                    fontSize: '13px',
                  }}
                >
                  {i + 1}
                </span>
                <div style={sx('min-width:0')}>
                  <div
                    style={sx('font-weight:600;font-size:13.5px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis')}
                  >
                    {s[0]}
                  </div>
                  <div className="muted" style={sx('font-size:10.5px')}>
                    {s[1]} redări
                  </div>
                </div>
              </div>
              <span style={sx('color:var(--muted)')}>
                <Ic svg={I.play} />
              </span>
            </div>
          ))}
        </div>
      </div>

      <div className="sec" hidden={!x}>
        <SecH
          icon={I.play}
          icbg="var(--indigo-soft)"
          iccol="var(--indigo-2)"
          title="Videoclipuri"
          sub={`${x?.videos.length ?? 0} clipuri`}
        />
        <div className="rail">
          {((x?.videos ?? []) as [string, string, string][]).map((v) => (
            <div key={v[0]} className="mcard" onClick={() => showToast('▶ ' + v[0])} style={sx('min-width:236px')}>
              <div className="cover" style={{ background: v[1], height: 146 }}>
                <span className="em">{v[2]}</span>
                <div className="scrim" />
                <div style={sx('position:absolute;inset:0;display:grid;place-items:center;z-index:3')}>
                  <div
                    style={sx('width:54px;height:54px;border-radius:50%;background:rgba(255,255,255,.92);display:grid;place-items:center;color:#141020')}
                  >
                    <Ic svg={I.play} />
                  </div>
                </div>
                <div className="btm">
                  <div className="ctitle" style={sx('font-size:14px')}>
                    {v[0]}
                  </div>
                </div>
              </div>
            </div>
          ))}
        </div>
      </div>

      <div className="sec">
        <SecH icon={I.cal} icbg="var(--indigo-soft)" iccol="var(--indigo-2)" title="Evenimente viitoare" sub={`${upEv.length} evenimente`} />
        <div className="pad" style={sx('display:flex;flex-direction:column;gap:13px')}>
          {upEv.map((ev) => (
            <EvRow key={ev.id} ev={ev as UiEvent} />
          ))}
        </div>
      </div>
      <div style={sx('height:10px')} />
      <BottomNav active="" />
    </div>
  );
}

/* =========================================================
   S.venue
   ========================================================= */
export function Venue({ id }: { id?: string }) {
  const { go } = useNav();
  const demo = (VEN as Record<string, Ev>)[id || 'arena'] as Ev | undefined;
  const live = useCatalogVenue(demo ? undefined : id);

  if (!demo && live.loading) return <CatalogLoading title="Locație" />;
  if (!demo && !live.data) return <MissingContent what="Locația" />;

  const v = demo ?? (live.data as { rec: Ev }).rec;
  const isLive = !demo;

  const list: Ev[] = isLive
    ? (live.data?.events ?? [])
    : (() => {
        const evs = allEvents().filter((e) => e.ven === v.id);

        return evs.length ? evs : [(EV as Record<string, Ev>).coldplay];
      })();

  return (
    <div style={sx('min-height:100%;background:var(--bg)')}>
      <DBar title={v.name} />
      <div className="poster" style={{ background: v._bg ?? bgv(v), height: 210, borderRadius: '0 0 28px 28px' }}>
        <div style={sx('position:absolute;inset:0;background:linear-gradient(180deg,rgba(0,0,0,.25),transparent 45%,rgba(11,9,18,.6))')} />
        <div style={sx('position:absolute;left:20px;bottom:18px;color:#fff')}>
          <div style={sx('font-size:22px;font-weight:600')}>{v.name}</div>
          <div style={sx('font-size:12.5px;opacity:.85')}>
            {v.addr} · {v.city}
          </div>
        </div>
      </div>

      <div className="pad" style={sx('margin-top:16px')}>
        <div className="row" style={sx('gap:10px')}>
          {([
            ['Capacitate', v.cap],
            ['Oraș', v.city],
            /* „4.8" era o valoare fixa in prototip. Pe o locatie reala punem
               nota Google, si doar cand exista — o nota inventata pe pagina
               unei sali adevarate ar fi o afirmatie falsa despre ea. */
            v._rating ? ['Rating', `${Number(v._rating).toFixed(1)} ⭐`] : null,
          ].filter(Boolean) as [string, string][]).map((s) => (
            <div key={s[0]} className="card" style={sx('flex:1;text-align:center;padding:12px')}>
              <div style={sx('font-weight:600;font-size:15px')}>{s[1]}</div>
              <div className="muted" style={sx('font-size:10px;font-weight:500;text-transform:uppercase;margin-top:2px')}>
                {s[0]}
              </div>
            </div>
          ))}
        </div>

        {/* Harta reala, cand stim coordonatele.

            OpenStreetMap, nu Google: embed-ul OSM e gratuit, fara cheie si
            fara cota — Google Maps JavaScript API se factureaza per incarcare
            de harta, iar asta ar fi un cost care creste cu fiecare deschidere
            de pagina de locatie. Butoanele de navigare de mai jos deschid
            aplicatia de harti de pe telefon, ceea ce nu costa nimic indiferent
            de furnizor.

            Fara coordonate ramane caseta decorativa din prototip: mai bine un
            substitut evident decat o harta centrata pe un loc gresit. */}
        {typeof v._lat === 'number' && typeof v._lng === 'number' ? (
          <div style={sx('height:150px;border-radius:18px;margin-top:16px;overflow:hidden;background:#0d0b16')}>
            <iframe
              title={`Harta · ${v.name}`}
              loading="lazy"
              style={{ width: '100%', height: '100%', border: 0, display: 'block' }}
              src={`https://www.openstreetmap.org/export/embed.html?bbox=${v._lng - 0.006}%2C${v._lat - 0.003}%2C${v._lng + 0.006}%2C${v._lat + 0.003}&layer=mapnik&marker=${v._lat}%2C${v._lng}`}
            />
          </div>
        ) : (
          <div
            style={sx('height:130px;border-radius:18px;margin-top:16px;background:linear-gradient(120deg,#141126,#0d0b16);position:relative;overflow:hidden')}
          >
            <div style={sx('position:absolute;inset:0;background-image:var(--grid);background-size:26px 26px;opacity:.7')} />
            <div
              style={sx('position:absolute;left:46%;top:42%;width:16px;height:16px;border-radius:50%;background:var(--indigo);border:3px solid var(--bg);box-shadow:var(--sh-p)')}
            />
          </div>
        )}

        <div className="row" style={sx('gap:10px;margin-top:12px')}>
          <button className="cta ghost" style={sx('padding:13px')} onClick={() => go('stay22', { id: v.id })}>
            <Ic svg={I.car} /> Direcții
          </button>
          <button className="cta ghost" style={sx('padding:13px')} onClick={() => go('stay22', { id: v.id })}>
            <Ic svg={I.bed} /> Cazare
          </button>
        </div>

        {v._desc ? (
          <>
            <div className="h2" style={sx('font-size:15px;margin-top:24px')}>
              Despre
            </div>
            <p
              className="muted"
              style={sx('font-size:13.5px;line-height:1.62;margin-top:8px;white-space:pre-line')}
            >
              {v._desc}
            </p>
          </>
        ) : null}

        <div className="h2" style={sx('font-size:15px;margin-top:24px;margin-bottom:12px')}>
          Evenimente aici
        </div>
        <div style={sx('display:flex;flex-direction:column;gap:13px')}>
          {list.map((ev) => (
            <EvRow key={ev.id} ev={ev as UiEvent} />
          ))}
          {!list.length ? (
            <div className="muted" style={sx('font-size:12.5px;padding:6px 0')}>
              Nu sunt evenimente anunțate aici deocamdată.
            </div>
          ) : null}
        </div>
      </div>
      <div style={sx('height:8px')} />
      <BottomNav active="" />
    </div>
  );
}
