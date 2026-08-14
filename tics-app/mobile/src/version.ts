/* =========================================================
   Versiunea aplicatiei.

   Constanta de mai jos e scrisa de `publish-bundle.ps1` la fiecare publicare —
   nu se mai editeaza de mana. Inainte era scrisa manual si a ramas la 4.1.0 in
   timp ce bundle-ul ajunsese la 4.5.0: un numar de versiune gresit e mai rau
   decat niciunul, fiindca il folosesti ca sa stabilesti daca update-ul a intrat.

   Pe telefon se cere si versiunea REALA a bundle-ului instalat, de la
   plugin-ul de OTA: aia e adevarul absolut — daca update-ul n-a intrat,
   constanta compilata in pachet ar minti in continuare. In browser (dezvoltare)
   plugin-ul nu exista si ramane constanta.
   ========================================================= */

/** Scrisa de build. NU edita manual. */
export const APP_VERSION = 'v4.9.0';

/**
 * Versiunea bundle-ului chiar instalat pe telefon.
 *
 * Import dinamic: pe web plugin-ul nu e disponibil, iar un import static ar
 * trage codul nativ in bundle degeaba.
 */
export async function installedVersion(): Promise<string> {
  try {
    const { CapacitorUpdater } = await import('@capgo/capacitor-updater');
    const cur = await CapacitorUpdater.current();
    const v = cur.bundle?.version;

    if (v && v !== 'builtin') return v.startsWith('v') ? v : `v${v}`;
  } catch {
    /* web sau plugin indisponibil: ramane versiunea din build */
  }

  return APP_VERSION;
}
