# Tixello Web Scan & POS — Plan de implementare web în panoul organizator

**Scop:** Replica web a aplicației mobile Android (Tixello Scan / Ambilet Scan) pentru organizatorii care folosesc iOS și nu pot instala APK-ul. Trebuie să aibă TOATE funcționalitățile aplicației mobile, mai puțin:
- Plata prin NFC (Stripe Tap)
- Conexiune cu POS bancar fizic

**Locație de bază:** `epas/resources/marketplaces/ambilet/organizer/` + asset-uri în `epas/resources/marketplaces/ambilet/assets/`

---

## 🚨 REGULA #1 — DOAR ADĂUGĂRI

**NU ștergem, NU modificăm, NU rescriem nimic din ce există acum în panoul organizator.** Toate paginile existente (`dashboard.php`, `events.php`, `participants.php`, `sales.php`, `team.php`, `settings.php`, `leisure-*.php`, etc.) rămân **identice**. 

Implementarea constă EXCLUSIV din:
- Pagini PHP **noi** într-un sub-namespace `organizer/scan-app/` (sau prefix `scan-`)
- Asset-uri JS **noi** într-un sub-folder `assets/js/scan-app/`
- Asset-uri CSS **noi** (`scan-app.css` separat, nu modificăm `organizer.css`)
- **Doar adăugări** în sidebar (`organizer-sidebar.php`) — un nou link / secțiune
- **Doar adăugări** în `.htaccess` — rewrite rules noi pentru rutele noi
- **Doar adăugări** în `api/proxy.php` dacă apar endpoint-uri noi (puțin probabil — folosim API-ul existent)

Convenția pentru editarea sidebar/.htaccess: **inserăm secțiuni clar delimitate cu comentarii** (`<!-- SCAN APP START -->` / `<!-- SCAN APP END -->`) ca să fie ușor de revertit dacă e nevoie.

---

## 1. Analiza aplicației mobile — ce trebuie replicat

### Roluri și navigare (5 tab-uri pentru organizator):

| Tab | Screen mobil | Replica web (rută propusă) |
|---|---|---|
| Panou | DashboardScreen | `/organizator/scan/panou` |
| Scanare | CheckInScreen | `/organizator/scan/scanare` |
| Vânzare | SalesScreen | `/organizator/scan/vanzare` |
| Rapoarte | ReportsScreen | `/organizator/scan/rapoarte` |
| Setări | SettingsScreen | `/organizator/scan/setari-scan` |

Plus pagini secundare (foste modaluri în mobil):
- EventsModal → `/organizator/scan/evenimente` (event picker)
- StaffModal → `/organizator/scan/staff`
- GuestListModal → `/organizator/scan/guest-list`
- GateManagerModal → `/organizator/scan/porti`
- StaffAssignmentModal → `/organizator/scan/asignare-personal`
- NotificationsPanel → `/organizator/scan/notificari`
- SeatingMapScreen → componentă embed în Vânzare

### Roluri venue_owner (4 tab-uri):

Mobile app are flow alternativ pentru `venue_owner`. **Decizie:** îl includem ca același set de pagini, dar cu `userType` salvat în sessionStorage care reroutează call-urile API către `/venue-owner/*` (exact ca în `api/client.js` mobile). 

### Funcționalități native ce trebuie traduse în web:

| Feature mobil | Bibliotecă nativă | Strategia web |
|---|---|---|
| Scanare QR/barcode | `expo-camera` + `BarCodeScanner` | **`BarcodeDetector` API** (Chrome/Android browser, iOS Safari 16.4+) cu **fallback `jsQR`** + **fallback `zxing-js/browser`** pentru iOS vechi |
| Vibrație feedback | RN Vibration | `navigator.vibrate()` (limited Safari — silent fallback) |
| Sunet feedback | `expo-av` | Web Audio API + `<audio>` tag preload |
| Storage token | `expo-secure-store` (Keychain) | `localStorage` (deja folosit de `AmbiletAuth`) |
| Cache offline | `AsyncStorage` | **IndexedDB** prin wrapper simplu |
| Push real-time | Pusher/Reverb WS | **Reverb prin laravel-echo** in browser (deja există config) — sau polling fallback |
| Keep-awake | `expo-keep-awake` | **Wake Lock API** (cu fallback no-op pentru iOS < 16.4) |
| NFC plată | Stripe Terminal SDK | **EXCLUS** per cerință |
| POS bancar fizic | Conexiune Bluetooth | **EXCLUS** per cerință |
| Generare QR claim | `react-native-qrcode-svg` | **`qrcode` npm package** sau `qrcodejs2` (CDN-able) |

