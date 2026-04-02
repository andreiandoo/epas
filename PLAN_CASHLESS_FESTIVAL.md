# PLAN: Microserviciu Cashless pentru Festival

> **Branch:** `claude/plan-cashless-festival-nR4NW`
> **Data:** 2026-04-02
> **Bază analiză:** branch `core`

---

## 1. Situația Actuală (Ce Există)

### Modele existente relevante:
| Model | Ce face | Ce lipsește |
|-------|---------|-------------|
| `Vendor` | Date companie, CUI, ANAF, API token | Roluri user (manager/supervisor/member), login cu parolă per user |
| `VendorEmployee` | Staff cu PIN, rol generic | Roluri doar `admin` – lipsesc `manager`, `supervisor`, `member`; lipsește login cu email/parolă |
| `VendorProduct` | Produs cu preț, variante, alergeni | Lipsesc: `type` (food/drink/merch), `weight_volume` (gramaj), `unit_measure`, preț supplier vs preț vânzare |
| `VendorProductCategory` | Categorii ierarhice | OK, funcțional |
| `VendorSaleItem` | Line item per vânzare | Lipsește entitate `CashlessSale` (grupare items într-o tranzacție), lipsește legătura directă cu `Customer` |
| `Wristband` | Balance, topup, charge, refund, cashout, transfer | Lipsește: cont digital client (fără wristband fizic), top-up online vs fizic, locație top-up |
| `WristbandTransaction` | Ledger tranzacții | Lipsește: `channel` (online/physical), `topup_location_id`, balance snapshot pe Customer |
| `MerchandiseSupplier` | Supplier basic (nume, CUI, contact) | Lipsesc: branduri, produse supplier, prețuri, contracte, stocuri |
| `MerchandiseItem` | Item cu preț achiziție, VAT | Lipsește: legătură cu VendorProduct, preț vânzare, markup |
| `MerchandiseAllocation` | Distribuție item → vendor | OK ca bază, dar lipsește tracking consum |
| `FestivalEdition` | Ediție festival cu cashless_mode | OK |
| `VendorEdition` | Pivot vendor↔ediție cu comision | Lipsesc: taxă fixă/zi, perioadă, modele finance complexe |
| `Customer` | Profil client, date_of_birth, points | Lipsesc: sex/gender, balance cont digital, profil comportamental |
| `FestivalPassPurchase` | Pass cumpărat → wristband | OK ca legătură bilet ↔ cashless |

### Ce trebuie construit de la zero:
1. **CashlessAccount** – cont digital per client/ediție (suportă și fără wristband fizic)
2. **CashlessSale** – entitate vânzare completă (grupează sale items)
3. **TopUpLocation** – locații fizice de top-up
4. **SupplierProduct** – produs din catalogul supplier cu preț bază
5. **SupplierBrand** – branduri per supplier
6. **InventoryMovement** – mișcări stoc (intrare, alocare, consum, retur)
7. **FinanceFeeRule** – reguli taxe festival (fixe/procent/perioadă)
8. **PricingRule** – reguli preț (markup, SGR, TVA, preț impus)
9. **CustomerProfile** – profil comportamental extins
10. **Rapoarte** – servicii + widgets Filament

---

## 2. Arhitectura Microserviciului Cashless

### 2.1 Activare Microserviciu

Microserviciul `cashless` se înregistrează în tabela `microservices` cu slug `cashless`. Când un tenant Festival îl activează, se deblocează automat sub-componentele:

```
Cashless (microservice principal)
├── Festival Vendors      (gestiune vendori + useri + produse)
├── Festival Sales        (vânzări + tracking)
├── Top-ups              (alimentări cont)
├── Cashouts             (retrageri din cont)
├── Reports              (rapoarte real-time + extensive)
├── Suppliers            (furnizori + stocuri)
├── Finance              (taxe, comisioane, pricing)
└── Customer Profiles    (profilare clienți)
```

### 2.2 Structura fișiere noi

```
app/
├── Models/
│   ├── Cashless/
│   │   ├── CashlessAccount.php
│   │   ├── CashlessSale.php
│   │   ├── TopUpLocation.php
│   │   ├── SupplierProduct.php
│   │   ├── SupplierBrand.php
│   │   ├── InventoryMovement.php
│   │   ├── InventoryStock.php
│   │   ├── FinanceFeeRule.php
│   │   ├── PricingRule.php
│   │   ├── PricingRuleComponent.php
│   │   └── CustomerProfile.php
│   └── (modelele existente rămân, se extind)
├── Services/
│   └── Cashless/
│       ├── CashlessAccountService.php
│       ├── SaleService.php
│       ├── TopUpService.php
│       ├── CashoutService.php
│       ├── SupplierStockService.php
│       ├── PricingService.php
│       ├── FinanceFeeService.php
│       ├── ReportService.php
│       ├── CustomerProfileService.php
│       └── ProductImportService.php
├── Http/
│   └── Controllers/Api/Cashless/
│       ├── AccountController.php
│       ├── SaleController.php
│       ├── TopUpController.php
│       ├── CashoutController.php
│       ├── SupplierController.php
│       ├── StockController.php
│       ├── FinanceController.php
│       ├── ReportController.php
│       └── VendorUserController.php
├── Filament/
│   └── Tenant/Resources/
│       └── Cashless/
│           ├── CashlessAccountResource.php
│           ├── CashlessSaleResource.php
│           ├── TopUpResource.php
│           ├── CashoutResource.php
│           ├── SupplierResource.php
│           ├── StockResource.php
│           ├── FinanceRuleResource.php
│           ├── PricingRuleResource.php
│           ├── CustomerProfileResource.php
│           └── Pages/
│               └── CashlessReports.php
└── Enums/
    ├── VendorUserRole.php
    ├── TopUpChannel.php
    ├── TopUpMethod.php
    ├── CashoutChannel.php
    ├── FeeType.php
    ├── PricingComponentType.php
    ├── ProductType.php
    └── StockMovementType.php
```

---

## 3. Festival Vendors (Extindere)

### 3.1 Modificări model `VendorEmployee` → roluri noi

**Enum `VendorUserRole`:**
```php
enum VendorUserRole: string
{
    case Manager = 'manager';       // CRUD produse, rapoarte, gestiune staff
    case Supervisor = 'supervisor'; // Vizualizare rapoarte, gestiune shift-uri
    case Member = 'member';         // Doar operare POS (vânzare)
}
```

**Câmpuri noi pe `VendorEmployee`:**
```
+ full_name          VARCHAR(255)
+ email              VARCHAR(255) UNIQUE per vendor
+ phone              VARCHAR(50)
+ password           VARCHAR(255) -- bcrypt, pentru login
+ role               ENUM('manager','supervisor','member') -- înlocuiește rolul generic
+ email_verified_at  TIMESTAMP NULL
```

**Permisiuni per rol:**

| Acțiune | Manager | Supervisor | Member |
|---------|---------|------------|--------|
| Login panou vendor | ✅ | ✅ | ✅ |
| Operare POS (vânzări) | ✅ | ✅ | ✅ |
| Vizualizare rapoarte | ✅ | ✅ | ❌ |
| CRUD produse | ✅ | ❌ | ❌ |
| Import CSV produse | ✅ | ❌ | ❌ |
| Gestiune staff (CRUD useri) | ✅ | ❌ | ❌ |
| Gestiune shift-uri | ✅ | ✅ | ❌ |
| Vizualizare stocuri | ✅ | ✅ | ❌ |

