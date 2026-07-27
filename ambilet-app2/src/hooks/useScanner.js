import { useState, useCallback, useRef } from 'react';
import { checkinByCode } from '../api/participants';
import { publicApiGet } from '../api/client';
import { useApp } from '../context/AppContext';
import * as Haptics from 'expo-haptics';
import { playSuccess, playError } from '../utils/sounds';

export function useScanner() {
  const [isScanning, setIsScanning] = useState(false);
  const [scanResult, setScanResult] = useState(null);
  const lastScannedRef = useRef(null);
  const cooldownRef = useRef(false);
  const { vibrationFeedback, soundEffects, autoConfirmValid, addScan } = useApp();

  const extractCode = useCallback((data) => {
    // Extract ticket code from URL or use as-is
    if (data.includes('/t/')) {
      return data.split('/t/').pop().split('?')[0];
    }
    return data.trim();
  }, []);

  const handleBarCodeScanned = useCallback(async ({ data }) => {
    if (cooldownRef.current) return;

    const code = extractCode(data);
    if (!code) return;

    // Prevent duplicate scans of same code within 3 seconds
    if (lastScannedRef.current === code) return;
    lastScannedRef.current = code;
    cooldownRef.current = true;

    setIsScanning(true);
    setScanResult(null);

    try {
      if (autoConfirmValid) {
        // Rapid mode: directly check in
        const result = await checkinByCode(code);
        if (result.success) {
          const scanData = {
            id: Date.now(),
            status: 'valid',
            name: result.data?.customer?.name || 'Guest',
            ticket: result.data?.ticket?.ticket_type || 'Ticket',
            seat: result.data?.ticket?.seat_label,
            message: 'Welcome! Enjoy the show.',
            time: new Date().toLocaleTimeString('ro-RO', { hour: '2-digit', minute: '2-digit' }),
            code,
          };
          setScanResult(scanData);
          addScan(scanData);

          if (vibrationFeedback) {
            Haptics.notificationAsync(Haptics.NotificationFeedbackType.Success);
          }
          if (soundEffects) playSuccess();
        }
      } else {
        // Manual mode: lookup first
        const result = await publicApiGet(`/public/ticket/${code}`);
        const status = result.status;
        let scanData;

        if (status === 'valid') {
          scanData = {
            id: Date.now(),
            status: 'valid',
            name: result.attendee_name || 'Guest',
            ticket: result.ticket_type || 'Ticket',
            seat: result.seat_label,
            message: 'Valid ticket - tap to check in',
            time: new Date().toLocaleTimeString('ro-RO', { hour: '2-digit', minute: '2-digit' }),
            code,
            needsConfirm: true,
          };
          if (vibrationFeedback) {
            Haptics.notificationAsync(Haptics.NotificationFeedbackType.Success);
          }
          if (soundEffects) playSuccess();
        } else if (status === 'used') {
          scanData = {
            id: Date.now(),
            status: 'duplicate',
            name: result.attendee_name || 'Guest',
            ticket: result.ticket_type || 'Ticket',
            message: `Already scanned${result.checked_in_at ? ' at ' + new Date(result.checked_in_at).toLocaleTimeString('ro-RO', { hour: '2-digit', minute: '2-digit' }) : ''}`,
            time: new Date().toLocaleTimeString('ro-RO', { hour: '2-digit', minute: '2-digit' }),
            code,
          };
          if (vibrationFeedback) {
            Haptics.notificationAsync(Haptics.NotificationFeedbackType.Warning);
          }
          if (soundEffects) playError();
        } else {
          scanData = {
            id: Date.now(),
            status: 'invalid',
            name: null,
            ticket: null,
            message: status === 'not_found' ? 'Ticket not found' : `Ticket ${status}`,
            time: new Date().toLocaleTimeString('ro-RO', { hour: '2-digit', minute: '2-digit' }),
            code,
          };
          if (vibrationFeedback) {
            Haptics.notificationAsync(Haptics.NotificationFeedbackType.Error);
          }
          if (soundEffects) playError();
        }

        setScanResult(scanData);
        addScan(scanData);
      }
    } catch (error) {
      // Check if it's a "already checked in" response
      const message = error.message || 'Scan failed';
      const isAlreadyChecked = message.toLowerCase().includes('already') || message.toLowerCase().includes('checked');

      const scanData = {
        id: Date.now(),
        status: isAlreadyChecked ? 'duplicate' : 'invalid',
        name: null,
        ticket: null,
        message: message,
        time: new Date().toLocaleTimeString('ro-RO', { hour: '2-digit', minute: '2-digit' }),
        code,
      };
      setScanResult(scanData);
      addScan(scanData);

      if (vibrationFeedback) {
        Haptics.notificationAsync(
          isAlreadyChecked
            ? Haptics.NotificationFeedbackType.Warning
            : Haptics.NotificationFeedbackType.Error
        );
      }
      if (soundEffects) playError();
    }

    setIsScanning(false);

    // Reset cooldown after 3 seconds
    setTimeout(() => {
      cooldownRef.current = false;
      lastScannedRef.current = null;
    }, 3000);
  }, [autoConfirmValid, vibrationFeedback, soundEffects, addScan, extractCode]);

  const confirmCheckin = useCallback(async (code) => {
    try {
      const result = await checkinByCode(code);
      if (result.success) {
        setScanResult(prev => prev ? {
          ...prev,
          status: 'valid',
          message: 'Welcome! Enjoy the show.',
          needsConfirm: false,
        } : null);
        if (vibrationFeedback) {
          Haptics.notificationAsync(Haptics.NotificationFeedbackType.Success);
        }
        if (soundEffects) playSuccess();
        return true;
      }
    } catch (error) {
      setScanResult(prev => prev ? {
        ...prev,
        status: 'invalid',
        message: error.message || 'Check-in failed',
        needsConfirm: false,
      } : null);
      if (vibrationFeedback) {
        Haptics.notificationAsync(Haptics.NotificationFeedbackType.Error);
      }
      if (soundEffects) playError();
    }
    return false;
  }, [vibrationFeedback, soundEffects]);

  const clearResult = useCallback(() => {
    setScanResult(null);
    lastScannedRef.current = null;
    cooldownRef.current = false;
  }, []);

  return {
    isScanning,
    scanResult,
    handleBarCodeScanned,
    confirmCheckin,
    clearResult,
  };
}
