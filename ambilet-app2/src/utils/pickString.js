// Coerce a possibly-translatable field into a plain string. EventPilot's
// backend usually resolves the current-locale value server-side, but a few
// endpoints leak the raw `{ en: "...", ro: "..." }` JSON when the relation
// is auto-eager-loaded. Rendering that object directly inside <Text> crashes
// React with "Objects are not valid as a React child".
//
// Prefers Romanian, then English, then the first available value. Any
// non-string / non-object input (null, number, array) falls through to the
// provided fallback so callers stay safe on unusual shapes.
export function pickString(value, fallback = '') {
  if (value == null) return fallback;
  if (typeof value === 'string') return value;
  if (typeof value !== 'object') return String(value);
  if (Array.isArray(value)) {
    return value.map(v => pickString(v, '')).filter(Boolean).join(', ') || fallback;
  }
  if (typeof value.ro === 'string' && value.ro) return value.ro;
  if (typeof value.en === 'string' && value.en) return value.en;
  const first = Object.values(value).find(v => typeof v === 'string' && v);
  return first || fallback;
}
