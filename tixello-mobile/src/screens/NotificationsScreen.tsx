import React, { useState } from 'react';
import { Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import type { NativeStackScreenProps } from '@react-navigation/native-stack';
import { useApp } from '@/store/AppContext';
import { palette, radius, withAlpha } from '@/theme/colors';
import type { RootStackParams } from '@/navigation';

type Props = NativeStackScreenProps<RootStackParams, 'Notifications'>;

type Cat = 'toate' | 'vanzari' | 'operational' | 'financiar' | 'sistem';
interface Notif {
  id: number;
  cat: Exclude<Cat, 'toate'>;
  title: string;
  sub: string;
  time: string;
  color: string;
  glyph: string;
  unread?: boolean;
}

// Placeholder feed until wired to recent-orders + Reverb events.
const FEED: Notif[] = [
  { id: 1, cat: 'vanzari', title: '3 bilete vândute — Coldplay', sub: 'Fan Pit ×2, Standard ×1 · 850 lei', time: 'acum 2 min', color: '#00c896', glyph: '🎟', unread: true },
  { id: 2, cat: 'operational', title: 'Scan invalid la Poarta B', sub: 'QR nerecunoscut · verifică manual', time: 'acum 6 min', color: '#f04f4f', glyph: '⚠', unread: true },
  { id: 3, cat: 'operational', title: 'Capacitate 92% — Coldplay', sub: 'Ultimele 300 locuri', time: 'acum 20 min', color: '#f5a623', glyph: '▲', unread: true },
  { id: 4, cat: 'financiar', title: 'Decont procesat · 18.400 lei', sub: 'Transfer către IBAN ****3921', time: 'azi 08:12', color: '#9b7ff8', glyph: '₊' },
  { id: 5, cat: 'operational', title: 'Invitație folosită · Dan Popescu', sub: 'Presă · check-in Poarta A', time: 'ieri 21:03', color: '#3ddb8a', glyph: '✓' },
  { id: 6, cat: 'sistem', title: 'Contract de semnat', sub: 'Necesar înainte de publicare', time: 'acum 2 zile', color: palette.muted, glyph: '📄' },
];

const CATS: { key: Cat; label: string }[] = [
  { key: 'toate', label: 'Toate' },
  { key: 'vanzari', label: 'Vânzări' },
  { key: 'operational', label: 'Operațional' },
  { key: 'financiar', label: 'Financiar' },
  { key: 'sistem', label: 'Sistem' },
];

export default function NotificationsScreen({ navigation }: Props) {
  const { accent } = useApp();
  const [cat, setCat] = useState<Cat>('toate');
  const list = cat === 'toate' ? FEED : FEED.filter((n) => n.cat === cat);
  const unread = FEED.filter((n) => n.unread).length;

  return (
    <SafeAreaView style={styles.safe}>
      <View style={styles.header}>
        <View style={styles.headLeft}>
          <Pressable onPress={() => navigation.goBack()} hitSlop={8}>
            <Text style={styles.back}>‹</Text>
          </Pressable>
          <View>
            <Text style={styles.title}>Notificări</Text>
            <Text style={styles.sub}>{unread} necitite</Text>
          </View>
        </View>
        <Text style={[styles.markAll, { color: accent.base }]}>Citește tot</Text>
      </View>

      <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={styles.filters}>
        {CATS.map((c) => {
          const on = cat === c.key;
          return (
            <Text
              key={c.key}
              onPress={() => setCat(c.key)}
              style={[
                styles.chip,
                on ? { color: accent.base, backgroundColor: accent.soft, borderColor: accent.border } : { color: palette.muted },
              ]}
            >
              {c.label}
            </Text>
          );
        })}
      </ScrollView>

      <ScrollView contentContainerStyle={styles.list}>
        {list.map((n) => (
          <View
            key={n.id}
            style={[
              styles.item,
              n.unread && { backgroundColor: withAlpha(accent.base, 0.06), borderColor: accent.border },
            ]}
          >
            <View style={[styles.icon, { backgroundColor: withAlpha(n.color, 0.14) }]}>
              <Text style={{ color: n.color, fontSize: 16 }}>{n.glyph}</Text>
            </View>
            <View style={{ flex: 1 }}>
              <Text style={styles.nTitle}>{n.title}</Text>
              <Text style={styles.nSub}>{n.sub}</Text>
              <Text style={styles.nTime}>{n.time}</Text>
            </View>
            {n.unread && <View style={[styles.dot, { backgroundColor: accent.base }]} />}
          </View>
        ))}
      </ScrollView>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  safe: { flex: 1, backgroundColor: palette.bg },
  header: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', paddingHorizontal: 16, paddingVertical: 14 },
  headLeft: { flexDirection: 'row', alignItems: 'center', gap: 12 },
  back: { color: palette.muted, fontSize: 30, marginTop: -4 },
  title: { color: palette.text, fontSize: 18, fontWeight: '900' },
  sub: { color: palette.muted, fontSize: 11 },
  markAll: { fontSize: 12.5, fontWeight: '800' },
  filters: { gap: 6, paddingHorizontal: 15, paddingBottom: 6 },
  chip: {
    fontSize: 11,
    fontWeight: '800',
    paddingHorizontal: 11,
    paddingVertical: 6,
    borderRadius: 999,
    borderWidth: 1,
    borderColor: palette.border2,
    backgroundColor: palette.surface2,
    overflow: 'hidden',
  },
  list: { paddingHorizontal: 15, paddingBottom: 24, gap: 9, paddingTop: 6 },
  item: {
    flexDirection: 'row',
    gap: 11,
    padding: 12,
    borderRadius: radius.md,
    backgroundColor: palette.surface,
    borderWidth: 1,
    borderColor: palette.border2,
  },
  icon: { width: 36, height: 36, borderRadius: 11, alignItems: 'center', justifyContent: 'center' },
  nTitle: { color: palette.text, fontSize: 12.5, fontWeight: '800' },
  nSub: { color: palette.hint, fontSize: 11, marginTop: 2 },
  nTime: { color: palette.faint, fontSize: 10, marginTop: 4 },
  dot: { width: 7, height: 7, borderRadius: 4, marginTop: 4 },
});
