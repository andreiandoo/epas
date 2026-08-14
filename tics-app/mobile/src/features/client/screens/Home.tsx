/* =========================================================
   ACASA — port 1:1 al lui S.home() din client-app.html (linia 658).
   Structura, clasele si sirurile de stil sunt copiate verbatim.
   Sectiuni, in ordinea din prototip:
     header locatie + clopotel · searchbar · rail de categorii ("Pe val" +
     CATS) · "Pentru tine" (AI) · "Evenimente" (card mare + rail) ·
     "Experiente" (banda verde) · card festival · "Radar" · bottom nav
   ========================================================= */
import { Ic, sx } from '../../../design/sx';
import { CATS, EV, I } from '../../../mock/prototype';
import type { UiEvent } from '../../../api/tenantClient';
import { EvMini } from '../cards';
import { Avatar, BottomNav, SafeTop, SecH } from '../kit';
import { useNav } from '../nav';
import { radarToUi, useRadarCities, useRadarList } from '../radarData';
import { useCatalogEvents } from '../catalogData';
import { useAccountStats, useTickets } from '../accountData';
import { fetchFriends } from '../../../api/friends';
import { customerName, useCustomer } from '../accountData';
import { NearYou } from '../nearby';
import { useShortsPreview } from '../shorts/useShortsPreview';
import { PickerSheet, type Option } from '../picker';
import { useClient } from '../../../store/client';
import { useEffect, useState } from 'react';

/* EV vine dintr-un dump verbatim (@ts-nocheck), deci fara index signature. */
const ev = (id: string) => (EV as Record<string, unknown>)[id] as UiEvent;

