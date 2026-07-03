// KioskScreen — self-service check-in pentru tableta clientilor Sf. Ana.
//
// Comportament:
//  - Camera activa permanent cu chenar de scan + text amabil
//  - Debounce 2.5s intre scanari (evita lecturi duble ale aceluiasi QR)
//  - POST /organizer/participants/checkin { ticket_code }
//  - Success = ecran verde uriaș cu ✓ + tip bilet + "Bine ai venit, {nume}!"
//  - Duplicate = ecran galben cu ⚠ + timestamp scanarii anterioare
//  - Invalid  = ecran rosu cu ✕ + "Adresati-va unui membru al echipei"
//  - Auto-revine la starea de scan dupa 3.5s (success) / 4.5s (eroare)
//  - Blocheaza system back (kiosk mode). expo-keep-awake tine ecranul aprins.
//
// Layout responsiv:
//  - Landscape / tableta (width > height): split 2 coloane
//      LEFT  = fundal + branding + mesaj bun venit + pasi ilustrati
//      RIGHT = camera + chenar scan + overlays de rezultat
//  - Portrait / telefon: fullscreen camera cu overlay peste (varianta veche)
//
// Camera flip:
//  - Iconita discreta in colt dreapta-sus, doar iconita, fara text.
//    Clientii nu vor fi tentati sa dea click, dar operatorul stie ca e acolo.
//  - Toggle "back" <-> "front".
//
// Sunete (expo-av, preincarcate la mount):
//  - success.wav: chime C5->E5 la validare cu succes
//  - error.wav:  buzz descendent F#4->D4->A3 la duplicate / invalid
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
  Pressable,
  useWindowDimensions,
} from 'react-native';
import { CameraView, useCameraPermissions } from 'expo-camera';
import { useKeepAwake } from 'expo-keep-awake';
import { Audio } from 'expo-av';
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

