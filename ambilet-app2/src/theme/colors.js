// AmBilet Scan v2 — light red/white theme.
//
// Reskinned from the dark/purple Tixello Staff palette. Every token
// name is preserved so existing screens that read `colors.purple` /
// `colors.background` / `colors.textPrimary` etc. keep compiling
// unchanged — only the VALUES flip. Token → value mapping mirrors
// redesign-mockups/MOCKUPS_README.md.
//
// New additions (safe — no old code references them yet):
//   - redAccent → bright link/action red (#C1121F)
//   - danger    → destructive-red (was overloaded on `red`)
//   - roleAdmin* / roleMgr* / roleStaff* → role-pill palette
//   - avatar*   → 4-color initials palette
//   - radius / shadow tokens → matches design-system.css
//
// Kept as an object literal (no theme provider) so any file can do
// `import { colors } from '../theme/colors'` and get the current
// value at import time, same as before.

export const colors = {
  // ---- App shell ----
  background: '#F6F2F2',
  surface: '#FFFFFF',
  surfaceHover: '#FBF8F8',
  border: '#ECE6E6',
  borderLight: '#E8E1E1',
  borderMedium: '#E0D8D8',

  // ---- Text ----
  textPrimary: '#1C1B1F',
  textSecondary: '#6B7280',
  textTertiary: '#9CA3AF',
  textQuaternary: '#C0C4CC',

  // ---- Brand red (replaces `purple`, name kept for source compat) ----
  purple: '#9A1B22',
  purpleSecondary: '#7A141A',
  purpleLight: '#FBEAEB',
  purpleBorder: 'rgba(154,27,34,0.22)',
  purpleBg: '#FBEAEB',
  purpleGlow: 'rgba(154,27,34,0.28)',

  // ---- Explicit brand-red aliases (preferred going forward) ----
  redPrimary: '#9A1B22',
  redSecondary: '#7A141A',
  redAccent: '#C1121F',
  redTint: '#FBEAEB',
  redTint2: '#F6DDDE',

  // ---- Destructive red (kept legible on white).
  //      Legacy code reads `red` for destructive actions — kept name,
  //      value retuned so brand-red (`purple`) and destructive-red
  //      don't visually collide.
  red: '#DC2626',
  redLight: '#FCE9E9',
  redBorder: 'rgba(220,38,38,0.25)',
  redBg: '#FCE9E9',

  danger: '#DC2626',
  dangerLight: '#FCE9E9',
  dangerBorder: 'rgba(220,38,38,0.25)',
  dangerBg: '#FCE9E9',

  // ---- Semantic (retuned for white bg) ----
  green: '#16A34A',
  greenLight: '#E7F6EC',
  greenBorder: 'rgba(22,163,74,0.25)',
  greenBg: '#E7F6EC',

  amber: '#D97706',
  amberLight: '#FDF0DD',
  amberBorder: 'rgba(217,119,6,0.25)',
  amberBg: '#FDF0DD',

  cyan: '#0E7490',
  cyanLight: '#E1F3F7',
  cyanBorder: 'rgba(14,116,144,0.25)',
  cyanBg: '#E1F3F7',

  // ---- Role pills ----
  roleAdminBg: '#FCE8E9',
  roleAdminFg: '#B0212B',
  roleMgrBg: '#E7F6EC',
  roleMgrFg: '#15803D',
  roleStaffBg: '#EEF1F4',
  roleStaffFg: '#5B6472',

  // ---- Avatar initials palette ----
  avatarRedBg: '#FBE4E5',
  avatarRedFg: '#B0212B',
  avatarBlueBg: '#E3ECFB',
  avatarBlueFg: '#1D4ED8',
  avatarAmberBg: '#FBEFD8',
  avatarAmberFg: '#B45309',
  avatarPurpleBg: '#EEE7FB',
  avatarPurpleFg: '#6D28D9',

  // ---- Neutrals ----
  white: '#FFFFFF',
  black: '#000000',

  // ---- Radius + shadow tokens ----
  radius: 16,
  radiusLg: 20,
  radiusSm: 10,
  shadowColor: '#140A0A',
};

export const gradients = {
  // Kept named `purple` for source compat — a few screens reference
  // gradients.purple for splash / login header / primary CTA. Value
  // now walks the AmBilet red gradient instead.
  purple: ['#9A1B22', '#7A141A'],
  red: ['#9A1B22', '#7A141A'],
  green: ['#16A34A', '#0F7B36'],
  // Was a dark solid — under the light theme the "surface" gradient
  // is a subtle warm-white so any BackgroundGradient consumer stays
  // legible against the new bg.
  surface: ['#FFFFFF', '#FBF8F8'],
};
