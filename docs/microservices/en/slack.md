# Slack Integration

## Short Presentation

Keep your team in the loop without leaving Slack. Slack Integration sends real-time notifications about orders, customers, and events directly to your workspace channels. Your team stays informed, responds faster, and never misses important updates.

New order comes in? Your sales channel knows instantly. VIP customer makes a purchase? Alert the right people. Event sells out? Celebrate together. Slack Integration turns your ticketing platform into a team communication hub.

Rich message formatting makes notifications actionable. See order details, customer information, and quick links without clicking away. Block Kit formatting presents data beautifully in Slack's native style.

Send to any channel. Route different notifications to different channels - sales alerts to #sales, support issues to #support, milestone celebrations to #general. You control where each message goes.

Files and attachments keep everyone informed. Share reports, export data, and send documents directly through Slack. No more hunting through emails for that sales report.

Multiple workspace support scales with your organization. Connect different teams, departments, or brands to their own Slack workspaces. Each connection is independent and secure.

OAuth 2.0 makes connection simple and secure. Click to authorize, select your workspace, and start receiving notifications. No manual token management required.

Transform Slack into your event operations dashboard. Real-time awareness for your entire team.

---

## Detailed Description

The Slack Integration microservice connects your event ticketing platform with Slack workspaces, enabling automated notifications, file sharing, and team communication through Slack's API.

### Notification Types

The integration sends various notifications:

| Event | Message Content |
|-------|-----------------|
| New Order | Order details, customer, items, total |
| VIP Purchase | High-value order alert with details |
| Refund Issued | Refund amount, reason, customer |
| Event Published | Event details, ticket link |
| Low Inventory | Warning when tickets running low |
| Daily Summary | Sales recap, attendance numbers |

### Message Formatting

Messages use Slack's Block Kit for rich formatting:

- **Headers**: Clear notification titles
- **Sections**: Organized content blocks
- **Fields**: Key-value data pairs
- **Buttons**: Quick action links
- **Dividers**: Visual separation

Example message structure:
```
🎫 New Order #1234
─────────────────
Customer: Ion Popescu
Event: Summer Festival 2025
Tickets: 2x VIP Pass
Total: €150.00

[View Order] [Contact Customer]
```

### Channel Management

Configure which notifications go where:

- Create channel mappings in your dashboard
- Route by notification type
- Route by event or organizer
- Support private channels with bot membership

### File Uploads

Share files directly to Slack:

- Daily/weekly sales reports
- Customer export lists
- Event attendance data
- Custom generated documents

Files upload asynchronously and appear in the designated channel.

### Webhook Support

Receive Slack events in your platform:

- Message reactions for quick feedback
- Slash commands for queries
- Interactive button responses
- Modal submissions

---

## Features

### Messaging
- Send messages to any channel
- Rich message formatting with blocks
- Thread replies support
- Emoji reactions
- Message editing and deletion

### Notifications
- Order notifications
- Customer alerts
- Event updates
- Inventory warnings
- Custom notifications

### File Sharing
- File uploads to channels
- Document sharing
- Report distribution
- Image attachments

### Channel Management
- List available channels
- Create new channels
- Channel routing rules
- Private channel support

### Authentication
- OAuth 2.0 secure connection
- Multiple workspace support
- Automatic token refresh
- Permission scoping

### Monitoring
- Message delivery logging
- Webhook event tracking
- Error notifications
- Activity history

---

## Use Cases

### Sales Alerts
Instant notifications when orders come in. High-value purchases ping the sales team. Daily summaries keep everyone aligned on performance.

### Operations Coordination
Event day updates in real-time. Ticket scan counts, attendance alerts, and capacity warnings help operations teams respond quickly.

### Customer Service
Refund notifications alert support teams. Customer issues flagged in dedicated channels. Response coordination happens naturally in Slack.

### Executive Visibility
Summary reports to leadership channels. Milestone celebrations shared company-wide. Revenue updates without dashboard checking.

### Multi-Team Coordination
Marketing gets event publish notifications. Finance sees daily revenue summaries. Each team gets relevant information in their channels.

### Remote Team Management
Distributed teams stay connected. Real-time updates regardless of location. Asynchronous awareness through persistent messages.

---

## Technical Documentation

### Overview

The Slack Integration microservice uses Slack's Web API and Events API to send messages, upload files, and receive webhook events. OAuth 2.0 handles workspace authorization.

### Prerequisites

- Slack workspace
- Slack App created in api.slack.com
- Bot Token Scopes configured
- Redirect URL for OAuth

### Configuration

