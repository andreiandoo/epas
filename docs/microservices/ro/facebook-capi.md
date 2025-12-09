# Facebook Conversions API

## Prezentare Scurtă

Fă ca fiecare euro din reclamele Facebook să conteze. Facebook Conversions API trimite vânzările tale de bilete direct de pe serverele tale către Meta, ocolind limitările browserelor care fac ca pixelii tradiționali să rateze conversii. Obține date precise, optimizare mai bună și randamente mai mari.

Schimbările de confidențialitate iOS și ad blocker-ele au degradat urmărirea prin pixel. Conversions API rezolvă asta stabilind o conexiune directă server-to-server cu Facebook. Când cineva cumpără un bilet, Facebook știe - chiar dacă browserul lor blochează scripturile de tracking.

Date mai bune înseamnă campanii mai inteligente. Machine learning-ul Facebook are nevoie de semnale de conversie precise pentru a-ți găsi cei mai buni clienți. Evenimentele server-side oferă date fiabile care îmbunătățesc targetarea audiențelor și optimizarea licitărilor.

Urmărește călătoria completă de achiziție. Dincolo de vânzările finale, monitorizează evenimentele Add to Cart, Initiate Checkout și Lead. Înțelege unde renunță clienții și optimizează-ți funnel-ul.

Construiește audiențe puternice. Folosește datele tale de clienți pentru a crea Custom Audiences pentru retargeting și Lookalike Audiences pentru a găsi noi cumpărători de bilete. Datele server-side îmbunătățesc calitatea audiențelor.

Deduplicarea evenimentelor previne dubla contorizare. Dacă rulezi și Meta Pixel pe site, integrarea deduplică automat evenimentele pentru a menține atribuirea precisă.

Conformă cu confidențialitatea prin design. Datele clienților sunt hash-uite înainte de transmisie. Facebook primește doar identificatori criptați necesari pentru potrivire - niciodată informații personale brute.

Deblochează întregul potențial al publicității Facebook. Urmărirea precisă transformă ghicitul în decizii bazate pe date.

---

## Descriere Detaliată

Microserviciul Facebook Conversions API oferă urmărirea evenimentelor server-side pentru platformele publicitare Meta (Facebook, Instagram, Audience Network). Trimite evenimente de conversie direct către serverele Facebook, permițând atribuirea precisă în ciuda limitărilor de urmărire ale browserelor.

### De Ce Urmărirea Server-Side

Urmărirea bazată pe browser întâmpină multiple provocări:

- **Ad Blockers**: Previn încărcarea scripturilor pixel
- **iOS 14.5+**: App Tracking Transparency limitează urmărirea
- **Confidențialitatea Browserului**: Safari ITP, Firefox ETP restricționează cookie-urile
- **Probleme de Rețea**: Apelurile pixel pot eșua silențios

Evenimentele server-side ocolesc toate aceste probleme trimițând date direct din infrastructura ta către Facebook.

### Tipuri de Evenimente

Integrarea urmărește evenimente standard Facebook:

- **Purchase**: Comenzi de bilete finalizate
- **Lead**: Înregistrări utilizatori și trimiteri de formulare
- **CompleteRegistration**: Crearea contului
- **AddToCart**: Bilete adăugate în coș
- **InitiateCheckout**: Checkout început
- **ViewContent**: Vizualizări pagini evenimente

Fiecare eveniment include parametri relevanți pentru valoare, valută, detalii conținut și informații utilizator.

### Potrivirea Atribuirii

Facebook potrivește evenimentele server cu utilizatorii prin multipli identificatori:

- **fbclid**: Facebook Click ID din click-uri pe reclame
- **cookie fbc**: Cookie first-party care stochează fbclid
- **cookie fbp**: ID-ul de browser Facebook
- **Email**: Adresă de email hash-uită
- **Telefon**: Număr de telefon hash-uit
- **External ID**: Identificatorul tău de utilizator (hash-uit)

Cu cât mai mulți identificatori sunt furnizați, cu atât rata de potrivire e mai mare.

### Calitatea Evenimentelor

Facebook atribuie scoruri Event Match Quality (1-10) bazate pe:

