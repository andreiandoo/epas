// Centralised time formatting — everything the operator sees should be
// Europe/Bucharest, not the device's timezone. Ambilet is a Romanian
// product used by Romanian teams; the server runs UTC and returns ISO
// 8601 strings with offset ("2026-08-24T14:02:00+00:00"), and mobile
// devices can be set to any timezone (traveling ops, test emulators,
// tablets in Romanian airports set to Vienna, …). Pinning to RO here
// guarantees a scan at 17:02 RO shows as 17:02 in the app no matter
// what the device thinks.
//
// Input: ISO 8601 (with or without offset), Date object, epoch millis,
//        or the legacy "Y-m-d H:i:s" naked string (parsed as UTC —
//        matches the old backend response before the timezone fix).
// Output: string, or empty string if input is unusable.

const RO_TZ = 'Europe/Bucharest';

/**
 * Parse anything a backend / frontend might hand us into a Date. Returns
 * null when the input is unusable so callers can short-circuit to "".
 */
function toDate(input) {
  if (input == null || input === '') return null;
  if (input instanceof Date) {
    return isNaN(input.getTime()) ? null : input;
  }
  if (typeof input === 'number' && Number.isFinite(input)) {
    const d = new Date(input);
    return isNaN(d.getTime()) ? null : d;
  }
  const s = String(input).trim();
  if (!s) return null;
  // Legacy naked "Y-m-d H:i:s" from old backend responses = UTC. Add "Z"
  // so JS parses it as UTC instead of local time.
  const normalized = /^\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}(:\d{2})?$/.test(s)
    ? s.replace(' ', 'T') + 'Z'
    : s;
  const d = new Date(normalized);
  return isNaN(d.getTime()) ? null : d;
}

/** HH:MM in RO timezone. Empty string if input is unusable. */
export function formatTime(input) {
  const d = toDate(input);
  if (!d) return '';
  return d.toLocaleTimeString('ro-RO', {
    timeZone: RO_TZ,
    hour: '2-digit',
    minute: '2-digit',
    hour12: false,
  });
}

/** DD.MM.YYYY in RO timezone. Empty string if input is unusable. */
export function formatDate(input) {
  const d = toDate(input);
  if (!d) return '';
  return d.toLocaleDateString('ro-RO', {
    timeZone: RO_TZ,
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
  });
}

/** DD.MM.YYYY HH:MM in RO timezone. Empty string if input is unusable. */
export function formatDateTime(input) {
  const d = toDate(input);
  if (!d) return '';
  return `${formatDate(d)} ${formatTime(d)}`;
}

/**
 * Compact check-in / scan stamp. Same day → "HH:MM"; earlier this year →
 * "DD.MM HH:MM"; older → "DD.MM.YYYY HH:MM". Empty string if unusable.
 */
export function formatCheckInStamp(input) {
  const d = toDate(input);
  if (!d) return '';
  const now = new Date();
  const sameDay = d.toDateString() === now.toDateString();
  if (sameDay) return formatTime(d);
  const sameYear = d.getFullYear() === now.getFullYear();
  const dd = d.toLocaleDateString('ro-RO', {
    timeZone: RO_TZ,
    day: '2-digit',
    month: '2-digit',
    ...(sameYear ? {} : { year: 'numeric' }),
  });
  return `${dd} ${formatTime(d)}`;
}

/** Minutes elapsed since `input`. Negative if in the future. */
export function minutesSince(input) {
  const d = toDate(input);
  if (!d) return null;
  return Math.floor((Date.now() - d.getTime()) / 60_000);
}

/**
 * "acum X min" / "acum X h" / "acum Y zile" / a formatted stamp for
 * older values. Handles the "same session" case (0 minutes → "acum câteva
 * secunde") because a duplicate scan usually happens within seconds.
 */
export function formatRelativeAgo(input) {
  const d = toDate(input);
  if (!d) return '';
  const diffMs = Date.now() - d.getTime();
  if (diffMs < 0) return formatDateTime(d); // future — fall back to absolute
  const sec = Math.floor(diffMs / 1000);
  if (sec < 45) return 'acum câteva secunde';
  const min = Math.floor(diffMs / 60_000);
  if (min < 60) return `acum ${min} min`;
  const hr = Math.floor(diffMs / 3_600_000);
  if (hr < 24) return `acum ${hr} h`;
  const days = Math.floor(diffMs / 86_400_000);
  if (days < 7) return `acum ${days} ${days === 1 ? 'zi' : 'zile'}`;
  return formatDateTime(d);
}
