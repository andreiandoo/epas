# Airtable Integration

## Short Presentation

Power your workflows with Airtable's flexible database platform. Airtable Integration syncs your orders, tickets, and customer data to Airtable bases, enabling custom views, automations, and team collaboration that spreadsheets can't match.

Airtable combines spreadsheet simplicity with database power. Your event data becomes actionable - sortable, filterable, linkable, and automatable.

Push data automatically as events occur. New orders appear in your base instantly. Ticket sales populate tables in real-time. Customer records update continuously.

Bidirectional sync keeps everything aligned. Update a record in Airtable and changes flow back to your platform. True two-way synchronization.

Custom field mapping connects your data correctly. Map ticket fields to Airtable columns. Link related records. Preserve data types and relationships.

Multiple bases organize complex operations. Sales data in one base, customer management in another, event planning in a third. Connected but organized.

OAuth or Personal Access Token - your choice. Secure OAuth for full integration, or simple PAT for quick setup. Flexibility in authentication.

Build custom workflows on top of your data. Airtable's automation tools trigger from your synced records. Create, assign, notify - automatically.

Make your event data work harder. Beyond storage into action.

---

## Detailed Description

The Airtable Integration microservice connects your event ticketing platform with Airtable's database platform. It enables pushing records to Airtable tables and optionally syncing changes back.

### Data Flow

| Direction | Description |
|-----------|-------------|
| Platform → Airtable | Push orders, tickets, customers to tables |
| Airtable → Platform | Sync updates back (bidirectional mode) |

### Supported Data Types

- **Orders**: Complete order records with line items
- **Tickets**: Individual ticket records with attendee data
- **Customers**: Customer profiles with purchase history
- **Events**: Event records with configuration

### Field Mapping

Airtable supports various field types:
- Single line text
- Long text
- Number, Currency
- Date, DateTime
- Single/Multiple select
- Linked records
- Attachments

Map your platform fields to appropriate Airtable field types for optimal functionality.

### Sync Modes

- **Push Only**: Data flows to Airtable, no sync back
- **Bidirectional**: Changes in either system sync to the other
- **Scheduled**: Periodic sync at configured intervals
- **Real-time**: Immediate sync on record changes

---

## Features

### Data Sync
- Orders export
- Tickets export
- Customers export
- Events export

### Field Mapping
- Custom field mapping
- Field type conversion
- Linked record support
- Attachment handling

### Sync Options
- OAuth 2.0 authentication
- Personal Access Token support
- Real-time or scheduled
- Bidirectional sync

### Base Management
- List available bases
- List tables in bases
- Create tables
- Manage fields

### Automation Support
- Webhook triggers
- Record creation events
- Update notifications

---

## Use Cases

### Event Planning Workflow
Track event planning in Airtable with linked ticket sales data. See which events are selling well. Coordinate planning with sales reality.

### Customer Success
Manage customer relationships in Airtable. Link purchase records to customer profiles. Track engagement and follow-ups.

### Operations Dashboard
Build visual dashboards with Airtable views. Kanban boards for order status. Calendar views for events.

### Team Collaboration
Share bases with team members. Assign tasks based on ticket data. Collaborate without sharing platform access.

---

## Technical Documentation

### Configuration

```php
'airtable' => [
    'client_id' => env('AIRTABLE_CLIENT_ID'),
    'client_secret' => env('AIRTABLE_CLIENT_SECRET'),
    'redirect_uri' => env('AIRTABLE_REDIRECT_URI'),
    // Or use PAT
    'personal_access_token' => env('AIRTABLE_PAT'),
]
```

### API Endpoints

#### OAuth Authorization

```
GET /api/integrations/airtable/auth
```

#### List Bases

```
GET /api/integrations/airtable/bases
```

#### List Tables

```
GET /api/integrations/airtable/bases/{baseId}/tables
```

#### Sync Records

```
POST /api/integrations/airtable/sync
```

**Request:**
```json
{
  "base_id": "appXXXXXXX",
  "table_id": "tblXXXXXXX",
  "data_type": "orders",
  "mode": "push",
  "field_mapping": {
    "order_number": "Order Number",
    "customer_email": "Customer Email",
    "total": "Total Amount"
  }
}
```

#### Create Record

```
POST /api/integrations/airtable/bases/{baseId}/tables/{tableId}/records
```

**Request:**
```json
{
  "fields": {
    "Order Number": "ORD-2025-001",
    "Customer Email": "customer@example.com",
    "Total Amount": 150.00
  }
}
```

### Database Schema

| Table | Description |
|-------|-------------|
| `airtable_connections` | OAuth tokens or PAT |
| `airtable_sync_configs` | Sync configuration |
| `airtable_field_mappings` | Field mapping config |
| `airtable_sync_logs` | Sync operation history |
