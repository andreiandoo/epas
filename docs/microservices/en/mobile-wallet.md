# Mobile Wallet Passes

## Short Presentation

Transform your customers' smartphones into ticket holders with Mobile Wallet Passes. In today's mobile-first world, nobody wants to print tickets or search through emails at venue entry. Give your customers the convenience they expect.

With a single tap, attendees can add their event tickets to Apple Wallet or Google Pay. The tickets are always accessible, even offline, right from their phone's home screen. No app download required, no account creation needed - just pure convenience.

But it's not just about storage. Mobile wallet passes are smart. When event details change - new venue, updated time, or schedule shift - passes update automatically on customers' devices. Send push notifications directly to the pass reminding them about the event, parking tips, or special offers.

Location-based reminders add another layer of magic. As customers approach the venue, their phone can remind them about their ticket. The barcode or QR code on the pass integrates seamlessly with your existing ticket scanners.

For event organizers, the benefits multiply. Track engagement with pass analytics, manage passes in batch for large events, and maintain your brand presence with custom-designed passes featuring your logo, colors, and imagery.

Whether it's a single concert ticket or a full festival pass, Mobile Wallet brings your tickets into the modern era. Give your customers an experience worthy of their event.

---

## Detailed Description

Mobile Wallet Passes is a comprehensive service for generating and managing digital tickets compatible with Apple Wallet and Google Pay. The service transforms traditional PDF or email tickets into dynamic, interactive wallet passes that live on customers' mobile devices.

### Platform Support

**Apple Wallet**: Generates .pkpass files compatible with iOS devices. Passes appear in the native Wallet app and support real-time updates, push notifications, and location-based reminders.

**Google Pay**: Creates JWT-based passes for Android devices. Integrates with Google Pay app and supports similar update and notification capabilities.

### Pass Lifecycle

1. **Generation**: When a ticket is purchased, a wallet pass is automatically generated with event details, attendee information, and a unique barcode.

2. **Delivery**: Passes are sent via email with an "Add to Wallet" button, or can be added directly from the ticket confirmation page.

3. **Updates**: When event details change, the service pushes updates to all issued passes. Customers see the new information without any action required.

4. **Check-in**: The barcode on the pass is scanned at entry, just like a traditional ticket. The pass can be marked as "used" to prevent re-entry.

5. **Expiration**: After the event, passes can be automatically archived or expired based on your configuration.

### Customization

Passes can be fully branded with your organization's visual identity:
- Logo and banner images
- Custom color schemes
- Event-specific imagery
- Localized content in multiple languages

---

## Features

### Pass Generation
- Apple Wallet (.pkpass) generation
- Google Pay pass generation
- Custom pass design with branding
- Barcode/QR code generation
- Multiple ticket types per pass
- Batch pass generation

### Real-Time Updates
- Push event detail changes to passes
- Time and venue updates
- Push notifications to wallet
- Location-based reminders
- Pass expiration management

### Delivery & Access
- Automatic pass delivery via email
- Direct add-to-wallet links
- Support for event series passes
- Offline access for customers

### Integration
- Integration with ticket scanner
- Check-in status sync
- Order system integration
- Event management sync

### Analytics
- Pass analytics and engagement tracking
- Add-to-wallet conversion rates
- Update delivery tracking
- Engagement metrics

---

## Use Cases

### Concert Tickets
Replace paper tickets with sleek digital passes. Fans show their phone at entry, and the excitement starts before the first note.

### Festival Multi-Day Passes
Issue a single pass that works across all festival days. Updates keep attendees informed about schedule changes and special announcements.

### Conference Badges
Digital badges with attendee information, session access, and networking features all in one wallet pass.

### Sporting Events
Season ticket holders get passes that update for each home game with date, opponent, and seat information.

### Theater & Shows
Elegant passes that reflect the sophistication of the performance, with curtain time reminders and venue information.

### Transportation & Parking
Combine event admission with parking passes, all in one convenient wallet location.

---

## Technical Documentation

### Overview

The Mobile Wallet Passes microservice generates and manages Apple Wallet (.pkpass) and Google Pay digital tickets. It handles pass creation, updates, notifications, and analytics.

### Architecture

