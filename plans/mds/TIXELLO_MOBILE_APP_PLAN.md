# Tixello — Aplicație Mobilă (Staff / Organizator / POS & Scanare)

**Status:** 🟡 Fază 1 — planificare (structură ecrane + funcții)
**Data:** 2026-07-31
**Model de referință:** `ambilet-app2` (Tixello Scan APK, bundle `com.ambilet.scan.next`) + replica web `/organizator/scan/*`
**Obiectiv:** o singură aplicație mobilă „Tixello", multi-brand, care permite **oricărui tenant** al platformei **și oricărui organizator dintr-un marketplace** să **vândă bilete, să le scaneze/valideze, să vadă rapoarte și să-și gestioneze echipa** — de pe telefon.

---

## 0. Sinteză & principii

`ambilet-app2` este astăzi o aplicație de scanare + POS **legată de un singur marketplace** (AmBilet), pentru organizatorii lui. Aplicația **Tixello** generalizează acest model la întreaga platformă:

Platforma rulează **două lumi comerciale** peste aceleași tabele `Order`/`Ticket`:

```
TIXELLO (core.tixello.com)
│
├── Lumea TENANT (white-label SaaS)
│     Tenant (teatru / festival / agenție / artist)
│       └── Event → TicketType → Ticket / Order (tenant_id)
│
└── Lumea MARKETPLACE
      MarketplaceClient (AmBilet, bilete.online, …)
        └── MarketplaceOrganizer
              ├── MarketplaceOrganizerTeamMember (staff: scanner, casier…)
              └── MarketplaceEvent → MarketplaceTicketType → Ticket / Order
```

**Principiile aplicației Tixello:**

1. **Un singur binar, multi-context.** La login, aplicația rezolvă *contextul de lucru* al contului (tenant / organizator-marketplace / membru-echipă / venue-owner) și adaptează navigația, permisiunile, brandingul și baza API.
2. **Auth prin Sanctum** (bearer token). Cheia de marketplace (`mpc_`) **nu** se împachetează în binar — ea doar identifică marketplace-ul; token-ul Sanctum autorizează operatorul. Backend-ul există deja și este identic cu cel folosit de APK-ul Android.
3. **Roluri → persona.** Owner/Admin, Manager, Staff (scanner), Casier, `admin_mobile` (scan+vânzare), roluri leisure specializate, Venue-owner (read-only + scan).
4. **Branding la runtime.** Logo / culori / nume se aduc per-context: tenant → `/api/tenant-client/theme`; marketplace → `/api/marketplace-client/config`; organizator → `/organizer/me` + `/organizer/mobile-settings`.
5. **Additiv & zero-backend-nou (fază 1).** Toate endpoint-urile de mai jos **există deja** pe server.
6. **Offline-first la scanare.** Coadă locală de scanări + retry; dedup 60s per cod; audit în `LeisureScanAttempt`.

---

## 1. Arhitectura de navigație

### 1.1. Tab bar de jos (adaptiv pe rol)

| Tab | Owner/Admin | Manager | Casier (`pos_cashier`) | Scanner (`check_in`) | Venue-owner |
|---|:---:|:---:|:---:|:---:|:---:|
| **Panou** (Dashboard) | ✅ | ✅ | ✅ (tură) | ✅ (tură) | ✅ |
| **Scanare** | ✅ | ✅ | — | ✅ | ✅ |
| **Vânzare** (POS) | ✅ | ✅ | ✅ | — | — |
| **Rapoarte** | ✅ | ✅ | parțial | parțial | ✅ (event lui) |
| **Mai multe** (Setări/Echipă/Porți) | ✅ | ✅ | ✅ | ✅ | ✅ |

- Scannerul „pur" vede maximum 3 taburi (Panou, Scanare, Mai multe) — la fel ca în app-ul actual.
- `admin_mobile` = Scanare **+** Vânzare.
- Rolurile leisure (`rental_boats`, `validation_pontoon`, …) înlocuiesc Vânzare/Scanare cu ecranele lor specializate.

