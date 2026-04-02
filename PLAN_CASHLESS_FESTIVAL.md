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

---

## 14. Portal Vendor (Panou Dedicat Vendori)

### 14.1 De ce e nevoie de un portal separat

Filament Tenant panel este pentru **admin-ul festivalului**. Vendorii (manager, supervisor, member) au nevoie de propriul panou cu acces limitat la datele lor. Opțiuni:

**Recomandare: Filament Panel separat** – `VendorPanel`

```php
// app/Providers/Filament/VendorPanelProvider.php
class VendorPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('vendor')
            ->path('vendor')            // /vendor/login, /vendor/dashboard
            ->login(VendorLogin::class)  // login cu email + parolă VendorEmployee
            ->authGuard('vendor')        // guard separat
            ->tenant(Vendor::class)      // multi-vendor isolation
            ->resources([...])
            ->widgets([...]);
    }
}
```

**Guard nou în `config/auth.php`:**
```php
'guards' => [
    'vendor' => [
        'driver' => 'session',
        'provider' => 'vendor_employees',
    ],
],
'providers' => [
    'vendor_employees' => [
        'driver' => 'eloquent',
        'model' => App\Models\VendorEmployee::class,
    ],
],
```

### 14.2 Ecrane per rol

#### Manager vede:
| Ecran | Descriere |
|-------|-----------|
| **Dashboard** | KPI-uri: vânzări azi, total ediție, top produse, grafic orar |
| **Produse** | CRUD complet + import CSV + vizualizare prețuri impuse |
| **Categorii** | CRUD categorii produse |
| **Staff** | CRUD angajați (supervisor, member) + resetare parolă |
| **Shift-uri** | Vizualizare + management shift-uri |
| **Vânzări** | Lista completă vânzări + filtre + export |
| **Stocuri** | Stoc curent per produs, istoric mișcări |
| **Rapoarte** | Vânzări per produs/categorie/oră/zi/angajat |
| **Finance** | Comisioane, taxe, estimare payout |
| **Profil vendor** | Date companie, setări |

#### Supervisor vede:
| Ecran | Descriere |
|-------|-----------|
| **Dashboard** | KPI-uri simplificate |
| **Shift-uri** | Start/stop shift-uri, management |
| **Vânzări** | Lista vânzări (read-only) |
| **Stocuri** | Vizualizare stoc curent |
| **Rapoarte** | Rapoarte de bază |

#### Member vede:
| Ecran | Descriere |
|-------|-----------|
| **Dashboard** | Vânzări proprii azi |
| **POS** | Interfață de vânzare (scanare wristband + selectare produse) |
| **Shift** | Start/stop shift propriu |

### 14.3 POS Interface (pentru Member/Supervisor)

Ecran dedicat de vânzare optimizat pentru tabletă:
```
┌─────────────────────────────────────────────┐
│  VENDOR POS - Food Corner                    │
├──────────────────────┬──────────────────────┤
│  [Categorii]         │  Coș curent          │
│  ┌──────┐ ┌──────┐  │  ──────────────────  │
│  │ Bere │ │ Food │  │  2x Ursus 500ml  30  │
│  └──────┘ └──────┘  │  1x Hot Dog      13  │
│  ┌──────┐ ┌──────┐  │  ──────────────────  │
│  │Sucuri│ │Desert│  │  TOTAL:        43 RON │
│  └──────┘ └──────┘  │                       │
│                      │  [SCAN WRISTBAND]     │
│  Ursus 500ml   15.00│  [ANULEAZĂ]           │
│  Heineken 330  12.00│                       │
│  Cola 330ml    10.49│                       │
│  ...                 │                       │
└──────────────────────┴──────────────────────┘
```

---

## 15. API Mobile App Client + Notificări

### 15.1 Endpoints client (app/website)

Toate rutele sub prefix `/api/cashless/client/` cu autentificare Sanctum (Customer token).

#### Cont & Sold
```
GET    /client/account                  → sold curent, status cont, wristband info
GET    /client/account/history          → istoric complet tranzacții (paginat)
GET    /client/account/history?type=topup   → doar top-ups
GET    /client/account/history?type=purchase → doar achiziții
GET    /client/account/history?type=cashout  → doar retrageri
```

#### Top-up online
```
POST   /client/topup/initiate          → { amount_cents, payment_method: 'card' }
POST   /client/topup/confirm           → { payment_intent_id } (callback Stripe)
GET    /client/topup/methods           → metode de plată salvate
```

#### Cashout online
```
POST   /client/cashout/request         → { amount_cents, method: 'bank_transfer', iban }
GET    /client/cashout/status/{id}     → status cerere cashout
```

#### Achiziții & Receipts
```
GET    /client/purchases               → lista achiziții (CashlessSales) paginată
GET    /client/purchases/{id}          → detalii achiziție cu items
GET    /client/purchases/{id}/receipt  → receipt digital (JSON sau PDF)
```

#### Profil & Preferințe
```
GET    /client/profile                 → profil complet (date + stats + segment)
PUT    /client/profile                 → update date personale
GET    /client/profile/spending        → breakdown cheltuieli per categorie
GET    /client/profile/badges          → badge-uri/achievements (dacă gamification e activ)
```

#### Vendor Discovery
```
GET    /client/vendors                 → lista vendori cu locație, status, categorii
GET    /client/vendors/{id}/menu       → meniul vendor-ului (produse disponibile)
GET    /client/vendors/{id}/menu?category=Bere → filtru categorie
```

#### Transfer între conturi
```
POST   /client/transfer                → { to_account_number, amount_cents }
```

### 15.2 Notificări

**Model: Folosim sistemul existent Laravel Notifications + canale multiple.**

#### Canale de notificare:
- **Push** (Firebase FCM) – mobil
- **Email** – confirmare tranzacții, receipt
- **SMS** (SendSMS.ro – deja integrat) – OTP, confirmare top-up mare
- **In-app** (database notifications) – vizibile în app

#### Notificări Client:

| Trigger | Canal | Mesaj |
|---------|-------|-------|
| Top-up reușit | Push + Email | "Ai alimentat contul cu {sumă}. Sold: {sold}" |
| Achiziție | Push | "Achiziție {sumă} la {vendor}. Sold: {sold}" |
| Cashout aprobat | Push + Email | "Retragere {sumă} procesată. Transfer în 1-3 zile." |
| Sold scăzut (<20 RON) | Push | "Sold scăzut: {sold}. Alimentează contul." |
| Receipt disponibil | Email | Receipt digital PDF atașat |
| Transfer primit | Push | "{nume} ți-a transferat {sumă}. Sold: {sold}" |
| Cont activat | Push + Email | "Contul cashless e activ! Alimentează pentru a cumpăra." |
| End of festival reminder | Push + Email | "Festivalul se termină mâine. Sold neretras: {sold}." |
| Auto-cashout completat | Email | "Soldul rămas de {sumă} a fost returnat pe cardul tău." |

#### Notificări Vendor (către Manager/Supervisor):

| Trigger | Canal | Mesaj |
|---------|-------|-------|
| Stoc scăzut (<20% rămas) | Push + Email | "Stoc scăzut: {produs} - {cantitate} rămase" |
| Stoc epuizat | Push + Email | "STOC EPUIZAT: {produs}!" |
| Raport zilnic | Email | Sumar vânzări + top produse + stocuri |
| Shift neterminat | Push | "Shift-ul lui {angajat} a depășit 12h fără închidere" |
| Alocație primită | Push | "Ai primit {cantitate} x {produs} de la festival" |

#### Notificări Admin Festival:

| Trigger | Canal | Mesaj |
|---------|-------|-------|
| Alertă fraud | Push + Email | "Activitate suspectă pe wristband {uid}: {detalii}" |
| POS offline >30min | Push | "POS {device} al {vendor} e offline de {minute} min" |
| Volum tranzacții scăzut brusc | Push | "Volum tranzacții -50% față de aceeași oră ieri" |
| Threshold revenue | Email | "Revenue a depășit {sumă} pentru ediția curentă!" |
| Cashout mare solicitat | Push | "Cashout de {sumă} solicitat de {client}" |
| Reconciliere completă | Email | "Reconciliere offline: {N} tranzacții procesate, {M} conflicte" |

### 15.3 Configurare notificări

**Tabel `cashless_notification_preferences`:**
```
id                      BIGINT PK AUTO
tenant_id               BIGINT FK
festival_edition_id     BIGINT FK
notifiable_type         VARCHAR(50) -- 'customer', 'vendor_employee', 'user'
notifiable_id           BIGINT
channel                 ENUM('push','email','sms','in_app')
notification_type       VARCHAR(100) -- 'topup_success', 'low_balance', etc.
is_enabled              BOOLEAN DEFAULT true
threshold_value         INT NULL -- ex: low_balance threshold = 2000 (20 RON)
created_at              TIMESTAMP
updated_at              TIMESTAMP
```

Clienții pot dezactiva anumite notificări din app (ex: dezactivează push la fiecare achiziție, păstrează doar sold scăzut).

---

## 16. Cashless Settings per Ediție + Voucher/Promo Credits

### 16.1 Cashless Settings centralizate

Configurări per ediție de festival, stocate în `FestivalEdition.settings` JSON sau într-un model dedicat.

**Recomandare: model dedicat `CashlessSettings`**

**Tabel `cashless_settings`:**
```
id                          BIGINT PK AUTO
tenant_id                   BIGINT FK
festival_edition_id         BIGINT FK UNIQUE

-- Top-up limits
min_topup_cents             INT DEFAULT 1000 -- minim 10 RON
max_topup_cents             INT DEFAULT 100000 -- maxim 1000 RON per tranzacție
max_balance_cents           INT DEFAULT 500000 -- sold maxim 5000 RON
daily_topup_limit_cents     INT NULL -- limită zilnică per client (NULL = fără limită)

-- Cashout settings
allow_online_cashout        BOOLEAN DEFAULT true
allow_physical_cashout      BOOLEAN DEFAULT true
min_cashout_cents           INT DEFAULT 100 -- minim 1 RON
cashout_fee_cents           INT DEFAULT 0 -- taxă fixă per cashout
cashout_fee_percentage      DECIMAL(5,2) DEFAULT 0 -- taxă procentuală
auto_cashout_after_festival BOOLEAN DEFAULT true -- returnare automată sold la final
auto_cashout_delay_days     INT DEFAULT 7 -- zile după final festival
auto_cashout_method         ENUM('bank_transfer','card_refund') DEFAULT 'bank_transfer'

-- Transfer settings
allow_account_transfer      BOOLEAN DEFAULT true
max_transfer_cents          INT NULL -- limită per transfer
transfer_fee_cents          INT DEFAULT 0

-- POS settings
require_pin_above_cents     INT NULL -- cere PIN pentru tranzacții > X (NULL = niciodată)
max_charge_cents            INT DEFAULT 200000 -- maxim 2000 RON per tranzacție POS
charge_cooldown_seconds     INT DEFAULT 10 -- cooldown între charges pe același cont

-- Age verification
enforce_age_verification    BOOLEAN DEFAULT true
age_verification_method     ENUM('date_of_birth','manual_id','both') DEFAULT 'date_of_birth'

-- Currency & display
currency                    VARCHAR(3) DEFAULT 'RON'
currency_symbol             VARCHAR(5) DEFAULT 'RON'
display_decimals            INT DEFAULT 2

-- Notifications
low_balance_threshold_cents INT DEFAULT 2000 -- notificare sold scăzut sub 20 RON
send_receipt_on_purchase    BOOLEAN DEFAULT true
send_daily_summary          BOOLEAN DEFAULT false

meta                        JSON NULL
created_at                  TIMESTAMP
updated_at                  TIMESTAMP
```

### 16.2 Voucher/Promo Credits

Coduri promoționale care adaugă sold gratuit în contul cashless. Exemple:
- Sponsor oferă 20 RON gratis primilor 1000 clienți
- Cod de compensare pentru probleme tehnice
- Promoție: top-up 100 RON, primești 10 RON bonus

**Tabel `cashless_vouchers`:**
```
id                      BIGINT PK AUTO
tenant_id               BIGINT FK
festival_edition_id     BIGINT FK
code                    VARCHAR(50) UNIQUE -- "SPONSOR20", "BONUS10"
name                    VARCHAR(255) -- "Voucher Coca-Cola 20 RON"
voucher_type            ENUM('fixed_credit','percentage_bonus','topup_bonus')
amount_cents            INT NULL -- pentru fixed_credit (ex: 2000 = 20 RON gratis)
bonus_percentage        DECIMAL(5,2) NULL -- pentru percentage_bonus/topup_bonus
min_topup_cents         INT NULL -- minim top-up pentru a primi bonusul
max_bonus_cents         INT NULL -- plafonare bonus (ex: max 50 RON bonus)
sponsor_name            VARCHAR(255) NULL -- "Coca-Cola"
total_budget_cents      INT NULL -- buget total (NULL = nelimitat)
used_budget_cents       INT DEFAULT 0 -- cât s-a consumat
max_redemptions         INT NULL -- nr. maxim utilizări totale (NULL = nelimitat)
current_redemptions     INT DEFAULT 0
max_per_customer        INT DEFAULT 1 -- câte ori poate folosi un client
valid_from              TIMESTAMP NULL
valid_until             TIMESTAMP NULL
is_active               BOOLEAN DEFAULT true
meta                    JSON NULL
created_at              TIMESTAMP
updated_at              TIMESTAMP
```

**Tabel `cashless_voucher_redemptions`:**
```
id                      BIGINT PK AUTO
cashless_voucher_id     BIGINT FK
cashless_account_id     BIGINT FK
customer_id             BIGINT FK
amount_cents            INT -- sumă creditată
wristband_transaction_id BIGINT FK -- tranzacția de tip 'voucher_credit'
redeemed_at             TIMESTAMP
meta                    JSON NULL
created_at              TIMESTAMP
```

**Flow-uri voucher:**

**A. Fixed credit (cod = bani gratis):**
```
1. Client introduce cod "SPONSOR20" în app sau la stand
2. Sistem verifică: cod valid, nefolosit de client, în buget, în perioadă
3. Creditare 20 RON → CashlessAccount.balance += 2000
4. WristbandTransaction cu transaction_type = 'voucher_credit'
5. Incrementare: voucher.current_redemptions++, voucher.used_budget_cents += 2000
```

**B. Top-up bonus (top-up X, primești Y% bonus):**
```
1. Client face top-up de 100 RON + introduce cod "BONUS10"
2. Sistem verifică codul → bonus_percentage = 10%
3. Se procesează top-up-ul normal: +100 RON
4. Se procesează bonus: +10 RON (transaction_type = 'voucher_credit')
5. Sold final: 110 RON
```

**Enum nouă - extindere `transaction_type` pe WristbandTransaction:**
```
Valori existente: topup, payment, refund, correction, transfer_in, transfer_out, cashout
Valori noi:       voucher_credit, promotional_credit, compensation_credit
```

---

## 17. Migrare Wristband → CashlessAccount (Backwards Compatibility)

### 17.1 Strategia de migrare

Flow-ul existent (`Wristband::topUp()`, `Wristband::charge()`, etc.) funcționează și trebuie păstrat. `CashlessAccount` devine layer-ul superior.

**Principiu: CashlessAccount = source of truth, Wristband = dispozitiv fizic sync.**

```
ÎNAINTE (existent):
  Client → Wristband → balance_cents
  POS → Wristband::charge()

DUPĂ (nou):
  Client → CashlessAccount → balance_cents (source of truth)
                ↕ sync
            Wristband → balance_cents (mirror/cache)
  POS → CashlessAccountService::charge() → actualizează ambele
```

### 17.2 Plan de migrare în 3 pași

**Pasul 1: Creare CashlessAccount pentru wristbands existente**
```php
// Migration job (one-time)
Wristband::whereNotNull('customer_id')->each(function ($wristband) {
    CashlessAccount::firstOrCreate(
        [
            'customer_id' => $wristband->customer_id,
            'festival_edition_id' => $wristband->festival_edition_id,
        ],
        [
            'tenant_id' => $wristband->tenant_id,
            'wristband_id' => $wristband->id,
            'balance_cents' => $wristband->balance_cents,
            'currency' => $wristband->currency,
            'status' => $wristband->isActive() ? 'active' : 'closed',
            'activated_at' => $wristband->activated_at,
        ]
    );
});
```

