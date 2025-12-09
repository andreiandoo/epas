# EuPlatesc Integration

## Short Presentation

Accept payments from Romanian customers with EuPlatesc, a trusted local payment gateway offering competitive rates and reliable processing. Perfect for businesses focused on the Romanian market who want straightforward, cost-effective payment acceptance.

EuPlatesc delivers where it matters most - reliable transactions at competitive costs. Their fee structure rewards volume, making them attractive for high-transaction businesses like event ticketing.

Romanian consumers recognize and trust the EuPlatesc brand. When your checkout displays EuPlatesc, local customers proceed with confidence. Familiarity reduces cart abandonment.

Card payments process smoothly. Visa, Mastercard, and Maestro cards work instantly. Customers enter their details, complete 3D Secure verification, and finish their purchase in seconds.

Security matches European banking standards. Every card transaction passes through 3D Secure authentication. PCI DSS compliance protects cardholder data throughout the payment flow.

Real-time status updates keep your system synchronized. Instant notifications confirm successful payments, failed attempts, and refunds. Your order management stays current automatically.

Integration is straightforward. EuPlatesc's well-documented API and responsive support team make setup smooth. Most merchants complete integration within days, not weeks.

Settlement happens predictably. Funds transfer to your Romanian bank account on schedule with clear reconciliation reports. Know exactly what's coming and when.

Simple, reliable, affordable. EuPlatesc Integration gives Romanian event organizers exactly what they need - dependable payment processing without complexity.

---

## Detailed Description

The EuPlatesc Integration microservice connects your event ticketing platform with EuPlatesc's payment gateway, providing Romanian-market-focused payment processing with competitive rates and reliable performance.

### Market Position

EuPlatesc has served the Romanian e-commerce market for years, building a reputation for reliability and competitive pricing. Their focus on the local market means strong relationships with Romanian banks and optimized processing for domestic cards.

### Payment Flow

The integration uses a redirect-based flow:

1. Customer selects EuPlatesc at checkout
2. System generates encrypted payment request
3. Customer redirects to EuPlatesc's secure payment page
4. Customer enters card details and completes 3D Secure
5. Customer returns to your site with transaction result
6. Your system receives confirmation webhook

This flow ensures PCI compliance - your servers never handle raw card data.

### Card Support

EuPlatesc processes:
- **Visa**: Credit and debit cards
- **Mastercard**: Credit and debit cards
- **Maestro**: Debit cards

Both domestic Romanian cards and international cards are supported, though domestic cards typically have higher approval rates.

### Security Framework

Every transaction requires 3D Secure authentication. The customer's issuing bank verifies the cardholder identity before approving the transaction. This provides:
- Liability shift for authenticated transactions
- Reduced fraud risk
- Consumer protection compliance

EuPlatesc maintains PCI DSS compliance, ensuring cardholder data is protected according to payment industry standards.

### Pricing Advantages

EuPlatesc's fee structure often proves competitive for Romanian merchants:
- Transparent percentage-based fees
- No hidden costs
- Volume discounts available
- Local currency settlement without conversion fees

For high-volume ticket sellers, the cost savings add up significantly over time.

### Settlement Process

Funds from successful transactions settle to your Romanian bank account according to your merchant agreement. Settlement reports provide transaction-level detail for easy reconciliation with your order records.

---

## Features

### Payment Acceptance
- Visa credit and debit cards
- Mastercard credit and debit
- Maestro debit cards
- Domestic Romanian cards
- International card support

### Security
- Mandatory 3D Secure authentication
- PCI DSS compliant processing
- Encrypted payment requests
- Secure hosted payment page
- Transaction verification

### Checkout Experience
- Redirect-based secure flow
- Mobile-responsive payment page
- Romanian language interface
- Clear error messages
- Customizable return URLs

### Transaction Management
- Real-time payment confirmations
- Webhook notifications
- Full refund processing
- Partial refund support
- Transaction status queries

### Merchant Tools
- Test/sandbox mode
- Transaction search
- Settlement reports
- Daily reconciliation
- API documentation
- Technical support

### Reporting
- Transaction history
- Settlement tracking
- Revenue reports
- Export functionality
- Custom date ranges

---

## Use Cases

### Romanian Concert Venues
Process ticket sales for venues across Romania. Local payment processing means higher approval rates for Romanian cardholders compared to international-only gateways.

### Festival Ticket Sales
Handle high-volume sales periods for Romanian festivals. EuPlatesc's infrastructure manages peak loads while keeping processing costs competitive.

### Theatre and Cultural Events
Cultural institutions benefit from straightforward pricing and reliable processing. Perfect for organizations that want dependable payments without complex setup.

### Local Promoters
Independent event promoters get enterprise-grade payment processing at competitive rates. Volume-based pricing rewards successful events.

### Regional Event Series
Process payments for recurring event series across Romanian cities. Consistent processing and clear reporting simplify multi-event accounting.

### Budget-Conscious Organizers
When payment processing costs matter, EuPlatesc's competitive fees help maximize ticket revenue. Every percentage point saved goes back to your event.

---

## Technical Documentation

### Overview

The EuPlatesc Integration microservice handles payment processing through EuPlatesc's gateway. It manages payment initialization, redirect flow, callback processing, and refund operations.

### Prerequisites

- Active EuPlatesc merchant account
- Merchant ID and secret key
- Callback URL configured in EuPlatesc admin
- SSL certificate on your domain

### Configuration

