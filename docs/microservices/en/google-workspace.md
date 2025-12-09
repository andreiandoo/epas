# Google Workspace Integration

## Short Presentation

Connect your event platform to Google's productivity suite. Google Workspace Integration syncs your data with Google Drive, Calendar, and Gmail. Seamless workflows where your team already lives.

Google Workspace powers modern work. Now your event operations flow naturally into Drive, Calendar, and Gmail. Files stored, events scheduled, emails sent - all automated.

Google Drive organizes your event files. Export reports and attendee lists directly to Drive folders. Share with stakeholders through familiar interfaces. Cloud storage with Google's security.

Google Calendar keeps schedules in sync. Create calendar events for your ticketed events. Send invitations automatically. See capacity and timing at a glance.

Gmail sends professional communications. Order confirmations, event reminders, follow-up messages. Reliable delivery through Google's infrastructure.

Shared Drives enable team collaboration. Event files accessible to your entire team. No more hunting through email attachments. Everything in one organized place.

OAuth 2.0 ensures secure, granular access. Request only the permissions you need. Users authorize through familiar Google flows. Security and convenience together.

Work smarter with Google. Your events, your data, your workspace.

---

## Detailed Description

The Google Workspace Integration microservice connects your event ticketing platform with Google's productivity applications. It enables file management, calendar synchronization, and email communication through a unified integration.

### Integration Components

| Service | Capabilities |
|---------|--------------|
| Google Drive | File uploads, folder management, sharing, Shared Drives |
| Google Calendar | Event creation, invitations, reminders, availability |
| Gmail | Email sending, templates, tracking, attachments |

### Authentication

Google OAuth 2.0 provides secure authorization:
- Granular permission scoping
- User or service account authentication
- Domain-wide delegation for enterprise
- Automatic token refresh

### Data Flow

| Direction | Description |
|-----------|-------------|
| Platform → Drive | Upload reports, exports, documents |
| Platform → Calendar | Create and update events |
| Platform → Gmail | Send email communications |
| Google Workspace → Platform | Receive webhook notifications |

---

## Features

### Google Drive Integration
- Upload files to My Drive or Shared Drives
- Create and organize folders
- Export reports (PDF, Excel, CSV)
- Generate shareable links
- Set file permissions
- Real-time collaboration support

### Google Calendar Integration
- Create calendar events
- Send invitations to attendees
- Set reminders and notifications
- Manage recurring events
- Check availability
- Support for multiple calendars

### Gmail Integration
- Send transactional emails
- HTML email support
- File attachments
- Template system
- Delivery tracking
- Reply tracking

### Team Collaboration
- Shared Drive support
- Team folder organization
- Collaborative document access
- Permission management
- Activity logging

### Authentication
- OAuth 2.0 secure auth
- Service account support
- Domain-wide delegation
- Multi-user access
- Permission scoping

---

## Use Cases

### Automated Reporting
Daily sales reports upload to Drive automatically. Finance team accesses data through familiar Google interfaces. No manual exports needed.

### Event Scheduling
Sync ticketed events to team calendars. Coordinate staff schedules. Block time for setup and teardown. Everyone sees the same schedule.

### Customer Communications
Send professional emails through Gmail. Order confirmations with attached tickets. Event reminders before show time. Post-event surveys and thank-you messages.

### Team File Sharing
Store event materials in Shared Drives. Marketing assets, operational checklists, vendor contracts. Access from anywhere, collaborate in real-time.

### Stakeholder Updates
Share live reports with stakeholders via Drive links. Update once, everyone sees current data. Perfect for sponsors, partners, and executives.

---

## Technical Documentation

### Configuration

```php
'google_workspace' => [
    'client_id' => env('GOOGLE_CLIENT_ID'),
    'client_secret' => env('GOOGLE_CLIENT_SECRET'),
    'redirect_uri' => env('GOOGLE_REDIRECT_URI'),
    'scopes' => [
        'https://www.googleapis.com/auth/drive.file',
        'https://www.googleapis.com/auth/calendar',
        'https://www.googleapis.com/auth/gmail.send',
    ],
    // For domain-wide delegation
    'service_account_credentials' => env('GOOGLE_SERVICE_ACCOUNT_JSON'),
    'impersonate_user' => env('GOOGLE_IMPERSONATE_EMAIL'),
]
```

### API Endpoints

#### OAuth Authorization

```
GET /api/integrations/google-workspace/auth
```

#### Upload to Drive

```
POST /api/integrations/google-workspace/drive/upload
```