### 3.2 Modificări model `VendorProduct` → câmpuri noi

```
+ type               ENUM('food','drink','alcohol','tobacco','merch','service','other')
+ unit_measure       VARCHAR(50) -- 'ml', 'g', 'kg', 'buc', 'porție'
+ weight_volume      DECIMAL(10,2) -- gramaj/volum (ex: 330 ml, 500 g)
+ supplier_product_id BIGINT NULL FK -- legătură cu produsul supplier
+ base_price_cents   INT NULL -- preț de bază de la supplier
+ sale_price_cents   INT -- preț final de vânzare (calculat sau manual)
+ is_age_restricted  BOOLEAN DEFAULT false -- doar pentru adulți (18+)
+ min_age            INT DEFAULT 18 -- vârsta minimă (de obicei 18)
+ sgr_cents          INT DEFAULT 0 -- taxa SGR (reciclare ambalaj)
+ vat_rate           DECIMAL(5,2) DEFAULT 19.00 -- rata TVA
+ vat_included       BOOLEAN DEFAULT true -- preț include TVA
+ sku                VARCHAR(100) NULL -- cod intern produs
```

### 3.3 Import CSV produse

**Format CSV acceptat:**
```csv
name,type,category,weight_volume,unit_measure,sale_price,vat_rate,is_age_restricted,sku
"Coca-Cola 330ml",drink,Sucuri,330,ml,9.99,19,false,CC330
"Bere Ursus 500ml",alcohol,Bere,500,ml,14.99,19,true,BU500
"Hot Dog Classic",food,Fast Food,250,g,12.99,9,false,HD250
```

**Service: `ProductImportService`**
- Parsare CSV cu validare per rând
- Matching automat pe categorie (creare dacă nu există)
- Matching pe supplier_product dacă SKU se potrivește
- Raport import: succese, erori, skip-uri
- Suport encoding UTF-8 BOM

---

## 4. Festival Sales (Vânzări)

### 4.1 Model nou: `CashlessSale`

O vânzare cashless grupează toate line items (VendorSaleItem) într-o singură tranzacție. Aceasta este entitatea principală de raportare.

**Tabel `cashless_sales`:**
```
id                      BIGINT PK AUTO
tenant_id               BIGINT FK → tenants
festival_edition_id     BIGINT FK → festival_editions
vendor_id               BIGINT FK → vendors
cashless_account_id     BIGINT FK → cashless_accounts
customer_id             BIGINT FK → customers NULL
wristband_transaction_id BIGINT FK → wristband_transactions NULL
vendor_employee_id      BIGINT FK → vendor_employees NULL
vendor_pos_device_id    BIGINT FK → vendor_pos_devices NULL
vendor_shift_id         BIGINT FK → vendor_shifts NULL
sale_number             VARCHAR(50) UNIQUE -- ref: SALE-XXXXX
subtotal_cents          INT -- sumă fără taxe
tax_cents               INT -- total taxe (TVA + SGR etc.)
total_cents             INT -- total final
commission_cents        INT -- comision festival
currency                VARCHAR(3)
items_count             INT
status                  ENUM('completed','refunded','partial_refund','voided')
sold_at                 TIMESTAMP -- data și ora exactă
meta                    JSON NULL
created_at              TIMESTAMP
updated_at              TIMESTAMP
```

**Relații:**
- `CashlessSale` hasMany `VendorSaleItem` (prin `cashless_sale_id` adăugat pe VendorSaleItem)
- `CashlessSale` belongsTo `Customer`, `Vendor`, `CashlessAccount`

### 4.2 Modificări pe `VendorSaleItem`

```
+ cashless_sale_id    BIGINT FK → cashless_sales
+ tax_cents           INT DEFAULT 0 -- TVA per item
+ sgr_cents           INT DEFAULT 0 -- taxa SGR per item
+ product_type        VARCHAR(50) -- snapshot de la produs
+ product_category_name VARCHAR(100) -- snapshot categorie (pentru rapoarte rapide)
```

### 4.3 Vizualizare vânzări grupate pe categorie

**Endpoint:** `GET /api/cashless/{editionId}/sales/by-category`

Response:
```json
{
  "data": [
    {
      "category": "Bere",
      "total_sales": 15420,
      "total_revenue_cents": 23145000,
      "total_quantity": 15420,
      "avg_price_cents": 1499,
      "products": [
        { "name": "Ursus 500ml", "quantity": 8200, "revenue_cents": 12298000 },
        { "name": "Heineken 330ml", "quantity": 7220, "revenue_cents": 10847000 }
      ]
    },
    {
      "category": "Fast Food",
      "total_sales": 8750,
      "total_revenue_cents": 11375000,
      ...
    }
  ]
}
```

**Widget Filament:** tabel cu group-by pe categorie, expandable per produs, filtre pe vendor/zi/oră.

---

## 5. Top-ups & Cashouts

### 5.1 Model nou: `CashlessAccount`

Contul digital al clientului per ediție festival. Suportă atât wristband fizic cât și cont pur digital (app/website).

**Tabel `cashless_accounts`:**
```
id                      BIGINT PK AUTO
tenant_id               BIGINT FK → tenants
festival_edition_id     BIGINT FK → festival_editions
customer_id             BIGINT FK → customers
wristband_id            BIGINT FK → wristbands NULL -- poate fi NULL (cont digital fără wristband)
festival_pass_purchase_id BIGINT FK NULL
account_number          VARCHAR(50) UNIQUE -- CA-XXXXXXXXX
balance_cents           INT DEFAULT 0 -- sold curent (row-level locking!)
total_topped_up_cents   INT DEFAULT 0 -- total alimentat ever
total_spent_cents       INT DEFAULT 0 -- total cheltuit ever
total_cashed_out_cents  INT DEFAULT 0 -- total retras ever
currency                VARCHAR(3)
status                  ENUM('active','frozen','closed')
activated_at            TIMESTAMP NULL
closed_at               TIMESTAMP NULL
meta                    JSON NULL
created_at              TIMESTAMP
updated_at              TIMESTAMP
```

**Logică:**
- La check-in (activare pass), se creează automat un `CashlessAccount`
- Dacă există wristband, se linkează
- Dacă clientul face top-up din app/website, contul funcționează și fără wristband
- Balance-ul din `CashlessAccount` este sursa de adevăr; `Wristband.balance_cents` devine un mirror/cache

### 5.2 Model nou: `TopUpLocation`

**Tabel `topup_locations`:**
```
id                      BIGINT PK AUTO
tenant_id               BIGINT FK → tenants
festival_edition_id     BIGINT FK → festival_editions
name                    VARCHAR(255) -- "Top-up Stand Intrare Nord"
location_code           VARCHAR(50) UNIQUE
coordinates             VARCHAR(100) NULL -- lat,lng
zone                    VARCHAR(100) NULL -- "Zona A", "Intrare"
is_active               BOOLEAN DEFAULT true
operating_hours         JSON NULL
meta                    JSON NULL
created_at              TIMESTAMP
updated_at              TIMESTAMP
```

### 5.3 Extindere `WristbandTransaction` pentru Top-ups

