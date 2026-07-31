/** Persisted session (token + active context) using expo-secure-store. */
import * as SecureStore from 'expo-secure-store';
import type { ContextKind } from '@/theme/colors';

const KEY = 'tixello.session.v1';

export interface PersistedSession {
  token: string;
  contextKind: ContextKind;
  organizerId: number;
  organizerName: string;
  accent?: string | null;
  marketplaceKey?: string | null;
}

export async function loadSession(): Promise<PersistedSession | null> {
  try {
    const raw = await SecureStore.getItemAsync(KEY);
    return raw ? (JSON.parse(raw) as PersistedSession) : null;
  } catch {
    return null;
  }
}

export async function saveSession(s: PersistedSession): Promise<void> {
  await SecureStore.setItemAsync(KEY, JSON.stringify(s));
}

export async function clearSession(): Promise<void> {
  await SecureStore.deleteItemAsync(KEY);
}
