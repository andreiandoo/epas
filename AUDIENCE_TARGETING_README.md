# Audience Targeting & Campaigns Microservice

Advanced customer profiling, segmentation, event-based targeting, and multi-channel campaign management system for tenants.

## Overview

The Audience Targeting microservice helps tenants generate more targeted sales by:
- Building enriched customer profiles from purchase history and behavior
- Creating dynamic segments based on flexible criteria
- Identifying best-fit customers for specific events using AI-powered matching
- Exporting audiences to ad platforms (Meta, Google, TikTok)
- Managing email campaigns via Brevo integration
- Orchestrating multi-channel marketing campaigns

### Key Features

- ✅ Customer profile enrichment with purchase metrics and preferences
- ✅ Dynamic segmentation with rule-based criteria
- ✅ Event-customer matching with scoring algorithm
- ✅ Export to Meta Custom Audiences
- ✅ Export to Google Ads Customer Match
- ✅ Export to TikTok Ads Audiences
- ✅ Brevo email list synchronization
- ✅ Lookalike audience creation
- ✅ Multi-channel campaign orchestration
- ✅ Campaign analytics and ROI tracking

## Pricing

- **Model**: Monthly recurring subscription
- **Base Price**: 15 EUR per month
- **Includes**: 10 segments, unlimited profile calculations, 5 exports/month
- **Extra**: Additional segments and exports available via add-ons

## Architecture

### Database Schema

**customer_profiles** - Enriched customer data:
- Purchase metrics (count, total spent, average order)
- Preferences (genres, event types, price range, preferred days)
- Engagement metrics (score, churn risk, recent activity)
- Location data

**audience_segments** - Customer segments:
- Dynamic (rule-based, auto-refresh)
- Static (manual selection)
- Lookalike (derived from source segment)

**audience_segment_customers** - Segment membership with affinity scores

**event_recommendations** - AI-driven event-customer matching:
- Match score (0-100)
- Match reasons (genre, type, artist, price, location)
- Conversion tracking

**audience_campaigns** - Marketing campaigns:
- Email campaigns via Brevo
- Ad campaigns (Meta, Google, TikTok)
- Multi-channel campaigns

**audience_exports** - Platform export tracking:
- External audience IDs
- Match rates and counts
- Expiration tracking

### Service Architecture

```
app/Services/AudienceTargeting/
├── CustomerProfileService.php      # Profile building and enrichment
├── SegmentationService.php         # Segment creation and rule engine
├── EventMatchingService.php        # Event-customer scoring
├── AudienceExportService.php       # Platform export orchestration
├── CampaignOrchestrationService.php # Campaign management
└── Providers/
    ├── AudienceProviderInterface.php
    ├── MetaAudienceProvider.php    # Facebook/Instagram
    ├── GoogleAdsAudienceProvider.php
    ├── TikTokAudienceProvider.php
    └── BrevoAudienceProvider.php   # Email marketing
```

## Installation

### 1. Run Migrations

```bash
php artisan migrate
```

This creates:
- `customer_profiles` table
- `audience_segments` table
- `audience_segment_customers` pivot table
- `event_recommendations` table
- `audience_campaigns` table
- `audience_exports` table
- Adds ad platform API keys to settings

### 2. Seed Microservice

```bash
php artisan db:seed --class=AudienceTargetingMicroserviceSeeder
```

### 3. Configure Platform Credentials

In the admin settings, configure:

**For Meta (Facebook/Instagram):**
- Facebook App ID
- Facebook App Secret
- Facebook Access Token

**For Google Ads:**
- Google Ads Customer ID
- Google Ads Developer Token
- Google Ads Credentials JSON (OAuth)

**For TikTok Ads:**
- TikTok Advertiser ID
- TikTok Access Token

**For Brevo:**
- Brevo API Key (already configured for tracking)

## Usage

### Customer Profiles

Customer profiles are automatically built from purchase history and analytics events.

**Rebuild all profiles:**
```
POST /api/audience/profiles/rebuild
{
  "tenant_id": 1
}
```

**Get customer profile:**
```
GET /api/audience/profiles/{customerId}?tenant_id=1
```

### Segments

**Create a dynamic segment:**
```
POST /api/audience/segments
{
  "tenant_id": 1,
  "name": "High Value Rock Fans",
  "segment_type": "dynamic",
  "criteria": {
    "match": "all",
    "rules": [
      {"field": "total_spent", "operator": ">=", "value": 100},
      {"field": "genres", "operator": "includes", "value": ["rock", "metal"]},
      {"field": "last_purchase", "operator": "within_days", "value": 90}
    ]
  }
}
```