**Pasul 2: Wrapper pe metodele Wristband existente**
```php
// Wristband::topUp() devine wrapper:
public function topUp(int $amountCents, ...): WristbandTransaction
{
    if ($this->cashlessAccount) {
        // Delegare la CashlessAccountService (noul flow)
        return app(CashlessAccountService::class)->topUp(
            $this->cashlessAccount, $amountCents, ...
        );
    }
    // Fallback: flow vechi (pentru wristbands fără CashlessAccount)
    return $this->legacyTopUp($amountCents, ...);
}
```

**Pasul 3: Deprecare metode directe pe Wristband**
- Toate apelurile noi trec prin `CashlessAccountService`
- API-urile existente (`/festival/wristbands/{uid}/topup`) rămân funcționale, dar intern delegă la `CashlessAccountService`
- Noi API-uri (`/cashless/accounts/{id}/topup`) sunt canonical

### 17.3 API backwards compatibility

```
EXISTENT (rămâne funcțional, intern delegă):
POST /api/festival/wristbands/{uid}/topup
POST /api/festival/wristbands/{uid}/charge

NOU (canonical):
POST /api/cashless/accounts/{id}/topup
POST /api/cashless/accounts/{id}/charge
POST /api/cashless/client/topup/initiate    (client app)
```

---

## 18. Refund Flow Detaliat

### 18.1 Tipuri de refund

| Tip | Inițiat de | Aprobare necesară | Efect |
|-----|-----------|-------------------|-------|
| **Refund complet** | Vendor employee | Da (supervisor/manager) | Returnare 100% din CashlessSale |
| **Refund parțial** | Vendor employee | Da (supervisor/manager) | Returnare 1+ items din CashlessSale |
| **Refund automat** | Sistem | Nu | Produse out-of-stock după plată |
| **Compensație** | Admin festival | Nu | Credit de compensare (nu legat de sale) |

### 18.2 Model nou: `CashlessRefund`

**Tabel `cashless_refunds`:**
```
id                      BIGINT PK AUTO
tenant_id               BIGINT FK
festival_edition_id     BIGINT FK
cashless_sale_id        BIGINT FK → cashless_sales
cashless_account_id     BIGINT FK → cashless_accounts
customer_id             BIGINT FK
vendor_id               BIGINT FK
refund_type             ENUM('full','partial','auto','compensation')
status                  ENUM('pending','approved','rejected','processed','cancelled')
requested_by_employee_id BIGINT FK → vendor_employees NULL
approved_by_employee_id  BIGINT FK → vendor_employees NULL
requested_at            TIMESTAMP
approved_at             TIMESTAMP NULL
processed_at            TIMESTAMP NULL
rejected_at             TIMESTAMP NULL
rejection_reason        TEXT NULL
total_refund_cents      INT
currency                VARCHAR(3)
wristband_transaction_id BIGINT FK NULL -- tranzacția de refund (după procesare)
reason                  TEXT -- motivul refund-ului
items                   JSON -- [{"vendor_sale_item_id": 5, "quantity": 1, "amount_cents": 1499}]
meta                    JSON NULL
created_at              TIMESTAMP
updated_at              TIMESTAMP
```

### 18.3 Flow refund cu aprobare

```
1. MEMBER/SUPERVISOR solicită refund:
   POST /api/cashless/refunds
   {
     "cashless_sale_id": 1234,
     "refund_type": "partial",
     "items": [{"vendor_sale_item_id": 5, "quantity": 1}],
     "reason": "Client a primit produs greșit"
   }
   → Se creează CashlessRefund cu status = 'pending'
   → Notificare push la manager/supervisor

2. MANAGER/SUPERVISOR aprobă:
   POST /api/cashless/refunds/{id}/approve
   → status = 'approved'
   → CashlessAccountService::refund() procesează refund-ul:
     - Lock CashlessAccount
     - Creditare balance
     - Creare WristbandTransaction (transaction_type = 'refund')
     - Update CashlessSale.status = 'partial_refund' sau 'refunded'
   → status = 'processed'
   → Notificare client: "Ai primit refund {sumă}. Sold: {sold}"

3. Sau MANAGER respinge:
   POST /api/cashless/refunds/{id}/reject
   { "rejection_reason": "Produsul a fost consumat" }
   → status = 'rejected'
   → Notificare employee: "Refund respins: {motiv}"
```

### 18.4 Reguli business refund

- Refund maxim = suma tranzacției originale
- Refund doar în aceeași ediție (nu cross-edition)
- Timp maxim de refund configurabil în `CashlessSettings` (ex: 2 ore de la achiziție)
- Member poate solicita, dar nu poate aproba (separation of duties)
- Manager poate și solicita și aproba (bypass approval pt urgențe)
- Toate refund-urile sunt logate în audit trail

---

## 19. Audit Trail

### 19.1 Ce se loghează

Folosim **Spatie Activity Log** (deja instalat) cu log name `cashless`.

| Entitate | Evenimente logate | Detalii extra |
|----------|-------------------|---------------|
| `CashlessAccount` | created, updated (balance changes) | balance_before, balance_after, trigger (topup/charge/refund/etc.) |
| `CashlessSale` | created, refunded | items, amounts, vendor, employee |
| `CashlessRefund` | created, approved, rejected, processed | who requested, who approved/rejected |
| `WristbandTransaction` | created | toate câmpurile (immutable - nu se editează niciodată) |
| `VendorProduct` | created, updated, deleted | price changes, availability changes |
| `InventoryMovement` | created | toate mișcările de stoc |
| `FinanceFeeRule` | created, updated, deleted | cine a modificat ce regulă |
| `PricingRule` | created, updated, deleted | modificări prețuri impuse |
| `VendorEmployee` | created, updated, deleted | role changes, permission changes |
| `CashlessSettings` | updated | orice modificare de configurare |
| `CashlessVoucher` | created, redeemed, deactivated | utilizări voucher |

### 19.2 Implementare pe modele

```php
// Exemplu pe CashlessAccount
class CashlessAccount extends Model
{
    use LogsActivity;
    
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['balance_cents', 'status'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn (string $eventName) => "Cashless account {$eventName}")
            ->useLogName('cashless');
    }

    public function tapActivity(Activity $activity, string $eventName)
    {
        $activity->properties = $activity->properties->merge([
            'tenant_id' => $this->tenant_id,
            'festival_edition_id' => $this->festival_edition_id,
            'customer_id' => $this->customer_id,
        ]);
    }
}
```

### 19.3 Vizualizare audit

- **Filament:** pagină dedicată Audit Log cu filtre pe entitate, user, perioadă
- **API:** `GET /api/cashless/audit?entity=cashless_account&entity_id=123`
- **Export:** CSV/PDF pentru audit extern
- **Retenție:** configurable, default 2 ani (GDPR compliant)

---

## 20. Reconciliere Tranzacții Offline

### 20.1 Problema

POS-urile pot funcționa offline (fără internet). Tranzacțiile se salvează local pe dispozitiv și se sincronizează când revine conexiunea. Pot apărea:
- **Duplicate** – aceeași tranzacție trimisă de 2 ori
- **Conflicte de balance** – soldul pe server diferă de soldul offline
- **Ordine greșită** – tranzacții ajung în ordine inversă
- **Gap-uri** – tranzacții pierdute

### 20.2 Mecanismul de sync (existent + îmbunătățiri)

**Câmpuri existente pe `WristbandTransaction`:**
- `sync_source` – 'online' sau 'offline_sync'
- `offline_ref` – referință unică generată de POS offline
- `offline_transacted_at` – timestamp-ul real al tranzacției (pe POS)
- `is_reconciled` – flag de reconciliere

**Câmpuri noi:**
```
+ reconciliation_batch_id   VARCHAR(50) NULL -- ID batch de sync
+ reconciliation_status     ENUM('pending','matched','conflict','resolved','rejected') NULL
+ reconciliation_notes      TEXT NULL
+ server_balance_at_sync    INT NULL -- ce sold avea serverul când a primit sync
+ device_balance_at_sync    INT NULL -- ce sold credea device-ul
```

### 20.3 Flow de reconciliere

**Etapa 1: Sync automat (NfcSyncService – existent, extins)**
```
1. POS trimite batch de tranzacții offline:
   POST /api/cashless/sync-offline
   { "device_uid": "POS-001", "transactions": [...], "batch_id": "BATCH-xxx" }

2. Pentru fiecare tranzacție:
   a. Check offline_ref → dacă există deja → marcare 'duplicate', skip
   b. Validare: wristband_id valid, vendor_id valid, amounts pozitive
   c. Replay tranzacție pe server:
      - Lock CashlessAccount
      - Verificare sold suficient (la momentul offline_transacted_at)
      - Aplicare tranzacție
      - Salvare cu sync_source = 'offline_sync'
   d. Dacă sold insuficient → marcare conflict
```

**Etapa 2: Conflict resolution**
```
Conflicte posibile:
A. Sold insuficient la replay:
   - Cauza: client a cheltuit între timp de la alt POS/online
   - Rezolvare automată: permite sold negativ temporar + alert admin
   
B. Wristband disabled pe server dar tranzacție offline:
   - Cauza: wristband raportată pierdută dar client a plătit offline înainte
   - Rezolvare: admin decide manual (approve/reject)

C. Duplicate (offline_ref identic):
   - Cauza: retry de sync
   - Rezolvare automată: skip, marcare 'duplicate'
```

**Etapa 3: Reconciliation report**

**Tabel `reconciliation_batches`:**
```
id                      BIGINT PK AUTO
tenant_id               BIGINT FK
festival_edition_id     BIGINT FK
batch_id                VARCHAR(50) UNIQUE
device_uid              VARCHAR(100)
vendor_id               BIGINT FK NULL
received_at             TIMESTAMP
total_transactions      INT
matched_count           INT DEFAULT 0
conflict_count          INT DEFAULT 0
duplicate_count         INT DEFAULT 0
rejected_count          INT DEFAULT 0
status                  ENUM('processing','completed','needs_review')
balance_discrepancy_cents INT DEFAULT 0 -- diferența totală de sold
notes                   TEXT NULL
reviewed_by             BIGINT FK NULL
reviewed_at             TIMESTAMP NULL
meta                    JSON NULL
created_at              TIMESTAMP
updated_at              TIMESTAMP
```

### 20.4 Dashboard reconciliere (Filament)

- **Status global:** X tranzacții nereconciliate, Y conflicte nerezolvate
- **Per device:** ultima sincronizare, nr. tranzacții offline pending
- **Per batch:** detalii batch cu status fiecare tranzacție
- **Acțiuni admin:** approve/reject conflicte individual sau batch
- **Alertă:** device-uri care nu au sincronizat de >1 oră

---

## 21. Transfer între Conturi (Account-to-Account)

### 21.1 Flow

Diferit de transfer wristband-to-wristband (care transferă TOT soldul). Account transfer permite sume specifice.

```
1. Client A trimite 50 RON lui Client B:
   POST /api/cashless/client/transfer
   { "to_account_number": "CA-XXXXXXXXX", "amount_cents": 5000 }

2. Validări:
   - Cont sursă activ, sold suficient
   - Cont destinație activ, aceeași ediție festival
   - Sumă în limita max_transfer_cents din CashlessSettings
   - Cont sursă != cont destinație

3. Procesare atomică (DB::transaction + lockForUpdate):
   a. Debitare cont A: balance -= 5000
   b. Creditare cont B: balance += 5000
   c. WristbandTransaction A: type='transfer_out', amount=5000
   d. WristbandTransaction B: type='transfer_in', amount=5000
   e. Dacă au wristbands → sync balances

4. Notificări:
   - Client A: "Ai transferat 50 RON către {nume}. Sold: {sold}"
   - Client B: "{nume} ți-a transferat 50 RON. Sold: {sold}"
```

### 21.2 Limitări de securitate

- Max X transferuri pe zi per cont (configurabil)
- Sumă maximă per transfer
- Cooldown între transferuri (ex: 1 minut)
- Flag automat dacă un cont primește > 5 transferuri în < 1 oră (posibil fraud)

---

## 22. Actualizare Ordine Implementare

Cu secțiunile noi, fazele devin:

### Faza 1: Fundație + Settings (Săptămâna 1-2)
- Migrări DB, modele noi, enums
- CashlessAccount + CashlessSettings
- Migrare wristbands existente → CashlessAccount (secțiunea 17)
- VendorEmployee roles + auth guard vendor

### Faza 2: Core Operations (Săptămâna 3-4)
- CashlessSale + SaleService
- TopUpService (online + fizic) + TopUpLocation
- CashoutService (parțial, digital/fizic)
- Account-to-account transfer
- Age verification enforcement

### Faza 3: Vendor Portal (Săptămâna 5-6)
- Filament VendorPanel setup + auth guard
- Ecrane per rol (manager/supervisor/member)
- POS interface
- CSV import produse

### Faza 4: Suppliers & Stocuri (Săptămâna 7-8)
- Supplier complet + brands + products
- InventoryStock + InventoryMovement
- Flow-uri stoc (livrare → alocare → consum → retur)
- Auto-decrementare stoc la vânzare

### Faza 5: Finance + Pricing (Săptămâna 9-10)
- FinanceFeeRule + PricingRule + components
- Enforcement prețuri obligatorii
- VendorFinanceSummary + calcul automat
- Refund flow cu aprobare (secțiunea 18)

### Faza 6: Reports + Dashboard (Săptămâna 11-12)
- ReportService (50+ rapoarte)
- Widget-uri Filament dashboard
- Export CSV/PDF
- Rapoarte programate

### Faza 7: Profiling + Notifications (Săptămâna 13-14)
- CustomerProfile + job de calculare
- Segmentare automată + tags
- Vouchers/promo credits
- Sistem notificări (push/email/sms)
- Preferințe notificări per client

### Faza 8: Mobile API + Polish (Săptămâna 15-16)
- API client mobile complet
- Receipts digitale
- Audit trail complet
- Reconciliere offline (secțiunea 20)
- Teste, documentare API, optimizare

---

## 23. Tabele Noi - Sumar Final Complet

| # | Tabel | Secțiune |
|---|-------|----------|
| 1 | `cashless_accounts` | 5.1 |
| 2 | `cashless_sales` | 4.1 |
| 3 | `cashless_refunds` | 18.2 |
| 4 | `topup_locations` | 5.2 |
| 5 | `supplier_brands` | 7.2 |
| 6 | `supplier_products` | 7.3 |
| 7 | `inventory_stocks` | 7.4 |
| 8 | `inventory_movements` | 7.4 |
| 9 | `finance_fee_rules` | 8.1 |
| 10 | `pricing_rules` | 8.2 |
| 11 | `pricing_rule_components` | 8.2 |
| 12 | `customer_profiles` | 10.1 |
| 13 | `vendor_finance_summaries` | 8.6 |
| 14 | `cashless_report_snapshots` | 6.1 |
| 15 | `cashless_settings` | 16.1 |
| 16 | `cashless_vouchers` | 16.2 |
| 17 | `cashless_voucher_redemptions` | 16.2 |
| 18 | `cashless_notification_preferences` | 15.3 |
| 19 | `reconciliation_batches` | 20.3 |
| 20 | `cashless_disputes` | 24.1 |
| 21 | `cashless_webhook_endpoints` | 25.1 |
| 22 | `cashless_webhook_deliveries` | 25.1 |
| 23 | `cashless_exchange_rates` | 26.1 |
| 24 | `cashless_spending_limits` | 28.1 |
| 25 | `cashless_gdpr_requests` | 30.3 |
| 26 | `cashless_batch_jobs` | 31.1 |
| 27 | `participant_location_events` | 33.3 |
| 28 | `heatmap_snapshots` | 33.4 |

### Tabele existente modificate:

| # | Tabel | Secțiune | Câmpuri noi |
|---|-------|----------|-------------|
| 1 | `vendor_employees` | 3.1 | full_name, email, phone, password, role enum, email_verified_at |
| 2 | `vendor_products` | 3.2 | type, unit_measure, weight_volume, supplier_product_id, base/sale_price, is_age_restricted, min_age, sgr, vat |
| 3 | `vendor_sale_items` | 4.2 | cashless_sale_id, tax_cents, sgr_cents, product_type, product_category_name |
| 4 | `wristband_transactions` | 5.3/5.5 | channel, topup_method, topup_location_id, cashless_account_id, balance_snapshot, cashout fields, reconciliation fields |
| 5 | `merchandise_suppliers` | 7.1 | company details, fiscal data, contract, banking, status |
| 6 | `customers` | 9.2 | gender, age_group, id_verified, id_verified_at, id_verification_method |
| 7 | `cashless_sales` | 27.1 | tip_cents, tip_percentage, total_with_tip_cents |
| 8 | `cashless_settings` | 16.1/26.1/27.1/29.2 | multi-currency, tipping, rate limiting config |
| 9 | `lost_and_found` | 32.2 | festival_edition_id, wristband_id, cashless_account_id, vendor_id, zone, urgency |
| 10 | `festival_points_of_interest` | 33.2 | vendor_id, topup_location_id, capacity, occupancy, polygon, live_status |
| 11 | `festival_maps` | 33.2 | festival_edition_id, map_type, tile_url, zoom config, heatmap/tracking flags |