```php
'slack' => [
    'client_id' => env('SLACK_CLIENT_ID'),
    'client_secret' => env('SLACK_CLIENT_SECRET'),
    'redirect_uri' => env('SLACK_REDIRECT_URI'),
    'signing_secret' => env('SLACK_SIGNING_SECRET'),
    'scopes' => [
        'chat:write',
        'channels:read',
        'files:write',
        'reactions:write',
    ],
]
```

### API Endpoints

#### OAuth Authorization

```
GET /api/integrations/slack/auth
```

Returns Slack OAuth authorization URL.

#### OAuth Callback

```
POST /api/integrations/slack/callback
```

Handles OAuth callback and stores tokens.

#### Connection Status

```
GET /api/integrations/slack/connection
```

**Response:**
```json
{
  "connected": true,
  "workspace": "Your Company",
  "team_id": "T1234567",
  "bot_user_id": "U7654321",
  "channels_count": 15
}
```

#### Send Message

```
POST /api/integrations/slack/messages
```

**Request:**
```json
{
  "channel": "C1234567890",
  "text": "New order received!",
  "blocks": [
    {
      "type": "header",
      "text": {
        "type": "plain_text",
        "text": "🎫 New Order #1234"
      }
    },
    {
      "type": "section",
      "fields": [
        {"type": "mrkdwn", "text": "*Customer:*\nIon Popescu"},
        {"type": "mrkdwn", "text": "*Total:*\n€150.00"}
      ]
    }
  ]
}
```

#### List Channels

```
GET /api/integrations/slack/channels
```

#### Upload File

```
POST /api/integrations/slack/files
```

**Request (multipart/form-data):**
```
file: [binary]
channels: C1234567890
filename: daily-report.pdf
title: Daily Sales Report
```

#### Add Reaction

```
POST /api/integrations/slack/reactions
```

**Request:**
```json
{
  "channel": "C1234567890",
  "timestamp": "1234567890.123456",
  "name": "white_check_mark"
}
```

### Message Building

```php
class SlackMessageBuilder
{
    public function orderNotification(Order $order): array
    {
        return [
            'blocks' => [
                [
                    'type' => 'header',
                    'text' => [
                        'type' => 'plain_text',
                        'text' => "🎫 New Order #{$order->number}",
                    ],
                ],
                [
                    'type' => 'section',
                    'fields' => [
                        ['type' => 'mrkdwn', 'text' => "*Customer:*\n{$order->customer->name}"],
                        ['type' => 'mrkdwn', 'text' => "*Total:*\n€{$order->total}"],
                        ['type' => 'mrkdwn', 'text' => "*Event:*\n{$order->event->name}"],
                        ['type' => 'mrkdwn', 'text' => "*Tickets:*\n{$order->items->count()}"],
                    ],
                ],
                [
                    'type' => 'actions',
                    'elements' => [
                        [
                            'type' => 'button',
                            'text' => ['type' => 'plain_text', 'text' => 'View Order'],
                            'url' => route('orders.show', $order),
                        ],
                    ],
                ],
            ],
        ];
    }
}
```

### Channel Routing

```php
// Configuration
'slack_routing' => [
    'order_created' => ['#sales', '#orders'],
    'refund_issued' => ['#support', '#finance'],
    'event_published' => ['#marketing'],
    'vip_purchase' => ['#vip-alerts'],
]
```

### Webhook Handler

```php
// Receive Slack events
POST /api/webhooks/slack

public function handleWebhook(Request $request): Response
{
    // Verify signature
    $this->verifySlackSignature($request);

    $payload = $request->input();

    // Handle URL verification challenge
    if ($payload['type'] === 'url_verification') {
        return response($payload['challenge']);
    }

    // Handle events
    if ($payload['type'] === 'event_callback') {
        $this->processEvent($payload['event']);
    }

    return response('OK');
}
```

### Database Schema

| Table | Description |
|-------|-------------|
| `slack_connections` | OAuth tokens and workspace info |
| `slack_channels` | Cached channel list |
| `slack_messages` | Sent message log |
| `slack_webhooks` | Incoming webhook events |

### Error Handling

| Error | Description | Resolution |
|-------|-------------|------------|
| channel_not_found | Invalid channel ID | Verify channel exists |
| not_in_channel | Bot not in channel | Invite bot to channel |
| token_revoked | OAuth token invalid | Re-authorize connection |
| rate_limited | Too many requests | Implement backoff |

### Rate Limits

Slack API limits:
- Tier 1: 1 request per second
- Tier 2: 20 requests per minute
- Tier 3: 50 requests per minute

Most messaging endpoints are Tier 3.