- Numărul de parametri utilizator trimiși
- Acuratețea parametrilor
- Livrare în timp real
- Configurația de deduplicare

Scorurile mai mari de calitate indică potențial mai bun de atribuire și optimizare.

### Custom Audiences

Datele server-side alimentează crearea audiențelor:

- **Website Custom Audiences**: Bazate pe evenimente server
- **Liste Clienți**: Încarcă date cumpărători pentru potrivire
- **Lookalike Audiences**: Găsește utilizatori similari cu clienții tăi
- **Engagement Audiences**: Utilizatori care au interacționat cu conținutul

---

## Funcționalități

### Urmărirea Evenimentelor
- Urmărire conversii Purchase
- Urmărire evenimente Lead
- Evenimente CompleteRegistration
- Urmărire AddToCart
- Evenimente InitiateCheckout
- Urmărire pagini ViewContent
- Suport evenimente personalizate

### Atribuire
- Urmărire Facebook Click ID (fbclid)
- Suport Browser ID (_fbp)
- Urmărire cookie Click ID (_fbc)
- Atribuire cross-device
- Deduplicare cu Pixel

### Potrivirea Utilizatorilor
- Potrivire email hash-uit
- Potrivire telefon hash-uit
- Suport External ID
- Adresă IP (pentru geo)
- Date user agent
- Țară și regiune

### Integrare Audiențe
- Sincronizare Custom Audience
- Încărcări liste clienți
- Generare Lookalike
- Audiențe vizitatori site
- Audiențe bazate pe achiziții

### Calitatea Datelor
- Monitorizare Event Match Quality
- Livrare în timp real
- Reîncercare la eșec
- Validare înainte de trimitere
- Logare și alerte erori

### Conformitate Confidențialitate
- Hash-uire date SHA-256
- Verificare consimțământ
- Minimizarea datelor
- Compatibilitate GDPR
- Politici clare de date

---

## Cazuri de Utilizare

### Reclame Facebook și Instagram
Optimizează campaniile în rețeaua publicitară Meta cu date de achiziție precise. Fie că rulezi reclame News Feed, Stories sau Reels, asigură-te că fiecare vânzare de bilet este atribuită corect.

### Campanii Advantage+
Alimentează cu date de conversie fiabile campaniile automatizate Meta. Campaniile Advantage+ Shopping și App se bazează mult pe semnalele de conversie pentru optimizare. Date mai bune înseamnă performanță mai bună.

### Campanii de Retargeting
Creează audiențe din vizitatorii site-ului și cei care au abandonat coșul. Evenimentele server-side captează mai mulți utilizatori decât pixelul singur, extinzând pool-ul tău de retargeting.

### Prospectare Lookalike
Construiește Lookalike Audiences din cumpărătorii tăi reali de bilete. Audiențele seed de calitate bazate pe date server produc prospecți cu potrivire mai bună.

### Analiză Performanță
Înțelege adevăratul return on ad spend cu atribuire precisă. Ia decizii informate despre creative, audiențe și alocare buget bazate pe date reale de achiziție.

### Optimizare Multi-Eveniment
Optimizează pentru diferite etape ale funnel-ului - lead-uri pentru evenimente gratuite, achiziții pentru bilete cu plată. Tipurile multiple de evenimente permit strategii de campanii sofisticate.

---

## Documentație Tehnică

### Prezentare Generală

Microserviciul Facebook Conversions API se conectează la Meta Marketing API pentru a trimite evenimente server-side. Gestionează colectarea evenimentelor, hash-uirea datelor utilizatorilor, transmisia API și deduplicarea cu pixelii din browser.

### Cerințe Preliminare

- Cont Facebook Business
- Facebook Pixel creat
- Token de acces Conversions API (System User)
- Permisiune ads_management acordată

### Configurare

```php
'facebook_capi' => [
    'pixel_id' => env('FACEBOOK_PIXEL_ID'),
    'access_token' => env('FACEBOOK_ACCESS_TOKEN'),
    'test_mode' => env('FACEBOOK_TEST_MODE', false),
    'test_event_code' => env('FACEBOOK_TEST_EVENT_CODE'),
    'events' => [
        'purchase' => true,
        'lead' => true,
        'registration' => true,
        'add_to_cart' => true,
        'initiate_checkout' => true,
    ],
]
```

