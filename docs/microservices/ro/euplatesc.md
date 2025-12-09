# Integrare EuPlatesc

## Prezentare Scurtă

Acceptă plăți de la clienții români cu EuPlatesc, un gateway de plăți local de încredere care oferă rate competitive și procesare fiabilă. Perfect pentru afacerile focusate pe piața românească care doresc acceptarea plăților într-un mod simplu și eficient din punct de vedere al costurilor.

EuPlatesc livrează acolo unde contează cel mai mult - tranzacții fiabile la costuri competitive. Structura lor de comisioane recompensează volumul, făcându-i atractivi pentru afaceri cu multe tranzacții precum ticketingul pentru evenimente.

Consumatorii români recunosc și au încredere în brandul EuPlatesc. Când checkout-ul tău afișează EuPlatesc, clienții locali procedează cu încredere. Familiaritatea reduce abandonarea coșului.

Plățile cu cardul se procesează fluid. Cardurile Visa, Mastercard și Maestro funcționează instantaneu. Clienții introduc detaliile, completează verificarea 3D Secure și finalizează achiziția în câteva secunde.

Securitatea îndeplinește standardele bancare europene. Fiecare tranzacție cu cardul trece prin autentificarea 3D Secure. Conformitatea PCI DSS protejează datele deținătorilor de card pe tot parcursul fluxului de plată.

Actualizările de status în timp real țin sistemul tău sincronizat. Notificările instantanee confirmă plățile reușite, încercările eșuate și rambursările. Managementul comenzilor tale rămâne actualizat automat.

Integrarea este simplă. API-ul bine documentat al EuPlatesc și echipa de suport responsivă fac configurarea fluidă. Majoritatea comercianților finalizează integrarea în zile, nu săptămâni.

Decontarea se întâmplă predictibil. Fondurile se transferă în contul tău bancar românesc conform programului, cu rapoarte clare de reconciliere. Știi exact ce vine și când.

Simplu, fiabil, accesibil. Integrarea EuPlatesc oferă organizatorilor de evenimente români exact ce au nevoie - procesare de plăți dependabilă fără complexitate.

---

## Descriere Detaliată

Microserviciul de Integrare EuPlatesc conectează platforma ta de ticketing pentru evenimente cu gateway-ul de plăți EuPlatesc, oferind procesare de plăți focusată pe piața românească cu rate competitive și performanță fiabilă.

### Poziția pe Piață

EuPlatesc a servit piața de e-commerce românească ani de zile, construindu-și o reputație pentru fiabilitate și prețuri competitive. Focusul lor pe piața locală înseamnă relații puternice cu băncile românești și procesare optimizată pentru cardurile domestice.

### Fluxul de Plată

Integrarea folosește un flux bazat pe redirecționare:

1. Clientul selectează EuPlatesc la checkout
2. Sistemul generează cererea de plată criptată
3. Clientul este redirecționat către pagina de plată securizată EuPlatesc
4. Clientul introduce detaliile cardului și completează 3D Secure
5. Clientul revine pe site-ul tău cu rezultatul tranzacției
6. Sistemul tău primește webhook-ul de confirmare

Acest flux asigură conformitatea PCI - serverele tale nu gestionează niciodată date brute de card.

### Suport Carduri

EuPlatesc procesează:
- **Visa**: Carduri de credit și debit
- **Mastercard**: Carduri de credit și debit
- **Maestro**: Carduri de debit

Atât cardurile românești domestice cât și cardurile internaționale sunt suportate, deși cardurile domestice au de obicei rate de aprobare mai mari.

### Cadrul de Securitate

Fiecare tranzacție necesită autentificare 3D Secure. Banca emitentă a clientului verifică identitatea deținătorului de card înainte de a aproba tranzacția. Aceasta oferă:
- Transfer de răspundere pentru tranzacțiile autentificate
- Risc redus de fraudă
- Conformitate cu protecția consumatorului

EuPlatesc menține conformitatea PCI DSS, asigurând că datele deținătorilor de card sunt protejate conform standardelor industriei de plăți.

### Avantaje de Preț

Structura de comisioane EuPlatesc se dovedește adesea competitivă pentru comercianții români:
- Comisioane transparente bazate pe procent
- Fără costuri ascunse
- Reduceri de volum disponibile
- Decontare în valută locală fără comisioane de conversie

Pentru vânzătorii de bilete cu volum mare, economiile de costuri se acumulează semnificativ în timp.

### Procesul de Decontare

Fondurile din tranzacțiile reușite se decontează în contul tău bancar românesc conform acordului tău de comerciant. Rapoartele de decontare oferă detalii la nivel de tranzacție pentru reconcilierea ușoară cu înregistrările tale de comenzi.

