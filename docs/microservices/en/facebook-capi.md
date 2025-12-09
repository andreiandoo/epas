# Facebook Conversions API

## Short Presentation

Make every Facebook ad dollar count. The Facebook Conversions API sends your ticket sales directly from your servers to Meta, bypassing browser limitations that cause traditional pixels to miss conversions. Get accurate data, better optimization, and higher returns.

iOS privacy changes and ad blockers have degraded pixel tracking. Conversions API solves this by establishing a direct server-to-server connection with Facebook. When someone buys a ticket, Facebook knows - even if their browser blocks tracking scripts.

Better data means smarter campaigns. Facebook's machine learning needs accurate conversion signals to find your best customers. Server-side events provide reliable data that improves audience targeting and bid optimization.

Track the complete purchase journey. Beyond final sales, monitor Add to Cart, Initiate Checkout, and Lead events. Understand where customers drop off and optimize your funnel.

Build powerful audiences. Use your customer data to create Custom Audiences for retargeting and Lookalike Audiences to find new ticket buyers. Server-side data improves audience quality.

Event deduplication prevents double counting. If you also run the Meta Pixel on your site, the integration automatically deduplicates events to maintain accurate attribution.

Privacy-compliant by design. Customer data is hashed before transmission. Facebook receives only encrypted identifiers needed for matching - never raw personal information.

Unlock Facebook advertising's full potential. Accurate tracking transforms guesswork into data-driven decisions.

---

## Detailed Description

The Facebook Conversions API microservice provides server-side event tracking for Meta advertising platforms (Facebook, Instagram, Audience Network). It sends conversion events directly to Facebook's servers, enabling accurate attribution despite browser tracking limitations.

### Why Server-Side Tracking

Browser-based tracking faces multiple challenges:

- **Ad Blockers**: Prevent pixel scripts from loading
- **iOS 14.5+**: App Tracking Transparency limits tracking
- **Browser Privacy**: Safari ITP, Firefox ETP restrict cookies
- **Network Issues**: Pixel calls can fail silently

Server-side events bypass all these issues by sending data directly from your infrastructure to Facebook.

### Event Types

The integration tracks standard Facebook events:

- **Purchase**: Completed ticket orders
- **Lead**: User registrations and form submissions
- **CompleteRegistration**: Account creation
- **AddToCart**: Tickets added to cart
- **InitiateCheckout**: Checkout started
- **ViewContent**: Event page views

Each event includes relevant parameters for value, currency, content details, and user information.

### Attribution Matching

Facebook matches server events to users through multiple identifiers:

- **fbclid**: Facebook Click ID from ad clicks
- **fbc cookie**: First-party cookie storing fbclid
- **fbp cookie**: Facebook browser ID
- **Email**: Hashed email address
- **Phone**: Hashed phone number
- **External ID**: Your user identifier (hashed)

The more identifiers provided, the higher the match rate.

### Event Quality

Facebook assigns Event Match Quality scores (1-10) based on:

- Number of user parameters sent
- Parameter accuracy
- Real-time delivery
- Deduplication configuration

Higher quality scores indicate better attribution and optimization potential.

### Custom Audiences

Server-side data powers audience creation:

- **Website Custom Audiences**: Based on server events
- **Customer Lists**: Upload buyer data for matching
- **Lookalike Audiences**: Find similar users to your customers
- **Engagement Audiences**: Users who interacted with content

---

## Features

### Event Tracking
- Purchase conversion tracking
- Lead event tracking
- CompleteRegistration events
- AddToCart tracking
- InitiateCheckout events
- ViewContent page tracking
- Custom event support

### Attribution
- Facebook Click ID (fbclid) tracking
- Browser ID (_fbp) support
- Click ID cookie (_fbc) tracking
- Cross-device attribution
- Event deduplication with Pixel

### User Matching
- Hashed email matching
- Hashed phone matching
- External ID support
- IP address (for geo)
- User agent data
- Country and region

### Audience Integration
- Custom Audience sync
- Customer list uploads
- Lookalike generation
- Website visitor audiences
- Purchase-based audiences

### Data Quality
- Event Match Quality monitoring
- Real-time delivery
- Retry on failure
- Validation before send
- Error logging and alerts

### Privacy Compliance
- SHA-256 data hashing
- Consent verification
- Data minimization
- GDPR compatibility
- Clear data policies

---

## Use Cases

### Facebook & Instagram Ads
Optimize campaigns across Meta's ad network with accurate purchase data. Whether running News Feed, Stories, or Reels ads, ensure every ticket sale is attributed correctly.

