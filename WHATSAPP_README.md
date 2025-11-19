# WhatsApp Notifications Microservice

**Version:** 1.0.0
**Pricing:** Usage-based (per message) or monthly subscription
**Category:** Communication / Customer Engagement

## Overview

The WhatsApp Notifications microservice provides comprehensive WhatsApp messaging capabilities for:
- **Order Confirmations**: Idempotent confirmation messages on payment completion
- **Event Reminders**: Automatic scheduling at D-7, D-3, D-1 before event start
- **Promotional Campaigns**: Targeted messaging to segmented audiences
- **Opt-in/Opt-out Management**: GDPR-compliant consent tracking
- **Multi-BSP Support**: Provider-agnostic adapter pattern (360dialog, Twilio, Meta Cloud API)

## Key Features

- **Idempotent Order Confirmations**: Prevents duplicate messages using (order_ref + template_name)
- **Timezone-Aware Reminders**: Calculates D-7/D-3/D-1 based on tenant timezone
- **Template Management**: Full BSP approval workflow (draft → submitted → approved/rejected)
- **Delivery Tracking**: Sent → Delivered → Read status updates via webhooks
- **Rate Limiting**: Configurable throttling to respect BSP limits
- **Cost Tracking**: Automatic balance deduction per message sent
- **Variable Templating**: Support for {first_name}, {event_name}, {order_code}, etc.
- **Dry-Run Mode**: Test campaigns without sending actual messages
- **Webhook Integration**: Real-time delivery status updates from BSP

## Architecture

### BSP Adapter Pattern

The service uses a vendor-agnostic adapter interface, allowing easy integration with multiple WhatsApp Business Solution Providers:

**BspAdapterInterface** methods:
- `sendTemplate()` - Send template message
- `registerTemplate()` - Submit template for BSP approval
- `getTemplateStatus()` - Check approval status
- `webhookHandler()` - Process delivery receipts
- `verifyWebhookSignature()` - Security validation
- `getAccountInfo()` - Balance and quota information

**Supported BSPs** (via adapters):
- Mock (for testing)
- 360dialog
- Twilio
- Meta Cloud API
- Custom implementations

## Database Schema

### `wa_optin` - Consent Management

| Column | Type | Description |
|--------|------|-------------|
| `tenant_id` | string | Tenant identifier |
| `user_ref` | string | Generic user reference |
| `phone_e164` | string | E.164 formatted phone (+40722123456) |
| `status` | enum | opt_in, opt_out |
| `source` | string | Consent source (checkout, settings_page, api) |
| `consented_at` | timestamp | When user consented |
| `metadata` | json | IP, user_agent, consent_text_version |

**Unique constraint**: (tenant_id, phone_e164)

### `wa_templates` - Message Templates

| Column | Type | Description |
|--------|------|-------------|
| `tenant_id` | string | Tenant identifier |
| `name` | string | Template identifier |
| `language` | string | Language code (en, ro, en_US) |
| `category` | enum | order_confirm, reminder, promo, otp, other |
| `body` | text | Template body with {variable} placeholders |
| `variables` | json | Array of variable names |
| `status` | enum | draft, submitted, approved, rejected, disabled |
| `provider_meta` | json | BSP template ID, rejection reasons |

**Unique constraint**: (tenant_id, name)

### `wa_messages` - Message History

| Column | Type | Description |
|--------|------|-------------|
| `tenant_id` | string | Tenant identifier |
| `type` | enum | order_confirm, reminder, promo, otp, other |
| `to_phone` | string | Recipient E.164 phone |
| `template_name` | string | Template used |
| `variables` | json | Variable values for this message |
| `status` | enum | queued, sent, delivered, read, failed |
| `error_code` | string | Error code if failed |
| `bsp_message_id` | string | BSP tracking ID |
| `correlation_ref` | string | order_ref, campaign_id, etc. |
| `sent_at` | timestamp | When sent |
| `delivered_at` | timestamp | When delivered |
| `read_at` | timestamp | When read |
| `cost` | decimal | Message cost |

**Idempotency index**: (tenant_id, correlation_ref, template_name)

### `wa_schedules` - Reminder Scheduling

| Column | Type | Description |
|--------|------|-------------|
| `tenant_id` | string | Tenant identifier |
| `message_type` | enum | reminder_d7, reminder_d3, reminder_d1, promo, other |
| `run_at` | timestamp | When to send |
| `payload` | json | All data needed to send message |
| `status` | enum | scheduled, run, skipped, failed |
| `correlation_ref` | string | order_ref, campaign_id |
| `result` | json | Execution result |

