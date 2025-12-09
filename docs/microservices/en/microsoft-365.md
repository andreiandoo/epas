# Microsoft 365 Integration

## Short Presentation

Connect your event platform to the Microsoft ecosystem. Microsoft 365 Integration syncs your tickets, orders, and customer data with OneDrive, Outlook, Teams, and Calendar. Work where your team already works.

Microsoft 365 powers enterprise communication. Now your event data flows into that ecosystem automatically. Files in OneDrive, notifications in Teams, events in Outlook Calendar.

OneDrive stores your event files securely. Export reports, attendee lists, and invoices directly to OneDrive folders. Organize by event, by date, or by type. Cloud storage with enterprise security.

Teams notifications keep everyone informed. Sales alerts in dedicated channels. Check-in updates for operations. VIP arrivals for customer success. Real-time awareness without switching tools.

Outlook Calendar integration syncs your events. Create calendar entries for scheduled events. Send invitations with event details. Coordinate schedules across your organization.

Email through Outlook delivers branded communications. Confirmation emails, reminder messages, post-event follow-ups. Professional delivery through your Microsoft tenant.

OAuth 2.0 with Azure AD ensures secure access. Enterprise-grade authentication using your existing identity provider. Single sign-on convenience with full security.

Bring your events into Microsoft's productivity suite. Unified workflows, unified data.

---

## Detailed Description

The Microsoft 365 Integration microservice connects your event ticketing platform with Microsoft's productivity suite. It enables file storage, team messaging, calendar management, and email communication through a unified integration.

### Integration Components

| Service | Capabilities |
|---------|--------------|
| OneDrive | File uploads, folder management, sharing |
| Teams | Channel messages, chat notifications, adaptive cards |
| Outlook Calendar | Event creation, attendee invitations, reminders |
| Outlook Mail | Email sending, template support, tracking |

### Authentication

Azure AD OAuth 2.0 provides enterprise authentication:
- Single sign-on with organizational accounts
- Granular permission scoping
- Admin consent for organization-wide access
- Token refresh handling

### Data Flow

| Direction | Description |
|-----------|-------------|
| Platform → OneDrive | Upload reports, exports, documents |
| Platform → Teams | Send notifications and updates |
| Platform → Calendar | Create and update events |
| Platform → Outlook | Send email communications |
| Microsoft 365 → Platform | Receive webhook callbacks |

---

## Features

### OneDrive Integration
- File upload to user or shared drives
- Folder creation and organization
- Report exports (PDF, Excel, CSV)
- Attendee list uploads
- Invoice and receipt storage
- Sharing link generation

### Teams Notifications
- Channel message posting
- Chat notifications
- Adaptive Card formatting
- Action buttons in messages
- @mention support
- Rich media attachments

### Calendar Integration
- Create Outlook calendar events
- Send meeting invitations
- Sync event schedules
- Automatic reminders
- Attendee management
- Location and online meeting links

### Email Integration
- Send transactional emails
- Template-based messages
- Branded communications
- Delivery tracking
- HTML and plain text
- Attachment support

### Authentication
- Azure AD OAuth 2.0
- Single sign-on
- Multi-tenant support
- Admin consent flow
- Permission scoping
- Token management

---

## Use Cases

### Enterprise Event Management
Large organizations manage events through familiar Microsoft tools. Reports land in SharePoint, notifications flow through Teams, calendars stay synchronized across departments.

### Team Coordination
Event operations teams receive real-time updates in Teams channels. Sales milestones, capacity warnings, VIP check-ins - all visible where teams already communicate.

### Automated Reporting
Daily sales reports upload to OneDrive automatically. Weekly summaries drop into shared folders. Finance team accesses data through familiar interfaces.

### Calendar-Based Planning
Event schedules sync to organizational calendars. Team members see upcoming events. Meeting rooms can be booked. Conflicts become visible early.

### Client Communications
Send professional emails through your Microsoft tenant. Branded confirmations, personalized follow-ups, and automated reminders - all from your domain.

---

## Technical Documentation

### Configuration

```php
'microsoft_365' => [
    'client_id' => env('AZURE_CLIENT_ID'),
    'client_secret' => env('AZURE_CLIENT_SECRET'),
    'tenant_id' => env('AZURE_TENANT_ID'),
    'redirect_uri' => env('AZURE_REDIRECT_URI'),
    'scopes' => [
        'Files.ReadWrite.All',
        'Mail.Send',
        'Calendars.ReadWrite',
        'ChannelMessage.Send',
    ],
]
```

