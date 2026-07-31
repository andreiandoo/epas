import React, { useState } from 'react';
import { Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import type { NativeStackScreenProps } from '@react-navigation/native-stack';
import { useApp, kindFromType } from '@/store/AppContext';
import { Card } from '@/components/ui';
import { accentFor, palette, radius } from '@/theme/colors';
import type { RootStackParams } from '@/navigation';

type Props = NativeStackScreenProps<RootStackParams, 'Switcher'>;

export default function SwitcherScreen({ navigation }: Props) {
  const { organizer, available, switchTo } = useApp();
  const [busy, setBusy] = useState<number | null>(null);

  const rows = available.length
    ? available
    : organizer
      ? [{ id: organizer.id, name: organizer.name, slug: organizer.slug, organizer_type: organizer.organizer_type }]
      : [];

  const pick = async (id: number) => {
    setBusy(id);
    try {
      await switchTo(id);
      navigation.goBack();
    } finally {
      setBusy(null);
    }
  };

  return (
    <SafeAreaView style={styles.safe}>
      <View style={styles.header}>
        <Text style={styles.title}>Alege contextul</Text>
        <Pressable onPress={() => navigation.goBack()} hitSlop={8}>
          <Text style={styles.close}>✕</Text>
        </Pressable>
      </View>
      <ScrollView contentContainerStyle={styles.list}>
        {rows.map((o) => {
          const accent = accentFor(kindFromType(o.organizer_type));
          const active = organizer?.id === o.id;
          return (
            <Pressable key={o.id} onPress={() => pick(o.id)} disabled={busy != null}>
              <Card
                style={{
                  ...styles.row,
                  borderColor: active ? accent.border : palette.border2,
                }}
              >
                <View style={[styles.av, { backgroundColor: accent.soft, borderColor: accent.border }]}>
                  <Text style={[styles.avText, { color: accent.base }]}>
                    {o.name.slice(0, 2).toUpperCase()}
                  </Text>
                </View>
                <View style={{ flex: 1 }}>
                  <Text style={styles.name}>{o.name}</Text>
                  <Text style={styles.type}>{o.organizer_type ?? 'Organizator'}</Text>
                </View>
                <Text style={[styles.chev, { color: accent.border }]}>
                  {busy === o.id ? '…' : active ? '●' : '›'}
                </Text>
              </Card>
            </Pressable>
          );
        })}
      </ScrollView>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  safe: { flex: 1, backgroundColor: palette.bg },
  header: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingHorizontal: 20,
    paddingVertical: 16,
  },
  title: { color: palette.text, fontSize: 20, fontWeight: '900' },
  close: { color: palette.muted, fontSize: 20 },
  list: { paddingHorizontal: 16, gap: 10, paddingBottom: 24 },
  row: { flexDirection: 'row', alignItems: 'center', gap: 13, padding: 14 },
  av: {
    width: 44,
    height: 44,
    borderRadius: radius.md,
    borderWidth: 1,
    alignItems: 'center',
    justifyContent: 'center',
  },
  avText: { fontSize: 14, fontWeight: '900' },
  name: { color: palette.text, fontSize: 14, fontWeight: '900' },
  type: { color: palette.muted, fontSize: 11.5, marginTop: 2 },
  chev: { fontSize: 18 },
});