### API-uri folosite de mobil (toate există deja pe server):

Pagina web va folosi **EXACT** aceleași endpoint-uri ca app-ul mobil, prin `AmbiletAPI` global (există deja în `assets/js/api.js`):
- `POST /organizer/login`
- `POST /venue-owner/login`
- `GET /organizer/me`, `GET /venue-owner/me`
- `GET /organizer/events?published_only=true&per_page=100`
- `GET /organizer/events/{id}`
- `GET /organizer/events/{id}/participants`
- `POST /organizer/participants/checkin` (cu `ticket_code`)
- `POST /organizer/events/{id}/check-in/{barcode}` + `DELETE` (undo)
- `GET /organizer/events/{id}/sales-breakdown`
- `POST /orders` (creează POS order)
- `POST /orders/{id}/generate-claim-url`
- `POST /orders/{id}/send-tickets`
- `POST /orders/{id}/pos-complete`
- `GET /claim/{token}/status` (polling claim)
- `GET /organizer/team`, `POST /organizer/team/update`
- `GET /organizer/venues/{id}/gates` + CRUD
- `POST /organizer/switch-organizer`
- WebSocket Reverb canal `event.{id}.sales` → eveniment `order.confirmed`
- Venue owner: `GET /venue-owner/events`, `GET /venue-owner/events/{id}/attendees`, `POST /venue-owner/scan`, `POST /venue-owner/check-in`, etc.

**Zero endpoint-uri noi de creat pe backend.** Toate sunt deja în `app/Http/Controllers/Api/MarketplaceClient/`.

---

## 2. Structura fișierelor noi (toate adăugate, nimic modificat)

### Layout sub-namespace (organizer/scan-app/)

```
epas/resources/marketplaces/ambilet/organizer/scan-app/
├── _layout.php              # Layout comun: head, navbar mobile, tab bar bottom, footer
├── _auth-check.php          # require_once la începutul fiecărei pagini
├── panou.php                # Dashboard
├── scanare.php              # CheckIn (camera + manual)
├── vanzare.php              # Sales
├── rapoarte.php             # Reports
├── setari-scan.php          # Settings
├── evenimente.php           # Event picker (former modal)
├── staff.php                # Staff list modal page
├── guest-list.php           # Guest list modal page
├── porti.php                # Gate manager
├── asignare-personal.php    # Staff assignment
├── notificari.php           # Notifications panel
├── seating-embed.php        # Seating map (loaded in vanzare via fetch)
├── venue-eveniment.php      # VenueEventDetail (venue_owner flow)
├── venue-tichet.php         # VenueTicketDetail (venue_owner flow)
└── venue-evenimente.php     # VenueEvents (venue_owner flow)
```

### Asset-uri scan-app

