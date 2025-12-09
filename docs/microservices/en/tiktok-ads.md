# TikTok Ads Integration

## Short Presentation

Reach the next generation of event-goers on TikTok. With over a billion active users, TikTok has become a discovery platform where people find their next concert, festival, or experience. Now you can track exactly how TikTok ads drive ticket sales.

TikTok Ads Integration sends server-side events directly to TikTok when customers buy tickets. No more guessing which videos and audiences convert - you'll know. This data feeds TikTok's algorithm, helping it find more people likely to buy.

Server-side tracking means reliable data. Browser pixels get blocked by privacy tools, but server-side events reach TikTok directly. Every purchase counts toward your campaign optimization.

Track the entire journey. From the first video view to the final purchase, monitor Add to Cart, Initiate Checkout, and Complete Payment events. Understand where prospects drop off and optimize accordingly.

Build audiences from real buyers. Upload customer lists to TikTok and create lookalike audiences. Find people similar to your ticket buyers across TikTok's massive user base.

Privacy-first design protects customer data. All personal information is hashed before leaving your servers. TikTok receives only encrypted identifiers for matching purposes.

Event deduplication ensures accurate counting. If you also use TikTok Pixel on your site, the integration automatically prevents double-counting conversions.

Turn TikTok's viral potential into ticket sales. Track, optimize, and scale your campaigns with confidence.

---

## Detailed Description

The TikTok Ads Integration microservice provides server-side conversion tracking through TikTok's Events API. It sends purchase and engagement events from your servers directly to TikTok, enabling accurate attribution and campaign optimization.

### Server-Side Advantage

Unlike browser-based pixels that can be blocked by ad blockers, browser privacy features, or iOS App Tracking Transparency, server-side events bypass these limitations. Your conversion data reaches TikTok reliably, giving the algorithm accurate signals for optimization.

### Event Types

The integration tracks key events in the purchase funnel:

- **ViewContent**: When users view event pages
- **AddToCart**: When tickets are added to cart
- **InitiateCheckout**: When checkout begins
- **CompletePayment**: When purchase is finalized
- **CompleteRegistration**: When users sign up

Each event includes relevant parameters like content type, value, and currency.

### TikTok Click ID Tracking

When users click TikTok ads, they arrive with a `ttclid` parameter. The integration:

1. Captures the ttclid from the URL
2. Stores it in a first-party cookie
3. Associates it with the user's session
4. Includes it in conversion events

This enables TikTok to attribute conversions back to specific ad clicks.

### TikTok Cookie (_ttp)

The integration also tracks TikTok's first-party cookie (`_ttp`), which provides additional matching signals. Combined with ttclid and hashed user data, this maximizes attribution accuracy.

### Custom Audiences

Create audiences based on your customer data:

- Upload buyer lists for retargeting
- Build lookalike audiences from your best customers
- Exclude existing ticket holders from prospecting
- Segment by event type, purchase frequency, or value

### Batch Processing

Events are queued and sent in batches for efficiency:

- Up to 1,000 events per batch
- Automatic retry on failures
- Rate limit compliance (50,000 events/day)
- Background processing for performance

---

## Features

### Event Tracking
- CompletePayment (purchase) tracking
- AddToCart events
- InitiateCheckout events
- CompleteRegistration events
- ViewContent page views
- Custom event support

### Attribution
- TikTok Click ID (ttclid) tracking
- First-party cookie (_ttp) support
- Cross-device attribution
- Event deduplication
- 28-day attribution window

### Privacy & Security
- SHA-256 user data hashing
- Server-side data transmission
- GDPR consent verification
- Minimal data collection
- Secure token storage

### Audience Management
- Custom audience creation
- Customer list uploads
- Lookalike audience building
- Audience sync automation
- Exclusion list support

### Monitoring
- Real-time event status
- Test mode validation
- Error logging
- API response tracking
- Event delivery confirmation

### Campaign Integration
- Value-based optimization
- Content ID tracking
- Product catalog support
- Conversion deduplication
- Multi-pixel support

---

## Use Cases

### Concert Promotion
Promote upcoming concerts to music fans on TikTok. Track which video styles and sounds drive ticket purchases. Scale winning creatives and retire underperformers.

### Festival Marketing
Reach festival audiences through creator content and paid ads. Measure true ROI by tracking purchases, not just engagement. Build lookalike audiences from past attendees.

### Event Discovery
TikTok users actively seek experiences to attend. Position your events in front of discovery-minded audiences. Track which event types resonate with TikTok demographics.

### Youth-Focused Events
Reach Gen Z audiences where they spend their time. TikTok's younger demographic is perfect for concerts, festivals, and entertainment events. Track conversions accurately despite iOS privacy changes.

### Retargeting Campaigns
Bring back users who viewed events but didn't buy. Create audiences from website visitors and cart abandoners. Serve personalized ads based on their interests.

### Influencer Attribution
When creators promote your events, track resulting ticket sales. Understand which influencer partnerships drive actual revenue, not just views.

---

## Technical Documentation

### Overview

The TikTok Ads Integration microservice connects to TikTok Events API v1.3 for server-side conversion tracking. It handles event collection, user data hashing, batch uploads, and audience management.

### Prerequisites

- TikTok For Business account
- TikTok Ads Manager access
- TikTok Pixel created
- Events API access token generated

### Configuration

```php
'tiktok_ads' => [
    'pixel_id' => env('TIKTOK_PIXEL_ID'),
    'access_token' => env('TIKTOK_ACCESS_TOKEN'),
    'advertiser_id' => env('TIKTOK_ADVERTISER_ID'),
    'test_mode' => env('TIKTOK_TEST_MODE', false),
    'test_event_code' => env('TIKTOK_TEST_EVENT_CODE'),
    'events' => [
        'track_purchases' => true,
        'track_add_to_cart' => true,
        'track_checkout' => true,
        'track_registrations' => true,
    ],
]
```

