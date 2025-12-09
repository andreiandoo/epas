# Google Ads Integration

## Short Presentation

Stop guessing which ads sell tickets. Google Ads Integration connects your ticket sales directly to your advertising campaigns, showing you exactly which keywords, ads, and audiences generate revenue.

Every ticket purchase becomes a conversion you can track. When someone clicks your ad and buys tickets, Google Ads knows. This data flows back to your campaigns, enabling smart bidding algorithms to find more buyers like your best customers.

Enhanced conversions take attribution further. By securely sharing hashed customer data, you help Google match more purchases to ad clicks - even when cookies fail. Better attribution means smarter optimization.

Build audiences from your customer base. Upload buyer lists to Google Ads and find similar people across Search, YouTube, and Display. Target past attendees for upcoming events or exclude existing ticket holders to focus on new audiences.

Server-side tracking means reliable data. Unlike browser-based pixels that ad blockers can stop, our integration sends conversion data directly from your servers. Every sale gets counted accurately.

Real-time conversion status lets you monitor what's happening. See which conversions were sent, matched, and attributed. Troubleshoot issues before they affect campaign performance.

Test mode validates your setup before going live. Send test conversions and verify they appear correctly in Google Ads before enabling production tracking.

Turn your Google Ads investment into measurable ticket sales. Know your true return on ad spend and scale what works.

---

## Detailed Description

The Google Ads Integration microservice provides server-side conversion tracking and audience management for your Google Ads campaigns. It automatically sends purchase and lead events to Google, enabling accurate attribution and campaign optimization.

### How It Works

When a customer completes a ticket purchase:

1. The system captures the transaction details and any Google click identifiers (GCLID, GBRAID, WBRAID)
2. Customer data is hashed using SHA-256 for privacy
3. A conversion event is sent to Google Ads via the API
4. Google matches the conversion to the original ad click
5. The attribution data improves your campaign optimization

### Click Identifier Tracking

The integration tracks multiple Google click identifiers:

- **GCLID**: Standard Google Click ID from Search and Shopping ads
- **GBRAID**: App-to-web measurement for iOS users
- **WBRAID**: Web-to-app measurement identifier

These identifiers are captured when users arrive from Google Ads and stored throughout their purchase journey.

### Enhanced Conversions

Enhanced conversions improve attribution by sending hashed customer data:
- Email address (SHA-256 hashed)
- Phone number (SHA-256 hashed)
- Name and address (SHA-256 hashed)

Google uses this data to match conversions when click identifiers aren't available, significantly improving attribution accuracy.

### Conversion Actions

The integration supports multiple conversion types:

- **Purchase**: When a customer completes a ticket order
- **Lead**: When someone registers or submits a form
- **Add to Cart**: When tickets are added to cart
- **Begin Checkout**: When checkout is initiated

Each conversion includes value data for revenue tracking and value-based bidding.

### Audience Sync

Customer Match integration lets you:
- Upload customer lists to Google Ads
- Create audiences from past buyers
- Build lookalike audiences to find new customers
- Exclude existing customers from campaigns

Lists sync automatically based on your configuration, keeping audiences fresh.

### Attribution Windows

Conversions are attributed based on Google Ads' attribution settings:
- Click-through conversions: Up to 90 days
- View-through conversions: Up to 30 days
- Attribution model: Data-driven or rules-based

---

## Features

### Conversion Tracking
- Automatic purchase conversion tracking
- Lead and registration tracking
- Add to cart events
- Begin checkout events
- Custom conversion actions
- Conversion value tracking

### Enhanced Conversions
- Hashed email matching
- Hashed phone matching
- First-party data enhancement
- Cross-device attribution
- Privacy-compliant data sharing

### Click ID Support
- GCLID tracking and storage
- GBRAID for iOS attribution
- WBRAID web-to-app tracking
- First-party cookie backup
- Click ID validation

### Audience Management
- Customer Match uploads
- Automatic list sync
- Audience segmentation
- Lookalike targeting
- Exclusion lists

### Monitoring & Testing
- Real-time conversion status
- Test mode for validation
- Conversion deduplication
- Error logging and alerts
- API response tracking

### Campaign Integration
- Value-based bidding support
- Conversion action mapping
- Multiple account support
- Offline conversion uploads
- Batch processing

---

## Use Cases

### Performance Max Campaigns
Feed accurate conversion data to Performance Max campaigns. Google's AI needs quality signals to optimize across Search, YouTube, Display, and Discover. Better data means better performance.

### Search Campaign Optimization
Track which keywords drive ticket sales, not just clicks. Bid higher on converting keywords and reduce spend on keywords that generate traffic but no revenue.

### YouTube Advertising
Measure true return on YouTube ads. See which video campaigns drive ticket purchases and optimize creative based on actual sales, not just views.

### Remarketing Campaigns
Build audiences from your website visitors and past buyers. Show targeted ads to people who browsed events but didn't purchase, or promote new events to previous attendees.

### Smart Bidding
Enable Target ROAS or Maximize Conversion Value bidding. With accurate conversion data including purchase values, Google's algorithms optimize for revenue, not just conversions.

### Cross-Channel Attribution
Understand how different Google Ads channels work together. See the full path from first click to ticket purchase across multiple touchpoints.

---

## Technical Documentation

### Overview

