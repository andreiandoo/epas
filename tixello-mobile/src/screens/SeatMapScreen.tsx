import React, { useState } from 'react';
import { Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import type { NativeStackScreenProps } from '@react-navigation/native-stack';
import { useApp } from '@/store/AppContext';
import { palette, radius, withAlpha } from '@/theme/colors';
import type { RootStackParams } from '@/navigation';

type Props = NativeStackScreenProps<RootStackParams, 'SeatMap'>;

type SeatState = 'free' | 'sold' | 'sel';
interface Seat {
  uid: string;
  state: SeatState;
}

// Placeholder hall until wired to GET /events/{id}/seating-map (WebView in prod).
function buildRow(row: string, sold: number[]): Seat[] {
  return Array.from({ length: 8 }, (_, i) => ({
    uid: `${row}${i + 1}`,
    state: sold.includes(i) ? 'sold' : 'free',
  }));
}
const HALL: { label: string; price: number; rows: { row: string; seats: Seat[] }[] }[] = [
  {
    label: 'Parter · rândurile A–E',
    price: 250,
    rows: [
      { row: 'A', seats: buildRow('A', [0, 1, 6, 7]) },
      { row: 'B', seats: buildRow('B', []) },
      { row: 'C', seats: buildRow('C', [3]) },
      { row: 'D', seats: buildRow('D', [2, 3]) },
      { row: 'E', seats: buildRow('E', []) },
    ],
  },
];

export default function SeatMapScreen({ navigation, route }: Props) {
  const { accent } = useApp();
  const { ticketName } = route.params;
  const [selected, setSelected] = useState<string[]>([]);
  const price = HALL[0].price;

  const toggle = (uid: string, state: SeatState) => {
    if (state === 'sold') return;
    setSelected((prev) =>
      prev.includes(uid) ? prev.filter((s) => s !== uid) : [...prev, uid],
    );
  };

  const color = (uid: string, state: SeatState): string => {
    if (selected.includes(uid)) return accent.base;
    if (state === 'sold') return 'rgba(255,255,255,0.05)';
    return palette.surface2;
  };

  return (
    <SafeAreaView style={styles.safe}>
      <View style={styles.header}>
        <Pressable onPress={() => navigation.goBack()} hitSlop={8}>
          <Text style={styles.back}>‹</Text>
        </Pressable>
        <View>
          <Text style={styles.title}>Alege locurile</Text>
          <Text style={styles.sub}>{ticketName} · Sala Mare</Text>
        </View>
      </View>

      <ScrollView contentContainerStyle={styles.body}>
        <View style={[styles.stage, { backgroundColor: accent.soft }]}>
          <Text style={styles.stageText}>S C E N Ă</Text>
        </View>
        {HALL.map((zone) => (
          <View key={zone.label} style={styles.zone}>
            <View style={styles.zoneHead}>
              <Text style={styles.zoneLabel}>{zone.label}</Text>
              <Text style={[styles.zonePrice, { color: accent.base }]}>{zone.price} lei</Text>
            </View>
            {zone.rows.map((r) => (
              <View key={r.row} style={styles.seatRow}>
                <Text style={styles.rowLabel}>{r.row}</Text>
                {r.seats.map((s) => (
                  <Pressable
                    key={s.uid}
                    onPress={() => toggle(s.uid, s.state)}
                    style={[
                      styles.seat,
                      {
                        backgroundColor: color(s.uid, s.state),
                        borderColor: s.state === 'free' ? palette.border2 : 'transparent',
                      },
                    ]}
                  />
                ))}
              </View>
            ))}
          </View>
        ))}

        <View style={styles.legend}>
          <Legend c={palette.surface2} l="Liber" bd={palette.border2} />
          <Legend c={accent.base} l="Selectat" />
          <Legend c="rgba(255,255,255,0.05)" l="Vândut" bd={palette.border} />
        </View>
      </ScrollView>

      <View style={styles.footer}>
        <View style={[styles.selSum, { borderColor: accent.border }]}>
          <Text style={[styles.selChip, { color: accent.base, backgroundColor: accent.soft, borderColor: accent.border }]}>
            {selected.length ? selected.join(' · ') : 'niciun loc'}
          </Text>
          <Text style={styles.selTotal}>{selected.length * price} lei</Text>
        </View>
        <Pressable
          disabled={!selected.length}
          onPress={() => navigation.goBack()}
          style={[styles.confirm, { backgroundColor: accent.base, opacity: selected.length ? 1 : 0.5 }]}
        >
          <Text style={styles.confirmText}>Confirmă {selected.length} locuri</Text>
        </Pressable>
      </View>
    </SafeAreaView>
  );
}

function Legend({ c, l, bd }: { c: string; l: string; bd?: string }) {
  return (
    <View style={styles.legItem}>
      <View style={[styles.legSw, { backgroundColor: c, borderColor: bd ?? 'transparent', borderWidth: bd ? 1 : 0 }]} />
      <Text style={styles.legText}>{l}</Text>
    </View>
  );
}

const styles = StyleSheet.create({
  safe: { flex: 1, backgroundColor: palette.bg },
  header: { flexDirection: 'row', alignItems: 'center', gap: 12, paddingHorizontal: 16, paddingVertical: 14 },
  back: { color: palette.muted, fontSize: 30, marginTop: -4 },
  title: { color: palette.text, fontSize: 18, fontWeight: '900' },
  sub: { color: palette.muted, fontSize: 11 },
  body: { paddingHorizontal: 16, paddingBottom: 20 },
  stage: {
    borderRadius: 12,
    borderBottomLeftRadius: 40,
    borderBottomRightRadius: 40,
    paddingVertical: 8,
    alignItems: 'center',
    marginHorizontal: 4,
  },
  stageText: { color: palette.muted, fontSize: 10, fontWeight: '800', letterSpacing: 6 },
  zone: { marginTop: 14 },
  zoneHead: { flexDirection: 'row', justifyContent: 'space-between', marginBottom: 8 },
  zoneLabel: { color: palette.hint, fontSize: 10, fontWeight: '800', textTransform: 'uppercase', letterSpacing: 0.4 },
  zonePrice: { fontSize: 11, fontWeight: '800' },
  seatRow: { flexDirection: 'row', gap: 5, alignItems: 'center', justifyContent: 'center', marginBottom: 5 },
  rowLabel: { color: palette.faint, fontSize: 9, width: 14, textAlign: 'center' },
  seat: { width: 20, height: 20, borderRadius: 5 },
  legend: { flexDirection: 'row', gap: 16, justifyContent: 'center', marginTop: 16 },
  legItem: { flexDirection: 'row', alignItems: 'center', gap: 6 },
  legSw: { width: 12, height: 12, borderRadius: 4 },
  legText: { color: palette.muted, fontSize: 10 },
  footer: { paddingHorizontal: 16, paddingBottom: 18, paddingTop: 8, gap: 10 },
  selSum: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    backgroundColor: palette.surface,
    borderWidth: 1,
    borderRadius: radius.md,
    paddingHorizontal: 12,
    paddingVertical: 10,
  },
  selChip: { fontSize: 10, fontWeight: '800', borderWidth: 1, borderRadius: 7, paddingHorizontal: 7, paddingVertical: 3, overflow: 'hidden' },
  selTotal: { color: palette.text, fontSize: 15, fontWeight: '900' },
  confirm: { borderRadius: radius.lg, paddingVertical: 15, alignItems: 'center' },
  confirmText: { color: '#04211a', fontSize: 15, fontWeight: '900' },
});
