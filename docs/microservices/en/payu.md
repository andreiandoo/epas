# PayU Integration

## Short Presentation

Expand your reach across Central and Eastern Europe with PayU, the region's powerhouse payment processor. With operations spanning Poland, Czech Republic, Romania, Hungary, and beyond, PayU connects you to millions of customers using their preferred local payment methods.

PayU understands regional markets like no other. Each country has unique payment preferences - Polish customers love BLIK, Czechs prefer bank transfers, Romanians trust local cards. PayU handles them all through one integration.

Card payments are just the beginning. Accept Visa, Mastercard, and local card schemes across all markets. But the real power lies in alternative payment methods that dominate regional preferences.

Bank transfers process instantly in many markets. Customers authenticate with their bank and complete payment without entering card details. Familiar, trusted, and fast.

Buy now, pay later options through PayU partners let customers spread payments over time. Perfect for premium ticket packages and high-value events.

Security spans every transaction. 3D Secure authentication, fraud detection, and PCI compliance protect your business and your customers across all payment methods.

One dashboard shows everything. Monitor transactions across all countries and payment methods. Unified reporting simplifies multi-market operations.

Settlement in local currencies reduces exchange rate complexity. Receive funds in PLN, CZK, RON, HUF, or EUR based on your market presence.

Unlock Central and Eastern Europe's event market with PayU. One integration, multiple countries, countless payment options.

---

## Detailed Description

The PayU Integration microservice connects your event ticketing platform with PayU's comprehensive payment network across Central and Eastern Europe. This integration provides access to local payment methods, multi-currency processing, and region-specific features.

### Regional Coverage

PayU operates in multiple CEE markets:

- **Poland**: Cards, BLIK, online bank transfers, installments
- **Czech Republic**: Cards, bank transfers, Apple Pay, Google Pay
- **Romania**: Cards, local bank integration
- **Hungary**: Cards, local payment methods
- **Slovakia**: Cards, bank transfers

### Payment Methods by Market

#### Poland
BLIK dominates mobile payments. Customers enter a 6-digit code from their banking app to authorize payments instantly. Bank transfer options include major Polish banks with real-time confirmation.

#### Czech Republic
Online bank transfers (platba online) are extremely popular. PayU connects directly to Czech banks for instant payment confirmation. Cards remain important for international customers.

#### Romania
Local card payments through Romanian banks. Integration with the Romanian banking system ensures smooth processing for domestic cards.

### Checkout Experience

PayU offers two integration approaches:

1. **Redirect**: Customers go to PayU's hosted payment page to select their method and complete payment
2. **Embedded**: PayU widgets embed directly in your checkout for seamless card capture

Both approaches maintain PCI compliance while providing flexibility in checkout design.

### Fraud Prevention

PayU's risk management systems analyze transactions across their entire network. Machine learning models identify fraud patterns while minimizing false positives. Customizable rules let you adjust risk tolerance for your business.

### Multi-Currency Processing

Accept payments in local currencies and settle in your preferred currency. PayU handles conversion with competitive exchange rates. This simplifies pricing strategy - show PLN to Polish customers, CZK to Czech customers.

### Recurring Payments

Token-based card storage enables one-click payments for returning customers. Useful for season ticket holders or frequent event attendees who want frictionless checkout.

---

## Features

### Payment Methods
- Visa and Mastercard cards
- Local card schemes
- BLIK (Poland)
- Bank transfers (multiple countries)
- Apple Pay
- Google Pay
- Installment payments
- Pay later options

### Regional Support
- Poland (PLN)
- Czech Republic (CZK)
- Romania (RON)
- Hungary (HUF)
- Slovakia (EUR)
- Multi-currency pricing

### Security
- 3D Secure 2 authentication
- PCI DSS Level 1 compliant
- AI-powered fraud detection
- Customizable risk rules
- Transaction monitoring
- Chargeback management

### Checkout Options
- Hosted payment page
- Embedded card form
- Payment method widgets
- Mobile-optimized flow
- Localized interfaces
- Custom branding

### Transaction Management
- Real-time status updates
- Webhook notifications
- Full and partial refunds
- Payment retry logic
- Capture and void operations
- Order management API

### Reporting & Analytics
- Unified multi-market dashboard
- Transaction search and export
- Settlement reports
- Performance analytics
- Currency conversion tracking
- Custom report generation

---

## Use Cases

### Multi-Country Events
Sell tickets to events that draw audiences from multiple CEE countries. Each customer sees their local payment methods. A music festival in Krakow reaches Polish, Czech, and Slovak audiences seamlessly.

### Polish Market Focus
Capitalize on Poland's booming event market. BLIK integration captures mobile-first consumers who prefer app-based payments over traditional cards.

### Czech Concert Tours
Process payments for tours across Czech venues. Bank transfer integration matches how Czech consumers prefer to pay for entertainment.

### Regional Festival Circuit
Operate festivals across multiple CEE countries with unified payment processing. One integration handles all markets while respecting local preferences.

### Cross-Border Ticket Sales
European events attracting international audiences benefit from PayU's multi-currency support. Customers pay in familiar currencies regardless of event location.

### Premium Packages
Offer installment payments for VIP experiences and premium packages. PayU's pay-later options make expensive tickets more accessible without increasing your risk.

