# TX Tracking SDK

Event Intelligence tracking SDK for tenant websites and marketplace.

## Quick Start

### Option 1: Async Loader (Recommended)

```html
<!-- Configuration -->
<script>
  window.txConfig = {
    tenantId: 'your-tenant-id',  // Required: your tenant ID
    apiEndpoint: 'https://tixello.com/api/tx/events/batch',  // API endpoint
    debug: false,  // Enable console logging
    autoTrackPageView: true,  // Auto-track page views
    autoTrackEngagement: true  // Auto-track engagement (scroll, time on page)
  };
</script>

<!-- Load SDK async -->
<script src="https://tixello.com/js/tracking/tx-loader.js" async></script>
```

### Option 2: Direct Include

```html
<script src="https://tixello.com/js/tracking/tx-sdk.js"></script>
<script>
  const tx = new TxTracker({
    tenantId: 'your-tenant-id',
    apiEndpoint: 'https://tixello.com/api/tx/events/batch'
  });
</script>
```

## Configuration Options

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `tenantId` | string | required | Your tenant identifier |
| `siteId` | string | null | Optional site identifier for multi-site tenants |
| `apiEndpoint` | string | '/api/tx/events/batch' | API endpoint URL |
| `debug` | boolean | false | Enable console logging |
| `autoTrackPageView` | boolean | true | Automatically track page views on load |
| `autoTrackEngagement` | boolean | true | Track engagement metrics (scroll, active time) |
| `flushInterval` | number | 5000 | Interval (ms) to send queued events |
| `respectDoNotTrack` | boolean | false | Disable tracking if DNT is enabled |

## Tracking Events

### Page View

```javascript
// Automatic (enabled by default)
// Or manual:
tx.pageView();

// With page type override:
tx.pageView('listing');

// Page types: home, listing, event, checkout, artist, venue, shop, account, other
```

### Event View (Ticket Event Page)

```javascript
tx.eventView(123, {
  priceFrom: 49.99,
  currency: 'EUR',
  availability: { vip: 10, ga: 100 }
});
```

### Add to Cart

```javascript
tx.addToCart('cart_abc', 123, [
  { ticket_type_id: 'tt_1', qty: 2, price: 49.99 },
  { ticket_type_id: 'tt_2', qty: 1, price: 99.99 }
], {
  cartValue: 199.97,
  currency: 'EUR'
});
```

### Checkout Started

```javascript
tx.checkoutStarted('cart_abc', {
  cartValue: 199.97,
  currency: 'EUR',
  isAuthenticated: false,
  itemCount: 3
});
```

### Checkout Step Completed

```javascript
tx.checkoutStepCompleted('cart_abc', 'contact_info', {
  stepDurationMs: 45000,
  validationErrors: []
});

// Step names: contact_info, delivery, payment, confirmation
```

### Payment Attempted

```javascript
tx.paymentAttempted('cart_abc', {
  method: 'card',
  amount: 199.97,
  currency: 'EUR'
});
```

### Search

```javascript
tx.searchPerformed('coldplay concert', {
  filters: { city: 'Bucharest', genre: 'rock' },
  resultsCount: 15
});
```

### Filter Changed

```javascript
tx.filterChanged('genre', 'rock', {
  city: 'Bucharest',
  genre: 'rock',
  price_max: 100
});
```

### Promo Code Applied

```javascript
tx.promoCodeApplied('cart_abc', 'SUMMER20', {
  discountAmount: 20,
  discountType: 'percentage',
  currency: 'EUR'
});
```

### User Identification

```javascript
// On login
tx.identify({ method: 'login', email: 'user@example.com' });

// On signup
tx.identify({ method: 'signup', email: 'user@example.com' });
```

### Custom Events

```javascript
tx.track('custom_event_name', {
  // any payload data
  custom_field: 'value'
}, {
  entities: { event_entity_id: '123' }
});
```

## Consent Management

### Setting Consent

```javascript
// When user accepts cookies
tx.setConsent({
  analytics: true,
  marketing: true,
  data_processing: true
});

// Partial consent
tx.setConsent({
  analytics: true,
  marketing: false,
  data_processing: true
});
```

### Checking Consent

```javascript
if (tx.hasConsent('marketing')) {
  // Can fire marketing pixels
}
```

