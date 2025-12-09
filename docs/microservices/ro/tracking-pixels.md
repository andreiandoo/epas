# Tracking & Pixels Manager

## Prezentare Scurtă

Un singur loc pentru a gestiona toate scripturile tale de tracking. Tracking & Pixels Manager gestionează Google Analytics 4, Google Tag Manager, Meta Pixel și TikTok Pixel cu management GDPR al consimțământului integrat. Nu sunt necesare schimbări de cod - configurează totul din dashboard.

Înțelegerea audienței tale începe cu tracking adecvat. Vezi de unde vin vizitatorii, ce evenimente vizualizează și ce îi convertește în cumpărători. Ia decizii bazate pe date în loc să ghicești.

Google Analytics 4 dezvăluie comportamentul vizitatorilor. Urmărește vizualizările de pagină, interacțiunile cu evenimentele și călătoriile complete de ecommerce. Înțelege funnel-ul tău de la prima vizită până la achiziția biletului.

Meta Pixel și TikTok Pixel alimentează publicitatea ta. Când aceste platforme știu cine cumpără bilete, pot găsi mai mulți oameni ca ei. Tracking mai bun înseamnă performanță mai bună a reclamelor.

Google Tag Manager aduce flexibilitate. Implementează și gestionează tag-uri fără a atinge codul. Actualizează configurațiile de tracking instantaneu pe măsură ce nevoile tale se schimbă.

Conformitatea GDPR este integrată. Sistemul de management al consimțământului asigură că tracking-ul se activează doar când utilizatorii dau permisiunea. Bannere de cookie, preferințe de consimțământ și încărcare conformă a scripturilor - toate gestionate automat.

Evenimentele de ecommerce urmăresc călătoria de achiziție. Evenimentele View Item, Add to Cart, Begin Checkout și Purchase se activează automat la fiecare etapă. Vezi exact unde renunță clienții.

Injecția de scripturi conformă CSP menține site-ul tău securizat. Toate scripturile de tracking se încarcă prin canale aprobate care îndeplinesc cerințele Content Security Policy.

Urmărește mai inteligent. Conformează-te mai ușor. Vinde mai multe bilete.

---

## Descriere Detaliată

Microserviciul Tracking & Pixels Manager oferă un sistem centralizat pentru gestionarea pixelilor de analytics și publicitate pe platforma ta de ticketing pentru evenimente. Gestionează încărcarea scripturilor, managementul consimțământului și urmărirea evenimentelor ecommerce pe multiple platforme.

### Platforme Suportate

#### Google Analytics 4 (GA4)
Integrare completă GA4 cu măsurare îmbunătățită și tracking ecommerce. Urmărește vizualizările de pagină, engagement-ul utilizatorilor și funnel-ul complet de achiziție cu date de venituri.

#### Google Tag Manager (GTM)
Implementează containerul GTM pentru management avansat de tag-uri. Include integrare dataLayer integrată pentru evenimente ecommerce. Permite configurații complexe de tracking fără schimbări de cod.

#### Meta Pixel
Pixel Facebook/Instagram pentru optimizarea publicității. Urmărește evenimente standard (ViewContent, AddToCart, Purchase) și permite Custom Audiences și optimizarea conversiilor.

#### TikTok Pixel
Pixel de publicitate TikTok pentru urmărirea campaniilor. Evenimentele standard de ecommerce ajută la optimizarea livrării reclamelor TikTok și măsurarea performanței.

### Managementul Consimțământului

Sistem de consimțământ conform GDPR cu:

- **Banner Cookie**: Popup de consimțământ personalizabil la prima vizită
- **Categorii de Consimțământ**: Grupări Analytics, Marketing, Funcționale
- **Control Granular**: Utilizatorii aleg ce tracking să permită
- **Stocare Preferințe**: Alegerile de consimțământ sunt memorate
- **Blocare Scripturi**: Tracking-ul dezactivat până la acordarea consimțământului

### Urmărirea Evenimentelor Ecommerce

Evenimente standard ecommerce urmărite automat:

| Eveniment | Declanșator | Platforme |
|-----------|-------------|-----------|
| view_item | Pagină eveniment vizualizată | GA4, Meta, TikTok |
| add_to_cart | Bilet adăugat în coș | GA4, Meta, TikTok |
| begin_checkout | Checkout început | GA4, Meta, TikTok |
| purchase | Comandă finalizată | GA4, Meta, TikTok |

Evenimentele includ detalii produs, cantități și venituri pentru atribuire completă.

### Încărcarea Scripturilor

Scripturile de tracking se încarcă condiționat pe baza:

1. Statusului consimțământului pentru fiecare categorie
2. Configurației specifice platformei
3. Tipului și contextului paginii
4. Preferințelor utilizatorului

Scripturile se injectează asincron pentru a minimiza impactul asupra încărcării paginii.

### Modul Debug

Instrumentele de debugging integrate ajută la verificarea tracking-ului:

- Modul preview arată evenimentele înainte să se activeze
- Logarea în consolă afișează toate apelurile de tracking
- Validare evenimente în timp real
- Vizualizări debug specifice platformei

---

## Funcționalități