**Câmpuri noi:**
```
+ channel               ENUM('online','physical') -- online = app/website, physical = stand
+ topup_method           ENUM('card','cash','bank_transfer','voucher') NULL
+ topup_location_id      BIGINT FK → topup_locations NULL
+ cashless_account_id    BIGINT FK → cashless_accounts
+ balance_snapshot_cents  INT -- snapshot sold DUPĂ tranzacție pe CashlessAccount
+ customer_email         VARCHAR(255) NULL -- snapshot pt. rapoarte
+ customer_name          VARCHAR(255) NULL -- snapshot pt. rapoarte
```

### 5.4 Flow Top-up

**Online (app/website):**
1. Clientul se autentifică → vede `CashlessAccount` cu sold
2. Alege suma → plată card (Stripe/procesator configurat)
3. La confirmare plată: `CashlessAccountService::topUp()`
   - Lock row pe `CashlessAccount`
   - Incrementare `balance_cents` și `total_topped_up_cents`
   - Creare `WristbandTransaction` cu `channel=online`, `topup_method=card`
   - Snapshot `balance_snapshot_cents` = noul sold
   - Dacă are wristband linked, sincronizează și `Wristband.balance_cents`

**Fizic (stand):**
1. Operator scanează wristband (NFC/QR) → identifică `CashlessAccount`
2. Alege suma + metodă (cash/card)
3. `CashlessAccountService::topUp()` cu `channel=physical`, `topup_location_id`
4. Același flow atomic ca mai sus

### 5.5 Flow Cashout

**Câmpuri noi pe `WristbandTransaction` pentru cashout:**
```
+ cashout_channel        ENUM('online','physical') NULL
+ cashout_method         ENUM('bank_transfer','cash','card_refund') NULL
+ cashout_reference      VARCHAR(255) NULL -- ref transfer bancar
+ cashout_processed_at   TIMESTAMP NULL
+ cashout_status         ENUM('pending','processing','completed','failed') NULL
```

**Cashout digital (online):**
1. Clientul cere retragere din app → sold se blochează
2. Transfer bancar sau refund pe card (async job)
3. Când se confirmă: `cashout_status = completed`

**Cashout fizic (stand):**
1. Operator scanează wristband → vede sold
2. Dă cash/card refund → `CashlessAccountService::cashout()`
3. Instant `cashout_status = completed`

**Cashout parțial:**
- Spre deosebire de cashout-ul actual (Wristband.cashout() care retrage TOT), trebuie suportat și cashout parțial
- `CashlessAccountService::cashout(int $amountCents, ...)` – retrage suma specificată, nu tot soldul

---

## 6. Reports (Rapoarte)

Aceasta este componenta cea mai critică. Rapoartele trebuie să fie atât **real-time** (dashboard live) cât și **extensive** (export CSV/PDF, perioade lungi).

### 6.1 Arhitectura Rapoarte

```
ReportService
├── RealTimeReportService    (query-uri live pe DB, cache Redis 30s)
├── AggregateReportService   (pre-calculate, stocate în tabele aggregate)
└── ExportReportService      (generare CSV/PDF async via Jobs)
```

**Tabel `cashless_report_snapshots` (agregări pre-calculate):**
```
id                      BIGINT PK AUTO
tenant_id               BIGINT FK
festival_edition_id     BIGINT FK
report_type             VARCHAR(50) -- 'hourly_sales', 'daily_vendor', etc.
period_start            TIMESTAMP
period_end              TIMESTAMP
dimensions              JSON -- {"vendor_id": 5, "category": "Bere"}
metrics                 JSON -- {"total_cents": 500000, "count": 342, ...}
created_at              TIMESTAMP
```

Job periodic (la fiecare 5 minute): `AggregateCashlessReportsJob` calculează snapshot-uri.

### 6.2 Catalog Rapoarte

#### A. RAPOARTE VÂNZĂRI (Sales Reports)

| # | Raport | Descriere | Filtre | Tip |
|---|--------|-----------|--------|-----|
| S1 | **Vânzări totale** | Total vânzări per ediție, cu trend orar | Perioadă, vendor, categorie | Real-time |
| S2 | **Vânzări per vendor** | Ranking vendori după volum/valoare | Perioadă, categorie produs | Real-time |
| S3 | **Vânzări per produs** | Top produse vândute (cantitate + valoare) | Vendor, categorie, perioadă | Real-time |
| S4 | **Vânzări per categorie** | Grupare pe categorii (Bere, Food, etc.) | Vendor, perioadă | Real-time |
| S5 | **Vânzări per tip produs** | Food vs Drink vs Alcohol vs Merch | Vendor, perioadă | Real-time |
| S6 | **Vânzări pe oră** | Heatmap: ore peak vs ore slabe | Vendor, zi, categorie | Real-time |
| S7 | **Vânzări pe zi** | Trend zilnic cu comparație zile | Vendor, categorie | Real-time |
| S8 | **Vânzări per POS device** | Performanță per terminal | Vendor, perioadă | Real-time |
| S9 | **Vânzări per angajat** | Performanță per employee | Vendor, shift | Real-time |
| S10 | **Vânzări per shift** | Venituri per schimb de lucru | Vendor, angajat | Real-time |
| S11 | **Comparație vendori** | Side-by-side 2+ vendori | Perioadă | Extensiv |
| S12 | **Trend vânzări real-time** | Grafic live cu vânzări pe minut | - | Real-time |

#### B. RAPOARTE FINANCIARE (Finance Reports)

| # | Raport | Descriere | Filtre | Tip |
|---|--------|-----------|--------|-----|
| F1 | **Revenue total festival** | Venituri totale (topups - cashouts) | Perioadă | Real-time |
| F2 | **Comisioane colectate** | Total comisioane de la vendori | Vendor, perioadă | Real-time |
| F3 | **Taxe colectate (SGR, etc.)** | Breakdown taxe per tip | Perioadă | Extensiv |
| F4 | **TVA colectat** | TVA total, per cotă (9%, 19%) | Vendor, categorie | Extensiv |
| F5 | **Sold vendori** | Cât datorează fiecare vendor sau i se datorează | Vendor | Real-time |
| F6 | **Fee-uri festival** | Taxe fixe/procent aplicate per vendor | Vendor, perioadă | Extensiv |
| F7 | **Profit per produs** | Preț vânzare - preț achiziție - taxe | Vendor, categorie | Extensiv |
| F8 | **Reconciliere** | Tranzacții offline nereconciliate | Status | Real-time |
| F9 | **Balanță generală** | Total alimentat - total cheltuit - total retras | Perioadă | Real-time |
| F10 | **Solduri neretrase** | Bani rămași în conturi la final festival | - | Extensiv |

#### C. RAPOARTE TOP-UP & CASHOUT

| # | Raport | Descriere | Filtre | Tip |
|---|--------|-----------|--------|-----|
| T1 | **Top-ups per canal** | Online vs fizic (volum + valoare) | Perioadă, locație | Real-time |
| T2 | **Top-ups per metodă** | Card vs cash vs voucher | Canal, perioadă | Real-time |
| T3 | **Top-ups per locație** | Ranking locații top-up | Perioadă | Real-time |
| T4 | **Top-ups pe oră** | Pattern temporal alimentări | Zi, canal | Real-time |
| T5 | **Valoare medie top-up** | Media per tranzacție, trend | Canal, perioadă | Real-time |
| T6 | **Cashouts per canal** | Online vs fizic | Perioadă | Real-time |
| T7 | **Cashouts per status** | Pending vs completed vs failed | Canal | Real-time |
| T8 | **Rata de cashout** | % din total topped-up care se retrage | Perioadă | Extensiv |

