# Stripe Payment Integration for Microservices

## Overview

Complete Stripe integration for processing microservice purchases with support for one-time payments and recurring subscriptions. This system handles the entire payment flow from checkout to invoice generation and email confirmations.

## Features

✅ **Stripe Checkout Integration**
- One-time payments
- Recurring subscriptions (monthly/yearly)
- Multi-item cart support
- Automatic currency handling

✅ **Admin Configuration**
- Settings > Connections subpage for Stripe API keys
- Test/Live mode toggle
- Encrypted key storage
- Connection testing

✅ **Microservices Marketplace**
- Browse available microservices
- Add multiple services to cart
- Real-time price calculation
- Visual service cards with features

✅ **Automated Processing**
- Webhook handling for payment events
- Automatic microservice activation
- Invoice generation
- Email confirmations with PDF attachments

✅ **Invoice Management**
- Automatic invoice numbering
- VAT calculation (19%)
- PDF generation
- Email delivery

## Installation & Setup

### 1. Run Migrations

```bash
php artisan migrate
```

This will add the following Stripe configuration fields to the `settings` table:
- `stripe_mode` - Test or Live mode
- `stripe_test_public_key`
- `stripe_test_secret_key`
- `stripe_live_public_key`
- `stripe_live_secret_key`
- `stripe_webhook_secret`

### 2. Install Stripe SDK

```bash
composer install
```

The `stripe/stripe-php` package is already added to `composer.json`.

### 3. Seed Email Template

```bash
php artisan db:seed --class=MicroservicePurchaseEmailTemplateSeeder
```

This creates an editable email template for purchase confirmations.

### 4. Configure Stripe

#### Get Your API Keys

