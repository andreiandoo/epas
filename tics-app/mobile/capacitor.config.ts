import type { CapacitorConfig } from '@capacitor/cli';

const config: CapacitorConfig = {
  appId: 'ro.tixello.app',
  appName: 'Tixello',
  webDir: 'dist',
  android: {
    allowMixedContent: true,
    backgroundColor: '#0A0711',
  },
  plugins: {
    Keyboard: {
      resize: 'none' as never,
    },
    /**
     * OTA self-hosted (§14 — "OTA fara update in store").
     *
     * Folosim plugin-ul @capgo/capacitor-updater (MPL-2.0, gratuit) dar cu
     * endpoint PROPRIU pe core.tixello.com — FARA abonament Capgo Cloud.
     * Serverul e `public/tics-app/updates.php` din acest repo: compara versiunea
     * bundle-ului de pe telefon cu `manifest.json` si raspunde cu zip-ul nou.
     *
     * autoUpdate: plugin-ul verifica la fiecare pornire/revenire din background,
     * descarca in fundal si aplica la urmatoarea deschidere.
     * appReadyTimeout: daca `notifyAppReady()` nu e apelat in 10s dupa aplicarea
     * unui bundle, se face rollback automat la cel anterior (vezi src/native.ts).
     */
    CapacitorUpdater: {
      updateUrl: 'https://core.tixello.com/tics-app/updates.php',
      autoUpdate: true,
      appReadyTimeout: 10000,
      responseTimeout: 20,
      // La update de versiune nativa (APK nou din store/sideload), arunca
      // bundle-urile OTA vechi si porneste de la assets-urile din APK.
      resetWhenUpdate: true,
    },
  },
};

export default config;
