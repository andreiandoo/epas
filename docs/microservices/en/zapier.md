# Zapier Integration

## Short Presentation

Connect your event platform to 5,000+ apps without writing a single line of code. Zapier Integration turns your ticket sales, registrations, and events into triggers that automate your entire business workflow.

When someone buys a ticket, things happen automatically. Add them to your email list. Create a CRM contact. Send a Slack notification. Update your spreadsheet. Zapier makes it possible - you just set up the workflow once.

Real-time triggers fire the moment events occur. Order placed? Trigger sent. Customer registered? Trigger sent. Event published? Your connected apps know immediately. No delays, no manual exports.

Six powerful triggers cover your key business events: orders created, tickets sold, customers registered, events published, registrations completed, and refunds issued. Each trigger sends complete data to power your automations.

REST Hook technology means efficient, instant delivery. Unlike polling-based integrations, webhooks push data to Zapier the moment something happens. Your workflows run in real-time.

No coding required. Zapier's visual workflow builder lets anyone create automations. Connect to Mailchimp, Google Sheets, Salesforce, Slack, and thousands more. If an app is on Zapier, you can connect it.

Track everything with built-in logging. See which triggers fired, when they fired, and what data was sent. Troubleshoot issues quickly with detailed delivery records.

Automate the busywork. Focus on your events.

---

## Detailed Description

The Zapier Integration microservice enables your event platform to communicate with thousands of third-party applications through Zapier's automation platform. It provides webhook-based triggers that fire when key events occur in your system.

### How Zapier Works

Zapier connects apps through "Zaps" - automated workflows with triggers and actions:

1. **Trigger**: Something happens in your platform (e.g., ticket sold)
2. **Action**: Zapier does something in another app (e.g., add to Mailchimp)

Your platform provides the triggers. Zapier users configure what actions happen in response.

### REST Hook Architecture

The integration uses REST Hooks (webhook subscriptions) rather than polling:

- **Subscribe**: When a user creates a Zap, Zapier subscribes to your trigger
- **Fire**: When the event occurs, your platform sends data to Zapier
- **Unsubscribe**: When the Zap is disabled, the subscription is removed

This architecture is more efficient than polling and delivers data instantly.

### Available Triggers

| Trigger | Fires When | Data Included |
|---------|------------|---------------|
| Order Created | New order placed | Order details, items, customer, totals |
| Ticket Sold | Individual ticket purchased | Ticket details, attendee, event info |
| Customer Created | New customer registers | Customer profile, contact info |
| Event Published | Event goes live | Event details, dates, venue, tickets |
| Registration Completed | Full registration finished | Registration data, custom fields |
| Refund Issued | Refund processed | Refund amount, reason, order reference |

### Authentication

The integration uses API key authentication:
- Users generate an API key in your platform
- They enter the key when connecting in Zapier
- All webhook calls include the key for verification

### Webhook Delivery

When a trigger fires:

1. Event occurs in your platform
2. System identifies active subscriptions for that trigger
3. Payload is built with relevant data
4. Webhook is sent to each subscribed endpoint
5. Delivery is logged for tracking

Failed deliveries are retried with exponential backoff.

---

## Features

### Triggers
- Order created trigger
- Ticket sold trigger
- Customer created trigger
- Event published trigger
- Registration completed trigger
- Refund issued trigger

### Integration
- REST Hook subscriptions
- Real-time webhook delivery
- Automatic retry on failure
- Subscription management
- Multi-Zap support

### Authentication & Security
- API key authentication
- Secure webhook endpoints
- Key rotation support
- Per-user API keys

### Monitoring
- Trigger logging
- Delivery tracking
- Error reporting
- Webhook history
- Debug information

### Data
- Complete event payloads
- Custom field inclusion
- Formatted timestamps
- Relationship data included
- Consistent data structure

---

## Use Cases

### Email Marketing Automation
When someone buys a ticket, automatically add them to your Mailchimp, ConvertKit, or ActiveCampaign list. Tag them based on event type. Start automated email sequences.

### CRM Updates
Create or update contacts in Salesforce, HubSpot, or Pipedrive when customers make purchases. Track purchase history and event attendance in your CRM automatically.

### Team Notifications
Send Slack or Microsoft Teams messages when orders come in. Alert your team to VIP purchases, large orders, or specific events. Keep everyone informed without manual checking.

### Spreadsheet Tracking
Append new orders to Google Sheets or Excel. Build real-time sales tracking dashboards. Create backup records of all transactions automatically.

### Customer Support
Create help desk tickets in Zendesk or Freshdesk when refunds are issued. Automatically assign follow-up tasks when certain triggers fire.

### Social Media
Post to social channels when events are published. Share milestones when ticket sales reach certain numbers. Automate your event promotion.

---

## Technical Documentation

