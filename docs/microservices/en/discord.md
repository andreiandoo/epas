# Discord Integration

## Short Presentation

Engage your community where they already hang out. Discord Integration sends event announcements, order notifications, and updates directly to your Discord servers. Build excitement, share news, and keep fans informed in the channels they love.

Gaming events, music festivals, esports tournaments - Discord is where your audience lives. Now your ticketing platform speaks their language. Announce new events, celebrate sellouts, and build hype automatically.

Webhook messaging delivers instant updates. Configure a webhook URL and start sending. No complex bot setup required for basic notifications. Messages appear as if from your brand.

Rich embed messages look professional. Custom colors, images, thumbnails, and formatted fields create eye-catching announcements. Your messages stand out in busy channels.

Bot integration unlocks advanced features. Full Discord bot access enables channel management, user interactions, and dynamic responses. Build deeper community engagement.

Multiple server support reaches all your communities. Different events can notify different servers. Regional communities stay informed about local happenings.

Custom branding makes messages yours. Set bot username and avatar to match your brand. Every message reinforces your identity.

Message logging tracks everything sent. Know what went out, when, and to which servers. Troubleshoot delivery issues with complete history.

Turn your Discord community into ticket-buying fans. Announce, engage, sell.

---

## Detailed Description

The Discord Integration microservice connects your event ticketing platform with Discord servers, enabling automated notifications through webhooks and optional bot functionality for advanced features.

### Integration Methods

The integration supports two approaches:

#### Webhooks (Simple)
- No bot required
- Configure webhook URL in Discord channel settings
- Send messages directly to webhooks
- Limited to message sending

#### Bot Integration (Advanced)
- Full Discord API access
- Channel and server management
- User interactions
- Reaction monitoring
- Role management

### Message Types

| Notification | Content |
|--------------|---------|
| Event Announcement | Event details, dates, ticket link |
| Ticket On Sale | Sale start notification with link |
| Low Inventory | Urgency message when tickets running low |
| Sold Out | Celebratory announcement |
| Event Reminder | Upcoming event notification |
| Order Confirmation | Purchase details (private DM optional) |

### Embed Formatting

Discord embeds provide rich message formatting:

```json
{
  "title": "🎫 Summer Festival 2025",
  "description": "Tickets now on sale!",
  "color": 5814783,
  "fields": [
    {"name": "Date", "value": "July 15, 2025", "inline": true},
    {"name": "Venue", "value": "Central Park", "inline": true},
    {"name": "Tickets From", "value": "€50", "inline": true}
  ],
  "thumbnail": {"url": "https://..."},
  "image": {"url": "https://..."},
  "footer": {"text": "Get your tickets now!"}
}
```

### Server Management

With bot integration:
- List servers the bot has joined
- List channels within servers
- Create announcement channels
- Manage channel permissions
- Post to specific channels

### Delivery Tracking

All messages are logged with:
- Timestamp
- Target server/channel
- Message content
- Delivery status
- Error details (if any)

---

## Features

### Messaging
- Webhook message delivery
- Rich embed messages
- Custom embed colors
- Image and thumbnail support
- Multiple embeds per message

### Bot Features
- OAuth 2.0 bot authorization
- Server (guild) listing
- Channel listing
- Channel creation
- Permission management

### Branding
- Custom bot username
- Custom bot avatar
- Branded embed colors
- Footer customization

### Notifications
- Event announcements
- Sale notifications
- Inventory alerts
- Reminder messages
- Custom notifications

### Management
- Multiple server support
- Channel routing
- Webhook management
- Message history

### Monitoring
- Delivery logging
- Error tracking
- Message history
- Debug mode

---

## Use Cases

### Gaming Events
Esports tournaments, gaming conventions, and LAN parties thrive on Discord. Announce ticket sales where gamers already gather. Build pre-event hype in community channels.

### Music Communities
Artist fan servers get exclusive announcements. Pre-sale notifications reward loyal fans. Build direct relationships with your audience.

### Festival Promotion
Music festival communities spread the word organically. Lineup announcements create shareable moments. Fan communities amplify your reach.

### Local Event Groups
Regional Discord servers for local events. Community-driven promotion. Neighborhood event discovery.

### VIP Fan Clubs
Exclusive Discord servers for superfans. Early access announcements. Special offers for community members.

### Event Day Updates
Real-time updates during events. Set time changes, weather alerts, special announcements. Keep attendees informed.

---

## Technical Documentation

### Overview

