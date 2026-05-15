// BoatsOperatorScreen — workflow operator bărci (F7)
// - Lista bărci disponibile + cu rental activ
// - Buton scanner pentru bilet acces sau bilet barcă
// - Start cursă pe barcă (cu variantă durată)
// - Lista curse active cu timer live
// - End + Finalize cu calcul calup extra
import React, { useEffect, useState, useCallback } from 'react';
import { View, Text, StyleSheet, ScrollView, TouchableOpacity, ActivityIndicator, Alert, RefreshControl } from 'react-native';
import { useShift } from '../context/ShiftContext';
import { colors } from '../theme/colors';
import {
  fetchLeisureConfig, fetchBoats, fetchActiveRentals,
  startRental, endRental, finalizeRental,
} from '../api/leisure';

export default function BoatsOperatorScreen({ navigation }) {
  const { activeEvent, activeShift } = useShift();
  const eventId = activeEvent?.id;
  const memberId = activeShift?.team_member_id || null;

  const [productTypes, setProductTypes] = useState([]); // rental products with physical_inventory
  const [selectedTtId, setSelectedTtId] = useState(null);
  const [boats, setBoats] = useState([]);
  const [rentals, setRentals] = useState([]);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [nowTick, setNowTick] = useState(Date.now());

  const loadData = useCallback(async () => {
    if (!eventId) return;
    try {
      const cfg = await fetchLeisureConfig(eventId);
      const types = (cfg.data?.ticket_types || []).filter(
        t => t.physical_inventory && t.physical_inventory.enabled
      );
      setProductTypes(types);
      const ttId = selectedTtId || types[0]?.id || null;
      if (ttId && ttId !== selectedTtId) setSelectedTtId(ttId);
      if (ttId) {
        const [bRes, rRes] = await Promise.all([
          fetchBoats(eventId, ttId),
          fetchActiveRentals(eventId, ttId),
        ]);
        setBoats(bRes.data?.boats || []);
        setRentals(rRes.data?.rentals || []);
      }
    } catch (e) {
      console.warn('[Boats] loadData', e);
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  }, [eventId, selectedTtId]);

  useEffect(() => { loadData(); }, [loadData]);
  // Tick pentru cronometre live (1s)
  useEffect(() => {
    const t = setInterval(() => setNowTick(Date.now()), 1000);
    return () => clearInterval(t);
  }, []);
  // Polling 15s
  useEffect(() => {
    const p = setInterval(loadData, 15000);
    return () => clearInterval(p);
  }, [loadData]);

  function openStart(boat) {
    const tt = productTypes.find(t => t.id === selectedTtId);
    const variants = tt?.variants || [];
    if (variants.length === 0) {
      // Direct start fără variant
      confirmStart(boat, null);
      return;
    }
    Alert.alert(
      `Pornește cursă · Barca #${boat.number}`,
      'Alege durata:',
      variants.map(v => ({
        text: `${v.label} (${parseFloat(v.price).toFixed(2)} RON)`,
        onPress: () => confirmStart(boat, v.id),
      })).concat([{ text: 'Anulează', style: 'cancel' }])
    );
  }

  async function confirmStart(boat, variantId) {
    try {
      const payload = {
        ticket_type_id: selectedTtId,
        boat_id: boat.id,
        variant_id: variantId,
        started_by_member_id: memberId,
      };
      const res = await startRental(eventId, payload);
      Alert.alert('Cursă pornită', `Barca #${boat.number}.`);
      loadData();
    } catch (e) {
      Alert.alert('Eroare', e.message || 'Pornire eșuată');
    }
  }

  async function handleEnd(rental) {
    Alert.alert(
      'Închizi cronometrul?',
      `Barca #${rental.boat_number}. Sistemul calculează diferența de calupuri.`,
      [
        { text: 'Anulează', style: 'cancel' },
        {
          text: 'Da, închide',
          onPress: async () => {
            try {
              const res = await endRental(eventId, rental.id);
              const d = res.data || {};
              const extra = parseInt(d.extra_calupuri || 0, 10);
              const total = parseFloat(d.extra_charge_total || 0);
              if (extra > 0) {
                Alert.alert(
                  '⚠️ Depășire',
                  `${extra} calup(uri) extra · ${total.toFixed(2)} RON\n\nÎncasează diferența și finalizează?`,
                  [
                    { text: 'Mai târziu', style: 'cancel' },
                    { text: `Încasează ${total.toFixed(2)} RON + Finalizează`, onPress: () => handleFinalize(rental.id) },
                  ]
                );
              } else {
                Alert.alert('✓ În limita planificată', 'Apasă Finalizează ca să eliberezi barca.', [
                  { text: 'Anulează', style: 'cancel' },
                  { text: 'Finalizează', onPress: () => handleFinalize(rental.id) },
                ]);
              }
              loadData();
            } catch (e) {
              Alert.alert('Eroare', e.message || 'Închidere eșuată');
            }
          },
        },
      ]
    );
  }

  async function handleFinalize(rentalId) {
    try {
      await finalizeRental(eventId, rentalId);
      loadData();
    } catch (e) {
      Alert.alert('Eroare', e.message || 'Finalizare eșuată');
    }
  }

  if (loading) {
    return <View style={styles.center}><ActivityIndicator color={colors.primary} /></View>;
  }
  if (!productTypes.length) {
    return <View style={styles.center}><Text style={styles.muted}>Nu există produse cu inventar fizic configurat.</Text></View>;
  }

  return (
    <ScrollView
      style={styles.container}
      contentContainerStyle={{ padding: 16, paddingBottom: 40 }}
      refreshControl={<RefreshControl refreshing={refreshing} onRefresh={() => { setRefreshing(true); loadData(); }} tintColor={colors.primary} />}
    >
      <Text style={styles.title}>🛶 Operator bărci</Text>

      {/* Product picker */}
      {productTypes.length > 1 && (
        <ScrollView horizontal style={styles.chips} showsHorizontalScrollIndicator={false}>
          {productTypes.map(t => (
            <TouchableOpacity
              key={t.id}
              onPress={() => setSelectedTtId(t.id)}
              style={[styles.chip, selectedTtId === t.id && styles.chipActive]}
            >
              <Text style={[styles.chipText, selectedTtId === t.id && styles.chipTextActive]}>{labelOf(t.name)}</Text>
            </TouchableOpacity>
          ))}
        </ScrollView>
      )}

      {/* Active rentals */}
      <Text style={styles.section}>Curse active ({rentals.length})</Text>
      {rentals.length === 0 ? (
        <Text style={styles.muted}>Nicio cursă pornită.</Text>
      ) : (
        rentals.map(r => {
          const elapsed = fmtElapsed(r.started_at, nowTick);
          const overdue = new Date(r.planned_end_at).getTime() < nowTick;
          return (
            <View key={r.id} style={[styles.card, overdue ? styles.cardOverdue : null]}>
              <View style={styles.row}>
                <Text style={styles.boatNumber}>#{r.boat_number}</Text>
                <Text style={[styles.timer, overdue && styles.timerOverdue]}>{elapsed}</Text>
              </View>
              <Text style={styles.muted}>Planificat: {fmtTime(r.planned_end_at)} · {overdue ? '⚠️ DEPĂȘIT' : '✓ ÎN PROGRES'}</Text>
              <TouchableOpacity style={styles.endBtn} onPress={() => handleEnd(r)}>
                <Text style={styles.endBtnText}>⏹ Închide timer</Text>
              </TouchableOpacity>
            </View>
          );
        })
      )}

      {/* Available boats */}
      <Text style={styles.section}>Bărci disponibile</Text>
      <View style={styles.grid}>
        {boats.filter(b => b.status === 'available').map(b => (
          <TouchableOpacity key={b.id} style={styles.boatCard} onPress={() => openStart(b)}>
            <Text style={styles.boatCardNum}>#{b.number}</Text>
            <Text style={styles.boatCardLabel}>Pornește cursă</Text>
          </TouchableOpacity>
        ))}
      </View>
      {boats.filter(b => b.status === 'available').length === 0 && (
        <Text style={styles.muted}>Toate bărcile sunt în uz.</Text>
      )}

      {/* Scanner quick action */}
      <TouchableOpacity
        style={styles.scanBtn}
        onPress={() => navigation.navigate('Scanner', {
          title: 'Scanează bilet acces',
          subtitle: 'Pentru a porni o cursă pentru un client existent',
          onScan: code => Alert.alert('Cod scanat', code + '\n\n(Lookup ticket în versiunea următoare)'),
        })}
      >
        <Text style={styles.scanBtnText}>📷 Scanează bilet</Text>
      </TouchableOpacity>

      <TouchableOpacity style={styles.backBtn} onPress={() => navigation.navigate('Hub')}>
        <Text style={styles.backBtnText}>← Înapoi la Hub</Text>
      </TouchableOpacity>
    </ScrollView>
  );
}

function labelOf(name) {
  if (typeof name === 'string') return name;
  if (name && typeof name === 'object') return name.ro || Object.values(name)[0] || '—';
  return '—';
}

function fmtElapsed(startIso, nowMs) {
  if (!startIso) return '—';
  const sec = Math.max(0, Math.floor((nowMs - new Date(startIso).getTime()) / 1000));
  const m = Math.floor(sec / 60), s = sec % 60;
  return `${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`;
}
function fmtTime(iso) {
  if (!iso) return '—';
  try {
    return new Date(iso).toLocaleTimeString('ro-RO', { hour: '2-digit', minute: '2-digit' });
  } catch { return iso; }
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: colors.background },
  center: { flex: 1, alignItems: 'center', justifyContent: 'center', padding: 24, backgroundColor: colors.background },
  title: { fontSize: 22, fontWeight: '800', color: colors.textPrimary, marginTop: 40, marginBottom: 16 },
  section: { fontSize: 11, textTransform: 'uppercase', letterSpacing: 1.5, color: colors.textTertiary, marginTop: 20, marginBottom: 10 },
  chips: { marginBottom: 4 },
  chip: { paddingHorizontal: 14, paddingVertical: 8, borderRadius: 20, backgroundColor: colors.surface, marginRight: 8, borderWidth: 1, borderColor: colors.border },
  chipActive: { backgroundColor: colors.primary, borderColor: colors.primary },
  chipText: { color: colors.textSecondary, fontSize: 12, fontWeight: '600' },
  chipTextActive: { color: '#0F2C20' },
  card: { backgroundColor: colors.surface, padding: 16, borderRadius: 14, marginBottom: 10, borderWidth: 2, borderColor: colors.border },
  cardOverdue: { borderColor: colors.danger },
  row: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between' },
  boatNumber: { fontSize: 28, fontWeight: '900', color: colors.textPrimary },
  timer: { fontSize: 22, fontWeight: '800', color: colors.success, fontVariant: ['tabular-nums'] },
  timerOverdue: { color: colors.danger },
  muted: { fontSize: 12, color: colors.textSecondary, marginTop: 4 },
  endBtn: { marginTop: 12, padding: 12, backgroundColor: colors.warning, borderRadius: 10, alignItems: 'center' },
  endBtnText: { color: '#0F2C20', fontWeight: '800', fontSize: 14 },
  grid: { flexDirection: 'row', flexWrap: 'wrap', gap: 8, marginTop: 4 },
  boatCard: { width: '23%', aspectRatio: 1, backgroundColor: colors.surface, borderRadius: 12, alignItems: 'center', justifyContent: 'center', borderWidth: 1, borderColor: colors.border, marginRight: '2%', marginBottom: 8 },
  boatCardNum: { fontSize: 22, fontWeight: '900', color: colors.textPrimary },
  boatCardLabel: { fontSize: 9, color: colors.muted, marginTop: 2 },
  scanBtn: { marginTop: 20, padding: 14, backgroundColor: colors.primary, borderRadius: 12, alignItems: 'center' },
  scanBtnText: { color: '#0F2C20', fontWeight: '800', fontSize: 15 },
  backBtn: { marginTop: 14, padding: 10, alignItems: 'center' },
  backBtnText: { color: colors.muted, fontSize: 13 },
});