```
Order Confirmed → Wallet Service → Pass Generator → Apple/Google APIs
                        ↓
                  Update Manager → Push Notifications
                        ↓
                  Analytics Tracker
```

### Database Schema

| Table | Description |
|-------|-------------|
| `wallet_passes` | Generated pass records |
| `wallet_pass_updates` | Update history and status |

### Pass Structure (Apple Wallet)

```json
{
  "formatVersion": 1,
  "passTypeIdentifier": "pass.com.example.event",
  "serialNumber": "ticket_abc123",
  "teamIdentifier": "TEAM_ID",
  "organizationName": "Event Organizer",
  "description": "Event Ticket",
  "eventTicket": {
    "primaryFields": [
      {"key": "event", "label": "EVENT", "value": "Summer Concert"}
    ],
    "secondaryFields": [
      {"key": "date", "label": "DATE", "value": "July 15, 2025"}
    ],
    "auxiliaryFields": [
      {"key": "seat", "label": "SEAT", "value": "A-15"}
    ],
    "backFields": [
      {"key": "terms", "label": "Terms", "value": "..."}
    ]
  },
  "barcode": {
    "format": "PKBarcodeFormatQR",
    "message": "TICKET-ABC123",
    "messageEncoding": "iso-8859-1"
  },
  "locations": [
    {"latitude": 44.4268, "longitude": 26.1025}
  ],
  "relevantDate": "2025-07-15T18:00:00+03:00"
}
```

### API Endpoints

#### Generate Pass
```
POST /api/wallet/generate/{ticketId}
```
Generate wallet pass for a ticket.

**Response:**
```json
{
  "pass_id": "pass_abc123",
  "apple_url": "https://example.com/wallet/apple/pass_abc123",
  "google_url": "https://example.com/wallet/google/pass_abc123",
  "created_at": "2025-01-15T10:00:00Z"
}
```

#### Get Pass
```
GET /api/wallet/pass/{passId}
```
Retrieve pass details and download URLs.

#### Update Pass
```
POST /api/wallet/update/{passId}
```
Push updates to an existing pass.

#### Send Notification
```
POST /api/wallet/notify/{passId}
```
Send push notification to pass holder.

#### Get Analytics
```
GET /api/wallet/analytics/{tenantId}
```
Retrieve pass engagement analytics.

### Configuration

```php
'wallet' => [
    'apple' => [
        'pass_type_identifier' => env('APPLE_PASS_TYPE_ID'),
        'team_identifier' => env('APPLE_TEAM_ID'),
        'certificate_path' => env('APPLE_CERT_PATH'),
        'certificate_password' => env('APPLE_CERT_PASSWORD'),
    ],
    'google' => [
        'issuer_id' => env('GOOGLE_WALLET_ISSUER_ID'),
        'service_account_file' => env('GOOGLE_SERVICE_ACCOUNT'),
    ],
    'default_images' => [
        'logo' => 'wallet/default-logo.png',
        'strip' => 'wallet/default-strip.png',
    ],
]
```

### Integration Example

```php
use App\Services\Wallet\WalletService;

// Generate pass for ticket
$wallet = app(WalletService::class);
$pass = $wallet->generatePass($ticket);

// Get download URLs
$appleUrl = $pass->getAppleWalletUrl();
$googleUrl = $pass->getGooglePayUrl();

// Push update when event changes
$wallet->updatePass($pass->id, [
    'event_time' => '19:00',
    'venue' => 'Updated Venue Name',
]);

// Send notification
$wallet->sendNotification($pass->id, 'Event starts in 1 hour!');
```

### Pass Update Flow

1. Event details change in system
2. Wallet service identifies affected passes
3. Update payload is generated
4. Push notification sent to Apple/Google
5. Device pulls new pass data
6. Customer sees updated information

### Supported Platforms

| Platform | Format | Features |
|----------|--------|----------|
| Apple Wallet | .pkpass (PKCS#7) | Updates, notifications, location |
| Google Pay | JWT | Updates, notifications |

### Metrics

Track pass performance:
- Passes generated per event
- Add-to-wallet conversion rate
- Update delivery success rate
- Notification engagement rate
- Check-in rate from wallet passes