```
epas/resources/marketplaces/ambilet/assets/
├── css/scan-app.css                     # Stilul mobile-first dark, nu modifică organizer.css
├── js/scan-app/
│   ├── app.js                           # App controller singleton (rute, tab switcher, badge update)
│   ├── auth.js                          # Wrapper peste AmbiletAuth + checkAuth/login/logout (venue+organizer)
│   ├── api.js                           # Wrapper peste AmbiletAPI cu rewritePath identic cu mobile client.js
│   ├── event-context.js                 # Mimează EventContext: events list, selectedEvent, stats polling 30s + Reverb
│   ├── app-context.js                   # Mimează AppContext: shift, turnover, recentScans/Sales, settings
│   ├── scanner.js                       # BarcodeDetector + jsQR fallback + camera control + vibrate/sound
│   ├── reverb-client.js                 # Echo client config (Reverb conn)
│   ├── offline-cache.js                 # IndexedDB wrapper pentru participanți + queue sync
│   ├── qr-generator.js                  # Wrapper peste qrcode lib
│   ├── pages/panou.js
│   ├── pages/scanare.js
│   ├── pages/vanzare.js
│   ├── pages/rapoarte.js
│   ├── pages/setari-scan.js
│   ├── pages/staff.js
│   ├── pages/guest-list.js
│   ├── pages/porti.js
│   ├── pages/asignare-personal.js
│   ├── pages/notificari.js
│   ├── pages/evenimente.js
│   ├── pages/venue-evenimente.js
│   ├── pages/venue-eveniment.js
│   └── pages/venue-tichet.js
└── sounds/                              # MP3-uri 1-2 KB pentru feedback
    ├── scan-success.mp3
    ├── scan-warning.mp3
    └── scan-error.mp3
```

### Librării externe (CDN, nu npm — păstrăm flow-ul existent fără build)

- **qrcode (Davidshimjs)** — generare QR pentru claim URL (5 KB CDN)
- **jsQR** — fallback QR decode pentru iOS < 16.4 (40 KB CDN)
- **@zxing/browser** — alternative decode broader (300 KB, optional, lazy load doar la nevoie)
- **laravel-echo + pusher-js** — Reverb realtime (există deja în site-uri Laravel; verificăm dacă e încărcat)

Toate adăugate doar în `_layout.php` al sub-namespace-ului scan, NU în head.php global.

---

## 3. Sidebar — adăugare nouă secțiune

În `includes/organizer-sidebar.php`, **adăugăm o singură secțiune nouă** la sfârșit (înainte de "settings section"), marcată clar:

```html
<!-- SCAN APP SECTION START — added 2026-06-12, do not remove without checking dependencies -->
<div class="mt-4 px-3">
  <p class="text-[10px] uppercase tracking-wider text-slate-500 mb-2">Aplicație scanare web (iOS-friendly)</p>
  <a href="/organizator/scan/panou" class="...">📊 Panou scan</a>
  <a href="/organizator/scan/scanare" class="...">📷 Scanare bilete</a>
  <a href="/organizator/scan/vanzare" class="...">💳 Vânzare on-site</a>
  <a href="/organizator/scan/rapoarte" class="...">📈 Rapoarte live</a>
  <a href="/organizator/scan/setari-scan" class="...">⚙️ Setări scanner</a>
</div>
<!-- SCAN APP SECTION END -->
```

**Vizibilitate:** link-urile apar pentru toți organizatorii. Pentru venue_owner, linkul direct rămâne în sidebar dar pagina detectează rolul și redirecționează la `/organizator/scan/venue-evenimente`.

---

## 4. .htaccess — adăugare reguli noi

În `epas/resources/marketplaces/ambilet/.htaccess`, **adăugăm doar reguli noi**, înainte de catch-all `/organizator/{slug}`:

```apache
# SCAN APP ROUTES START — added 2026-06-12
RewriteRule ^organizator/scan/?$                          organizer/scan-app/panou.php [L]
RewriteRule ^organizator/scan/panou/?$                    organizer/scan-app/panou.php [L]
RewriteRule ^organizator/scan/scanare/?$                  organizer/scan-app/scanare.php [L]
RewriteRule ^organizator/scan/vanzare/?$                  organizer/scan-app/vanzare.php [L]
RewriteRule ^organizator/scan/rapoarte/?$                 organizer/scan-app/rapoarte.php [L]
RewriteRule ^organizator/scan/setari-scan/?$              organizer/scan-app/setari-scan.php [L]
RewriteRule ^organizator/scan/evenimente/?$               organizer/scan-app/evenimente.php [L]
RewriteRule ^organizator/scan/staff/?$                    organizer/scan-app/staff.php [L]
RewriteRule ^organizator/scan/guest-list/?$               organizer/scan-app/guest-list.php [L]
RewriteRule ^organizator/scan/porti/?$                    organizer/scan-app/porti.php [L]
RewriteRule ^organizator/scan/asignare-personal/?$        organizer/scan-app/asignare-personal.php [L]
RewriteRule ^organizator/scan/notificari/?$               organizer/scan-app/notificari.php [L]
RewriteRule ^organizator/scan/venue-evenimente/?$         organizer/scan-app/venue-evenimente.php [L]
RewriteRule ^organizator/scan/venue-eveniment/([0-9]+)/?$ organizer/scan-app/venue-eveniment.php?id=$1 [L,QSA]
RewriteRule ^organizator/scan/venue-tichet/([0-9]+)/?$    organizer/scan-app/venue-tichet.php?id=$1 [L,QSA]
# SCAN APP ROUTES END
```

