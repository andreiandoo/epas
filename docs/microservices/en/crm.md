# CRM (Customer Relationship Management)

## Short Presentation

Know your customers like never before with our purpose-built CRM for event organizers. Every ticket purchase, every event attended, every interaction - all in one unified view. Transform data into relationships and relationships into loyal fans.

Build complete customer profiles automatically as people engage with your events. Purchase history, attendance records, communication preferences - everything you need to understand who your customers are and what they love.

Segment your audience with precision. Create groups based on spending patterns, event preferences, attendance frequency, or any custom criteria. Target VIP customers with exclusive offers, re-engage lapsed attendees, or reward your most loyal fans.

Launch automated email campaigns that feel personal. Welcome new customers, remind previous attendees about upcoming similar events, or send birthday offers. The CRM does the heavy lifting while you focus on creating great events.

Track customer lifetime value to understand which segments drive your business. Identify your highest-value customers and ensure they receive VIP treatment. Detect churn risk early and take action before customers drift away.

GDPR compliance is built-in, not bolted on. Honor consent preferences, handle data deletion requests, and maintain full audit trails. Import and export customer data easily with CSV and Excel support.

Your events create connections. The CRM helps you keep them.

---

## Detailed Description

The CRM microservice is a comprehensive customer relationship management system designed specifically for event organizers and ticketing platforms. It aggregates customer data from all touchpoints into unified profiles and provides tools for segmentation, marketing automation, and customer analytics.

### Unified Customer Profiles

Every interaction a customer has with your platform contributes to their profile:
- Ticket purchases and order history
- Event attendance records
- Email engagement metrics
- Support interactions
- Custom notes and tags

### Advanced Segmentation

Create dynamic segments based on multiple criteria:
- **Behavioral**: Purchase frequency, attendance rate, last purchase date
- **Demographic**: Location, age, preferences
- **Value-based**: Total spend, average order value, lifetime value
- **Custom**: Tags, notes, custom fields

Segments update automatically as customer data changes, ensuring your targeting is always current.

### Marketing Automation

Set up automated campaigns triggered by customer actions or time-based rules:
- Welcome series for new customers
- Re-engagement campaigns for inactive customers
- Event recommendations based on past attendance
- Birthday and anniversary offers
- Post-event feedback requests

### Customer Lifetime Value

The system calculates and tracks CLV for each customer, enabling:
- Identification of high-value customers
- ROI analysis by acquisition channel
- Churn prediction and prevention
- Value-based segmentation

### Data Privacy

Full GDPR compliance with:
- Consent management
- Right to access and deletion
- Data export capabilities
- Audit logging

---

## Features

### Customer Profiles
- Unified customer profiles with purchase history
- Event attendance history tracking
- Custom tags and labels
- Customer notes and activity timeline
- Duplicate detection and merging
- VIP customer identification

### Segmentation
- Advanced audience segmentation
- Dynamic segment updates
- Multi-criteria segment rules
- Segment performance analytics
- Customer list export

### Marketing
- Automated email campaigns
- SMS marketing integration
- Integration with email templates
- Abandoned cart recovery
- Campaign analytics

### Analytics
- Customer lifetime value tracking
- Churn prediction indicators
- Engagement scoring
- Cohort analysis
- Purchase pattern analysis

### Data Management
- Import/export customer data (CSV, Excel)
- Duplicate detection and merging
- Data enrichment capabilities
- Bulk operations support

### Compliance
- GDPR compliance tools
- Consent management
- Data deletion workflows
- Audit logging

---

## Use Cases

### VIP Customer Program
Identify your highest-value customers automatically. Tag them as VIPs and ensure they receive priority access, exclusive offers, and personalized communication.

### Re-engagement Campaigns
Segment customers who haven't purchased in 6 months. Send them personalized offers based on their past event preferences to bring them back.

### New Customer Nurturing
Welcome new customers with a series of emails introducing your venue, upcoming events, and loyalty benefits. Build the relationship from the first purchase.

### Event-Based Marketing
After a concert, automatically email attendees about similar upcoming acts. Use their genre preferences to suggest relevant events.

### Abandoned Cart Recovery
When customers leave items in their cart, trigger automated emails reminding them to complete their purchase with a time-limited incentive.

### Birthday Marketing
Send personalized birthday offers to customers, driving additional sales while making them feel valued.