**Total: 28 tabele noi + 11 tabele modificate + 14+ enums**

---

## Ordine Implementare Finală (10 faze)

### Faza 1: Fundație + Settings (Săptămâna 1-2)
- Migrări DB, modele noi, enums
- CashlessAccount + CashlessSettings
- Migrare wristbands existente → CashlessAccount (§17)
- VendorEmployee roles + auth guard vendor

### Faza 2: Core Operations (Săptămâna 3-4)
- CashlessSale + SaleService
- TopUpService (online + fizic) + TopUpLocation
- CashoutService (parțial, digital/fizic)
- Account-to-account transfer (§21)
- Age verification enforcement
- Tipping (§27)

### Faza 3: Vendor Portal (Săptămâna 5-6)
- Filament VendorPanel setup + auth guard
- Ecrane per rol (manager/supervisor/member)
- POS interface
- CSV import produse

### Faza 4: Suppliers & Stocuri (Săptămâna 7-8)
- Supplier complet + brands + products
- InventoryStock + InventoryMovement
- Flow-uri stoc (livrare → alocare → consum → retur)
- Auto-decrementare stoc la vânzare

### Faza 5: Finance + Pricing + Refunds (Săptămâna 9-10)
- FinanceFeeRule + PricingRule + components
- Enforcement prețuri obligatorii
- VendorFinanceSummary + calcul automat
- Refund flow cu aprobare (§18)
- Multi-currency support (§26)

### Faza 6: Reports + Dashboard (Săptămâna 11-13)
- ReportService (50+ rapoarte standard, §6)
- Widget-uri Filament dashboard
- Export CSV/PDF
- Rapoarte programate
- Rapoarte avansate: basket analysis, cohort, weather correlation (§34)

### Faza 7: Map + Heatmaps + Predictions (Săptămâna 14-15)
- Extindere FestivalMap + POI cu vendor linking (§33)
- Live participant tracking (event collection)
- Heatmap engine (aggregation + Redis + display)
- Crowd management dashboard
- PredictionService + AnomalyDetectionService (§34)

### Faza 8: Profiling + Notifications (Săptămâna 16-17)
- CustomerProfile + job de calculare
- Segmentare automată + tags
- Vouchers/promo credits (§16)
- Spending limits parentale (§28)
- Sistem notificări (push/email/sms) + preferințe

### Faza 9: Integrations + Operations (Săptămâna 18-19)
- Mobile API client complet (§15)
- Receipts digitale
- Webhooks externe (§25)
- Dispute resolution (§24)
- Lost & Found integrare cashless (§32)
- Reconciliere offline detaliată (§20)
- Batch operations + progress tracking (§31)

### Faza 10: Polish + Compliance (Săptămâna 20)
- Audit trail complet (§19)
- GDPR compliance (§30)
- Rate limiting granular (§29)
- Teste unitare + feature + load (§31)
- Documentare API (OpenAPI/Swagger)
- Security review
- Performance optimization (indexes, Redis cache, query optimization)

---

### Tabele noi adiționale (secțiunile 35-38):

| # | Tabel | Secțiune |
|---|-------|----------|
| 29 | `cashless_combos` | 35.2 |
| 30 | `cashless_combo_items` | 35.2 |
| 31 | `cashless_sale_splits` | 36.3 |

### Tabele existente modificate adițional:

| Tabel | Secțiune | Câmpuri noi |
|-------|----------|-------------|
| `cashless_sales` | 35/36 | tip_cents, tip_percentage, total_with_tip_cents, is_split_payment, split_count, split_method |
| `cashless_settings` | 35/36/37 | tipping config, split_max_participants, offline POS config |

**Total final: 31 tabele noi + 12 tabele modificate + 14+ enums**

---

*Plan complet: 40 secțiuni acoperind 10 cerințe originale + 19 îmbunătățiri. 34 tabele noi, 13 modificate, 14+ enums. 10 faze de implementare, ~20 săptămâni. 70+ rapoarte inclusiv predictive. Live tracking + heatmaps. Anomaly detection. Offline-first POS architecture. SLA targets definite.*

---

## 41. Decizii de Produs & Clarificări

Răspunsuri confirmate care influențează design-ul și trebuie respectate la implementare.

### A. Cont Cashless & Activare

| Decizie | Impact pe design |
|---------|-----------------|
| **Cont cashless FĂRĂ bilet** – staff, VIP, sponsori pot avea cont cashless fără FestivalPassPurchase | `CashlessAccount.festival_pass_purchase_id` rămâne NULL. Trebuie un flow de creare cont fără pass: admin creează manual sau prin invitație. Câmp nou: `account_origin ENUM('pass_purchase','staff','vip_invite','sponsor','manual')` |
| **Pre-top-up înainte de festival** – clientul poate alimenta contul cu oricât timp înainte | `CashlessAccount` se creează la momentul cumpărării biletului (nu la check-in). Flow: buy pass → creare cont automat → top-up imediat disponibil. Check-in-ul doar activează wristband-ul fizic (dacă e cazul) |

### B. Vendori & Standuri

| Decizie | Impact pe design |
|---------|-----------------|
| **Admin festival creează vendor + primul manager** | Flow Filament: admin creează Vendor → creează primul VendorEmployee cu rol manager → manager primește email de activare. Manager-ul poate apoi crea supervisori/members |
| **Vendor cross-festival (multi-tenant)** | Vendor-ul are `tenant_id` per festival (entitate separată per tenant), dar poate exista un mecanism de "import vendor" care clonează datele companiei de la un alt tenant. Sau: vendor global cu pivot per tenant. **Recomandare:** păstrăm vendor per tenant (simplu), cu posibilitate de "copiere date companie" dintr-un vendor existent |

### C. Top-up & Cashout

| Decizie | Impact pe design |
|---------|-----------------|
| **Sumă liberă top-up** (nu butoane predefinite) | UI: input numeric cu min/max din CashlessSettings. Butoane sugerate opțional (UX) dar nu obligatorii |
| **Cashout inițiat de client, nu automat** | Eliminăm `auto_cashout_after_festival` din CashlessSettings. Clientul inițiază explicit. Retur pe aceeași cale: card→card refund, cash→cash la stand |
| **Operator top-up poate adăuga SAU scădea bani** | Operatorul de la punctul fizic poate face și top-up și cashout. Confirmă nevoia de `CashlessAccountService::cashout()` parțial la stand fizic. Câmp pe VendorEmployee sau rol separat: `topup_operator` cu permisiuni de credit/debit |

### D. Finance

| Decizie | Impact pe design |
|---------|-----------------|
| **Payout vendori la final festival, prin transfer bancar** | Nu se integrează Stripe Connect pentru payouts (momentan). Se generează un raport de payout per vendor cu suma datorată + detalii IBAN. Admin festival confirmă manual fiecare transfer. Tabel: `vendor_payouts` cu status tracking (pending→approved→transferred→confirmed) |

**Tabel nou: `vendor_payouts`:**
```
id                      BIGINT PK AUTO
tenant_id               BIGINT FK
festival_edition_id     BIGINT FK
vendor_id               BIGINT FK
gross_sales_cents       INT
commissions_cents       INT
fees_cents              INT
refunds_cents           INT
tips_cents              INT -- tips merg integral la vendor
net_payout_cents        INT -- ce primește vendor-ul
currency                VARCHAR(3)
vendor_iban             VARCHAR(50) -- snapshot IBAN la momentul generării
vendor_bank             VARCHAR(255) NULL
vendor_cui              VARCHAR(20)
status                  ENUM('draft','approved','transferred','confirmed','disputed')
approved_by             BIGINT FK → users NULL
approved_at             TIMESTAMP NULL
transferred_at          TIMESTAMP NULL
transfer_reference      VARCHAR(255) NULL -- ref transfer bancar
confirmed_at            TIMESTAMP NULL
notes                   TEXT NULL
meta                    JSON NULL
created_at              TIMESTAMP
updated_at              TIMESTAMP
```

### E. Suppliers & Stoc

| Decizie | Impact pe design |
|---------|-----------------|
| **Produse proprii vendor (fără supplier)** – cu tracking stoc opțional | `VendorProduct.supplier_product_id` = NULL pentru produse proprii. Vendor-ul poate seta un `initial_stock` opțional. Dacă setat → se creează `InventoryStock` cu `vendor_id` setat și `supplier_product_id` NULL. Tracking stoc funcționează la fel (decrementare la vânzare). Dacă `initial_stock` nu e setat → fără tracking stoc pe acel produs |

**Câmp nou pe `VendorProduct`:**
```
+ has_stock_tracking     BOOLEAN DEFAULT false
+ initial_stock_quantity DECIMAL(12,3) NULL
```

**Câmp nou pe `InventoryStock`:**
```
supplier_product_id     BIGINT FK NULL -- NULL = produs propriu vendor (fără supplier)
vendor_product_id       BIGINT FK NULL -- legătură directă pt produse fără supplier
```

### F. Heatmap & Tracking

| Decizie | Impact pe design |
|---------|-----------------|
| **GPS tracking = opt-in cu consent explicit** + **scanări POS/gate** | Ambele surse. GPS: consent screen în app la prima deschidere, stochezi `customer.location_tracking_consent = true/false`. Scanări POS: implicit (nu necesită consent suplimentar, e parte din serviciu). Heatmap-ul funcționează minim pe scanări POS, îmbunătățit cu GPS unde e disponibil |

**Câmp nou pe `Customer`:**
```
+ location_tracking_consent  BOOLEAN DEFAULT false
+ location_consent_at        TIMESTAMP NULL
```

### G. General

| Decizie | Impact pe design |
|---------|-----------------|
| **Limba: română acum, multi-language ulterior** | Toate string-urile hardcoded în română. Dar: folosim sistemul `Translatable` existent din platformă pe modelele noi care au nume/descrieri publice (produse, categorii, combo-uri). Astfel, traducerile se pot adăuga ulterior fără refactoring |

---

## 42. Wristband Lifecycle Complet

### 42.1 Tipuri de wristband

**Enum `WristbandType` (extindere):**
```php
enum WristbandType: string
{
    case General = 'general';     // Acces standard, balance normal
    case Vip = 'vip';             // Acces zone VIP + standard
    case Staff = 'staff';         // Acces tot, primește credit zilnic/perioadă de la festival
    case Artist = 'artist';       // Acces backstage + tot, primește credit de la admin
    case Sponsor = 'sponsor';     // Acces VIP + zone sponsor
    case Media = 'media';         // Acces press/media zones
    case VendorStaff = 'vendor_staff'; // Staff vendor (nu al festivalului)
}
```

**Permisiuni per tip:**

| Tip | Zone acces | Balance | Credit festival | Top-up propriu | Cashout |
|-----|-----------|---------|-----------------|----------------|---------|
| General | Standard | Normal (min/max) | Nu | Da | Da |
| VIP | Standard + VIP | Normal | Nu | Da | Da |
| Staff | Toate | Fără limită max | Da (zilnic/perioadă) | Da | Da |
| Artist | Toate + Backstage | Fără limită max | Da (sumă alocată) | Da | Da |
| Sponsor | Standard + VIP + Sponsor | Fără limită max | Da (sumă alocată) | Da | Da |
| Media | Standard + Media/Press | Normal | Opțional | Da | Da |
| VendorStaff | Standard + Vendor area | Normal | Nu | Da | Da |

### 42.2 Credit alocat de festival (Staff/Artist/Sponsor)

**Tabel `cashless_credit_allocations`:**
```
id                      BIGINT PK AUTO
tenant_id               BIGINT FK
festival_edition_id     BIGINT FK
cashless_account_id     BIGINT FK
allocated_by            BIGINT FK → users -- admin care a alocat
allocation_type         ENUM('one_time','daily','per_period')
amount_cents            INT -- sumă per alocare
total_allocated_cents   INT DEFAULT 0 -- total alocat până acum
period_start            DATE NULL
period_end              DATE NULL
is_active               BOOLEAN DEFAULT true
notes                   TEXT NULL
meta                    JSON NULL
created_at              TIMESTAMP
updated_at              TIMESTAMP
```

**Flow:**
```
1. Admin festival: "Alocă 150 RON/zi staff-ului Ion Popescu"
   allocation_type='daily', amount_cents=15000

2. Job zilnic (AllocateDailyCreditJob):
   - Găsește toate alocările active cu allocation_type='daily'
   - Pentru fiecare: CashlessAccountService::topUp() cu transaction_type='festival_credit'
   - Incrementare total_allocated_cents
   - WristbandTransaction: channel='system', topup_method='festival_credit'

3. One-time (artist/sponsor):
   Admin: "Alocă 500 RON artistului X"
   → Se creditează instant, allocation_type='one_time'
```

### 42.3 Pre-provisioning & Encoding

**Wristband-urile sunt single-use per ediție.**

**Scenariul 1: Encoding on-site (la check-in)**
```
1. Festival primește wristbands goale (NFC chip-uri neînscrise) de la furnizor
2. La check-in:
   a. Staff scanează biletul clientului (QR code FestivalPassPurchase)
   b. Staff ia un wristband gol, îl scanează cu NFC writer
   c. Sistemul: generează UID unic → scrie pe chip NFC → creează Wristband în DB
   d. Asociere: Wristband → CashlessAccount → Customer
   e. Wristband activat instant
```

**Scenariul 2: Pre-encoding (înainte de festival, pentru expediere)**
```
1. Admin festival: "Pre-encodează 500 wristbands pentru sponsori/VIP"
2. Batch job: generare UID-uri → scriere pe NFC chips → creare Wristbands cu status='pre_encoded'
3. Asociere cu CashlessAccount-urile deja create (la cumpărarea biletului)
4. Expediere prin curier
5. La festival: clientul vine cu wristband deja activ, doar check-in la gate
```

**Status lifecycle wristband:**
```
unassigned → pre_encoded → assigned → activated → disabled/lost/returned
                              ↓
                         (direct la check-in on-site)
```

**Câmpuri noi pe `Wristband`:**
```
+ encoding_method       ENUM('on_site','pre_encoded','bulk_import') DEFAULT 'on_site'
+ encoded_at            TIMESTAMP NULL
+ encoded_by            VARCHAR(255) NULL -- staff care a encodat
+ shipped_at            TIMESTAMP NULL -- dacă s-a trimis prin curier
+ shipping_tracking     VARCHAR(255) NULL
+ batch_id              VARCHAR(100) NULL -- batch de encoding
```

### 42.4 Batch Import Wristbands

Flow existent (`WristbandController::import()`) acceptă CSV cu UID-uri. Extindere:

```csv
uid,wristband_type,access_zones,pre_assign_customer_email
NFC-001,general,"[""standard""]",
NFC-002,vip,"[""standard"",""vip""]",ion@email.com
NFC-003,staff,"[""all""]",maria.staff@festival.ro
NFC-004,artist,"[""all"",""backstage""]",artist@booking.com
```

### 42.5 Re-assignment (wristband returnat)

Când un wristband e returnat (ex: la Lost & Found) și trebuie re-folosit:

```
1. Wristband vechi: status = 'returned', dezasociat de CashlessAccount
2. NU se re-encodează (UID rămâne același)
3. Se poate re-assign la un alt client:
   a. Staff scanează wristband-ul returnat
   b. Sistemul confirmă: balance=0, status=returned
   c. Staff scanează biletul noului client
   d. Re-asociere: Wristband → nou CashlessAccount
   e. Status: 'activated'
4. Audit trail: logare completă a re-assignment-ului
```

**Notă:** Sold-ul NU se transferă. Wristband-ul returnat are balance=0. Clientul vechi păstrează soldul pe CashlessAccount (poate cere cashout sau primește wristband nou).

---

## 24. Dispute Resolution (Contestații Clienți)

Diferit de refund (inițiat de vendor): disputa e inițiată de **client** care contestă o tranzacție.

### 24.1 Model: `CashlessDispute`