---

## 5. Etape de implementare (sortate strict de la fundație → feature → polish)

### **Etapa 0 — Pre-fligth (1 oră)**
- ✅ Documentez plan complet ( fișierul ăsta)
- Verific că `AmbiletAPI` și `AmbiletAuth` existente acoperă toate metodele de care avem nevoie. Dacă lipsesc unele, adăugăm doar metode NOI în namespace `AmbiletAPI.scan.*` ca să nu spargem nimic.
- Verific dacă Reverb (laravel-echo + pusher-js) e configurat pentru subdomain-ul ambilet — dacă nu, polling pe 10s ca fallback inițial.
- Audit `BarcodeDetector` și `jsQR`: scriu un poc de 30 linii care detectează QR din video stream — confirm că merge în iOS Safari + Android Chrome.

### **Etapa 1 — Layout + auth + navigare gol (3-4 ore)**
- Creez `scan-app/_layout.php` cu:
  - Mobile-first, dark theme (replic culoarea aplicației Android — `colors.purple = #...`)
  - Tab bar fix jos (bottom navigation) cu 5 tab-uri
  - Header sticky sus cu numele event-ului selectat + selector
  - Slot pentru conținut
- Creez `_auth-check.php` care:
  - Verifică `AmbiletAuth.requireOrganizerAuth()` sau detectează `venue_owner`
  - Redirect la `/organizator/login` dacă lipsește
- Creez `panou.php`, `scanare.php`, `vanzare.php`, `rapoarte.php`, `setari-scan.php` cu doar "Hello tab X — placeholder"
- Adaug rutele în `.htaccess`, sidebar entry
- **Smoke test:** login normal pe organizer existant → click sidebar → tab-uri navighează între ele, header păstrează event-ul

### **Etapa 2 — Auth + EventContext + AppContext (3-4 ore)**
- `auth.js` — wrapper care:
  - Re-export funcțiile relevante din `AmbiletAuth` existent
  - Adaugă `checkAuth()`, `switchOrganizer()`, gestiunea `availableOrganizers`
  - Detectează `user_type` și rerutează API-ul (identic cu `client.js` mobile)
- `event-context.js` — store reactiv (Proxy + EventTarget) cu:
  - `events`, `selectedEvent`, `eventStats`, `ticketTypes`, `allTicketTypes`
  - `fetchEvents()`, `selectEvent()`, `refreshStats()`, `refreshTicketTypes()`
  - Polling 30s pentru stats + Reverb sub
- `app-context.js` — store reactiv cu:
  - Setări persistate în localStorage (`vibrationFeedback`, `soundEffects`, `autoConfirmValid`)
  - Shift state: `shiftStartTime`, `cashTurnover`, `cardTurnover`, `myScans`, `mySales`
  - `recentScans` (last 50, IndexedDB), `recentSales` (last 20, IndexedDB)
  - Online/offline detector
- **Smoke test:** login → events fetched → selectează event → state updates → reload page → state se restaurează din localStorage/IndexedDB