#### D. RAPOARTE CLIENȚI

| # | Raport | Descriere | Filtre | Tip |
|---|--------|-----------|--------|-----|
| C1 | **Clienți activi** | Nr. clienți cu cel puțin o tranzacție | Zi, perioadă | Real-time |
| C2 | **Spending mediu per client** | Total cheltuit / nr. clienți | Perioadă | Real-time |
| C3 | **Top spenders** | Ranking clienți după cheltuieli | Perioadă, categorie | Extensiv |
| C4 | **Distribuție solduri** | Histogramă: câți clienți au sold 0-10, 10-50, 50-100, 100+ | - | Real-time |
| C5 | **Frecvență achiziții** | Clienți cu 1, 2-5, 5-10, 10+ achiziții | Perioadă | Extensiv |
| C6 | **Coș mediu** | Valoare medie per tranzacție | Vendor, categorie, oră | Real-time |
| C7 | **Retenție intra-festival** | Clienți care revin la același vendor | Vendor | Extensiv |
| C8 | **Demografie achizitii** | Vânzări segmentate pe sex, vârstă | Categorie, vendor | Extensiv |
| C9 | **Ore peak per segment** | Când cumpără tinerii vs adulții | Segment, categorie | Extensiv |
| C10 | **Cross-sell analysis** | Clienți care cumpără din 2+ categorii | Categorie | Extensiv |

#### E. RAPOARTE STOCURI & SUPPLIERS

| # | Raport | Descriere | Filtre | Tip |
|---|--------|-----------|--------|-----|
| I1 | **Stoc curent per vendor** | Ce are fiecare vendor în stoc | Vendor, categorie | Real-time |
| I2 | **Stoc curent per supplier** | Ce a mai rămas din livrare | Supplier, produs | Real-time |
| I3 | **Consum per zi** | Câte unități s-au consumat pe zi | Vendor, produs | Real-time |
| I4 | **Predicție stoc** | La ritmul actual, când se termină? | Vendor, produs | Extensiv |
| I5 | **Raport livrări** | Toate livrările supplier → festival | Supplier, perioadă | Extensiv |
| I6 | **Raport alocări** | Distribuție marfă festival → vendori | Vendor, produs | Extensiv |
| I7 | **Pierderi/diferențe** | Stoc teoretic vs vândut (diferențe) | Vendor | Extensiv |

#### F. RAPOARTE OPERAȚIONALE

| # | Raport | Descriere | Filtre | Tip |
|---|--------|-----------|--------|-----|
| O1 | **Dashboard live** | KPI-uri real-time: vânzări/minut, topups/minut, nr. tranzacții | - | Real-time |
| O2 | **Activitate POS** | Status terminale: online/offline, ultima activitate | Vendor | Real-time |
| O3 | **Tranzacții offline** | Nr. tranzacții offline nesinc | Vendor, device | Real-time |
| O4 | **Alerte fraud** | Tranzacții suspecte (volum mare, frecvență anormală) | - | Real-time |
| O5 | **Utilizare wristband** | Wristbands active vs inactive, pierdute | Status | Real-time |

### 6.3 Dashboard Live (Widget-uri Filament)

**Widget-uri pe pagina principală Cashless:**

1. **KPI Cards (4 carduri top):**
   - Total Revenue (ediție curentă)
   - Tranzacții azi
   - Top-ups azi
   - Clienți activi azi

2. **Grafic liniar:** Vânzări per oră (ultimele 24h)
3. **Pie chart:** Distribuție vânzări pe categorie
4. **Bar chart:** Top 10 vendori după venituri
5. **Tabel:** Ultimele 20 tranzacții (live refresh)
6. **Heatmap:** Vânzări per oră per zi (matrice)

### 6.4 Export

- **CSV:** toate rapoartele extensive
- **PDF:** rapoarte sumar cu grafice (via DomPDF, deja instalat)
- **Scheduled:** rapoarte automate zilnice/săptămânale pe email (Job + Mail)
- **API:** toate rapoartele expuse și prin API pentru integrări externe

---

## 7. Suppliers (Furnizori & Stocuri)

### 7.1 Extindere `MerchandiseSupplier` → Supplier complet

Modelul existent `MerchandiseSupplier` este minimal (nume, CUI, contact). Trebuie extins semnificativ.

**Câmpuri noi pe `merchandise_suppliers`:**
```
+ company_name          VARCHAR(255)
+ reg_com               VARCHAR(50) NULL
+ fiscal_address        TEXT NULL
+ county                VARCHAR(100) NULL
+ city                  VARCHAR(100) NULL
+ country               VARCHAR(2) DEFAULT 'RO'
+ is_vat_payer          BOOLEAN DEFAULT false
+ bank_name             VARCHAR(255) NULL
+ iban                  VARCHAR(50) NULL
+ contract_number       VARCHAR(100) NULL
+ contract_start        DATE NULL
+ contract_end          DATE NULL
+ payment_terms_days    INT DEFAULT 30
+ status                ENUM('active','inactive','pending') DEFAULT 'active'
+ logo_url              VARCHAR(500) NULL
+ website               VARCHAR(500) NULL
```

### 7.2 Model nou: `SupplierBrand`

Un supplier poate avea mai multe branduri (ex: Coca-Cola HBC are brandurile Coca-Cola, Fanta, Sprite, Dorna).

**Tabel `supplier_brands`:**
```
id                      BIGINT PK AUTO
tenant_id               BIGINT FK → tenants
merchandise_supplier_id BIGINT FK → merchandise_suppliers
name                    VARCHAR(255) -- "Coca-Cola", "Fanta"
slug                    VARCHAR(255)
logo_url                VARCHAR(500) NULL
category                VARCHAR(100) NULL -- "Sucuri", "Ape"
is_active               BOOLEAN DEFAULT true
meta                    JSON NULL
created_at              TIMESTAMP
updated_at              TIMESTAMP
```

### 7.3 Model nou: `SupplierProduct`

Produsul din catalogul supplier-ului, cu preț de bază. Diferit de `VendorProduct` (care e ce vinde vendor-ul).

**Tabel `supplier_products`:**
```
id                      BIGINT PK AUTO
tenant_id               BIGINT FK → tenants
merchandise_supplier_id BIGINT FK → merchandise_suppliers
supplier_brand_id       BIGINT FK → supplier_brands NULL
festival_edition_id     BIGINT FK → festival_editions
name                    VARCHAR(255) -- "Coca-Cola 330ml PET"
sku                     VARCHAR(100) -- cod produs supplier
type                    ENUM('food','drink','alcohol','tobacco','merch','service','other')
unit_measure            VARCHAR(50) -- 'buc', 'kg', 'litru'
weight_volume           DECIMAL(10,2) NULL
base_price_cents        INT -- preț furnizor fără TVA
vat_rate                DECIMAL(5,2) DEFAULT 19.00
price_with_vat_cents    INT -- preț furnizor cu TVA
packaging_type          VARCHAR(100) NULL -- "PET", "doză", "sticlă"
packaging_units         INT DEFAULT 1 -- câte buc/bax
barcode                 VARCHAR(50) NULL
is_age_restricted       BOOLEAN DEFAULT false
min_age                 INT DEFAULT 18
is_active               BOOLEAN DEFAULT true
meta                    JSON NULL
created_at              TIMESTAMP
updated_at              TIMESTAMP
```

