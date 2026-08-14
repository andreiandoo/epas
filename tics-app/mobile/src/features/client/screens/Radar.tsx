/* =========================================================
   RADAR & CALENDAR — port 1:1 din client-app.html:
     S.ticslist   (1147) lista Radar din toata piata
     S.ticsoffers  (800) detaliu cu ofertele marketplace-urilor
     S.calendar   (1160) grila lunii + evenimentele zilei selectate

   Datele sunt REALE: vin din TICS Radar (app.tics.ro) prin api/ticsRadar.
   Structura si stilurile raman cele din prototip; se schimba doar sursa.
   ========================================================= */
import { useEffect, useState } from 'react';
import { Ic, cn, sx } from '../../../design/sx';
import { ART, CAL_DAY, CAL_DOTS, I, bgv } from '../../../mock/prototype';
import { RadarCard } from '../cards';
import { BottomNav, DBar, SafeTop, SecH, TopBar } from '../kit';
import { useNav } from '../nav';
import { useClient } from '../../../store/client';
import { useLightbox } from '../lightbox';
import { fmtK, useRadarCategories, useRadarCities, useRadarDay, useRadarEvent, useRadarList, useRadarMonth, useRadarStats } from '../radarData';
import { CatCard, poolsFromCategories } from '../catCard';
import { CAT_TO_TYPE, GENRE_OPTIONS, TYPE_OPTIONS, type RadarItem } from '../../../api/ticsRadar';
import { PickerChip, PickerSheet, type Option } from '../picker';
import { lookupCatalogVenue } from '../../../api/catalog';

type Ev = Record<string, any>;

/** Cat se economiseste, in medie, fata de mediana pietei. */
function avgSaving(items: RadarItem[]): number | null {
  const multi = items.filter((t) => t.offers.length > 1);
  if (!multi.length) return null;
  const pct = multi.map((t) => {
    const prices = t.offers.map((o) => o[1]);
    const min = Math.min(...prices);
    const max = Math.max(...prices);
    return max > 0 ? 1 - min / max : 0;
  });
  return Math.round((pct.reduce((a, b) => a + b, 0) / pct.length) * 100);
}

/* =========================================================
   S.ticslist
   ========================================================= */
const PAGE = 12;

/** Ziua aleasa in Calendar, formatata pentru subtitlu. */
const dayLabel = (day?: number) => {
  if (day === undefined) return null;
  const d = new Date(day);
  return `${d.getUTCDate()} ${MONTHS_FULL[d.getUTCMonth()]}`;
};

