# Tixello App (Ambilet Scan) — APK Build Steps

Reference: How to build & ship a new Android APK for `tixello-app/`. Same flow per release.

## TL;DR

1. Bump version in 3 files (app.json, build.gradle, config/app.php)
2. Build APK
3. Copy APK to `epas/public/downloads/ambilet-android.apk`
4. Commit + push: epas first, then main

## Step-by-step

### 1. Bump version (3 files — MUST match)

Pick the next version (e.g. `1.6.X` → `1.6.X+1`) and update:

**A. `tixello-app/app.json`:**
```json
"version": "1.6.7",
```

**B. `tixello-app/android/app/build.gradle`:**
```gradle
versionCode 1
versionName "1.6.7"
```
(`versionCode` stays at `1` — only `versionName` is bumped per release.)

**C. `epas/config/app.php`:**
```php
'staff_app_version' => env('STAFF_APP_VERSION', '1.6.7'),
```
This drives the "Actualizare disponibilă" modal in the running app — it polls `https://core.tixello.com/api/app-version` and compares to its embedded `APP_VERSION` constant.

### 2. Build APK

```bash
cd d:/000WORK/xampp/htdocs/web/eventpilot/tixello-app/android
NODE_OPTIONS="--dns-result-order=ipv4first" ./gradlew assembleRelease
```

- `NODE_OPTIONS="--dns-result-order=ipv4first"` is REQUIRED — Metro bundler crashes with `ERR_INTERNAL_ASSERTION` at `internalConnectMultiple` on Windows otherwise (IPv6 resolution bug).
- First build after version bump often FAILS transiently around 2–3 min with truncated output. **Just rerun the same command** — second attempt typically succeeds in 4–5 min. The failure is a Metro/network blip during JS bundle export; nothing in the project is broken.
- Output APK: `tixello-app/android/app/build/outputs/apk/release/app-release.apk` (~40 MB).
- Successful build ends with `BUILD SUCCESSFUL in Xm Ys`.

### 3. Deploy APK

```bash
cp tixello-app/android/app/build/outputs/apk/release/app-release.apk \
   epas/public/downloads/ambilet-android.apk
```

Served by Laravel route `/download-android` (and alias `/android`) in `epas/routes/web.php`. Ambilet `.htaccess` redirects `ambilet.ro/android` → `core.tixello.com/android`.

### 4. Commit + push (two repos because epas is a submodule)

**A. Inside `epas/` (branch `core`):**
```bash
cd epas
git add public/downloads/ambilet-android.apk config/app.php
git commit -m "Ambilet APK v1.6.7

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
git push origin core
```

**B. In main repo (branch `main`):**
```bash
cd ..
git add epas tixello-app
git commit -m "Update epas + tixello-app - ambilet APK v1.6.7"
git push origin main
```

## Known issues & fixes

- **First build fails at ~2–3 min with empty/short output**: rerun same command. Transient Metro/IPv6 issue. No project change needed.
- **`ERR_INTERNAL_ASSERTION` from Metro**: always use `NODE_OPTIONS="--dns-result-order=ipv4first"` prefix.
- **Build cache stale (modifications don't show up in APK)**: `./gradlew clean` then `./gradlew assembleRelease` — adds ~10 min but forces fresh JS bundle and resource compile.
- **Keystore signing**: shared `release.keystore` in `tixello-app/` root, password `android`, key alias `androiddebugkey`. DO NOT regenerate — must stay identical so updates install over existing installs without uninstall.
- **APK is 40 MB**: bundles arm64-v8a + armeabi-v7a only (no x86 — `reactNativeArchitectures` in `android/gradle.properties`). Don't add `x86_64` unless emulator support is needed.
- **versionCode never bumps**: only `versionName` (`1.6.X`) changes per release. `versionCode` stays at 1 because the comparison `latest_version` vs `APP_VERSION` runs from the JSON string in the app, not the Android version code.

## File map

| File | What lives here |
|---|---|
| `tixello-app/app.json` | Expo config — name, package id, splash, icons, version string |
| `tixello-app/android/app/build.gradle` | Native version + signing config + ABI filters |
| `tixello-app/android/gradle.properties` | `reactNativeArchitectures`, `newArchEnabled=false`, hermes settings |
| `tixello-app/App.js` | `APP_VERSION` constant compared against `/api/app-version` |
| `epas/config/app.php` | `staff_app_version` returned by `/api/app-version` |
| `epas/public/downloads/ambilet-android.apk` | The deployed APK that `ambilet.ro/android` serves |
| `epas/routes/web.php` | `/download-android` + `/android` routes |
