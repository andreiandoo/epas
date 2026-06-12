# Aplicație Scan (Web)

Replica web a aplicației mobile Tixello Scan (Ambilet Scan APK) pentru organizatorii care folosesc iOS și nu pot instala APK-ul.

**URL pe live:** `https://ambilet.ro/organizator/scan/`

---

## Garanție additivă

**ZERO ștergeri sau modificări la flow-urile existente** din panoul organizator. Toate paginile existente (`dashboard.php`, `events.php`, `participants.php`, `sales.php`, `team.php`, `settings.php`, `leisure-*.php`, etc.) rămân **identice**.

Singurele două fișiere existente atinse au modificări marcate clar cu comentarii **START/END** ca să fie ușor de revertit:

- `includes/organizer-sidebar.php` — inserție între `<!-- SCAN APP SIDEBAR SECTION START ... END -->`
- `.htaccess` — reguli noi între `# SCAN APP ROUTES START ... END`

Nimic în `assets/js/api.js`, `assets/js/auth.js`, `api/proxy.php`, `includes/head.php` sau `includes/organizer-topbar.php` nu a fost modificat.

---

## Pagini scan-app

| URL | Fișier PHP | Conținut |
|---|---|---|
| `/organizator/scan/panou` | `panou.php` | Dashboard cu admin view (statistici live, quick actions, modaluri) sau scanner view (turnover personal + duration tură) |
| `/organizator/scan/scanare` | `scanare.php` | Scanner camera (BarcodeDetector + jsQR fallback) + manual entry |
| `/organizator/scan/vanzare` | `vanzare.php` | Grid tipuri bilete, coș, plată cash/card, QR claim pentru email tickets |
| `/organizator/scan/rapoarte` | `rapoarte.php` | Check-in rate, per-type performance, activitate recentă, export CSV |
| `/organizator/scan/setari-scan` | `setari-scan.php` | Toggle vibrație/sunet/auto-confirm, admin section, logout, install banners |
| `/organizator/scan/porti` | `porti.php` | Gate manager — CRUD porți de acces (doar admin) |
| `/organizator/scan/asignare-personal` | `asignare-personal.php` | Atribuire membri echipă la porți (doar admin) |
| `/organizator/scan/manifest.webmanifest` | `manifest.php` | PWA manifest JSON |
| `/organizator/scan/sw.js` | `sw.php` | Service worker |
| `/organizator/scan/icon.php?size=N` | `icon.php` | Generator PNG dinamic via GD library |

---

## Asset-uri scan-app

```
assets/css/scan-app.css                  — stilul mobile-first dark (clase scanapp-*)
assets/js/scan-app/auth.js               — ScanAuth (wrapper peste AmbiletAuth)
assets/js/scan-app/app-context.js        — AppContext (settings, shift, recent activity)
assets/js/scan-app/event-context.js      — EventContext (events, selectedEvent, stats polling)
assets/js/scan-app/app.js                — bootstrap (auth gate, header binding, toast)
assets/js/scan-app/scanner.js            — camera scanner (BarcodeDetector + jsQR fallback)
assets/js/scan-app/pages/panou.js        — controller dashboard
assets/js/scan-app/pages/scanare.js      — controller scanare
assets/js/scan-app/pages/vanzare.js      — controller vânzare
assets/js/scan-app/pages/rapoarte.js     — controller rapoarte
assets/js/scan-app/pages/setari-scan.js  — controller setări
assets/js/scan-app/pages/porti.js        — controller gate manager
assets/js/scan-app/pages/asignare-personal.js — controller staff assignment
```

---

## API-uri folosite

Toate endpoint-urile **există deja** pe server, sunt **identice** cu cele folosite de aplicația mobilă Android. Zero modificări backend necesare.

