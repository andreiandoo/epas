/* =========================================================
   CARDUL DE CATEGORIE („Alege un vibe")

   Era definit doar in Explore.tsx. De cand Radarul a intrat in bara de jos,
   Explore nu mai are intrare proprie, iar gridul de categorii a devenit greu
   de gasit — desi e principalul mod de a rasfoi ce se intampla. Cardul sta
   acum aici, ca sa-l poata folosi si Radar, si Explore, dintr-o singura sursa:
   doua copii ale aceleiasi rotatii de imagini s-ar fi desincronizat la prima
   modificare.
   ========================================================= */
import { useEffect, useRef, useState } from 'react';
import { Ic, sx } from '../../design/sx';
import { I, bgv } from '../../mock/prototype';
import { useNav } from './nav';
import type { RadarCategory } from '../../api/ticsRadar';

export type Pool = { name: string; count: number; c: string; route: string; pool: Record<string, unknown>[] };

/* Culorile pastilei de categorie — se rotesc, ca in prototip. */
const CAT_COLORS = ['#be185d', '#0f766e', '#0e7490', '#b45309', '#6d28d9', '#dc2626', '#4338ca', '#0891b2'];

/** Categoriile reale (TICS Radar) aduse la forma pe care o asteapta CatCard. */
export function poolsFromCategories(cats: RadarCategory[]): Pool[] {
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
export function CatCard({ c }: { c: Pool }) {
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
