# Netopia Integration

## Short Presentation

Accept payments from Romanian customers with Netopia, the country's most trusted payment processor. With deep local market expertise and widespread consumer recognition, Netopia delivers the seamless checkout experience your Romanian audience expects.

Romanian consumers know and trust Netopia. That familiarity translates to higher conversion rates. When customers see the Netopia payment option at checkout, they proceed with confidence. No hesitation, no abandoned carts due to unfamiliar payment processors.

Card payments work instantly. Visa, Mastercard, and local Romanian cards are all supported. Customers enter their details, authenticate with their bank, and complete the purchase in seconds.

Bank transfers offer an alternative for customers who prefer direct payments. The integration handles the entire flow, from initiating the transfer to confirming receipt. No manual reconciliation needed.

Security meets Romanian banking standards. 3D Secure authentication protects every card transaction. PCI compliance ensures cardholder data stays secure throughout the payment process.

Real-time notifications keep you informed. Know immediately when payments succeed, fail, or require attention. Webhook integrations update your order status automatically.

Settlement happens on a predictable schedule. Netopia transfers your funds to your Romanian bank account reliably, with clear reconciliation reports.

Start accepting payments today. Connect your Netopia merchant account, configure your credentials, and enable Romanian payments in minutes.

Netopia Integration opens the Romanian market for your events. Sell more tickets to local audiences with their preferred payment method.

---

## Detailed Description

The Netopia Integration microservice connects your ticketing platform with Netopia Payments, Romania's leading payment processor. This integration handles card payments, bank transfers, and the complete transaction lifecycle for the Romanian market.

### Market Position

Netopia Payments (formerly mobilPay) has processed payments in Romania for over 15 years. It's the preferred payment solution for major Romanian e-commerce platforms, giving it strong brand recognition among local consumers.

### Payment Methods

The integration supports:

- **Card Payments**: Visa, Mastercard, Maestro, and local Romanian bank cards
- **Bank Transfers**: Direct bank-to-bank transfers for customers preferring non-card payments
- **Installments**: Card installment plans through partner banks (where available)

### Checkout Flow

When a customer selects Netopia at checkout, they're redirected to Netopia's secure payment page. After entering card details and completing 3D Secure authentication, they return to your site with the transaction result.

The redirect-based flow ensures PCI compliance - sensitive card data never touches your servers.

### Authentication & Security

Every card transaction passes through 3D Secure verification. The customer's bank authenticates the transaction, providing liability protection and reducing fraud risk.

Netopia's fraud detection systems monitor transactions for suspicious patterns. Combined with 3D Secure, this provides comprehensive protection for your business.

### Transaction Status

Real-time webhooks notify your system of payment status changes:
- **Confirmed**: Payment successful, funds secured
- **Pending**: Awaiting bank confirmation (transfers)
- **Cancelled**: Customer cancelled or timeout
- **Credit**: Refund processed

### Settlement

Netopia settles funds according to your merchant agreement, typically within 1-3 business days for card payments. Settlement reports provide detailed reconciliation data for accounting.

---

## Features

### Payment Acceptance
- Visa and Mastercard support
- Maestro debit cards
- Romanian local bank cards
- Bank transfer payments
- Card installment plans

### Security & Authentication
- 3D Secure mandatory authentication
- PCI DSS compliant processing
- Fraud detection monitoring
- Secure hosted payment page
- Transaction encryption

### Checkout Experience
- Redirect-based secure checkout
- Mobile-optimized payment page
- Romanian language interface
- Clear error messaging
- Return URL handling

### Transaction Management
- Real-time payment status
- Webhook notifications
- Full refund processing
- Partial refund support
- Transaction search and filtering

### Reporting
- Settlement reports
- Transaction history
- Daily reconciliation
- Export capabilities
- Revenue tracking

### Merchant Tools
- Test mode for development
- Sandbox environment
- API documentation
- Technical support
- Merchant dashboard

---

## Use Cases

### Romanian Events
Sell tickets to Romanian audiences using their trusted local payment method. Higher conversion rates compared to international-only payment options.

### Local Festival Sales
Process thousands of ticket purchases during high-demand periods. Netopia's infrastructure handles peak loads for major Romanian events.

### Corporate Events in Romania
Bill Romanian companies through their preferred local payment channels. Bank transfers provide an alternative for corporate procurement processes.

### Romanian Artist Tours
Local artists and promoters benefit from familiar payment processing. Fans complete purchases quickly without payment method friction.

