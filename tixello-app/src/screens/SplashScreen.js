import React, { useEffect, useRef } from 'react';
import {
  View,
  Text,
  StyleSheet,
  Animated,
  Dimensions,
} from 'react-native';
import Svg, { Path, Defs, LinearGradient, Stop, Rect, Line } from 'react-native-svg';
import { colors } from '../theme/colors';

const { width: SCREEN_WIDTH } = Dimensions.get('window');
const LOADING_BAR_WIDTH = SCREEN_WIDTH * 0.6;

export default function SplashScreen({ onFinish }) {
  const loadingProgress = useRef(new Animated.Value(0)).current;
  const glowOpacity = useRef(new Animated.Value(0.4)).current;
  const logoScale = useRef(new Animated.Value(0.8)).current;
  const logoOpacity = useRef(new Animated.Value(0)).current;
  const textOpacity = useRef(new Animated.Value(0)).current;

  useEffect(() => {
    // Logo entrance animation
    Animated.parallel([
      Animated.timing(logoOpacity, {
        toValue: 1,
        duration: 600,
        useNativeDriver: true,
      }),
      Animated.spring(logoScale, {
        toValue: 1,
        friction: 8,
        tension: 40,
        useNativeDriver: true,
      }),
    ]).start();

    // Text fade in after logo
    Animated.timing(textOpacity, {
      toValue: 1,
      duration: 500,
      delay: 400,
      useNativeDriver: true,
    }).start();

    // Loading bar fill over 2 seconds
    Animated.timing(loadingProgress, {
      toValue: 1,
      duration: 2000,
      delay: 300,
      useNativeDriver: false,
    }).start();

    // Glow pulse loop
    Animated.loop(
      Animated.sequence([
        Animated.timing(glowOpacity, {
          toValue: 0.8,
          duration: 800,
          useNativeDriver: true,
        }),
        Animated.timing(glowOpacity, {
          toValue: 0.3,
          duration: 800,
          useNativeDriver: true,
        }),
      ])
    ).start();

    // Call onFinish after 2.5s
    const timer = setTimeout(() => {
      if (onFinish) onFinish();
    }, 2500);

    return () => clearTimeout(timer);
  }, []);

  const loadingBarWidth = loadingProgress.interpolate({
    inputRange: [0, 1],
    outputRange: [0, LOADING_BAR_WIDTH],
  });

  return (
    <View style={styles.container}>
      <View style={styles.content}>
        {/* Logo with glow effect */}
        <Animated.View
          style={[
            styles.logoContainer,
            {
              opacity: logoOpacity,
              transform: [{ scale: logoScale }],
            },
          ]}
        >
          {/* Glow behind icon */}
          <Animated.View
            style={[
              styles.glow,
              { opacity: glowOpacity },
            ]}
          />
          <View style={styles.iconWrap}>
            <Svg width={64} height={64} viewBox="0 0 48 48" fill="none">
              <Defs>
                <LinearGradient id="splashGrad" x1="6" y1="10" x2="42" y2="38">
                  <Stop stopColor="#A51C30" />
                  <Stop offset="1" stopColor="#C41E3A" />
                </LinearGradient>
              </Defs>
              <Path d="M8 13C8 10.79 9.79 9 12 9H36C38.21 9 40 10.79 40 13V19C37.79 19 36 20.79 36 23V25C36 27.21 37.79 29 40 29V35C40 37.21 38.21 39 36 39H12C9.79 39 8 37.21 8 35V29C10.21 29 12 27.21 12 25V23C12 20.79 10.21 19 8 19V13Z" fill="url(#splashGrad)" />
              <Line x1="17" y1="15" x2="31" y2="15" stroke="white" strokeOpacity="0.25" strokeWidth="1.5" strokeLinecap="round" />
              <Line x1="15" y1="19" x2="33" y2="19" stroke="white" strokeOpacity="0.35" strokeWidth="1.5" strokeLinecap="round" />
              <Rect x="20" y="27" width="8" height="8" rx="1.5" fill="white" />
            </Svg>
          </View>
        </Animated.View>

        {/* Brand text */}
        <Animated.View style={[styles.brandRow, { opacity: textOpacity }]}>
          <Text style={styles.brandTextAm}>Am</Text>
          <Text style={styles.brandTextBilet}>Bilet</Text>
        </Animated.View>
        <Animated.View style={{ opacity: textOpacity }}>
          <Text style={styles.tagline}>Scanare & Vânzare Bilete</Text>
        </Animated.View>

        {/* Loading bar */}
        <View style={styles.loaderContainer}>
          <View style={styles.loaderTrack}>
            <Animated.View
              style={[
                styles.loaderBar,
                { width: loadingBarWidth },
              ]}
            />
          </View>
        </View>
      </View>

      {/* Footer */}
      <View style={styles.footer}>
        <Text style={styles.footerText}>Powered by AmBilet</Text>
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: colors.background,
    alignItems: 'center',
    justifyContent: 'center',
  },
  content: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'center',
  },
  logoContainer: {
    alignItems: 'center',
    justifyContent: 'center',
    marginBottom: 24,
  },
  glow: {
    position: 'absolute',
    width: 120,
    height: 120,
    borderRadius: 60,
    backgroundColor: 'rgba(196,30,58,0.4)',
  },
  iconWrap: {
    width: 80,
    height: 80,
    alignItems: 'center',
    justifyContent: 'center',
  },
  brandRow: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    marginBottom: 8,
  },
  brandTextAm: {
    fontSize: 36,
    fontWeight: '700',
    color: 'rgba(255,255,255,0.85)',
    textAlign: 'center',
  },
  brandTextBilet: {
    fontSize: 36,
    fontWeight: '700',
    color: '#C41E3A',
    textAlign: 'center',
  },
  tagline: {
    fontSize: 16,
    color: colors.textSecondary,
    textAlign: 'center',
    marginBottom: 48,
  },
  loaderContainer: {
    width: LOADING_BAR_WIDTH,
    alignItems: 'center',
  },
  loaderTrack: {
    width: LOADING_BAR_WIDTH,
    height: 4,
    borderRadius: 2,
    backgroundColor: 'rgba(255,255,255,0.08)',
    overflow: 'hidden',
  },
  loaderBar: {
    height: 4,
    borderRadius: 2,
    backgroundColor: '#C41E3A',
  },
  footer: {
    paddingBottom: 48,
    alignItems: 'center',
  },
  footerText: {
    fontSize: 13,
    color: colors.textQuaternary,
  },
});
