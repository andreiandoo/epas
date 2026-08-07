/* =========================================================
   OTA self-hosted (§14) — @capgo/capacitor-updater (MPL-2.0)
   cu endpoint propriu pe core.tixello.com. FARA Capgo Cloud.

   Contract critic: dupa ce pluginul aplica un bundle nou, aplicatia are
   `appReadyTimeout` (10s) sa apeleze `notifyAppReady()`. Daca nu o face —
   pentru ca bundle-ul e stricat si crapa la boot — pluginul face ROLLBACK
   automat la bundle-ul anterior. De aceea apelul se face cat mai devreme,
   dar DUPA ce React a montat cu succes primul render.

   DIAGNOSTIC: prima versiune esua complet mut — daca descarcarea sau
   despachetarea pica, nu se vedea nimic nicaieri si parea ca "nu face nimic".
   Acum fiecare pas isi lasa urma in `log`, iar Profilul o poate afisa.
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
  /** ultima eroare, ca sa nu mai esueze mut */
  error: string | null;
  /** urma pasilor, in ordine, pentru diagnostic pe telefon */
  log: string[];
  native_platform: boolean;
};

let state: OtaState = {
  current: 'builtin',
  native: '',
  pending: null,
  downloading: false,
  progress: 0,
  error: null,
  log: [],
  native_platform: false,
};

const listeners = new Set<(s: OtaState) => void>();

const emit = (patch: Partial<OtaState>) => {
  state = { ...state, ...patch };
  listeners.forEach((fn) => fn(state));
};

const note = (msg: string) => {
  const stamp = new Date().toISOString().slice(11, 19);
  emit({ log: [...state.log, `${stamp} ${msg}`].slice(-30) });
  console.log('[OTA]', msg);
};

export const getOtaState = () => state;
export const onOtaChange = (fn: (s: OtaState) => void): (() => void) => {
  listeners.add(fn);
  return () => {
    listeners.delete(fn);
  };
};

const errText = (e: unknown) =>
  e instanceof Error ? e.message : typeof e === 'string' ? e : JSON.stringify(e);

/**
 * Se apeleaza o singura data, dupa primul render reusit.
 * Pe web (dev in browser) nu face nimic.
 */
export async function initOta() {
  const isNative = Capacitor.isNativePlatform();
  emit({ native_platform: isNative });
  if (!isNative) {
    note('rulez in browser — OTA inactiv');
    return;
  }

  try {
    await CapacitorUpdater.notifyAppReady();
    note('notifyAppReady OK');
  } catch (e) {
    note('notifyAppReady a esuat: ' + errText(e));
    emit({ error: 'notifyAppReady: ' + errText(e) });
  }

  try {
    const cur = await CapacitorUpdater.current();
    emit({ current: cur.bundle?.version ?? 'builtin', native: cur.native ?? '' });
    note(`bundle curent: ${cur.bundle?.version ?? 'builtin'} · nativ ${cur.native ?? '?'}`);
  } catch (e) {
    note('current() a esuat: ' + errText(e));
  }

  try {
    CapacitorUpdater.addListener('download', (res) => {
      emit({ downloading: true, progress: res.percent ?? 0 });
    });
    CapacitorUpdater.addListener('downloadComplete', (res) => {
      note('descarcare completa: ' + (res.bundle?.version ?? '?'));
      emit({ downloading: false, progress: 100, pending: res.bundle?.version ?? null });
    });
    CapacitorUpdater.addListener('downloadFailed', (res) => {
      note('DESCARCARE ESUATA: ' + JSON.stringify(res));
      emit({ downloading: false, error: 'descărcare eșuată: ' + JSON.stringify(res) });
    });
    CapacitorUpdater.addListener('updateFailed', (res) => {
      note('UPDATE ESUAT (rollback): ' + JSON.stringify(res));
      emit({ downloading: false, pending: null, error: 'update eșuat, s-a revenit la versiunea anterioară' });
    });
    CapacitorUpdater.addListener('noNeedUpdate', () => {
      note('serverul spune: nu e nimic nou');
      emit({ pending: null });
    });
    CapacitorUpdater.addListener('majorAvailable', (res) => note('versiune majora disponibila: ' + JSON.stringify(res)));
  } catch (e) {
    note('inregistrarea listenerelor a esuat: ' + errText(e));
  }
}

/** Verificare manuala, din Profil / Setari. Intoarce un mesaj de afisat. */
export async function checkForUpdate(): Promise<string> {
  if (!Capacitor.isNativePlatform()) return 'OTA e disponibil doar în aplicația nativă';
  try {
    note('verific manual…');
    const latest = await CapacitorUpdater.getLatest();
    note('raspuns server: ' + JSON.stringify(latest));

    if (!latest?.version) {
      emit({ error: 'serverul nu a întors o versiune' });
      return 'Serverul nu a întors nicio versiune';
    }
    if (latest.version === state.current) return `Ești pe cea mai nouă versiune (${latest.version})`;
    if (!latest.url) {
      emit({ error: 'serverul nu a întors URL de bundle' });
      return 'Serverul nu a întors adresa bundle-ului';
    }

    emit({ downloading: true, progress: 0, error: null });
    note('descarc ' + latest.version);
    const bundle = await CapacitorUpdater.download({ version: latest.version, url: latest.url });
    note('descarcat, aplic bundle ' + bundle.id);
    await CapacitorUpdater.set({ id: bundle.id });
    return `Se aplică versiunea ${latest.version}…`;
  } catch (e) {
    const msg = errText(e);
    note('VERIFICARE ESUATA: ' + msg);
    emit({ downloading: false, error: msg });
    return 'A eșuat: ' + msg;
  }
}

/** Aplica imediat bundle-ul deja descarcat. */
export async function applyPendingUpdate(): Promise<void> {
  if (!Capacitor.isNativePlatform()) return;
  try {
    await CapacitorUpdater.reload();
  } catch (e) {
    note('reload esuat: ' + errText(e));
  }
}

/** Text de diagnostic, copiabil, pentru cand nimic nu merge. */
export function otaDiagnostics(): string {
  return [
    `platforma nativa : ${state.native_platform}`,
    `bundle curent    : ${state.current}`,
    `versiune nativa  : ${state.native || '?'}`,
    `in asteptare     : ${state.pending ?? '-'}`,
    `eroare           : ${state.error ?? '-'}`,
    '',
    ...state.log,
  ].join('\n');
}
