# Tenant Payment Processor Integration

This document describes the complete payment processor integration system for tenants, allowing them to process payments from their customers using their preferred payment gateway.

## Overview

The system provides a unified interface for multiple payment processors, allowing tenants to:
1. Select their preferred payment processor during registration
2. Configure API credentials in their admin panel
3. Process customer payments through their chosen gateway
4. Handle webhooks/callbacks for payment confirmation

## Supported Payment Processors

### 1. Stripe
- **Region**: Global
- **Currencies**: 135+ currencies including EUR, USD, GBP, RON
- **Fees**: 2.9% + €0.25 per transaction
- **Best for**: International businesses, modern API

### 2. Netopia Payments (mobilPay)
- **Region**: Romania
- **Currencies**: RON, EUR, USD
- **Best for**: Romanian businesses with local customers

### 3. EuPlatesc
- **Region**: Romania, Eastern Europe
- **Currencies**: RON, EUR, USD
- **Best for**: Romanian businesses, competitive rates

### 4. PayU
- **Region**: Eastern Europe
- **Currencies**: RON, EUR, USD, PLN, HUF
- **Fees**: 2.99% + RON 0.39 per transaction
- **Best for**: Multi-country Eastern European operations

## Database Structure

### Migrations

1. **Add payment processor to tenants** (`2025_11_16_130000_add_payment_processor_to_tenants.php`)
   - Adds `payment_processor` field (enum: stripe, netopia, euplatesc, payu)
   - Adds `payment_processor_mode` field (enum: test, live)

2. **Tenant payment configs** (`2025_11_16_130001_create_tenant_payment_configs_table.php`)
   - Stores encrypted API credentials for each processor
   - Fields for all supported payment processors
   - Unique constraint on (tenant_id, processor)

### Models

#### TenantPaymentConfig
Located at: `app/Models/TenantPaymentConfig.php`

**Key Methods:**
- `getActiveKeys()`: Returns decrypted keys based on processor and mode
- `isConfigured()`: Checks if all required fields are populated

**Security:**
All sensitive fields use Laravel's `encrypted` cast:
- `stripe_secret_key`
- `stripe_webhook_secret`
- `netopia_api_key`
- `netopia_signature`
- `netopia_public_key`
- `euplatesc_secret_key`
- `payu_secret_key`

#### Tenant Model Extensions
The Tenant model has been extended with:
- `payment_processor` field
- `payment_processor_mode` field
- `paymentConfigs()` relationship
- `activePaymentConfig()` helper method

## Architecture

### Payment Processor Interface

All payment processors implement `PaymentProcessorInterface` located at:
`app/Services/PaymentProcessors/PaymentProcessorInterface.php`

**Core Methods:**
```php
public function createPayment(array $data): array;
public function processCallback(array $payload, array $headers = []): array;
public function verifySignature(array $payload, array $headers): bool;
public function getPaymentStatus(string $paymentId): array;
public function refundPayment(string $paymentId, ?float $amount = null, ?string $reason = null): array;
public function isConfigured(): bool;
public function getName(): string;
```

### Payment Processor Implementations

Located at: `app/Services/PaymentProcessors/`

1. **StripeProcessor.php** - Stripe integration using Stripe PHP SDK
2. **NetopiaProcessor.php** - Netopia mobilPay with RSA encryption
3. **EuplatescProcessor.php** - EuPlatesc with HMAC-MD5 signatures
4. **PayUProcessor.php** - PayU with HMAC-MD5 and IPN/IOS

### Payment Processor Factory

Located at: `app/Services/PaymentProcessors/PaymentProcessorFactory.php`

**Key Methods:**
- `make(Tenant $tenant)`: Create processor instance from tenant
- `makeFromConfig(TenantPaymentConfig $config)`: Create from config
- `getAvailableProcessors()`: List all processors with metadata
- `getRequiredFields(string $processor)`: Get config fields for processor
- `validateConfig(string $processor, array $data)`: Validate credentials

**Usage Example:**
```php
use App\Services\PaymentProcessors\PaymentProcessorFactory;

$processor = PaymentProcessorFactory::make($tenant);

$payment = $processor->createPayment([
    'amount' => 100.00,
    'currency' => 'EUR',
    'description' => 'Event Ticket Purchase',
    'customer_email' => 'customer@example.com',
    'customer_name' => 'John Doe',
    'order_id' => 'ORDER-12345',
    'success_url' => route('payment.success'),
    'cancel_url' => route('payment.cancel'),
]);

// Redirect customer to payment
redirect($payment['redirect_url']);
```

## Registration Flow

### Onboarding Controller

Location: `app/Http/Controllers/OnboardingController.php`

