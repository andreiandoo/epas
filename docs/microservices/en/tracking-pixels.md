# Tracking & Pixels Manager

## Short Presentation

One place to manage all your tracking scripts. The Tracking & Pixels Manager handles Google Analytics 4, Google Tag Manager, Meta Pixel, and TikTok Pixel with built-in GDPR consent management. No code changes needed - configure everything from your dashboard.

Understanding your audience starts with proper tracking. See where visitors come from, which events they view, and what converts them to buyers. Make data-driven decisions instead of guessing.

Google Analytics 4 reveals visitor behavior. Track page views, event interactions, and complete ecommerce journeys. Understand your funnel from first visit to ticket purchase.

Meta Pixel and TikTok Pixel power your advertising. When these platforms know who's buying tickets, they can find more people like them. Better tracking means better ad performance.

Google Tag Manager brings flexibility. Deploy and manage tags without touching code. Update tracking configurations instantly as your needs change.

GDPR compliance is built in. The consent management system ensures tracking only fires when users give permission. Cookie banners, consent preferences, and compliant script loading - all handled automatically.

Ecommerce events track the purchase journey. View Item, Add to Cart, Begin Checkout, and Purchase events fire automatically at each stage. See exactly where customers drop off.

CSP-compliant script injection keeps your site secure. All tracking scripts load through approved channels that meet Content Security Policy requirements.

Track smarter. Comply easier. Sell more tickets.

---

## Detailed Description

The Tracking & Pixels Manager microservice provides a centralized system for managing analytics and advertising pixels on your event ticketing platform. It handles script loading, consent management, and ecommerce event tracking across multiple platforms.

### Supported Platforms

#### Google Analytics 4 (GA4)
Full GA4 integration with enhanced measurement and ecommerce tracking. Tracks page views, user engagement, and the complete purchase funnel with revenue data.

#### Google Tag Manager (GTM)
Deploy GTM container for advanced tag management. Includes built-in dataLayer integration for ecommerce events. Enables complex tracking configurations without code changes.

#### Meta Pixel
Facebook/Instagram pixel for advertising optimization. Tracks standard events (ViewContent, AddToCart, Purchase) and enables Custom Audiences and conversion optimization.

#### TikTok Pixel
TikTok advertising pixel for campaign tracking. Standard ecommerce events help optimize TikTok ad delivery and measure performance.

### Consent Management

GDPR-compliant consent system with:

- **Cookie Banner**: Customizable consent popup on first visit
- **Consent Categories**: Analytics, Marketing, Functional groupings
- **Granular Control**: Users choose which tracking to allow
- **Preference Storage**: Consent choices remembered
- **Script Blocking**: Tracking disabled until consent given

### Ecommerce Event Tracking

Standard ecommerce events tracked automatically:

| Event | Trigger | Platforms |
|-------|---------|-----------|
| view_item | Event page viewed | GA4, Meta, TikTok |
| add_to_cart | Ticket added to cart | GA4, Meta, TikTok |
| begin_checkout | Checkout started | GA4, Meta, TikTok |
| purchase | Order completed | GA4, Meta, TikTok |

Events include product details, quantities, and revenue for complete attribution.

### Script Loading

Tracking scripts load conditionally based on:

1. Consent status for each category
2. Platform-specific configuration
3. Page type and context
4. User preferences

Scripts inject asynchronously to minimize page load impact.

### Debug Mode

Built-in debugging tools help verify tracking:

- Preview mode shows events before they fire
- Console logging displays all tracking calls
- Real-time event validation
- Platform-specific debug views

---

## Features

### Analytics Integration
- Google Analytics 4 setup
- Enhanced measurement
- Custom event tracking
- User property management
- Session tracking
- Traffic source attribution

### Tag Management
- Google Tag Manager container
- DataLayer integration
- Custom variable support
- Tag firing rules
- Version control
- Preview and debug

### Advertising Pixels
- Meta Pixel integration
- TikTok Pixel integration
- Custom audience building
- Conversion tracking
- Event optimization
- Retargeting support

### Consent Management
- GDPR cookie banner
- Consent category management
- User preference storage
- Consent-based script loading
- Audit trail logging
- Privacy policy integration

### Ecommerce Tracking
- view_item events
- add_to_cart tracking
- begin_checkout events
- purchase conversions
- Revenue tracking
- Product data layer

### Technical Features
- CSP-compliant injection
- Async script loading
- Event deduplication
- Error handling
- Performance optimization
- Unlimited pageviews

---

## Use Cases

### Marketing Analytics
Understand which marketing channels drive ticket sales. GA4 attribution shows the complete journey from first touch to purchase. Optimize marketing spend based on real data.

### Advertising Optimization
Power Meta and TikTok campaigns with accurate conversion data. Pixels track purchases for campaign optimization and audience building. Better tracking means lower cost per acquisition.

### Funnel Analysis
See where visitors drop off in the purchase journey. Compare conversion rates between event pages, cart abandonment rates, and checkout completion. Identify and fix friction points.

### GDPR Compliance
Meet European privacy regulations without sacrificing analytics. Consent management ensures tracking only fires when permitted. Document compliance with audit logs.

### A/B Testing
Use GTM to deploy testing tools and track results. Measure which page variations drive more conversions. Data-driven optimization for continuous improvement.

### Multi-Platform Attribution
Compare performance across advertising platforms. See how Meta, TikTok, and Google campaigns work together. Allocate budget to highest-performing channels.

---

## Technical Documentation

### Overview

The Tracking & Pixels Manager microservice handles tracking script injection, consent management, and ecommerce event firing across GA4, GTM, Meta Pixel, and TikTok Pixel.

### Configuration

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
        'cookie_lifetime' => 365, // days
    ],
]
```

### API Endpoints

#### Get Tracking Configuration

```
GET /api/tracking/config
```

Returns current tracking configuration for frontend.

**Response:**
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

#### Update Configuration

```
PUT /api/tracking/config
```

Updates tracking configuration (admin only).

#### Get Consent Status

```
GET /api/tracking/consent
```

Returns user's current consent preferences.

#### Update Consent

```
POST /api/tracking/consent
```

**Request:**
```json
{
  "necessary": true,
  "analytics": true,
  "marketing": false,
  "functional": true
}
```

### DataLayer Events

The system pushes ecommerce events to the dataLayer:

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

### Script Injection

Scripts inject based on consent:

```php
// In page head
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

### Consent Banner Component

```javascript
// Vue/React component structure
<ConsentBanner
  :categories="['necessary', 'analytics', 'marketing', 'functional']"
  :defaultEnabled="['necessary']"
  :privacyPolicyUrl="/privacy"
  @consent-updated="handleConsentUpdate"
/>
```

### Consent Storage

```javascript
// Cookie structure
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

### Event Tracking Service

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

### Debug Mode

Enable debug logging:

```javascript
// In browser console
localStorage.setItem('tracking_debug', 'true');

// Events logged to console
[Tracking] GA4 Event: view_item
[Tracking] Meta Pixel: ViewContent
[Tracking] TikTok Pixel: ViewContent
```

### Content Security Policy

Configure CSP headers to allow tracking:

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

### Error Handling

The system handles tracking errors gracefully:

```javascript
try {
    gtag('event', eventName, eventData);
} catch (error) {
    console.warn('[Tracking] GA4 error:', error);
    // Don't break user experience
}
```

### Testing

Test tracking without affecting production:

1. Enable debug mode in browser
2. Use platform-specific debug tools:
   - GA4 DebugView
   - Meta Pixel Helper extension
   - TikTok Pixel Helper
3. Verify events in real-time reports