### Consent Scopes

- `necessary`: Required for service operation (always allowed)
- `analytics`: Product analytics & profiling
- `marketing`: Ad pixels & audience exports

## Cross-Domain Identity

When users navigate between marketplace and tenant sites, use identity linking:

### Decorating Links

```javascript
// Automatically add visitor ID to links going to other domains
tx.decorateLinks('a[href]', ['marketplace.com', 'tenant1.ro', 'tenant2.ro']);
```

### Manual URL Generation

```javascript
const linkedUrl = tx.getLinkedUrl('https://marketplace.com/event/123');
// Returns: https://marketplace.com/event/123?_txvid=abc-123-...
```

### Getting Visitor ID for Backend

```javascript
const visitorId = tx.getVisitorId();
// Send this with form submissions for server-side tracking
```

## Integration Examples

### Checkout Form

```html
<form id="checkout-form" action="/checkout" method="POST">
  <input type="hidden" name="visitor_id" id="visitor_id">
  <input type="hidden" name="session_id" id="session_id">
  <!-- other fields -->
</form>

<script>
  document.getElementById('visitor_id').value = tx.getVisitorId();
  document.getElementById('session_id').value = tx.getSessionId();
</script>
```

### E-commerce Integration

```javascript
// Product listing page
tx.pageView('listing');

// Event page
tx.eventView(eventId, { priceFrom: 49.99, currency: 'EUR' });

// Ticket selection
tx.ticketTypeSelected(eventId, ticketTypeId, {
  qty: 2,
  unitPrice: 49.99,
  currency: 'EUR'
});

// Add to cart button click
document.querySelector('.add-to-cart').addEventListener('click', function() {
  tx.addToCart(cartId, eventId, items, { cartValue: total, currency: 'EUR' });
});

// Checkout page load
tx.checkoutStarted(cartId, { cartValue: total, currency: 'EUR' });

// Each checkout step completion
tx.checkoutStepCompleted(cartId, 'contact_info', { stepDurationMs: Date.now() - stepStart });
```

### Single Page Application (SPA)

```javascript
// On route change
router.afterEach((to, from) => {
  tx.pageView(getPageType(to.path));
});
```

## API Reference

### TxTracker Methods

| Method | Description |
|--------|-------------|
| `track(name, payload, options)` | Track custom event |
| `pageView(pageType, data)` | Track page view |
| `eventView(eventId, data)` | Track event page view |
| `addToCart(cartId, eventId, items, data)` | Track add to cart |
| `removeFromCart(cartId, eventId, ticketTypeId, data)` | Track remove from cart |
| `checkoutStarted(cartId, data)` | Track checkout start |
| `checkoutStepCompleted(cartId, step, data)` | Track checkout step |
| `paymentAttempted(cartId, data)` | Track payment attempt |
| `searchPerformed(query, data)` | Track search |
| `filterChanged(name, value, allFilters)` | Track filter change |
| `promoCodeApplied(cartId, code, data)` | Track promo code |
| `identify(data)` | Track user identification |
| `setConsent(consent)` | Update consent preferences |
| `hasConsent(scope)` | Check consent status |
| `getVisitorId()` | Get visitor ID |
| `getSessionId()` | Get session ID |
| `getLinkedUrl(url)` | Get URL with visitor ID |
| `decorateLinks(selector, domains)` | Add visitor ID to links |
| `flush()` | Immediately send queued events |

## Debugging

Enable debug mode to see console logs:

```javascript
window.txConfig = {
  tenantId: 'your-tenant-id',
  debug: true
};
```

Or in browser console:
```javascript
tx.debug = true;
```

## Privacy & GDPR

- SDK respects consent settings before tracking analytics/marketing events
- `necessary` events (checkout, order confirmation) are always tracked
- Visitor ID is first-party only (localStorage, per-domain)
- No cross-site cookies used
- Identity linking happens server-side after consent + conversion

## Browser Support

- Chrome 60+
- Firefox 55+
- Safari 11+
- Edge 79+
- Mobile browsers (iOS Safari, Chrome for Android)

## File Sizes

- `tx-sdk.js`: ~15KB (unminified)
- `tx-loader.js`: ~1KB (async loader)