**Tabel `cashless_disputes`:**
```
id                       BIGINT PK AUTO
tenant_id                BIGINT FK
festival_edition_id      BIGINT FK
cashless_account_id      BIGINT FK
customer_id              BIGINT FK
wristband_transaction_id BIGINT FK NULL -- tranzacția contestată
cashless_sale_id         BIGINT FK NULL
vendor_id                BIGINT FK NULL
dispute_type             ENUM('unauthorized_charge','wrong_amount','duplicate_charge','product_not_received','quality_issue','other')
status                   ENUM('open','investigating','resolved_refund','resolved_partial_refund','resolved_no_action','rejected','escalated')
amount_disputed_cents    INT
amount_refunded_cents    INT DEFAULT 0
description              TEXT -- descrierea clientului
evidence                 JSON NULL -- screenshots, photos uploaded
admin_notes              TEXT NULL
assigned_to              BIGINT FK → users NULL -- admin asignat
priority                 ENUM('low','medium','high','urgent') DEFAULT 'medium'
opened_at                TIMESTAMP
resolved_at              TIMESTAMP NULL
resolution_reason        TEXT NULL
meta                     JSON NULL
created_at               TIMESTAMP
updated_at               TIMESTAMP
```

### 24.2 Flow dispută

```
1. CLIENT deschide dispută din app:
   POST /api/cashless/client/disputes
   { "wristband_transaction_id": 5678, "dispute_type": "wrong_amount", 
     "description": "Mi s-a debitat 45 RON dar am cumpărat doar o bere de 15 RON" }
   → status = 'open', priority auto-calculated pe baza sumei

2. ADMIN festival vede disputa în Filament → o preia (assigned_to = admin)
   → status = 'investigating'
   → Poate vedea: tranzacția, istoricul clientului, camera POS (dacă există), logul vendorului

3. ADMIN decide:
   a. Refund complet → CashlessAccountService::refund() + status = 'resolved_refund'
   b. Refund parțial → status = 'resolved_partial_refund'
   c. Fără acțiune (tranzacție validă) → status = 'resolved_no_action'
   d. Escalare (fraud suspect) → status = 'escalated' + alertă security

4. CLIENT primește notificare cu rezoluția
```

### 24.3 Auto-escalare

- Dispute nerezolvate >2h în timpul festivalului → priority auto-upgrade
- >5 dispute de la același vendor în <1h → alertă admin: posibilă problemă la vendor
- >3 dispute de la același client → flag review (posibil abuz)

---

## 25. Webhooks Externe

### 25.1 Sistem de webhook subscriptions

Festivalul poate configura URL-uri externe care primesc events în real-time.

**Tabel `cashless_webhook_endpoints`:**
```
id                      BIGINT PK AUTO
tenant_id               BIGINT FK
festival_edition_id     BIGINT FK NULL -- NULL = toate edițiile
url                     VARCHAR(500)
secret                  VARCHAR(255) -- pentru HMAC signature
description             VARCHAR(255) NULL
events                  JSON -- ["sale.completed","topup.completed","stock.low",...]
is_active               BOOLEAN DEFAULT true
last_success_at         TIMESTAMP NULL
last_failure_at         TIMESTAMP NULL
consecutive_failures    INT DEFAULT 0 -- disable auto după 10 failures
meta                    JSON NULL
created_at              TIMESTAMP
updated_at              TIMESTAMP
```

**Tabel `cashless_webhook_deliveries`:**
```
id                      BIGINT PK AUTO
webhook_endpoint_id     BIGINT FK
event_type              VARCHAR(100)
payload                 JSON
response_status         INT NULL
response_body           TEXT NULL
attempted_at            TIMESTAMP
succeeded               BOOLEAN DEFAULT false
attempt_number          INT DEFAULT 1
next_retry_at           TIMESTAMP NULL
```

### 25.2 Events disponibile

```
sale.completed          → payload: sale_id, vendor, items, total, customer
sale.refunded           → payload: refund_id, sale_id, amount
topup.completed         → payload: account_id, amount, channel, method
cashout.requested       → payload: account_id, amount, method
cashout.completed       → payload: account_id, amount
stock.low               → payload: vendor_id, product, quantity_remaining
stock.depleted          → payload: vendor_id, product
vendor.shift_started    → payload: vendor_id, employee, shift_id
vendor.shift_ended      → payload: vendor_id, employee, sales_total
dispute.opened          → payload: dispute_id, customer, amount
account.activated       → payload: account_id, customer
wristband.disabled      → payload: wristband_uid, reason
reconciliation.completed → payload: batch_id, stats
```

### 25.3 Delivery cu retry

```
Retry schedule: 30s, 2min, 10min, 1h, 6h (5 încercări)
După 10 delivery failures consecutive → endpoint dezactivat automat + notificare admin
Signature: HMAC-SHA256 pe payload cu secret-ul endpoint-ului
Header: X-Cashless-Signature, X-Cashless-Event, X-Cashless-Delivery-Id
```

---

## 26. Currency Exchange (Multi-Currency)

### 26.1 Configurare

Festivaluri internaționale: clientul poate plăti în moneda lui, festivalul operează în moneda locală.

**Câmpuri noi pe `CashlessSettings`:**
```
+ supported_currencies      JSON DEFAULT '["RON"]' -- ["RON","EUR","USD","GBP"]
+ base_currency             VARCHAR(3) DEFAULT 'RON' -- moneda festivalului
+ exchange_rate_source      ENUM('manual','ecb','bnr','auto') DEFAULT 'manual'
+ exchange_rate_refresh_minutes INT DEFAULT 60
+ exchange_markup_percentage DECIMAL(5,2) DEFAULT 2.00 -- markup pe curs (profit festival)
```

**Tabel `cashless_exchange_rates`:**
```
id                      BIGINT PK AUTO
tenant_id               BIGINT FK
festival_edition_id     BIGINT FK
from_currency           VARCHAR(3)
to_currency             VARCHAR(3)
rate                    DECIMAL(12,6) -- 1 EUR = 4.9750 RON
markup_rate             DECIMAL(12,6) -- 1 EUR = 5.0745 RON (cu markup 2%)
valid_from              TIMESTAMP
valid_until             TIMESTAMP NULL
source                  VARCHAR(50) -- 'manual', 'bnr_api', 'ecb_api'
meta                    JSON NULL
created_at              TIMESTAMP

UNIQUE (festival_edition_id, from_currency, to_currency, valid_from)
```

### 26.2 Flow top-up multi-currency

```
1. Client cu card EUR face top-up 50 EUR
2. Sistem: curs EUR→RON = 5.0745 (cu markup)
3. Se creditează: 253.73 RON în CashlessAccount
4. WristbandTransaction: amount_cents=25373, currency='RON',
   meta: { original_amount_cents: 5000, original_currency: 'EUR', exchange_rate: 5.0745 }
5. Toate operațiile interne rămân în base_currency (RON)
```

---

## 27. Tipping (Bacșiș Digital)

### 27.1 Concept

Clientul poate adăuga bacșiș opțional la orice achiziție. Bacșișul merge **integral** la vendor (nu se aplică comision festival).

**Câmpuri noi pe `CashlessSale`:**
```
+ tip_cents              INT DEFAULT 0
+ tip_percentage         DECIMAL(5,2) NULL -- dacă a ales procent (10%, 15%, 20%)
+ total_with_tip_cents   INT -- total_cents + tip_cents
```

**Câmpuri noi pe `CashlessSettings`:**
```
+ tipping_enabled           BOOLEAN DEFAULT false
+ tip_preset_percentages    JSON DEFAULT '[10, 15, 20]' -- opțiuni rapide
+ tip_custom_enabled        BOOLEAN DEFAULT true -- permite sumă custom
+ tip_max_percentage        DECIMAL(5,2) DEFAULT 50.00 -- maxim 50% bacșiș
+ tip_exempt_from_commission BOOLEAN DEFAULT true -- nu se aplică comision pe tips
```

### 27.2 Flow POS cu tip

```
1. Employee scanează produse → Total: 45 RON
2. Ecranul se întoarce la client: "Dorești să adaugi bacșiș?"
   [10% = 4.50] [15% = 6.75] [20% = 9.00] [Altă sumă] [Nu, mulțumesc]
3. Client alege 15% → tip_cents = 675
4. Charge total: 45.00 + 6.75 = 51.75 RON
5. WristbandTransaction.amount_cents = 5175
6. CashlessSale: total_cents=4500, tip_cents=675, total_with_tip_cents=5175
7. La calcul comision: se aplică doar pe total_cents (4500), NU pe tip
```

### 27.3 Rapoarte tipping

- Total tips per vendor / per angajat / per zi
- Procentaj mediu tip per vendor
- Distribuție tips: câți clienți dau 10%, 15%, 20%, custom
- Raport: vendori cu cele mai mari/mici tips (indicator calitate serviciu)

---

## 28. Spending Limits Parentale

### 28.1 Concept

Un părinte (cont adult) poate seta limită de cheltuieli pe contul copilului minor.

**Tabel `cashless_spending_limits`:**
```
id                      BIGINT PK AUTO
tenant_id               BIGINT FK
festival_edition_id     BIGINT FK
parent_account_id       BIGINT FK → cashless_accounts
child_account_id        BIGINT FK → cashless_accounts
daily_limit_cents       INT NULL -- limită pe zi (NULL = fără limită zilnică)
total_limit_cents       INT NULL -- limită totală pe toată durata
per_transaction_limit_cents INT NULL -- maxim per tranzacție
daily_spent_cents       INT DEFAULT 0 -- reset zilnic la 00:00
total_spent_cents       INT DEFAULT 0
blocked_categories      JSON NULL -- ["alcohol","tobacco"] -- categorii blocate complet
require_approval_above_cents INT NULL -- cere aprobare părintelui pentru sume mari
is_active               BOOLEAN DEFAULT true
created_at              TIMESTAMP
updated_at              TIMESTAMP
```

### 28.2 Flow

```
1. PĂRINTE din app: "Setează limită pentru copilul meu"
   POST /api/cashless/client/spending-limits
   { "child_account_number": "CA-XXX", "daily_limit_cents": 20000, 
     "blocked_categories": ["alcohol","tobacco"] }

2. La fiecare CHARGE pe contul copilului:
   a. SaleService verifică spending limits
   b. Categorii blocate → BLOCK instant
   c. daily_spent + charge > daily_limit → BLOCK: "Limita zilnică depășită"
   d. total_spent + charge > total_limit → BLOCK: "Limita totală depășită"
   e. charge > per_transaction_limit → BLOCK sau cerere aprobare

3. NOTIFICARE PĂRINTE la fiecare achiziție a copilului:
   "{copil} a cheltuit {sumă} la {vendor}. Sold: {sold}. Limită: {rămas}/{total}"

4. Job zilnic: ResetDailySpendingLimitsJob → daily_spent_cents = 0
```

### 28.3 Linking parent ↔ child

Validare: ambele conturi trebuie să fie din aceeași ediție festival. Linking-ul se face prin:
- QR code scan din app-ul părintelui pe wristband-ul copilului
- Sau manual prin account_number + confirmare pe ambele conturi

---

## 29. API Rate Limiting Detaliat

### 29.1 Straturi de rate limiting

```
Layer 1: Global (nginx/CloudFlare)
  → 1000 req/min per IP

Layer 2: Per API key (Laravel throttle middleware)
  → 300 req/min per tenant API key

Layer 3: Per endpoint (granular)
  → POS charge: 60 req/min per vendor_pos_device
  → Top-up: 10 req/min per customer
  → Sync offline: 10 req/min per device
  → Reports: 30 req/min per user
  → Client app: 120 req/min per customer token

Layer 4: Per entitate (business logic)
  → 1 charge per 10s per cashless_account (anti-double-tap)
  → 1 transfer per 60s per account
  → 3 cashout requests per hour per account
  → 5 dispute opens per day per customer
```

### 29.2 Configurare în `CashlessSettings`

```
+ rate_limit_charge_cooldown_seconds    INT DEFAULT 10
+ rate_limit_topup_per_minute           INT DEFAULT 10
+ rate_limit_transfer_cooldown_seconds  INT DEFAULT 60
+ rate_limit_cashout_per_hour           INT DEFAULT 3
```

### 29.3 Middleware custom

```php
// app/Http/Middleware/CashlessRateLimit.php
class CashlessRateLimit
{
    public function handle($request, Closure $next, string $context)
    {
        $settings = $this->getEditionSettings($request);
        $key = $this->buildRateLimitKey($request, $context);
        $limit = $this->getLimitForContext($settings, $context);
        
        if (RateLimiter::tooManyAttempts($key, $limit)) {
            return response()->json([
                'error' => 'rate_limit_exceeded',
                'retry_after' => RateLimiter::availableIn($key),
            ], 429);
        }
        
        RateLimiter::hit($key, decay: $this->getDecayForContext($context));
        return $next($request);
    }
}
```

---

## 30. Data Retention & GDPR

### 30.1 Politică de retenție

| Categorie date | Retenție | Acțiune după expirare |
|----------------|----------|----------------------|
| Tranzacții financiare | 10 ani | Obligație legală (fiscal) – se păstrează |
| Date personale client (nume, email, telefon) | 2 ani post-festival | Anonimizare |
| Customer profiles (comportament) | 1 an post-festival | Ștergere completă |
| Audit logs | 5 ani | Arhivare cold storage |
| Wristband scan logs | 6 luni post-festival | Ștergere |
| Notification preferences | La cerere sau 1 an | Ștergere |
| Dispute evidence (photos) | 1 an post-rezolvare | Ștergere |
| Webhook delivery logs | 90 zile | Ștergere |
| Location/heatmap data | 6 luni post-festival | Anonimizare |

### 30.2 Anonimizare

**Job: `AnonymizeCashlessDataJob`** (rulat periodic)

```php
// Anonimizare tranzacții mai vechi de 2 ani:
WristbandTransaction::where('created_at', '<', now()->subYears(2))
    ->update([
        'customer_email' => null,
        'customer_name' => null,
        'operator' => 'anonymized',
    ]);

// Anonimizare CashlessAccount:
CashlessAccount::where('closed_at', '<', now()->subYears(2))
    ->each(function ($account) {
        $account->update(['meta' => null]);
        // Customer rămâne (date fiscale) dar profilul se șterge
        $account->profile()->delete();
    });
```

### 30.3 Drepturi GDPR client

| Drept | Implementare |
|-------|-------------|
| **Drept la acces** | `GET /api/cashless/client/gdpr/export` → export JSON/CSV cu toate datele |
| **Drept la ștergere** | `POST /api/cashless/client/gdpr/delete-request` → cerere review admin → ștergere/anonimizare |
| **Drept la portabilitate** | Același endpoint export, format standard |
| **Drept la rectificare** | `PUT /api/cashless/client/profile` → modificare date |
| **Drept la opoziție** | Dezactivare notificări, dezactivare profiling |

**Tabel `cashless_gdpr_requests`:**
```
id                      BIGINT PK AUTO
tenant_id               BIGINT FK
customer_id             BIGINT FK
request_type            ENUM('export','deletion','rectification','objection')
status                  ENUM('pending','processing','completed','rejected')
requested_at            TIMESTAMP
processed_at            TIMESTAMP NULL
processed_by            BIGINT FK → users NULL
export_file_path        VARCHAR(500) NULL
notes                   TEXT NULL
meta                    JSON NULL
created_at              TIMESTAMP
updated_at              TIMESTAMP
```

---

## 31. Batch Operations + Testing Strategy

### 31.1 Batch Operations

Operații în masă necesare pentru managementul festivalului.

**Job-uri batch cu progress tracking:**

| Operație | Job | Input |
|----------|-----|-------|
| Import 5000 wristbands | `BatchImportWristbandsJob` | CSV cu UIDs |
| Activare conturi în masă | `BatchActivateAccountsJob` | Lista customer IDs |
| Alocare stoc la 20 vendori | `BatchAllocateStockJob` | JSON: [{vendor_id, product_id, qty}] |
| Top-up promo (voucher masiv) | `BatchApplyVoucherJob` | Voucher ID + lista accounts |
| Auto-cashout final festival | `BatchAutoCashoutJob` | Edition ID |
| Export raport mare | `BatchExportReportJob` | Report config |
| Anonimizare GDPR | `AnonymizeCashlessDataJob` | Date range |

**Progress tracking:**