### Advantage+ Campaigns
Feed reliable conversion data to Meta's automated campaigns. Advantage+ Shopping and App campaigns rely heavily on conversion signals for optimization. Better data means better performance.

### Retargeting Campaigns
Create audiences from website visitors and cart abandoners. Server-side events capture more users than pixel alone, expanding your retargeting pool.

### Lookalike Prospecting
Build Lookalike Audiences from your actual ticket buyers. High-quality seed audiences based on server data produce better-matching prospects.

### Performance Analysis
Understand true return on ad spend with accurate attribution. Make informed decisions about creative, audiences, and budget allocation based on real purchase data.

### Multi-Event Optimization
Optimize for different funnel stages - leads for free events, purchases for paid tickets. Multiple event types enable sophisticated campaign strategies.

---

## Technical Documentation

### Overview

The Facebook Conversions API microservice connects to Meta's Marketing API to send server-side events. It handles event collection, user data hashing, API transmission, and deduplication with browser pixels.

### Prerequisites

- Facebook Business account
- Facebook Pixel created
- Conversions API access token (System User)
- ads_management permission granted

### Configuration

```php
'facebook_capi' => [
    'pixel_id' => env('FACEBOOK_PIXEL_ID'),
    'access_token' => env('FACEBOOK_ACCESS_TOKEN'),
    'test_mode' => env('FACEBOOK_TEST_MODE', false),
    'test_event_code' => env('FACEBOOK_TEST_EVENT_CODE'),
    'events' => [
        'purchase' => true,
        'lead' => true,
        'registration' => true,
        'add_to_cart' => true,
        'initiate_checkout' => true,
    ],
]
```

### API Endpoints

#### Connection Status

```
GET /api/integrations/facebook-capi/connection
```

**Response:**
```json
{
  "connected": true,
  "pixel_id": "123456789",
  "pixel_name": "My Event Pixel",
  "last_event_time": "2025-01-15T14:30:00Z",
  "event_match_quality": 7.2
}
```

#### Create Connection

```
POST /api/integrations/facebook-capi/connect
```

**Request:**
```json
{
  "pixel_id": "123456789",
  "access_token": "your_system_user_access_token"
}
```

#### Send Event

```
POST /api/integrations/facebook-capi/events
```

**Request:**
```json
{
  "event_name": "Purchase",
  "event_time": 1705326600,
  "event_id": "order_123",
  "event_source_url": "https://yoursite.com/checkout/complete",
  "action_source": "website",
  "user_data": {
    "email": "customer@example.com",
    "phone": "+40721234567",
    "first_name": "Ion",
    "last_name": "Popescu",
    "city": "Bucharest",
    "country": "RO",
    "external_id": "user_456",
    "client_ip_address": "192.168.1.1",
    "client_user_agent": "Mozilla/5.0...",
    "fbc": "fb.1.1234567890.abcdef",
    "fbp": "fb.1.1234567890.1234567890"
  },
  "custom_data": {
    "value": 150.00,
    "currency": "EUR",
    "content_type": "product",
    "contents": [
      {
        "id": "ticket_789",
        "quantity": 2,
        "item_price": 75.00
      }
    ],
    "num_items": 2,
    "order_id": "order_123"
  }
}
```

**Response:**
```json
{
  "success": true,
  "events_received": 1,
  "fbtrace_id": "ABC123..."
}
```

#### Create Custom Audience

```
POST /api/integrations/facebook-capi/audiences
```

**Request:**
```json
{
  "name": "Ticket Buyers - Last 90 Days",
  "description": "Customers who purchased tickets in last 90 days",
  "customer_file_source": "USER_PROVIDED_ONLY"
}
```

#### Upload Audience Members

```
POST /api/integrations/facebook-capi/audiences/{id}/users
```

**Request:**
```json
{
  "schema": ["EMAIL", "PHONE", "FN", "LN"],
  "data": [
    ["a1b2c3...", "d4e5f6...", "g7h8...", "i9j0..."],
    ["k1l2m3...", "n4o5p6...", "q7r8...", "s9t0..."]
  ]
}
```

### Event Structure

