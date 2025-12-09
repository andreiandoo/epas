# Integrare Stripe

## Prezentare Scurtă

Acceptă plăți de la clienți din întreaga lume cu Stripe, platforma de plăți lider global în care au încredere milioane de afaceri. De la plăți cu cardul până la Apple Pay și Google Pay, Stripe le gestionează pe toate fără probleme.

Clienții tăi se așteaptă la o experiență de checkout fluidă. Stripe oferă exact asta - rapid, sigur și familiar. Cardurile de credit, cardurile de debit și portofelele digitale funcționează instantaneu. Fără redirecționări, fără fricțiuni, fără coșuri abandonate.

Securitatea este integrată în fiecare tranzacție. Conformitatea PCI DSS Nivel 1 înseamnă că datele cardurilor clienților tăi sunt protejate de cele mai înalte standarde de securitate. Autentificarea 3D Secure adaugă un alt nivel de protecție împotriva fraudei.

Intră pe piața globală fără complexitate. Stripe suportă peste 135 de valute și zeci de metode de plată populare în diferite regiuni. Fie că clienții tăi sunt în Europa, Asia sau America, pot plăti cum preferă.

Dashboard-urile în timp real arată fiecare tranzacție. Urmărește plățile reușite, monitorizează rambursările și analizează veniturile. Instrumentele de raportare Stripe îți oferă vizibilitate completă asupra operațiunilor de plată.

Transferurile automate îți transferă câștigurile pe un program care funcționează pentru tine. Zilnic, săptămânal sau personalizat - banii tăi ajung în contul bancar în mod fiabil.

Configurarea durează minute. Conectează-ți contul Stripe, configurează cheile API și începe să accepți plăți imediat. Nu este necesară expertiză tehnică.

---

## Descriere Detaliată

Microserviciul de Integrare Stripe conectează platforma ta de ticketing pentru evenimente cu infrastructura completă de plăți Stripe. Această integrare gestionează întregul ciclu de viață al plății, de la checkout până la decontare.

### Metode de Plată

Acoperirea metodelor de plată Stripe include:

- **Plăți cu Card**: Visa, Mastercard, American Express, Discover, JCB, Diners Club și UnionPay
- **Portofele Digitale**: Apple Pay, Google Pay și Link (checkout-ul Stripe cu un click)
- **Debitări Bancare**: SEPA Direct Debit pentru clienții europeni
- **Cumpără Acum, Plătește Mai Târziu**: Klarna, Afterpay/Clearpay unde este disponibil

### Experiența de Checkout

Integrarea încorporează Stripe Elements direct în pagina ta de checkout. Clienții introduc detaliile de plată fără a părăsi site-ul tău. Formularul se adaptează pentru a afișa câmpurile relevante în funcție de metoda de plată selectată.

Validarea cardului are loc în timp real. Numerele invalide, cardurile expirate și detaliile incomplete sunt detectate înainte de trimitere. Aceasta reduce plățile eșuate și frustrarea clienților.

### Cadrul de Securitate

Toate datele de plată trec prin infrastructura conformă PCI a Stripe. Serverele tale nu ating niciodată numerele de card brute. Aceasta reduce semnificativ povara ta de securitate și cerințele de conformitate.

3D Secure (Autentificarea Puternică a Clienților) se activează automat când este cerut de banca clientului sau de reglementările regionale. Integrarea gestionează fluxul de autentificare fără probleme.

Stripe Radar, sistemul de detectare a fraudei încorporat, evaluează fiecare tranzacție folosind machine learning antrenat pe miliarde de puncte de date. Tranzacțiile suspecte pot fi blocate automat sau marcate pentru revizuire.

### Decontare și Raportare

Fondurile din tranzacțiile reușite apar instantaneu în soldul tău Stripe. Transferurile automate transferă aceste fonduri în contul bancar conectat pe baza programului configurat.

Dashboard-ul Stripe oferă raportare completă, inclusiv istoricul tranzacțiilor, rapoarte de decontare și analize. Toate datele sunt exportabile pentru contabilitate și reconciliere.

---

## Funcționalități

### Acceptarea Plăților
- Plăți cu carduri de credit și debit
- Integrare Apple Pay
- Integrare Google Pay
- Link checkout cu un click
- SEPA Direct Debit (EUR)
- iDEAL (Olanda)
- Bancontact (Belgia)
- Sofort (Germania, Austria)

### Securitate și Conformitate
- Conformitate PCI DSS Nivel 1
- Autentificare 3D Secure 2
- Detectarea fraudei cu Stripe Radar
- Stocare tokenizată a cardurilor
- Transmisie criptată a datelor
- Conformitate SCA (UE)

### Experiența de Checkout
- Formular de plată încorporat
- Validare card în timp real
- Design responsive pentru mobil
- Suport pentru multiple valute
- Metode de plată localizate
- Metode de plată salvate

### Gestionarea Tranzacțiilor
- Procesare plăți în timp real
- Confirmare automată a plății
- Rambursări totale și parțiale
- Gestionarea disputelor de plată
- Notificări webhook
- Cereri idempotente

### Raportare și Decontare
- Dashboard tranzacții în timp real
- Transferuri automate
- Rapoarte de decontare
- Analize venituri
- Export în CSV/PDF
- Raportare multi-valută

---

## Cazuri de Utilizare