### 7.4 Stocuri: `InventoryStock` + `InventoryMovement`

Două niveluri de stoc: **supplier level** (cât a livrat) și **vendor level** (cât are fiecare vendor).

**Tabel `inventory_stocks`:**
```
id                      BIGINT PK AUTO
tenant_id               BIGINT FK → tenants
festival_edition_id     BIGINT FK → festival_editions
supplier_product_id     BIGINT FK → supplier_products
vendor_id               BIGINT FK → vendors NULL -- NULL = stoc festival (nedistribuit)
quantity_total          DECIMAL(12,3) DEFAULT 0 -- total primit
quantity_allocated      DECIMAL(12,3) DEFAULT 0 -- distribuit la vendori (doar pt stoc festival)
quantity_sold           DECIMAL(12,3) DEFAULT 0 -- vândut (doar pt stoc vendor)
quantity_returned       DECIMAL(12,3) DEFAULT 0 -- returnat supplier/festival
quantity_wasted         DECIMAL(12,3) DEFAULT 0 -- pierderi/stricăciuni
quantity_available      DECIMAL(12,3) GENERATED -- calculat: total - allocated - sold - returned - wasted
unit_measure            VARCHAR(50)
last_movement_at        TIMESTAMP NULL
meta                    JSON NULL
created_at              TIMESTAMP
updated_at              TIMESTAMP

UNIQUE (festival_edition_id, supplier_product_id, vendor_id)
```

**Tabel `inventory_movements`:**
```
id                      BIGINT PK AUTO
tenant_id               BIGINT FK → tenants
festival_edition_id     BIGINT FK → festival_editions
inventory_stock_id      BIGINT FK → inventory_stocks
supplier_product_id     BIGINT FK → supplier_products
movement_type           ENUM('delivery','allocation','sale','return_to_supplier','return_to_festival','waste','correction')
from_vendor_id          BIGINT FK NULL -- de unde vine
to_vendor_id            BIGINT FK NULL -- unde se duce
quantity                DECIMAL(12,3) -- cantitate (pozitivă)
unit_measure            VARCHAR(50)
reference               VARCHAR(255) NULL -- nr. aviz, nr. factură
notes                   TEXT NULL
performed_by            VARCHAR(255) NULL -- cine a făcut mișcarea
performed_at            TIMESTAMP
meta                    JSON NULL
created_at              TIMESTAMP
updated_at              TIMESTAMP
```

### 7.5 Flow-uri Stoc

**Flow 1: Supplier livrează la festival**
```
1. Admin festival creează delivery: movement_type = 'delivery'
   → Crește inventory_stocks.quantity_total unde vendor_id IS NULL (stoc festival)
2. Se asociază cu invoice (merchandise_items.invoice_number)
```

**Flow 2: Festival distribuie la vendor**
```
1. Admin alocă stoc: movement_type = 'allocation'
   → Scade quantity_available din stocul festival (to_vendor_id = NULL)
   → Crește quantity_total în stocul vendor-ului
   → from_vendor_id = NULL, to_vendor_id = vendor_id
```

**Flow 3: Vendor vinde**
```
1. La fiecare CashlessSale care conține un produs legat de supplier_product:
   → Auto-increment quantity_sold pe stocul vendor-ului
   → Auto-creare InventoryMovement cu movement_type = 'sale'
```

**Flow 4: Retur la festival / supplier**
```
1. movement_type = 'return_to_festival' sau 'return_to_supplier'
   → Ajustări pe stocuri
```

### 7.6 Legătura Supplier Product → Vendor Product

Un `VendorProduct` poate fi legat opțional de un `SupplierProduct` prin `supplier_product_id`. Aceasta permite:
- Calcul markup automat (preț vânzare - preț achiziție)
- Tracking consum automat (vânzare → decrementare stoc)
- Rapoarte profit per produs
- Prețuri impuse de festival (vezi secțiunea Finance)

---

## 8. Finance (Taxe, Comisioane, Pricing)

### 8.1 Model nou: `FinanceFeeRule`

Reguli de taxare pe care festivalul le aplică vendorilor.

**Tabel `finance_fee_rules`:**
```
id                      BIGINT PK AUTO
tenant_id               BIGINT FK → tenants
festival_edition_id     BIGINT FK → festival_editions
vendor_id               BIGINT FK → vendors NULL -- NULL = se aplică tuturor
name                    VARCHAR(255) -- "Taxa participare zilnică"
fee_type                ENUM('fixed_daily','fixed_period','percentage_sales','fixed_per_transaction','percentage_per_category')
amount_cents            INT NULL -- pentru fixed_daily, fixed_period, fixed_per_transaction
percentage              DECIMAL(8,4) NULL -- pentru percentage_sales, percentage_per_category
category_filter         JSON NULL -- ["Bere","Sucuri"] pt. percentage_per_category
period_start            DATE NULL
period_end              DATE NULL
is_active               BOOLEAN DEFAULT true
apply_on                ENUM('gross_sales','net_sales') DEFAULT 'gross_sales'
billing_frequency       ENUM('daily','weekly','end_of_festival','custom')
notes                   TEXT NULL
meta                    JSON NULL
created_at              TIMESTAMP
updated_at              TIMESTAMP
```

**Exemple configurare:**
```
Taxa fixă pe zi:         fee_type='fixed_daily', amount_cents=50000 (500 RON/zi)
Taxa fixă pe perioadă:   fee_type='fixed_period', amount_cents=300000, period_start/end
Procent din vânzări:     fee_type='percentage_sales', percentage=15.00 (15%)
Procent per categorie:   fee_type='percentage_per_category', percentage=20.00, category_filter=["Alcool"]
Taxă per tranzacție:     fee_type='fixed_per_transaction', amount_cents=50 (0.50 RON/tranzacție)
```

### 8.2 Model nou: `PricingRule` + `PricingRuleComponent`

Pricing rules permit festivalului să seteze prețuri de vânzare obligatorii pe produse furnizate de suppliori. Aceasta acoperă exemplul cu Cola: preț supplier 4.59 → preț vânzare 9.99 + 0.50 SGR.

**Tabel `pricing_rules`:**
```
id                      BIGINT PK AUTO
tenant_id               BIGINT FK → tenants
festival_edition_id     BIGINT FK → festival_editions
supplier_product_id     BIGINT FK → supplier_products NULL -- per produs specific
supplier_brand_id       BIGINT FK → supplier_brands NULL -- per brand
product_category        VARCHAR(100) NULL -- per categorie
name                    VARCHAR(255) -- "Pricing Cola 330ml"
is_mandatory            BOOLEAN DEFAULT true -- vendorii TREBUIE să aplice acest preț
final_price_cents       INT -- prețul final de vânzare (ex: 1049 = 10.49 RON)
currency                VARCHAR(3) DEFAULT 'RON'
is_active               BOOLEAN DEFAULT true
valid_from              DATE NULL
valid_until             DATE NULL
notes                   TEXT NULL
meta                    JSON NULL
created_at              TIMESTAMP
updated_at              TIMESTAMP
```