export default function KioskScreen() {
  useKeepAwake();
  const { width: SW, height: SH } = useWindowDimensions();
  const isLandscape = SW > SH;

  const [permission, requestPermission] = useCameraPermissions();
  const [status, setStatus] = useState(S_READY);
  const [payload, setPayload] = useState(null);   // rezultat check-in
  const [errorMsg, setErrorMsg] = useState('');
  const [cameraFacing, setCameraFacing] = useState('back');
  const lastScanRef = useRef({ code: null, at: 0 });
  const returnTimerRef = useRef(null);
  const successSoundRef = useRef(null);
  const errorSoundRef = useRef(null);

  // Cerere permisiune la prima montare
  useEffect(() => {
    if (permission && !permission.granted && permission.canAskAgain) {
      requestPermission();
    }
  }, [permission]);

  // Preincarcare sunete la mount + cleanup la unmount. Le tinem in ref pentru
  // ca playAsync sa poata rula fara re-load. replayAsync = re-declanseaza chiar
  // daca sunetul era la mijlocul redarii (ex. 2 scanari rapide).
  useEffect(() => {
    let mounted = true;
    (async () => {
      try {
        await Audio.setAudioModeAsync({
          playsInSilentModeIOS: true,
          shouldDuckAndroid: true,
          staysActiveInBackground: false,
        });
        const [{ sound: sSuccess }, { sound: sError }] = await Promise.all([
          Audio.Sound.createAsync(require('../../assets/sounds/success.wav')),
          Audio.Sound.createAsync(require('../../assets/sounds/error.wav')),
        ]);
        if (mounted) {
          successSoundRef.current = sSuccess;
          errorSoundRef.current = sError;
        } else {
          sSuccess.unloadAsync();
          sError.unloadAsync();
        }
      } catch (e) {
        // Fail silent — kiosk-ul functioneaza si fara audio.
      }
    })();
    return () => {
      mounted = false;
      if (successSoundRef.current) successSoundRef.current.unloadAsync();
      if (errorSoundRef.current) errorSoundRef.current.unloadAsync();
    };
  }, []);

  const playSound = useCallback((kind) => {
    const ref = kind === 'success' ? successSoundRef : errorSoundRef;
    const snd = ref.current;
    if (!snd) return;
    // replayAsync repornește sunetul chiar dacă e la mijloc; fail silent daca nu
    // e inca gata (mount race).
    snd.replayAsync().catch(() => {});
  }, []);

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

  const flipCamera = useCallback(() => {
    setCameraFacing((prev) => (prev === 'back' ? 'front' : 'back'));
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
        playSound('success');
        scheduleReturn(RESULT_MS_SUCCESS);
        return;
      }
      // Duplicate: HTTP 400 cu message "already checked in"
      const msg = String(resp?.message || resp?.error || '');
      if (/already checked in/i.test(msg) || /deja/i.test(msg)) {
        setPayload(resp?.data || resp);
        setErrorMsg(msg);
        setStatus(S_DUPLICATE);
        playSound('error');
        scheduleReturn(RESULT_MS_ERROR);
        return;
      }
      setErrorMsg(msg || 'Bilet invalid');
      setStatus(S_INVALID);
      playSound('error');
      scheduleReturn(RESULT_MS_ERROR);
    } catch (e) {
      const em = e?.message || 'Eroare la validare';
      // apiPost arunca cu message inclus; detect duplicate din text
      if (/already checked in/i.test(em) || /deja/i.test(em)) {
        setErrorMsg(em);
        setStatus(S_DUPLICATE);
        playSound('error');
        scheduleReturn(RESULT_MS_ERROR);
        return;
      }
      setErrorMsg(em);
      setStatus(S_INVALID);
      playSound('error');
      scheduleReturn(RESULT_MS_ERROR);
    }
  }, [status, scheduleReturn, playSound]);

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

  // ─────────────────────────────────────────────────────────────────
  // LANDSCAPE (tabletă): 2 coloane
  // ─────────────────────────────────────────────────────────────────
  if (isLandscape) {
    // 45% stanga (welcome) / 55% dreapta (camera). Camera > text ca proportie
    // pentru ca acolo se intampla actiunea.
    const rightW = Math.max(360, Math.round(SW * 0.55));
    const frameSize = Math.min(rightW, SH) * 0.7;

    return (
      <View style={styles.rowLayout}>
        {/* LEFT — welcome + branding + pasi */}
        <View style={styles.leftPanel}>
          <View style={styles.leftAccentTop} />

          <View style={styles.leftHeader}>
            <Text style={styles.brandKicker}>Lacul Sf. Ana</Text>
            <Text style={styles.brandTitle}>Bun venit!</Text>
            <Text style={styles.brandSubtitle}>
              Îți dorim o zi plăcută. Scanează biletul pentru a intra rapid.
            </Text>
          </View>

          <View style={styles.stepsBlock}>
            <StepRow
              n="1"
              title="Deschide biletul"
              body="Pe telefon, deschide QR-ul primit prin email după cumpărare."
            />
            <StepRow
              n="2"
              title="Îndreaptă spre cameră"
              body="Ține telefonul la ~20 cm în fața camerei din partea dreaptă."
            />
            <StepRow
              n="3"
              title="Așteaptă semnalul"
              body="Ecran verde ✓ = poți intra. Ecran galben / roșu = un membru al echipei te ajută."
            />
          </View>

          <View style={styles.leftFooter}>
            <Text style={styles.footerHint}>
              Ai nevoie de ajutor? Adresează-te unui membru al echipei. 🌲
            </Text>
          </View>
        </View>

        {/* RIGHT — camera + chenar + overlays */}
        <View style={[styles.rightPanel, { width: rightW }]}>
          <CameraView
            style={StyleSheet.absoluteFillObject}
            facing={cameraFacing}
            barcodeScannerSettings={{ barcodeTypes: ['qr', 'pdf417', 'code128', 'code39', 'ean13'] }}
            onBarcodeScanned={status === S_READY ? handleScan : undefined}
          />

          {/* Chenar de scan + buton flip (doar in READY) */}
          {status === S_READY && (
            <>
              <View style={styles.rightScrim} />
              <View style={styles.readyRightWrap} pointerEvents="box-none">
                <View style={[styles.frame, { width: frameSize, height: frameSize }]} />
                <Text style={styles.rightHint}>
                  🎫 QR-ul biletului trebuie să fie în chenar
                </Text>
              </View>
              <FlipCameraButton onPress={flipCamera} facing={cameraFacing} />
            </>
          )}

          {/* Overlay LOADING - acopera doar coloana dreapta */}
          {status === S_LOADING && (
            <View style={[styles.fullRightOverlay, styles.overlayLoading]}>
              <ActivityIndicator size="large" color={colors.paper} />
              <Text style={styles.loadingText}>Se verifică biletul...</Text>
            </View>
          )}

          {status === S_SUCCESS && <SuccessOverlay data={payload} rightSide />}
          {status === S_DUPLICATE && <DuplicateOverlay data={payload} message={errorMsg} rightSide />}
          {status === S_INVALID && <InvalidOverlay message={errorMsg} rightSide />}
        </View>
      </View>
    );
  }

  // ─────────────────────────────────────────────────────────────────
  // PORTRAIT (telefon): fullscreen camera cu overlay (varianta veche)
  // ─────────────────────────────────────────────────────────────────
  const frameSize = Math.min(SW, SH) * 0.55;
  return (
    <View style={styles.container}>
      <CameraView
        style={StyleSheet.absoluteFillObject}
        facing={cameraFacing}
        barcodeScannerSettings={{ barcodeTypes: ['qr', 'pdf417', 'code128', 'code39', 'ean13'] }}
        onBarcodeScanned={status === S_READY ? handleScan : undefined}
      />

      {status === S_READY && (
        <>
          <ReadyOverlay frameSize={frameSize} />
          <FlipCameraButton onPress={flipCamera} facing={cameraFacing} />
        </>
      )}

      {status === S_LOADING && (
        <View style={[styles.fullOverlay, styles.overlayLoading]}>
          <ActivityIndicator size="large" color={colors.paper} />
          <Text style={styles.loadingText}>Se verifică biletul...</Text>
        </View>
      )}

      {status === S_SUCCESS && <SuccessOverlay data={payload} />}
      {status === S_DUPLICATE && <DuplicateOverlay data={payload} message={errorMsg} />}
      {status === S_INVALID && <InvalidOverlay message={errorMsg} />}
    </View>
  );
}

