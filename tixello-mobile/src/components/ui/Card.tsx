import React from 'react';
import { View, StyleSheet, ViewStyle } from 'react-native';
import { colors, borderRadius, spacing } from '../../utils/theme';

interface CardProps {
  children: React.ReactNode;
  variant?: 'default' | 'primary' | 'success' | 'warning' | 'error';
  style?: ViewStyle;
  padding?: 'none' | 'sm' | 'md' | 'lg';
}

export const Card: React.FC<CardProps> = ({
  children,
  variant = 'default',
  style,
  padding = 'md',
}) => {
  const getVariantStyle = (): ViewStyle => {
    switch (variant) {
      case 'primary':
        return styles.primary;
      case 'success':
        return styles.success;
      case 'warning':
        return styles.warning;
      case 'error':
        return styles.error;
      default:
        return styles.default;
    }
  };

  const getPaddingStyle = (): ViewStyle => {
    switch (padding) {
      case 'none':
        return { padding: 0 };
      case 'sm':
        return { padding: spacing.sm };
      case 'lg':
        return { padding: spacing.xl };
      default:
        return { padding: spacing.lg };
    }
  };

  return (
    <View style={[styles.base, getVariantStyle(), getPaddingStyle(), style]}>
      {children}
    </View>
  );
};

const styles = StyleSheet.create({
  base: {
    borderRadius: borderRadius.xl,
    borderWidth: 1,
  },
  default: {
    backgroundColor: colors.backgroundCard,
    borderColor: colors.borderLight,
  },
  primary: {
    backgroundColor: colors.primaryLight,
    borderColor: colors.borderPrimary,
  },
  success: {
    backgroundColor: colors.successLight,
    borderColor: 'rgba(16, 185, 129, 0.3)',
  },
  warning: {
    backgroundColor: colors.warningLight,
    borderColor: 'rgba(245, 158, 11, 0.3)',
  },
  error: {
    backgroundColor: colors.errorLight,
    borderColor: 'rgba(239, 68, 68, 0.3)',
  },
});

export default Card;