**Unique constraint**: (tenant_id, correlation_ref, message_type)

## API Endpoints

### 1. Create/Update Template

**POST** `/api/wa/templates`

```json
{
  "tenant": "tenant_123",
  "name": "order_confirmation",
  "language": "ro",
  "category": "order_confirm",
  "body": "Buna {first_name}! Comanda ta {order_code} pentru {event_name} a fost confirmata.",
  "variables": ["first_name", "order_code", "event_name"]
}
```

**Response:**
```json
{
  "success": true,
  "template": {
    "id": 1,
    "name": "order_confirmation",
    "status": "draft",
    "message": "Template saved as draft. Submit for BSP approval to activate."
  }
}
```

### 2. Send Order Confirmation

**POST** `/api/wa/send/confirm`

```json
{
  "tenant": "tenant_123",
  "order_ref": "ORD-2025-001",
  "template_name": "order_confirmation",
  "customer_phone": "+40722123456",
  "customer_first_name": "Ion",
  "event_name": "Concert Rock",
  "event_date": "2025-12-01",
  "total_amount": "200 RON",
  "download_url": "https://example.com/download/..."
}
```

**Response:**
```json
{
  "success": true,
  "message_id": 123,
  "bsp_message_id": "wamid.ABC123...",
  "status": "sent"
}
```

**Idempotency**: If already sent for this (order_ref + template_name), returns existing message.

### 3. Schedule Event Reminders

**POST** `/api/wa/schedule/reminders`

```json
{
  "tenant": "tenant_123",
  "order_ref": "ORD-2025-001",
  "template_name": "event_reminder",
  "event_start_at": "2025-12-01 19:00:00",
  "customer_phone": "+40722123456",
  "customer_first_name": "Ion",
  "event_name": "Concert Rock",
  "event_date": "1 Dec 2025",
  "event_time": "19:00",
  "venue_name": "Arena Nationala",
  "venue_address": "Str. Basarabia 37-39"
}
```

**Response:**
```json
{
  "success": true,
  "scheduled_count": 3,
  "reminders": [
    {"type": "reminder_d7", "run_at": "2025-11-24T19:00:00+02:00"},
    {"type": "reminder_d3", "run_at": "2025-11-28T19:00:00+02:00"},
    {"type": "reminder_d1", "run_at": "2025-11-30T19:00:00+02:00"}
  ]
}
```

**Timezone**: Uses tenant's configured timezone (default: Europe/Bucharest).
**Skipping**: Skips reminders if date has passed or order is cancelled.

### 4. Send Promo Campaign

**POST** `/api/wa/send/promo`

```json
{
  "tenant": "tenant_123",
  "campaign_id": "PROMO-2025-001",
  "template_name": "promo_discount",
  "dry_run": false,
  "variables": {
    "discount_code": "SUMMER20",
    "expiry_date": "31 Dec 2025"
  },
  "recipients": [
    {
      "phone": "+40722123456",
      "variables": {"first_name": "Ion"}
    },
    {
      "phone": "+40722654321",
      "variables": {"first_name": "Maria"}
    }
  ]
}
```

**Response:**
```json
{
  "success": true,
  "sent": 2,
  "skipped": 0,
  "failed": 0,
  "dry_run": false,
  "results": [
    {"phone": "+40722123456", "status": "sent", "message_id": "wamid.ABC123"},
    {"phone": "+40722654321", "status": "sent", "message_id": "wamid.DEF456"}
  ]
}
```

**Features:**
- Checks opt-in before sending (skips non-consented)
- Merges base variables with recipient-specific variables
- Applies rate limiting and throttling
- Dry-run mode for testing

### 5. Webhook Handler

**POST** `/api/wa/webhook`

**Headers:**
- `X-Tenant-ID`: tenant_123
- `X-Webhook-Signature`: signature_from_bsp

**Payload** (example from BSP):
```json
{
  "type": "message_status",
  "message_id": "wamid.ABC123",
  "status": "delivered",
  "timestamp": "2025-11-16T10:00:00Z"
}
```

**Response:**
```json
{
  "success": true,
  "message": "Webhook processed"
}
```

**Security**: Verifies webhook signature to ensure authenticity.

### 6. Manage Opt-in/Opt-out

**POST** `/api/wa/optin`

```json
{
  "tenant": "tenant_123",
  "phone": "+40722123456",
  "action": "opt_in",
  "source": "checkout",
  "user_ref": "user_456"
}
```

**Response:**
```json
{
  "success": true,
  "message": "User opted in to WhatsApp notifications",
  "status": "opt_in"
}
```

