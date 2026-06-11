# Sales-Driving Integrations Plan

This document outlines planned integrations focused on helping tenants increase their event ticket sales through customer segmentation, tracking, conversions, and marketing automation.

## Overview

These integrations are **connectors** that allow tenants to connect their own accounts and automate data flow. The platform does NOT run ads or manage accounts for tenants - it provides the plumbing to make their marketing more effective.

---

## Tier 1: Advertising Platform Connectors (High Priority)

These integrations enable **conversion tracking** and **audience sync** with ad platforms.

### Google Ads
- **Purpose**: Conversion tracking, audience sync, offline conversions
- **What it does**:
  - Send purchase events back to tenant's Google Ads account
  - Enable Smart Bidding (Target ROAS, Maximize Conversions)
  - Sync customer lists for lookalike audiences
  - Import offline conversions (ticket scans at event)
- **OAuth Scopes**: `https://www.googleapis.com/auth/adwords`
- **API**: Google Ads API v15+

### Meta Ads (Facebook/Instagram)
- **Purpose**: Conversions API, Custom Audiences, Catalog sync
- **What it does**:
  - Server-side conversion tracking (more reliable than pixel)
  - Upload buyer lists for Custom Audiences
  - Create lookalike audiences from purchasers
  - Sync event catalog for dynamic ads
- **OAuth Scopes**: `ads_management`, `business_management`
- **API**: Marketing API v18+

### TikTok Ads
- **Purpose**: Events API, audience sync
- **What it does**:
  - Send purchase/view events to TikTok
  - Sync audiences for retargeting
  - Enable Value-Based Optimization
- **OAuth Scopes**: `advertiser.manage`
- **API**: TikTok Marketing API

### LinkedIn Ads
- **Purpose**: B2B event promotion, conversion tracking
- **What it does**:
  - Track conversions for professional/B2B events
  - Sync company/contact lists for targeting
  - Matched Audiences for retargeting
- **OAuth Scopes**: `r_ads`, `rw_ads`
- **API**: LinkedIn Marketing API

### Twitter/X Ads
- **Purpose**: Conversion tracking, tailored audiences
- **What it does**:
  - Track ticket purchases as conversions
  - Upload email lists for targeting
  - Website tag events
- **API**: Twitter Ads API

### Snapchat Ads
- **Purpose**: Snap Pixel, audience sync
- **What it does**:
  - Conversion tracking for younger demographics
  - Geofilter campaigns for events
  - Audience sync for retargeting
- **API**: Snapchat Marketing API

### Pinterest Ads
- **Purpose**: Conversion tracking for visual events
- **What it does**:
  - Track conversions for visual events (weddings, art, food)
  - Catalog sync for event pins
  - Audience targeting
- **API**: Pinterest Ads API

---

## Tier 2: Email Marketing & Automation

### Klaviyo (Recommended)
- **Purpose**: E-commerce email + SMS, predictive analytics
- **Features**:
  - Abandoned cart/browse abandonment flows
  - RFM segmentation (Recency, Frequency, Monetary)
  - Predictive analytics (next purchase date, churn risk)
  - SMS marketing built-in
- **API**: Klaviyo API v2023+

### Mailchimp
- **Purpose**: Email campaigns, automation, segmentation
- **Features**:
  - Automated email flows
  - Audience segmentation
  - A/B testing
  - Landing pages
- **API**: Mailchimp Marketing API

### ActiveCampaign
- **Purpose**: Marketing automation, CRM, site tracking
- **Features**:
  - Visual automation builder
  - Lead scoring
  - CRM with deal tracking
  - Site tracking for behavior triggers
- **API**: ActiveCampaign API v3

### Customer.io
- **Purpose**: Behavioral messaging, event-triggered
- **Features**:
  - Real-time event triggers
  - Multi-channel (email, push, SMS, in-app)
  - Workflow automation
  - A/B testing
- **API**: Customer.io Track API

### Drip
- **Purpose**: E-commerce automation
- **Features**:
  - Visual workflow builder
  - E-commerce focused triggers
  - Revenue attribution
- **API**: Drip API v2

### SendGrid
- **Purpose**: Transactional + marketing email at scale
- **Features**:
  - High-volume delivery
  - Email validation
  - Marketing campaigns
  - Detailed analytics
- **API**: SendGrid API v3

---

## Tier 3: SMS & Push Marketing

### Attentive
- **Purpose**: SMS marketing platform
- **Features**:
  - 98%+ open rates
  - Two-way conversations
  - Segmentation
  - Compliance built-in (TCPA)
- **API**: Attentive API

### Postscript
- **Purpose**: SMS marketing for e-commerce
- **Features**:
  - Abandoned cart SMS
  - Keyword campaigns
  - Subscriber growth tools
- **API**: Postscript API

