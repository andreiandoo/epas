import { useState, useEffect, useRef } from 'react';
import {
  View,
  Text,
  StyleSheet,
  TouchableOpacity,
  ScrollView,
  Alert,
  Vibration,
} from 'react-native';
import { Camera, CameraView } from 'expo-camera';
import { Ionicons } from '@expo/vector-icons';
import * as Haptics from 'expo-haptics';
import { Card, Button, StatusBadge } from '../../../src/components/ui';
import { useEventStore } from '../../../src/stores/eventStore';
import { useCheckInStore } from '../../../src/stores/checkInStore';
import { useAppStore } from '../../../src/stores/appStore';
import { checkInApi } from '../../../src/api';
import { colors, spacing, typography, borderRadius } from '../../../src/utils/theme';

export default function CheckInScreen() {
  const { selectedEvent } = useEventStore();
  const {
    isScanning,
    setScanning,
    lastScanResult,
    setScanResult,
    scanHistory,
    addToHistory,
    settings,
    totalScans,
    validScans,
  } = useCheckInStore();
  const { isShiftPaused } = useAppStore();

  const [hasPermission, setHasPermission] = useState<boolean | null>(null);
  const [scanned, setScanned] = useState(false);
  const cameraRef = useRef<CameraView>(null);

  useEffect(() => {
    (async () => {
      const { status } = await Camera.requestCameraPermissionsAsync();
      setHasPermission(status === 'granted');
    })();
  }, []);

  const handleBarCodeScanned = async ({ type, data }: { type: string; data: string }) => {
    if (scanned || isShiftPaused || !selectedEvent) return;

    setScanned(true);
    setScanning(true);

    // Haptic feedback
    if (settings.vibration_feedback) {
      Haptics.notificationAsync(Haptics.NotificationFeedbackType.Success);
    }

    try {
      const response = await checkInApi.checkIn(selectedEvent.id, data);

      if (response.success && response.data) {
        const result = response.data;
        setScanResult(result);

        // Add to history
        addToHistory({
          id: Date.now().toString(),
          code: data,
          holder_name: result.ticket?.holder_name || 'Unknown',
          ticket_type: result.ticket?.ticket_type || 'Unknown',
          status: result.ticket?.status === 'valid' ? 'valid' :
                  result.ticket?.status === 'used' ? 'duplicate' : 'invalid',
          scanned_at: new Date().toISOString(),
        });

        // Haptic feedback based on result
        if (result.success) {
          if (settings.vibration_feedback) {
            Haptics.notificationAsync(Haptics.NotificationFeedbackType.Success);
          }
        } else {
          if (settings.vibration_feedback) {
            Haptics.notificationAsync(Haptics.NotificationFeedbackType.Error);
          }
        }
      }
    } catch (error: any) {
      console.error('Check-in error:', error);
      setScanResult({
        success: false,
        message: error.response?.data?.message || 'Check-in failed',
        timestamp: new Date().toISOString(),
      });

      if (settings.vibration_feedback) {
        Haptics.notificationAsync(Haptics.NotificationFeedbackType.Error);
      }
    } finally {
      setScanning(false);

      // Auto-reset for next scan
      if (settings.auto_confirm_valid) {
        setTimeout(() => {
          setScanned(false);
          setScanResult(null);
        }, 1500);
      }
    }
  };

  const resetScanner = () => {
    setScanned(false);
    setScanResult(null);
  };

  const getScanResultStyle = () => {
    if (!lastScanResult) return {};
    if (lastScanResult.success) return styles.scannerValid;
    if (lastScanResult.ticket?.status === 'used') return styles.scannerDuplicate;
    return styles.scannerInvalid;
  };

  const getResultStatusText = () => {
    if (!lastScanResult) return '';
    if (lastScanResult.success) return 'ACCESS GRANTED';
    if (lastScanResult.ticket?.status === 'used') return 'ALREADY SCANNED';
    return 'INVALID TICKET';
  };

  const getResultStatusColor = () => {
    if (!lastScanResult) return colors.textPrimary;
    if (lastScanResult.success) return colors.success;
    if (lastScanResult.ticket?.status === 'used') return colors.warning;
    return colors.error;
  };

  if (hasPermission === null) {
    return (
      <View style={styles.container}>
        <Text style={styles.permissionText}>Requesting camera permission...</Text>
      </View>
    );
  }

  if (hasPermission === false) {
    return (
      <View style={styles.container}>
        <Text style={styles.permissionText}>Camera permission denied</Text>
        <Text style={styles.permissionSubtext}>
          Please enable camera access in your device settings to scan tickets.
        </Text>
      </View>
    );
  }

  return (
    <View style={styles.container}>
      {/* Shift Paused Overlay */}
      {isShiftPaused && (
        <View style={styles.pausedOverlay}>
          <View style={styles.pausedContent}>
            <Ionicons name="pause" size={64} color={colors.textPrimary} />
            <Text style={styles.pausedText}>Shift Paused</Text>
          </View>
        </View>
      )}

      <ScrollView style={styles.content} showsVerticalScrollIndicator={false}>
        {/* Scanner Frame */}
        <View style={styles.scannerContainer}>
          <View style={[styles.scannerFrame, getScanResultStyle()]}>
            {!scanned && !lastScanResult ? (
              <CameraView
                ref={cameraRef}
                style={styles.camera}
                facing="back"
                barcodeScannerSettings={{
                  barcodeTypes: ['qr', 'code128', 'code39', 'ean13'],
                }}
                onBarcodeScanned={scanned ? undefined : handleBarCodeScanned}
              >
                <View style={styles.scannerOverlay}>
                  <View style={styles.scannerCorners}>
                    <View style={[styles.corner, styles.cornerTL]} />
                    <View style={[styles.corner, styles.cornerTR]} />
                    <View style={[styles.corner, styles.cornerBL]} />
                    <View style={[styles.corner, styles.cornerBR]} />
                  </View>
                  {isScanning && <View style={styles.scanLine} />}
                </View>
              </CameraView>
            ) : lastScanResult ? (
              <View style={styles.resultContainer}>
                <Ionicons
                  name={
                    lastScanResult.success
                      ? 'checkmark-circle'
                      : lastScanResult.ticket?.status === 'used'
                      ? 'alert-circle'
                      : 'close-circle'
                  }
                  size={64}
                  color={getResultStatusColor()}
                />
              </View>
            ) : (
              <View style={styles.scannerPrompt}>
                <Ionicons name="phone-portrait" size={48} color={colors.textMuted} />
                <Text style={styles.promptText}>Point camera at QR code</Text>
              </View>
            )}
          </View>

          {/* Result Card */}
          {lastScanResult && (
            <Card
              variant={
                lastScanResult.success
                  ? 'success'
                  : lastScanResult.ticket?.status === 'used'
                  ? 'warning'
                  : 'error'
              }
              style={styles.resultCard}
            >
              <Text style={[styles.resultStatus, { color: getResultStatusColor() }]}>
                {getResultStatusText()}
              </Text>
              {lastScanResult.ticket?.holder_name && (
                <Text style={styles.resultName}>{lastScanResult.ticket.holder_name}</Text>
              )}
              {lastScanResult.ticket?.ticket_type && (
                <Text style={styles.resultTicketType}>{lastScanResult.ticket.ticket_type}</Text>
              )}
              {lastScanResult.ticket?.seat_label && (
                <Text style={styles.resultSeat}>{lastScanResult.ticket.seat_label}</Text>
              )}
              <Text style={styles.resultMessage}>{lastScanResult.message}</Text>
            </Card>
          )}

          {/* Scan Button */}
          <Button
            title={
              isScanning
                ? 'Scanning...'
                : lastScanResult
                ? 'Scan Next'
                : 'Start Scanning'
            }
            onPress={resetScanner}
            loading={isScanning}
            icon={<Ionicons name="camera" size={20} color={colors.textPrimary} />}
            size="lg"
            style={styles.scanButton}
            disabled={isShiftPaused}
          />
        </View>

        {/* Check-in Stats */}
        <View style={styles.statsRow}>
          <View style={styles.statPill}>
            <Text style={styles.statValue}>{validScans}</Text>
            <Text style={styles.statLabel}>Valid</Text>
          </View>
          <View style={styles.statPill}>
            <Text style={styles.statValue}>{totalScans}</Text>
            <Text style={styles.statLabel}>Total</Text>
          </View>
        </View>

        {/* Recent Scans */}
        <View style={styles.recentScans}>
          <Text style={styles.sectionTitle}>Recent Scans</Text>
          {scanHistory.slice(0, 10).map((scan) => (
            <View key={scan.id} style={styles.scanItem}>
              <View
                style={[
                  styles.scanStatusIcon,
                  scan.status === 'valid'
                    ? styles.statusValid
                    : scan.status === 'duplicate'
                    ? styles.statusDuplicate
                    : styles.statusInvalid,
                ]}
              >
                <Ionicons
                  name={scan.status === 'valid' ? 'checkmark' : 'alert'}
                  size={14}
                  color={colors.textPrimary}
                />
              </View>
              <View style={styles.scanInfo}>
                <Text style={styles.scanName}>{scan.holder_name}</Text>
                <Text style={styles.scanTicket}>{scan.ticket_type}</Text>
              </View>
              <Text style={styles.scanTime}>
                {new Date(scan.scanned_at).toLocaleTimeString('en-US', {
                  hour: '2-digit',
                  minute: '2-digit',
                })}
              </Text>
            </View>
          ))}
          {scanHistory.length === 0 && (
            <Text style={styles.emptyText}>No scans yet</Text>
          )}
        </View>

        <View style={styles.bottomPadding} />
      </ScrollView>
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: colors.background,
    paddingTop: 60,
  },
  content: {
    flex: 1,
    padding: spacing.xl,
  },
  permissionText: {
    fontSize: typography.fontSize.lg,
    color: colors.textPrimary,
    textAlign: 'center',
    marginTop: 100,
  },
  permissionSubtext: {
    fontSize: typography.fontSize.md,
    color: colors.textMuted,
    textAlign: 'center',
    marginTop: spacing.md,
    paddingHorizontal: spacing.xxl,
  },
  pausedOverlay: {
    ...StyleSheet.absoluteFillObject,
    backgroundColor: 'rgba(0, 0, 0, 0.8)',
    justifyContent: 'center',
    alignItems: 'center',
    zIndex: 100,
  },
  pausedContent: {
    alignItems: 'center',
  },
  pausedText: {
    fontSize: typography.fontSize.xxl,
    fontWeight: '600',
    color: colors.textPrimary,
    marginTop: spacing.lg,
  },
  scannerContainer: {
    alignItems: 'center',
    marginBottom: spacing.xxl,
  },
  scannerFrame: {
    width: 280,
    height: 280,
    borderRadius: borderRadius.xxl,
    backgroundColor: colors.backgroundCard,
    borderWidth: 2,
    borderColor: colors.border,
    overflow: 'hidden',
    marginBottom: spacing.xl,
  },
  scannerValid: {
    borderColor: colors.success,
    shadowColor: colors.success,
    shadowOffset: { width: 0, height: 0 },
    shadowOpacity: 0.3,
    shadowRadius: 20,
  },
  scannerDuplicate: {
    borderColor: colors.warning,
    shadowColor: colors.warning,
    shadowOffset: { width: 0, height: 0 },
    shadowOpacity: 0.3,
    shadowRadius: 20,
  },
  scannerInvalid: {
    borderColor: colors.error,
    shadowColor: colors.error,
    shadowOffset: { width: 0, height: 0 },
    shadowOpacity: 0.3,
    shadowRadius: 20,
  },
  camera: {
    flex: 1,
  },
  scannerOverlay: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
  },
  scannerCorners: {
    width: 200,
    height: 200,
    position: 'relative',
  },
  corner: {
    position: 'absolute',
    width: 30,
    height: 30,
    borderColor: 'rgba(255, 255, 255, 0.5)',
  },
  cornerTL: {
    top: 0,
    left: 0,
    borderTopWidth: 3,
    borderLeftWidth: 3,
    borderTopLeftRadius: 8,
  },
  cornerTR: {
    top: 0,
    right: 0,
    borderTopWidth: 3,
    borderRightWidth: 3,
    borderTopRightRadius: 8,
  },
  cornerBL: {
    bottom: 0,
    left: 0,
    borderBottomWidth: 3,
    borderLeftWidth: 3,
    borderBottomLeftRadius: 8,
  },
  cornerBR: {
    bottom: 0,
    right: 0,
    borderBottomWidth: 3,
    borderRightWidth: 3,
    borderBottomRightRadius: 8,
  },
  scanLine: {
    position: 'absolute',
    left: 20,
    right: 20,
    height: 2,
    backgroundColor: colors.primary,
  },
  scannerPrompt: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
  },
  promptText: {
    fontSize: typography.fontSize.md,
    color: colors.textMuted,
    marginTop: spacing.md,
  },
  resultContainer: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
  },
  resultCard: {
    width: '100%',
    alignItems: 'center',
    marginBottom: spacing.lg,
  },
  resultStatus: {
    fontSize: typography.fontSize.sm,
    fontWeight: '700',
    letterSpacing: 1,
    marginBottom: spacing.md,
  },
  resultName: {
    fontSize: typography.fontSize.xl,
    fontWeight: '700',
    color: colors.textPrimary,
    marginBottom: spacing.xs,
  },
  resultTicketType: {
    fontSize: typography.fontSize.md,
    color: colors.textSecondary,
    marginBottom: spacing.xs,
  },
  resultSeat: {
    fontSize: typography.fontSize.sm,
    color: colors.textMuted,
    marginBottom: spacing.md,
  },
  resultMessage: {
    fontSize: typography.fontSize.md,
    color: colors.textSecondary,
  },
  scanButton: {
    width: '100%',
  },
  statsRow: {
    flexDirection: 'row',
    justifyContent: 'center',
    gap: spacing.lg,
    marginBottom: spacing.xxl,
  },
  statPill: {
    flexDirection: 'row',
    alignItems: 'baseline',
    gap: spacing.xs,
    paddingHorizontal: spacing.lg,
    paddingVertical: spacing.sm,
    backgroundColor: colors.backgroundCard,
    borderRadius: borderRadius.round,
  },
  statValue: {
    fontSize: typography.fontSize.xl,
    fontWeight: '700',
    color: colors.textPrimary,
    fontFamily: typography.fontFamily.mono,
  },
  statLabel: {
    fontSize: typography.fontSize.sm,
    color: colors.textMuted,
  },
  recentScans: {
    marginTop: spacing.lg,
  },
  sectionTitle: {
    fontSize: typography.fontSize.md,
    fontWeight: '600',
    color: colors.textSecondary,
    marginBottom: spacing.md,
  },
  scanItem: {
    flexDirection: 'row',
    alignItems: 'center',
    padding: spacing.md,
    backgroundColor: colors.backgroundCard,
    borderRadius: borderRadius.md,
    marginBottom: spacing.sm,
  },
  scanStatusIcon: {
    width: 28,
    height: 28,
    borderRadius: 8,
    justifyContent: 'center',
    alignItems: 'center',
    marginRight: spacing.md,
  },
  statusValid: {
    backgroundColor: colors.successLight,
  },
  statusDuplicate: {
    backgroundColor: colors.warningLight,
  },
  statusInvalid: {
    backgroundColor: colors.errorLight,
  },
  scanInfo: {
    flex: 1,
  },
  scanName: {
    fontSize: typography.fontSize.md,
    fontWeight: '500',
    color: colors.textPrimary,
  },
  scanTicket: {
    fontSize: typography.fontSize.sm,
    color: colors.textMuted,
  },
  scanTime: {
    fontSize: typography.fontSize.sm,
    color: colors.textMuted,
    fontFamily: typography.fontFamily.mono,
  },
  emptyText: {
    fontSize: typography.fontSize.md,
    color: colors.textMuted,
    textAlign: 'center',
    paddingVertical: spacing.xxl,
  },
  bottomPadding: {
    height: 100,
  },
});
