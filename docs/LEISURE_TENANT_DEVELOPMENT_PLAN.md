# Plan de dezvoltare — Tenant de tip „Leisure”

> Scop: aducerea în modelul **tenant** (`tenant_type = leisure`) a tuturor funcționalităților
> dezvoltate în **marketplace** pentru organizatorii de tip leisure, plus interfața publică de
> vânzare bilete (`resources/tenant-demos/leisure`), interfața POS (ca la ambilet) și panoul de
> administrare complet pentru un tenant leisure.
>
> Document de planificare — nu conține cod de producție. Fișier: `docs/LEISURE_TENANT_DEVELOPMENT_PLAN.md`

---

## 0. Situația actuală — două stive paralele „leisure”

Codul conține **două implementări leisure** care coexistă. Înțelegerea acestei dualități este cheia întregului plan.

### Stiva A — Marketplace (LIVE, matură — „Lacul Sf. Ana”, ambilet)
- **Scope:** `marketplace_organizer_id` / `marketplace_client_id` / `event_id`.
- **Model de date:** `Event` + `TicketType` (tabele partajate) + modelele `App\Models\Leisure*` la nivel root:
  `LeisureSlotBooking`, `LeisureBoat`, `BoatRental`, `LeisureResourceLock`, `LeisureStaffMember`,
  `LeisureStaffCheckin`, `LeisureShift`, `LeisureCashierSession`, `LeisureScanAttempt`.
- **Config:** JSON „liber” în `Event.venue_config` + `TicketType.meta`.
- **Motorul:** `App\Http\Controllers\Api\MarketplaceClient\Organizer\Leisure\LeisureController.php`
  (~4.500 linii) — POS sale, sesiuni de casă, facturare pe 2 societăți, bărci/rentals, scanări,
  ture, rapoarte, deconturi. Front-end: `resources/marketplaces/ambilet/organizer/leisure-*.php`
  + driver ESC/POS `assets/js/pos-printer.js`.
- **Public/online:** `MarketplaceEvent` + `MarketplaceTicketType` + `MarketplaceEventDateCapacity`
  (a treia reprezentare de „eveniment”), consumat de `Customer/CheckoutController`.

### Stiva B — Tenant (NOUĂ, țintă, deja începută)
- **Scope:** `tenant_id`, activat prin `Tenant.tenant_type === TenantType::Leisure`.
- **Model de date:** `App\Models\Leisure\*` (`PhysicalResource`, `PhysicalResourceType`,
  `ResourceRental`, `TicketTypeCapacity`, `TenantTeamMember`, `TenantTeamMemberShift`,
  `TenantTaxRegistry`) — toate cu `tenant_id` direct.
- **Config:** coloane de prim rang pe `TicketType` (`leisure_pricing_rules`, `leisure_seasons`,
  `channel_pricing`, `service_category`, `tenant_tax_registry_id`, `leisure_default_daily_capacity`,
  `leisure_schedule_*`, `leisure_slot_duration_minutes`, variante durată, overtime).
- **Servicii:** `App\Services\Leisure\*` (`LeisurePricingResolver`, `ChannelPricingResolver`,
  `CapacityAvailabilityService`, `RentalService`, `InvoiceSplitter`, `ReceiptPrinterService`,
  `SlotBookingService`*).
- **Admin:** grup „Leisure” în panoul Filament Tenant (`app/Filament/Tenant/Resources/*`,
  `Pages/LeisureReports`, `Pages/LeisureSchedule`).
- **POS:** panoul **Operator** (`/operator`, `app/Filament/Operator/*`), tenant-scoped prin
  middleware `EnsureTenantTeamMember`.
- **Public:** doar `resources/views/leisure/{embed,receipt,qr-print}.blade.php` + API `/api/leisure/*`
  (doar `availability` month/slots). **Nu există încă un site public de vânzare.**

