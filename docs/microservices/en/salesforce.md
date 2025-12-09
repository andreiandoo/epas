# Salesforce Integration

## Short Presentation

Bring your event ticket sales into the world's leading CRM. Salesforce Integration syncs your customers, orders, and event data directly with Salesforce, giving your sales team complete visibility into every interaction.

Every ticket purchase tells a story. See it in Salesforce. When customers buy tickets, their contact records update automatically. Purchase history, event attendance, and spending patterns flow into your CRM without manual data entry.

Contacts sync bidirectionally. Create a customer in your ticketing platform and they appear in Salesforce. Update a contact in Salesforce and your platform reflects the change. One source of truth, always in sync.

Leads convert to opportunities. Track potential VIP buyers, corporate clients, and group sales through your Salesforce pipeline. Link opportunities to specific events and measure conversion from lead to ticket sale.

Custom field mapping puts you in control. Map your ticket data to any standard or custom Salesforce field. Event names, ticket types, purchase dates, and custom registration fields all flow where you need them.

SOQL query support enables powerful filtering. Sync only the records that matter. Filter by date, event, purchase value, or any other criteria.

OAuth 2.0 authentication keeps connections secure. Industry-standard security with automatic token refresh means reliable connections without manual intervention.

See your complete customer picture in Salesforce. Turn ticket buyers into lifetime relationships.

---

## Detailed Description

The Salesforce Integration microservice connects your event ticketing platform with Salesforce CRM through the Salesforce REST API. It enables bidirectional synchronization of contacts, leads, opportunities, and accounts.

### Supported Salesforce Objects

The integration works with standard Salesforce objects:

- **Contact**: Individual customer records with personal and contact details
- **Lead**: Potential customers before conversion
- **Opportunity**: Sales deals linked to events or ticket packages
- **Account**: Company records for B2B ticket sales

Custom objects are supported through field mapping configuration.

### Sync Directions

| Direction | Description |
|-----------|-------------|
| Platform → Salesforce | Push customer data when tickets are purchased |
| Salesforce → Platform | Pull updates made in Salesforce back |
| Bidirectional | Keep both systems in sync automatically |

### Field Mapping

Configure how your platform data maps to Salesforce fields:

| Platform Field | Salesforce Field | Notes |
|----------------|------------------|-------|
| email | Email | Primary identifier |
| first_name | FirstName | Standard field |
| last_name | LastName | Standard field |
| phone | Phone | Standard field |
| total_purchases | Custom_Total__c | Custom field example |
| last_event | Custom_Last_Event__c | Custom field example |

Create unlimited custom mappings to match your Salesforce configuration.

### Sync Triggers

Data syncs automatically when:

- New customer created
- Order completed
- Customer profile updated
- Manual sync triggered
- Scheduled sync runs

### SOQL Integration

Query Salesforce data directly:

```sql
SELECT Id, FirstName, LastName, Email
FROM Contact
WHERE Custom_Total__c > 1000
ORDER BY CreatedDate DESC
LIMIT 100
```

Use queries to segment customers or pull data for reporting.

### Audit Trail

Every sync operation is logged:

- What data was synced
- When the sync occurred
- Whether it succeeded or failed
- Error details for troubleshooting

---

## Features

### Object Sync
- Contact creation and sync
- Lead management
- Opportunity tracking
- Account management
- Custom object support

### Field Management
- Custom field mapping
- Standard field support
- Lookup field handling
- Picklist value mapping
- Formula field reading

### Sync Options
- Bidirectional sync
- One-way push/pull
- Real-time sync on events
- Scheduled batch sync
- Manual sync trigger

### Query & Filter
- SOQL query support
- Record filtering
- Custom query builder
- Pagination handling
- Bulk data operations

### Security
- OAuth 2.0 authentication
- Automatic token refresh
- Secure credential storage
- Connected app setup
- Permission set support

### Monitoring
- Sync logging and audit
- Error tracking
- Success/failure metrics
- Sync history
- Debug mode

---

## Use Cases

### B2B Event Sales
Create Accounts for companies purchasing corporate tickets. Link Contacts as employees. Track Opportunities for pending group sales. Your sales team manages everything in Salesforce.

### VIP Customer Tracking
Flag high-value ticket buyers in Salesforce. See their complete purchase history. Enable your sales team to provide personalized service and upsell VIP packages.

### Event-Based Campaigns
Segment contacts by event attendance. Create Salesforce campaigns targeting past attendees. Measure marketing ROI from lead to ticket purchase.

### Corporate Client Management
Track companies that buy group tickets. Manage contract renewals and season passes. Link multiple contacts to parent accounts for complete visibility.

### Sales Pipeline Integration
Create Opportunities for large ticket packages or sponsorships. Track deals through your existing Salesforce pipeline. Forecast event revenue alongside other sales.

### Post-Event Follow-Up
Sync attendance data back to Salesforce. Enable sales follow-up with attendees. Track which leads converted from event participation.

---

## Technical Documentation