```json
{
  "data": [
    {
      "event_name": "Purchase",
      "event_time": 1705326600,
      "event_id": "unique_event_id",
      "event_source_url": "https://yoursite.com/checkout",
      "action_source": "website",
      "user_data": {
        "em": ["hashed_email"],
        "ph": ["hashed_phone"],
        "fn": ["hashed_first_name"],
        "ln": ["hashed_last_name"],
        "ct": ["hashed_city"],
        "country": ["ro"],
        "external_id": ["hashed_user_id"],
        "client_ip_address": "192.168.1.1",
        "client_user_agent": "Mozilla/5.0...",
        "fbc": "fb.1.1234567890.abcdef",
        "fbp": "fb.1.1234567890.1234567890"
      },
      "custom_data": {
        "value": 150.00,
        "currency": "EUR",
        "content_type": "product",
        "contents": [...],
        "num_items": 2
      }
    }
  ]
}
```

### User Data Hashing

```php
// Hash all PII before sending
$hashedEmail = hash('sha256', strtolower(trim($email)));
$hashedPhone = hash('sha256', preg_replace('/[^0-9]/', '', $phone));
$hashedFirstName = hash('sha256', strtolower(trim($firstName)));
$hashedLastName = hash('sha256', strtolower(trim($lastName)));
$hashedCity = hash('sha256', strtolower(str_replace(' ', '', $city)));

// Country code - lowercase, not hashed
$countryCode = strtolower($country); // 'ro'
```

### Click ID Capture

```javascript
// Capture fbclid from URL
const urlParams = new URLSearchParams(window.location.search);
const fbclid = urlParams.get('fbclid');
if (fbclid) {
    // Store as _fbc cookie in Meta format
    const fbc = `fb.1.${Date.now()}.${fbclid}`;
    document.cookie = `_fbc=${fbc};max-age=7776000;path=/`;
}

// Capture _fbp (set automatically by Pixel)
const fbp = document.cookie
    .split('; ')
    .find(row => row.startsWith('_fbp='))
    ?.split('=')[1];
```

### Event Deduplication

Use the same event_id for Pixel and CAPI events:

```php
// Generate consistent event ID
$eventId = 'purchase_' . $order->id . '_' . $order->created_at->timestamp;

// Send to CAPI
$capiEvent = [
    'event_id' => $eventId,
    // ...
];

// Pixel fires with same ID
// fbq('track', 'Purchase', data, {eventID: eventId});
```

### Test Mode

Validate events without affecting campaigns:

```php
'test_mode' => true,
'test_event_code' => 'TEST12345'
```

Test events appear in Events Manager > Test Events.

### Error Handling

| Error Code | Description | Action |
|------------|-------------|--------|
| 200 | Success | Event received |
| 2200 | Invalid event time | Check timestamp |
| 2201 | Invalid action source | Use valid source |
| 2202 | Missing required param | Add missing fields |
| 190 | Invalid access token | Refresh token |

### Rate Limits

- 1,000 events per request
- 100,000 events per hour per pixel
- No daily limit

### Event Match Quality

Monitor quality in Events Manager:

| Score | Quality | Action |
|-------|---------|--------|
| 8-10 | Excellent | Maintain current setup |
| 6-7 | Good | Consider adding parameters |
| 4-5 | Fair | Add more user identifiers |
| 1-3 | Poor | Review implementation |

### Integration Example

```php
class FacebookCapiService
{
    public function trackPurchase(Order $order): void
    {
        $event = [
            'event_name' => 'Purchase',
            'event_time' => now()->timestamp,
            'event_id' => 'purchase_' . $order->id,
            'event_source_url' => url('/checkout/complete'),
            'action_source' => 'website',
            'user_data' => $this->buildUserData($order->customer),
            'custom_data' => [
                'value' => $order->total,
                'currency' => $order->currency,
                'content_type' => 'product',
                'contents' => $order->items->map(fn($item) => [
                    'id' => $item->ticket_type_id,
                    'quantity' => $item->quantity,
                    'item_price' => $item->price,
                ])->all(),
                'num_items' => $order->items->sum('quantity'),
                'order_id' => $order->id,
            ],
        ];

        dispatch(new SendFacebookEvent($event));
    }

    private function buildUserData(Customer $customer): array
    {
        return [
            'em' => [hash('sha256', strtolower($customer->email))],
            'ph' => [hash('sha256', $customer->phone)],
            'fn' => [hash('sha256', strtolower($customer->first_name))],
            'ln' => [hash('sha256', strtolower($customer->last_name))],
            'country' => [strtolower($customer->country_code)],
            'external_id' => [hash('sha256', $customer->id)],
            'fbc' => $customer->fbc_cookie,
            'fbp' => $customer->fbp_cookie,
            'client_ip_address' => request()->ip(),
            'client_user_agent' => request()->userAgent(),
        ];
    }
}
```