### Ce funcționează deja pe stiva B (fundații solide)
- Enum `TenantType::Leisure` cu `defaultFeatures()` + `defaultMicroserviceSlugs()`.
- `TenantObserver` completează flag-urile `features.leisure.*` la creare/conversie.
- Model de date complet tenant-scoped pentru echipă, inventar, rentals, capacități, societăți fiscale.
- Motor de rentals funcțional cap-coadă (`RentalService` + operator `ActiveRentals` + admin force-end).
- Prețuri pe canal/sezon/durată/zi (`ChannelPricingResolver` + `LeisurePricingResolver`).
- `InvoiceSplitter` (split pe societate) + `ReceiptPrinterService` (bon 80mm) — construite dar neconectate.
- Panou Operator tabletă cu **check-in** și **rentals** end-to-end.
- Admin tenant: echipă/roluri/ture, inventar + tipuri + QR, excepții de capacitate, multi-societate,
  istoric rentals, pagină rapoarte, pagină pontaj.

### Golurile portante (ce lipsește)
1. **POS checkout este STUB** — `Operator\Pos::checkout()` doar afișează o notificare și golește coșul.
   Nu creează Order/Ticket, nu decrementează capacitatea, nu tipărește bon, nu face split de factură,
   nu impune sesiune de casă. (Marcat intern „E10”.)
2. **Nu există sesiune de casă tenant-scoped** — `LeisureCashierSession` este `marketplace_organizer_id`-scoped.
   La fel: `LeisureStaffMember`, `LeisureShift`, `LeisureScanAttempt`, `LeisureBoat`, `BoatRental`,
   `LeisureSlotBooking` (pe `event_id`).
3. **Provisioning incomplet** — observer-ul scrie doar `features`; nu creează pivoții de microservicii,
   societatea fiscală implicită, tipurile de resurse, paginile default, sau user-ul/TeamMember owner.
4. **Nu există `Tenant::isLeisure()` / helper de feature** — gating duplicat inline în ~10 fișiere.
5. **Nu există site public de vânzare** (`resources/tenant-demos/leisure`) și nici API public de comenzi leisure.
6. **Ecrane admin subțiri/absente**: flux creare eveniment/produs leisure, calendar disponibilitate de bază,
   sezoane/zile închise ca ecran de prim rang, casă/Z-report, configurare embed, rapoarte bogate
   (ocupare, utilizare rentals, decont pe societate).
7. **Config dublat** (`Event.venue_config` + `TicketType.meta` vs coloane de prim rang) — trebuie consolidat.

---

## Decizii de arhitectură (recomandări — de confirmat)

| # | Decizie | Recomandare | Motiv |
|---|---------|-------------|-------|
| D1 | Consolidare model eveniment | **Stiva B**: `Event` + `TicketType` tenant-scoped, cu coloane de prim rang; abandonăm progresiv `venue_config`/`meta` pentru tenant | Scaffolding-ul tenant deja folosește coloanele; serviciile B le citesc |
| D2 | Portare vs bridge la API marketplace | **Portare nativă** în tenant (nu bridge) | Scopul e îmbogățirea *modelului tenant*; bridge-ul ar lega tenantul de `marketplace_organizer_id` |
| D3 | API public leisure | **Extindem `/api/leisure/*`** cu paritate față de `/tenant-client/*` (config, events, order, order-summary), reutilizând `ResolvesTenant` + `TenantClientCors` | Menține separarea slot/availability; permite reutilizarea cart/checkout din teatru |
| D4 | Tipărire bon | **Portăm driverul WebUSB ESC/POS** din ambilet (`pos-printer.js`) ca țintă principală; `leisure.receipt` (browser print) rămâne fallback | User a cerut explicit „ca la ambilet”; paritate fiscală (2 copii, TVA, serii) |
| D5 | Sesiune de casă | Model nou **tenant-scoped** `LeisureCashierSession` (tenant_id + team_member_id), portat 1:1 din logica marketplace | Consistență cu panoul Operator care e deja tenant-scoped |
| D6 | Roluri operaționale | Consolidăm pe `TenantTeamMember.leisure_role` (setul din stiva B), cu mapare din setul mai bogat marketplace | Panoul Operator citește deja acest câmp |

---

## Structura pe epice

Planul e organizat în 7 epice. Ordinea recomandată de execuție e dată în secțiunea „Faze & secvențiere”.

### EPIC 1 — Fundații & refactorizări partajate
*Prerechizite pentru tot restul. Efort: mic-mediu.*

