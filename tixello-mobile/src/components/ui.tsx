/** Small themed UI primitives shared across screens. */
import React from 'react';
import {
  ActivityIndicator,
  Pressable,
  StyleSheet,
  Text,
  View,
  type ViewStyle,
} from 'react-native';
import { palette, radius, spacing, type Accent } from '@/theme/colors';

export function Card({
  children,
  style,
}: {
  children: React.ReactNode;
  style?: ViewStyle;
}) {
  return <View style={[styles.card, style]}>{children}</View>;
}

export function SectionTitle({ children }: { children: React.ReactNode }) {
  return <Text style={styles.sectionTitle}>{children}</Text>;
}

export function Badge({
  label,
  color = palette.muted,
  bg = palette.surface2,
  border = palette.border2,
}: {
  label: string;
  color?: string;
  bg?: string;
  border?: string;
}) {
  return (
    <View style={[styles.badge, { backgroundColor: bg, borderColor: border }]}>
      <Text style={[styles.badgeText, { color }]}>{label}</Text>
    </View>
  );
}

export function PrimaryButton({
  label,
  onPress,
  accent,
  loading,
  disabled,
}: {
  label: string;
  onPress: () => void;
  accent: Accent;
  loading?: boolean;
  disabled?: boolean;
}) {
  return (
    <Pressable
      onPress={onPress}
      disabled={disabled || loading}
      style={({ pressed }) => [
        styles.primaryBtn,
        { backgroundColor: accent.base, opacity: disabled ? 0.5 : pressed ? 0.85 : 1 },
      ]}
    >
      {loading ? (
        <ActivityIndicator color="#04211a" />
      ) : (
        <Text style={styles.primaryBtnText}>{label}</Text>
      )}
    </Pressable>
  );
}

export function KpiCard({
  value,
  label,
  color = palette.text,
}: {
  value: string;
  label: string;
  color?: string;
}) {
  return (
    <View style={styles.kpi}>
      <Text style={[styles.kpiValue, { color }]}>{value}</Text>
      <Text style={styles.kpiLabel}>{label}</Text>
    </View>
  );
}

export function ProgressBar({ pct, color }: { pct: number; color: string }) {
  return (
    <View style={styles.track}>
      <View
        style={{
          height: '100%',
          width: `${Math.max(0, Math.min(100, pct))}%`,
          backgroundColor: color,
          borderRadius: 3,
        }}
      />
    </View>
  );
}

const styles = StyleSheet.create({
  card: {
    backgroundColor: palette.surface,
    borderWidth: 1,
    borderColor: palette.border2,
    borderRadius: radius.lg,
    overflow: 'hidden',
  },
  sectionTitle: {
    color: palette.faint,
    fontSize: 11,
    fontWeight: '800',
    letterSpacing: 0.5,
    textTransform: 'uppercase',
  },
  badge: {
    borderWidth: 1,
    borderRadius: 7,
    paddingHorizontal: 8,
    paddingVertical: 2,
    alignSelf: 'flex-start',
  },
  badgeText: { fontSize: 10, fontWeight: '800' },
  primaryBtn: {
    borderRadius: radius.md,
    paddingVertical: 15,
    alignItems: 'center',
    justifyContent: 'center',
  },
  primaryBtnText: { color: '#04211a', fontSize: 15, fontWeight: '900' },
  kpi: {
    flex: 1,
    backgroundColor: palette.surface,
    borderWidth: 1,
    borderColor: palette.border2,
    borderRadius: radius.md,
    paddingVertical: 11,
    paddingHorizontal: 10,
    alignItems: 'center',
  },
  kpiValue: { fontSize: 17, fontWeight: '900' },
  kpiLabel: {
    fontSize: 8.5,
    fontWeight: '700',
    color: palette.faint,
    textTransform: 'uppercase',
    marginTop: 2,
    letterSpacing: 0.3,
  },
  track: {
    height: 5,
    backgroundColor: palette.surface2,
    borderRadius: 3,
    overflow: 'hidden',
  },
});

export { spacing, radius, palette };