### 7. Get Statistics

**GET** `/api/wa/stats/tenant_123?days=30`

**Response:**
```json
{
  "success": true,
  "tenant_id": "tenant_123",
  "period_days": 30,
  "messages": {
    "order_confirm": {
      "sent": {"count": 45, "cost": 0.225},
      "delivered": {"count": 42, "cost": 0.21},
      "failed": {"count": 3, "cost": 0.015}
    },
    "reminder": {
      "sent": {"count": 120, "cost": 0.6},
      "delivered": {"count": 115, "cost": 0.575}
    },
    "promo": {
      "sent": {"count": 200, "cost": 1.0}
    }
  },
  "opt_ins": 523,
  "approved_templates": 5,
  "scheduled_reminders": 87
}
```

### 8. List Messages

**GET** `/api/wa/messages/tenant_123?type=order_confirm&status=sent&limit=50`

### 9. List Scheduled Reminders

**GET** `/api/wa/schedules/tenant_123?status=scheduled&limit=50`

## Workflow Examples

### Order Confirmation Flow

```
[Payment Captured Event]
       ↓
[Trigger POST /api/wa/send/confirm]
       ↓
[Check idempotency] → Already sent? Return existing
       ↓ No
[Normalize phone to E.164]
       ↓
[Check opt-in] → Not opted in? Return fallback to email
       ↓ Opted in
[Load approved template]
       ↓
[Create message record (status: queued)]
       ↓
[Send via BSP adapter]
       ↓
   ┌───┴───┐
   ↓       ↓
[Success] [Fail]
   ↓       ↓
[Mark sent] [Mark failed + log error]
   ↓
[Deduct cost from tenant balance]
```

### Reminder Scheduling Flow

```
[Order Created/Confirmed]
       ↓
[Trigger POST /api/wa/schedule/reminders]
       ↓
[Get tenant timezone]
       ↓
[Parse event_start_at in timezone]
       ↓
[Calculate D-7, D-3, D-1 timestamps]
       ↓
[For each reminder]
   ↓
[Skip if date already passed]
   ↓
[Check idempotency] → Already scheduled? Skip
   ↓ No
[Create schedule record (status: scheduled)]
       ↓
[Cron job processes schedules at run_at time]
       ↓
[Check opt-in and order validity]
       ↓
[Send message via BSP]
       ↓
[Mark schedule as run/skipped/failed]
```

### Background Job Processing

Set up cron jobs to process scheduled reminders:

```php
// In app/Console/Kernel.php

protected function schedule(Schedule $schedule): void
{
    // Process scheduled reminders every 10 minutes
    $schedule->call(function () {
        $service = app(WhatsAppService::class);
        $result = $service->processScheduledReminders(limit: 50);

        Log::info('Processed WhatsApp reminders', $result);
    })->everyTenMinutes();
}
```

## BSP Adapter Implementation

### Creating a Custom Adapter

Example for 360dialog:

```php
namespace App\Services\WhatsApp\Adapters;

use Illuminate\Support\Facades\Http;

class ThreeSixZeroDialogAdapter implements BspAdapterInterface
{
    protected string $apiKey;
    protected string $baseUrl = 'https://waba.360dialog.io/v1';

    public function authenticate(array $credentials): array
    {
        $this->apiKey = $credentials['api_key'] ?? '';

        if (empty($this->apiKey)) {
            return ['success' => false, 'message' => 'Missing API key'];
        }

        return ['success' => true, 'message' => 'Authenticated'];
    }

    public function sendTemplate(string $to, string $templateName, array $variables = [], array $options = []): array
    {
        $response = Http::withHeaders([
            'D360-API-KEY' => $this->apiKey,
        ])->post("{$this->baseUrl}/messages", [
            'to' => $to,
            'type' => 'template',
            'template' => [
                'name' => $templateName,
                'language' => ['code' => $options['language'] ?? 'en'],
                'components' => [[
                    'type' => 'body',
                    'parameters' => array_map(fn($v) => ['type' => 'text', 'text' => $v], $variables),
                ]],
            ],
        ]);

        if ($response->successful()) {
            return [
                'success' => true,
                'message_id' => $response->json('messages.0.id'),
                'status' => 'sent',
                'cost' => 0.005, // Get from pricing API
            ];
        }

        return [
            'success' => false,
            'error_code' => $response->json('errors.0.code'),
            'error_message' => $response->json('errors.0.title'),
        ];
    }

    public function registerTemplate(string $name, string $body, string $language, array $variables = [], array $options = []): array
    {
        // Submit to 360dialog for approval
        $response = Http::withHeaders([
            'D360-API-KEY' => $this->apiKey,
        ])->post("{$this->baseUrl}/configs/templates", [
            'name' => $name,
            'language' => $language,
            'category' => $options['category'] ?? 'MARKETING',
            'components' => [[
                'type' => 'BODY',
                'text' => $body,
            ]],
        ]);

        return [
            'success' => $response->successful(),
            'template_id' => $response->json('id'),
            'status' => 'submitted',
            'message' => 'Template submitted for approval',
        ];
    }

    // Implement other methods...
}
```

