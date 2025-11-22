# Ticket Insurance Microservice

**Category:** Payment Add-ons
**Integration:** Provider-agnostic via adapter pattern
**Version:** 1.0.0

## Overview

Optional insurance add-on for ticket purchases with hierarchical configuration, real-time quoting, automated policy management, and provider-agnostic integration.

## Features

✅ **Hierarchical Configuration** - tenant → event → ticket_type priority
✅ **Flexible Pricing** - Fixed amount or percentage of ticket price
✅ **Real-time Quotes** - Calculate premiums with min/max caps
✅ **Provider Integration** - Adapter pattern for multiple insurers
✅ **Policy Management** - Idempotent issuance, void, refund
✅ **Tax Handling** - Configurable tax policies
✅ **Eligibility Rules** - Country, ticket type, event, price range filters
✅ **Checkout Integration** - Optional add-on with consent
✅ **Analytics** - Attach rate, GMV, void/refund tracking

## Installation

```bash
# Run migrations
php artisan migrate

# Seed microservice
php artisan db:seed --class=TicketInsuranceMicroserviceSeeder
```

## Configuration

### Create Insurance Config

```php
use App\Models\InsuranceConfig;

InsuranceConfig::create([
    'tenant_id' => 'tenant-123',
    'scope' => 'tenant',  // or 'event', 'ticket_type'
    'scope_ref' => null,   // event_id or ticket_type for specific scope
    'pricing_mode' => 'percent',  // or 'fixed'
    'value_decimal' => 5.00,      // 5% or 5 EUR
    'min_decimal' => 2.00,
    'max_decimal' => 50.00,
    'tax_policy' => [
        'rate' => 19,
        'inclusive' => false,
        'category' => 'insurance',
    ],
    'scope_level' => 'per_ticket',  // or 'per_order'
    'eligibility' => [
        'countries' => ['RO', 'IT', 'FR'],
        'exclude_ticket_types' => ['free', 'staff'],
        'min_ticket_price' => 10.00,
    ],
    'terms' => [
        'terms_url' => 'https://example.com/insurance-terms',
        'description' => 'Protect your ticket with insurance',
        'consent_text' => 'I agree to the insurance terms',
        'cancellation_policy' => 'full_if_unused',  // or 'no_refund', 'proportional'
    ],
    'insurer_provider' => 'mock',
    'enabled' => true,
]);
```

## API Usage

### Get Quote

```bash
GET /api/ti/quote?tenant=tenant-123&ticket_price=100&ticket_type=vip&country=RO
```

**Response:**
```json
{
  "success": true,
  "quote": {
    "available": true,
    "premium": 5.00,
    "tax": 0.95,
    "total": 5.95,
    "currency": "EUR",
    "config_id": "config-456",
    "terms_url": "https://...",
    "description": "Protect your ticket with insurance",
    "scope_level": "per_ticket"
  }
}
```

### Issue Policy

```bash
POST /api/ti/issue
{
  "tenant": "tenant-123",
  "order_ref": "order-789",
  "ticket_ref": "ticket-101",
  "premium": 5.95,
  "ticket_price": 100,
  "user": {
    "name": "John Doe",
    "email": "john@example.com"
  },
  "event": {
    "name": "Concert 2025",
    "date": "2025-12-31"
  }
}
```

**Response:**
```json
{
  "success": true,
  "policy": {
    "id": "policy-202",
    "order_ref": "order-789",
    "ticket_ref": "ticket-101",
    "status": "issued",
    "policy_number": "MOCK-ABC123XYZ",
    "policy_doc_url": "https://provider.com/policies/ABC123.pdf",
    "premium_amount": 5.95,
    "currency": "EUR"
  }
}
```

### List Policies

```bash
GET /api/ti/policies?tenant=tenant-123&status=issued&from_date=2025-01-01
```

### Get Statistics

```bash
GET /api/ti/stats?tenant=tenant-123&from_date=2025-01-01&to_date=2025-12-31
```

**Response:**
```json
{
  "success": true,
  "stats": {
    "total_policies": 150,
    "issued": 145,
    "voided": 3,
    "refunded": 2,
    "errors": 0,
    "premium_gmv": 750.00,
    "refunded_amount": 10.00,
    "net_premium": 740.00,
    "void_rate": 2.00,
    "error_rate": 0.00
  }
}
```

### Void Policy

```bash
POST /api/ti/policy-123/void
{
  "reason": "Customer requested cancellation"
}
```

### Refund Policy

```bash
POST /api/ti/policy-123/refund
{
  "amount": 5.95  // null for full refund
}
```

## Provider Adapters

### Interface

```php
interface InsurerAdapterInterface
{
    public function quote(array $params): array;
    public function issue(array $params): array;
    public function void(string $policyNumber): array;
    public function refund(string $policyNumber, ?float $amount): array;
    public function sync(string $policyNumber): array;
}
```

### Creating Custom Adapter

