// Paleta Sf. Ana — bazată pe design-ul HTML al biletului
export const colors = {
  // Forest palette
  forest50: '#F0FAF4', forest100: '#DCF2E3', forest200: '#BBE5C9', forest300: '#8DCFA5',
  forest400: '#5BB17F', forest500: '#3D9663', forest600: '#2D7A4F', forest700: '#256142',
  forest800: '#1F4E37', forest900: '#0F2C20',
  // Lake palette
  lake300: '#67E8F9', lake400: '#22D3EE', lake600: '#0891B2', lake800: '#155E75',
  // Sand
  sand100: '#F1EBE0', sand400: '#B89968',
  // Roles
  ink: '#0F1E1A', paper: '#FAFBF8',

  // App semantic (legacy keys folosite în ecranele existente — păstrate pt compat)
  background: '#0F2C20',
  surface: '#1F4E37',
  surfaceHover: '#256142',
  border: '#256142',
  borderLight: 'rgba(255,255,255,0.08)',
  borderMedium: 'rgba(255,255,255,0.12)',

  textPrimary: '#FAFBF8',
  textSecondary: 'rgba(250,251,248,0.7)',
  textTertiary: 'rgba(250,251,248,0.5)',
  textQuaternary: 'rgba(250,251,248,0.35)',

  primary: '#5BB17F',
  primaryDark: '#3D9663',
  accent: '#22D3EE',

  // Status colors
  success: '#10B981',
  warning: '#FBBF24',
  danger: '#F87171',
  info: '#0891B2',

  // Aliases pentru compat cu screen-uri existente
  purple: '#5BB17F',
  purpleSecondary: '#3D9663',
  purpleLight: 'rgba(91,177,127,0.15)',
  purpleBorder: 'rgba(91,177,127,0.3)',
  purpleBg: 'rgba(91,177,127,0.08)',
  purpleGlow: 'rgba(91,177,127,0.4)',
};

export const roleColors = {
  operator_boats:           { accent: '#22D3EE', emoji: '🛶', label: 'Operator închiriere bărci' },
  operator_pontoon:         { accent: '#0891B2', emoji: '🎫', label: 'Operator validare bilete vaporaș' },
  operator_pontoon_rental:  { accent: '#06B6D4', emoji: '🚤', label: 'Operator închiriere vaporaș' },
  operator_sled:            { accent: '#60A5FA', emoji: '🛷', label: 'Operator închiriere sănii' },
  operator_tow_validation:  { accent: '#F59E0B', emoji: '🪢', label: 'Operator validare tractări' },
  sales_operator:           { accent: '#5BB17F', emoji: '💳', label: 'Operator POS (fix casă)' },
  gate_scanner:             { accent: '#B89968', emoji: '✅', label: 'Operator check-in' },
  field_seller:             { accent: '#A78BFA', emoji: '📱', label: 'Operator teren' },
  shift_manager:            { accent: '#FBBF24', emoji: '👤', label: 'Manager schimb' },
  accountant:               { accent: '#94A3B8', emoji: '📊', label: 'Contabil' },
  admin_mobile:             { accent: '#FBBF24', emoji: '⭐', label: 'Membru admin (scan + vânzare)' },
};

export function getRoleConfig(role) {
  return roleColors[role] || { accent: '#5BB17F', emoji: '👤', label: role || 'Operator' };
}
