import React, { useState, useEffect, useRef } from 'react';
import {
  View,
  Text,
  TouchableOpacity,
  Modal,
  StyleSheet,
  Animated,
  Dimensions,
} from 'react-native';
import Svg, { Path } from 'react-native-svg';
import { colors } from '../../theme/colors';

const { height: SCREEN_HEIGHT } = Dimensions.get('window');

const EMERGENCY_OPTIONS = [
  {
    id: 'medical',
    label: 'Urgență Medicală',
    severity: 'high',
    icon: (color) => (
      <Svg width={24} height={24} viewBox="0 0 24 24" fill="none">
        <Path d="M12 8v4m0 4h.01M4.93 4.93l14.14 14.14M12 2a10 10 0 100 20 10 10 0 000-20z" stroke={color} strokeWidth={1.8} strokeLinecap="round" strokeLinejoin="round" />
      </Svg>
    ),
  },
  {
    id: 'fire',
    label: 'Incendiu / Evacuare',
    severity: 'high',
    icon: (color) => (
      <Svg width={24} height={24} viewBox="0 0 24 24" fill="none">
        <Path d="M12 2c.5 3.5-1.5 6-1.5 6 2 1.5 3.5 3.5 3.5 6.5 0 3-2.5 5.5-5.5 5.5S3 17.5 3 14.5c0-2 .5-3.5 1.5-5C5.5 8 7 6 7 3.5c0 0 2.5 1 3 3 .5-2 2-4.5 2-4.5z" stroke={color} strokeWidth={1.8} strokeLinecap="round" strokeLinejoin="round" />
      </Svg>
    ),
  },
  {
    id: 'security',
    label: 'Problemă de Securitate',
    severity: 'high',
    icon: (color) => (
      <Svg width={24} height={24} viewBox="0 0 24 24" fill="none">
        <Path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" stroke={color} strokeWidth={1.8} strokeLinecap="round" strokeLinejoin="round" />
      </Svg>
    ),
  },
  {
    id: 'technical',
    label: 'Problemă Tehnică',
    severity: 'medium',
    icon: (color) => (
      <Svg width={24} height={24} viewBox="0 0 24 24" fill="none">
        <Path d="M14.7 6.3a1 1 0 000 1.4l1.6 1.6a1 1 0 001.4 0l3.77-3.77a6 6 0 01-7.94 7.94l-6.91 6.91a2.12 2.12 0 01-3-3l6.91-6.91a6 6 0 017.94-7.94l-3.76 3.76z" stroke={color} strokeWidth={1.8} strokeLinecap="round" strokeLinejoin="round" />
      </Svg>
    ),
  },
  {
    id: 'crowd',
    label: 'Control Mulțime',
    severity: 'medium',
    icon: (color) => (
      <Svg width={24} height={24} viewBox="0 0 24 24" fill="none">
        <Path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2M9 11a4 4 0 100-8 4 4 0 000 8zM23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75" stroke={color} strokeWidth={1.8} strokeLinecap="round" strokeLinejoin="round" />
      </Svg>
    ),
  },
  {
    id: 'equipment',
    label: 'Defecțiune Echipament',
    severity: 'medium',
    icon: (color) => (
      <Svg width={24} height={24} viewBox="0 0 24 24" fill="none">
        <Path d="M12.22 2h-.44a2 2 0 00-2 2v.18a2 2 0 01-1 1.73l-.43.25a2 2 0 01-2 0l-.15-.08a2 2 0 00-2.73.73l-.22.38a2 2 0 00.73 2.73l.15.1a2 2 0 011 1.72v.51a2 2 0 01-1 1.74l-.15.09a2 2 0 00-.73 2.73l.22.38a2 2 0 002.73.73l.15-.08a2 2 0 012 0l.43.25a2 2 0 011 1.73V20a2 2 0 002 2h.44a2 2 0 002-2v-.18a2 2 0 011-1.73l.43-.25a2 2 0 012 0l.15.08a2 2 0 002.73-.73l.22-.39a2 2 0 00-.73-2.73l-.15-.08a2 2 0 01-1-1.74v-.5a2 2 0 011-1.74l.15-.09a2 2 0 00.73-2.73l-.22-.38a2 2 0 00-2.73-.73l-.15.08a2 2 0 01-2 0l-.43-.25a2 2 0 01-1-1.73V4a2 2 0 00-2-2z" stroke={color} strokeWidth={1.8} strokeLinecap="round" strokeLinejoin="round" />
        <Path d="M12 15a3 3 0 100-6 3 3 0 000 6z" stroke={color} strokeWidth={1.8} strokeLinecap="round" strokeLinejoin="round" />
      </Svg>
    ),
  },
  {
    id: 'weather',
    label: 'Alertă Meteo',
    severity: 'low',
    icon: (color) => (
      <Svg width={24} height={24} viewBox="0 0 24 24" fill="none">
        <Path d="M18 10h-1.26A8 8 0 109 20h9a5 5 0 000-10z" stroke={color} strokeWidth={1.8} strokeLinecap="round" strokeLinejoin="round" />
      </Svg>
    ),
  },
  {
    id: 'other',
    label: 'Altele',
    severity: 'low',
    icon: (color) => (
      <Svg width={24} height={24} viewBox="0 0 24 24" fill="none">
        <Path d="M12 22c5.523 0 10-4.477 10-10S17.523 2 12 2 2 6.477 2 12s4.477 10 10 10zM12 16v-4M12 8h.01" stroke={color} strokeWidth={1.8} strokeLinecap="round" strokeLinejoin="round" />
      </Svg>
    ),
  },
];

