# Integrare Netopia

## Prezentare Scurtă

Acceptă plăți de la clienții români cu Netopia, cel mai de încredere procesor de plăți din țară. Cu o expertiză profundă pe piața locală și o recunoaștere largă în rândul consumatorilor, Netopia oferă experiența de checkout fluidă pe care o așteaptă publicul tău român.

Consumatorii români cunosc și au încredere în Netopia. Acea familiaritate se traduce în rate de conversie mai mari. Când clienții văd opțiunea de plată Netopia la checkout, procedează cu încredere. Fără ezitare, fără coșuri abandonate din cauza procesorilor de plăți necunoscuți.

Plățile cu cardul funcționează instantaneu. Visa, Mastercard și cardurile locale românești sunt toate suportate. Clienții introduc detaliile, se autentifică cu banca lor și finalizează achiziția în câteva secunde.

Transferurile bancare oferă o alternativă pentru clienții care preferă plățile directe. Integrarea gestionează întregul flux, de la inițierea transferului până la confirmarea primirii. Nu este necesară reconcilierea manuală.

Securitatea îndeplinește standardele bancare românești. Autentificarea 3D Secure protejează fiecare tranzacție cu cardul. Conformitatea PCI asigură că datele deținătorilor de card rămân securizate pe tot parcursul procesului de plată.

Notificările în timp real te țin informat. Știi imediat când plățile reușesc, eșuează sau necesită atenție. Integrările webhook actualizează automat statusul comenzilor.

Decontarea se întâmplă pe un program predictibil. Netopia transferă fondurile în contul tău bancar românesc în mod fiabil, cu rapoarte clare de reconciliere.

Începe să accepți plăți astăzi. Conectează-ți contul de comerciant Netopia, configurează credențialele și activează plățile românești în câteva minute.

---

## Descriere Detaliată

Microserviciul de Integrare Netopia conectează platforma ta de ticketing cu Netopia Payments, liderul procesatorilor de plăți din România. Această integrare gestionează plățile cu cardul, transferurile bancare și întregul ciclu de viață al tranzacțiilor pentru piața românească.

### Poziția pe Piață

Netopia Payments (fostul mobilPay) a procesat plăți în România de peste 15 ani. Este soluția de plată preferată pentru platformele majore de e-commerce românești, oferindu-i o recunoaștere puternică a brandului în rândul consumatorilor locali.

### Metode de Plată

Integrarea suportă:

- **Plăți cu Card**: Visa, Mastercard, Maestro și carduri locale ale băncilor românești
- **Transferuri Bancare**: Transferuri directe bancă-la-bancă pentru clienții care preferă plățile non-card
- **Rate**: Planuri de rate pentru carduri prin băncile partenere (unde sunt disponibile)

### Fluxul de Checkout

Când un client selectează Netopia la checkout, este redirecționat către pagina de plată securizată Netopia. După introducerea detaliilor cardului și finalizarea autentificării 3D Secure, revine pe site-ul tău cu rezultatul tranzacției.

Fluxul bazat pe redirecționare asigură conformitatea PCI - datele sensibile ale cardului nu ajung niciodată pe serverele tale.

### Autentificare și Securitate

Fiecare tranzacție cu cardul trece prin verificarea 3D Secure. Banca clientului autentifică tranzacția, oferind protecție a răspunderii și reducând riscul de fraudă.

Sistemele de detectare a fraudei Netopia monitorizează tranzacțiile pentru tipare suspecte. Combinat cu 3D Secure, aceasta oferă protecție completă pentru afacerea ta.

### Statusul Tranzacției

Webhook-urile în timp real notifică sistemul tău despre schimbările de status ale plății:
- **Confirmat**: Plată reușită, fonduri securizate
- **În Așteptare**: Se așteaptă confirmarea băncii (transferuri)
- **Anulat**: Clientul a anulat sau timeout
- **Creditat**: Rambursare procesată

### Decontare

Netopia decontează fondurile conform acordului tău de comerciant, de obicei în 1-3 zile lucrătoare pentru plățile cu cardul. Rapoartele de decontare oferă date detaliate de reconciliere pentru contabilitate.

---

## Funcționalități

### Acceptarea Plăților
- Suport Visa și Mastercard
- Carduri de debit Maestro
- Carduri locale ale băncilor românești
- Plăți prin transfer bancar
- Planuri de plată în rate

### Securitate și Autentificare
- Autentificare obligatorie 3D Secure
- Procesare conformă PCI DSS
- Monitorizare detectare fraudă
- Pagină de plată găzduită securizat
- Criptarea tranzacțiilor

### Experiența de Checkout
- Checkout securizat bazat pe redirecționare
- Pagină de plată optimizată pentru mobil
- Interfață în limba română
- Mesaje de eroare clare
- Gestionarea URL-ului de retur

### Gestionarea Tranzacțiilor
- Status plată în timp real
- Notificări webhook
- Procesare rambursări complete
- Suport rambursări parțiale
- Căutare și filtrare tranzacții

### Raportare
- Rapoarte de decontare
- Istoric tranzacții
- Reconciliere zilnică
- Capacități de export
- Urmărirea veniturilor

### Instrumente pentru Comercianți
- Mod test pentru dezvoltare
- Mediu sandbox
- Documentație API
- Suport tehnic
- Dashboard comerciant

---

## Cazuri de Utilizare

### Evenimente Românești
Vinde bilete către audiențe românești folosind metoda lor de plată locală de încredere. Rate de conversie mai mari comparativ cu opțiunile de plată doar internaționale.

### Vânzări la Festivaluri Locale
Procesează mii de achiziții de bilete în perioadele de cerere ridicată. Infrastructura Netopia gestionează încărcăturile de vârf pentru evenimente majore românești.

