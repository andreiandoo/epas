/* =========================================================
   ASISTENT AI — „Planifică-mi seara"

   Portat dupa macheta din elements/designs (OnPaste.20260814-114549):
   bula ta cu cererea, raspunsul, trei randuri de alegeri (cu cine · oras ·
   buget), planul compus si costul estimat.

   CE FACE, DE FAPT
   Nu vorbeste cu un model de limbaj si nu se preface ca o face. Planul se
   compune din catalogul nostru: se iau evenimentele viitoare din orasul ales,
   se filtreaza dupa buget, si se aseaza in ordinea in care ai petrece seara —
   ceva „inainte" (o experienta, ceva de vazut devreme) si „evenimentul"
   propriu-zis. Asta e tot ce promitea si macheta: un plan din ce exista, nu o
   conversatie.

   De ce nu un LLM: raspunsul trebuie sa contina DOAR evenimente care exista,
   cu preturile lor de acum si cu bilete cumparabile. Un model care compune
   text ar inventa exact partea care nu se poate inventa. Daca se adauga mai
   tarziu, rolul lui e sa aleaga si sa formuleze — nu sa produca datele.
   ========================================================= */
import { useEffect, useMemo, useState } from 'react';
import { Ic, Raw, cn, sx } from '../../../design/sx';
import { I } from '../../../mock/prototype';
import { radarMark } from '../../../design/radarMark';
import { CityPicker } from '../cityPicker';
import { BottomNav, TopBar } from '../kit';
import { useNav } from '../nav';
import { useClient } from '../../../store/client';
import { useCatalogEvents } from '../catalogData';
import type { UiEvent } from '../../../api/tenantClient';

type Who = 'solo' | 'friends' | 'date' | 'family';

const WHO: [Who, string, string][] = [
  ['solo', '🎤', 'Singur'],
  ['friends', '🥂', 'Cu prietenii'],
  ['date', '💜', 'În doi'],
  ['family', '👨‍👩‍👧', 'Cu familia'],
];

const BUDGETS: [number, string][] = [
  [100, '100 lei'],
  [250, '250 lei'],
  [500, '500 lei'],
  [0, 'Oricât'],
];

/** Cate evenimente intra intr-un plan de seara. Mai multe n-ar incapea intr-o seara. */
const PLAN_SIZE = 2;

const DOW = ['dum', 'lun', 'mar', 'mie', 'joi', 'vin', 'sâm'];

/**
 * Zilele oferite: azi + doua saptamani.
 *
 * Calculate o singura data, la incarcarea modulului: sunt aceleasi pentru
 * toata sesiunea, iar recalcularea la fiecare randare ar da un tablou nou si
 * ar reporni cererile.
 */
const DAYS = (() => {
  const base = new Date();
  base.setHours(0, 0, 0, 0);

  return Array.from({ length: 15 }, (_, i) => {
    const d = new Date(base);
    d.setDate(base.getDate() + i);

    const iso = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
    const label = i === 0 ? 'Azi' : i === 1 ? 'Mâine' : `${DOW[d.getDay()]} ${d.getDate()}`;

    return { iso, label };
  });
})();