- **1.1 `Tenant::isLeisure()`** + helper de feature-flags. Adăugăm în `app/Models/Tenant.php`:
  - `isLeisure(): bool` (analog cu `isTheater()` etc.).
  - `feature(string $path, $default=null)` / `hasFeature(string $path)` care citește `features.*`
    prin dot-notation (ex. `hasFeature('leisure.pos.enabled')`).
  - Refactorizăm cele ~10 locuri cu `tenant_type->value === 'leisure'` inline (toate Resources leisure,
    `EventResource`, `TicketTypeResource`, paginile) să folosească helper-ul.
- **1.2 Consolidare reprezentare eveniment (D1).** Confirmăm că fluxul tenant folosește `Event` +
  coloanele de prim rang pe `TicketType`. Documentăm maparea `venue_config`/`meta` → coloane și marcăm
  path-ul legacy `IsLeisureVenue` ca „doar marketplace”. (Fără ștergere acum — doar delimitare.)
- **1.3 Enum-uri & etichete.** Verificăm `TenantType::Leisure` label/microservicii/features (deja OK).
  Adăugăm, dacă e cazul, un `LeisureRole` enum dedicat (azi e string cu 6 valori pe `TenantTeamMember`).

**Livrabile:** helper-e pe Tenant, refactor gating, notă de arhitectură în `docs/`.

---

### EPIC 2 — Provisioning complet la crearea tenantului leisure
*Un tenant leisure nou trebuie să fie operațional „din cutie”. Efort: mediu.*

- **2.1 Serviciu de provisioning** `App\Services\Leisure\LeisureTenantProvisioner` apelat din
  `TenantObserver::created()` (și din `OnboardingController`) când `tenant_type = leisure`:
  - creează pivoții `tenant_microservices` din `TenantType::Leisure->defaultMicroserviceSlugs()`
    (`leisure-core`, `leisure-pos`, `leisure-rentals`, `door-sales`, `efactura`, `accounting`, ...);
  - creează o `TenantTaxRegistry` implicită (din datele de firmă ale tenantului, `is_default = true`);
  - creează câteva `PhysicalResourceType` implicite (opțional, în funcție de sub-profil);
  - creează paginile default (`TenantPage::createDefaultPages()` adaptat pentru leisure);
  - creează `User` owner + `TenantTeamMember` cu `leisure_role = admin`.
- **2.2 Onboarding leisure.** Ramură în wizard-ul din `OnboardingController` cu pași specifici
  (date firmă/CIF, prima „locație/serviciu”, primul produs/tip bilet, invitare operatori).
- **2.3 Seeder demo** `LeisureDemoSeeder` (pt. `demo_shadow`) — o locație cu 2-3 produse (acces, rental
  bărci, parcare), capacități, câțiva operatori, ca să existe date pentru demo/preview.

**Livrabile:** provisioner + hook observer, ramură onboarding, seeder demo.

---

### EPIC 3 — Migrarea modelelor marketplace → tenant-scoped
*Re-scope-ul datelor „grele” care azi sunt legate de organizator. Efort: mare.*

Pentru fiecare model marketplace fără echivalent tenant, creăm echivalentul `tenant_id`-scoped
(migrare + model + factory), reutilizând convențiile din `app/Models/Leisure/*`:

| Marketplace (azi) | Tenant (de creat) | Note |
|---|---|---|
| `LeisureCashierSession` (`marketplace_organizer_id`) | `LeisureCashierSession` tenant-scoped **(D5)** | `tenant_id` + `tenant_team_member_id`; `opened_at/closed_at/closing_snapshot` |
| `LeisureStaffMember` + `LeisureStaffCheckin` | integrat în `TenantTeamMember` + `TenantTeamMemberShift`/checkin | QR staff pe TeamMember; timeclock |
| `LeisureShift` | deja există `TenantTeamMemberShift` | verificăm paritate câmpuri (gate/role) |
| `LeisureScanAttempt` | `LeisureScanAttempt` tenant-scoped | audit scanări bilete (valid/invalid) |
| `LeisureBoat` + `BoatRental` | deja acoperit de `PhysicalResource` + `ResourceRental` | mapare „calup/overtime” → `RentalService` |
| `LeisureResourceLock` | `LeisureResourceLock` tenant-scoped (sau via `TicketTypeCapacity` sloturi) | rezervări pe interval pt. inventar |
| `LeisureSlotBooking` (`event_id`) | re-scope `SlotBookingService` pe `TicketTypeCapacity` sloturi | unifică cu `CapacityAvailabilityService` |
| `MarketplaceEventDateCapacity` | deja acoperit de `TicketTypeCapacity` | — |
| bloc dublu-emitent organizer | deja acoperit de `TenantTaxRegistry` (N societăți) | — |