### **Etapa 3 — Scanner camera (5-7 ore — cel mai delicat)**
- `scanner.js`:
  - Pornire camera `getUserMedia({ video: { facingMode: 'environment' } })`
  - Detector primar: `BarcodeDetector` cu formate `['qr_code', 'code_128', 'ean_13', 'ean_8']`
  - Fallback: `jsQR` rulat pe frame din `<canvas>` (decode QR only)
  - Fallback secundar: `@zxing/browser` lazy-loaded pentru iOS vechi
  - Animație scan line (CSS keyframes, nu RN Animated)
  - Vibrate prin `navigator.vibrate()` (silent fail iOS)
  - Sunet prin Web Audio API (preload 3 mp3-uri mici)
  - Dedup local: Set in-memory de coduri scanate în ultimele 60s
  - Permission management: detect "denied", arată mesaj educativ cu cum se acceptă în Safari/Chrome
- `scanare.php` + `pages/scanare.js`:
  - UI: card cu camera preview, overlay scan line, buton "Manual" / "Camera off"
  - Submit la `POST /organizer/participants/checkin`
  - Result card: green / amber / red (același vocabular ca mobile)
  - Auto-advance dacă `autoConfirmValid`
  - Mod offline: dacă `offlineMode` activ → match local
  - Mod reports-only: detectează `selectedEvent.timeCategory === 'past'` și arată placeholder cu link la `/organizator/scan/rapoarte`
- **Smoke test:** scanez un QR real cu telefon iOS + Android → check-in success → vibrate + sunet → stats live update via Reverb

### **Etapa 4 — Dashboard / Panou (3-4 ore)**
- `panou.php` + `pages/panou.js`:
  - Dual-mode: admin vs scanner (după `userRole`)
  - **Admin view:**
    - Card eveniment selectat cu countdown
    - Grid quick-actions: Scan / Sell / Guest List / Staff
    - Card "Intrați" → modal "TicketSalesByType" (lista tipuri cu progress check-in)
    - Card "Vânzări" → modal "SalesBreakdown" (online vs POS)
    - Card "Venituri" → modal același breakdown
    - Card "Rămase" → modal "RemainingByType"
    - Buton "Închide Tura" → modal `ShiftSummary`
    - Pull-to-refresh = refresh button în header
  - **Scanner view (staff):**
    - Card personal turnover (cash + card)
    - Card scans count + sales count
    - Card duration shift
    - Buton "Începe Scanarea" → tab scanare
    - Buton "Începe Vânzarea" → tab vânzare
    - Buton "Închide Tura"
  - Auto-refresh stats 30s + Reverb push
- **Smoke test:** admin vede toate cardurile cu date reale, staff vede only personal stats

### **Etapa 5 — Sales / Vânzare on-site (6-8 ore — al doilea cel mai delicat)**
- `vanzare.php` + `pages/vanzare.js`:
  - View 1: Selectare tipuri bilete (grid cu culoare/preț/disponibilitate)
  - Coș: cantitate +/-, total, comision (included vs added-on-top)
  - Payment method: Cash / Card (NU NFC — exclus per cerință)
  - Submit: `POST /orders` cu `source='pos_app'`
  - Generare claim URL: `POST /orders/{id}/generate-claim-url`
  - Afișare QR claim (qrcode.js) — client îl scanează cu telefonul pt email
  - Polling status claim 5s (`GET /claim/{token}/status`)
  - Auto-check-in dacă `autoConfirmValid`
  - Replay QR pentru vânzări recente
- Pentru evenimente cu seating: `seating-embed.php` într-un modal sau drawer
- **Smoke test:** creez o comandă POS cu plată cash → primesc QR → claim valid pe alt device

### **Etapa 6 — Rapoarte (2-3 ore)**
- `rapoarte.php` + `pages/rapoarte.js`:
  - Check-in rate, total sold, peak hour
  - Sparkline pentru check-in rate (CSS-only sau Chart.js mic)
  - Past event selector
  - Gate performance bars
  - Revenue breakdown per ticket type
  - Hourly distribution chart (16:00-22:00 placeholder)
  - Buton "Exportă Raport" → CSV download (deja există export endpoint)
- **Smoke test:** vizualizare rapoarte pentru event live + past