**Tabel `pricing_rule_components` (breakdown prețului final):**
```
id                      BIGINT PK AUTO
pricing_rule_id         BIGINT FK → pricing_rules
component_type          ENUM('base_price','markup_fixed','markup_percentage','vat','sgr','eco_tax','service_fee','custom')
label                   VARCHAR(255) -- "Preț bază furnizor", "Adaos comercial", "TVA 19%", "Taxa SGR"
amount_cents            INT NULL -- sumă fixă
percentage              DECIMAL(8,4) NULL -- procent (ex: 19.0000 pentru TVA)
applies_on              ENUM('base_price','subtotal','custom') DEFAULT 'base_price'
sort_order              INT DEFAULT 0
is_included_in_final    BOOLEAN DEFAULT true -- contribuie la prețul final
meta                    JSON NULL
created_at              TIMESTAMP
updated_at              TIMESTAMP
```

### 8.3 Exemplu concret: Cola 330ml

**Supplier:** Coca-Cola HBC
**Produs:** Cola 330ml PET
**Preț supplier (cu TVA):** 4.59 RON

**Pricing Rule: "Cola 330ml - Preț festival"**
```
final_price_cents = 1049 (10.49 RON total)

Components:
1. base_price:        459 cents  (preț furnizor cu TVA)
2. markup_fixed:      490 cents  (adaos comercial)
3. sgr:                50 cents  (taxa SGR reciclare)
   ─────────────────────────────
   TOTAL:            999 cents  = 9.99 RON preț produs
   + SGR:             50 cents  = 0.50 RON
   ─────────────────────────────
   FINAL:           1049 cents  = 10.49 RON
```

**Alternativ, cu TVA explicit:**
```
Components:
1. base_price:        386 cents  (preț furnizor fără TVA: 4.59/1.19)
2. markup_fixed:      412 cents  (adaos fără TVA)
3. vat (19%):         152 cents  (TVA pe 386+412=798)
4. sgr:                50 cents  (taxa SGR, neimpozabilă)
   ─────────────────────────────
   TOTAL:            1000 cents = 10.00 RON + 0.50 SGR = 10.50 RON
```

Flexibilitatea vine din faptul că fiecare componentă e independentă și poate fi de orice tip.

### 8.4 Enforcement prețuri

Când `is_mandatory = true` pe un PricingRule:
1. La crearea/editarea unui `VendorProduct` legat de acel `SupplierProduct`, sistemul **forțează** `sale_price_cents` = `final_price_cents` din regula de pricing
2. Vendor-ul Manager poate vedea breakdown-ul prețului dar **nu poate modifica** prețul de vânzare
3. Excepție: produse fără PricingRule → vendor-ul setează liber prețul

### 8.5 Calcul automat comisioane la vânzare

La fiecare `CashlessSale`, `FinanceFeeService` calculează:
```php
// Pseudocod
foreach ($sale->items as $item) {
    // 1. Comision VendorEdition (existent)
    $commission = $vendorEdition->calculateCommission($item->total_cents);
    
    // 2. Fee-uri adiționale din FinanceFeeRules
    foreach ($applicableRules as $rule) {
        $fee = match($rule->fee_type) {
            'percentage_sales' => $item->total_cents * $rule->percentage / 100,
            'fixed_per_transaction' => $rule->amount_cents,
            'percentage_per_category' => inCategory($item, $rule) 
                ? $item->total_cents * $rule->percentage / 100 
                : 0,
        };
        $totalFees += $fee;
    }
}
```

### 8.6 Model: `VendorFinanceSummary` (agregat)

**Tabel `vendor_finance_summaries`:**
```
id                      BIGINT PK AUTO
tenant_id               BIGINT FK
festival_edition_id     BIGINT FK
vendor_id               BIGINT FK
period_date             DATE
gross_sales_cents       INT DEFAULT 0
net_sales_cents         INT DEFAULT 0 -- gross - refunds
commission_cents        INT DEFAULT 0
fees_cents              INT DEFAULT 0 -- din FinanceFeeRules
tax_collected_cents     INT DEFAULT 0 -- TVA colectat
sgr_collected_cents     INT DEFAULT 0 -- SGR colectat
vendor_payout_cents     INT DEFAULT 0 -- ce primește vendor-ul (net - commission - fees)
transactions_count      INT DEFAULT 0
meta                    JSON NULL
created_at              TIMESTAMP
updated_at              TIMESTAMP

UNIQUE (festival_edition_id, vendor_id, period_date)
```

Se calculează zilnic prin `CalculateVendorFinanceSummaryJob`.

---

## 9. Clienți Finali (Legătura bilet ↔ cashless)

### 9.1 Flow complet: Bilet → Cont Cashless → Achiziții

```
1. Client cumpără FestivalPass (bilet) → se creează FestivalPassPurchase
2. La check-in (scanare bilet la intrare):
   a. Se verifică FestivalPassPurchase.status = 'active'
   b. Se creează/activează CashlessAccount legat de Customer + FestivalPassPurchase
   c. Dacă e wristband fizic: se asociază Wristband → CashlessAccount
   d. Customer primește confirmarea pe email/app
3. Client face top-up → se alimentează CashlessAccount
4. Client cumpără de la vendor → se face charge pe CashlessAccount via CashlessSale
5. La final festival → cashout (parțial sau total)
```

### 9.2 Modificări pe `Customer`

**Câmpuri noi:**
```
+ gender                ENUM('male','female','other','prefer_not_to_say') NULL
+ age_group             VARCHAR(20) NULL -- calculat automat: 'minor', '18-24', '25-34', '35-44', '45-54', '55+'
+ id_verified           BOOLEAN DEFAULT false
+ id_verified_at        TIMESTAMP NULL
+ id_verification_method VARCHAR(50) NULL -- 'manual', 'ocr', 'app'
```

**Relații noi pe `Customer`:**
```php
public function cashlessAccounts(): HasMany
{
    return $this->hasMany(CashlessAccount::class);
}

public function cashlessAccountForEdition(int $editionId): ?CashlessAccount
{
    return $this->cashlessAccounts()
        ->where('festival_edition_id', $editionId)
        ->first();
}

public function cashlessSales(): HasMany
{
    return $this->hasMany(CashlessSale::class);
}
```

### 9.3 Verificare vârstă pentru produse restricționate

**Flow age-gate la POS:**
```
1. Vendor employee scanează wristband → identifică CashlessAccount → Customer
2. Coșul conține produs cu is_age_restricted = true
3. Sistem verifică:
   a. Customer.date_of_birth != null?
   b. Vârsta >= product.min_age?
   c. Dacă date_of_birth e null → BLOCARE: "Vârsta necunoscută - verificare necesară"
   d. Dacă vârsta < min_age → BLOCARE: "Client minor - produs interzis"
4. Dacă trece verificarea → se marchează Customer.id_verified = true (o singură dată)
5. La tranzacții ulterioare, dacă id_verified = true, verificarea e automată (doar pe date_of_birth)
```

**Enforcement:**
- `SaleService::validateAgeRestrictions()` - verificare înainte de charge
- Logare în `meta` pe `CashlessSale` a flag-ului de verificare vârstă
- Raport: "Tentative de achiziție produse restricționate de către minori"

### 9.4 Corelarea completă a datelor client

Toate entitățile legate de un client per ediție:

```
Customer
├── FestivalPassPurchase (biletul)
│   └── Wristband (brățara fizică)
├── CashlessAccount (contul digital)
│   ├── WristbandTransaction[] (toate tranzacțiile)
│   ├── CashlessSale[] (toate achizițiile)
│   │   └── VendorSaleItem[] (detalii produse)
│   ├── Top-ups (alimentări)
│   └── Cashouts (retrageri)
├── FestivalAddonPurchase[] (add-on-uri: camping, parking)
└── CustomerProfile (profil comportamental)
```

