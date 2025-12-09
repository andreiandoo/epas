# HubSpot Integration

## Short Presentation

Unite your event business with HubSpot's powerful CRM and marketing platform. HubSpot Integration syncs your ticket buyers, deals, and company data automatically, enabling sophisticated marketing automation based on real purchase behavior.

Every ticket sale enriches your HubSpot data. Customer contacts update with purchase history, event attendance, and spending patterns. Your marketing team sees the complete picture without manual data entry.

Contacts flow both ways. Create a customer during checkout and they appear in HubSpot. Update a contact's preferences in HubSpot and your platform reflects the change. Always synchronized, always current.

Deals track your sales pipeline. Link opportunities to specific events, track corporate ticket packages, and forecast revenue. Your sales process lives in HubSpot while your ticketing runs smoothly.

Property mapping gives you flexibility. Map ticket data to any HubSpot property - standard or custom. Event names, ticket types, registration fields, and purchase amounts go exactly where you need them.

Marketing automation triggers on real behavior. Segment contacts by events attended, total spending, or ticket types purchased. Build workflows that respond to actual customer actions.

OAuth 2.0 keeps everything secure. Connect once, stay connected. Automatic token refresh means no manual reconnection required.

Make HubSpot the command center for your event marketing. Know your customers better than ever.

---

## Detailed Description

The HubSpot Integration microservice connects your event ticketing platform with HubSpot CRM through the HubSpot API. It enables bidirectional synchronization of contacts, deals, and companies for comprehensive customer relationship management.

### Supported HubSpot Objects

The integration works with core HubSpot objects:

