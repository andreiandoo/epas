// PontoonOperatorScreen — operator vaporașe
// Listă produse vaporașe + dashboard simple cu count vandut azi.
// Operatorul scanează biletele clienților la îmbarcare (acces + vaporaș).
import React, { useEffect, useState, useCallback } from 'react';
import { View, Text, StyleSheet, ScrollView, TouchableOpacity, ActivityIndicator, RefreshControl, Alert } from 'react-native';
import { useShift } from '../context/ShiftContext';
import { colors } from '../theme/colors';
import { fetchLeisureConfig, fetchDashboardLive } from '../api/leisure';

export default function PontoonOperatorScreen({ navigation }) {
  const { activeEvent } = useShift();
  const eventId = activeEvent?.id;

  const [pontoonProducts, setPontoonProducts] = useState([]);
  const [stats, setStats] = useState({});
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);

  const load = useCallback(async () => {
    if (!eventId) return;
    try {
      const [cfg, live] = await Promise.all([
        fetchLeisureConfig(eventId),
        fetchDashboardLive(eventId).catch(() => ({ data: { stats: {} } })),
      ]);
      const all = cfg.data?.ticket_types || [];
      // Vaporașe = produse activity SAU cu slots_config enabled
      const pontoon = all.filter(t =>
        t.service_category === 'activity'
        || (t.slots_config && t.slots_config.enabled)
      );
      setPontoonProducts(pontoon);
      setStats(live.data?.stats || {});
    } catch (e) {
      console.warn('[Pontoon] load', e);
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  }, [eventId]);

  useEffect(() => { load(); }, [load]);
  useEffect(() => { const t = setInterval(load, 30000); return () => clearInterval(t); }, [load]);

  function openCheckin() {
    navigation.navigate('Scanner', {
      title: 'Scanează bilet vaporaș',
      subtitle: 'Validează biletul la îmbarcare',
      onScan: code => Alert.alert('Bilet scanat', `Cod: ${code}\n(Validare automată în versiunea API finală)`),
    });
  }

  if (loading) {
    return <View style={styles.center}><ActivityIndicator color={colors.primary} /></View>;
  }

  return (
    <ScrollView
      style={styles.container}
      contentContainerStyle={{ padding: 16, paddingTop: 60 }}
      refreshControl={<RefreshControl refreshing={refreshing} onRefresh={() => { setRefreshing(true); load(); }} tintColor={colors.primary} />}
    >
      <Text style={styles.title}>🚤 Operator vaporașe</Text>

      <View style={styles.statsGrid}>
        <View style={styles.statCard}>
          <Text style={styles.statValue}>{stats.sold_today ?? 0}</Text>
          <Text style={styles.statLabel}>Bilete vândute azi</Text>
        </View>
        <View style={styles.statCard}>
          <Text style={styles.statValue}>{stats.scanned_today ?? 0}</Text>
          <Text style={styles.statLabel}>Check-in-uri</Text>
        </View>
      </View>

      <Text style={styles.section}>Produse vaporașe</Text>
      {pontoonProducts.length === 0 ? (
        <Text style={styles.muted}>Niciun produs vaporașe configurat.</Text>
      ) : (
        pontoonProducts.map(p => (
          <View key={p.id} style={styles.card}>
            <Text style={styles.cardTitle}>{labelOf(p.name)}</Text>
            <Text style={styles.cardMeta}>
              {p.daily_capacity ? `${p.daily_capacity}/zi` : 'Stoc nelimitat'}
              {p.slots_config?.enabled && ` · Slot ${p.slots_config.duration_minutes}min · ${p.slots_config.capacity_per_slot} pax`}
            </Text>
            <Text style={styles.cardPrice}>
              Online: {parseFloat(p.price || 0).toFixed(2)} RON
              {p.pos_price !== null && p.pos_price !== undefined && ` · POS: ${parseFloat(p.pos_price).toFixed(2)} RON`}
            </Text>
          </View>
        ))
      )}

      <TouchableOpacity style={styles.scanBtn} onPress={openCheckin}>
        <Text style={styles.scanBtnText}>📷 Scanează bilet pentru îmbarcare</Text>
      </TouchableOpacity>

      <TouchableOpacity style={styles.posBtn} onPress={() => navigation.navigate('POS')}>
        <Text style={styles.posBtnText}>💳 Vinde bilet (POS)</Text>
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

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: colors.background },
  center: { flex: 1, alignItems: 'center', justifyContent: 'center', padding: 24, backgroundColor: colors.background },
  title: { fontSize: 22, fontWeight: '800', color: colors.textPrimary, marginBottom: 16 },
  statsGrid: { flexDirection: 'row', gap: 10, marginBottom: 16 },
  statCard: { flex: 1, backgroundColor: colors.surface, padding: 14, borderRadius: 14, borderWidth: 1, borderColor: colors.border, marginRight: 8 },
  statValue: { fontSize: 28, fontWeight: '900', color: colors.accent },
  statLabel: { fontSize: 11, color: colors.textSecondary, marginTop: 2 },
  section: { fontSize: 11, textTransform: 'uppercase', letterSpacing: 1.5, color: colors.textTertiary, marginTop: 10, marginBottom: 10 },
  card: { backgroundColor: colors.surface, padding: 14, borderRadius: 12, marginBottom: 8, borderWidth: 1, borderColor: colors.border },
  cardTitle: { fontSize: 16, fontWeight: '700', color: colors.textPrimary },
  cardMeta: { fontSize: 11, color: colors.textSecondary, marginTop: 4 },
  cardPrice: { fontSize: 12, color: colors.accent, marginTop: 4, fontWeight: '600' },
  scanBtn: { marginTop: 20, padding: 14, backgroundColor: colors.primary, borderRadius: 12, alignItems: 'center' },
  scanBtnText: { color: '#0F2C20', fontWeight: '800', fontSize: 15 },
  posBtn: { marginTop: 10, padding: 14, backgroundColor: colors.surface, borderRadius: 12, alignItems: 'center', borderWidth: 1, borderColor: colors.border },
  posBtnText: { color: colors.textPrimary, fontWeight: '600', fontSize: 14 },
  muted: { fontSize: 12, color: colors.textSecondary },
  backBtn: { marginTop: 14, padding: 10, alignItems: 'center' },
  backBtnText: { color: colors.muted, fontSize: 13 },
});
