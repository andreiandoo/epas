# Tixello Widget (Android)

Aplicație nativă mică, instalată de mână, care ține pe ecranul telefonului
cifrele întregii platforme Tixello și alertează la fiecare comision încasat.
**Fără Firebase** — telefonul întreabă serverul, nu invers.

Ghidul complet (server, token, instalare, depanare) e în
[`docs/tixello-widget-android.md`](../docs/tixello-widget-android.md).

## Build

```bash
./gradlew testDebugUnitTest   # teste JVM
./gradlew assembleDebug       # app/build/outputs/apk/debug/app-debug.apk
```

Ai nevoie de JDK 17 și Android SDK (compileSdk 35). Dacă nu ai SDK-ul local,
APK-ul se construiește în GitHub Actions — workflow-ul
`.github/workflows/tixello-widget-android.yml`, artefact `tixello-widget-apk`.

Proiectul e complet separat de `tics-app/mobile` (Capacitor): acolo e aplicația
pentru clienți și organizatori, aici e un utilitar de proprietar, cu widget
nativ și serviciu de fundal — lucruri pe care un WebView nu le poate face.

## Ce e unde

| Fișier                     | Rol                                                                 |
|----------------------------|----------------------------------------------------------------------|
| `SyncEngine.kt`            | singura definiție a lui „trage cifrele, salvează, desenează, alertează" |
| `PollService.kt`           | serviciu foreground; interoghează la N secunde                       |
| `PollScheduler.kt`         | pornește/oprește serviciul + WorkManager la 15 min ca plasă de siguranță |
| `Notifier.kt`              | canalele de notificare (sunet/vibrație) și alertele de comision      |
| `TixelloApi.kt`            | clientul HTTP (HttpURLConnection, fără dependințe)                   |
| `Snapshot.kt`              | parsarea payload-ului de la server                                   |
| `Format.kt`                | formatare românească a cifrelor                                      |
| `widget/WidgetRenderer.kt` | desenează ambele widget-uri din ultimul snapshot salvat              |
| `SetupActivity.kt`         | singurul ecran: adresă, token, interval, comutatoare                 |

## Reguli de care depinde comportamentul

- **Cursorul, nu ceasul.** Alerta se declanșează pentru comisioanele cu
  `id > last_commission_id`, cursor ținut pe telefon și trimis serverului. Fără
  el ai avea alerte duble sau pierdute.
- **Prima sincronizare tace.** Cursorul `-1` înseamnă „încă nu știu nimic":
  se învață poziția, fără să sune pentru istoric.
- **Widget-ul nu face rețea.** Desenează din snapshot-ul salvat; rețeaua e
  exclusiv treaba serviciului și a worker-ului.
- **targetSdk rămâne 34.** De la 35, Android limitează serviciile foreground de
  tip `dataSync` la 6 h pe zi.
- **Un singur mutex peste sincronizare.** Serviciul și worker-ul pot porni în
  același moment; fără el ar citi același cursor și ar suna de două ori.
