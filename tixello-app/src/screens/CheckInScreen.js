import React, { useState, useEffect, useRef, useCallback } from 'react';
import {
  View,
  Text,
  TouchableOpacity,
  StyleSheet,
  ScrollView,
  Animated,
  Dimensions,
  Vibration,
  Modal,
  TextInput,
} from 'react-native';
import Svg, { Path, Defs, LinearGradient, Stop, Rect, Line } from 'react-native-svg';
import { colors } from '../theme/colors';
import { useAuth } from '../context/AuthContext';
import { useEvent } from '../context/EventContext';
import { useApp } from '../context/AppContext';
import { checkinByCode } from '../api/participants';
import { publicApiGet } from '../api/client';
import { CameraView, useCameraPermissions } from 'expo-camera';

const { width: SCREEN_WIDTH } = Dimensions.get('window');
const SCANNER_SIZE = 280;
const CORNER_SIZE = 40;
const CORNER_THICKNESS = 4;

// ─── SVG Icons ───────────────────────────────────────────────

function CheckIcon({ size = 24, color = '#FFFFFF' }) {
  return (
    <Svg width={size} height={size} viewBox="0 0 24 24" fill="none">
      <Path
        d="M20 6L9 17l-5-5"
        stroke={color}
        strokeWidth={2.5}
        strokeLinecap="round"
        strokeLinejoin="round"
      />
    </Svg>
  );
}

function XIcon({ size = 24, color = '#FFFFFF' }) {
  return (
    <Svg width={size} height={size} viewBox="0 0 24 24" fill="none">
      <Path
        d="M18 6L6 18M6 6l12 12"
        stroke={color}
        strokeWidth={2.5}
        strokeLinecap="round"
        strokeLinejoin="round"
      />
    </Svg>
  );
}

function WarningIcon({ size = 24, color = '#FFFFFF' }) {
  return (
    <Svg width={size} height={size} viewBox="0 0 24 24" fill="none">
      <Path
        d="M12 9v4m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"
        stroke={color}
        strokeWidth={2}
        strokeLinecap="round"
        strokeLinejoin="round"
      />
    </Svg>
  );
}

function ScannerIcon({ size = 48, color }) {
  return (
    <Svg width={size} height={size} viewBox="0 0 24 24" fill="none">
      <Path
        d="M1 9V5a2 2 0 012-2h4M15 3h4a2 2 0 012 2v4M23 15v4a2 2 0 01-2 2h-4M9 21H5a2 2 0 01-2-2v-4"
        stroke={color || colors.textSecondary}
        strokeWidth={1.8}
        strokeLinecap="round"
        strokeLinejoin="round"
      />
      <Line
        x1="1" y1="12" x2="23" y2="12"
        stroke={color || colors.textSecondary}
        strokeWidth={1.5}
        strokeLinecap="round"
      />
    </Svg>
  );
}

function PauseIcon({ size = 48, color = '#FFFFFF' }) {
  return (
    <Svg width={size} height={size} viewBox="0 0 24 24" fill="none">
      <Path
        d="M10 4H6v16h4V4zM18 4h-4v16h4V4z"
        stroke={color}
        strokeWidth={2}
        strokeLinecap="round"
        strokeLinejoin="round"
      />
    </Svg>
  );
}

function ClockIcon({ size = 16, color }) {
  return (
    <Svg width={size} height={size} viewBox="0 0 24 24" fill="none">
      <Path
        d="M12 22c5.523 0 10-4.477 10-10S17.523 2 12 2 2 6.477 2 12s4.477 10 10 10z"
        stroke={color || colors.textSecondary}
        strokeWidth={1.8}
      />
      <Path
        d="M12 6v6l4 2"
        stroke={color || colors.textSecondary}
        strokeWidth={1.8}
        strokeLinecap="round"
      />
    </Svg>
  );
}

function BarChartIcon({ size = 20, color }) {
  return (
    <Svg width={size} height={size} viewBox="0 0 24 24" fill="none">
      <Path
        d="M12 20V10M18 20V4M6 20v-4"
        stroke={color || colors.textSecondary}
        strokeWidth={2}
        strokeLinecap="round"
        strokeLinejoin="round"
      />
    </Svg>
  );
}