---

## Funcționalități

### Acceptarea Plăților
- Carduri de credit și debit Visa
- Credit și debit Mastercard
- Carduri de debit Maestro
- Carduri românești domestice
- Suport carduri internaționale

### Securitate
- Autentificare obligatorie 3D Secure
- Procesare conformă PCI DSS
- Cereri de plată criptate
- Pagină de plată găzduită securizat
- Verificarea tranzacțiilor

### Experiența de Checkout
- Flux securizat bazat pe redirecționare
- Pagină de plată responsive pentru mobil
- Interfață în limba română
- Mesaje de eroare clare
- URL-uri de retur personalizabile

### Gestionarea Tranzacțiilor
- Confirmări de plată în timp real
- Notificări webhook
- Procesare rambursări complete
- Suport rambursări parțiale
- Interogări status tranzacție

### Instrumente pentru Comercianți
- Mod test/sandbox
- Căutare tranzacții
- Rapoarte de decontare
- Reconciliere zilnică
- Documentație API
- Suport tehnic

### Raportare
- Istoric tranzacții
- Urmărirea decontărilor
- Rapoarte de venituri
- Funcționalitate de export
- Intervale de date personalizate

---

## Cazuri de Utilizare

### Săli de Concert Românești
Procesează vânzări de bilete pentru săli din toată România. Procesarea locală a plăților înseamnă rate de aprobare mai mari pentru deținătorii de carduri români comparativ cu gateway-urile doar internaționale.

### Vânzări de Bilete la Festivaluri
Gestionează perioadele de vânzări cu volum mare pentru festivalurile românești. Infrastructura EuPlatesc gestionează încărcăturile de vârf menținând costurile de procesare competitive.

### Teatru și Evenimente Culturale
Instituțiile culturale beneficiază de prețuri simple și procesare fiabilă. Perfect pentru organizațiile care doresc plăți dependabile fără configurare complexă.

### Promotori Locali
Promotorii independenți de evenimente primesc procesare de plăți de nivel enterprise la rate competitive. Prețurile bazate pe volum recompensează evenimentele de succes.

### Serii de Evenimente Regionale
Procesează plăți pentru serii de evenimente recurente în orașele românești. Procesarea consistentă și raportarea clară simplifică contabilitatea multi-eveniment.

### Organizatori cu Buget Limitat
Când costurile de procesare a plăților contează, comisioanele competitive EuPlatesc ajută la maximizarea veniturilor din bilete. Fiecare punct procentual economisit se întoarce la evenimentul tău.

---

## Documentație Tehnică

### Prezentare Generală

Microserviciul de Integrare EuPlatesc gestionează procesarea plăților prin gateway-ul EuPlatesc. Gestionează inițializarea plății, fluxul de redirecționare, procesarea callback-urilor și operațiunile de rambursare.

### Cerințe Preliminare

- Cont de comerciant EuPlatesc activ
- ID comerciant și cheie secretă
- URL callback configurat în admin-ul EuPlatesc
- Certificat SSL pe domeniul tău

### Configurare

```php
'euplatesc' => [
    'merchant_id' => env('EUPLATESC_MERCHANT_ID'),
    'secret_key' => env('EUPLATESC_SECRET_KEY'),
    'test_mode' => env('EUPLATESC_TEST_MODE', false),
    'currency' => 'RON',
    'callback_url' => env('APP_URL') . '/api/webhooks/euplatesc',
    'success_url' => env('APP_URL') . '/checkout/success',
    'fail_url' => env('APP_URL') . '/checkout/failed',
]
```

### Endpoint-uri API

#### Inițializare Plată

```
POST /api/payments/euplatesc/init
```

Creează o cerere de plată și returnează datele formularului pentru redirecționare.

**Cerere:**
```json
{
  "amount": 250.00,
  "currency": "RON",
  "order_id": "order_456",
  "description": "Abonament Festival - Weekend",
  "customer": {
    "email": "client@exemplu.ro",
    "first_name": "Maria",
    "last_name": "Ionescu",
    "phone": "0722345678"
  },
  "billing": {
    "city": "Cluj-Napoca",
    "country": "Romania",
    "address": "Str. Exemplu 123"
  }
}
```

**Răspuns:**
```json
{
  "form_url": "https://secure.euplatesc.ro/tdsprocess/tranzactd.php",
  "form_data": {
    "amount": "250.00",
    "curr": "RON",
    "invoice_id": "order_456",
    "order_desc": "Abonament Festival - Weekend",
    "merch_id": "xxx",
    "timestamp": "20250115120000",
    "nonce": "abc123...",
    "fp_hash": "..."
  }
}
```

