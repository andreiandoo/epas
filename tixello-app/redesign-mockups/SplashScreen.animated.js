// ============================================================================
// AmBilet Scan — animated splash (QR-scan reveal → logo lockup)
//
// Drop-in replacement for src/screens/SplashScreen.js. Same contract:
//   <SplashScreen onFinish={() => ...} />  is called once when the sequence ends.
//
// Sequence (~2.6s, runs ONCE):
//   1. maroon radial background + pulsing glow fade in
//   2. scan-frame corner brackets draw in
//   3. a glowing beam sweeps top→bottom and "lights up" the QR as it passes
//   4. green success ring + check pop
//   5. the whole scan zone collapses upward and morphs into the AmBilet mark
//   6. "AmBilet Scan" wordmark + tagline rise; loading bar fills
//
// Dependencies: only react-native + react-native-svg (both already installed).
// No new packages, no expo-linear-gradient, no Lottie.
// ============================================================================
import React, { useEffect, useMemo, useRef } from 'react';
import { View, Text, StyleSheet, Animated, Easing, Dimensions, Platform } from 'react-native';
import Svg, { Rect, Defs, RadialGradient, Stop, Path, G } from 'react-native-svg';

const { width: SCREEN_W } = Dimensions.get('window');

// Brand palette (self-contained so this file works before theme is re-skinned)
const C = {
  glow: 'rgba(226,58,69,0.55)',
  beam: '#FFFFFF',
  green: '#25D07A',
  red: '#9A1B22',
  redBright: '#E23A45',
};

const ZONE = 210;   // scan-zone box
const QR = 168;     // qr size within the zone
const N = 21;       // qr modules per side
const DURATION = 2600;

// ── deterministic QR-like matrix (finder patterns + timing + pseudo-random data)
function buildMatrix() {
  let seed = 7;
  const rnd = () => { seed = (seed * 1103515245 + 12345) & 0x7fffffff; return seed / 0x7fffffff; };
  const m = Array.from({ length: N }, () => Array(N).fill(0));
  for (let r = 0; r < N; r++) for (let c = 0; c < N; c++) m[r][c] = rnd() > 0.52 ? 1 : 0;
  const finder = (r0, c0) => {
    for (let i = -1; i <= 7; i++) for (let j = -1; j <= 7; j++) {
      const r = r0 + i, c = c0 + j;
      if (r < 0 || c < 0 || r >= N || c >= N) continue;
      const ring = i >= 0 && i <= 6 && j >= 0 && j <= 6 && (i === 0 || i === 6 || j === 0 || j === 6);
      const core = i >= 2 && i <= 4 && j >= 2 && j <= 4;
      m[r][c] = ring || core ? 1 : 0;
    }
  };
  finder(0, 0); finder(0, 14); finder(14, 0);
  for (let i = 8; i < 13; i++) { m[6][i] = i % 2; m[i][6] = i % 2; }
  return m;
}

function QrLayer({ lit }) {
  const matrix = useMemo(buildMatrix, []);
  const cells = [];
  for (let r = 0; r < N; r++) for (let c = 0; c < N; c++) {
    if (!matrix[r][c]) continue;
    const accent = lit && (r + c) % 6 === 0;
    cells.push(
      <Rect
        key={`${r}-${c}`}
        x={c + 0.06} y={r + 0.06} width={0.88} height={0.88} rx={0.12}
        fill={lit ? (accent ? C.redBright : '#FFFFFF') : '#FFFFFF'}
        fillOpacity={lit ? 1 : 0.14}
      />
    );
  }
  return (
    <Svg width={QR} height={QR} viewBox={`0 0 ${N} ${N}`}>
      <G>{cells}</G>
    </Svg>
  );
}

