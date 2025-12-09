# Telegram Bot Integration

## Short Presentation

Reach millions of Telegram users with your own event bot. Telegram Bot Integration sends notifications, updates, and reminders directly to subscribers' phones. Build a direct communication channel with your audience that bypasses crowded inboxes.

Create your bot through @BotFather and connect it to your platform. Subscribers opt in by starting your bot, building an engaged audience that wants to hear from you.

Order confirmations arrive instantly. Ticket buyers receive their confirmation, QR codes, and event details right in Telegram. No email delays, no spam folder worries.

Event reminders drive attendance. Automated messages before events remind ticket holders of dates, times, and important information. Reduce no-shows with timely notifications.

Broadcast announcements reach everyone at once. New event launches, special offers, and important updates go to all subscribers simultaneously. Build anticipation and drive sales.

Inline keyboards make messages interactive. Add buttons for quick actions - view tickets, get directions, contact support. Users engage without leaving the conversation.

Channel integration amplifies your reach. Post to Telegram channels for public announcements. Build communities around your events and brands.

Subscriber management tracks your audience. See who's subscribed, segment by preferences, and measure engagement. Understand your Telegram community.

Connect directly with your audience. No algorithms, no ads, just direct communication.

---

## Detailed Description

The Telegram Bot Integration microservice connects your event ticketing platform with Telegram through the Bot API. It enables automated messaging, subscriber management, and interactive notifications.

### Bot Setup

1. Create bot via @BotFather on Telegram
2. Receive bot token
3. Configure token in platform settings
4. Set webhook URL for incoming messages
5. Start engaging subscribers

### Message Types

- **Text Messages**: Plain text with markdown formatting
- **Photos**: Event images with captions
- **Documents**: PDF tickets, invoices
- **Inline Keyboards**: Interactive button menus
- **Location**: Venue maps and directions

### Subscriber Management

When users `/start` your bot:
- User ID captured and stored
- Welcome message sent
- Preferences optionally collected
- User added to broadcast list

### Webhook Updates

Receive real-time notifications when users:
- Start the bot
- Send messages
- Click inline buttons
- Share contact information

---

## Features

### Messaging
- Text message sending
- Photo and media sharing
- Document attachments
- Markdown formatting
- Inline keyboards

### Notifications
- Order confirmations
- Event reminders
- Ticket delivery
- Broadcast announcements
- Custom notifications

### Subscriber Management
- Automatic subscription on /start
- Subscriber list management
- Preference tracking
- Unsubscribe handling

### Channel Integration
- Post to channels
- Channel management
- Public announcements
- Community building

### Interactivity
- Inline keyboard buttons
- Callback query handling
- Quick reply options
- Deep linking

---

## Use Cases

### Ticket Delivery
Send tickets directly to Telegram. QR codes display perfectly on mobile. No printing required, always accessible.

### Event Reminders
Automated reminders before events. Time, location, and what to bring. Reduce no-shows and improve experience.

### Flash Sales
Instant notifications for limited offers. Subscribers act fast on exclusive deals. Drive urgency and conversions.

### Community Building
Build engaged communities around events. Regular updates maintain interest. Turn casual buyers into loyal fans.

---

## Technical Documentation

### Configuration

```php
'telegram' => [
    'bot_token' => env('TELEGRAM_BOT_TOKEN'),
    'webhook_url' => env('APP_URL') . '/api/webhooks/telegram',
]
```

### API Endpoints

#### Send Message

```
POST /api/integrations/telegram/messages
```

**Request:**
```json
{
  "chat_id": 123456789,
  "text": "Your tickets are ready! 🎫",
  "parse_mode": "Markdown",
  "reply_markup": {
    "inline_keyboard": [[
      {"text": "View Tickets", "url": "https://..."}
    ]]
  }
}
```

#### Send Photo

```
POST /api/integrations/telegram/photos
```

#### Broadcast Message

```
POST /api/integrations/telegram/broadcast
```

#### Get Subscribers

```
GET /api/integrations/telegram/subscribers
```

### Webhook Handler

```php
POST /api/webhooks/telegram

public function handleWebhook(Request $request): void
{
    $update = $request->all();

    if (isset($update['message']['text'])) {
        if ($update['message']['text'] === '/start') {
            $this->handleStart($update['message']['from']);
        }
    }

    if (isset($update['callback_query'])) {
        $this->handleCallback($update['callback_query']);
    }
}
```

### Database Schema

| Table | Description |
|-------|-------------|
| `telegram_subscribers` | Bot subscribers |
| `telegram_messages` | Sent message log |
| `telegram_callbacks` | Callback query log |