const SEVERITY_CONFIGS = {
  high: {
    bg: colors.redBg,
    border: colors.redBorder,
    color: colors.red,
    iconColor: colors.red,
  },
  medium: {
    bg: colors.amberBg,
    border: colors.amberBorder,
    color: colors.amber,
    iconColor: colors.amber,
  },
  low: {
    bg: 'rgba(255,255,255,0.05)',
    border: 'rgba(255,255,255,0.08)',
    color: colors.textSecondary,
    iconColor: colors.textTertiary,
  },
};

function SuccessState() {
  const scaleAnim = useRef(new Animated.Value(0)).current;
  const opacityAnim = useRef(new Animated.Value(0)).current;

  useEffect(() => {
    Animated.parallel([
      Animated.spring(scaleAnim, {
        toValue: 1,
        tension: 50,
        friction: 7,
        useNativeDriver: true,
      }),
      Animated.timing(opacityAnim, {
        toValue: 1,
        duration: 300,
        useNativeDriver: true,
      }),
    ]).start();
  }, [scaleAnim, opacityAnim]);

  return (
    <Animated.View
      style={[
        styles.successState,
        {
          opacity: opacityAnim,
          transform: [{ scale: scaleAnim }],
        },
      ]}
    >
      <View style={styles.successCircle}>
        <Svg width={48} height={48} viewBox="0 0 24 24" fill="none">
          <Path
            d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"
            stroke={colors.green}
            strokeWidth={2}
            strokeLinecap="round"
            strokeLinejoin="round"
          />
        </Svg>
      </View>
      <Text style={styles.successTitle}>Alertă Trimisă!</Text>
      <Text style={styles.successSubtitle}>Supervizorii au fost notificați</Text>
    </Animated.View>
  );
}