**API endpoint:** `GET /api/cashless/customers/{customerId}/full-profile`
→ Returnează toată structura de mai sus, aggregată per ediție.

---

## 10. Customer Profiling (Profilare Clienți)

### 10.1 Model nou: `CustomerProfile`

Profil comportamental calculat și actualizat continuu pe baza activității.

**Tabel `customer_profiles`:**
```
id                          BIGINT PK AUTO
tenant_id                   BIGINT FK → tenants
festival_edition_id         BIGINT FK → festival_editions
customer_id                 BIGINT FK → customers
cashless_account_id         BIGINT FK → cashless_accounts

-- Demographics
age                         INT NULL
age_group                   VARCHAR(20) NULL
gender                      VARCHAR(20) NULL
city                        VARCHAR(100) NULL
country                     VARCHAR(2) NULL

-- Spending behavior
total_spent_cents           INT DEFAULT 0
total_transactions          INT DEFAULT 0
avg_transaction_cents       INT DEFAULT 0
max_transaction_cents       INT DEFAULT 0
min_transaction_cents       INT DEFAULT 0
total_topped_up_cents       INT DEFAULT 0
total_cashed_out_cents      INT DEFAULT 0
net_spend_cents             INT DEFAULT 0 -- topped_up - cashed_out

-- Product preferences
top_categories              JSON -- [{"category":"Bere","count":15,"total_cents":22500}, ...]
top_products                JSON -- [{"product":"Ursus 500ml","count":10,"total_cents":14990}, ...]
top_vendors                 JSON -- [{"vendor":"Food Corner","count":8,"total_cents":10400}, ...]
product_type_distribution   JSON -- {"food":35,"drink":50,"alcohol":10,"merch":5} (procente)

-- Temporal patterns
first_transaction_at        TIMESTAMP NULL
last_transaction_at         TIMESTAMP NULL
peak_hour                   INT NULL -- ora cu cele mai multe achiziții (0-23)
active_hours                JSON -- [0,0,0,0,0,0,0,0,1,3,5,8,12,15,10,8,6,9,14,18,12,5,2,0]
active_days                 JSON -- {"2026-07-15": 12, "2026-07-16": 8, ...} (nr tranzacții/zi)
avg_time_between_purchases  INT NULL -- minute medie între achiziții

-- Engagement scores
spending_score              INT DEFAULT 0 -- 0-100 (quantile rank)
frequency_score             INT DEFAULT 0 -- 0-100
diversity_score             INT DEFAULT 0 -- 0-100 (cât de variate sunt achizițiile)
overall_score               INT DEFAULT 0 -- 0-100 (compozit)

-- Segments
segment                     VARCHAR(50) NULL -- 'whale','regular','occasional','minimal'
tags                        JSON -- ['beer_lover','night_owl','big_spender','food_explorer']

-- Flags
is_minor                    BOOLEAN DEFAULT false
has_age_restricted_attempts BOOLEAN DEFAULT false
flagged_for_review          BOOLEAN DEFAULT false
flag_reason                 VARCHAR(255) NULL

meta                        JSON NULL
calculated_at               TIMESTAMP NULL
created_at                  TIMESTAMP
updated_at                  TIMESTAMP
```

### 10.2 Segmentare automată

**Segmente pre-definite (calculare automată):**

| Segment | Criterii | Acțiuni |
|---------|----------|---------|
| **Whale** 🐋 | Top 5% spenders, overall_score >= 90 | Oferte VIP, cashback |
| **Regular** | 20-80 percentil, frequency_score >= 50 | Targetare cross-sell |
| **Occasional** | 1-3 tranzacții total | Notificări push cu oferte |
| **Minimal** | 1 tranzacție sau doar top-up | Re-engagement campaign |
| **Minor** | is_minor = true | Restricții alcohol/tobacco |

**Tags auto-generate:**
```php
// Exemple de reguli tag-uri
'beer_lover'     → >50% din achiziții sunt în categoria "Bere"/"Alcohol"
'food_explorer'  → achiziții din 5+ categorii diferite de food
'night_owl'      → >60% din tranzacții după ora 22:00
'early_bird'     → >60% din tranzacții înainte de ora 14:00
'big_spender'    → spending_score >= 80
'social_buyer'   → nr. mare de tranzacții mici (sharing/rounds)
'one_stop_shop'  → >80% din achiziții de la un singur vendor
'variety_seeker' → diversity_score >= 80
```

### 10.3 Job de calculare: `CalculateCustomerProfilesJob`

- Rulează la fiecare **15 minute** în timpul festivalului
- Recalculează profilurile tuturor clienților activi
- Actualizează scoruri, segmente, tag-uri
- Stochează snapshot-ul curent

```php
// Pseudocod simplificat
class CalculateCustomerProfilesJob
{
    public function handle()
    {
        $accounts = CashlessAccount::where('festival_edition_id', $this->editionId)
            ->where('status', 'active')
            ->with('customer')
            ->cursor();

        foreach ($accounts as $account) {
            $sales = CashlessSale::where('cashless_account_id', $account->id)->get();
            $transactions = WristbandTransaction::where('cashless_account_id', $account->id)->get();
            
            $profile = CustomerProfile::updateOrCreate(
                ['cashless_account_id' => $account->id],
                [
                    'total_spent_cents' => $sales->sum('total_cents'),
                    'total_transactions' => $sales->count(),
                    'avg_transaction_cents' => $sales->avg('total_cents'),
                    'top_categories' => $this->calculateTopCategories($sales),
                    'top_products' => $this->calculateTopProducts($sales),
                    'peak_hour' => $this->calculatePeakHour($sales),
                    'active_hours' => $this->calculateActiveHours($sales),
                    'spending_score' => $this->calculateSpendingScore($account),
                    'frequency_score' => $this->calculateFrequencyScore($sales),
                    'diversity_score' => $this->calculateDiversityScore($sales),
                    'segment' => $this->assignSegment($scores),
                    'tags' => $this->generateTags($sales, $scores),
                    'calculated_at' => now(),
                ]
            );
        }
    }
}
```

### 10.4 Enforcement produse adulți

**Reguli enforced la nivel de sistem:**

1. **La POS (charge):** `SaleService` verifică dacă vreun produs din coș e `is_age_restricted`
   - Dacă da → verifică `customer.date_of_birth` → calculează vârsta
   - Minor → BLOCK tranzacția cu mesaj explicit
   - Vârstă necunoscută → BLOCK, necesită verificare manuală
   
2. **La POS, operator override:** Un supervisor/manager poate face override cu motiv (ex: "ID verificat manual"), dar se loghează în audit trail

3. **În app/online:** Produsele age-restricted nu apar în catalog dacă clientul e minor sau vârstă necunoscută

4. **Raport audit:** Toate tentativele blocate + override-urile sunt raportate

---

## 11. Schema Baze de Date - Sumar Complet

### 11.1 Tabele NOI de creat