function EditIcon({ size = 18, color }) {
  return (
    <Svg width={size} height={size} viewBox="0 0 24 24" fill="none">
      <Path
        d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"
        stroke={color || colors.textSecondary}
        strokeWidth={1.8}
        strokeLinecap="round"
        strokeLinejoin="round"
      />
      <Path
        d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"
        stroke={color || colors.textSecondary}
        strokeWidth={1.8}
        strokeLinecap="round"
        strokeLinejoin="round"
      />
    </Svg>
  );
}

// ─── Scanner Corner Brackets ─────────────────────────────────

function ScannerCorner({ position, color }) {
  const cornerStyles = {
    topLeft: { top: 0, left: 0, borderTopWidth: CORNER_THICKNESS, borderLeftWidth: CORNER_THICKNESS },
    topRight: { top: 0, right: 0, borderTopWidth: CORNER_THICKNESS, borderRightWidth: CORNER_THICKNESS },
    bottomLeft: { bottom: 0, left: 0, borderBottomWidth: CORNER_THICKNESS, borderLeftWidth: CORNER_THICKNESS },
    bottomRight: { bottom: 0, right: 0, borderBottomWidth: CORNER_THICKNESS, borderRightWidth: CORNER_THICKNESS },
  };

  const radiusStyles = {
    topLeft: { borderTopLeftRadius: 12 },
    topRight: { borderTopRightRadius: 12 },
    bottomLeft: { borderBottomLeftRadius: 12 },
    bottomRight: { borderBottomRightRadius: 12 },
  };

  return (
    <View
      style={[
        styles.scannerCorner,
        cornerStyles[position],
        radiusStyles[position],
        { borderColor: color },
      ]}
    />
  );
}

// ─── Extract Ticket Code Helper ─────────────────────────────────────────────
function extractTicketCode(input) {
  if (!input) return null;
  const trimmed = input.trim();
  // Try to extract from URL like https://...tixello.com/t/CODE or /verify/CODE
  const urlMatch = trimmed.match(/\/t\/([A-Za-z0-9_-]+)/);
  if (urlMatch) return urlMatch[1];
  const verifyMatch = trimmed.match(/\/verify\/([A-Za-z0-9_-]+)/);
  if (verifyMatch) return verifyMatch[1];
  // Use raw input as code
  return trimmed;
}

// ─── Main Component ──────────────────────────────────────────

