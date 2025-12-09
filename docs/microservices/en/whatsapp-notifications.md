# WhatsApp Notifications

## Short Presentation

Transform the way you communicate with your customers through WhatsApp Notifications. In a world where instant communication is key, this powerful service enables you to reach your audience directly on their most-used messaging platform.

Send automated order confirmations the moment a purchase is complete, ensuring your customers feel valued and informed. Schedule intelligent event reminders at D-7, D-3, and D-1 intervals to maximize attendance and reduce no-shows. Launch targeted promotional campaigns to boost ticket sales and engage your audience with personalized offers.

Built with compliance in mind, our service includes complete opt-in/opt-out consent management that meets GDPR requirements. The template management system with BSP approval workflow ensures all your messages are pre-approved and ready to send. Multi-provider support (360dialog, Twilio, Meta Cloud API) gives you flexibility and reliability.

Track every message with delivery and read receipts, monitor costs in real-time, and access comprehensive statistics and reporting. Whether you're organizing a concert, conference, or festival, WhatsApp Notifications helps you maintain meaningful connections with your audience throughout their entire customer journey.

---

## Detailed Description

WhatsApp Notifications is a comprehensive messaging solution designed specifically for event organizers and ticketing platforms. The service leverages the WhatsApp Business API to deliver transactional and promotional messages at scale while maintaining full compliance with messaging regulations.

### How It Works

When a customer completes a purchase, the system can automatically send an order confirmation via WhatsApp with all relevant details including ticket information, event details, and download links. The idempotent design ensures no duplicate messages are sent even if the order process is triggered multiple times.

For event reminders, the intelligent scheduling system automatically queues messages at configurable intervals before each event. The timezone-aware engine ensures reminders arrive at appropriate local times for your customers, regardless of their location.

Promotional campaigns can be segmented and targeted to specific customer groups. With dry-run mode, you can test campaigns before sending to ensure everything looks perfect. Variable templating allows personalization with customer names, event details, discount codes, and more.

### Compliance & Security

All messages require customer consent through the opt-in management system. Customers can opt-out at any time, and their preferences are immediately respected across all communication channels. The system maintains complete audit logs for regulatory compliance.

### Provider Flexibility

The adapter-based architecture supports multiple Business Solution Providers (BSPs), allowing you to choose the provider that best fits your needs or switch providers without changing your integration.

---

## Features

### Messaging Capabilities
- Order confirmation messages with idempotent delivery
- Automatic event reminders at D-7, D-3, and D-1 intervals
- Promotional campaigns with audience segmentation
- Variable templating ({first_name}, {event_name}, {discount_code}, etc.)
- Dry-run mode for testing campaigns before sending

### Consent Management
- Opt-in/opt-out consent management (GDPR compliant)
- E.164 phone number validation
- Consent source tracking (checkout, settings page, etc.)
- Complete audit trail of consent changes

### Template Management
- Template management with BSP approval workflow
- Support for multiple template categories (order_confirm, reminder, promo, otp)
- Multi-language template support
- Real-time template status tracking

### Provider Integration
- Multi-BSP support (360dialog, Twilio, Meta Cloud API)
- Provider-agnostic adapter pattern
- Automatic failover capabilities
- Rate limiting and throttling

### Tracking & Analytics
- Delivery receipts and read receipts
- Cost tracking and balance deduction
- Comprehensive statistics and reporting
- Message history and audit logs
- Webhook integration for status updates

### Scheduling
- Timezone-aware reminder scheduling
- Configurable reminder intervals
- Bulk scheduling for events
- Schedule management and cancellation

---

## Use Cases

### Concert & Festival Organizers
Send instant ticket confirmations and build excitement with countdown reminders. Promote last-minute ticket availability or special VIP upgrades to your engaged audience.

### Conference & Business Events
Keep attendees informed with session reminders, schedule changes, and networking opportunities. Share presenter information and venue details directly to their phones.

### Theater & Performing Arts
Remind patrons of upcoming shows, share pre-show information, and promote subscription packages or upcoming performances.

### Sports Events
Send match-day reminders with parking information, gate opening times, and exclusive merchandise offers to ticket holders.

### Recurring Events
Build customer loyalty by notifying past attendees about upcoming editions and offering early-bird discounts.

### Emergency Communications
Quickly notify all ticket holders about venue changes, event postponements, or important safety information.

---

## Technical Documentation

### Overview

The WhatsApp Notifications microservice provides a complete messaging solution for event ticketing platforms. It handles the full lifecycle of WhatsApp messaging including consent management, template administration, message delivery, and analytics.

### Architecture

The service follows an adapter-based architecture that abstracts BSP-specific implementations behind a common interface. This allows seamless integration with multiple providers (360dialog, Twilio, Meta Cloud API) while maintaining a consistent API.

### Database Schema

| Table | Description |
|-------|-------------|
| `wa_optin` | Customer consent records with opt-in/opt-out status |
| `wa_templates` | Message templates with approval status |
| `wa_messages` | Sent message records with delivery status |
| `wa_schedules` | Scheduled messages for future delivery |

### API Endpoints

#### Template Management
```
POST /api/wa/templates
```
Create or update a message template for BSP approval.

#### Send Messages
```
POST /api/wa/send/confirm
```
Send order confirmation message (idempotent).

```
POST /api/wa/send/promo
```
Send promotional message to opted-in customers.

#### Schedule Reminders
```
POST /api/wa/schedule/reminders
```
Schedule event reminder messages at configured intervals.

#### Webhooks
```
POST /api/wa/webhook
```
Receive delivery status updates from BSP.

#### Opt-in Management
```
POST /api/wa/optin
```
Record customer opt-in or opt-out preference.

#### Statistics
```
GET /api/wa/stats/{tenantId}
```
Retrieve messaging statistics and analytics.

```
GET /api/wa/messages/{tenantId}
```
List sent messages with status and cost information.

```
GET /api/wa/schedules/{tenantId}
```
List scheduled messages pending delivery.

### Message Types

| Type | Description |
|------|-------------|
| `order_confirm` | Order and ticket confirmation |
| `reminder` | Event reminder (D-7, D-3, D-1) |
| `promo` | Promotional campaign message |
| `otp` | One-time password verification |

### Configuration

```php
'whatsapp' => [
    'default_provider' => env('WHATSAPP_PROVIDER', 'twilio'),
    'reminder_intervals' => ['D-7', 'D-3', 'D-1'],
    'rate_limit' => [
        'per_minute' => 100,
        'per_hour' => 1000,
    ],
    'cost_tracking' => true,
]
```

### Integration Example

```php
use App\Services\WhatsApp\WhatsAppService;

// Send order confirmation
$whatsapp = app(WhatsAppService::class);
$whatsapp->sendOrderConfirmation($order, $customer);

// Schedule event reminders
$whatsapp->scheduleReminders($event, $attendees);

// Send promotional campaign
$whatsapp->sendPromo($campaign, $segment);
```

### Error Handling

The service implements exponential backoff for failed deliveries and maintains detailed error logs. Failed messages are automatically retried up to the configured maximum attempts before being moved to a dead-letter queue.

### Metrics

The service tracks the following metrics:
- Messages sent/delivered/read per tenant
- Cost per message and total spend
- Opt-in/opt-out rates
- Template approval rates
- Delivery success rates