// ============================================================================
// Sub-components
// ============================================================================

function StepRow({ n, title, body }) {
  return (
    <View style={styles.stepRow}>
      <View style={styles.stepBadge}>
        <Text style={styles.stepBadgeText}>{n}</Text>
      </View>
      <View style={styles.stepBody}>
        <Text style={styles.stepTitle}>{title}</Text>
        <Text style={styles.stepText}>{body}</Text>
      </View>
    </View>
  );
}

function FlipCameraButton({ onPress, facing }) {
  // Doar iconita, fara text — nu vrem sa tentam clientii sa dea click.
  // Discret in colt dreapta-sus; operatorul stie ca e acolo.
  const label = facing === 'back' ? 'Cameră frontală' : 'Cameră spate';
  return (
    <View style={styles.flipWrap} pointerEvents="box-none">
      <Pressable
        onPress={onPress}
        style={({ pressed }) => [styles.flipBtn, pressed && styles.flipBtnPressed]}
        accessibilityLabel={label}
        hitSlop={10}
      >
        <Text style={styles.flipIcon}>🔄</Text>
      </Pressable>
    </View>
  );
}

function ReadyOverlay({ frameSize }) {
  // Legacy portrait ready overlay — pastrat pentru telefoane si test dev
  return (
    <View style={styles.readyOverlay}>
      <View style={styles.readyTop}>
        <Text style={styles.readyBrand}>Lacul Sf. Ana</Text>
        <Text style={styles.readyTitle}>Bun venit!</Text>
        <Text style={styles.readySubtitle}>
          Îndreaptă biletul spre cameră pentru validare.
        </Text>
      </View>

      <View style={[styles.frame, { width: frameSize, height: frameSize }]} />

      <View style={styles.readyBottom}>
        <Text style={styles.readyHint}>
          🎫 QR-ul biletului trebuie să fie în chenar. Așteaptă semnalul verde.
        </Text>
      </View>
    </View>
  );
}