**Request:**
```json
{
  "file_name": "Sales Report - Event 123.pdf",
  "mime_type": "application/pdf",
  "folder_id": "1ABC...",
  "content_base64": "...",
  "shared_drive_id": "0BCD..."
}
```

**Response:**
```json
{
  "success": true,
  "file_id": "1XYZ...",
  "web_view_link": "https://drive.google.com/file/d/1XYZ.../view",
  "web_content_link": "https://drive.google.com/uc?id=1XYZ..."
}
```

#### Create Folder

```
POST /api/integrations/google-workspace/drive/folders
```

**Request:**
```json
{
  "name": "Summer Festival 2025",
  "parent_id": "1ABC...",
  "shared_drive_id": "0BCD..."
}
```

#### Export Report to Drive

```
POST /api/integrations/google-workspace/drive/export
```

**Request:**
```json
{
  "report_type": "attendees",
  "event_id": "evt_123",
  "format": "xlsx",
  "folder_id": "1ABC...",
  "include_columns": ["name", "email", "ticket_type", "checked_in"]
}
```

#### Create Calendar Event

```
POST /api/integrations/google-workspace/calendar/events
```

**Request:**
```json
{
  "calendar_id": "primary",
  "summary": "Summer Festival 2025",
  "description": "Annual summer music festival...",
  "location": "Central Park Arena",
  "start": {
    "dateTime": "2025-07-15T18:00:00",
    "timeZone": "Europe/Bucharest"
  },
  "end": {
    "dateTime": "2025-07-15T23:00:00",
    "timeZone": "Europe/Bucharest"
  },
  "attendees": [
    {"email": "team@company.com"},
    {"email": "operations@company.com"}
  ],
  "reminders": {
    "useDefault": false,
    "overrides": [
      {"method": "email", "minutes": 1440},
      {"method": "popup", "minutes": 60}
    ]
  }
}
```

**Response:**
```json
{
  "success": true,
  "event_id": "abc123...",
  "html_link": "https://calendar.google.com/event?eid=..."
}
```

#### Send Email via Gmail

```
POST /api/integrations/google-workspace/gmail/send
```

**Request:**
```json
{
  "to": ["customer@example.com"],
  "cc": ["support@company.com"],
  "subject": "Your Tickets for Summer Festival 2025",
  "body_html": "<html><body>...</body></html>",
  "body_text": "Plain text fallback...",
  "attachments": [
    {
      "filename": "tickets.pdf",
      "mime_type": "application/pdf",
      "content_base64": "..."
    }
  ],
  "reply_to": "events@company.com"
}
```

**Response:**
```json
{
  "success": true,
  "message_id": "msg123...",
  "thread_id": "thread456..."
}
```

#### Sync Event to Calendar

```
POST /api/integrations/google-workspace/calendar/sync
```

**Request:**
```json
{
  "event_id": "evt_123",
  "calendar_id": "team-calendar@group.calendar.google.com",
  "include_details": true,
  "sync_updates": true
}
```

### Service Account Setup

For server-to-server integration:

1. Create service account in Google Cloud Console
2. Enable domain-wide delegation
3. Configure required OAuth scopes in admin console
4. Download credentials JSON

```php
$client = new Google_Client();
$client->setAuthConfig('/path/to/service-account.json');
$client->setScopes([
    Google_Service_Drive::DRIVE_FILE,
    Google_Service_Calendar::CALENDAR,
    Google_Service_Gmail::GMAIL_SEND,
]);
$client->setSubject('user@domain.com'); // Impersonation
```

### Webhook Notifications

Configure push notifications for Drive changes:

```
POST /api/integrations/google-workspace/drive/watch
```

**Request:**
```json
{
  "file_id": "1ABC...",
  "webhook_url": "https://your-platform.com/webhooks/drive",
  "expiration": "2025-02-01T00:00:00Z"
}
```

### Shared Drive Operations

```php
// List Shared Drives
GET /api/integrations/google-workspace/drive/shared-drives

// Create folder in Shared Drive
POST /api/integrations/google-workspace/drive/folders
{
  "name": "Q1 2025 Events",
  "shared_drive_id": "0ACD..."
}

// Upload to Shared Drive
POST /api/integrations/google-workspace/drive/upload
{
  "shared_drive_id": "0ACD...",
  "supports_all_drives": true,
  ...
}
```

### Database Schema

| Table | Description |
|-------|-------------|
| `google_workspace_connections` | OAuth tokens |
| `google_workspace_files` | Uploaded file references |
| `google_workspace_calendar_syncs` | Calendar sync mappings |
| `google_workspace_email_logs` | Sent email history |
| `google_workspace_folders` | Folder structure cache |