### Overview

The Salesforce Integration microservice connects to Salesforce via REST API using OAuth 2.0. It handles object CRUD operations, field mapping, and bidirectional synchronization.

### Prerequisites

- Salesforce org (any edition with API access)
- Connected App configured
- API user with appropriate permissions
- Field-level security for sync fields

### Configuration

```php
'salesforce' => [
    'client_id' => env('SALESFORCE_CLIENT_ID'),
    'client_secret' => env('SALESFORCE_CLIENT_SECRET'),
    'redirect_uri' => env('SALESFORCE_REDIRECT_URI'),
    'api_version' => 'v58.0',
    'sandbox' => env('SALESFORCE_SANDBOX', false),
    'sync' => [
        'contacts' => true,
        'leads' => true,
        'opportunities' => true,
        'accounts' => true,
    ],
]
```

### API Endpoints

#### OAuth Authorization

```
GET /api/integrations/salesforce/auth
```

Returns Salesforce OAuth authorization URL.

**Response:**
```json
{
  "auth_url": "https://login.salesforce.com/services/oauth2/authorize?..."
}
```

#### OAuth Callback

```
POST /api/integrations/salesforce/callback
```

Handles OAuth callback, stores tokens.

#### Connection Status

```
GET /api/integrations/salesforce/connection
```

**Response:**
```json
{
  "connected": true,
  "instance_url": "https://yourorg.salesforce.com",
  "user": "admin@yourorg.com",
  "last_sync": "2025-01-15T10:30:00Z"
}
```

#### Sync Contacts

```
POST /api/integrations/salesforce/sync/contacts
```

**Request:**
```json
{
  "direction": "push",
  "filter": {
    "created_after": "2025-01-01"
  }
}
```

#### Get Contact

```
GET /api/integrations/salesforce/contacts/{id}
```

#### Create/Update Contact

```
POST /api/integrations/salesforce/contacts
```

**Request:**
```json
{
  "email": "customer@example.com",
  "first_name": "Ion",
  "last_name": "Popescu",
  "phone": "+40721234567",
  "custom_fields": {
    "Total_Tickets__c": 5,
    "Last_Event__c": "Summer Festival 2025"
  }
}
```

#### Execute SOQL Query

```
POST /api/integrations/salesforce/query
```

**Request:**
```json
{
  "query": "SELECT Id, Email, FirstName FROM Contact WHERE Email != null LIMIT 10"
}
```

#### Get Field Mappings

```
GET /api/integrations/salesforce/mappings
```

#### Update Field Mappings

```
PUT /api/integrations/salesforce/mappings
```

**Request:**
```json
{
  "contact": {
    "email": "Email",
    "first_name": "FirstName",
    "last_name": "LastName",
    "total_spent": "Total_Spent__c"
  }
}
```

### Sync Service

```php
class SalesforceSyncService
{
    public function syncContact(Customer $customer): SalesforceContact
    {
        $data = $this->mapCustomerToSalesforce($customer);

        // Check if contact exists
        $existing = $this->findByEmail($customer->email);

        if ($existing) {
            return $this->client->update('Contact', $existing->Id, $data);
        }

        return $this->client->create('Contact', $data);
    }

    public function pullContacts(array $filter = []): Collection
    {
        $query = $this->buildQuery('Contact', $filter);
        $results = $this->client->query($query);

        foreach ($results as $record) {
            $this->updateLocalCustomer($record);
        }

        return $results;
    }
}
```

### Database Schema

| Table | Description |
|-------|-------------|
| `salesforce_connections` | OAuth tokens and org info |
| `salesforce_sync_logs` | Sync operation history |
| `salesforce_field_mappings` | Field mapping config |
| `salesforce_object_cache` | Cached Salesforce IDs |

### Error Handling

| Error | Description | Resolution |
|-------|-------------|------------|
| INVALID_SESSION_ID | Token expired | Auto-refresh token |
| DUPLICATE_VALUE | Record exists | Update instead of create |
| REQUIRED_FIELD_MISSING | Missing required field | Check field mappings |
| FIELD_INTEGRITY_EXCEPTION | Invalid field value | Validate data format |

### Token Refresh

```php
// Automatic token refresh
if ($this->isTokenExpired()) {
    $newTokens = $this->client->refreshToken($this->refreshToken);
    $this->storeTokens($newTokens);
}
```

### Bulk Operations

For large data volumes:

```php
// Bulk sync (up to 200 records per call)
$batches = $customers->chunk(200);

foreach ($batches as $batch) {
    $this->client->composite('Contact', $batch->toArray());
}
```

### Testing

1. Use Salesforce Sandbox for testing
2. Set `SALESFORCE_SANDBOX=true`
3. Create test Connected App in sandbox
4. Verify field mappings with sample data
5. Test sync in both directions

### Security Best Practices

1. Use dedicated API user with minimal permissions
2. Enable IP restrictions on Connected App
3. Store tokens encrypted
4. Implement audit logging
5. Regular token rotation
