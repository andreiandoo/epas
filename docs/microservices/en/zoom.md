# Zoom Integration

## Short Presentation

Take your events virtual with seamless Zoom integration. Create meetings and webinars automatically when events are published. Sync ticket holders as registrants. Track attendance and manage recordings - all from your ticketing platform.

Virtual events deserve the same professional experience as in-person ones. Zoom Integration bridges your ticketing and video conferencing, eliminating manual setup and data entry.

Automatic meeting creation saves hours. Publish a virtual event and Zoom meeting details generate instantly. Meeting links, passwords, and dial-in information ready for ticket buyers.

Registrant sync ensures smooth access. Ticket holders automatically become Zoom registrants. No manual list uploads, no access issues on event day.

Attendance tracking provides insights. See who joined, how long they stayed, and engagement patterns. Understand your virtual audience like never before.

Webinar support handles large audiences. Scale from intimate meetings to thousands of attendees. Registration, Q&A, and polling all synchronized.

Recording management preserves content. Access cloud recordings, share with attendees, or repurpose for marketing. Your virtual events live on.

OAuth 2.0 security protects your Zoom account. Industry-standard authentication with automatic token refresh. Connect once, stay connected.

Bring your events online without the headaches. Professional virtual experiences, automated.

---

## Detailed Description

The Zoom Integration microservice connects your event ticketing platform with Zoom's meeting and webinar infrastructure. It automates meeting creation, registrant management, and attendance tracking.

### Meeting Types

| Type | Best For | Capacity |
|------|----------|----------|
| Meeting | Interactive sessions | Up to 1,000 |
| Webinar | Presentations | Up to 50,000 |

### Automatic Setup

When you create a virtual event:
1. Zoom meeting/webinar created automatically
2. Settings configured (waiting room, passwords, etc.)
3. Join link generated and stored
4. Event page updated with meeting details

### Registrant Sync

When tickets are purchased:
1. Buyer info captured
2. Registrant added to Zoom meeting
3. Confirmation email with join link sent
4. Attendee list kept synchronized

### Attendance Tracking

After meetings:
- Join/leave times recorded
- Duration calculated
- Attendance reports generated
- Data synced to customer records

---

## Features

### Meeting Management
- Create scheduled meetings
- Create instant meetings
- Update meeting settings
- Delete meetings
- Get meeting details

### Webinar Support
- Create webinars
- Manage panelists
- Registration management
- Q&A and polling
- Practice sessions

### Registrant Sync
- Auto-add registrants
- Sync ticket holder data
- Update registrations
- Remove on refund

### Attendance
- Track join times
- Record duration
- Generate reports
- Sync to CRM

### Recordings
- Access cloud recordings
- Download recordings
- Share recording links
- Delete recordings

### Authentication
- OAuth 2.0 connection
- Automatic token refresh
- Webhook signature verification

---

## Use Cases

### Virtual Conferences
Multi-session conferences with breakout rooms. Attendee tracking across sessions. Professional virtual event experience.

### Online Workshops
Interactive workshops with participant engagement. Screen sharing and collaboration. Attendance certificates based on participation.

### Webinar Series
Educational content at scale. Registration gates content. Recordings extend value.

### Hybrid Events
In-person events with virtual attendance option. Same content, different delivery. Expanded reach without venue limits.

---

## Technical Documentation

### Configuration

```php
'zoom' => [
    'client_id' => env('ZOOM_CLIENT_ID'),
    'client_secret' => env('ZOOM_CLIENT_SECRET'),
    'redirect_uri' => env('ZOOM_REDIRECT_URI'),
    'webhook_secret' => env('ZOOM_WEBHOOK_SECRET'),
]
```

### API Endpoints

#### OAuth Authorization

```
GET /api/integrations/zoom/auth
```

#### Create Meeting

```
POST /api/integrations/zoom/meetings
```

**Request:**
```json
{
  "topic": "Summer Workshop 2025",
  "type": 2,
  "start_time": "2025-07-15T14:00:00Z",
  "duration": 120,
  "timezone": "Europe/Bucharest",
  "settings": {
    "waiting_room": true,
    "registration_type": 2,
    "approval_type": 0
  }
}
```

#### Add Registrant

```
POST /api/integrations/zoom/meetings/{meetingId}/registrants
```

**Request:**
```json
{
  "email": "attendee@example.com",
  "first_name": "Ion",
  "last_name": "Popescu"
}
```

#### Get Attendance Report

```
GET /api/integrations/zoom/meetings/{meetingId}/participants
```

### Webhook Events

| Event | Description |
|-------|-------------|
| meeting.started | Meeting began |
| meeting.ended | Meeting concluded |
| meeting.participant_joined | Someone joined |
| meeting.participant_left | Someone left |
| recording.completed | Recording ready |

### Database Schema

| Table | Description |
|-------|-------------|
| `zoom_connections` | OAuth tokens |
| `zoom_meetings` | Meeting records |
| `zoom_registrants` | Synced registrants |
| `zoom_attendance` | Attendance data |
