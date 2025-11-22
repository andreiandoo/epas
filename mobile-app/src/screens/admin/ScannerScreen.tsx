import React, { useState, useEffect } from 'react';
import {
  View,
  Text,
  StyleSheet,
  TouchableOpacity,
  TextInput,
  Alert,
  ActivityIndicator,
  Platform,
  Vibration,
} from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { useAuthStore } from '../../store/authStore';
import { adminService } from '../../services/api';
import { getOfflineStore } from '../../services/offlineDb';

// Conditional import for barcode scanner
let BarCodeScanner: any = null;
if (Platform.OS !== 'web') {
  BarCodeScanner = require('expo-barcode-scanner').BarCodeScanner;
}

export function AdminScannerScreen() {
  const auth = useAuthStore((state) => state.auth);
  const [hasPermission, setHasPermission] = useState<boolean | null>(null);
  const [scanned, setScanned] = useState(false);
  const [manualCode, setManualCode] = useState('');
  const [loading, setLoading] = useState(false);
  const [showCamera, setShowCamera] = useState(false);
  const [lastResult, setLastResult] = useState<{
    valid: boolean;
    message: string;
    offline?: boolean;
  } | null>(null);
  const [offlineStats, setOfflineStats] = useState({ cached: 0, pending: 0 });

  useEffect(() => {
    if (Platform.OS !== 'web' && BarCodeScanner) {
      (async () => {
        const { status } = await BarCodeScanner.requestPermissionsAsync();
        setHasPermission(status === 'granted');
      })();
    }

    // Load offline stats
    loadOfflineStats();
  }, []);

  const loadOfflineStats = async () => {
    const store = getOfflineStore();
    const stats = await store.getOfflineStats();
    setOfflineStats(stats);
  };

  const handleBarCodeScanned = async ({ data }: { data: string }) => {
    setScanned(true);
    Vibration.vibrate(100);
    await handleValidate(data);
  };

  const handleValidate = async (qrData: string) => {
    if (!auth || !qrData) return;

    setLoading(true);
    const store = getOfflineStore();

    try {
      // Try offline validation first
      const cachedTicket = await store.findTicketByQRData(qrData);

      if (cachedTicket) {
        if (cachedTicket.status === 'used') {
          setLastResult({
            valid: false,
            message: `Already checked in${cachedTicket.checked_in_at ? ` at ${cachedTicket.checked_in_at}` : ''}`,
            offline: true,
          });
          Vibration.vibrate([0, 200, 100, 200]);
          return;
        }

        if (cachedTicket.status === 'void') {
          setLastResult({
            valid: false,
            message: 'Ticket has been voided',
            offline: true,
          });
          Vibration.vibrate([0, 200, 100, 200]);
          return;
        }

        // Valid ticket - mark as used offline
        await store.markTicketUsed(cachedTicket.code, 'mobile-gate');
        setLastResult({
          valid: true,
          message: `${cachedTicket.ticket_type || 'Ticket'} - ${cachedTicket.customer_email || 'Guest'}`,
          offline: true,
        });
        Vibration.vibrate(100);
        loadOfflineStats();
        return;
      }

      // Try online validation
      const result = await adminService.validateTicket(auth.token, qrData, 'mobile-gate');
      setLastResult({
        valid: result.valid,
        message: result.message,
        offline: false,
      });

      if (result.valid) {
        Vibration.vibrate(100);
      } else {
        Vibration.vibrate([0, 200, 100, 200]);
      }
    } catch (e: any) {
      // Network error - try offline only
      const cachedTicket = await store.findTicketByQRData(qrData);
      if (cachedTicket && cachedTicket.status === 'valid') {
        await store.markTicketUsed(cachedTicket.code, 'mobile-gate');
        setLastResult({
          valid: true,
          message: `${cachedTicket.ticket_type} (Offline)`,
          offline: true,
        });
        Vibration.vibrate(100);
      } else {
        setLastResult({
          valid: false,
          message: 'Ticket not found in offline cache',
          offline: true,
        });
        Vibration.vibrate([0, 200, 100, 200]);
      }
    } finally {
      setLoading(false);
      setManualCode('');
    }
  };

  const handleManualSubmit = () => {
    if (manualCode.trim()) {
      handleValidate(manualCode.trim());
    }
  };

  const renderCamera = () => {
    if (Platform.OS === 'web') {
      return (
        <View style={styles.cameraPlaceholder}>
          <Ionicons name="camera" size={64} color="#9ca3af" />
          <Text style={styles.placeholderText}>
            Camera not available on web
          </Text>
        </View>
      );
    }

    if (hasPermission === null) {
      return (
        <View style={styles.cameraPlaceholder}>
          <ActivityIndicator size="large" color="#6366f1" />
          <Text style={styles.placeholderText}>Requesting camera permission...</Text>
        </View>
      );
    }

    if (hasPermission === false) {
      return (
        <View style={styles.cameraPlaceholder}>
          <Ionicons name="camera-outline" size={64} color="#ef4444" />
          <Text style={styles.placeholderText}>Camera permission denied</Text>
          <TouchableOpacity
            style={styles.retryButton}
            onPress={() => BarCodeScanner?.requestPermissionsAsync()}
          >
            <Text style={styles.retryButtonText}>Grant Permission</Text>
          </TouchableOpacity>
        </View>
      );
    }

    if (!showCamera) {
      return (
        <TouchableOpacity
          style={styles.cameraPlaceholder}
          onPress={() => setShowCamera(true)}
        >
          <Ionicons name="camera" size={64} color="#6366f1" />
          <Text style={styles.startScanText}>Tap to Start Scanning</Text>
        </TouchableOpacity>
      );
    }

    return (
      <View style={styles.cameraContainer}>
        <BarCodeScanner
          onBarCodeScanned={scanned ? undefined : handleBarCodeScanned}
          style={StyleSheet.absoluteFillObject}
        />
        <View style={styles.scanOverlay}>
          <View style={styles.scanFrame} />
        </View>
        {scanned && (
          <TouchableOpacity
            style={styles.scanAgainButton}
            onPress={() => setScanned(false)}
          >
            <Text style={styles.scanAgainText}>Tap to Scan Again</Text>
          </TouchableOpacity>
        )}
      </View>
    );
  };

  return (
    <View style={styles.container}>
      {renderCamera()}

      <View style={styles.manualEntry}>
        <Text style={styles.sectionTitle}>Manual Entry</Text>
        <View style={styles.inputRow}>
          <TextInput
            style={styles.input}
            placeholder="Enter ticket code"
            value={manualCode}
            onChangeText={setManualCode}
            autoCapitalize="characters"
          />
          <TouchableOpacity
            style={[styles.validateButton, (loading || !manualCode.trim()) && styles.buttonDisabled]}
            onPress={handleManualSubmit}
            disabled={loading || !manualCode.trim()}
          >
            {loading ? (
              <ActivityIndicator color="#fff" size="small" />
            ) : (
              <Ionicons name="checkmark" size={24} color="#fff" />
            )}
          </TouchableOpacity>
        </View>
      </View>

      {lastResult && (
        <View
          style={[
            styles.resultCard,
            lastResult.valid ? styles.resultValid : styles.resultInvalid,
          ]}
        >
          <Ionicons
            name={lastResult.valid ? 'checkmark-circle' : 'close-circle'}
            size={32}
            color={lastResult.valid ? '#10b981' : '#ef4444'}
          />
          <View style={styles.resultContent}>
            <Text style={styles.resultTitle}>
              {lastResult.valid ? 'Valid Ticket' : 'Invalid Ticket'}
              {lastResult.offline && ' (Offline)'}
            </Text>
            <Text style={styles.resultMessage}>{lastResult.message}</Text>
          </View>
        </View>
      )}

      <View style={styles.offlineStatus}>
        <Ionicons name="cloud-offline-outline" size={16} color="#6b7280" />
        <Text style={styles.offlineText}>
          {offlineStats.cached} cached • {offlineStats.pending} pending sync
        </Text>
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: '#f9fafb',
  },
  cameraContainer: {
    height: 300,
    backgroundColor: '#000',
  },
  cameraPlaceholder: {
    height: 250,
    backgroundColor: '#1f2937',
    justifyContent: 'center',
    alignItems: 'center',
  },
  placeholderText: {
    color: '#9ca3af',
    fontSize: 16,
    marginTop: 16,
  },
  startScanText: {
    color: '#6366f1',
    fontSize: 16,
    fontWeight: '600',
    marginTop: 16,
  },
  scanOverlay: {
    ...StyleSheet.absoluteFillObject,
    justifyContent: 'center',
    alignItems: 'center',
  },
  scanFrame: {
    width: 250,
    height: 250,
    borderWidth: 2,
    borderColor: '#6366f1',
    borderRadius: 16,
    backgroundColor: 'transparent',
  },
  scanAgainButton: {
    position: 'absolute',
    bottom: 20,
    left: 20,
    right: 20,
    backgroundColor: 'rgba(99, 102, 241, 0.9)',
    padding: 16,
    borderRadius: 8,
    alignItems: 'center',
  },
  scanAgainText: {
    color: '#fff',
    fontSize: 16,
    fontWeight: '600',
  },
  retryButton: {
    marginTop: 16,
    backgroundColor: '#6366f1',
    paddingHorizontal: 20,
    paddingVertical: 10,
    borderRadius: 8,
  },
  retryButtonText: {
    color: '#fff',
    fontWeight: '600',
  },
  manualEntry: {
    padding: 16,
  },
  sectionTitle: {
    fontSize: 16,
    fontWeight: '600',
    color: '#374151',
    marginBottom: 12,
  },
  inputRow: {
    flexDirection: 'row',
    gap: 12,
  },
  input: {
    flex: 1,
    backgroundColor: '#fff',
    borderWidth: 1,
    borderColor: '#d1d5db',
    borderRadius: 8,
    padding: 12,
    fontSize: 16,
  },
  validateButton: {
    backgroundColor: '#6366f1',
    borderRadius: 8,
    width: 48,
    justifyContent: 'center',
    alignItems: 'center',
  },
  buttonDisabled: {
    backgroundColor: '#a5b4fc',
  },
  resultCard: {
    flexDirection: 'row',
    alignItems: 'center',
    marginHorizontal: 16,
    padding: 16,
    borderRadius: 12,
    marginBottom: 16,
  },
  resultValid: {
    backgroundColor: '#d1fae5',
  },
  resultInvalid: {
    backgroundColor: '#fee2e2',
  },
  resultContent: {
    marginLeft: 12,
    flex: 1,
  },
  resultTitle: {
    fontSize: 16,
    fontWeight: '600',
    color: '#111827',
  },
  resultMessage: {
    fontSize: 14,
    color: '#6b7280',
    marginTop: 2,
  },
  offlineStatus: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    padding: 12,
    backgroundColor: '#f3f4f6',
    marginHorizontal: 16,
    borderRadius: 8,
  },
  offlineText: {
    marginLeft: 8,
    fontSize: 12,
    color: '#6b7280',
  },
});