export function Assistant() {
  const { go, back } = useNav();
  const city = useClient((s) => s.city);

  const [who, setWho] = useState<Who>('friends');
  const [town, setTown] = useState(city || '');
  const [budget, setBudget] = useState(250);
  /* Ziua planului. Fara ea, planul aduna evenimente din zile diferite — doua
     lucruri pe care nu le poti face in aceeasi seara. Gol = oricand. */
  const [day, setDay] = useState('');
  /* De cate ori ai cerut alte sugestii. Muta fereastra peste candidati, deci
     apasarea da chiar ALT plan, nu acelasi reasezat. */
  const [reroll, setReroll] = useState(0);
  const [citySheet, setCitySheet] = useState(false);

  /* Se cer mai multe decat afisam: dupa filtrarea pe buget raman mai putine,
     iar un plan de un singur eveniment nu e un plan. */
  const { items, loading } = useCatalogEvents({
    city: town || undefined,
    date: day || undefined,
    limit: 30,
  });

  useEffect(() => {
    if (!town && city) setTown(city);
  }, [city, town]);

  const priceOf = (e: UiEvent) => Number((e as unknown as { from?: number }).from ?? 0);

  /* Planul: intai ce e mai ieftin („inainte"), apoi evenimentul principal.
     Ordinea nu e estetica — asa se petrece o seara, iar suma trebuie sa intre
     in buget PE PERSOANA. */
  const plan = useMemo(() => {
    const usable = items.filter((e) => priceOf(e) > 0).sort((a, b) => priceOf(a) - priceOf(b));
    const cap = budget || Number.POSITIVE_INFINITY;
    const picked: UiEvent[] = [];
    let total = 0;

    /* „Alte sugestii" porneste cautarea mai departe in lista si o continua
       circular. Asa a doua apasare da alt plan, nu acelasi in alta ordine —
       iar cand ai epuizat candidatii, revii la inceput in loc sa ramai fara. */
    const start = usable.length ? (reroll * PLAN_SIZE) % usable.length : 0;

    for (let k = 0; k < usable.length && picked.length < PLAN_SIZE; k++) {
      const e = usable[(start + k) % usable.length];
      if (picked.includes(e)) continue;
      if (total + priceOf(e) > cap) continue;
      picked.push(e);
      total += priceOf(e);
    }

    return { picked, total };
  }, [items, budget, reroll]);

  const whoLabel = WHO.find((w) => w[0] === who)?.[2] ?? '';
  const dayLabel = day ? (DAYS.find((d) => d.iso === day)?.label ?? day) : 'seara';

  return (
    <div className="grid" style={sx('min-height:100%')}>
      <TopBar>
        <div className="row" style={sx('gap:12px')}>
          <div className="icon-btn" onClick={back}>
            <Ic svg={I.back} />
          </div>
          <div className="row" style={sx('gap:10px')}>
            <div style={sx('width:34px;height:34px;border-radius:12px;overflow:hidden;flex:none')}>
              <Raw html={radarMark(34)} />
            </div>
            <div>
              <div className="h2">tics AI</div>
              <div className="row" style={sx('gap:5px;font-size:11px;color:var(--green-2);font-weight:600')}>
                <span className="livedot" /> planuri din catalogul real
              </div>
            </div>
          </div>
        </div>
        <div style={sx('width:42px')} />
      </TopBar>

      {/* Cererea ta, ca in macheta: bula mov, aliniata la dreapta. */}
      <div className="pad" style={sx('margin-top:14px;display:flex;justify-content:flex-end')}>
        <div className="aibubble me">
          {`Planifică-mi ${dayLabel.toLowerCase()} ${whoLabel.toLowerCase()}${town ? ` · ${town}` : ''}`}
        </div>
      </div>

      <div className="pad" style={sx('margin-top:9px;display:flex')}>
        <div className="aibubble">
          {loading
            ? 'Mă uit ce se întâmplă…'
            : plan.picked.length
              ? `Am compus un plan ${whoLabel.toLowerCase()}${town ? ` în ${town}` : ''}${budget ? `, sub ${budget} lei` : ''}:`
              : `N-am găsit nimic care să intre în ${budget ? `${budget} lei` : 'filtrele astea'}${town ? ` în ${town}` : ''}. Încearcă alt oraș sau un buget mai mare.`}
        </div>
      </div>

      {/* ---------- ALEGERILE ---------- */}
      <div className="pad" style={sx('margin-top:12px')}>
        <div className="card" style={sx('padding:15px')}>
          <div className="label" style={sx('margin-bottom:8px')}>
            Cu cine
          </div>
          <div className="scroll-x" style={sx('padding:1px 0;margin:0 -2px')}>
            {WHO.map(([id, emoji, label]) => (
              <button key={id} className={cn('chip', who === id && 'ind on')} onClick={() => setWho(id)}>
                {emoji} {label}
              </button>
            ))}
          </div>

          <div className="label" style={sx('margin:14px 0 8px')}>
            Când
          </div>
          <div className="scroll-x" style={sx('padding:1px 0;margin:0 -2px')}>
            <button className={cn('chip', !day && 'ind on')} onClick={() => setDay('')}>
              Oricând
            </button>
            {DAYS.map((d) => (
              <button key={d.iso} className={cn('chip', day === d.iso && 'ind on')} onClick={() => setDay(d.iso)}>
                {d.label}
              </button>
            ))}
          </div>

          <div className="between" style={sx('margin:14px 0 8px')}>
            <span className="label" style={sx('margin:0')}>
              Oraș
            </span>
            <span className="muted" style={sx('font-size:10.5px')}>
              orice localitate din România
            </span>
          </div>
          {/* Cautare, nu o lista de chip-uri: chip-urile ofereau doar orasele
              din feed, deci cine locuieste in alta parte nu se putea alege pe
              sine. Lista vine din `geo_localities`. */}
          <div className="row" style={sx('gap:8px')}>
            <button className={cn('chip', !town && 'ind on')} onClick={() => setTown('')}>
              Oriunde
            </button>
            <button className="chip ind on" onClick={() => setCitySheet(true)} style={sx('flex:1;justify-content:space-between')}>
              <span className="row" style={sx('gap:6px;min-width:0')}>
                <Ic svg={I.pin} />
                <span style={sx('overflow:hidden;text-overflow:ellipsis;white-space:nowrap')}>
                  {town || 'Alege orașul'}
                </span>
              </span>
              <span style={sx('color:var(--muted)')}>⌄</span>
            </button>
          </div>

          <div className="label" style={sx('margin:14px 0 8px')}>
            Buget / persoană
          </div>
          <div className="scroll-x" style={sx('padding:1px 0;margin:0 -2px')}>
            {BUDGETS.map(([v, label]) => (
              <button key={label} className={cn('chip', budget === v && 'ind on')} onClick={() => setBudget(v)}>
                {label}
              </button>
            ))}
          </div>
        </div>
      </div>

      {/* ---------- PLANUL ---------- */}
      <div className="pad" style={sx('margin-top:16px')}>
        <div className="label" style={sx('margin-bottom:9px')}>
          Planul tău
        </div>

        {loading ? (
          <div style={sx('display:flex;flex-direction:column;gap:10px')}>
            <div className="sk" style={sx('height:76px;border-radius:18px')} />
            <div className="sk" style={sx('height:76px;border-radius:18px')} />
          </div>
        ) : null}

        {!loading && !plan.picked.length ? (
          <div className="muted" style={sx('font-size:12.5px;line-height:1.6;padding:10px 0')}>
            Planul se compune din evenimente reale, cu bilete cumpărabile. Când nu intră niciunul în buget,
            preferăm să-ți spunem, nu să-ți propunem ceva ce nu poți lua.
          </div>
        ) : null}

        <div style={sx('display:flex;flex-direction:column;gap:10px')}>
          {plan.picked.map((e, i) => (
            <div
              key={e.id}
              className="card"
              onClick={() => go('event', { id: e.id })}
              style={sx('padding:11px;cursor:pointer')}
            >
              <div className="row" style={sx('gap:12px')}>
                <div
                  style={{
                    width: 58,
                    height: 58,
                    borderRadius: 16,
                    flex: 'none',
                    background: (e as unknown as { poster?: string }).poster
                      ? `url('${(e as unknown as { poster: string }).poster}') center/cover`
                      : 'linear-gradient(135deg,#2a1065,#0f0d18)',
                  }}
                />
                <div style={sx('flex:1;min-width:0')}>
                  <span className="badge" style={sx('background:var(--surface-3);color:var(--ink-2)')}>
                    {i + 1} · {i === 0 && plan.picked.length > 1 ? 'Înainte' : 'Evenimentul'}
                  </span>
                  <div style={sx('font-weight:600;font-size:14px;margin-top:5px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis')}>
                    {e.s}
                  </div>
                  <div className="metaline" style={sx('margin-top:3px')}>
                    <Ic svg={I.pin} />
                    <span>{e.city}</span>
                    <span className="dot" />
                    <span>{e.d}</span>
                  </div>
                </div>
                <div style={sx('font-weight:700;font-size:14px;color:var(--indigo-2);flex:none')}>
                  {priceOf(e)} lei
                </div>
              </div>
            </div>
          ))}
        </div>

        {plan.picked.length ? (
          <div className="card" style={sx('padding:15px;margin-top:12px')}>
            <div className="between">
              <div>
                <div className="muted" style={sx('font-size:11px')}>
                  Cost estimativ / persoană
                </div>
                <div style={sx('font-size:26px;font-weight:700;margin-top:2px')}>
                  {plan.total} <span style={sx('font-size:13px;font-weight:600;color:var(--muted)')}>lei</span>
                </div>
              </div>
              <button
                className="cta"
                onClick={() => go('event', { id: plan.picked[0].id })}
                style={sx('width:auto;padding:13px 20px')}
              >
                Ia biletele <Ic svg={I.arrow} />
              </button>
            </div>
            {/* Cinstit: nu putem cumpara doua evenimente dintr-o singura
                comanda — sunt organizatori diferiti, cu procesatoare diferite. */}
            {plan.picked.length > 1 ? (
              <div className="muted" style={sx('font-size:11px;margin-top:10px;line-height:1.5')}>
                Biletele se cumpără separat, de la fiecare organizator. Butonul te duce la primul.
              </div>
            ) : null}
          </div>
        ) : null}
      </div>

      {/* „Alte sugestii" sta la FINAL, dupa plan: se apasa dupa ce ai citit ce
          ti-am propus, nu inainte. */}
      {!loading && items.length > PLAN_SIZE ? (
        <div className="pad" style={sx('margin-top:14px')}>
          <button className="cta ghost" onClick={() => setReroll((n) => n + 1)} style={sx('padding:13px')}>
            <Ic svg={I.star} /> Dă-mi alte sugestii
          </button>
        </div>
      ) : null}

      {citySheet ? (
        <CityPicker
          value={town}
          onPick={(c) => {
            setTown(c);
            setReroll(0);
          }}
          onClose={() => setCitySheet(false)}
        />
      ) : null}

      <div style={{ height: 'var(--ep-nav-space)' }} />
      <BottomNav active="explore" />
    </div>
  );
}
