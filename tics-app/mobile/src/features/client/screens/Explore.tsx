/* =========================================================
   EXPLOREAZA — port 1:1 al lui S.explore() din client-app.html (linia 689),
   inclusiv INIT.explore (rotatia imaginilor din cardurile de categorie).
   Sectiuni, in ordinea din prototip:
     antet · card AI de preferinte · rail recomandari · card "Pe val" ·
     Calendar + Radar preturi · "Alege un vibe" (CATPOOLS) · "Langa tine"
   ========================================================= */
import { Ic, cn, sx } from '../../../design/sx';
import { CATPOOLS, EV, I } from '../../../mock/prototype';
import type { UiEvent } from '../../../api/tenantClient';
import { EvMini } from '../cards';
import { BottomNav, SafeTop, SecH } from '../kit';
import { useNav } from '../nav';
import { useCatalogEvents } from '../catalogData';
import { radarToUi, useRadarList, useRadarMonth } from '../radarData';
import { useClient } from '../../../store/client';
import { useRadarCategories } from '../radarData';
import { CatCard, poolsFromCategories, type Pool } from '../catCard';

const ev = (id: string) => (EV as Record<string, unknown>)[id] as UiEvent;

export function Explore() {
  const { go } = useNav();
  const cats = useRadarCategories();
  /* Pana raspunde app.tics.ro ramanem pe categoriile prototipului, ca sectiunea
     sa nu apara goala; apoi le inlocuim cu cele reale (17 tipuri masurate). */
  const pools = cats.length ? poolsFromCategories(cats) : (CATPOOLS as unknown as Pool[]);
  const prefsSel = useClient((s) => s.prefsSel);
  /* Aceeasi ordine ca pe Acasa: evenimentele NOASTRE primele — de acolo se
     poate cumpara bilet — apoi Radarul, ca sa completeze randul cand avem
     putine. Fara nimic real, ramane datasetul prototipului. */
  /* FARA filtru de oras, intentionat.

     „Explorează" inseamna „arata-mi ce exista", iar orasul ales in antet
     ingusta lista exact ca in Radar — deci cele doua ecrane deveneau acelasi
     lucru. Cine vrea un oras anume are Radarul, care e construit pentru
     filtrat. Aici se cauta larg. */
  const mine = useCatalogEvents({ limit: 6 });
  const { items: radar } = useRadarList({ limit: 6 });

  /* Luna curenta din feed, pentru banda de zile. Aceeasi sursa ca ecranul de
     Calendar, deci cifrele nu pot sa difere intre cele doua. */
  const today = new Date();
  const month = useRadarMonth(today.getFullYear(), today.getMonth());
  const monthLoading = month.loading;

  const DOW = ['Dum', 'Lun', 'Mar', 'Mie', 'Joi', 'Vin', 'Sâm'];

  /* Doua saptamani incepand de AZI. Nu luna calendaristica: pe 28 ale lunii,
     o banda „luna curenta" ar arata trei zile si ar parea goala. */
  const daysStrip = (() => {
    const out: { stamp: number; num: number; dow: string; count: number }[] = [];
    const base = new Date(today.getFullYear(), today.getMonth(), today.getDate());

    for (let i = 0; i < 14; i++) {
      const d = new Date(base);
      d.setDate(base.getDate() + i);

      /* Contorul exista doar pentru zilele din luna incarcata; pentru zilele
         care trec in luna urmatoare ramane 0 pana se cere si aia. */
      const count = d.getMonth() === today.getMonth() ? (month.data?.counts[d.getDate()] ?? 0) : 0;

      out.push({
        stamp: Date.UTC(d.getFullYear(), d.getMonth(), d.getDate()),
        num: d.getDate(),
        dow: i === 0 ? 'Azi' : DOW[d.getDay()],
        count,
      });
    }

    return out;
  })();

  const pool = [...mine.items, ...radar.map(radarToUi)];
  const rec = pool.length ? pool.slice(0, 6) : ['coldplay', 'salina', 'swan'].map(ev);

  return (
    <div className="grid" style={sx('min-height:100%;padding-bottom:6px')}>
      <div className="stickytop">
        <SafeTop />
        <div className="hrow">
          <div>
            <div className="eyebrow">Descoperă</div>
            <h1 className="h1" style={sx('font-size:23px;margin-top:2px')}>
              Explorează
            </h1>
          </div>
          <div className="icon-btn" onClick={() => go('search')}>
            <Ic svg={I.search} />
          </div>
        </div>
      </div>

      {/* ---------- RECOMANDĂRI AI ----------
          Dupa macheta din elements/designs: banda mov care ocupa tot randul,
          intrebarea, butonul alb care duce in Asistent, si scurtaturile de
          dedesubt. Cardul de dinainte era mai mic si ducea doar la editarea
          intereselor — adica la setari, nu la un raspuns. */}
      <div className="airec">
        <div className="airec-glow" aria-hidden="true" />
        <div
          className="row"
          style={sx('gap:7px;color:#c4b5fd;font-size:11px;font-weight:700;letter-spacing:.12em;text-transform:uppercase;position:relative')}
        >
          <Ic svg={I.star} /> Recomandări AI
        </div>
        <div style={sx('font-size:24px;font-weight:700;letter-spacing:-.03em;margin-top:8px;position:relative')}>
          Ce ai chef să faci?
        </div>
        <div style={sx('font-size:13px;opacity:.85;margin-top:5px;position:relative')}>
          Alege un vibe, iar tics îți alege evenimentele.
        </div>

        <button className="airec-cta" onClick={() => go('assistant')}>
          🤖 Planifică-mi seara <Ic svg={I.arrow} />
        </button>

        <div className="scroll-x" style={sx('margin-top:13px;padding:1px 0;position:relative')}>
          {([
            ['🌙', 'Diseară'],
            ['🧘', 'Weekend relaxant'],
            ['🥂', 'Cu prietenii'],
            ['👨‍👩‍👧', 'Cu familia'],
          ] as [string, string][]).map(([emoji, label]) => (
            <button key={label} className="airec-chip" onClick={() => go('assistant')}>
              {emoji} {label}
            </button>
          ))}
        </div>

        <div className="between" style={sx('margin-top:16px;position:relative')}>
          <div className="row" style={sx('gap:7px;font-size:14px;font-weight:600')}>
            🤖 Pentru tine
          </div>
          <button
            onClick={() => go('prefsEdit')}
            className="row"
            style={sx('gap:5px;background:none;border:0;color:#c4b5fd;font:inherit;font-size:12.5px;cursor:pointer')}
          >
            personalizează <Ic svg={I.arrow} />
          </button>
        </div>
        <div className="muted" style={sx('font-size:11px;margin-top:3px;position:relative;color:rgba(255,255,255,.6)')}>
          {prefsSel.length} interese · {prefsSel.slice(0, 2).join(' & ') || 'încă neconfigurat'}
        </div>
      </div>

      {/* Eticheta de oras a plecat odata cu filtrul: n-are ce anunta cand
          lista nu mai e restransa la un oras. */}

      <div className="rail airec-rail">
        {rec.map((e) => (
          <EvMini key={e.id} ev={e} />
        ))}
      </div>

      {/* Bannerul „Pe val" a plecat de aici: e deja pe Acasa, iar „Pe val"
          are propriul tab in bara de jos. A treia intrare catre acelasi ecran,
          pe un ecran care ar trebui sa arate ce ALTCEVA mai exista. */}

      {/* ---------- TICS CALENDAR ----------
          Banda de zile, incepand cu azi, cu numarul de evenimente pe fiecare.
          O zi atinsa deschide Radarul filtrat pe ea; „Vezi luna" deschide
          grila intreaga. Cifrele vin din acelasi feed ca restul Radarului. */}
      <div className="sec">
        <SecH
          icon={I.cal}
          icbg="var(--green-soft)"
          iccol="var(--green-2)"
          title="tics Calendar"
          sub={monthLoading ? 'Se încarcă…' : `${daysStrip.reduce((n, d) => n + d.count, 0)} evenimente în perioada asta`}
          more={['Vezi luna', () => go('calendar')]}
        />
        <div className="scroll-x" style={sx('padding:2px 0')}>
          {daysStrip.map((d) => (
            <button
              key={d.stamp}
              className={cn('daypill', d.count === 0 && 'empty')}
              onClick={() => go('ticslist', { day: d.stamp })}
              disabled={d.count === 0}
            >
              <span className="daypill-dow">{d.dow}</span>
              <span className="daypill-num">{d.num}</span>
              <span className="daypill-n">{d.count === 0 ? '—' : d.count}</span>
            </button>
          ))}
          {monthLoading && !daysStrip.length
            ? Array.from({ length: 7 }).map((_, i) => (
                <div key={`sk${i}`} className="sk" style={sx('width:56px;height:76px;border-radius:16px;flex:none')} />
              ))
            : null}
        </div>
      </div>

      <div className="pad" style={sx('margin-top:16px')}>
        <div className="row" style={sx('gap:12px')}>
          <div
            className="mcard"
            onClick={() => go('calendar')}
            style={sx('flex:1;padding:15px 14px;background:var(--surface-solid)')}
          >
            <div className="iconbadge" style={sx('background:var(--green-soft);color:var(--green-2)')}>
              <Ic svg={I.cal} />
            </div>
            <div style={sx('font-weight:600;font-size:14px;margin-top:11px')}>Calendar</div>
            <div className="muted" style={sx('font-size:11px;margin-top:2px')}>
              Tot ce se întâmplă
            </div>
          </div>
          <div
            className="mcard"
            onClick={() => go('ticslist')}
            style={sx('flex:1;padding:15px 14px;background:var(--surface-solid)')}
          >
            <div className="iconbadge" style={sx('background:var(--cyan-soft);color:var(--cyan)')}>
              <Ic svg={I.layers} />
            </div>
            <div style={sx('font-weight:600;font-size:14px;margin-top:11px')}>Radar prețuri</div>
            <div className="muted" style={sx('font-size:11px;margin-top:2px')}>
              Cel mai bun preț
            </div>
          </div>
        </div>
      </div>

      <div className="sec">
        <SecH icon="🧭" icbg="var(--surface-3)" iccol="var(--ink)" title="Alege un vibe" sub="Tot ce se întâmplă, pe categorii" />
        <div className="pad">
          <div style={sx('display:grid;grid-template-columns:1fr 1fr;gap:14px')}>
            {pools.map((c) => (
              <CatCard key={c.name} c={c} />
            ))}
          </div>
        </div>
      </div>

      {/* „Langa tine" a plecat pe Acasa, unde e util la deschiderea aplicatiei,
          si e acum construita din coordonate reale (vezi features/client/nearby).
          Aici era o harta desenata cu doua puncte fixe si acelasi festival demo,
          la orice ora si in orice oras — arata a harta fara sa fie una. */}

      <BottomNav active="explore" />
    </div>
  );
}