export default function SplashScreen({ onFinish }) {
  // Two master clocks: `t` drives transforms/opacity (native driver),
  // `tJS` drives layout props that the native driver can't (height, width).
  const t = useRef(new Animated.Value(0)).current;
  const tJS = useRef(new Animated.Value(0)).current;

  useEffect(() => {
    const cfg = { duration: DURATION, easing: Easing.linear, useNativeDriver: true };
    Animated.parallel([
      Animated.timing(t, { toValue: 1, ...cfg }),
      Animated.timing(tJS, { toValue: 1, duration: DURATION, easing: Easing.linear, useNativeDriver: false }),
    ]).start(({ finished }) => { if (finished && onFinish) onFinish(); });
    // safety net in case the animation is interrupted
    const timer = setTimeout(() => { onFinish && onFinish(); }, DURATION + 400);
    return () => clearTimeout(timer);
  }, [t, tJS, onFinish]);

  // ── interpolations (fractions mirror the CSS keyframes) ──────────────────
  const glowOpacity = t.interpolate({ inputRange: [0, 0.18, 0.45, 0.6, 1], outputRange: [0, 0.5, 0.9, 0.7, 0.6] });

  const bracketOpacity = t.interpolate({ inputRange: [0, 0.04, 0.12, 0.52, 0.62], outputRange: [0, 0, 1, 1, 0] });
  const bracketScale = t.interpolate({ inputRange: [0, 0.12, 1], outputRange: [0.7, 1, 1] });

  const qrDimOpacity = t.interpolate({ inputRange: [0, 0.08, 0.16, 0.54, 0.62], outputRange: [0, 0, 1, 1, 0] });
  const revealHeight = tJS.interpolate({ inputRange: [0, 0.13, 0.49, 1], outputRange: [0, 0, QR, QR] });
  const litOpacity = t.interpolate({ inputRange: [0, 0.13, 0.54, 0.62], outputRange: [0, 1, 1, 0] });

  const beamOpacity = t.interpolate({ inputRange: [0, 0.13, 0.16, 0.49, 0.53], outputRange: [0, 0, 1, 1, 0] });
  const beamY = t.interpolate({ inputRange: [0, 0.16, 0.49, 1], outputRange: [4, 4, QR + 18, QR + 18] });

  const ringOpacity = t.interpolate({ inputRange: [0, 0.5, 0.54, 0.6, 0.66], outputRange: [0, 0, 1, 0.9, 0] });
  const ringScale = t.interpolate({ inputRange: [0, 0.5, 0.54, 0.6, 0.66], outputRange: [0.55, 0.55, 0.85, 1.12, 1.3] });
  const checkOpacity = t.interpolate({ inputRange: [0, 0.51, 0.54, 0.66, 0.7], outputRange: [0, 0, 1, 1, 0] });
  const checkScale = t.interpolate({ inputRange: [0, 0.52, 0.6, 0.7], outputRange: [0.5, 0.5, 1.05, 1] });

  const zoneScale = t.interpolate({ inputRange: [0, 0.52, 0.66], outputRange: [1, 1, 0.42] });
  const zoneY = t.interpolate({ inputRange: [0, 0.52, 0.66], outputRange: [0, 0, -170] });
  const zoneOpacity = t.interpolate({ inputRange: [0, 0.58, 0.66], outputRange: [1, 1, 0] });

  const lockOpacity = t.interpolate({ inputRange: [0, 0.6, 0.7, 1], outputRange: [0, 0, 1, 1] });
  const lockY = t.interpolate({ inputRange: [0, 0.6, 0.7], outputRange: [24, 24, 0] });

  const loadWidth = tJS.interpolate({ inputRange: [0, 0.06, 0.9, 1], outputRange: [0, 0, 150, 150] });
  const poweredOpacity = t.interpolate({ inputRange: [0, 0.08, 0.2, 1], outputRange: [0, 0, 0.85, 0.85] });

  const bracketStyle = { opacity: bracketOpacity, transform: [{ scale: bracketScale }] };

  return (
    <View style={styles.root}>
      {/* radial maroon background */}
      <Svg style={StyleSheet.absoluteFill}>
        <Defs>
          <RadialGradient id="bg" cx="50%" cy="22%" rx="95%" ry="80%">
            <Stop offset="0" stopColor="#8c1520" />
            <Stop offset="0.42" stopColor="#5c0d13" />
            <Stop offset="0.78" stopColor="#3a070c" />
            <Stop offset="1" stopColor="#2a050a" />
          </RadialGradient>
        </Defs>
        <Rect width="100%" height="100%" fill="url(#bg)" />
      </Svg>

      {/* pulsing glow */}
      <Animated.View style={[styles.glow, { opacity: glowOpacity }]} pointerEvents="none">
        <Svg width={SCREEN_W} height={SCREEN_W}>
          <Defs>
            <RadialGradient id="glow" cx="50%" cy="50%" r="50%">
              <Stop offset="0" stopColor={C.redBright} stopOpacity="0.55" />
              <Stop offset="1" stopColor={C.redBright} stopOpacity="0" />
            </RadialGradient>
          </Defs>
          <Rect width="100%" height="100%" fill="url(#glow)" />
        </Svg>
      </Animated.View>

      {/* ── scan zone (collapses into the logo) ── */}
      <Animated.View
        style={[styles.zone, { opacity: zoneOpacity, transform: [{ translateY: zoneY }, { scale: zoneScale }] }]}
      >
        {/* brackets */}
        <Animated.View style={[styles.bracket, styles.b1, bracketStyle]} />
        <Animated.View style={[styles.bracket, styles.b2, bracketStyle]} />
        <Animated.View style={[styles.bracket, styles.b3, bracketStyle]} />
        <Animated.View style={[styles.bracket, styles.b4, bracketStyle]} />

        {/* dim QR base */}
        <Animated.View style={[styles.qrAbs, { opacity: qrDimOpacity }]}>
          <QrLayer lit={false} />
        </Animated.View>

        {/* lit QR, revealed top→down by the beam (animated height clip) */}
        <Animated.View style={[styles.qrAbs, { opacity: litOpacity }]}>
          <Animated.View style={{ height: revealHeight, width: QR, overflow: 'hidden' }}>
            <QrLayer lit />
          </Animated.View>
        </Animated.View>

        {/* beam */}
        <Animated.View style={[styles.beamWrap, { opacity: beamOpacity, transform: [{ translateY: beamY }] }]}>
          <View style={styles.beamGlow} />
          <View style={styles.beamLine} />
        </Animated.View>

        {/* success ring + check */}
        <Animated.View style={[styles.ring, { opacity: ringOpacity, transform: [{ scale: ringScale }] }]} />
        <Animated.View style={[styles.checkWrap, { opacity: checkOpacity, transform: [{ scale: checkScale }] }]}>
          <Svg width={120} height={120} viewBox="0 0 120 120">
            <Path d="M32 62 L52 82 L90 40" stroke={C.green} strokeWidth={9} fill="none"
              strokeLinecap="round" strokeLinejoin="round" />
          </Svg>
        </Animated.View>
      </Animated.View>

      {/* ── logo lockup ── */}
      <Animated.View style={[styles.lockup, { opacity: lockOpacity, transform: [{ translateY: lockY }] }]}>
        <View style={styles.mark}>
          <View style={styles.ticket} />
          <View style={styles.ticketDash} />
        </View>
        <Text style={styles.word}>
          <Text style={{ color: '#fff' }}>Am</Text>
          <Text style={{ color: '#F7B9BC' }}>Bilet</Text>
          <Text style={{ color: '#fff' }}> Scan</Text>
        </Text>
        <Text style={styles.tag}>Scanare &amp; Vânzare Bilete</Text>
      </Animated.View>

      {/* ── loading bar + footer ── */}
      <View style={styles.foot}>
        <View style={styles.loadTrack}>
          <Animated.View style={[styles.loadFill, { width: loadWidth }]} />
        </View>
        <Animated.Text style={[styles.powered, { opacity: poweredOpacity }]}>Powered by AmBilet</Animated.Text>
      </View>
    </View>
  );
}

