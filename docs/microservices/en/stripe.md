# Stripe Integration

## Short Presentation

Accept payments from customers worldwide with Stripe, the leading global payment platform trusted by millions of businesses. From local card payments to Apple Pay and Google Pay, Stripe handles it all seamlessly.

Your customers expect a smooth checkout experience. Stripe delivers exactly that - fast, secure, and familiar. Credit cards, debit cards, and digital wallets work instantly. No redirects, no friction, no abandoned carts.

Security is built into every transaction. PCI DSS Level 1 compliance means your customers' card data is protected by the highest security standards. 3D Secure authentication adds another layer of protection against fraud.

Go global without complexity. Stripe supports 135+ currencies and dozens of payment methods popular in different regions. Whether your customers are in Europe, Asia, or the Americas, they can pay how they prefer.

Real-time dashboards show every transaction. Track successful payments, monitor refunds, and analyze your revenue. Stripe's reporting tools give you complete visibility into your payment operations.

Automatic payouts transfer your earnings on a schedule that works for you. Daily, weekly, or custom - your money flows to your bank account reliably.

Setup takes minutes. Connect your Stripe account, configure your API keys, and start accepting payments immediately. No technical expertise required.

Stripe Integration transforms your ticketing platform into a global sales machine. Accept payments confidently and grow your business.

---

## Detailed Description

The Stripe Integration microservice connects your event ticketing platform with Stripe's comprehensive payment infrastructure. This integration handles the complete payment lifecycle from checkout to settlement.

### Payment Methods

Stripe's payment method coverage includes:

- **Card Payments**: Visa, Mastercard, American Express, Discover, JCB, Diners Club, and UnionPay
- **Digital Wallets**: Apple Pay, Google Pay, and Link (Stripe's one-click checkout)
- **Bank Debits**: SEPA Direct Debit for European customers
- **Buy Now, Pay Later**: Klarna, Afterpay/Clearpay where available

### Checkout Experience

The integration embeds Stripe Elements directly into your checkout page. Customers enter their payment details without leaving your site. The form adapts to show relevant fields based on the payment method selected.

Card validation happens in real-time. Invalid numbers, expired cards, and incomplete details are caught before submission. This reduces failed payments and customer frustration.

### Security Framework

All payment data flows through Stripe's PCI-compliant infrastructure. Your servers never touch raw card numbers. This significantly reduces your security burden and compliance requirements.

3D Secure (Strong Customer Authentication) activates automatically when required by the customer's bank or regional regulations. The integration handles the authentication flow seamlessly.

Stripe Radar, the built-in fraud detection system, evaluates every transaction using machine learning trained on billions of data points. Suspicious transactions can be automatically blocked or flagged for review.

### Settlement & Reporting

Funds from successful transactions appear in your Stripe balance immediately. Automatic payouts transfer these funds to your connected bank account based on your configured schedule.

The Stripe Dashboard provides comprehensive reporting including transaction history, settlement reports, and analytics. All data is exportable for accounting and reconciliation purposes.

---

## Features

### Payment Acceptance
- Credit and debit card payments
- Apple Pay integration
- Google Pay integration
- Link one-click checkout
- SEPA Direct Debit (EUR)
- iDEAL (Netherlands)
- Bancontact (Belgium)
- Sofort (Germany, Austria)

### Security & Compliance
- PCI DSS Level 1 compliance
- 3D Secure 2 authentication
- Stripe Radar fraud detection
- Tokenized card storage
- Encrypted data transmission
- SCA compliance (EU)

### Checkout Experience
- Embedded payment form
- Real-time card validation
- Responsive mobile design
- Multiple currency support
- Localized payment methods
- Saved payment methods

### Transaction Management
- Real-time payment processing
- Automatic payment confirmation
- Full and partial refunds
- Payment dispute handling
- Webhook notifications
- Idempotent requests

### Reporting & Settlement
- Real-time transaction dashboard
- Automated payouts
- Settlement reports
- Revenue analytics
- Export to CSV/PDF
- Multi-currency reporting

---

## Use Cases

### International Events
Sell tickets to audiences worldwide. Stripe automatically presents the most relevant payment methods based on the customer's location. European customers see SEPA and local options, while North American customers see cards and Apple Pay.

### High-Volume Sales
Handle ticket rushes for popular events without missing a beat. Stripe's infrastructure processes thousands of transactions per second. Your sales won't be bottlenecked by payment processing.

### Mobile-First Sales
Capture mobile customers with Apple Pay and Google Pay. One-tap checkout removes the friction of typing card numbers on small screens. Mobile conversion rates increase significantly.

### Subscription Events
Run recurring events with saved payment methods. Customers authorize their card once and purchase future tickets with a single click. Reduces checkout abandonment for repeat customers.

### Multi-Currency Pricing
Price tickets in local currencies without exchange rate headaches. Stripe handles conversion and settlement automatically. Customers see prices in their familiar currency.

### Premium Events
Sell high-value VIP packages with confidence. Stripe's fraud protection and chargeback management protect against payment disputes on large transactions.

---

## Technical Documentation

### Overview

The Stripe Integration microservice handles payment processing through Stripe's API. It manages payment intent creation, confirmation, webhook processing, and refund operations.

### Prerequisites

- Active Stripe account (Standard or Express)
- API keys (publishable and secret)
- Webhook endpoint configured in Stripe Dashboard

### Configuration

```php
'stripe' => [
    'publishable_key' => env('STRIPE_PUBLISHABLE_KEY'),
    'secret_key' => env('STRIPE_SECRET_KEY'),
    'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
    'currency' => 'eur',
    'payment_methods' => ['card', 'sepa_debit', 'ideal'],
]
```

### API Endpoints

#### Create Payment Intent

```
POST /api/payments/stripe/intent
```

Creates a payment intent for a checkout session.

**Request:**
```json
{
  "amount": 5000,
  "currency": "eur",
  "order_id": "order_123",
  "customer_email": "customer@example.com",
  "metadata": {
    "event_id": 456,
    "ticket_ids": [1, 2, 3]
  }
}
```

**Response:**
```json
{
  "client_secret": "pi_xxx_secret_xxx",
  "payment_intent_id": "pi_xxx",
  "amount": 5000,
  "currency": "eur"
}
```

#### Confirm Payment

```
POST /api/payments/stripe/confirm
```

Confirms a payment after customer authorization.

#### Process Refund

```
POST /api/payments/stripe/refund
```

**Request:**
```json
{
  "payment_intent_id": "pi_xxx",
  "amount": 2500,
  "reason": "requested_by_customer"
}
```

#### Get Payment Status

```
GET /api/payments/stripe/{paymentIntentId}
```

Returns current status of a payment intent.

### Webhook Events

Configure your webhook endpoint to receive:

| Event | Description |
|-------|-------------|
| `payment_intent.succeeded` | Payment completed successfully |
| `payment_intent.payment_failed` | Payment attempt failed |
| `charge.refunded` | Refund processed |
| `charge.dispute.created` | Customer initiated dispute |

### Webhook Handler

```php
POST /api/webhooks/stripe

// Signature verification
$payload = @file_get_contents('php://input');
$sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'];
$event = \Stripe\Webhook::constructEvent(
    $payload, $sig_header, $webhook_secret
);
```

### Frontend Integration

```javascript
// Initialize Stripe
const stripe = Stripe('pk_live_xxx');
const elements = stripe.elements();

// Create payment element
const paymentElement = elements.create('payment');
paymentElement.mount('#payment-element');

// Handle submission
const { error } = await stripe.confirmPayment({
  elements,
  confirmParams: {
    return_url: 'https://yoursite.com/checkout/complete',
  },
});
```

### Error Handling

| Error Code | Description | Action |
|------------|-------------|--------|
| `card_declined` | Card was declined | Ask for different payment method |
| `expired_card` | Card has expired | Request updated card details |
| `insufficient_funds` | Insufficient funds | Suggest lower amount or different card |
| `authentication_required` | 3DS required | Trigger authentication flow |

### Testing

Use Stripe test mode with test API keys. Test card numbers:

- `4242424242424242` - Successful payment
- `4000000000000002` - Card declined
- `4000002500003155` - Requires 3D Secure

### Security Best Practices

1. Never log or store raw card numbers
2. Use webhook signatures to verify events
3. Implement idempotency keys for retries
4. Enable Radar rules for fraud prevention
5. Monitor for unusual transaction patterns