**Tabel `cashless_batch_jobs`:**
```
id                      BIGINT PK AUTO
tenant_id               BIGINT FK
festival_edition_id     BIGINT FK NULL
job_type                VARCHAR(100)
status                  ENUM('queued','processing','completed','failed','cancelled')
total_items             INT
processed_items         INT DEFAULT 0
failed_items            INT DEFAULT 0
progress_percentage     DECIMAL(5,2) DEFAULT 0
started_at              TIMESTAMP NULL
completed_at            TIMESTAMP NULL
error_log               JSON NULL -- [{item_id, error_message}]
result_file_path        VARCHAR(500) NULL -- CSV cu rezultate
initiated_by            BIGINT FK → users
meta                    JSON NULL
created_at              TIMESTAMP
updated_at              TIMESTAMP
```

**API:**
```
POST   /api/cashless/batch/{type}        → inițiere job
GET    /api/cashless/batch/{id}/status    → progress real-time
POST   /api/cashless/batch/{id}/cancel    → anulare
GET    /api/cashless/batch/{id}/result    → download rezultat
```

### 31.2 Testing Strategy

#### Unit Tests (PHPUnit)

| Component | Ce se testează | Nr. teste estimat |
|-----------|---------------|-------------------|
| `CashlessAccountService` | topUp, charge, refund, cashout, transfer – cu locking, edge cases | ~30 |
| `PricingService` | Calcul prețuri, components, TVA, SGR, mandatory pricing | ~20 |
| `FinanceFeeService` | Calcul comisioane, fee rules, percentage/fixed/per-category | ~15 |
| `SaleService` | Creare sale, age verification, spending limits, tipping | ~25 |
| `SupplierStockService` | Delivery, allocation, consumption, return, waste | ~20 |
| `CustomerProfileService` | Scoring, segmentation, tagging | ~15 |
| `ProductImportService` | CSV parse, validation, matching | ~10 |
| `ReconciliationService` | Offline sync, conflicts, duplicates | ~15 |
| Models | Relații, scopes, accessors, computed fields | ~30 |
| **Total** | | **~180 tests** |

#### Feature Tests (Integration)

| Flow | Ce se testează |
|------|---------------|
| Full purchase flow | Activare cont → top-up → achiziție → receipt → cashout |
| Refund with approval | Member request → manager approve → balance restored |
| Dispute flow | Client opens → admin investigates → refund/reject |
| Stock flow | Delivery → allocate → sell → stock decremented |
| Multi-currency topup | EUR top-up → RON conversion → correct balance |
| Offline reconciliation | Batch sync → conflict detection → resolution |
| Voucher redemption | Apply code → validate → credit account |
| Parental spending limit | Set limit → child charges → limit enforcement |

#### Load Testing

```
Tool: k6 sau Laravel's built-in stress testing

Scenarii:
1. Peak POS: 5000 charge requests/min (simulare 100 POS-uri × 50 tranzacții/min)
2. Peak top-up: 500 top-ups/min
3. Concurrent charges pe același cont: 10 simultane (test locking)
4. Mass sync: 50 POS-uri trimit câte 200 tranzacții offline simultan
5. Report generation: 20 rapoarte complexe simultane
```

#### Seeders

```php
// database/seeders/CashlessFestivalSeeder.php
// Generează un festival complet cu:
// - 1 ediție activă
// - 30 vendori cu câte 20-50 produse fiecare
// - 5 suppliers cu 100 produse
// - 5000 clienți cu conturi cashless active
// - 50,000 tranzacții (mix topup/charge/refund)
// - 10 POS devices per vendor
// - 200 angajați
// - Pricing rules + finance rules
// - Stocuri alocate
```

---

## 32. Lost & Found (Integrare Cashless)

### 32.1 Ce există

Modelul `LostAndFound` este deja funcțional cu: categorii (phone, wallet, keys etc.), matching lost↔found, claiming, foto, scopes. Trebuie doar integrat cu sistemul cashless.

### 32.2 Extinderi necesare

**Câmpuri noi pe `lost_and_found`:**
```
+ festival_edition_id    BIGINT FK → festival_editions NULL
+ wristband_id           BIGINT FK → wristbands NULL -- wristband pierdută/găsită
+ cashless_account_id    BIGINT FK → cashless_accounts NULL
+ wristband_uid          VARCHAR(100) NULL -- UID scanat de pe wristband găsit
+ vendor_id              BIGINT FK NULL -- raportare de la un vendor
+ topup_location_id      BIGINT FK NULL -- raportare de la un top-up stand
+ zone                   VARCHAR(100) NULL -- zona din festival (legat de FestivalMap)
+ urgency                ENUM('low','medium','high') DEFAULT 'medium'
+ notification_sent      BOOLEAN DEFAULT false
```

**Categorie nouă:**
```php
'wristband' => 'Wristband / Brățară',
```

### 32.3 Flow wristband pierdută

```
1. Client raportează pierdere din app:
   POST /api/cashless/client/lost-and-found
   { "type": "lost", "category": "wristband", "location_found_or_lost": "Zona Stage A" }

2. Sistem AUTOMAT:
   a. Dezactivare wristband: Wristband::disable('reported_lost')
   b. CashlessAccount rămâne ACTIV (sold păstrat, contul funcționează digital)
   c. Creare LostAndFound entry cu wristband_id link
   d. Notificare: "Wristband dezactivat. Soldul tău de {sold} e protejat."

3. La Info Point, staff găsește o wristband:
   a. Scanare NFC → identificare UID
   b. Creare LostAndFound entry type='found', wristband_uid=UID
   c. Auto-matching cu entries 'lost' care au același wristband_id
   d. Dacă match → notificare client: "Wristband-ul tău a fost găsit! Vino la Info Point {X}"

4. Client vine să revendice:
   a. Verificare identitate (CI/pașaport)
   b. LostAndFound::markClaimed()
   c. Re-activare wristband + re-link la CashlessAccount
```

### 32.4 Flow wristband replacement

```
1. Client la Info Point: "Am pierdut wristband-ul"
2. Staff verifică CashlessAccount → sold confirmé
3. Dezactivare wristband vechi (dacă nu era deja)
4. Atribuire wristband NOU → CashlessAccount (Wristband::assignTo())
5. Sync balance pe noul wristband
6. Logare în audit trail: "Wristband replacement: old=UID1, new=UID2"
```

### 32.5 API client

```
POST   /api/cashless/client/lost-and-found           → raportare pierdere
GET    /api/cashless/client/lost-and-found            → status raportările mele
GET    /api/cashless/client/lost-and-found/{id}       → detalii
```

---

## 33. Festival Map + Live Tracking + Heatmaps

### 33.1 Ce există

- `FestivalMap` – hartă cu image_url + bounds (coordonate)
- `FestivalPointOfInterest` – POI cu lat/lng, categorie, ore funcționare, stage_id

### 33.2 Extinderi necesare pe modele existente

**Câmpuri noi pe `festival_points_of_interest`:**
```
+ vendor_id              BIGINT FK → vendors NULL -- POI = vendor location
+ topup_location_id      BIGINT FK → topup_locations NULL
+ capacity               INT NULL -- capacitate maximă zonă
+ current_occupancy       INT DEFAULT 0 -- persoane estimate acum (live)
+ occupancy_level         ENUM('low','moderate','high','full') NULL
+ is_cashless_point       BOOLEAN DEFAULT false -- are terminal cashless?
+ live_status             ENUM('open','closed','busy','full') DEFAULT 'open'
+ polygon_coordinates     JSON NULL -- contur zonă (pentru heatmap)
+ elevation               DECIMAL(6,2) NULL -- nivel/etaj
```

**Câmpuri noi pe `festival_maps`:**
```
+ festival_edition_id    BIGINT FK → festival_editions NULL
+ map_type               ENUM('static','interactive','satellite') DEFAULT 'static'
+ tile_url               VARCHAR(500) NULL -- custom tile server URL
+ min_zoom               INT DEFAULT 14
+ max_zoom               INT DEFAULT 20
+ default_zoom           INT DEFAULT 16
+ default_center         JSON NULL -- {"lat": 44.4268, "lng": 26.1025}
+ overlay_layers         JSON NULL -- layer-uri aditionale
+ heatmap_enabled        BOOLEAN DEFAULT false
+ live_tracking_enabled  BOOLEAN DEFAULT false
```

### 33.3 Live Participant Tracking

**Sursele de date locație:**
1. **Scanări wristband la POS** – cea mai precisă (știm exact unde e clientul)
2. **Top-up la stand** – locație exactă
3. **Check-in la gate** – intrare/ieșire zone
4. **App GPS** (opțional, cu consent) – tracking continuu
5. **Bluetooth beacons** (opțional, hardware adițional)

**Tabel `participant_location_events`:**
```
id                      BIGINT PK AUTO
tenant_id               BIGINT FK
festival_edition_id     BIGINT FK
customer_id             BIGINT FK NULL -- poate fi anonim
cashless_account_id     BIGINT FK NULL
source                  ENUM('pos_scan','topup_scan','gate_checkin','app_gps','beacon')
lat                     DECIMAL(9,6) NULL
lng                     DECIMAL(9,6) NULL
zone_id                 BIGINT FK → festival_points_of_interest NULL
vendor_id               BIGINT FK NULL
accuracy_meters         DECIMAL(6,1) NULL
recorded_at             TIMESTAMP
-- Nu stocăm created_at/updated_at - optimizare write performance
```

**Index:** `(festival_edition_id, recorded_at)` + `(zone_id, recorded_at)` pentru query-uri rapide.

**Retenție:** Ștergere automată după 6 luni (GDPR). Agregările rămân.

### 33.4 Heatmap Engine

**Cum funcționează:**

```
1. COLECTARE: La fiecare scanare POS/topup/gate, se scrie în participant_location_events
   → Async via queue (nu blochează tranzacția)

2. AGREGARE: Job periodic (la fiecare 2 minute):
   AggregateHeatmapJob calculează densitate per zonă:
   
   SELECT zone_id, COUNT(DISTINCT customer_id) as unique_visitors,
          COUNT(*) as total_events
   FROM participant_location_events
   WHERE recorded_at > NOW() - INTERVAL '15 minutes'
   GROUP BY zone_id

3. STOCARE: Redis hash pentru acces rapid
   Key: "heatmap:{edition_id}:current"
   Value: { "zone_123": { "density": 0.85, "count": 342 }, ... }

4. AFIȘARE: Frontend (app + admin) citește din Redis → overlay pe hartă
```

**Tabel `heatmap_snapshots` (istoric pentru analiză post-festival):**
```
id                      BIGINT PK AUTO
tenant_id               BIGINT FK
festival_edition_id     BIGINT FK
snapshot_at             TIMESTAMP
granularity             ENUM('5min','15min','1hour') DEFAULT '15min'
data                    JSON -- {"zones": [{"id": 1, "density": 0.7, "visitors": 250}, ...]}
total_active_visitors   INT
meta                    JSON NULL
```

### 33.5 API Hartă + Heatmap

```
GET    /api/cashless/map/{editionId}                    → hartă + toate POI-uri
GET    /api/cashless/map/{editionId}/vendors             → doar vendori cu locație
GET    /api/cashless/map/{editionId}/topup-locations     → standuri top-up
GET    /api/cashless/map/{editionId}/heatmap/live        → heatmap real-time (Redis)
GET    /api/cashless/map/{editionId}/heatmap/history     → heatmap istoric (per interval)
GET    /api/cashless/map/{editionId}/zones/{zoneId}/occupancy → ocupare zonă live
```

**API client app:**
```
POST   /api/cashless/client/location                    → report GPS location (opt-in)
GET    /api/cashless/client/map                          → hartă personalizată (cu vendori vizitați highlighted)
GET    /api/cashless/client/nearby-vendors               → vendori apropiați pe baza locației
```

### 33.6 Crowd Management Dashboard (Filament)

Widget-uri pe pagina Map admin:

1. **Hartă interactivă live** cu heatmap overlay (culori: verde→galben→roșu→violet)
2. **Ocupare per zonă** – barchart cu capacitate vs. actual
3. **Alertă suprasolicitare** – dacă o zonă depășește 90% capacitate → alertă automată
4. **Flow vizitatori** – animație: de unde vin vizitatorii (entry points) și cum se distribuie
5. **Comparație cu ziua precedentă** – overlay: azi vs ieri la aceeași oră
6. **Timeline slider** – vizualizare heatmap istoric, scrub prin ore

---

## 34. Advanced Reports, Analytics & Predictions

### 34.1 Rapoarte adiționale (extindere secțiunea 6)

#### G. RAPOARTE AVANSATE VÂNZĂRI

| # | Raport | Descriere |
|---|--------|-----------|
| SA1 | **Basket analysis** | Ce produse se cumpără împreună (asocieri) – ex: "Clienții care cumpără bere cumpără și hot dog 67% din timp" |
| SA2 | **Price elasticity** | Impactul prețului asupra volumului – dacă prețul crește cu 10%, volumul scade cu X% |
| SA3 | **Sales velocity** | Viteza vânzărilor per produs (unități/oră) – trending up/down |
| SA4 | **Vendor performance matrix** | Revenue × Satisfaction (tip %) × Efficiency (avg serve time) |
| SA5 | **Lost sales estimation** | Estimare vânzări pierdute din cauza stoc epuizat (bazat pe trend pre-depletion) |
| SA6 | **Revenue per square meter** | Dacă avem suprafața vendorilor → revenue/mp (eficiență spațiu) |
| SA7 | **Weather correlation** | Corelație vânzări ↔ vreme (necesită weather API) – "Pe ploaie, vânzările de bere scad 30% dar cafeaua crește 50%" |

#### H. RAPOARTE AVANSATE CLIENȚI

| # | Raport | Descriere |
|---|--------|-----------|
| CA1 | **Customer journey map** | Traseul complet al clientului: check-in → prim topup → unde a mâncat → ce concerte → cashout |
| CA2 | **Cohort analysis** | Comportament pe cohorte: Early Bird vs. Last Minute buyers, VIP vs. General |
| CA3 | **Churn prediction intra-festival** | Clienți care au încetat să mai cumpere – probabilitate cashout anticipat |
| CA4 | **Lifetime value per client** | Dacă clientul a participat la mai multe ediții → LTV cross-edition |
| CA5 | **Net Promoter Score correlation** | Corelare NPS (din FestivalReview) cu spending patterns |
| CA6 | **Social spending patterns** | Grupuri de clienți care cumpără împreună (same vendor, same time, transfer-uri între ei) |
| CA7 | **First purchase delay** | Cât timp trece de la activare cont → prima achiziție (indicator onboarding friction) |

#### I. RAPOARTE PREDICTIVE (ML-ready)

| # | Raport | Descriere | Algoritm sugerat |
|---|--------|-----------|------------------|
| P1 | **Demand forecasting per produs** | Predicție cerere pe orele următoare | Time series (ARIMA/Prophet) |
| P2 | **Stock depletion prediction** | Când se va termina stocul fiecărui produs | Linear regression pe consumption rate |
| P3 | **Peak hour prediction** | Predicție ore vârf pentru ziua curentă | Pattern matching pe zile anterioare |
| P4 | **Revenue forecast** | Estimare revenue total ediție bazat pe trend curent | Exponential smoothing |
| P5 | **Crowd density prediction** | Estimare densitate pe zone pentru următoarele 2h | LSTM pe heatmap history |
| P6 | **Cashout volume prediction** | Estimare volum cashout-uri (pentru pregătire cash) | Historical pattern + day-of-festival position |
| P7 | **Vendor staffing recommendation** | Câți angajați ar trebui per shift bazat pe predicted demand | Optimization model |

### 34.2 Implementare predicții

**Abordare pragmatică (fără ML extern inițial):**

```php
// app/Services/Cashless/PredictionService.php

class PredictionService
{
    /**
     * Predicție simplă bazată pe moving average + day-of-festival pattern
     */
    public function forecastHourlySales(int $editionId, int $hoursAhead = 6): array
    {
        // 1. Obține vânzări per oră pentru zilele anterioare din aceeași ediție
        $historicalHourly = $this->getHourlySalesHistory($editionId);
        
        // 2. Calculează pattern: "la ora 14, ziua 3, în medie se vând X"
        $dayOfFestival = $this->getCurrentDayNumber($editionId);
        
        // 3. Ajustare cu trend curent (azi e mai sus/jos decât media)
        $todayTrend = $this->calculateTodayTrend($editionId);
        
        // 4. Proiecție pe orele următoare
        $forecast = [];
        for ($h = 1; $h <= $hoursAhead; $h++) {
            $targetHour = now()->addHours($h)->hour;
            $baselineForHour = $historicalHourly[$targetHour] ?? 0;
            $forecast[] = [
                'hour' => $targetHour,
                'predicted_sales_cents' => (int) ($baselineForHour * $todayTrend),
                'confidence' => $this->calculateConfidence($historicalHourly, $targetHour),
            ];
        }
        return $forecast;
    }

    /**
     * Predicție stock depletion
     */
    public function predictStockDepletion(int $stockId): ?Carbon
    {
        $stock = InventoryStock::find($stockId);
        
        // Consumption rate din ultimele 4 ore
        $recentConsumption = InventoryMovement::where('inventory_stock_id', $stockId)
            ->where('movement_type', 'sale')
            ->where('created_at', '>', now()->subHours(4))
            ->sum('quantity');
        
        $hourlyRate = $recentConsumption / 4;
        
        if ($hourlyRate <= 0) return null; // nu se consumă
        
        $hoursUntilEmpty = $stock->quantity_available / $hourlyRate;
        
        return now()->addHours($hoursUntilEmpty);
    }
}
```

