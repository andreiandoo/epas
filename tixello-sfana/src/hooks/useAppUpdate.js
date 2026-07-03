// useAppUpdate — poll /api/app-version-sfana si expune starea update-ului.
//
// - Prima verificare la mount (~2s dupa render ca sa nu blocheze primul frame).
// - Repolare la 30 min (kiosk sta zile intregi aprins).
// - Repolare pe schimbare AppState -> 'active' (operatorul redeschide app-ul).
// - Fail-silent: daca reteaua e down, ramanem in ultima stare cunoscuta.
//
// Returneaza:
//   {
//     hasUpdate: boolean,
//     latestVersion: string | null,
//     downloadUrl: string | null,
//     forceUpdate: boolean,
//   }
//
// UI (KioskScreen, Hub, etc.) foloseste `hasUpdate` ca sa afiseze banner.

import { useEffect, useRef, useState } from 'react';
import { AppState } from 'react-native';
import { APP_VERSION, VERSION_CHECK_URL, isNewerVersion } from '../config/version';

const POLL_INTERVAL_MS = 30 * 60 * 1000; // 30 min
const INITIAL_DELAY_MS = 2000;

export function useAppUpdate() {
  const [state, setState] = useState({
    hasUpdate: false,
    latestVersion: null,
    downloadUrl: null,
    forceUpdate: false,
  });
  const timerRef = useRef(null);
  const mountedRef = useRef(true);

  useEffect(() => {
    mountedRef.current = true;

    const check = async () => {
      try {
        // AbortController cu timeout 8s — pe kiosk cu conexiune slaba nu vrem
        // sa ramanem blocati intr-un fetch etern.
        const ctl = new AbortController();
        const to = setTimeout(() => ctl.abort(), 8000);
        const resp = await fetch(VERSION_CHECK_URL, { signal: ctl.signal });
        clearTimeout(to);
        if (!resp.ok) return;
        const json = await resp.json();
        if (!mountedRef.current) return;
        const latest = json?.latest_version || null;
        const downloadUrl = json?.download_url || null;
        const forceUpdate = !!json?.force_update;
        setState({
          hasUpdate: isNewerVersion(latest, APP_VERSION),
          latestVersion: latest,
          downloadUrl,
          forceUpdate,
        });
      } catch (e) {
        // Fail-silent — pastram starea anterioara.
      }
    };

    // Verificare initiala amanata
    const initialTimer = setTimeout(check, INITIAL_DELAY_MS);
    // Repolare periodica
    timerRef.current = setInterval(check, POLL_INTERVAL_MS);
    // Repolare pe foreground
    const sub = AppState.addEventListener('change', (next) => {
      if (next === 'active') check();
    });

    return () => {
      mountedRef.current = false;
      clearTimeout(initialTimer);
      if (timerRef.current) clearInterval(timerRef.current);
      sub.remove();
    };
  }, []);

  return state;
}
