/* =========================================================
   Preferinte de redare — accesibilitate (D10) + economie de date (D9).

   Trei lucruri decid dacă un short pornește singur:
     1. setarea explicită a utilizatorului (mereu / doar Wi-Fi / niciodată);
     2. `prefers-reduced-motion` din sistem — respectat necondiționat;
     3. tipul de rețea (Capacitor Network), pentru varianta „doar Wi-Fi".

   Setarea se ține în localStorage: e o preferință de dispozitiv, nu de cont, și
   trebuie să fie activă din prima redare, înainte de orice cerere de rețea.
   ========================================================= */
import { useCallback, useEffect, useState } from 'react';

export type AutoplayMode = 'always' | 'wifi' | 'never';

const AUTOPLAY_KEY = 'tixello.shorts.autoplay';
const DATA_SAVER_KEY = 'tixello.shorts.dataSaver';

function read<T extends string>(key: string, fallback: T): T {
  try {
    return (localStorage.getItem(key) as T | null) ?? fallback;
  } catch {
    return fallback;
  }
}

function write(key: string, value: string): void {
  try {
    localStorage.setItem(key, value);
  } catch {
    // Storage blocat — preferința rămâne doar pentru sesiunea curentă.
  }
}

/** Tipul conexiunii, via Capacitor Network când e disponibil. */
function useConnectionType(): 'wifi' | 'cellular' | 'none' | 'unknown' {
  const [type, setType] = useState<'wifi' | 'cellular' | 'none' | 'unknown'>('unknown');

  useEffect(() => {
    let disposed = false;
    let remove: (() => void) | undefined;

    void import('@capacitor/network')
      .then(async ({ Network }) => {
        if (disposed) return;

        const status = await Network.getStatus();
        if (!disposed) setType(status.connectionType as typeof type);

        const handle = await Network.addListener('networkStatusChange', (s) => {
          setType(s.connectionType as typeof type);
        });
        remove = () => void handle.remove();
      })
      .catch(() => {
        // Plugin absent (rulare în browser) — rămâne 'unknown', adică
        // tratat ca rețea bună.
      });

    return () => {
      disposed = true;
      remove?.();
    };
  }, []);

  return type;
}

export function usePlaybackPreferences() {
  const [autoplayMode, setAutoplayModeState] = useState<AutoplayMode>(() => read(AUTOPLAY_KEY, 'wifi'));
  const [dataSaver, setDataSaverState] = useState(() => read<'0' | '1'>(DATA_SAVER_KEY, '0') === '1');
  const connection = useConnectionType();

  const [reducedMotion, setReducedMotion] = useState(
    () => typeof window !== 'undefined' && window.matchMedia?.('(prefers-reduced-motion: reduce)').matches === true,
  );

  useEffect(() => {
    const query = window.matchMedia?.('(prefers-reduced-motion: reduce)');
    if (!query) return;

    const onChange = (e: MediaQueryListEvent) => setReducedMotion(e.matches);
    query.addEventListener('change', onChange);

    return () => query.removeEventListener('change', onChange);
  }, []);

  const setAutoplayMode = useCallback((mode: AutoplayMode) => {
    setAutoplayModeState(mode);
    write(AUTOPLAY_KEY, mode);
  }, []);

  const setDataSaver = useCallback((on: boolean) => {
    setDataSaverState(on);
    write(DATA_SAVER_KEY, on ? '1' : '0');
  }, []);

  /* Setarea de sistem bate preferința din app — dacă utilizatorul a cerut mai
     puțină mișcare, nu i-o dăm oricum. */
  const autoplayAllowed =
    !reducedMotion &&
    (autoplayMode === 'always' || (autoplayMode === 'wifi' && connection !== 'cellular'));

  /* Câte short-uri înainte preîncărcăm. Pe date mobile sau cu economie de date
     pornită, prefetch-ul e exact ce nu vrei să plătești. */
  const prefetchCount = dataSaver || connection === 'cellular' ? 0 : 2;

  return {
    autoplayMode,
    setAutoplayMode,
    dataSaver,
    setDataSaver,
    connection,
    reducedMotion,
    autoplayAllowed,
    prefetchCount,
  };
}