### 1.2. Header de aplicație (persistent — v2)
Bară de sus prezentă pe **fiecare** ecran (din modelul template-urilor):
- **Avatar organizator** (inițiale, colorat cu accentul contextului) — tap → deschide comutatorul de context.
- **Nume + tip** (ex. „Teatrul Național · Cluj"), scurtat cu ellipsis.
- **Buton comutare context** (switch) — pentru conturile cu mai multe entități.
- **Clopoțel notificări** cu **badge** (număr necitite) sau punct roșu → deschide Centrul de notificări (ecran N, §J).
- Pe ecranele operaționale (Scanare/Vânzare) header-ul se restrânge: buton înapoi + titlu + clopoțel; pastila **Live/Offline** (Reverb) apare lângă titlu.

### 1.3. Accent per-context (branding)
Fiecare tenant/organizator are un **accent** propriu (variabila `--ac`) care temează întreaga interfață (hero, KPI, butoane, badge-uri): organizator=teal, teatru=violet, venue=cyan, artist=turcoaz, agenție=mov, festival=pink. Comutarea de context schimbă accentul + logo + nume + navigația.

### 1.4. Navigație pe tip de tenant (v2)
Peste nucleul comun, tab-bar-ul de jos diferă după tipul de cont (din template-uri):

| Tip cont | Tab bar |
|---|---|
| **Organizator** (marketplace) | Home · Evenimente · Vânzări · Scanare · Setări |
| **Teatru** | Home · Abonamente · Vânzări · Scanare · Setări |
| **Venue** | Home · Evenimente · **Live** (gate-uri) · Vânzări · Setări |
| **Artist** | Home · Show-uri · Vânzări · **Fani** · Setări |
| **Agenție** | Home · **Artiști** · Shows · **Contracte** · **Financiar** |
| **Festival** | Home · Zile/Scene · Vânzări · Scanare · Setări |

### 1.5. Selector eveniment / workspace
- **Selector eveniment** pe Panou (interactiv), read-only pe Scanare/Vânzare.
- **Comutator context** (organizator/tenant/venue) via avatar/switch — `switch-organizer`, `available_organizers[]`.

---

## 2. Ecrane & funcții — inventar complet

### A. Onboarding, autentificare & context

| # | Ecran | Funcții | Endpoint-uri |
|---|---|---|---|
| A1 | **Splash / bootstrap** | Restaurează token, rezolvă context & branding, verifică versiune app | `GET /organizer/me`, `GET /api/app-version` |
| A2 | **Login** | Email+parolă; normalizează email; owner **sau** team-member; suspendat → 403; întoarce `{organizer, token, available_organizers[]}` | `POST /organizer/login` |
| A3 | **Recuperare parolă** | Forgot + reset | `POST /organizer/forgot-password`, `/reset-password` |
| A4 | **Verificare email** | Verify + resend | `POST /organizer/verify-email`, `/resend-verification` |
| A5 | **Acceptă invitație (deep-link)** | Validează token invitație; dacă are deja parolă → skip form; setează parolă & activează | `GET /organizer/team/validate-invite`, `POST /organizer/team/accept-invite` |
| A6 | **Semnare contract** | Blochează accesul organizatorului până semnează (obligatoriu din 2026-07-24) | `GET /organizer/contract`, `/contract/download`, `POST /organizer/contract/sign` |
| A7 | **Selector workspace / organizator** | Comută între organizatorii la care are acces; emite token nou, revocă vechi | `POST /organizer/switch-organizer` |

### B. Panou (Dashboard) — două vederi pe rol

**B1. Vedere Admin/Owner** (`GET /organizer/events/{event}/statistics`, `.../sales-breakdown`)
- Hero eveniment: dată + countdown + venue; badge „Viitor / Live / Încheiat".
- Grid 2×2 KPI (fiecare card = buton → bottom-sheet cu defalcare):
  - **Scanați** (persoane intrate) → deschide Listă invitați
  - **Vândute** (bilete) → defalcare pe tip bilet
  - **Încasări** (lei) → defalcare venituri
  - **Disponibile** (bilete rămase)
- **Bară capacitate** (vândute / total).
- **Online vs. la ușă** — bară segmentată; click segment → defalcare pe tip (sursă `pos_app` = „la ușă").
- **Acțiuni rapide**: Scanare · Vânzare · Listă invitați · Echipă.
- **Activitate recentă** (scanări + vânzări locale).
- **Închide tura** · pull-to-refresh.
- **Mod „doar rapoarte"** când evenimentul e trecut (grid colorat static în loc de stats live).

**B2. Vedere Scanner/Staff** (stare tură din `sessionStorage` + `GET /me/active-shift`)
- Card **Încasări tură**: Numerar + Card.
- 3 statistici: Scanări · Vânzări · Durată tură.
- Butoane mari: Începe Scanarea · Începe Vânzarea.
- Închide tura.

### C. Scanare (validare check-in)

| Funcție | Detaliu | Endpoint |
|---|---|---|
| **Cameră scanner** | Multi-tier: `BarcodeDetector` nativ → `jsQR` fallback → hardware/manual. Formate: QR, Code128/39, EAN-13/8. Dedup 60s. Wake-lock ecran. | — |
| **Check-in după cod** | Endpoint global; normalizează URL-uri `/v/`, `/t/`, `/verify/`; detectează `STAFF-` (pontaj personal); rezolvă bilet / invitație / external / activity | `POST /organizer/participants/checkin` |
| **Check-in scoped pe eveniment** | Variantă legată de un eveniment anume | `POST /organizer/events/{event}/check-in/{barcode}` |
| **Undo check-in** | Anulează validarea (curăță `checked_in_at`) | `DELETE /organizer/events/{event}/check-in/{barcode}` |
| **Card rezultat** | Valid (verde) / Duplicat (galben, arată cine & când) / Invalid (roșu). Afișează atendee, tip, **loc** (`seat_label`/section/row/seat), `venue_notes`. Sunet + vibrație distincte. | payload din `buildTicketScanPayload` |
| **Cod manual** | Bottom-sheet input pentru cititor hardware / tastatură | idem check-in |
| **Scanări recente** | Listă live scanări (max 50) | local |
| **Activity booking** | Lookup (preview) → confirmă; cod = `confirmation_code` (toată rezervarea) sau `ticket.code` (un participant) | `GET /organizer/activity-bookings/lookup/{code}`, `POST /.../check-in/{code}`, `DELETE …` |
| **Contorizare** | scanări/min, în așteptare, intrați | derivat |
| **Lock eveniment trecut** | Redirect → Rapoarte | — |

**Contract erori** (important pentru UI): duplicat/invalid → HTTP 400 `success:false` + mesaj uman + payload complet (pentru „cine a intrat deja"). `/tickets/validate` (read-only, doar `marketplace.auth`) → `{valid:bool}` fără mutație (util pentru scanner light / box-office).

### D. Vânzare (POS / box office)

| Funcție | Detaliu | Endpoint |
|---|---|---|
| **Grid tipuri bilete** | Doar bilete de intrare (`is_entry_ticket`); preț, stoc, min/max | `GET /organizer/events/{event}` |
| **Coș** | Cantități 1–20, subtotal | local |
| **Selectare locuri (sub-ecran hartă)** | Sub-ecran dedicat (v2): **scenă** sus, **secțiuni** (parter/lojă/balcon), locuri cu stări **liber/selectat/vândut/VIP**, legendă, rezumat selecție (ex. „B2 · B3 · 500 lei") → confirmă. Randat în **WebView** cu token semnat HMAC; hold temporar pe locuri | `GET /organizer/events/{event}/seating-map`, `POST /organizer/seating/embed-token`, `POST /api/public/seats/{hold,release,confirm}` |
| **Creare comandă** | `source=pos_app`; `tickets[]`, `seat_uids[]`, `customer{}` opțional, `locale` | `POST /orders` |
| **Plată** | Bottom-sheet metodă: **Numerar** / **Card POS** / **Card NFC** (dacă `card_nfc_enabled`) / **Factură** (leisure B2B) | `POST /orders/{order}/pay`, `GET /orders/{order}/payment-status` |
| **Finalizare POS** | `pos-complete` cu `auto_checkin` opțional (`checked_in_via='pos_app'`) | `POST /orders/{order}/pos-complete` |
| **Livrare bilet** | Trimite pe email **sau** QR de „claim" (predă biletul cumpărătorului) + polling status claim | `POST /orders/{order}/send-tickets`, `/generate-claim-url`, `GET /claim/{token}/status` |
| **Print** | Client-side (ESC/POS thermal via WebUSB / Android print); tipărește shortlink `/v/CODE?p=pos` | `pos-printer.js` |
| **Vânzări azi** | Listă vânzări din tură | local + `sales-breakdown` |
| **Anulare / refund** | Din POS | `POST /orders/{order}/cancel`, `/refund` |

**Leisure POS** (parcuri / atracții — `organizer_type=leisure`):
- **Sesiune casă obligatorie** (deschide/închide tejghea) înainte de orice vânzare (altfel 403 „Casa este închisă"): `GET/POST /organizer/events/{event}/leisure/cashier/{current,open,close}`, `/sessions`, `/sales-csv`.
- Vânzare produse: `POST /organizer/events/{event}/leisure/pos-sale` (`items[]{ticket_type_id,qty,variant_id,slot_time,addons[]}`, `payment_method` ∈ `cash|card|invoice`).
- Factură B2B: `POST /organizer/orders/{order}/generate-invoice`.
- Închirieri bărci (F7): `boatsIndex/Sync`, `activeRentals`, `rentalStart/End/Finalize`.

### E. Rapoarte

| Raport | Detaliu | Endpoint |
|---|---|---|
| **Selector eveniment (trecut)** | Filtre an/lună | `GET /organizer/events` |
| **Rezumat eveniment** | Rata check-in, total vândute, intrați, venituri | `GET /organizer/events/{event}/statistics` |
| **Performanța porților** | Defalcare pe poartă | `statistics` / `scans-detail` |
| **Per tip bilet** | Defalcare vânzări | `sales-breakdown` |
| **Raport staff (vânzări/persoană)** | Bucketing pe `team_member_id` / `sold_by` | `GET /organizer/events/{event}/staff-report` |
| **Scanări (leisure)** | Chart pe zi: așteptat vs. real; detaliu pe zi | `GET …/leisure/scans`, `/scans-detail` |
| **Pontaj staff leisure** | Log check-in-uri STAFF- + agregate | `GET /organizer/leisure/staff-checkins` |
| **Export CSV** | Participanți / raport / vânzări / staff | `…/participants/export`, `…/report/export`, `…/leisure/sales/range-csv`, `…/leisure/staff-export` |
| **Finance / payouts** | Sold, tranzacții, cereri decont | `GET /organizer/balance`, `/finance`, `/transactions`, `/payouts` |

### F. Participanți / Listă invitați
- Statistici: Total · Intrați · Lipsesc.
- Filtre: Toți / Intrați / Neintrați.
- Căutare după nume, email sau cod bilet.
- Check-in manual din listă.
- Endpoint: `GET /organizer/events/{event}/participants`, `GET /organizer/participants` (toate evenimentele), `/export`.

### G. Echipă & Porți (admin)

| Ecran | Funcții | Endpoint |
|---|---|---|
| **Echipă** | Listă membri; rol (`admin/manager/staff`) + permisiuni (`events,orders,reports,team,checkin`) + `leisure_role`; gate assignment | `GET /organizer/team`, `POST /team/{invite,update,remove,activate,resend-invite,reset-password}` |
| **Porți de acces** | CRUD porți (nume, capacitate) — definesc `gate_label`/`location` | `GET/POST/PUT/DELETE /organizer/venues/{venue}/gates` |
| **Asignare personal → poartă** | Atribuie membri la porți | `POST /organizer/team/update` |
| **Staff leisure (QR fix)** | CRUD angajați cu QR `STAFF-{12hex}` auto-generat, imutabil; pontaj prin scanare | `GET/POST/PUT/DELETE /organizer/leisure/staff` |

### H. Mai multe / Setări

| Secțiune | Funcții | Endpoint |
|---|---|---|
| **Cont / profil** | Nume, organizator, date companie/CUI, issuer secundar | `GET /organizer/me`, `PUT /organizer/profile`, `POST /organizer/verify-cui` |
| **Scanner** | Toggle Vibrație / Efecte sonore / Auto-confirmare valide (local) | `localStorage` |
| **Hardware / dispozitiv** | Tip imprimantă (thermal 58/80mm / A4), **Card prin NFC** on/off | `PUT /organizer/mobile-settings` (`card_nfc_enabled`) |
| **Payout / bănci** | Conturi bancare CRUD, detalii payout | `GET/POST/PUT/DELETE /organizer/bank-accounts`, `PUT /organizer/payout-details` |
| **API key** | Generare / regenerare cheie personală | `GET/POST /organizer/api-key`, `/api-key/regenerate` |
| **Parolă** | Schimbare parolă | `PUT /organizer/password` |
| **Limbă / temă / despre** | RO/EN, versiune, install PWA | — |
| **Workspace switch** | Comută organizator/tenant | `POST /organizer/switch-organizer` |
| **Logout** | Revocă token | `POST /organizer/logout` |

### I. Evenimente (admin / manager)
- Listă & detaliu evenimente; creare/editare rapidă; submit pentru aprobare; upload imagini; goals & milestones.
- **Detaliu eveniment cu taburi** (din template): Info · Bilete · Lineup · Invitații · Raport.
- `GET/POST/PUT/DELETE /organizer/events`, `/events/{event}/submit`, `/cancel`, `/images`, goals/milestones.
- *Notă:* CRUD-ul complex de eveniment poate rămâne pe web (deep-link din app); mobilul acoperă operațional (status, submit, imagini, obiective).

### J. Centru de notificări (v2 — nou)
Deschis din clopoțelul din header. Preia funcțiile pe care ambilet-app2 le avea în icoana de notificări.
- **Feed** cu categorii + iconuri colorate + timestamp + stare necitit; „marchează tot citit".
- **Filtre**: Toate · Vânzări · Operațional · Financiar · Sistem.
- **Tipuri de notificări**: bilet nou vândut / comandă confirmată; capacitate &gt;80%; scan invalid / duplicat la poartă; aprobare (IGSU/ISU, eveniment); decont procesat / factură scadentă; invitație folosită; contract de semnat; actualizare app.
- **Preferințe** (toggles, din template settings): bilet vândut, capacitate &gt;80%, scan invalid, decont, raport zilnic — plus canal (push/email).
- **Real-time** via Reverb; **push** pe telefon (build nativ).
- Endpoint-uri: feed derivat din `GET /organizer/dashboard/recent-orders` + evenimente Reverb (`order.confirmed`, scan-uri); preferințe în `PUT /organizer/settings`.

### K. Ecrane specifice pe tip de tenant (v2 — din template-uri)
Peste nucleul comun, fiecare tip de cont adaugă module proprii:

| Tip | Ecrane / module proprii |
|---|---|
| **Teatru** | **Spectacole** (autor, regizor, sală, categorie), **Abonamente** (utilizare, reînnoire), **Săli configurate** + hartă locuri, **Taxe culturale** (timbru, TVA 5%, Card Cultural) |
| **Venue** | **Live** (status gate-uri, ocupare pe zone, intrări recente în timp real), zone & capacitate, echipă acces dashboard |
| **Artist** | **Fani** (top orașe, fideli, top cheltuieli, demografice, fidelitate), **Rider** (tehnic/hospitality/contract), Show-uri cu vânzări per oraș |
| **Agenție** | **Artiști** (booking, comisioane per artist), **Contracte** (status, expirare), **Financiar** (fee-uri de plată către artiști, încasări) |
| **Organizator** | Evenimente cu lineup + costuri producție, invitații, canal de vânzare (online/parteneri/ghișeu) |
| **Festival** | Zile/scene, abonamente multi-zi, wristband/cashless |

---

## 3. Straturi transversale (multi-tenant/marketplace)

### 3.1. Rezolvarea contextului & rutare API
La login, `userType()` ∈ `{organizer_owner, team_member, venue_owner, tenant_staff}`. Un **API rewriter** mapează căile în funcție de context (model din README-ul scan-app, iterația 2):

| Context | Bază API | Auth |
|---|---|---|
| Organizator marketplace | `/api/marketplace-client/organizer/*` | `marketplace.auth` + Sanctum |
| Membru echipă | idem (token `team-member-{id}`) | Sanctum |
| Venue-owner | `/api/marketplace-client/venue-owner/*` (`/scan`, `/check-in`) | Sanctum |
| Tenant staff / box-office | `/api/tenant-client/*` (rezolvat pe domeniu) | Sanctum |

### 3.2. Branding la runtime
Un singur strat de temă, cheiat pe context: logo + nume + culori + fonturi din `/theme` (tenant) sau `/config` (marketplace) sau `/me` (organizator). Fallback pe brandul Tixello.

### 3.3. Roluri & permisiuni (RBAC)
- Roluri: `owner`, `admin`, `manager`, `staff`; permisiuni: `events, orders, reports, team, checkin`.
- Roluri leisure: `check_in`, `pos_cashier`, `admin_mobile`, `rental_*`, `validation_*`, `kiosk_selfcheckin`.
- Gating pe tab-uri, acțiuni și ecrane.

### 3.4. Real-time & offline
- **Reverb (WebSocket)**: EventContext reîmprospătează stats la `order.confirmed` în loc de polling; fallback polling 30s.
- **Offline scanare**: coadă locală + retry; audit `LeisureScanAttempt` (invalid/duplicat) pentru rapoarte.
- **Notificări push** (fază 2): vânzări noi, ținte atinse, tură.

### 3.5. Hardware
- **Print**: ESC/POS thermal (WebUSB / Android print service) — client-side; shortlink `/v/CODE?p=pos`.
- **NFC / POS bancar fizic**: gated de `card_nfc_enabled`; necesită build nativ (indisponibil în PWA pură).
- **Case de marcat fiscale** (roadmap — vezi `FISCAL_CASHREGISTER_INTEGRATION.md`): bon fiscal automat la finalizare, arhitectură multi-model (Partner 200, Datecs, Tremol).

---

## 4. Design system (v2 — modelul din template-uri)

- **Temă întunecată**, mobile-first. Fundal `#080c10`; suprafețe `#0e1318` / `#141c24`; borduri `rgba(255,255,255,.07–.18)`.
- **Accent per-context** (`--ac`) care temează întreaga interfață: organizator `#00c896`, teatru `#9b60c8`, venue `#00e5ff`, artist `#1ddab4`, agenție `#9b7ff8`, festival `#f03e8f`. Comutarea contextului schimbă accentul + logo + nume + navigația.
- **Semantice:** succes `#3ddb8a`, atenție `#f5a623`, eroare `#f04f4f`; plus tonuri pink `#f03e8f` / turcoaz `#1ddab4` / mov `#9b7ff8` pentru categorii.
- **Text:** `#f0f4ff` + opacități (.5 / .28 / .16).
- Componente: **header aplicație** (avatar + switch + clopoțel), hero GMV cu glow, KPI orizontale, alerte colorate, carduri eveniment cu progress, subtabs pe pastile, bottom-nav cu indicator-punct, bottom-sheets, toggles, **hartă locuri**, **feed notificări**, safe-area insets.
- Tipografie: system UI (800/900 pe titluri, tabular-nums pe cifre); mono pentru coduri.
- **PWA**: manifest standalone, portrait, service worker (stale-while-revalidate), iconuri 192/512 + maskable. Build nativ pentru push + NFC + print.

---

## 5. Roadmap pe faze

| Fază | Conținut |
|---|---|
| **F1 — Paritate (MVP)** | Login/context, Panou (2 vederi), Scanare, Vânzare, Rapoarte, Listă invitați, Setări, Porți, Echipă — pe path-ul organizator-marketplace. Reverb + offline scan. |
| **F2 — Multi-context** | Rewriter tenant + venue-owner; selector workspace; branding runtime per context; push notifications. |
| **F3 — Leisure complet** | Sesiuni casă, produse, închirieri bărci, staff QR, rapoarte scanări/vreme. |
| **F4 — Fiscal & hardware nativ** | Case de marcat fiscale, NFC/POS bancar, print nativ avansat. |

---

## 6. Referințe cod

- **Model app (web replica):** `resources/marketplaces/ambilet/organizer/scan-app/` + `resources/marketplaces/ambilet/assets/js/scan-app/`
- **APK actual:** `storage/app/public/downloads/tixello-staff.apk` (rute `/android-nou`, `/android-nou-2`)
- **Design tokens:** `resources/marketplaces/ambilet/assets/css/scan-app.css` (`:root` `--scanapp-*`)
- **API organizator/staff:** `routes/api.php` (grup `marketplace-client/organizer`, ~1731–2322) + `app/Http/Controllers/Api/MarketplaceClient/Organizer/*`
- **Tenancy/branding:** `app/Models/{Tenant,MarketplaceClient,MarketplaceOrganizer,Ticket,Order,TicketType}.php`, `app/Services/ThemeService.php`
- **Fiscal roadmap:** `plans/mds/FISCAL_CASHREGISTER_INTEGRATION.md`