---

## Technical Documentation

### Overview

The CRM microservice provides customer relationship management capabilities for event ticketing platforms. It aggregates customer data, enables segmentation, supports marketing automation, and provides analytics.

### Architecture

```
Platform Events → CRM Service → Customer Profiles
                       ↓
              Segmentation Engine
                       ↓
              Marketing Automation → Email/SMS Services
                       ↓
              Analytics Dashboard
```

### Database Schema

| Table | Description |
|-------|-------------|
| `crm_customers` | Unified customer profiles |
| `crm_segments` | Segment definitions |
| `crm_segment_customers` | Segment membership |
| `crm_campaigns` | Marketing campaigns |
| `crm_activities` | Customer activity log |

### API Endpoints

#### List Customers
```
GET /api/crm/customers
```
List customers with filtering and pagination.

**Query Parameters:**
- `search` - Search by name, email
- `segment_id` - Filter by segment
- `tags` - Filter by tags
- `min_ltv` - Minimum lifetime value
- `last_purchase_after` - Date filter

#### Get Customer
```
GET /api/crm/customers/{id}
```
Retrieve complete customer profile with history.

#### Create Segment
```
POST /api/crm/segments
```
Create a new customer segment.

**Request:**
```json
{
  "name": "VIP Customers",
  "rules": {
    "operator": "and",
    "conditions": [
      {"field": "total_spend", "operator": ">=", "value": 500},
      {"field": "events_attended", "operator": ">=", "value": 5}
    ]
  },
  "auto_update": true
}
```

#### Get Segment Customers
```
GET /api/crm/segments/{id}/customers
```
List customers in a segment.

#### Create Campaign
```
POST /api/crm/campaigns
```
Create automated marketing campaign.

#### Get LTV Analytics
```
GET /api/crm/analytics/ltv
```
Customer lifetime value analytics.

### Customer Profile Structure

```json
{
  "id": "cust_abc123",
  "email": "customer@example.com",
  "name": "John Smith",
  "phone": "+40722123456",
  "created_at": "2024-01-15T10:00:00Z",
  "metrics": {
    "total_orders": 15,
    "total_spend": 750.00,
    "events_attended": 12,
    "average_order_value": 50.00,
    "lifetime_value": 1200.00,
    "last_purchase": "2025-01-10T18:30:00Z"
  },
  "segments": ["vip", "rock-fans"],
  "tags": ["early-adopter", "newsletter"],
  "preferences": {
    "genres": ["rock", "jazz"],
    "communication": ["email"]
  },
  "consent": {
    "marketing_email": true,
    "marketing_sms": false,
    "updated_at": "2024-06-01T12:00:00Z"
  }
}
```

### Segmentation Rules

```json
{
  "operator": "and",
  "conditions": [
    {"field": "total_spend", "operator": ">=", "value": 100},
    {
      "operator": "or",
      "conditions": [
        {"field": "last_purchase", "operator": "within", "value": "90d"},
        {"field": "events_attended", "operator": ">=", "value": 3}
      ]
    }
  ]
}
```

### Configuration

```php
'crm' => [
    'ltv_calculation_period' => 365, // days
    'churn_threshold_days' => 180,
    'segment_refresh_interval' => 60, // minutes
    'duplicate_detection' => [
        'fields' => ['email', 'phone'],
        'fuzzy_match' => true,
    ],
    'integrations' => [
        'mailchimp' => env('MAILCHIMP_API_KEY'),
        'sendgrid' => env('SENDGRID_API_KEY'),
        'twilio' => env('TWILIO_API_KEY'),
    ],
]
```

### Integration Example

```php
use App\Services\CRM\CRMService;

$crm = app(CRMService::class);

// Get or create customer
$customer = $crm->findOrCreate([
    'email' => 'customer@example.com',
    'name' => 'John Smith',
]);

// Add to segment
$crm->addToSegment($customer->id, 'vip');

// Track activity
$crm->trackActivity($customer->id, 'purchase', [
    'order_id' => 'ord_123',
    'amount' => 150.00,
]);

// Get LTV
$ltv = $crm->getLifetimeValue($customer->id);
```

### Metrics

Track CRM performance:
- Active customers (30/60/90 days)
- Customer acquisition rate
- Churn rate
- Average lifetime value
- Segment growth rates
- Campaign conversion rates