### OneSignal
- **Purpose**: Web + mobile push notifications
- **Features**:
  - Instant push to subscribers
  - Segmentation
  - A/B testing
  - In-app messaging
- **API**: OneSignal REST API

### Braze
- **Purpose**: Multi-channel customer engagement
- **Features**:
  - Push, email, SMS, in-app
  - Canvas (journey builder)
  - Predictive suite
  - Content cards
- **API**: Braze REST API

---

## Tier 4: Customer Data Platforms (CDPs)

### Segment (Recommended)
- **Purpose**: Unify customer data, distribute to all tools
- **Features**:
  - Single API for all tracking
  - 300+ destination integrations
  - Identity resolution
  - Audiences and computed traits
- **API**: Segment Tracking API
- **Why Important**: Connect once, send data everywhere

### Rudderstack
- **Purpose**: Open-source CDP alternative
- **Features**:
  - Self-hosted option
  - Warehouse-first approach
  - Real-time streaming
- **API**: Rudderstack API

### mParticle
- **Purpose**: Mobile-focused CDP
- **Features**:
  - Mobile SDK
  - Data quality controls
  - Audience management
- **API**: mParticle Events API

---

## Tier 5: Analytics & Tracking

### Mixpanel
- **Purpose**: Product analytics, funnel analysis
- **Features**:
  - Event-based tracking
  - Funnel analysis (identify drop-offs)
  - Cohort analysis
  - Retention reports
- **API**: Mixpanel Ingestion API

### Amplitude
- **Purpose**: Behavioral analytics
- **Features**:
  - User journey mapping
  - Cohort analysis
  - Retention analysis
  - Predictive analytics
- **API**: Amplitude HTTP API

### Heap
- **Purpose**: Auto-capture analytics
- **Features**:
  - Automatic event capture (no manual tracking)
  - Retroactive analysis
  - Session replay
  - Funnel analysis
- **API**: Heap Server-Side API

### Hotjar
- **Purpose**: UX insights, heatmaps
- **Features**:
  - Heatmaps (where users click)
  - Session recordings
  - Feedback polls
  - Conversion funnels
- **API**: Hotjar API

### FullStory
- **Purpose**: Digital experience analytics
- **Features**:
  - Session replay
  - Frustration signals (rage clicks)
  - Search across sessions
  - Conversion funnels
- **API**: FullStory Data Export API

---

## Tier 6: Retargeting & Personalization

### Criteo
- **Purpose**: Dynamic retargeting ads
- **Features**:
  - Show exact events users viewed
  - Cross-device retargeting
  - AI-powered bidding
- **API**: Criteo Marketing Solutions API

### AdRoll
- **Purpose**: Cross-platform retargeting
- **Features**:
  - Display, social, email retargeting
  - Cross-device identity
  - Attribution reporting
- **API**: AdRoll API

### Dynamic Yield
- **Purpose**: Personalization engine
- **Features**:
  - Personalized recommendations
  - A/B testing
  - Behavioral targeting
  - Email personalization
- **API**: Dynamic Yield API

### Optimizely
- **Purpose**: A/B testing, experimentation
- **Features**:
  - Visual editor for tests
  - Feature flags
  - Personalization
  - Stats engine
- **API**: Optimizely REST API

---

## Tier 7: Affiliate & Referral Programs

### Impact
- **Purpose**: Affiliate/partner management
- **Features**:
  - Track affiliate sales
  - Automated payouts
  - Fraud detection
  - Partner discovery
- **API**: Impact Partnership Cloud API

### Refersion
- **Purpose**: Affiliate tracking
- **Features**:
  - Referral link generation
  - Commission tracking
  - Influencer management
- **API**: Refersion API

### ReferralCandy
- **Purpose**: Referral program automation
- **Features**:
  - "Refer a friend, get $X off"
  - Automated rewards
  - Fraud detection
- **API**: ReferralCandy API

### Viral Loops
- **Purpose**: Viral referral campaigns
- **Features**:
  - Pre-launch waitlists
  - Gamified referrals
  - Milestone rewards
  - Leaderboards
- **API**: Viral Loops API

---

## Tier 8: Social Proof & Reviews

### Trustpilot
- **Purpose**: Review collection & display
- **Features**:
  - Automated review invitations
  - Widget for website
  - Google seller ratings
- **API**: Trustpilot Business API

### Yotpo
- **Purpose**: Reviews + loyalty + referrals
- **Features**:
  - Review collection
  - Photo/video reviews
  - Loyalty points
  - Referral program
- **API**: Yotpo Core API

### Fomo
- **Purpose**: Social proof notifications
- **Features**:
  - "John from NYC just bought 2 tickets"
  - Live visitor count
  - Customizable design
- **API**: Fomo API

### Proof
- **Purpose**: Social proof popups
- **Features**:
  - Recent activity notifications
  - Live visitor count
  - Hot streaks
