# Tracking & Pixels Manager Microservice

Complete GDPR-compliant tracking and analytics pixels management system for tenants.

## Overview

The Tracking & Pixels Manager microservice allows tenants to integrate analytics and marketing tracking pixels on their websites while maintaining GDPR compliance through a consent management system.

### Supported Providers

- **Google Analytics 4 (GA4)** - Website analytics with gtag.js
- **Google Tag Manager (GTM)** - Tag management with dataLayer events
- **Meta Pixel (Facebook)** - Facebook advertising pixel
- **TikTok Pixel** - TikTok advertising pixel

### Key Features

- ✅ GDPR-compliant with opt-in consent
- ✅ No tracking without explicit user consent
- ✅ CSP-compliant script injection with nonce support
- ✅ Standard ecommerce event tracking
- ✅ Per-tenant configuration
- ✅ Page scope control (public/admin/all)
- ✅ Injection location control (head/body)
- ✅ Debug and preview tools

## Pricing

- **Model**: Recurring monthly subscription
- **Price**: 1 EUR per month
- **Billing**: Monthly recurring

## Architecture

### Database Schema

**tracking_integrations table:**
```sql
- id
- tenant_id (foreign key)
- provider (enum: ga4, gtm, meta, tiktok)
- enabled (boolean)
- consent_category (enum: analytics, marketing)
- settings (JSON) - Provider-specific configuration
- created_at, updated_at
```

**Settings JSON structure:**
```json
{
  "measurement_id": "G-XXXXXXXXXX",  // For GA4
  "container_id": "GTM-XXXXXX",      // For GTM
  "pixel_id": "1234567890",          // For Meta/TikTok
  "inject_at": "head",               // or "body"
  "page_scope": "public"             // "public", "admin", or "all"
}
```

### Consent System

**ConsentServiceInterface** - Abstract interface for consent management
**SessionConsentService** - Default implementation using session storage

**Consent Categories:**
- `analytics` - Used for GA4 and GTM
- `marketing` - Used for Meta Pixel and TikTok Pixel
- `necessary` - Always allowed (cookies required for site function)
- `preferences` - User preference cookies

**Default Behavior:** Deny all (opt-in mode) - No tracking occurs without explicit consent

### Provider System

Each provider implements `TrackingProviderInterface`:

```php
interface TrackingProviderInterface
{
    public function getName(): string;
    public function getConsentCategory(): string;
    public function injectHead(array $settings, ?string $nonce): string;
    public function injectBodyEnd(array $settings, ?string $nonce): string;
    public function getDataLayerAdapter(): string;
    public function validateSettings(array $settings): array;
}
```

**Providers:**
- `Ga4Provider` - Google Analytics 4
- `GtmProvider` - Google Tag Manager
- `MetaPixelProvider` - Meta Pixel
- `TikTokPixelProvider` - TikTok Pixel

### Event System

**TrackingEventBus** - Emits standard ecommerce events:
- `tracking:pageview` - Page view event
- `tracking:view_item` - Product/item view
- `tracking:add_to_cart` - Add to cart
- `tracking:begin_checkout` - Checkout started
- `tracking:purchase` - Purchase completed

**Event Format:**
```javascript
{
  value: 99.99,
  currency: 'EUR',
  items: [
    {
      item_id: 'TICKET-123',
      item_name: 'Concert Ticket',
      price: 99.99,
      quantity: 1,
      item_category: 'Events'
    }
  ],
  transaction_id: 'ORDER-456' // for purchase events
}
```

### Script Injection

**TrackingScriptInjector** handles injection based on:
1. Tenant configuration (enabled integrations)
2. User consent (checked per category)
3. Page scope (public/admin/all)
4. Injection location (head/body)
5. CSP nonce (if available)

## Installation

### 1. Run Migrations

```bash
php artisan migrate
```

This creates the `tracking_integrations` table.

### 2. Seed Microservice

```bash
php artisan db:seed --class=TrackingPixelsMicroserviceSeeder
```

This creates the microservice entry in the database.

