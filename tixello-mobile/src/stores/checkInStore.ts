import { create } from 'zustand';
import { ScanHistoryItem, CheckInResult, ScannerSettings } from '../types';

interface CheckInState {
  isScanning: boolean;
  lastScanResult: CheckInResult | null;
  scanHistory: ScanHistoryItem[];
  totalScans: number;
  validScans: number;
  duplicateScans: number;
  invalidScans: number;
  settings: ScannerSettings;

  // Actions
  setScanning: (scanning: boolean) => void;
  setScanResult: (result: CheckInResult | null) => void;
  addToHistory: (item: ScanHistoryItem) => void;
  clearHistory: () => void;
  updateSettings: (settings: Partial<ScannerSettings>) => void;
  resetStats: () => void;
}

export const useCheckInStore = create<CheckInState>((set, get) => ({
  isScanning: false,
  lastScanResult: null,
  scanHistory: [],
  totalScans: 0,
  validScans: 0,
  duplicateScans: 0,
  invalidScans: 0,
  settings: {
    vibration_feedback: true,
    sound_effects: true,
    auto_confirm_valid: false,
  },

  setScanning: (isScanning) => set({ isScanning }),

  setScanResult: (lastScanResult) => set({ lastScanResult }),

  addToHistory: (item) => {
    const { scanHistory, totalScans, validScans, duplicateScans, invalidScans } = get();

    const updates: Partial<CheckInState> = {
      scanHistory: [item, ...scanHistory].slice(0, 100), // Keep last 100
      totalScans: totalScans + 1,
    };

    if (item.status === 'valid') {
      updates.validScans = validScans + 1;
    } else if (item.status === 'duplicate') {
      updates.duplicateScans = duplicateScans + 1;
    } else {
      updates.invalidScans = invalidScans + 1;
    }

    set(updates);
  },

  clearHistory: () => {
    set({ scanHistory: [] });
  },

  updateSettings: (newSettings) => {
    const { settings } = get();
    set({ settings: { ...settings, ...newSettings } });
  },

  resetStats: () => {
    set({
      totalScans: 0,
      validScans: 0,
      duplicateScans: 0,
      invalidScans: 0,
      scanHistory: [],
    });
  },
}));