export function TicsList({ cat, type: typeArg, catKey, day }: { cat?: string; type?: string; catKey?: string; day?: number }) {
  const { go, back, stack } = useNav();
  const city = useClient((s) => s.city);
  const setCity = useClient((s) => s.setCity);
  const picked = useClient((s) => s.cities);
  const toggleCity = useClient((s) => s.toggleCity);
  const setCities = useClient((s) => s.setCities);
  const f = useClient((s) => s.radarF);
  const setRadarF = useClient((s) => s.setRadarF);
  const resetRadarF = useClient((s) => s.resetRadarF);
  const cities = useRadarCities();

  /* Panoul complet de filtre: chip-ul "Toate" il deschide. */
  const [panel, setPanel] = useState(false);

  /* Ecran-radacina (intrat din bara) vs. ecran-copil (o categorie, o zi din
     calendar). Difera antetul si prezenta gridului de categorii. */
  const root = !cat && stack.length <= 1;

  /* Categoriile reale, pentru „Alege un vibe". Se arata patru, restul la
     cerere: toate 12 deodata impingeau lista de evenimente cu ~1100px in jos,
     adica exact continutul pentru care ai deschis Radarul. */
  const cats = useRadarCategories(undefined, picked);
  const [allCats, setAllCats] = useState(false);
  /* Categoriile fara niciun eveniment nu se mai arata.

     Se afisau toate 22, „ca pe site", cu contorul pe 0 — iar dupa ce contorul
     a inceput sa tina cont de orasul ales, jumatate din grila devenea un sir
     de carduri care nu duc nicaieri. Un card e o promisiune ca ai ce gasi. */
  const pools = poolsFromCategories(cats).filter((c) => c.count > 0);

  const [sheet, setSheet] = useState<'city' | 'type' | 'genre' | null>(null);
  const [shown, setShown] = useState(PAGE);

  /* "Alege un vibe" intra fie cu o categorie, fie direct cu event_type. */
  const type = typeArg ?? (cat ? CAT_TO_TYPE[cat] : undefined) ?? f.type ?? undefined;
  const { items, loading, hasMore } = useRadarList({
    limit: shown,
    cities: picked,
    catKey,
    type: type || undefined,
    genre: f.genre || undefined,
    day,
    when: day === undefined ? f.when : undefined,
    maxPrice: f.maxPrice || undefined,
    scarce: f.scarce || undefined,
  });

  const live = useRadarStats();
  const saving = avgSaving(items);
  const noFilter = f.when === 'all' && !f.maxPrice && !f.scarce && !f.genre && !city;
  const activeCount = (f.when !== 'all' ? 1 : 0) + (f.maxPrice ? 1 : 0) + (f.scarce ? 1 : 0) + (f.genre ? 1 : 0) + (city ? 1 : 0);
  const cityOptions: Option[] = [['', 'Toată România'], ...cities.map((c) => [c, c] as Option)];
  const labelOf = (opts: Option[], v: string, fb: string) => opts.find((o) => o[0] === v)?.[1] ?? fb;

  const stats: [string, string][] = [
    [live ? fmtK(live.liveEvents) : '3.2k', 'evenimente'],
    [live ? String(live.platforms) : '20', 'platforme'],
    [saving !== null ? `−${saving}%` : '−22%', 'sub medie'],
  ];

  const subtitle = [dayLabel(day), city || 'Din toată România'].filter(Boolean).join(' · ');

  /* Cum se numeste, in cuvinte, zona in care cauti. Peste doua orase, numele
     lor n-ar mai incapea pe un rand de telefon. */
  const whereLabel =
    picked.length === 0
      ? 'România'
      : picked.length <= 2
        ? picked.join(' și ')
        : `${picked[0]} +${picked.length - 1}`;

  /* Ce restrange lista chiar acum, in cuvinte. Orasul e primul: e filtrul cel
     mai des uitat, fiindca se alege din alt ecran. */
  const active = [
    picked.length ? (picked.length === 1 ? `orașul ${picked[0]}` : `${picked.length} orașe`) : null,
    cat ? `categoria ${cat}` : null,
    f.genre ? `genul ${labelOf(GENRE_OPTIONS, f.genre, f.genre)}` : null,
    f.when === 'today' ? 'azi' : f.when === 'weekend' ? 'weekend' : null,
    f.maxPrice ? `sub ${f.maxPrice} lei` : null,
    f.scarce ? 'aproape sold-out' : null,
    dayLabel(day),
  ].filter(Boolean) as string[];

  return (
    <div className="grid" style={sx('min-height:100%')}>
      {root ? (
        /* Intrat din bara de jos, Radarul e ecran-radacina: acelasi antet ca
           „Biletele mele" si „Acasă" (eyebrow + h1 mare). Purta antetul mic de
           ecran-copil, cu titlu de 15px, si iesea din rand fata de restul
           aplicatiei — plus o sageata de back care n-avea unde sa duca. */
        <div className="stickytop">
          <SafeTop />
          <div className="hrow">
            <div style={sx('min-width:0')}>
              {/* Titlul spune ce vezi ACUM: se schimba odata cu orasele alese,
                  nu ramane „România" cand tu cauti in Ploiesti. */}
              <div className="eyebrow">Orice eveniment din {whereLabel}</div>
              <h1 className="h1" style={sx('font-size:23px;margin-top:2px')}>
                Radar
              </h1>
            </div>
            <div className="icon-btn" onClick={() => go('calendar')}>
              <Ic svg={I.cal} />
            </div>
          </div>

          {/* Selectorul de orase.

              Era un chip mic, lipit de titlu, care arata a eticheta. Acum e un
              rand propriu, cu ac de harta, orasele alese si un semn clar ca se
              deschide o lista. Se pot alege MAI MULTE: locuiesti intre doua
              orase sau urmaresti un turneu, iar pana acum trebuia sa cauti
              de doua ori. */}
          <button
            className="cityline"
            onClick={() => setSheet('city')}
            aria-label="Alege orașele"
          >
            <span className="cityline-ic">
              <Ic svg={I.pin} />
            </span>
            <span className="cityline-txt">{whereLabel}</span>
            {picked.length ? <span className="cityline-n">{picked.length}</span> : null}
            <span className="cityline-caret" aria-hidden="true">
              ⌄
            </span>
          </button>

          {dayLabel(day) ? (
            <div className="muted" style={sx('font-size:11.5px;margin-top:6px')}>
              {dayLabel(day)}
            </div>
          ) : null}
        </div>
      ) : (
        <TopBar>
          <div className="row" style={sx('gap:12px')}>
            <div className="icon-btn" onClick={back}>
              <Ic svg={I.back} />
            </div>
            <div>
              <div className="h2">{cat ?? 'Radar'}</div>
              <div className="muted" style={sx('font-size:11.5px')}>
                {subtitle}
              </div>
            </div>
          </div>
          <div className="icon-btn" onClick={() => go('calendar')}>
            <Ic svg={I.cal} />
          </div>
        </TopBar>
      )}

      <div className="pad" style={sx('margin-top:12px')}>
        <div
          style={sx('border-radius:22px;overflow:hidden;background:radial-gradient(120% 130% at 100% 0%,rgba(45,214,238,.18),transparent 55%),linear-gradient(140deg,#10222b,#0b1420);border:1px solid rgba(45,214,238,.28);padding:17px 16px;position:relative')}
        >
          <div style={sx('position:absolute;right:-6px;top:-8px;font-size:78px;opacity:.14')}>📡</div>
          <div
            className="row"
            style={sx('gap:7px;color:var(--cyan);font-size:11px;font-weight:700;letter-spacing:.12em;text-transform:uppercase')}
          >
            <span className="livedot" />
            Radar live
          </div>
          <div style={sx('font-size:17px;font-weight:600;margin-top:8px;letter-spacing:-.02em')}>
            Cel mai bun preț din toată piața
          </div>
          <div className="row" style={sx('gap:20px;margin-top:14px')}>
            {stats.map((s) => (
              <div key={s[1]}>
                <div style={sx('font-size:19px;font-weight:700;color:var(--cyan);font-variant-numeric:tabular-nums')}>
                  {s[0]}
                </div>
                <div
                  className="muted"
                  style={sx('font-size:9.5px;font-weight:600;text-transform:uppercase;letter-spacing:.04em;margin-top:1px')}
                >
                  {s[1]}
                </div>
              </div>
            ))}
          </div>
        </div>
      </div>

      {/* ---------- ALEGE UN VIBE ----------
          Gridul de categorii statea doar in „Explorează", iar ecranul acela
          nu mai are intrare in bara de jos de cand Radarul i-a luat locul:
          categoriile ajunsesera de negasit. Locul lor firesc e aici — Radarul
          E rasfoirea pietei, categoriile sunt felul in care o rasfoiesti. */}
      {root && pools.length ? (
        <div className="sec">
          <SecH
            icon="🧭"
            icbg="var(--surface-3)"
            iccol="var(--ink)"
            title="Alege un vibe"
            sub="Tot ce se întâmplă, pe categorii"
            more={allCats ? undefined : ['Toate', () => setAllCats(true)]}
          />
          <div className="pad">
            <div style={sx('display:grid;grid-template-columns:1fr 1fr;gap:14px')}>
              {(allCats ? pools : pools.slice(0, 4)).map((c) => (
                <CatCard key={c.name} c={c} />
              ))}
            </div>
            {!allCats && pools.length > 4 ? (
              <button className="cta ghost" style={sx('margin-top:12px;padding:12px')} onClick={() => setAllCats(true)}>
                Vezi toate categoriile ({pools.length})
              </button>
            ) : null}
          </div>
        </div>
      ) : null}

      {/* Linie de despartire: gridul de categorii si lista de evenimente sunt
          doua lucruri diferite, iar fara nimic intre ele cardurile pareau
          primele rezultate ale listei. */}
      {root && pools.length ? <div className="secsplit" /> : null}

      <div className="filterbar" style={sx('margin-top:14px')}>
        <div className={cn('flt', (panel || !noFilter) && 'on')} onClick={() => setPanel((v) => !v)}>
          <Ic svg={I.slider} /> Toate{activeCount ? ` · ${activeCount}` : ''}
        </div>
        <div
          className={cn('flt', f.when === 'today' && 'on')}
          onClick={() => setRadarF({ when: f.when === 'today' ? 'all' : 'today' })}
        >
          <Ic svg={I.cal} /> Azi
        </div>
        <div
          className={cn('flt', f.when === 'weekend' && 'on')}
          onClick={() => setRadarF({ when: f.when === 'weekend' ? 'all' : 'weekend' })}
        >
          Weekend
        </div>
        <div
          className={cn('flt', f.maxPrice > 0 && 'on')}
          onClick={() => setRadarF({ maxPrice: f.maxPrice ? 0 : 100 })}
        >
          <Ic svg={I.tag} /> Sub 100 lei
        </div>
        <div className={cn('flt', f.scarce && 'on')} onClick={() => setRadarF({ scarce: !f.scarce })}>
          🔥 Aproape sold-out
        </div>
      </div>

      {panel ? (
        <div className="pad" style={sx('margin-top:12px')}>
          <div className="card" style={sx('padding:15px')}>
            <div className="between" style={sx('margin-bottom:10px')}>
              <span className="label" style={sx('margin:0')}>
                Toate filtrele
              </span>
              <button className="chip" onClick={() => { resetRadarF(); setCities([]); setCity(''); }} style={sx('padding:5px 11px;font-size:11px')}>
                Resetează
              </button>
            </div>
            <div className="scroll-x" style={sx('padding:2px 0;margin:0 -4px')}>
              <PickerChip icon="📍" label={city || 'Toată România'} active={!!city} onClick={() => setSheet('city')} />
              <PickerChip
                icon="🎫"
                label={labelOf(TYPE_OPTIONS, f.type ?? '', 'Toate tipurile')}
                active={!!f.type}
                onClick={() => setSheet('type')}
              />
              <PickerChip
                icon="🎵"
                label={f.genre ? labelOf(GENRE_OPTIONS, f.genre, f.genre) : 'Toate genurile'}
                active={!!f.genre}
                onClick={() => setSheet('genre')}
              />
            </div>
            <div className="muted" style={sx('font-size:11px;margin-top:10px;line-height:1.5')}>
              Chip-urile de sus filtrează după dată, preț și stoc. Aici alegi oraș, tip și gen.
            </div>
          </div>
        </div>
      ) : null}

      {sheet === 'city' ? (
        <PickerSheet
          title="Alege orașele"
          options={cityOptions}
          value={picked}
          multiple
          onPick={(v) => (v === '' ? setCities([]) : toggleCity(v))}
          onClose={() => setSheet(null)}
          searchable
          searchPlaceholder="Caută orașul"
        />
      ) : null}
      {sheet === 'type' ? (
        <PickerSheet
          title="Tipul evenimentului"
          options={TYPE_OPTIONS}
          value={f.type ?? ''}
          onPick={(t) => setRadarF({ type: t })}
          onClose={() => setSheet(null)}
        />
      ) : null}
      {sheet === 'genre' ? (
        <PickerSheet
          title="Genul muzical"
          options={GENRE_OPTIONS}
          value={f.genre ?? ''}
          onPick={(g) => setRadarF({ genre: g })}
          onClose={() => setSheet(null)}
        />
      ) : null}

      <div className="pad" style={sx('margin-top:16px;display:flex;flex-direction:column;gap:14px')}>
        {items.map((t, i) => (
          <RadarCard key={t.id + i} t={t as never} st="width:100%" />
        ))}

        {/* schelete cat timp se incarca — inainte se vedeau evenimente demo */}
        {loading && !items.length
          ? Array.from({ length: 4 }).map((_, i) => (
              <div
                key={`sk${i}`}
                className="card"
                style={sx('height:212px;border-radius:22px;background:var(--surface-2);opacity:.6')}
              />
            ))
          : null}

        {hasMore ? (
          <button className="cta ghost" onClick={() => setShown((n) => n + PAGE)} style={sx('padding:13px')}>
            {loading ? 'Se încarcă…' : 'Încarcă mai multe'}
          </button>
        ) : null}
        {!items.length ? (
          <div className="card" style={sx('padding:22px;text-align:center')}>
            <div style={sx('font-size:30px')}>{loading ? '📡' : '🤷'}</div>
            <div className="h2" style={sx('font-size:14px;margin-top:8px')}>
              {loading ? 'Caut prețuri…' : 'Nimic pentru filtrele astea'}
            </div>
            {!loading ? (
              <>
                {/* Se SPUNE ce filtreaza acum. „Nimic pentru filtrele astea"
                    fara sa arate care sunt ele lasa omul sa caute prin ecran
                    ce anume l-a golit — de multe ori orasul, care nu e nici
                    macar in bara de filtre, ci in antetul de pe Acasă. */}
                <div className="muted" style={sx('font-size:12px;margin-top:6px;line-height:1.5')}>
                  {active.length ? `Filtrezi după: ${active.join(', ')}.` : 'Nu e nimic publicat aici deocamdată.'}
                </div>
                {active.length ? (
                  <button
                    className="cta ghost"
                    style={sx('margin-top:14px;padding:11px')}
                    onClick={() => {
                      /* Sterge TOT ce poate goli lista, orasul inclusiv.
                         Butonul chema doar `resetRadarF`, care nu atinge
                         orasul — deci cand vinovat era orasul, apasarea nu
                         schimba nimic si parea stricat. */
                      resetRadarF();
                      setCities([]);
                      setCity('');
                      if (cat) back();
                    }}
                  >
                    {cat ? 'Șterge filtrele și ieși din categorie' : 'Șterge filtrele'}
                  </button>
                ) : null}
              </>
            ) : null}
          </div>
        ) : null}
      </div>
      <div style={sx('height:8px')} />
      <BottomNav active="ticslist" />
    </div>
  );
}

