/* =========================================================
   EXPLOREAZA — port 1:1 al lui S.explore() din client-app.html (linia 689),
   inclusiv INIT.explore (rotatia imaginilor din cardurile de categorie).
   Sectiuni, in ordinea din prototip:
     antet · card AI de preferinte · rail recomandari · card "Pe val" ·
     Calendar + Radar preturi · "Alege un vibe" (CATPOOLS) · "Langa tine"
   ========================================================= */
import { Ic, sx } from '../../../design/sx';
import { CATPOOLS, EV, I } from '../../../mock/prototype';
import type { UiEvent } from '../../../api/tenantClient';
import { EvMini } from '../cards';
import { BottomNav, SafeTop, SecH } from '../kit';
import { useNav } from '../nav';
import { useShortsPreview } from '../shorts/useShortsPreview';
import { useCatalogEvents } from '../catalogData';
import { radarToUi, useRadarList } from '../radarData';
import { useClient } from '../../../store/client';
import { useRadarCategories } from '../radarData';
import { CatCard, poolsFromCategories, type Pool } from '../catCard';

const ev = (id: string) => (EV as Record<string, unknown>)[id] as UiEvent;

export function Explore() {
  const preview = useShortsPreview();
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

      <div className="pad" style={sx('margin-top:14px')}>
        <div
          onClick={() => go('prefsEdit')}
          style={sx(
            'cursor:pointer;border-radius:20px;overflow:hidden;background:linear-gradient(135deg,var(--indigo-3),#2a1065);border:1px solid var(--line-2);color:#fff;padding:16px;position:relative',
          )}
        >
          <div style={sx('position:absolute;right:-10px;bottom:-18px;font-size:88px;opacity:.2')}>🤖</div>
          <div
            className="row"
            style={sx('gap:6px;color:#c4b5fd;font-size:11px;font-weight:600;letter-spacing:.12em;text-transform:uppercase')}
          >
            <Ic svg={I.star} /> Recomandat pentru tine
          </div>
          <div style={sx('font-size:16px;font-weight:600;margin-top:7px;letter-spacing:-.02em')}>
            Pentru că îți place {prefsSel.slice(0, 2).join(' & ') || 'muzica'}
          </div>
          <div className="row" style={sx('gap:6px;font-size:12px;opacity:.85;margin-top:5px')}>
            {prefsSel.length} interese · editează <Ic svg={I.arrow} />
          </div>
        </div>
      </div>

      {/* Eticheta de oras a plecat odata cu filtrul: n-are ce anunta cand
          lista nu mai e restransa la un oras. */}

      <div className="rail" style={sx('margin-top:10px')}>
        {rec.map((e) => (
          <EvMini key={e.id} ev={e} />
        ))}
      </div>

      {/* ---------- „Pe val" ----------
          Era un gradient fix cu un text peste: arata ca un banner publicitar,
          nu ca o intrare in continut. Acum poarta chiar posterele din feed,
          deci se vede DIN CE intri, si se innoieste singur pe masura ce apar
          short-uri noi. Fara postere (feed gol sau offline) ramane varianta
          simpla, care nu depinde de retea. */}
      <div className="pad" style={sx('margin-top:18px')}>
        <div className="wave" onClick={() => go('shorts')} role="button" tabIndex={0}>
          <div className="wave-bg" />

          <div className="wave-stack" aria-hidden="true">
            {(preview?.posters ?? []).map((src, i) => (
              <span key={src} className={`wave-card p${i}`} style={{ backgroundImage: `url('${src}')` }} />
            ))}
          </div>

          <div className="wave-text">
            <div className="row" style={sx('gap:7px;font-size:10.5px;font-weight:700;letter-spacing:.12em;text-transform:uppercase;opacity:.9')}>
              <Ic svg={I.wave} /> Pe val
            </div>
            <div style={sx('font-size:22px;font-weight:700;letter-spacing:-.03em;margin-top:6px;line-height:1.1')}>
              Descoperă prin video
            </div>
            <div style={sx('font-size:12.5px;opacity:.88;margin-top:4px')}>
              {preview
                ? `${preview.count}+ momente de la artiști, locații și evenimente`
                : 'Scrolează evenimente ca la Shorts'}
            </div>
            <span className="wave-cta">
              <Ic svg={I.play} /> Începe
            </span>
          </div>
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
