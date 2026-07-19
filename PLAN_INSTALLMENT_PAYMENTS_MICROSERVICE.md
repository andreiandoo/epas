# Installment Payments Microservice — Implementation Plan

Plată în rate ("buy-now-pay-later" propriu) pentru orice marketplace și orice
procesator de plată deja integrat (Netopia, Stripe, EuPlatesc, PayU).

Exemplu de referință: **ambilet.ro** (MarketplaceClient) lucrează cu **Netopia**
și vrea să ofere plata în rate în checkout, cu planuri configurabile din admin.

---

## 1. Obiective & cerințe (din brief)

| # | Cerință | Cum o rezolvăm |
|---|---------|----------------|
| 1 | Valoare inițială de plată (avans) | `down_payment_type` (percent/fix/none) + `down_payment_value` la nivel de plan |
| 2 | Timp între rate ȘI/SAU dată fixă + procent | `schedule_type` = `interval` \| `fixed_dates`; distribuție `equal` \| `custom_percent` |
| 3 | Total în rate > plata directă | `surcharge` obligatoriu > 0 (validare hard) + fee-uri; garantat de `InstallmentPlanCalculator` |
| 4 | Tixello +1% și marketplace comision extra (procentual/fix) | `platform_fee_percent` global (1%) + `marketplace_commission_type/value` per plan |
| 5 | Debitări automate + mailuri | Motor cu token recurent (Netopia/Stripe) + fallback payment-link; scheduler + Jobs + email templates |
| 6 | Client nu mai plătește / retur | Dunning + default policy; integrare `MarketplaceRefundRequest` / `PaymentRefundService` |
| 7 | Trebuie achitat integral înainte de eveniment | Validare `ultima scadență <= event_date − buffer`; planuri neeligibile ascunse în checkout |

---

## 2. Model financiar (toate valorile în bani/cents, integer)

### Definiții

```
base_total_cents      = ce ar plăti clientul dacă plătește direct
                        (ticket_gross + comision_marketplace + processing_fee + asigurare)

platform_fee_cents    = ceil(base_total_cents * platform_fee_percent / 100)   // Tixello, default 1%
marketplace_fee_cents = comision extra marketplace (percent SAU fix, per plan)
surcharge_cents       = "dobânda" planului (percent SAU fix) — face totalul > plata directă

total_cents           = base_total_cents + platform_fee_cents
                        + marketplace_fee_cents + surcharge_cents

down_payment_cents    = avans la checkout (percent din total / fix / 0)
financed_cents        = total_cents − down_payment_cents
```

### Garanția "total > plata directă"

`InstallmentPlanCalculator` refuză să emită un plan dacă
`total_cents <= base_total_cents`. Deoarece `platform_fee_percent` e mereu ≥ 1%,
condiția e satisfăcută structural; validarea rămâne ca plasă de siguranță.

### Împărțirea pe rate (rounding determinist)

- `financed_cents` se împarte pe N rate după `distribution`:
  - `equal`: `floor(financed/N)` pentru fiecare rată, **restul (bani rămași) se adaugă
    la ultima rată** → suma ratelor == financed_cents exact.
  - `custom_percent`: fiecare rată = `floor(financed * pct_i / 100)`, restul la ultima.
- Fără floats: totul cu `intdiv`/`floor` pe cenți. Suma verificată prin assert în teste.

### Fee-uri: cine încasează

- **platform_fee_cents (1%)** → venit Tixello, evidențiat separat (nu intră în balanța
  organizatorului). Se decontează prin `MarketplaceTransaction` tip `commission`/`adjustment`.
- **marketplace_fee_cents** → venit marketplace (ambilet), configurabil.
- **surcharge_cents** → politica marketplace-ului (poate merge la marketplace sau split);
  configurabil, dar default → marketplace.
- Fiecare fee are un **snapshot pe agreement** (ca `processing_fee_*` pe Order) ca să fie
  imutabil chiar dacă se schimbă configul ulterior.

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

### 4.1 `installment_plans` (șabloane definite în admin)

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

