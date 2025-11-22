import React, { useState } from 'react';
import {
  View,
  Text,
  StyleSheet,
  TouchableOpacity,
  TextInput,
  Alert,
  ActivityIndicator,
  Platform,
} from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { useAuthStore } from '../../store/authStore';
import { adminService } from '../../services/api';

export function AdminScannerScreen() {
  const auth = useAuthStore((state) => state.auth);
  const [manualCode, setManualCode] = useState('');
  const [loading, setLoading] = useState(false);
  const [lastResult, setLastResult] = useState<{
    valid: boolean;
    message: string;
  } | null>(null);

  const handleValidate = async (qrData: string) => {
    if (!auth || !qrData) return;

    setLoading(true);
    try {
      const result = await adminService.validateTicket(auth.token, qrData);
      setLastResult(result);

      if (result.valid) {
        Alert.alert('Valid', result.message);
      } else {
        Alert.alert('Invalid', result.message);
      }
    } catch (e: any) {
      Alert.alert('Error', e.message || 'Validation failed');
      setLastResult({ valid: false, message: e.message || 'Error' });
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

  return (
    <View style={styles.container}>
      <View style={styles.cameraPlaceholder}>
        <Ionicons name="camera" size={64} color="#9ca3af" />
        <Text style={styles.placeholderText}>
          Camera scanner available on device
        </Text>
        <Text style={styles.placeholderSubtext}>
          Use manual entry below for web testing
        </Text>
      </View>

      <View style={styles.manualEntry}>
        <Text style={styles.sectionTitle}>Manual Entry</Text>
        <View style={styles.inputRow}>
          <TextInput
            style={styles.input}
            placeholder="Enter ticket code or QR data"
            value={manualCode}
            onChangeText={setManualCode}
            autoCapitalize="none"
          />
          <TouchableOpacity
            style={[styles.validateButton, loading && styles.buttonDisabled]}
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
            </Text>
            <Text style={styles.resultMessage}>{lastResult.message}</Text>
          </View>
        </View>
      )}

      <View style={styles.instructions}>
        <Text style={styles.instructionsTitle}>How to Scan</Text>
        <View style={styles.instructionItem}>
          <Ionicons name="qr-code-outline" size={20} color="#6b7280" />
          <Text style={styles.instructionText}>
            Point camera at ticket QR code
          </Text>
        </View>
        <View style={styles.instructionItem}>
          <Ionicons name="flash-outline" size={20} color="#6b7280" />
          <Text style={styles.instructionText}>
            Ensure good lighting for best results
          </Text>
        </View>
        <View style={styles.instructionItem}>
          <Ionicons name="wifi-outline" size={20} color="#6b7280" />
          <Text style={styles.instructionText}>
            Works offline with downloaded tickets
          </Text>
        </View>
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: '#f9fafb',
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
  placeholderSubtext: {
    color: '#6b7280',
    fontSize: 14,
    marginTop: 4,
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
  instructions: {
    padding: 16,
  },
  instructionsTitle: {
    fontSize: 16,
    fontWeight: '600',
    color: '#374151',
    marginBottom: 12,
  },
  instructionItem: {
    flexDirection: 'row',
    alignItems: 'center',
    marginBottom: 12,
  },
  instructionText: {
    marginLeft: 12,
    fontSize: 14,
    color: '#6b7280',
  },
});
