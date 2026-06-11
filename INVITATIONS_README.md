# Invitations Microservice - Complete Documentation

**Microservice Price:** 1 EUR/month (recurring)
**Category:** Distribution & Access Management
**Version:** 1.0.0

## Table of Contents

1. [Overview](#overview)
2. [Features](#features)
3. [Architecture](#architecture)
4. [Installation](#installation)
5. [Usage Guide](#usage-guide)
6. [API Reference](#api-reference)
7. [CSV Import Format](#csv-import-format)
8. [Status Flow](#status-flow)
9. [Security](#security)
10. [Troubleshooting](#troubleshooting)

---

## Overview

The **Invitations Microservice** provides a complete solution for managing zero-value tickets (invitations) with batch generation, distribution, and tracking capabilities. Perfect for VIP guests, press passes, complimentary tickets, and staff access.

### Key Capabilities

- **Batch Management** - Create and manage invitation batches
- **CSV Import** - Import recipient data with field mapping
- **PDF Generation** - Uses Ticket Templates for professional output
- **Email Distribution** - Queue-based delivery with retry logic
- **Download Tracking** - Monitor invitation downloads
- **Check-in Integration** - Compatible with existing scanners
- **Comprehensive Reporting** - CSV export with full tracking data

---

## Features

### Batch Management

✅ Create batches with planned quantity
✅ Auto-generate unique invitation codes
✅ Link to events and ticket templates
✅ Configurable watermarks
✅ Batch status workflow
✅ Cancel batches with automatic voiding

### Recipient Management

✅ CSV import with drag & drop
✅ Field mapping for flexible formats
✅ Recipient data: name, email, phone, company, title
✅ Optional seat assignment
✅ Import validation with error reporting

### Ticket Integration

✅ Zero-value ticket generation
✅ Unique QR codes with anti-replay protection
✅ Format: `INV:{code}:{ticket_ref}:{checksum}`
✅ Compatible with check-in scanners
✅ Void/invalidate tickets

### Distribution

✅ Individual PDF downloads (signed URLs)
✅ Bulk ZIP downloads for batches
✅ Email delivery with queueing
✅ Chunked sending (configurable size)
✅ Retry with exponential backoff
✅ Per-tenant email templates

### Tracking & Analytics

✅ Multi-stage status tracking
✅ Timestamp tracking: rendered, emailed, downloaded, opened, checked-in
✅ Pixel tracking for email opens (GDPR-compliant)
✅ Download tracking with IP/user agent
✅ Gate and check-in tracking
✅ Comprehensive audit logs

### Reporting

✅ CSV export with full data
✅ Real-time batch statistics
✅ Progress tracking dashboards
✅ Delivery status monitoring

---

## Architecture

### Database Schema

**inv_batches**
- Batch metadata and configuration
- Template and event references
- Status tracking
- Denormalized statistics (qty_generated, qty_emailed, etc.)

**inv_invites**
- Individual invitation records
- Recipient information (JSON)
- Ticket and QR data
- Status and timestamp tracking
- Download URLs

**inv_logs**
- Comprehensive audit trail
- Action types: generate, render, email, download, open, void, resend, check_in, error
- Actor tracking
- Request context (IP, user agent)

### Services

**TicketIssueAdapter**
- Creates zero-value tickets
- Generates QR codes with checksums
- Validates tickets at check-in
- Voids tickets

**InviteBatchService**
- Batch CRUD operations
- Invitation generation
- CSV import with validation
- Statistics calculation

**InviteRenderService**
- PDF/PNG generation via Ticket Templates
- Watermark injection
- Bulk rendering
- Signed URL generation

**InviteEmailService**
- Email delivery with queueing
- Chunked sending
- Retry logic
- Delivery tracking

**InviteDownloadService**
- Signed URL generation
- PDF downloads
- ZIP creation for batches
- Download tracking

**InviteTrackingService**
- Status transitions
- Pixel tracking
- Check-in validation
- Void management

---

## Installation

### Prerequisites

- PHP 8.2+
- Laravel 12+
- Ticket Templates microservice
- Queue worker configured
- Mail driver configured
- ZIP extension enabled

### Setup Steps

```bash
# 1. Run migrations
php artisan migrate

# 2. Configure mail driver (.env)
MAIL_MAILER=smtp
MAIL_HOST=smtp.mailtrap.io
MAIL_PORT=2525
MAIL_USERNAME=your_username
MAIL_PASSWORD=your_password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@example.com
MAIL_FROM_NAME="${APP_NAME}"

# 3. Configure queue driver (.env)
QUEUE_CONNECTION=redis

# 4. Start queue worker
php artisan queue:work

# 5. Link storage
php artisan storage:link

# 6. Seed microservice
php artisan db:seed --class=InvitationsMicroserviceSeeder
```

---

## Usage Guide

### Creating a Batch

**API Endpoint:** `POST /api/inv/batch`

```json
{
  "tenant_id": "tenant-123",
  "event_ref": "event-456",
  "name": "VIP Guests - Opening Night",
  "qty_planned": 50,
  "template_id": "template-789",
  "options": {
    "watermark": "VIP INVITATION",
    "seat_mode": "manual",
    "notes": "Special guests for opening night"
  }
}
```

**Response:**
```json
{
  "success": true,
  "batch": {
    "id": "batch-123",
    "name": "VIP Guests - Opening Night",
    "status": "draft",
    "qty_planned": 50,
    "qty_generated": 50
  },
  "stats": {
    "qty_generated": 50,
    "qty_rendered": 0,
    "qty_emailed": 0
  }
}
```

### Importing Recipients

**API Endpoint:** `POST /api/inv/batch/import`

```bash
curl -X POST /api/inv/batch/import \
  -F "batch_id=batch-123" \
  -F "csv_file=@recipients.csv" \
  -F "mapping[name]=0" \
  -F "mapping[email]=1" \
  -F "mapping[phone]=2"
```

**Response:**
```json
{
  "success": true,
  "imported": 48,
  "errors": [
    "Row 15: Invalid email address",
    "Row 32: Invalid email address"
  ]
}
```

### Rendering PDFs

**API Endpoint:** `POST /api/inv/batch/render`

```json
{
  "batch_id": "batch-123"
}
```

**Response:**
```json
{
  "success": true,
  "rendered": 48,
  "batch": {
    "status": "ready",
    "qty_rendered": 48
  }
}
```

### Sending Emails

**API Endpoint:** `POST /api/inv/send`

**Batch Mode:**
```json
{
  "batch_id": "batch-123",
  "mode": "email",
  "chunk_size": 100
}
```

**Individual Mode:**
```json
{
  "invite_ids": ["invite-1", "invite-2", "invite-3"],
  "mode": "email"
}
```

**Response:**
```json
{
  "success": true,
  "queued": 48,
  "failed": 0
}
```

### Downloading Invitations

**Individual PDF:**
```
GET /api/inv/{id}/download?signature={token}&code={invite_code}
```

**Batch ZIP:**
```
GET /api/inv/batch/{id}/download-zip
```

### Exporting Report

**API Endpoint:** `GET /api/inv/batch/{id}/export`

Returns CSV file with columns:
- Invite Code
- Recipient Name
- Email
- Phone
- Company
- Seat
- Status
- Email Status
- Download Status
- Check-in Status
- Gate
- Timestamps (Rendered, Emailed, Downloaded, Opened, Checked In)

---

## API Reference

### Batch Endpoints

#### POST /api/inv/batch
Create a new invitation batch.

**Request:**
```json
{
  "tenant_id": "uuid",
  "event_ref": "string",
  "name": "string",
  "qty_planned": number,
  "template_id": "uuid",
  "options": {
    "watermark": "string",
    "seat_mode": "auto|manual|none"
  }
}
```

#### POST /api/inv/batch/import
Import recipients from CSV.

**Request:** Multipart form data
- `batch_id`: UUID
- `csv_file`: File (CSV)
- `mapping`: Array (optional)

#### POST /api/inv/batch/render
Render PDFs for entire batch.

**Request:**
```json
{
  "batch_id": "uuid"
}
```

#### GET /api/inv/batch/{id}/export
Export batch data as CSV.

#### GET /api/inv/batch/{id}/download-zip
Download all batch invitations as ZIP.

### Email Endpoints

#### POST /api/inv/send
Send invitation emails.

**Request:**
```json
{
  "batch_id": "uuid",  // OR invite_ids
  "invite_ids": ["uuid"],
  "mode": "email|link_only",
  "chunk_size": number
}
```

### Invitation Endpoints

#### GET /api/inv/{id}
Get invitation details and tracking.

**Response:**
```json
{
  "success": true,
  "invite": { ... },
  "tracking": {
    "status": "downloaded",
    "emailed": true,
    "downloaded": true,
    "opened": false,
    "checked_in": false
  }
}
```

#### POST /api/inv/{id}/void
Void an invitation.

**Request:**
```json
{
  "reason": "Recipient cancelled"
}
```

#### POST /api/inv/{id}/resend
Resend invitation email.

#### GET /api/inv/{id}/download
Download PDF (requires valid signature).

### Webhook Endpoints

#### POST /api/inv/webhook/open
Track email open via pixel.

**Query:** `?code={invite_code}`

Returns 1x1 transparent GIF.

---

## CSV Import Format

### Standard Format

```csv
name,email,phone,company,seat_ref
John Doe,john@example.com,+1234567890,Acme Corp,A-12
Jane Smith,jane@example.com,+0987654321,TechCo,B-5
```

### Field Mapping

Default mapping:
- Column 0: name
- Column 1: email
- Column 2: phone
- Column 3: company
- Column 4: seat_ref

Custom mapping:
```json
{
  "mapping": {
    "name": 1,
    "email": 0,
    "phone": 3,
    "company": 2,
    "seat_ref": 4
  }
}
```

### Validation Rules

- **Email**: Required, must be valid format
- **Name**: Optional
- **Phone**: Optional
- **Company**: Optional
- **Seat**: Optional, must exist if seat_mode is not "none"

### Error Handling

- Invalid rows are skipped
- Errors are returned in response
- Valid rows are imported
- Import continues after errors

---

## Status Flow

### Invitation Status Flow

```
created
   ↓
rendered
   ↓
emailed
   ↓
downloaded
   ↓
opened
   ↓
checked_in

At any point → void
```

### Batch Status Flow

```
draft
   ↓
rendering
   ↓
ready
   ↓
sending
   ↓
completed

At any point → cancelled
```

### Delivery Status

- `pending`: Email queued but not sent
- `sent`: Email sent to SMTP server
- `delivered`: Email delivered to recipient
- `bounced`: Email bounced
- `failed`: Send failed
- `complaint`: Spam complaint

---

## Security

### Download URLs

- Signed URLs with expiration (30 days default)
- Requires valid signature and invite code
- Rate limiting per IP
- Anti-enumeration protection

### Email Security

- PII minimization
- No large attachments (uses download links)
- Secure download links in emails
- SPF/DKIM/DMARC support

### Pixel Tracking

- Only if consent given
- Respects GDPR
- No PII in pixel URL
- Returns 1x1 transparent GIF

### QR Codes

- Format: `INV:{code}:{ticket_ref}:{checksum}`
- HMAC-SHA256 checksum
- Anti-replay protection
- Secret key from `config('app.key')`

### API Security

- Rate limiting: 60 requests/minute
- Tenant isolation
- Authentication required (except public download/pixel)
- Signed URLs for downloads

---

## Troubleshooting

### Issue: Emails not sending

**Symptoms:** Invitations stuck in "rendered" status

**Solutions:**
1. Check queue worker is running: `php artisan queue:work`
2. Check mail configuration in `.env`
3. Test mail connection: `php artisan tinker` → `Mail::raw('Test', fn($msg) => $msg->to('test@example.com'))`
4. Check logs: `storage/logs/laravel.log`
5. Verify queue driver is configured

### Issue: PDFs not generating

**Symptoms:** Render fails or batch stuck in "rendering"

**Solutions:**
1. Ensure Ticket Templates microservice is active
2. Check template exists and is active
3. Verify storage permissions: `storage/app/public` writable
4. Check logs for specific errors
5. Test template rendering separately

### Issue: Downloads failing

**Symptoms:** 403/404 errors on download

**Solutions:**
1. Verify signed URL hasn't expired
2. Check `APP_URL` matches current domain
3. Ensure storage is linked: `php artisan storage:link`
4. Verify file exists in storage
5. Check invite hasn't been voided

### Issue: Check-in failing

**Symptoms:** Scanner rejects invitations

**Solutions:**
1. Verify QR format: must start with `INV:`
2. Check invite hasn't been voided
3. Verify checksum validation
4. Check `APP_KEY` hasn't changed (regenerating key breaks old QR codes)
5. Test QR validation: `POST /api/inv/check` with QR data

### Issue: CSV import errors

**Symptoms:** Import fails or skips rows

**Solutions:**
1. Verify CSV encoding (UTF-8 recommended)
2. Check email format validation
3. Ensure column mapping is correct
4. Review error messages in response
5. Test with small sample first

---

## Best Practices

### Batch Management

- Use descriptive batch names
- Plan quantity accurately
- Test with small batch first
- Monitor rendering progress
- Cancel unused batches

### Email Delivery

- Send in off-peak hours
- Use appropriate chunk size (100-200)
- Monitor delivery rates
- Resend failures manually
- Warm up new sending domains

### Recipient Data

- Validate emails before import
- Include name for personalization
- Add company for reporting
- Use seat assignment when applicable
- Clean data before import

### Template Design

- Use clear watermarks
- Include essential info only
- Test rendering before batch
- Consider print quality
- Use readable fonts (>= 10pt)

### Security

- Rotate signed URL expiration as needed
- Monitor download patterns
- Void unused invitations
- Regular audit log review
- Implement rate limiting

---

## Integration Examples

### With Event Management

```php
// Create invitations when event is published
$batch = $batchService->createBatch([
    'tenant_id' => $event->tenant_id,
    'event_ref' => $event->id,
    'name' => "Invitations - {$event->name}",
    'qty_planned' => $event->vip_quota,
    'template_id' => $event->invitation_template_id,
]);
```

### With CRM

```php
// Import VIP contacts from CRM
$csvPath = $crm->exportContacts('vip');
$result = $batchService->importRecipients($batch, $csvPath);
```

### With Check-in Scanner

```php
// Validate invitation at gate
$result = $trackingService->trackCheckIn($qrData, $gateRef);

if ($result['success']) {
    echo "Welcome, {$result['recipient']}!";
} else {
    echo "Error: {$result['message']}";
}
```

---

## Performance Optimization

### Database Indexes

All critical fields are indexed:
- `invite_code` (unique)
- `tenant_id`, `status`
- `emailed_at`, `downloaded_at`, `checked_in_at`

### Caching

Consider caching:
- Batch statistics
- Tenant settings
- Template data

### Queue Optimization

- Use Redis for queue
- Run multiple workers
- Set appropriate retry attempts
- Monitor failed jobs

---

## Support & Contact

**Documentation:** `/docs/microservices/invitations`
**Email:** support@epas.ro
**GitHub Issues:** https://github.com/epas/issues

**Version:** 1.0.0
**Last Updated:** November 16, 2025
**Author:** EPAS Development Team

---

## License

Proprietary - Part of EPAS Ticketing System
© 2025 EPAS. All rights reserved.