```php
namespace App\Services\Insurance\Adapters;

class CustomInsurerAdapter implements InsurerAdapterInterface
{
    public function quote(array $params): array
    {
        // Call external API
        return [
            'premium' => 5.00,
            'currency' => 'EUR',
            'details' => [...],
        ];
    }

    public function issue(array $params): array
    {
        // Issue policy via provider API
        return [
            'policy_number' => 'POL-123',
            'policy_doc_url' => 'https://...',
            'details' => [...],
        ];
    }

    // Implement other methods...
}
```

### Register Adapter

```php
// In AppServiceProvider.php
use App\Services\Insurance\InsuranceService;

public function boot()
{
    $insuranceService = app(InsuranceService::class);
    $insuranceService->registerAdapter('custom', new CustomInsurerAdapter());
}
```

## Checkout Integration

### Frontend Component Example

```javascript
// Get quote
const response = await fetch('/api/ti/quote?' + new URLSearchParams({
  tenant: tenantId,
  ticket_price: 100,
  ticket_type: 'vip',
  country: 'RO'
}));

const { quote } = await response.json();

if (quote.available) {
  // Display insurance option
  displayInsuranceOption({
    premium: quote.total,
    description: quote.description,
    termsUrl: quote.terms_url
  });
}

// On checkout
if (insuranceSelected) {
  // Add premium to order total
  orderTotal += quote.total;

  // After payment captured
  await fetch('/api/ti/issue', {
    method: 'POST',
    body: JSON.stringify({
      tenant: tenantId,
      order_ref: orderId,
      ticket_ref: ticketId,
      premium: quote.total,
      ticket_price: 100,
      user: {...},
      event: {...}
    })
  });
}
```

## Webhook Integration

### On Payment Captured

```php
// In your payment webhook handler
public function handlePaymentCaptured($order)
{
    $insuranceService = app(InsuranceService::class);

    foreach ($order->tickets as $ticket) {
        if ($ticket->has_insurance) {
            $insuranceService->issue(
                $order->tenant_id,
                $order->id,
                [
                    'ticket_ref' => $ticket->id,
                    'premium' => $ticket->insurance_premium,
                    'ticket_price' => $ticket->price,
                    'user' => $order->customer->toArray(),
                    'event' => $order->event->toArray(),
                ]
            );
        }
    }
}
```

## Pricing Strategies

### Fixed Amount

```php
'pricing_mode' => 'fixed',
'value_decimal' => 10.00,  // 10 EUR per ticket
```

### Percentage

```php
'pricing_mode' => 'percent',
'value_decimal' => 5.00,    // 5% of ticket price
'min_decimal' => 2.00,      // Minimum 2 EUR
'max_decimal' => 50.00,     // Maximum 50 EUR
```

### Per-Order (Single Insurance for Entire Order)

```php
'scope_level' => 'per_order',
// Premium calculated based on total order value
```

## Eligibility Rules

```php
'eligibility' => [
    // Only these countries
    'countries' => ['RO', 'IT', 'FR', 'ES'],

    // Exclude these ticket types
    'exclude_ticket_types' => ['free', 'staff', 'press'],

    // Exclude specific events
    'exclude_events' => ['event-123', 'event-456'],

    // Price range
    'min_ticket_price' => 10.00,
    'max_ticket_price' => 1000.00,
],
```

## Cancellation Policies

### No Refund
```php
'terms' => [
    'cancellation_policy' => 'no_refund'
]
// Policy cannot be refunded once issued
```

### Full If Unused
```php
'terms' => [
    'cancellation_policy' => 'full_if_unused'
]
// Full refund if event hasn't occurred
```

### Proportional
```php
'terms' => [
    'cancellation_policy' => 'proportional'
]
// Refund proportional to time remaining
```

## Security & Compliance

- **Separation from Ticket Price** - Insurance displayed as separate line item
- **Explicit Consent** - Checkbox required, not pre-checked
- **Clear Terms** - Link to insurance terms before purchase
- **PII Minimization** - Only necessary data sent to provider
- **Secure Storage** - Policy documents stored securely or via signed URLs

## Troubleshooting

### Issue: Quote returns "not available"

**Check:**
1. Insurance config exists and is enabled
2. Ticket meets eligibility rules
3. Country, ticket type, event not excluded
4. Ticket price within min/max range

### Issue: Policy issuance fails

**Check:**
1. Provider adapter is properly configured
2. Provider API credentials are correct
3. Check `ti_events` table for error details
4. Verify idempotency (check for existing policy)

### Issue: Provider adapter not found

**Solution:**
Register adapter in `AppServiceProvider`:
```php
$insuranceService->registerAdapter('provider-key', new CustomAdapter());
```

## Support

**Documentation:** `/docs/microservices/ticket-insurance`
**Email:** support@epas.ro

**Version:** 1.0.0
**Last Updated:** November 16, 2025

---

© 2025 EPAS. All rights reserved.