The Google Ads Integration microservice connects to Google Ads API to send offline conversions and manage Customer Match audiences. It handles OAuth authentication, conversion uploads, and audience synchronization.

### Prerequisites

- Google Ads account with API access
- OAuth 2.0 credentials configured
- Google Ads API developer token
- Conversion actions created in Google Ads

### Configuration

```php
'google_ads' => [
    'customer_id' => env('GOOGLE_ADS_CUSTOMER_ID'),
    'developer_token' => env('GOOGLE_ADS_DEVELOPER_TOKEN'),
    'oauth' => [
        'client_id' => env('GOOGLE_ADS_CLIENT_ID'),
        'client_secret' => env('GOOGLE_ADS_CLIENT_SECRET'),
        'refresh_token' => env('GOOGLE_ADS_REFRESH_TOKEN'),
    ],
    'conversion_actions' => [
        'purchase' => 'conversions/123456789',
        'lead' => 'conversions/987654321',
    ],
    'enhanced_conversions' => true,
    'test_mode' => env('GOOGLE_ADS_TEST_MODE', false),
]
```

### API Endpoints

#### OAuth Authorization

```
GET /api/integrations/google-ads/auth
```

Returns OAuth URL for account connection.

**Response:**
```json
{
  "auth_url": "https://accounts.google.com/o/oauth2/v2/auth?...",
  "state": "abc123"
}
```

#### OAuth Callback

```
POST /api/integrations/google-ads/callback
```

Handles OAuth callback and stores tokens.

#### Connection Status

```
GET /api/integrations/google-ads/connection
```

**Response:**
```json
{
  "connected": true,
  "customer_id": "123-456-7890",
  "account_name": "My Google Ads Account",
  "last_sync": "2025-01-15T10:30:00Z"
}
```

#### Send Conversion

```
POST /api/integrations/google-ads/conversions
```

**Request:**
```json
{
  "conversion_action": "purchase",
  "order_id": "order_123",
  "conversion_time": "2025-01-15T14:30:00Z",
  "conversion_value": 150.00,
  "currency_code": "EUR",
  "gclid": "CjwKCAiA...",
  "user_data": {
    "email": "customer@example.com",
    "phone": "+40721234567",
    "first_name": "Ion",
    "last_name": "Popescu"
  }
}
```

**Response:**
```json
{
  "success": true,
  "conversion_id": "conv_abc123",
  "status": "ACCEPTED"
}
```

#### List Conversion Actions

```
GET /api/integrations/google-ads/conversion-actions
```

Returns available conversion actions from Google Ads account.

#### Create Audience

```
POST /api/integrations/google-ads/audiences
```

**Request:**
```json
{
  "name": "Past Event Buyers",
  "description": "Customers who purchased tickets in last 90 days",
  "membership_life_span": 90
}
```

#### Sync Audience Members

```
POST /api/integrations/google-ads/audiences/{id}/sync
```

**Request:**
```json
{
  "operation": "add",
  "members": [
    {
      "email": "user1@example.com",
      "phone": "+40721234567"
    },
    {
      "email": "user2@example.com"
    }
  ]
}
```

### Conversion Data Structure

```json
{
  "conversion_action": "customers/123456/conversionActions/789",
  "conversion_date_time": "2025-01-15 14:30:00+00:00",
  "conversion_value": 150.00,
  "currency_code": "EUR",
  "order_id": "order_123",
  "gclid": "CjwKCAiA...",
  "gbraid": null,
  "wbraid": null,
  "user_identifiers": [
    {
      "hashed_email": "a1b2c3d4..."
    },
    {
      "hashed_phone_number": "e5f6g7h8..."
    }
  ]
}
```

### Enhanced Conversion Hashing

```php
// Hash email (lowercase, trim whitespace)
$email = strtolower(trim($email));
$hashedEmail = hash('sha256', $email);

// Hash phone (E.164 format)
$phone = preg_replace('/[^0-9+]/', '', $phone);
$hashedPhone = hash('sha256', $phone);

// Hash name (lowercase, trim, UTF-8)
$firstName = strtolower(trim($firstName));
$hashedFirstName = hash('sha256', $firstName);
```

### Event Triggers

The integration automatically sends conversions for:

| Event | Conversion Action | Value |
|-------|-------------------|-------|
| Order Completed | purchase | Order total |
| User Registered | lead | Configurable |
| Form Submitted | lead | Configurable |

### Batch Upload

For high-volume scenarios, conversions are batched:

```php
// Conversions queued and sent in batches
$batchSize = 2000; // Google's limit
$conversions->chunk($batchSize)->each(function ($batch) {
    $this->uploadConversionBatch($batch);
});
```

### Error Handling

| Error | Description | Action |
|-------|-------------|--------|
| INVALID_GCLID | Click ID not found | Conversion sent with user data only |
| DUPLICATE_CONVERSION | Already uploaded | Skip, deduplication working |
| INVALID_CONVERSION_ACTION | Action not found | Check configuration |
| AUTHENTICATION_ERROR | Token expired | Refresh OAuth tokens |

### Testing

Enable test mode to validate without affecting campaigns:

```php
'test_mode' => true
```

Test conversions appear in Google Ads under "Test events" and don't affect bidding.

### Security

1. All user data is hashed before transmission
2. OAuth tokens stored encrypted
3. API calls use HTTPS only
4. Minimal data retention
5. GDPR consent verification before sending
