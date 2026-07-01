// KioskScreen — self-service check-in pentru tableta clientilor Sf. Ana.
//
// Comportament:
//  - Camera activa permanent (fullscreen) cu chenar de scan + text amabil
//  - Debounce 2.5s intre scanari (evita lecturi duble ale aceluiasi QR)
//  - POST /organizer/participants/checkin { ticket_code }
//  - Success = ecran verde uriaș cu ✓ + tip bilet + "Bine ai venit, {nume}!"
//  - Duplicate = ecran galben cu ⚠ + timestamp scanarii anterioare
//  - Invalid  = ecran rosu cu ✕ + "Adresati-va unui membru al echipei"
//  - Auto-revine la starea de scan dupa 3.5s (success) / 4.5s (eroare)
//  - Blocheaza system back (kiosk mode). expo-keep-awake tine ecranul aprins.
//
// Activare: user cu team_member.leisure_role === 'kiosk_selfcheckin'
// este rutat direct aici in App.js (nu vede Hub / login card / setari).

import React, { useCallback, useEffect, useRef, useState } from 'react';
import {
  View,
  Text,
  StyleSheet,
  ActivityIndicator,
  BackHandler,
  Platform,
  Dimensions,
} from 'react-native';
import { CameraView, useCameraPermissions } from 'expo-camera';
import { useKeepAwake } from 'expo-keep-awake';
import { colors } from '../theme/colors';
import { organizerCheckInByCode } from '../api/leisure';

// Fereastre de afisare rezultat inainte de auto-return
const RESULT_MS_SUCCESS = 3500;
const RESULT_MS_ERROR = 4500;
// Fereastra de debounce intre scanari (evita re-trigger pe acelasi QR)
const DEBOUNCE_MS = 2500;

// STATES
const S_READY = 'ready';       // camera activa, asteapta scan
const S_LOADING = 'loading';   // request in curs
const S_SUCCESS = 'success';
const S_DUPLICATE = 'duplicate';
const S_INVALID = 'invalid';

const { width: SW, height: SH } = Dimensions.get('window');
const FRAME = Math.min(SW, SH) * 0.55; // patrat 55% din latura mica

export default function KioskScreen() {
  useKeepAwake();
  const [permission, requestPermission] = useCameraPermissions();
  const [status, setStatus] = useState(S_READY);
  const [payload, setPayload] = useState(null);   // rezultat check-in
  const [errorMsg, setErrorMsg] = useState('');
  const lastScanRef = useRef({ code: null, at: 0 });
  const returnTimerRef = useRef(null);

  // Cerere permisiune la prima montare
  useEffect(() => {
    if (permission && !permission.granted && permission.canAskAgain) {
      requestPermission();
    }
  }, [permission]);

  // Blocheaza system back (kiosk mode)
  useEffect(() => {
    const sub = BackHandler.addEventListener('hardwareBackPress', () => true);
    return () => sub.remove();
  }, []);

  // Cleanup timer la unmount
  useEffect(() => {
    return () => {
      if (returnTimerRef.current) clearTimeout(returnTimerRef.current);
    };
  }, []);

  const scheduleReturn = useCallback((ms) => {
    if (returnTimerRef.current) clearTimeout(returnTimerRef.current);
    returnTimerRef.current = setTimeout(() => {
      setStatus(S_READY);
      setPayload(null);
      setErrorMsg('');
    }, ms);
  }, []);

  const handleScan = useCallback(async ({ data }) => {
    if (!data) return;
    if (status !== S_READY) return;

    // Debounce: acelasi cod scanat de mai multe ori in <2.5s
    const now = Date.now();
    if (lastScanRef.current.code === data && (now - lastScanRef.current.at) < DEBOUNCE_MS) return;
    lastScanRef.current = { code: data, at: now };

    setStatus(S_LOADING);
    try {
      const resp = await organizerCheckInByCode(data);
      if (resp?.success) {
        setPayload(resp?.data || resp);
        setStatus(S_SUCCESS);
        scheduleReturn(RESULT_MS_SUCCESS);
        return;
      }
      // Duplicate: HTTP 400 cu message "already checked in"
      const msg = String(resp?.message || resp?.error || '');
      if (/already checked in/i.test(msg) || /deja/i.test(msg)) {
        setPayload(resp?.data || resp);
        setErrorMsg(msg);
        setStatus(S_DUPLICATE);
        scheduleReturn(RESULT_MS_ERROR);
        return;
      }
      setErrorMsg(msg || 'Bilet invalid');
      setStatus(S_INVALID);
      scheduleReturn(RESULT_MS_ERROR);
    } catch (e) {
      const em = e?.message || 'Eroare la validare';
      // apiPost arunca cu message inclus; detect duplicate din text
      if (/already checked in/i.test(em) || /deja/i.test(em)) {
        setErrorMsg(em);
        setStatus(S_DUPLICATE);
        scheduleReturn(RESULT_MS_ERROR);
        return;
      }
      setErrorMsg(em);
      setStatus(S_INVALID);
      scheduleReturn(RESULT_MS_ERROR);
    }
  }, [status, scheduleReturn]);

  // Camera permissions gates
  if (!permission) {
    return (
      <View style={styles.center}>
        <ActivityIndicator size="large" color={colors.primary} />
      </View>
    );
  }
  if (!permission.granted) {
    return (
      <View style={styles.center}>
        <Text style={styles.permTitle}>📷 Acces cameră necesar</Text>
        <Text style={styles.permText}>
          Această tabletă are nevoie de permisiunea camerei pentru a scana biletele.
        </Text>
        <Text style={styles.permHint}>Adresați-vă unui membru al echipei.</Text>
      </View>
    );
  }

  return (
    <View style={styles.container}>
      <CameraView
        style={StyleSheet.absoluteFillObject}
        facing="back"
        barcodeScannerSettings={{ barcodeTypes: ['qr', 'pdf417', 'code128', 'code39', 'ean13'] }}
        onBarcodeScanned={status === S_READY ? handleScan : undefined}
      />

      {/* Overlay READY: chenar + text amabil */}
      {status === S_READY && <ReadyOverlay />}

      {/* Overlay LOADING */}
      {status === S_LOADING && (
        <View style={[styles.fullOverlay, styles.overlayLoading]}>
          <ActivityIndicator size="large" color={colors.paper} />
          <Text style={styles.loadingText}>Se verifică biletul...</Text>
        </View>
      )}

      {/* Overlay SUCCESS */}
      {status === S_SUCCESS && <SuccessOverlay data={payload} />}

      {/* Overlay DUPLICATE */}
      {status === S_DUPLICATE && <DuplicateOverlay data={payload} message={errorMsg} />}

      {/* Overlay INVALID */}
      {status === S_INVALID && <InvalidOverlay message={errorMsg} />}
    </View>
  );
}

