// Alternate palettes for the theme toggle in Settings. The active palette
// is copied INTO the mutable `colors` object exported from theme/colors.js
// as early as possible during app boot. Because React Native evaluates
// screen imports (and their StyleSheet.create calls) at bundle load time,
// switching themes at runtime cannot reliably re-style every screen — the
// setting is persisted and applied on the next cold start.
//
// Three modes:
//   light     — the default AmBilet red-on-white identity (v2 baseline)
//   dark      — inverted for night-time festival use; brand red stays red
//                 but surfaces flip to near-black, text flips to near-white
//   lowLight  — same colours as light, but darker text + thicker borders so
//                 numbers read cleanly on a dim device / poor projector-lit
//                 environments. Everything else identical to light.

export const LIGHT_PALETTE = {
  background: '#F6F2F2',
  surface: '#FFFFFF',
  surfaceHover: '#FBF8F8',
  border: '#ECE6E6',
  borderLight: '#E8E1E1',
  borderMedium: '#E0D8D8',
  textPrimary: '#1C1B1F',
  textSecondary: '#6B7280',
  textTertiary: '#9CA3AF',
  textQuaternary: '#C0C4CC',
  shadowColor: '#140A0A',
};

export const DARK_PALETTE = {
  background: '#0F0A0B',
  surface: '#1B1113',
  surfaceHover: '#241618',
  border: '#2E1E20',
  borderLight: '#3A2528',
  borderMedium: '#4A2C30',
  textPrimary: '#F6F2F2',
  textSecondary: '#B7ADAF',
  textTertiary: '#7C7274',
  textQuaternary: '#4B4243',
  shadowColor: '#000000',
};

// Low-light = light theme with (a) darker primary text, (b) darker
// secondary text, (c) thicker/darker borders for higher contrast. Keeps
// operator's eyes adapted to daylight while still being readable in dusk.
export const LOW_LIGHT_PALETTE = {
  background: '#F6F2F2',
  surface: '#FFFFFF',
  surfaceHover: '#F4EEEE',
  border: '#B8AFAF',
  borderLight: '#B0A7A7',
  borderMedium: '#9E9494',
  textPrimary: '#0A0708',
  textSecondary: '#3B3033',
  textTertiary: '#5A4E51',
  textQuaternary: '#7D7274',
  shadowColor: '#140A0A',
};

export const PALETTES = {
  light: LIGHT_PALETTE,
  dark: DARK_PALETTE,
  lowLight: LOW_LIGHT_PALETTE,
};

// Apply a palette by mutating the exported colors object in place so any
// downstream module that already captured a reference to `colors` sees the
// new values. NB: StyleSheet.create snapshots values at call time — so
// this only affects styles evaluated AFTER the mutation runs. In practice
// that means "next cold start" for most screens.
export function applyPalette(colorsObject, mode) {
  const palette = PALETTES[mode] || LIGHT_PALETTE;
  Object.assign(colorsObject, palette);
}