export default function CheckInScreen({ navigation }) {
  const { user } = useAuth();
  const { selectedEvent, eventStats, isReportsOnlyMode, refreshStats } = useEvent();
  const {
    isShiftPaused,
    setIsShiftPaused,
    vibrationFeedback,
    addScan,
    recentScans,
    myScans,
  } = useApp();

  const [isScanning, setIsScanning] = useState(false);
  const [scanResult, setScanResult] = useState(null); // { type: 'valid'|'duplicate'|'invalid', data: {} }
  const [scansPerMinute, setScansPerMinute] = useState(0);
  const [avgWaitTime, setAvgWaitTime] = useState(0);
  const [showManualEntry, setShowManualEntry] = useState(false);
  const [manualCode, setManualCode] = useState('');
  const [permission, requestPermission] = useCameraPermissions();
  const [cameraActive, setCameraActive] = useState(false);
  const [scannedLock, setScannedLock] = useState(false);

  const scanLineAnim = useRef(new Animated.Value(0)).current;
  const scanLineLoop = useRef(null);
  const resultTimeout = useRef(null);
  const scanTimestamps = useRef([]);

  // ── Scan line animation ──

  const startScanLineAnimation = useCallback(() => {
    scanLineAnim.setValue(0);
    scanLineLoop.current = Animated.loop(
      Animated.sequence([
        Animated.timing(scanLineAnim, {
          toValue: 1,
          duration: 2000,
          useNativeDriver: true,
        }),
        Animated.timing(scanLineAnim, {
          toValue: 0,
          duration: 2000,
          useNativeDriver: true,
        }),
      ])
    );
    scanLineLoop.current.start();
  }, [scanLineAnim]);

  const stopScanLineAnimation = useCallback(() => {
    if (scanLineLoop.current) {
      scanLineLoop.current.stop();
      scanLineLoop.current = null;
    }
  }, []);

  // ── Cleanup on unmount ──

  useEffect(() => {
    return () => {
      stopScanLineAnimation();
      if (resultTimeout.current) clearTimeout(resultTimeout.current);
    };
  }, [stopScanLineAnimation]);

  // ── Calculate scans per minute ──

  useEffect(() => {
    const now = Date.now();
    const oneMinuteAgo = now - 60000;
    scanTimestamps.current = scanTimestamps.current.filter(t => t > oneMinuteAgo);
    setScansPerMinute(scanTimestamps.current.length);
  }, [myScans]);

  // ── Get border color based on state ──

  const getScannerBorderColor = () => {
    if (scanResult) {
      switch (scanResult.type) {
        case 'valid': return colors.green;
        case 'duplicate': return colors.amber;
        case 'invalid': return colors.red;
      }
    }
    if (isScanning) return colors.purple;
    return colors.border;
  };

  const getScannerGlowStyle = () => {
    if (!scanResult && !isScanning) return {};
    const glowColor = getScannerBorderColor();
    return {
      shadowColor: glowColor,
      shadowOffset: { width: 0, height: 0 },
      shadowOpacity: 0.6,
      shadowRadius: 20,
      elevation: 12,
    };
  };

  // ── Handle check-in ──

  const handleCheckIn = useCallback(async (inputCode) => {
    if (!selectedEvent || !inputCode) return;

    const code = extractTicketCode(inputCode);
    if (!code) return;

    setShowManualEntry(false);
    setManualCode('');
    setIsScanning(true);
    setCameraActive(false);
    setScanResult(null);
    startScanLineAnimation();

    try {
      const response = await checkinByCode(code);

      stopScanLineAnimation();
      setIsScanning(false);

      if (response.success || response.data) {
        const participant = response.data || response;
        const result = {
          type: 'valid',
          data: {
            name: participant.full_name || participant.name || 'Participant',
            ticketType: participant.ticket_type_name || participant.ticket_type || 'General',
            seat: participant.seat || null,
            code: code,
          },
        };
        setScanResult(result);

        if (vibrationFeedback) {
          Vibration.vibrate(100);
        }

        scanTimestamps.current.push(Date.now());

        addScan({
          id: Date.now(),
          type: 'valid',
          name: result.data.name,
          ticketType: result.data.ticketType,
          time: new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }),
          code: code,
        });

        refreshStats();
      }
    } catch (error) {
      stopScanLineAnimation();
      setIsScanning(false);

      const message = error.message || '';

      if (message.toLowerCase().includes('already') || message.toLowerCase().includes('checked')) {
        const result = {
          type: 'duplicate',
          data: {
            message: 'Acest bilet a fost deja scanat',
            checkedInAt: error.checked_in_at || 'Mai devreme azi',
          },
        };
        setScanResult(result);

        if (vibrationFeedback) {
          Vibration.vibrate([0, 100, 100, 100]);
        }

        addScan({
          id: Date.now(),
          type: 'duplicate',
          name: error.attendee_name || 'Unknown',
          ticketType: error.ticket_type || 'Ticket',
          time: new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }),
          code: code,
        });
      } else {
        const result = {
          type: 'invalid',
          data: {
            message: message || 'Bilet negăsit sau cod invalid',
          },
        };
        setScanResult(result);

        if (vibrationFeedback) {
          Vibration.vibrate([0, 200, 100, 200]);
        }

        addScan({
          id: Date.now(),
          type: 'invalid',
          name: 'Invalid Ticket',
          ticketType: '-',
          time: new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }),
          code: code,
        });
      }
    }

    // Auto-clear result after 3 seconds
    resultTimeout.current = setTimeout(() => {
      setScanResult(null);
    }, 3000);
  }, [selectedEvent, vibrationFeedback, addScan, refreshStats, startScanLineAnimation, stopScanLineAnimation]);

  const handleBarcodeScan = useCallback(({ data }) => {
    if (scannedLock || isScanning) return;
    setScannedLock(true);
    setCameraActive(false);
    handleCheckIn(data);
    // Reset lock after processing
    setTimeout(() => setScannedLock(false), 2000);
  }, [scannedLock, isScanning, handleCheckIn]);

  // ── Scan line translateY interpolation ──

  const scanLineTranslateY = scanLineAnim.interpolate({
    inputRange: [0, 1],
    outputRange: [0, SCANNER_SIZE - 4],
  });

  // ── Reports Only Mode ──

  if (isReportsOnlyMode) {
    return (
      <View style={styles.container}>
        <ScrollView
          style={styles.scrollView}
          contentContainerStyle={styles.centeredContent}
          showsVerticalScrollIndicator={false}
        >
          <View style={styles.reportsPlaceholder}>
            <View style={styles.reportsIconContainer}>
              <BarChartIcon size={48} color={colors.purple} />
            </View>
            <Text style={styles.reportsTitle}>Eveniment Trecut</Text>
            <Text style={styles.reportsDescription}>
              Acest eveniment s-a încheiat. Check-in-ul nu mai este disponibil, dar puteți vizualiza rapoartele și statisticile.
            </Text>
            <TouchableOpacity
              style={styles.reportsButton}
              activeOpacity={0.7}
              onPress={() => navigation?.navigate?.('Reports')}
            >
              <BarChartIcon size={18} color={colors.white} />
              <Text style={styles.reportsButtonText}>Vezi Rapoarte</Text>
            </TouchableOpacity>
          </View>
        </ScrollView>
      </View>
    );
  }

  // ── Shift Paused Overlay ──

  const renderPausedOverlay = () => {
    if (!isShiftPaused) return null;

    return (
      <View style={styles.pausedOverlay}>
        <View style={styles.pausedContent}>
          <View style={styles.pausedIconContainer}>
            <PauseIcon size={48} color={colors.purple} />
          </View>
          <Text style={styles.pausedTitle}>Tură Întreruptă</Text>
          <Text style={styles.pausedDescription}>
            Tura dvs. este momentan întreruptă. Reluați pentru a continua scanarea biletelor.
          </Text>
          <TouchableOpacity
            style={styles.resumeButton}
            activeOpacity={0.7}
            onPress={() => setIsShiftPaused(false)}
          >
            <Svg width={18} height={18} viewBox="0 0 24 24" fill="none">
              <Path
                d="M5 3l14 9-14 9V3z"
                stroke={colors.white}
                strokeWidth={2}
                strokeLinecap="round"
                strokeLinejoin="round"
              />
            </Svg>
            <Text style={styles.resumeButtonText}>Continuă</Text>
          </TouchableOpacity>
        </View>
      </View>
    );
  };

  // ── Scan Result Card ──

  const renderResultCard = () => {
    if (!scanResult) return null;

    const configs = {
      valid: {
        bgColor: colors.greenBg,
        borderColor: colors.greenBorder,
        iconBg: colors.green,
        icon: <CheckIcon size={28} color={colors.white} />,
        title: 'ACCES APROBAT',
        titleColor: colors.green,
      },
      duplicate: {
        bgColor: colors.amberBg,
        borderColor: colors.amberBorder,
        iconBg: colors.amber,
        icon: <WarningIcon size={28} color={colors.white} />,
        title: 'DEJA SCANAT',
        titleColor: colors.amber,
      },
      invalid: {
        bgColor: colors.redBg,
        borderColor: colors.redBorder,
        iconBg: colors.red,
        icon: <XIcon size={28} color={colors.white} />,
        title: 'BILET INVALID',
        titleColor: colors.red,
      },
    };

    const config = configs[scanResult.type];

    return (
      <View
        style={[
          styles.resultCard,
          { backgroundColor: config.bgColor, borderColor: config.borderColor },
        ]}
      >
        <View style={styles.resultCardInner}>
          <View style={[styles.resultIconCircle, { backgroundColor: config.iconBg }]}>
            {config.icon}
          </View>
          <View style={styles.resultTextContainer}>
            <Text style={[styles.resultTitle, { color: config.titleColor }]}>
              {config.title}
            </Text>
            {scanResult.type === 'valid' && (
              <>
                <Text style={styles.resultName}>{scanResult.data.name}</Text>
                <View style={styles.resultDetails}>
                  <Text style={styles.resultDetail}>{scanResult.data.ticketType}</Text>
                  {scanResult.data.seat && (
                    <>
                      <Text style={styles.resultDetailDot}>{'\u2022'}</Text>
                      <Text style={styles.resultDetail}>Seat {scanResult.data.seat}</Text>
                    </>
                  )}
                </View>
              </>
            )}
            {scanResult.type === 'duplicate' && (
              <View style={styles.resultDetails}>
                <ClockIcon size={14} color={colors.amber} />
                <Text style={[styles.resultDetail, { color: colors.amber, marginLeft: 4 }]}>
                  Checked in: {scanResult.data.checkedInAt}
                </Text>
              </View>
            )}
            {scanResult.type === 'invalid' && (
              <Text style={[styles.resultDetail, { color: colors.red }]}>
                {scanResult.data.message}
              </Text>
            )}
          </View>
        </View>
      </View>
    );
  };

  // ── Main Render ──

  const totalChecked = eventStats?.checked_in || eventStats?.total_checked_in || 0;

  return (
    <View style={styles.container}>
      {renderPausedOverlay()}

      <ScrollView
        style={styles.scrollView}
        contentContainerStyle={styles.scrollContent}
        showsVerticalScrollIndicator={false}
      >
        {/* Scanner Container */}
        <View style={styles.scannerSection}>
          <View
            style={[
              styles.scannerFrame,
              { borderColor: getScannerBorderColor() },
              getScannerGlowStyle(),
            ]}
          >
            {/* Camera or placeholder */}
            {cameraActive && permission?.granted ? (
              <CameraView
                style={StyleSheet.absoluteFillObject}
                facing="back"
                barcodeScannerSettings={{
                  barcodeTypes: ['qr', 'code128', 'code39', 'ean13', 'ean8'],
                }}
                onBarcodeScanned={handleBarcodeScan}
              />
            ) : (
              <View style={styles.scannerPlaceholder}>
                <ScannerIcon size={48} color={colors.textQuaternary} />
                <Text style={styles.scannerPlaceholderText}>
                  {permission?.granted ? 'Apasă pentru a scana' : 'Cameră necesară pentru scanare'}
                </Text>
              </View>
            )}

            {/* Animated scan line */}
            {isScanning && (
              <Animated.View
                style={[
                  styles.scanLine,
                  { transform: [{ translateY: scanLineTranslateY }] },
                ]}
              >
                <Svg width={SCANNER_SIZE - 16} height={3}>
                  <Defs>
                    <LinearGradient id="scanLineGrad" x1="0" y1="0" x2="1" y2="0">
                      <Stop offset="0" stopColor="transparent" />
                      <Stop offset="0.3" stopColor={colors.purple} />
                      <Stop offset="0.7" stopColor={colors.purple} />
                      <Stop offset="1" stopColor="transparent" />
                    </LinearGradient>
                  </Defs>
                  <Rect
                    x={0} y={0}
                    width={SCANNER_SIZE - 16} height={3}
                    fill="url(#scanLineGrad)"
                    rx={1.5}
                  />
                </Svg>
              </Animated.View>
            )}

            {/* Corner brackets */}
            <ScannerCorner position="topLeft" color={getScannerBorderColor()} />
            <ScannerCorner position="topRight" color={getScannerBorderColor()} />
            <ScannerCorner position="bottomLeft" color={getScannerBorderColor()} />
            <ScannerCorner position="bottomRight" color={getScannerBorderColor()} />
          </View>
        </View>

        {/* Scan Result Card */}
        {renderResultCard()}

        {/* Action Buttons */}
        <View style={styles.actionsContainer}>
          <TouchableOpacity
            style={[
              styles.scanButton,
              isScanning && styles.scanButtonScanning,
            ]}
            activeOpacity={0.8}
            onPress={async () => {
              if (!permission?.granted) {
                const result = await requestPermission();
                if (!result.granted) return;
              }
              setCameraActive(!cameraActive);
              setScanResult(null);
            }}
            disabled={isShiftPaused}
          >
            <Svg width={22} height={22} viewBox="0 0 24 24" fill="none">
              <Path
                d="M1 9V5a2 2 0 012-2h4M15 3h4a2 2 0 012 2v4M23 15v4a2 2 0 01-2 2h-4M9 21H5a2 2 0 01-2-2v-4"
                stroke={colors.white}
                strokeWidth={2}
                strokeLinecap="round"
                strokeLinejoin="round"
              />
            </Svg>
            <Text style={styles.scanButtonText}>
              {isScanning ? 'Se scanează...' : cameraActive ? 'Oprește Camera' : scanResult ? 'Scanează Următorul' : 'Începe Scanarea'}
            </Text>
          </TouchableOpacity>

          <TouchableOpacity
            style={styles.manualEntryButton}
            activeOpacity={0.7}
            disabled={isShiftPaused}
            onPress={() => setShowManualEntry(true)}
          >
            <EditIcon size={16} color={colors.textSecondary} />
            <Text style={styles.manualEntryText}>Cod Manual</Text>
          </TouchableOpacity>
        </View>

        {/* Live Stats Pills */}
        <View style={styles.statsRow}>
          <View style={styles.statPill}>
            <Text style={styles.statValue}>{scansPerMinute}</Text>
            <Text style={styles.statLabel}>scanări/min</Text>
          </View>
          <View style={styles.statPill}>
            <Text style={styles.statValue}>{avgWaitTime}s</Text>
            <Text style={styles.statLabel}>așteptare</Text>
          </View>
          <View style={styles.statPill}>
            <Text style={styles.statValue}>{totalChecked}</Text>
            <Text style={styles.statLabel}>intrați</Text>
          </View>
        </View>

        {/* Recent Scans */}
        {recentScans.length > 0 && (
          <View style={styles.recentSection}>
            <Text style={styles.recentTitle}>Scanări Recente</Text>
            {recentScans.map((scan) => (
              <View key={scan.id} style={styles.recentItem}>
                <View
                  style={[
                    styles.recentStatusIcon,
                    {
                      backgroundColor:
                        scan.type === 'valid'
                          ? colors.greenBg
                          : scan.type === 'duplicate'
                            ? colors.amberBg
                            : colors.redBg,
                    },
                  ]}
                >
                  {scan.type === 'valid' && <CheckIcon size={14} color={colors.green} />}
                  {scan.type === 'duplicate' && <WarningIcon size={14} color={colors.amber} />}
                  {scan.type === 'invalid' && <XIcon size={14} color={colors.red} />}
                </View>
                <View style={styles.recentItemText}>
                  <Text style={styles.recentName} numberOfLines={1}>
                    {scan.name}
                  </Text>
                  <Text style={styles.recentTicketType} numberOfLines={1}>
                    {scan.ticketType}
                  </Text>
                </View>
                <Text style={styles.recentTime}>{scan.time}</Text>
              </View>
            ))}
          </View>
        )}

        {/* Manual Entry Modal */}
        <Modal
          visible={showManualEntry}
          transparent
          animationType="fade"
          onRequestClose={() => setShowManualEntry(false)}
        >
          <View style={styles.modalOverlay}>
            <View style={styles.manualEntryModal}>
              <Text style={styles.modalTitle}>Introduceți Codul Biletului</Text>
              <Text style={styles.modalDescription}>
                Tastați sau lipiți codul biletului, codul de bare sau URL-ul de verificare
              </Text>
              <TextInput
                style={styles.codeInput}
                placeholder="ex. ABC123 sau URL scanare"
                placeholderTextColor={colors.textQuaternary}
                value={manualCode}
                onChangeText={setManualCode}
                autoCapitalize="none"
                autoCorrect={false}
                autoFocus={true}
                returnKeyType="go"
                onSubmitEditing={() => manualCode.trim() && handleCheckIn(manualCode)}
              />
              <TouchableOpacity
                style={[styles.checkInSubmitButton, !manualCode.trim() && styles.checkInSubmitButtonDisabled]}
                onPress={() => handleCheckIn(manualCode)}
                activeOpacity={0.7}
                disabled={!manualCode.trim() || isScanning}
              >
                <Text style={styles.checkInSubmitButtonText}>Check-in</Text>
              </TouchableOpacity>
              <TouchableOpacity
                style={styles.cancelButton}
                onPress={() => { setShowManualEntry(false); setManualCode(''); }}
                activeOpacity={0.7}
              >
                <Text style={styles.cancelButtonText}>Anulare</Text>
              </TouchableOpacity>
            </View>
          </View>
        </Modal>

        {/* Bottom spacing */}
        <View style={{ height: 32 }} />
      </ScrollView>
    </View>
  );
}

