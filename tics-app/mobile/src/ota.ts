/* =========================================================
   OTA self-hosted (§14) — @capgo/capacitor-updater (MPL-2.0)
   cu endpoint propriu pe core.tixello.com. FARA Capgo Cloud.

   Contract critic: dupa ce pluginul aplica un bundle nou, aplicatia are
   `appReadyTimeout` (10s) sa apeleze `notifyAppReady()`. Daca nu o face —
   pentru ca bundle-ul e stricat si crapa la boot — pluginul face ROLLBACK
   automat la bundle-ul anterior. De aceea apelul se face cat mai devreme,
   dar DUPA ce React a montat cu succes primul render.
   ========================================================= */
import { Capacitor } from '@capacitor/core';
import { CapacitorUpdater } from '@capgo/capacitor-updater';

export type OtaState = {
  /** versiunea bundle-ului web care ruleaza acum ('builtin' = assets din APK) */
  current: string;
  /** versiunea nativa a APK-ului */
  native: string;
  /** un bundle nou a fost descarcat si se aplica la urmatoarea deschidere */
  pending: string | null;
  downloading: boolean;
  progress: number;
};

let state: OtaState = { current: 'builtin', native: '', pending: null, downloading: false, progress: 0 };
const listeners = new Set<(s: OtaState) => void>();

const emit = (patch: Partial<OtaState>) => {
  state = { ...state, ...patch };
  listeners.forEach((fn) => fn(state));
};

export const getOtaState = () => state;
/** Returneaza functia de dezabonare (compatibila cu cleanup-ul din useEffect). */
export const onOtaChange = (fn: (s: OtaState) => void): (() => void) => {
  listeners.add(fn);
  return () => {
    listeners.delete(fn);
  };
};

/**
 * Se apeleaza o singura data, dupa primul render reusit.
 * Pe web (dev in browser) nu face nimic.
 */
export async function initOta() {
  if (!Capacitor.isNativePlatform()) return;

  try {
    // Confirma ca bundle-ul curent porneste. Fara asta -> rollback dupa 10s.
    await CapacitorUpdater.notifyAppReady();

    const cur = await CapacitorUpdater.current();
    emit({ current: cur.bundle?.version ?? 'builtin', native: cur.native ?? '' });

    CapacitorUpdater.addListener('downloadComplete', (res) => {
      emit({ downloading: false, progress: 100, pending: res.bundle?.version ?? null });
    });
    CapacitorUpdater.addListener('download', (res) => {
      emit({ downloading: true, progress: res.percent ?? 0 });
    });
    CapacitorUpdater.addListener('updateFailed', (res) => {
      // Bundle-ul nou nu a pornit; pluginul a revenit la cel anterior.
      console.warn('[OTA] update esuat, rollback la bundle-ul anterior', res);
      emit({ downloading: false, pending: null });
    });
    CapacitorUpdater.addListener('noNeedUpdate', () => emit({ pending: null }));
  } catch (e) {
    // OTA nu trebuie sa poata darama aplicatia. Daca ceva pica aici,
    // ramanem pe bundle-ul curent si mergem mai departe.
    console.warn('[OTA] init esuat', e);
  }
}

/** Verificare manuala, din Setari. */
export async function checkForUpdate(): Promise<string> {
  if (!Capacitor.isNativePlatform()) return 'OTA e disponibil doar în aplicația nativă';
  try {
    const latest = await CapacitorUpdater.getLatest();
    if (!latest.version || latest.version === state.current) return 'Ești pe cea mai nouă versiune';
    emit({ downloading: true, progress: 0 });
    const bundle = await CapacitorUpdater.download({ version: latest.version, url: latest.url ?? '', checksum: latest.checksum });
    await CapacitorUpdater.set({ id: bundle.id });
    return `Se aplică versiunea ${latest.version}…`;
  } catch (e) {
    emit({ downloading: false });
    return 'Verificarea a eșuat — încearcă mai târziu';
  }
}

/** Aplica imediat bundle-ul deja descarcat. */
export async function applyPendingUpdate(): Promise<void> {
  if (!Capacitor.isNativePlatform()) return;
  try {
    await CapacitorUpdater.reload();
  } catch (e) {
    console.warn('[OTA] reload esuat', e);
  }
}