The Discord Integration microservice connects to Discord via webhooks for simple messaging or the Discord API for full bot functionality. OAuth 2.0 handles bot authorization.

### Prerequisites

For webhooks:
- Discord server with manage webhooks permission
- Webhook URL from channel settings

For bot:
- Discord Developer Portal application
- Bot token
- OAuth2 configured with required scopes

### Configuration

```php
'discord' => [
    'client_id' => env('DISCORD_CLIENT_ID'),
    'client_secret' => env('DISCORD_CLIENT_SECRET'),
    'bot_token' => env('DISCORD_BOT_TOKEN'),
    'redirect_uri' => env('DISCORD_REDIRECT_URI'),
    'default_color' => 5814783, // Embed color
]
```

### API Endpoints

#### OAuth Authorization (Bot)

```
GET /api/integrations/discord/auth
```

Returns Discord OAuth URL for bot authorization.

#### OAuth Callback

```
POST /api/integrations/discord/callback
```

Handles OAuth callback for bot connections.

#### Connection Status

```
GET /api/integrations/discord/connection
```

**Response:**
```json
{
  "connected": true,
  "bot_name": "Event Bot",
  "guilds_count": 5,
  "webhooks_count": 3
}
```

#### List Guilds (Servers)

```
GET /api/integrations/discord/guilds
```

#### List Channels

```
GET /api/integrations/discord/guilds/{guildId}/channels
```

#### Send Webhook Message

```
POST /api/integrations/discord/webhooks/{webhookId}/messages
```

**Request:**
```json
{
  "content": "Check out our new event!",
  "embeds": [{
    "title": "🎫 Summer Festival 2025",
    "description": "The biggest event of the year!",
    "color": 5814783,
    "fields": [
      {"name": "Date", "value": "July 15, 2025", "inline": true},
      {"name": "Price", "value": "From €50", "inline": true}
    ],
    "image": {"url": "https://example.com/banner.jpg"},
    "url": "https://tickets.example.com/summer-festival"
  }],
  "username": "Event Announcements",
  "avatar_url": "https://example.com/logo.png"
}
```

#### Send Bot Message

```
POST /api/integrations/discord/channels/{channelId}/messages
```

**Request:**
```json
{
  "content": "🎉 **SOLD OUT** - Summer Festival 2025",
  "embeds": [{
    "title": "Thank You!",
    "description": "All tickets have been sold.",
    "color": 15844367
  }]
}
```

#### Create Webhook

```
POST /api/integrations/discord/channels/{channelId}/webhooks
```

**Request:**
```json
{
  "name": "Event Notifications",
  "avatar": "base64_encoded_image"
}
```

### Webhook Service

```php
class DiscordWebhookService
{
    public function sendAnnouncement(string $webhookUrl, Event $event): void
    {
        Http::post($webhookUrl, [
            'embeds' => [[
                'title' => "🎫 {$event->name}",
                'description' => $event->description,
                'color' => 5814783,
                'fields' => [
                    ['name' => 'Date', 'value' => $event->date->format('F j, Y'), 'inline' => true],
                    ['name' => 'Venue', 'value' => $event->venue->name, 'inline' => true],
                    ['name' => 'Tickets From', 'value' => "€{$event->min_price}", 'inline' => true],
                ],
                'thumbnail' => ['url' => $event->thumbnail_url],
                'image' => ['url' => $event->banner_url],
                'url' => $event->ticket_url,
                'footer' => ['text' => 'Get your tickets now!'],
            ]],
            'username' => config('discord.bot_name'),
            'avatar_url' => config('discord.bot_avatar'),
        ]);
    }
}
```

### Database Schema

| Table | Description |
|-------|-------------|
| `discord_connections` | Bot OAuth tokens |
| `discord_webhooks` | Stored webhook URLs |
| `discord_messages` | Sent message log |

### Error Handling

| Error | Description | Resolution |
|-------|-------------|------------|
| 10003 | Unknown channel | Channel deleted or bot removed |
| 10015 | Unknown webhook | Webhook deleted |
| 50001 | Missing access | Bot lacks permissions |
| 50013 | Missing permissions | Specific permission needed |

### Rate Limits

Discord rate limits:
- Webhooks: 30 requests per minute per webhook
- Bot API: 50 requests per second globally
- Message creation: 5 per 5 seconds per channel

### Embed Limits

- Title: 256 characters
- Description: 4096 characters
- Fields: 25 maximum
- Field name: 256 characters
- Field value: 1024 characters
- Total embed size: 6000 characters
