/* =========================================================
   EXPLOREAZA — port 1:1 al lui S.explore() din client-app.html (linia 689),
   inclusiv INIT.explore (rotatia imaginilor din cardurile de categorie).
   Sectiuni, in ordinea din prototip:
     antet · card AI de preferinte · rail recomandari · card "Pe val" ·
     Calendar + Radar preturi · "Alege un vibe" (CATPOOLS) · "Langa tine"
   ========================================================= */
import { useEffect, useRef, useState } from 'react';
import { Ic, sx } from '../../../design/sx';
import { CATPOOLS, EV, FEST, I, bgv } from '../../../mock/prototype';
import type { UiEvent } from '../../../api/tenantClient';
import { EvMini } from '../cards';
import { BottomNav, SafeTop, SecH } from '../kit';
import { useNav } from '../nav';
import { useClient } from '../../../store/client';
import { useRadarCategories } from '../radarData';
import type { RadarCategory } from '../../../api/ticsRadar';

const ev = (id: string) => (EV as Record<string, unknown>)[id] as UiEvent;

type Pool = { name: string; count: number; c: string; route: string; pool: Record<string, unknown>[] };

/* Culorile pastilei de categorie — se rotesc, ca in prototip. */
const CAT_COLORS = ['#be185d', '#0f766e', '#0e7490', '#b45309', '#6d28d9', '#dc2626', '#4338ca', '#0891b2'];

/** Categoriile reale (TICS Radar) aduse la forma pe care o asteapta CatCard. */
function poolsFromCategories(cats: RadarCategory[]): Pool[] {
  return cats.map((c, i) => ({
    name: c.cat,
    count: c.count,
    // culoarea oficiala a categoriei cand vine din feed; altfel paleta rotativa
    c: c.color ?? CAT_COLORS[i % CAT_COLORS.length],
    route: `go:ticslist:${c.type}`,
    pool: c.samples as unknown as Record<string, unknown>[],
  }));
}

/**
 * Cardul de categorie. INIT.explore roteste imaginea la 5s: opacity -> 0,
 * dupa 280ms schimba fundalul/emoji/titlul, apoi opacity -> 1.
 */
function CatCard({ c }: { c: Pool }) {
  const { go } = useNav();
  const [k, setK] = useState(0);
  const [visible, setVisible] = useState(true);
  const timers = useRef<ReturnType<typeof setTimeout>[]>([]);

  useEffect(() => {
    if (c.pool.length < 2) return;
    const id = setInterval(() => {
      setVisible(false);
      const t = setTimeout(() => {
        setK((x) => (x + 1) % c.pool.length);
        setVisible(true);
      }, 280);
      timers.current.push(t);
    }, 5000);
    return () => {
      clearInterval(id);
      timers.current.forEach(clearTimeout);
      timers.current = [];
    };
  }, [c.pool.length]);

  /* Categoriile fara evenimente in perioada acoperita n-au exemple; cardul
     ramane pe identitatea categoriei, ca sa nu crape si sa nu para gol. */
  const it = (c.pool[k] as { g: string; s: string } | undefined) ?? {
    g: '🎟',
    s: c.name,
    tone: `linear-gradient(150deg, ${c.c}, #1a1428)`,
  };

  const onClick = () => {
    // route are forma "go:ticslist:<event_type>" (categorii reale) sau, pentru
    // datasetul prototipului, "go:category:Concerte" / "go:festival"
    const parts = c.route.split(':');
    if (parts[0] !== 'go') return;
    /* "Alege un vibe" duce in Radar, filtrat pe categoria aleasa — acolo sunt
       evenimentele reale, cu preturi comparate pe platforme. */
    if (parts[1] === 'ticslist') return go('ticslist', { cat: c.name, catKey: parts[2] });
    if (parts[1] === 'category') return go('ticslist', { cat: c.name });
    go(parts[1], parts[2] ? { id: parts[2] } : undefined);
  };

  return (
    <div className="catcard" onClick={onClick}>
      <div
        className="cover catcover"
        style={{
          background: (it as { poster?: string }).poster
            ? `url('${(it as { poster?: string }).poster}') center/cover, #14101f`
            : bgv(it),
          height: 150,
          opacity: visible ? 1 : 0,
        }}
      >
        <span className="em catem">{it.g}</span>
        <div className="scrim" />
        <div className="btm">
          <div className="ctitle cattitle" style={sx('font-size:13px')}>
            {it.s}
          </div>
        </div>
      </div>
      <div className="catlabel">
        <span className="catcount" style={{ background: c.c }}>
          {c.count}
        </span>
        <span className="catname">{c.name}</span>
        <span style={{ marginLeft: 'auto', color: c.c }}>
          <Ic svg={I.arrow} />
        </span>
      </div>
    </div>
  );
}