### Endpoint-uri API

#### Status Conexiune

```
GET /api/integrations/facebook-capi/connection
```

**Răspuns:**
```json
{
  "connected": true,
  "pixel_id": "123456789",
  "pixel_name": "Pixelul Meu de Evenimente",
  "last_event_time": "2025-01-15T14:30:00Z",
  "event_match_quality": 7.2
}
```

#### Creare Conexiune

```
POST /api/integrations/facebook-capi/connect
```

**Cerere:**
```json
{
  "pixel_id": "123456789",
  "access_token": "token_ul_tau_de_acces_system_user"
}
```

#### Trimitere Eveniment

```
POST /api/integrations/facebook-capi/events
```

**Cerere:**
```json
{
  "event_name": "Purchase",
  "event_time": 1705326600,
  "event_id": "order_123",
  "event_source_url": "https://site-ul-tau.com/checkout/complete",
  "action_source": "website",
  "user_data": {
    "email": "client@exemplu.com",
    "phone": "+40721234567",
    "first_name": "Ion",
    "last_name": "Popescu",
    "city": "București",
    "country": "RO",
    "external_id": "user_456",
    "client_ip_address": "192.168.1.1",
    "client_user_agent": "Mozilla/5.0...",
    "fbc": "fb.1.1234567890.abcdef",
    "fbp": "fb.1.1234567890.1234567890"
  },
  "custom_data": {
    "value": 150.00,
    "currency": "EUR",
    "content_type": "product",
    "contents": [
      {
        "id": "ticket_789",
        "quantity": 2,
        "item_price": 75.00
      }
    ],
    "num_items": 2,
    "order_id": "order_123"
  }
}
```

**Răspuns:**
```json
{
  "success": true,
  "events_received": 1,
  "fbtrace_id": "ABC123..."
}
```

#### Creare Custom Audience

```
POST /api/integrations/facebook-capi/audiences
```

**Cerere:**
```json
{
  "name": "Cumpărători Bilete - Ultimele 90 Zile",
  "description": "Clienți care au cumpărat bilete în ultimele 90 de zile",
  "customer_file_source": "USER_PROVIDED_ONLY"
}
```

#### Încărcare Membri Audiență

```
POST /api/integrations/facebook-capi/audiences/{id}/users
```

**Cerere:**
```json
{
  "schema": ["EMAIL", "PHONE", "FN", "LN"],
  "data": [
    ["a1b2c3...", "d4e5f6...", "g7h8...", "i9j0..."],
    ["k1l2m3...", "n4o5p6...", "q7r8...", "s9t0..."]
  ]
}
```

### Structura Evenimentului

```json
{
  "data": [
    {
      "event_name": "Purchase",
      "event_time": 1705326600,
      "event_id": "unique_event_id",
      "event_source_url": "https://site-ul-tau.com/checkout",
      "action_source": "website",
      "user_data": {
        "em": ["email_hashuit"],
        "ph": ["telefon_hashuit"],
        "fn": ["prenume_hashuit"],
        "ln": ["nume_hashuit"],
        "ct": ["oras_hashuit"],
        "country": ["ro"],
        "external_id": ["user_id_hashuit"],
        "client_ip_address": "192.168.1.1",
        "client_user_agent": "Mozilla/5.0...",
        "fbc": "fb.1.1234567890.abcdef",
        "fbp": "fb.1.1234567890.1234567890"
      },
      "custom_data": {
        "value": 150.00,
        "currency": "EUR",
        "content_type": "product",
        "contents": [...],
        "num_items": 2
      }
    }
  ]
}
```

### Hash-uirea Datelor Utilizatorului

