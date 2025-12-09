# Integrare TikTok Ads

## Prezentare Scurtă

Ajunge la următoarea generație de participanți la evenimente pe TikTok. Cu peste un miliard de utilizatori activi, TikTok a devenit o platformă de descoperire unde oamenii își găsesc următorul concert, festival sau experiență. Acum poți urmări exact cum reclamele TikTok generează vânzări de bilete.

Integrarea TikTok Ads trimite evenimente server-side direct către TikTok când clienții cumpără bilete. Nu mai ghici care videouri și audiențe convertesc - vei ști. Aceste date alimentează algoritmul TikTok, ajutându-l să găsească mai mulți oameni predispuși să cumpere.

Urmărirea server-side înseamnă date fiabile. Pixelii din browser sunt blocați de instrumentele de confidențialitate, dar evenimentele server-side ajung direct la TikTok. Fiecare achiziție contează pentru optimizarea campaniilor tale.

Urmărește întreaga călătorie. De la prima vizualizare video până la achiziția finală, monitorizează evenimentele Add to Cart, Initiate Checkout și Complete Payment. Înțelege unde renunță prospecții și optimizează în consecință.

Construiește audiențe din cumpărători reali. Încarcă liste de clienți pe TikTok și creează audiențe lookalike. Găsește persoane similare cu cumpărătorii tăi de bilete în baza masivă de utilizatori TikTok.

Designul privacy-first protejează datele clienților. Toate informațiile personale sunt hash-uite înainte de a părăsi serverele tale. TikTok primește doar identificatori criptați pentru potrivire.

Deduplicarea evenimentelor asigură contorizarea precisă. Dacă folosești și TikTok Pixel pe site, integrarea previne automat dubla contorizare a conversiilor.

Transformă potențialul viral al TikTok în vânzări de bilete. Urmărește, optimizează și scalează campaniile cu încredere.

---

## Descriere Detaliată

Microserviciul de Integrare TikTok Ads oferă urmărirea conversiilor server-side prin TikTok Events API. Trimite evenimente de achiziție și engagement de pe serverele tale direct către TikTok, permițând atribuirea precisă și optimizarea campaniilor.

### Avantajul Server-Side

Spre deosebire de pixelii bazați pe browser care pot fi blocați de ad blockere, funcțiile de confidențialitate ale browserelor sau App Tracking Transparency iOS, evenimentele server-side ocolesc aceste limitări. Datele tale de conversie ajung fiabil la TikTok, oferind algoritmului semnale precise pentru optimizare.

### Tipuri de Evenimente

Integrarea urmărește evenimente cheie în funnel-ul de achiziție:

- **ViewContent**: Când utilizatorii vizualizează pagini de evenimente
- **AddToCart**: Când biletele sunt adăugate în coș
- **InitiateCheckout**: Când începe checkout-ul
- **CompletePayment**: Când achiziția este finalizată
- **CompleteRegistration**: Când utilizatorii se înregistrează

Fiecare eveniment include parametri relevanți precum tipul de conținut, valoarea și valuta.

### Urmărirea TikTok Click ID

Când utilizatorii dau click pe reclame TikTok, ajung cu un parametru `ttclid`. Integrarea:

1. Captează ttclid din URL
2. Îl stochează într-un cookie first-party
3. Îl asociază cu sesiunea utilizatorului
4. Îl include în evenimentele de conversie

Aceasta permite TikTok să atribuie conversiile către click-uri specifice pe reclame.

### Cookie TikTok (_ttp)

Integrarea urmărește și cookie-ul first-party TikTok (`_ttp`), care oferă semnale suplimentare de potrivire. Combinat cu ttclid și datele hash-uite ale utilizatorilor, aceasta maximizează acuratețea atribuirii.

### Audiențe Personalizate

Creează audiențe bazate pe datele tale de clienți:

- Încarcă liste de cumpărători pentru retargeting
- Construiește audiențe lookalike din cei mai buni clienți
- Exclude deținătorii de bilete existenți din prospectare
- Segmentează după tipul evenimentului, frecvența achizițiilor sau valoare

### Procesare Batch

Evenimentele sunt puse în coadă și trimise în loturi pentru eficiență:

- Până la 1.000 de evenimente pe lot
- Reîncercare automată la eșecuri
- Conformitate cu limita de rate (50.000 evenimente/zi)
- Procesare în background pentru performanță

---

## Funcționalități

### Urmărirea Evenimentelor
- Urmărire CompletePayment (achiziție)
- Evenimente AddToCart
- Evenimente InitiateCheckout
- Evenimente CompleteRegistration
- ViewContent pagini
- Suport evenimente personalizate

### Atribuire
- Urmărire TikTok Click ID (ttclid)
- Suport cookie first-party (_ttp)
- Atribuire cross-device
- Deduplicarea evenimentelor
- Fereastră de atribuire 28 zile

