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

## Decizii de arhitectură (CONFIRMATE — 2026-07-25)

| # | Decizie | Alegere confirmată | Motiv |
|---|---------|--------------------|-------|
| D1 | Consolidare model eveniment | ✅ **Stiva B**: `Event` + `TicketType` tenant-scoped, cu coloane de prim rang; abandonăm `venue_config`/`meta` pentru tenant | Scaffolding-ul tenant deja folosește coloanele; serviciile B le citesc |
| D2 | Portare vs bridge la API marketplace | ✅ **Portare nativă** în tenant (nu bridge) | Scopul e îmbogățirea *modelului tenant*; bridge-ul ar lega tenantul de `marketplace_organizer_id` |
| D3 | API public leisure | ✅ **Extindem `/api/leisure/*`** cu config, products, orders, order-summary, reutilizând `ResolvesTenant` + `TenantClientCors` | Menține separarea slot/availability; permite reutilizarea cart/checkout din teatru |
| D4 | Tipărire bon | ✅ **Driver WebUSB ESC/POS** portat din ambilet (`pos-printer.js`) ca țintă principală; `leisure.receipt` (browser print) = fallback | Cerut explicit „ca la ambilet”; paritate fiscală (2 copii, TVA, serii) |
| D5 | Sesiune de casă | ✅ Model nou **tenant-scoped** `LeisureCashierSession` (`tenant_id` + `tenant_team_member_id`), portat 1:1 din logica marketplace | Decurge din D2; consistență cu panoul Operator (deja tenant-scoped) |
| D6 | Roluri operaționale | ✅ Consolidare pe `TenantTeamMember.leisure_role` (setul stiva B), cu mapare din setul marketplace | Decurge din D2; panoul Operator citește deja acest câmp |
| D7 | Entitate „Locație” | ✅ **Locația = un `Event`** cu `display_template='leisure_venue'`, re-etichetat în UI ca „Locație/Unitate” (ex. „Parc Distracții Disney”); poate referi un `Venue` pentru adresă/geo | 1:1 cu stiva marketplace; un tenant are 1..N locații = 1..N event-uri |
| D8 | Container produse (tenant „nu are evenimente”) | ✅ **Varianta B**: păstrăm `TicketType belongsTo Event`; produsele stau sub event-ul-locație; **NU decuplăm** de Event, rezolvăm „fără evenimente” **prin UI** (relabel Event→Locație). Partajarea unui produs pe mai multe locații = **clonare/template** | Elimină riscul de decuplare; portare 1:1 din marketplace; `GroupPricingTier` rămâne `event_id`-scoped |
| D9 | Taxonomie tipuri de produse | ✅ Enum nou **`LeisureServiceCategory`**: `access`, `service_rental`, `product_rental`, `parking`, `camping`, `other` + `package`; mapare din valorile vechi | Acoperă toate scenariile (acces limitat, închirieri servicii/produse, parcări, camping) |
| D10 | Subtip tenant leisure | ✅ Coloană **`leisure_subtype`** + enum `LeisureSubtype` (salină, parc aventură, parc distracții, rezervație, muzeu) care conduce preset-uri | Profiluri foarte diferite; `TenantType::Museum` rămâne separat pentru muzee „simple” |

---

## Modelul de domeniu leisure (revizuit — „fără evenimente”)

Un tenant leisure **nu operează evenimente**. Operează **locații** și un **catalog de produse/servicii**.
Este fundamental diferit de toate celelalte tipuri de tenant. Scenarii pe care modelul trebuie să le suporte:

- **1..N locații** (o salină cu mai multe puncte, un parc cu mai multe zone/porți).
- **1..N societăți (CIF-uri)** cu **alocare per produs**: unele produse pe SC1, altele pe SC2
  (`TicketType.tenant_tax_registry_id`, opțional override per locație).
- **Produse configurabile avansat, folosibile pe orice locație aleasă** (pivot produs↔locație).
- **Subtipuri foarte diferite**: salină, parc aventură, parc distracții, rezervație, muzeu.

### Entități (Varianta B — păstrăm Event)

