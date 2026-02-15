import React, { useEffect, useRef } from 'react';
import {
  View,
  Text,
  StyleSheet,
  Animated,
  Dimensions,
} from 'react-native';
import Svg, { Rect, Path, Defs, LinearGradient, Stop } from 'react-native-svg';
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
            <Svg viewBox="0 0 64 64" width={80} height={80}>
              <Defs>
                <LinearGradient id="splashGrad" x1="0" y1="0" x2="64" y2="64">
                  <Stop offset="0" stopColor="#8B5CF6" />
                  <Stop offset="1" stopColor="#6366F1" />
                </LinearGradient>
              </Defs>
              <Rect width="64" height="64" rx="16" fill="url(#splashGrad)" />
              <Path
                d="M16 24h32M16 32h24M16 40h16"
                stroke="white"
                strokeWidth="4"
                strokeLinecap="round"
              />
            </Svg>
          </View>
        </Animated.View>

        {/* Brand text */}
        <Animated.View style={{ opacity: textOpacity }}>
          <Text style={styles.brandText}>Tixello</Text>
          <Text style={styles.tagline}>Event Staff Platform</Text>
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
        <Text style={styles.footerText}>Powered by Tixello</Text>
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
    backgroundColor: colors.purpleGlow,
  },
  iconWrap: {
    width: 80,
    height: 80,
    alignItems: 'center',
    justifyContent: 'center',
  },
  brandText: {
    fontSize: 36,
    fontWeight: '700',
    color: colors.textPrimary,
    textAlign: 'center',
    marginBottom: 8,
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
    backgroundColor: colors.purple,
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
