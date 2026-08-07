# Tixello Mobile — build & instalare

Aplicația mobilă unică (client + organizator) conform `../TIXELLO_BUILD_GUIDE.md`.
Stack: **Capacitor 6 + React 18 + Vite 5 + TypeScript**, un singur build web pentru ambele shell-uri.

---

## Build rapid

```powershell
cd i:\WORK\eventpilot\epas\tics-app\mobile
.\build-apk.ps1              # build web + sync + APK
.\build-apk.ps1 -Install     # și instalează pe telefonul conectat prin USB
.\build-apk.ps1 -Clean       # gradlew clean înainte
.\build-apk.ps1 -Publish -Version 0.1.1   # copiază APK-ul versionat în ..\..\public\tics-app\
```

Rezultat: `android\app\build\outputs\apk\debug\app-debug.apk` (copiat și în `out\tixello-debug.apk`).

### Publicare la core.tixello.com/tics-app

```powershell
.\build-apk.ps1 -Publish -Version 0.1.1
# apoi actualizează versiunea + SHA-256 în epas\public\tics-app\index.html
cd ..\..
git add public/tics-app tics-app
git commit -m "tics-app: build de test 0.1.1"
git push origin core
```

Pe server (`core.tixello.com`) e suficient un **`git pull`** — se schimbă doar fișiere statice sub
`public/`, deci **nu** e nevoie de `artisan down`, `composer install`, `route:cache` sau restart FPM.
Dacă e activ Cloudflare, dă purge pe `/tics-app/*` sau folosește un nume de fișier nou (versionat),
cum face scriptul.

### Manual (echivalent)

```powershell
npm run build
npx cap sync android
cd android
.\gradlew.bat assembleDebug
```

> Rulează comenzile manuale **doar** cu variabilele de mediu din secțiunea „Particularități" de mai jos,
> altfel build-ul eșuează pe această mașină.

---

## Instalare pe telefon

**Varianta 1 — ADB (recomandat).** Pe telefon: Setări → Despre telefon → apasă de 7× pe „Număr build"
pentru a activa Opțiuni dezvoltator → activează **Depanare USB**. Conectează cablul, acceptă promptul
„Permite depanarea USB?", apoi:

```powershell
adb devices                                    # trebuie să apară dispozitivul
adb install -r "i:\WORK\eventpilot\epas\tics-app\mobile\android\app\build\outputs\apk\debug\app-debug.apk"
```

`adb` e la `C:\Users\PC\AppData\Local\Android\Sdk\platform-tools\adb.exe`.

**Varianta 2 — transfer direct.** Copiază `out\tixello-debug.apk` pe telefon (cablu, Google Drive,
WhatsApp către tine etc.), deschide-l din Fișiere și acceptă „Instalează din surse necunoscute"
pentru aplicația din care îl deschizi.

**Varianta 3 — WiFi ADB** (Android 11+): pe telefon Opțiuni dezvoltator → Depanare wireless → Asociere
cu cod, apoi `adb pair <ip>:<port>` și `adb connect <ip>:<port>`.

---

## Particularități ale acestei mașini (rezolvate în `build-apk.ps1`)

Trei lucruri blochează un build Gradle standard aici. Scriptul le tratează automat; le documentăm
pentru cazul în care rulezi comenzile manual sau se schimbă mediul.

### 1. `GRADLE_USER_HOME` arată spre un disc inexistent

Variabila de mediu e setată la `D:\01.CACHE\GradleCache`, dar **discul D: nu există**.
Orice build Gradle crapă cu `Could not create parent directory for lock file`.

*Fix aplicat:* scriptul forțează `GRADLE_USER_HOME=C:\Users\PC\.gradle` doar pentru durata build-ului.
*Fix permanent (opțional, la tine):* corectează sau șterge variabila din Variabile de mediu Windows.

### 2. Avast interceptează TLS-ul → Java dă `PKIX path building failed`

Avast Web/Mail Shield face MITM pe HTTPS. Windows are CA-ul lui în store, Java **nu** —
deci descărcarea dependențelor din Maven Central / Google se oprește cu eroare de certificat.