### API Endpoints

#### OAuth Authorization

```
GET /api/integrations/microsoft-365/auth
```

#### Upload to OneDrive

```
POST /api/integrations/microsoft-365/onedrive/upload
```

**Request:**
```json
{
  "file_type": "report",
  "format": "pdf",
  "event_id": "evt_123",
  "folder_path": "/Events/2025/Reports",
  "data_type": "attendees"
}
```

**Response:**
```json
{
  "success": true,
  "file_id": "01ABC...",
  "web_url": "https://onedrive.live.com/...",
  "download_url": "https://..."
}
```

#### Send Teams Message

```
POST /api/integrations/microsoft-365/teams/message
```

**Request:**
```json
{
  "team_id": "team-uuid",
  "channel_id": "channel-uuid",
  "message": {
    "type": "adaptive_card",
    "content": {
      "type": "AdaptiveCard",
      "body": [
        {
          "type": "TextBlock",
          "text": "New Ticket Sale!",
          "weight": "bolder"
        },
        {
          "type": "FactSet",
          "facts": [
            {"title": "Event", "value": "Summer Festival 2025"},
            {"title": "Tickets", "value": "4"},
            {"title": "Total", "value": "$200.00"}
          ]
        }
      ],
      "actions": [
        {
          "type": "Action.OpenUrl",
          "title": "View Order",
          "url": "https://platform.com/orders/123"
        }
      ]
    }
  }
}
```

#### Create Calendar Event

```
POST /api/integrations/microsoft-365/calendar/events
```

**Request:**
```json
{
  "subject": "Summer Festival 2025",
  "start": "2025-07-15T18:00:00",
  "end": "2025-07-15T23:00:00",
  "timezone": "Europe/Bucharest",
  "location": "Central Park Arena",
  "body": "Annual summer music festival featuring...",
  "attendees": ["team@company.com"],
  "is_online_meeting": false,
  "reminder_minutes": 1440
}
```

#### Send Email

```
POST /api/integrations/microsoft-365/mail/send
```

**Request:**
```json
{
  "to": ["customer@example.com"],
  "subject": "Your Tickets for Summer Festival",
  "body": "<html>...</html>",
  "body_type": "html",
  "attachments": [
    {
      "name": "tickets.pdf",
      "content_type": "application/pdf",
      "content_base64": "..."
    }
  ],
  "save_to_sent": true
}
```

### Teams Adaptive Cards

Example notification card:

```json
{
  "$schema": "http://adaptivecards.io/schemas/adaptive-card.json",
  "type": "AdaptiveCard",
  "version": "1.4",
  "body": [
    {
      "type": "Container",
      "items": [
        {
          "type": "TextBlock",
          "text": "Order Completed",
          "weight": "bolder",
          "size": "medium"
        },
        {
          "type": "ColumnSet",
          "columns": [
            {
              "type": "Column",
              "items": [
                {"type": "TextBlock", "text": "Customer"},
                {"type": "TextBlock", "text": "Event"},
                {"type": "TextBlock", "text": "Total"}
              ]
            },
            {
              "type": "Column",
              "items": [
                {"type": "TextBlock", "text": "John Doe"},
                {"type": "TextBlock", "text": "Summer Festival"},
                {"type": "TextBlock", "text": "$150.00", "color": "good"}
              ]
            }
          ]
        }
      ]
    }
  ],
  "actions": [
    {
      "type": "Action.OpenUrl",
      "title": "View Order",
      "url": "https://platform.com/orders/123"
    }
  ]
}
```

### Webhook Events

Configure webhooks to receive Microsoft 365 events:

```
POST /api/integrations/microsoft-365/webhooks
```

**Request:**
```json
{
  "resource": "/me/mailFolders/inbox/messages",
  "change_type": "created",
  "notification_url": "https://your-platform.com/webhooks/m365",
  "expiration_datetime": "2025-02-01T00:00:00Z"
}
```

### Database Schema

| Table | Description |
|-------|-------------|
| `microsoft_365_connections` | OAuth tokens and tenant info |
| `microsoft_365_files` | Uploaded file references |
| `microsoft_365_teams_configs` | Teams channel configurations |
| `microsoft_365_calendar_syncs` | Calendar sync mappings |
| `microsoft_365_email_logs` | Sent email history |