### Evenimente Corporate în România
Facturează companiile românești prin canalele lor de plată locale preferate. Transferurile bancare oferă o alternativă pentru procesele de achiziții corporative.

### Turneele Artiștilor Români
Artiștii și promotorii locali beneficiază de procesarea familiară a plăților. Fanii finalizează achizițiile rapid fără fricțiuni la metoda de plată.

### Evenimente Multi-Valută
În timp ce Netopia procesează în RON, platforma ta poate afișa prețuri în EUR sau alte valute cu conversie automată la checkout.

### Vânzări în Rate
Oferă planuri de plată pentru biletele premium prin parteneriatele Netopia cu băncile românești. Fă pachetele VIP scumpe mai accesibile.

---

## Documentație Tehnică

### Prezentare Generală

Microserviciul de Integrare Netopia procesează plățile prin gateway-ul de plăți Netopia. Gestionează inițializarea plății, fluxul de redirecționare, procesarea callback-urilor și operațiunile de rambursare.

### Cerințe Preliminare

- Cont de comerciant Netopia activ
- Credențiale comerciant (semnătură și cheie publică)
- URL webhook configurat în admin-ul Netopia
- Certificat SSL pe domeniul tău

### Configurare

```php
'netopia' => [
    'signature' => env('NETOPIA_SIGNATURE'),
    'public_key_path' => env('NETOPIA_PUBLIC_KEY_PATH'),
    'private_key_path' => env('NETOPIA_PRIVATE_KEY_PATH'),
    'sandbox' => env('NETOPIA_SANDBOX', false),
    'currency' => 'RON',
    'confirm_url' => env('APP_URL') . '/api/webhooks/netopia',
    'return_url' => env('APP_URL') . '/checkout/complete',
]
```

### Endpoint-uri API

#### Inițializare Plată

```
POST /api/payments/netopia/init
```

Creează o cerere de plată și returnează URL-ul de redirecționare.

**Cerere:**
```json
{
  "amount": 150.00,
  "currency": "RON",
  "order_id": "order_123",
  "description": "Bilete Concert x2",
  "customer": {
    "email": "client@exemplu.ro",
    "first_name": "Ion",
    "last_name": "Popescu",
    "phone": "0721234567"
  },
  "billing_address": {
    "city": "București",
    "country": "Romania"
  }
}
```

**Răspuns:**
```json
{
  "redirect_url": "https://secure.mobilpay.ro/pay/...",
  "transaction_id": "txn_abc123",
  "env_key": "...",
  "data": "..."
}
```

#### Callback Plată (Webhook)

```
POST /api/webhooks/netopia
```

Primește notificări de status al plății de la Netopia.

**Netopia trimite payload XML:**
```xml
<?xml version="1.0" encoding="utf-8"?>
<order>
  <mobilpay:action>confirmed</mobilpay:action>
  <mobilpay:original_amount>150.00</mobilpay:original_amount>
  <mobilpay:processed_amount>150.00</mobilpay:processed_amount>
</order>
```

#### Procesare Rambursare

```
POST /api/payments/netopia/refund
```

**Cerere:**
```json
{
  "transaction_id": "txn_abc123",
  "amount": 75.00
}
```

#### Obținere Status Tranzacție

```
GET /api/payments/netopia/{transactionId}
```

### Stări Plată

| Status | Cod | Descriere |
|--------|-----|-----------|
| În Așteptare | 0 | Plată inițiată, se așteaptă finalizarea |
| Confirmat | 1 | Plată reușită |
| Autorizare în Așteptare | 2 | Card autorizat, se așteaptă capturarea |
| Plătit (În Așteptare) | 3 | Plătit, decontare în așteptare |
| Anulat | 4 | Plată anulată |
| Creditat | 5 | Rambursare procesată |
| Respins | 6 | Plată respinsă |

### Implementarea Fluxului de Redirecționare

```php
// 1. Creare cerere de plată
$paymentData = $netopia->createPayment($order);

// 2. Construire formular și redirecționare
$formHtml = '<form method="POST" action="' . $netopia->getGatewayUrl() . '">';
$formHtml .= '<input type="hidden" name="env_key" value="' . $paymentData['env_key'] . '">';
$formHtml .= '<input type="hidden" name="data" value="' . $paymentData['data'] . '">';
$formHtml .= '</form>';
$formHtml .= '<script>document.forms[0].submit();</script>';

// 3. Gestionare callback
public function handleCallback(Request $request)
{
    $response = $netopia->processCallback($request);

    if ($response->isConfirmed()) {
        $order->markAsPaid();
    }

    return $netopia->buildResponse($response);
}
```

### Coduri de Eroare

| Cod | Descriere |
|-----|-----------|
| 16 | Card respins de bancă |
| 17 | Card expirat |
| 18 | Fonduri insuficiente |
| 19 | Număr de card invalid |
| 20 | Limita tranzacției depășită |
| 21 | Autentificare 3D Secure eșuată |
| 99 | Eroare generică |

### Testare

Folosește modul sandbox cu carduri de test:

| Număr Card | Rezultat |
|------------|----------|
| 9900004810225098 | Plată reușită |
| 9900004810225099 | Plată eșuată |

Setează `NETOPIA_SANDBOX=true` pentru a activa modul de test.

### Considerații de Securitate

1. Validează semnăturile XML pe callback-uri
2. Verifică că sumele tranzacțiilor corespund comenzilor
3. Folosește HTTPS pentru toate URL-urile de callback
4. Stochează doar referințele tranzacțiilor, nu datele cardului
5. Implementează procesare idempotentă a callback-urilor
