// Offline sales queue — persisted list of orders queued while the app was
// offline. Each entry carries a client-generated `idempotency_key` so a
// retry storm can't double-book tickets: backend short-circuits on a key
// it has already seen.
//
// Storage shape (AsyncStorage key `offline_sales_queue`):
//   [
//     {
//       idempotency_key: 'uuid-v4',
//       event_id: 123,
//       payload: { ... /* orderPayload */ },
//       created_at: 172xxxxxxxx,
//       retries: 0,
//       last_error: null,
//     },
//     ...
//   ]
//
// This service is stateless — the AppContext owns the runtime state and
// calls these helpers.
import AsyncStorage from '@react-native-async-storage/async-storage';

const STORAGE_KEY = 'offline_sales_queue';
const MAX_RETRIES = 20; // beyond this we surface a hard alert

let Crypto = null;
try { Crypto = require('expo-crypto'); } catch {}

// UUID v4 via expo-crypto when available. Falls back to a Math.random
// suffix if the module isn't linked (dev without prebuild). Not
// cryptographically strong in the fallback path, but idempotency only
// needs practical uniqueness — a collision would just fetch someone
// else's already-processed order, which the backend still validates.
export function generateIdempotencyKey() {
  if (Crypto?.randomUUID) return Crypto.randomUUID();
  const rand = (n) => Math.random().toString(16).slice(2, 2 + n);
  return `${rand(8)}-${rand(4)}-4${rand(3)}-${rand(4)}-${rand(12)}`;
}

export async function loadQueue() {
  try {
    const raw = await AsyncStorage.getItem(STORAGE_KEY);
    if (!raw) return [];
    const parsed = JSON.parse(raw);
    return Array.isArray(parsed) ? parsed : [];
  } catch {
    return [];
  }
}

async function saveQueue(queue) {
  try {
    await AsyncStorage.setItem(STORAGE_KEY, JSON.stringify(queue));
  } catch {
    // Storage full or corrupted — nothing sane to do here without
    // dropping the sale, so we let the caller surface via badge.
  }
}

export async function enqueueOfflineSale({ eventId, payload }) {
  const queue = await loadQueue();
  const entry = {
    idempotency_key: generateIdempotencyKey(),
    event_id: eventId,
    payload: { ...payload, idempotency_key: undefined /* set on flush */ },
    created_at: Date.now(),
    retries: 0,
    last_error: null,
  };
  queue.push(entry);
  await saveQueue(queue);
  return entry;
}

export async function removeFromQueue(idempotencyKey) {
  const queue = await loadQueue();
  const next = queue.filter(e => e.idempotency_key !== idempotencyKey);
  await saveQueue(next);
  return next;
}

export async function markRetried(idempotencyKey, errorMessage) {
  const queue = await loadQueue();
  const next = queue.map(e =>
    e.idempotency_key === idempotencyKey
      ? { ...e, retries: (e.retries || 0) + 1, last_error: errorMessage || null }
      : e
  );
  await saveQueue(next);
  return next;
}

export function isPermanentlyFailed(entry) {
  return (entry?.retries || 0) >= MAX_RETRIES;
}

export async function countQueued() {
  const queue = await loadQueue();
  return queue.length;
}
