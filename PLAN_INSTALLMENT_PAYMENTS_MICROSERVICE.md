# Installment Payments Microservice — Implementation Plan

Plată în rate ("buy-now-pay-later" propriu) pentru orice marketplace și orice
procesator de plată deja integrat (Netopia, Stripe, EuPlatesc, PayU).

Exemplu de referință: **ambilet.ro** (MarketplaceClient) lucrează cu **Netopia**
și vrea să ofere plata în rate în checkout, cu planuri configurabile din admin.

---

## 1. Obiective & cerințe (din brief)

| # | Cerință | Cum o rezolvăm |
|---|---------|----------------|
| 1 | Valoare inițială de plată (avans) | Setat **per eveniment** în panoul de eveniment (`event_installment_configs`), percent/fix, pe `customer_total` |
| 2 | Timp între rate ȘI/SAU dată fixă + procent | `schedule_type` = `interval` \| `fixed_dates`; distribuție `equal` \| `custom_percent` |
| 3 | Total în rate > plata directă | `surcharge` marketplace obligatoriu > 0 (validare hard); garantat de `InstallmentPlanCalculator` |
| 4 | Tixello 2% (vs 1%) și surcharge marketplace (procentual/fix) | `platform_fee_percent` global = 2% la rate, colectat **de la marketplace**; `surcharge_percent`/`surcharge_fixed_cents` per plan, încasat de marketplace |
| 5 | Debitări automate + mailuri | Motor cu token recurent (Netopia/Stripe); scheduler + Jobs + email templates |
| 6 | Client nu mai plătește / retur | Dunning + default policy; taxe nereturnabile; integrare `MarketplaceRefundRequest` / `PaymentRefundService` |
| 7 | Trebuie achitat integral înainte de eveniment | Validare `ultima scadență <= event_date − buffer`; planuri neeligibile ascunse în checkout |

---

## 2. Model financiar (toate valorile în bani/cents, integer)

### Definiții

Există **două fluxuri de bani separate**: ce plătește clientul (customer-facing)
și ce decontează Tixello cu marketplace-ul (B2B). Taxa Tixello **NU** se adaugă la
ce plătește clientul.

**A. Customer-facing (ce plătește clientul)**
```
base_total_cents        = ce ar plăti clientul dacă plătește direct
                          (ticket_gross + comision_marketplace + processing_fee + asigurare)

surcharge_cents         = markup-ul marketplace-ului pentru rate (percent ȘI/SAU fix)
                          — OBLIGATORIU > 0, singurul lucru care face totalul > plata directă
                          — încasat integral de marketplace

customer_total_cents    = base_total_cents + surcharge_cents

down_payment_cents      = avans la checkout (percent din customer_total / fix / 0)
financed_cents          = customer_total_cents − down_payment_cents
```

**B. B2B settlement (Tixello ↔ marketplace) — nu apare în checkout**
```
platform_fee_percent    = 2.0 pentru comenzi cu rate   (vs 1.0 default fără rate)
platform_fee_cents      = ceil(base_total_cents * platform_fee_percent / 100)
                        → colectat DE LA marketplace (nu de la client), prin
                          MarketplaceTransaction; reduce available_balance-ul marketplace-ului.
```
Practic, opțiunea de rate costă marketplace-ul **+1% peste default** (2% în loc de 1%).
Marketplace-ul își acoperă acest cost din `surcharge`-ul pe care îl încasează de la client.

### Garanția "customer_total > plata directă"

`InstallmentPlanCalculator` refuză să emită un plan dacă `surcharge_cents <= 0`
(deci `customer_total_cents <= base_total_cents`). Validare hard + test.

### Împărțirea pe rate (rounding determinist)

- `financed_cents` se împarte pe N rate după `distribution`:
  - `equal`: `floor(financed/N)` pentru fiecare rată, **restul (bani rămași) se adaugă
    la ultima rată** → suma ratelor == financed_cents exact.
  - `custom_percent`: fiecare rată = `floor(financed * pct_i / 100)`, restul la ultima.
- Fără floats: totul cu `intdiv`/`floor` pe cenți. Suma verificată prin assert în teste.

### Fee-uri: cine încasează

- **surcharge_cents** → integral **venit marketplace** (ambilet). Singurul cost adăugat clientului.
- **platform_fee_cents (2% la rate)** → venit **Tixello**, colectat **de la marketplace**,
  nu de la client. Decontat prin `MarketplaceTransaction` (`commission`/`adjustment`).
- Ambele au **snapshot pe agreement** (ca `processing_fee_*` pe Order) → imutabile chiar
  dacă se schimbă configul/rata ulterior.

---

## 3. Constrângerea datei evenimentului

La generarea preview-ului planului:

```
last_due_date = data ultimei rate calculate
require: last_due_date <= event_date − days_before_event_fully_paid
```

- `days_before_event_fully_paid` e configurabil per plan (default 0 = fix înainte de eveniment).
- Dacă planul NU încape (eveniment prea aproape pentru N rate la intervalul dat) → planul
  este **eligibil = false** și nu apare în checkout pentru acel eveniment.