- **API**: Proof API

---

## Tier 9: Live Chat & Conversion

### Intercom
- **Purpose**: Customer messaging platform
- **Features**:
  - Live chat
  - Chatbots
  - Product tours
  - Help center
- **API**: Intercom API

### Drift
- **Purpose**: Conversational marketing
- **Features**:
  - Chat-to-purchase flows
  - Meeting scheduling
  - AI chatbots
- **API**: Drift API

### Tidio
- **Purpose**: Live chat + chatbots
- **Features**:
  - 24/7 chatbot
  - Visitor tracking
  - Email integration
- **API**: Tidio API

---

## Tier 10: Data Enrichment

### Clearbit
- **Purpose**: B2B data enrichment
- **Features**:
  - Company data from email
  - Role/seniority detection
  - Lead scoring data
- **API**: Clearbit Enrichment API

### FullContact
- **Purpose**: Identity resolution
- **Features**:
  - Person enrichment
  - Company enrichment
  - Identity graph
- **API**: FullContact API

### ZoomInfo
- **Purpose**: B2B database
- **Features**:
  - Company data
  - Contact data
  - Intent signals
- **API**: ZoomInfo API

---

## Tier 11: Event-Specific Cross-Promotion

### Eventbrite
- **Purpose**: Cross-listing, discovery
- **Features**:
  - List on Eventbrite marketplace
  - Import/export events
  - Attendee sync
- **API**: Eventbrite API

### Meetup
- **Purpose**: Community events
- **Features**:
  - Tap into interest-based groups
  - Event promotion
- **API**: Meetup GraphQL API

### Bandsintown
- **Purpose**: Concert tracking
- **Features**:
  - Alert fans when artists play
  - Concert discovery
  - RSVP tracking
- **API**: Bandsintown API

### Dice
- **Purpose**: Music event ticketing
- **Features**:
  - Music fan audience
  - Artist tools
- **API**: Dice Partner API

---

## Tier 12: Loyalty & Gamification

### Smile.io
- **Purpose**: Loyalty program
- **Features**:
  - Points system
  - VIP tiers
  - Referral program
- **API**: Smile.io API

### LoyaltyLion
- **Purpose**: Loyalty platform
- **Features**:
  - Points & rewards
  - Tiers
  - Custom rewards
- **API**: LoyaltyLion API

### Gameball
- **Purpose**: Gamification engine
- **Features**:
  - Challenges & missions
  - Badges & levels
  - Leaderboards
- **API**: Gameball API

---

## Implementation Priority

### Phase 1: Foundation (Highest Impact)
1. **Segment** - CDP to unify all data (connect once, send everywhere)
2. **Google Ads** - Conversion tracking (most used ad platform)
3. **Meta Ads** - Conversions API (Facebook/Instagram)
4. **Klaviyo** - Email + SMS automation
5. **Fomo** - Quick social proof wins

### Phase 2: Scale
6. **TikTok Ads** - Growing platform, lower CPM
7. **Mixpanel** - Understand user behavior
8. **OneSignal** - Push notifications
9. **Impact** - Affiliate program
10. **Criteo** - Dynamic retargeting

### Phase 3: Optimize
11. **Hotjar** - UX insights
12. **Dynamic Yield** - Personalization
13. **Intercom** - Convert hesitant buyers
14. **Clearbit** - B2B enrichment
15. **Smile.io** - Loyalty program

---

## Technical Architecture

Each integration follows the same microservice pattern:

```
microservices/
├── segment-integration/          # CDP
├── google-ads-integration/       # Ad platform
├── meta-ads-integration/         # Ad platform
├── tiktok-ads-integration/       # Ad platform
├── klaviyo-integration/          # Email/SMS
├── mixpanel-integration/         # Analytics
├── fomo-integration/             # Social proof
└── ...
```

### Data Flow

```
Tenant Event (ticket purchase, page view, etc.)
         ↓
    Core Platform
         ↓
    Segment CDP (if connected)
         ↓
    All connected destinations (Google Ads, Klaviyo, Mixpanel, etc.)
```

### Tenant Configuration

Each tenant connects their own accounts via OAuth or API key in their dashboard:
- Google Ads → OAuth 2.0
- Meta Ads → OAuth 2.0
- Klaviyo → API Key
- Segment → Write Key
- etc.

The core admin configures the OAuth app credentials (Client ID/Secret) in the Connections page.
Tenants then use those apps to connect their individual accounts.

---

## Next Steps

1. Review and prioritize integrations based on tenant demand
2. Start with Phase 1 integrations (Segment, Google Ads, Meta Ads, Klaviyo, Fomo)
3. Create microservices following existing patterns
4. Add tenant-facing connection UI
5. Implement webhook handlers for real-time sync