- **Contacts**: Individual people who buy tickets or register for events
- **Deals**: Sales opportunities for ticket packages, sponsorships, or corporate sales
- **Companies**: Organizations making bulk purchases or requiring invoicing
- **Tickets**: Support tickets (HubSpot's service object, if needed)

### How Sync Works

When events occur in your platform, data flows to HubSpot:

| Platform Event | HubSpot Action |
|----------------|----------------|
| Customer created | Create/update Contact |
| Order completed | Update Contact properties, create Deal |
| Company purchase | Create/update Company, link Contacts |
| Registration completed | Update Contact with custom fields |

### Property Mapping

HubSpot uses "properties" instead of fields. The integration maps:

- Standard properties (email, firstname, lastname, phone)
- Custom properties you create in HubSpot
- Calculated properties based on ticket data

Example mappings:
- `total_purchases` → `total_event_purchases`
- `last_event_date` → `last_event_attended`
- `favorite_event_type` → `preferred_event_category`

### Search and Filter

Query HubSpot records using filters:

```json
{
  "filterGroups": [{
    "filters": [{
      "propertyName": "total_event_purchases",
      "operator": "GTE",
      "value": "500"
    }]
  }]
}
```

Use filters to segment high-value customers, recent buyers, or specific event attendees.

### Webhook Support

HubSpot can notify your platform of changes:

- Contact property updates
- Deal stage changes
- Company modifications
- Manual data corrections

This enables true bidirectional sync where HubSpot changes reflect in your platform.

---

## Features

### Contact Management
- Contact creation and sync
- Property mapping (standard & custom)
- Contact search and filtering
- Lifecycle stage tracking
- Contact lists integration

### Deal Tracking
- Deal creation
- Deal stage management
- Revenue tracking
- Deal-to-Contact association
- Pipeline management

### Company Records
- Company creation and sync
- Contact-Company associations
- Company properties
- Parent-child company relationships

### Sync Options
- Bidirectional sync
- Real-time sync on events
- Scheduled batch sync
- Manual sync trigger
- Incremental updates

### Security
- OAuth 2.0 authentication
- Automatic token refresh
- Secure credential storage
- Scope-based permissions

### Monitoring
- Sync logging
- Error tracking
- Webhook history
- Debug mode

---

## Use Cases

### Marketing Segmentation
Segment contacts by event attendance, purchase value, or ticket types. Create HubSpot lists for targeted email campaigns. Send personalized content based on actual behavior.

### Sales Pipeline
Track corporate ticket sales as Deals. Move opportunities through your pipeline. Forecast event revenue alongside other business.

### Customer Lifecycle
Track customers from first purchase to loyal repeat buyer. Automate lifecycle stage updates based on purchase frequency and value.

### Event Marketing Automation
Trigger workflows when customers buy tickets. Send confirmation sequences, pre-event information, and post-event follow-ups automatically.

### Company Management
Link multiple contacts to company records. Track B2B ticket sales by organization. Manage corporate accounts and bulk purchases.

### Support Integration
Create HubSpot tickets when customers have issues. Track resolution alongside purchase history. Provide context-aware support.

---

## Technical Documentation

### Overview

The HubSpot Integration microservice connects to HubSpot via their v3 API using OAuth 2.0. It handles contact, deal, and company operations with bidirectional sync capabilities.

### Prerequisites

- HubSpot account (Free CRM or paid tier)
- Developer account for OAuth app
- API scopes: contacts, deals, companies

### Configuration

```php
'hubspot' => [
    'client_id' => env('HUBSPOT_CLIENT_ID'),
    'client_secret' => env('HUBSPOT_CLIENT_SECRET'),
    'redirect_uri' => env('HUBSPOT_REDIRECT_URI'),
    'scopes' => ['crm.objects.contacts.read', 'crm.objects.contacts.write',
                 'crm.objects.deals.read', 'crm.objects.deals.write',
                 'crm.objects.companies.read', 'crm.objects.companies.write'],
    'sync' => [
        'contacts' => true,
        'deals' => true,
        'companies' => true,
    ],
]
```

### API Endpoints

#### OAuth Authorization

```
GET /api/integrations/hubspot/auth
```

Returns HubSpot OAuth authorization URL.

#### OAuth Callback

```
POST /api/integrations/hubspot/callback
```

Handles OAuth callback and stores tokens.

#### Connection Status

```
GET /api/integrations/hubspot/connection
```

**Response:**
```json
{
  "connected": true,
  "portal_id": "12345678",
  "hub_domain": "yourcompany.hubspot.com",
  "last_sync": "2025-01-15T10:30:00Z"
}
```

#### Sync Contacts

```
POST /api/integrations/hubspot/sync/contacts
```

#### Create/Update Contact

```
POST /api/integrations/hubspot/contacts
```

**Request:**
```json
{
  "email": "customer@example.com",
  "properties": {
    "firstname": "Ion",
    "lastname": "Popescu",
    "phone": "+40721234567",
    "total_event_purchases": 500,
    "last_event_attended": "Summer Festival 2025"
  }
}
```

#### Search Contacts

```
POST /api/integrations/hubspot/contacts/search
```

**Request:**
```json
{
  "filterGroups": [{
    "filters": [{
      "propertyName": "email",
      "operator": "EQ",
      "value": "customer@example.com"
    }]
  }],
  "properties": ["firstname", "lastname", "email", "total_event_purchases"]
}
```

#### Create Deal

```
POST /api/integrations/hubspot/deals
```

**Request:**
```json
{
  "properties": {
    "dealname": "Corporate Tickets - Summer Festival",
    "amount": 5000,
    "dealstage": "qualifiedtobuy",
    "pipeline": "default"
  },
  "associations": {
    "contacts": ["contact_123"],
    "companies": ["company_456"]
  }
}
```

#### Get Property Mappings

```
GET /api/integrations/hubspot/mappings
```

#### Update Property Mappings

```
PUT /api/integrations/hubspot/mappings
```

### Sync Service

```php
class HubSpotSyncService
{
    public function syncContact(Customer $customer): array
    {
        $properties = $this->mapToHubSpot($customer);

        // Search for existing contact
        $existing = $this->searchByEmail($customer->email);

        if ($existing) {
            return $this->client->contacts()->update($existing['id'], $properties);
        }

        return $this->client->contacts()->create($properties);
    }

    public function createDealFromOrder(Order $order): array
    {
        return $this->client->deals()->create([
            'properties' => [
                'dealname' => "Order #{$order->number}",
                'amount' => $order->total,
                'dealstage' => 'closedwon',
            ],
            'associations' => [
                'contacts' => [$order->customer->hubspot_id],
            ],
        ]);
    }
}
```

### Database Schema

| Table | Description |
|-------|-------------|
| `hubspot_connections` | OAuth tokens and portal info |
| `hubspot_sync_logs` | Sync operation history |
| `hubspot_property_mappings` | Property mapping config |

### Error Handling

| Error | Description | Resolution |
|-------|-------------|------------|
| 401 | Unauthorized | Refresh token |
| 409 | Conflict | Record already exists, update instead |
| 429 | Rate limited | Implement backoff |
| 400 | Bad request | Check property names and values |

### Rate Limits

HubSpot API limits:
- 100 requests per 10 seconds
- 500,000 requests per day (varies by tier)

Implement request queuing for bulk operations.

### Webhooks

Configure HubSpot webhooks for bidirectional sync:

```php
// Webhook endpoint
POST /api/webhooks/hubspot

// Handle incoming changes
public function handleWebhook(Request $request): void
{
    foreach ($request->input('events') as $event) {
        if ($event['subscriptionType'] === 'contact.propertyChange') {
            $this->syncFromHubSpot($event['objectId']);
        }
    }
}
```

### Testing

1. Create HubSpot developer test account
2. Configure OAuth app with test portal
3. Test contact creation and updates
4. Verify property mappings
5. Test deal associations
