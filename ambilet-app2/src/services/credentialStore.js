// credentialStore — persist last-used email + password in SecureStore so
// the login form pre-fills them on re-open. Two goals:
//   1. After INACTIVITY auto-logout, the operator hits Autentificare and
//      is back in without re-typing credentials.
//   2. After EXPLICIT sign-out (from Settings → „Încheie Tura &
//      Deconectare"), credentials are wiped and the form is blank next
//      time — as a real handover to another operator would expect.
//
// Storage: expo-secure-store (Keystore on Android, Keychain on iOS).
// SecureStore is device-only and encrypted at rest. Values are never
// transmitted anywhere — only used to pre-fill the login form.
import * as SecureStore from 'expo-secure-store';

const KEY_EMAIL = 'ambilet_last_email';
const KEY_PASSWORD = 'ambilet_last_password';

export async function saveCredentials(email, password) {
  try {
    if (email) await SecureStore.setItemAsync(KEY_EMAIL, String(email));
    if (password) await SecureStore.setItemAsync(KEY_PASSWORD, String(password));
  } catch {
    // Best-effort — a failed save shouldn't block login itself.
  }
}

export async function getSavedCredentials() {
  try {
    const email = await SecureStore.getItemAsync(KEY_EMAIL);
    const password = await SecureStore.getItemAsync(KEY_PASSWORD);
    return { email: email || '', password: password || '' };
  } catch {
    return { email: '', password: '' };
  }
}

export async function clearSavedCredentials() {
  try {
    await SecureStore.deleteItemAsync(KEY_EMAIL);
    await SecureStore.deleteItemAsync(KEY_PASSWORD);
  } catch {}
}