#### Callback Plată

```
POST /api/webhooks/euplatesc
```

Primește notificări cu rezultatul plății.

**EuPlatesc trimite:**
```
amount=250.00
curr=RON
invoice_id=order_456
ep_id=123456789
action=0
message=Approved
fp_hash=...
```

#### Interogare Tranzacție

```
GET /api/payments/euplatesc/{invoiceId}/status
```

Returnează statusul curent al tranzacției.

#### Procesare Rambursare

```
POST /api/payments/euplatesc/refund
```

**Cerere:**
```json
{
  "ep_id": "123456789",
  "amount": 250.00,
  "reason": "Clientul a solicitat anularea"
}
```

### Coduri de Acțiune

| Acțiune | Descriere |
|---------|-----------|
| 0 | Aprobat |
| 1 | Tranzacție duplicat |
| 2 | Refuzat |
| 3 | Eroare |
| 4 | În așteptare (verificare suplimentară) |
| 5 | Card neînrolat 3D Secure |

### Verificarea Hash-ului

```php
// Generare hash pentru cerere
$data = implode('', [
    strlen($amount) . $amount,
    strlen($curr) . $curr,
    strlen($invoice_id) . $invoice_id,
    strlen($order_desc) . $order_desc,
    strlen($merch_id) . $merch_id,
    strlen($timestamp) . $timestamp,
    strlen($nonce) . $nonce,
]);
$fp_hash = strtoupper(hash_hmac('md5', $data, hex2bin($secret_key)));

// Verificare hash callback
$callback_data = implode('', [
    strlen($amount) . $amount,
    strlen($curr) . $curr,
    strlen($invoice_id) . $invoice_id,
    strlen($ep_id) . $ep_id,
    strlen($action) . $action,
    strlen($message) . $message,
]);
$expected_hash = strtoupper(hash_hmac('md5', $callback_data, hex2bin($secret_key)));
$is_valid = hash_equals($expected_hash, $received_hash);
```

### Implementarea Fluxului de Redirecționare

```php
// 1. Generare formular de plată
$paymentData = $euplatesc->createPayment($order);

// 2. Randare formular cu auto-submit
return view('payment.redirect', [
    'form_url' => $paymentData['form_url'],
    'form_data' => $paymentData['form_data'],
]);

// Template Blade
<form id="payment-form" method="POST" action="{{ $form_url }}">
    @foreach($form_data as $key => $value)
        <input type="hidden" name="{{ $key }}" value="{{ $value }}">
    @endforeach
</form>
<script>document.getElementById('payment-form').submit();</script>

// 3. Gestionare callback
public function callback(Request $request)
{
    if (!$euplatesc->verifyHash($request->all())) {
        return response('Hash invalid', 400);
    }

    $order = Order::find($request->invoice_id);

    if ($request->action === '0') {
        $order->markAsPaid($request->ep_id);
    } else {
        $order->markAsFailed($request->message);
    }

    return response('OK');
}
```

### Mesaje de Eroare

| Cod | Mesaj | Descriere |
|-----|-------|-----------|
| 00 | Approved | Tranzacție reușită |
| 05 | Do not honour | Card refuzat de bancă |
| 12 | Invalid transaction | Eroare tranzacție |
| 14 | Invalid card number | Număr card incorect |
| 33 | Expired card | Card expirat |
| 51 | Insufficient funds | Sold insuficient |
| 54 | Expired card | Valabilitatea cardului a expirat |
| 91 | Issuer unavailable | Sistemul băncii inaccesibil |

### Testare

Activează modul test:
```
EUPLATESC_TEST_MODE=true
```

Carduri de test (doar sandbox):
- Folosește orice număr de card cu format valid
- Folosește dată de expirare în viitor
- Folosește orice CVV de 3 cifre

### Lista de Verificare pentru Integrare

1. Obține credențiale de comerciant de la EuPlatesc
2. Configurează URL-ul webhook în portalul comerciantului
3. Implementează generarea și verificarea hash-ului
4. Construiește trimiterea formularului de redirecționare
5. Gestionează notificările callback
6. Testează în modul sandbox
7. Trimite pentru aprobare producție
8. Comută la credențialele de producție

### Bune Practici de Securitate

1. Verifică întotdeauna hash-ul callback înainte de procesare
2. Folosește HTTPS pentru toate URL-urile de callback
3. Implementează gestionare idempotentă a callback-urilor
4. Loghează toate tranzacțiile pentru audit
5. Nu stoca niciodată numerele de card
6. Validează că sumele corespund totalurilor comenzilor