| Concept | Model | Note |
|---|---|---|
| **Locație / Unitate** | `Event` cu `display_template='leisure_venue'` (D7), re-etichetat UI „Locație” | tenantul creează 1..N locații = 1..N event-uri (ex. „Parc Distracții Disney”); config locație (program/sezoane/zile închise) pe event; poate lega un `Venue` pt. adresă/geo |
| **Produs / Serviciu** | `TicketType belongsTo Event` (NU decuplat, D8) | păstrează scoping via `event.tenant_id`; toate coloanele leisure de prim rang rămân valabile |
| **Partajare produs pe locații** | acțiune **clonare/template** | un „șablon de produs” reutilizabil se clonează în fiecare locație-event; NU pivot many-to-many |
| **Societate fiscală** | `TenantTaxRegistry` (există) | N per tenant; alocare per produs via `TicketType.tenant_tax_registry_id` |
| **Subtip** | coloană `leisure_subtype` + enum `LeisureSubtype` (D10) | salină / parc aventură / parc distracții / rezervație / muzeu → preset-uri |

> **De ce Varianta B:** „tenantul nu are evenimente” se rezolvă **prin UI** (Event re-etichetat „Locație”,
> `EventResource` deja are gating leisure), nu prin schimbarea modelului. Astfel evităm decuplarea
> `TicketType`↔`Event` (riscul cel mai critic), păstrăm portarea 1:1 din marketplace, și `GroupPricingTier`
> rămâne `event_id`-scoped. Costul: pentru a folosi „același” produs pe mai multe locații, se clonează
> (produs-șablon → instanțe per locație), cu opțiune de sincronizare a modificărilor.

### Taxonomia produselor (`LeisureServiceCategory`, D9)

| Categorie | Exemplu | Capacitate/Inventar | Note |
|---|---|---|---|
| `access` | bilet acces salină/parc/muzeu, 1 zi sau durată limitată | `TicketTypeCapacity` (zi/slot) | poate cere durată/oră; „bilet de acces” pe care alte produse îl pot pretinde |
| `service_rental` | închiriere ghid, tobogan, tiroliană, activitate cu durată | slot + durată (`RentalService`) | variante durată + overtime |
| `product_rental` | închiriere bărci, biciclete, echipament | inventar fizic (`PhysicalResource` + `ResourceRental` + lock interval) | unități fizice cu QR |
| `parking` | loc parcare pe zi/interval | capacitate zilnică | poate fi pe altă societate |
| `camping` | loc cort/rulotă pe noapte | capacitate pe interval de nopți | **nou**, necesită logică multi-noapte |
| `other` | orice alt serviciu | configurabil | fallback |
| `package` | (flag de compunere) | umbrella + componente $0 | combină produse din categoriile de mai sus |

Preset-urile pe subtip (D10) pre-selectează categoriile relevante la onboarding
(ex. *parc aventură* → access + service_rental; *rezervație/salină/muzeu* → access + parking;
*parc distracții* → access + service_rental + product_rental).

> Impact în plan (Varianta B): containerul rămâne `Event` (re-etichetat „Locație” în UI). EPIC 1 adaugă
> config locație pe event + taxonomie + subtip (fără decuplare); EPIC 5 = `LocationResource` (Event adaptat)
> + `ProductResource` (TicketType) + clonare produse-șablon; EPIC 6 = site public per locație-event;
> scoping-ul din EPIC 3/4 rămâne pe `event.tenant_id` (ca în marketplace).

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
- **1.2 Model de domeniu leisure (D1, D7, D8, D9, D10 — Varianta B).** Vezi secțiunea „Modelul de domeniu”:
  - **Locația = Event** (`display_template='leisure_venue'`), tenant-scoped prin `Event.tenant_id`. **Fără
    decuplare** de `TicketType` — păstrăm `belongsTo Event`. Un tenant creează 1..N locații-event.
  - **Config locație pe event**: program/sezoane/zile închise ca **coloane de prim rang pe `events`** (D1),
    nu `venue_config` JSON (path-ul legacy rămâne „doar marketplace”). Produsele pot suprascrie la nivel de `TicketType`.
  - **Produse = TicketType** sub event-ul-locație; toate coloanele leisure de prim rang rămân.
  - **Partajare pe locații**: acțiune de **clonare „produs-șablon → instanțe per locație”** (cu opțiune de
    resincronizare), în loc de pivot many-to-many.
  - **Taxonomie**: enum `LeisureServiceCategory` (`access`/`service_rental`/`product_rental`/`parking`/`camping`/`other` + `package`);
    mapare valori vechi (`rental`→`service_rental`/`product_rental` după inventar, `activity`→`service_rental`, `extra`→`other`).
  - **Subtip**: coloană `leisure_subtype` + enum `LeisureSubtype`.