**Step 2 - Company Information**
During registration, tenants select their preferred payment processor:
- Payment processor selection is required
- Default mode is set to "test"
- Selection is saved to `tenants.payment_processor`

**View:**
Location: `resources/views/onboarding/wizard.blade.php`

Displays all available processors with:
- Name and description
- Supported currencies
- Fee structure
- Radio button selection

## Admin Configuration

### Filament Resource Page

Location: `app/Filament/Resources/Tenants/Pages/ManagePaymentConfig.php`

Accessible via: `/admin/tenants/{id}/payment-config`

**Features:**
- View selected payment processor (cannot change after registration)
- Switch between test/live modes
- Configure processor-specific API credentials
- Test connection button
- Display webhook URLs for each processor

**Processor-Specific Fields:**

**Stripe:**
- Publishable Key (public)
- Secret Key (encrypted)
- Webhook Secret (encrypted, optional)

**Netopia:**
- Merchant Signature
- Private Key (PEM format, encrypted)
- Public Certificate (PEM format, encrypted)

**EuPlatesc:**
- Merchant ID
- Secret Key (encrypted)

**PayU:**
- Merchant Code
- Secret Key (encrypted)

### Test Connection

The "Test Connection" button:
1. Loads the payment processor instance
2. Calls `isConfigured()` to validate all required fields
3. Shows success/failure notification
4. Does NOT make actual API calls to avoid charges

## Webhook Handling

### Webhook Controller

Location: `app/Http/Controllers/TenantPaymentWebhookController.php`

**Route:**
```
POST /webhooks/tenant-payment/{tenant}/{processor}
```

**Webhook URLs (displayed in admin panel):**
- Stripe: `https://yourdomain.com/webhooks/tenant-payment/{tenant_id}/stripe`
- Netopia: `https://yourdomain.com/webhooks/tenant-payment/{tenant_id}/netopia`
- EuPlatesc: `https://yourdomain.com/webhooks/tenant-payment/{tenant_id}/euplatesc`
- PayU: `https://yourdomain.com/webhooks/tenant-payment/{tenant_id}/payu`

**Security:**
1. Verifies processor matches tenant's selected processor
2. Verifies signature using processor-specific validation
3. Logs all webhook attempts
4. Returns appropriate HTTP status codes

**Webhook Flow:**
```php
1. Receive webhook POST request
2. Load tenant and payment config
3. Verify processor matches
4. Get processor instance
5. Verify signature
6. Process callback and get standardized result
7. Update order/transaction status
8. Return success response
```

**Standardized Result Format:**
```php
[
    'status' => 'success|failed|pending|cancelled',
    'payment_id' => 'processor_payment_id',
    'order_id' => 'your_order_reference',
    'amount' => 100.00,
    'currency' => 'EUR',
    'transaction_id' => 'processor_transaction_id',
    'paid_at' => '2025-11-16T12:00:00+00:00',
    'metadata' => [...],
]
```

## Creating a Payment

### Basic Payment Flow

```php
use App\Services\PaymentProcessors\PaymentProcessorFactory;

// 1. Get tenant's processor
$processor = PaymentProcessorFactory::make($tenant);

// 2. Create payment
$payment = $processor->createPayment([
    'amount' => 100.00, // Amount in main currency unit (EUR, RON, etc.)
    'currency' => 'EUR',
    'description' => 'Event Ticket - Concert XYZ',
    'customer_email' => 'customer@example.com',
    'customer_name' => 'John Doe',
    'order_id' => 'ORDER-12345', // Your internal order reference
    'success_url' => route('checkout.success', ['order' => $order->id]),
    'cancel_url' => route('checkout.cancel'),
    'metadata' => [
        'event_id' => $event->id,
        'ticket_count' => 2,
    ],
]);

// 3. Redirect customer
return redirect($payment['redirect_url']);

// Payment result includes:
// - payment_id: Processor's payment session/intent ID
// - redirect_url: URL to redirect customer to
// - additional_data: Processor-specific data
```

### Checking Payment Status

```php
$status = $processor->getPaymentStatus($paymentId);

// Returns:
// [
//     'status' => 'success',
//     'amount' => 100.00,
//     'currency' => 'EUR',
//     'paid_at' => '2025-11-16T12:00:00+00:00',
// ]
```

### Refunding a Payment

```php
// Full refund
$refund = $processor->refundPayment($paymentId);

// Partial refund
$refund = $processor->refundPayment($paymentId, 50.00, 'Partial refund requested');

// Returns:
// [
//     'refund_id' => 'refund_xyz',
//     'status' => 'success',
//     'amount' => 50.00,
// ]
```

## Processor-Specific Notes

### Stripe
- Uses Stripe Checkout Sessions for hosted payment page
- Webhooks are highly recommended for production
- Test mode uses `pk_test_` and `sk_test_` keys
- Live mode uses `pk_live_` and `sk_live_` keys