### Confidențialitate și Securitate
- Hash-uire date utilizator SHA-256
- Transmisie date server-side
- Verificare consimțământ GDPR
- Colectare minimă de date
- Stocare securizată token-uri

### Gestionarea Audiențelor
- Creare audiențe personalizate
- Încărcări liste clienți
- Construire audiențe lookalike
- Automatizare sincronizare audiențe
- Suport liste de excludere

### Monitorizare
- Status evenimente în timp real
- Validare în modul test
- Logare erori
- Urmărirea răspunsurilor API
- Confirmare livrare evenimente

### Integrare Campanii
- Optimizare bazată pe valoare
- Urmărire Content ID
- Suport catalog produse
- Deduplicare conversii
- Suport multi-pixel

---

## Cazuri de Utilizare

### Promovarea Concertelor
Promovează concertele viitoare fanilor muzicii pe TikTok. Urmărește care stiluri video și sunete generează achiziții de bilete. Scalează creative-urile câștigătoare și retrage pe cele slabe.

### Marketing pentru Festivaluri
Ajunge la audiențele de festival prin conținut de creatori și reclame plătite. Măsoară ROI-ul real urmărind achizițiile, nu doar engagement-ul. Construiește audiențe lookalike din participanții anteriori.

### Descoperirea Evenimentelor
Utilizatorii TikTok caută activ experiențe la care să participe. Poziționează evenimentele tale în fața audiențelor orientate spre descoperire. Urmărește ce tipuri de evenimente rezonează cu demografia TikTok.

### Evenimente pentru Tineri
Ajunge la audiențele Gen Z acolo unde își petrec timpul. Demografia mai tânără a TikTok este perfectă pentru concerte, festivaluri și evenimente de divertisment. Urmărește conversiile cu precizie în ciuda schimbărilor de confidențialitate iOS.

### Campanii de Retargeting
Adu înapoi utilizatorii care au vizualizat evenimente dar nu au cumpărat. Creează audiențe din vizitatorii site-ului și cei care au abandonat coșul. Servește reclame personalizate bazate pe interesele lor.

### Atribuire Influenceri
Când creatorii îți promovează evenimentele, urmărește vânzările de bilete rezultate. Înțelege care parteneriate cu influenceri generează venituri reale, nu doar vizualizări.

---

## Documentație Tehnică

### Prezentare Generală

Microserviciul de Integrare TikTok Ads se conectează la TikTok Events API v1.3 pentru urmărirea conversiilor server-side. Gestionează colectarea evenimentelor, hash-uirea datelor utilizatorilor, încărcările batch și gestionarea audiențelor.

### Cerințe Preliminare

- Cont TikTok For Business
- Acces la TikTok Ads Manager
- TikTok Pixel creat
- Token de acces Events API generat

### Configurare

```php
'tiktok_ads' => [
    'pixel_id' => env('TIKTOK_PIXEL_ID'),
    'access_token' => env('TIKTOK_ACCESS_TOKEN'),
    'advertiser_id' => env('TIKTOK_ADVERTISER_ID'),
    'test_mode' => env('TIKTOK_TEST_MODE', false),
    'test_event_code' => env('TIKTOK_TEST_EVENT_CODE'),
    'events' => [
        'track_purchases' => true,
        'track_add_to_cart' => true,
        'track_checkout' => true,
        'track_registrations' => true,
    ],
]
```

### Endpoint-uri API

#### Status Conexiune

```
GET /api/integrations/tiktok-ads/connection
```

**Răspuns:**
```json
{
  "connected": true,
  "pixel_id": "CXXXXXX",
  "advertiser_id": "123456789",
  "test_mode": false,
  "last_event_sent": "2025-01-15T14:30:00Z"
}
```

#### Creare Conexiune

```
POST /api/integrations/tiktok-ads/connect
```

**Cerere:**
```json
{
  "pixel_id": "CXXXXXX",
  "access_token": "token_ul_tau_de_acces",
  "advertiser_id": "123456789"
}
```

#### Trimitere Eveniment

```
POST /api/integrations/tiktok-ads/events
```

**Cerere:**
```json
{
  "event_type": "CompletePayment",
  "event_time": 1705326600,
  "event_id": "order_123",
  "user": {
    "email": "client@exemplu.com",
    "phone": "+40721234567",
    "external_id": "user_456"
  },
  "properties": {
    "value": 150.00,
    "currency": "EUR",
    "content_type": "product",
    "contents": [
      {
        "content_id": "ticket_789",
        "content_name": "Concert VIP Pass",
        "quantity": 2,
        "price": 75.00
      }
    ]
  },
  "context": {
    "page_url": "https://site-ul-tau.com/checkout/complete",
    "user_agent": "Mozilla/5.0...",
    "ip": "192.168.1.1"
  }
}
```