- **1.3 Enum-uri & etichete.** Verificăm `TenantType::Leisure` (OK). `LeisureSubtype` cu `label()` +
  `defaultServiceCategories()` (preset-uri). Opțional `LeisureRole` enum (azi string cu 6 valori).

**Livrabile:** coloane de prim rang pt. config locație pe `events`, enum-uri `LeisureServiceCategory`/`LeisureSubtype`,
migrare de mapare taxonomie, acțiune clonare produs-șablon, helper-e pe Tenant, refactor gating, notă de arhitectură.
**Notă:** dispare migrarea `tenant_id`/`event_id` nullable pe `ticket_types` — nu mai e necesară în Varianta B.

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
- **4.3 Conectare `Operator\Pos::checkout()`** la `PosSaleService`; adăugăm în UI: **selector locație**
  (event-ul-locație, dacă operatorul acoperă mai multe), catalog produse filtrat pe locația-event,
  panou client/companie (cu **lookup ANAF** pe CUI), selector metodă de plată (cash/card/link),
  variante/sloturi/inventar, add-ons, pachete, comision, total. Alocarea pe societate rezultă din produs.
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

- **5.1 Locații + Catalog produse (Varianta B — Event re-etichetat).**
  - **`LocationResource`** = `EventResource` adaptat pentru leisure (Event `leisure_venue`), re-etichetat
    „Locații/Unități”: CRUD locație (nume, adresă/`Venue`, program, sezoane, zile închise, capacitate).
    Ascundem tot ce ține de „eveniment” (dată unică, cast etc.).
  - **`ProductResource`** (peste `TicketType`, sub locația-event): catalog de produse cu wizard avansat pe
    `LeisureServiceCategory` (access/service_rental/product_rental/parking/camping/other), societate
    (`tenant_tax_registry_id`), prețuri (canal/sezon/durată), inventar (pentru product_rental).
  - **Produse-șablon + clonare pe locații**: bibliotecă de produse reutilizabile, cu acțiune „clonează în
    locația X” și resincronizare, ca să nu configureze de la zero pentru fiecare locație.
  - Preset-uri la creare în funcție de `leisure_subtype`.
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
  - `index.php` (hero + locații + servicii/produse featured), `locatii.php` / selector locație (dacă
    tenantul are >1 locație), `servicii.php`/catalog filtrat pe locație, `serviciu.php` (detaliu produs +
    **date/slot picker** din `/api/leisure/.../availability` month + `.../slots`), `cos.php`,
    `finalizare.php`, `confirmare.php`, `cont/*`, pagini statice (about/contact/termeni).
  - Reutilizăm fluxul Alpine de cart/checkout din teatru; înlocuim `seatMap()` cu `slotPicker()`.
  - Catalogul se rezolvă pe locația-event aleasă; produsele afișate = `TicketType`-urile acelei locații.
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

### EPIC 8 — Tarife de grup & Abonamente / Season pass leisure
*Adaptarea la modelul produse+locații (azi sunt legate de evenimente/locuri). Efort: mediu-mare.*

**8.A Tarife de grup.** Fundații existente: `GroupBooking`, `GroupBookingMember`, `GroupPricingTier`
(azi `event_id`-scoped), `GroupBookingResource` + `GroupBookingPage` (tenant + marketplace),
`TicketType.min_per_order/max_per_order/ticket_group`.
- **8.A.1** `GroupPricingTier` rămâne `event_id`-scoped (Varianta B ⇒ event = locație), deci pragurile sunt
  per locație — OK. Adăugăm opțional praguri **la nivel de produs** (`ticket_type_id`) pentru granularitate.
  Praguri: `min_tickets`/`max_tickets` → `discount_percentage` (sau sumă fixă).