1. Go to [Stripe Dashboard](https://dashboard.stripe.com/apikeys)
2. Copy your **Publishable key** and **Secret key**
3. For testing, use keys starting with `pk_test_` and `sk_test_`
4. For production, use keys starting with `pk_live_` and `sk_live_`

#### Add Keys to Application

1. Navigate to **Admin Panel** → **Settings** → **Connections**
2. Select **Stripe Mode** (Test or Live)
3. Enter your API keys:
   - **Test Publishable Key**: `pk_test_...`
   - **Test Secret Key**: `sk_test_...`
   - **Live Publishable Key**: `pk_live_...`
   - **Live Secret Key**: `sk_live_...`
4. Click **Test Stripe Connection** to verify
5. Click **Save Configuration**

#### Configure Webhooks

1. Go to [Stripe Webhooks](https://dashboard.stripe.com/webhooks)
2. Click **Add endpoint**
3. Enter the endpoint URL: `https://yourdomain.com/webhooks/stripe`
4. Select events to listen for:
   - `checkout.session.completed`
   - `invoice.paid`
   - `customer.subscription.created`
   - `customer.subscription.deleted`
5. Click **Add endpoint**
6. Copy the **Signing secret** (starts with `whsec_`)
7. Add it to **Settings** → **Connections** → **Webhook Signing Secret**

## Usage

### For Admins

#### Managing Microservice Pricing

1. Go to **Admin Panel** → **Microservices**
2. Edit any microservice
3. Update the **Price** field
4. Select **Pricing Model**:
   - One-time Payment
   - Monthly Subscription
   - Yearly Subscription
   - Pay Per Use
5. Save changes

#### Viewing Tenants Using Microservices

1. Go to **Admin Panel** → **Microservices**
2. Click on the **Active Tenants** count for any microservice
3. View list of all tenants using that service
4. See activation dates and status

#### Editing Purchase Confirmation Email

1. Go to **Admin Panel** → **Email Templates**
2. Find **Microservice Purchase Confirmation**
3. Edit the template using available placeholders:
   - `{{tenant_name}}` - Tenant name
   - `{{invoice_number}}` - Invoice number
   - `{{invoice_amount}}` - Total amount
   - `{{microservices_list}}` - List of purchased services
   - `{{microservices_count}}` - Number of services
   - `{{admin_url}}` - Admin panel URL

### For Tenants

#### Browsing Microservices

Navigate to: `/micro/marketplace?tenant_id=YOUR_TENANT_ID`

The marketplace displays:
- All available microservices
- Pricing information
- Feature lists
- Already activated services (marked with green badge)

#### Purchasing Microservices

1. Select microservices by checking the "Add to cart" checkbox
2. Review the cart summary at the bottom
3. Click **Proceed to Checkout**
4. Complete payment on Stripe's secure checkout page
5. Return to confirmation page after successful payment

#### After Purchase

- Microservices are activated immediately
- Confirmation email sent with invoice PDF
- Access admin panel to configure services

## Payment Flow

```
┌─────────────────────────────────────────────────────────────┐
│                    PAYMENT FLOW                              │
├─────────────────────────────────────────────────────────────┤
│                                                               │
│  1. Tenant visits marketplace                                │
│     └─ /micro/marketplace                                    │
│                                                               │
│  2. Select microservices & checkout                          │
│     └─ POST /micro/checkout                                  │
│     └─ StripeService creates checkout session               │
│     └─ Redirect to Stripe                                    │
│                                                               │
│  3. Customer completes payment on Stripe                     │
│     └─ Stripe processes payment                              │
│     └─ Stripe sends webhook to /webhooks/stripe             │
│                                                               │
│  4. Backend processes webhook                                │
│     └─ Verify webhook signature                              │
│     └─ Activate microservices for tenant                     │
│     └─ Generate invoice                                       │
│     └─ Send confirmation email                               │
│                                                               │
│  5. Redirect to success page                                 │
│     └─ GET /micro/payment/success                            │
│     └─ Display purchase summary                              │
└─────────────────────────────────────────────────────────────┘
```

## API Endpoints

### Public Routes

| Method | Route | Description |
|--------|-------|-------------|
| GET | `/micro/marketplace` | Browse microservices marketplace |
| POST | `/micro/checkout` | Initiate Stripe checkout |
| GET | `/micro/payment/success` | Payment success page |
| GET | `/micro/payment/cancel` | Payment cancellation page |
| POST | `/webhooks/stripe` | Stripe webhook endpoint |

## Database Schema

### Settings Table (Extended)

```sql
stripe_mode VARCHAR(255) DEFAULT 'test'
stripe_test_public_key TEXT NULL
stripe_test_secret_key TEXT NULL (encrypted)
stripe_live_public_key TEXT NULL
stripe_live_secret_key TEXT NULL (encrypted)
stripe_webhook_secret TEXT NULL (encrypted)
```

### Invoices Table

Stores generated invoices for microservice purchases:

```sql
id BIGINT
tenant_id BIGINT FK
number VARCHAR(255) - Auto-generated invoice number
description TEXT
issue_date DATE
due_date DATE
subtotal DECIMAL(10,2)
vat_rate DECIMAL(5,2)
vat_amount DECIMAL(10,2)
amount DECIMAL(10,2) - Total including VAT
currency VARCHAR(3)
status VARCHAR(50) - 'paid', 'outstanding', etc.
meta JSON - Contains stripe_session_id, microservice_ids
created_at TIMESTAMP
updated_at TIMESTAMP
```

## Security

### API Key Encryption

Secret keys are automatically encrypted using Laravel's encryption:
- `stripe_test_secret_key`
- `stripe_live_secret_key`
- `stripe_webhook_secret`

### Webhook Signature Verification

All webhook requests are verified using Stripe's signature verification to prevent tampering:

```php
$event = Webhook::constructEvent($payload, $signature, $webhookSecret);
```

### CSRF Protection

The webhook endpoint is excluded from CSRF protection but uses signature verification instead.

## Error Handling

### Payment Failures

- User is redirected back to marketplace with error message
- No microservices are activated
- No invoice is generated

### Webhook Failures

- All webhook events are logged
- Failed webhook processing is logged with full stack trace
- Webhooks can be retried from Stripe Dashboard

### Email Failures

- Email failures are logged but don't block payment processing
- Admins can manually resend invoices if needed

## Testing

### Test Mode

1. Set **Stripe Mode** to **Test** in Settings > Connections
2. Use test API keys
3. Use [Stripe test cards](https://stripe.com/docs/testing):
   - Success: `4242 4242 4242 4242`
   - Decline: `4000 0000 0000 0002`
   - 3D Secure: `4000 0025 0000 3155`

### Testing Webhooks Locally

Use Stripe CLI to forward webhooks to local development:

```bash
stripe listen --forward-to http://localhost:8000/webhooks/stripe
```

This will provide a webhook signing secret to use in your settings.

## Troubleshooting

### Stripe Not Configured Warning

**Problem**: Yellow warning banner on marketplace

**Solution**: Complete Stripe configuration in Settings > Connections

### Connection Test Fails

**Problem**: "Connection failed" notification

**Possible Causes**:
- Invalid API keys
- Wrong mode selected (test keys in live mode or vice versa)
- Network connectivity issues

**Solution**: Double-check API keys match the selected mode

### Webhook Not Working

**Problem**: Payments succeed but microservices not activated

**Possible Causes**:
- Webhook URL not configured in Stripe
- Invalid webhook signing secret
- Webhook endpoint not accessible

**Solution**:
1. Verify webhook URL in Stripe Dashboard
2. Check webhook signing secret matches
3. Review logs: `tail -f storage/logs/laravel.log`

### Invoice Not Generated

**Problem**: Payment succeeds but no invoice created

**Possible Causes**:
- Webhook processing failed
- Invoice generation error

**Solution**:
1. Check webhook logs
2. Verify invoice settings in Settings
3. Check `invoices` table for records

## Customization

### Changing VAT Rate

Edit `app/Http/Controllers/StripeWebhookController.php`:

```php
protected function generateInvoice(Tenant $tenant, $microservices, $result): Invoice
{
    $vatRate = 19; // Change this value
    // ...
}
```

### Custom Email Template

1. Go to Admin Panel → Email Templates
2. Edit "Microservice Purchase Confirmation"
3. Customize HTML/text content
4. Use available placeholders
5. Save changes

### Invoice PDF Styling

Edit `resources/views/invoices/microservice-purchase.blade.php` to customize the PDF layout and styling.

## Support

For issues or questions:
- Check the logs: `storage/logs/laravel.log`
- Review Stripe Dashboard for payment details
- Contact development team

## License

Proprietary - Part of the EPAS platform