/* =========================================================
   S.ticsoffers
   ========================================================= */
export function TicsOffers({ id }: { id?: string }) {
  const { go } = useNav();
  const lb = useLightbox();
  const showToast = useClient((s) => s.showToast);
  const toggleSavedRadar = useClient((s) => s.toggleSavedRadar);
  const saved = useClient((s) => !!s.savedRadar[id ?? '']);
  const t = useRadarEvent(id) as RadarItem & Ev;
  /* Ce furnizor e desfacut. Unul singur: doua liste deschise pe acelasi ecran
     fac comparatia mai grea, nu mai usoara. */
  const [openPlatform, setOpenPlatform] = useState<string | null>(null);

  /* Id-ul salii din catalogul nostru, cand numele din Radar se potriveste sigur. */
  const [venueId, setVenueId] = useState<number | null>(null);
  useEffect(() => {
    setVenueId(null);
    if (!t.venName || t.venName.length < 3) return;

    let alive = true;
    void lookupCatalogVenue(t.venName, t.city || undefined).then((v) => {
      if (alive && v) setVenueId(v.id);
    });

    return () => {
      alive = false;
    };
  }, [t.venName, t.city]);

  const openVenue = () => {
    if (venueId) go('venue', { id: String(venueId) });
  };
  const sorted = [...(t.offers as [string, number, string][])].sort((a, b) => a[1] - b[1]);
  const ch = sorted[0]?.[1] ?? 0;
  /* Cand avem mai multe platforme, "mediana pietei" e chiar cea mai scumpa
     oferta reala. Cu o singura oferta n-avem cu ce compara, deci pastram
     estimarea prototipului (+28%) ca ecranul sa ramana intreg. */
  const med = sorted.length > 1 ? sorted[sorted.length - 1][1] : Math.round(ch * 1.28);
  const savePct = med > 0 ? Math.round((1 - ch / med) * 100) : 0;

  /* Butonul "Mergi" duce pe site-ul platformei. window.open(_blank) e
     interceptat de Capacitor si deschis in browserul sistemului. */
  /* Share: pe telefon exista foaia nativa; in browser cade pe clipboard. */
  const share = async () => {
    const best = t.offers[0];
    const url = (best && t.urls?.[best[0]]) || 'https://app.tics.ro';
    const text = `${t.s}${best ? ` — de la ${best[1]} lei` : ''}`;
    try {
      if (navigator.share) {
        await navigator.share({ title: t.s, text, url });
        return;
      }
      await navigator.clipboard.writeText(`${text}
${url}`);
      showToast('Link copiat');
    } catch {
      /* utilizatorul a inchis foaia de share — nu e o eroare */
    }
  };

  const goToOffer = (platform: string) => {
    const url = t.urls?.[platform];
    if (!url) return showToast('Mergi la ' + platform);
    showToast('Deschid ' + platform);
    window.open(url, '_blank');
  };

  return (
    <div style={sx('min-height:100%;background:var(--bg);padding-bottom:2px')}>
      <DBar
        title={t.s}
        right={
          <>
            <div className="icon-btn glass" onClick={share}>
              <Ic svg={I.share} />
            </div>
            <div
              className="icon-btn glass"
              onClick={() => {
                if (!id) return;
                toggleSavedRadar(t);
                showToast(saved ? 'Scos din salvate' : 'Salvat');
              }}
              style={saved ? sx('color:var(--indigo-2);background:var(--indigo-soft);border-color:var(--indigo)') : undefined}
            >
              <Ic svg={I.save} />
            </div>
          </>
        }
      />

      <div
        className="poster"
        style={{
          background: t.poster ? `url('${t.poster}') center/cover, #14101f` : bgv(t),
          height: 320,
          borderRadius: '0 0 30px 30px',
        }}
      >
        <div style={sx('position:absolute;inset:0;background:linear-gradient(180deg,rgba(0,0,0,.3),transparent 30%,rgba(11,9,18,.96))')} />
        <div style={sx('position:absolute;left:20px;right:20px;bottom:20px')}>
          <span className="badge" style={sx('background:rgba(255,255,255,.16);backdrop-filter:blur(8px);color:#fff')}>
            {[t.cat, t.rat && t.rat !== '—' ? `⭐ ${t.rat}` : ''].filter(Boolean).join(' · ')}
          </span>
          <div style={sx('font-size:23px;font-weight:600;letter-spacing:-.03em;margin-top:10px;line-height:1.14')}>
            {t.s}
          </div>
          <div style={sx('font-size:12.5px;color:rgba(255,255,255,.82);margin-top:4px')}>
            {[t.city, `${t.day} ${t.mon}`.trim(), t.time].filter(Boolean).join(' · ')}
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
              <div style={sx('font-size:12.5px;font-weight:500')}>
                {t.day} {t.mon}
              </div>
              <div style={sx('font-size:11px;color:var(--muted)')}>{t.time ? `Ora ${t.time}` : 'Oră neanunțată'}</div>
            </div>
          </div>
          {/* Radarul tine sala doar ca text — n-are id-ul nostru. O cautam dupa
              nume si, daca o gasim sigur, numele devine link catre fisa ei. */}
          <div className="row" style={sx('gap:9px')} onClick={openVenue}>
            <div className="icon-btn" style={sx('width:38px;height:38px')}>
              <Ic svg={I.pin} />
            </div>
            <div>
              <div style={sx('font-size:12.5px;font-weight:500')}>{t.venName}</div>
              <div
                style={{
                  fontSize: '11px',
                  color: venueId ? 'var(--indigo-2)' : 'var(--muted)',
                  fontWeight: venueId ? 600 : undefined,
                }}
              >
                {venueId ? 'Vezi locația ›' : t.city}
              </div>
            </div>
          </div>
        </div>

        {t.artists?.length ? (
          <>
            <div className="h2" style={sx('margin-top:22px;font-size:15px')}>
              Artiști
            </div>
            <div className="scroll-x" style={sx('margin-top:11px;padding:0')}>
              {(t.artists as string[]).map((aid) => {
                const a = (ART as Record<string, Ev>)[aid];
                if (!a) return null;
                return (
                  <div key={aid} onClick={() => go('artist', { id: aid })} style={sx('min-width:90px;text-align:center;cursor:pointer')}>
                    <div
                      style={{
                        width: 72,
                        height: 72,
                        borderRadius: 24,
                        margin: '0 auto',
                        background: a.tone,
                        display: 'grid',
                        placeItems: 'center',
                        fontSize: '28px',
                      }}
                    >
                      {a.g}
                    </div>
                    <div style={sx('font-weight:500;font-size:12.5px;margin-top:7px')}>{a.name}</div>
                    <div style={sx('font-size:10.5px;color:var(--muted)')}>{a.role}</div>
                  </div>
                );
              })}
            </div>
          </>
        ) : null}

        {/* Radarul agrega preturi, nu descrieri — sectiunea apare doar cand
            sursa chiar are text, ca sa nu ramana un titlu peste gol. */}
        {t.desc ? (
          <>
            <div className="h2" style={sx('margin-top:22px;font-size:15px')}>
              Despre
            </div>
            <p style={sx('color:var(--ink-2);font-size:13.5px;line-height:1.62;margin-top:8px')}>{t.desc}</p>
          </>
        ) : null}

        {t.gallery?.length > 1 ? (
          <>
            <div className="between" style={sx('margin-top:22px')}>
              <div className="h2" style={sx('font-size:15px')}>
                Galerie
              </div>
              <span className="muted" style={sx('font-size:11px;font-weight:600')}>
                atinge pentru mărire
              </span>
            </div>
            <div className="scroll-x" style={sx('margin-top:11px;padding:0')}>
              {(t.gallery as string[]).map((g, i) => (
                <div
                  key={i}
                  onClick={() => lb.open(t.gallery, i, t.g)}
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
            </div>
          </>
        ) : null}
      </div>

      <div className="pad" style={sx('margin-top:22px')}>
        <div className="listitem" style={sx('background:var(--cyan-soft);border-color:rgba(45,214,238,.3)')}>
          <div style={sx('width:40px;height:40px;border-radius:12px;background:rgba(45,214,238,.2);display:grid;place-items:center;color:var(--cyan)')}>
            <Ic svg={I.layers} />
          </div>
          <div style={sx('flex:1')}>
            <div style={sx('font-weight:600;font-size:13px')}>Cel mai bun preț, garantat</div>
            <div className="muted" style={sx('font-size:11.5px')}>
              Urmărim {t.offers.length} oferte în timp real și te trimitem direct la cea mai avantajoasă.
            </div>
          </div>
        </div>
      </div>

      {/* Pretul fata de piata */}
      <div className="pad" style={sx('margin-top:14px')}>
        <div className="card" style={sx('padding:15px')}>
          <div className="between" style={sx('margin-bottom:11px')}>
            <div className="h2" style={sx('font-size:14px')}>
              Prețul față de piață
            </div>
            <span className="badge" style={sx('background:var(--green-soft);color:var(--green-2)')}>
              -{savePct}% sub medie
            </span>
          </div>
          <div style={sx('height:9px;border-radius:9px;background:var(--surface-3);position:relative;margin:8px 0')}>
            <div style={sx('position:absolute;left:0;top:0;bottom:0;width:38%;background:linear-gradient(90deg,var(--green),var(--green-2));border-radius:9px')} />
            <div style={sx('position:absolute;left:38%;top:-4px;width:17px;height:17px;border-radius:50%;background:#fff;border:3px solid var(--green);transform:translateX(-50%);box-shadow:0 4px 10px rgba(0,0,0,.4)')} />
          </div>
          <div className="between" style={sx('font-size:11px;color:var(--faint);font-weight:600')}>
            <span>de la {ch} lei</span>
            <span>mediana pieței {med} lei</span>
          </div>
        </div>
      </div>

      {/* Unde gasesti bilete */}
      <div className="pad" style={sx('margin-top:22px')}>
        <div className="between" style={sx('margin-bottom:12px')}>
          <div className="h2" style={sx('font-size:16px')}>
            Unde găsești bilete
          </div>
          <span className="row" style={sx('gap:5px;font-size:11px;font-weight:600;color:var(--green-2)')}>
            <span className="stockdot" style={sx('background:var(--green-2)')} />
            {t.offers.length} oferte · stoc live
          </span>
        </div>
        <div className="card" style={sx('overflow:hidden')}>
          {sorted.map((o, i) => {
            const amber = /ultim|puține|6/i.test(o[2]);
            const rows = t.tickets?.[o[0]] ?? [];
            const open = openPlatform === o[0];

            return (
              <div key={o[0]} style={{ borderTop: i > 0 ? '1px solid var(--line)' : undefined }}>
              <div
                className="selrow"
                onClick={() => rows.length > 1 && setOpenPlatform(open ? null : o[0])}
                style={{
                  background: i === 0 ? 'var(--green-soft)' : undefined,
                  cursor: rows.length > 1 ? 'pointer' : undefined,
                }}
              >
                <div
                  style={{
                    width: 42,
                    height: 42,
                    borderRadius: 12,
                    background: i === 0 ? 'rgba(34,197,94,.2)' : 'var(--surface-3)',
                    display: 'grid',
                    placeItems: 'center',
                    fontWeight: 700,
                    fontSize: '15px',
                    color: i === 0 ? 'var(--green-2)' : 'var(--indigo-2)',
                    flex: 'none',
                  }}
                >
                  {o[0][0].toUpperCase()}
                </div>
                <div style={sx('flex:1;min-width:0')}>
                  <div className="row" style={sx('gap:6px')}>
                    <span style={sx('font-weight:600;font-size:13.5px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis')}>
                      {o[0]}
                    </span>
                    {i === 0 ? (
                      <span className="chip-mini" style={sx('background:var(--green);color:#05230f')}>
                        cel mai ieftin
                      </span>
                    ) : null}
                  </div>
                  <div className="metaline" style={sx('margin-top:4px')}>
                    <span className="stockdot" style={{ background: amber ? 'var(--amber)' : 'var(--green-2)' }} />
                    <span>{o[2]}</span>
                    {rows.length > 1 ? (
                      <>
                        <span className="dot" />
                        <span style={sx('color:var(--indigo-2);font-weight:600')}>
                          {rows.length} tipuri {open ? '▴' : '▾'}
                        </span>
                      </>
                    ) : null}
                  </div>
                </div>
                <div style={sx('text-align:right;flex:none')}>
                  <div
                    style={{
                      fontWeight: 700,
                      fontSize: '17px',
                      color: i === 0 ? 'var(--green-2)' : 'var(--ink)',
                      lineHeight: 1,
                      fontVariantNumeric: 'tabular-nums',
                    }}
                  >
                    {o[1]}
                    <small style={sx('font-size:10px;color:var(--muted);font-weight:600')}> lei</small>
                  </div>
                  <button
                    className="gpill"
                    onClick={(e) => {
                      // randul de deasupra desface lista; butonul duce pe site
                      e.stopPropagation();
                      goToOffer(o[0]);
                    }}
                    style={
                      i === 0
                        ? { marginTop: 7, background: 'linear-gradient(135deg,var(--green),#16a34a)', borderColor: 'transparent' }
                        : { marginTop: 7, background: 'var(--surface-3)', borderColor: 'var(--line-2)', color: 'var(--indigo-2)' }
                    }
                  >
                    Mergi <Ic svg={I.ext} />
                  </button>
                </div>
              </div>

              {/* Categoriile de bilet ale furnizorului. Aceeasi animatie de
                  inaltime ca la descrierea din feed, ca lista sa nu apara brusc. */}
              <div className={open ? 'exp open' : 'exp'}>
                <div className="expinner">
                  <div style={sx('padding:2px 14px 12px')}>
                    {rows.map((r) => (
                      <div
                        key={r.name + r.price}
                        className="between"
                        style={sx('padding:8px 0;border-top:1px solid var(--line)')}
                      >
                        <div style={sx('min-width:0;flex:1')}>
                          <div style={sx('font-size:12.5px;font-weight:500')}>{r.name}</div>
                          <div className="metaline" style={sx('margin-top:2px')}>
                            <span className="stockdot" style={sx('background:var(--green-2)')} />
                            <span>{r.stock}</span>
                          </div>
                        </div>
                        <div style={sx('font-weight:700;font-size:14px;font-variant-numeric:tabular-nums;flex:none')}>
                          {r.price}
                          <small style={sx('font-size:10px;color:var(--muted);font-weight:600')}> lei</small>
                        </div>
                      </div>
                    ))}
                  </div>
                </div>
              </div>
              </div>
            );
          })}
        </div>
        <div className="muted" style={sx('font-size:10.5px;text-align:center;margin-top:10px')}>
          💚 Economisești până la <b style={sx('color:var(--green-2)')}>{med - ch} lei</b> față de mediana pieței
        </div>
      </div>

      {/* Detaliu pret */}
      {(() => {
        const base = Math.round(ch / 1.03);
        const proc = ch - base;
        return (
          <div className="pad" style={sx('margin-top:14px')}>
            <div className="card" style={sx('padding:14px')}>
              <div className="h2" style={sx('font-size:13.5px;margin-bottom:9px')}>
                Detaliu preț · {sorted[0][0]}
              </div>
              <div className="between" style={sx('padding:5px 0;font-size:12.5px')}>
                <span className="muted">Preț bază bilet</span>
                <span style={sx('font-weight:500')}>{base} lei</span>
              </div>
              <div className="between" style={sx('padding:5px 0;font-size:12.5px')}>
                <span className="muted">Taxă procesare</span>
                <span style={sx('font-weight:500')}>{proc} lei</span>
              </div>
              <div className="between" style={sx('padding:8px 0 2px;border-top:1px solid var(--line);margin-top:4px')}>
                <span style={sx('font-weight:600')}>Total de plată</span>
                <span style={sx('font-weight:600;color:var(--green-2)')}>{ch} lei</span>
              </div>
            </div>
          </div>
        );
      })()}

      <div className="pad" style={sx('margin-top:14px')}>
        <div className="muted" style={sx('font-size:11px;text-align:center;line-height:1.5')}>
          Prețurile și stocul sunt urmărite automat, în timp real. Achiziția se finalizează pe site-ul ofertei alese.
        </div>
      </div>
      <BottomNav active="" />
    </div>
  );
}

/* =========================================================
   S.calendar
   ========================================================= */
const WEEKDAYS = ['L', 'Ma', 'Mi', 'J', 'V', 'S', 'D'];
const MONTHS_FULL = [
  'ianuarie', 'februarie', 'martie', 'aprilie', 'mai', 'iunie',
  'iulie', 'august', 'septembrie', 'octombrie', 'noiembrie', 'decembrie',
];
/** culoarea bulinei, pe categorie — aceeasi paleta ca in CAL_DOTS */
const DOT_COLOR: Record<string, string> = {
  Concerte: '#8b5cf6',
  Festival: '#22c55e',
  Teatru: '#3b82f6',
  'Stand-up': '#f59e0b',
  Petrecere: '#ec4899',
  Sport: '#12b3a6',
  Film: '#6366f1',
  Altele: '#94a3b8',
};

/** Luni = 0, ca in grila prototipului (L Ma Mi J V S D). */
const startBlank = (y: number, m: number) => (new Date(Date.UTC(y, m, 1)).getUTCDay() + 6) % 7;
const daysInMonth = (y: number, m: number) => new Date(Date.UTC(y, m + 1, 0)).getUTCDate();

export function Calendar() {
  const { go, back } = useNav();
  const calDay = useClient((s) => s.calDay);

  const now = new Date();
  const [ym, setYm] = useState<[number, number]>([now.getFullYear(), now.getMonth()]);
  const [year, month] = ym;

  /* Miezul zilei de azi, in UTC — grila e construita tot in UTC (`Date.UTC`
     mai jos), deci comparatia trebuie facuta in acelasi sistem, altfel ziua
     curenta apare inactiva in fusurile de la est de Greenwich. */
  const TODAY_UTC = Date.UTC(now.getFullYear(), now.getMonth(), now.getDate());
  const shiftMonth = (d: number) => {
    const t = new Date(Date.UTC(year, month + d, 1));

    // Nu se coboara sub luna curenta: n-ar avea decat zile inactive.
    if (Date.UTC(t.getUTCFullYear(), t.getUTCMonth(), 1) < Date.UTC(now.getFullYear(), now.getMonth(), 1)) return;

    setYm([t.getUTCFullYear(), t.getUTCMonth()]);
  };
  const canGoBack = Date.UTC(year, month, 1) > Date.UTC(now.getFullYear(), now.getMonth(), 1);

  const calF = useClient((s) => s.calF);
  const setCalF = useClient((s) => s.setCalF);
  const cities = useRadarCities();
  const [sheet, setSheet] = useState<'city' | 'type' | 'genre' | null>(null);

  const { data, loading } = useRadarMonth(year, month, {
    city: calF.city || undefined,
    type: calF.type || undefined,
    genre: calF.genre || undefined,
  });
  const stats = useRadarStats();

  const cityOptions: Option[] = [['', 'Toate orașele'], ...cities.map((c) => [c, c] as Option)];
  const labelOf = (opts: Option[], v: string, fallback: string) => opts.find((o) => o[0] === v)?.[1] ?? fallback;

  const days = Array.from({ length: daysInMonth(year, month) }, (_, i) => i + 1);
  const START_BLANK = startBlank(year, month);
  const counts = data?.counts ?? {};
  const byDay = data?.byDay ?? {};
  /* Bulinele arata ce fel de evenimente sunt in ziua respectiva; cand luna
     inca se incarca, ramanem pe cele din prototip ca grila sa nu palpaie. */
  const dots: Record<number, string[]> = data
    ? Object.fromEntries(
        Object.entries(byDay).map(([d, list]) => [d, list.map((e) => DOT_COLOR[e.cat] ?? DOT_COLOR.Altele)])
      )
    : (CAL_DOTS as Record<number, string[]>);
  const dayEvents = useRadarDay(byDay[calDay] ?? []);

  /* Randurile zilei au exact forma tuplului din prototip (CAL_DAY):
     [categorie, titlu, meta, pret, platforma, culoare] + id-ul, pentru nav. */
  type Row = [string, string, string, string, string, string, string];
  const rows: Row[] = data
    ? dayEvents.map((e): Row => {
        const best = e.offers[0];
        return [
          e.cat,
          e.s,
          [e.time, e.venName || e.city].filter(Boolean).join(' · ') || '—',
          best ? String(best[1]) : '—',
          best ? best[0] : '—',
          DOT_COLOR[e.cat] ?? DOT_COLOR.Altele,
          e.id,
        ];
      })
    : (CAL_DAY as [string, string, string, string, string, string][]).map((r): Row => [...r, 'smiley']);

  return (
    <div className="grid" style={sx('min-height:100%')}>
      <TopBar>
        <div className="row" style={sx('gap:12px')}>
          <div className="icon-btn" onClick={back}>
            <Ic svg={I.back} />
          </div>
          <div>
            <div className="h2">Calendar</div>
            <div className="muted" style={sx('font-size:11.5px')}>
              Tot ce se întâmplă · {stats ? stats.liveEvents.toLocaleString('ro-RO') : '3.210'} live
            </div>
          </div>
        </div>
        <div className="icon-btn" onClick={() => go('ticslist')}>
          <Ic svg={I.layers} />
        </div>
      </TopBar>

      <div className="filterbar" style={sx('margin-top:12px')}>
        <PickerChip
          icon="📍"
          label={calF.city || 'Toate orașele'}
          active={!!calF.city}
          onClick={() => setSheet('city')}
        />
        <PickerChip
          icon="🎫"
          label={labelOf(TYPE_OPTIONS, calF.type, 'Toate tipurile')}
          active={!!calF.type}
          onClick={() => setSheet('type')}
        />
        <PickerChip
          icon="🎵"
          label={calF.genre ? labelOf(GENRE_OPTIONS, calF.genre, calF.genre) : 'Genuri'}
          active={!!calF.genre}
          onClick={() => setSheet('genre')}
        />
      </div>

      {sheet === 'city' ? (
        <PickerSheet
          title="Alege orașul"
          options={cityOptions}
          value={calF.city}
          onPick={(city) => setCalF({ city })}
          onClose={() => setSheet(null)}
          searchable
          searchPlaceholder="Caută orașul"
        />
      ) : null}
      {sheet === 'type' ? (
        <PickerSheet
          title="Tipul evenimentului"
          options={TYPE_OPTIONS}
          value={calF.type}
          onPick={(type) => setCalF({ type })}
          onClose={() => setSheet(null)}
        />
      ) : null}
      {sheet === 'genre' ? (
        <PickerSheet
          title="Genul muzical"
          options={GENRE_OPTIONS}
          value={calF.genre}
          onPick={(genre) => setCalF({ genre })}
          onClose={() => setSheet(null)}
        />
      ) : null}

      <div className="pad" style={sx('margin-top:14px')}>
        <div className="between" style={sx('margin-bottom:10px')}>
          <div className="row" style={sx('gap:10px')}>
            <span
              className="muted"
              style={{ cursor: canGoBack ? 'pointer' : 'default', opacity: canGoBack ? 1 : 0.3 }}
              onClick={() => shiftMonth(-1)}
            >
              ‹
            </span>
            <div style={sx('font-weight:600;font-size:15px')}>
              {MONTHS_FULL[month].replace(/^./, (c) => c.toUpperCase())} {year}
            </div>
            <span className="muted" style={sx('cursor:pointer')} onClick={() => shiftMonth(1)}>
              ›
            </span>
          </div>
          <span className="muted" style={sx('font-size:11px')}>
            {/* `capped` = am atins plafonul de paginare, deci numaram doar ce am vazut */}
            {loading ? '…' : `${data?.capped ? '+' : ''}${(data?.total ?? 0).toLocaleString('ro-RO')} ev.`}
          </span>
        </div>
        <div style={sx('display:grid;grid-template-columns:repeat(7,1fr);gap:5px')}>
          {WEEKDAYS.map((d) => (
            <div key={d} style={sx('text-align:center;font-size:10px;font-weight:600;color:var(--faint);padding-bottom:2px')}>
              {d}
            </div>
          ))}
          {Array.from({ length: START_BLANK }).map((_, i) => (
            <div key={`b${i}`} />
          ))}
          {days.map((d) => {
            const cnt = counts[d];
            const on = d === calDay;
            const dd = dots[d] || (cnt ? ['#8b5cf6', '#22c55e', '#3b82f6'] : []);
            /* Zilele trecute nu se pot alege: la un eveniment de ieri nu mai ai
               ce face, iar Radarul oricum nu are ce lista acolo. */
            const past = Date.UTC(year, month, d) < TODAY_UTC;
            return (
              <div
                key={d}
                aria-disabled={past || undefined}
                onClick={() => !past && useClient.setState({ calDay: d })}
                style={{
                  aspectRatio: '1',
                  borderRadius: 11,
                  border: `1px solid ${on ? 'var(--indigo)' : 'var(--line)'}`,
                  background: on ? 'var(--indigo-soft)' : 'var(--surface-solid)',
                  padding: '4px 5px',
                  cursor: past ? 'default' : 'pointer',
                  opacity: past ? 0.38 : 1,
                  display: 'flex',
                  flexDirection: 'column',
                }}
              >
                <div className="between">
                  <span style={sx('font-size:11.5px;font-weight:600')}>{d}</span>
                  {cnt ? <span style={sx('font-size:8px;color:var(--green-2);font-weight:600')}>{cnt}</span> : null}
                </div>
                <div className="row" style={sx('gap:2px;margin-top:auto')}>
                  {dd.slice(0, 5).map((c, k) => (
                    <span key={k} style={{ width: 4, height: 4, borderRadius: '50%', background: c }} />
                  ))}
                </div>
              </div>
            );
          })}
        </div>
      </div>

      <div className="pad" style={sx('margin-top:16px')}>
        <div className="card" style={sx('padding:14px')}>
          <div className="between">
            <div>
              <div className="muted" style={sx('font-size:10.5px;font-weight:600;text-transform:uppercase')}>
                Ziua selectată
              </div>
              <div style={sx('font-weight:600;font-size:18px')}>
                {calDay} {MONTHS_FULL[month]}
              </div>
            </div>
            <div style={sx('text-align:right')}>
              <div style={sx('font-weight:600;font-size:18px;color:var(--green-2)')}>{counts[calDay] || 0}</div>
              <div className="muted" style={sx('font-size:10px')}>
                evenimente
              </div>
            </div>
          </div>

          <div style={sx('margin-top:12px;display:flex;flex-direction:column;gap:12px')}>
            {rows.map((e) => (
              <div key={e[6]} className="row" onClick={() => go('ticsoffers', { id: e[6] })} style={sx('gap:11px;cursor:pointer')}>
                <div
                  style={{
                    width: 44,
                    height: 44,
                    borderRadius: 12,
                    background: e[5] + '22',
                    display: 'grid',
                    placeItems: 'center',
                    fontSize: '18px',
                  }}
                >
                  🎟
                </div>
                <div style={sx('flex:1;min-width:0')}>
                  <div className="row" style={sx('gap:6px')}>
                    <span style={{ width: 6, height: 6, borderRadius: '50%', background: e[5] }} />
                    <span className="muted" style={sx('font-size:9.5px;font-weight:600')}>
                      {e[0]}
                    </span>
                  </div>
                  <div style={sx('font-weight:600;font-size:13px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis')}>
                    {e[1]}
                  </div>
                  <div className="muted" style={sx('font-size:11px')}>
                    {e[2]}
                  </div>
                </div>
                <div style={sx('text-align:right')}>
                  <div style={sx('font-weight:600;color:var(--green-2);font-size:13px')}>
                    {e[3]}
                    {e[3] !== '—' ? ' RON' : ''}
                  </div>
                  <div className="muted" style={sx('font-size:9.5px')}>
                    {e[4] !== '—' ? 'pe ' + e[4] : '—'}
                  </div>
                </div>
              </div>
            ))}
            {!rows.length ? (
              <div className="muted" style={sx('font-size:12px;text-align:center;padding:6px 0')}>
                {loading ? 'Se încarcă…' : 'Nimic programat în ziua asta.'}
              </div>
            ) : null}
          </div>

          <button
            className="cta green"
            style={sx('margin-top:14px;padding:13px')}
            onClick={() => go('ticslist', { day: Date.UTC(year, month, calDay) })}
          >
            Vezi toate ({counts[calDay] || 0}) <Ic svg={I.arrow} />
          </button>
        </div>
      </div>

      <div className="pad" style={sx('margin-top:12px')}>
        <div className="muted" style={sx('font-size:11px;text-align:center;line-height:1.5')}>
          Cele mai bune prețuri din toată piața, urmărite automat în timp real.
        </div>
      </div>
      <BottomNav active="" />
    </div>
  );
}
