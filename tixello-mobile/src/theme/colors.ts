/**
 * Tixello design tokens — dark, mobile-first.
 * Ground palette + semantics are fixed; the ACCENT changes per active
 * context (organizer / theatre / venue / artist / agency / festival),
 * exactly like the web templates.
 */

export const palette = {
  bg: '#080c10',
  panel: '#0a1016',
  surface: '#0e1318',
  surface2: '#141c24',

  border: 'rgba(255,255,255,0.07)',
  border2: 'rgba(255,255,255,0.12)',
  border3: 'rgba(255,255,255,0.18)',

  text: '#f0f4ff',
  muted: 'rgba(240,244,255,0.5)',
  hint: 'rgba(240,244,255,0.28)',
  faint: 'rgba(240,244,255,0.16)',

  // semantics
  success: '#3ddb8a',
  warning: '#f5a623',
  danger: '#f04f4f',
  pink: '#f03e8f',
  teal: '#1ddab4',
  purple: '#9b7ff8',
} as const;

export type ContextKind =
  | 'organizer'
  | 'theatre'
  | 'venue'
  | 'artist'
  | 'agency'
  | 'festival'
  | 'tenant';

/** Per-context accent (hex) + soft/border tints derived from it. */
export const contextAccent: Record<ContextKind, string> = {
  organizer: '#00c896',
  theatre: '#9b60c8',
  venue: '#00e5ff',
  artist: '#1ddab4',
  agency: '#9b7ff8',
  festival: '#f03e8f',
  tenant: '#00c896',
};

/** rgba() from a #rrggbb hex + alpha, so we can tint the accent at runtime. */
export function withAlpha(hex: string, alpha: number): string {
  const h = hex.replace('#', '');
  const r = parseInt(h.substring(0, 2), 16);
  const g = parseInt(h.substring(2, 4), 16);
  const b = parseInt(h.substring(4, 6), 16);
  return `rgba(${r},${g},${b},${alpha})`;
}

export interface Accent {
  base: string;
  soft: string;
  border: string;
}

export function accentFor(kind: ContextKind, override?: string | null): Accent {
  const base = override || contextAccent[kind] || contextAccent.organizer;
  return {
    base,
    soft: withAlpha(base, 0.13),
    border: withAlpha(base, 0.32),
  };
}

export const radius = { sm: 10, md: 14, lg: 18, xl: 22, pill: 999 } as const;

export const spacing = { xs: 4, sm: 8, md: 12, lg: 16, xl: 24 } as const;
