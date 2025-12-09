# Google Sheets Integration

## Short Presentation

Turn your ticket data into actionable spreadsheets. Google Sheets Integration exports orders, tickets, customers, and event analytics directly to Google Sheets. Build real-time dashboards, automate reports, and share live data with your team.

Spreadsheets power business decisions. Now your event data flows there automatically. No manual exports, no outdated numbers, no copy-paste errors.

Real-time sync keeps sheets current. New orders append automatically as they come in. Your sales dashboard updates while you watch. Live data at your fingertips.

Scheduled exports run themselves. Daily sales reports every morning. Weekly summaries for management. Monthly analytics for planning. Set it and forget it.

Custom column mapping puts you in control. Choose what data goes where. Map fields to match your existing templates. Export exactly what you need.

Multiple spreadsheets organize your data. Orders in one sheet, customers in another, events in a third. Clear organization, easy access.

Share with your team effortlessly. Google Sheets collaboration means everyone sees the same data. Finance, marketing, operations - all aligned.

OAuth 2.0 security protects your Google account. Secure authorization, no passwords stored. Connect with confidence.

Transform raw data into insights. Make better decisions faster.

---

## Detailed Description

The Google Sheets Integration microservice connects your event ticketing platform with Google Sheets, enabling automated data export, real-time synchronization, and custom reporting.

### Export Types

| Data Type | Contents |
|-----------|----------|
| Orders | Order details, totals, status, customer reference |
| Tickets | Individual tickets, attendees, check-in status |
| Customers | Contact info, purchase history, preferences |
| Events | Event details, sales summary, attendance |

### Sync Modes

- **Full Sync**: Export all data, replacing existing content
- **Incremental**: Add only new records since last sync
- **Append**: Add new rows without touching existing data
- **Real-time**: Continuous sync as events occur

### Scheduling Options

| Frequency | Use Case |
|-----------|----------|
| Real-time | Live dashboards, urgent monitoring |
| Hourly | Active sales periods |
| Daily | Regular reporting |
| Weekly | Management summaries |

### Column Mapping

Configure which fields appear and in what order:
- Select fields to include
- Set column order
- Rename headers
- Apply formatting rules

---

## Features

### Data Export
- Export orders
- Export tickets with attendees
- Export customer lists
- Export event analytics

### Sync Options
- Real-time sync
- Scheduled sync
- Manual sync
- Full or incremental

### Spreadsheet Management
- Create new spreadsheets
- Select existing spreadsheets
- Multiple sheets per type
- Automatic headers

### Customization
- Custom column mapping
- Field selection
- Header formatting
- Date/number formatting

### Authentication
- OAuth 2.0 secure auth
- Token refresh
- Multi-account support
- Permission scoping

### Monitoring
- Sync history
- Job tracking
- Error logging
- Delivery confirmation

---

## Use Cases

### Real-Time Sales Dashboard
Watch ticket sales as they happen. Create charts and graphs that update automatically. Share with stakeholders for live visibility.

### Daily Finance Reports
Export daily orders for reconciliation. Automated reports every morning. Finance team always has fresh data.

### Marketing Analysis
Export customer data for segmentation. Analyze purchase patterns. Plan targeted campaigns based on real behavior.

### Event Day Operations
Live attendee lists for check-in teams. Real-time attendance tracking. Capacity monitoring during events.

### Post-Event Reporting
Comprehensive event analytics. Attendance reports for sponsors. Data for planning future events.

---

## Technical Documentation

### Configuration

```php
'google_sheets' => [
    'client_id' => env('GOOGLE_CLIENT_ID'),
    'client_secret' => env('GOOGLE_CLIENT_SECRET'),
    'redirect_uri' => env('GOOGLE_REDIRECT_URI'),
    'scopes' => [
        'https://www.googleapis.com/auth/spreadsheets',
        'https://www.googleapis.com/auth/drive.file',
    ],
]
```

### API Endpoints

#### OAuth Authorization

```
GET /api/integrations/google-sheets/auth
```

#### Create Spreadsheet

```
POST /api/integrations/google-sheets/spreadsheets
```

**Request:**
```json
{
  "title": "Event Sales Report",
  "data_type": "orders",
  "columns": ["order_number", "customer_email", "total", "status", "created_at"]
}
```

#### Sync Data

```
POST /api/integrations/google-sheets/sync
```

**Request:**
```json
{
  "spreadsheet_id": "1ABC...",
  "data_type": "orders",
  "mode": "incremental",
  "filters": {
    "created_after": "2025-01-01"
  }
}
```

#### Schedule Sync

```
POST /api/integrations/google-sheets/schedules
```

**Request:**
```json
{
  "spreadsheet_id": "1ABC...",
  "frequency": "daily",
  "time": "06:00",
  "timezone": "Europe/Bucharest",
  "data_type": "orders"
}
```

#### Get Sync History

```
GET /api/integrations/google-sheets/sync-history
```

### Database Schema

| Table | Description |
|-------|-------------|
| `google_sheets_connections` | OAuth tokens |
| `google_sheets_spreadsheets` | Linked spreadsheets |
| `google_sheets_sync_jobs` | Scheduled jobs |
| `google_sheets_column_mappings` | Field mappings |