### 3. Configure Service Provider

The `ConsentServiceInterface` is already registered in `AppServiceProvider`:

```php
$this->app->singleton(
    \App\Services\Tracking\ConsentServiceInterface::class,
    \App\Services\Tracking\SessionConsentService::class
);
```

## Usage

### Admin Interface (Filament)

Navigate to: **Admin Panel → Marketing → Tracking & Pixels**

**Create Integration:**
1. Select tenant
2. Choose provider (GA4, GTM, Meta, TikTok)
3. Enable/disable integration
4. Enter provider-specific ID
5. Set injection location (head/body)
6. Set page scope (public/admin/all)
7. Save

**Preview Injection:**
- Click "Preview Injection" button on edit page
- Shows what scripts would be injected
- Displays consent status per provider

### API Endpoints

**Get Tracking Configuration:**
```
GET /api/tracking/config?tenant={id}
```

Returns active integrations (filtered by consent).

**Get Consent Status:**
```
GET /api/tracking/consent
```

Returns current consent preferences.

**Update Consent:**
```
POST /api/tracking/consent
Content-Type: application/json

{
  "analytics": true,
  "marketing": false
}
```

**Revoke All Consents:**
```
POST /api/tracking/consent/revoke
```

**Debug Preview:**
```
GET /api/tracking/debug/preview?tenant={id}&page_scope=public
```

Shows what scripts would be injected for debugging.

**Create/Update Integration (Admin):**
```
POST /api/tracking/integrations
Content-Type: application/json

{
  "tenant_id": 1,
  "provider": "ga4",
  "enabled": true,
  "consent_category": "analytics",
  "settings": {
    "measurement_id": "G-XXXXXXXXXX",
    "inject_at": "head",
    "page_scope": "public"
  }
}
```

### Frontend Integration

#### Step 1: Inject Scripts

In your layout blade template:

```php
@php
use App\Services\Tracking\TrackingScriptInjector;
use App\Services\Tracking\SessionConsentService;

$tenant = /* get current tenant */;
$injector = app(TrackingScriptInjector::class);
@endphp

<!-- Your HTML -->
{!! $injector->inject($html, $tenant, 'public', request()) !!}
```

Or use response middleware (recommended).

#### Step 2: Emit Tracking Events

The tracking event bus is automatically injected. Use from JavaScript:

```javascript
// Page view (automatic on load)
TrackingEvents.pageview();

// View item
TrackingEvents.viewItem({
  value: 99.99,
  currency: 'EUR',
  items: [{
    item_id: 'TICKET-123',
    item_name: 'Concert Ticket',
    price: 99.99,
    quantity: 1
  }]
});

// Add to cart
TrackingEvents.addToCart({
  value: 99.99,
  currency: 'EUR',
  items: [/* ... */]
});

// Begin checkout
TrackingEvents.beginCheckout({
  value: 199.98,
  currency: 'EUR',
  items: [/* ... */]
});

// Purchase
TrackingEvents.purchase({
  transaction_id: 'ORDER-456',
  value: 199.98,
  currency: 'EUR',
  tax: 19.00,
  shipping: 5.00,
  items: [/* ... */]
});
```

#### Step 3: Implement Consent Banner

Example consent banner:

```javascript
// Check if user has already given consent
fetch('/api/tracking/consent')
  .then(r => r.json())
  .then(data => {
    if (!data.analytics && !data.marketing) {
      showConsentBanner();
    }
  });

function acceptAnalytics() {
  fetch('/api/tracking/consent', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ analytics: true })
  }).then(() => location.reload());
}

function acceptAll() {
  fetch('/api/tracking/consent', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ analytics: true, marketing: true })
  }).then(() => location.reload());
}

function rejectAll() {
  fetch('/api/tracking/consent/revoke', {
    method: 'POST'
  }).then(() => location.reload());
}
```

## Provider Configuration

### Google Analytics 4 (GA4)

