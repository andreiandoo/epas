# End-to-End Testing — Flexible Payments (fără Netopia real, fără așteptare între rate)

Ghid practic de testare a întregului sistem (rate, BNPL, plată delegată, retur,
dunning, reminder-e) folosind un **procesator fals** și **manipularea timpului**,
ca să vezi în minute ce în realitate ar dura săptămâni.

## 0. Setup (o singură dată, pe staging)

```bash
# 1) Activează procesatorul FALS (nicio tranzacție reală Netopia/Stripe)
#    în .env:
FLEX_FAKE_PROCESSOR=true

php artisan config:clear
php artisan migrate

# 2) Înregistrează microserviciul + șabloanele de email
php artisan db:seed --class=FlexiblePaymentsMicroserviceSeeder
php artisan db:seed --class=InstallmentEmailTemplatesSeeder
```

Apoi, în admin: **activează microserviciul „Plăți flexibile" pe marketplace-ul de
test** (pivotul `marketplace_client_microservices`, status `active`). Marketplace-ul
trebuie să aibă un procesator „tokenizabil" ca metodă implicită (Netopia sau Stripe
cu chei de test — nu se apelează, dar eligibilitatea verifică slug-ul).

### Controlul rezultatului la mock (ca să testezi fiecare ramură)
`MockTokenizableProcessor` întoarce rezultatul după **ultimii bani ai sumei**:
- sumă terminată în **`.13`** → **eșec** (decline)
- sumă terminată în **`.15`** → **autentificare 3DS** (action_required)
- orice altceva → **succes**

(Poți forța și explicit cu `metadata['force'] = 'success'|'failed'|'action_required'`.)

---

## 1. Configurare plan + eveniment (admin)

1. **Modulul „Planuri de rate"** → creează un plan:
   - Tip programare: **Auto (fit_to_event)** — recomandat
   - Nr. rate: 3, Distribuție: Egale, Surcharge: 5%
2. **„Rate pe evenimente"** → alege un eveniment de test:
   - Activează **Plată în rate** (și/sau BNPL / delegat)
   - Setează **avansul** (ex. 20%)
   - Atașează planul creat
   - (Opțional) **Bilete eligibile**: alege doar unele tipuri de bilete

---

## 2. Happy path — rate complete în câteva secunde

Poți testa fie prin UI (checkout → alege planul → „plătește" avansul pe pagina
mock care redirectează la confirm), fie direct din `tinker`:

```php
config(['installments.fake_processor' => true]);

// 1) Pornește planul pentru o comandă pending existentă (sau creează un agreement):
$order = App\Models\Order::where('status','pending')->latest()->first();
$resp  = app(App\Http\Controllers\Api\TenantClient\InstallmentController::class); // sau apel HTTP la /api/tenant-client/installments/start

// Din tinker, cel mai simplu e serviciul direct:
$plan = App\Models\InstallmentPlan::first();
$q = app(App\Services\Installments\InstallmentPlanCalculator::class)
    ->quote($plan, (int) round($order->total*100), [
      'down_payment_type'=>'percent','down_payment_value'=>2000,
      'event_start_date'=>$order->event?->start_date,
      'platform_fee_percent'=>2.0,
    ]);
$ag = app(App\Services\Installments\InstallmentAgreementService::class)
    ->createFromQuote($plan, $q, [
      'order_id'=>$order->id,'marketplace_client_id'=>$order->marketplace_client_id,
      'customer_email'=>$order->customer_email,'event_start_date'=>$order->event?->start_date,
    ]);

// 2) Simulează confirmarea avansului (ca și callback-ul Netopia):
app(App\Services\Installments\InstallmentAgreementService::class)
    ->handleDownPaymentCallback($order->fresh(), ['mandate_reference'=>'mock_mandate','transaction_id'=>'mock_down']);
// → agreement 'active', biletele 'pending_installments' (invalide)

// 3) TIME TRAVEL: fă toate ratele scadente ACUM
$ag->payments()->where('sequence','>',0)->update(['due_date'=>now()->subDay()]);
```

Apoi rulează scheduler-ul manual (sincron, fără cozi):

```bash
php artisan installments:process-due --sync
```

Rezultat așteptat: toate ratele `paid`, agreement `completed`, biletele devin
`valid`, comanda `completed`. Verifică în „Comenzi cu plăți flexibile" +
desfășurătorul de plăți pe comandă.

---

## 3. 3DS / autentificare (fără card real)

- Setează suma unei rate să se termine în `.15` (sau `metadata.force='action_required'`).
- Rulează `installments:process-due --sync` → rata devine **`action_required`**, se
  generează un `payment_link` și se trimite emailul `installment_action_required`.
- Deschide `GET /pay/{token}` → `POST /pay/{token}` → `GET /pay/{token}/confirm`
  (mock-ul confirmă) → rata se `paid`, planul avansează.

## 4. Eșec + dunning + default

- Setează o rată să se termine în `.13` (decline).
- `installments:process-due --sync` → rata intră în **retry** (backoff), email
  `installment_payment_failed`. Rulează de câteva ori (sau scade `attempts`/mută
  `due_date`) până la `failed` → `installment_overdue` → `installment_default_warning`.
- Pentru default imediat: apropie `event_start_date` de azi și rulează din nou →
  agreement `defaulted`, bilete invalidate, `installment_defaulted`.

## 5. BNPL

- Plan `bnpl_single`, activat pe eveniment. La `start`, mock-ul „încasează" 1 leu
  (captare card). Time-travel pe unica rată → `process-due --sync` → `completed`.

## 6. Plată delegată (24h → secunde)

```php
$link = app(App\Services\Installments\DelegatedPaymentService::class)->createLink($order, ['payer_email'=>'parinte@test.ro']);
// deschide /pay/{$link->token} → /pay/.../confirm  → order paid, bilete valide
// Expirare: $link->update(['expires_at'=>now()->subHour()]); php artisan installments:expire-links → order cancelled
```

## 7. Retur (toate tranzacțiile marcate)

- Din „Comenzi cu plăți flexibile" → acțiunea **Retur** (bifă „eveniment anulat"
  pentru retur integral) **sau** fluxul standard de refund.
- Cu mock-ul, fiecare `refundPayment` reușește → fiecare rată devine `refunded`,
  agreement `refunded`, balanța organizatorului stornată. Verifică în log-uri
  (`installment_events`) și în ledger.

## 8. Reminder-e (fără să aștepți zile)

```php
// mută scadența unei rate active la +3 zile și rulează:
$ag->payments()->where('sequence',1)->update(['due_date'=>now()->addDays(3),'reminder_sent_at'=>null]);
```
```bash
php artisan installments:send-reminders   # trimite installment_payment_upcoming
```
Sau time-travel global în tinker: `Carbon::setTestNow(now()->addDays(3));` apoi rulează comanda.

---

## 9. Ce verifici la final
- „Comenzi cu plăți flexibile" + desfășurătorul de plăți pe comandă (metodă, sold).
- Widget-ul KPI + pagina „Analytics plăți flexibile".
- Emailuri (log-ul de mail / MailHog pe staging).
- `installment_events` (audit) pentru fiecare tranziție.
- Biletul: `pending_installments` → `valid` la finalizare; `cancelled` la default.

## 10. Înainte de producție
- **`FLEX_FAKE_PROCESSOR=false`** (obligatoriu) + `config:clear`.
- Rulează spike-ul de sandbox Netopia (confirmă câmpurile token-ului recurent).
- Sign-off juridic (§16.6 din planul principal).
