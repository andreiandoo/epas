import React, { useEffect, useRef } from 'react';
import { View, Text, TouchableOpacity, StyleSheet, Animated } from 'react-native';
import Svg, { Rect, Path, Defs, LinearGradient, Stop } from 'react-native-svg';
import { colors } from '../theme/colors';
import { useApp } from '../context/AppContext';

function TixelloLogo() {
  return (
    <Svg width={32} height={32} viewBox="0 0 32 32">
      <Defs>
        <LinearGradient id="logoGrad" x1="0" y1="0" x2="1" y2="1">
          <Stop offset="0" stopColor="#8B5CF6" />
          <Stop offset="1" stopColor="#6366F1" />
        </LinearGradient>
      </Defs>
      <Rect x={0} y={0} width={32} height={32} rx={8} fill="url(#logoGrad)" />
      <Path
        d="M8 12 L16 8 L24 12 L24 20 L16 24 L8 20 Z"
        stroke="#FFFFFF"
        strokeWidth={1.5}
        fill="none"
        strokeLinejoin="round"
      />
      <Path
        d="M16 8 L16 24 M8 12 L24 12 M8 20 L24 20"
        stroke="#FFFFFF"
        strokeWidth={1}
        opacity={0.6}
      />
    </Svg>
  );
}

function PulsingDot({ color }) {
  const pulseAnim = useRef(new Animated.Value(1)).current;

  useEffect(() => {
    const animation = Animated.loop(
      Animated.sequence([
        Animated.timing(pulseAnim, {
          toValue: 0.3,
          duration: 1000,
          useNativeDriver: true,
        }),
        Animated.timing(pulseAnim, {
          toValue: 1,
          duration: 1000,
          useNativeDriver: true,
        }),
      ])
    );
    animation.start();
    return () => animation.stop();
  }, [pulseAnim]);

  return (
    <Animated.View
      style={[
        styles.pulsingDot,
        { backgroundColor: color, opacity: pulseAnim },
      ]}
    />
  );
}

export default function Header({ onNotificationPress }) {
  const { isOnline, notifications } = useApp();
  const unreadCount = notifications.filter(n => n.unread).length;

  return (
    <View style={styles.container}>
      <View style={styles.inner}>
        {/* Left: Logo + Name */}
        <View style={styles.left}>
          <TixelloLogo />
          <Text style={styles.brandText}>Tixello</Text>
        </View>

        {/* Right: Connection Status + Notifications */}
        <View style={styles.right}>
          {/* Connection status pill */}
          <View
            style={[
              styles.statusPill,
              isOnline ? styles.statusPillOnline : styles.statusPillOffline,
            ]}
          >
            <PulsingDot color={isOnline ? colors.green : colors.red} />
            <Text
              style={[
                styles.statusText,
                { color: isOnline ? colors.green : colors.red },
              ]}
            >
              {isOnline ? 'Live' : 'Offline'}
            </Text>
          </View>

          {/* Notification bell */}
          <TouchableOpacity
            style={styles.bellButton}
            onPress={onNotificationPress}
            activeOpacity={0.7}
          >
            <Svg width={22} height={22} viewBox="0 0 24 24" fill="none">
              <Path
                d="M18 8A6 6 0 1 0 6 8c0 7-3 9-3 9h18s-3-2-3-9zM13.73 21a2 2 0 0 1-3.46 0"
                stroke={colors.textSecondary}
                strokeWidth={1.8}
                strokeLinecap="round"
                strokeLinejoin="round"
              />
            </Svg>
            {unreadCount > 0 && (
              <View style={styles.badge}>
                <Text style={styles.badgeText}>
                  {unreadCount > 9 ? '9+' : unreadCount}
                </Text>
              </View>
            )}
          </TouchableOpacity>
        </View>
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    backgroundColor: 'rgba(10, 10, 15, 0.95)',
    borderBottomWidth: 1,
    borderBottomColor: colors.border,
    paddingTop: 48,
    paddingBottom: 12,
    paddingHorizontal: 16,
    zIndex: 100,
  },
  inner: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
  },
  left: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
  },
  brandText: {
    fontSize: 20,
    fontWeight: '700',
    color: colors.textPrimary,
    letterSpacing: 0.5,
  },
  right: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
  },
  statusPill: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingHorizontal: 10,
    paddingVertical: 5,
    borderRadius: 20,
    gap: 6,
  },
  statusPillOnline: {
    backgroundColor: colors.greenLight,
    borderWidth: 1,
    borderColor: colors.greenBorder,
  },
  statusPillOffline: {
    backgroundColor: colors.redLight,
    borderWidth: 1,
    borderColor: colors.redBorder,
  },
  statusText: {
    fontSize: 12,
    fontWeight: '600',
    letterSpacing: 0.3,
  },
  pulsingDot: {
    width: 7,
    height: 7,
    borderRadius: 3.5,
  },
  bellButton: {
    width: 40,
    height: 40,
    borderRadius: 20,
    backgroundColor: colors.surface,
    borderWidth: 1,
    borderColor: colors.border,
    alignItems: 'center',
    justifyContent: 'center',
  },
  badge: {
    position: 'absolute',
    top: 2,
    right: 2,
    backgroundColor: colors.red,
    borderRadius: 9,
    minWidth: 18,
    height: 18,
    alignItems: 'center',
    justifyContent: 'center',
    paddingHorizontal: 4,
    borderWidth: 2,
    borderColor: colors.background,
  },
  badgeText: {
    color: colors.white,
    fontSize: 10,
    fontWeight: '700',
    lineHeight: 12,
  },
});