- Opțional `compress_schedule = true`: dacă nu încape la intervalul standard, motorul
  scurtează intervalul ca să încapă exact înainte de eveniment (dacă tot nu încape → ascuns).

---

## 4. Schema bazei de date

### Unde se configurează ce (decizie stakeholder)

- **Modulul de Planuri de rate** (fereastră/resursă separată) — aici marketplace-ul
  **creează și întreține șabloanele de plan** (număr de rate, grafic, surcharge,
  eligibilitate, politici). Reutilizabile pe mai multe evenimente.
- **Panoul de administrare al evenimentului** (secțiune nouă în `EventResource`) — aici,
  per eveniment, marketplace-ul: **(a)** activează plata în rate, **(b)** alege care
  plan(uri) din modul se aplică acelui eveniment, **(c)** setează **avansul** pentru
  acel eveniment. Avansul stă pe eveniment tocmai ca același plan să poată avea avansuri
  diferite pe evenimente diferite.

### 4.1 `installment_plans` (șabloane definite în modulul de planuri)

```
id (uuid, PK)
marketplace_client_id (FK, nullable)   // sau tenant_id pentru tenanți non-marketplace
tenant_id (FK, nullable)
name (json, translatable)
slug (string)
description (json, translatable, nullable)
is_active (bool)
sort_order (int)
currency (string)

-- NB: AVANSUL nu se ține pe plan. Se setează per eveniment (vezi 4.5), pentru că
-- același plan se poate refolosi pe evenimente cu avansuri diferite.
-- Opțional: down_payment_default_* ca sugestie pre-completată în panoul de eveniment.
down_payment_default_type (enum: none, percent, fixed, nullable)
down_payment_default_value (int, nullable)

-- Programare rate
number_of_installments (int)           // fără avans
schedule_type (enum: interval, fixed_dates)
interval_unit (enum: day, week, month) // pentru interval
interval_count (int)                   // ex: la 30 zile => unit=day, count=30
fixed_dates (json, nullable)           // ["2026-03-01", ...] pentru fixed_dates
distribution (enum: equal, custom_percent)
installments_percentages (json, nullable) // [40,30,30] pentru custom_percent

-- Costuri (markup marketplace către client — percent ȘI/SAU fix, sumă > 0)
surcharge_percent (int, default 0)     // percent*100
surcharge_fixed_cents (int, default 0)
-- platform_fee_percent NU se ține aici; e global (config/installments.php),
-- 2% pentru rate vs 1% default, colectat de la marketplace.

-- Eligibilitate
min_order_cents (int, nullable)
max_order_cents (int, nullable)
days_before_event_fully_paid (int, default 0)
compress_schedule (bool, default false)
eligibility (json, nullable)           // scope evenimente / ticket types / categorii

-- Politici
-- ticket_issuance: biletul se emite imediat dar INVALID până la plata integrală
ticket_issuance_policy (enum: issue_invalid_until_paid)  // default & singura pt. v1
default_policy (json)                  // grace_days, max_retries, retry_backoff, forfeit
refund_policy (json)                   // fee-uri nereturnabile: surcharge + platform_fee
terms_url (string, nullable)

created_at, updated_at, deleted_at (soft delete)
Index: (marketplace_client_id, is_active), (tenant_id, is_active)
```

### 4.2 `installment_agreements` (plan concret atașat unei comenzi)

```
id (uuid, PK)
marketplace_client_id (FK, nullable)
tenant_id (FK, nullable)
order_id (FK → orders)
installment_plan_id (FK → installment_plans, nullable on delete)
marketplace_customer_id (FK, nullable)
customer_email (string)
customer_name, customer_phone (string, nullable)

event_id (FK, nullable)
event_date (datetime, nullable)        // snapshot

currency (string)
base_total_cents (int)                 // plata directă
surcharge_cents (int)                  // markup marketplace (customer-facing)
customer_total_cents (int)             // base + surcharge = ce plătește clientul
platform_fee_cents (int)               // Tixello 2%, colectat de la marketplace (B2B, nu de la client)
platform_fee_percent (decimal snapshot)
down_payment_cents (int)
financed_cents (int)

number_of_installments (int)
paid_installments_count (int, default 0)
next_due_at (datetime, nullable)

status (enum: pending, active, completed, defaulted, cancelled, refunded)
ticket_issuance_policy (enum snapshot)

-- Auto-debit
provider (string, nullable)            // netopia/stripe/...
payment_method_id (FK → marketplace_customer_payment_methods, nullable)
mandate_reference (string, nullable)   // token recurent / mandate id
auto_debit_enabled (bool, default false)

plan_snapshot (json)                   // termeni înghețați ai planului
metadata (json, nullable)

created_at, updated_at
Index: status, next_due_at, order_id, (marketplace_client_id, status)
```

### 4.3 `installment_payments` (rândurile de plată din grafic)