1. Create GA4 property at https://analytics.google.com
2. Go to Admin → Data Streams → Web
3. Copy Measurement ID (format: `G-XXXXXXXXXX`)
4. Add to Tracking Integration with:
   - Provider: GA4
   - Consent Category: Analytics
   - Measurement ID: Your ID

### Google Tag Manager (GTM)

1. Create GTM account at https://tagmanager.google.com
2. Create container for your website
3. Copy Container ID (format: `GTM-XXXXXX`)
4. Add to Tracking Integration with:
   - Provider: GTM
   - Consent Category: Analytics
   - Container ID: Your ID

### Meta Pixel (Facebook)

1. Go to Facebook Events Manager
2. Create or select a Pixel
3. Copy Pixel ID (numeric)
4. Add to Tracking Integration with:
   - Provider: Meta
   - Consent Category: Marketing
   - Pixel ID: Your ID

### TikTok Pixel

1. Go to TikTok Ads Manager → Assets → Events
2. Create or select a Pixel
3. Copy Pixel ID (alphanumeric)
4. Add to Tracking Integration with:
   - Provider: TikTok
   - Consent Category: Marketing
   - Pixel ID: Your ID

## Security & Compliance

### GDPR Compliance

- **Opt-in by default**: No tracking without explicit consent
- **Consent categories**: Separate consent for analytics vs marketing
- **Easy revocation**: Users can revoke consent anytime
- **No data collection without consent**: Scripts don't load until consent given

### CSP (Content Security Policy)

Scripts are injected with CSP nonce support:

```php
// Generate nonce in middleware
$nonce = base64_encode(random_bytes(16));
$request->attributes->set('csp_nonce', $nonce);

// Add to CSP header
header("Content-Security-Policy: script-src 'self' 'nonce-{$nonce}'");
```

All tracking scripts will use this nonce automatically.

### Data Privacy

- Consent stored in session (not persistent by default)
- No personal data sent to tracking services without consent
- IP anonymization enabled for GA4
- Cookie flags set to `SameSite=None;Secure`

## Testing

### Unit Tests

```bash
php artisan test --filter=Tracking
```

### Manual Testing

1. **Test Consent Flow:**
   - Visit site without consent
   - Verify no tracking scripts loaded
   - Grant analytics consent
   - Verify GA4/GTM scripts loaded
   - Grant marketing consent
   - Verify Meta/TikTok scripts loaded

2. **Test Event Tracking:**
   - Open browser console
   - Navigate to product page
   - Verify `tracking:view_item` event fired
   - Add to cart
   - Verify `tracking:add_to_cart` event fired
   - Check Network tab for tracking requests

3. **Test Debug Endpoint:**
   ```bash
   curl "http://localhost/api/tracking/debug/preview?tenant=1&page_scope=public"
   ```

## Troubleshooting

### Scripts Not Loading

1. Check consent status: `GET /api/tracking/consent`
2. Verify integration is enabled in admin
3. Check page scope matches current page
4. Review browser console for CSP errors

### Events Not Tracking

1. Verify provider scripts loaded (check Network tab)
2. Check browser console for JavaScript errors
3. Verify data layer adapters loaded
4. Test event emission manually in console

### GDPR Compliance Issues

1. Ensure consent banner is visible on first visit
2. Verify scripts only load after consent
3. Test consent revocation functionality
4. Check that consent is not auto-granted

## Performance Considerations

- Scripts load asynchronously
- Data layer adapters are lightweight
- No blocking operations
- Session-based consent (fast lookups)
- Cached provider instances

## Future Enhancements

Potential additions for future versions:

- Cookie-based consent persistence
- Server-side tracking for GA4
- Custom event definitions
- A/B testing integration
- Heatmap integration
- More providers (LinkedIn, Pinterest, etc.)
- Enhanced preview with visual overlay
- Consent analytics dashboard

## Support

For issues or questions:
- Review logs: `storage/logs/laravel.log`
- Check API documentation: `/api/tracking/*`
- Debug endpoint: `/api/tracking/debug/preview`

---

**Version**: 1.0.0
**Last Updated**: 2025-11-16
**Microservice Price**: 1 EUR/month recurring
