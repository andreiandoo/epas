// ScannerScreen — scanner QR shared între ecrane.
// Părintele apelează navigation.navigate('Scanner', { onScan: code => {...} })
// sau folosim un event/callback prin route.params.
import React, { useState, useRef } from 'react';
import { View, Text, StyleSheet, TouchableOpacity, ActivityIndicator } from 'react-native';
import { CameraView, useCameraPermissions } from 'expo-camera';
import { colors } from '../theme/colors';

export default function ScannerScreen({ navigation, route }) {
  const [permission, requestPermission] = useCameraPermissions();
  const [scanned, setScanned] = useState(false);
  const onScanRef = useRef(route?.params?.onScan);
  const title = route?.params?.title || 'Scanează cod QR';
  const subtitle = route?.params?.subtitle || 'Aliniază codul QR în chenarul de mai jos';

  function handleScan({ data }) {
    if (scanned) return;
    setScanned(true);
    const cb = onScanRef.current;
    if (typeof cb === 'function') {
      cb(data);
    }
    // Întoarce-te la ecranul precedent
    setTimeout(() => navigation.goBack(), 300);
  }

  if (!permission) {
    return (
      <View style={styles.center}><ActivityIndicator color={colors.primary} /></View>
    );
  }
  if (!permission.granted) {
    return (
      <View style={styles.center}>
        <Text style={styles.title}>Acces cameră necesar</Text>
        <Text style={styles.subtitle}>Trebuie permisiunea camerei pentru a scana coduri QR.</Text>
        <TouchableOpacity style={styles.button} onPress={requestPermission}>
          <Text style={styles.buttonText}>Acordă permisiunea</Text>
        </TouchableOpacity>
      </View>
    );
  }

  return (
    <View style={styles.container}>
      <CameraView
        style={StyleSheet.absoluteFillObject}
        facing="back"
        barcodeScannerSettings={{ barcodeTypes: ['qr'] }}
        onBarcodeScanned={scanned ? undefined : handleScan}
      />
      <View style={styles.overlay}>
        <View style={styles.topBar}>
          <Text style={styles.title}>{title}</Text>
          <Text style={styles.subtitle}>{subtitle}</Text>
        </View>
        <View style={styles.frame} />
        <TouchableOpacity style={styles.cancelBtn} onPress={() => navigation.goBack()}>
          <Text style={styles.cancelText}>Anulează</Text>
        </TouchableOpacity>
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: '#000' },
  center: { flex: 1, alignItems: 'center', justifyContent: 'center', padding: 24, backgroundColor: colors.background },
  overlay: { flex: 1, justifyContent: 'space-between', backgroundColor: 'rgba(0,0,0,0.35)' },
  topBar: { paddingTop: 60, paddingHorizontal: 24, paddingBottom: 16 },
  title: { fontSize: 22, fontWeight: '800', color: '#FFF', textAlign: 'center', marginBottom: 8 },
  subtitle: { fontSize: 13, color: 'rgba(255,255,255,0.8)', textAlign: 'center' },
  frame: { alignSelf: 'center', width: 260, height: 260, borderRadius: 24, borderWidth: 3, borderColor: colors.primary },
  cancelBtn: { marginBottom: 50, alignSelf: 'center', paddingHorizontal: 32, paddingVertical: 14, backgroundColor: 'rgba(0,0,0,0.55)', borderRadius: 30 },
  cancelText: { color: '#FFF', fontSize: 15, fontWeight: '600' },
  button: { marginTop: 20, paddingHorizontal: 28, paddingVertical: 14, backgroundColor: colors.primary, borderRadius: 12 },
  buttonText: { color: '#0F2C20', fontSize: 15, fontWeight: '700' },
});
