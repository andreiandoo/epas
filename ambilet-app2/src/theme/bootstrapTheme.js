// Called ONCE from App.js's top-level import chain (before the first
// StyleSheet.create runs) to switch the palette based on the user's
// persisted preference. Doing this synchronously at module-import time is
// impossible with AsyncStorage — we settle for "apply as soon as we know"
// and warn the operator in Settings that theme changes take effect on the
// next full restart of the app.
import AsyncStorage from '@react-native-async-storage/async-storage';
import { colors } from './colors';
import { applyPalette } from './palettes';

const STORAGE_KEY = 'theme_mode';

export async function loadPersistedTheme() {
  try {
    const raw = await AsyncStorage.getItem(STORAGE_KEY);
    if (raw && ['light', 'dark', 'lowLight'].includes(raw)) {
      applyPalette(colors, raw);
      return raw;
    }
  } catch {}
  return 'light';
}

export async function persistThemeMode(mode) {
  try {
    await AsyncStorage.setItem(STORAGE_KEY, mode);
  } catch {}
}
