// FieldOperatorScreen — operator în teren (field_seller)
// Combinație de check-in (scanare verificare) + POS mobil cu pos_price.
// Fără chitanță fizică.
import React, { useState } from 'react';
import { View, Text, StyleSheet, ScrollView, TouchableOpacity, Alert } from 'react-native';
import { colors } from '../theme/colors';

export default function FieldOperatorScreen({ navigation }) {
  const [recentScans, setRecentScans] = useState([]);

  function openScanner() {
    navigation.navigate('Scanner', {
      title: 'Verificare în teren',
      subtitle: 'Scanează biletul pentru a verifica valabilitatea',
      onScan: code => {
        const entry = {
          code,
          ts: new Date().toLocaleTimeString('ro-RO', { hour: '2-digit', minute: '2-digit' }),
        };
        setRecentScans(prev => [entry, ...prev].slice(0, 20));
      },
    });
  }

  return (
    <ScrollView style={styles.container} contentContainerStyle={{ padding: 16, paddingTop: 60 }}>
      <Text style={styles.title}>📱 Operator teren</Text>
      <Text style={styles.subtitle}>Verificare + vânzare mobilă (fără chitanță fizică)</Text>

      <View style={styles.actionsGrid}>
        <TouchableOpacity style={styles.actionCard} onPress={openScanner}>
          <Text style={styles.actionEmoji}>📷</Text>
          <Text style={styles.actionTitle}>Verificare bilete</Text>
          <Text style={styles.actionSubtitle}>Scanează pentru a confirma valabilitatea</Text>
        </TouchableOpacity>

        <TouchableOpacity style={styles.actionCard} onPress={() => navigation.navigate('POS')}>
          <Text style={styles.actionEmoji}>💳</Text>
          <Text style={styles.actionTitle}>Vinde bilete (POS)</Text>
          <Text style={styles.actionSubtitle}>Vânzare directă cu prețuri POS</Text>
        </TouchableOpacity>
      </View>

      {recentScans.length > 0 && (
        <>
          <Text style={styles.section}>Verificări recente ({recentScans.length})</Text>
          {recentScans.map((s, i) => (
            <View key={i} style={styles.scanRow}>
              <Text style={styles.scanCode}>{s.code}</Text>
              <Text style={styles.scanTime}>{s.ts}</Text>
            </View>
          ))}
        </>
      )}

      <TouchableOpacity style={styles.backBtn} onPress={() => navigation.navigate('Hub')}>
        <Text style={styles.backBtnText}>← Înapoi la Hub</Text>
      </TouchableOpacity>
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: colors.background },
  title: { fontSize: 22, fontWeight: '800', color: colors.textPrimary },
  subtitle: { fontSize: 13, color: colors.textSecondary, marginBottom: 24 },
  actionsGrid: { gap: 12 },
  actionCard: { backgroundColor: colors.surface, padding: 20, borderRadius: 16, borderWidth: 1, borderColor: colors.border, marginBottom: 10 },
  actionEmoji: { fontSize: 36 },
  actionTitle: { fontSize: 17, fontWeight: '800', color: colors.textPrimary, marginTop: 8 },
  actionSubtitle: { fontSize: 12, color: colors.textSecondary, marginTop: 2 },
  section: { fontSize: 11, textTransform: 'uppercase', letterSpacing: 1.5, color: colors.textTertiary, marginTop: 20, marginBottom: 8 },
  scanRow: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', padding: 12, backgroundColor: colors.surface, borderRadius: 10, marginBottom: 6, borderWidth: 1, borderColor: colors.border },
  scanCode: { fontSize: 13, color: colors.textPrimary, fontFamily: 'monospace', fontWeight: '700' },
  scanTime: { fontSize: 11, color: colors.textSecondary },
  backBtn: { marginTop: 14, padding: 10, alignItems: 'center' },
  backBtnText: { color: colors.muted, fontSize: 13 },
});