| Endpoint | Folosit în |
|---|---|
| `GET /organizer/events?published_only=true&per_page=100` | EventContext.fetchEvents |
| `GET /organizer/events/{id}` | EventContext.refreshTicketTypes, panou.js (revenue breakdown) |
| `GET /organizer/events/{id}/participants?per_page=1` | EventContext.refreshStats |
| `POST /organizer/participants/checkin` | scanner check-in |
| `POST /orders` (source='pos_app') | vânzare on-site |
| `POST /orders/{id}/generate-claim-url` | claim QR pentru email tickets |
| `GET /claim/{token}/status` | polling claim status |
| `POST /orders/{id}/pos-complete` | auto check-in dacă autoConfirmValid |
| `GET /organizer/events/{id}/participants/export` | export CSV rapoarte |
| `GET /organizer/venues/{id}/gates` (+CRUD) | gate manager |
| `GET /organizer/team`, `POST /organizer/team/update` | staff assignment |
| `GET /organizer/events/{id}/sales-breakdown` (cu fallback `/events/{id}/sales-breakdown`) | revenue modal |

---

## Funcționalități excluse din iterația 1

Conform deciziilor user-ului (2026-06-12):

- ❌ **Plată prin NFC (Stripe Tap)** — payment button NU apare în UI; fizic nu se poate face în browser fără hardware specializat
- ❌ **Conexiune cu POS bancar fizic** — același motiv
- ❌ **Venue owner flow** (`/venue-owner/*` API rewriter, VenueEvents/VenueEventDetail/VenueScan) — planificat pentru iterația 2
- ❌ **Leisure (Sf. Ana) operator screens** — sunt în `tixello-sfana/`, nu în `tixello-app/`; out of scope definitiv

---

## PWA — instalare ca app nativă

### Android (Chrome / Edge)

1. Vizitează `https://ambilet.ro/organizator/scan/panou`
2. Chrome arată automat un banner "Adaugă pe ecranul de start" după câteva secunde
3. **SAU** menu (⋮) → "Instalează aplicația"
4. App se deschide standalone fără browser chrome

**Recomandare în UI:** banner-ul Android din `setari-scan.php` redirecționează spre descărcarea APK-ului nativ pentru o experiență mai bună la scanare îndelungată.

### iOS (Safari 14+)

1. Vizitează `https://ambilet.ro/organizator/scan/panou` în **Safari** (nu Chrome — A2HS funcționează doar în Safari pe iOS)
2. Atinge butonul **Distribuie** (pătrat cu săgeată în sus)
3. Scroll și alege **Adaugă pe ecranul de start**
4. App apare pe home screen cu iconul scan-app

Tutorial-ul este afișat automat în `setari-scan.php` când userul intră de pe iOS Safari și NU rulează în standalone mode.

---

## Bibliotecile externe folosite (CDN, fără build pipeline)

| Bibliotecă | URL | Folosit pentru |
|---|---|---|
| `qrcode@1.5.3` | https://cdn.jsdelivr.net/npm/qrcode@1.5.3/build/qrcode.min.js | Generare QR claim URL în vânzare |
| `jsqr@1.4.0` | https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.min.js | Fallback decode QR pe iOS Safari < 16.4 (lazy-loaded) |

Acestea sunt cache-uite de service worker (stale-while-revalidate) pentru offline.

---

## State management — cum funcționează

### EventContext (`event-context.js`)
- Singleton care ține lista de evenimente + `selectedEvent` + stats
- Folosește **pub/sub via EventTarget** (no React/Vue, doar custom events)
- Polling 30s pentru stats când există event selectat
- Pauză automată polling când tab-ul e ascuns; reia + refresh imediat când redevine vizibil
- `categorizeEvent()` port direct din `tixello-app/src/utils/eventCategories.js` (inclusiv fix-ul nostru recent pentru evenimente range în desfășurare unde server-side `event_date = now()` evită bug-ul de timing)

### AppContext (`app-context.js`)
- Settings persistate în `localStorage` (`scanapp_settings_v1`): vibrație, sunet, auto-confirm, mod offline
- Shift state persistat în `sessionStorage` (`scanapp_shift_v1`): start time, turnover cash/card, scan/sale count, pause flag
- Recent activity per eveniment în `localStorage` (`scanapp_scans_<eventId>`, `scanapp_sales_<eventId>`) — max 50 scanări, max 20 vânzări

