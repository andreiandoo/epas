import React from 'react';
import { View, Text, StyleSheet } from 'react-native';
import { colors, borderRadius, spacing, typography } from '../../utils/theme';

interface StatusBadgeProps {
  status: 'live' | 'upcoming' | 'ended' | 'online' | 'offline' | 'valid' | 'invalid' | 'duplicate';
  label?: string;
  showDot?: boolean;
  size?: 'sm' | 'md';
}

export const StatusBadge: React.FC<StatusBadgeProps> = ({
  status,
  label,
  showDot = true,
  size = 'md',
}) => {
  const getStatusConfig = () => {
    switch (status) {
      case 'live':
      case 'online':
      case 'valid':
        return {
          backgroundColor: colors.successLight,
          textColor: colors.success,
          dotColor: colors.success,
          defaultLabel: status === 'live' ? 'LIVE' : status === 'valid' ? 'VALID' : 'Online',
        };
      case 'upcoming':
        return {
          backgroundColor: colors.infoLight,
          textColor: colors.info,
          dotColor: colors.info,
          defaultLabel: 'Upcoming',
        };
      case 'ended':
        return {
          backgroundColor: 'rgba(255, 255, 255, 0.1)',
          textColor: colors.textMuted,
          dotColor: colors.textMuted,
          defaultLabel: 'Ended',
        };
      case 'offline':
      case 'invalid':
        return {
          backgroundColor: colors.errorLight,
          textColor: colors.error,
          dotColor: colors.error,
          defaultLabel: status === 'offline' ? 'Offline' : 'INVALID',
        };
      case 'duplicate':
        return {
          backgroundColor: colors.warningLight,
          textColor: colors.warning,
          dotColor: colors.warning,
          defaultLabel: 'DUPLICATE',
        };
      default:
        return {
          backgroundColor: colors.backgroundCard,
          textColor: colors.textSecondary,
          dotColor: colors.textMuted,
          defaultLabel: status,
        };
    }
  };

  const config = getStatusConfig();
  const displayLabel = label || config.defaultLabel;

  return (
    <View
      style={[
        styles.container,
        { backgroundColor: config.backgroundColor },
        size === 'sm' && styles.containerSm,
      ]}
    >
      {showDot && (
        <View
          style={[
            styles.dot,
            { backgroundColor: config.dotColor },
            (status === 'live' || status === 'online') && styles.dotPulse,
          ]}
        />
      )}
      <Text
        style={[
          styles.text,
          { color: config.textColor },
          size === 'sm' && styles.textSm,
        ]}
      >
        {displayLabel}
      </Text>
    </View>
  );
};

const styles = StyleSheet.create({
  container: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingHorizontal: spacing.md,
    paddingVertical: spacing.xs,
    borderRadius: borderRadius.sm,
    gap: spacing.sm,
  },
  containerSm: {
    paddingHorizontal: spacing.sm,
    paddingVertical: 2,
  },
  dot: {
    width: 6,
    height: 6,
    borderRadius: 3,
  },
  dotPulse: {
    // Animation handled via Animated API in actual usage
  },
  text: {
    fontSize: typography.fontSize.xs,
    fontWeight: '700',
    letterSpacing: 0.5,
  },
  textSm: {
    fontSize: 10,
  },
});

export default StatusBadge;