### 34.3 Dashboard Analytics Avansat (Filament)

**Pagină dedicată: "Analytics & Predictions"**

**Row 1: Predicții live**
- Card: "Revenue estimat EOD" → predicted total + confidence interval
- Card: "Peak hour azi" → ora estimată cu cel mai mare volum
- Card: "Produse care se termină în <2h" → alertă roșie
- Card: "Crowd peak estimat" → ora + zona

**Row 2: Trend-uri**
- Grafic: Actual vs. Predicted sales (suprapuse, ultimele 12h)
- Grafic: Stock depletion curves (top 10 produse cu cel mai rapid consum)

**Row 3: Patterns**
- Heatmap temporal: Ore × Zile (intensitate = volum vânzări)
- Sankey diagram: Customer flow (Vendor A → Stage B → Vendor C)
- Scatter plot: Spending vs. Age (clustering vizual)

**Row 4: Cross-festival insights** (dacă au existat ediții anterioare)
- Comparație ediție curentă vs. precedentă (KPI-uri side by side)
- Trend multi-ediție: revenue, nr. clienți, avg. spending growth

### 34.4 Real-time Anomaly Detection

**Service: `AnomalyDetectionService`**

Rulează la fiecare 5 minute, verifică:

| Anomalie | Detectare | Alertă |
|----------|-----------|--------|
| Spike vânzări brusc (+200% vs. media orei) | Z-score > 3 pe sliding window 1h | Posibil bug POS sau fraud |
| Drop vânzări brusc (-70% vs. media orei) | Z-score < -2 pe sliding window 1h | POS down? Incident? |
| Cont cu >20 tranzacții/oră | Threshold pe frequency | Posibil sharing fraudulent |
| Top-up sume rotunde repetate | Pattern detection | Posibil money laundering |
| Vendor cu 0 vânzări >2h (în ore active) | Gap detection | POS offline? Vendor închis? |
| Sold negativ cont | Balance < 0 (din offline sync) | Necesită reconciliere |
| Wristband folosit la 2 POS-uri simultan | Temporal overlap | Wristband clonat |

```php
class AnomalyDetectionService
{
    public function detectSalesSpike(int $editionId): array
    {
        $currentHourSales = $this->getCurrentHourSales($editionId);
        $avgHourSales = $this->getAverageHourSales($editionId, now()->hour);
        $stdDev = $this->getStdDevHourSales($editionId, now()->hour);
        
        if ($stdDev == 0) return [];
        
        $zScore = ($currentHourSales - $avgHourSales) / $stdDev;
        
        if (abs($zScore) > 3) {
            return [[
                'type' => $zScore > 0 ? 'sales_spike' : 'sales_drop',
                'severity' => abs($zScore) > 4 ? 'critical' : 'warning',
                'current_value' => $currentHourSales,
                'expected_value' => $avgHourSales,
                'z_score' => $zScore,
                'detected_at' => now(),
            ]];
        }
        return [];
    }
}
```

### 34.5 Export & API

- Toate rapoartele avansate disponibile prin API: `GET /api/cashless/analytics/{report_type}`
- Predictions API: `GET /api/cashless/predictions/sales?hours_ahead=6`
- Anomalies API: `GET /api/cashless/anomalies?status=active`
- Webhook event: `anomaly.detected` → trimis la endpoints configurate

---

## 35. Combo / Bundle Deals

### 35.1 Concept

Pachete predefinite care oferă discount când produsele se cumpără împreună. Pot fi configurate:
- **Per vendor** – vendor-ul își setează combo-urile proprii ("Bere + Hot Dog = 22 RON")
- **Per festival** – admin-ul festivalului impune combo-uri cross-vendor sau pe produse supplier

### 35.2 Model: `CashlessCombo`

**Tabel `cashless_combos`:**
```
id                      BIGINT PK AUTO
tenant_id               BIGINT FK
festival_edition_id     BIGINT FK
vendor_id               BIGINT FK NULL -- NULL = combo la nivel de festival (orice vendor)
name                    VARCHAR(255) -- "Bere + Hot Dog Menu"
slug                    VARCHAR(255)
description             TEXT NULL
combo_type              ENUM('fixed_price','percentage_discount','fixed_discount')
fixed_price_cents       INT NULL -- preț fix combo (ex: 2200 = 22 RON)
discount_percentage     DECIMAL(5,2) NULL -- 15% discount pe total items
discount_cents          INT NULL -- 500 = 5 RON reducere fixă
original_price_cents    INT -- suma prețurilor individuale (ex: 2700 = 27 RON)
savings_cents           INT -- economie (ex: 500 = 5 RON)
image_url               VARCHAR(500) NULL
is_active               BOOLEAN DEFAULT true
valid_from              TIMESTAMP NULL
valid_until             TIMESTAMP NULL
max_redemptions         INT NULL -- limită totală
current_redemptions     INT DEFAULT 0
max_per_customer        INT NULL -- max per client per zi
sort_order              INT DEFAULT 0
meta                    JSON NULL
created_at              TIMESTAMP
updated_at              TIMESTAMP
```

**Tabel `cashless_combo_items`:**
```
id                      BIGINT PK AUTO
cashless_combo_id       BIGINT FK → cashless_combos
vendor_product_id       BIGINT FK → vendor_products NULL -- produs specific
product_category        VARCHAR(100) NULL -- SAU orice produs din categorie
quantity                INT DEFAULT 1 -- câte bucăți din acest item
is_required             BOOLEAN DEFAULT true -- obligatoriu sau opțional
sort_order              INT DEFAULT 0
meta                    JSON NULL
```

### 35.3 Exemple configurare

**Combo fix (vendor level):**
```
Combo: "Bere + Hot Dog Menu"
Items: 1x Ursus 500ml (15 RON) + 1x Hot Dog Classic (12 RON)
combo_type: 'fixed_price', fixed_price_cents: 2200
original_price_cents: 2700, savings_cents: 500
→ Client plătește 22 RON în loc de 27 RON
```

**Combo categorie (festival level):**
```
Combo: "Drink + Food Deal"
Items: 1x orice din "Bere" + 1x orice din "Fast Food"
combo_type: 'percentage_discount', discount_percentage: 10
→ 10% discount pe orice combinație bere + mâncare
```

**Combo time-limited:**
```
Combo: "Happy Hour Combo"
valid_from: 14:00, valid_until: 16:00
→ Activ doar în intervalul orar
```

### 35.4 Flow POS

```
1. Employee adaugă produse în coș: Ursus 500ml + Hot Dog Classic
2. SaleService::detectCombos() verifică automat dacă produsele match un combo activ
3. Dacă da → afișare pe ecran POS: "Combo disponibil: -5 RON"
   [APLICĂ COMBO] [NU, PREȚURI INDIVIDUALE]
4. Employee/client confirmă → se aplică discount
5. CashlessSale: total_cents=2200, meta: { "combo_id": 5, "combo_savings_cents": 500 }
6. VendorSaleItem-uri: fiecare item cu flag combo_applied=true
```

### 35.5 Rapoarte combo

- Total combo-uri vândute per tip
- Savings total oferit clienților (marketing metric)
- Top combo-uri (popularitate)
- Conversion rate: câte ori s-a afișat combo vs. câte ori s-a aplicat

---

## 36. Split Payment (Plată Împărțită)

### 36.1 Concept

Doi sau mai mulți clienți împart nota de plată la un vendor. Ex: 3 prieteni comandă împreună, fiecare plătește partea lui.

### 36.2 Flow split payment

```
1. Employee creează coșul normal:
   2x Bere Ursus    30 RON
   1x Pizza          25 RON
   1x Nachos         18 RON
   TOTAL:            73 RON

2. Employee alege [SPLIT PAYMENT]

3. Opțiuni split:
   a. EGAL: 73 / 3 = 24.33 RON fiecare (rotunjire pe ultimul)
   b. PER ITEM: fiecare alege ce-a comandat
   c. CUSTOM: sume custom per persoană

4. Scanare wristband #1 → charge 24.33 RON ✓
   Scanare wristband #2 → charge 24.33 RON ✓
   Scanare wristband #3 → charge 24.34 RON ✓ (rotunjire)

5. Se creează UN SINGUR CashlessSale (parent) cu mai multe WristbandTransactions
```

### 36.3 Model

**Câmpuri noi pe `CashlessSale`:**
```
+ is_split_payment       BOOLEAN DEFAULT false
+ split_count            INT DEFAULT 1 -- câte persoane
+ split_method           ENUM('equal','per_item','custom') NULL
```

**Tabel `cashless_sale_splits`:**
```
id                      BIGINT PK AUTO
cashless_sale_id        BIGINT FK → cashless_sales
cashless_account_id     BIGINT FK → cashless_accounts
customer_id             BIGINT FK → customers NULL
wristband_transaction_id BIGINT FK → wristband_transactions
amount_cents            INT -- suma plătită de acest participant
split_order             INT -- 1, 2, 3...
items                   JSON NULL -- items alocate (pt split per_item)
meta                    JSON NULL
created_at              TIMESTAMP
```

### 36.4 Reguli business

- Split maxim: configurabil în CashlessSettings (default 6 persoane)
- Fiecare participant trebuie să aibă sold suficient ÎNAINTE de procesare
- Dacă un participant nu are sold → opțiune de re-split între ceilalți
- Toate charge-urile dintr-un split se procesează atomic (toate sau niciuna)
- Suma split-urilor trebuie să fie exact egală cu total_cents (validare strictă)
- Tipping: se poate adăuga tip doar pe split-ul propriu

```php
// Atomic split processing
DB::transaction(function () use ($sale, $splits) {
    foreach ($splits as $split) {
        $account = CashlessAccount::lockForUpdate()->find($split['account_id']);
        if ($account->balance_cents < $split['amount_cents']) {
            throw new InsufficientBalanceException(
                "Account {$account->account_number} has insufficient balance for split"
            );
        }
    }
    // All validated, now charge all
    foreach ($splits as $split) {
        $this->cashlessAccountService->charge($split['account_id'], $split['amount_cents'], ...);
    }
});
```

---

## 37. Offline-First POS Architecture

### 37.1 Overview

POS-ul trebuie să funcționeze **complet offline**. Conexiunea la internet e instabilă la festivaluri (mulți oameni, acoperire slabă). Arhitectura trebuie să fie offline-first, online-second.

### 37.2 Arhitectura device

```
┌──────────────────────────────────────────┐
│  POS Device (Tablet Android/iOS)          │
│                                           │
│  ┌─────────────────────────────────────┐  │
│  │  POS App (PWA sau Native)           │  │
│  │                                     │  │
│  │  ┌────────────┐  ┌──────────────┐  │  │
│  │  │ UI Layer   │  │ Sync Engine  │  │  │
│  │  │ (Vue/React)│  │              │  │  │
│  │  └──────┬─────┘  └──────┬───────┘  │  │
│  │         │               │           │  │
│  │  ┌──────┴───────────────┴───────┐  │  │
│  │  │     Local Business Logic     │  │  │
│  │  │  (charge, validate, queue)   │  │  │
│  │  └──────────────┬───────────────┘  │  │
│  │                 │                   │  │
│  │  ┌──────────────┴───────────────┐  │  │
│  │  │     SQLite / IndexedDB       │  │  │
│  │  │  - products cache            │  │  │
│  │  │  - wristband balance cache   │  │  │
│  │  │  - transaction queue         │  │  │
│  │  │  - shift data                │  │  │
│  │  └──────────────────────────────┘  │  │
│  └─────────────────────────────────────┘  │
│                                           │
│  ┌──────────┐  ┌───────────┐              │
│  │ NFC      │  │ Camera    │              │
│  │ Reader   │  │ (QR scan) │              │
│  └──────────┘  └───────────┘              │
└──────────────────────────────────────────┘
         ↕ (când e online)
    ┌─────────────┐
    │  Server API │
    └─────────────┘
```

### 37.3 Date locale pe device

**Ce se sincronizează de pe server → device (download):**

| Date | Frecvență sync | Stocare |
|------|---------------|---------|
| Produse vendor (nume, preț, categorie, is_age_restricted) | La fiecare online ping (sau manual) | SQLite |
| Combo-uri active | La fiecare sync | SQLite |
| Pricing rules (prețuri impuse) | La fiecare sync | SQLite |
| Lista wristband UIDs + balance cache | La fiecare sync (delta) | SQLite |
| Angajat curent + permisiuni | La login | SQLite |
| Cashless settings (limits, cooldowns) | La fiecare sync | SQLite |

**Ce se sincronizează de pe device → server (upload):**

| Date | Frecvență | Handling |
|------|-----------|----------|
| Tranzacții (charges, refunds) | Real-time dacă online, batch la reconnect | Queue + idempotent sync |
| Shift start/end | Real-time sau batch | Queue |
| Balance updates pe wristbands | Embedded în tranzacții | Server recalculează |

### 37.4 Offline charge flow

```
1. Employee scanează wristband (NFC/QR) → POS citește UID
2. POS verifică LOCAL:
   a. UID există în baza locală? (downloaded la sync)
   b. Balance cache suficient? (ultimul sold known)
   c. Wristband nu e disabled?
   d. Cooldown respectat? (ultima tranzacție locală > 10s)
   e. Age restriction ok? (date_of_birth din cache)

3. Dacă validare trece:
   a. Decrementare balance LOCAL (optimistic)
   b. Generare offline_ref unic: "{device_uid}-{timestamp}-{sequence}"
   c. Salvare tranzacție în LOCAL QUEUE cu status='pending_sync'
   d. Afișare succes pe ecranul POS
   e. Print/show receipt local

4. Când revine conexiunea:
   a. Sync engine trimite batch-ul de tranzacții pending
   b. Server procesează fiecare cu lockForUpdate()
   c. Server returnează status per tranzacție
   d. POS marchează tranzacțiile ca synced
   e. POS primește balance-uri actualizate de pe server → update cache local
```

### 37.5 Conflict resolution pe device

```
Scenariul: Client cu 50 RON plătește offline 30 RON la POS-A.
Între timp, plătește 30 RON la POS-B (tot offline, ambele au cache cu 50 RON).
La sync: server are sold real 50, primește -30 (POS-A) și -30 (POS-B) = -10 RON.

Rezolvare (server-side):
1. Prima tranzacție ajunsă: OK (50 - 30 = 20 RON)
2. A doua tranzacție: sold insuficient (20 < 30)
   → Marcare 'conflict' pe reconciliation
   → Tranzacția SE ACCEPTĂ totuși (sold devine -10 RON)
   → Flag pe CashlessAccount: negative_balance_allowed_until = now + 24h
   → Alertă admin: "Sold negativ pe contul {X}: -{10 RON}"
   → La următorul top-up al clientului, soldul negativ se recuperează automat

Rațiune: e mai bine să permitem sold negativ temporar decât să respingem
o tranzacție care deja s-a finalizat fizic (clientul a primit produsul).
```

### 37.6 Heartbeat & connectivity indicator

```
POS → Server: ping la fiecare 30 secunde
Răspuns: { "status": "ok", "server_time": "...", "pending_sync": 0 }

UI indicator pe POS:
🟢 Online (0 pending)
🟡 Online (5 pending sync)  
🔴 Offline (23 pending sync)
⚫ Offline >2h (alertă mare pe ecran)
```

---

## 38. SLA & Performance Targets

### 38.1 Performance Targets per operație

