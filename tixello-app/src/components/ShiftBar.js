import React, { useState, useEffect } from 'react';
import { View, Text, TouchableOpacity, StyleSheet } from 'react-native';
import Svg, { Path, Circle } from 'react-native-svg';
import { colors } from '../theme/colors';
import { useApp } from '../context/AppContext';
import { formatCurrency } from '../utils/formatCurrency';

function ClockIcon() {
  return (
    <Svg width={16} height={16} viewBox="0 0 24 24" fill="none">
      <Circle
        cx={12}
        cy={12}
        r={10}
        stroke={colors.textTertiary}
        strokeWidth={1.8}
      />
      <Path
        d="M12 6v6l4 2"
        stroke={colors.textTertiary}
        strokeWidth={1.8}
        strokeLinecap="round"
        strokeLinejoin="round"
      />
    </Svg>
  );
}

function PauseIcon() {
  return (
    <Svg width={16} height={16} viewBox="0 0 24 24" fill="none">
      <Path
        d="M6 4h4v16H6zM14 4h4v16h-4z"
        fill={colors.background}
      />
    </Svg>
  );
}

function PlayIcon() {
  return (
    <Svg width={16} height={16} viewBox="0 0 24 24" fill="none">
      <Path
        d="M5 3l14 9-14 9V3z"
        fill={colors.background}
      />
    </Svg>
  );
}

function AlertIcon() {
  return (
    <Svg width={16} height={16} viewBox="0 0 24 24" fill="none">
      <Path
        d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"
        stroke={colors.white}
        strokeWidth={1.8}
        strokeLinecap="round"
        strokeLinejoin="round"
      />
      <Path
        d="M12 9v4M12 17h.01"
        stroke={colors.white}
        strokeWidth={1.8}
        strokeLinecap="round"
        strokeLinejoin="round"
      />
    </Svg>
  );
}

function CoinIcon() {
  return (
    <Svg width={14} height={14} viewBox="0 0 24 24" fill="none">
      <Circle cx={12} cy={12} r={9} stroke={colors.green} strokeWidth={1.8} />
      <Path
        d="M9 12h6M12 9v6"
        stroke={colors.green}
        strokeWidth={1.8}
        strokeLinecap="round"
      />
    </Svg>
  );
}

function formatShiftDuration(startTime) {
  if (!startTime) return '00:00:00';

  const now = new Date();
  const start = new Date(startTime);
  const diffMs = Math.max(0, now - start);

  const hours = Math.floor(diffMs / (1000 * 60 * 60));
  const minutes = Math.floor((diffMs % (1000 * 60 * 60)) / (1000 * 60));
  const seconds = Math.floor((diffMs % (1000 * 60)) / 1000);

  const pad = (n) => String(n).padStart(2, '0');
  return `${pad(hours)}:${pad(minutes)}:${pad(seconds)}`;
}

export default function ShiftBar({ onEmergencyPress }) {
  const {
    shiftStartTime,
    isShiftPaused,
    setIsShiftPaused,
    cashTurnover,
    cardTurnover,
  } = useApp();

  const [duration, setDuration] = useState('00:00:00');

  useEffect(() => {
    if (!shiftStartTime || isShiftPaused) return;

    const interval = setInterval(() => {
      setDuration(formatShiftDuration(shiftStartTime));
    }, 1000);

    // Set immediately
    setDuration(formatShiftDuration(shiftStartTime));

    return () => clearInterval(interval);
  }, [shiftStartTime, isShiftPaused]);

  if (!shiftStartTime) return null;

  const handlePauseResume = () => {
    setIsShiftPaused(!isShiftPaused);
  };

  return (
    <View style={[styles.container, isShiftPaused && styles.containerPaused]}>
      <View style={styles.inner}>
        {/* Left section: Shift info */}
        <View style={styles.left}>
          <View style={styles.shiftInfo}>
            <ClockIcon />
            <Text style={styles.durationText}>{duration}</Text>
            {isShiftPaused && (
              <View style={styles.pausedBadge}>
                <Text style={styles.pausedBadgeText}>PAUZĂ</Text>
              </View>
            )}
          </View>

          <View style={styles.turnoverInfo}>
            <CoinIcon />
            <Text style={styles.turnoverText}>
              {formatCurrency(cashTurnover + cardTurnover)}
            </Text>
          </View>
        </View>

        {/* Right section: Action buttons */}
        <View style={styles.right}>
          {/* Pause / Resume button */}
          <TouchableOpacity
            style={[
              styles.actionButton,
              isShiftPaused ? styles.resumeButton : styles.pauseButton,
            ]}
            onPress={handlePauseResume}
            activeOpacity={0.7}
          >
            {isShiftPaused ? <PlayIcon /> : <PauseIcon />}
            <Text
              style={[
                styles.actionButtonText,
                isShiftPaused ? styles.resumeButtonText : styles.pauseButtonText,
              ]}
            >
              {isShiftPaused ? 'Continuă' : 'Pauză'}
            </Text>
          </TouchableOpacity>

          {/* Emergency button */}
          <TouchableOpacity
            style={[styles.actionButton, styles.emergencyButton]}
            onPress={onEmergencyPress}
            activeOpacity={0.7}
          >
            <AlertIcon />
          </TouchableOpacity>
        </View>
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    backgroundColor: colors.surface,
    borderBottomWidth: 1,
    borderBottomColor: colors.border,
    paddingHorizontal: 16,
    paddingVertical: 10,
  },
  containerPaused: {
    backgroundColor: colors.amberBg,
    borderBottomColor: colors.amberBorder,
  },
  inner: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
  },
  left: {
    flex: 1,
    marginRight: 12,
    gap: 4,
  },
  shiftInfo: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
  },
  durationText: {
    fontSize: 15,
    fontWeight: '700',
    color: colors.textPrimary,
    fontVariant: ['tabular-nums'],
    letterSpacing: 0.5,
  },
  pausedBadge: {
    backgroundColor: colors.amber,
    paddingHorizontal: 6,
    paddingVertical: 2,
    borderRadius: 4,
    marginLeft: 4,
  },
  pausedBadgeText: {
    fontSize: 9,
    fontWeight: '800',
    color: colors.background,
    letterSpacing: 0.8,
  },
  turnoverInfo: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 5,
  },
  turnoverText: {
    fontSize: 12,
    fontWeight: '500',
    color: colors.green,
  },
  right: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
  },
  actionButton: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    paddingVertical: 8,
    paddingHorizontal: 12,
    borderRadius: 8,
    gap: 5,
  },
  pauseButton: {
    backgroundColor: colors.amber,
  },
  resumeButton: {
    backgroundColor: colors.green,
  },
  actionButtonText: {
    fontSize: 12,
    fontWeight: '700',
    letterSpacing: 0.3,
  },
  pauseButtonText: {
    color: colors.background,
  },
  resumeButtonText: {
    color: colors.background,
  },
  emergencyButton: {
    backgroundColor: colors.red,
    paddingHorizontal: 10,
  },
});