// ─── Styles ──────────────────────────────────────────────────

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: colors.background,
  },
  scrollView: {
    flex: 1,
  },
  scrollContent: {
    paddingHorizontal: 20,
    paddingTop: 16,
    paddingBottom: 20,
  },
  centeredContent: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    paddingHorizontal: 32,
  },

  // ── Reports Only Mode ──

  reportsPlaceholder: {
    alignItems: 'center',
  },
  reportsIconContainer: {
    width: 88,
    height: 88,
    borderRadius: 44,
    backgroundColor: colors.purpleBg,
    borderWidth: 1,
    borderColor: colors.purpleBorder,
    alignItems: 'center',
    justifyContent: 'center',
    marginBottom: 24,
  },
  reportsTitle: {
    fontSize: 22,
    fontWeight: '700',
    color: colors.textPrimary,
    marginBottom: 12,
  },
  reportsDescription: {
    fontSize: 15,
    color: colors.textSecondary,
    textAlign: 'center',
    lineHeight: 22,
    marginBottom: 32,
  },
  reportsButton: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
    backgroundColor: colors.purple,
    paddingHorizontal: 28,
    paddingVertical: 14,
    borderRadius: 12,
  },
  reportsButtonText: {
    fontSize: 16,
    fontWeight: '600',
    color: colors.white,
  },

  // ── Paused Overlay ──

  pausedOverlay: {
    ...StyleSheet.absoluteFillObject,
    backgroundColor: 'rgba(10, 10, 15, 0.92)',
    zIndex: 50,
    alignItems: 'center',
    justifyContent: 'center',
    paddingHorizontal: 32,
  },
  pausedContent: {
    alignItems: 'center',
  },
  pausedIconContainer: {
    width: 88,
    height: 88,
    borderRadius: 44,
    backgroundColor: colors.purpleBg,
    borderWidth: 1,
    borderColor: colors.purpleBorder,
    alignItems: 'center',
    justifyContent: 'center',
    marginBottom: 24,
  },
  pausedTitle: {
    fontSize: 22,
    fontWeight: '700',
    color: colors.textPrimary,
    marginBottom: 12,
  },
  pausedDescription: {
    fontSize: 15,
    color: colors.textSecondary,
    textAlign: 'center',
    lineHeight: 22,
    marginBottom: 32,
  },
  resumeButton: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
    backgroundColor: colors.purple,
    paddingHorizontal: 28,
    paddingVertical: 14,
    borderRadius: 12,
  },
  resumeButtonText: {
    fontSize: 16,
    fontWeight: '600',
    color: colors.white,
  },

  // ── Scanner ──

  scannerSection: {
    alignItems: 'center',
    marginBottom: 20,
  },
  scannerFrame: {
    width: SCANNER_SIZE,
    height: SCANNER_SIZE,
    borderRadius: 24,
    borderWidth: 2,
    backgroundColor: 'rgba(255,255,255,0.02)',
    overflow: 'hidden',
    position: 'relative',
  },
  scannerPlaceholder: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'center',
    gap: 12,
  },
  scannerPlaceholderText: {
    fontSize: 13,
    color: colors.textQuaternary,
    letterSpacing: 0.3,
  },
  scanLine: {
    position: 'absolute',
    top: 0,
    left: 8,
    right: 8,
    height: 3,
    alignItems: 'center',
  },
  scannerCorner: {
    position: 'absolute',
    width: CORNER_SIZE,
    height: CORNER_SIZE,
    borderColor: colors.purple,
  },

  // ── Result Card ──

  resultCard: {
    borderWidth: 1,
    borderRadius: 16,
    padding: 16,
    marginBottom: 20,
  },
  resultCardInner: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 14,
  },
  resultIconCircle: {
    width: 52,
    height: 52,
    borderRadius: 26,
    alignItems: 'center',
    justifyContent: 'center',
  },
  resultTextContainer: {
    flex: 1,
  },
  resultTitle: {
    fontSize: 14,
    fontWeight: '800',
    letterSpacing: 1,
    marginBottom: 4,
  },
  resultName: {
    fontSize: 17,
    fontWeight: '600',
    color: colors.textPrimary,
    marginBottom: 4,
  },
  resultDetails: {
    flexDirection: 'row',
    alignItems: 'center',
  },
  resultDetail: {
    fontSize: 13,
    color: colors.textSecondary,
  },
  resultDetailDot: {
    fontSize: 13,
    color: colors.textTertiary,
    marginHorizontal: 6,
  },

  // ── Action Buttons ──

  actionsContainer: {
    alignItems: 'center',
    gap: 12,
    marginBottom: 24,
  },
  scanButton: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 10,
    width: '100%',
    paddingVertical: 16,
    borderRadius: 14,
    backgroundColor: colors.purple,
  },
  scanButtonScanning: {
    backgroundColor: colors.purpleSecondary,
    opacity: 0.8,
  },
  scanButtonText: {
    fontSize: 17,
    fontWeight: '700',
    color: colors.white,
    letterSpacing: 0.3,
  },
  manualEntryButton: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 8,
    paddingVertical: 12,
    paddingHorizontal: 20,
  },
  manualEntryText: {
    fontSize: 14,
    fontWeight: '500',
    color: colors.textSecondary,
  },

  // ── Live Stats Pills ──

  statsRow: {
    flexDirection: 'row',
    justifyContent: 'center',
    gap: 10,
    marginBottom: 28,
  },
  statPill: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    backgroundColor: colors.surface,
    borderWidth: 1,
    borderColor: colors.border,
    borderRadius: 20,
    paddingHorizontal: 14,
    paddingVertical: 8,
  },
  statValue: {
    fontSize: 14,
    fontWeight: '700',
    color: colors.textPrimary,
  },
  statLabel: {
    fontSize: 12,
    color: colors.textTertiary,
    letterSpacing: 0.2,
  },

  // ── Recent Scans ──

  recentSection: {
    marginBottom: 8,
  },
  recentTitle: {
    fontSize: 16,
    fontWeight: '600',
    color: colors.textPrimary,
    marginBottom: 12,
  },
  recentItem: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: colors.surface,
    borderWidth: 1,
    borderColor: colors.border,
    borderRadius: 12,
    padding: 12,
    marginBottom: 8,
    gap: 12,
  },
  recentStatusIcon: {
    width: 32,
    height: 32,
    borderRadius: 16,
    alignItems: 'center',
    justifyContent: 'center',
  },
  recentItemText: {
    flex: 1,
  },
  recentName: {
    fontSize: 14,
    fontWeight: '600',
    color: colors.textPrimary,
    marginBottom: 2,
  },
  recentTicketType: {
    fontSize: 12,
    color: colors.textTertiary,
  },
  recentTime: {
    fontSize: 12,
    color: colors.textQuaternary,
    fontWeight: '500',
  },

  // ── Manual Entry Modal ──
  modalOverlay: {
    flex: 1,
    backgroundColor: 'rgba(0,0,0,0.7)',
    alignItems: 'center',
    justifyContent: 'center',
  },
  manualEntryModal: {
    backgroundColor: '#16161F',
    borderRadius: 20,
    padding: 28,
    marginHorizontal: 24,
    width: '90%',
    maxWidth: 400,
    alignItems: 'center',
    borderWidth: 1,
    borderColor: colors.border,
  },
  modalTitle: {
    fontSize: 20,
    fontWeight: '700',
    color: colors.textPrimary,
    marginBottom: 8,
  },
  modalDescription: {
    fontSize: 14,
    color: colors.textSecondary,
    textAlign: 'center',
    lineHeight: 20,
    marginBottom: 24,
  },
  codeInput: {
    width: '100%',
    height: 48,
    backgroundColor: colors.surface,
    borderWidth: 1,
    borderColor: 'rgba(255,255,255,0.1)',
    borderRadius: 12,
    paddingHorizontal: 16,
    fontSize: 16,
    color: colors.textPrimary,
    marginBottom: 16,
    textAlign: 'center',
    letterSpacing: 1,
  },
  checkInSubmitButton: {
    width: '100%',
    height: 48,
    backgroundColor: colors.purple,
    borderRadius: 12,
    alignItems: 'center',
    justifyContent: 'center',
    marginBottom: 8,
  },
  checkInSubmitButtonDisabled: {
    opacity: 0.5,
  },
  checkInSubmitButtonText: {
    fontSize: 16,
    fontWeight: '600',
    color: colors.white,
  },
  cancelButton: {
    paddingVertical: 10,
  },
  cancelButtonText: {
    fontSize: 14,
    color: colors.textSecondary,
    fontWeight: '500',
  },
});