-- Avans
down_payment_type (enum: none, percent, fixed)
down_payment_value (int)               // percent*100 sau cents

-- Programare rate
number_of_installments (int)           // fără avans
schedule_type (enum: interval, fixed_dates)
interval_unit (enum: day, week, month) // pentru interval
interval_count (int)                   // ex: la 30 zile => unit=day, count=30
fixed_dates (json, nullable)           // ["2026-03-01", ...] pentru fixed_dates
distribution (enum: equal, custom_percent)
installments_percentages (json, nullable) // [40,30,30] pentru custom_percent

-- Costuri
surcharge_type (enum: percent, fixed)  // "dobânda" (total > direct)
surcharge_value (int)
marketplace_commission_type (enum: none, percent, fixed)
marketplace_commission_value (int)
-- platform_fee_percent NU se ține aici; e global (config/installments.php)

-- Eligibilitate
min_order_cents (int, nullable)
max_order_cents (int, nullable)
days_before_event_fully_paid (int, default 0)
compress_schedule (bool, default false)
eligibility (json, nullable)           // scope evenimente / ticket types / categorii

-- Politici
ticket_issuance_policy (enum: on_full_payment, on_down_payment)
default_policy (json)                  // grace_days, max_retries, retry_backoff, forfeit
refund_policy (json)                   // ce fee-uri sunt nereturnabile
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
platform_fee_cents (int)               // Tixello 1%
marketplace_fee_cents (int)
surcharge_cents (int)
total_cents (int)                      // total în rate
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

Implementări:
- **Netopia (mobilPay):** plăți recurente pe bază de token (recurring/token) — avansul
  tokenizează, ratele se debitează cu token-ul. (Provider principal ambilet.)
- **Stripe:** `SetupIntent` (sau PaymentIntent `setup_future_usage=off_session`) la avans +
  `PaymentIntent off_session` la fiecare rată; token = `payment_method` + `customer`.
- **EuPlatesc / PayU:** unde nu există recurent → `supportsTokenization() = false` → se
  folosește automat modul **fallback payment-link** (email cu link securizat per rată).

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
     email chitanță; dacă e ultima → `agreement.status = completed` → emite biletele (dacă
     politica era `on_full_payment`).
   - Eșec → `attempts++`, reprogramează retry (backoff configurabil: ex. +1z, +3z, +5z);
     după `max_retries` → `failed` → `InstallmentDunningService`.
4. **Mod pay-link:** trimite email cu link; la scadență fără plată → reminder/escaladare.

**Reminders:** `installments:send-reminders` (zilnic) → email cu X zile înainte de scadență.

**Jobs:** `ChargeInstallmentJob`, `SendInstallmentReminderJob`, `HandleInstallmentDefaultJob`.

Toate idempotente (guard pe status + `attempts`) ca să reziste la retrigger/webhook dublu.

---

## 8. Emailuri / notificări

Prin `MarketplaceEmailService` / `TenantMailService` + `MarketplaceEmailTemplate`
(șabloane editabile per marketplace, i18n RO/EN):

1. **Confirmare plan + grafic complet** (la checkout: avans plătit, următoarele N rate cu date/sume, total, diferența vs plata directă).
2. **Reminder rată** (X zile înainte).
3. **Chitanță rată plătită**.
4. **Rată eșuată + retry programat** (cu link de update card / plată manuală).
5. **Avertisment default** (ultima șansă înainte de anulare — relevant pentru deadline eveniment).
6. **Plan finalizat** (bilete emise/valide).
7. **Retur / anulare** (sumă returnată, fee-uri reținute).

---

## 9. Neplată (default) & retur

### 9.1 Politica de emitere bilete (cheie pentru deadline eveniment)

- `on_full_payment` (**recomandat, default**): biletele stau **rezervate/blocate** și devin
  valide doar când agreement-ul e `completed`. Elimină riscul "client la eveniment cu bilet neplătit".
