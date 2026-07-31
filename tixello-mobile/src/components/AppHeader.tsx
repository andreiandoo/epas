/**
 * Persistent app header: org avatar (tap = switch context), name + type,
 * switch button and a notifications bell with an unread badge.
 */
import React from 'react';
import { Pressable, StyleSheet, Text, View } from 'react-native';
import { palette, radius, type Accent } from '@/theme/colors';

function initials(name: string): string {
  return name
    .split(/\s+/)
    .slice(0, 2)
    .map((w) => w[0]?.toUpperCase() ?? '')
    .join('');
}

export function AppHeader({
  name,
  subtitle,
  accent,
  unread = 0,
  onAvatar,
  onSwitch,
  onBell,
}: {
  name: string;
  subtitle: string;
  accent: Accent;
  unread?: number;
  onAvatar?: () => void;
  onSwitch?: () => void;
  onBell?: () => void;
}) {
  return (
    <View style={styles.wrap}>
      <Pressable style={styles.id} onPress={onAvatar} hitSlop={6}>
        <View
          style={[
            styles.avatar,
            { backgroundColor: accent.soft, borderColor: accent.border },
          ]}
        >
          <Text style={[styles.avatarText, { color: accent.base }]}>
            {initials(name)}
          </Text>
        </View>
        <View style={styles.idText}>
          <Text style={styles.name} numberOfLines={1}>
            {name}
          </Text>
          <Text style={[styles.sub, { color: accent.base }]} numberOfLines={1}>
            {subtitle}
          </Text>
        </View>
      </Pressable>

      <View style={styles.acts}>
        <Pressable style={styles.hbtn} onPress={onSwitch} hitSlop={6}>
          <Text style={styles.hicon}>⇄</Text>
        </Pressable>
        <Pressable style={styles.hbtn} onPress={onBell} hitSlop={6}>
          <Text style={styles.hicon}>🔔</Text>
          {unread > 0 && (
            <View style={styles.badge}>
              <Text style={styles.badgeText}>{unread > 9 ? '9+' : unread}</Text>
            </View>
          )}
        </Pressable>
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  wrap: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingHorizontal: 16,
    paddingTop: 10,
    paddingBottom: 12,
  },
  id: { flexDirection: 'row', alignItems: 'center', gap: 11, flexShrink: 1 },
  avatar: {
    width: 40,
    height: 40,
    borderRadius: 12,
    borderWidth: 1.5,
    alignItems: 'center',
    justifyContent: 'center',
  },
  avatarText: { fontSize: 12, fontWeight: '900' },
  idText: { flexShrink: 1 },
  name: { color: palette.text, fontSize: 15, fontWeight: '900' },
  sub: { fontSize: 11, fontWeight: '700', marginTop: 1 },
  acts: { flexDirection: 'row', gap: 8 },
  hbtn: {
    width: 38,
    height: 38,
    borderRadius: radius.md,
    backgroundColor: palette.surface2,
    borderWidth: 1,
    borderColor: palette.border2,
    alignItems: 'center',
    justifyContent: 'center',
  },
  hicon: { fontSize: 16, color: palette.muted },
  badge: {
    position: 'absolute',
    top: -5,
    right: -5,
    minWidth: 17,
    height: 17,
    paddingHorizontal: 4,
    borderRadius: 9,
    backgroundColor: palette.danger,
    alignItems: 'center',
    justifyContent: 'center',
    borderWidth: 2,
    borderColor: palette.bg,
  },
  badgeText: { color: '#fff', fontSize: 10, fontWeight: '800' },
});