- **3.1** Migrări + modele + factories pentru: `leisure_cashier_sessions` (tenant), `leisure_scan_attempts`
  (tenant), `leisure_resource_locks` (tenant, dacă păstrăm inventarul pe interval separat de capacități).
- **3.2** Re-scope `SlotBookingService` de pe `event_id`/`LeisureSlotBooking` pe modelul tenant
  (`TicketTypeCapacity` cu sloturi) — sau păstrăm `LeisureSlotBooking` dar cu `tenant_id`. Decizie în 3.0.
- **3.3** Unificăm `CapacityAvailabilityService` + `SlotBookingService` pe aceeași sursă de adevăr.

**Livrabile:** migrări reversibile + modele + factories + teste unitare pentru concurență (locks).

---

### EPIC 4 — POS (panoul Operator) — de la stub la funcțional („E10”)
*Cea mai portantă piesă funcțională. Efort: mare.*

Portăm logica din `LeisureController::posSale()` + endpoint-urile de casă într-un serviciu tenant-scoped
și o conectăm la panoul Operator.

- **4.1 Serviciu `App\Services\Leisure\PosSaleService`** (tenant-scoped, portat din `posSale`):
  - validare items/customer/company/`payment_method ∈ {cash, card, invoice}`/locale;
  - **guard: sesiune de casă deschisă** obligatorie (altfel 403);
  - preț per linie: variantă → `channel_pricing`/`pos_price` → comision (mod `added_on_top`/`included`);
  - capacitate slot (`CapacityAvailabilityService::reserve/confirm`) + inventar fizic (`LeisureResourceLock`);
  - tranzacție DB: creează `Order` (`source='pos'`, meta bogat: pos, payment_method, visit_date,
    cashier_session_id, comision), `OrderItem`-uri, `Ticket`-uri (incl. pachete: umbrella + componente $0),
    lock-uri inventar;
  - split pe societate cu `InvoiceSplitter`; rezervă numărul de factură cu `TenantTaxRegistry::nextInvoiceNumber()`.
- **4.2 Sesiuni de casă (tenant).** Pagini/acțiuni Operator: „Deschidere casă / Închidere casă”,
  overlay de blocare când casa e închisă, „Desfășurător casă” (timeline sesiuni cu breakdown pe
  metodă de plată și pe societate SC1/SC2), export CSV, snapshot la închidere (Z-report).
- **4.3 Conectare `Operator\Pos::checkout()`** la `PosSaleService`; adăugăm în UI: panou client/companie
  (cu **lookup ANAF** pe CUI), selector metodă de plată (cash/card/link), variante/sloturi/inventar,
  add-ons, pachete, comision, total.
- **4.4 Tipărire bon & factură (D4).** Portăm `resources/marketplaces/ambilet/assets/js/pos-printer.js`
  (WebUSB ESC/POS) în panoul Operator: conectare imprimantă, status hârtie, auto-print, reprint,
  bon bilet (cu QR), **factură fiscală RO** în 2 copii (CUI/serie/TVA/semnături). `leisure.receipt`
  rămâne fallback pentru browser-print.
- **4.5 Scanări.** Legăm `Operator\CheckIn` la `LeisureScanAttempt` (audit) + rapoarte scanări.

**Livrabile:** `PosSaleService` + teste, sesiuni de casă tenant, UI POS complet, driver tipărire, audit scanări.

---

### EPIC 5 — Panou de administrare tenant leisure (Filament Tenant) — completare
*Toate funcționalitățile de admin necesare. Efort: mediu-mare.*

Pe lângă ce există deja (echipă/ture, inventar/tipuri/QR, excepții capacitate, multi-societate,
istoric rentals, rapoarte, pontaj), adăugăm:

- **5.1 Flux „Locație/Serviciu & Produs”.** Ecran prietenos de creare a evenimentului-container leisure
  + tipuri de bilet (azi `EventResource` e ascuns pentru leisure, iar crearea Event nu e expusă).
  Wizard: date locație → program de bază → produse (`service_category`) → prețuri (canal/sezon/durată).
- **5.2 Calendar disponibilitate de bază** (nu doar excepții): ecran lunar peste `TicketTypeCapacity`
  cu capacitate/vândut/rezervat/închis/price-override și acțiuni Livewire (analog `DailyCapacities`
  din marketplace), plus derivare sloturi din `leisure_schedule_*`.
- **5.3 Sezoane & zile închise** ca resursă de prim rang (azi doar repeater pe produs sau `venue_config`).
- **5.4 Casă & Z-rapoarte în admin**: listă sesiuni de casă, deconturi, export, vizualizare snapshot.
- **5.5 Rapoarte bogate** (extindem `LeisureReports`): ocupare/serie, utilizare rentals & overtime,
  decont pe societate (`reportsByIssuer`), payouts/settlement, timeline vânzări, participanți.
- **5.6 Configurare embed**: ecran de setări pentru `features.leisure.embed` (temă, accent, logo,
  return_url, generare snippet iframe) — azi există doar view-ul, fără ecran de config.
- **5.7 Facturare / e-Factura / ANAF**: integrare `InvoiceSplitter` → emitere efectivă facturi pe
  `TenantTaxRegistry`, cu microserviciul `efactura`; lookup ANAF pe CUI la nivel de comandă B2B.

**Livrabile:** wizard produs, calendar disponibilitate, sezoane/închideri, ecrane casă/decont,
rapoarte extinse, config embed, punte facturare.

---

### EPIC 6 — Site public de vânzare bilete (`resources/tenant-demos/leisure`)
*Skin PHP standalone, soră cu `teatru`. Efort: mare (front-end + API public).*

Urmăm fidel pattern-ul `resources/tenant-demos/teatru/`:

- **6.1 Scaffold skin** `resources/tenant-demos/leisure/`:
  - `includes/{config,api,head,header,footer}.php` (copiem clientul cu disk-cache + `api_request()`
    normalizat; schimbăm doar branding/culori/set de pagini/harta acțiuni proxy);
  - `api/proxy.php` (browser→core, injectează `tenant`/`hostname`, forwardează `Authorization`,
    menține cookie sesiune);
  - `.htaccess` cu alias-uri RO adecvate leisure;
  - `cont/` (zonă cont client) + `_webhook-deploy.php` (branch `leisure`).
- **6.2 Set de pagini leisure** (diferă de teatru — **fără seat map**, cu **selector dată/slot**):
  - `index.php` (hero + servicii/produse featured), `servicii.php`/catalog, `serviciu.php` (detaliu
    produs + **date/slot picker** din `/api/leisure/.../availability` month + `.../slots`),
    `cos.php`, `finalizare.php`, `confirmare.php`, `cont/*`, pagini statice (about/contact/termeni).
  - Reutilizăm fluxul Alpine de cart/checkout din teatru; înlocuim `seatMap()` cu `slotPicker()`.
- **6.3 Extindere API public `/api/leisure/*` (D3)** — backend. Azi există doar `availability`. Adăugăm:
  - `GET /leisure/tenants/{tenant}/config` (branding, produse, societăți, metode plată);
  - `GET /leisure/tenants/{tenant}/products` (+ detaliu);
  - `POST /leisure/tenants/{tenant}/orders` (checkout real — azi e stub în embed) prin
    `PosSaleService` refolosit pe canal `online` (fără sesiune de casă, cu plată online);
  - `GET /leisure/order-summary`;
  - reutilizăm `ResolvesTenant` + `TenantClientCors` pentru rezolvarea tenantului prin domeniu/`?tenant=`.
  - Alternativ (de evaluat în D3): mapăm leisure pe `/tenant-client/*` existent.
- **6.4 Plată online**: integrăm `TenantPaymentConfig` (Stripe/Netopia/EuPlătesc/PayU) + webhook
  `/webhooks/tenant-payment/{tenant}/{processor}` (deja existent) pentru confirmarea comenzii.
