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

*Plan complet: 34 secțiuni acoperind 10 cerințe originale + 13 îmbunătățiri. 28 tabele noi, 11 modificate, 14+ enums. 10 faze de implementare, ~20 săptămâni. 70+ rapoarte inclusiv predictive. Live tracking + heatmaps. Anomaly detection.*

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
