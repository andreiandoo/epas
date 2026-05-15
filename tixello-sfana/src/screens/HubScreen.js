// HubScreen — afișează rolul activ al operatorului și redirectează la
// ecranul corespunzător. Dacă nu există shift activ, afișează mesaj.
import React, { useEffect } from 'react';
import { View, Text, StyleSheet, ScrollView, TouchableOpacity, ActivityIndicator } from 'react-native';
import { useAuth } from '../context/AuthContext';
import { useShift } from '../context/ShiftContext';
import { colors, getRoleConfig } from '../theme/colors';

export default function HubScreen({ navigation }) {
  const { user, logout } = useAuth();
  const { activeShift, activeEvent, loading, error, refresh } = useShift();

  useEffect(() => {
    refresh();
    const interval = setInterval(refresh, 5 * 60 * 1000); // 5 min
    return () => clearInterval(interval);
  }, [refresh]);

  const role = activeShift?.role || null;
  const cfg = role ? getRoleConfig(role) : null;

  const screenForRole = {
    operator_boats: 'BoatsOperator',
    operator_pontoon: 'PontoonOperator',
    sales_operator: 'POS',
    gate_scanner: 'Checkin',
    field_seller: 'FieldOperator',
    shift_manager: 'POS', // managerul poate face POS oricum
  };

  function goToActiveScreen() {
    if (!role) return;
    const dest = screenForRole[role];
    if (dest) navigation.navigate(dest);
  }

  return (
    <ScrollView style={styles.container} contentContainerStyle={styles.content}>
      <View style={styles.header}>
        <Text style={styles.appTitle}>Tixello · Sf. Ana</Text>
        <TouchableOpacity onPress={logout}>
          <Text style={styles.logout}>Ieșire</Text>
        </TouchableOpacity>
      </View>

      <View style={styles.userCard}>
        <Text style={styles.userLabel}>Conectat ca</Text>
        <Text style={styles.userName}>{user?.name || user?.email || 'Operator'}</Text>
        {activeEvent && (
          <Text style={styles.eventName}>{activeEvent.name || activeEvent.title}</Text>
        )}
      </View>

      {loading && (
        <View style={styles.center}>
          <ActivityIndicator color={colors.primary} />
          <Text style={styles.muted}>Se verifică turneta...</Text>
        </View>
      )}

      {!loading && !activeShift && (
        <View style={styles.warningCard}>
          <Text style={styles.warningTitle}>⚠️ Nu există turnetă activă</Text>
          <Text style={styles.warningText}>
            Managerul nu a setat un rol pentru contul tău în acest interval.
            Contactează-l ca să-ți aloce o turnetă (operator bărci, vaporașe, POS, check-in sau teren).
          </Text>
          <TouchableOpacity style={styles.button} onPress={refresh}>
            <Text style={styles.buttonText}>🔄 Verifică din nou</Text>
          </TouchableOpacity>
        </View>
      )}

      {!loading && activeShift && cfg && (
        <View style={[styles.roleCard, { borderColor: cfg.accent }]}>
          <Text style={styles.roleLabel}>Rol activ acum</Text>
          <Text style={styles.roleEmoji}>{cfg.emoji}</Text>
          <Text style={styles.roleTitle}>{cfg.label}</Text>
          {activeShift.gate && (
            <Text style={styles.muted}>Poartă: {activeShift.gate}</Text>
          )}
          <Text style={styles.shiftTime}>
            {fmtShift(activeShift.start_at)} → {fmtShift(activeShift.end_at)}
          </Text>
          <TouchableOpacity style={[styles.bigButton, { backgroundColor: cfg.accent }]} onPress={goToActiveScreen}>
            <Text style={styles.bigButtonText}>Deschide panou rol →</Text>
          </TouchableOpacity>
        </View>
      )}

      {error && <Text style={styles.error}>{error}</Text>}
    </ScrollView>
  );
}

function fmtShift(iso) {
  if (!iso) return '—';
  try {
    const d = new Date(iso);
    return d.toLocaleString('ro-RO', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' });
  } catch { return iso; }
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: colors.background },
  content: { padding: 16, paddingTop: 60 },
  header: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginBottom: 24 },
  appTitle: { fontSize: 20, fontWeight: '800', color: colors.textPrimary },
  logout: { fontSize: 14, color: colors.muted ?? colors.textSecondary },
  userCard: { backgroundColor: colors.surface, padding: 16, borderRadius: 16, marginBottom: 20 },
  userLabel: { fontSize: 11, textTransform: 'uppercase', color: colors.textTertiary, letterSpacing: 1.5 },
  userName: { fontSize: 18, fontWeight: '700', color: colors.textPrimary, marginTop: 4 },
  eventName: { fontSize: 13, color: colors.accent, marginTop: 2 },
  roleCard: { borderWidth: 2, padding: 20, borderRadius: 20, alignItems: 'center', backgroundColor: colors.surface },
  roleLabel: { fontSize: 11, textTransform: 'uppercase', letterSpacing: 1.5, color: colors.textTertiary },
  roleEmoji: { fontSize: 48, marginVertical: 8 },
  roleTitle: { fontSize: 22, fontWeight: '800', color: colors.textPrimary },
  shiftTime: { fontSize: 12, color: colors.textSecondary, marginTop: 6 },
  bigButton: { marginTop: 16, paddingHorizontal: 24, paddingVertical: 14, borderRadius: 12, width: '100%', alignItems: 'center' },
  bigButtonText: { fontSize: 15, fontWeight: '700', color: '#0F2C20' },
  warningCard: { backgroundColor: '#3D2A14', padding: 20, borderRadius: 16, borderWidth: 1, borderColor: '#A78043' },
  warningTitle: { fontSize: 16, fontWeight: '700', color: '#FCD34D', marginBottom: 6 },
  warningText: { fontSize: 13, color: '#FDE68A', lineHeight: 20 },
  button: { marginTop: 12, padding: 12, backgroundColor: colors.primary, borderRadius: 10, alignItems: 'center' },
  buttonText: { color: '#0F2C20', fontWeight: '700' },
  center: { alignItems: 'center', paddingVertical: 40 },
  muted: { color: colors.textSecondary, fontSize: 12, marginTop: 8 },
  error: { color: colors.danger, fontSize: 12, marginTop: 12 },
});