// Extrage primul nume dintr-un string full-name. Backend returneaza
// customer.name ca "Prenume Nume" (asamblat din marketplace_customers).
// Cazuri edge: null/empty -> null; "John" -> "John"; "John Doe" -> "John";
// spatii multiple / trim / capitalizare surse -> normalizat.
function firstNameOf(fullName) {
  if (!fullName || typeof fullName !== 'string') return null;
  const cleaned = fullName.trim().replace(/\s+/g, ' ');
  if (!cleaned) return null;
  const first = cleaned.split(' ')[0];
  return first || null;
}

function SuccessOverlay({ data, rightSide }) {
  // Distinctia bilet client vs staff QR:
  //   - Bilet client: backend returneaza data.customer.name (assembly
  //     first_name+last_name din marketplace_customers) + data.ticket.ticket_type.
  //   - Staff QR:     backend returneaza data.staff.full_name +
  //     data.staff.position. Nu exista data.customer sau data.ticket.
  //
  // Fallback chain robusta pentru ambele cazuri + invitatii (attendee_name)
  // + POS orders vechi (order.customer_name).
  const isStaff = !!data?.is_staff || !!data?.staff;
  const fullName =
    data?.customer?.name ||
    data?.staff?.full_name ||
    data?.ticket?.attendee_name ||
    data?.order?.customer_name ||
    '';
  const firstName = firstNameOf(fullName);
  const badge = isStaff
    ? (data?.staff?.position || 'Personal')
    : (data?.ticket?.ticket_type || 'Bilet');
  const closingLine = isStaff
    ? 'Spor la muncă! 🌲'
    : 'Vă mulțumim! Ziua plăcută la Lacul Sf. Ana. 🌲';

  return (
    <View style={[rightSide ? styles.fullRightOverlay : styles.fullOverlay, styles.overlaySuccess]}>
      <Text style={styles.bigIcon}>✓</Text>
      <Text style={styles.bigTitle}>
        {firstName ? `Bun venit, ${firstName}!` : 'Bun venit!'}
      </Text>
      <View style={styles.pill}>
        <Text style={styles.pillText}>{badge}</Text>
      </View>
      <Text style={styles.politeMessage}>{closingLine}</Text>
    </View>
  );
}

