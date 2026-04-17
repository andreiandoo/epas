import React, { useEffect, useRef, useState } from 'react';
import { View, Text, TouchableOpacity, StyleSheet, Animated, Image } from 'react-native';
import Svg, { Path } from 'react-native-svg';
import { colors } from '../theme/colors';
import { useApp } from '../context/AppContext';
import { useAuth } from '../context/AuthContext';
import OrganizerSwitcherModal from './modals/OrganizerSwitcherModal';

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

export default function Header({ onNotificationPress, onOrganizerSwitched }) {
  const { isOnline, notifications } = useApp();
  const { user, hasMultipleOrganizers } = useAuth();
  const unreadCount = notifications.filter(n => n.unread).length;
  const [switcherOpen, setSwitcherOpen] = useState(false);

  return (
    <View style={styles.container}>
      <View style={styles.inner}>
        {/* Left: Logo + (optional) organizer switcher */}
        <View style={styles.left}>
          <Image
            source={require('../../assets/logo-header.png')}
            style={styles.logoImage}
            resizeMode="contain"
          />

          {hasMultipleOrganizers && user ? (
            <TouchableOpacity
              style={styles.orgSwitcher}
              onPress={() => setSwitcherOpen(true)}
              activeOpacity={0.7}
            >
              <Text style={styles.orgSwitcherName} numberOfLines={1}>{user.name}</Text>
              <Svg width={14} height={14} viewBox="0 0 24 24" fill="none">
                <Path d="M6 9l6 6 6-6" stroke={colors.textSecondary} strokeWidth={2} strokeLinecap="round" strokeLinejoin="round" />
              </Svg>
            </TouchableOpacity>
          ) : null}
        </View>

        <OrganizerSwitcherModal
          visible={switcherOpen}
          onClose={() => setSwitcherOpen(false)}
          onSwitched={onOrganizerSwitched}
        />

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
    flex: 1,
    gap: 8,
  },
  logoImage: {
    width: 120,
    height: 54,
  },
  orgSwitcher: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 4,
    paddingHorizontal: 10,
    paddingVertical: 6,
    borderRadius: 12,
    backgroundColor: colors.surface,
    borderWidth: 1,
    borderColor: colors.border,
    maxWidth: 140,
  },
  orgSwitcherName: {
    fontSize: 13,
    fontWeight: '600',
    color: colors.textPrimary,
    flexShrink: 1,
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
