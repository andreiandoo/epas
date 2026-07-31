import React, { useMemo, useState } from 'react';
import { Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { useNavigation } from '@react-navigation/native';
import { useApp } from '@/store/AppContext';
import { Card, SectionTitle } from '@/components/ui';
import { palette, radius } from '@/theme/colors';

interface Line {
  id: number;
  name: string;
  price: number;
  color: string;
  seated?: boolean;
  qty: number;
}

// Placeholder catalog until wired to GET /events/{id} ticket_types.
const INITIAL: Line[] = [
  { id: 1, name: 'Fan Pit', price: 350, color: '#00c896', qty: 0 },
  { id: 2, name: 'Standard cu loc', price: 250, color: '#00e5ff', seated: true, qty: 0 },
  { id: 3, name: 'VIP Loge', price: 800, color: '#f5a623', seated: true, qty: 0 },
  { id: 4, name: 'Elev / Student', price: 120, color: '#9b7ff8', qty: 0 },
];

export default function SalesScreen() {
  const { accent } = useApp();
  const nav = useNavigation<any>();
  const [lines, setLines] = useState<Line[]>(INITIAL);

  const bump = (id: number, d: number) => {
    const line = lines.find((l) => l.id === id);
    if (line?.seated && d > 0) {
      nav.navigate('SeatMap', { eventId: 0, ticketTypeId: id, ticketName: line.name });
      return;
    }
    setLines((prev) =>
      prev.map((l) => (l.id === id ? { ...l, qty: Math.max(0, l.qty + d) } : l)),
    );
  };

  const { count, total } = useMemo(() => {
    const c = lines.reduce((s, l) => s + l.qty, 0);
    const t = lines.reduce((s, l) => s + l.qty * l.price, 0);
    return { count: c, total: t };
  }, [lines]);

  return (
    <SafeAreaView style={styles.safe} edges={['top']}>
      <View style={styles.topbar}>
        <Text style={styles.title}>Vânzare la ușă</Text>
        <Text style={styles.sub}>sursă pos_app</Text>
      </View>
      <ScrollView contentContainerStyle={styles.body}>
        <SectionTitle>Categorie bilete</SectionTitle>
        {lines.map((l) => (
          <Card key={l.id} style={styles.tt}>
            <View style={[styles.sq, { backgroundColor: l.color }]} />
            <View style={{ flex: 1 }}>
              <Text style={styles.ttName}>{l.name}</Text>
              <Text style={styles.ttPrice}>
                {l.price} lei{l.seated ? ' · alege pe hartă' : ''}
              </Text>
            </View>
            <View style={styles.stepper}>
              <Pressable style={styles.stepBtn} onPress={() => bump(l.id, -1)}>
                <Text style={styles.stepGlyph}>–</Text>
              </Pressable>
              <Text style={styles.qty}>{l.qty}</Text>
              <Pressable style={styles.stepBtn} onPress={() => bump(l.id, 1)}>
                <Text style={styles.stepGlyph}>+</Text>
              </Pressable>
            </View>
          </Card>
        ))}
      </ScrollView>

      {count > 0 && (
        <View style={styles.payBar}>
          <Pressable style={[styles.payBtn, { backgroundColor: accent.base }]}>
            <View>
              <Text style={styles.payC}>{count} bilete · Continuă</Text>
              <Text style={styles.payAmt}>{total} lei</Text>
            </View>
            <Text style={styles.payArrow}>→</Text>
          </Pressable>
        </View>
      )}
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  safe: { flex: 1, backgroundColor: palette.bg },
  topbar: { paddingHorizontal: 16, paddingTop: 10, paddingBottom: 8 },
  title: { color: palette.text, fontSize: 20, fontWeight: '900' },
  sub: { color: palette.muted, fontSize: 11, marginTop: 1 },
  body: { paddingHorizontal: 15, paddingBottom: 20, gap: 10 },
  tt: { flexDirection: 'row', alignItems: 'center', gap: 11, padding: 11 },
  sq: { width: 5, alignSelf: 'stretch', borderRadius: 3 },
  ttName: { color: palette.text, fontSize: 13, fontWeight: '800' },
  ttPrice: { color: palette.hint, fontSize: 11, marginTop: 1 },
  stepper: { flexDirection: 'row', alignItems: 'center', gap: 9 },
  stepBtn: {
    width: 28,
    height: 28,
    borderRadius: 8,
    backgroundColor: palette.surface2,
    borderWidth: 1,
    borderColor: palette.border2,
    alignItems: 'center',
    justifyContent: 'center',
  },
  stepGlyph: { color: palette.muted, fontSize: 16, fontWeight: '800' },
  qty: { color: palette.text, fontSize: 14, fontWeight: '900', minWidth: 16, textAlign: 'center' },
  payBar: { paddingHorizontal: 15, paddingTop: 10, paddingBottom: 16 },
  payBtn: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    borderRadius: radius.lg,
    paddingVertical: 14,
    paddingHorizontal: 16,
  },
  payC: { color: '#04211a', fontSize: 11.5, fontWeight: '800', opacity: 0.85 },
  payAmt: { color: '#04211a', fontSize: 16, fontWeight: '900' },
  payArrow: { color: '#04211a', fontSize: 18, fontWeight: '900' },
});