export function Home() {
  const { go, tab } = useNav();
  const me = useCustomer();
  const meName = customerName(me) ?? 'Contul tău';
  const city = useClient((s) => s.city);
  const setCity = useClient((s) => s.setCity);
  const cities = useRadarCities();
  const [picker, setPicker] = useState(false);
  /* DOUA surse, in ordinea asta:
       1. evenimentele NOASTRE — ale tenantilor si marketplace-urilor tics.
          Au bilet in aplicatie, deci merg intotdeauna primele;
       2. Radarul TICS — acolo doar comparam preturi de pe alte platforme.
     Cate un singur apel de fiecare, nu unul pe sectiune: patru cereri ar
     insemna patru asteptari si patru ocazii de esec pe ecranul de pornire. */
  const preview = useShortsPreview();
  const { groups } = useTickets();
  const stats = useAccountStats();
  const localPoints = useClient((s) => s.points);
  const points = stats?.points ?? localPoints;

  /* Prietenii, pentru cardul de mai jos. Tace fara cont tics: `null` inseamna
     „nu stim", si se afiseaza „—", nu zero — zero ar fi o afirmatie falsa. */
  const [friends, setFriends] = useState<number | null>(null);
  const [requests, setRequests] = useState(0);

  useEffect(() => {
    let alive = true;
    void fetchFriends().then((r) => {
      if (!alive || !r.ok) return;
      setFriends(r.data.friends.length);
      setRequests(r.data.requests.length);
    });

    return () => {
      alive = false;
    };
  }, []);

  const upcoming = groups.filter((g) => g.upcoming && g.live);

  const mine = useCatalogEvents({ city: city || undefined, limit: 12 });
  const { items: radar, loading: radarLoading } = useRadarList({ limit: 14, city: city || undefined });

  /* Deduplicare: acelasi eveniment poate aparea si la noi, si in Radar (care
     ne agrega si pe noi). Cheia e titlul + ziua, singurele comune celor doua
     surse — id-urile sunt din lumi diferite. Al nostru castiga, fiindca de
     acolo se poate cumpara. */
  const mineKeys = new Set(mine.items.map((e) => `${String(e.s).toLowerCase()}|${e.d}`));
  const pool = [
    ...mine.items,
    ...radar.filter((r) => !mineKeys.has(`${r.s.toLowerCase()}|${[r.day, r.mon].filter(Boolean).join(' ')}`)).map(radarToUi),
  ];

  /* Fara niciun eveniment real (offline, sau oras fara nimic anuntat) ramanem
     pe datasetul prototipului: un ecran de pornire gol nu spune nimic despre
     ce face aplicatia. */
  const live = pool.length > 0;

  const forYou = live ? pool.slice(0, 6) : ['coldplay', 'celestial', 'swan'].map(ev);

  return (
    <div className="grid" style={sx('min-height:100%;padding-bottom:6px')}>
      <div className="stickytop">
        <SafeTop />
        <div className="hdr" style={sx('padding:4px 20px 11px')}>
          <div className="row" style={sx('gap:11px')}>
            {/* Poza reala, nu initialele demo „AP" scrise in cod. */}
            <Avatar name={meName} src={me?.avatar} size={40} radius={13} />
            {/* orasul filtreaza Radarul; lista vine din evenimentele reale */}
            <div style={sx('cursor:pointer')} onClick={() => setPicker(true)}>
              <div className="loc-l">
                <Ic svg={I.pin} /> Locația ta
              </div>
              <div className="loc-v">
                {city || 'Toată România'}{' '}
                <svg
                  width="13"
                  height="13"
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="var(--muted)"
                  strokeWidth="2.6"
                  strokeLinecap="round"
                >
                  <path d="M6 9l6 6 6-6" />
                </svg>
              </div>
            </div>
          </div>
          <div className="icon-btn" onClick={() => go('notif')} style={sx('position:relative')}>
            <Ic svg={I.bell} />
            <span
              style={sx(
                'position:absolute;top:9px;right:10px;width:8px;height:8px;border-radius:50%;background:var(--indigo);border:2px solid var(--bg)',
              )}
            />
          </div>
        </div>
      </div>

      <div className="pad" style={sx('margin-top:14px')}>
        <div className="searchbar" onClick={() => go('search')}>
          <span className="muted">
            <Ic svg={I.search} />
          </span>
          <span className="ph">Caută evenimente, artiști…</span>
          <span style={sx('color:var(--indigo-2)')}>
            <Ic svg={I.slider} />
          </span>
        </div>
      </div>

      <div className="rail" style={sx('margin-top:15px;padding-bottom:2px')}>
        {/* Aici erau doua chip-uri, „Pe val" si „Descoperă". Amandoua duceau
            in ECRANE INTREGI, nu filtrau randul de categorii de langa ele —
            deci se citeau gresit. Au coborat, ca bannere. */}
        {(CATS as [string, string][]).map((c, i) => (
          <button
            key={c[0]}
            className={`chip ind ${i === 0 ? 'on' : ''}`}
            onClick={() => {
              if (i === 0) return;
              /* „Festival" ducea la pagina unui festival DEMO, nu la o
                 categorie. Acum se comporta ca oricare alta pastila. */
              go('category', { id: c[0] });
            }}
          >
            {c[1]} {c[0]}
          </button>
        ))}
      </div>

      {/* ---------- PENTRU TINE ----------
          Singura banda de evenimente de pe Acasa. Inainte erau patru
          („Pentru tine", „Evenimente", „Experiențe", „Radar") care afisau, in
          mare, aceleasi evenimente din aceleasi surse — deci derulai mult ca sa
          vezi de trei ori acelasi afis. Restul catalogului se ajunge din Radar
          si din scurtaturi. */}
      <div className="sec">
        <SecH
          icon="🤖"
          icbg="var(--indigo-soft)"
          iccol="var(--indigo-2)"
          title="Pentru tine"
          sub={city ? `În ${city}, pe gusturile tale` : 'Alese pe gusturile tale'}
          more={['Vezi tot', () => go('category', { id: 'Toate' })]}
        />
        <div className="rail">
          {forYou.map((e) => (
            <EvMini key={e.id} ev={e} />
          ))}
          {!live && radarLoading
            ? Array.from({ length: 3 }).map((_, i) => (
                <div key={`sk${i}`} className="mcard sk" style={sx('min-width:212px;height:268px')} />
              ))
            : null}
        </div>
      </div>

      {/* ---------- PE VAL ----------
          Ramane pe Acasa desi e si in bara: e cel mai bun mod de a descoperi
          ceva nou, si merita o intrare care arata ce contine, nu doar o
          iconita. */}
      <div className="pad sec tight">
        <div className="wave" onClick={() => tab('shorts')} role="button" tabIndex={0}>
          <div className="wave-bg" />
          <div className="wave-stack" aria-hidden="true">
            {(preview?.posters ?? []).map((src, i) => (
              <span key={src} className={`wave-card p${i}`} style={{ backgroundImage: `url('${src}')` }} />
            ))}
          </div>
          {/* Textul se opreste la 58% din latime: posterele din dreapta sunt
              parte din banner, iar titlul trecea peste ele. */}
          <div className="wave-text">
            <div className="row" style={sx('gap:7px;font-size:10.5px;font-weight:700;letter-spacing:.12em;text-transform:uppercase;opacity:.9')}>
              <Ic svg={I.wave} /> Pe val
            </div>
            <div style={sx('font-size:21px;font-weight:700;letter-spacing:-.03em;margin-top:6px;line-height:1.1')}>
              Reels &amp; Shorts
            </div>
            <div style={sx('font-size:12px;opacity:.88;margin-top:4px;line-height:1.35')}>
              Momente de la artiști, evenimente și locații
            </div>
          </div>
        </div>
      </div>

      {/* ---------- DESCOPERĂ ----------
          Era un chip in randul de categorii, langa „Concerte" si „Teatru" —
          adica arata ca inca un filtru, desi e un ecran intreg, cu categorii,
          calendar si recomandari. Tratat ca atare: banner propriu. */}
      <div className="pad sec tight">
        <div className="wave discover" onClick={() => tab('explore')} role="button" tabIndex={0}>
          <div className="wave-bg" />
          <div className="wave-text">
            <div className="row" style={sx('gap:7px;font-size:10.5px;font-weight:700;letter-spacing:.12em;text-transform:uppercase;opacity:.9')}>
              <Ic svg={I.compass} /> Descoperă
            </div>
            <div style={sx('font-size:21px;font-weight:700;letter-spacing:-.03em;margin-top:6px;line-height:1.1')}>
              Tot ce se întâmplă
            </div>
            <div style={sx('font-size:12px;opacity:.88;margin-top:4px;line-height:1.35')}>
              Categorii, calendar și recomandări pe gusturile tale
            </div>
          </div>
          <div className="discover-glyph" aria-hidden="true">
            🧭
          </div>
        </div>
      </div>

      {/* ---------- LANGA TINE ----------
          Ceruta pe Acasa, si e locul potrivit: „ce se intampla in jurul meu"
          e intrebarea cu care deschizi aplicatia. Se ascunde singura cand nu
          stim unde esti sau cand nu e nimic pe 100 km. */}
      <NearYou city={city} />

      {/* ---------- BILETELE TALE ----------
          Doar cele care urmeaza. Un bilet de acum trei luni n-are ce cauta pe
          ecranul de pornire; istoricul sta in „Biletele mele". */}
      {upcoming.length ? (
        <div className="sec">
          <SecH
            icon={I.ticket}
            icbg="var(--green-soft)"
            iccol="var(--green-2)"
            title="Biletele tale"
            sub={upcoming.length === 1 ? 'Urmează unul' : `Urmează ${upcoming.length}`}
            more={['Vezi tot', () => tab('tickets')]}
          />
          <div className="pad" style={sx('display:flex;flex-direction:column;gap:10px')}>
            {upcoming.slice(0, 2).map((g) => (
              <div
                key={g.ev}
                className="listitem"
                onClick={() => go('ticket', { id: g.ev })}
                style={sx('cursor:pointer')}
              >
                <div
                  style={sx('width:44px;height:44px;border-radius:14px;background:var(--green-soft);color:var(--green-2);display:grid;place-items:center;flex:none')}
                >
                  <Ic svg={I.ticket} />
                </div>
                <div style={sx('flex:1;min-width:0')}>
                  <div style={sx('font-weight:600;font-size:13.5px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis')}>
                    {g.title}
                  </div>
                  <div className="muted" style={sx('font-size:11.5px;margin-top:2px')}>
                    {[g.date, g.time, g.venue].filter(Boolean).join(' · ')}
                  </div>
                </div>
                <span className="chip-mini">{g.passes.length} bilete</span>
              </div>
            ))}
          </div>
        </div>
      ) : null}

      {/* ---------- PUNCTE + PRIETENI ----------
          Doua carduri mici, unul langa altul: sunt scurtaturi catre contul tau,
          nu continut de rasfoit, si n-au de ce sa ocupe cate un rand intreg. */}
      <div className="pad sec tight">
        <div className="row" style={sx('gap:11px;align-items:stretch')}>
          <div
            className="card"
            onClick={() => go('points')}
            style={sx('flex:1;padding:14px;cursor:pointer;background:linear-gradient(140deg,#3a2a08,#1a1526);border-color:rgba(245,158,11,.25)')}
          >
            <div className="row" style={sx('gap:7px;color:var(--amber);font-size:11px;font-weight:700')}>
              <Ic svg={I.star} /> PUNCTE
            </div>
            <div style={sx('font-size:24px;font-weight:700;margin-top:6px;line-height:1')}>{points}</div>
            <div className="muted" style={sx('font-size:11px;margin-top:3px')}>Vezi recompensele</div>
          </div>

          <div
            className="card"
            onClick={() => go('friends')}
            style={sx('flex:1;padding:14px;cursor:pointer;background:linear-gradient(140deg,var(--indigo-soft),var(--surface-solid));border-color:var(--indigo-line)')}
          >
            <div className="row" style={sx('gap:7px;color:var(--indigo-2);font-size:11px;font-weight:700')}>
              👥 PRIETENI
            </div>
            <div style={sx('font-size:24px;font-weight:700;margin-top:6px;line-height:1')}>
              {friends === null ? '—' : friends}
            </div>
            <div className="muted" style={sx('font-size:11px;margin-top:3px')}>
              {requests ? `${requests} cereri noi` : 'Invită și tu'}
            </div>
          </div>
        </div>
      </div>

      {picker ? (
        <PickerSheet
          title="Unde ești?"
          options={[['', 'Toată România'], ...cities.map((c) => [c, c] as Option)]}
          value={city}
          onPick={setCity}
          onClose={() => setPicker(false)}
          searchable
          searchPlaceholder="Caută orașul"
        />
      ) : null}

      {/* Spatiul de sub ultimul card. `--ep-nav-space` tine cont de bara, de
          voalul de sub ea si de bara de gesturi a telefonului — cardul de
          Puncte ajungea dedesubt. */}
      <div style={{ height: 'var(--ep-nav-space)' }} />
      <BottomNav active="home" />
    </div>
  );
}
