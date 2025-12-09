# Platform Integrations

## Overview

Connect your event platform with the tools you already use. Our integration ecosystem brings together communications, marketing, productivity, and CRM tools into one seamless workflow.

---

## Communication Integrations

### Slack Integration - €10/month
Send notifications and updates to Slack channels. Keep your team informed about orders, events, and customer activities in real-time.

**Features:**
- OAuth 2.0 secure authentication
- Send messages to any channel
- Rich message formatting with blocks
- File uploads and sharing
- Webhook notifications
- Multiple workspace support

---

### Discord Integration - €10/month
Engage with your community on Discord. Send notifications via webhooks or bot, automate announcements for events and sales.

**Features:**
- Webhook message delivery
- Bot integration support
- Rich embed messages
- Multiple server support
- Custom bot username and avatar

---

### Telegram Bot Integration
Connect a Telegram Bot for notifications and customer interaction. Supports broadcast messaging and inline keyboards.

**Features:**
- Bot messaging (text, photos, documents)
- Inline keyboards for user actions
- Broadcast messages to subscribers
- Channel integration
- Order notifications and event reminders

---

## Marketing & Ads Integrations

### Google Ads Integration - €25/month
Track conversions from ticket purchases automatically. Optimize your ad campaigns with real conversion data.

**Features:**
- Automatic purchase conversion tracking
- Enhanced conversions with hashed data
- Support for GCLID, GBRAID, WBRAID
- Custom conversion actions
- Customer Match audience sync
- OAuth 2.0 authentication

---

### Facebook Conversions API
Server-side event tracking for Facebook/Meta Ads. Send conversions directly to Facebook for improved attribution.

**Features:**
- Purchase and lead event tracking
- Registration events
- Event deduplication with browser pixel
- Automatic SHA-256 hashing
- Custom Audiences sync

---

### TikTok Ads Integration - €20/month
Track conversions via TikTok Events API. Build custom audiences from your customer base.

**Features:**
- Server-side conversion tracking
- CompletePayment and AddToCart events
- TikTok Click ID (ttclid) support
- Batch event uploads
- Custom audience creation

---

### LinkedIn Ads Integration - €25/month
Perfect for B2B events and professional conferences. Track purchases, leads, and registrations.

**Features:**
- Server-side conversion tracking
- Purchase and lead tracking
- LinkedIn Member ID matching
- Matched Audiences (Customer Match)
- Multi-touch attribution

---

### Tracking & Pixels Manager - €1/month
Manage all tracking pixels with GDPR-compliant consent management.

**Features:**
- GA4 Integration
- Google Tag Manager
- Meta Pixel Integration
- TikTok Pixel Integration
- Consent management
- Ecommerce event tracking

---

## CRM Integrations

### HubSpot Integration - €20/month
Sync customer data with HubSpot CRM. Automate marketing and sales workflows.

**Features:**
- Contact management
- Deal creation and tracking
- Company records
- Bidirectional sync
- Webhook support
- OAuth 2.0 authentication

---

### Salesforce Integration - €25/month
Keep your sales pipeline in sync with Salesforce CRM.

**Features:**
- Contact and lead sync
- Opportunity tracking
- Custom field mapping
- Bidirectional sync
- SOQL query support

---

## Productivity Integrations

### Google Sheets Integration - €12/month
Export orders, tickets, and customer data to Google Sheets for reporting and collaboration.

**Features:**
- Export orders to Sheets
- Export tickets with attendee details
- Real-time data sync
- Scheduled sync (hourly, daily, weekly)
- Custom column mapping

---

### Google Workspace Integration - €15/month
Connect with Google Drive, Calendar, and Gmail.

**Features:**
- Google Drive file uploads
- Google Calendar event creation
- Gmail email sending
- OAuth 2.0 authentication

---

### Microsoft 365 Integration - €15/month
Integrate with OneDrive, Outlook, and Teams.

**Features:**
- OneDrive file uploads
- Outlook email sending
- Microsoft Teams messaging
- Calendar integration

---

### Airtable Integration
Sync data with Airtable bases for custom workflows.

**Features:**
- OAuth connection
- Orders, tickets, customers export
- Field mapping
- Bidirectional sync
- Auto sync scheduling

---

### Jira Integration
Connect with Jira for project management workflows.

---

## Automation Integrations

### Zapier Integration - €20/month
Connect with 5000+ apps through Zapier. Trigger automated workflows on orders, tickets, and events.

**Features:**
- Order created trigger
- Ticket sold trigger
- Customer created trigger
- Event published trigger
- Registration completed trigger
- Refund issued trigger
- REST Hook subscriptions

---

## Video & Virtual Events

### Zoom Integration
Create and manage Zoom meetings for virtual events.

**Features:**
- OAuth connection
- Meeting and webinar creation
- Auto-create meetings for events
- Registrant sync with ticket holders
- Attendance tracking
- Recording management

---

## Technical Documentation

All integrations follow consistent patterns:

### Authentication
- OAuth 2.0 for most services
- API key authentication where required
- Encrypted credential storage
- Automatic token refresh

### Webhook Support
Integrations that support webhooks receive real-time updates for:
- Order events
- Ticket events
- Customer events
- Payment events

### Configuration Example

```php
'integrations' => [
    'slack' => [
        'client_id' => env('SLACK_CLIENT_ID'),
        'client_secret' => env('SLACK_CLIENT_SECRET'),
    ],
    'hubspot' => [
        'client_id' => env('HUBSPOT_CLIENT_ID'),
        'client_secret' => env('HUBSPOT_CLIENT_SECRET'),
    ],
    // ... more integrations
]
```

### Common Endpoints Pattern

Each integration follows a similar API pattern:

```
GET /api/integrations/{provider}/auth
POST /api/integrations/{provider}/callback
GET /api/integrations/{provider}/connection
DELETE /api/integrations/{provider}/disconnect
POST /api/integrations/{provider}/sync
GET /api/integrations/{provider}/status
```
