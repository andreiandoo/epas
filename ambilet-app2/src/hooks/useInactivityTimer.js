import { useCallback, useEffect, useRef } from 'react';
import { AppState } from 'react-native';
import AsyncStorage from '@react-native-async-storage/async-storage';

// Inactivity timer — fires `onExpire` after `timeoutMs` of no user activity.
// Callers wire `bumpActivity` into a top-level responder (touch/scroll) so
// every operator interaction resets the countdown.
//
// Pause: while ANY key in `pauseReasons` is truthy the timer is frozen
// (e.g. card-confirmation modal open, active scan in progress). This
// avoids kicking the operator out mid-transaction.
//
// Persistence: last activity timestamp is mirrored to AsyncStorage. If the
// app cold-starts and the previous "last activity" is older than the
// timeout, we treat it as expired immediately — closing the app doesn't
// give an operator an unlocked session forever.

const STORAGE_KEY = 'last_activity_ts';

export function useInactivityTimer({ timeoutMs, enabled, onExpire, pauseWhen }) {
  const timerRef = useRef(null);
  const pausedRef = useRef(false);

  const clearTimer = useCallback(() => {
    if (timerRef.current) {
      clearTimeout(timerRef.current);
      timerRef.current = null;
    }
  }, []);

  const scheduleTimer = useCallback(() => {
    clearTimer();
    if (!enabled || pausedRef.current) return;
    timerRef.current = setTimeout(() => {
      try { onExpire?.(); } catch {}
    }, timeoutMs);
  }, [enabled, timeoutMs, onExpire, clearTimer]);

  const bumpActivity = useCallback(() => {
    if (!enabled) return;
    try { AsyncStorage.setItem(STORAGE_KEY, String(Date.now())).catch(() => {}); } catch {}
    scheduleTimer();
  }, [enabled, scheduleTimer]);

  // Pause / resume
  useEffect(() => {
    const nextPaused = !!pauseWhen;
    if (nextPaused === pausedRef.current) return;
    pausedRef.current = nextPaused;
    if (nextPaused) {
      clearTimer();
    } else {
      scheduleTimer();
    }
  }, [pauseWhen, scheduleTimer, clearTimer]);

  // Cold-start check + timer arm on mount
  useEffect(() => {
    if (!enabled) {
      clearTimer();
      return;
    }
    (async () => {
      try {
        const raw = await AsyncStorage.getItem(STORAGE_KEY);
        const last = raw ? parseInt(raw, 10) : 0;
        if (last && Date.now() - last >= timeoutMs) {
          onExpire?.();
          return;
        }
      } catch {}
      scheduleTimer();
    })();
    return clearTimer;
  }, [enabled, timeoutMs, onExpire, scheduleTimer, clearTimer]);

  // AppState — when app returns from background, check if the timeout
  // elapsed WHILE we were away; if so, expire immediately.
  useEffect(() => {
    if (!enabled) return;
    const appStateRef = { value: AppState.currentState };
    const sub = AppState.addEventListener('change', async (next) => {
      if (appStateRef.value.match(/inactive|background/) && next === 'active') {
        try {
          const raw = await AsyncStorage.getItem(STORAGE_KEY);
          const last = raw ? parseInt(raw, 10) : Date.now();
          if (Date.now() - last >= timeoutMs) {
            onExpire?.();
            appStateRef.value = next;
            return;
          }
        } catch {}
        scheduleTimer();
      } else if (next.match(/inactive|background/)) {
        clearTimer();
      }
      appStateRef.value = next;
    });
    return () => sub.remove();
  }, [enabled, timeoutMs, onExpire, scheduleTimer, clearTimer]);

  return { bumpActivity };
}
