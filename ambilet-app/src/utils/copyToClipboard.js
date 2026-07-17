// Thin wrapper around expo-clipboard so screens can call one function
// without directly touching a native module. Silently no-ops if the
// module is unavailable (e.g. web preview) so calling code never has
// to guard around it.
import { ToastAndroid, Platform, Alert } from 'react-native';

let Clipboard = null;
try {
  Clipboard = require('expo-clipboard');
} catch (e) {
  Clipboard = null;
}

export async function copyToClipboard(value, label) {
  if (!value) return;
  const text = String(value);
  try {
    if (Clipboard?.setStringAsync) {
      await Clipboard.setStringAsync(text);
    } else if (Clipboard?.setString) {
      Clipboard.setString(text);
    } else {
      return;
    }
    const msg = label ? `${label}: ${text} copiat` : `${text} copiat`;
    if (Platform.OS === 'android') {
      ToastAndroid.show(msg, ToastAndroid.SHORT);
    } else {
      // iOS has no native toast; a lightweight alert is the least intrusive
      // fallback that still confirms the copy happened.
      Alert.alert('Copiat', msg);
    }
  } catch (e) {
    // Silent — the operator can retry, and failing clipboard shouldn't
    // block whatever screen they were on.
  }
}