**Register adapter:**

```php
// In a service provider
$whatsAppService->registerAdapter('360dialog', new ThreeSixZeroDialogAdapter());
```

## Configuration

### Tenant Credentials

Store BSP credentials securely per tenant:

```php
DB::table('tenant_configs')->insert([
    'tenant_id' => 'tenant_123',
    'key' => 'whatsapp_credentials',
    'value' => Crypt::encryptString(json_encode([
        'api_key' => '360dialog_api_key_here',
        'phone_number_id' => '+40722000000',
    ]))
]);
```

### Tenant Timezone

```php
DB::table('tenant_configs')->insert([
    'tenant_id' => 'tenant_123',
    'key' => 'timezone',
    'value' => 'Europe/Bucharest'
]);
```

## Security & Compliance

### GDPR Compliance

- **Explicit Consent**: Users must opt-in before receiving messages
- **Consent Source Tracking**: Records where consent was given (checkout, settings, etc.)
- **Easy Opt-out**: POST /api/wa/optin with action=opt_out
- **Metadata Logging**: IP, user agent, consent text version
- **Right to be Forgotten**: Delete user data on request

### Phone Number Validation

- **E.164 Format**: All phones normalized to +[country][number]
- **Romanian Default**: +40 prefix added if missing
- **Validation**: Regex `/^\+\d{10,15}$/`

### Webhook Security

- **Signature Verification**: HMAC-SHA256 validation
- **Tenant Isolation**: X-Tenant-ID header required
- **No Throttling**: Webhooks bypass rate limits

## Cost Tracking

Messages automatically deduct from tenant balance:

```php
// After successful send
$this->deductBalance($tenantId, $cost);
```

**Average costs** (vary by BSP and destination):
- Order confirmations: €0.005/message
- Reminders: €0.005/message
- Promos: €0.005-0.010/message

## Monitoring & Troubleshooting

### Check Opt-in Status

```php
$hasOptedIn = WhatsAppOptIn::hasOptedIn('tenant_123', '+40722123456');
```

### View Message History

```sql
SELECT * FROM wa_messages
WHERE tenant_id = 'tenant_123'
AND correlation_ref = 'ORD-2025-001'
ORDER BY created_at DESC;
```

### Check Scheduled Reminders

```sql
SELECT * FROM wa_schedules
WHERE tenant_id = 'tenant_123'
AND status = 'scheduled'
AND run_at BETWEEN NOW() AND NOW() + INTERVAL '24 HOURS'
ORDER BY run_at ASC;
```

### Common Issues

**Issue**: Message not sent
- Check opt-in status
- Verify template is approved
- Check BSP credentials
- Review error_message in wa_messages table

**Issue**: Reminder not scheduled
- Verify event_start_at is in future
- Check idempotency (already scheduled?)
- Verify timezone configuration

**Issue**: Webhook not processing
- Verify X-Tenant-ID header
- Check signature validation
- Ensure bsp_message_id matches

## Testing

### Mock Adapter

The `MockBspAdapter` simulates BSP behavior:

```php
$service->registerAdapter('mock', new MockBspAdapter());

// Sends with 95% success rate
// Templates approved with 90% rate
// Generates mock message IDs
```

### Dry-Run Promo Campaigns

```json
{
  "dry_run": true,
  "recipients": [...]
}
```

Returns what would be sent without actually sending.

## Changelog

### v1.0.0 (2025-11-16)
- Initial release
- Order confirmation messages (idempotent)
- Event reminders (D-7, D-3, D-1)
- Promo campaigns
- Opt-in/opt-out management
- Template approval workflow
- Multi-BSP support via adapter pattern
- Delivery tracking with webhooks
- Cost tracking
- 9 API endpoints
- Comprehensive statistics

## Support

For questions about the WhatsApp Notifications microservice:
- Review this documentation
- Check Laravel logs for errors
- Verify BSP credentials and quota
- Contact BSP support for delivery issues
- Review GDPR compliance requirements
