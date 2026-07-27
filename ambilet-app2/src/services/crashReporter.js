// Sentry integration. Optional at runtime — if the module isn't linked yet
// (dev-only prebuild not done, or DSN not configured) every helper below is
// a silent no-op so the app boots normally.
//
// Configuration:
//   - Set env EXPO_PUBLIC_SENTRY_DSN in .env (or hardcode in app.json extra)
//   - Prod DSN vs dev DSN can differ via environment
//
// Data hygiene:
//   - `beforeSend` strips password / token / email fields from any captured
//     event so PII never leaves the device
//   - Breadcrumbs cap at 50 (default) — enough for a crash trail without
//     hoarding memory

let Sentry = null;
try {
  Sentry = require('@sentry/react-native');
} catch (e) {
  Sentry = null;
}

const PII_KEYS = ['password', 'token', 'api_key', 'authorization', 'email', 'phone'];

function scrubObject(obj) {
  if (!obj || typeof obj !== 'object') return obj;
  const out = Array.isArray(obj) ? [] : {};
  for (const [k, v] of Object.entries(obj)) {
    if (PII_KEYS.some(p => k.toLowerCase().includes(p))) {
      out[k] = '[redacted]';
    } else if (v && typeof v === 'object') {
      out[k] = scrubObject(v);
    } else {
      out[k] = v;
    }
  }
  return out;
}

let initialized = false;

export function initCrashReporter({ dsn, release, environment = 'production' } = {}) {
  if (!Sentry || initialized) return;
  if (!dsn) return; // no-op when the operator hasn't wired a DSN
  try {
    Sentry.init({
      dsn,
      release,
      environment,
      // Traces = performance sampling. 20% is a decent starting point.
      tracesSampleRate: 0.2,
      // Auto session tracking for release health
      autoSessionTracking: true,
      beforeSend(event) {
        try {
          if (event.request) event.request = scrubObject(event.request);
          if (event.extra) event.extra = scrubObject(event.extra);
          if (event.contexts) event.contexts = scrubObject(event.contexts);
        } catch {}
        return event;
      },
      beforeBreadcrumb(crumb) {
        try {
          if (crumb.data) crumb.data = scrubObject(crumb.data);
        } catch {}
        return crumb;
      },
    });
    initialized = true;
  } catch (e) {
    // Silent — a crashed crash reporter shouldn't cascade
  }
}

// Update user context (call after login / logout).
export function setCrashUser(user, organizerId) {
  if (!Sentry || !initialized) return;
  try {
    if (!user) {
      Sentry.setUser(null);
      return;
    }
    // Only stable IDs — never name/email/phone (PII).
    Sentry.setUser({
      id: String(user.id),
      role: user?.team_member?.role || 'owner',
      organizer_id: organizerId ? String(organizerId) : undefined,
    });
  } catch {}
}

// Manual capture for handled errors that we still want to know about.
export function captureError(error, context = {}) {
  if (!Sentry || !initialized) {
    // Fallback to console so dev builds still see it.
    console.error('[crashReporter]', error, context);
    return;
  }
  try {
    Sentry.captureException(error, { extra: scrubObject(context) });
  } catch {}
}

export function addBreadcrumb(crumb) {
  if (!Sentry || !initialized) return;
  try {
    Sentry.addBreadcrumb(crumb);
  } catch {}
}

// HOC helper: wraps the root App component with Sentry's error boundary
// AND profiler so react-render performance is tracked. No-op when Sentry
// isn't available.
export function wrapRootApp(RootComponent) {
  if (!Sentry) return RootComponent;
  try {
    return Sentry.wrap(RootComponent);
  } catch {
    return RootComponent;
  }
}