// ============================================================================
// Overlays
// ============================================================================

function ReadyOverlay() {
  return (
    <View style={styles.readyOverlay}>
      <View style={styles.readyTop}>
        <Text style={styles.readyBrand}>Lacul Sf. Ana</Text>
        <Text style={styles.readyTitle}>Bun venit!</Text>
        <Text style={styles.readySubtitle}>
          Îndreaptă biletul spre cameră pentru validare.
        </Text>
      </View>

      <View style={styles.frame} />

      <View style={styles.readyBottom}>
        <Text style={styles.readyHint}>
          🎫 QR-ul biletului trebuie să fie în chenar. Așteaptă semnalul verde.
        </Text>
      </View>
    </View>
  );
}

function SuccessOverlay({ data }) {
  const tName = data?.ticket?.ticket_type || 'Bilet';
  const attendee = data?.ticket?.attendee_name || data?.customer?.name || '';
  return (
    <View style={[styles.fullOverlay, styles.overlaySuccess]}>
      <Text style={styles.bigIcon}>✓</Text>
      <Text style={styles.bigTitle}>Bine ai venit!</Text>
      {attendee ? <Text style={styles.bigSubtitle}>{attendee}</Text> : null}
      <View style={styles.pill}>
        <Text style={styles.pillText}>{tName}</Text>
      </View>
      <Text style={styles.politeMessage}>
        Vă mulțumim! Ziua plăcută la Lacul Sf. Ana. 🌲
      </Text>
    </View>
  );
}

function DuplicateOverlay({ data, message }) {
  const tName = data?.ticket?.ticket_type || 'Bilet';
  const checkedAt = data?.ticket?.checked_in_at
    ? new Date(data.ticket.checked_in_at).toLocaleTimeString('ro-RO', { hour: '2-digit', minute: '2-digit' })
    : null;
  return (
    <View style={[styles.fullOverlay, styles.overlayDuplicate]}>
      <Text style={styles.bigIcon}>⚠</Text>
      <Text style={styles.bigTitle}>Bilet deja validat</Text>
      <View style={styles.pill}>
        <Text style={styles.pillText}>{tName}</Text>
      </View>
      {checkedAt ? (
        <Text style={styles.politeMessage}>
          Acest bilet a fost scanat astăzi la ora {checkedAt}.
        </Text>
      ) : (
        <Text style={styles.politeMessage}>{message || 'Biletul a fost deja validat.'}</Text>
      )}
      <Text style={styles.politeSmall}>
        Pentru asistență, adresați-vă unui membru al echipei.
      </Text>
    </View>
  );
}