### Evenimente Internaționale
Vinde bilete către audiențe din întreaga lume. Stripe prezintă automat cele mai relevante metode de plată în funcție de locația clientului. Clienții europeni văd SEPA și opțiuni locale, în timp ce clienții nord-americani văd carduri și Apple Pay.

### Vânzări cu Volum Mare
Gestionează valurile de bilete pentru evenimente populare fără probleme. Infrastructura Stripe procesează mii de tranzacții pe secundă. Vânzările tale nu vor fi încetinite de procesarea plăților.

### Vânzări Mobile-First
Captează clienții mobili cu Apple Pay și Google Pay. Checkout-ul cu o singură atingere elimină fricțiunea tastării numerelor de card pe ecrane mici. Ratele de conversie mobile cresc semnificativ.

### Evenimente cu Abonament
Organizează evenimente recurente cu metode de plată salvate. Clienții autorizează cardul o dată și cumpără bilete viitoare cu un singur click. Reduce abandonul checkout-ului pentru clienții recurenți.

### Prețuri Multi-Valută
Stabilește prețurile biletelor în valute locale fără bătăi de cap cu cursul de schimb. Stripe gestionează automat conversia și decontarea. Clienții văd prețurile în valuta lor familiară.

### Evenimente Premium
Vinde pachete VIP de mare valoare cu încredere. Protecția împotriva fraudei Stripe și gestionarea chargeback-urilor protejează împotriva disputelor de plată la tranzacții mari.

---

## Documentație Tehnică

### Prezentare Generală

Microserviciul de Integrare Stripe gestionează procesarea plăților prin API-ul Stripe. Gestionează crearea payment intent, confirmarea, procesarea webhook și operațiunile de rambursare.

### Cerințe Preliminare

- Cont Stripe activ (Standard sau Express)
- Chei API (publishable și secret)
- Endpoint webhook configurat în Dashboard-ul Stripe

### Configurare

```php
'stripe' => [
    'publishable_key' => env('STRIPE_PUBLISHABLE_KEY'),
    'secret_key' => env('STRIPE_SECRET_KEY'),
    'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
    'currency' => 'eur',
    'payment_methods' => ['card', 'sepa_debit', 'ideal'],
]
```

### Endpoint-uri API

#### Creare Payment Intent

```
POST /api/payments/stripe/intent
```

Creează un payment intent pentru o sesiune de checkout.

**Cerere:**
```json
{
  "amount": 5000,
  "currency": "eur",
  "order_id": "order_123",
  "customer_email": "client@exemplu.com",
  "metadata": {
    "event_id": 456,
    "ticket_ids": [1, 2, 3]
  }
}
```

**Răspuns:**
```json
{
  "client_secret": "pi_xxx_secret_xxx",
  "payment_intent_id": "pi_xxx",
  "amount": 5000,
  "currency": "eur"
}
```

#### Confirmare Plată

```
POST /api/payments/stripe/confirm
```

Confirmă o plată după autorizarea clientului.

#### Procesare Rambursare

```
POST /api/payments/stripe/refund
```

**Cerere:**
```json
{
  "payment_intent_id": "pi_xxx",
  "amount": 2500,
  "reason": "requested_by_customer"
}
```

#### Obținere Status Plată

```
GET /api/payments/stripe/{paymentIntentId}
```

Returnează statusul curent al unui payment intent.

### Evenimente Webhook

Configurează endpoint-ul webhook pentru a primi:

| Eveniment | Descriere |
|-----------|-----------|
| `payment_intent.succeeded` | Plata finalizată cu succes |
| `payment_intent.payment_failed` | Încercare de plată eșuată |
| `charge.refunded` | Rambursare procesată |
| `charge.dispute.created` | Clientul a inițiat o dispută |

### Handler Webhook

```php
POST /api/webhooks/stripe

// Verificarea semnăturii
$payload = @file_get_contents('php://input');
$sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'];
$event = \Stripe\Webhook::constructEvent(
    $payload, $sig_header, $webhook_secret
);
```

### Integrare Frontend

```javascript
// Inițializare Stripe
const stripe = Stripe('pk_live_xxx');
const elements = stripe.elements();

// Creare element de plată
const paymentElement = elements.create('payment');
paymentElement.mount('#payment-element');

// Gestionare trimitere
const { error } = await stripe.confirmPayment({
  elements,
  confirmParams: {
    return_url: 'https://site-ul-tau.com/checkout/complete',
  },
});
```

### Gestionarea Erorilor

| Cod Eroare | Descriere | Acțiune |
|------------|-----------|---------|
| `card_declined` | Cardul a fost refuzat | Solicită metodă de plată diferită |
| `expired_card` | Cardul a expirat | Solicită detalii actualizate ale cardului |
| `insufficient_funds` | Fonduri insuficiente | Sugerează sumă mai mică sau alt card |
| `authentication_required` | 3DS necesar | Declanșează fluxul de autentificare |

### Testare

Folosește modul de test Stripe cu chei API de test. Numere de card pentru test:

- `4242424242424242` - Plată reușită
- `4000000000000002` - Card refuzat
- `4000002500003155` - Necesită 3D Secure

### Bune Practici de Securitate

1. Nu loga și nu stoca niciodată numerele de card brute
2. Folosește semnături webhook pentru verificarea evenimentelor
3. Implementează chei de idempotență pentru reîncercări
4. Activează regulile Radar pentru prevenirea fraudei
5. Monitorizează pentru tipare de tranzacții neobișnuite