| # | Tabel | Descriere |
|---|-------|-----------|
| 1 | `cashless_accounts` | Cont digital client per ediție |
| 2 | `cashless_sales` | Vânzare completă (grupare sale items) |
| 3 | `topup_locations` | Locații fizice de top-up |
| 4 | `supplier_brands` | Branduri per supplier |
| 5 | `supplier_products` | Produse din catalogul supplier |
| 6 | `inventory_stocks` | Stocuri la nivel festival + vendor |
| 7 | `inventory_movements` | Mișcări stoc (livrare, alocare, consum, retur) |
| 8 | `finance_fee_rules` | Reguli taxe festival → vendori |
| 9 | `pricing_rules` | Reguli preț impus pe produse |
| 10 | `pricing_rule_components` | Breakdown componentelor de preț |
| 11 | `customer_profiles` | Profil comportamental per client/ediție |
| 12 | `vendor_finance_summaries` | Agregat financiar zilnic per vendor |
| 13 | `cashless_report_snapshots` | Pre-calculări rapoarte |

### 11.2 Tabele EXISTENTE de modificat

| Tabel | Modificări |
|-------|-----------|
| `vendor_employees` | + `full_name`, `email` (unique/vendor), `phone`, `password`, `role` ENUM('manager','supervisor','member'), `email_verified_at` |
| `vendor_products` | + `type`, `unit_measure`, `weight_volume`, `supplier_product_id` FK, `base_price_cents`, `sale_price_cents`, `is_age_restricted`, `min_age`, `sgr_cents`, `vat_rate`, `vat_included`, `sku` |
| `vendor_sale_items` | + `cashless_sale_id` FK, `tax_cents`, `sgr_cents`, `product_type`, `product_category_name` |
| `wristband_transactions` | + `channel`, `topup_method`, `topup_location_id` FK, `cashless_account_id` FK, `balance_snapshot_cents`, `customer_email`, `customer_name`, `cashout_channel`, `cashout_method`, `cashout_reference`, `cashout_processed_at`, `cashout_status` |
| `merchandise_suppliers` | + `company_name`, `reg_com`, `fiscal_address`, `county`, `city`, `country`, `is_vat_payer`, `bank_name`, `iban`, `contract_number`, `contract_start`, `contract_end`, `payment_terms_days`, `status`, `logo_url`, `website` |
| `customers` | + `gender`, `age_group`, `id_verified`, `id_verified_at`, `id_verification_method` |

### 11.3 Enums noi

| Enum | Valori |
|------|--------|
| `VendorUserRole` | `manager`, `supervisor`, `member` |
| `TopUpChannel` | `online`, `physical` |
| `TopUpMethod` | `card`, `cash`, `bank_transfer`, `voucher` |
| `CashoutChannel` | `online`, `physical` |
| `CashoutMethod` | `bank_transfer`, `cash`, `card_refund` |
| `CashoutStatus` | `pending`, `processing`, `completed`, `failed` |
| `FeeType` | `fixed_daily`, `fixed_period`, `percentage_sales`, `fixed_per_transaction`, `percentage_per_category` |
| `PricingComponentType` | `base_price`, `markup_fixed`, `markup_percentage`, `vat`, `sgr`, `eco_tax`, `service_fee`, `custom` |
| `ProductType` | `food`, `drink`, `alcohol`, `tobacco`, `merch`, `service`, `other` |
| `StockMovementType` | `delivery`, `allocation`, `sale`, `return_to_supplier`, `return_to_festival`, `waste`, `correction` |
| `AccountStatus` | `active`, `frozen`, `closed` |
| `SaleStatus` | `completed`, `refunded`, `partial_refund`, `voided` |

---

## 12. Ordine de Implementare (Faze)

### Faza 1: Fundație (Săptămâna 1-2)
1. Migrări DB: tabele noi + modificări pe existente
2. Modele Eloquent noi + relații
3. Enums noi
4. `CashlessAccount` + `CashlessAccountService` (cu row-level locking)
5. Extindere `VendorEmployee` cu roluri noi + autentificare parolă
6. Extindere `VendorProduct` cu câmpuri noi
7. Seed microservice `cashless` în tabela `microservices`
8. Update `TenantType::Festival` → defaultMicroserviceSlugs adaugă `cashless`

### Faza 2: Sales & Top-ups (Săptămâna 3-4)
1. `CashlessSale` model + `SaleService`
2. `TopUpLocation` model + CRUD
3. `TopUpService` (online + fizic)
4. `CashoutService` (parțial + total, digital + fizic)
5. Modificare flow existent Wristband → delegare la CashlessAccount
6. API endpoints noi pentru sales, top-ups, cashouts
7. Verificare vârstă pe produse restricționate

### Faza 3: Suppliers & Stocuri (Săptămâna 5-6)
1. Extindere `MerchandiseSupplier`
2. `SupplierBrand` + `SupplierProduct` models
3. `InventoryStock` + `InventoryMovement` models
4. `SupplierStockService` (flow-uri livrare, alocare, consum, retur)
5. Legătura automată sale → decrementare stoc
6. `ProductImportService` (CSV import)
7. API endpoints suppliers + stocuri

### Faza 4: Finance (Săptămâna 7-8)
1. `FinanceFeeRule` model + `FinanceFeeService`
2. `PricingRule` + `PricingRuleComponent` models + `PricingService`
3. `VendorFinanceSummary` model + job de calcul
4. Enforcement prețuri obligatorii
5. Calcul automat comisioane + fee-uri la fiecare sale
6. API + Filament CRUD pentru rules

### Faza 5: Reports & Dashboards (Săptămâna 9-10)
1. `ReportService` (real-time + aggregate)
2. `cashless_report_snapshots` + job periodic
3. Widget-uri Filament (KPI cards, grafice, tabele)
4. Pagină dedicată rapoarte cu filtre
5. Export CSV/PDF
6. Rapoarte programate (email zilnic/săptămânal)
7. API endpoints rapoarte

### Faza 6: Customer Profiling (Săptămâna 11-12)
1. Extindere `Customer` cu câmpuri noi
2. `CustomerProfile` model
3. `CustomerProfileService` + `CalculateCustomerProfilesJob`
4. Segmentare automată + tag-uri
5. Enforcement age-gate complet
6. Dashboard profil client (Filament)
7. API endpoint profil complet

### Faza 7: Polish & Integrare (Săptămâna 13-14)
1. Teste unitare + feature tests
2. Documentare API (OpenAPI/Swagger)
3. Optimizare query-uri + indexes
4. Cache strategy (Redis) pentru rapoarte real-time
5. Webhook notifications (sale completed, low stock, etc.)
6. Review securitate (SQL injection, auth, rate limiting)

---

## 13. Dependențe și Riscuri

### Dependențe tehnice:
- **Redis** – necesar pentru cache rapoarte real-time și rate limiting
- **Queue worker** – necesar pentru jobs de agregare, export, profiling
- **Stripe/Payment processor** – necesar pentru top-up online
- **DomPDF** (deja instalat) – pentru export PDF rapoarte

### Riscuri:
| Risc | Impact | Mitigare |
|------|--------|----------|
| Concurență pe balance (race conditions) | Pierdere/câștig bani | Row-level locking (`lockForUpdate()`) – deja implementat pe Wristband |
| Volum mare tranzacții (peak hours) | Latență DB | Pre-calculate aggregates, Redis cache, database indexes |
| Offline POS sync conflicts | Tranzacții duplicate/pierdute | `offline_ref` + `is_reconciled` flag, conflict resolution |
| Complexitate pricing rules | Erori de calcul | Teste unitare extensive pe PricingService |
| GDPR date personale clienți | Amenzi | Anonimizare în rapoarte aggregate, data retention policy |

---

*Acest plan acoperă toate cele 10 cerințe specificate. Fiecare secțiune poate fi implementată independent, cu dependențe minime între faze.*