### Multi-Currency Events
While Netopia processes in RON, your platform can display prices in EUR or other currencies with automatic conversion at checkout.

### Installment Sales
Offer payment plans for premium tickets through Netopia's installment partnerships with Romanian banks. Make expensive VIP packages more accessible.

---

## Technical Documentation

### Overview

The Netopia Integration microservice processes payments through Netopia's payment gateway. It handles payment initialization, redirect flow, callback processing, and refund operations.

### Prerequisites

- Active Netopia merchant account
- Merchant credentials (signature and public key)
- Webhook URL configured in Netopia admin
- SSL certificate on your domain

### Configuration

```php
'netopia' => [
    'signature' => env('NETOPIA_SIGNATURE'),
    'public_key_path' => env('NETOPIA_PUBLIC_KEY_PATH'),
    'private_key_path' => env('NETOPIA_PRIVATE_KEY_PATH'),
    'sandbox' => env('NETOPIA_SANDBOX', false),
    'currency' => 'RON',
    'confirm_url' => env('APP_URL') . '/api/webhooks/netopia',
    'return_url' => env('APP_URL') . '/checkout/complete',
]
```

### API Endpoints

#### Initialize Payment

```
POST /api/payments/netopia/init
```

Creates a payment request and returns redirect URL.

**Request:**
```json
{
  "amount": 150.00,
  "currency": "RON",
  "order_id": "order_123",
  "description": "Concert Tickets x2",
  "customer": {
    "email": "client@exemplu.ro",
    "first_name": "Ion",
    "last_name": "Popescu",
    "phone": "0721234567"
  },
  "billing_address": {
    "city": "București",
    "country": "Romania"
  }
}
```

**Response:**
```json
{
  "redirect_url": "https://secure.mobilpay.ro/pay/...",
  "transaction_id": "txn_abc123",
  "env_key": "...",
  "data": "..."
}
```

#### Payment Callback (Webhook)

```
POST /api/webhooks/netopia
```

Receives payment status notifications from Netopia.

**Netopia sends XML payload:**
```xml
<?xml version="1.0" encoding="utf-8"?>
<order>
  <mobilpay:action>confirmed</mobilpay:action>
  <mobilpay:original_amount>150.00</mobilpay:original_amount>
  <mobilpay:processed_amount>150.00</mobilpay:processed_amount>
</order>
```

#### Process Refund

```
POST /api/payments/netopia/refund
```

**Request:**
```json
{
  "transaction_id": "txn_abc123",
  "amount": 75.00
}
```

#### Get Transaction Status

```
GET /api/payments/netopia/{transactionId}
```

### Payment States

| Status | Code | Description |
|--------|------|-------------|
| Pending | 0 | Payment initiated, awaiting completion |
| Confirmed | 1 | Payment successful |
| Pending Authorization | 2 | Card authorized, awaiting capture |
| Paid (Pending) | 3 | Paid, settlement pending |
| Cancelled | 4 | Payment cancelled |
| Credited | 5 | Refund processed |
| Rejected | 6 | Payment rejected |

### Redirect Flow Implementation

```php
// 1. Create payment request
$paymentData = $netopia->createPayment($order);

// 2. Build form and redirect
$formHtml = '<form method="POST" action="' . $netopia->getGatewayUrl() . '">';
$formHtml .= '<input type="hidden" name="env_key" value="' . $paymentData['env_key'] . '">';
$formHtml .= '<input type="hidden" name="data" value="' . $paymentData['data'] . '">';
$formHtml .= '</form>';
$formHtml .= '<script>document.forms[0].submit();</script>';

// 3. Handle callback
public function handleCallback(Request $request)
{
    $response = $netopia->processCallback($request);

    if ($response->isConfirmed()) {
        $order->markAsPaid();
    }

    return $netopia->buildResponse($response);
}
```

### Error Codes

| Code | Description |
|------|-------------|
| 16 | Card rejected by bank |
| 17 | Card expired |
| 18 | Insufficient funds |
| 19 | Invalid card number |
| 20 | Transaction limit exceeded |
| 21 | 3D Secure authentication failed |
| 99 | Generic error |

### Testing

Use sandbox mode with test cards:

| Card Number | Result |
|-------------|--------|
| 9900004810225098 | Successful payment |
| 9900004810225099 | Failed payment |

Set `NETOPIA_SANDBOX=true` to enable test mode.

### Security Considerations

1. Validate XML signatures on callbacks
2. Verify transaction amounts match orders
3. Use HTTPS for all callback URLs
4. Store only transaction references, not card data
5. Implement idempotent callback processing
