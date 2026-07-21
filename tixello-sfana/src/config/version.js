// Single source of truth pentru versiunea instalata a app-ului tixello-sfana.
//
// La FIECARE build public trebuie sincronizate 4 locuri:
//   1. app.json                                    -> "version": "X.Y.Z"
//   2. android/app/build.gradle                    -> versionName "X.Y.Z"
//                                                     versionCode  N++ (integer, +1)
//   3. Acest fisier                                -> APP_VERSION
//   4. epas/config/app.php  sfana_app_version      -> "X.Y.Z"
//      (sau SFANA_APP_VERSION in .env)
//
// Pasii 1-3 stau in tixello-sfana repo. Pasul 4 e in epas si controleaza
// ce zice endpoint-ul /api/app-version-sfana catre client. Cand APP_VERSION
// < server, useAppUpdate hook detecteaza si arata UpdateBanner.

export const APP_VERSION = '0.1.2';

// URL absolut pentru version check (nu trece prin BASE_URL scoped
// marketplace-client — endpoint-ul e public si nu necesita bearer token).
export const VERSION_CHECK_URL = 'https://core.tixello.com/api/app-version-sfana';

// Compara doua string-uri semver (X.Y.Z). Return true daca `latest` > `current`.
// Nu e strict semver — ignora sufixe (0.1.0-beta => 0.1.0). Suficient pentru
// versionarea noastra simpla.
export function isNewerVersion(latest, current) {
  if (!latest || !current) return false;
  const parse = (v) => String(v).split('-')[0].split('.').map((n) => parseInt(n, 10) || 0);
  const a = parse(latest);
  const b = parse(current);
  const len = Math.max(a.length, b.length);
  for (let i = 0; i < len; i++) {
    const ai = a[i] || 0;
    const bi = b[i] || 0;
    if (ai > bi) return true;
    if (ai < bi) return false;
  }
  return false;
}