---

## Technical Documentation

### Overview

The PayU Integration microservice handles payment processing across PayU's CEE network. It manages order creation, payment method selection, status tracking, and refund operations.

### Prerequisites

- PayU merchant account for target markets
- OAuth credentials (client_id and client_secret)
- Webhook endpoint URL
- POS ID for each market/currency

### Configuration

```php
'payu' => [
    'pos_id' => env('PAYU_POS_ID'),
    'client_id' => env('PAYU_CLIENT_ID'),
    'client_secret' => env('PAYU_CLIENT_SECRET'),
    'second_key' => env('PAYU_SECOND_KEY'),
    'sandbox' => env('PAYU_SANDBOX', false),
    'default_currency' => 'PLN',
    'notify_url' => env('APP_URL') . '/api/webhooks/payu',
    'continue_url' => env('APP_URL') . '/checkout/complete',
]
```

### API Endpoints

#### Create Order

```
POST /api/payments/payu/orders
```

Creates a payment order.

**Request:**
```json
{
  "amount": 15000,
  "currency": "PLN",
  "description": "Concert tickets - 2 x General Admission",
  "external_order_id": "order_123",
  "customer": {
    "email": "customer@example.pl",
    "first_name": "Jan",
    "last_name": "Kowalski",
    "phone": "+48123456789"
  },
  "products": [
    {
      "name": "General Admission",
      "unit_price": 7500,
      "quantity": 2
    }
  ]
}
```

**Response:**
```json
{
  "order_id": "WZHF5FFDRJ140731GUEST000P01",
  "redirect_uri": "https://secure.payu.com/pay/?orderId=...",
  "status": "NEW"
}
```

#### Get Order Status

```
GET /api/payments/payu/orders/{orderId}
```

#### Cancel Order

```
DELETE /api/payments/payu/orders/{orderId}
```

#### Process Refund

```
POST /api/payments/payu/orders/{orderId}/refunds
```

**Request:**
```json
{
  "description": "Customer requested refund",
  "amount": 7500
}
```

#### List Payment Methods

```
GET /api/payments/payu/methods?currency=PLN
```

Returns available payment methods for currency.

### Webhook Handler

```
POST /api/webhooks/payu
```

PayU sends notifications for order status changes.

**Payload:**
```json
{
  "order": {
    "orderId": "WZHF5FFDRJ140731GUEST000P01",
    "extOrderId": "order_123",
    "orderCreateDate": "2025-01-15T12:00:00.000+01:00",
    "status": "COMPLETED",
    "totalAmount": "15000",
    "currencyCode": "PLN"
  }
}
```

### Order Statuses

| Status | Description |
|--------|-------------|
| NEW | Order created |
| PENDING | Awaiting payment |
| WAITING_FOR_CONFIRMATION | Payment received, awaiting capture |
| COMPLETED | Payment successful |
| CANCELED | Order cancelled |
| REJECTED | Payment rejected |

### Authentication

PayU uses OAuth 2.0 for API authentication.

```php
// Get access token
POST https://secure.payu.com/pl/standard/user/oauth/authorize

// Headers for API calls
Authorization: Bearer {access_token}
Content-Type: application/json
```

### Signature Verification

```php
// Verify webhook signature
$signature = hash('md5', $jsonBody . $secondKey);
if ($signature !== $request->header('OpenPayu-Signature')) {
    throw new InvalidSignatureException();
}
```

### Error Codes

| Code | Description |
|------|-------------|
| UNAUTHORIZED | Invalid credentials |
| DATA_NOT_FOUND | Order not found |
| BUSINESS_ERROR | Business rule violation |
| ERROR_VALUE_MISSING | Required field missing |
| ERROR_VALUE_INVALID | Invalid field value |

### Testing

Use sandbox environment with test credentials:

```
PAYU_SANDBOX=true
```

Test card numbers:
- `4444333322221111` - Successful payment
- `5434021016824014` - 3DS required

### Integration Flow

```php
// 1. Create order
$order = $payu->createOrder([
    'amount' => $cart->total * 100, // amount in cents
    'currency' => 'PLN',
    'description' => $cart->description,
    'customer' => $customer->toPayU(),
    'products' => $cart->items->map->toPayU(),
]);

// 2. Redirect customer
return redirect($order['redirect_uri']);

// 3. Handle webhook
public function webhook(Request $request)
{
    $this->verifySignature($request);

    $notification = json_decode($request->getContent(), true);
    $order = Order::findByExternalId($notification['order']['extOrderId']);

    if ($notification['order']['status'] === 'COMPLETED') {
        $order->markAsPaid();
    }

    return response('OK');
}
```

### Multi-Market Configuration

```php
'payu_markets' => [
    'pl' => [
        'pos_id' => env('PAYU_PL_POS_ID'),
        'client_id' => env('PAYU_PL_CLIENT_ID'),
        'client_secret' => env('PAYU_PL_CLIENT_SECRET'),
    ],
    'cz' => [
        'pos_id' => env('PAYU_CZ_POS_ID'),
        'client_id' => env('PAYU_CZ_CLIENT_ID'),
        'client_secret' => env('PAYU_CZ_CLIENT_SECRET'),
    ],
    // Add more markets as needed
]
```