### **Etapa 7 — Settings + Modaluri admin (4-5 ore)**
- `setari-scan.php`:
  - Toggle vibration, sound, auto-confirm
  - Toggle offline mode + download participants
  - Hardware status (placeholder pentru POS bancar — disconnected, info: "Plata cu card via POS bancar nu e suportată în versiunea web")
  - Linkuri către pagini admin: Porți, Asignare personal
  - Logout / end shift
- `porti.php` — CRUD gates
- `asignare-personal.php` — Assignment members ↔ gates
- `staff.php` — Listă staff
- `guest-list.php` — Listă participants cu filtre + search + export
- `notificari.php` — Listă notificări (in-memory, fallback la endpoint dacă există)
- `evenimente.php` — Event picker (alternativ la selector din header)
- **Smoke test:** admin face CRUD pe gates, asignează staff la gate, vede schimbarea reflectată

### **Etapa 8 — Venue owner flow (3-4 ore)**
- `_auth-check.php` detectează `user_type === 'venue_owner'`
- Override sidebar: ascunde linkurile organizer-only, arată doar 4 tab-uri
- `venue-evenimente.php`, `venue-eveniment.php`, `venue-tichet.php`
- API rewriter (`/organizer/...` → `/venue-owner/...`) în `api.js` scan-app
- Note polymorphic CRUD în `venue-tichet.php`
- **Smoke test:** login venue_owner → vede event-urile lui → scanează → vede atendees + note

### **Etapa 9 — PWA / iOS polish (2-3 ore)**
- `manifest.json` pentru "Add to Home Screen" pe iOS — devine practic app
- Splash screen, icon, theme color
- Service worker minimal pentru cache offline (script + CSS)
- Meta viewport corect pentru notch iPhone
- Wake Lock API request la intrare în Scan / Vânzare
- Test pe iPhone real cu Safari → adăugat pe ecran de start → arată ca app nativ

### **Etapa 10 — QA + documentație (2 ore)**
- Test matrix: iOS Safari 16+ / iOS Safari 15 (fallback jsQR) / Android Chrome / Desktop Chrome
- Test rolurile: admin / manager / staff / venue_owner
- Test offline mode → reconectare
- Document `SCAN_APP_README.md` cu structură + cum se extinde
- Commit + push pe `core`, deploy `ambilet` branch via `deploy-ambilet.bat`

---

## 6. Estimare totală

| Etapă | Ore estimate |
|---|---|
| 0 — Pre-flight | 1 |
| 1 — Layout + nav | 3-4 |
| 2 — Auth + contexts | 3-4 |
| 3 — Scanner camera | 5-7 |
| 4 — Dashboard | 3-4 |
| 5 — Sales | 6-8 |
| 6 — Rapoarte | 2-3 |
| 7 — Settings + admin modals | 4-5 |
| 8 — Venue owner flow | 3-4 |
| 9 — PWA polish | 2-3 |
| 10 — QA + docs | 2 |
| **TOTAL** | **34-45 ore** |

Estimare pesimistă: ~6-7 zile de muncă focusată sau ~10-12 zile cu testare live și iterații.

---

## 7. Riscuri și soluții

| Risc | Probabilitate | Mitigare |
|---|---|---|
| `BarcodeDetector` nu funcționează pe iOS < 16.4 | Mare | Fallback la `jsQR` (testat pe iOS 14+) |
| Camera nu primește permisiune din web view embedded în Add-to-Home-Screen | Mediu | Documentăm flow-ul: user trebuie să accepte explicit, arătăm tutorial |
| Reverb nu e configurat pe ambilet.ro pentru subdomain CORS | Mediu | Polling 10s ca fallback default, Reverb e bonus |
| `AmbiletAPI` actual nu expune toate metodele necesare | Mic | Adăugăm metode NOI în namespace `scan.*`, nu modificăm existente |
| Inline CSS Tailwind se ciocnește cu scan-app.css | Mic | Folosim prefix BEM `scanapp-*` pentru toate clasele noi |
| Plata cash nu generează factură fiscală pe POS bancar | N/A | Excluse per cerință — afișăm doar mesaj informativ în setări |
| Update APK Android nu mai e necesar pentru iOS users — păstrăm două ecosisteme | Acceptabil | Documentăm că pe iOS = web, pe Android = APK; ambele consumă același API |