**Available segment criteria:**
| Field | Operators | Description |
|-------|-----------|-------------|
| `total_spent` | `>=`, `<=`, `=`, `between` | Total spent in EUR |
| `purchase_count` | `>=`, `<=`, `=`, `between` | Number of purchases |
| `avg_order` | `>=`, `<=`, `between` | Average order value |
| `engagement_score` | `>=`, `<=`, `between` | Engagement score (0-100) |
| `churn_risk` | `>=`, `<=`, `between` | Churn risk (0-100) |
| `last_purchase` | `within_days`, `before_days` | Days since last purchase |
| `genres` | `includes` | Preferred genres |
| `event_types` | `includes` | Preferred event types |
| `city` | `is`, `in` | Customer city |
| `country` | `is`, `in` | Customer country |
| `age` | `>=`, `<=`, `between` | Customer age |

**Preview segment (without saving):**
```
POST /api/audience/segments/preview
{
  "tenant_id": 1,
  "criteria": {
    "match": "all",
    "rules": [...]
  }
}
```

### Event Targeting

**Find best customers for an event:**
```
GET /api/audience/events/{eventId}/recommendations?limit=100&min_score=60
```

Returns customers ranked by match score with reasons:
```json
{
  "event_id": 42,
  "count": 85,
  "customers": [
    {
      "customer_id": 123,
      "email": "john@example.com",
      "score": 95,
      "reasons": [
        {"reason": "genre_match", "weight": 25},
        {"reason": "type_match", "weight": 20},
        {"reason": "price_fit", "weight": 15}
      ]
    }
  ]
}
```

**Create segment from event recommendations:**
```
POST /api/audience/events/{eventId}/create-segment
{
  "min_score": 70,
  "name": "Best fit for Summer Festival"
}
```

### Exports

**Export to Meta (Facebook/Instagram):**
```
POST /api/audience/exports/meta
{
  "segment_id": 1,
  "audience_name": "High Value Customers Q4"
}
```

**Export to Google Ads:**
```
POST /api/audience/exports/google
{
  "segment_id": 1,
  "audience_name": "Concert Enthusiasts"
}
```

**Export to TikTok:**
```
POST /api/audience/exports/tiktok
{
  "segment_id": 1,
  "audience_name": "Young Festival Goers"
}
```

**Export to Brevo (email list):**
```
POST /api/audience/exports/brevo
{
  "segment_id": 1,
  "list_name": "Newsletter VIPs"
}
```

### Campaigns

**Create an email campaign:**
```
POST /api/audience/campaigns
{
  "tenant_id": 1,
  "segment_id": 1,
  "event_id": 42,
  "name": "Summer Festival Promo",
  "campaign_type": "email",
  "settings": {
    "subject": "Exclusive early access to Summer Festival!",
    "sender_name": "Festival Team",
    "sender_email": "tickets@example.com",
    "html_content": "<h1>You're invited!</h1>..."
  }
}
```

**Launch a campaign:**
```
POST /api/audience/campaigns/{id}/launch
```

**Get campaign stats:**
```
GET /api/audience/campaigns/{id}/stats
```

Returns:
```json
{
  "sent": 1000,
  "delivered": 980,
  "opens": 450,
  "clicks": 120,
  "conversions": 25,
  "revenue_cents": 250000,
  "open_rate": 45.92,
  "click_rate": 12.24,
  "conversion_rate": 20.83,
  "roas": 50.0
}
```

## Event Matching Algorithm

The event-customer matching uses a weighted scoring system:

| Factor | Max Weight | Description |
|--------|------------|-------------|
| Genre Match | 25 | Customer's preferred genres match event genres |
| Type Match | 20 | Customer's preferred event types match |
| Artist Match | 20 | Customer attended previous events with same artists |
| Price Fit | 15 | Event price within customer's usual range |
| Location | 10 | Event in customer's city/country |
| High Engagement | 5 | Customer engagement score ≥ 70 |
| Watchlist | 5 | Event is on customer's watchlist |

Total maximum score: 100

## Admin Interface (Filament)

Navigate to **Admin Panel → Marketing**:

- **Audience Segments** - Create and manage segments
- **Campaigns** - Create and launch marketing campaigns

### Segment Builder

1. Select tenant
2. Choose segment type (dynamic/static/lookalike)
3. Add rules using the criteria builder
4. Preview matching customers
5. Save and auto-refresh

### Campaign Manager

1. Select target segment
2. Choose campaign type (email/ads)
3. Configure platform-specific settings
4. Schedule or launch immediately
5. Monitor results

## API Endpoints