const BRK = 44, BW = 4;
const styles = StyleSheet.create({
  root: { flex: 1, backgroundColor: '#5c0d13', alignItems: 'center', justifyContent: 'center', overflow: 'hidden' },
  glow: { position: 'absolute', top: '10%', alignSelf: 'center' },

  zone: { width: ZONE, height: ZONE, alignItems: 'center', justifyContent: 'center' },
  bracket: { position: 'absolute', width: BRK, height: BRK, borderColor: '#fff' },
  b1: { top: 0, left: 0, borderTopWidth: BW, borderLeftWidth: BW, borderTopLeftRadius: 12 },
  b2: { top: 0, right: 0, borderTopWidth: BW, borderRightWidth: BW, borderTopRightRadius: 12 },
  b3: { bottom: 0, left: 0, borderBottomWidth: BW, borderLeftWidth: BW, borderBottomLeftRadius: 12 },
  b4: { bottom: 0, right: 0, borderBottomWidth: BW, borderRightWidth: BW, borderBottomRightRadius: 12 },

  qrAbs: { position: 'absolute', width: QR, height: QR, alignItems: 'center', justifyContent: 'flex-start' },

  beamWrap: { position: 'absolute', top: (ZONE - QR) / 2 - 9, left: -6, right: -6, height: 40, alignItems: 'stretch' },
  beamLine: {
    position: 'absolute', top: 0, left: 0, right: 0, height: 3, borderRadius: 3, backgroundColor: C.beam,
    shadowColor: C.redBright, shadowOpacity: 0.9, shadowRadius: 12, shadowOffset: { width: 0, height: 0 },
    ...(Platform.OS === 'android' ? { elevation: 8 } : null),
  },
  beamGlow: { position: 'absolute', top: 2, left: 8, right: 8, height: 46, backgroundColor: 'rgba(255,255,255,0.18)', borderRadius: 20 },

  ring: { position: 'absolute', width: 132, height: 132, borderRadius: 66, borderWidth: 4, borderColor: C.green },
  checkWrap: { position: 'absolute', width: 120, height: 120, alignItems: 'center', justifyContent: 'center' },

  lockup: { position: 'absolute', alignItems: 'center' },
  mark: {
    width: 84, height: 84, borderRadius: 22, backgroundColor: '#fff', alignItems: 'center', justifyContent: 'center',
    marginBottom: 14, shadowColor: '#000', shadowOpacity: 0.35, shadowRadius: 18, shadowOffset: { width: 0, height: 12 }, elevation: 8,
  },
  ticket: { width: 50, height: 38, borderWidth: 4, borderColor: C.red, borderRadius: 8 },
  ticketDash: { position: 'absolute', width: 0, height: 46, borderLeftWidth: 3, borderStyle: 'dashed', borderColor: C.red },
  word: { fontSize: 30, fontWeight: '800', letterSpacing: -1 },
  tag: { color: '#f2cccc', fontSize: 13, marginTop: 6 },

  foot: { position: 'absolute', bottom: 60, alignItems: 'center' },
  loadTrack: { width: 150, height: 5, borderRadius: 3, backgroundColor: 'rgba(255,255,255,0.16)', overflow: 'hidden' },
  loadFill: { height: 5, borderRadius: 3, backgroundColor: '#fff' },
  powered: { color: '#e0aeae', fontSize: 12, letterSpacing: 0.4, marginTop: 16 },
});