**Răspuns:**
```json
{
  "success": true,
  "event_id": "order_123",
  "code": 0,
  "message": "OK"
}
```

#### Evenimente Batch

```
POST /api/integrations/tiktok-ads/events/batch
```

Trimite multiple evenimente într-o singură cerere (până la 1.000 de evenimente).

#### Creare Audiență

```
POST /api/integrations/tiktok-ads/audiences
```

**Cerere:**
```json
{
  "advertiser_id": "123456789",
  "custom_audience_name": "Cumpărători Anteriori Bilete",
  "audience_type": "CUSTOMER_FILE"
}
```

#### Sincronizare Audiență

```
POST /api/integrations/tiktok-ads/audiences/{id}/sync
```

**Cerere:**
```json
{
  "action": "APPEND",
  "id_schema": ["EMAIL_SHA256", "PHONE_SHA256"],
  "members": [
    ["a1b2c3...", "d4e5f6..."],
    ["g7h8i9...", "j0k1l2..."]
  ]
}
```

### Structura Evenimentului

```json
{
  "pixel_code": "CXXXXXX",
  "event": "CompletePayment",
  "event_id": "unique_event_id",
  "timestamp": "2025-01-15T14:30:00Z",
  "context": {
    "ad": {
      "callback": "valoare_ttclid"
    },
    "page": {
      "url": "https://site-ul-tau.com/checkout"
    },
    "user": {
      "external_id": "user_id_hashuit",
      "email": "email_hashuit",
      "phone_number": "telefon_hashuit"
    },
    "user_agent": "Mozilla/5.0...",
    "ip": "192.168.1.1"
  },
  "properties": {
    "contents": [...],
    "currency": "EUR",
    "value": 150.00
  }
}
```

### Hash-uirea Datelor Utilizatorului

```php
// Toate datele utilizatorului trebuie hash-uite cu SHA-256
$hashedEmail = hash('sha256', strtolower(trim($email)));

// Telefon în format E.164 înainte de hash-uire
$phone = preg_replace('/[^0-9]/', '', $phone);
$hashedPhone = hash('sha256', $phone);

// External ID (ID-ul tău de utilizator)
$hashedExternalId = hash('sha256', $userId);
```

### Deduplicarea Evenimentelor

Folosește valori consistente pentru event_id:

```php
// Pentru achiziții
$eventId = 'purchase_' . $order->id;

// Pentru evenimente coș
$eventId = 'cart_' . $session->id . '_' . time();
```

TikTok deduplică evenimentele cu event_id potrivit în 48 de ore.

### Capturarea Click ID

```javascript
// Captează ttclid din URL la încărcarea paginii
const urlParams = new URLSearchParams(window.location.search);
const ttclid = urlParams.get('ttclid');
if (ttclid) {
    document.cookie = `ttclid=${ttclid};max-age=2592000;path=/`;
}

// De asemenea captează cookie-ul _ttp pentru potrivire suplimentară
const ttpCookie = document.cookie
    .split('; ')
    .find(row => row.startsWith('_ttp='))
    ?.split('=')[1];
```

### Modul Test

Activează modul test pentru validare fără a afecta campaniile:

```php
'test_mode' => true,
'test_event_code' => 'TEST12345'
```

Evenimentele de test apar în TikTok Events Manager sub "Test Events".

### Gestionarea Erorilor

| Cod | Descriere | Acțiune |
|-----|-----------|---------|
| 0 | Succes | Eveniment acceptat |
| 40001 | Pixel invalid | Verifică pixel_id |
| 40002 | Eveniment invalid | Verifică structura evenimentului |
| 40003 | Date utilizator invalide | Verifică formatul hash-uirii |
| 40100 | Limită rate depășită | Implementează backoff |

### Limite de Rate

- 50.000 evenimente pe zi per pixel
- 1.000 evenimente per cerere batch
- 10 cereri pe secundă

### Exemplu de Integrare

```php
class TikTokEventService
{
    public function trackPurchase(Order $order): void
    {
        $event = [
            'event_type' => 'CompletePayment',
            'event_id' => 'purchase_' . $order->id,
            'event_time' => now()->timestamp,
            'user' => $this->hashUserData($order->customer),
            'properties' => [
                'value' => $order->total,
                'currency' => $order->currency,
                'contents' => $order->items->map(fn($item) => [
                    'content_id' => $item->ticket_type_id,
                    'content_name' => $item->ticket_type_name,
                    'quantity' => $item->quantity,
                    'price' => $item->price,
                ])->all(),
            ],
            'context' => [
                'ttclid' => $order->ttclid,
                'ttp' => $order->ttp_cookie,
            ],
        ];

        dispatch(new SendTikTokEvent($event));
    }
}
```