### API Endpoints

#### Connection Status

```
GET /api/integrations/tiktok-ads/connection
```

**Response:**
```json
{
  "connected": true,
  "pixel_id": "CXXXXXX",
  "advertiser_id": "123456789",
  "test_mode": false,
  "last_event_sent": "2025-01-15T14:30:00Z"
}
```

#### Create Connection

```
POST /api/integrations/tiktok-ads/connect
```

**Request:**
```json
{
  "pixel_id": "CXXXXXX",
  "access_token": "your_access_token",
  "advertiser_id": "123456789"
}
```

#### Send Event

```
POST /api/integrations/tiktok-ads/events
```

**Request:**
```json
{
  "event_type": "CompletePayment",
  "event_time": 1705326600,
  "event_id": "order_123",
  "user": {
    "email": "customer@example.com",
    "phone": "+40721234567",
    "external_id": "user_456"
  },
  "properties": {
    "value": 150.00,
    "currency": "EUR",
    "content_type": "product",
    "contents": [
      {
        "content_id": "ticket_789",
        "content_name": "Concert VIP Pass",
        "quantity": 2,
        "price": 75.00
      }
    ]
  },
  "context": {
    "page_url": "https://yoursite.com/checkout/complete",
    "user_agent": "Mozilla/5.0...",
    "ip": "192.168.1.1"
  }
}
```

**Response:**
```json
{
  "success": true,
  "event_id": "order_123",
  "code": 0,
  "message": "OK"
}
```

#### Batch Events

```
POST /api/integrations/tiktok-ads/events/batch
```

Sends multiple events in a single request (up to 1,000 events).

#### Create Audience

```
POST /api/integrations/tiktok-ads/audiences
```

**Request:**
```json
{
  "advertiser_id": "123456789",
  "custom_audience_name": "Past Ticket Buyers",
  "audience_type": "CUSTOMER_FILE"
}
```

#### Sync Audience

```
POST /api/integrations/tiktok-ads/audiences/{id}/sync
```

**Request:**
```json
{
  "action": "APPEND",
  "id_schema": ["EMAIL_SHA256", "PHONE_SHA256"],
  "members": [
    ["a1b2c3...", "d4e5f6..."],
    ["g7h8i9...", "j0k1l2..."]
  ]
}
```

### Event Structure

```json
{
  "pixel_code": "CXXXXXX",
  "event": "CompletePayment",
  "event_id": "unique_event_id",
  "timestamp": "2025-01-15T14:30:00Z",
  "context": {
    "ad": {
      "callback": "ttclid_value"
    },
    "page": {
      "url": "https://yoursite.com/checkout"
    },
    "user": {
      "external_id": "hashed_user_id",
      "email": "hashed_email",
      "phone_number": "hashed_phone"
    },
    "user_agent": "Mozilla/5.0...",
    "ip": "192.168.1.1"
  },
  "properties": {
    "contents": [...],
    "currency": "EUR",
    "value": 150.00
  }
}
```

### User Data Hashing

```php
// All user data must be hashed with SHA-256
$hashedEmail = hash('sha256', strtolower(trim($email)));

// Phone in E.164 format before hashing
$phone = preg_replace('/[^0-9]/', '', $phone);
$hashedPhone = hash('sha256', $phone);

// External ID (your user ID)
$hashedExternalId = hash('sha256', $userId);
```

### Event Deduplication

Use consistent event_id values:

```php
// For purchases
$eventId = 'purchase_' . $order->id;

// For cart events
$eventId = 'cart_' . $session->id . '_' . time();
```

TikTok deduplicates events with matching event_id within 48 hours.

### Click ID Capture

```javascript
// Capture ttclid from URL on page load
const urlParams = new URLSearchParams(window.location.search);
const ttclid = urlParams.get('ttclid');
if (ttclid) {
    document.cookie = `ttclid=${ttclid};max-age=2592000;path=/`;
}

// Also capture _ttp cookie for additional matching
const ttpCookie = document.cookie
    .split('; ')
    .find(row => row.startsWith('_ttp='))
    ?.split('=')[1];
```

### Test Mode

Enable test mode to validate without affecting campaigns:

```php
'test_mode' => true,
'test_event_code' => 'TEST12345'
```

Test events appear in TikTok Events Manager under "Test Events".

### Error Handling

| Code | Description | Action |
|------|-------------|--------|
| 0 | Success | Event accepted |
| 40001 | Invalid pixel | Check pixel_id |
| 40002 | Invalid event | Verify event structure |
| 40003 | Invalid user data | Check hashing format |
| 40100 | Rate limit exceeded | Implement backoff |

### Rate Limits

- 50,000 events per day per pixel
- 1,000 events per batch request
- 10 requests per second

### Integration Example

```php
class TikTokEventService
{
    public function trackPurchase(Order $order): void
    {
        $event = [
            'event_type' => 'CompletePayment',
            'event_id' => 'purchase_' . $order->id,
            'event_time' => now()->timestamp,
            'user' => $this->hashUserData($order->customer),
            'properties' => [
                'value' => $order->total,
                'currency' => $order->currency,
                'contents' => $order->items->map(fn($item) => [
                    'content_id' => $item->ticket_type_id,
                    'content_name' => $item->ticket_type_name,
                    'quantity' => $item->quantity,
                    'price' => $item->price,
                ])->all(),
            ],
            'context' => [
                'ttclid' => $order->ttclid,
                'ttp' => $order->ttp_cookie,
            ],
        ];

        dispatch(new SendTikTokEvent($event));
    }
}
```
