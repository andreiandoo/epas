// Tixello Theme Configuration
// Based on the template design

export const colors = {
  // Primary
  primary: '#8B5CF6',
  primaryLight: 'rgba(139, 92, 246, 0.2)',
  primaryDark: '#6366F1',

  // Background
  background: '#0A0A0F',
  backgroundLight: '#12121A',
  backgroundCard: 'rgba(255, 255, 255, 0.03)',
  backgroundCardHover: 'rgba(255, 255, 255, 0.06)',

  // Text
  textPrimary: '#FFFFFF',
  textSecondary: 'rgba(255, 255, 255, 0.7)',
  textMuted: 'rgba(255, 255, 255, 0.5)',
  textDisabled: 'rgba(255, 255, 255, 0.3)',

  // Status Colors
  success: '#10B981',
  successLight: 'rgba(16, 185, 129, 0.2)',
  warning: '#F59E0B',
  warningLight: 'rgba(245, 158, 11, 0.2)',
  error: '#EF4444',
  errorLight: 'rgba(239, 68, 68, 0.2)',
  info: '#06B6D4',
  infoLight: 'rgba(6, 182, 212, 0.2)',

  // Border
  border: 'rgba(255, 255, 255, 0.1)',
  borderLight: 'rgba(255, 255, 255, 0.05)',
  borderPrimary: 'rgba(139, 92, 246, 0.3)',

  // Specific
  live: '#10B981',
  offline: '#EF4444',
  cardPayment: '#06B6D4',
  cashPayment: '#10B981',
};

export const spacing = {
  xs: 4,
  sm: 8,
  md: 12,
  lg: 16,
  xl: 20,
  xxl: 24,
  xxxl: 32,
};

export const borderRadius = {
  sm: 6,
  md: 10,
  lg: 14,
  xl: 16,
  xxl: 20,
  round: 9999,
};

export const typography = {
  fontFamily: {
    regular: 'System',
    medium: 'System',
    semibold: 'System',
    bold: 'System',
    mono: 'Courier',
  },
  fontSize: {
    xs: 10,
    sm: 12,
    md: 14,
    lg: 16,
    xl: 18,
    xxl: 20,
    xxxl: 24,
    display: 28,
    hero: 36,
  },
  lineHeight: {
    tight: 1.2,
    normal: 1.5,
    relaxed: 1.75,
  },
};

export const shadows = {
  sm: {
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.1,
    shadowRadius: 4,
    elevation: 2,
  },
  md: {
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 4 },
    shadowOpacity: 0.15,
    shadowRadius: 8,
    elevation: 4,
  },
  lg: {
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 10 },
    shadowOpacity: 0.2,
    shadowRadius: 20,
    elevation: 8,
  },
  primary: {
    shadowColor: colors.primary,
    shadowOffset: { width: 0, height: 10 },
    shadowOpacity: 0.4,
    shadowRadius: 40,
    elevation: 10,
  },
};

// Common style patterns
export const commonStyles = {
  container: {
    flex: 1,
    backgroundColor: colors.background,
  },
  card: {
    backgroundColor: colors.backgroundCard,
    borderRadius: borderRadius.xl,
    borderWidth: 1,
    borderColor: colors.borderLight,
    padding: spacing.lg,
  },
  cardPrimary: {
    backgroundColor: colors.primaryLight,
    borderRadius: borderRadius.xl,
    borderWidth: 1,
    borderColor: colors.borderPrimary,
    padding: spacing.lg,
  },
  button: {
    flexDirection: 'row' as const,
    alignItems: 'center' as const,
    justifyContent: 'center' as const,
    paddingVertical: spacing.lg,
    paddingHorizontal: spacing.xl,
    borderRadius: borderRadius.lg,
  },
  buttonPrimary: {
    backgroundColor: colors.primary,
  },
  buttonSecondary: {
    backgroundColor: colors.backgroundCard,
    borderWidth: 1,
    borderColor: colors.border,
  },
  buttonText: {
    color: colors.textPrimary,
    fontSize: typography.fontSize.lg,
    fontWeight: '600' as const,
  },
  input: {
    backgroundColor: colors.backgroundCard,
    borderRadius: borderRadius.lg,
    borderWidth: 1,
    borderColor: colors.border,
    paddingHorizontal: spacing.lg,
    paddingVertical: spacing.lg,
    color: colors.textPrimary,
    fontSize: typography.fontSize.md,
  },
  inputFocused: {
    borderColor: colors.primary,
    backgroundColor: colors.primaryLight,
  },
  sectionTitle: {
    fontSize: typography.fontSize.md,
    fontWeight: '600' as const,
    color: colors.textSecondary,
    marginBottom: spacing.md,
  },
  statusBadge: {
    flexDirection: 'row' as const,
    alignItems: 'center' as const,
    paddingHorizontal: spacing.md,
    paddingVertical: spacing.xs,
    borderRadius: borderRadius.sm,
  },
  statusDot: {
    width: 6,
    height: 6,
    borderRadius: 3,
    marginRight: spacing.sm,
  },
};