- **6.5 Deploy** `deploy-leisure.bat` (branch `leisure` → domeniul locației → `_webhook-deploy.php`).
- **6.6 (Opțional)** `WebTemplateCategory::Leisure` + `compatibleTenantTypes → TenantType::Leisure`
  + `leisure.ts` în `resources/tenant-client/` dacă vrem și varianta SPA în galeria `/web-templates`.

**Livrabile:** skin `tenant-demos/leisure` complet, API public de comenzi leisure, plată online + webhook,
script deploy, (opțional) categorie template + template SPA.

---

### EPIC 7 — Cross-cutting: fiscal, rapoarte, testare, hardening
*Efort: mediu, în paralel cu 4–6.*

- **7.1 Facturare fiscală**: `InvoiceSplitter` → emitere reală (serii pe `TenantTaxRegistry`),
  integrare `efactura`, ANAF lookup.
- **7.2 Teste**: unit (pricing, capacity locking, rental overtime, invoice split), feature
  (POS sale end-to-end, cashier open/close, public checkout), concurență (slot/inventory races).
- **7.3 Permisiuni & securitate**: policies pe `leisure_role`, scoping `tenant_id` verificat pe toate
  query-urile noi (aliniat cu `TenantScope`/`SecureTenantScope`).
- **7.4 Observabilitate**: logare vânzări POS, audit scanări, jurnal sesiuni casă.

---

## Faze & secvențiere (milestones)

```
Faza 1 (fundații)      : EPIC 1  → EPIC 2
Faza 2 (date)          : EPIC 3            (poate începe în paralel cu Faza 1 după D1/D5)
Faza 3 (POS)           : EPIC 4            (depinde de EPIC 3.1 casă + 3.x capacități)
Faza 4 (admin)         : EPIC 5            (în paralel cu EPIC 4, partajează serviciile)
Faza 5 (public)        : EPIC 6            (depinde de PosSaleService din EPIC 4 pt. checkout)
Transversal            : EPIC 7            (continuu)
```

Cale critică: **D1/D5 → EPIC 3.1 (casă tenant) → EPIC 4 (PosSaleService) → EPIC 6.3 (API public checkout)**.
`PosSaleService` e piesa refolosită atât de POS (canal POS + casă) cât și de site-ul public (canal online).

---

## Riscuri & puncte de atenție

1. **Dublarea config-ului** (`venue_config`/`meta` vs coloane) — dacă nu se decide clar D1, riscăm a
   treia divergență. Recomandare: tenant folosește exclusiv coloanele de prim rang.
2. **Concurență la capacități/inventar** — trebuie păstrat pattern-ul de `lockForUpdate` din serviciile
   existente; teste dedicate pe race conditions.
3. **Paritate fiscală RO** — facturarea pe 2+ societăți, seriile și TVA trebuie portate exact
   (referință: `pos-printer.js::buildInvoiceCommands` + `MarketplaceOrganizer::reserveNextInvoiceNumber`).
4. **WebUSB doar Chrome/Edge** (și WinUSB pe Windows) — de comunicat operatorilor; fallback browser-print.
5. **Nu reutiliza `DoorSalesController`/`app/Services/DoorSales`** — e scaffold neconectat, card-only/EUR;
   `door-sales` rămâne doar feature-flag/microserviciu, nu logică de vânzare.
6. **Volum de portat** — `LeisureController` are ~4.500 linii; portarea trebuie făcută incremental,
   pe sub-domenii (casă, sale, scanări, rapoarte), cu teste la fiecare pas.

---

## Rezumat livrabile pe cele 4 cerințe ale task-ului

| Cerință | Epice | Rezultat |
|---|---|---|
| Toate funcționalitățile marketplace pe tenant leisure | EPIC 1, 2, 3, 7 | Model tenant-scoped complet + provisioning + refactor |
| Interfață publică vânzare + template `tenant-demos/leisure` | EPIC 6 | Skin PHP + API public + plată online + deploy |
| Interfață POS (ca la ambilet) | EPIC 4 | POS Operator funcțional + casă + tipărire ESC/POS |
| Admin complet pentru tenant leisure | EPIC 5 | Wizard produs, calendar, sezoane, casă, rapoarte, embed, facturare |
