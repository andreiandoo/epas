// CheckinOperatorScreen — operator check-in (gate_scanner)
// Doar scanare bilete pentru validare la intrare. Read-only.
import React, { useState } from 'react';
import { View, Text, StyleSheet, TouchableOpacity, ScrollView, Alert } from 'react-native';
import { colors } from '../theme/colors';

export default function CheckinOperatorScreen({ navigation }) {
  const [history, setHistory] = useState([]); // ultimele scanări

  function openScanner() {
    navigation.navigate('Scanner', {
      title: 'Scanează bilet acces',
      subtitle: 'Validare check-in',
      onScan: code => {
        // În versiunea finală: apel /tickets/lookup + decizie valid/invalid
        const entry = {
          code,
          ts: new Date().toLocaleTimeString('ro-RO', { hour: '2-digit', minute: '2-digit', second: '2-digit' }),
          status: 'valid', // placeholder
        };
        setHistory(prev => [entry, ...prev].slice(0, 30));
      },
    });
  }

  return (
    <ScrollView style={styles.container} contentContainerStyle={{ padding: 16, paddingTop: 60 }}>
      <Text style={styles.title}>✅ Operator check-in</Text>
      <Text style={styles.subtitle}>Scanare bilete la intrare</Text>

      <TouchableOpacity style={styles.bigScan} onPress={openScanner}>
        <Text style={styles.bigScanEmoji}>📷</Text>
        <Text style={styles.bigScanText}>SCANEAZĂ BILET</Text>
      </TouchableOpacity>

      <Text style={styles.section}>Ultimele scanări ({history.length})</Text>
      {history.length === 0 ? (
        <Text style={styles.muted}>Nicio scanare încă.</Text>
      ) : (
        history.map((h, i) => (
          <View key={i} style={[styles.row, h.status === 'valid' ? styles.rowValid : styles.rowInvalid]}>
            <View style={{ flex: 1 }}>
              <Text style={styles.rowCode}>{h.code}</Text>
              <Text style={styles.rowTime}>{h.ts}</Text>
            </View>
            <Text style={styles.rowStatus}>{h.status === 'valid' ? '✓' : '✕'}</Text>
          </View>
        ))
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
  bigScan: { backgroundColor: colors.primary, padding: 40, borderRadius: 20, alignItems: 'center', marginBottom: 24 },
  bigScanEmoji: { fontSize: 56 },
  bigScanText: { fontSize: 18, fontWeight: '900', color: '#0F2C20', letterSpacing: 1, marginTop: 8 },
  section: { fontSize: 11, textTransform: 'uppercase', letterSpacing: 1.5, color: colors.textTertiary, marginTop: 10, marginBottom: 8 },
  row: { flexDirection: 'row', alignItems: 'center', padding: 12, borderRadius: 10, marginBottom: 6, borderWidth: 1 },
  rowValid: { backgroundColor: 'rgba(16,185,129,0.1)', borderColor: colors.success },
  rowInvalid: { backgroundColor: 'rgba(248,113,113,0.1)', borderColor: colors.danger },
  rowCode: { fontSize: 13, color: colors.textPrimary, fontFamily: 'monospace', fontWeight: '700' },
  rowTime: { fontSize: 10, color: colors.textSecondary, marginTop: 2 },
  rowStatus: { fontSize: 22, fontWeight: '900' },
  muted: { fontSize: 12, color: colors.textSecondary, padding: 12 },
  backBtn: { marginTop: 14, padding: 10, alignItems: 'center' },
  backBtnText: { color: colors.muted, fontSize: 13 },
});
