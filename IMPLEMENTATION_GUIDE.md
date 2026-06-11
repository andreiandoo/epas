# EPAS Microservices Implementation Guide

**Version:** 1.0.0  
**Last Updated:** November 16, 2025  
**Author:** EPAS Development Team

This guide covers the implementation, configuration, and deployment of the EPAS microservices infrastructure.

---

## Table of Contents

1. [Overview](#overview)
2. [Infrastructure Components](#infrastructure-components)
3. [Service Adapters](#service-adapters)
4. [Configuration](#configuration)
5. [Deployment Checklist](#deployment-checklist)
6. [Testing](#testing)
7. [Monitoring & Maintenance](#monitoring--maintenance)
8. [Troubleshooting](#troubleshooting)

---

## Overview

The EPAS platform now includes comprehensive microservices infrastructure with:

- **4 Production Adapters** - Twilio (WhatsApp), ANAF (eFactura), SmartBill (Accounting)
- **Notification System** - Multi-channel tenant notifications
- **Email Service** - Queue-based email delivery for Invitations
- **Webhook System** - Tenant integrations with external systems
- **Feature Flags** - Gradual rollout and A/B testing capabilities

All services are built with:
- Queue-based processing
- Automatic retry logic
- Comprehensive logging
- Tenant isolation
- Security best practices

---

## Infrastructure Components

### 1. Critical Infrastructure (Completed ✓)

#### Database Tables

**tenant_configs**
- Purpose: Encrypted storage of tenant credentials
- Usage: Store API keys, certificates, and service settings per tenant
- Encryption: Automatic via Laravel Crypt

**microservices**
- Purpose: Catalog of available microservices
- Fields: Name, pricing, features, dependencies, status

**tenant_microservices**
- Purpose: Track which microservices each tenant has activated
- Fields: Status, activation date, expiration, settings, usage stats

**tenant_notifications**
- Purpose: Store tenant notifications across multiple channels
- Types: 10 notification types (microservice_expiring, efactura_rejected, etc.)
- Channels: Email, database, WhatsApp

**tenant_webhooks** & **tenant_webhook_deliveries**
- Purpose: Webhook endpoint management and delivery tracking
- Features: Retry logic, signature verification, delivery history

**feature_flags** & **tenant_feature_flags**
- Purpose: Feature flag management for gradual rollouts
- Strategies: All, percentage, whitelist, custom conditions

#### Service Providers

**MicroservicesServiceProvider** (`app/Providers/MicroservicesServiceProvider.php`)
- Registers all microservice services as singletons
- Registers adapters (Mock, Twilio, ANAF, SmartBill)
- Location: `bootstrap/providers.php`

#### Middleware

**TenantAuthentication** (`app/Http/Middleware/TenantAuthentication.php`)
- Validates X-API-Key header
- Attaches tenant context to requests
- Logs API requests for audit
- Alias: `tenant.auth`

#### Events & Listeners

**OrderConfirmed Event**
- Triggers: `SendOrderConfirmationListener` (WhatsApp)
- Sends order confirmation + schedules event reminders

**PaymentCaptured Event**
- Triggers: 
  - `SubmitEFacturaListener` (eFactura submission)
  - `IssueInvoiceListener` (External accounting)

#### Scheduled Tasks (`routes/console.php`)

- WhatsApp reminders: Every 10 minutes
- eFactura processing: Every 5 minutes
- eFactura polling: Every 10 minutes
- Microservice expiration alerts: Daily at 8 AM
- Auto-suspension of expired subscriptions: Daily at midnight

---

## Service Adapters

### WhatsApp BSP Adapters

#### Twilio Adapter (Production Ready ✓)

**File:** `app/Services/WhatsApp/Adapters/TwilioAdapter.php`

**Configuration:**
```php
$credentials = [
    'account_sid' => 'ACxxxxxxxxxxxxxxxxxxxxxxxxxxxx',
    'auth_token' => 'your_auth_token',
    'from_number' => '+14155238886', // Twilio WhatsApp sandbox or approved number
];
```

**Features:**
- Content API integration
- Template message support
- Delivery receipts
- Webhook handling
- Rate limits: 80/sec, 4800/min

**Usage:**
```php
$whatsAppService = app(\App\Services\WhatsApp\WhatsAppService::class);
$whatsAppService->setAdapter('twilio', $tenantCredentials);

$result = $whatsAppService->sendOrderConfirmation(
    $tenantId,
    $orderRef,
    $orderData
);
```

**Template Format:**
- Create templates in Twilio Console
- Get Content SID (starts with 'HX')
- Store Content SID in template mapping

**Webhook Setup:**
1. Configure webhook URL in Twilio Console
2. Point to: `/api/whatsapp/webhook/twilio`
3. Set HTTP POST method
4. Enable delivery status

**Testing:**
```bash
# Test connection
curl -X POST /api/whatsapp/test \
  -H "X-API-Key: your-api-key" \
  -d '{"tenant_id": "test-tenant"}'

# Send test message
curl -X POST /api/whatsapp/send \
  -H "X-API-Key: your-api-key" \
  -d '{
    "tenant_id": "test-tenant",
    "to": "+40712345678",
    "template": "order_confirmation",
    "variables": ["John", "ORD-001"]
  }'
```

---

### eFactura Adapters

#### ANAF Adapter (Production Ready ✓)

**File:** `app/Services/EFactura/Adapters/AnafAdapter.php`

**Configuration:**
```php
$credentials = [
    'certificate' => file_get_contents('/path/to/cert.pem'),
    'private_key' => file_get_contents('/path/to/key.pem'),
    'api_token' => 'optional_oauth_token',
    'environment' => 'production', // or 'test'
];
```

**Features:**
- UBL 2.1 XML generation
- XMLDSig signing (production requires robrichards/xmlseclibs)
- ANAF SPV API integration
- Status polling
- PDF/ZIP downloads

**XML Generation:**
- Follows Romanian eFactura standard
- Validates seller/buyer information
- Includes all required UBL elements
- Generates proper namespaces

**Workflow:**
1. **Build XML:** Convert invoice data to UBL 2.1
2. **Sign:** Apply XMLDSig signature
3. **Submit:** POST to ANAF SPV API
4. **Poll:** Check status periodically
5. **Download:** Retrieve confirmation documents

**Usage:**
```php
$eFacturaService = app(\App\Services\EFactura\EFacturaService::class);
$eFacturaService->setAdapter('anaf', $tenantCredentials);

// Queue invoice for submission
$result = $eFacturaService->queueInvoice(
    $tenantId,
    $invoiceId,
    $invoiceData
);

// Process queue (scheduled task)
$result = $eFacturaService->processQueue(10);

// Poll for status updates
$result = $eFacturaService->pollPending(20);
```

**Certificate Setup:**
1. Obtain qualified certificate from authorized CA
2. Store certificate and private key securely
3. Add to tenant_configs (encrypted)
4. Configure certificate path in credentials

**API Endpoints:**
- Production: `https://api.anaf.ro/prod/FCTEL/rest`
- Test: `https://api.anaf.ro/test/FCTEL/rest`

**Error Handling:**
- Invalid VAT number
- Missing required fields
- XML validation errors
- ANAF rejection reasons

---

### Accounting Adapters

#### SmartBill Adapter (Production Ready ✓)

**File:** `app/Services/Accounting/Adapters/SmartBillAdapter.php`

**Configuration:**
```php
$credentials = [
    'username' => 'your@email.com',
    'token' => 'your_api_token',
    'company_vat' => 'RO12345678',
];
```

**Features:**
- Customer management (ensure/create)
- Product/service management
- Invoice creation
- PDF generation
- Credit notes
- Data sync (customers/products)

**API Base URL:** `https://ws.smartbill.ro/SBORO/api`

**Authentication:** HTTP Basic Auth (username + token)

**Usage:**
```php
$accountingService = app(\App\Services\Accounting\AccountingService::class);
$accountingService->setAdapter('smartbill', $tenantCredentials);

// Ensure customer exists
$customerResult = $accountingService->ensureCustomer([
    'name' => 'Acme Corp',
    'vat_number' => 'RO12345678',
    'email' => 'contact@acme.com',
    'address' => [...],
]);

// Create invoice
$invoiceResult = $accountingService->issueInvoice(
    $tenantId,
    $invoiceData,
    ['issue_extern' => true]
);

// Get PDF
$pdf = $accountingService->getInvoicePdf($externalRef);
```

**SmartBill Specific Fields:**
- `seriesName`: Invoice series (e.g., 'EPAS')
- `isTaxPayer`: Boolean for VAT payer status
- `measuringUnit`: Product unit (e.g., 'buc', 'ora')
- `productType`: 'Produs' or 'Serviciu'

**Sync Workflow:**
1. Import customers from SmartBill
2. Map to EPAS customer IDs
3. Sync products/services
4. Enable automatic invoice creation

---

## Configuration

### Environment Variables

Add to `.env`:

```bash
# Queue Configuration
QUEUE_CONNECTION=redis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

# Mail Configuration
MAIL_MAILER=smtp
MAIL_HOST=smtp.your-provider.com
MAIL_PORT=587
MAIL_USERNAME=your_username
MAIL_PASSWORD=your_password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@epas.ro
MAIL_FROM_NAME="${APP_NAME}"

# Webhook Configuration
WEBHOOK_RETRY_LIMIT=3
WEBHOOK_TIMEOUT=30

# Feature Flags
FEATURE_FLAGS_CACHE_TTL=300

# Email Tracking (GDPR compliant)
ENABLE_EMAIL_TRACKING=false
```

### Tenant Configuration

Store sensitive credentials in `tenant_configs` table:

```php
// WhatsApp (Twilio)
$config = [
    'tenant_id' => 'tenant-123',
    'key' => 'whatsapp.twilio.credentials',
    'value' => json_encode([
        'account_sid' => 'AC...',
        'auth_token' => '...',
        'from_number' => '+14155238886',
    ]),
    'is_encrypted' => true,
];

// eFactura (ANAF)
$config = [
    'tenant_id' => 'tenant-123',
    'key' => 'efactura.anaf.credentials',
    'value' => json_encode([
        'certificate' => '...',
        'private_key' => '...',
        'environment' => 'production',
    ]),
    'is_encrypted' => true,
];

// Accounting (SmartBill)
$config = [
    'tenant_id' => 'tenant-123',
    'key' => 'accounting.smartbill.credentials',
    'value' => json_encode([
        'username' => 'email@example.com',
        'token' => '...',
        'company_vat' => 'RO12345678',
    ]),
    'is_encrypted' => true,
];
```

---

## Deployment Checklist

### Pre-Deployment

- [ ] Run migrations: `php artisan migrate`
- [ ] Seed microservices: `php artisan db:seed --class=MicroservicesSeeder`
- [ ] Configure queue driver (Redis recommended)
- [ ] Configure mail driver
- [ ] Set up SSL certificates for HTTPS
- [ ] Configure CORS if needed
- [ ] Set up monitoring (logs, metrics)

### Queue Workers

Start queue workers for background processing:

```bash
# Start general queue worker
php artisan queue:work --queue=default --tries=3

# Start email queue worker
php artisan queue:work --queue=emails --tries=3

# Start webhooks queue worker
php artisan queue:work --queue=webhooks --tries=3

# Use supervisor for production
sudo supervisorctl start laravel-worker:*
```

### Scheduler

Add to crontab:
```bash
* * * * * cd /path/to/epas && php artisan schedule:run >> /dev/null 2>&1
```

### Security

- [ ] Rotate application key: `php artisan key:generate` (ONLY on first setup)
- [ ] Enable HTTPS (SSL/TLS)
- [ ] Configure firewall rules
- [ ] Set up rate limiting
- [ ] Enable API authentication
- [ ] Review file permissions (storage writable)
- [ ] Configure backup strategy

---

## Testing

### Run Test Suite

```bash
# Run all tests
php artisan test

# Run specific test file
php artisan test tests/Feature/WhatsAppMicroserviceTest.php

# Run with coverage
php artisan test --coverage
```

### Manual Testing

**Test WhatsApp Integration:**
```bash
curl -X POST http://localhost/api/whatsapp/test \
  -H "X-API-Key: test-key" \
  -H "X-Tenant-ID: test-tenant"
```

**Test eFactura Integration:**
```bash
curl -X POST http://localhost/api/efactura/test \
  -H "X-API-Key: test-key" \
  -H "X-Tenant-ID: test-tenant"
```

**Test Notification System:**
```bash
curl -X POST http://localhost/api/notifications/test \
  -H "X-API-Key: test-key" \
  -d '{"tenant_id": "test-tenant", "type": "system_alert"}'
```

---

## Monitoring & Maintenance

### Logs

Monitor these log files:
- `storage/logs/laravel.log` - Application logs
- Queue worker logs (supervisor)
- Web server logs (nginx/apache)

### Metrics to Monitor

1. **Queue Health**
   - Queue size
   - Failed jobs count
   - Average processing time

2. **Microservices**
   - WhatsApp send success rate
   - eFactura submission success rate
   - Accounting sync errors

3. **Webhooks**
   - Delivery success rate
   - Average response time
   - Failed deliveries needing retry

4. **Notifications**
   - Email delivery rate
   - Unread notification count per tenant

### Scheduled Maintenance Tasks

Weekly:
- Review failed jobs: `php artisan queue:failed`
- Check webhook delivery failures
- Review notification delivery stats

Monthly:
- Archive old webhook deliveries
- Clean up old notification records
- Review feature flag usage

---

## Troubleshooting

### Common Issues

**Queue Jobs Not Processing**
```bash
# Check queue worker status
supervisorctl status laravel-worker:*

# Restart queue workers
supervisorctl restart laravel-worker:*

# Check failed jobs
php artisan queue:failed

# Retry failed job
php artisan queue:retry {job-id}
```

**WhatsApp Messages Not Sending**
1. Verify Twilio credentials
2. Check WhatsApp template approval status
3. Verify phone number format (E.164)
4. Check rate limits
5. Review logs for specific errors

**eFactura Submission Failing**
1. Verify certificate hasn't expired
2. Check VAT number format
3. Validate XML structure
4. Review ANAF rejection reasons
5. Test with ANAF test environment first

**Emails Not Delivering**
1. Check mail configuration in `.env`
2. Verify SMTP credentials
3. Check queue worker is running
4. Review mail logs
5. Test with: `php artisan tinker` → `Mail::raw('Test', fn($msg) => $msg->to('test@example.com'))`

**Webhooks Failing**
1. Verify endpoint URL is accessible
2. Check SSL certificate (if verify_ssl=true)
3. Review webhook timeout settings
4. Check endpoint response codes
5. Verify signature validation on receiving end

---

## Next Steps

### Immediate (Required for Production)

1. **Configure Credentials**
   - Add Twilio credentials for WhatsApp
   - Add ANAF certificate for eFactura
   - Add SmartBill credentials for accounting

2. **Set Up Infrastructure**
   - Configure Redis for queues
   - Set up mail server
   - Configure supervisor for queue workers

3. **Security Hardening**
   - Enable HTTPS
   - Configure rate limiting
   - Set up firewall rules

### Short Term (Recommended)

1. **Create Admin Panels** (Filament)
   - Microservice management
   - Webhook configuration
   - Feature flag management
   - Notification management

2. **Add Monitoring**
   - Laravel Telescope (development)
   - Laravel Horizon (queue monitoring)
   - Custom metrics dashboard

3. **Implement Missing Features**
   - 360dialog adapter (when credentials available)
   - Additional accounting adapters (FGO, Saga)
   - API documentation (OpenAPI/Swagger)

### Long Term (Nice to Have)

1. **Advanced Features**
   - Audit logging
   - Usage analytics
   - Cost tracking per microservice
   - Automated billing

2. **Developer Experience**
   - API client libraries
   - Webhook testing tools
   - Sandbox environment

---

## Support

For questions or issues:
- **Email:** support@epas.ro
- **Documentation:** `/docs/microservices`
- **GitHub Issues:** https://github.com/epas/issues

---

**Version:** 1.0.0  
**Last Updated:** November 16, 2025  
© 2025 EPAS. All rights reserved.