*Fix aplicat:* `android\tixello-cacerts.jks` = copie a `cacerts` din JDK 17 + CA-ul root Avast,
folosit prin `GRADLE_OPTS=-Djavax.net.ssl.trustStore=…`. JDK-ul de sistem rămâne nemodificat.

Regenerare (dacă Avast rotește certificatul sau schimbi JDK-ul):

```powershell
$jdk = "C:\Program Files\Eclipse Adoptium\jdk-17.0.18.8-hotspot"
$store = [System.Security.Cryptography.X509Certificates.X509Store]::new("Root","LocalMachine")
$store.Open("ReadOnly")
$ca = ($store.Certificates | Where-Object { $_.Subject -like "*Avast*" })[0]
$store.Close()
$pem = "-----BEGIN CERTIFICATE-----`n" + [Convert]::ToBase64String($ca.RawData,'InsertLineBreaks') + "`n-----END CERTIFICATE-----"
[System.IO.File]::WriteAllText("android\avast-root.cer", $pem, [System.Text.UTF8Encoding]::new($false))
Copy-Item "$jdk\lib\security\cacerts" "android\tixello-cacerts.jks" -Force
& "$jdk\bin\keytool.exe" -importcert -noprompt -trustcacerts -alias avast-web-shield `
  -file "android\avast-root.cer" -keystore "android\tixello-cacerts.jks" -storepass changeit
```

### 3. Wrapper-ul Gradle nu apucă să descarce distribuția

`gradle-wrapper` are read-timeout de 10s; distribuția are 193 MB și conexiunea prin Avast e prea lentă
→ `SocketTimeoutException`. Scriptul o descarcă întâi cu `Invoke-WebRequest` în
`C:\Users\PC\.gradle\wrapper\dists\gradle-8.2.1-all\d8pvvlun5bx6sdtwqhf8y9z4b\gradle-8.2.1-all.zip`,
apoi wrapper-ul o găsește local și doar o dezarhivează.

### 4. `compileSdk` = 35, nu 34

SDK-ul local are platformele **35 și 36** instalate, dar nu 34 (implicitul Capacitor 6), iar
`cmdline-tools` e gol → nu putem rula `sdkmanager` ca să adăugăm 34. Am setat `compileSdkVersion`
și `targetSdkVersion` la **35** în `android\variables.gradle`. Fără efect funcțional pentru noi.

---

## Structura proiectului

```
src/
  design/     tokens.css (§4) · fonts.css (Outfit inline, 0 CDN) · base.css
              icons/Icon.tsx (46 iconițe SVG, extrase din prototip)
              components/ (Card, Row, StatTile, Pill, Button, Toggle, Sheet, FullModal, …)
  store/      session.ts — replică structura de stare `S` din organizer-app.html
  api/        types.ts (contractele §13) · client.ts (mock; USE_MOCK=true)
  mock/       org.ts (CTX cele 6 verticale + LEISURE + staff/gates/notifs/scan)
              client.ts (EV/VEN/MYTIX/wallet)
  features/
    auth/       LoginScreen
    identity/   ChooserScreen („Alege contul")
    client/     ClientShell + Home/Explore/Tickets/Wallet/Profile + modale
    org/        OrgShell + OrgChrome + Dashboard/CheckIn/Sales/Reports/Settings + OrgModals
    leisure/    LeisureShell (navigatorul distinct venue-owner)
  native.ts   suprafața Capacitor (status bar, back button, network)
```

## Conturi demo (orice parolă)

| Email | Ce obții |
|---|---|
| `andrei@tixello.ro` | client + 3 organizatori → apare pasul **„Alege contul"** |
| `client@tixello.ro` | doar client → intrare directă în shell-ul de client |
| `operator@tixello.ro` | doar organizatori → chooser doar cu cele 3 conturi de organizator |

## Ce urmează

Fazele §15 din ghid: Client MVP → Organizator MVP → Offline & operațional → Festival & verticale →
Decontări & polish. OTA (Capgo) se cablează imediat după validarea acestui APK — necesită cheia
contului Capgo.