- **8.A.2** Aplicare în cascada de preț (vezi risc #8) atât la POS cât și online; „group guide bonus”
  (bilet ghid gratuit la fiecare N) portat din marketplace ca opțiune per produs.
- **8.A.3** `GroupBooking` decuplat de `Event` → legat de **locație + produse + dată/slot vizită**
  (rezervări de grup pentru școli, firme), cu ofertă/deviz și plată ulterioară (invoice).
- **8.A.4** Ecran admin: praguri per produs + gestiune rezervări de grup; pe site-ul public: formular „cerere grup”.

**8.B Abonamente / Season pass.** Fundații existente: `TenantSubscriptionPlan` + `TenantCustomerSubscription`
(orientate teatru: `shows_included`/`shows_used`/`seat_mode`/`allowed_sections`), `SubscriptionController`
(`/tenant-client/subscriptions`, `/subscribe`, `/redeem`), `Season`/`SeasonSubscription`.
- **8.B.1 REUTILIZARE `FlexPass`.** Există deja un motor complet de pass multi-intrare/multi-zi:
  `app/Models/FlexPass.php` + `FlexPassRedemption.php` + `FlexPassPurchase` — `total_entries` (punch-card),
  `valid_from`/`valid_until` (fereastră multi-zi), `eligible_event_ids`/`eligible_ticket_type_ids`,
  `max_entries_per_event`, `is_transferable`/`is_refundable`; `FlexPassRedemption` loghează fiecare intrare.
  Plus `app/Models/SeasonSubscription.php` (`valid_from`/`valid_until`, `subscription_type`, `events_included`,
  `auto_renew`) pentru **abonament anual (pass de an)**. → Adaptăm `FlexPass` la leisure (event=locație,
  `eligible_ticket_type_ids` = produse acoperite, `max_entries_per_event`/zi = limită zilnică) în loc să
  construim de la zero. `TenantSubscriptionPlan` rămâne pentru abonamentele „stil teatru”.
- **8.B.2** Redemption la **poartă (check-in)** și la **POS**: creăm un `FlexPassRedemption` per scanare
  (nu doar flip pe `checked_in_at`); verifică intrări rămase, acoperire produs/locație, limită zilnică,
  expirare; loghează în `LeisureScanAttempt` (anti-fraudă — vezi risc #8).
- **8.B.3** Emitere abonament cu **QR/card membru** (reutilizăm generarea QR din `PhysicalResource`/`Ticket`).
- **8.B.4** Reînnoire / expirare / notificări; raport „abonați activi” și utilizare.
- **8.B.5** Vânzare abonament online (pagina `/abonamente` pe skin-ul leisure) + la POS.

**Livrabile:** praguri de grup pe produs, rezervări de grup leisure, model+flux abonament leisure cu
redemption la poartă/POS, card membru QR, vânzare online+POS, rapoarte.

---

### EPIC 9 — Capabilități suplimentare recomandate (specifice profilurilor leisure)
*Ce lipsește pentru scenarii reale de salină/parc aventură/parc distracții/rezervație/muzeu. Prioritizabil.*

- **9.1 Restricții pe produs**: vârstă minimă, înălțime/greutate (parc aventură/distracții), număr max
  participanți/slot, echipament necesar. Validare la vânzare și la check-in.
- **9.2 Waivere / declarații de răspundere** (parc aventură): consimțământ + semnătură digitală per
  participant, atașat biletului; reutilizăm infrastructura de contracte/semnătură existentă.
- **9.3 Rezervări cu resurse limitate & programare**: sloturi cu ghizi/echipament finit (nu doar capacitate
  numerică) — alocarea unei resurse/persoane pe slot; listă de așteptare (waiting list) pentru sloturi pline.
- **9.4 Camping multi-noapte**: rezervare pe interval de nopți (logică tip hotel: check-in/out, disponibilitate
  pe noapte, tarif pe noapte/sezon), distinct de biletele pe zi.
- **9.5 Politici de anulare/rambursare/no-show** per produs (ex. rambursare până cu 24h înainte), reprogramare.
- **9.6 Check-in offline pe tabletă**: conectivitate slabă la salină/rezervație → validare locală cu sync
  ulterior (cache bilete valabile pe zi + coadă de scanări).
- **9.7 Kiosk self-check-in** (rol `kiosk_selfcheckin` din marketplace): terminal auto-servire la intrare.
- **9.8 Multi-locale pe produse/bilete/bonuri** (RO/HU/EN) — strategie de traducere pe coloane de prim rang
  (azi marketplace folosește `meta.translations`).
- **9.9 Rapoarte fiscale & export**: registru de casă, Z-report pe tură, export ANAF/SAF-T, decont pe societate.
- **9.10 Pass-uri combo / bilete-familie** cross-produs și cross-locație (compunere `package` extinsă).

---

### EPIC 10 — Blocante de corectitudine & conformitate (descoperiri din analiză)
*Bug-uri și lipsuri care fac vânzarea leisure incorectă/ilegală dacă nu sunt rezolvate. Prioritate MAXIMĂ.*

- **10.1 Release de capacitate nu e leisure-aware (LATENT → obligatoriu la construirea vânzării).**
  Verificat: `Order::releaseStockForTickets()` (`app/Models/Order.php:415`) eliberează doar locuri
  (`EventSeat`/`SeatHold`) și stoc generic (`MarketplaceTicketType.quantity_sold` / `TicketType.quota_sold`);
  **nicio** referință la `Leisure\TicketTypeCapacity`, `SlotBookingService` sau `LeisureSlotBooking`.
  **Nuanță de severitate:** azi `CapacityAvailabilityService::confirm()` (write `sold`) **nu e apelat de nicăieri**
  (POS = stub; marketplace numără `OrderItem`-uri, nu contorul stocat) → deci **nu e un bug care arde în producție**,
  ci o **cerință de design**: când legăm vânzarea tenant de `confirm()`/`reserve()`, release-ul pe refund TREBUIE
  să elibereze și capacitatea leisure. *Fix:* ramură leisure în release care cheamă serviciile (deja scrise, orfane).
- **10.2 Lipsă TTL pe rezervări (LATENT → prerechizit pt. coșul online).** Verificat: `ticket_type_capacities`
  are `sold`/`reserved` dar **niciun `expires_at`**; `reserve()` doar `increment('reserved')`; nicio comandă din
  `app/Console/Commands` nu atinge `TicketTypeCapacity`/`SlotBookingService`. `reserve()` **nu e apelat încă nicăieri**
  → latent. Devine activ în momentul în care coșul online cheamă `reserve()`. *Fix (înainte de site-ul public):*
  `expires_at` pe rezervare (sau tabel `leisure_capacity_holds`) + comandă `leisure:release-expired-holds`
  (model după `ReleaseExpiredHolds`/`SeatHoldService`).
- **10.3 Webhook de plată tenant nefinalizat (BLOCANT — CONFIRMAT).** Verificat: `TenantPaymentWebhookController::handle()`
  verifică semnătura și `processCallback()`, dar tot blocul de update comandă e **cod comentat** (liniile 74-88,
  literalmente `// TODO: Process the payment result`). La plată online reușită nu se marchează comanda, nu se
  confirmă capacitatea, nu se emit biletele. În plus `$payload = $request->all()` (array) e dat lui
  `verifySignature` — Stripe are nevoie de **raw body** (`getContent()`). *Fix:* rezolvare `Order` din
  `client_reference_id`, `paid` → `confirm()`/`SlotBookingService`, emitere bilete, chitanță/e-Factura,
  **idempotență pe event-id**, corectare raw-body pentru Stripe. **Blocant real pentru checkout online.**
- **10.4 Fără validare dată/slot/durată la poartă (BUG — CONFIRMAT).** Verificat: `CheckInController::checkInByCode`
  și `checkInPerEvent` verifică doar `is_invitation` și `checked_in_at` (dublă-intrare) — **nu** citesc
  `meta.visit_date`/`slot_time`. Un bilet de pe 20 iul intră pe 25 iul; un slot de 10:00 intră la 16:00.
  *Fix:* validare dată/slot/fereastră în check-in, cu respingeri în `LeisureScanAttempt`; suport
  re-entry/anti-passback pentru bilete de zi.
- **10.5 Fiscalizare — bon fiscal (CONFORMITATE LEGALĂ).** `ReceiptPrinterService` produce un bon **ne-fiscal**
  (HTML 80mm). Un tenant leisure care vinde B2C cash în RO e **obligat legal** să emită **bon fiscal de la o
  casă de marcat/AMEF certificată** și să raporteze la ANAF. e-Factura ≠ bon fiscal. *Fix:* strat de dispozitiv
  fiscal (prin calea ESC/POS din Stiva A sau serviciu fiscal cloud). **Hard requirement, nu opțional.**
- **10.6 Numerotare facturi consolidată.** Două autorități de numerotare: `TenantTaxRegistry::nextInvoiceNumber()`
  (tenant) vs `InvoiceGeneratorService` pe `settings.invoice_next_number` (marketplace) → risc de goluri/duplicate.
  *Fix:* pe calea tenant leisure folosim exclusiv `TenantTaxRegistry`.
- **10.7 Câmpuri leisure de prim rang pe bilet.** `visit_date`/`slot_time`/durată trăiesc doar în `tickets.meta`
  (JSON) → scanarea, refund-ul, reminderele, PDF-ul, analytics-ul sunt „oarbe”. *Fix:* coloane de prim rang
  (`visit_date`, `slot_time`, `valid_until`) pe `tickets`, backfill din meta, indexare.
- **10.8 PDF bilet leisure + QR consistent.** `pdf/ticket.blade.php` nu afișează câmpuri leisure (arată dată
  goală/greșită); URL-ul de verificare diferă (`/v/{code}` în job vs `/t/{code}` în model). *Fix:* ramură leisure
  în PDF (visit_date/slot/durată/emitent) + unificare URL QR.
- **10.9 Enforcement microservicii.** `Tenant::hasMicroservice()` există dar nu e aplicat central; microserviciile
  leisure au `price=0`. *Fix:* middleware/policy care mapează rute+pagini Filament la slug-uri și aplică
  entitlement; prețuri reale + facturare recurentă (reutilizăm calea `Invoice`/`StripeService`).

---

### EPIC 11 — Componente reutilizabile descoperite (accelerează epicele)
*Nu construi ce există deja. Hartă reuse → funcționalitate.*

| Nevoie leisure | Componentă existentă (reuse) | Epic țintă |
|---|---|---|
| Abonament multi-intrare / multi-zi / pass sezon | `FlexPass`+`FlexPassRedemption`+`FlexPassPurchase`, `SeasonSubscription` | 8.B |
| Rambursări parțiale per bilet (multi-societate) | `PaymentRefundService::processTicketLevelRefund`, `MarketplaceRefundRequest` (de portat tenant-scoped) | 7, 10.1 |
| Plăți online card + Apple/Google Pay | `StripeProcessor` (`automatic_payment_methods`), `PaymentProcessorFactory::make(Tenant)` | 6.4, 10.3 |
| Payment-link pe email (POS „link plată”) | `StripeService::createInvoicePaymentLink` (de abstractizat în interfață) | 4.3 |
| e-Factura + adaptor ANAF real (SPV, UBL, semnătură) | `EFacturaService` + `AnafAdapter` + `AnafQueue` | 5.7, 10.5/10.6 |
| Lookup firmă după CUI (autofill B2B la POS) | `AnafService::lookupByCui()` | 4.3 |
| Contabilitate (SmartBill/Oblio/FGO/Keez) | `app/Services/Accounting/*` | 5.7 |
| Cupoane pe zi/oră, per produs, tenant-scoped | `Coupon\CouponCode` + `CouponService` | 8.A / marketing |
| Gift cards (cadou zi-pass) | `Shop\ShopGiftCard` + `SendGiftCardEmailJob` | 8.B / marketing |
| Reselleri / OTA | `Affiliate*` + `AffiliateTrackingService` | marketing |
| Wallet Apple/Google | `WalletPass` + `GenerateWalletPassJob` | 6 / bilete |
| SMS / WhatsApp confirmări & remindere | `SendTicketConfirmationSmsJob`, `Services/Sms/*`, `Services/WhatsApp/*` | 9 / notificări |
| Email bilete leisure (RO/HU/EN, QR, per-emitent) | `SendLeisureTicketsEmailJob` + `LeisureTicketsConfirmation` | deja folosit |
| Waitlist (ofertă la eliberare slot) | `Waitlist`+`WaitlistEntry`+`WaitlistService` | 9.3 |
| Analytics/dashboards/rapoarte programate | `Services/Analytics/*`, `AnalyticsWidget`, `ScheduledReportService` | 5.5 |
| Multi-valută (afișare) | `ExchangeRate` + `ExchangeRateService` | 9.8 |
| Season pass RFID / brățări NFC | stack `Wristband`/`Cashless/Nfc/*` (azi festival-scoped) | 9 (opțional) |
| Semnătură/contracte (waivere) | `ESignatureService`, `ContractPdfService` | 9.2 |

**Notă:** multe sunt „event/festival/marketplace-scoped” azi; reuse ≠ zero muls — necesită adaptare la
`tenant_id` / event-locație. Dar economisesc constructia de la zero a motoarelor.

---

## Faze & secvențiere (milestones)

```
Faza 1 (fundații)      : EPIC 1  → EPIC 2   (model domeniu: locații, produse, subtipuri, taxonomie)
Faza 2 (date)          : EPIC 3             (poate începe în paralel cu Faza 1 după D1/D5)
Faza 3 (POS)           : EPIC 4             (depinde de EPIC 3.1 casă + 3.x capacități)
Faza 4 (admin)         : EPIC 5             (în paralel cu EPIC 4, partajează serviciile)
Faza 5 (public)        : EPIC 6             (depinde de PosSaleService + EPIC 10.1/10.2/10.3)
Faza 6 (grup+abonam.)  : EPIC 8             (după cascada de preț din EPIC 4/5; reuse FlexPass)
Faza 7 (extra profil)  : EPIC 9             (prioritizabil per subtip/client)
Blocante corectitudine : EPIC 10            (10.1/10.2/10.3/10.5 = GATE pt. Faza 3/5 — se fac ÎNAINTE de go-live)
Reuse (accelerator)    : EPIC 11            (consultat de toate epicele — nu construi ce există)
Transversal            : EPIC 7             (continuu)
```

> **Regulă de go-live:** nu se lansează vânzarea publică (Faza 5) fără **10.1** (fix scurgere capacitate),
> **10.2** (TTL rezervări), **10.3** (webhook plată) și **10.5** (bon fiscal). Sunt condiții de corectitudine/legalitate.

Cale critică: **D1/D5 → EPIC 3.1 (casă tenant) → EPIC 4 (PosSaleService) → EPIC 6.3 (API public checkout)**.
`PosSaleService` e piesa refolosită atât de POS (canal POS + casă) cât și de site-ul public (canal online).

---

## Riscuri & puncte de atenție (analiză proactivă)

Ordonate după impact. Fiecare are o strategie de mitigare recomandată.

### Critice (pot corupe date sau bani)

1. **Clonare produse pe locații → drift de configurare (Varianta B).** Pentru că partajarea unui produs pe
   mai multe locații se face prin clonare (nu pivot), instanțele pot ajunge divergente (preț/reguli schimbate
   într-o locație, nu în alta). *Mitigare:* concept de „produs-șablon” cu `template_id` pe instanțe, acțiune de
   **resincronizare** (push modificări din șablon în instanțe, cu diff/confirmare), și raport „produse
   divergente față de șablon”. (Notă: Varianta B **elimină** riscul mult mai mare de decuplare `TicketType`↔`Event`.)

2. **Overselling la concurență** (online + POS + mai multe case vând simultan aceeași capacitate/slot/unitate).
   Azi există două surse de adevăr (`CapacityAvailabilityService` pe `TicketTypeCapacity` vs `SlotBookingService`
   pe `LeisureSlotBooking`). *Mitigare:* **o singură sursă de adevăr** cu `lockForUpdate` + constrângeri unice;
   unificăm cele două servicii; teste de concurență dedicate; rezervare→confirmare→eliberare cu TTL.

3. **Numerotare facturi multi-societate.** Coș cu produse pe SC1 și SC2 → split în comenzi/facturi separate,
   fiecare cu seria ei; numerotarea trebuie **atomică** (reference: `reserveNextInvoiceNumber`), fără
   găuri/duplicate, inclusiv la retur/refund parțial pe o singură societate. *Mitigare:* rezervare număr
   în tranzacție cu lock pe `TenantTaxRegistry`; strategie clară de storno; teste pe refund parțial.

4. **Sesiune de casă & reconciliere cash.** Riscuri: casă lăsată deschisă peste noapte, mai mulți operatori pe
   aceeași casă, Z-report greșit la schimb de tură, bani nereconciliați. *Mitigare:* o sesiune activă per
   casă, avertisment/auto-close la capăt de zi, snapshot imutabil la închidere, reconciliere cash declarat vs
   calculat cu diferență raportată.

5. **Vânzare înregistrată dar bon netipărit** (imprimanta ESC/POS cade la mijloc). *Mitigare:* separăm
   „sale committed” de „printed”; **reprint idempotent** din istoricul comenzii; coadă de tipărire cu retry;
   niciodată nu re-creăm comanda la retry de print.

### Majore (regresii funcționale / experiență)

6. **Regresie pe marketplace-ul LIVE.** Modelele `Event`/`TicketType`/`Order`/`Ticket` sunt partajate cu
   stiva marketplace (producție „Lacul Sf. Ana”, ambilet). *Mitigare:* orice schimbare pe modele partajate e
   aditivă și ramificată pe `tenant_id`/`display_template`; suită de regresie pe fluxul marketplace înainte de merge.

7. **Cascada de preț devine impredictibilă.** Se compun: bază → variantă durată → reguli pe zi → sezon
   (`LeisurePricingResolver`) → preț absolut pe canal (`ChannelPricingResolver`) → discount grup → abonament
   (gratis/redus) → `price_override` per dată → override per locație. *Mitigare:* **o singură funcție „preț
   efectiv” cu ordine deterministă documentată**, un singur punct de intrare folosit de POS + online + rapoarte;
   teste tabelare pe combinații; afișare „defalcare preț” în UI.

8. **Anti-fraudă abonamente/season pass.** Un abonament folosit de mai multe persoane, la mai multe porți
   simultan. *Mitigare:* scanare obligatorie la poartă cu log (`LeisureScanAttempt`), limită pe zi, detecție
   utilizări suspecte, foto/nume pe card membru.

9. **Scoping multi-locație.** Un operator legat de o locație nu trebuie să vândă/scaneze/raporteze pentru
   alta; produsele sunt pe pivot. *Mitigare:* scoping pe `location_id` în panoul Operator (nu doar `tenant_id`),
   verificat pe fiecare query; teste de izolare între locații.

10. **Conversia unui tenant existent la leisure.** `TenantObserver::updating` completează features, dar tenantul
    poate avea deja evenimente/comenzi. *Mitigare:* flux de conversie explicit (nu automat), validări, fără
    ștergere de date; leisure nou ≠ tenant convertit.

### Moderate (calitate / operare)

11. **Performanță catalog × locații × calendar.** Disponibilitate pe lună, POS instant pe tabletă. *Mitigare:*
    indexare (`tenant_id`, `ticket_type_id`, `capacity_date`), cache disponibilitate, paginare, query-uri lean.
12. **WebUSB doar Chrome/Edge** (+ WinUSB/Zadig pe Windows), diacritice RO/HU. *Mitigare:* documentație operator,
    fallback browser-print (`leisure.receipt`), test pe imprimante reale (Bixolon/Epson/Star/Citizen).
13. **Multi-locale** pe produse/bilete/bonuri (marketplace folosește `meta.translations`). *Mitigare:* strategie
    de traducere decisă devreme pentru coloanele de prim rang (tabel de traduceri vs JSON dedicat).
14. **Domenii publice per locație.** Un tenant cu N locații: un site cu selector vs subdomeniu per locație —
    afectează rezolvarea prin `Domain`. *Mitigare:* decizie de produs (recomandat: un site cu selector locație).
15. **Offline la checkin** (salină/rezervație cu semnal slab). *Mitigare:* EPIC 9.6 (validare locală + sync).
16. **Nu reutiliza `DoorSalesController`/`app/Services/DoorSales`** — scaffold neconectat, card-only/EUR;
    `door-sales` rămâne doar feature-flag/microserviciu.
17. **Volum de portat** — `LeisureController` ~4.500 linii. *Mitigare:* portare incrementală pe sub-domenii
    (casă → sale → scanări → rapoarte), cu teste la fiecare pas; fără „big bang”.

### Strategie transversală de mitigare
- **Expand/contract** pentru toate migrările pe tabele partajate (nicicând schimbări distructive directe).
- **Feature flags** (`features.leisure.*`) pentru a livra incremental și a putea dezactiva rapid.
- **Suită de regresie marketplace** rulată înainte de orice merge care atinge modele partajate.
- **Seeder demo realist** (EPIC 2.3) ca fundație de testare manuală + automată.

---

## Rezumat livrabile pe cele 4 cerințe ale task-ului

| Cerință | Epice | Rezultat |
|---|---|---|
| Toate funcționalitățile marketplace pe tenant leisure | EPIC 1, 2, 3, 7 | Model tenant-scoped complet (locații+produse) + provisioning + refactor |
| Interfață publică vânzare + template `tenant-demos/leisure` | EPIC 6 | Skin PHP + API public + plată online + deploy |
| Interfață POS (ca la ambilet) | EPIC 4 | POS Operator funcțional + casă + tipărire ESC/POS |
| Admin complet pentru tenant leisure | EPIC 5 | Locații + catalog produse, calendar, sezoane, casă, rapoarte, embed, facturare |
| Tarife de grup & abonamente/season pass | EPIC 8 | Praguri grup pe produs, rezervări grup, abonament leisure via **FlexPass** cu redemption poartă/POS |
| Capabilități specifice profil (salină/parc/rezervație/muzeu) | EPIC 9 | Restricții, waivere, camping multi-noapte, offline check-in, kiosk, rapoarte fiscale |
| Blocante corectitudine & conformitate | EPIC 10 | Fix scurgere capacitate, TTL rezervări, webhook plată, validare poartă, **bon fiscal**, numerotare, câmpuri prim rang |
| Accelerare prin reuse | EPIC 11 | Hartă componente existente (FlexPass, e-Factura/ANAF, wallet, cupoane, waitlist, analytics) → epice |
