# Continuare la revenire în chat

Ultimul lucru discutat: user restart VS Code + PC pentru memorie. Play Store prep gata la nivel de code + docs.

## Status la momentul restart-ului

### GATA (checked, verifiat)
- ☑ `store-config/upload.keystore` generat (PKCS12 RSA 2048, 25 ani validitate)
- ☑ `store-config/signing.properties` (parola 32 chars, alias `ambilet-scan-upload`)
- ☑ `store-config/KEYSTORE-BACKUP-CREDENTIALS.md` cu SHA-256 + backup instructions
- ☑ `.gitignore` include keystore + signing.properties (nu ajung pe git)
- ☑ `android/app/build.gradle` extins cu 2 flavors: `sideload` (existing) + `store` (Play Store)
- ☑ `store/` flavor override: applicationId `com.ambilet.scan.store`, versionCode `220000`, versionName `2.2.0`, signed cu upload.keystore
- ☑ `listing/title.txt` — AmBilet - Vânzare & Scan
- ☑ `listing/short-description.txt` — 80 chars
- ☑ `listing/full-description.txt` — ~2700 chars descriere completă
- ☑ `listing/whats-new.txt` — release notes primul release
- ☑ `listing/data-safety.md` — răspunsuri Data Safety form (obligatoriu)
- ☑ `PLAY-CONSOLE-GUIDE.md` — 6 pași cu URL-uri actualizate + credentials test
- ☑ `GRAPHICS-TODO.md` — specificații + instrucțiuni cum să faci grafice
- ☑ URL-uri publice confirmate: `/confidentialitate`, `/termeni`, `/gdpr`
- ☑ Credentials test dummy: `org@organizator.ro` / `Org@n!zat0r`

### DE FĂCUT după restart

1. **Verifică că build.gradle modificat merge**:
   ```bash
   cd i:/WORK/eventpilot/ambilet-app2/android
   ./gradlew tasks | grep -i "bundle\|assemble" | grep -i "release"
   ```
   Ar trebui să vezi task-uri noi: `assembleSideloadRelease`, `assembleStoreRelease`, `bundleSideloadRelease`, `bundleStoreRelease`.

2. **Build sideload dev.10 refresh** (unfinished from before restart):
   ```bash
   ./gradlew --no-daemon -Dorg.gradle.parallel=false assembleSideloadRelease
   ```
   Output: `app/build/outputs/apk/sideloadRelease/app-sideload-arm64-v8a-release.apk`
   Comanda veche `assembleRelease` nu mai există — trebuie `assembleSideloadRelease` sau `assembleStoreRelease`.

3. **Prima încercare build AAB pentru Play Store**:
   ```bash
   ./gradlew --no-daemon -Dorg.gradle.parallel=false bundleStoreRelease
   ```
   Output: `app/build/outputs/bundle/storeRelease/app-store-release.aab`

4. **Grafice** (user pregătește ~30 min):
   - Icon 512×512 PNG
   - Feature graphic 1024×500 PNG/JPG
   - Min 2 screenshots (recomandate 4-6): panou, scanare, vânzare, rapoarte

5. **Push docs în git**:
   Push doar files safe: `signing.properties.example`, `PLAY-CONSOLE-GUIDE.md`, `GRAPHICS-TODO.md`, `NEXT-SESSION-CHECKPOINT.md`, `listing/*`. Restul (keystore + parole) sunt deja gitignored.

## Warning-uri pentru viitor

**Comenzile de build s-au SCHIMBAT** din cauza product flavors:
- Vechi: `./gradlew assembleRelease`
- Nou: `./gradlew assembleSideloadRelease` (pentru dev sideload)
- Nou: `./gradlew bundleStoreRelease` (pentru Play Store AAB)

Notat la commit-ul care va veni ca să nu se piardă workflow-ul.

## Punct de plecare imediat la revenire

Începe cu: "verifică că build.gradle modificat funcționează (task-uri gradle noi vizibile) + build sideload dev.10 refresh". Apoi user upload grafice sau confirm că are decisions făcute.