export default function EmergencyModal({ visible, onClose }) {
  const [sent, setSent] = useState(false);
  const closeTimerRef = useRef(null);

  useEffect(() => {
    if (!visible) {
      setSent(false);
      if (closeTimerRef.current) {
        clearTimeout(closeTimerRef.current);
        closeTimerRef.current = null;
      }
    }
  }, [visible]);

  const handleReport = (option) => {
    setSent(true);
    closeTimerRef.current = setTimeout(() => {
      if (onClose) {
        onClose();
      }
    }, 2000);
  };

  return (
    <Modal
      visible={visible}
      transparent
      animationType="slide"
      onRequestClose={onClose}
    >
      <View style={styles.overlay}>
        <TouchableOpacity style={styles.overlayTouchable} onPress={onClose} activeOpacity={1} />
        <View style={styles.sheet}>
          {/* Header */}
          <View style={styles.header}>
            <View style={styles.handle} />
            {!sent && (
              <>
                <View style={styles.headerRow}>
                  <View style={styles.titleRow}>
                    <Svg width={22} height={22} viewBox="0 0 24 24" fill="none">
                      <Path
                        d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0zM12 9v4M12 17h.01"
                        stroke={colors.red}
                        strokeWidth={2}
                        strokeLinecap="round"
                        strokeLinejoin="round"
                      />
                    </Svg>
                    <Text style={styles.title}>Raportează Problemă</Text>
                  </View>
                  <TouchableOpacity onPress={onClose} style={styles.closeButton} activeOpacity={0.7}>
                    <Svg width={20} height={20} viewBox="0 0 24 24" fill="none">
                      <Path
                        d="M18 6L6 18M6 6l12 12"
                        stroke={colors.textSecondary}
                        strokeWidth={2}
                        strokeLinecap="round"
                        strokeLinejoin="round"
                      />
                    </Svg>
                  </TouchableOpacity>
                </View>
                <Text style={styles.description}>Selectează o problemă pentru a notifica supervizorii</Text>
              </>
            )}
          </View>

          {/* Content */}
          {sent ? (
            <SuccessState />
          ) : (
            <View style={styles.gridContainer}>
              <View style={styles.grid}>
                {EMERGENCY_OPTIONS.map((option) => {
                  const config = SEVERITY_CONFIGS[option.severity];
                  return (
                    <TouchableOpacity
                      key={option.id}
                      style={[
                        styles.gridItem,
                        {
                          backgroundColor: config.bg,
                          borderColor: config.border,
                        },
                      ]}
                      onPress={() => handleReport(option)}
                      activeOpacity={0.7}
                    >
                      <View style={styles.gridItemIcon}>
                        {option.icon(config.iconColor)}
                      </View>
                      <Text style={[styles.gridItemLabel, { color: config.color }]}>
                        {option.label}
                      </Text>
                    </TouchableOpacity>
                  );
                })}
              </View>
            </View>
          )}
        </View>
      </View>
    </Modal>
  );
}

const styles = StyleSheet.create({
  overlay: {
    flex: 1,
    backgroundColor: 'rgba(0,0,0,0.6)',
    justifyContent: 'flex-end',
  },
  overlayTouchable: {
    flex: 1,
  },
  sheet: {
    backgroundColor: '#15151F',
    borderTopLeftRadius: 24,
    borderTopRightRadius: 24,
    paddingBottom: 34,
  },
  header: {
    alignItems: 'center',
    paddingTop: 12,
    paddingHorizontal: 20,
    paddingBottom: 16,
  },
  handle: {
    width: 40,
    height: 4,
    borderRadius: 2,
    backgroundColor: 'rgba(255,255,255,0.15)',
    marginBottom: 16,
  },
  headerRow: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    width: '100%',
    marginBottom: 8,
  },
  titleRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
  },
  title: {
    fontSize: 20,
    fontWeight: '700',
    color: colors.textPrimary,
    letterSpacing: 0.3,
  },
  description: {
    fontSize: 13,
    color: colors.textTertiary,
    alignSelf: 'flex-start',
  },
  closeButton: {
    width: 36,
    height: 36,
    borderRadius: 18,
    backgroundColor: colors.surface,
    borderWidth: 1,
    borderColor: colors.border,
    alignItems: 'center',
    justifyContent: 'center',
  },
  gridContainer: {
    paddingHorizontal: 20,
    paddingTop: 8,
    paddingBottom: 12,
  },
  grid: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 10,
  },
  gridItem: {
    width: '48%',
    flexGrow: 1,
    borderRadius: 14,
    borderWidth: 1,
    paddingVertical: 20,
    paddingHorizontal: 14,
    alignItems: 'center',
    justifyContent: 'center',
    gap: 10,
  },
  gridItemIcon: {
    width: 40,
    height: 40,
    borderRadius: 20,
    backgroundColor: 'rgba(0,0,0,0.2)',
    alignItems: 'center',
    justifyContent: 'center',
  },
  gridItemLabel: {
    fontSize: 12,
    fontWeight: '600',
    textAlign: 'center',
    letterSpacing: 0.2,
  },
  successState: {
    alignItems: 'center',
    justifyContent: 'center',
    paddingVertical: 60,
    paddingHorizontal: 20,
  },
  successCircle: {
    width: 80,
    height: 80,
    borderRadius: 40,
    backgroundColor: colors.greenLight,
    borderWidth: 2,
    borderColor: colors.greenBorder,
    alignItems: 'center',
    justifyContent: 'center',
    marginBottom: 20,
  },
  successTitle: {
    fontSize: 22,
    fontWeight: '700',
    color: colors.green,
    marginBottom: 6,
  },
  successSubtitle: {
    fontSize: 14,
    color: colors.textTertiary,
  },
});
