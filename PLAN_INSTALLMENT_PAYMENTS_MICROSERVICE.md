# Flexible Payments Microservice — Implementation Plan

> **STARE IMPLEMENTARE (branch `claude/installment-payment-microservice-khnntb`):**
> Toate cele 9 faze au fost implementate. Nucleul (calculator, motor de debitare,
> agreements, refund) e verificat: 7 teste unitare verzi + un test end-to-end pe SQLite
> (creare plan → quote → agreement cu grafic care se însumează exact → activare avans →
> sold/next-due corecte). Ce a rămas ca dependență externă: **spike-ul de sandbox Netopia**
> pentru a confirma numele exacte de câmpuri la tokenul recurent (marcat în `NetopiaProcessor`),
> și **sign-off-ul juridic** (§16.6). Fișiere livrate — vezi §21.


Microserviciu-**umbrelă „Plăți flexibile"** cu 3 sub-module pentru orice marketplace și orice
procesator de plată deja integrat (v1: Stripe + Netopia):

1. **Plată în rate** (installments) — cu avans, grafic, surcharge, debitare automată.
2. **BNPL** — o singură plată amânată ≤30 zile, înainte de eveniment.
3. **Plată delegată** (someone-else-pays) — blochează biletul, altcineva plătește prin link 24h.

Exemplu de referință: **ambilet.ro** (MarketplaceClient) lucrează cu **Netopia**.
Documentul păstrează denumirea istorică „installment" în multe secțiuni — motorul de rate e
nucleul, iar BNPL & plata delegată se sprijină pe el (vezi §19).

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
last_due_date = data ultimei rate/plăți calculate
event_start_date = data de START a evenimentului (nu ora — la nivel de zi, în timezone-ul evenimentului)
require: last_due_date <= event_start_date − 1 zi        // CONFIRMAT: MINIM cu o zi înainte
```

- **Regulă fermă (CONFIRMAT):** ultima rată/plată trebuie să fie **cu minimum o zi înainte** de
  data de start a evenimentului — **niciodată în ziua evenimentului**. Ne raportăm la `event_start_date`.
- `days_before_event_fully_paid` e configurabil per plan, dar **minimul impus e 1** (default 1).
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

### 4.5 `event_flexible_payment_configs` (configul per eveniment — setat în panoul de eveniment)

Operatorul decide **per eveniment** care dintre cele 3 metode sunt disponibile.

```
id (uuid, PK)
event_id (FK → events, nullable)              // sau marketplace_event_id
marketplace_event_id (FK, nullable)

-- Toggle-uri per metodă (CONFIRMAT: decizie per eveniment)
enable_installments (bool, default false)
enable_bnpl (bool, default false)
enable_delegated_pay (bool, default false)

-- Avansul pt. rate (pe acest eveniment)
down_payment_type (enum: none, percent, fixed)
down_payment_value (int)

-- BNPL pe acest eveniment
bnpl_max_horizon_days (int, default 30)       // ≤30

-- Plată delegată pe acest eveniment
delegated_hold_hours (int, default 24)        // ≤24
delegated_max_locked_tickets (int, nullable)  // cap opțional anti-blocare inventar

notes (string, nullable)
created_at, updated_at
Unique: (event_id) / (marketplace_event_id)
```

```
-- care planuri de rate se aplică evenimentului
event_installment_plan (pivot)
  event_flexible_payment_config_id (FK)
  installment_plan_id (FK)                     // include și plan_type=bnpl_single
  sort_order (int)
  is_active (bool)