### Integrare Analytics
- Configurare Google Analytics 4
- Măsurare îmbunătățită
- Urmărire evenimente personalizate
- Management proprietăți utilizator
- Urmărire sesiuni
- Atribuire sursă trafic

### Management Tag-uri
- Container Google Tag Manager
- Integrare DataLayer
- Suport variabile personalizate
- Reguli de activare tag-uri
- Control versiuni
- Preview și debug

### Pixeli Publicitate
- Integrare Meta Pixel
- Integrare TikTok Pixel
- Construire audiențe personalizate
- Urmărire conversii
- Optimizare evenimente
- Suport retargeting

### Managementul Consimțământului
- Banner cookie GDPR
- Management categorii consimțământ
- Stocare preferințe utilizator
- Încărcare scripturi bazată pe consimțământ
- Logare audit trail
- Integrare politică confidențialitate

### Urmărire Ecommerce
- Evenimente view_item
- Urmărire add_to_cart
- Evenimente begin_checkout
- Conversii purchase
- Urmărire venituri
- Data layer produs

### Funcționalități Tehnice
- Injecție conformă CSP
- Încărcare scripturi async
- Deduplicare evenimente
- Gestionare erori
- Optimizare performanță
- Pageviews nelimitate

---

## Cazuri de Utilizare

### Analytics Marketing
Înțelege ce canale de marketing generează vânzări de bilete. Atribuirea GA4 arată călătoria completă de la primul contact până la achiziție. Optimizează cheltuielile de marketing pe baza datelor reale.

### Optimizare Publicitate
Alimentează campaniile Meta și TikTok cu date de conversie precise. Pixelii urmăresc achizițiile pentru optimizarea campaniilor și construirea audiențelor. Tracking mai bun înseamnă cost per achiziție mai mic.

### Analiză Funnel
Vezi unde renunță vizitatorii în călătoria de achiziție. Compară ratele de conversie între paginile de evenimente, ratele de abandon coș și finalizarea checkout-ului. Identifică și repară punctele de fricțiune.

### Conformitate GDPR
Îndeplinește reglementările europene de confidențialitate fără a sacrifica analytics-ul. Managementul consimțământului asigură că tracking-ul se activează doar când este permis. Documentează conformitatea cu log-uri de audit.

### Testare A/B
Folosește GTM pentru a implementa instrumente de testare și urmări rezultatele. Măsoară care variații de pagină generează mai multe conversii. Optimizare bazată pe date pentru îmbunătățire continuă.

### Atribuire Multi-Platformă
Compară performanța între platformele de publicitate. Vezi cum funcționează împreună campaniile Meta, TikTok și Google. Alocă bugetul canalelor cu cea mai bună performanță.

---

## Documentație Tehnică

### Prezentare Generală

Microserviciul Tracking & Pixels Manager gestionează injecția scripturilor de tracking, managementul consimțământului și activarea evenimentelor ecommerce în GA4, GTM, Meta Pixel și TikTok Pixel.

### Configurare

```php
'tracking' => [
    'ga4' => [
        'enabled' => true,
        'measurement_id' => env('GA4_MEASUREMENT_ID'),
        'enhanced_measurement' => true,
    ],
    'gtm' => [
        'enabled' => true,
        'container_id' => env('GTM_CONTAINER_ID'),
    ],
    'meta_pixel' => [
        'enabled' => true,
        'pixel_id' => env('META_PIXEL_ID'),
    ],
    'tiktok_pixel' => [
        'enabled' => true,
        'pixel_id' => env('TIKTOK_PIXEL_ID'),
    ],
    'consent' => [
        'enabled' => true,
        'default_categories' => ['necessary'],
        'cookie_name' => 'cookie_consent',
        'cookie_lifetime' => 365, // zile
    ],
]
```

### Endpoint-uri API

#### Obținere Configurare Tracking

```
GET /api/tracking/config
```

Returnează configurarea curentă de tracking pentru frontend.

**Răspuns:**
```json
{
  "ga4": {
    "enabled": true,
    "measurement_id": "G-XXXXXXXXXX"
  },
  "gtm": {
    "enabled": true,
    "container_id": "GTM-XXXXXXX"
  },
  "meta_pixel": {
    "enabled": true,
    "pixel_id": "123456789"
  },
  "tiktok_pixel": {
    "enabled": true,
    "pixel_id": "CXXXXXXX"
  },
  "consent_required": true
}
```

#### Actualizare Configurare

```
PUT /api/tracking/config
```

Actualizează configurarea tracking-ului (doar admin).

#### Obținere Status Consimțământ

```
GET /api/tracking/consent
```

Returnează preferințele curente de consimțământ ale utilizatorului.

#### Actualizare Consimțământ

```
POST /api/tracking/consent
```

**Cerere:**
```json
{
  "necessary": true,
  "analytics": true,
  "marketing": false,
  "functional": true
}
```

### Evenimente DataLayer

Sistemul împinge evenimente ecommerce în dataLayer:

#### View Item

