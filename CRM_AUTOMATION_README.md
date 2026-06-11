# CRM & Marketing Automation Microservice

Customer relationship management with email marketing and automated workflows.

## Features
- Dynamic customer segmentation
- Email campaign builder
- Campaign analytics (opens, clicks)
- Automation workflows with triggers
- Customer activity timeline
- Multi-step workflow builder

## Database Tables
- `customer_segments` - Segment definitions with conditions
- `customer_segment_members` - Segment membership
- `email_campaigns` - Email campaign records
- `campaign_recipients` - Individual recipient tracking
- `automation_workflows` - Workflow definitions
- `automation_steps` - Workflow steps
- `automation_enrollments` - Customer workflow progress
- `customer_activities` - Activity timeline

## API Endpoints

### Segments
- `POST /api/crm/segments` - Create segment
- `GET /api/crm/segments` - List segments
- `POST /api/crm/segments/{id}/calculate` - Recalculate members

### Campaigns
- `POST /api/crm/campaigns` - Create campaign
- `GET /api/crm/campaigns` - List campaigns
- `POST /api/crm/campaigns/{id}/schedule` - Schedule send
- `POST /api/crm/campaigns/{id}/send` - Send immediately
- `GET /api/crm/campaigns/stats` - Get statistics

### Workflows
- `POST /api/crm/workflows` - Create workflow
- `GET /api/crm/workflows` - List workflows
- `POST /api/crm/workflows/{id}/steps` - Add step
- `POST /api/crm/workflows/{id}/toggle` - Activate/deactivate

### Activities
- `POST /api/crm/activities` - Log activity
- `GET /api/crm/customers/{id}/timeline` - Get timeline

### Tracking
- `GET /api/crm/track/open/{id}` - Track email open
- `GET /api/crm/track/click/{id}` - Track click

## Segment Conditions
```json
{
  "conditions": [
    {"type": "total_spent", "operator": ">=", "value": 100},
    {"type": "orders_count", "operator": ">=", "value": 3},
    {"type": "tag", "value": "vip"}
  ]
}
```

## Workflow Triggers
- `purchase` - After ticket purchase
- `signup` - On customer registration
- `event_day` - Day of event
- `custom` - Manual trigger

## Usage
```php
$service = app(CRMService::class);

// Create segment
$segment = $service->createSegment([
    'tenant_id' => $tenantId,
    'name' => 'High Value',
    'conditions' => [
        ['type' => 'total_spent', 'operator' => '>=', 'value' => 500]
    ],
]);

// Create and send campaign
$campaign = $service->createCampaign([
    'tenant_id' => $tenantId,
    'segment_id' => $segment->id,
    'name' => 'VIP Offer',
    'subject' => 'Exclusive discount for you',
    'content' => '<html>...</html>',
]);
$service->sendCampaign($campaign);

// Process automation trigger
$service->processTrigger($tenantId, 'purchase', $customer, [
    'order_total' => 150
]);
```