```

La checkout, planurile eligibile = planurile atașate evenimentului (via pivot) **∩**
regulile de eligibilitate ale planului (min/max, fit-before-event − 1 zi). Avansul vine din
`down_payment_*`, calculat pe `customer_total_cents`. Metodele apar **doar dacă** toggle-ul
respectiv e activ pe eveniment (vezi regulile de checkout §10).

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
- **Mesaje explicite (CONFIRMAT — A4):** pe bilet (PDF/wallet/cont), în emailuri și în portal
  afișăm clar: *"Bilet REZERVAT — devine valid după achitarea integrală (sold rămas: X lei,
  următoarea plată: {due_date})."* Un banner vizibil + status colorat, ca să nu existe confuzia
  „am biletul, de ce nu pot intra". La eveniment, biletul invalid NU trece de scanare.

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

### 10.1 Reguli de disponibilitate a metodelor (CONFIRMAT — critice)

Un serviciu `FlexiblePaymentEligibilityService` decide ce metode apar, în această ordine:

1. **Microserviciu activ** pe marketplace + sub-modulul activ (installments/bnpl/delegated).
2. **Toggle per eveniment:** o metodă apare doar dacă e activă pe evenimentul biletului
   (`event_flexible_payment_configs.enable_*`).
3. **REGULA COȘ MONO-EVENIMENT (fermă):** dacă în coș sunt bilete de la **mai multe evenimente**,
   **NICIUNA** dintre cele 3 metode noi nu e permisă — **chiar dacă toate evenimentele au rate
   activate** — pentru că graficul și valorile diferă de la un eveniment la altul.
   → Afișăm mesaj în checkout: *"Pentru plata în rate și BNPL este necesar să plasezi comenzi
   separate, câte una per eveniment."* Metodele redevin disponibile când coșul conține bilete
   de la **un singur eveniment**.
   (Plata directă normală rămâne mereu disponibilă. Plata delegată pentru coș multi-eveniment
   poate fi relaxată în viitor — nu are termeni variabili — dar în v1 respectă aceeași regulă.)
4. **Eligibilitate plan** (sumă min/max, grafic care încape ≤ event_start − 1 zi).

### 10.2 Fluxul

- Secțiune nouă în checkout: **"Plăți flexibile"** (rate / BNPL / plată delegată), afișată doar
  conform 10.1.
- **Rate/BNPL:** apar doar planurile eligibile, fiecare cu preview: *avans acum X lei, apoi N rate
  de Y lei pe datele …, total Z lei (cu +W lei față de plata directă)* — transparent (cerința 3).
  Avansul vine din `event_flexible_payment_configs`, aplicat pe `customer_total`.
- Client alege plan → `InstallmentAgreementService` creează agreement `pending` + grafic →
  plătește avansul, sau **1 leu la BNPL** (`createPaymentWithMandate`) → la callback: token salvat,
  agreement `active`, biletul emis `pending_installments` (invalid), scheduler preia ratele.
- **Plată delegată:** vezi §19.2.

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
- **Pagină „Comenzi cu plăți flexibile"** (§18bis.D) — doar comenzile cu rate/BNPL/delegat + progres.
- **Pagină „Analytics Plăți flexibile"** (§18bis.B) — GMV per metodă, collection/default rate, DSO, expunere.
- **Pe `OrderResource` și `TicketResource`** (§18bis.C) — metoda de plată + sold rămas; pe comandă,
  **desfășurătorul de plăți**.
- **Widget-uri pe Dashboard** (§18bis.A) — venit rate/BNPL/delegat + sold de încasat.
- `InstallmentSettings` page — activare microserviciu + sub-module, config dunning/reminder (cadență
  `reminder_days_before`), status tokenizare per procesator, vizualizare `platform_fee_percent`
  (read-only pentru marketplace).

**Admin/platform panel (`/admin`):**
- Înregistrare `Microservice` (`installments`), `platform_fee_percent` global editabil,
  raportare cross-marketplace (GMV în rate, rată default, restanțe).

---

## 12. Înregistrare microserviciu

Microserviciul e o **umbrelă „Plăți flexibile"** cu 3 sub-module activabile independent:
**rate**, **BNPL**, **plată delegată** (someone-else-pays). Împart ~70% din infra
(`payment_links`, agreement/charge engine, emailuri, webhooks).

```php
Microservice::create([
  'name' => ['ro' => 'Plăți flexibile', 'en' => 'Flexible Payments'],
  'slug' => 'flexible-payments',
  'icon' => 'heroicon-o-calendar-days',
  'price' => 0.00,            // sau fee de activare
  'currency' => 'EUR',
  'billing_cycle' => 'monthly',
  'config_schema' => [
    // toggle-uri sub-module
    'enable_installments' => true,
    'enable_bnpl' => true,
    'enable_delegated_pay' => true,
    // platform fee, provideri tokenizabili, dunning defaults, hold delegat etc.
  ],
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
| **6** | Portal client (plată anticipată) + `InstallmentAgreementResource` + **payout incremental** (§16.4) + webhook-uri (§16.8) | vizibilitate + control + decontare corectă |
| **7** | **BNPL** (plan_type, captare 1 leu, auto-charge+link) + **plată delegată** (`payment_links`, hold 24h) — §19 | cele 3 metode funcționale end-to-end |
| **8** | **Suprafețe admin & raportare** (§18bis): dashboard stats, pagină analytics, afișare metodă+sold pe comandă/bilet, desfășurător plăți, listă comenzi flexibile | operatorul vede totul |
| **9** | **Pagină publică de prezentare** (§18bis.E) + facturare/e-Factura (§16.5) + reminder-e multi-canal (§16.7) + review juridic (§16.6) | conformitate + comunicare |

---

## 14. Edge cases & teste

- Rounding: Σ rate == financed_cents (restul pe ultima rată).
- `customer_total_cents > base_total_cents` mereu (validare + test).
- **Deadline: ultima plată ≤ event_start − 1 zi** (niciodată în ziua evenimentului) — test dedicat.
- **Coș multi-eveniment → toate metodele noi ascunse** + mesaj (§10.1) — test dedicat.
- Eveniment prea aproape → plan ascuns / grafic comprimat.
- Card care ar expira înainte de ultima scadență → blocat la checkout (§16.2).
- Debitare off-session → 3DS `authentication_required` → link on-session, nu eșec direct (§16.1).
- **BNPL: dublă plată** (auto-charge vs. link) → cine plătește primul câștigă, celălalt no-op (lock + idempotency).
- **Plată delegată: expirare hold 24h** → eliberare loc + „plătește tu acum" pentru inițiator înainte de release.
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

9. **SCA/MIT (16.1)** → confirmat. **Avansul e mereu > 0**, deci pasul 1 la checkout e chiar
   plata avansului (SCA on-session) — nu mai avem nevoie de un pas separat de verificare card
   la rate. Fallback 3DS pe rate rămâne. (NB: produsul BNPL de la §20.1 reintroduce nevoia de
   verificare-card la checkout, fiind 0 la start.)
10. **Anulare eveniment (16.3)** → **ca până acum** (fluxul de refund existent): fie **refund
    integral** (organizatorul suportă taxele și comisioanele), fie **refund parțial** — la decizie.
    Nu mai forțăm stornarea automată a fee-ului Tixello; urmează politica de refund existentă.
11. **Reprogramare (16.3)** → confirmat: auto-comprimare + opțiune de refund.
12. **Plafon durată (16.6)** → **HARD CAP: planurile de rate ≤ 3 luni de la începere** (validare
    în calculator + config). BNPL ≤ 30 zile. Ține și de zona legală scutită.
13. **Facturare (16.5)** → confirmat: v1 doar chitanțe; factură unică la finalizare ulterior.
14. **Early payoff (16.9)** → confirmat: sold integral, fără discount de surcharge în v1.
15. **Legal (16.6)** → marketplace = creditor / Tixello = tech; sign-off juridic = gating înainte de go-live.
16. **Îmbunătățirile §18 (1–7)** → **acceptate**, intră în backlog de implementare.
17. **Microserviciu-umbrelă (CONFIRMAT)** → „Plăți flexibile" cu 3 sub-module: rate, BNPL, plată delegată. (§12, §19)
18. **BNPL (CONFIRMAT)** → **1 leu** la checkout pentru captarea cardului; **auto-charge la
    scadență + link de plată pe email** (achitabil în cele 30 zile); Tixello **2%**. (§19.1)
19. **Plată delegată (CONFIRMAT)** → hold **24h** cu **toggle per eveniment**; **gratuit** (doar
    taxele/comisioanele existente pe bilet); sub aceeași umbrelă. (§19.2)

---

## 16. Aspecte suplimentare identificate (nu erau în brief, dar trebuie gestionate)

Acestea sunt lucruri fără de care sistemul "merge la demo, dar cade la producție". Le-am
ordonat după criticitate.

### 16.1 3-D Secure / SCA pe debitările automate (CRITIC pentru RO/UE) — recomandare
Sub PSD2, o debitare off-session poate cere autentificare (3DS) chiar și cu token salvat.
**Model recomandat (MIT — Merchant Initiated Transaction):**
- **Avansul (pasul 1) e mereu on-session cu SCA** și stabilește mandatul + salvează
  network transaction id / token. **CONFIRMAT: avansul e mereu > 0**, deci plata avansului
  ESTE pasul SCA — nu mai e nevoie de verificare-card separată la rate.
  (Excepție: produsul **BNPL** §20.1 pornește de la 0 → acolo folosim un pas de verificare-card
  cu SCA la checkout: Stripe SetupIntent / autorizare 1 RON + void la Netopia.)
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

### 16.3 Anulare/reprogramare eveniment de către organizator (mid-plan) — CONFIRMAT
- **Anulare eveniment (CONFIRMAT):** oprim debitările, anulăm mandatul, agreement → `cancelled`,
  iar refund-ul urmează **fluxul existent** — fie **integral** (organizatorul suportă taxele și
  comisioanele), fie **parțial**, la decizie. (Flux `event-cancelled` existent, fără logică nouă.)
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
- **Structurare (CONFIRMAT):** (a) **HARD CAP: planul de rate ≤ 3 luni de la începere** (validare
  în `InstallmentPlanCalculator` — respinge orice grafic care depășește), BNPL ≤ 30 zile,
  (b) surcharge modest, (c) **marketplace-ul e "creditorul"** — **Tixello e doar furnizor tehnic**
  + încasează 2%; contează pentru licențiere, (d) ecran de **informare precontractuală** în checkout
  + checkbox de acceptare termeni, cu **log de consimțământ**.
- Nu sunt jurist — **sign-off legal rămâne gating item la client înainte de go-live.**

**Ce se întâmplă dacă depășim 3 luni (întrebare stakeholder A5):** DA, apar implicații legale
serioase. Peste pragul scutit (creditare cu costuri, > 3 luni), activitatea devine **credit de
consum** în sensul deplin al OUG 50/2010, iar acordarea de credite cu titlu profesional în RO
necesită, de regulă, statut de **Instituție Financiară Nebancară (IFN)** înscrisă la **BNR**
(Legea 93/2009) — cu cerințe de capital, raportare, guvernanță, conformitate ANPC etc. Practic,
ambilet.ro ar trebui să se autorizeze/înregistreze ca IFN (sau să lucreze printr-un partener
IFN/bancar licențiat). **De aceea recomandarea fermă e să rămânem ≤ 3 luni** (zona probabil
scutită) și să lăsăm creditarea „grea" pe un partener licențiat dacă vreodată se dorește termen
mai lung. **Confirmarea finală o dă avocatul** — dar direcția e clară: >3 luni = povară de
autorizare, ≤3 luni = evităm.

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

## 18. Îmbunătățiri suplimentare — ACCEPTATE (1–7 confirmate, intră în backlog)

Peste cerințe, lucruri care cresc conversia, colectarea și robustețea:

1. **Simulator de rate pe pagina evenimentului** ("de la X lei/lună") înainte de checkout →
   crește conversia; refolosește `InstallmentPlanCalculator`.
2. **Retry inteligent temporal (AMÂNAT — „încă nu"):** reîncercare în jurul zilelor de salariu.
   Rămâne în backlog, nu în v1.
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
11. **Deadline pe cart mixt:** N/A pentru metodele noi (regula mono-eveniment §10.1 le blochează);
    rămâne relevant doar pentru validări defensive.
12. **Mod test/preview** pentru marketplace: previzualizarea planurilor fără debitare reală.

**Acceptate suplimentar (rundă C):**
13. **Buton „achită tot acum" în fiecare reminder** — one-click early payoff → accelerează cash, scade default.
14. **View „at-risk" în dashboard** — agreements cu risc mare (rată ratată, card care expiră, declinuri) → outreach proactiv.
15. **„Plătește tu acum" ca rescue** — la eșec BNPL/auto-charge sau expirare link delegat, oferim inițiatorului să achite el → recuperăm vânzarea.
16. **Mesaj de cadou la plata delegată** — personalizare pentru scenariul copil→părinte.
17. **Analytics de canibalizare** — cât din rate/BNPL sunt vânzări NOI vs. mutare de la plata integrală → ROI-ul celor 2%.
18. **„De la X lei/lună" pe cardurile de listing** (nu doar pagina evenimentului) → lift de conversie.
19. **Semnale de fraudă** — velocity (același card pe multe planuri), email disposable la plătitorul B.

---

## 18bis. Suprafețe de admin, raportare & site public (rundă stakeholder)

Cerințe confirmate — unde apar aceste plăți în interfețe.

### A. Statistici pe Dashboard-ul marketplace
Includem aceste tipuri de venit în statisticile de pe dashboard-ul principal:
**venit din rate**, **venit BNPL**, **venit din plăți delegate**, plus **sold de încasat** (outstanding).
Separate de vânzările cu plată integrală, ca operatorul să vadă mixul.

### B. Pagină dedicată de Analytics „Plăți flexibile"
Pagină separată (Filament) cu: GMV per metodă (rate/BNPL/delegat), collection rate, default rate,
DSO (sold mediu de încasat), calendar debitări viitoare, expunere per eveniment, evoluție în timp.
(Se leagă de §16.8.)

### C. Afișare pe pagina de Comandă și Bilet (admin marketplace)
- Pe **comandă** și pe **bilet**: **prin ce metodă** a fost cumpărat (integral / rate / BNPL /
  delegat) + **dacă mai există sold de achitat** și cât.
- Pe pagina **unei comenzi**: **desfășurătorul de plăți** complet (graficul: fiecare rată, scadență,
  status, cât s-a plătit, cât rămâne, next due).

### D. Pagină „Comenzi cu plăți flexibile"
Resursă/listă separată care arată **doar** comenzile cu rate / BNPL / delegat și **progresul** lor
(status agreement, rate plătite/total, next due, sold rămas), cu filtre pe metodă/status.

### E. Pagină publică de prezentare (ambilet.ro)
Pagină pe site-ul public care prezintă frumos metodele: ce înseamnă rate/BNPL/plată delegată,
cum funcționează, exemple, întrebări frecvente. (Template de pagină în sistemul de pagini existent.)

---

## 19. Extensii de produs (noi) — BNPL & "plătește altcineva"

Ambele se sprijină pe infra deja proiectată. Introducem un **primitiv comun de link de plată**
care servește 3 scenarii: link 3DS/manual pentru o rată (§16.1), plata unică BNPL, și plata delegată.

### 19.0 Primitiv comun: `payment_links`

```
id (uuid, PK)
token (string, unique, random securizat)
purpose (enum: installment, bnpl, delegated_pay)
marketplace_client_id / tenant_id (nullable)
order_id (FK, nullable)
installment_payment_id (FK, nullable)      // pt. purpose=installment
amount_cents (int)
currency (string)
status (enum: active, paid, expired, cancelled)
expires_at (datetime)
payer_email (string, nullable)             // pt. delegated: cine plătește
payer_name (string, nullable)
created_by_customer_id (FK, nullable)
paid_at (datetime, nullable)
payment_reference (string, nullable)
metadata (json, nullable)
created_at, updated_at
Index: token (unique), status, expires_at, order_id
```
Endpoint public: `GET/POST /pay/{token}` → pagină de plată on-session (SCA), validează plata via
webhook, apoi acțiune specifică purpose-ului.

### 19.1 Buy Now, Pay Later (o singură plată amânată)

**Definiție:** o singură plată integrală, în ≤ **30 de zile** și **înainte de eveniment**,
0 (sau mic) la checkout, cu **comision extra**. NU e în rate.

**Reutilizare — e practic un plan cu `N=1`:**
- Adăugăm `plan_type (enum: installments, bnpl_single)` pe `installment_plans`.
- Pentru `bnpl_single`: `number_of_installments = 1`, scadență unică la
  `min(checkout + max_horizon_days[≤30], event_date − buffer)`, `down_payment = 0` (sau mic),
  **surcharge propriu** (comisionul extra). Restul (agreement, `installment_payments` cu o
  singură linie, scheduler, dunning, default, refund, emailuri) se refolosește 1:1.
- **Captare card la checkout (CONFIRMAT):** clientul plătește **1 leu** la checkout — o
  tranzacție reală care ne dă token-ul/mandatul (mai sigur decât SetupIntent/void și cu SCA
  garantat). 1 leul se scade din suma finală (sau se stornează, de aliniat contabil).
- **Debitare (CONFIRMAT): AMBELE** — auto-charge pe token la scadență **și** un **link de plată
  trimis pe email** pe care clientul îl poate achita oricând în cele 30 de zile (`payment_links`
  purpose=bnpl). Reminder-e pe parcurs. Dacă auto-charge eșuează → linkul + dunning; dacă nu se
  plătește până la deadline → bilet invalidat, loc eliberat.
- **Fee platformă (CONFIRMAT):** Tixello ia **tot 2%** și pe BNPL.
- Bilet: `pending_installments` (invalid) până la plata unică → apoi `valid`. Plafon §16.6 (≤30 zile) respectat.

### 19.2 Buy Now, Someone Else Pays (plată delegată prin link)

**Definiție:** clientul A **blochează** biletul și trimite un **link de plată valid 24h** către
plătitorul B (ex. copil cumpără, părinte plătește). NU e credit/rate — doar mecanică de plată.

**Flux:**
1. A alege biletele → "plătește altcineva" → creăm `Order` în `awaiting_payment` + **rezervare
   inventar/loc 24h** (extindem TTL-ul de `SeatHold`/rezervare) + `payment_links`
   (purpose=delegated_pay, expires_at = +24h, opțional `payer_email`).
2. A trimite linkul (sau îl trimitem noi pe email/SMS către B, dacă avem contactul).
3. B deschide `/pay/{token}` → vede sumarul comenzii → plătește **on-session (SCA)**, plată unică normală.
4. Webhook confirmă plata → `Order.paid`, **biletele devin valide**, email de confirmare către A (și B).
5. **Expirare 24h fără plată** → eliberăm rezervarea, `Order.cancelled`, notificăm A.

**Reutilizare:** checkout/procesare existentă, `SeatHold`/rezervare, `Order`, webhooks,
`MarketplaceEmailTemplate`. Nou: primitivul `payment_links` + un mic serviciu `DelegatedPaymentService`.

**Considerații (CONFIRMAT):**
- **Risc de inventar:** hold de 24h → **toggle per eveniment** (organizatorul îl oprește la
  evenimente cu cerere mare) + durată configurabilă (≤24h). Recomand și un cap opțional pe nr. de
  bilete blocate simultan așa.
- **Gratuit (CONFIRMAT):** nu percepem nimic extra față de taxele și comisioanele deja existente
  pe acel bilet. Se aplică doar processing fee-ul normal + comisioanele obișnuite ale comenzii.
- **Împachetare (CONFIRMAT):** **sub aceeași umbrelă** cu rate + BNPL (vezi §12).

---

## 20. Următorul pas

Planul e complet și aliniat la deciziile confirmate. Rămân de închis doar întrebările de produs
de la §19 (BNPL & plată delegată). După aceea, implementarea începe cu **Faza 0** (tokenizare
Stripe + Netopia + mandat MIT) + primitivul `payment_links`, fiind dependențele critice.

---

## 21. Fișiere livrate (implementare)

**Migrări** (`database/migrations/2026_07_20_*`): payment_links, installment_plans,
event_flexible_payment_configs (+ pivot event_installment_plan), installment_agreements,
installment_payments, installment_events, add_installment_agreement_to_orders.

**Modele** (`app/Models/`): PaymentLink, InstallmentPlan, InstallmentAgreement,
InstallmentPayment, InstallmentEvent, EventFlexiblePaymentConfig (+ slug-uri în MarketplaceEmailTemplate).

**Procesoare** (`app/Services/PaymentProcessors/`): SupportsTokenizedPayments (interfață
opt-in) implementată în StripeProcessor (SetupIntent + off_session MIT + 3DS) și
NetopiaProcessor (token recurent v2 + 3DS).

**Servicii** (`app/Services/Installments/`): InstallmentPlanCalculator (pur, testat),
FlexiblePaymentEligibilityService, InstallmentAgreementService, InstallmentChargeService
(+ early payoff), InstallmentDunningService, InstallmentRefundService, InstallmentPayoutService
(incremental), TicketStateService, DelegatedPaymentService, ProcessorResolver, FlexiblePaymentMailer.

**Comenzi + Jobs** (`app/Console/Commands`, `app/Jobs`): installments:process-due,
installments:send-reminders, installments:expire-links, ChargeInstallmentJob (+ scheduling în
`routes/console.php`).

**HTTP**: `Api\TenantClient\InstallmentController` (availability/plans/start),
`FlexiblePaymentController` (portal, early payoff, pay-link, delegated) + rute în
`routes/api.php` și `routes/web.php` (`/pay/{token}`, `/installments/*`, `/plati-flexibile`).

**Filament (Marketplace)**: InstallmentPlanResource (modul planuri),
EventFlexiblePaymentConfigResource (config per eveniment), InstallmentAgreementResource
(comenzi + desfășurător plăți), FlexiblePaymentStatsWidget (KPI dashboard).

**Config + Seedere**: `config/installments.php`, FlexiblePaymentsMicroserviceSeeder,
InstallmentEmailTemplatesSeeder (19 șabloane branded), înregistrate în DatabaseSeeder.

**Teste**: `tests/Unit/InstallmentPlanCalculatorTest.php` (7 teste, 26 aserțiuni).

### Polish finalizat (rundă ulterioară)
- **Pagină dedicată de analytics** (`FlexiblePaymentAnalytics`) + **pagină de setări**
  (`FlexiblePaymentSettings`, toggle sub-module) — LIVRATE.
- **Afișare metodă + sold + desfășurător de plăți** pe `OrderResource` (coloane + secțiune
  în infolist) — LIVRAT.
- **Pagina publică** `/plati-flexibile` + **widget KPI** pe dashboard — LIVRATE.

### Hardening din review (rundă de review pe cod)
- **Securitate:** portal + early payoff erau accesibile după id secvențial (PII + debitare
  reală a cardului). Acum **token per-agreement** (`portal_token`, ascuns din serializare);
  rutele publice sunt `throttle:30,1`.
- **Integritate:** `confirmLink` verifică plata la procesator (`getPaymentStatus`) înainte de
  a marca link-ul plătit — nu se mai încrede într-un GET.
- **Payout:** creditarea incrementală acoperă acum și avansul și plata anticipată.
- **Validare checkout:** `start` respinge comenzi neeligibile și planuri neatașate/dezactivate
  pe eveniment; `createDelegated` verifică starea comenzii + toggle-ul evenimentului.
- **BNPL:** captarea de 1 leu e modelată ca avans (sequence 0) → se **scade din sold**.
- **Reminder due-today** activat.

### Rundă de verificări totale (audit adversarial pe cod)

**Inventar bilete/locuri (HIGH):**
- **Oversell:** locurile ținute (`held`) nu erau confirmate la plata avansului → sweep-ul de
  eliberare le putea elibera de sub o plată activă. Acum `TicketStateService::confirmSeatsForOrder`
  e apelat din `handleDownPaymentCallback` și din `DelegatedPaymentService::confirm` (`held → sold`).
- **Stoc fantomă:** la default/anulare/expirare link, locurile + cota nu se eliberau. Acum
  `releaseInventoryForOrder` (via `invalidateForAgreement`) + comanda e trecută în `cancelled`
  (`syncTerminalOrder`), închizând și comanda-zombie (A#2).

**Coliziune id-space (MED):** `where(event_id)->orWhere(marketplace_event_id)` putea potrivi
config-ul altui eveniment. Introdus `EventFlexiblePaymentConfig::resolveFor()` care potrivește
fiecare id în spațiul lui; toate căutările din microserviciu trecute prin el.

**Check-in (MED):** biletele `pending_installments` erau deja blocate de gate-ul pe statusul
comenzii; adăugat mesaj explicit dedicat la scanare (VenueOwner + Organizer).

**Refund extern Stripe (MED):** `charge.refunded` din dashboard pe avans nu oprea planul (auto-debit
continua!). Adăugat `InstallmentRefundService::externalRefund` (idempotent, fără re-refund la
procesator) rutat din webhook.

**Idempotență refund (MED):** înlocuit guard-ul ne-atomic `status===REFUNDED` cu un claim atomic
prin `UPDATE ... WHERE status != refunded` (0 rânduri afectate → bail).

**Batch LOW:**
- `waived` bloca finalizarea (`outstandingCents` nu-l scădea) → adăugat `waivedCents()`.
- **Drift la payout:** rotunjirea independentă a cotei de bază per rată deriva față de
  `base_total`; acum **telescoping** pe secvență (sumă exactă, independentă de ordine).
- `fail()` hard-decline nu recalcula `next_due_at` → adăugat `recomputeNextDue()`.
- **Avans 0 ("0 avans"):** captura de 1 ban era respinsă de Stripe; acum **SetupIntent** (0 lei)
  via `supportsZeroAmountMandate()`; Netopia (nu poate 0) e blocat cu mesaj clar.
- **Cheie de idempotență** per-rată acum *attempt-scoped* (`id-aN`): un retry real primește cheie
  nouă, dar re-rularea aceleiași încercări (callback pierdut) rămâne idempotentă → fără dublă taxare.
- `confirmLink` verifică și **suma** raportată de procesator înainte de settle.

**GAP-uri închise:**
- **§16.2 expirare card:** `checkCardExpiry` la activare (dormant până când procesorul livrează
  `card_exp_*`; flag + email la nevoie).
- **§16.3 reprogramare eveniment:** `reconcileEventStartDate` (rulat din `process-due`) recomprimă
  ratele rămase ca planul să se încheie înainte de noul deadline; email `installment_event_rescheduled`.
- **§16.6(d) consimțământ:** log `consent_recorded` la creare (breakdown + grafic + `terms_url` +
  timestamp) pe lângă `plan_snapshot` imutabil.

### Amânat explicit (backlog, neblocant)
- Reminder-e multi-canal SMS/WhatsApp (§16.7), card de rezervă, retry inteligent temporal.
- Populare efectivă `card_exp_*` din procesor (necesită expand payment_method la Stripe / câmp Netopia).