```
id (uuid, PK)
installment_agreement_id (FK)
sequence (int)                         // 0 = avans, 1..N = rate
due_date (datetime)
amount_cents (int)
principal_cents (int)                  // opțional breakdown
fee_cents (int)                        // porțiunea de fee/surcharge alocată

status (enum: scheduled, due, processing, paid, failed, retrying, waived, refunded, cancelled)
paid_at (datetime, nullable)
paid_amount_cents (int, nullable)
payment_reference (string, nullable)   // tx id procesator
attempts (int, default 0)
last_attempt_at (datetime, nullable)
last_error (string, nullable)
reminder_sent_at (datetime, nullable)
dunning_stage (int, default 0)
pay_link_token (string, nullable)      // pentru mod fallback
metadata (json, nullable)

created_at, updated_at
Index: (installment_agreement_id, sequence), (status, due_date)
```

### 4.4 `installment_events` (audit log)

```
id, installment_agreement_id, installment_payment_id (nullable),
type (created|charged|failed|retried|reminder_sent|defaulted|cancelled|refunded|completed),
message, payload (json), created_at
```

### 4.5 `event_installment_configs` (configul per eveniment — setat în panoul de eveniment)

```
id (uuid, PK)
event_id (FK → events, nullable)              // sau marketplace_event_id
marketplace_event_id (FK, nullable)
enabled (bool, default false)                 // (a) activează rate pt. acest eveniment
down_payment_type (enum: none, percent, fixed)// (c) avansul pt. acest eveniment
down_payment_value (int)
notes (string, nullable)
created_at, updated_at
Unique: (event_id) / (marketplace_event_id)
```

```
-- (b) care planuri se aplică evenimentului
event_installment_plan (pivot)
  event_installment_config_id (FK)
  installment_plan_id (FK)
  sort_order (int)
  is_active (bool)
```

La checkout, planurile eligibile = planurile atașate evenimentului (via pivot) **∩**
regulile de eligibilitate ale planului (min/max, fit-before-event). Avansul aplicat vine
din `event_installment_configs.down_payment_*`, calculat pe `customer_total_cents`.

### 4.6 Câmpuri adăugate pe modele existente

- `orders`: `installment_agreement_id` (nullable), `payment_status` capătă `partially_paid`.
- `tickets`: status nou `pending_installments` (emis dar invalid) → flip la `valid`/`invalidated`.
- Reminder-ele și dunning-ul refolosesc `MarketplaceEmailTemplate` (i18n, editabil per marketplace).

> Decontarea financiară refolosește `MarketplaceTransaction` existent
> (SALE / COMMISSION / REFUND) — nu reinventăm ledger-ul.

---

## 5. Servicii (App\Services\Installments)

| Serviciu | Rol |
|----------|-----|
| `InstallmentPlanCalculator` | **Pur, stateless, cents-int.** Din `base_total`, plan, `event_date` → quote complet: `{schedule[], down_payment, platform_fee, marketplace_fee, surcharge, total, fits_before_event, eligible}`. Rounding determinist. Oglindă a `ProcessingFeeCalculator`. |
| `InstallmentEligibilityService` | Filtrează planurile eligibile pentru un cart/eveniment/sumă (min/max, fit-before-event, scope). |
| `InstallmentAgreementService` | Creează agreement + grafic la checkout; înghețare snapshot; tranziții de status. |
| `InstallmentChargeService` | Debitare rată: token recurent sau pay-link; idempotent per `installment_payment.id`; retry/backoff; scrie `MarketplaceTransaction`. |
| `InstallmentDunningService` | Reminder-e, escaladare, marcare default, revocare bilete conform politicii. |
| `InstallmentRefundService` | Retur: calculează cât s-a plătit efectiv, aplică `refund_policy` (fee-uri nereturnabile), anulează ratele rămase, revocă mandatul, deleagă la `PaymentRefundService`/`MarketplaceRefundRequest`. |

---

## 6. Dependență critică: tokenizare / plăți recurente off-session

Procesoarele actuale **nu** suportă debitare off-session. Extindem contractul:

```php
interface PaymentProcessorInterface {
    // ... metode existente ...
    public function supportsTokenization(): bool;
    // La plata avansului: cere salvarea unui token/mandat reutilizabil
    public function createPaymentWithMandate(array $data): array; // + mandate_reference în callback
    // Debitare ulterioară fără client prezent
    public function chargeWithToken(string $mandateReference, array $data): array;
}
```

Implementări **pentru v1 (decizia luată): doar Stripe + Netopia**:
- **Netopia (mobilPay):** plăți recurente pe bază de token (recurring/token) — avansul
  tokenizează, ratele se debitează cu token-ul. (Provider principal ambilet.)
- **Stripe:** `SetupIntent` (sau PaymentIntent `setup_future_usage=off_session`) la avans +
  `PaymentIntent off_session` la fiecare rată; token = `payment_method` + `customer`.
- **EuPlatesc / PayU:** `supportsTokenization() = false` → opțiunea de rate **nu apare**
  în checkout pentru marketplace-urile pe aceste procesoare. Fallback payment-link amânat
  post-v1 (când/dacă se adaugă aceste procesoare).

Token-ul se salvează în `MarketplaceCustomerPaymentMethod` (deja are câmpurile necesare),
iar `mandate_reference` pe agreement.

---

## 7. Motor de debitare automată & scheduler

**Command:** `installments:process-due` (rulează prin `schedule:run`, ex. orar).