| Operație | Target | Max acceptable | Măsurare |
|----------|--------|----------------|----------|
| **POS charge** (online) | <150ms | <500ms | P95 response time |
| **POS charge** (offline local) | <50ms | <100ms | Local processing |
| **Top-up online** (card payment excluded) | <200ms | <800ms | P95 |
| **Balance check** | <100ms | <300ms | P95 |
| **Heatmap refresh** | <3s | <5s | Redis read + aggregation |
| **Report generation** (standard) | <2s | <5s | P95 |
| **Report generation** (complex/aggregate) | <10s | <30s | P95 |
| **Report export CSV** (10K rows) | <5s | <15s | Async job |
| **Report export PDF** | <10s | <30s | Async job |
| **Offline sync batch** (200 transactions) | <5s | <15s | P95 |
| **Customer profile calculation** | <500ms per client | <2s | Batch job |
| **Prediction forecast** (hourly sales) | <1s | <3s | P95 |
| **Anomaly detection scan** | <10s per run | <30s | 5-min job |
| **Map POI load** | <500ms | <1s | P95 |
| **Webhook delivery** (first attempt) | <2s | <5s | P95 |

### 38.2 Availability Targets

| Component | Target | Comentariu |
|-----------|--------|------------|
| **Financial operations** (charge, topup, cashout) | 99.95% | Maxim ~22 min downtime/lună |
| **POS API** | 99.9% | Offline-first compensează |
| **Heatmap & tracking** | 99.0% | Non-critical |
| **Reports** | 99.5% | Async, tolerabil |
| **Client app API** | 99.9% | |
| **Webhook delivery** | 99.5% | Retry compensează |
| **Database** | 99.99% | PostgreSQL replication |
| **Redis cache** | 99.9% | Fallback la DB dacă Redis down |

### 38.3 Scalability Targets

| Metric | Target | Scenariul |
|--------|--------|-----------|
| Concurrent POS devices | 500 | Festival mare (50 vendori × 10 POS) |
| Transactions per minute (peak) | 5,000 | Peak hour (500 POS × 10 tx/min) |
| Concurrent app users | 10,000 | Vizualizare sold + heatmap |
| Wristbands per edition | 100,000 | Festival mare |
| Total transactions per edition | 2,000,000 | 4 zile × 500K tx/zi |
| Offline sync batch (concurrent) | 50 | 50 POS-uri sync simultan |
| Report query (max rows scanned) | 10,000,000 | Aggregate pe toată ediția |

### 38.4 Optimizări necesare

| Zonă | Strategie |
|------|-----------|
| **POS charge latency** | Redis cache balance + optimistic locking; DB write async cu confirm |
| **Report queries** | Pre-calculated aggregates (cashless_report_snapshots), materialized views, partition by edition_id |
| **Heatmap** | Redis sorted sets per zonă, agregare in-memory |
| **Offline sync** | Batch insert cu ON CONFLICT (offline_ref) DO NOTHING (idempotent) |
| **Database scaling** | Read replicas pentru reports; primary doar pt. writes financiare |
| **Connection pooling** | PgBouncer pentru PostgreSQL (max 500 connections) |
| **Queue throughput** | Redis queue driver (nu database), 4+ workers dedicați |
| **CDN** | Static assets (map tiles, images) pe CloudFlare CDN |
| **Indexing** | Composite indexes pe: (edition_id, created_at), (account_id, transaction_type), (vendor_id, created_at) |

### 38.5 Monitoring & Alerting

**Metrici monitorizate (Prometheus/Grafana sau similar):**

```
cashless_charge_duration_seconds          (histogram)
cashless_topup_duration_seconds           (histogram)
cashless_active_pos_devices               (gauge)
cashless_transactions_per_minute          (counter rate)
cashless_offline_pending_count            (gauge per device)
cashless_negative_balance_accounts        (gauge)
cashless_report_generation_seconds        (histogram)
cashless_heatmap_refresh_seconds          (histogram)
cashless_redis_hit_rate                   (gauge)
cashless_db_connection_pool_usage         (gauge)
cashless_queue_depth                      (gauge)
cashless_webhook_delivery_success_rate    (gauge)
cashless_anomalies_active                 (gauge)
```

**Alerte automate:**

| Alertă | Condiție | Severitate |
|--------|----------|------------|
| Charge latency high | P95 > 500ms pentru 5 min | Warning |
| Charge latency critical | P95 > 2s pentru 2 min | Critical |
| Offline devices high | >10 POS-uri offline >30 min | Warning |
| Transaction rate drop | -50% vs. aceeași oră ieri | Warning |
| Queue depth high | >1000 jobs pending >5 min | Warning |
| Redis down | Connection failed | Critical |
| DB replication lag | >10s | Warning |
| Negative balance accounts | >5 conturi cu sold negativ | Warning |
| Webhook failures | >50% failure rate >10 min | Warning |

---

## 39. Fiscalizare (Bonuri Fiscale per Vendor)

### 39.1 Principii

- **Fiecare vânzare cashless generează bon fiscal** – emis de vendor pe CUI-ul propriu
- **Bonul se emite real-time** la momentul tranzacției (inclusiv offline – se generează local pe POS, se trimite la casa de marcat)
- **Top-up / Cashout NU generează bon fiscal** – se emite doar un bon nefiscal (chitanță informativă)
- **TVA per produs** – setat în admin festival pe fiecare produs; bonul conține breakdown TVA per cotă
- **Integrare casă de marcat fizică** – fiecare vendor are propria casă de marcat (modelul va fi definit ulterior)
- **Nu se trimite nimic la e-Factura ANAF** momentan – doar bonuri fiscale locale

### 39.2 Model: `CashlessFiscalReceipt`

**Tabel `cashless_fiscal_receipts`:**
```
id                      BIGINT PK AUTO
tenant_id               BIGINT FK
festival_edition_id     BIGINT FK
cashless_sale_id        BIGINT FK → cashless_sales NULL -- bon fiscal (legat de vânzare)
wristband_transaction_id BIGINT FK NULL -- bon nefiscal (topup/cashout)
vendor_id               BIGINT FK
vendor_cui              VARCHAR(20) -- snapshot CUI vendor la emitere
receipt_type            ENUM('fiscal','non_fiscal')
receipt_number          VARCHAR(100) -- nr. bon fiscal de pe casa de marcat
fiscal_device_id        VARCHAR(100) NULL -- ID casa de marcat
status                  ENUM('pending','printed','sent_to_device','confirmed','failed','voided')

-- Sume
subtotal_cents          INT -- total fără TVA
total_tax_cents         INT -- total TVA
total_cents             INT -- total cu TVA
currency                VARCHAR(3)

-- TVA breakdown
vat_breakdown           JSON -- [{"rate": 9.00, "base_cents": 1200, "tax_cents": 108}, {"rate": 19.00, "base_cents": 800, "tax_cents": 152}]

-- Items
items                   JSON -- [{"name": "Ursus 500ml", "qty": 2, "unit_price_cents": 1500, "total_cents": 3000, "vat_rate": 19.00}]

-- Timing
issued_at               TIMESTAMP -- când s-a emis
printed_at              TIMESTAMP NULL
voided_at               TIMESTAMP NULL
void_reason             TEXT NULL

-- Offline
is_offline              BOOLEAN DEFAULT false
offline_ref             VARCHAR(100) NULL
synced_at               TIMESTAMP NULL

meta                    JSON NULL
created_at              TIMESTAMP
updated_at              TIMESTAMP
```

### 39.3 Flow bon fiscal la vânzare (online)

```
1. CashlessSale se creează (charge reușit)
2. SaleService apelează FiscalReceiptService::generateForSale($sale)
3. Se construiește receipt-ul:
   a. Se iterează VendorSaleItems → items cu preț, cantitate, TVA
   b. Se calculează TVA breakdown per cotă (9%, 19% etc.)
   c. Se creează CashlessFiscalReceipt cu status='pending'
4. Se trimite comanda la casa de marcat (API/driver - TBD)
5. Casa de marcat confirmă → status='confirmed', receipt_number = nr. de pe bon
6. POS afișează/printează bonul
```

### 39.4 Flow bon fiscal offline

```
1. POS este offline → vânzare se face local
2. POS generează receipt LOCAL:
   - Items, TVA breakdown calculat pe device
   - offline_ref generat local
   - is_offline = true
   - Dacă casa de marcat e conectată local la POS → se printează direct
   - Dacă nu → receipt se pune în queue local
3. La reconectare:
   a. Sync tranzacții → server creează CashlessSale
   b. Sync receipts → server creează CashlessFiscalReceipt cu is_offline=true
   c. Dacă receipt-ul a fost deja printat local → status='confirmed'
   d. Dacă nu → se trimite la casă de marcat
```

### 39.5 Bon nefiscal (top-up / cashout)

```
La top-up:
  receipt_type = 'non_fiscal'
  items: [{"name": "Alimentare cont cashless", "total_cents": 10000}]
  Nu conține TVA

La cashout:
  receipt_type = 'non_fiscal'
  items: [{"name": "Retragere sold cashless", "total_cents": 5000}]
  Nu conține TVA
```

### 39.6 TVA calculat din produse

TVA per produs e setat pe `VendorProduct.vat_rate`. La generarea bonului:

```php
class FiscalReceiptService
{
    public function buildVatBreakdown(CashlessSale $sale): array
    {
        $byRate = [];
        foreach ($sale->items as $item) {
            $rate = $item->product->vat_rate ?? 19.00;
            $vatIncluded = $item->product->vat_included ?? true;
            
            if ($vatIncluded) {
                // Preț include TVA → extragem baza
                $baseCents = (int) round($item->total_cents / (1 + $rate / 100));
                $taxCents = $item->total_cents - $baseCents;
            } else {
                $baseCents = $item->total_cents;
                $taxCents = (int) round($baseCents * $rate / 100);
            }
            
            $key = (string) $rate;
            $byRate[$key] ??= ['rate' => $rate, 'base_cents' => 0, 'tax_cents' => 0];
            $byRate[$key]['base_cents'] += $baseCents;
            $byRate[$key]['tax_cents'] += $taxCents;
        }
        return array_values($byRate);
    }
}
```

### 39.7 Integrare casă de marcat (placeholder)

```php
// app/Services/Cashless/FiscalDevice/FiscalDeviceInterface.php
interface FiscalDeviceInterface
{
    public function printReceipt(CashlessFiscalReceipt $receipt): FiscalDeviceResponse;
    public function voidReceipt(string $receiptNumber): FiscalDeviceResponse;
    public function getStatus(): FiscalDeviceStatus;
    public function dailyReport(): FiscalDailyReport;
}

// Implementări viitoare per model de casă de marcat:
// app/Services/Cashless/FiscalDevice/Drivers/GenericEscPosDriver.php
// app/Services/Cashless/FiscalDevice/Drivers/DatecsDriver.php
// app/Services/Cashless/FiscalDevice/Drivers/TremolDriver.php
// etc.
```

Interfața e definită, driver-ele concrete se vor implementa când se stabilesc modelele de case de marcat.

### 39.8 Rapoarte fiscale

| Raport | Descriere |
|--------|-----------|
| Total bonuri emise per vendor/zi | Volum + valoare |
| TVA colectat per cotă per vendor | Breakdown 9% / 19% |
| Bonuri nule (voided) | Cu motiv |
| Bonuri offline nesinc | Status pending sync |
| Raport Z zilnic per casă | Sumar zilnic (ca raport Z fiscal) |

---

## 40. Multi-Stand per Vendor

### 40.1 Concept

Un vendor poate opera **mai multe standuri fizice** în festival. Fiecare stand:
- Are nume propriu ("Beer Corner - Stand Nord")
- Stoc propriu (dar vizibil agregat la nivel vendor)
- Poate avea meniu diferit (subset de produse, eventual prețuri diferite)
- Are propria casă de marcat (un fiscal device per stand)
- Are POS-uri (telefoane angajați) legate de stand
- Angajații pot rota între standuri de la o zi la alta

### 40.2 Model nou: `VendorStand`

**Tabel `vendor_stands`:**
```
id                      BIGINT PK AUTO
tenant_id               BIGINT FK
festival_edition_id     BIGINT FK
vendor_id               BIGINT FK → vendors
name                    VARCHAR(255) -- "Beer Corner - Stand Nord"
slug                    VARCHAR(255)
location                VARCHAR(255) NULL -- descriere text locație
location_coordinates    VARCHAR(100) NULL -- lat,lng
zone                    VARCHAR(100) NULL -- "Zona A", "VIP Area"
poi_id                  BIGINT FK → festival_points_of_interest NULL -- link pe hartă
fiscal_device_id        VARCHAR(100) NULL -- ID casa de marcat alocată
status                  ENUM('active','inactive','setup','closed') DEFAULT 'setup'
operating_hours         JSON NULL -- {"monday": {"open": "10:00", "close": "02:00"}, ...}
capacity                INT NULL -- nr. maxim angajați simultani
contact_phone           VARCHAR(50) NULL
meta                    JSON NULL
created_at              TIMESTAMP
updated_at              TIMESTAMP

UNIQUE (festival_edition_id, slug)
```

### 40.3 Modificări pe modele existente

**`VendorPosDevice` – câmp nou:**
```
+ vendor_stand_id        BIGINT FK → vendor_stands NULL
```

**`VendorShift` – câmp nou:**
```
+ vendor_stand_id        BIGINT FK → vendor_stands NULL
```

**`VendorSaleItem` – câmp nou:**
```
+ vendor_stand_id        BIGINT FK → vendor_stands NULL
```

**`CashlessSale` – câmp nou:**
```
+ vendor_stand_id        BIGINT FK → vendor_stands NULL
```

**`CashlessFiscalReceipt` – câmp nou:**
```
+ vendor_stand_id        BIGINT FK → vendor_stands NULL
```

**`InventoryStock` – modificare:**
```
Existent: UNIQUE (festival_edition_id, supplier_product_id, vendor_id)
Nou:      vendor_stand_id BIGINT FK → vendor_stands NULL
          -- vendor_id = stoc total vendor (agregat)
          -- vendor_stand_id = stoc per stand
Nou UNIQUE: (festival_edition_id, supplier_product_id, vendor_id, vendor_stand_id)
```

### 40.4 Meniu per stand

Un stand poate avea un subset de produse din catalogul vendor-ului, cu posibilitate de prețuri diferite.

**Tabel `vendor_stand_products`:**
```
id                      BIGINT PK AUTO
vendor_stand_id         BIGINT FK → vendor_stands
vendor_product_id       BIGINT FK → vendor_products
is_available            BOOLEAN DEFAULT true
override_price_cents    INT NULL -- NULL = folosește prețul standard din vendor_products
sort_order              INT DEFAULT 0
meta                    JSON NULL
created_at              TIMESTAMP
updated_at              TIMESTAMP

UNIQUE (vendor_stand_id, vendor_product_id)
```

**Logica:**
- Dacă `vendor_stand_products` nu are niciun entry pentru stand → stand-ul are TOATE produsele vendor-ului
- Dacă are entries → stand-ul are DOAR produsele listate
- Dacă `override_price_cents` IS NOT NULL → se folosește prețul custom, altfel prețul din `vendor_products.sale_price_cents`

### 40.5 Stoc per Stand

**Ierarhie stocuri:**
```
Festival (organizator)
  └── Vendor (agregat toate standurile)
       ├── Stand Nord (stoc propriu)
       ├── Stand Sud (stoc propriu)
       └── Stand VIP (stoc propriu)
```

**InventoryStock** are acum 3 niveluri:
- `vendor_id NOT NULL, vendor_stand_id IS NULL` → stoc agregat vendor (calcul automat = sumă standuri)
- `vendor_id NOT NULL, vendor_stand_id NOT NULL` → stoc per stand (sursa de adevăr)
- `vendor_id IS NULL, vendor_stand_id IS NULL` → stoc festival (organizator, nedistribuit)

### 40.6 Stock Transfer cu confirmare bilaterală

**Tabel `inventory_transfer_requests`:**
```
id                      BIGINT PK AUTO
tenant_id               BIGINT FK
festival_edition_id     BIGINT FK
supplier_product_id     BIGINT FK
quantity                DECIMAL(12,3)
unit_measure            VARCHAR(50)

-- Sursa
from_type               ENUM('festival','vendor','stand')
from_vendor_id          BIGINT FK NULL
from_stand_id           BIGINT FK → vendor_stands NULL

-- Destinație
to_type                 ENUM('vendor','stand')
to_vendor_id            BIGINT FK NULL
to_stand_id             BIGINT FK → vendor_stands NULL

-- Status
status                  ENUM('pending','accepted','rejected','cancelled','expired')
requested_by            VARCHAR(255) -- cine a inițiat
requested_at            TIMESTAMP
accepted_by             VARCHAR(255) NULL
accepted_at             TIMESTAMP NULL
rejected_by             VARCHAR(255) NULL
rejected_at             TIMESTAMP NULL
rejection_reason        TEXT NULL
expires_at              TIMESTAMP NULL -- expiră dacă nu e acceptat (ex: 2h)

-- Post-confirmare
inventory_movement_id   BIGINT FK → inventory_movements NULL -- creat la accept
notes                   TEXT NULL
meta                    JSON NULL
created_at              TIMESTAMP
updated_at              TIMESTAMP
```

