import React, { useCallback, useRef, useState } from 'react';
import { Pressable, StyleSheet, Text, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { CameraView, useCameraPermissions, type BarcodeScanningResult } from 'expo-camera';
import * as Haptics from 'expo-haptics';
import { useApp } from '@/store/AppContext';
import * as api from '@/api/endpoints';
import type { ScanResult } from '@/api/types';
import { palette, radius, withAlpha } from '@/theme/colors';

const DEDUP_MS = 60_000;

export default function ScanScreen() {
  const { accent, apiCtx } = useApp();
  const [permission, requestPermission] = useCameraPermissions();
  const [active, setActive] = useState(false);
  const [result, setResult] = useState<{ ok: boolean; res: ScanResult } | null>(null);
  const recent = useRef<Map<string, number>>(new Map());
  const busy = useRef(false);

  const onScanned = useCallback(
    async (r: BarcodeScanningResult) => {
      const code = r.data;
      const now = Date.now();
      const last = recent.current.get(code);
      if (busy.current || (last && now - last < DEDUP_MS)) return;
      recent.current.set(code, now);
      busy.current = true;
      try {
        const res = await api.checkInByCode(apiCtx, code);
        const ok = res.success !== false;
        setResult({ ok, res });
        await Haptics.notificationAsync(
          ok
            ? Haptics.NotificationFeedbackType.Success
            : Haptics.NotificationFeedbackType.Warning,
        );
      } catch (e) {
        const res = e as { payload?: ScanResult; message?: string };
        setResult({
          ok: false,
          res: (res.payload as ScanResult) ?? { success: false, message: res.message },
        });
        await Haptics.notificationAsync(Haptics.NotificationFeedbackType.Error);
      } finally {
        setTimeout(() => (busy.current = false), 1200);
      }
    },
    [apiCtx],
  );

  if (!permission?.granted) {
    return (
      <SafeAreaView style={styles.center} edges={['top']}>
        <Text style={styles.permText}>Camera este necesară pentru scanare.</Text>
        <Pressable
          onPress={requestPermission}
          style={[styles.permBtn, { backgroundColor: accent.base }]}
        >
          <Text style={styles.permBtnText}>Permite accesul la cameră</Text>
        </Pressable>
      </SafeAreaView>
    );
  }

  return (
    <SafeAreaView style={styles.safe} edges={['top']}>
      <View style={styles.topbar}>
        <Text style={styles.title}>Scanare</Text>
      </View>
      <View style={styles.camWrap}>
        {active ? (
          <CameraView
            style={StyleSheet.absoluteFill}
            facing="back"
            barcodeScannerSettings={{ barcodeTypes: ['qr', 'code128', 'code39', 'ean13', 'ean8'] }}
            onBarcodeScanned={onScanned}
          />
        ) : (
          <View style={[StyleSheet.absoluteFill, styles.camOff]} />
        )}
        <View style={[styles.reticle, { borderColor: accent.base }]} />
        {!active && (
          <Pressable
            onPress={() => setActive(true)}
            style={[styles.startBtn, { backgroundColor: accent.base }]}
          >
            <Text style={styles.startBtnText}>Pornește camera</Text>
          </Pressable>
        )}
      </View>

      {result && (
        <View
          style={[
            styles.result,
            {
              backgroundColor: withAlpha(result.ok ? palette.success : palette.warning, 0.14),
              borderColor: withAlpha(result.ok ? palette.success : palette.warning, 0.35),
            },
          ]}
        >
          <Text style={[styles.resTitle, { color: result.ok ? palette.success : palette.warning }]}>
            {result.ok ? '✓ Check-in valid' : '⚠ ' + (result.res.message ?? 'Bilet respins')}
          </Text>
          {result.res.ticket && (
            <Text style={styles.resMeta}>
              {[
                result.res.ticket.attendee_name,
                result.res.ticket.ticket_type,
                result.res.ticket.seat_label,
              ]
                .filter(Boolean)
                .join(' · ')}
            </Text>
          )}
          {result.res.venue_notes ? (
            <Text style={styles.resNote}>📌 {result.res.venue_notes}</Text>
          ) : null}
        </View>
      )}
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  safe: { flex: 1, backgroundColor: palette.bg },
  center: {
    flex: 1,
    backgroundColor: palette.bg,
    alignItems: 'center',
    justifyContent: 'center',
    gap: 16,
    paddingHorizontal: 30,
  },
  permText: { color: palette.muted, fontSize: 14, textAlign: 'center' },
  permBtn: { borderRadius: radius.md, paddingVertical: 13, paddingHorizontal: 20 },
  permBtnText: { color: '#04211a', fontWeight: '900' },
  topbar: { paddingHorizontal: 16, paddingTop: 10, paddingBottom: 8 },
  title: { color: palette.text, fontSize: 20, fontWeight: '900' },
  camWrap: {
    flex: 1,
    margin: 15,
    borderRadius: radius.xl,
    overflow: 'hidden',
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: '#04070a',
  },
  camOff: { backgroundColor: '#0c1420' },
  reticle: { width: 180, height: 180, borderWidth: 3, borderRadius: 16, opacity: 0.9 },
  startBtn: {
    position: 'absolute',
    bottom: 24,
    borderRadius: radius.md,
    paddingVertical: 13,
    paddingHorizontal: 22,
  },
  startBtnText: { color: '#04211a', fontWeight: '900' },
  result: {
    margin: 15,
    marginTop: 0,
    borderRadius: radius.lg,
    borderWidth: 1,
    padding: 14,
    gap: 6,
  },
  resTitle: { fontSize: 15, fontWeight: '900' },
  resMeta: { color: palette.muted, fontSize: 12.5 },
  resNote: { color: palette.warning, fontSize: 12 },
});