```php
'euplatesc' => [
    'merchant_id' => env('EUPLATESC_MERCHANT_ID'),
    'secret_key' => env('EUPLATESC_SECRET_KEY'),
    'test_mode' => env('EUPLATESC_TEST_MODE', false),
    'currency' => 'RON',
    'callback_url' => env('APP_URL') . '/api/webhooks/euplatesc',
    'success_url' => env('APP_URL') . '/checkout/success',
    'fail_url' => env('APP_URL') . '/checkout/failed',
]
```

### API Endpoints

#### Initialize Payment

```
POST /api/payments/euplatesc/init
```

Creates a payment request and returns form data for redirect.

**Request:**
```json
{
  "amount": 250.00,
  "currency": "RON",
  "order_id": "order_456",
  "description": "Festival Pass - Weekend",
  "customer": {
    "email": "client@exemplu.ro",
    "first_name": "Maria",
    "last_name": "Ionescu",
    "phone": "0722345678"
  },
  "billing": {
    "city": "Cluj-Napoca",
    "country": "Romania",
    "address": "Str. Exemplu 123"
  }
}
```

**Response:**
```json
{
  "form_url": "https://secure.euplatesc.ro/tdsprocess/tranzactd.php",
  "form_data": {
    "amount": "250.00",
    "curr": "RON",
    "invoice_id": "order_456",
    "order_desc": "Festival Pass - Weekend",
    "merch_id": "xxx",
    "timestamp": "20250115120000",
    "nonce": "abc123...",
    "fp_hash": "..."
  }
}
```

#### Payment Callback

```
POST /api/webhooks/euplatesc
```

Receives payment result notifications.

**EuPlatesc sends:**
```
amount=250.00
curr=RON
invoice_id=order_456
ep_id=123456789
action=0
message=Approved
fp_hash=...
```

#### Query Transaction

```
GET /api/payments/euplatesc/{invoiceId}/status
```

Returns current transaction status.

#### Process Refund

```
POST /api/payments/euplatesc/refund
```

**Request:**
```json
{
  "ep_id": "123456789",
  "amount": 250.00,
  "reason": "Customer requested cancellation"
}
```

### Action Codes

| Action | Description |
|--------|-------------|
| 0 | Approved |
| 1 | Duplicate transaction |
| 2 | Declined |
| 3 | Error |
| 4 | Pending (additional verification) |
| 5 | Card not 3D Secure enrolled |

### Hash Verification

```php
// Generate request hash
$data = implode('', [
    strlen($amount) . $amount,
    strlen($curr) . $curr,
    strlen($invoice_id) . $invoice_id,
    strlen($order_desc) . $order_desc,
    strlen($merch_id) . $merch_id,
    strlen($timestamp) . $timestamp,
    strlen($nonce) . $nonce,
]);
$fp_hash = strtoupper(hash_hmac('md5', $data, hex2bin($secret_key)));

// Verify callback hash
$callback_data = implode('', [
    strlen($amount) . $amount,
    strlen($curr) . $curr,
    strlen($invoice_id) . $invoice_id,
    strlen($ep_id) . $ep_id,
    strlen($action) . $action,
    strlen($message) . $message,
]);
$expected_hash = strtoupper(hash_hmac('md5', $callback_data, hex2bin($secret_key)));
$is_valid = hash_equals($expected_hash, $received_hash);
```

### Redirect Flow Implementation

```php
// 1. Generate payment form
$paymentData = $euplatesc->createPayment($order);

// 2. Render auto-submit form
return view('payment.redirect', [
    'form_url' => $paymentData['form_url'],
    'form_data' => $paymentData['form_data'],
]);

// Blade template
<form id="payment-form" method="POST" action="{{ $form_url }}">
    @foreach($form_data as $key => $value)
        <input type="hidden" name="{{ $key }}" value="{{ $value }}">
    @endforeach
</form>
<script>document.getElementById('payment-form').submit();</script>

// 3. Handle callback
public function callback(Request $request)
{
    if (!$euplatesc->verifyHash($request->all())) {
        return response('Invalid hash', 400);
    }

    $order = Order::find($request->invoice_id);

    if ($request->action === '0') {
        $order->markAsPaid($request->ep_id);
    } else {
        $order->markAsFailed($request->message);
    }

    return response('OK');
}
```

### Error Messages

| Code | Message | Description |
|------|---------|-------------|
| 00 | Approved | Transaction successful |
| 05 | Do not honour | Card declined by bank |
| 12 | Invalid transaction | Transaction error |
| 14 | Invalid card number | Card number incorrect |
| 33 | Expired card | Card has expired |
| 51 | Insufficient funds | Not enough balance |
| 54 | Expired card | Card validity ended |
| 91 | Issuer unavailable | Bank system down |

### Testing

Enable test mode:
```
EUPLATESC_TEST_MODE=true
```

Test cards (sandbox only):
- Use any valid-format card number
- Use future expiry date
- Use any 3-digit CVV

### Integration Checklist

1. Obtain merchant credentials from EuPlatesc
2. Configure webhook URL in merchant portal
3. Implement hash generation and verification
4. Build redirect form submission
5. Handle callback notifications
6. Test in sandbox mode
7. Submit for production approval
8. Switch to production credentials

### Security Best Practices

1. Always verify callback hash before processing
2. Use HTTPS for all callback URLs
3. Implement idempotent callback handling
4. Log all transactions for audit
5. Never store card numbers
6. Validate amounts match order totals
