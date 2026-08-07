/* =========================================================
   Utilitare de FIDELITATE pentru portul din prototip.

   Problema pe care o rezolva: traducerea manuala a lui
       style="padding:4px 20px 11px;gap:11px"
   in
       style={{ padding: '4px 20px 11px', gap: 11 }}
   e exact pasul unde s-au pierdut spatierile la prima incercare de port.

   Solutia: copiem sirul de stil VERBATIM din prototip si il parsam la runtime.
       <div className="hdr" style={sx('padding:4px 20px 11px;gap:11px')}>
   Zero rescriere manuala => zero drift fata de prototip.
   ========================================================= */
import { useMemo, type CSSProperties, type ReactNode } from 'react';

/** Cache global: acelasi sir de stil apare de zeci de ori intre ecrane. */
const cache = new Map<string, CSSProperties>();

const toCamel = (prop: string) =>
  prop.startsWith('--') ? prop : prop.replace(/-([a-z])/g, (_, c: string) => c.toUpperCase());

/**
 * Converteste un sir CSS inline (exact cum apare in atributul style="" din
 * prototip) intr-un obiect de stil React.
 *
 * Suporta: proprietati custom (--x), valori cu ';' in interiorul unui url(...)
 * sau al unui data URI, si valori cu ghilimele.
 */
export function sx(css: string): CSSProperties {
  const hit = cache.get(css);
  if (hit) return hit;

  const out: Record<string, string> = {};
  let depth = 0;
  let quote: string | null = null;
  let buf = '';

  const flush = () => {
    const decl = buf.trim();
    buf = '';
    if (!decl) return;
    const idx = decl.indexOf(':');
    if (idx < 0) return;
    const prop = decl.slice(0, idx).trim();
    const value = decl.slice(idx + 1).trim();
    if (!prop || !value) return;
    out[toCamel(prop)] = value;
  };

  for (const ch of css) {
    if (quote) {
      buf += ch;
      if (ch === quote) quote = null;
      continue;
    }
    if (ch === '"' || ch === "'") {
      quote = ch;
      buf += ch;
      continue;
    }
    if (ch === '(') depth++;
    else if (ch === ')') depth--;

    if (ch === ';' && depth === 0) {
      flush();
      continue;
    }
    buf += ch;
  }
  flush();

  const style = out as unknown as CSSProperties;
  cache.set(css, style);
  return style;
}

/** Varianta hook, pentru siruri compuse dinamic (evita re-parsarea la fiecare render). */
export const useSx = (css: string) => useMemo(() => sx(css), [css]);

/**
 * Randeaza un SVG inline din prototip (obiectul `I` din src/mock/prototype.ts)
 * fara sa-l traducem in JSX. Iconitele sunt siruri de markup in prototip;
 * orice rescriere manuala ar risca sa piarda un atribut.
 */
export function Ic({
  svg,
  className,
  style,
}: {
  svg: string;
  className?: string;
  style?: CSSProperties;
}) {
  return <span className={className} style={style} dangerouslySetInnerHTML={{ __html: svg }} />;
}

/**
 * Fragment de markup portat verbatim din prototip, acolo unde traducerea in
 * JSX nu aduce nimic (blocuri pur decorative: mesh-uri, grid-uri, sparkles).
 * Se foloseste rar si deliberat — restul ecranelor sunt componente reale.
 */
export function Raw({ html, className, style }: { html: string; className?: string; style?: CSSProperties }) {
  return <div className={className} style={style} dangerouslySetInnerHTML={{ __html: html }} />;
}

/** Helper pentru liste de clase conditionate, ca in prototip. */
export const cn = (...xs: (string | false | null | undefined)[]) => xs.filter(Boolean).join(' ');

export type { ReactNode };