Flux:
1. Selectează `installment_payments` cu `status ∈ {scheduled, due, retrying}` și `due_date <= now`.
2. Pentru fiecare → dispatch `ChargeInstallmentJob` (queue, idempotent, lock pe payment id).
3. **Mod token:** `InstallmentChargeService->charge()` → `chargeWithToken()`.
   - Succes → `paid`, incrementează `paid_installments_count`, `MarketplaceTransaction`,
     email chitanță; dacă e ultima → `agreement.status = completed` → biletul devine `valid`.
   - Eșec → `attempts++`, reprogramează retry (backoff configurabil: ex. +1z, +3z, +5z);
     după `max_retries` → `failed` → `InstallmentDunningService`.
4. **3-D Secure / SCA:** dacă debitarea off-session cere autentificare (vezi §16.1) →
   trimite clientului link on-session pentru acea rată; nu se marchează eșec direct.

**Jobs:** `ChargeInstallmentJob`, `SendInstallmentReminderJob`, `HandleInstallmentDefaultJob`.

Toate idempotente (guard pe status + `attempts`) ca să reziste la retrigger/webhook dublu.

---

## 8. Emailuri / reminder-e / notificări

Prin `MarketplaceEmailService` / `TenantMailService` + `MarketplaceEmailTemplate`
(șabloane editabile per marketplace, i18n RO/EN). Toate au token de deep-link către portalul
clientului (grafic, update card, plată manuală).

### Ciclul de emailuri per agreement
1. **Confirmare plan + grafic complet** (la checkout: avans plătit, următoarele N rate cu date/sume, `customer_total`, diferența vs plata directă).
2. **Chitanță rată plătită** (după fiecare debitare reușită).
3. **Rată eșuată + retry programat** (cu link update card / plată manuală / autentificare 3DS).
4. **Avertisment default** (ultima șansă înainte de anulare — corelat cu deadline-ul evenimentului).
5. **Plan finalizat** (biletul a devenit valid).
6. **Retur / anulare** (sumă returnată, taxe reținute).
7. **Update card necesar** (card expiră înainte de o rată — vezi §16.2).

### Reminder-e programate (cerință explicită) — `installments:send-reminders` (zilnic)
- **Cadență configurabilă** per plan/marketplace: ex. `reminder_days_before = [7, 3, 1]` →
  câte un email înainte de fiecare scadență.