```javascript
dataLayer.push({
  event: 'view_item',
  ecommerce: {
    currency: 'EUR',
    value: 75.00,
    items: [{
      item_id: 'ticket_123',
      item_name: 'Concert VIP Pass',
      item_category: 'Concert',
      price: 75.00,
      quantity: 1
    }]
  }
});
```

#### Add to Cart

```javascript
dataLayer.push({
  event: 'add_to_cart',
  ecommerce: {
    currency: 'EUR',
    value: 150.00,
    items: [{
      item_id: 'ticket_123',
      item_name: 'Concert VIP Pass',
      price: 75.00,
      quantity: 2
    }]
  }
});
```

#### Begin Checkout

```javascript
dataLayer.push({
  event: 'begin_checkout',
  ecommerce: {
    currency: 'EUR',
    value: 150.00,
    items: [...]
  }
});
```

#### Purchase

```javascript
dataLayer.push({
  event: 'purchase',
  ecommerce: {
    transaction_id: 'order_456',
    currency: 'EUR',
    value: 150.00,
    tax: 28.50,
    items: [...]
  }
});
```

### Injecția Scripturilor

Scripturile se injectează pe baza consimțământului:

```php
// În head-ul paginii
@if($trackingConfig->ga4->enabled && $consent->analytics)
    <!-- Google Analytics 4 -->
    <script async src="https://www.googletagmanager.com/gtag/js?id={{ $ga4MeasurementId }}"></script>
    <script>
        window.dataLayer = window.dataLayer || [];
        function gtag(){dataLayer.push(arguments);}
        gtag('js', new Date());
        gtag('config', '{{ $ga4MeasurementId }}');
    </script>
@endif

@if($trackingConfig->gtm->enabled)
    <!-- Google Tag Manager -->
    <script>
        (function(w,d,s,l,i){...})(window,document,'script','dataLayer','{{ $gtmContainerId }}');
    </script>
@endif

@if($trackingConfig->metaPixel->enabled && $consent->marketing)
    <!-- Meta Pixel -->
    <script>
        !function(f,b,e,v,n,t,s){...}(window,document,'script','https://connect.facebook.net/en_US/fbevents.js');
        fbq('init', '{{ $metaPixelId }}');
        fbq('track', 'PageView');
    </script>
@endif
```

### Componenta Banner Consimțământ

```javascript
// Structură componentă Vue/React
<ConsentBanner
  :categories="['necessary', 'analytics', 'marketing', 'functional']"
  :defaultEnabled="['necessary']"
  :privacyPolicyUrl="/confidentialitate"
  @consent-updated="handleConsentUpdate"
/>
```

### Stocarea Consimțământului

```javascript
// Structura cookie
{
  "consent": {
    "necessary": true,
    "analytics": true,
    "marketing": false,
    "functional": true
  },
  "timestamp": "2025-01-15T10:30:00Z",
  "version": "1.0"
}
```

### Serviciul de Urmărire Evenimente

```php
class TrackingService
{
    public function trackViewItem(Event $event, TicketType $ticketType): void
    {
        $this->pushToDataLayer([
            'event' => 'view_item',
            'ecommerce' => [
                'currency' => $event->currency,
                'value' => $ticketType->price,
                'items' => [[
                    'item_id' => $ticketType->id,
                    'item_name' => $ticketType->name,
                    'item_category' => $event->category,
                    'price' => $ticketType->price,
                    'quantity' => 1,
                ]],
            ],
        ]);
    }

    public function trackPurchase(Order $order): void
    {
        $this->pushToDataLayer([
            'event' => 'purchase',
            'ecommerce' => [
                'transaction_id' => $order->id,
                'currency' => $order->currency,
                'value' => $order->total,
                'tax' => $order->tax,
                'items' => $order->items->map->toEcommerceItem()->all(),
            ],
        ]);
    }
}
```

### Modul Debug

Activează logarea debug:

```javascript
// În consola browserului
localStorage.setItem('tracking_debug', 'true');

// Evenimentele sunt logate în consolă
[Tracking] GA4 Event: view_item
[Tracking] Meta Pixel: ViewContent
[Tracking] TikTok Pixel: ViewContent
```

### Content Security Policy

Configurează headerele CSP pentru a permite tracking-ul:

```
Content-Security-Policy:
  script-src 'self'
    https://www.googletagmanager.com
    https://www.google-analytics.com
    https://connect.facebook.net
    https://analytics.tiktok.com;
  img-src 'self'
    https://www.google-analytics.com
    https://www.facebook.com
    https://analytics.tiktok.com;
  connect-src 'self'
    https://www.google-analytics.com
    https://www.facebook.com
    https://analytics.tiktok.com;
```

### Gestionarea Erorilor

Sistemul gestionează erorile de tracking cu grație:

```javascript
try {
    gtag('event', eventName, eventData);
} catch (error) {
    console.warn('[Tracking] Eroare GA4:', error);
    // Nu strica experiența utilizatorului
}
```

### Testare

Testează tracking-ul fără a afecta producția:

1. Activează modul debug în browser
2. Folosește instrumentele de debug specifice platformei:
   - GA4 DebugView
   - Extensia Meta Pixel Helper
   - TikTok Pixel Helper
3. Verifică evenimentele în rapoartele în timp real