- `on_down_payment`: biletele se emit după avans; la default se **revocă/invalidează**
  (pentru marketplace-uri care acceptă riscul).

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
- Client alege plan → `InstallmentAgreementService` creează agreement `pending` + grafic →
  plătește avansul (`createPaymentWithMandate`) → la callback: token salvat, agreement `active`,
  bilete rezervate, scheduler preia ratele.

**API (App\Http\Controllers\Api\TenantClient):**
- `GET  /api/installments/plans?event=&amount=` → planuri eligibile + preview
- `POST /api/installments/quote` → grafic detaliat pentru un plan ales
- checkout submit acceptă `installment_plan_id`
- `GET  /api/installments/agreements/{id}` → portal client (status, grafic, sume)
- `POST /api/installments/agreements/{id}/pay/{sequence}` → plată manuală (mod pay-link)
- extindere `TenantPaymentWebhookController` pentru rezultatele debitărilor recurente

---

## 11. Admin (Filament)

**Marketplace panel (`/marketplace`)** — și replicat pe `/tenant` dacă e cazul:
- `InstallmentPlanResource` — CRUD șabloane (avans, grafic, surcharge, comision marketplace,
  eligibilitate, politici, termeni). Preview live al unui grafic exemplu.
- `InstallmentAgreementResource` — listă/detaliu agreements: grafic, status rate, acțiuni
  manuale (charge acum, waive rată, anulează plan, inițiază retur).
- `InstallmentSettings` page — activare microserviciu, config dunning/reminder, status
  tokenizare per procesator, vizualizare `platform_fee_percent` (1%, read-only pentru marketplace).

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
Config global platformă în `config/installments.php` (`platform_fee_percent = 1.0`).

---

## 13. Ordinea de implementare (faze)

| Fază | Conținut | Livrabil verificabil |
|------|----------|----------------------|
| **0** | Tokenizare procesoare (interface + Netopia + Stripe; flag fallback pentru restul) | debitare off-session funcțională în sandbox |
| **1** | Migrări + modele + `InstallmentPlanCalculator` + înregistrare microserviciu | calculator cu teste de rounding & garanție total>direct |
| **2** | Admin `InstallmentPlanResource` + settings | marketplace poate defini planuri |
| **3** | Checkout: eligibilitate, quote, creare agreement, plată avans + tokenizare | client alege plan și plătește avansul |
| **4** | Scheduler + Jobs + emailuri (debitare automată + reminder + chitanțe) | ratele se debitează automat, mailuri trimise |
| **5** | Dunning/default + retur (integrare refund + revocare bilete) | scenarii neplată & retur acoperite |
| **6** | Portal client + raportare + `InstallmentAgreementResource` acțiuni manuale | vizibilitate completă + control admin |

---

## 14. Edge cases & teste

- Rounding: Σ rate == financed_cents (restul pe ultima rată).
- `total_cents > base_total_cents` mereu (validare + test).
- Eveniment prea aproape → plan ascuns / grafic comprimat.
- Card expirat / mandat invalid → retry + email update card + eventual default.
- Webhook dublu / retrigger scheduler → idempotență pe `installment_payment.id`.
- Retur după 2 din 4 rate plătite → sumă corectă, fee-uri reținute, rate viitoare anulate.
- Schimbarea configului planului după emitere → agreement folosește `plan_snapshot`, nu configul live.
- Multi-currency: agreement îngheață `currency`; fără conversie mid-plan.

---

## 15. Întrebări de clarificare (recomand decizie înainte de Faza 3–5)

1. **Emitere bilete**: default `on_full_payment` (recomandat) sau `on_down_payment`?
2. **Surcharge/dobânda**: cine o încasează — integral marketplace, sau split cu Tixello?
3. **Fee-uri la retur**: `platform_fee` (1%) și `marketplace_fee` sunt nereturnabile (recomandat) sau se returnează?
4. **Fallback pay-link**: îl construim din Faza 4 pentru EuPlatesc/PayU, sau lansăm inițial doar cu Netopia+Stripe tokenizate?
5. **Scope**: microserviciul e doar pentru marketplace-uri (ambilet) sau și pentru tenanți individuali de la început?