### ScanAuth (`auth.js`)
- Read-only wrapper peste `AmbiletAuth` global existent
- Expune `getUserRole()`, `isAdmin()`, `isStaff()`, `hasPermission()`, `getOrganizer()`, `getTeamMember()`, `userType()`, `logout()`
- Nu modifică niciodată shape-ul localStorage al lui `AmbiletAuth`

---

## Scanner camera — strategie multi-tier

`scanner.js` încearcă în ordine:

1. **Native `BarcodeDetector` API** (Chrome 88+, Edge, Safari 16.4+, Android Chrome) — formate: `qr_code`, `code_128`, `code_39`, `ean_13`, `ean_8`. Decode hardware-accelerated, low battery.
2. **`jsQR`** lazy-loaded din jsdelivr CDN — pentru iOS Safari < 16.4. Decode pe canvas la 6.5fps. Doar QR.
3. **Manual entry** — bottom sheet cu input pentru hardware barcode reader sau introducere de la tastatură.

Dedup în memorie: same code în 60s e ignorat (prevenire spam check-in cu același bilet în frame).

Wake Lock API ține ecranul aprins (silent no-op pe browsere fără suport).

Feedback:
- Sunet: Web Audio API generat dinamic (no mp3 files) — beep distinct success/warning/error
- Vibrație: `navigator.vibrate` cu pattern-uri identice cu mobile (200ms valid, `[0,100,100,100]` duplicate, `[0,200,100,200,100,200]` invalid)

---

## Testare manuală end-to-end

### Pe Android Chrome
1. ✅ Login organizator standard
2. ✅ Sidebar → "Aplicație Scan (iOS)" → click "Panou scan"
3. ✅ Verifică că hero card afișează numele evenimentului live + countdown
4. ✅ Click "Intrați" / "Vândute" / "Venituri" / "Rămase" → modaluri se deschid cu defalcare
5. ✅ Tab "Scanare" → "Pornește camera" → permite acces → scanează un QR real
6. ✅ Result card verde apare + sunet + vibrație
7. ✅ Tab "Vânzare" → adaugă bilete în coș → "Plătește" → "Numerar" → primește QR claim
8. ✅ Tab "Rapoarte" → vezi rate, type breakdown, activitate
9. ✅ Tab "Setări" → toggle vibrație off → scanează din nou → fără vibrație
10. ✅ Setări → "Administrare porți" → adaugă o poartă → confirmă
11. ✅ Setări → "Asignează personal" → schimbă poartă pentru un membru → confirmă

### Pe iOS Safari
1. ✅ Login + navigare la `/organizator/scan/panou`
2. ✅ Setări → vezi banner "Folosești iPhone?" → urmează instrucțiunile A2HS
3. ✅ Deschide app de pe home screen → rulează standalone fără address bar
4. ✅ Scanare camera funcționează (cere permisiune cameră una singură dată în Settings)

---

## Iterația 2 — venue_owner

În scope:
- `/organizator/scan/venue-evenimente` — listă evenimente venue
- `/organizator/scan/venue-eveniment/{id}` — detail event + listă atendees searchable
- `/organizator/scan/venue-tichet/{id}` — detail bilet + note polymorphic
- Rewriter în `scan-app/api.js` (similar cu `client.js` mobile): paths `/organizer/*` → `/venue-owner/*` când `ScanAuth.userType() === 'venue_owner'`
- Sidebar: ascunde linkurile organizer-only când venue_owner, afișează doar 4 tab-uri

Estimare: 3-4 ore.

---

## Documentație complementară

- Plan detaliat: `plans/TIXELLO_WEB_SCAN_PLAN.md` (în rădăcina repo-ului main)
- Build APK Android: `TIXELLO_APK_BUILD_STEPS.md`