### Netopia (mobilPay)
- Requires RSA key pair (private key + public certificate)
- Uses XML-based communication
- Data is encrypted with AES-256 then RSA
- Sandbox URL: `https://sandboxsecure.mobilpay.ro`
- Live URL: `https://secure.mobilpay.ro`

### EuPlatesc
- Uses HMAC-MD5 for signature generation
- Callback signature verification is mandatory
- Supports IOS (Instant Order Status) API
- Sandbox URL: `https://sandboxsecure.euplatesc.ro`
- Live URL: `https://secure.euplatesc.ro`

### PayU
- Requires auto-submit HTML form for redirect
- Uses HMAC-MD5 signatures
- Supports IPN (Instant Payment Notification)
- Supports IOS (Instant Order Status) for payment queries
- Supports IRN (Instant Refund Notification)
- Sandbox URL: `https://sandbox.payu.ro`
- Live URL: `https://secure.payu.ro`

## Security Best Practices

1. **API Keys**: All sensitive keys are encrypted using Laravel's encryption
2. **Webhook Verification**: Always verify signatures before processing
3. **HTTPS**: All webhook URLs must use HTTPS in production
4. **Test Mode**: Start in test mode and only switch to live when ready
5. **Logging**: All webhook attempts are logged for audit trail
6. **Error Handling**: Never expose sensitive error details to customers

## Configuration Instructions

### For Tenants

1. **After Registration:**
   - Payment processor is selected during onboarding (Step 2)
   - Cannot be changed without contacting support

2. **Configure API Credentials:**
   - Navigate to Admin Panel → Tenants → [Your Tenant] → Payment Config
   - Fill in processor-specific fields
   - Start in "Test" mode
   - Use "Test Connection" to verify configuration

3. **Set Up Webhooks:**
   - Copy webhook URL from admin panel
   - Add to your payment processor's dashboard:
     - **Stripe**: Dashboard → Webhooks → Add endpoint
     - **Netopia**: Account settings → Notification URL
     - **EuPlatesc**: Merchant settings → Callback URL
     - **PayU**: Account configuration → IPN URL

4. **Go Live:**
   - Test thoroughly in test mode
   - Update credentials to live/production keys
   - Change mode to "Live"
   - Test with small real transaction
   - Monitor webhooks and transactions

### Obtaining API Credentials

**Stripe:**
1. Create account at stripe.com
2. Go to Developers → API keys
3. Copy Publishable key and Secret key
4. For webhooks: Developers → Webhooks → Add endpoint → Get signing secret

**Netopia:**
1. Register at netopia-payments.com
2. Complete merchant verification
3. Download security certificate and private key
4. Get merchant signature from dashboard

**EuPlatesc:**
1. Register at euplatesc.ro
2. Complete merchant application
3. Get Merchant ID and Secret Key from account settings

**PayU:**
1. Register at payu.ro
2. Complete merchant verification
3. Get Merchant Code and Secret Key from technical settings

## Testing

### Test Cards (Stripe)

- Success: `4242 4242 4242 4242`
- Decline: `4000 0000 0000 0002`
- 3D Secure: `4000 0027 6000 3184`

### Test Mode URLs

Each processor has separate test/sandbox environments. The system automatically uses the correct URL based on the `mode` setting.

## Troubleshooting

### Common Issues

1. **"Invalid signature" errors:**
   - Verify webhook secret is correct
   - Check that webhook URL matches exactly
   - Ensure request is not being modified by middleware

2. **"Configuration not found" errors:**
   - Verify payment config exists in database
   - Check `is_active` is true
   - Confirm processor matches tenant's selection

3. **Payments not completing:**
   - Check webhook logs in payment processor dashboard
   - Verify webhook URL is accessible (not behind firewall)
   - Ensure HTTPS is used for webhooks
   - Check webhook signature verification

4. **Test connection fails:**
   - Verify all required fields are filled
   - Check key format (especially for Stripe: pk_/sk_ prefixes)
   - For Netopia: ensure PEM format is correct

## Future Enhancements

Potential improvements for future versions:

1. **Multi-processor support**: Allow tenants to configure multiple processors
2. **Auto-retry**: Retry failed webhook processing
3. **Payment analytics**: Dashboard with payment statistics
4. **Recurring payments**: Support for subscriptions
5. **Apple Pay / Google Pay**: Quick checkout options
6. **More processors**: Add support for additional payment gateways
7. **Payment links**: Generate shareable payment links
8. **Split payments**: Support marketplace split payments

## Support

For issues or questions:
- Check logs: `storage/logs/laravel.log`
- Review webhook logs in payment processor dashboard
- Contact payment processor support for API issues
- Contact platform support for integration issues
