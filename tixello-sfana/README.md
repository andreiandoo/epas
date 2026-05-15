# Tixello Sf. Ana — App Operator

Aplicație mobilă pentru operatorii din teren la Lacul Sf. Ana. Bazată pe `tixello-app` (Expo + RN 0.81 + RN Navigation), cu UI specific leisure.

## 5 roluri operator (shift-based)

Aplicația citește **shift-ul activ acum** din `/me/active-shift` (calculat din `LeisureShift.start_at ≤ now ≤ end_at`) și deschide ecranul corespunzător:

| Rol din `LeisureShift.role` | Ecran | Funcționalități |
|---|---|---|
| `operator_boats` | BoatsOperator | Listă bărci, start/end/finalize cursă, timer live, calul calupuri extra |
| `operator_pontoon` | PontoonOperator | Listă curse zilei, stats live, scanner check-in vaporașe |
| `sales_operator` (sau `shift_manager`) | POS | Vânzare on-site cu `pos_price`, plată cash/card/email |
| `gate_scanner` | Checkin | Doar scanare bilete acces pentru validare check-in |
| `field_seller` | FieldOperator | Verificare bilete + POS mobil (fără chitanță fizică) |

**Hub-ul** detectează rolul activ și redirectează. Dacă nu există shift activ, afișează avertisment.

## Setup local

```bash
cd tixello-sfana
npm install
npm start         # Expo dev server
npm run android   # rulează direct pe device/emulator Android conectat
npm run ios       # iOS (necesită macOS + Xcode)
```

## Configurare API

Pune URL-ul real al backend-ului în `src/api/client.js`:

```js
const BASE_URL = 'https://core.tixello.com/api/marketplace-client';
const DEFAULT_API_KEY = 'mpc_xxxxxxxxxxxxxxxxx';
```

## Build APK pentru distribuție

```bash
# EAS Build (recomandat — semnare automată)
npm i -g eas-cli
eas login
eas build:configure
eas build --platform android --profile preview
```

Sau **local** cu Expo prebuild + Gradle:

```bash
npx expo prebuild --platform android
cd android
./gradlew assembleRelease
# APK rezultat: android/app/build/outputs/apk/release/app-release.apk
```

## Endpoint-uri API folosite

- `POST /organizer/login` — Sanctum auth
- `GET /organizer/me/active-shift` — rolul activ acum
- `GET /organizer/events` — listă evenimente organizator (filtrare leisure_venue)
- `GET /organizer/events/{event}/leisure/config` — produse + variante + slots + physical
- `GET /organizer/events/{event}/leisure/dashboard/live` — stats live
- `GET /organizer/events/{event}/leisure/boats?ticket_type_id=X` — bărci + rentals active
- `POST /organizer/events/{event}/leisure/boat-rentals/start` — pornește cursă
- `POST /organizer/events/{event}/leisure/boat-rentals/{rental}/end` — închide timer
- `POST /organizer/events/{event}/leisure/boat-rentals/{rental}/finalize` — finalizare cu calup extra
- `POST /organizer/events/{event}/leisure/pos-sale` — vânzare POS

## Note implementare

- **Auth** reutilizat 1:1 din tixello-app (Sanctum + SecureStore pentru token).
- **Theme** custom forest/lake bazată pe HTML-ul bilet Sf. Ana.
- **Scanner QR** folosește `expo-camera` `CameraView` (single barcode).
- **Cronometru bărci** tick 1s pe ecranul Boats; refresh periodic 15s.
- **Polling shift** 5min pe HubScreen (refresh automat când managerul schimbă rolurile).

## Limitări versiune curentă (v0.1.0)

- Scanare bilet: handler-ul `onScan` întoarce codul, dar **lookup-ul real al biletului** (`/tickets/lookup`) e placeholder. Trebuie hook-uit la endpoint când va fi disponibil pe backend.
- POS: nu gestionează deocamdată servicii cu slots/physical_inventory (`slot_time` / `start_time` lipsă din UI).
- Notificări push: nu sunt configurate (Expo Notifications poate fi adăugat ulterior).
- Print termic chitanță: există pe web POS, nu și pe mobile (necesită driver imprimantă termică Bluetooth — feature complex).

## Build distribuție pe ambilet.ro

User-ul face build APK local cu EAS și apoi îl urcă manual pe `ambilet.ro/android-sfana/app-release.apk` pentru distribuire operatorilor.
