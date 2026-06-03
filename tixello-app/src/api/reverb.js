/**
 * Minimal Pusher-protocol WebSocket client for Laravel Reverb.
 *
 * We speak the Pusher wire protocol directly over a native WebSocket —
 * no pusher-js dependency. The protocol is just JSON frames:
 *   in:  { event: 'pusher:connection_established', data: '{...}' }
 *   in:  { event: '<broadcastAs>', channel: '...', data: '{...}' }
 *   out: { event: 'pusher:subscribe', data: { channel: 'event.1.sales' } }
 *   out: { event: 'pusher:ping', data: {} }
 *
 * Used by EventContext to subscribe to `event.{id}.sales` and trigger
 * an immediate dashboard refresh when an order is confirmed anywhere
 * (web, mobile, venue-owner POS).
 *
 * Usage:
 *   const conn = createReverbConnection(realtimeConfig);
 *   conn.subscribe('event.4421.sales', 'order.confirmed', (payload) => { ... });
 *   // ...later
 *   conn.close();
 */

const PING_INTERVAL_MS = 30000;
const RECONNECT_INITIAL_MS = 1000;
const RECONNECT_MAX_MS = 30000;

function buildWsUrl(cfg) {
  const scheme = cfg.scheme === 'https' ? 'wss' : 'ws';
  const portPart =
    (cfg.scheme === 'https' && cfg.port === 443)
    || (cfg.scheme !== 'https' && cfg.port === 80)
      ? '' : ':' + cfg.port;
  const path = cfg.path || '';
  return `${scheme}://${cfg.host}${portPart}${path}/app/${cfg.app_key}?protocol=7&client=js&version=8.4.0&flash=false`;
}

function safeParse(s) {
  try { return JSON.parse(s); } catch { return null; }
}

/**
 * Open a Reverb connection. Returns an object with:
 *   subscribe(channel, eventName, callback) → registers a listener
 *   close() → tear down the connection
 * The connection auto-reconnects with exponential back-off on drop.
 */
export function createReverbConnection(realtimeConfig) {
  if (!realtimeConfig || !realtimeConfig.enabled || !realtimeConfig.app_key || !realtimeConfig.host) {
    // Real-time disabled — return a no-op handle so callers don't need
    // to special-case it. The existing 30 s polling still runs.
    return {
      subscribe: () => () => {},
      close: () => {},
    };
  }

  const url = buildWsUrl(realtimeConfig);
  let ws = null;
  let pingTimer = null;
  let reconnectTimer = null;
  let reconnectDelay = RECONNECT_INITIAL_MS;
  let closed = false;

  // listeners[channel][eventName] = Set<callback>
  const listeners = new Map();

  function ensureSubscriptionState(channel) {
    if (!listeners.has(channel)) {
      listeners.set(channel, new Map());
    }
    return listeners.get(channel);
  }

  function sendSubscribe(channel) {
    if (!ws || ws.readyState !== WebSocket.OPEN) return;
    ws.send(JSON.stringify({ event: 'pusher:subscribe', data: { channel } }));
  }

  function connect() {
    if (closed) return;
    try {
      ws = new WebSocket(url);
    } catch (e) {
      scheduleReconnect();
      return;
    }

    ws.onopen = () => {
      // No-op — wait for pusher:connection_established before subscribing.
    };

    ws.onmessage = (evt) => {
      const frame = safeParse(evt.data);
      if (!frame) return;
      const data = typeof frame.data === 'string'
        ? (frame.data ? safeParse(frame.data) : null)
        : frame.data;

      switch (frame.event) {
        case 'pusher:connection_established':
          reconnectDelay = RECONNECT_INITIAL_MS;
          // (Re)subscribe every channel we're tracking.
          for (const channel of listeners.keys()) {
            sendSubscribe(channel);
          }
          clearInterval(pingTimer);
          pingTimer = setInterval(() => {
            if (ws && ws.readyState === WebSocket.OPEN) {
              ws.send(JSON.stringify({ event: 'pusher:ping', data: {} }));
            }
          }, PING_INTERVAL_MS);
          break;
        case 'pusher:pong':
        case 'pusher_internal:subscription_succeeded':
        case 'pusher:error':
          // Handled / ignored at protocol level.
          break;
        default: {
          // Dispatch to user listeners. Pusher includes `channel` on
          // application events.
          const ch = frame.channel;
          if (!ch) break;
          const chMap = listeners.get(ch);
          if (!chMap) break;
          const cbs = chMap.get(frame.event);
          if (!cbs) break;
          for (const cb of cbs) {
            try { cb(data); } catch (e) { /* swallow */ }
          }
          break;
        }
      }
    };

    ws.onclose = () => {
      clearInterval(pingTimer);
      pingTimer = null;
      if (!closed) scheduleReconnect();
    };

    ws.onerror = () => {
      // onclose fires right after; reconnect lives there.
    };
  }

  function scheduleReconnect() {
    clearTimeout(reconnectTimer);
    reconnectTimer = setTimeout(connect, reconnectDelay);
    reconnectDelay = Math.min(reconnectDelay * 2, RECONNECT_MAX_MS);
  }

  function subscribe(channel, eventName, callback) {
    const chMap = ensureSubscriptionState(channel);
    if (!chMap.has(eventName)) {
      chMap.set(eventName, new Set());
    }
    chMap.get(eventName).add(callback);
    // If already connected, send the subscribe frame now (idempotent on
    // Reverb's side).
    sendSubscribe(channel);
    return () => {
      const m = listeners.get(channel);
      if (!m) return;
      const cbs = m.get(eventName);
      if (!cbs) return;
      cbs.delete(callback);
      if (cbs.size === 0) {
        m.delete(eventName);
      }
      if (m.size === 0) {
        listeners.delete(channel);
        if (ws && ws.readyState === WebSocket.OPEN) {
          ws.send(JSON.stringify({ event: 'pusher:unsubscribe', data: { channel } }));
        }
      }
    };
  }

  function close() {
    closed = true;
    clearTimeout(reconnectTimer);
    clearInterval(pingTimer);
    if (ws) {
      try { ws.close(); } catch {}
    }
    listeners.clear();
  }

  connect();

  return { subscribe, close };
}
