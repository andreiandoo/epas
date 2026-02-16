import React, { useEffect, useRef } from 'react';
import { View, Text, TouchableOpacity, StyleSheet, Animated } from 'react-native';
import Svg, { Path, Defs, LinearGradient, Stop, Rect, Line } from 'react-native-svg';
import { colors } from '../theme/colors';
import { useApp } from '../context/AppContext';

function AmBiletLogo() {
  return (
    <Svg width={28} height={28} viewBox="0 0 48 48" fill="none">
      <Defs>
        <LinearGradient id="logoGrad" x1="6" y1="10" x2="42" y2="38">
          <Stop stopColor="#A51C30" />
          <Stop offset="1" stopColor="#C41E3A" />
        </LinearGradient>
      </Defs>
      <Path d="M8 13C8 10.79 9.79 9 12 9H36C38.21 9 40 10.79 40 13V19C37.79 19 36 20.79 36 23V25C36 27.21 37.79 29 40 29V35C40 37.21 38.21 39 36 39H12C9.79 39 8 37.21 8 35V29C10.21 29 12 27.21 12 25V23C12 20.79 10.21 19 8 19V13Z" fill="url(#logoGrad)" />
      <Line x1="17" y1="15" x2="31" y2="15" stroke="white" strokeOpacity="0.25" strokeWidth="1.5" strokeLinecap="round" />
      <Line x1="15" y1="19" x2="33" y2="19" stroke="white" strokeOpacity="0.35" strokeWidth="1.5" strokeLinecap="round" />
      <Rect x="20" y="27" width="8" height="8" rx="1.5" fill="white" />
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
          <AmBiletLogo />
          <Text style={styles.logoTextAm}>Am</Text>
          <Text style={styles.logoTextBilet}>Bilet</Text>
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
    gap: 4,
  },
  logoTextAm: {
    fontSize: 18,
    fontWeight: '800',
    color: 'rgba(255,255,255,0.85)',
    marginLeft: 6,
  },
  logoTextBilet: {
    fontSize: 18,
    fontWeight: '800',
    color: '#C41E3A',
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