### Profiles
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/audience/profiles` | List profiles |
| GET | `/api/audience/profiles/{id}` | Get profile |
| POST | `/api/audience/profiles/rebuild` | Rebuild all profiles |

### Segments
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/audience/segments` | List segments |
| POST | `/api/audience/segments` | Create segment |
| GET | `/api/audience/segments/{id}` | Get segment |
| POST | `/api/audience/segments/{id}/refresh` | Refresh segment |
| POST | `/api/audience/segments/preview` | Preview criteria |

### Event Targeting
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/audience/events/{id}/recommendations` | Get best-fit customers |
| POST | `/api/audience/events/{id}/create-segment` | Create segment from event |
| POST | `/api/audience/events/{id}/generate-recommendations` | Generate recommendations |

### Exports
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/audience/exports/platforms` | List available platforms |
| GET | `/api/audience/exports` | List exports |
| POST | `/api/audience/exports/meta` | Export to Meta |
| POST | `/api/audience/exports/google` | Export to Google Ads |
| POST | `/api/audience/exports/tiktok` | Export to TikTok |
| POST | `/api/audience/exports/brevo` | Export to Brevo |

### Campaigns
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/audience/campaigns` | List campaigns |
| POST | `/api/audience/campaigns` | Create campaign |
| GET | `/api/audience/campaigns/{id}` | Get campaign |
| POST | `/api/audience/campaigns/{id}/launch` | Launch campaign |
| GET | `/api/audience/campaigns/{id}/stats` | Get campaign stats |

### Dashboard
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/audience/dashboard` | Get dashboard summary |

## Data Privacy & Compliance

### GDPR Compliance

- Customer data is hashed (SHA256) before export to ad platforms
- Only email and phone are exported (with consent)
- Consent is managed via the Tracking Pixels microservice
- Customers can request data deletion

### Data Hashing

All PII is hashed before sending to ad platforms:
- Email: lowercase, trimmed, SHA256
- Phone: E.164 format, SHA256
- Names: lowercase, trimmed, SHA256

### Audience Retention

- Meta: 180 days
- Google Ads: 540 days
- TikTok: 365 days
- Brevo: No expiration

## Scheduled Tasks

Add to your scheduler (`app/Console/Kernel.php`):

```php
// Rebuild recently active profiles daily
$schedule->call(function () {
    $tenants = Tenant::whereHas('tenantMicroservices', function ($q) {
        $q->where('microservice_id', /* audience-targeting id */)
          ->where('is_active', true);
    })->get();

    foreach ($tenants as $tenant) {
        app(CustomerProfileService::class)->rebuildRecentlyActiveProfiles($tenant);
    }
})->daily();

// Refresh stale segments hourly
$schedule->call(function () {
    app(SegmentationService::class)->refreshStaleSegments();
})->hourly();

// Process scheduled campaigns
$schedule->call(function () {
    app(CampaignOrchestrationService::class)->processScheduledCampaigns();
})->everyMinute();

// Refresh expiring exports
$schedule->call(function () {
    app(AudienceExportService::class)->refreshExpiringExports(30);
})->daily();
```

## Best Practices

### Segmentation

1. **Start broad, then refine** - Begin with simple criteria, add complexity as needed
2. **Use engagement scores** - Target engaged customers for better conversion
3. **Combine criteria wisely** - Use "all" for precision, "any" for reach
4. **Monitor segment sizes** - Very small segments may not work well with ad platforms

### Event Targeting

1. **Generate recommendations early** - Run recommendation generation when event is created
2. **Set appropriate minimum scores** - 60+ for quality, 40+ for reach
3. **Review match reasons** - Understand why customers are matched

### Campaigns

1. **Test with small segments first** - Validate before full launch
2. **Use scheduled campaigns** - Plan ahead for event promotions
3. **Track conversions** - Connect purchases back to campaigns

## Troubleshooting

### Profile Not Building

1. Check if customer has orders for the tenant
2. Verify `customer_tenant` pivot relationship
3. Run manual rebuild: `POST /api/audience/profiles/rebuild`

### Segment Empty

1. Check criteria syntax
2. Verify profile data exists for criteria fields
3. Use preview to test criteria before saving

### Export Failed

1. Check platform credentials in settings
2. Verify API access and permissions
3. Review error message in export record

### Campaign Not Sending

1. Check campaign status is "draft" or "scheduled"
2. Verify segment has customers
3. Check platform export was successful

## Support

For issues or questions:
- Review logs: `storage/logs/laravel.log`
- Check API documentation above
- Test with preview/debug endpoints

---

**Version**: 1.0.0
**Last Updated**: 2025-12-03
**Microservice Price**: 15 EUR/month recurring