export function Explore() {
  const { go } = useNav();
  const cats = useRadarCategories();
  /* Pana raspunde app.tics.ro ramanem pe categoriile prototipului, ca sectiunea
     sa nu apara goala; apoi le inlocuim cu cele reale (17 tipuri masurate). */
  const pools = cats.length ? poolsFromCategories(cats) : (CATPOOLS as unknown as Pool[]);
  const prefsSel = useClient((s) => s.prefsSel);
  const rec = ['coldplay', 'salina', 'swan'].map(ev);

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

      <div className="rail" style={sx('margin-top:14px')}>
        {rec.map((e) => (
          <EvMini key={e.id} ev={e} />
        ))}
      </div>

      <div className="pad" style={sx('margin-top:16px')}>
        <div className="mcard" onClick={() => go('shorts')} style={sx('overflow:hidden;cursor:pointer')}>
          <div style={sx('height:120px;position:relative;background:linear-gradient(120deg,#0e7490,#7c3aed,#ec4899)')}>
            <div
              style={sx('position:absolute;inset:0;background:radial-gradient(80% 100% at 20% 0%,rgba(255,255,255,.25),transparent 60%)')}
            />
            <div
              style={sx('position:absolute;left:16px;top:0;bottom:0;display:flex;flex-direction:column;justify-content:center;color:#fff')}
            >
              <div
                className="row"
                style={sx('gap:7px;font-size:11px;font-weight:700;letter-spacing:.1em;text-transform:uppercase')}
              >
                <Ic svg={I.wave} /> Nou
              </div>
              <div
                style={sx('font-size:19px;font-weight:700;letter-spacing:-.02em;margin-top:5px;text-shadow:0 2px 12px rgba(0,0,0,.4)')}
              >
                Pe val
              </div>
              <div style={sx('font-size:12px;opacity:.9;margin-top:2px')}>Scrolează evenimente ca la Shorts</div>
            </div>
            <div
              style={sx('position:absolute;right:16px;top:50%;transform:translateY(-50%);width:52px;height:52px;border-radius:50%;background:rgba(255,255,255,.9);display:grid;place-items:center;color:#7c3aed')}
            >
              <Ic svg={I.play} />
            </div>
            <div style={sx('position:absolute;right:74px;top:16px;font-size:34px;opacity:.5')}>🌊</div>
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

      <div className="sec">
        <SecH icon={I.pin} icbg="var(--indigo-soft)" iccol="var(--indigo-2)" title="Lângă tine" sub="Pe hartă" />
        <div className="pad">
          <div className="mcard" onClick={() => go('festival')}>
            <div style={sx('height:132px;background:linear-gradient(120deg,#1a1730,#0f0d18);position:relative')}>
              <div style={sx('position:absolute;inset:0;background-image:var(--grid);background-size:24px 24px;opacity:.6')} />
              <div
                style={sx('position:absolute;left:40%;top:44%;width:16px;height:16px;border-radius:50%;background:var(--indigo);border:3px solid var(--bg);box-shadow:var(--sh-p)')}
              />
              <div
                style={sx('position:absolute;left:66%;top:32%;width:12px;height:12px;border-radius:50%;background:var(--cyan);border:3px solid var(--bg)')}
              />
              <span className="gpill" style={sx('position:absolute;left:12px;top:12px')}>
                📍 2 lângă tine
              </span>
            </div>
            <div className="selrow">
              <div className="iconbadge" style={{ background: FEST.tone }}>
                🎪
              </div>
              <div style={sx('flex:1')}>
                <div style={sx('font-weight:600;font-size:14px')}>Nordvale Festival</div>
                <div className="metaline" style={sx('margin-top:3px')}>
                  <Ic svg={I.pin} />
                  <span>Wonderland</span>
                  <span className="dot" />
                  <span>8 km</span>
                </div>
              </div>
              <span
                className="circ"
                style={sx('position:static;background:var(--surface-3);border-color:var(--line-2);color:var(--indigo-2);backdrop-filter:none')}
              >
                <Ic svg={I.arrow} />
              </span>
            </div>
          </div>
        </div>
      </div>

      <BottomNav active="explore" />
    </div>
  );
}