- **Reminder în ziua scadenței** (mai ales pentru fluxul cu debitare automată: "azi îți
  debităm rata X").
- **Reminder post-eșec / restanță** escaladat (dunning): la +1, +3, +5 zile după rată ratată,
  cu ton crescător și menționarea deadline-ului față de eveniment.
- Anti-spam: `reminder_sent_at` per `installment_payment` previne dublurile la re-rulare.
- **Multi-canal opțional** (§16.7): aceleași trigger-e pot merge și pe SMS/WhatsApp prin
  serviciile existente, dacă marketplace-ul le are activate.

---

## 9. Neplată (default) & retur

### 9.1 Politica de emitere bilete (cheie pentru deadline eveniment)

- **`issue_invalid_until_paid` (decizia luată):** biletul se **emite de la început, dar cu
  status invalid** (nu poate fi scanat/folosit) și devine **valid automat** doar când
  agreement-ul trece în `completed` (toate ratele plătite). Clientul are biletul în cont
  imediat, dar nu îl poate folosi până la achitarea integrală → elimină riscul "bilet valid neplătit".
- Implementare: biletul primește un status dedicat (ex. `pending_installments`) în loc de
  `valid`; `Order.payment_status = partially_paid`. La `completed` → flip la `valid`.
  La `defaulted` → flip la `invalidated`/`cancelled` + eliberare loc.

### 9.2 Default (neplată)

- Grace period + retries (din `default_policy`).
- La depășire: `agreement.status = defaulted` → anulează ratele rămase (`cancelled`),
  revocă biletele dacă erau emise, eliberează locurile (integrare `SeatHold`/inventar),
  reține avansul conform `forfeit` policy, loghează `installment_events`.
- **Hard stop legat de eveniment:** dacă `event_date` se apropie și planul nu e completat,
  dunning-ul se accelerează (deadline = `event_date − buffer`); după deadline → default automat.

### 9.3 Retur (refund)

- Reutilizează `MarketplaceRefundRequest` + `PaymentRefundService`.
- `InstallmentRefundService` calculează **suma plătită efectiv** (Σ rate `paid`) și aplică
  `refund_policy`:
  - Fee-uri nereturnabile configurabile (default: `platform_fee` și `marketplace_fee` reținute).
  - Anulează ratele viitoare `scheduled`, revocă mandatul/token-ul la procesator.
- Full paid → refund standard. Partial paid → refund proporțional cu politica.

---

## 10. Integrare checkout (client)

- Secțiune nouă în checkout: **"Plătește în rate"**.
  - Apar doar planurile **eligibile** (sumă în interval + grafic care încape înainte de eveniment).
  - Fiecare plan afișează preview: *avans acum X lei, apoi N rate de Y lei pe datele …, total Z lei
    (cu +W lei față de plata directă)* — transparent, conform cerinței 3.
  Avansul afișat vine din `event_installment_configs` (per eveniment), aplicat pe `customer_total`.
- Client alege plan → `InstallmentAgreementService` creează agreement `pending` + grafic →
  plătește avansul (`createPaymentWithMandate`) → la callback: token salvat, agreement `active`,
  biletul emis cu status `pending_installments` (invalid), scheduler preia ratele.

**API (App\Http\Controllers\Api\TenantClient):**
- `GET  /api/installments/plans?event=&amount=` → planuri eligibile + preview (avans din event config)
- `POST /api/installments/quote` → grafic detaliat pentru un plan ales
- checkout submit acceptă `installment_plan_id`
- `GET  /api/installments/agreements/{id}` → portal client (status, grafic, sume, update card, plată anticipată)
- `POST /api/installments/agreements/{id}/pay/{sequence}` → plată/autentificare on-session a unei rate (retry manual, 3DS)
- extindere `TenantPaymentWebhookController` pentru rezultatele debitărilor recurente

---

## 11. Admin (Filament)

**Marketplace panel (`/marketplace`)** — replicat și pe `/tenant` (scope-ul acoperă ambele):
- **Modulul de planuri** — `InstallmentPlanResource`: CRUD șabloane (grafic, surcharge,
  eligibilitate, politici, termeni, avans-default opțional). Preview live al unui grafic exemplu.
- **Panoul de eveniment** — secțiune nouă în `EventResource` "Plată în rate": toggle `enabled`,
  multi-select planuri aplicabile, **setare avans** pentru acel eveniment. (§4.5)
- `InstallmentAgreementResource` — listă/detaliu agreements: grafic, status rate, acțiuni
  manuale (charge acum, reprogramează scadență, waive rată, anulează plan, inițiază retur).
- `InstallmentSettings` page — activare microserviciu, config dunning/reminder (cadență
  `reminder_days_before`), status tokenizare per procesator, vizualizare `platform_fee_percent`
  (read-only pentru marketplace).

**Admin/platform panel (`/admin`):**
- Înregistrare `Microservice` (`installments`), `platform_fee_percent` global editabil,
  raportare cross-marketplace (GMV în rate, rată default, restanțe).

---

## 12. Înregistrare microserviciu

```php
Microservice::create([
  'name' => ['ro' => 'Plată în rate', 'en' => 'Installment Payments'],
  'slug' => 'installments',
  'icon' => 'heroicon-o-calendar-days',
  'price' => 0.00,            // sau fee de activare
  'currency' => 'EUR',
  'billing_cycle' => 'monthly',
  'config_schema' => [ /* platform_fee_percent, providers tokenizabili, dunning defaults */ ],
]);
```
Activare per marketplace prin pivot `marketplace_client_microservices.settings`.
Config global platformă în `config/installments.php`:
`platform_fee_percent_default = 1.0`, `platform_fee_percent_installments = 2.0`.

---

## 13. Ordinea de implementare (faze)

| Fază | Conținut | Livrabil verificabil |
|------|----------|----------------------|
| **0** | Tokenizare procesoare (interface + Netopia + Stripe; flag fallback pentru restul) | debitare off-session funcțională în sandbox |
| **1** | Migrări + modele + `InstallmentPlanCalculator` + înregistrare microserviciu | calculator cu teste de rounding & garanție total>direct |
| **2** | Modulul de planuri (`InstallmentPlanResource`) + secțiunea din `EventResource` (enable + planuri + avans) + settings | marketplace definește planuri și le activează pe eveniment |
| **3** | Checkout: eligibilitate, quote, creare agreement, plată avans + tokenizare + bilet emis invalid | client alege plan și plătește avansul |
| **4** | Scheduler + Jobs + emailuri (**`InstallmentEmailTemplatesSeeder`** §17) + **reminder-e programate** + **3DS fallback** (§16.1) | ratele se debitează automat, reminder-e trimise |
| **5** | Dunning/default + retur + **expirare card** (§16.2) + **anulare/reprogramare eveniment** (§16.3) | scenarii neplată/retur/eveniment acoperite |
| **6** | Portal client (plată anticipată, update card) + `InstallmentAgreementResource` + **payout incremental** (§16.4) + webhook-uri/raportare (§16.8) | vizibilitate + control + decontare corectă |
| **7** | Integrare facturare/e-Factura (§16.5) + reminder-e multi-canal (§16.7) + review juridic (§16.6) | conformitate fiscală & legală |

---

## 14. Edge cases & teste

- Rounding: Σ rate == financed_cents (restul pe ultima rată).
- `customer_total_cents > base_total_cents` mereu (validare + test).
- Eveniment prea aproape → plan ascuns / grafic comprimat.
- Card expiră înainte de ultima scadență → avertisment la checkout + email update card (§16.2).
- Debitare off-session → 3DS `authentication_required` → link on-session, nu eșec direct (§16.1).
- Organizator anulează/reprogramează evenimentul mid-plan → stop debitări + refund/re-plan (§16.3).
- Webhook dublu / retrigger scheduler → idempotență pe `installment_payment.id`.
- Retur după 2 din 4 rate plătite → sumă corectă, taxe reținute, rate viitoare anulate.
- Schimbarea configului planului după emitere → agreement folosește `plan_snapshot`, nu configul live.
- Multi-currency: agreement îngheață `currency`; fără conversie mid-plan.

---

## 15. Decizii confirmate (stakeholder)

1. **Emitere bilete** → `issue_invalid_until_paid`: biletul se emite imediat, cu status
   **invalid**, și devine valid doar la plata integrală. (Secțiunea 9.1)
2. **Surcharge & fee platformă** → surcharge-ul îl încasează **doar marketplace-ul**
   (poate seta oricât). Tixello încasează **2% în loc de 1%** pentru bilete în rate,
   **de la marketplace** (nu de la client). (Secțiunea 2)
3. **Retur** → toate taxele sunt **nereturnabile** (surcharge + platform fee). (Secțiunea 9.3)
4. **Provideri v1** → lansăm doar cu **Stripe + Netopia** (tokenizare). EuPlatesc/PayU:
   fără rate până la suport recurent. (Secțiunea 6)
5. **Scope** → **marketplace + tenant**. Schema ține deja atât `marketplace_client_id` cât
   și `tenant_id` (nullable), serviciile sunt agnostice de owner, iar resursele Filament se
   replică ieftin pe `/tenant` (copie a celor de pe `/marketplace`). Efort suplimentar minim,
   deci le acoperim pe ambele. Config platformă (1%/2%) e global, se aplică identic.
6. **Avans** → setat **per eveniment** în panoul de eveniment; planurile trăiesc într-un modul
   separat, reutilizabile. (Secțiunile 4.5, 4.1)
7. **Payout organizator** → **incremental**, pe măsură ce se încasează fiecare rată. (§16.4)
8. **Expirare card** → cerem la checkout un card valabil până la finalul planului; fără
   update-card mid-plan în v1. (§16.2)

### Puncte încă deschise (recomandări în §16, aștept confirmarea ta)
- **16.1** SCA/MIT: pas 1 mereu SCA (+ verificare card când avans=0), fallback 3DS pe rate.
- **16.3** Anulare/reprogramare eveniment de organizator: refund integral incl. taxe la anulare;
  auto-comprimare + opțiune refund la reprogramare.
- **16.5** Facturare: v1 doar chitanțe; factură unică la finalizare când se adaugă facturarea.
- **16.6** Legal: planuri ≤3 luni, marketplace = creditor / Tixello = tech, sign-off juridic gating.
- **16.9** Early payoff: sold integral, fără discount de surcharge în v1.

---

## 16. Aspecte suplimentare identificate (nu erau în brief, dar trebuie gestionate)

Acestea sunt lucruri fără de care sistemul "merge la demo, dar cade la producție". Le-am
ordonat după criticitate.

### 16.1 3-D Secure / SCA pe debitările automate (CRITIC pentru RO/UE) — recomandare
Sub PSD2, o debitare off-session poate cere autentificare (3DS) chiar și cu token salvat.
**Model recomandat (MIT — Merchant Initiated Transaction):**
- **Avansul (pasul 1) e mereu on-session cu SCA** și stabilește mandatul + salvează
  network transaction id / token. Dacă avansul e 0 → tot facem un pas de **verificare card
  cu SCA** la checkout (Stripe SetupIntent / autorizare 1 RON + void la Netopia) ca să avem mandat valid.
- **Ratele următoare = MIT/off-session**, în general scutite de SCA (sunt recurente, sumă/comerciant fix).
- Dacă totuși issuer-ul cere autentificare (`authentication_required`) → rata devine
  `action_required`, NU eșec direct: trimitem email cu link on-session (`pay/{sequence}`),
  fereastră de grație (ex. 3–5 zile) + reminder; dacă expiră → `failed` → dunning.
- **Întrebare deschisă:** accepți ca (a) pasul 1 la checkout să fie mereu SCA (inclusiv un pas
  de verificare card când avansul e 0) și (b) ocazional o rată să ceară clientului autentificare
  cu fallback pe email?

### 16.2 Expirarea cardului în timpul planului — DECIS
**Decizie (stakeholder):** la checkout **cerem un card care NU expiră în perioada planului.**
Verificăm `exp_month/exp_year` vs. data ultimei scadențe; dacă expiră înainte → blocăm cardul
cu mesaj clar ("folosește un card valabil până la {ultima_rată}"). **Fără** flux de update-card
mid-plan în v1 (rămâne ca îmbunătățire viitoare). Simplu și robust.

### 16.3 Anulare/reprogramare eveniment de către organizator (mid-plan) — recomandare
Cheia: **cine e "de vină" decide cât se returnează** (spre deosebire de returul cerut de client,
unde taxele sunt reținute).
- **Anulare eveniment:** oprim debitările, anulăm mandatul, agreement → `cancelled`, iar
  clientul primește **100% din ce a plătit, INCLUSIV surcharge** (nu e vina lui). Tixello
  **stornează / nu percepe** platform fee-ul; marketplace suportă stornarea surcharge-ului.
  (Flux `event-cancelled` existent.)
- **Reprogramare mai târziu:** planul încape (mai mult timp) → continuă; recalculăm doar deadline-ul.
- **Reprogramare mai devreme și graficul nu mai încape:** auto-**comprimare** a ratelor rămase
  ca să se termine înainte de noua dată; dacă e imposibil (dată prea apropiată) → notificăm
  clientul cu alegere: **plată integrală acum SAU refund**.
- **Drept de refund la orice reprogramare** (schimbare materială) — oferim mereu opțiunea de refund.
- **Întrebare deschisă:** confirmi (a) anulare = refund integral incl. taxe + Tixello își
  stornează fee-ul, și (b) reprogramare-mai-devreme = auto-comprimare + opțiune de refund?

### 16.4 Momentul plății către organizator (payout timing) — DECIS
**Decizie (stakeholder): payout INCREMENTAL.** Creditam balanța organizatorului **pe măsură
ce se încasează fiecare rată** (nu tot upfront), prin `MarketplaceTransaction`. Nu plătim
organizatorul pentru bani neîncă colectați → risc zero de sold negativ la default.
La default → nu se mai creditează ratele viitoare; la refund pe anulare → storno proporțional.

### 16.5 Facturare / e-Factura & TVA — recomandare
**Context:** azi NU există facturare la checkout. Nu construim un motor fiscal complet acum.
- **v1:** doar **chitanțe/confirmări de plată** (non-fiscale) per rată + confirmare plan. Aliniat
  cu starea curentă (fără facturi).
- **Când se adaugă facturarea** (integrare EFACTURA/`InvoiceGeneratorService`): modelul cel mai
  curat pentru RO/TVA e **o singură factură pe întreaga comandă la finalizarea plății** (când
  biletul devine valid), cu **surcharge-ul ca linie separată**; ratele intermediare rămân
  chitanțe de avans. Alternativa (facturi de avans per rată + factură finală) e mai grea și doar
  dacă e nevoie de recunoaștere de venit per rată.
- Proiectăm datele (înregistrări per plată + linia de surcharge) astfel încât factura unică la
  final să fie trivială ulterior. **Necesită confirmare contabil când se implementează facturarea.**
- **Întrebare deschisă:** OK cu v1 = doar chitanțe, iar factura unică la finalizare când adăugați facturarea?

### 16.6 Conformitate legală — credit de consum (RO: OUG 50/2010) — recomandare
Plata în rate cu surcharge = un "cost al creditării", deci scutirea "fără dobândă și fără costuri"
NU se aplică. **Însă** legea scutește creditul **rambursat în ≤3 luni cu costuri nesemnificative**
și cel sub anumite praguri. Cum biletele trebuie plătite înainte de eveniment, aproape toate
planurile sunt scurte (≤3 luni) → probabil în zona scutită.
- **Recomandare structurare:** (a) încurajăm/limităm planurile la **termen scurt (≤3 luni)**,
  (b) surcharge modest, (c) **marketplace-ul e "creditorul"** (extinde termenul de plată, poartă
  creanța) — **Tixello e doar furnizor de tehnologie** + încasează 2%; asta contează pentru licențiere,
  (d) ecran de **informare precontractuală** în checkout (cost total, grafic, diferența vs plata directă)
  + checkbox de acceptare termeni, cu **log de consimțământ**.
- Nu sunt jurist — **sign-off legal rămâne gating item la client înainte de go-live.**
- **Întrebare deschisă:** ești OK cu (a) planuri ≤3 luni, (b) marketplace = creditor / Tixello = tech,
  (c) sign-off juridic ca item obligatoriu înainte de lansare?

### 16.7 Reminder-e multi-canal (SMS / WhatsApp) — refolosire servicii existente
Repo-ul are deja servicii SMS și WhatsApp. Aceleași trigger-e de reminder/eșec pot merge și
pe aceste canale, opțional, per preferință marketplace. Extensie ieftină, valoare mare la colectare.

### 16.8 Webhook-uri către marketplace + raportare receivables
Emitem evenimente (`installment.paid`, `installment.failed`, `agreement.defaulted`,
`agreement.completed`) prin `MarketplaceWebhookService` existent, ca sistemele marketplace să
reacționeze. Plus un dashboard cu: rată de colectare, rată default, sold de încasat (DSO),
expunere per eveniment.

### 16.9 Plată anticipată (early payoff) — self-service — recomandare
Clientul poate achita restul mai devreme din portal → agreement `completed`, biletul devine
valid imediat, ratele viitoare `cancelled`. Simplu și crește satisfacția.
- **Recomandare v1:** debităm **restul integral** (fără reducere de surcharge) — simplu, corect
  în zona "scutit" de la §16.6. (Dacă juridic ne clasează totuși ca credit de consum, apare
  dreptul la reducerea costului la rambursare anticipată → tratăm atunci.)
- **Întrebare deschisă:** OK cu early payoff la sold integral, fără discount de surcharge în v1?

### 16.10 Limite de risc / expunere (opțional, per marketplace)
Config opțional: valoare max per plan, număr max de planuri active simultan per client,
prag minim de comandă, **blocarea clienților cu default anterior**. Reduce frauda și restanțele.

---

## 17. Seed de emailuri (cerință explicită)

Refolosim infrastructura existentă: `MarketplaceEmailTemplate` (slug/subject/body_html/
variables/category), `MarketplaceEmailTemplate::TEMPLATE_SLUGS`, seedere per-marketplace cu
trait-ul `Database\Seeders\Concerns\BrandedEmailWrapper` (vezi `PayoutEmailTemplatesSeeder`).

**De făcut:**
1. **Înregistrăm slug-urile noi** în `MarketplaceEmailTemplate::TEMPLATE_SLUGS`.
2. **`InstallmentEmailTemplatesSeeder`** (per fiecare `MarketplaceClient`, branded, i18n),
   idempotent (fallback la body hardcodat dacă rândul lipsește — ca la payout).
3. Echivalent tenant prin `TenantMailService` (scope-ul acoperă și tenanții).
4. Adăugat în `DatabaseSeeder` + rulabil standalone (`--class=InstallmentEmailTemplatesSeeder`).

**Slug-uri client (variabile `{{...}}`: `plan_name`, `schedule_table`, `down_payment`,
`installment_amount`, `due_date`, `remaining_balance`, `customer_total`, `direct_price`,
`event_name`, `event_date`, `pay_link`, `portal_link`):**

| Slug | Trigger |
|------|---------|
| `installment_plan_confirmation` | La checkout — avans plătit + grafic complet + diferența vs plata directă |
| `installment_payment_upcoming` | Reminder înainte de scadență (cadență `reminder_days_before`) |
| `installment_payment_due_today` | În ziua scadenței ("azi debităm rata X") |
| `installment_payment_receipt` | După fiecare debitare reușită |
| `installment_action_required` | 3DS / autentificare necesară (link on-session) |
| `installment_payment_failed` | Debitare eșuată + retry programat + link plată |
| `installment_overdue` | Restanță / dunning escaladat (stage 1..N) |
| `installment_default_warning` | Ultima șansă înainte de anulare (corelat cu deadline eveniment) |
| `installment_defaulted` | Plan anulat, bilet invalidat, loc eliberat |
| `installment_plan_completed` | Plată integrală — biletul a devenit valid |
| `installment_early_payoff_receipt` | Confirmare plată anticipată |
| `installment_refund` | Retur/anulare inițiat de client (taxe reținute) |
| `installment_event_cancelled_refund` | Eveniment anulat de organizator — refund integral incl. taxe |
| `installment_event_rescheduled` | Eveniment reprogramat — grafic nou / alegere plată-acum-sau-refund |

**Slug-uri organizator/admin:**

| Slug | Trigger |
|------|---------|
| `organizer_installment_defaulted` | Notificare organizator când un plan pe evenimentul lui intră în default |
| `admin_installment_defaulted` | (opțional) Alertă admin marketplace pentru restanțe |

---

## 18. Îmbunătățiri suplimentare propuse (research nou)

Peste cerințe, lucruri care cresc conversia, colectarea și robustețea:

1. **Simulator de rate pe pagina evenimentului** ("de la X lei/lună") înainte de checkout →
   crește conversia; refolosește `InstallmentPlanCalculator`.
2. **Auto-revânzare la default:** când un plan intră în default și locul se eliberează, îl
   întoarcem în inventar / **waitlist** (module existente `Waitlist`/`SeatHold`) → recuperare venit.
3. **Card de rezervă (backup):** clientul poate adăuga un al doilea card; la eșecul primului,
   încercăm backup-ul înainte de dunning → mai puține default-uri. (post-v1)
4. **Job de reconciliere nocturnă:** compară `installment_payments` cu înregistrările
   procesatorului → prinde webhook-uri pierdute / debitări neînregistrate. Robustețe.
5. **Reguli de eligibilitate client (risk):** doar clienți cu o comandă finalizată anterior,
   blocarea celor cu default în trecut (leagă §16.10) → risc mai mic.
6. **Notificare organizator la default** pe evenimentul lui (afectează venitul) — prin
   notificările organizator existente.
7. **Fereastră de "cooling-off"** scurtă după avans (ex. 24h): clientul se poate răzgândi și
   primește refund integral **înainte** de orice rată → UX bun + potențial drept de retragere.
8. **Dashboard KPI rate:** collection rate, default rate, sold de încasat (DSO), calendar debitări
   viitoare, expunere per eveniment.
9. **Taxă de întârziere opțională (late fee)** — implicit OPRITĂ (atenție la suprafața legală
   §16.6); doar dacă marketplace-ul o activează conștient.
10. **Localizare corectă** (RON, dată RO) în emailuri + portal + checkout — încredere.
11. **Deadline pe cart mixt:** dacă coșul are evenimente diferite, deadline-ul de plată = cel
    mai apropiat eveniment. Motorul îl ia pe cel mai restrictiv.
12. **Mod test/preview** pentru marketplace: previzualizarea planurilor fără debitare reală.

---

## 19. Următorul pas

Planul e complet și aliniat la deciziile de mai sus. După ce închidem punctele deschise din
§16 (16.1, 16.3, 16.5, 16.6, 16.9), implementarea începe cu **Faza 0** (tokenizare Stripe +
Netopia + mandat MIT), fiind dependența critică pentru tot restul.
