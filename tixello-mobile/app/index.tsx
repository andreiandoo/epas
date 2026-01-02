import { useEffect, useState } from 'react';
import { View, StyleSheet, Animated, Text } from 'react-native';
import { router } from 'expo-router';
import { LinearGradient } from 'expo-linear-gradient';
import { useAuthStore } from '../src/stores/authStore';
import { colors, typography } from '../src/utils/theme';

export default function SplashScreen() {
  const { isAuthenticated, isLoading } = useAuthStore();
  const [fadeAnim] = useState(new Animated.Value(0));
  const [scaleAnim] = useState(new Animated.Value(0.8));
  const [progressAnim] = useState(new Animated.Value(0));

  useEffect(() => {
    // Animate splash screen elements
    Animated.parallel([
      Animated.timing(fadeAnim, {
        toValue: 1,
        duration: 500,
        useNativeDriver: true,
      }),
      Animated.spring(scaleAnim, {
        toValue: 1,
        friction: 4,
        useNativeDriver: true,
      }),
    ]).start();

    // Animate progress bar
    Animated.timing(progressAnim, {
      toValue: 1,
      duration: 2000,
      useNativeDriver: false,
    }).start();

    // Navigate after animation
    const timer = setTimeout(() => {
      if (!isLoading) {
        if (isAuthenticated) {
          router.replace('/(main)/(tabs)/dashboard');
        } else {
          router.replace('/(auth)/login');
        }
      }
    }, 2500);

    return () => clearTimeout(timer);
  }, [isLoading, isAuthenticated]);

  const progressWidth = progressAnim.interpolate({
    inputRange: [0, 1],
    outputRange: ['0%', '100%'],
  });

  return (
    <View style={styles.container}>
      <LinearGradient
        colors={[colors.background, colors.backgroundLight]}
        style={styles.gradient}
      >
        <Animated.View
          style={[
            styles.content,
            {
              opacity: fadeAnim,
              transform: [{ scale: scaleAnim }],
            },
          ]}
        >
          {/* Logo */}
          <View style={styles.logoContainer}>
            <LinearGradient
              colors={[colors.primary, colors.primaryDark]}
              style={styles.logoBackground}
            >
              <View style={styles.logoLines}>
                <View style={[styles.logoLine, styles.logoLine1]} />
                <View style={[styles.logoLine, styles.logoLine2]} />
                <View style={[styles.logoLine, styles.logoLine3]} />
              </View>
            </LinearGradient>
          </View>

          {/* Brand Name */}
          <Text style={styles.brandName}>Tixello</Text>
          <Text style={styles.tagline}>Event Staff App</Text>

          {/* Progress Bar */}
          <View style={styles.progressContainer}>
            <View style={styles.progressTrack}>
              <Animated.View
                style={[
                  styles.progressBar,
                  { width: progressWidth },
                ]}
              />
            </View>
          </View>
        </Animated.View>

        {/* Footer */}
        <Text style={styles.footer}>
          Made with <Text style={styles.heart}>♥</Text> for events
        </Text>
      </LinearGradient>
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
  },
  gradient: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    padding: 40,
  },
  content: {
    alignItems: 'center',
  },
  logoContainer: {
    marginBottom: 20,
    shadowColor: colors.primary,
    shadowOffset: { width: 0, height: 0 },
    shadowOpacity: 0.5,
    shadowRadius: 30,
    elevation: 10,
  },
  logoBackground: {
    width: 80,
    height: 80,
    borderRadius: 20,
    justifyContent: 'center',
    alignItems: 'center',
  },
  logoLines: {
    width: 48,
    height: 32,
    justifyContent: 'space-between',
  },
  logoLine: {
    height: 4,
    backgroundColor: '#fff',
    borderRadius: 2,
  },
  logoLine1: {
    width: '100%',
  },
  logoLine2: {
    width: '75%',
  },
  logoLine3: {
    width: '50%',
  },
  brandName: {
    fontSize: 36,
    fontWeight: '700',
    color: colors.textPrimary,
    letterSpacing: -1,
  },
  tagline: {
    fontSize: typography.fontSize.md,
    color: colors.textMuted,
    marginTop: 8,
  },
  progressContainer: {
    marginTop: 40,
    width: 120,
  },
  progressTrack: {
    height: 3,
    backgroundColor: 'rgba(255, 255, 255, 0.1)',
    borderRadius: 2,
    overflow: 'hidden',
  },
  progressBar: {
    height: '100%',
    backgroundColor: colors.primary,
    borderRadius: 2,
  },
  footer: {
    position: 'absolute',
    bottom: 40,
    color: colors.textDisabled,
    fontSize: typography.fontSize.sm,
  },
  heart: {
    color: colors.error,
  },
});