function InvalidOverlay({ message }) {
  return (
    <View style={[styles.fullOverlay, styles.overlayInvalid]}>
      <Text style={styles.bigIcon}>✕</Text>
      <Text style={styles.bigTitle}>Bilet invalid</Text>
      <Text style={styles.politeMessage}>
        Codul scanat nu poate fi validat.
      </Text>
      <Text style={styles.politeSmall}>
        Vă rugăm să vă adresați unui membru al echipei pentru asistență.
      </Text>
    </View>
  );
}

// ============================================================================
// Styles
// ============================================================================

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: '#000' },
  center: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'center',
    padding: 32,
    backgroundColor: colors.background,
  },
  permTitle: { fontSize: 28, fontWeight: '800', color: colors.paper, marginBottom: 12, textAlign: 'center' },
  permText: { fontSize: 18, color: colors.textSecondary, textAlign: 'center', marginBottom: 8 },
  permHint: { fontSize: 15, color: colors.textTertiary, textAlign: 'center' },

  // READY
  readyOverlay: {
    ...StyleSheet.absoluteFillObject,
    justifyContent: 'space-between',
    alignItems: 'center',
    paddingVertical: 48,
    backgroundColor: 'rgba(15,44,32,0.55)',
  },
  readyTop: { alignItems: 'center', paddingHorizontal: 32 },
  readyBrand: {
    fontSize: 16,
    fontWeight: '700',
    color: colors.lake300,
    letterSpacing: 3,
    textTransform: 'uppercase',
    marginBottom: 12,
  },
  readyTitle: {
    fontSize: 44,
    fontWeight: '900',
    color: colors.paper,
    textAlign: 'center',
    marginBottom: 8,
  },
  readySubtitle: {
    fontSize: 20,
    color: colors.paper,
    opacity: 0.9,
    textAlign: 'center',
    fontWeight: '500',
  },
  frame: {
    width: FRAME,
    height: FRAME,
    borderRadius: 32,
    borderWidth: 4,
    borderColor: colors.primary,
    backgroundColor: 'transparent',
  },
  readyBottom: { paddingHorizontal: 32 },
  readyHint: {
    fontSize: 16,
    color: colors.paper,
    opacity: 0.85,
    textAlign: 'center',
    fontWeight: '500',
  },

  // FULL OVERLAY (used for loading + result screens)
  fullOverlay: {
    ...StyleSheet.absoluteFillObject,
    justifyContent: 'center',
    alignItems: 'center',
    paddingHorizontal: 32,
  },
  overlayLoading: { backgroundColor: 'rgba(15,44,32,0.85)' },
  overlaySuccess: { backgroundColor: 'rgba(45,122,79,0.98)' },
  overlayDuplicate: { backgroundColor: 'rgba(212,146,42,0.98)' },
  overlayInvalid: { backgroundColor: 'rgba(190,50,55,0.98)' },

  loadingText: {
    marginTop: 20,
    fontSize: 20,
    color: colors.paper,
    fontWeight: '600',
  },

  bigIcon: {
    fontSize: 140,
    color: colors.paper,
    fontWeight: '900',
    marginBottom: 12,
    ...Platform.select({
      android: { lineHeight: 160 },
    }),
  },
  bigTitle: {
    fontSize: 48,
    fontWeight: '900',
    color: colors.paper,
    textAlign: 'center',
    marginBottom: 12,
  },
  bigSubtitle: {
    fontSize: 26,
    fontWeight: '700',
    color: colors.paper,
    opacity: 0.95,
    textAlign: 'center',
    marginBottom: 16,
  },
  pill: {
    backgroundColor: 'rgba(255,255,255,0.22)',
    paddingHorizontal: 24,
    paddingVertical: 12,
    borderRadius: 999,
    marginBottom: 24,
  },
  pillText: {
    fontSize: 20,
    fontWeight: '700',
    color: colors.paper,
    letterSpacing: 0.5,
  },
  politeMessage: {
    fontSize: 22,
    color: colors.paper,
    textAlign: 'center',
    fontWeight: '500',
    opacity: 0.95,
    marginBottom: 12,
    maxWidth: 640,
  },
  politeSmall: {
    fontSize: 16,
    color: colors.paper,
    opacity: 0.85,
    textAlign: 'center',
    maxWidth: 560,
  },
});