```php
// Hash-uiește toate PII înainte de trimitere
$hashedEmail = hash('sha256', strtolower(trim($email)));
$hashedPhone = hash('sha256', preg_replace('/[^0-9]/', '', $phone));
$hashedFirstName = hash('sha256', strtolower(trim($firstName)));
$hashedLastName = hash('sha256', strtolower(trim($lastName)));
$hashedCity = hash('sha256', strtolower(str_replace(' ', '', $city)));

// Codul țării - lowercase, nu hash-uit
$countryCode = strtolower($country); // 'ro'
```

### Capturarea Click ID

```javascript
// Captează fbclid din URL
const urlParams = new URLSearchParams(window.location.search);
const fbclid = urlParams.get('fbclid');
if (fbclid) {
    // Stochează ca cookie _fbc în format Meta
    const fbc = `fb.1.${Date.now()}.${fbclid}`;
    document.cookie = `_fbc=${fbc};max-age=7776000;path=/`;
}

// Captează _fbp (setat automat de Pixel)
const fbp = document.cookie
    .split('; ')
    .find(row => row.startsWith('_fbp='))
    ?.split('=')[1];
```

### Deduplicarea Evenimentelor

Folosește același event_id pentru evenimentele Pixel și CAPI:

```php
// Generează event ID consistent
$eventId = 'purchase_' . $order->id . '_' . $order->created_at->timestamp;

// Trimite la CAPI
$capiEvent = [
    'event_id' => $eventId,
    // ...
];

// Pixelul se declanșează cu același ID
// fbq('track', 'Purchase', data, {eventID: eventId});
```

### Modul Test

Validează evenimentele fără a afecta campaniile:

```php
'test_mode' => true,
'test_event_code' => 'TEST12345'
```

Evenimentele de test apar în Events Manager > Test Events.

### Gestionarea Erorilor

| Cod Eroare | Descriere | Acțiune |
|------------|-----------|---------|
| 200 | Succes | Eveniment primit |
| 2200 | Timp eveniment invalid | Verifică timestamp |
| 2201 | Sursă acțiune invalidă | Folosește sursă validă |
| 2202 | Parametru necesar lipsă | Adaugă câmpurile lipsă |
| 190 | Token acces invalid | Reîmprospătează token |

### Limite de Rate

- 1.000 evenimente per cerere
- 100.000 evenimente per oră per pixel
- Fără limită zilnică

### Calitatea Event Match

Monitorizează calitatea în Events Manager:

| Scor | Calitate | Acțiune |
|------|----------|---------|
| 8-10 | Excelentă | Menține configurația curentă |
| 6-7 | Bună | Consideră adăugarea parametrilor |
| 4-5 | Acceptabilă | Adaugă mai mulți identificatori utilizator |
| 1-3 | Slabă | Revizuiește implementarea |

### Exemplu de Integrare

```php
class FacebookCapiService
{
    public function trackPurchase(Order $order): void
    {
        $event = [
            'event_name' => 'Purchase',
            'event_time' => now()->timestamp,
            'event_id' => 'purchase_' . $order->id,
            'event_source_url' => url('/checkout/complete'),
            'action_source' => 'website',
            'user_data' => $this->buildUserData($order->customer),
            'custom_data' => [
                'value' => $order->total,
                'currency' => $order->currency,
                'content_type' => 'product',
                'contents' => $order->items->map(fn($item) => [
                    'id' => $item->ticket_type_id,
                    'quantity' => $item->quantity,
                    'item_price' => $item->price,
                ])->all(),
                'num_items' => $order->items->sum('quantity'),
                'order_id' => $order->id,
            ],
        ];

        dispatch(new SendFacebookEvent($event));
    }

    private function buildUserData(Customer $customer): array
    {
        return [
            'em' => [hash('sha256', strtolower($customer->email))],
            'ph' => [hash('sha256', $customer->phone)],
            'fn' => [hash('sha256', strtolower($customer->first_name))],
            'ln' => [hash('sha256', strtolower($customer->last_name))],
            'country' => [strtolower($customer->country_code)],
            'external_id' => [hash('sha256', $customer->id)],
            'fbc' => $customer->fbc_cookie,
            'fbp' => $customer->fbp_cookie,
            'client_ip_address' => request()->ip(),
            'client_user_agent' => request()->userAgent(),
        ];
    }
}
```