### 40.7 Flow-uri Stock Transfer

**Scenariul 1: Organizator → Stand (marfa pleacă de la organizator)**
```
1. Admin festival creează transfer request:
   from_type='festival', to_type='stand', to_stand_id=5, quantity=100, product="Cola 330ml"
   → status='pending'
   → Notificare la vendor manager: "Organizatorul vrea să livreze 100x Cola la Stand Nord"

2. Vendor manager ACCEPTĂ:
   → status='accepted'
   → Se creează InventoryMovement: movement_type='allocation'
   → Stoc festival: quantity_allocated += 100
   → Stoc stand: quantity_total += 100
   → Stoc vendor (agregat): recalculat automat

3. Sau RESPINGE:
   → status='rejected', rejection_reason="Nu avem spațiu de depozitare"
```

**Scenariul 2: Stand ↔ Stand (transfer intern vendor)**
```
1. Manager Stand Nord: "Transfer 20x Ursus la Stand Sud"
   from_type='stand', from_stand_id=1, to_type='stand', to_stand_id=2
   → status='pending'
   → Notificare la Stand Sud

2. Manager/Supervisor Stand Sud ACCEPTĂ:
   → InventoryMovement cu from_vendor_id + to_vendor_id (sau stand IDs)
   → Stoc Stand Nord: -20
   → Stoc Stand Sud: +20
   → Stoc vendor (agregat): rămâne neschimbat (20 se mută doar între standuri)
```

**Scenariul 3: Vendor → Stand (vendorul are stoc centralizat, distribuie la standuri)**
```
1. Vendor manager alocă din stocul vendor (nealocat la niciun stand) → Stand Nord
   from_type='vendor', from_vendor_id=3, to_type='stand', to_stand_id=1
   → status='pending'
   → Dacă managerul e cel care face și primește → auto-accept

2. Accept → InventoryMovement, update stocuri
```

### 40.8 Retur de marfă (end-of-festival)

```
La final festival, organizatorul trebuie să știe cât a rămas per stand și per vendor.

1. Admin festival: "Raport stoc final"
   → Per stand: ce cantitate rămâne din fiecare produs
   → Per vendor: sumă pe toate standurile
   → Per supplier: cât a fost livrat - cât s-a vândut - cât se returnează

2. Retur: organizatorul inițiază transfer request invers:
   from_type='stand', to_type='festival'
   → Stand confirmă cantitatea reală (poate diferi de teoretic!)
   → Diferențe = pierderi/waste, logate separat
```

### 40.9 Angajați și standuri

Angajații sunt legați de **vendor** (relație permanentă) dar lucrează la un **stand** (prin shift).

**`VendorShift.vendor_stand_id`** indică la ce stand lucrează angajatul în acel shift.

```
Zi 1: Ion lucrează la Stand Nord (shift cu vendor_stand_id=1)
Zi 2: Ion lucrează la Stand Sud (shift cu vendor_stand_id=2)
```

Un angajat NU poate avea 2 shift-uri active simultan la standuri diferite.

### 40.10 POS ca aplicație mobilă

POS-ul = aplicația mobilă instalată pe telefonul angajatului. La login/start shift:
1. Angajatul se loghează în app cu email + parolă
2. Selectează stand-ul la care lucrează azi
3. POS-ul se leagă automat de `vendor_stand_id`
4. Toate tranzacțiile au `vendor_stand_id` setat
5. Toate bonurile fiscale se emit pe casa de marcat a standului respectiv

**`VendorPosDevice`** poate fi:
- Device fix (tabletă la stand) → `vendor_stand_id` setat permanent
- Device mobil (telefon angajat) → `vendor_stand_id` setat la start shift, poate schimba

### 40.11 Rapoarte per stand

Toate rapoartele din secțiunea 6 care au filtru "per vendor" primesc și filtru **"per stand"**:

| Raport | Granularitate nouă |
|--------|-------------------|
| Vânzări totale | Per stand + per vendor (agregat) |
| Vânzări per produs | Per stand |
| Vânzări per angajat | Per stand (unde a lucrat) |
| Stoc curent | Per stand + per vendor (agregat) |
| Comisioane | Per vendor (nu per stand – CUI unic) |
| Raport fiscal | Per stand (casă de marcat per stand) |
| Performance comparison | Stand vs. Stand al aceluiași vendor |

---

## 43. Interacțiunea Combo + Split Payment

### 43.1 Scenarii posibile

| Scenariul | Descriere | Rezolvare |
|-----------|-----------|-----------|
| Combo simplu, plată unică | 1 client cumpără un combo | Normal: combo price se aplică, 1 charge |
| Combo + split egal | 2 clienți împart un combo | Combo price / 2 per persoană |
| Combo + split per item | 2 clienți, fiecare plătește ce-a ales | Combo discount se distribuie proporțional pe items |
| Multiple combos + split | 3 clienți, 2 combos + items individuale | Combo-urile se calculează mai întâi, apoi split |
| Combo parțial (doar unele items) | 3 items în coș, 2 formează combo, 1 nu | Combo pe 2 items, preț normal pe al 3-lea |

### 43.2 Ordinea de calcul la POS

```
1. Employee adaugă produse în coș
2. SaleService::detectCombos() → identifică combo-uri aplicabile
3. Se calculează prețul final (cu combo-uri aplicate)
4. Employee alege [PLATĂ NORMALĂ] sau [SPLIT]
5. Dacă split:
   a. Split egal: total_with_combos / nr_persoane
   b. Split per item: fiecare item are prețul lui (cu combo discount distribuit)
   c. Split custom: sume manuale (suma trebuie = total_with_combos)
6. Se procesează charges
```

### 43.3 Distribuirea discount-ului combo la split per item

```
Exemplu:
  Ursus 500ml: 15 RON (ales de Client A)
  Hot Dog:     12 RON (ales de Client B)
  Combo: 22 RON (saving 5 RON)

  Distribuire proporțională:
  Client A: 15/27 * 22 = 12.22 RON
  Client B: 12/27 * 22 =  9.78 RON
  Total:                  22.00 RON ✓
```

### 43.4 Restricții

- Un combo nu poate fi split între mai multe sale-uri separate (e tot o singură CashlessSale)
- Age-restricted items din combo: se verifică TOȚI participanții la split (toți trebuie să fie adulți)
- Combo savings se loghează pe CashlessSale.meta pentru raportare

---

## 44. Offline Queue Priority + Disaster Recovery

### 44.1 Prioritizare queue offline pe POS

Când POS-ul revine online, tranzacțiile se sincronizează în ordinea priorității:

| Prioritate | Tip tranzacție | Motiv |
|-----------|---------------|-------|
| 1 (HIGH) | Charges (plăți) | Bani deja încasați fizic, trebuie înregistrate |
| 2 (HIGH) | Refunds | Bani returnați, trebuie reflectat în sold |
| 3 (MEDIUM) | Shift close | Afectează rapoartele |
| 4 (MEDIUM) | Stock movements | Stocul trebuie actualizat |
| 5 (LOW) | Heartbeats | Status updates |
| 6 (LOW) | Analytics events | Location pings, etc. |

```
// POS Sync Engine - pseudocod
class SyncEngine {
    async sync() {
        const queue = await LocalDB.getPendingTransactions();
        
        // Sort by priority, then by timestamp
        queue.sort((a, b) => {
            if (a.priority !== b.priority) return a.priority - b.priority;
            return a.timestamp - b.timestamp;
        });
        
        // Sync in batches of 50
        for (const batch of chunk(queue, 50)) {
            const result = await api.syncBatch(batch);
            await LocalDB.markSynced(result.synced);
            await LocalDB.markConflict(result.conflicts);
        }
    }
}
```

### 44.2 Queue overflow (storage limitat)

```
Limită: max 10.000 tranzacții în queue local

Dacă queue e 80% plin (8.000):
  → Alertă pe ecran POS: "⚠️ Sync urgent - conectează la WiFi"

Dacă queue e 95% plin (9.500):
  → Alertă critică: "🔴 Spațiu aproape plin. Sincronizează ACUM."
  → Dezactivare analytics/heartbeat (eliberare spațiu)

Dacă queue e 100%:
  → POS continuă să funcționeze (nu blochează vânzările!)
  → Cele mai vechi tranzacții LOW priority se șterg pentru a face loc
  → Flag: data_loss_risk = true → alertă admin la sync
```

### 44.3 Disaster Recovery financiar

**Scenariul: Database primară pică în mijlocul festivalului.**

**Strategie pe 3 niveluri:**

```
Nivel 1: Read Replica Failover (< 30 secunde)
  - PostgreSQL streaming replication → read replica promovată la primary
  - Replication lag maxim acceptat: 10 secunde
  - Pierdere maximă date: ultimele 10s de tranzacții
  - POS-urile offline nu sunt afectate (continuă local)

Nivel 2: Point-in-Time Recovery (< 15 minute)
  - WAL archiving continuu (la fiecare 1 minut)
  - Restore la orice punct din ultimele 24h
  - Se folosește când replica e compromisă

Nivel 3: Full Backup Restore (< 1 oră)
  - Backup complet zilnic (noaptea, în afara peak)
  - Stocat pe S3/external storage
  - Ultimul resort
```

**Backup schedule în timpul festivalului:**
```
- WAL archive: continuu (la fiecare 60 secunde)
- Incremental backup: la fiecare 4 ore
- Full backup: zilnic la 05:00 (post-peak)
- Transaction log export (CSV): la fiecare oră → S3 (safety net)
```

**Redis failure:**
```
Dacă Redis pică:
  - Heatmaps: indisponibile temporar (non-critical)
  - Rate limiting: fallback la in-memory (per-process, mai puțin precis)
  - Report cache: fallback la DB queries directe (mai lente)
  - Balance cache: fallback la DB (lockForUpdate() funcționează oricum)
  → Redis NU este single point of failure pentru operații financiare
```

---

## 45. Vendor Onboarding + End-of-Festival Checklist

### 45.1 Vendor Onboarding Flow (pas cu pas)

```
ETAPA 1: CREARE VENDOR (Admin Festival)
├── Admin creează vendor în Filament (nume, CUI, date companie)
├── Admin creează primul VendorEmployee cu rol=manager
├── Sistem trimite email invitație la manager (set password link)
└── Status vendor: 'onboarding'

ETAPA 2: SETUP VENDOR (Manager)
├── Manager setează parola, se loghează în Vendor Portal
├── Manager completează profilul companiei (dacă nu e complet)
├── Manager adaugă produse (manual sau CSV import)
├── Manager creează categorii produse
├── Manager adaugă staff (supervisori, members)
└── Status vendor: 'pending_approval'

ETAPA 3: APROBARE (Admin Festival)
├── Admin revizuiește: lista produse, prețuri, date companie
├── Admin verifică pricing rules respectate (prețuri impuse OK)
├── Admin verifică produse age-restricted marcate corect
├── Admin aprobă vendor → status: 'approved'
└── Dacă probleme → admin respinge cu feedback → vendor revizuiește

ETAPA 4: SETUP EDIȚIE (Admin Festival)
├── Admin asociază vendor cu ediția (VendorEdition)
├── Admin setează: comision, locație stand(uri), zone acces
├── Admin alocă stoc de la suppliers (dacă e cazul)
├── Admin alocă casa de marcat per stand
└── Status vendor edition: 'ready'

ETAPA 5: GO LIVE (Ziua festivalului)
├── Manager/staff se loghează pe POS-uri
├── Staff-ul startează shift-uri
├── Primele vânzări
└── Status vendor edition: 'active'
```

**Câmp nou pe `VendorEdition`:**
```
+ onboarding_status     ENUM('onboarding','pending_approval','approved','ready','active','completed','suspended')
+ approved_at           TIMESTAMP NULL
+ approved_by           BIGINT FK → users NULL
+ go_live_at            TIMESTAMP NULL
```

### 45.2 End-of-Festival Checklist

Procedura completă de închidere, executată ca un wizard în Filament.

**Tabel `festival_closure_checklists`:**
```
id                      BIGINT PK AUTO
tenant_id               BIGINT FK
festival_edition_id     BIGINT FK
status                  ENUM('not_started','in_progress','completed')
started_at              TIMESTAMP NULL
completed_at            TIMESTAMP NULL
started_by              BIGINT FK → users NULL
steps                   JSON -- status fiecare pas
meta                    JSON NULL
created_at              TIMESTAMP
updated_at              TIMESTAMP
```

**Pașii din checklist:**

```
FAZA 1: STOP OPERATIONS
☐ 1.1 Anunță vendorii: "Ultimele 30 minute de vânzări"
☐ 1.2 Stop top-ups (dezactivare online + fizic)
☐ 1.3 Stop vânzări (dezactivare POS charge)
☐ 1.4 Force-close toate shift-urile active

FAZA 2: RECONCILIERE
☐ 2.1 Forțează sync pe toate POS-urile offline
☐ 2.2 Așteaptă sincronizare completă (0 pending)
☐ 2.3 Rulează reconciliere finală (rezolvare conflicte)
☐ 2.4 Verifică: 0 tranzacții nereconciliate

FAZA 3: STOCURI
☐ 3.1 Inventar final per stand (vendor confirmă cantități reale)
☐ 3.2 Calcul diferențe stoc (teoretic vs real)
☐ 3.3 Logare pierderi/waste
☐ 3.4 Inițiere retur marfă:
       - Stoc de la organizator → retur la organizator
       - Stoc de la supplier → retur la supplier
☐ 3.5 Confirmare retur din ambele părți

FAZA 4: FINANCE
☐ 4.1 Generare VendorFinanceSummary final per vendor
☐ 4.2 Generare raport fiscal final (bonuri, TVA)
☐ 4.3 Calcul payouts per vendor (net_sales - commissions - fees + tips)
☐ 4.4 Revizuire payouts de către admin
☐ 4.5 Aprobare payouts → creare vendor_payouts cu status 'approved'
☐ 4.6 Executare transferuri bancare
☐ 4.7 Confirmare transferuri

FAZA 5: CASHOUT CLIENȚI
☐ 5.1 Notificare clienți: "Festivalul s-a terminat. Solicită cashout în {N} zile."
☐ 5.2 Activare cashout online (dacă nu era deja)
☐ 5.3 Procesare cereri cashout (daily job)
☐ 5.4 Reminder clienți la {N/2} zile: "Mai ai {sold} în cont"
☐ 5.5 Reminder final la {N-3} zile: "Ultimele 3 zile pentru cashout"
☐ 5.6 La expirare deadline: solduri rămase → revenue festival

FAZA 6: RAPOARTE FINALE
☐ 6.1 Generare raport final complet (PDF)
☐ 6.2 Raport per vendor (trimis fiecărui vendor pe email)
☐ 6.3 Raport financiar agregat
☐ 6.4 Raport customer insights
☐ 6.5 Raport stocuri (livrat vs vândut vs returnat vs pierderi)

FAZA 7: CLEANUP
☐ 7.1 Dezactivare toate wristband-urile active
☐ 7.2 Închidere toate CashlessAccount-urile (status='closed')
☐ 7.3 Marcare ediție ca 'completed'
☐ 7.4 Arhivare date (mută în cold storage dacă e cazul)
```

### 45.3 Cashout deadline configurabil

**Câmpuri noi pe `CashlessSettings`:**
```
+ cashout_deadline_days         INT DEFAULT 30 -- zile după end_date ediție
+ cashout_reminder_days         JSON DEFAULT '[15, 7, 3, 1]' -- când se trimit remindere
+ unclaimed_balance_action      ENUM('revenue','donate','hold') DEFAULT 'revenue'
+ unclaimed_balance_note        TEXT NULL -- "Soldurile nerevendicate devin venituri festival"
```

**Flow automat:**
```
1. Festival se termină (end_date)
2. Job zilnic verifică: e ziua de reminder? → trimite notificare
3. La deadline: 
   - unclaimed_balance_action = 'revenue' → soldurile rămase se marchează ca revenue
   - CashlessAccount.status = 'closed'
   - WristbandTransaction: type='unclaimed_closure', amount = remaining balance
```

---