function DuplicateOverlay({ data, message, rightSide }) {
  const tName = data?.ticket?.ticket_type || 'Bilet';
  const checkedAt = data?.ticket?.checked_in_at
    ? new Date(data.ticket.checked_in_at).toLocaleTimeString('ro-RO', { hour: '2-digit', minute: '2-digit' })
    : null;
  return (
    <View style={[rightSide ? styles.fullRightOverlay : styles.fullOverlay, styles.overlayDuplicate]}>
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

function InvalidOverlay({ message, rightSide }) {
  return (
    <View style={[rightSide ? styles.fullRightOverlay : styles.fullOverlay, styles.overlayInvalid]}>
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

  // ── Landscape (tabletă) — 2 coloane
  rowLayout: {
    flex: 1,
    flexDirection: 'row',
    backgroundColor: '#000',
  },
  leftPanel: {
    flex: 1,
    // Fundal padure/lac — verde-negru profund din tema (ink)
    backgroundColor: colors.ink,
    paddingHorizontal: 40,
    paddingVertical: 48,
    justifyContent: 'space-between',
    overflow: 'hidden',
  },
  leftAccentTop: {
    position: 'absolute',
    top: -80,
    right: -80,
    width: 260,
    height: 260,
    borderRadius: 260,
    backgroundColor: colors.primary + '22', // tint verde subtil, colt dreapta-sus
  },
  leftHeader: {
    zIndex: 1,
  },
  brandKicker: {
    fontSize: 14,
    fontWeight: '700',
    color: colors.lake300,
    letterSpacing: 4,
    textTransform: 'uppercase',
    marginBottom: 16,
  },
  brandTitle: {
    fontSize: 56,
    fontWeight: '900',
    color: colors.paper,
    marginBottom: 12,
    lineHeight: 62,
  },
  brandSubtitle: {
    fontSize: 20,
    color: colors.paper,
    opacity: 0.85,
    lineHeight: 28,
    maxWidth: 480,
  },

  stepsBlock: {
    zIndex: 1,
    gap: 20,
    marginVertical: 24,
  },
  stepRow: {
    flexDirection: 'row',
    alignItems: 'flex-start',
    gap: 16,
  },
  stepBadge: {
    width: 44,
    height: 44,
    borderRadius: 22,
    backgroundColor: colors.primary,
    alignItems: 'center',
    justifyContent: 'center',
  },
  stepBadgeText: {
    color: colors.paper,
    fontWeight: '900',
    fontSize: 20,
  },
  stepBody: {
    flex: 1,
    paddingTop: 2,
  },
  stepTitle: {
    fontSize: 18,
    fontWeight: '800',
    color: colors.paper,
    marginBottom: 4,
  },
  stepText: {
    fontSize: 15,
    color: colors.paper,
    opacity: 0.8,
    lineHeight: 22,
  },

  leftFooter: {
    zIndex: 1,
    paddingTop: 16,
    borderTopWidth: 1,
    borderTopColor: 'rgba(255,255,255,0.15)',
  },
  footerHint: {
    fontSize: 15,
    color: colors.paper,
    opacity: 0.7,
    textAlign: 'left',
  },

  rightPanel: {
    height: '100%',
    backgroundColor: '#000',
  },
  rightScrim: {
    ...StyleSheet.absoluteFillObject,
    backgroundColor: 'rgba(15,44,32,0.35)',
  },
  readyRightWrap: {
    ...StyleSheet.absoluteFillObject,
    alignItems: 'center',
    justifyContent: 'center',
    paddingHorizontal: 24,
  },
  rightHint: {
    marginTop: 28,
    fontSize: 16,
    color: colors.paper,
    opacity: 0.9,
    fontWeight: '600',
    textAlign: 'center',
  },

  // Buton flip camera (iconita discreta, colt dreapta-sus)
  flipWrap: {
    position: 'absolute',
    top: 16,
    right: 16,
    zIndex: 20,
  },
  flipBtn: {
    width: 40,
    height: 40,
    borderRadius: 20,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: 'rgba(0,0,0,0.35)',
    borderWidth: 1,
    borderColor: 'rgba(255,255,255,0.15)',
  },
  flipBtnPressed: {
    backgroundColor: 'rgba(0,0,0,0.65)',
  },
  flipIcon: {
    fontSize: 16,
    color: colors.paper,
    opacity: 0.85,
  },

  // READY portrait (varianta veche, telefon)
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

  // FULL OVERLAY (folosit pentru loading + rezultate in portrait)
  fullOverlay: {
    ...StyleSheet.absoluteFillObject,
    justifyContent: 'center',
    alignItems: 'center',
    paddingHorizontal: 32,
  },
  // Variant care se aplica peste doar coloana dreapta in landscape
  fullRightOverlay: {
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