---

## 8. Decizii de design pe care le aștept de la tine înainte de a începe

1. **Numele afișat în sidebar:** "Aplicație Scan Web" vs "Tixello Scan" vs "iOS Scan" vs altceva?
2. **URL prefix:** `/organizator/scan/*` (recomandat) vs `/organizator/app/*` vs `/organizator/mobil/*`?
3. **Iconul / branding:** folosim aceleași icoane SVG ca app-ul Android (le pot extrage din `App.js` linile 56-122)?
4. **Vrei să apară link-ul scan-app pentru TOȚI organizatorii din momentul deploy, sau doar pentru o whitelist pe care o controlezi (ex: feature flag în DB)?**
5. **PWA cu Add-to-Home-Screen vs doar pagină web normală?** PWA = experiență de app, dar necesită HTTPS și asset suplimentar (manifest, SW).
6. **Suport venue_owner în această iterație** sau doar organizer/team_member, și venue_owner îl facem în iterația 2?
7. **Suport leisure (Sf. Ana) în scan-app?** Mobile app are screens leisure separate (BoatsOperator, PontoonOperator, etc.) — sunt în tixello-sfana, NU în tixello-app. Deci în scope NU intră — dar dacă vrei să le incluzi pentru organizatorii leisure care n-au Android, e o etapă separată.

---

## 9. Ce NU se modifică (lista albă pentru garanție)

Pentru claritate maximă, aceste fișiere/foldere **RĂMÂN INTACTE** și nu vor fi atinse decât cu adăugări non-distructive (sidebar.php, .htaccess, eventual api.js):

- `organizer/dashboard.php` ✋
- `organizer/events.php` ✋
- `organizer/participants.php` ✋
- `organizer/sales.php` ✋
- `organizer/team.php` ✋
- `organizer/settings.php` ✋
- `organizer/leisure-*.php` (toate) ✋
- `organizer/billing.php`, `documents.php`, `finance.php`, etc. ✋
- `assets/js/api.js` (doar ADAUGĂ metode în namespace nou) ✋
- `assets/js/auth.js` (doar ADAUGĂ funcții) ✋
- `assets/css/organizer.css` ✋ (folosim `scan-app.css` separat)
- `api/proxy.php` ✋ (doar adăugări de rute proxy dacă apar)
- `includes/head.php`, `includes/organizer-topbar.php`, `includes/organizer-footer.php` ✋
- `includes/organizer-sidebar.php` — **doar ADAUGĂ secțiune nouă cu markeri START/END**
- `.htaccess` — **doar ADAUGĂ reguli noi în zonă marcată START/END**

---

**Next step așteaptă de la tine:** răspunsuri la cele 7 decizii de design (secțiunea 8), apoi încep implementarea de la Etapa 0.

---

## 10. Decizii confirmate de user (2026-06-12)

1. **Nume afișat sidebar:** "Aplicație Scan"
2. **URL prefix:** `/organizator/scan/*`
3. **Icoane SVG:** refolosim din App.js mobil
4. **Vizibilitate:** toți organizatorii deodată (no feature flag)
5. **PWA cu Add-to-Home-Screen:** DA. Pentru Android afișăm banner cu redirect la descărcarea APK; pentru iOS afișăm tutorial "Adaugă pe ecranul de start"
6. **Venue_owner flow:** EXCLUS din iterația 1, va fi iterația 2
7. **Leisure (Sf. Ana) screens:** EXCLUSE definitiv (sunt în tixello-sfana, nu tixello-app)

**Scope iterația 1 (în execuție):** organizer/admin/team_member organizer (NU venue_owner, NU leisure). 5 tab-uri principale + modaluri admin + PWA cu Add-to-Home-Screen iOS optimizat + banner Android "descarcă APK".