### Overview

The Zapier Integration microservice provides REST Hook endpoints for Zapier to subscribe to platform events. It manages subscriptions, fires webhooks when events occur, and logs all activity.

### Configuration

```php
'zapier' => [
    'enabled' => env('ZAPIER_ENABLED', true),
    'api_key_header' => 'X-API-Key',
    'retry_attempts' => 3,
    'retry_delay' => [1, 5, 30], // seconds
    'timeout' => 30,
    'logging' => true,
]
```

### API Endpoints

#### Subscribe to Trigger

```
POST /api/zapier/hooks/subscribe
```

Called by Zapier when a Zap is enabled.

**Headers:**
```
X-API-Key: your_api_key
```

**Request:**
```json
{
  "hookUrl": "https://hooks.zapier.com/hooks/catch/123/abc",
  "trigger": "order_created"
}
```

**Response:**
```json
{
  "id": "hook_abc123",
  "trigger": "order_created",
  "hookUrl": "https://hooks.zapier.com/hooks/catch/123/abc",
  "active": true,
  "created_at": "2025-01-15T10:30:00Z"
}
```

#### Unsubscribe from Trigger

```
DELETE /api/zapier/hooks/{hookId}
```

Called when a Zap is disabled.

#### List Subscriptions

```
GET /api/zapier/hooks
```

Returns active webhook subscriptions.

#### Test Trigger

```
POST /api/zapier/hooks/test/{trigger}
```

Sends sample data for Zap setup.

### Trigger Payloads

#### Order Created

```json
{
  "id": "order_123",
  "order_number": "ORD-2025-0001",
  "status": "completed",
  "total": 150.00,
  "currency": "EUR",
  "created_at": "2025-01-15T14:30:00Z",
  "customer": {
    "id": "cust_456",
    "email": "customer@example.com",
    "first_name": "Ion",
    "last_name": "Popescu"
  },
  "items": [
    {
      "ticket_type": "VIP Pass",
      "event_name": "Summer Festival 2025",
      "quantity": 2,
      "unit_price": 75.00
    }
  ],
  "event": {
    "id": "evt_789",
    "name": "Summer Festival 2025",
    "date": "2025-07-15T18:00:00Z"
  }
}
```

#### Ticket Sold

```json
{
  "id": "ticket_abc",
  "ticket_number": "TKT-2025-12345",
  "ticket_type": "General Admission",
  "status": "valid",
  "price": 50.00,
  "attendee": {
    "first_name": "Maria",
    "last_name": "Ionescu",
    "email": "maria@example.com"
  },
  "event": {
    "id": "evt_789",
    "name": "Summer Festival 2025",
    "date": "2025-07-15T18:00:00Z",
    "venue": "Central Park Arena"
  },
  "order_id": "order_123"
}
```

#### Customer Created

```json
{
  "id": "cust_456",
  "email": "newcustomer@example.com",
  "first_name": "Alexandru",
  "last_name": "Dumitru",
  "phone": "+40721234567",
  "created_at": "2025-01-15T10:00:00Z",
  "source": "checkout",
  "marketing_consent": true
}
```

### Webhook Delivery

```php
class ZapierWebhookService
{
    public function fireWebhook(string $trigger, array $data): void
    {
        $subscriptions = $this->getActiveSubscriptions($trigger);

        foreach ($subscriptions as $subscription) {
            dispatch(new SendZapierWebhook(
                $subscription->hook_url,
                $data,
                $subscription->id
            ));
        }
    }
}

// In event listener
class OrderCreatedListener
{
    public function handle(OrderCreated $event): void
    {
        app(ZapierWebhookService::class)->fireWebhook(
            'order_created',
            $event->order->toZapierPayload()
        );
    }
}
```

### Database Schema

| Table | Description |
|-------|-------------|
| `zapier_connections` | API key connections |
| `zapier_triggers` | Webhook subscriptions |
| `zapier_trigger_logs` | Delivery history |
| `zapier_actions` | Incoming action requests |

### Error Handling

Failed webhook deliveries are retried:

```php
// Retry schedule
$retryDelays = [1, 5, 30]; // seconds

// After all retries fail
if ($attempts >= 3) {
    $subscription->markAsFailed();
    Log::error('Zapier webhook delivery failed', [
        'hook_id' => $subscription->id,
        'attempts' => $attempts,
    ]);
}
```

### Testing

Test your Zapier integration:

1. Create test API key in platform
2. Set up Zap in Zapier with your trigger
3. Use "Test Trigger" to send sample data
4. Verify data appears correctly in Zapier
5. Complete Zap setup with desired action

### Security

1. Validate API keys on every request
2. Use HTTPS for all webhook URLs
3. Implement rate limiting
4. Log all subscription changes
5. Monitor for unusual activity
